# RabbitMQ 入门：10 分钟学完

> 所属：后端通识（与 [redis/](../redis/redis入门一天学完.md) 平级）
> 类型：零基础 10 分钟通识 + 概念地图 + 真实代码锚点
> 建议时长：10–20 分钟（先把心智模型立起来，命令可稍后手敲）
> 交叉阅读：Week 08 计划 → [plan/week08/第八周-第一个可展示项目.md](../plan/week08/第八周-第一个可展示项目.md) 与 [php/week08/day01.md](../php/week08/day01.md)
> 真实代码锚点：[php/week08/RabbitMq.php](../php/week08/RabbitMq.php) 与 [php/week06/OriginCodes/OrderService.php](../php/week06/OriginCodes/OrderService.php)

---

## 今日目标

10 分钟内搞懂：RabbitMQ 解决什么问题、五个核心角色、消息是怎么从发送端走到消费者的、以及「为什么支付成功后必须用 MQ 而不是全同步」。

**今日核心句：**

> MQ 的价值是异步解耦。支付回调只负责确认支付事实并投递一个事件，发通知、发权益、更新其他系统这些后续动作，交给消费者慢慢处理。**用了 MQ 不代表万事大吉，消费者必须能处理重复消息。**

---

## 1. 一句话定位

**RabbitMQ** 是一个消息中间件（Broker）：生产者把消息交给它，它暂存并按规则路由，再交给一个或多个消费者。它站在两个服务中间，把一个「同步、强耦合、怕失败」的调用，拆成「异步、解耦、可重试」的事件。

和 MySQL / Redis 的分工对齐：

| 组件 | 定位 | 典型场景 |
|------|------|----------|
| MySQL | 真相源，事务与强约束 | 订单、账务、用户主数据 |
| Redis | 内存缓存 / 计数器 / 锁 | 热点读、会话、限流、锁 |
| RabbitMQ | 异步事件总线 | 支付成功 → 通知/权益/发货 |

---

## 2. 五个核心角色（10 秒记住）

| 角色 | 含义 | 类比 |
|------|------|------|
| Producer | 生产者，发送消息 | 支付服务发出「支付成功事件」 |
| Exchange | 交换机，决定消息怎么路由 | 邮局分拣中心 |
| route_key | 路由键，说明消息类型 | `payment.succeeded` |
| Queue | 队列，暂存消息 | 收件箱 |
| Consumer | 消费者，处理队列消息 | 通知服务 / 权益服务 |

```text
Producer
  ↓ publish(exchange, route_key, message)
Exchange
  ↓ 按 route_key + 绑定规则路由
Queue
  ↓
Consumer
```

---

## 3. 为什么支付成功后必须用 MQ？

支付成功后常要做很多事：更新订单、发通知、发权益、触发发货、写统计。若全塞在 Webhook 接口里同步执行：

```text
支付平台回调 → 验签 → 更新支付单 → 更新订单 → 发短信 → 发邮件 → 发权益 → 写统计 → 返回支付平台
```

| 问题 | 后果 |
|---|---|
| Webhook 响应慢 | 支付平台可能超时并重试 |
| 任一步失败影响整体 | 发短信失败拖垮整个回调 |
| 系统强耦合 | 支付服务知道太多下游细节 |
| 不好单独重试 | 无法只重试失败的通知/权益 |

换成 MQ：

```text
Webhook 确认支付成功 → 投递 payment.succeeded 事件 → 快速返回
```

后续由消费者异步处理。**支付回调只确认事实、投递事件，其余交给下游。**

---

## 4. exchange 怎么把消息送到队列？

一个事件可以被多个消费者处理，这就是事件驱动的基础。例如：

```text
exchange = payment.events
route_key = payment.succeeded
message = { order_id: 1001, payment_id: 2001, amount: 1999 }
```

绑定规则决定它进哪些队列：`order_paid_queue`、`notification_queue`、`points_queue`。

exchange 的常见类型（先记住名字）：

| 类型 | 规则 | 用在哪 |
|------|------|--------|
| Direct | 按 route_key 精确匹配 | 一对一，最常用 |
| Fanout | 广播给所有绑定队列 | 一个事件所有人看 |
| Topic | 按 route_key 通配符匹配 | 按事件类型分流 |

---

## 5. 读真实代码：`RabbitMq::send`

仓库里的 [RabbitMq.php](../php/week08/RabbitMq.php) 是发送封装。看 [RabbitMq.php:34-120](../php/week08/RabbitMq.php#L34-L120) 的 `send($exchangeName, $routeKey, $msg, $delaySeconds, $isFormatMsg)`，它做四件事：

1. **建连接**：从配置读 `rabbitmq.host/port/user/pwd`
2. **开 channel**：`$channel = $connection->channel()`，channel 是连接内的逻辑通道
3. **发布确认**：`$channel->confirm_select()` + `wait_for_pending_acks()` —— 确认消息真的被 Broker 收了，防止发送阶段丢消息（Publisher Confirm）
4. **发送并记日志**：`basic_publish($sendmsg, $exchangeName, $routeKey)`；成功/失败都写 Redis 队列日志 `pushMqInsertLog`

另外两个要点，正好对应 MQ 的两大能力：

- **延迟消息**：`send(..., $delaySeconds)` 在 `application_headers` 里塞 `x-delay` 毫秒 → 用到延迟交换机。真实案例见 [OrderService.php:812](../php/week06/OriginCodes/OrderService.php#L812) `sendDelayDelivery`，支付完成延迟 1 小时把订单扭成待发货。
- **持久化**：`delivery_mode => DELIVERY_MODE_PERSISTENT`，Broker 重启不丢消息。

调用方真实写法（exchange/route_key 全来自配置，不硬编码）：

```php
// [OrderService.php:3878] 订单状态变化事件
$exchange = \Yii::$app->params['order_change_mq']['exchange_name'];
$routeKey = \Yii::$app->params['order_change_mq']['route_key'];
$result   = RabbitMq::send($exchange, $routeKey, json_encode($sendData, 320));
```

---

## 6. MQ 的三个风险，一次讲清

| 风险 | 说明 | 应对 |
|---|---|---|
| 消息丢失 | 发送失败 / Broker 异常 / 消费者没接住 | Publisher Confirm + 持久化 + 重试 |
| 重复消息 | 网络重试、Broker 重投 | **消费者幂等**（必须做） |
| 消费失败 | 消费者代码异常 | 重试 + 死信队列（DLQ） |

消息顺序、堆积（消费慢于生产）也要监控：看队列长度、扩容消费者。

> 一句话：用了 MQ 不等于万无一失。**消费者必须能处理重复消息**，幂等是 MQ 入门的及格线。

---

## 7. 和 Node.js 怎么对应

| PHP/RabbitMQ | Node.js |
|---|---|
| `RabbitMq::send` | `channel.publish` / BullMQ `queue.add` |
| exchange | exchange / queue name |
| route_key | job name / routing key |
| consumer | worker / processor |
| 消费者幂等 | job idempotency |
| 死信队列 | DLQ / failed jobs |

---

## 8. 回到心智模型

```text
支付回调（真相：支付单已支付）
  ↓ 验签 / 幂等 / 金额校验
  ↓ 投递 payment.succeeded 事件（exchange + route_key）
  ↓ 快速返回
消费者（订单 / 通知 / 权益）
  ↓ 幂等消费、失败重试、死信兜底
```

---

## 附：10 分钟自检

- [ ] 能说清 producer / exchange / route_key / queue / consumer 五个角色
- [ ] 能解释「支付成功后为什么要异步」而不是全同步
- [ ] 能说出 exchange + route_key 一起决定什么（投到哪些队列）
- [ ] 能说出 MQ 三大风险（丢失 / 重复 / 失败）和应对
- [ ] 能对照 `RabbitMq::send` 说出「Publisher Confirm」「持久化」「延迟消息」三件事
- [ ] 能解释为什么 `event_id` 和消费者幂等缺一不可

**下一步**：按 [php/week08/day01.md](../php/week08/day01.md) 手敲一次 `docker run -p 5672:5672 -p 15672:15672 rabbitmq:3-management`，用 Python / PHP / Go 各写一个 send + consume 的最小示例。