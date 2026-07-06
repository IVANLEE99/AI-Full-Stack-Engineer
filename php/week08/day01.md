# Week 08 Day 01：RabbitMQ 基础

> 所属周：Week 08：MQ + Webhook + Docker  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`pay-service + mall-gateway`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 exchange、route_key、消息投递。

---

## 1. 学习内容

- RabbitMQ 教程前 3 章
- 项目 MQ 配置说明

---

## 2. 源码阅读

- `mall-core/common/libraries/App/Utils/RabbitMq.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 3. 练习任务

- 读 RabbitMq.php 工具类
- 列项目中 2 个 MQ 使用场景
- 对比 BullMQ

---

## 4. JS/Node.js 类比

- RabbitMq::send≈amqplib publish
- MQ≈异步解耦

---

## 5. AI Review 提问

- 消息丢失如何防范？

---

## 6. 今日产出

- MQ 场景笔记

---

## 7. 今日完成标准

- [ ] 能解释 send 参数

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
