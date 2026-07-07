# Week 12 Day 03：从 BFF 到 TP8 到支付/售后的全链路追踪

> 所属周：Week 12：跨服务调用 / InternalService / 全链路串联  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`全部后端`  
> 类型：编码练习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

把前面学过的 BFF、TP8、订单、支付、售后串成一条完整链路，能画出一次“结账 / 支付 / 售后”请求从前端进入 BFF，再到 TP8 或核心服务，再通过 `PayInternal` / `InternalServiceHelper` 调用支付、售后服务的完整时序图。

今天你要真正掌握这一句话：

> 全链路追踪不是只知道“调用了支付”，而是能标清每一跳：谁调用谁、path 是什么、传什么参数、如何鉴权、超时如何处理、错误如何向上传递、trace_id 如何贯穿全程。

---

## 0. 今日学习路线

1. 回顾 Week 05：BFF 网关职责和 HTTP Client 封装
2. 回顾 Week 08：支付、Webhook、MQ、幂等
3. 回顾 Week 10：售后服务和支付回调链路
4. 回顾 Week 11：TP8 `store-api` 分层和 `InternalServiceHelper`
5. 选择一条链路：结账支付、查询支付状态、门店发起售后、退款
6. 按“前端 → BFF → TP8/核心服务 → Internal Client → 下游服务”画图
7. 标注每一跳的 request_id、trace_id、鉴权、timeout
8. 记录同步调用和异步 MQ/Webhook 的边界
9. 用 AI Review 检查链路是否遗漏

---

## 1. 学习内容

### 1.1 什么是“全链路”？

全链路就是一次业务请求经过的所有关键节点。

例如结账支付：

```text
前端点击支付
  ↓
BFF 接收支付请求
  ↓
订单/核心服务校验订单
  ↓
PayInternal 调用支付服务
  ↓
pay-service 创建或确认支付
  ↓
支付渠道返回结果
  ↓
订单更新状态 / MQ 通知 / Webhook 回调
  ↓
前端看到支付结果
```

小白重点：你不是只看一个 Controller，而是看多个服务之间怎么协作。

---

### 1.2 先分清同步与异步

跨服务链路里有两类步骤。

#### 同步 HTTP

调用方会等待结果：

```text
BFF → order-service → pay-service
```

特点：

- 需要 timeout
- 需要错误返回
- 用户可能正在等待响应
- trace_id 必须传递

#### 异步 MQ / Webhook

调用方不一定等待最终结果：

```text
pay-service → MQ → order-service 更新状态
支付渠道 → Webhook → pay-service → MQ
```

特点：

- 需要幂等
- 需要重试消费
- 日志必须有业务 ID
- 用户看到的可能是“处理中”

---

### 1.3 推荐链路图模板

你可以先画文本版：

```text
[前端]
  POST /checkout/pay
  trace_id=T1
    ↓
[BFF / mall-gateway]
  PayController::actionPay()
  调用 OrderRequest / PayRequest
  headers: X-Trace-Id=T1, Authorization=用户 token
    ↓
[TP8 / mall-core / order-service]
  CheckoutService::pay()
  校验订单、金额、用户、库存
  调用 PayInternal::capture()
    ↓
[PayInternal]
  POST /internal/pay/capture
  headers: X-Trace-Id=T1, X-Service-Name=order-service, X-Signature=...
  timeout=2s
    ↓
[pay-service]
  InternalPayController::capture()
  验签、幂等、调用渠道
    ↓
[支付渠道 / DB / MQ]
  返回支付状态或处理中
```

---

### 1.4 每一跳都要记录什么？

| 记录项 | 说明 |
|---|---|
| 调用方 | 谁发起调用 |
| 被调用方 | 谁接收调用 |
| path | HTTP 路径 |
| method | GET/POST 等 |
| 参数 | body/query 关键字段 |
| headers | token、signature、trace_id |
| timeout | 最多等多久 |
| retry | 是否重试 |
| 错误 | 失败时返回什么 |
| 日志 ID | trace_id/request_id/业务 ID |

如果你能给每一跳填表，就说明链路真正读通了。

---

### 1.5 从 BFF 开始追踪

BFF 的职责通常是：

- 鉴权
- 获取用户上下文
- 接收前端参数
- 调用下游服务
- 转换响应格式
- 不写核心业务

伪代码：

```php
<?php

public function actionPay(): array
{
    $params = $this->request->post();
    $userId = $this->getLoginUserId();

    $result = $this->orderRequest->pay([
        'user_id' => $userId,
        'order_no' => $params['order_no'],
    ]);

    return $this->success($result);
}
```

你要判断：BFF 有没有把订单金额计算、支付状态更新这种核心逻辑写在自己这里？如果有，就要标记为职责不清。

---

### 1.6 到 TP8 / 核心服务看业务编排

TP8 或核心服务通常做业务校验：

```php
<?php

public function pay(array $params): array
{
    $order = $this->orderRepository->findByNo($params['order_no']);
    $this->checkUserOwnsOrder($order, $params['user_id']);
    $this->checkOrderCanPay($order);

    return $this->payInternal->capture([
        'order_no' => $order->order_no,
        'amount' => $order->pay_amount,
        'currency' => $order->currency,
        'idempotency_key' => $order->pay_idempotency_key,
    ]);
}
```

重点：

```text
核心业务校验在业务服务里，跨服务 HTTP 细节在 Internal Client 里。
```

---

### 1.7 支付/售后服务如何返回错误？

下游错误不一定等于上游错误。

支付服务内部错误：

```json
{
  "code": "CHANNEL_TIMEOUT",
  "message": "provider timeout",
  "request_id": "pay-r1"
}
```

订单服务向 BFF 返回：

```json
{
  "code": "PAY_PROCESSING",
  "message": "支付处理中，请稍后刷新订单状态",
  "trace_id": "T1"
}
```

前端看到：

```text
支付处理中
```

开发排查时拿 `trace_id=T1` 去查所有服务日志。

---

### 1.8 trace_id 如何贯穿全链路？

推荐规则：

1. 如果前端/BFF 已经有 `trace_id`，继续传。
2. 如果没有，BFF 生成一个。
3. 每次服务间 HTTP 调用都放到 header。
4. 每条日志都打印 trace_id。
5. MQ 消息体或 headers 也要带 trace_id。
6. Webhook 入口要生成或关联 trace_id，并记录渠道事件 ID。

示例：

```text
HTTP header: X-Trace-Id=T1
MQ message: { trace_id: "T1", order_no: "O1001" }
Log: [T1] capture pay failed, order_no=O1001
```

---

### 1.9 今日易错点

| 易错点 | 正确理解 |
|---|---|
| 只画同步 HTTP，不画 MQ/Webhook | 支付和售后常有异步补偿 |
| 只写服务名，不写 path | path 才能帮助你反查代码 |
| 忽略鉴权 | 内网调用也要签名或 token |
| 忽略 timeout | 下游慢会拖垮上游 |
| 下游错误直接给用户 | 应转换成用户友好错误 |
| trace_id 只在 BFF 有 | 必须贯穿每一跳 |

---

## 2. 源码阅读

本日重点复盘前面几周链路，建议回看：

- `mall-gateway/services/http/PayRequest.php`
- `mall-gateway/services/http/OrderRequest.php`
- `mall-core/common/api/PayInternal.php`
- `store-api/app/common/library/helper/InternalServiceHelper.php`
- `pay-service/pay-api/controllers/outer/StripeController.php`
- `aftersale-service/common/services/AfterSaleService.php`

---

## 3. 练习任务

### 练习 1：画结账全链路图

要求包含：

- 前端
- BFF
- TP8 / 订单 / 核心服务
- `PayInternal` 或 `InternalServiceHelper`
- 支付服务
- MQ / Webhook
- 售后服务可选

### 练习 2：每一跳填表

| 跳数 | 调用方 | 被调用方 | path | 参数 | headers | timeout | 错误处理 |
|---|---|---|---|---|---|---|---|
| 1 | 前端 | BFF |  |  |  |  |  |
| 2 | BFF | 订单/TP8 |  |  |  |  |  |
| 3 | 订单/TP8 | 支付 |  |  |  |  |  |

### 练习 3：trace_id 传递检查

| 节点 | 是否有 trace_id | 如何传递 | 日志是否记录 |
|---|---|---|---|
| BFF |  |  |  |
| 订单/TP8 |  |  |  |
| 支付服务 |  |  |  |
| MQ |  |  |  |
| 售后服务 |  |  |  |

---

## 4. JS/Node.js 类比

- 全链路 ≈ Express BFF → NestJS service → axios internal client → payment service
- `trace_id` ≈ correlation id
- MQ ≈ BullMQ / RabbitMQ async job
- Webhook ≈ Stripe webhook route
- Internal Client ≈ service SDK wrapper

---

## 5. AI Review 提问

```text
我正在画从 BFF 到 TP8/订单到支付/售后的全链路。
请检查：
1. 我是否遗漏了某一跳？
2. 每一跳的职责是否清楚？
3. 同步 HTTP 和异步 MQ/Webhook 是否区分清楚？
4. trace_id/request_id 是否贯穿全链路？
5. 错误传递和超时策略是否合理？
```

---

## 6. 今日产出

- [ ] 结账全链路时序图
- [ ] 每一跳调用表
- [ ] trace_id 传递表
- [ ] 同步/异步边界说明
- [ ] 错误传递说明
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能画出 BFF → TP8/订单 → 支付/售后的链路
- [ ] 能标注每一跳 path、参数、headers
- [ ] 能区分同步 HTTP 和异步 MQ/Webhook
- [ ] 能解释 trace_id/request_id 的传递
- [ ] 能说明下游错误如何向上传递

---

## 8. 今日自测题

### 8.1 什么是“全链路”？

参考答案：

> ✅ 全链路是指一次业务请求经过的所有关键节点。例如结账支付会经过：前端 → BFF → 订单/核心服务 → PayInternal → pay-service → 支付渠道 → 订单更新/MQ/Webhook → 前端。看全链路不是只看一个 Controller，而是看多个服务如何协作。

---

### 8.2 全链路里同步 HTTP 和异步 MQ/Webhook 有什么区别？

参考答案：

> ✅ 同步 HTTP（如 BFF → 订单 → 支付）调用方要等结果，重点是 timeout、错误返回、trace_id 传递，用户在等响应；异步 MQ/Webhook（如支付回调、状态通知）调用方不一定等最终结果，重点是幂等、重试消费、业务 ID 日志，用户可能先看到“处理中”。

---

### 8.3 画一条结账支付链路图，至少要标注哪些每一跳的信息？

参考答案：

> ✅ 每一跳都要标清：谁调用谁、path 是什么、传什么参数、如何鉴权（签名/token header）、timeout 多久、错误如何向上传递、trace_id 如何贯穿。只知道“调用了支付”是不够的。

---

### 8.4 trace_id 在全链路里起什么作用？

参考答案：

> ✅ trace_id 是全链路追踪 ID，在 BFF、订单、PayInternal、支付服务、MQ、Webhook 里保持同一个值，通过它可以把一次请求在多个服务里的日志串起来，出问题时快速定位到具体链路和节点。

---

### 8.5 为什么要先分清同步调用和异步调用的边界？

参考答案：

> ✅ 因为两者的可靠性设计完全不同。同步调用用户在等，必须控制超时和错误返回；异步调用靠 MQ/Webhook 最终一致，必须做幂等和重试。分不清边界就会出现该幂等的没幂等、该超时的死等，导致重复扣款或请求卡死。

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
我正在进行 Week 12 Day 03：从 BFF 到 TP8 到支付/售后的全链路追踪 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 12 README](./README.md)
