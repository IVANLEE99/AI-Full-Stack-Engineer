# Week 04 Day 05：阶段总结与类比日

> 所属周：Week 04：配置中心 + 站点 API  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-core`  
> 类型：类比日  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

整理 Week 01 到 Week 04 的学习笔记，独立读通一条 CSR 链路，并用 JS/Node.js 的分层思维类比 PHP 企业项目中的 Controller、Service、Repository、Model。

今天你要真正掌握这一句话：

> CSR 链路不是几个目录名的组合，而是一条请求从 Controller 进入、由 Service 组织业务、通过 Repository/Model 读取数据、最后返回响应的后端工程主线。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Week 01：PHP 语法、Composer、OOP、namespace
2. 回顾 Week 02：Yii2 入口、Controller、路由、Filter
3. 回顾 Week 03：Service、Repository、Model、业务阅读
4. 回顾 Week 04：配置读取、配置 API、配置中心
5. 选择一条真实或模拟接口链路
6. 按 Controller → Service → Repository → Model 追踪
7. 用 Node/Express 分层方式做类比
8. 整理配置项清单和阶段总结
9. 用 AI Review 检查是否可以进入下一阶段

---

## 1. 学习内容

### 1.1 为什么要做阶段总结？

学习后端很容易出现一种情况：

```text
每天都看了一些概念，但串不起来。
```

阶段总结的目的就是把零散知识串成一条线。

你前 4 周已经接触到：

| 周次 | 主题 | 你应该能掌握什么 |
|---|---|---|
| Week 01 | PHP 基础 | 变量、类型、函数、OOP、Composer |
| Week 02 | Yii2 基础 | 入口、路由、Controller、Filter |
| Week 03 | 项目分层 | Service、Repository、Model、业务链路 |
| Week 04 | 配置中心 | `g_config()`、配置 API、动态配置 |

今天要做的是：把这些知识合并成“我能读懂一个 PHP 接口”的能力。

---

### 1.2 CSR 是什么？

这里的 CSR 指：

```text
Controller → Service → Repository → Model
```

不要和前端里的 Client Side Rendering 混淆。

在后端项目里，它可以这样理解：

| 层 | 作用 | 类比 |
|---|---|---|
| Controller | 接收 HTTP 请求，返回响应 | Express route/controller |
| Service | 组织业务逻辑 | service class |
| Repository | 封装数据查询 | DAO / repository |
| Model | 表示数据表或领域对象 | ORM model |

请求链路：

```text
前端请求
  ↓
Controller
  ↓
Service
  ↓
Repository
  ↓
Model / DB
  ↓
返回数据
```

小白重点：读项目时，你不是在读“文件”，而是在追踪“请求怎么流动”。

---

### 1.3 Controller 负责什么？

Controller 是 HTTP 入口。

它通常负责：

- 接收请求参数
- 做基础参数校验
- 调用 Service
- 返回统一响应

示例：

```php
<?php

public function actionDetail(): array
{
    $orderId = (int)$this->request->get('order_id');

    $detail = $this->orderService->getDetail($orderId);

    return $this->success($detail);
}
```

Controller 不应该写太多复杂业务，例如：

- 订单金额计算
- 库存扣减
- 优惠券核销
- 大量 SQL 查询

这些应该下沉到 Service 或更底层。

---

### 1.4 Service 负责什么？

Service 是业务组织层。

它通常负责：

- 编排多个 Repository/Model
- 处理业务规则
- 组合返回数据
- 调用配置、缓存、第三方服务

示例：

```php
<?php

final class OrderService
{
    public function getDetail(int $orderId): array
    {
        $order = $this->orderRepository->findById($orderId);
        $items = $this->orderItemRepository->findByOrderId($orderId);

        return [
            'order' => $order,
            'items' => $items,
        ];
    }
}
```

小白重点：Service 是你理解业务的核心入口。

---

### 1.5 Repository 和 Model 负责什么？

Repository 更关注“怎么查数据”。

Model 更关注“数据结构或 ORM 映射”。

示例：

```php
<?php

final class OrderRepository
{
    public function findById(int $orderId): ?Order
    {
        return Order::findOne(['id' => $orderId]);
    }
}
```

这里：

- `OrderRepository` 封装查询方法
- `Order` 可能是 ActiveRecord Model
- Service 不需要知道复杂查询细节

分层的好处：

| 好处 | 说明 |
|---|---|
| 易读 | 每层职责清楚 |
| 易改 | 查询变化时主要改 Repository |
| 易测 | Service 可以 mock Repository |
| 易复用 | 多个 Service 可复用查询方法 |

---

### 1.6 如何独立读通一条 CSR 链路？

按这个步骤来：

1. 找接口 URL 或 Controller 方法
2. 看 Controller 接收哪些参数
3. 找 Controller 调用了哪个 Service
4. 跳到 Service 方法，记录业务步骤
5. 找 Service 调用了哪些 Repository/Model
6. 看 Repository 查询了哪些表或字段
7. 回到 Service，看返回结构
8. 回到 Controller，看响应格式

记录模板：

| 层级 | 文件/类/方法 | 作用 |
|---|---|---|
| Controller |  |  |
| Service |  |  |
| Repository |  |  |
| Model |  |  |
| Response |  |  |

---

### 1.7 把配置链路放进 CSR

Week 04 学的配置并不是孤立知识，它会出现在 Service 或 Controller 中。

例如：

```php
<?php

$showCoupon = g_config('site', 'show_coupon', false);
```

它可能影响 Service 返回：

```php
<?php

return [
    'show_coupon' => $showCoupon,
    'goods' => $goodsList,
];
```

所以完整链路可能是：

```text
Controller
  ↓
Service
  ↓     ↘
Repository   g_config()
  ↓          ↓
Model/DB     配置中心/缓存
  ↓          ↓
返回组合数据给前端
```

---

### 1.8 Node.js 类比

Node/Express 项目可能这样分层：

```text
router/controller → service → repository/dao → model/db
```

PHP 项目中的 CSR 类似：

```text
Controller → Service → Repository → Model
```

对比：

| PHP | Node.js |
|---|---|
| Controller | route handler / controller |
| Service | service class |
| Repository | dao / repository |
| Model | Sequelize/Prisma model |
| `g_config()` | config service / feature flag |

---

### 1.9 小白实操：把 CSR 当成“请求旅行路线”

今天的核心是把前 4 周知识串起来。

你可以把一次接口请求想成一次旅行：

```text
前端发请求
  ↓
Controller：入口检票员，确认请求进来
  ↓
Service：导游，安排这次业务要做哪些事
  ↓
Repository：资料员，负责去查数据
  ↓
Model/DB：真正的数据来源
  ↓
Service：整理数据
  ↓
Controller：把结果返回给前端
```

CSR 不是一个前端概念，这里指：

```text
Controller → Service → Repository → Model
```

---

### 1.10 第一步：先认清每层职责

你读企业 PHP 项目时，不要只看文件夹名字，要问：这一层负责什么？

| 层级 | 一句话理解 | 常见代码特征 |
|---|---|---|
| Controller | 接 HTTP 请求，返回响应 | `actionXxx()`、`success()`、参数获取 |
| Service | 组织业务流程 | 调多个 Repository、调用配置、组合返回数据 |
| Repository | 封装查询 | `findById()`、`getList()`、查询条件 |
| Model | 表/数据对象 | 字段、表名、ORM 查询 |

一个常见错误是把所有逻辑都放 Controller。你要建立正确习惯：

```text
Controller 要薄，Service 才是业务主线。
```

---

### 1.11 第二步：用一条订单详情接口练习

假设有一个接口：

```text
GET /order/detail?order_id=1001
```

#### Controller 层

```php
<?php

public function actionDetail(): array
{
    $orderId = (int)$this->request->get('order_id');

    $detail = $this->orderService->getDetail($orderId);

    return $this->success($detail);
}
```

你要读出：

| 代码 | 含义 |
|---|---|
| `$this->request->get('order_id')` | 从请求里拿订单 ID |
| `(int)` | 转成整数 |
| `$this->orderService->getDetail($orderId)` | 调 Service 查订单详情 |
| `success($detail)` | 返回统一响应 |

#### Service 层

```php
<?php

public function getDetail(int $orderId): array
{
    $order = $this->orderRepository->findById($orderId);
    $items = $this->orderItemRepository->findByOrderId($orderId);

    return [
        'order' => $order,
        'items' => $items,
    ];
}
```

Service 负责把订单主表和订单商品组合起来。

#### Repository 层

```php
<?php

public function findById(int $orderId): ?Order
{
    return Order::findOne(['id' => $orderId]);
}
```

Repository 负责具体怎么查数据。

---

### 1.12 第三步：把配置读取放进 CSR

配置读取通常不会单独存在，它会出现在链路中。

例如商品列表接口可能需要决定是否展示优惠券：

```php
<?php

public function getHomeData(): array
{
    $goods = $this->goodsRepository->getHotGoods();
    $showCoupon = g_config('site', 'show_coupon', false);

    return [
        'goods' => $goods,
        'show_coupon' => $showCoupon,
    ];
}
```

这时链路不是单线，而是 Service 同时拿数据和拿配置：

```text
Controller
  ↓
Service
  ├─ Repository → Model/DB
  └─ g_config() → 配置中心/缓存
  ↓
返回组合数据
```

小白重点：Service 经常是“业务拼装中心”。

---

### 1.13 第四步：读链路时按固定问题走

每次读一个接口，都用下面 8 个问题：

1. 这个接口 URL 或入口方法是什么？
2. Controller 方法叫什么？
3. Controller 接收了哪些参数？
4. Controller 调用了哪个 Service？
5. Service 做了哪几步业务？
6. Service 调用了哪些 Repository/Model？
7. 有没有读取配置、缓存或第三方服务？
8. 最终返回给前端哪些字段？

把答案填进表格：

| 问题 | 我的记录 |
|---|---|
| 接口入口 |  |
| Controller |  |
| 请求参数 |  |
| Service |  |
| Repository/Model |  |
| 配置读取 |  |
| 返回字段 |  |
| 不懂的地方 |  |

只要你坚持按这个模板读，项目阅读会稳定很多。

---

### 1.14 第五步：用 Node.js 类比理解

Node/Express 中可能这样写：

```js
router.get('/order/detail', async (req, res) => {
  const orderId = Number(req.query.order_id);
  const detail = await orderService.getDetail(orderId);
  res.json({ code: 0, data: detail });
});
```

Service：

```js
async function getDetail(orderId) {
  const order = await orderRepository.findById(orderId);
  const items = await orderItemRepository.findByOrderId(orderId);
  return { order, items };
}
```

对应 PHP：

| PHP | Node.js |
|---|---|
| Controller action | Express route handler |
| Service class | service module/class |
| Repository | DAO / repository |
| Model | ORM model / Prisma model |
| Composer | npm package + autoload 概念 |
| namespace | import/export 的组织思路 |

类比的目的不是说它们完全相同，而是让你迁移已有后端分层思维。

---

### 1.15 第六步：阶段总结应该怎么写

不要写成流水账：

```text
我学了 PHP，学了 Yii2，学了配置。
```

要写成能力总结：

```markdown
## 我已经掌握
- 能读懂 Controller 的 action 方法。
- 能顺着 Controller 找到 Service。
- 能理解 Repository/Model 是数据层。
- 能解释 g_config(module, key, default)。

## 我还不熟
- namespace 和 PSR-4 还需要多练。
- Service 里复杂业务分支容易迷路。
- Repository 查询条件还需要结合数据库理解。

## 我能读通的一条链路
- 入口：xxx
- Controller：xxx
- Service：xxx
- Repository/Model：xxx
- 配置读取：xxx
- 返回字段：xxx
```

---

### 1.16 今日易错点

| 易错点 | 正确理解 |
|---|---|
| 把 CSR 理解成前端渲染 | 这里是 Controller → Service → Repository → Model |
| 只看 Controller，不追 Service | Service 才是业务理解重点 |
| Repository 和 Model 分不清 | Repository 封装查询，Model 表示数据结构/ORM |
| 忽略配置读取 | 配置可能影响返回字段和业务行为 |
| 阶段总结只写感受 | 要写“证据”：读通了哪条链路 |

---

### 1.17 今日掌握检查

请回答：

1. 后端 CSR 分别代表什么？
2. Controller 不应该写太多什么逻辑？
3. Service 为什么是业务理解重点？
4. Repository 和 Model 的区别是什么？
5. `g_config()` 可能出现在 CSR 的哪一层？

参考答案：

1. Controller、Service、Repository、Model。
2. 不应该写大量业务计算、复杂 SQL、库存/金额等核心业务逻辑。
3. 因为 Service 负责组织业务流程、调用数据层、组合返回数据。
4. Repository 封装查询方法，Model 表示数据表/ORM 对象。
5. 常见在 Service，也可能在 Controller，但复杂业务里更推荐放 Service。

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议你从前几周读过的接口里任选一条，例如：

- 配置 API 链路
- 订单详情链路
- 商品列表链路
- 用户信息链路

记录：

| 链路节点 | 记录 |
|---|---|
| 请求 URL/入口方法 |  |
| Controller |  |
| Service |  |
| Repository |  |
| Model/数据表 |  |
| 配置读取 |  |
| 返回字段 |  |

---

## 3. 练习任务

### 练习 1：独立读通一条 CSR 链路

按这个格式输出：

```text
我选择的接口：
Controller：
Service：
Repository：
Model/表：
配置读取：
返回字段：
我不理解的地方：
```

### 练习 2：完成类比打卡

| PHP 概念 | JS/Node 类比 | 差异 |
|---|---|---|
| Controller |  |  |
| Service |  |  |
| Repository |  |  |
| Model |  |  |
| Composer |  |  |
| namespace |  |  |
| `g_config()` |  |  |

### 练习 3：列配置项清单

| module | key | default | 影响页面/业务 | 风险 |
|---|---|---|---|---|
|  |  |  |  |  |
|  |  |  |  |  |
|  |  |  |  |  |

---

## 4. JS/Node.js 类比

- CSR ≈ Controller → Service → Repository → Model
- Controller ≈ Express route/controller
- Service ≈ 业务 service
- Repository ≈ DAO/Prisma/Sequelize 查询封装
- Model ≈ ORM Model
- 配置读取 ≈ config service / feature flags

---

## 5. AI Review 提问

```text
我正在做 Week 01-04 阶段总结。
我已经独立追踪了一条 Controller → Service → Repository → Model 链路，并整理了 JS/Node 类比。
请你检查：
1. 我的 CSR 链路是否完整？
2. 每一层职责是否理解正确？
3. 是否把 Controller 和 Service 的职责混淆了？
4. 我的 Node.js 类比是否准确？
5. 我是否已经具备进入 BFF/微服务阶段的基础？
```

---

## 6. 今日产出

- [ ] CSR 链路笔记
- [ ] JS/Node 类比打卡表
- [ ] 配置项清单
- [ ] Week 01-04 阶段总结
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能独立读通一条 CSR 链路
- [ ] 能解释 Controller、Service、Repository、Model 的职责
- [ ] 能说出配置读取在链路中可能出现的位置
- [ ] 能把 PHP 分层和 Node.js 分层做准确类比
- [ ] 能整理 Week 01-04 的学习收获和薄弱点

---

## 8. 今日自测题

### 8.1 后端语境里的 CSR 指什么？

参考答案：指 Controller → Service → Repository → Model 这条后端分层链路，不要和前端的 Client Side Rendering 混淆。

### 8.2 Controller 层不应该写太多什么逻辑？

参考答案：不应该写大量业务计算和复杂查询，例如订单金额计算、库存扣减、优惠券核销、大量 SQL。这些应下沉到 Service 或更底层。

### 8.3 为什么说 Service 是理解业务的核心入口？

参考答案：因为 Service 负责组织业务流程，编排多个 Repository/Model，处理业务规则，并组合出最终返回数据，业务主线基本都在这一层。

### 8.4 Repository 和 Model 的区别是什么？

参考答案：Repository 关注“怎么查数据”，封装查询方法（如 `findById()`）；Model 关注“数据结构或 ORM 映射”，表示数据表或领域对象。

### 8.5 `g_config()` 通常出现在 CSR 的哪一层？

参考答案：最常见在 Service 层，用来读取配置并组合进返回数据；也可能出现在 Controller，但复杂业务里更推荐放 Service。

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
我正在进行 Week 04 Day 05：阶段总结与类比日 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 04 README](./README.md)
