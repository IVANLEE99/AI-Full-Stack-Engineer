# Week 12 Day 05：错误传递 / trace_id / request_id 类比日

> 所属周：Week 12：跨服务调用 / InternalService / 全链路串联  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`全部后端`  
> 类型：类比日  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解跨服务链路中的错误传递、日志关联、`trace_id`、`request_id` 和业务 ID 的区别，并能写出一份可用于排查问题的全链路日志规范草案。

今天你要真正掌握这一句话：

> 跨服务排障靠的不是“猜哪个服务错了”，而是用 trace_id 串起 BFF、TP8、订单、支付、售后、MQ、Webhook 的日志，再结合 request_id 和业务 ID 定位具体失败点。

---

## 0. 今日学习路线

1. 理解错误传递和异常处理的区别
2. 区分用户可见错误、业务错误、系统错误、下游原始错误
3. 理解 `trace_id`、`request_id`、业务 ID 的不同作用
4. 学会设计统一错误结构
5. 学会在 HTTP header、日志、MQ 消息中传递 trace_id
6. 用 Node.js correlation id 做类比
7. 输出全链路日志规范草案
8. 用 AI Review 检查是否可用于线上排障

---

## 1. 学习内容

### 1.1 什么是错误传递？

错误传递不是简单地把下游错误原样返回给上游。

例如支付服务返回：

```json
{
  "code": "STRIPE_TIMEOUT",
  "message": "curl error 28: operation timed out",
  "request_id": "pay-r-001"
}
```

订单服务要做判断：

```text
这是支付渠道超时，不一定支付失败。
对用户应该提示“支付处理中”。
日志里保留 STRIPE_TIMEOUT 和 pay-r-001。
```

向 BFF 返回：

```json
{
  "code": "PAY_PROCESSING",
  "message": "支付处理中，请稍后查看订单状态",
  "trace_id": "T-001"
}
```

小白重点：

```text
下游错误要“翻译”成上游能理解、用户能接受的错误。
```

---

### 1.2 四类错误要分清

| 错误类型 | 示例 | 应该给用户看吗 |
|---|---|---|
| 用户可见错误 | 库存不足、订单已取消 | 可以 |
| 业务错误 | 订单状态不允许支付 | 可以转换后展示 |
| 系统错误 | 数据库连接失败、代码异常 | 不直接展示 |
| 下游原始错误 | `curl error`、渠道错误码 | 不直接展示 |

示例：

```text
订单已取消 → 可以告诉用户“订单已取消，无法支付”
支付服务超时 → 告诉用户“支付处理中/稍后再试”，不要暴露 curl 错误
数据库异常 → 告诉用户“系统繁忙”，日志记录堆栈
```

---

### 1.3 统一错误结构

建议跨服务内部错误包含：

```json
{
  "success": false,
  "code": "PAY_TIMEOUT",
  "message": "支付服务暂时不可用",
  "trace_id": "T-001",
  "request_id": "order-r-001",
  "downstream": {
    "service": "pay-service",
    "request_id": "pay-r-001",
    "code": "STRIPE_TIMEOUT"
  }
}
```

字段含义：

| 字段 | 说明 |
|---|---|
| `success` | 是否成功 |
| `code` | 当前服务定义的错误码 |
| `message` | 当前服务给上游的错误说明 |
| `trace_id` | 全链路 ID |
| `request_id` | 当前服务本次请求 ID |
| `downstream` | 下游服务错误摘要，主要用于日志 |

注意：`downstream` 不一定返回给前端，可以只写日志。

---

### 1.4 `trace_id`、`request_id`、业务 ID 的区别

| ID | 作用 | 生命周期 | 示例 |
|---|---|---|---|
| `trace_id` | 串联整条链路 | 一次用户请求全程 | `T-001` |
| `request_id` | 标识某个服务的一次请求 | 单个服务/单跳 | `pay-r-001` |
| 业务 ID | 定位业务对象 | 业务长期存在 | `order_no=O1001` |

一条日志最好同时有：

```text
trace_id=T-001 request_id=pay-r-001 order_no=O1001 msg="capture timeout"
```

这样排查时可以：

1. 用 `trace_id` 找整条链路。
2. 用 `request_id` 找某个服务具体请求。
3. 用 `order_no` 查业务数据。

---

### 1.5 trace_id 在 HTTP 中怎么传？

上游请求下游时放 header：

```text
X-Trace-Id: T-001
X-Request-Id: order-r-001
```

下游收到后：

1. 读取 `X-Trace-Id`。
2. 如果没有，就生成新的。
3. 为当前服务生成自己的 `request_id`。
4. 后续日志都带上这两个 ID。
5. 再调用下游时继续传同一个 `trace_id`。

---

### 1.6 trace_id 在 MQ 中怎么传？

MQ 消息也要带 trace_id。

```json
{
  "trace_id": "T-001",
  "event_id": "evt-001",
  "order_no": "O1001",
  "type": "PAY_PAID"
}
```

消费者日志：

```text
trace_id=T-001 event_id=evt-001 order_no=O1001 consume PAY_PAID start
```

小白重点：异步链路如果不带 trace_id，日志会断掉。

---

### 1.7 trace_id 在 Webhook 中怎么处理？

Webhook 是外部渠道主动打进来，可能没有你的 trace_id。

做法：

1. 为 Webhook 请求生成新的 trace_id。
2. 记录渠道事件 ID，例如 Stripe event id。
3. 通过业务 ID 关联订单或支付单。
4. 后续发 MQ 时带上新 trace_id。

日志示例：

```text
trace_id=T-webhook-001 provider_event_id=evt_123 pay_no=P1001 msg="webhook received"
```

---

### 1.8 错误传递流程示例

```text
pay-service 调渠道超时
  ↓
pay-service 日志记录 raw error + request_id
  ↓
pay-service 返回 PAY_CHANNEL_TIMEOUT 给 order-service
  ↓
order-service 转换为 PAY_PROCESSING
  ↓
BFF 返回“支付处理中”给前端
  ↓
开发用 trace_id 查询 pay-service 原始错误
```

这就是“用户友好 + 开发可排障”的平衡。

---

### 1.9 Node.js 类比

Node/Express 常用 middleware 生成 correlation id：

```js
app.use((req, res, next) => {
  req.context = {
    traceId: req.headers['x-trace-id'] ?? generateTraceId(),
    requestId: generateRequestId(),
  };
  next();
});
```

axios interceptor 继续传：

```js
client.interceptors.request.use(config => {
  config.headers['X-Trace-Id'] = context.traceId;
  return config;
});
```

PHP 项目也是同样思想，只是实现位置不同。

---

## 2. 源码阅读

建议回看所有有日志、错误返回、HTTP Client 的位置：

- BFF `BaseApi` / `*Request.php`
- `PayInternal.php`
- `InternalServiceHelper.php`
- 支付 Webhook Controller
- 售后支付回调链路

阅读记录：

| 观察点 | 记录 |
|---|---|
| 是否生成 trace_id |  |
| 是否透传 trace_id |  |
| 是否生成 request_id |  |
| 日志是否带业务 ID |  |
| 下游错误是否保留 |  |
| 用户 message 是否友好 |  |

---

## 3. 练习任务

### 练习 1：设计错误结构

为支付超时设计：

| 字段 | 值 |
|---|---|
| code |  |
| user message |  |
| trace_id |  |
| downstream service |  |
| downstream request_id |  |
| log message |  |

### 练习 2：写日志规范草案

至少包含：

- trace_id
- request_id
- service name
- user_id/store_id
- order_no/pay_no/refund_no
- downstream service
- error code
- cost_ms

### 练习 3：画错误传递链路

```text
pay-service 原始错误
  ↓
order-service 业务错误
  ↓
BFF 用户提示
  ↓
前端展示
```

---

## 4. JS/Node.js 类比

- `trace_id` ≈ correlation id
- `request_id` ≈ per-service request id
- 统一错误结构 ≈ error middleware normalized response
- MQ trace 传递 ≈ job.data.traceId
- Webhook event id ≈ provider event id

---

## 5. AI Review 提问

```text
我正在设计跨服务错误传递和 trace_id/request_id 规范。
请检查：
1. trace_id、request_id、业务 ID 是否分清楚？
2. 错误结构是否适合跨服务排障？
3. 哪些错误不应该直接展示给用户？
4. MQ/Webhook 是否会导致 trace 断链？
5. 日志字段是否足够定位问题？
```

---

## 6. 今日产出

- [ ] 错误结构设计
- [ ] trace_id/request_id 区分笔记
- [ ] 全链路日志规范草案
- [ ] MQ/Webhook trace 传递说明
- [ ] 错误传递流程图
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能区分 trace_id、request_id、业务 ID
- [ ] 能设计统一错误结构
- [ ] 能说明哪些错误不能给用户看
- [ ] 能说明 HTTP/MQ/Webhook 如何传递 trace_id
- [ ] 能用日志字段定位跨服务问题

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
我正在进行 Week 12 Day 05：错误传递 / trace_id / request_id 类比日 的学习。
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
