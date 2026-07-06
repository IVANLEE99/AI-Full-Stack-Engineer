# Week 08 Day 05：Laravel Queue 对比与阶段总结

> 所属周：Week 08：MQ + Webhook + Docker  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`pay-service + mall-gateway`  
> 类型：类比日  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

写出 Laravel Queue、RabbitMQ、Node BullMQ 的对照笔记，并完成 Week 05-08 第二阶段自评，确认自己已经理解 BFF、订单、支付、Webhook、MQ 和异步处理的主线。

今天你要真正掌握这一句话：

> Laravel Queue 是队列能力的框架级抽象，RabbitMQ 是消息中间件基础设施；前者让你更方便地写 Job，后者负责可靠投递、路由、队列和消费。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 RabbitMQ 的 producer、exchange、queue、consumer
2. 阅读 Laravel Queues 和 Events 基础概念
3. 对比 Laravel Queue 与 RabbitMQ 的定位
4. 对比 Node.js BullMQ 与 RabbitMQ 的使用体验
5. 整理支付成功事件在三种体系中的写法
6. 回顾 Week 05-08：BFF、订单、支付、异步
7. 填写阶段②自评表
8. 写阶段总结
9. 用 AI Review 检查对照是否准确

---

## 1. 学习内容

### 1.1 Laravel Queue 是什么？

Laravel Queue 是 Laravel 提供的队列抽象。

你可以写一个 Job：

```php
<?php

final class SendPaymentSuccessNotification
{
    public function handle(): void
    {
        // 发送支付成功通知
    }
}
```

然后派发：

```php
<?php

SendPaymentSuccessNotification::dispatch($orderId);
```

Laravel 底层可以用不同 driver：

- database
- redis
- sqs
- rabbitmq（通过扩展包）

所以 Laravel Queue 更像“框架封装层”。

---

### 1.2 RabbitMQ 是什么定位？

RabbitMQ 是消息中间件。

它关注：

- exchange
- queue
- route_key
- binding
- ack/nack
- retry
- dead letter
- consumer

也就是说，RabbitMQ 是更底层的消息基础设施。

对比：

| 层级 | 关注点 |
|---|---|
| Laravel Queue | 开发者如何写 Job、dispatch、handle |
| RabbitMQ | 消息如何路由、排队、投递、确认、重试 |

---

### 1.3 Laravel Queue vs RabbitMQ

| 对比项 | Laravel Queue | RabbitMQ |
|---|---|---|
| 定位 | 框架队列抽象 | 消息中间件 |
| 开发体验 | 写 Job 类 | publish/consume 消息 |
| 路由能力 | 相对简单 | exchange + route_key 强大 |
| 消费确认 | 框架封装 | ack/nack 机制 |
| 死信队列 | 依赖 driver/配置 | 原生支持 DLX/DLQ |
| 适合 | Laravel 项目内部异步任务 | 微服务间事件通信 |

小白重点：Laravel Queue 可以使用 RabbitMQ 作为底层 driver，但二者不是同一层东西。

---

### 1.4 Node BullMQ vs RabbitMQ

BullMQ 通常基于 Redis，适合任务队列：

```js
await queue.add('payment-succeeded', { orderId: 1001 });
```

Worker：

```js
new Worker('payment', async job => {
  await handlePaymentSucceeded(job.data);
});
```

RabbitMQ 更偏消息路由和事件分发：

```text
exchange + route_key → 多个 queue → 多个 consumer
```

对比：

| 对比项 | BullMQ | RabbitMQ |
|---|---|---|
| 底层 | Redis | AMQP Broker |
| 使用场景 | Job/任务队列 | 消息路由、事件驱动 |
| 延迟/重试 | 很方便 | 需要配置 TTL/DLX 或业务重试 |
| 多消费者事件广播 | 需要设计 | exchange/binding 更自然 |
| Node 生态 | 非常常见 | 也常见，但更底层 |

---

### 1.5 支付成功事件三种写法对照

#### RabbitMQ 思路

```php
<?php

RabbitMq::send(
    'payment.events',
    'payment.succeeded',
    [
        'event_id' => 'evt_001',
        'order_id' => 1001,
        'payment_id' => 2001,
    ]
);
```

#### Laravel Queue 思路

```php
<?php

PaymentSucceededJob::dispatch($orderId, $paymentId);
```

#### BullMQ 思路

```js
await paymentQueue.add('payment.succeeded', {
  order_id: 1001,
  payment_id: 2001,
});
```

三者都能异步处理，但抽象层级不同。

---

### 1.6 阶段②回顾：Week 05-08

| 周次 | 主题 | 核心能力 |
|---|---|---|
| Week 05 | BFF 网关 | 能从前端 URL 反查到内网服务 |
| Week 06 | 订单域 | 能理解下单链路、状态、幂等、库存风险 |
| Week 07 | 支付域 | 能理解支付链、回调验签、金额校验 |
| Week 08 | MQ/Webhook/Docker | 能理解异步事件和开发环境 |

这一阶段的主线：

```text
前端下单
  ↓
BFF 网关
  ↓
订单服务创建订单
  ↓
支付服务创建支付
  ↓
支付平台 Webhook 回调
  ↓
MQ 投递支付成功事件
  ↓
消费者异步更新订单/通知/权益
```

---

### 1.7 阶段②自评表

| 能力项 | 自评分 0-4 | 证据 | 需要补什么 |
|---|---:|---|---|
| BFF 路由反查 |  |  |  |
| 薄 Controller |  |  |  |
| HTTP Client 封装 |  |  |  |
| 订单创建链路 |  |  |  |
| 订单状态机 |  |  |  |
| 支付回调验签 |  |  |  |
| 支付状态机 |  |  |  |
| RabbitMQ 基础 |  |  |  |
| 消费者幂等 |  |  |  |
| Docker 环境 |  |  |  |

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议回看：

- Week 05 路由表
- Week 06 下单时序图
- Week 07 支付状态机
- Week 08 RabbitMQ/Webhook 笔记

---

## 3. 练习任务

### 练习 1：写 Queue vs RabbitMQ 对照

完成至少 8 项对比。

### 练习 2：完成阶段②自评

按 0-4 分填写能力项，并写证据。

### 练习 3：写阶段总结

不少于 500 字，说明你如何理解 BFF → 订单 → 支付 → Webhook → MQ 这条主线。

---

## 4. JS/Node.js 类比

- Laravel Queue ≈ BullMQ Job 抽象
- RabbitMQ ≈ AMQP 消息中间件
- Laravel Job ≈ BullMQ processor job
- exchange/route_key ≈ topic/event routing
- 阶段总结 ≈ 微服务支付链路 milestone review

---

## 5. AI Review 提问

```text
我正在做 Laravel Queue、RabbitMQ、BullMQ 对照和阶段②总结。
我已经整理了 BFF、订单、支付、Webhook、MQ 的主线。
请你检查：
1. Laravel Queue 和 RabbitMQ 的定位是否区分清楚？
2. BullMQ 类比是否准确？
3. 阶段②主线是否完整？
4. 我在哪些能力项上证据不足？
5. 进入用户服务/下一阶段前应该补什么？
```

---

## 6. 今日产出

- [ ] Queue vs RabbitMQ 对照
- [ ] RabbitMQ vs BullMQ 对照
- [ ] 阶段②自评表
- [ ] 阶段②总结
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 完成 Queue vs RabbitMQ 对照
- [ ] 能说明 Laravel Queue 和 RabbitMQ 的层级差异
- [ ] 能用 BullMQ 类比队列任务
- [ ] 完成阶段②自评
- [ ] 能口述 BFF → 订单 → 支付 → Webhook → MQ 主线

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
我正在进行 Week 08 Day 05：Laravel Queue 对比与阶段总结 的学习。
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
