# Week 07 Day 04：支付渠道 SDK 封装

> 所属周：Week 07：支付域 + Node 流水线  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`pay-service`  
> 类型：架构理解  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 Stripe/Braintree 等支付渠道 SDK 封装的前后端分工：前端负责安全收集支付信息和触发支付流程，后端负责创建支付意图、校验金额、保存支付单、处理回调和更新订单状态。

今天你要真正掌握这一句话：

> 支付 SDK 封装的核心原则是：敏感密钥和最终金额校验必须在后端，前端只拿临时支付参数完成用户交互，不能让前端决定订单金额或支付结果。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾支付渠道工厂如何选择 SDK Service
2. 选择一个渠道，如 StripeService 或 BraintreeService
3. 理解前端 SDK 和后端 SDK 分别做什么
4. 阅读 Stripe PaymentIntent 或类似文档概念
5. 列出 SDK Service 封装了哪些第三方 API
6. 画“前端 → 后端 → 支付平台 → 回调 → 订单”的流程
7. 标注敏感信息、金额校验、回调验签位置
8. 对比 Stripe.js 和后端 Stripe SDK 分工
9. 用 AI Review 检查是否有安全边界误解

---

## 1. 学习内容

### 1.1 为什么支付需要前后端分工？

支付涉及敏感信息和资金，不能让前端完成全部逻辑。

前端适合做：

- 展示支付方式
- 收集卡信息或跳转支付页面
- 调用 Stripe.js / PayPal JS SDK
- 使用后端返回的 client secret 或支付参数
- 展示支付结果处理中状态

后端必须做：

- 校验订单归属和状态
- 校验金额和币种
- 创建支付单
- 使用密钥调用支付平台 API
- 保存第三方交易 ID
- 验证支付回调签名
- 更新支付单和订单状态

小白重点：前端负责交互，后端负责可信业务判断。

---

### 1.2 Stripe PaymentIntent 怎么理解？

Stripe 中常见概念是 PaymentIntent。

可以理解为：

```text
一次支付意图 / 一次准备支付的记录。
```

后端创建：

```php
<?php

$paymentIntent = $stripe->paymentIntents->create([
    'amount' => 1000,
    'currency' => 'usd',
    'metadata' => [
        'order_id' => '1001',
    ],
]);
```

后端返回给前端：

```json
{
  "client_secret": "pi_xxx_secret_xxx"
}
```

前端用 Stripe.js 继续确认支付。

注意：`amount` 必须由后端根据订单重新计算，不能信前端传来的金额。

---

### 1.3 后端 SDK Service 封装什么？

以 `StripeService` 为例，可能封装：

| 方法 | 作用 |
|---|---|
| `createPaymentIntent()` | 创建支付意图 |
| `confirmPayment()` | 确认支付 |
| `retrievePayment()` | 查询支付状态 |
| `refund()` | 发起退款 |
| `verifyWebhook()` | 验证回调签名 |
| `parseWebhookEvent()` | 解析回调事件 |

这些方法的共同目标是：把第三方 SDK/API 包装成项目内部可理解的方法。

---

### 1.4 前端 SDK 做什么？

Stripe.js 或类似前端 SDK 通常用于：

- 安全收集卡信息
- 避免卡号直接进入你的服务器
- 使用 `client_secret` 完成确认支付
- 处理 3DS 验证等用户交互

前端示例：

```js
const result = await stripe.confirmCardPayment(clientSecret, {
  payment_method: {
    card: cardElement,
  },
});
```

但前端不能做：

- 决定真实订单金额
- 保存支付平台密钥
- 判断订单最终支付成功
- 直接更新订单状态

---

### 1.5 支付平台回调为什么重要？

前端说“支付成功”不一定可信。

最终可信来源通常是支付平台回调：

```text
Stripe/Braintree/PayPal → pay-service outer webhook
```

回调处理要做：

1. 验签，确认来自支付平台
2. 解析事件类型
3. 找到内部支付单/订单
4. 校验金额、币种、交易号
5. 幂等处理重复回调
6. 更新支付状态
7. 通知订单更新为已支付

小白重点：支付结果以后端收到并验证后的回调为准。

---

### 1.6 敏感信息不能放前端

绝不能暴露到前端：

- 支付平台 secret key
- webhook signing secret
- 商户私钥
- 后端服务间 token
- 内部支付单状态修改接口

可以给前端：

- publishable key
- client secret（按支付平台规则使用）
- 支付跳转 URL
- 临时支付参数

判断原则：

```text
能让人直接操作商户资金或伪造支付结果的东西，绝不能放前端。
```

---

### 1.7 前后端支付流程图

```text
前端结账页
  ↓ 请求创建支付
后端 PayService
  ↓ 校验订单/金额/状态
后端 StripeService
  ↓ 创建 PaymentIntent
Stripe 平台
  ↓ 返回 client_secret
后端返回前端
  ↓
前端 Stripe.js 确认支付
  ↓
Stripe 回调后端 webhook
  ↓ 验签 + 幂等 + 金额校验
后端更新支付单和订单状态
```

---

### 1.8 Node.js 类比

Node 后端也类似：

```js
const paymentIntent = await stripe.paymentIntents.create({
  amount: order.amount,
  currency: order.currency,
  metadata: { order_id: order.id },
});
```

前端：

```js
await stripe.confirmCardPayment(clientSecret, { payment_method: { card } });
```

PHP 和 Node 在支付安全边界上没有本质区别。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议选读：

- `StripeService`
- `BraintreeService`
- 支付回调 Controller
- 支付渠道配置
- Stripe PaymentIntent 文档

记录：

| SDK Service 方法 | 第三方 API | 入参 | 出参 | 风险 |
|---|---|---|---|---|
|  |  |  |  |  |

---

## 3. 练习任务

### 练习 1：列 SDK 封装了哪些第三方 API

| 项目方法 | 第三方 API | 作用 |
|---|---|---|
|  |  |  |

### 练习 2：对比前端 Stripe.js 与后端 SDK 分工

| 任务 | 前端 Stripe.js | 后端 Stripe SDK |
|---|---|---|
| 收集卡信息 |  |  |
| 创建 PaymentIntent |  |  |
| 保存 secret key |  |  |
| 确认支付交互 |  |  |
| 处理回调 |  |  |
| 更新订单状态 |  |  |

### 练习 3：标注敏感信息

列出哪些信息可以给前端，哪些绝不能给前端。

---

## 4. JS/Node.js 类比

- 前端 SDK ≈ Stripe.js / PayPal JS SDK，负责用户交互和安全收集支付信息
- 后端 SDK ≈ Stripe PHP SDK / Node SDK，负责创建支付意图、验签、退款、查询
- PaymentIntent ≈ 一次支付意图
- webhook ≈ 支付平台异步通知
- client_secret ≈ 前端继续支付所需的临时参数，不等于 secret key

---

## 5. AI Review 提问

```text
我正在学习支付渠道 SDK 封装。
我已经整理了前端 Stripe.js、后端 StripeService、PaymentIntent、webhook 回调的分工。
请你检查：
1. 我对前后端支付分工的理解是否正确？
2. 哪些敏感信息绝不能放到前端？
3. 金额校验应该在哪一层完成？
4. 前端支付成功和后端回调成功有什么区别？
5. 真实支付 SDK 封装最容易遗漏哪些安全问题？
```

---

## 6. 今日产出

- [ ] SDK Service 方法清单
- [ ] 前端 SDK vs 后端 SDK 分工表
- [ ] 敏感信息清单
- [ ] 支付流程图
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说明前后端支付分工
- [ ] 能解释 PaymentIntent 或类似支付意图
- [ ] 能列出后端 SDK 封装的 3 个能力
- [ ] 能说明哪些敏感信息不能给前端
- [ ] 能解释为什么支付结果以后端回调为准
- [ ] 能说明金额校验必须在后端完成

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
我正在进行 Week 07 Day 04：支付渠道 SDK 封装 的学习。
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
