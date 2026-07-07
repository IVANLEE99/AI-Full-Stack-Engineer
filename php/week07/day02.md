# Week 07 Day 02：PayService 与 processPayment Node 链

> 所属周：Week 07：支付域 + Node 流水线  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`pay-service`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

掌握支付核心 `PayService::processPayment` 的 Node 流水线思想，理解支付处理为什么要拆成多个 Node，以及每个 Node 如何通过 Context 共享数据、成功继续、失败中断。

今天你要真正掌握这一句话：

> 支付流程不是一个超长函数，而是一条责任链：每个 Node 只负责一个步骤，例如校验订单、创建支付单、选择渠道、调用 SDK、更新状态，任何关键步骤失败都应该中断并返回明确错误。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Week 06 下单后进入支付域的衔接点
2. 理解为什么支付流程适合拆成 Node 链
3. 阅读责任链模式的基本思想
4. 打开 `PayService.php`，找到 `processPayment`
5. 识别 NodeExecutionEngine 或类似执行器
6. 列出至少 4 个 Node 及职责
7. 画 Node 顺序图
8. 理解 Context 在 Node 间传递什么数据
9. 用 AI Review 检查失败中断和状态更新是否理解正确

---

## 1. 学习内容

### 1.1 支付流程为什么复杂？

用户点击支付后，后端不是简单调用第三方接口。

它可能要做：

- 校验订单是否存在
- 校验订单是否属于当前用户
- 校验订单是否待支付
- 校验金额是否一致
- 创建支付单
- 选择支付渠道
- 调用第三方 SDK
- 保存第三方交易号
- 返回前端支付参数
- 等待支付回调

如果这些都写在一个方法里，会变成几百行大函数。

Node 链就是为了解决这个问题。

---

### 1.2 什么是责任链 / Node 链？

责任链可以理解为：

```text
把一个大流程拆成多个小节点，每个节点处理完再交给下一个节点。
```

例如支付链：

```text
ValidateOrderNode
  ↓
CreatePaymentNode
  ↓
SelectChannelNode
  ↓
ProcessPaymentNode
  ↓
UpdatePaymentStatusNode
```

每个 Node 只做一件事。

好处：

| 好处 | 说明 |
|---|---|
| 易读 | 每个节点职责清晰 |
| 易测 | 可以单独测试某个 Node |
| 易扩展 | 新增风控/优惠/渠道节点更方便 |
| 易中断 | 某个节点失败即可停止流程 |
| 易记录 | 每个节点可记录日志和耗时 |

---

### 1.3 `processPayment` 怎么读？

读 `processPayment` 时先不要陷入细节，先找：

| 阅读点 | 你要找什么 |
|---|---|
| 输入参数 | order_id、amount、channel、user_id 等 |
| Context | 是否创建支付上下文对象/数组 |
| Node 列表 | 有哪些支付节点 |
| 执行器 | 是否有 NodeExecutionEngine |
| 失败处理 | 节点失败如何返回 |
| 成功结果 | 返回给前端什么数据 |

伪代码：

```php
<?php

public function processPayment(array $params): array
{
    $context = new PaymentContext($params);

    $nodes = [
        new ValidateOrderNode(),
        new CreatePaymentNode(),
        new SelectChannelNode(),
        new ProcessPaymentNode(),
    ];

    return $this->nodeExecutionEngine->execute($nodes, $context);
}
```

---

### 1.4 Context 是什么？

Context 是 Node 之间共享的数据容器。

它可能包含：

| 字段 | 含义 |
|---|---|
| `order_id` | 订单 ID |
| `user_id` | 用户 ID |
| `amount` | 支付金额 |
| `currency` | 币种 |
| `channel` | 支付渠道 |
| `payment_id` | 支付单 ID |
| `third_transaction_id` | 第三方交易号 |
| `client_secret` | 前端继续支付需要的参数 |

Node A 写入，Node B 读取。

例如：

```text
CreatePaymentNode 写入 payment_id
ProcessPaymentNode 读取 payment_id 并调用第三方 SDK
```

---

### 1.5 Node 失败时如何中断？

支付流程中任何关键节点失败，都不能继续。

例如：订单金额不一致：

```text
ValidateOrderNode 失败 → 不创建支付单 → 不调用第三方支付
```

伪代码：

```php
<?php

foreach ($nodes as $node) {
    $result = $node->handle($context);

    if ($result->isFail()) {
        return [
            'code' => $result->code,
            'data' => null,
            'info' => $result->message,
        ];
    }
}
```

小白重点：支付链路失败要“明确中断”，不能带着错误状态继续往后走。

---

### 1.6 支付 Node 常见职责

| Node | 职责 | 失败示例 |
|---|---|---|
| ValidateOrderNode | 校验订单状态、归属、金额 | 订单不存在、金额不一致 |
| CreatePaymentNode | 创建支付单 | 支付单创建失败 |
| SelectChannelNode | 选择 Stripe/Braintree 等渠道 | 渠道未开启 |
| ProcessPaymentNode | 调用第三方 SDK 创建/确认支付 | 第三方返回失败 |
| UpdatePaymentNode | 保存第三方交易号或状态 | DB 更新失败 |
| BuildResponseNode | 生成前端需要的支付参数 | 参数缺失 |

实际 Node 名称以源码为准。

---

### 1.7 Node 链与订单状态的关系

支付链处理成功后，订单通常不会立刻变成“已完成”。

常见情况：

```text
创建支付请求成功 → 支付单 processing / pending
第三方回调成功 → 支付单 success → 订单已支付/待发货
```

所以要区分：

| 事件 | 含义 |
|---|---|
| 创建支付参数成功 | 前端可以继续支付 |
| 支付平台确认成功 | 钱真的支付成功 |
| 回调处理成功 | 后端确认并更新订单状态 |

不要把“创建支付单成功”等同于“订单已支付”。

---

### 1.8 Express middleware 类比

Node/Express middleware：

```js
app.use(validateOrder);
app.use(createPayment);
app.use(processPayment);
app.use(buildResponse);
```

每个 middleware 都能读写 `req.context`，失败时 `next(error)` 中断。

PHP Node 链类似：

```text
Node 读取/写入 Context
成功 → 下一个 Node
失败 → 中断返回
```

---

## 2. 源码阅读

- `pay-service/common/services/pay/PayService.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| 阅读点 | 记录 |
|---|---|
| `processPayment` 输入 |  |
| Context 类型/结构 |  |
| Node 列表 |  |
| 执行器 |  |
| 失败中断逻辑 |  |
| 成功返回数据 |  |

---

## 3. 练习任务

### 练习 1：读 `PayService::processPayment`

记录：

```text
方法名：
输入参数：
创建的 Context：
Node 顺序：
返回格式：
失败时如何处理：
```

### 练习 2：列 4 个 Node 及职责

| Node | 输入 | 输出 | 职责 | 失败时后果 |
|---|---|---|---|---|
|  |  |  |  |  |

### 练习 3：画 Node 顺序图

```text
PayService::processPayment
  ↓
NodeExecutionEngine
  ↓
ValidateOrderNode
  ↓
CreatePaymentNode
  ↓
ProcessPaymentNode
  ↓
BuildResponseNode
```

按源码实际顺序修正。

---

## 4. JS/Node.js 类比

- Node 链 ≈ Express middleware 管道
- Context ≈ `req.context` 共享状态
- NodeExecutionEngine ≈ middleware runner / pipeline executor
- Node 失败中断 ≈ `next(error)` 或 throw error
- `processPayment` ≈ payment use case orchestration

---

## 5. AI Review 提问

```text
我正在阅读 PayService::processPayment 和支付 Node 链。
我已经列出 Node 顺序、Context 字段和失败中断规则。
请你检查：
1. 我对 Node 链的理解是否正确？
2. 每个 Node 职责是否划分合理？
3. Node 失败时应该如何中断和回滚？
4. 创建支付成功和支付真正成功是否区分清楚？
5. 与 Express middleware 管道的类比是否准确？
```

---

## 6. 今日产出

- [ ] `PayService::processPayment` 阅读笔记
- [ ] 4+ 个 Node 职责表
- [ ] Node 顺序图
- [ ] Context 字段表
- [ ] 支付创建成功 vs 支付成功区别笔记
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能列出至少 4 个支付 Node
- [ ] 能说明 Context 的作用
- [ ] 能解释 Node 失败时如何中断
- [ ] 能区分创建支付参数成功和支付真正成功
- [ ] 能用 Express middleware 类比 Node 链

---

## 8. 今日自测题

### 8.1 为什么支付流程适合拆成 Node 链，而不是写成一个大函数？

参考答案：

> ✅ 因为支付要做校验订单、创建支付单、选渠道、调 SDK、更新状态等很多步骤。拆成 Node 链后每个节点只做一件事，代码更易读、易测、易扩展，也方便某步失败时中断。

---

### 8.2 Context 在 Node 链里起什么作用？

参考答案：

> ✅ Context 是 Node 之间共享的数据容器。前一个 Node 写入数据（如 payment_id），后一个 Node 读取使用，从而把一次支付流程的数据在各节点间传递。

---

### 8.3 某个关键 Node 失败时应该怎么处理？

参考答案：

> ✅ 应该明确中断整条链路并返回明确错误，不能带着错误状态继续往后走。例如订单金额校验失败，就不应再创建支付单和调用第三方支付。

---

### 8.4 “创建支付单成功”是否等于“订单已支付”？

参考答案：

> ✅ 不等于。创建支付参数成功只代表前端可以继续支付；只有第三方回调确认成功、后端处理成功后，支付单才算成功，订单才更新为已支付。

---

### 8.5 支付 Node 链可以类比 Node.js 里的什么？

参考答案：

> ✅ 可以类比 Express 的 middleware 管道。每个 middleware 读写 `req.context`，成功调用 `next()` 继续，失败用 `next(error)` 中断，和 Node 链读写 Context、成功继续、失败中断的思路一致。

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
我正在进行 Week 07 Day 02：PayService 与 processPayment Node 链 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 07 README](./README.md)
