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
> 本仓库快照：[StripeController.php](./StripeController.php)。下文行号均指这份快照。

阅读记录：

| 阅读点 | 记录 |
|---|---|
| Webhook action | `actionWebhook()`，[L28-47](./StripeController.php#L28-L47)。入口只做三件事：取参数 → 调 Service → 按 `code` 返回 `endSuccess` / `endFail`；异常统一 `g_log_error` 后 `endFail` |
| raw body 获取方式 | **快照中未实现**。L30 直接 `$params = \Yii::$app->request->post()`。pay-api 配置注册了 `'application/json' => 'yii\web\JsonParser'`，所以 `post()` 返回的是**从 body 解析出的 JSON 字段数组**，不是原始字节流；验签需要对原始字节做 HMAC，解析后的数组用不了 |
| signature header 名称 | **快照中未出现**。全文件没有 `Stripe-Signature`，也没有任何 header 读取（`grep` 零命中） |
| webhook secret 来源 | **快照中未出现**。没有 secret 配置、没有 SDK 初始化 |
| 验签方法 | **快照中未实现**。没有 `constructEvent`、没有 `Webhook::constructEvent`，也没有 `behaviors()` / `beforeAction()` 注入校验。父类 `common\BaseWebController`（**pay-service 仓库**，190 行）已核实：只做 `pf` / `language` / `site` 与 AB 实验初始化，无 `behaviors()`、无 Filter。应用配置 `baomi-pay-api/config/main.php` 已核实：全局只有 `as corsFilter`（`yii\filters\Cors`），**无签名 Filter** |
| event type 分发 | L33 取 `$params['type']`，原样传给 `PaymentWebhookService::paymentWebhookHandle(PaymentRepository::PAY_BY_STRIPR, $params['type'], $params)`。注意 L33 **没有任何 `isset` / `empty` 校验**（对比同文件 L57 的 3DS 链路先校验再取），缺 key 会告警并把 `null` 传下去。按 [04-pay-service.md](../04-pay-service.md#L820-L844) §9.1：映射表维护在 `PaymentWebhookService::$eventList`，**执行**映射的是 `WebhookEventMappingNode`（如 `payment_intent.succeeded → paymentCompletedHandle`）。两者均无源码快照 |
| 幂等处理 | **Controller 层无幂等**：无 event_id 校验、无 Redis `SETNX`、无 processed_events 表。下游只有**已支付短路**——`PaymentSuccessNotifyNode` 会「先检查支付单是否已经支付，避免重复通知」（[04-pay-service.md](../04-pay-service.md#L890)）。这**不等于 event-id 幂等**：文档把「Webhook 幂等键 = 渠道 event ID」列为推荐做法（§13.2）、把「实现签名验证和 event ID 幂等」列为待办（§14.2），并把 Webhook 表唯一索引列为待确认项（§19）。所以准确表述是：**下游有已支付短路；事件级幂等是否存在，无法从仓库确认** |
| 是否投递 MQ | **Controller 不直接投递**。它 `import` 了 `MqService`（L16）却**全文未调用**，也没有 `RabbitMq::send`。真正更新在 `PaymentSuccessService::updatePaymentSuccess()`（[04-pay-service.md](../04-pay-service.md#L893)）。MQ 侧：`payment_paid_notify_mq`、`order_pay_success_delay_mq` 是 §12.3 列出的**服务级配置清单**（[L1174](../04-pay-service.md#L1174)），文档并未证明 `updatePaymentSuccess()` 会发布到该队列——**这条链路是推断，不是仓库事实** |

读完必须先建立的**风险认知**（这是本日最重要的一条）：

> 这份 `StripeController` 是一个**薄分发层**：它不验签、不取 raw body、不做幂等，直接把 `$params['type']` 当成可信事件类型往下传。
>
> 已核实的边界：
> 1. 快照与真实仓库文件 `baomi-pay-api/controllers/outer/StripeController.php` **逐行一致**（L28-47 已对读）；
> 2. 父类 `common\BaseWebController`（pay-service 仓库，190 行）**已读全文，确认不验签**——只做 `pf` / `language` / `site` 与 AB 实验初始化，没有 `behaviors()`、没有 Filter；
> 3. 应用配置 `baomi-pay-api/config/main.php` **已读全文**：全局只注册了 `as corsFilter`（`yii\filters\Cors`），**没有签名 Filter**；
> 4. 因此唯一可能的验签位置只剩 `PaymentWebhookService::paymentWebhookHandle()` 及其下游，而这个类在仓库里**没有源码快照**。
>
> 注意区分两个同名文件：`03-mall-core-internal.md` §5.4 描述的是 **mall-core** 的 `BaseWebController`，与 pay-service 的 `common\BaseWebController` **不是同一个文件**，不能互相当证据。
>
> 学习时**不要**把 day02 前半部分讲的「raw body + Stripe-Signature + constructEvent」当成这个 controller 的现状。那是 Stripe 的标准做法与「应该怎么写」，不是这份代码「已经怎么写」。真实项目里要确认验签在哪一层，**在没读到实现之前，不能假定它存在**——这正好是 [04-pay-service.md](../04-pay-service.md#L916-L928) §9.4 强调的「仓库各渠道验签实现并不完全一致，修改前必须阅读该渠道 Controller、Webhook Service 和底层 SDK 调用」。

另外几个容易读漏的细节：

1. **常量名拼写是 `PAY_BY_STRIPR`**（少一个 E），不是笔误可以随手改的地方——它是 `PaymentRepository` 里真实存在的常量名，改错会直接报未定义常量。
2. **`actionConfirmCallback()` 是另一条链路**（3DS），[L52-82](./StripeController.php#L52-L82)。它同样用 `request->post()`、同样没验签，靠 `payment_intent` + `payment_intent_client_secret` 查支付参数，结果写 `PaymentRedis::setPayStatus()` 给前端轮询。它和 `actionWebhook` 是**两个入口，不要混为一谈**。
3. **`MqService` 在 L16 被 import，但全文没有用到**。看到这个 import 就以为 Webhook 会投 MQ，是常见的误读；MQ 投递发生在下游 Service。
4. **`$params['payment_intent_client_secret']` 里的 “secret” 不是 webhook secret**。那是 Stripe 3DS 的 PaymentIntent client secret，和验签密钥是两回事；全文没有出现 webhook signing secret。

> 后续动作：拿到 `PaymentWebhookService` / `StripeWebhookService` 源码后，把验签结论补进本表，再勾选「今日完成标准」。

### 同名但不同层：网关侧的 `Outer\StripeController`

`pay-service` 之外，**网关仓库里还有一个同名控制器**，命名空间是 `fecshop\app\frontapi\modules\Outer\controllers`，只做转发：

```php
public function actionWebhook()
{
    $params  = \Yii::$app->request->post();
    $headers = $this->getAllHeaders();          // ← 这里读了 header
    $result  = NewPayRequest::instance()->stripeWebhook($params, $headers);
    $this->endJson($result['code'], null, $result['info']);
}
```

对比两条链路，差异正好解释了「为什么在 pay-service 里搜不到验签」：

| | 网关 `Outer\StripeController` | 支付服务 `outer\StripeController` |
|---|---|---|
| 原始 body | `post()`，不取 raw | `post()`，不取 raw |
| header | **`getAllHeaders()` 取全部 header** | 完全不读 header |
| 下游 | `NewPayRequest::stripeWebhook($params, $headers)` | `PaymentWebhookService::paymentWebhookHandle(...)` |
| 验签位置 | 在 `NewPayRequest`（网关侧 HTTP 客户端，仓库无源码） | 在 `PaymentWebhookService` 或更下层（仓库无源码） |

两个容易踩的判断：

1. **网关 `BaseController::behaviors()` 里挂着 `VerifySignatureFilter`**（见 [02-mall-gateway-fecshop.md](../02-mall-gateway-fecshop.md) §6.2–6.3），但那个校验的是内部调用方的 `__s__` 参数签名，**不是 Stripe 的 `Stripe-Signature`**。两套签名不能混为一谈，看到「有验签 Filter」不等于 Webhook 已验证来源。
2. 网关侧虽然读了全部 header，但它**没有自己验签**，只是把 header 透传给 `NewPayRequest`。所以即使 header 里有 `Stripe-Signature`，结论仍要落到 `NewPayRequest::stripeWebhook` 的实现上。

> 因此本表填写的是**支付服务侧**控制器。网关侧这个同名类是「验签可能在谁手里」的线索，不是答案本身。

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
