# Week 08：MQ + Webhook + Docker

> 强度建议：约 20h/周（周一到周五各 3h + 周末 5h）  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review  
> 公开说明：使用匿名仓库代号，不包含公司、品牌、内网域名或本地绝对路径。

---

## 1. 本周定位

- 阶段：第二阶段：网关 + 微服务
- 主仓库/项目：`pay-service + mall-gateway`
- 本周目标：掌握 MQ、Webhook、幂等、Docker。

### 为什么本周要学这些

- 真实业务大量异步。
- 要懂回调与补偿。

---

## 2. 本周需要掌握的知识点

1. RabbitMQ
2. Webhook
3. 幂等
4. Docker
5. Laravel Queue

### php-pro 能力对齐

- Webhook 验签
- 幂等 key 稳定
- 日志含业务 ID

---

## 3. 必读代码/文件路径

- `pay-service/pay-api/controllers/outer/StripeController.php`
- `mall-core/common/libraries/App/Utils/RabbitMq.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 4. 七天详细学习安排

| 天 | 类型 | 主题 |
|----|------|------|
| Day 1（周一） | 概念入门 | RabbitMQ 基础 |
| Day 2（周二） | 源码阅读 | Stripe Webhook 与验签 |
| Day 3（周三） | 编码练习 | 退款幂等设计 |
| Day 4（周四） | 架构理解 | Docker 开发环境 |
| Day 5（周五） | 类比日 | Laravel Queue 对比与阶段总结 |
| Day 6（周六） | 项目实战 | 结账全链路时序图（含 MQ） |
| Day 7（周日） | 复盘预习 | 验收与预习 |

### Day 1（周一）：RabbitMQ 基础

**类型**：概念入门  
**今日目标**：理解 exchange、route_key、消息投递。

**学习内容**：
- RabbitMQ 教程前 3 章
- 项目 MQ 配置说明

**源码阅读**：
- `mall-core/common/libraries/App/Utils/RabbitMq.php`

**练习任务**：
- 读 RabbitMq.php 工具类
- 列项目中 2 个 MQ 使用场景
- 对比 BullMQ

**JS/Node 类比**：
- RabbitMq::send≈amqplib publish
- MQ≈异步解耦

**AI Review 提问**：
- 消息丢失如何防范？

**今日产出**：
- MQ 场景笔记

**今日完成标准**：
- [ ] 能解释 send 参数

---

### Day 2（周二）：Stripe Webhook 与验签

**类型**：源码阅读  
**今日目标**：理解 Webhook 入口与验签流程。

**学习内容**：
- Stripe Webhooks 官方指南
- 阅读 outer/StripeController

**源码阅读**：
- `pay-service/pay-api/controllers/outer/StripeController.php`

**练习任务**：
- 读 Webhook Controller
- 画 Webhook 处理流程
- 列验签关键步骤

**JS/Node 类比**：
- Webhook≈stripe.webhooks.constructEvent()

**AI Review 提问**：
- 验签失败如何处理？

**今日产出**：
- Webhook 流程图

**今日完成标准**：
- [ ] 能解释验签流程

---

### Day 3（周三）：退款幂等设计

**类型**：编码练习  
**今日目标**：分析 RefundVerifyNode 与 MQ 节点。

**学习内容**：
- 理解幂等性概念
- 阅读退款相关 Node

**练习任务**：
- 读 RefundVerifyNode
- 读 AddRefundHandleMqNode
- 写 1 页退款幂等分析

**JS/Node 类比**：
- 幂等≈Redis SET NX + 业务唯一键

**AI Review 提问**：
- 重复退款如何拦截？

**今日产出**：
- 幂等分析文档

**今日完成标准**：
- [ ] 能解释幂等设计

---

### Day 4（周四）：Docker 开发环境

**类型**：架构理解  
**今日目标**：配置 Docker 并进入 PHP 容器。

**学习内容**：
- Docker 入门教程
- 本地环境说明文档

**练习任务**：
- 启动 Docker 环境
- 进入 PHP 容器
- 在容器内查看日志

**JS/Node 类比**：
- Docker≈统一开发与部署环境

**AI Review 提问**：
- 容器与本地 PHP 差异？

**今日产出**：
- Docker 操作笔记

**今日完成标准**：
- [ ] 能进入容器
- [ ] 能查看日志

---

### Day 5（周五）：Laravel Queue 对比与阶段总结

**类型**：类比日  
**今日目标**：写 Queue vs RabbitMQ 对照，阶段②自评。

**学习内容**：
- Laravel Queues + Events 文档
- 回顾 W5-W8 笔记

**练习任务**：
- 写 Queue vs RabbitMQ 对照
- 完成阶段②自评

**JS/Node 类比**：
- Laravel Queue≈RabbitMQ 上层抽象

**AI Review 提问**：
- 对照准确吗？

**今日产出**：
- Queue 对照
- 阶段总结

**今日完成标准**：
- [ ] 完成对照
- [ ] 完成阶段自评

---

### Day 6（周六）：结账全链路时序图（含 MQ）

**类型**：项目实战  
**今日目标**：画含 Webhook 与 MQ 的完整结账图。

**学习内容**：
- 合并 W5 路由表与 W6/W7 时序
- 补充异步步骤

**练习任务**：
- 画完整结账时序图
- 标注 MQ 与 Webhook 触发点

**JS/Node 类比**：
- 全链路图≈架构面试必备

**AI Review 提问**：
- 是否遗漏网关层 Webhook？

**今日产出**：
- 完整时序图

**今日完成标准**：
- [ ] 时序图含 MQ/Webhook

---

### Day 7（周日）：验收与预习

**类型**：复盘预习  
**今日目标**：验收并预习用户服务。

**学习内容**：
- 对照验收
- 预习 UserController

**练习任务**：
- 勾选验收
- 写总结

**JS/Node 类比**：
- 准备好学用户域吗？

**AI Review 提问**：
- 周总结

**今日产出**：
- 完成验收

**今日完成标准**：

---

## 5. JS/Node.js 类比学习（本周总览）

MQ≈BullMQ；Webhook≈Stripe 回调；幂等≈SET NX。

### 本周类比打卡模板

```text
本周概念：
Node 等价：
差异：
我能用自己的话解释吗：是 / 否
理解自评：1 / 2 / 3 / 4 / 5
```

---

## 6. 本周产出物

- [ ] 结账全链路图
- [ ] 退款幂等分析
- [ ] 阶段②总结

---

## 7. 推荐学习资料

- RabbitMQ 教程
- Stripe Webhooks
- Docker 入门

---

## 8. 本周验收标准

- [ ] 能解释双层 Webhook
- [ ] 能解释幂等

---

## 9. AI Review 提示词

```text
我正在进行 Week 08：MQ + Webhook + Docker 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：理解是否正确、JS 类比是否准确、是否遗漏风险、真实项目需注意什么。
请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 10. 周日复盘与下周预习

| 复盘项 | 记录 |
|--------|------|
| 本周最清楚的概念 |  |
| 本周最卡的概念 |  |
| JS/Node 类比是否帮助理解 |  |
| 本周产出是否完成 |  |
| 自评分（1-5） |  |

**下周预习**：预习用户服务。
