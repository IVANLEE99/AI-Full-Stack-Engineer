# Week 07 Day 05：ProcessPaymentNode 与类比日

> 所属周：Week 07：支付域 + Node 流水线  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`pay-service`  
> 类型：类比日  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

读懂 `ProcessPaymentNode` 在支付 Node 链中的职责，画出 Context 数据如何在各 Node 之间传递，并理解“共享上下文”如何帮助支付流程拆分成多个可维护步骤。

今天你要真正掌握这一句话：

> `ProcessPaymentNode` 通常是支付链路中真正调用渠道 SDK 的节点，它依赖前面 Node 写入的订单、支付单、渠道、金额等 Context 数据，并把第三方返回结果继续写回 Context 供后续节点使用。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Day 02 的 PayService Node 链图
2. 理解 `ProcessPaymentNode` 在链路中的位置
3. 阅读 `ProcessPaymentNode.php`
4. 找它从 Context 读取哪些字段
5. 找它向 Context 写入哪些字段
6. 画 Context 字段在各 Node 间传递图
7. 标注失败时如何中断流程
8. 用 Express middleware 共享 `req` 对象做类比
9. 完成类比打卡并让 AI Review 检查

---

## 1. 学习内容

### 1.1 `ProcessPaymentNode` 负责什么？

支付 Node 链中，不同节点有不同职责：

```text
ValidateOrderNode：校验订单
CreatePaymentNode：创建支付单
SelectChannelNode：选择支付渠道
ProcessPaymentNode：调用渠道 SDK 处理支付
BuildResponseNode：构造返回给前端的数据
```

`ProcessPaymentNode` 通常负责：

- 根据渠道找到 SDK Service
- 调用 Stripe/Braintree/PayPal 等第三方 API
- 保存第三方返回的交易 ID 或 client secret
- 识别第三方返回的成功/失败/处理中状态
- 把结果写回 Context

---

### 1.2 Context 数据流怎么理解？

Context 是支付流程的共享数据容器。

示例数据流：

```text
入口参数写入 Context：order_id、user_id、channel
  ↓
ValidateOrderNode 写入：order、amount、currency
  ↓
CreatePaymentNode 写入：payment_id、payment_no
  ↓
SelectChannelNode 写入：channel_service
  ↓
ProcessPaymentNode 写入：third_transaction_id、client_secret、payment_status
  ↓
BuildResponseNode 读取并生成前端响应
```

小白重点：Context 不是随便塞数据的全局变量，而是一次支付流程的上下文。

---

### 1.3 `ProcessPaymentNode` 可能读取哪些字段？

| 字段 | 来源 | 用途 |
|---|---|---|
| `order_id` | 请求入口 | 关联订单 |
| `payment_id` | CreatePaymentNode | 关联内部支付单 |
| `amount` | ValidateOrderNode | 传给第三方支付平台 |
| `currency` | ValidateOrderNode | 币种 |
| `channel` | 请求或配置 | 选择支付 SDK |
| `user_id` | 网关/入口 | 校验订单归属 |
| `metadata` | 前面节点组装 | 第三方回调时识别订单 |

---

### 1.4 `ProcessPaymentNode` 可能写入哪些字段？

| 字段 | 含义 | 后续谁会用 |
|---|---|---|
| `third_transaction_id` | 第三方交易号 | 状态更新、回调匹配 |
| `client_secret` | 前端继续支付参数 | BuildResponseNode / 前端 |
| `redirect_url` | 跳转支付地址 | 前端 |
| `payment_status` | 支付初始状态 | UpdatePaymentNode |
| `raw_response` | 第三方原始响应 | 日志/排查 |
| `error_message` | 第三方错误信息 | 失败返回 |

注意：原始响应可能包含敏感信息，记录日志时要脱敏。

---

### 1.5 失败时如何处理？

`ProcessPaymentNode` 失败可能来自：

- 渠道不存在
- SDK 抛异常
- 第三方返回失败
- 网络超时
- 金额或币种不合法
- 支付单状态不允许继续处理

失败时应该：

```text
记录日志 → 标记支付失败或处理中 → 中断 Node 链 → 返回明确错误
```

伪代码：

```php
<?php

try {
    $response = $channelService->createPayment($context->toPaymentParams());
    $context->set('client_secret', $response->clientSecret);
    return NodeResult::success();
} catch (\Throwable $e) {
    $context->set('error_message', $e->getMessage());
    return NodeResult::fail('支付渠道调用失败');
}
```

---

### 1.6 Context 字段是否越多越好？

不是。

Context 字段太少：

- 后续 Node 需要重复查询
- 数据传递不清楚

Context 字段太多：

- 每个 Node 都能乱改数据
- 字段来源不清楚
- 调试困难

较好的做法：

| 原则 | 说明 |
|---|---|
| 字段命名清晰 | `payment_id` 比 `id` 好 |
| 写入者明确 | 知道哪个 Node 写入 |
| 敏感字段谨慎 | 不要到处传 secret key |
| 只放流程必要数据 | 不把无关对象塞进去 |
| 关键变化记录日志 | 方便排查 |

---

### 1.7 Express middleware 类比

Express 中多个 middleware 共享 `req`：

```js
async function validateOrder(req, res, next) {
  req.context.order = await orderService.find(req.body.order_id);
  next();
}

async function processPayment(req, res, next) {
  const result = await stripeService.create(req.context.order);
  req.context.clientSecret = result.client_secret;
  next();
}
```

PHP Node 链类似：

```text
每个 Node 读写 Context
成功继续
失败中断
```

---

## 2. 源码阅读

- `pay-service/common/services/pay/nodes/pay/ProcessPaymentNode.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| 阅读点 | 记录 |
|---|---|
| Node 类名 |  |
| 从 Context 读取字段 |  |
| 写入 Context 字段 |  |
| 调用的 Factory/Service |  |
| 失败处理 |  |
| 日志记录 |  |

---

## 3. 练习任务

### 练习 1：画 Context 字段传递图

要求至少包含：

- 入口参数
- 订单信息
- 支付单信息
- 支付渠道
- 第三方返回结果
- 前端响应字段

### 练习 2：完成类比打卡

| PHP Node 链 | Express middleware 类比 | 说明 |
|---|---|---|
| Context | `req.context` |  |
| Node | middleware |  |
| Node 失败 | `next(error)` |  |
| ProcessPaymentNode | 调用支付 SDK 的 middleware |  |

### 练习 3：标注敏感字段

列出 Context 中哪些字段不应该直接打印到日志。

---

## 4. JS/Node.js 类比

- Context 传递 ≈ middleware 间共享 `req.context`
- `ProcessPaymentNode` ≈ 调用支付 SDK 的 middleware/use case step
- NodeResult fail ≈ `next(error)` / throw
- `client_secret` ≈ 前端支付参数，不等于 secret key
- `raw_response` ≈ 第三方原始响应，需要日志脱敏

---

## 5. AI Review 提问

```text
我正在学习 ProcessPaymentNode 和 Context 数据流。
我已经画了 Context 字段在各 Node 间传递的图，并标注了 ProcessPaymentNode 读取和写入的字段。
请你检查：
1. Context 字段是否足够表达支付流程？
2. 哪些字段不应该放入 Context 或不应该打印日志？
3. ProcessPaymentNode 的职责是否过重？
4. 失败中断和错误记录是否合理？
5. 与 Express middleware 共享 req.context 的类比是否准确？
```

---

## 6. 今日产出

- [ ] `ProcessPaymentNode` 阅读笔记
- [ ] Context 数据流图
- [ ] Context 字段读写表
- [ ] 敏感字段标注表
- [ ] 类比打卡表
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 Context 的作用
- [ ] 能说明 `ProcessPaymentNode` 读取哪些字段
- [ ] 能说明 `ProcessPaymentNode` 写入哪些字段
- [ ] 能画出 Context 数据流
- [ ] 能标注敏感字段和日志风险
- [ ] 能用 middleware 共享 `req.context` 类比

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
我正在进行 Week 07 Day 05：ProcessPaymentNode 与类比日 的学习。
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
