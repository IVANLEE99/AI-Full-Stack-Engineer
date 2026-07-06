# Week 05 Day 03：薄 Controller 实践

> 所属周：Week 05：BFF 网关架构  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`mall-gateway`  
> 类型：编码练习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解网关 Controller 为什么要保持精简，能判断一个 action 里哪些代码属于“网关职责”，哪些业务逻辑应该下沉到内网服务。

今天你要真正掌握这一句话：

> 薄 Controller 的核心是“鉴权、取参、调用、返回”：网关 action 可以做入口编排，但不应该把支付、订单、库存等核心业务规则写在 Controller 里。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先回顾 BFF 网关职责
2. 理解什么是“薄 Controller”
3. 复习 Module 路由如何定位到 Controller/action
4. 阅读一个 `PayController` action
5. 统计每个 action 行数，观察是否过长
6. 把 action 内代码分成鉴权、取参、调用、返回、业务逻辑五类
7. 手写一个纯转发 action 伪代码
8. 列出不应出现在网关 Controller 的逻辑
9. 用 AI Review 检查你的 action 是否足够薄

---

## 1. 学习内容

### 1.1 什么是薄 Controller？

薄 Controller 指 Controller 里只保留入口层必须做的事情。

典型职责：

| 职责 | 说明 |
|---|---|
| 鉴权 | 判断用户是否登录、token 是否有效 |
| 取参 | 从 request 中读取参数 |
| 基础校验 | 检查必填、类型、格式 |
| 调用服务 | 调用 Request/Service 完成业务 |
| 返回响应 | 用统一格式返回给前端 |

示例：

```php
<?php

declare(strict_types=1);

public function actionMethods(): array
{
    $userId = $this->getUserId();

    $methods = $this->payRequest->methods([
        'user_id' => $userId,
    ]);

    return $this->success($methods);
}
```

这个 action 很薄：它没有计算支付规则，只是入口编排。

---

### 1.2 为什么网关 Controller 要薄？

因为 BFF 网关不是核心业务服务。

如果 Controller 写太厚，会出现：

- 同一个业务规则在网关和服务里重复
- 支付/订单规则散落在入口层
- 修改业务时不知道该改网关还是服务
- 测试困难
- 多端复用困难
- 容易把网关变成“大泥球”

错误方向：

```text
PayController 里计算支付渠道、优惠、订单状态、风控规则
```

正确方向：

```text
PayController 负责收参数 → 调 PayRequest → 返回支付服务结果
```

小白重点：Controller 越接近 HTTP，越应该薄；核心业务越应该放到稳定的业务服务里。

---

### 1.3 Module 路由如何找到 action？

很多 PHP 框架会把 URL 映射到模块、Controller 和 action。

例如：

```text
/pay/pay/methods
```

可能对应：

```text
module: Pay
controller: PayController
action: actionMethods
```

你阅读时可以这样拆：

| URL 片段 | 可能含义 |
|---|---|
| `pay` | 模块名 |
| `pay` | controller 名 |
| `methods` | action 名 |

真实项目规则要以路由配置为准，但你可以先用这个思路定位代码。

---

### 1.4 如何分析一个 action 是否“薄”？

把 action 里的每一段代码分类：

| 分类 | 是否适合在 Controller | 示例 |
|---|---|---|
| 鉴权 | 适合 | `$userId = $this->getUserId()` |
| 取参 | 适合 | `$orderId = $this->get('order_id')` |
| 基础校验 | 适合 | 判断参数是否为空 |
| 调用 Request/Service | 适合 | `$this->payRequest->methods($params)` |
| 复杂业务规则 | 不适合 | 计算支付金额、库存扣减 |
| SQL 查询 | 通常不适合 | 直接写查询表逻辑 |
| 响应封装 | 适合 | `return $this->success($data)` |

如果一个 action 超过 50-80 行，并且包含大量业务判断，就要警惕。

---

### 1.5 纯转发 action 伪代码

一个典型 BFF action 可以写成：

```php
<?php

declare(strict_types=1);

public function actionCreate(): array
{
    $userId = $this->requireLogin();

    $params = [
        'user_id' => $userId,
        'order_id' => (int)$this->request->post('order_id'),
        'pay_type' => (string)$this->request->post('pay_type'),
    ];

    $result = $this->payRequest->create($params);

    return $this->success($result);
}
```

它做了：

1. 登录校验
2. 读取参数
3. 调用支付服务
4. 返回结果

它没有做：

- 判断订单能不能支付
- 计算金额
- 修改订单状态
- 写支付流水

这些应该由订单/支付服务处理。

---

### 1.6 哪些逻辑不应出现在网关？

| 不应出现在网关的逻辑 | 应该放在哪里 | 原因 |
|---|---|---|
| 订单金额计算 | 订单服务/价格服务 | 核心业务规则 |
| 支付状态流转 | 支付服务 | 涉及资金状态 |
| 库存扣减 | 库存/商品服务 | 涉及并发一致性 |
| 优惠券核销 | 营销/优惠券服务 | 规则复杂且可复用 |
| 数据库事务 | 具体业务服务 | 网关不应跨服务控事务 |
| 风控决策核心规则 | 风控服务 | 安全和策略集中管理 |

网关可以做的是：

- 传递用户身份
- 传递渠道、语言、设备信息
- 做基础参数检查
- 聚合服务结果
- 做响应字段适配

---

### 1.7 Node.js 类比

Express 中薄 route handler：

```js
app.post('/pay/create', auth, async (req, res) => {
  const result = await payClient.create({
    user_id: req.user.id,
    order_id: req.body.order_id,
    pay_type: req.body.pay_type,
  });

  res.json({ code: 0, data: result });
});
```

不推荐：

```js
app.post('/pay/create', async (req, res) => {
  // 在 route 里计算金额、改订单、扣库存、写支付流水
});
```

PHP BFF 同理。

---

## 2. 源码阅读

- `mall-gateway/frontapi/modules/Pay/controllers/PayController.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| action | 行数 | 做了哪些事 | 是否足够薄 | 备注 |
|---|---:|---|---|---|
|  |  |  |  |  |
|  |  |  |  |  |
|  |  |  |  |  |

---

## 3. 练习任务

### 练习 1：统计 PayController 各 action 行数

记录：

| action | 起止行 | 行数 | 主要职责 |
|---|---|---:|---|
|  |  |  |  |

### 练习 2：手写一个纯转发 action 伪代码

要求包含：

- 登录校验
- 取参数
- 调用 `PayRequest`
- 统一返回
- 不写核心支付逻辑

### 练习 3：列出不应出现在网关的逻辑

至少写 5 条，并说明应该放到哪个服务。

---

## 4. JS/Node.js 类比

- 薄 Controller ≈ Express route 只做 auth + params + service call + response
- 业务逻辑 ≈ 下游微服务或 service 层
- `PayRequest` ≈ `payClient`
- `BaseApi` ≈ middleware + response helper

---

## 5. AI Review 提问

```text
我正在练习薄 Controller。
我统计了 PayController 各 action 行数，并写了一个纯转发 action 伪代码。
请你检查：
1. 我的 Controller 是否足够薄？
2. 哪些逻辑不应该放在网关？
3. 我的 action 是否混入了支付/订单核心业务？
4. 与 Express route handler 的类比是否准确？
5. 真实项目里如何避免网关越来越厚？
```

---

## 6. 今日产出

- [ ] PayController action 行数统计表
- [ ] action 职责分类表
- [ ] 纯转发 action 伪代码
- [ ] 不应出现在网关的逻辑清单
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释“薄 Controller”的含义
- [ ] 能说出 Controller 适合做的 5 件事
- [ ] 能举出至少 5 个不应放在网关的反例
- [ ] 能手写一个纯转发 action 伪代码
- [ ] 能用 Node/Express 类比薄 Controller

---

## 8. 学习记录

| 记录项 | 内容 |
|--------|------|
| 今日最清楚的概念 |  |
| 今日最卡的概念 |  |
| JS/Node 类比是否帮助理解 |  |
| 实际耗时 |  |
| 明日要补的问题 |  |

---

## 9. AI Review 提示词

```text
我正在进行 Week 05 Day 03：薄 Controller 实践 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 05 README](./README.md)
