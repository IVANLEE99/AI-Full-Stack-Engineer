# Week 08 Day 01：RabbitMQ 基础

> 所属周：Week 08：MQ + Webhook + Docker  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`pay-service + mall-gateway`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 RabbitMQ 的 exchange、queue、route_key 和消息投递流程，知道支付成功后为什么不应该把所有后续动作都放在 Webhook 请求里同步完成。

今天你要真正掌握这一句话：

> MQ 的核心价值是异步解耦：支付回调只负责确认支付事实并投递事件，发通知、发权益、更新其他系统等后续动作交给消费者慢慢处理。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解为什么支付成功后需要异步处理
2. 理解 producer、exchange、queue、consumer 的角色
3. 理解 route_key 如何决定消息投递到哪里
4. 阅读 `RabbitMq.php` 工具类的发送方法
5. 找项目中 2 个 MQ 使用场景
6. 画“支付成功事件 → MQ → 消费者”的流程图
7. 对比 Node.js 的 BullMQ / amqplib
8. 思考消息丢失、重复消费、消费失败如何处理
9. 完成今日自测和 AI Review

---

## 1. 学习内容

### 1.1 为什么需要 MQ？

支付成功后，系统可能要做很多事：

- 更新订单状态
- 发送支付成功通知
- 发放积分/权益
- 触发发货流程
- 写审计日志
- 通知数据统计系统

如果全部放在 Webhook 接口里同步执行：

```text
支付平台回调
  ↓
验签
  ↓
更新支付单
  ↓
更新订单
  ↓
发短信
  ↓
发邮件
  ↓
发权益
  ↓
写统计
  ↓
返回支付平台
```

问题是：

| 问题 | 后果 |
|---|---|
| Webhook 响应慢 | 支付平台可能认为失败并重试 |
| 任一步失败影响整体 | 短信失败可能拖垮支付回调 |
| 系统耦合严重 | 支付服务知道太多下游细节 |
| 不好重试 | 很难只重试失败的通知/权益 |

MQ 的做法：

```text
Webhook 确认支付成功 → 投递 payment.succeeded 事件 → 快速返回
```

后续由消费者异步处理。

---

### 1.2 RabbitMQ 里的几个核心角色

| 角色 | 含义 | 类比 |
|---|---|---|
| Producer | 生产者，发送消息 | 支付服务发出“支付成功事件” |
| Exchange | 交换机，决定消息如何路由 | 邮局分拣中心 |
| route_key | 路由键，说明消息类型 | `payment.succeeded` |
| Queue | 队列，保存消息 | 收件箱 |
| Consumer | 消费者，处理队列消息 | 订单服务/通知服务 |

流程：

```text
Producer
  ↓ publish(exchange, route_key, message)
Exchange
  ↓ 根据 route_key 路由
Queue
  ↓ 拉取/推送
Consumer
```

---

### 1.3 exchange 和 route_key 怎么理解？

假设支付服务发送：

```text
exchange = payment.events
route_key = payment.succeeded
message = { order_id: 1001, payment_id: 2001 }
```

RabbitMQ 根据绑定规则把消息送到对应队列：

```text
payment.events + payment.succeeded
  ↓
order_paid_queue
notification_queue
points_queue
```

一个事件可以被多个消费者处理，这就是事件驱动的基础。

---

### 1.4 `RabbitMq::send` 可能做什么？

项目里的 `RabbitMq.php` 工具类通常封装：

- 建立连接
- 声明 exchange / queue
- 设置 route_key
- 序列化消息
- publish 消息
- 关闭连接或复用连接

伪代码：

```php
<?php

RabbitMq::send(
    exchange: 'payment.events',
    routeKey: 'payment.succeeded',
    message: [
        'order_id' => 1001,
        'payment_id' => 2001,
        'amount' => 1999,
    ]
);
```

你读源码时重点看 send 的参数，而不是一开始钻 RabbitMQ 底层协议。

---

### 1.5 支付成功事件应该包含什么？

建议包含最小必要信息：

| 字段 | 说明 |
|---|---|
| `event_id` | 事件唯一 ID，用于幂等 |
| `event_type` | 如 `payment.succeeded` |
| `order_id` | 订单 ID |
| `payment_id` | 支付单 ID |
| `amount` | 支付金额 |
| `currency` | 币种 |
| `channel` | 支付渠道 |
| `occurred_at` | 事件发生时间 |

不要把第三方完整原始响应、密钥、卡信息等敏感内容直接放入消息。

---

### 1.6 MQ 的风险：丢失、重复、失败

MQ 引入异步，也带来新问题：

| 风险 | 说明 | 应对思路 |
|---|---|---|
| 消息丢失 | 发送失败或 broker 异常 | publisher confirm、日志、重试 |
| 重复消息 | 网络重试、broker 重投 | 消费者幂等 |
| 消费失败 | 消费者代码异常 | 重试、死信队列 |
| 顺序问题 | 多消费者并发 | 按业务 key 控制顺序 |
| 消息堆积 | 消费慢于生产 | 监控队列长度、扩容消费者 |

小白重点：用了 MQ 不代表万事大吉，消费者必须能处理重复消息。

---

### 1.7 Node.js 类比

使用 `amqplib`：

```js
channel.publish(
  'payment.events',
  'payment.succeeded',
  Buffer.from(JSON.stringify({ order_id: 1001 }))
);
```

使用 BullMQ：

```js
await queue.add('payment.succeeded', {
  order_id: 1001,
  payment_id: 2001,
});
```

类比：

| PHP/RabbitMQ | Node.js |
|---|---|
| `RabbitMq::send` | `channel.publish` / `queue.add` |
| exchange | exchange / queue name |
| route_key | job name / routing key |
| consumer | worker / processor |
| 消费者幂等 | job idempotency |

---

## 2. 源码阅读

- `mall-core/common/libraries/App/Utils/RabbitMq.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| 阅读点 | 记录 |
|---|---|
| send 方法参数 |  |
| exchange 从哪里来 |  |
| route_key 从哪里来 |  |
| message 如何序列化 |  |
| 失败是否记录日志 |  |
| 是否支持重试 |  |

---

## 3. 练习任务

### 练习 1：读 `RabbitMq.php` 工具类

记录：

```text
类名：
发送方法：
参数：
exchange：
route_key：
消息格式：
异常处理：
```

### 练习 2：列项目中 2 个 MQ 使用场景

| 场景 | 事件 | 消费者 | 为什么要异步 |
|---|---|---|---|
| 支付成功 |  |  |  |
| 退款成功 |  |  |  |

### 练习 3：对比 BullMQ

写出 RabbitMQ 与 BullMQ 在项目使用上的相似点和差异。

---

## 4. JS/Node.js 类比

- `RabbitMq::send` ≈ `amqplib publish` / BullMQ `queue.add`
- MQ ≈ 异步解耦
- route_key ≈ job name / event type
- Consumer ≈ worker
- 死信队列 ≈ failed jobs / dead-letter queue

---

## 5. AI Review 提问

```text
我正在学习 RabbitMQ 基础。
我已经理解 producer、exchange、route_key、queue、consumer，并阅读了 RabbitMq::send。
请你检查：
1. 我对消息投递流程理解是否正确？
2. 支付成功事件应该包含哪些字段？
3. 消息丢失如何防范？
4. 重复消费为什么必须考虑？
5. RabbitMQ 与 BullMQ 的类比是否准确？
```

---

## 6. 今日产出

- [ ] RabbitMQ 核心概念笔记
- [ ] `RabbitMq.php` 阅读笔记
- [ ] 2 个 MQ 使用场景表
- [ ] 支付成功事件字段设计
- [ ] RabbitMQ vs BullMQ 对照
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 producer、exchange、route_key、queue、consumer
- [ ] 能解释 `RabbitMq::send` 常见参数
- [ ] 能画出支付成功事件投递流程
- [ ] 能列出 2 个 MQ 使用场景
- [ ] 能说明消息丢失和重复消费风险

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
我正在进行 Week 08 Day 01：RabbitMQ 基础 的学习。
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
