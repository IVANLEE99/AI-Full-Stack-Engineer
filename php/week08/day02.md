# Week 08 Day 02：Stripe Webhook 与验签

> 所属周：Week 08：MQ + Webhook + Docker  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`pay-service + mall-gateway`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 Webhook 入口与验签流程。

---

## 1. 学习内容

- Stripe Webhooks 官方指南
- 阅读 outer/StripeController

---

## 2. 源码阅读

- `pay-service/pay-api/controllers/outer/StripeController.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 3. 练习任务

- 读 Webhook Controller
- 画 Webhook 处理流程
- 列验签关键步骤

---

## 4. JS/Node.js 类比

- Webhook≈stripe.webhooks.constructEvent()

---

## 5. AI Review 提问

- 验签失败如何处理？

---

## 6. 今日产出

- Webhook 流程图

---

## 7. 今日完成标准

- [ ] 能解释验签流程

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
