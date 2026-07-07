# Week 08 Day 02：Stripe Webhook 与验签

> 所属周：Week 08：MQ + Webhook + Docker  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`pay-service + mall-gateway`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 Stripe Webhook 的入口、原始请求体、签名头、验签密钥和事件分发流程，知道为什么 Webhook 必须快速返回，并把耗时后续动作交给 MQ 异步处理。

今天你要真正掌握这一句话：

> Webhook 是支付平台主动通知你的入口：它不走用户登录，但必须用原始 body + 签名头 + webhook secret 验签，验签成功后只做必要状态确认和事件投递，避免在回调请求里做过多耗时逻辑。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Week 07 的 outer Controller 和支付回调风险
2. 理解 Webhook 为什么不走用户登录鉴权
3. 阅读 Stripe Webhooks 官方指南的验签部分
4. 打开 `outer/StripeController.php` 找回调入口
5. 找 raw body、signature header、webhook secret
6. 画 Webhook 处理流程图
7. 标注验签失败、重复事件、金额不一致处理
8. 理解为什么验签成功后要尽快返回
9. 用 AI Review 检查流程是否安全

---

## 1. 学习内容

### 1.1 Webhook 是什么？

Webhook 可以理解为：

```text
第三方平台在某个事件发生后，主动请求你的接口。
```

支付场景：

```text
用户支付完成
  ↓
Stripe 产生 payment_intent.succeeded 事件
  ↓
Stripe 请求你的 webhook URL
  ↓
你的后端验证并处理事件
```

Webhook 不是前端用户发起的，所以通常没有用户 session/token。

---

### 1.2 为什么必须验签？

如果不验签，任何人都可以伪造请求：

```json
{
  "type": "payment_intent.succeeded",
  "data": {
    "object": {
      "metadata": { "order_id": "1001" },
      "amount": 1999
    }
  }
}
```

这可能让未支付订单被错误标记为已支付。

验签要证明：

```text
这个请求确实来自 Stripe，并且 body 没被篡改。
```

---

### 1.3 Stripe 验签关键材料

Stripe Webhook 验签通常需要：

| 材料 | 来源 | 作用 |
|---|---|---|
| raw body | HTTP 请求原始 body | 参与签名验证 |
| signature header | `Stripe-Signature` | Stripe 发送的签名 |
| webhook secret | 后端配置 | 验证签名 |
| constructEvent | Stripe SDK 方法 | 验签并解析事件 |

Node.js 里常见：

```js
const event = stripe.webhooks.constructEvent(
  rawBody,
  signature,
  endpointSecret
);
```

PHP SDK 也有类似能力。

---

### 1.4 为什么要使用 raw body？

验签通常要求原始 body。

如果你先把 JSON parse 成数组，再重新编码，内容可能发生变化：

- 空格变化
- 字段顺序变化
- 编码变化
- 转义变化

这些都会导致验签失败。

所以 Webhook Controller 要特别注意：

```text
验签用原始请求体，不要用已经被框架改写过的数组。
```

---

### 1.5 Webhook 处理流程

基础流程：

```text
Stripe 请求 Webhook
  ↓
读取 raw body 和 Stripe-Signature
  ↓
使用 webhook secret 验签
  ↓
验签失败：记录日志并返回失败
  ↓
验签成功：解析 event type
  ↓
根据 event_id 做幂等检查
  ↓
校验 payment_id/order_id/amount/currency
  ↓
更新支付单状态或投递 MQ
  ↓
快速返回 2xx 给 Stripe
```

小白重点：Webhook 的目标是可靠接收事件，不是把所有业务都同步做完。

---

### 1.6 验签失败如何处理？

验签失败时：

- 不更新支付状态
- 不更新订单状态
- 不投递支付成功 MQ
- 记录必要日志
- 返回非 2xx 或按项目约定返回失败

不要为了“避免 Stripe 重试”而对伪造请求返回成功并处理业务。

---

### 1.7 快速返回为什么重要？

支付平台通常有超时和重试机制。

如果你的 Webhook 处理太慢：

```text
Stripe 认为通知失败 → 重试同一个事件
```

所以推荐：

```text
Webhook 中做必要校验和落库/投递事件，然后快速返回。
```

耗时动作交给 MQ：

- 发送短信
- 发放权益
- 通知订单服务
- 写统计
- 发邮件

---

### 1.8 Node.js 类比

Node/Express 中 Stripe Webhook 常见写法：

```js
app.post('/webhook/stripe', express.raw({ type: 'application/json' }), (req, res) => {
  const sig = req.headers['stripe-signature'];
  const event = stripe.webhooks.constructEvent(req.body, sig, endpointSecret);
  res.sendStatus(200);
});
```

重点是 `express.raw()`，因为验签需要原始 body。

---

## 2. 源码阅读

- `pay-service/pay-api/controllers/outer/StripeController.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| 阅读点 | 记录 |
|---|---|
| Webhook action |  |
| raw body 获取方式 |  |
| signature header 名称 |  |
| webhook secret 来源 |  |
| 验签方法 |  |
| event type 分发 |  |
| 幂等处理 |  |
| 是否投递 MQ |  |

---

## 3. 练习任务

### 练习 1：读 Webhook Controller

记录入口、验签、事件解析、失败处理、成功处理。

### 练习 2：画 Webhook 处理流程

至少包含：请求、raw body、验签、event type、幂等、金额校验、MQ、返回。

### 练习 3：列验签关键步骤

| 步骤 | 作用 | 漏掉后风险 |
|---|---|---|
|  |  |  |

---

## 4. JS/Node.js 类比

- Webhook 验签 ≈ `stripe.webhooks.constructEvent()`
- raw body ≈ `express.raw()`
- webhook secret ≈ 后端私密配置
- event_id 幂等 ≈ processed_events 表 / Redis set
- 快速返回 ≈ 先 ack 再异步处理

---

## 5. AI Review 提问

```text
我正在学习 Stripe Webhook 与验签。
我已经画了 Webhook 入口、raw body、Stripe-Signature、webhook secret、constructEvent、事件分发和 MQ 投递流程。
请你检查：
1. 验签流程是否完整？
2. 验签失败应该如何处理？
3. 为什么必须使用 raw body？
4. 重复 Webhook 事件如何幂等？
5. 哪些逻辑应该放 MQ 异步处理？
```

---

## 6. 今日产出

- [ ] Webhook Controller 阅读笔记
- [ ] Webhook 流程图
- [ ] 验签关键步骤表
- [ ] 快速返回与 MQ 分工笔记
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 Webhook 为什么不走用户登录
- [ ] 能解释 Stripe 验签需要哪些材料
- [ ] 能说明为什么使用 raw body
- [ ] 能说明验签失败如何处理
- [ ] 能画出 Webhook → MQ 的处理流程

---

## 8. 今日自测题

### 8.1 Webhook 是什么？为什么它通常不走用户登录鉴权？

参考答案：

> ✅ Webhook 是第三方平台在某个事件发生后，主动请求你的接口。支付场景里是支付平台在用户支付完成后回调你的 URL。它不是前端用户发起的，没有用户 session/token，所以不能用普通的用户登录鉴权，而要靠验签来确认请求来源。

---

### 8.2 Stripe 验签需要哪些关键材料？

参考答案：

> ✅ 需要三样东西：raw body（HTTP 请求的原始 body）、signature header（`Stripe-Signature` 头）、webhook secret（后端配置的密钥）。再用 Stripe SDK 的 `constructEvent` 把它们组合起来验签并解析事件。

---

### 8.3 为什么验签必须使用 raw body，而不是解析后的数组？

参考答案：

> ✅ 因为签名是针对原始字节算出来的。如果先把 JSON parse 成数组再重新编码，空格、字段顺序、编码、转义都可能变化，导致算出的签名和 Stripe 发来的对不上，验签就会失败。所以要用原始请求体，不要用被框架改写过的数组。

---

### 8.4 验签失败时应该怎么处理？

参考答案：

> ✅ 不更新支付状态、不更新订单状态、不投递支付成功 MQ，只记录必要日志并返回非 2xx（或按项目约定返回失败）。绝不能为了避免平台重试就对伪造请求返回成功并处理业务。

---

### 8.5 验签成功后为什么要尽快返回，哪些动作应该交给 MQ？

参考答案：

> ✅ 支付平台有超时和重试机制，Webhook 处理太慢会被判为失败并重试同一事件。所以验签成功后只做必要的校验和落库/投递事件就快速返回 2xx。发短信、发邮件、发权益、通知订单服务、写统计等耗时动作都交给 MQ 异步处理。

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
我正在进行 Week 08 Day 02：Stripe Webhook 与验签 的学习。
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
