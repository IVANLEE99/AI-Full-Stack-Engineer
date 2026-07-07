# Week 16 Day 02：PHP Node 链复习

> 所属周：Week 16：编排模式对比  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`pay-service + ai-lab`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

重读支付服务里的 NodeExecutionEngine（Node 执行引擎），彻底搞清楚「一份 Context 是怎么顺着一串 Node 往下传、被逐步加工」的。

今天你要真正掌握这一句话：

> PHP 的 NodeExecutionEngine 就是一个「链式编排引擎」：它拿着一份 Context，按顺序把每个 Node 跑一遍，每个 Node 读 Context、改 Context、再交给下一个。这跟昨天 LangGraph 的 `graph.invoke(state)` 是同一件事，只是 LangGraph 是「图」，这里是「一条直链」。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回忆昨天的 LangGraph：State / Node / Edge
2. 建立映射预期：PHP 里对应的东西叫什么
3. 打开 PayService，找到「Node 列表」在哪定义
4. 找到「执行引擎」在哪循环跑 Node
5. 找到 Context 是什么结构、初始有哪些字段
6. 逐个 Node 看：它读了什么、写了什么
7. 画出支付 Node 链的顺序图
8. 列一张完整的 Context 字段演变表
9. 对照 LangGraph 写异同笔记
10. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 先建立「找什么」的预期

昨天学了 LangGraph 的三大件。今天读 PHP 源码，其实就是去找这三样东西的 PHP 版本：

| LangGraph 概念 | 今天要在 PHP 里找的东西 |
|---|---|
| State | Context（通常是一个 array 或一个 Context 对象） |
| Node | 一个个 Node 类（每个有 `handle` 或 `execute` 方法） |
| Edge / 执行顺序 | Node 列表的顺序 + 引擎里的循环 |
| `graph.invoke()` | 引擎的 `run()` / `execute()` 方法 |

小白重点：

> 读陌生源码别一行行啃。带着「我要找 Context、Node、引擎循环」这三个目标去扫，找到锚点再展开。

---

### 1.2 责任链模式：PHP Node 链的底层套路

PHP 的 Node 链背后是一个经典设计模式：**责任链（Chain of Responsibility）**。

它的核心结构长这样：

```php
<?php

declare(strict_types=1);

// 每个 Node 都实现同一个接口
interface NodeInterface
{
    // 读 context，处理，返回处理后的 context
    public function handle(array $context): array;
}
```

一个具体 Node：

```php
<?php

declare(strict_types=1);

class ValidateOrderNode implements NodeInterface
{
    public function handle(array $context): array
    {
        // 读
        $orderId = $context['order_id'];

        // 处理（这里只是示意）
        if ($orderId <= 0) {
            throw new \RuntimeException('订单号非法');
        }

        // 写
        $context['validated'] = true;

        // 返回给引擎，由引擎决定下一个
        return $context;
    }
}
```

小白重点：

> 有的项目里 Node 自己调 `$next($context)` 往下传（像 Express middleware）；有的项目里 Node 只 `return $context`，由引擎负责调下一个。今天读源码时**先确认你们项目是哪种**。下面的引擎示例用后者（引擎驱动循环），更接近 LangGraph 的模型。

---

### 1.3 NodeExecutionEngine：驱动整条链的引擎

引擎的核心就是一个循环，把 Node 列表按顺序跑一遍：

```php
<?php

declare(strict_types=1);

class NodeExecutionEngine
{
    /** @var NodeInterface[] */
    private array $nodes;

    /**
     * @param NodeInterface[] $nodes 按执行顺序排列的 Node 列表
     */
    public function __construct(array $nodes)
    {
        $this->nodes = $nodes;
    }

    /**
     * 拿着初始 context，顺序跑完所有 node
     */
    public function run(array $context): array
    {
        foreach ($this->nodes as $node) {
            // 每个 node 读旧 context、返回新 context
            $context = $node->handle($context);

            // 有的引擎会在这里检查是否要中断
            if (!empty($context['_stop'])) {
                break;
            }
        }
        return $context;
    }
}
```

使用：

```php
<?php

$engine = new NodeExecutionEngine([
    new ValidateOrderNode(),
    new LockStockNode(),
    new CallGatewayNode(),
    new UpdateOrderNode(),
    new SendNotifyNode(),
]);

$result = $engine->run([
    'order_id' => 1001,
    'amount'   => 99.00,
]);
```

对照昨天的 LangGraph：

```python
# LangGraph
result = graph.invoke({"order_id": 1001, "amount": 99.00})
```

| 对比项 | PHP 引擎 | LangGraph |
|---|---|---|
| 初始数据 | `run($context)` 的入参 | `invoke(input)` 的入参 |
| 步骤集合 | 构造函数传入的 Node 列表 | `add_node` 登记的节点 |
| 执行顺序 | 列表的先后顺序 | Edge 连线决定 |
| 启动 | `run()` | `invoke()` |
| 结果 | 最终 `$context` | 最终 State |

小白重点：

> 这个 `foreach` 循环就是「链式编排」的心脏。它没有分支、没有回头——纯粹按列表顺序往下跑。这正是「链是图的特例」的直接体现。

---

### 1.4 Context：贯穿全程的数据容器

Context 是这条链的 State。它通常在链的开头初始化，然后被每个 Node 逐步填充。

一个支付流程的 Context 演变可能是这样：

```php
// 初始（进入引擎前）
$context = [
    'order_id' => 1001,
    'amount'   => 99.00,
    'user_id'  => 88,
];

// ValidateOrderNode 之后
$context['validated'] = true;

// LockStockNode 之后
$context['stock_locked'] = true;
$context['lock_id'] = 'LK-xxx';

// CallGatewayNode 之后
$context['pay_status'] = 'success';
$context['trade_no'] = 'TN-xxx';

// UpdateOrderNode 之后
$context['order_status'] = 'paid';

// SendNotifyNode 之后
$context['notified'] = true;
```

小白重点：

> Context 像滚雪球一样越来越大。**每个 Node 只负责往里加自己那几个字段**，这就是「单一职责」。读源码时，重点搞清楚每个 Node「读哪几个字段、写哪几个字段」。

---

### 1.5 读 PayService 的实战步骤

现在打开真实文件：

- `pay-service/common/services/pay/PayService.php`

按下面顺序找（每找到一处，在笔记里记下行号或方法名）：

**第 1 步：找 Node 列表**

搜索关键词：`Node`、`nodes`、`new XxxNode`、数组里一串 `new`。

你要找到类似这样的地方：

```php
$nodes = [
    new XxxValidateNode(),
    new XxxLockNode(),
    new XxxGatewayNode(),
    // ...
];
```

这就是「执行顺序」，等价于 LangGraph 的边。

**第 2 步：找引擎 / 循环**

搜索：`foreach`、`->handle(`、`->execute(`、`->run(`、`Engine`。

确认它是「引擎驱动循环」还是「Node 自己调 next」。

**第 3 步：找 Context 初始化**

搜索：`$context = [`、`Context`、传给第一个 Node 的那份数据。

记下初始字段有哪些。

**第 4 步：逐个 Node 追字段**

对每个 Node，问三个问题：

1. 它从 Context 读了什么字段？
2. 它往 Context 写了什么字段？
3. 它在什么情况下会中断 / 抛异常？

小白重点：

> 如果你们项目的 Node 类不在 PayService 同一个文件里，`PayService.php` 里往往只有「组装 + 启动」，具体 Node 在别的目录（比如 `pay-service/common/services/pay/nodes/`）。顺着 `new XxxNode()` 的类名去搜文件即可。

---

### 1.6 一个典型支付 Node 链（示例结构）

真实项目字段会更多，但骨架大同小异。下面是一个脱敏示例，帮你对号入座：

```text
START
  │
  ▼
[1] 校验订单 ValidateOrder      读: order_id      写: validated
  │
  ▼
[2] 锁定库存 LockStock          读: order_id      写: stock_locked, lock_id
  │
  ▼
[3] 调用渠道 CallGateway        读: amount        写: pay_status, trade_no
  │
  ▼
[4] 更新订单 UpdateOrder        读: pay_status    写: order_status
  │
  ▼
[5] 发送通知 SendNotify         读: order_status  写: notified
  │
  ▼
END → 返回 $context
```

对照 LangGraph，这就是一条「只有普通边、没有条件边」的直链图。

---

### 1.7 链式编排的优点和坑

读完源码，你应该能体会到链式编排的特点：

**优点：**

- 结构极简单，一个 `foreach` 就能理解
- 顺序明确，调试时容易定位「跑到第几个 Node 崩的」
- 适合「步骤固定、很少分支」的业务（支付主流程就是这样）

**坑（也是明天对比的重点）：**

| 坑 | 说明 |
|---|---|
| 分支难表达 | 要分支只能在 Node 内部写 if，流程图看不出来 |
| 不好回头 | 想「失败后重试上一步」很别扭 |
| Context 越滚越大 | 后期字段几十个，容易变成「大杂烩」 |
| 隐式依赖 | Node B 依赖 Node A 写的字段，但代码上看不出来 |

小白重点：

> 这些「坑」不是说链式不好，而是它的**适用边界**。支付这种固定流程用链式非常合适；而 AI Agent 那种「要反复决策、可能循环」的流程，图式（LangGraph）就更合适。

---

## 2. 源码阅读

- `pay-service/common/services/pay/PayService.php`
- （如果 Node 类分开放）`pay-service/common/services/pay/nodes/`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. Node 列表在哪定义，顺序是什么
2. 引擎循环在哪，是「引擎驱动」还是「Node 调 next」
3. Context 初始有哪些字段
4. 每个 Node 读什么、写什么
5. 哪里会中断 / 抛异常 / 回滚

建议在笔记里写出类似表格：

| Node 名 | 读取字段 | 写入字段 | 可能中断 |
|---|---|---|---|
| ValidateOrder | order_id | validated | 订单非法时抛异常 |
| LockStock | order_id | stock_locked, lock_id | 库存不足 |
| CallGateway | amount | pay_status, trade_no | 渠道超时 |
| ... | ... | ... | ... |

---

## 3. 练习任务

### 练习 1：定位三大件

在 `PayService.php` 里，用注释或笔记标出：

- Node 列表在第几行
- 引擎循环在第几行
- Context 初始化在第几行

目标：能一眼指出「这条链的 State / Node / Edge 分别在哪」。

---

### 练习 2：列 Context 字段演变表

完整填一张表（从初始到结束）：

| 阶段 | 新增/变化的字段 | 当前 Context 字段全集 |
|---|---|---|
| 初始 | order_id, amount, user_id | order_id, amount, user_id |
| Node1 后 | validated | + validated |
| Node2 后 | stock_locked, lock_id | + stock_locked, lock_id |
| ... | ... | ... |

目标：亲眼确认 Context 是逐步累积的。

---

### 练习 3：画支付 Node 链图

用 ASCII 或纸笔，画出真实 PayService 的 Node 顺序图，标注每个 Node 读写的关键字段（参考 1.6）。

目标：把源码「翻译」成一张流程图。

---

### 练习 4：找一处分支

在源码里找一个 Node 内部的 `if` 分支（比如「VIP 走不同逻辑」「金额为 0 跳过支付」）。

用一句话描述：如果用 LangGraph 的条件边来表达，这个分支会画成什么样？

目标：为明天的对比预热。

---

### 练习 5：写「引擎最小复刻」

不看源码，凭理解自己写一个最小的 `NodeExecutionEngine`（参考 1.3），用 2 个假 Node 跑通。

目标：从「能读懂」升级到「能写出来」。

---

## 4. JS/Node.js 类比

### 4.1 PHP Node 链 ≈ Express/Koa middleware

Express 的中间件就是同步的责任链：

```js
// Express：req 顺着中间件往下传
app.use((req, res, next) => {
  req.validated = true;
  next(); // 交给下一个
});

app.use((req, res, next) => {
  req.stockLocked = true;
  next();
});
```

对照 PHP：

```php
// PHP：$context 顺着 Node 往下传
$context['validated'] = true;      // 相当于第一个中间件
$context['stock_locked'] = true;   // 相当于第二个中间件
```

| Express middleware | PHP Node 链 |
|---|---|
| `req` | `$context` |
| 一个 middleware 函数 | 一个 Node 类 |
| `next()` | 引擎循环 / `$next()` |
| `app.use` 的注册顺序 | Node 列表顺序 |
| 抛错进 error middleware | 抛异常被引擎捕获 |

### 4.2 与昨天 LangGraph 的关系

```text
LangGraph（图）  ─── 特殊化 ──→  一条直链  ─── 就等于 ──→  PHP Node 链
```

三者共享同一个骨架：**共享数据 + 一串处理步骤 + 一个驱动执行的东西**。

### 4.3 本周类比打卡

```text
本周概念：PHP NodeExecutionEngine（链式编排）
Node 等价：Express/Koa middleware 管道
差异：PHP 引擎驱动循环、Context 是 array；middleware 靠 next() 手动往下传
我能用自己的话解释吗：是 / 否
理解自评：1 / 2 / 3 / 4 / 5
```

---

## 5. AI Review 提问

```text
我正在学习 Week 16 Day 02：PHP NodeExecutionEngine 源码阅读。

请你按资深 PHP 工程师标准帮我检查：

1. 我对「责任链模式 + 引擎驱动」的理解是否正确？
2. 我列的 Context 字段演变表是否合理？有没有漏掉的中断/回滚字段？
3. 我画的 Node 链图顺序对不对？
4. 我用 Express middleware 做的类比准不准？
5. 对比 LangGraph，我说「链是图的特例」这个理解对吗？
6. 真实支付项目里，这条链还应该关注什么（幂等、事务、超时、回滚）？

请用中文输出：
- 我理解正确的地方
- 我理解错误或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

- [ ] PayService 三大件定位笔记（行号/方法名）
- [ ] Context 字段演变表
- [ ] 支付 Node 链流程图
- [ ] 一处分支的「LangGraph 化」描述
- [ ] 自己复刻的最小 NodeExecutionEngine
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能指出 PayService 里 Node 列表、引擎循环、Context 初始化的位置
- [ ] 能说清楚每个 Node 读什么、写什么
- [ ] 能完整列出 Context 字段的演变
- [ ] 能画出支付 Node 链的顺序图
- [ ] 能解释「引擎驱动」和「Node 调 next」两种写法的区别
- [ ] 能说出 PHP Node 链 ↔ LangGraph 直链的对应关系
- [ ] 能说出链式编排的至少 2 个「坑」

---

## 8. 今日自测题

### 8.1 NodeExecutionEngine 的核心是什么？

参考答案：

> ✅ 核心是一个循环（`foreach`），拿着一份 Context，按 Node 列表的顺序依次调用每个 Node 的 `handle`，把返回的 Context 传给下一个，直到跑完或中断。

---

### 8.2 Context 在这条链里扮演什么角色？

参考答案：

> ✅ Context 是整条链共享、逐步累积的数据容器，等价于 LangGraph 的 State。每个 Node 读它、往里写自己的字段，最后引擎把它作为结果返回。

---

### 8.3 「引擎驱动循环」和「Node 自己调 next」有什么区别？

参考答案：

> ✅ 引擎驱动：Node 只 `return $context`，由引擎的循环决定下一个（更接近 LangGraph）。Node 调 next：每个 Node 内部调 `$next($context)` 主动往下传（更像 Express middleware）。两者都能实现链式编排。

---

### 8.4 链式编排在表达「分支」上有什么局限？

参考答案：

> ✅ 分支只能写在 Node 内部的 if 里，流程图上看不出来，改流程要改节点代码；而图式编排（LangGraph）用条件边把分支显式画出来。

---

### 8.5 为什么说「链是图的特例」？

参考答案：

> ✅ 因为链就是一张每个节点只有一条出边、指向下一个节点、没有分叉也没有回头的有向图。NodeExecutionEngine 的顺序 `foreach` 正是这种最简单的图执行。

---

## 9. 学习记录

| 记录项 | 内容 |
|--------|------|
| 今日最清楚的概念 |  |
| 今日最卡的概念 |  |
| JS/Node 类比是否帮助理解 |  |
| 实际耗时 |  |
| 明日要补的问题 |  |

---

## 10. AI Review 提示词

```text
我正在进行 Week 16 Day 02：PHP Node 链复习 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 16 README](./README.md)
