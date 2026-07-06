# Week 08 Day 06：结账全链路时序图（含 MQ）

> 所属周：Week 08：MQ + Webhook + Docker  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`pay-service + mall-gateway`  
> 类型：项目实战  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

合并 Week 05 的 BFF 路由表、Week 06 的下单链路、Week 07 的支付链路和 Week 08 的 Webhook/MQ，画出一张包含同步与异步步骤的完整结账时序图。

今天你要真正掌握这一句话：

> 完整结账链路不是“下单接口 + 支付接口”这么简单，而是从前端结账、网关转发、订单创建、支付发起、Webhook 回调、MQ 投递、消费者处理到订单/通知/权益更新的一整条同步 + 异步链路。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Week 05 BFF API 路由表
2. 回顾 Week 06 `confirm/place` 下单时序图
3. 回顾 Week 07 支付状态机和 Webhook 风险
4. 回顾 Week 08 RabbitMQ 与 Webhook 快速返回
5. 列出完整结账链路中的所有参与者
6. 画同步阶段：前端 → 网关 → 订单 → 支付
7. 画异步阶段：Webhook → MQ → 消费者
8. 标注每一步入参、出参、风险和失败处理
9. 用 AI Review 检查是否遗漏网关层、Webhook 或 MQ

---

## 1. 学习内容

### 1.1 完整结账链路有哪些参与者？

| 参与者 | 职责 |
|---|---|
| 前端结账页 | 展示确认页、提交订单、发起支付 |
| BFF 网关 | 鉴权、公参、路由转发、响应适配 |
| 订单服务 | confirm/place、订单创建、状态初始化 |
| 支付服务 | 创建支付单、调用渠道 SDK、处理回调 |
| 第三方支付平台 | 扣款、支付结果通知 |
| Webhook Controller | 验签、解析事件、幂等检查 |
| RabbitMQ | 投递支付成功/退款成功事件 |
| 消费者 | 更新订单、通知用户、发放权益、写日志 |

---

### 1.2 同步阶段：结账确认与创建订单

```text
前端
  ↓ trade/confirm
BFF 网关
  ↓
订单服务 OrderController::confirm
  ↓
Form 校验 + OrderService::confirm
  ↓
返回商品/地址/优惠/金额

前端
  ↓ trade/place
BFF 网关
  ↓
订单服务 OrderController::place
  ↓
Form 校验 + 锁/幂等 + OrderService::place
  ↓
创建订单，状态：待支付
```

这个阶段重点风险：

- 金额不能信前端
- 库存要校验
- 地址/优惠券不能越权
- 下单要防重复提交

---

### 1.3 同步阶段：发起支付

```text
前端选择支付方式
  ↓
BFF 网关 /pay/create
  ↓
支付服务 PayController
  ↓
PayService::processPayment
  ↓
Node 链：校验订单 → 创建支付单 → 选择渠道 → 调 SDK
  ↓
PaymentFactory → StripeService/BraintreeService
  ↓
第三方平台创建 PaymentIntent/交易
  ↓
返回 client_secret / redirect_url
  ↓
前端继续支付交互
```

注意：

```text
发起支付成功 ≠ 支付真正成功
```

真正成功要等支付平台回调。

---

### 1.4 异步阶段：Webhook 与 MQ

```text
第三方支付平台
  ↓ Webhook 回调
pay-service outer/StripeController
  ↓ raw body + signature + secret 验签
  ↓ event_id 幂等检查
  ↓ 金额/币种/order_id 校验
  ↓ 更新支付单状态
  ↓ 投递 payment.succeeded MQ
  ↓ 快速返回 2xx 给支付平台

RabbitMQ
  ↓
订单消费者 / 通知消费者 / 权益消费者
  ↓
更新订单状态 / 发通知 / 发权益 / 写审计日志
```

Webhook 快速返回是关键：不要在回调里同步做所有耗时动作。

---

### 1.5 完整时序图模板

```text
前端        BFF网关        订单服务        支付服务        支付平台        RabbitMQ        消费者
 |             |              |              |              |              |              |
 | confirm     |              |              |              |              |              |
 |-----------> |------------> |              |              |              |              |
 |             |              | 校验/计算金额 |              |              |              |
 |<----------- |<------------ |              |              |              |              |
 | place       |              |              |              |              |              |
 |-----------> |------------> | 创建订单待支付 |              |              |              |
 |<----------- |<------------ |              |              |              |              |
 | pay/create  |              |              |              |              |              |
 |-----------> |----------------------------> |              |              |              |
 |             |              |              | 创建支付单/调SDK |              |              |
 |             |              |              |------------> |              |              |
 | client_secret/redirect_url |              |              |              |              |
 |<----------- |<---------------------------- |              |              |              |
 | 前端完成支付 |              |              |              |              |              |
 |             |              |              | Webhook      |              |              |
 |             |              |              |<------------ |              |              |
 |             |              |              | 验签/幂等/金额 |              |              |
 |             |              |              | publish event|------------> |              |
 |             |              |              | 快速返回2xx  |              |              |
 |             |              |              |              |              | consume      |
 |             |              |<--------------------------------------------- | 更新订单状态 |
```

你可以根据实际项目修正节点名称。

---

### 1.6 每个阶段的失败处理

| 阶段 | 失败场景 | 处理思路 |
|---|---|---|
| confirm | 商品下架/地址无效 | 返回前端错误 |
| place | 库存不足/重复提交 | 返回错误，不创建重复订单 |
| pay/create | 订单不可支付/渠道失败 | 支付失败或允许换渠道 |
| Webhook 验签 | 签名无效 | 不处理业务，记录日志 |
| Webhook 金额校验 | 金额不一致 | 拒绝更新成功，告警 |
| MQ 投递失败 | 事件未发送 | 记录、重试、补偿 |
| 消费失败 | 通知/权益失败 | 重试、死信队列、人工处理 |
| 重复消费 | 同一事件多次消费 | 消费者幂等 |

---

### 1.7 必须标注的风险点

完整图中至少标注：

- 鉴权
- 公参注入
- Form 校验
- 金额后端计算
- 库存校验
- 下单锁/幂等
- 支付回调验签
- 回调金额校验
- Webhook 快速返回
- MQ 投递
- 消费者幂等
- 死信队列/失败重试

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议结合：

- Week 05 路由表
- Week 06 下单链路
- Week 07 PayService/PaymentFactory/Webhook
- Week 08 RabbitMQ/Refund MQ 笔记

记录：

| 阶段 | 文件/方法 | 入参 | 出参 | 风险 |
|---|---|---|---|---|
| confirm |  |  |  |  |
| place |  |  |  |  |
| pay/create |  |  |  |  |
| webhook |  |  |  |  |
| MQ consumer |  |  |  |  |

---

## 3. 练习任务

### 练习 1：画完整结账时序图

必须包含同步和异步两部分。

### 练习 2：标注 MQ 与 Webhook 触发点

明确：

- 哪一步触发 Webhook
- 哪一步投递 MQ
- 哪些消费者处理消息

### 练习 3：标注风险和补偿

至少列 8 个风险点和处理方式。

---

## 4. JS/Node.js 类比

- 全链路图 ≈ 架构面试必备
- Webhook → MQ ≈ event-driven payment processing
- Consumer ≈ BullMQ worker / RabbitMQ consumer
- 死信队列 ≈ failed jobs / DLQ
- 消费者幂等 ≈ processed_events 去重

---

## 5. AI Review 提问

```text
我正在画包含 MQ/Webhook 的完整结账时序图。
我已经合并了 BFF 路由、订单 confirm/place、支付 PayService、Stripe Webhook、RabbitMQ 和消费者。
请你检查：
1. 是否遗漏网关层或 Webhook 层？
2. 同步和异步边界是否清楚？
3. MQ 投递和消费者职责是否合理？
4. 是否标注了金额、库存、幂等、验签、死信队列风险？
5. 这张图是否适合用于架构面试或新人 onboarding？
```

---

## 6. 今日产出

- [ ] 完整结账时序图
- [ ] MQ 与 Webhook 触发点说明
- [ ] 阶段入参/出参表
- [ ] 风险与补偿表
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 时序图包含 BFF、订单、支付、Webhook、MQ、消费者
- [ ] 能区分同步阶段和异步阶段
- [ ] 能说明 Webhook 为什么要快速返回
- [ ] 能说明 MQ 消费者为什么要幂等
- [ ] 能标注 8 个以上风险点
- [ ] 能口述完整结账链路

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
我正在进行 Week 08 Day 06：结账全链路时序图（含 MQ） 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 08 README](./README.md)
