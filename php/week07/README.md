# Week 07：支付域 + Node 流水线

> 强度建议：约 20h/周（周一到周五各 3h + 周末 5h）  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review  
> 公开说明：使用匿名仓库代号，不包含公司、品牌、内网域名或本地绝对路径。

---

## 1. 本周定位

- 阶段：第二阶段：网关 + 微服务
- 主仓库/项目：`pay-service`
- 本周目标：理解支付 Node 链、工厂、状态机。

### 为什么本周要学这些

- 支付是高风险域。
- 要懂状态、幂等、渠道。

---

## 2. 本周需要掌握的知识点

1. PayController
2. PayService
3. PaymentFactory
4. Node 链
5. 渠道 SDK

### php-pro 能力对齐

- 支付关注幂等
- 金额精度
- 日志带 payment_no

---

## 3. 必读代码/文件路径

- `pay-service/pay-api/controllers/PayController.php`
- `pay-service/common/services/pay/PayService.php`
- `pay-service/common/factory/payment/PaymentFactory.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 4. 七天详细学习安排

| 天 | 类型 | 主题 |
|----|------|------|
| Day 1（周一） | 概念入门 | PayController 分类 |
| Day 2（周二） | 源码阅读 | PayService 与 processPayment Node 链 |
| Day 3（周三） | 编码练习 | PaymentFactory 渠道工厂 |
| Day 4（周四） | 架构理解 | 支付渠道 SDK 封装 |
| Day 5（周五） | 类比日 | ProcessPaymentNode 与类比日 |
| Day 6（周六） | 项目实战 | 支付状态机与渠道笔记 |
| Day 7（周日） | 复盘预习 | 验收与预习 |

### Day 1（周一）：PayController 分类

**类型**：概念入门  
**今日目标**：理解 client/internal/outer 控制器分类。

**学习内容**：
- 复习支付域文档
- 理解三类 Controller 使用场景

**源码阅读**：
- `pay-service/pay-api/controllers/PayController.php`

**练习任务**：
- 读 PayController
- 列全部 action 与 HTTP 方法
- 按 client/internal/outer 分类

**JS/Node 类比**：
- outer≈Webhook 无用户鉴权
- internal≈内网服务互调

**AI Review 提问**：
- 分类是否合理？

**今日产出**：
- PayController action 清单

**今日完成标准**：
- [ ] 能说出三类区别

---

### Day 2（周二）：PayService 与 processPayment Node 链

**类型**：源码阅读  
**今日目标**：掌握支付核心 Node 流水线。

**学习内容**：
- 阅读责任链模式
- 理解 NodeExecutionEngine 概念

**源码阅读**：
- `pay-service/common/services/pay/PayService.php`

**练习任务**：
- 读 PayService processPayment
- 列 4 个 Node 及职责
- 画 Node 顺序图

**JS/Node 类比**：
- Node 链≈Express middleware 管道
- Context≈req.context 共享状态

**AI Review 提问**：
- Node 失败时如何中断？

**今日产出**：
- Node 顺序图

**今日完成标准**：
- [ ] 能列出 4 个 Node

---

### Day 3（周三）：PaymentFactory 渠道工厂

**类型**：编码练习  
**今日目标**：理解支付方式到 SDK 的映射。

**学习内容**：
- 阅读工厂模式
- 读 PaymentFactory 源码

**源码阅读**：
- `pay-service/common/factory/payment/PaymentFactory.php`

**练习任务**：
- 列所有支付渠道常量与 Service 类
- 画「支付方式→公司→SDK」映射表

**JS/Node 类比**：
- PaymentFactory≈策略注册表 handlers map

**AI Review 提问**：
- 新增渠道要改哪些地方？

**今日产出**：
- 渠道映射表

**今日完成标准**：
- [ ] 能解释工厂映射

---

### Day 4（周四）：支付渠道 SDK 封装

**类型**：架构理解  
**今日目标**：理解前后端支付分工。

**学习内容**：
- 选读 StripeService 或 BraintreeService
- 阅读 Stripe PaymentIntent 文档

**练习任务**：
- 列 SDK 封装了哪些第三方 API
- 对比前端 Stripe.js 与后端 SDK 分工

**JS/Node 类比**：
- 前端 SDK≈收集卡信息
- 后端 SDK≈创建/确认 PaymentIntent

**AI Review 提问**：
- 敏感信息是否在前端处理？

**今日产出**：
- SDK 分工笔记

**今日完成标准**：
- [ ] 能说明前后端分工

---

### Day 5（周五）：ProcessPaymentNode 与类比日

**类型**：类比日  
**今日目标**：读 ProcessPaymentNode 并画 Context 数据流。

**学习内容**：
- 读 ProcessPaymentNode 源码
- 回顾 Node 链图

**源码阅读**：
- `pay-service/common/services/pay/nodes/pay/ProcessPaymentNode.php`

**练习任务**：
- 画 Context 字段在各 Node 间传递图
- 完成类比打卡

**JS/Node 类比**：
- Context 传递≈middleware 间共享 req 对象

**AI Review 提问**：
- Context 字段是否足够？

**今日产出**：
- 数据流图
- 类比打卡

**今日完成标准**：
- [ ] 能解释 Context 作用

---

### Day 6（周六）：支付状态机与渠道笔记

**类型**：项目实战  
**今日目标**：画支付状态机并写渠道映射笔记。

**学习内容**：
- 整理创建→确认→捕获→完成状态
- 结合 PaymentFactory 写渠道笔记

**练习任务**：
- 画支付状态机图
- 写 1 页渠道映射笔记

**JS/Node 类比**：
- 状态机≈订单/支付联调共同语言

**AI Review 提问**：
- 状态机是否覆盖异常状态？

**今日产出**：
- 状态机图
- 渠道笔记

**今日完成标准**：
- [ ] 能口述状态机

---

### Day 7（周日）：验收与预习

**类型**：复盘预习  
**今日目标**：验收并预习 MQ/Webhook。

**学习内容**：
- 对照验收
- 预习 RabbitMQ

**练习任务**：
- 勾选验收
- 写总结

**JS/Node 类比**：
- 准备好学异步吗？

**AI Review 提问**：
- 周总结

**今日产出**：
- 完成验收

**今日完成标准**：

---

## 5. JS/Node.js 类比学习（本周总览）

Node 链≈middleware pipeline；Factory≈handler map。

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

- [ ] processPayment 图
- [ ] 支付状态机
- [ ] 渠道映射表

---

## 7. 推荐学习资料

- Stripe/Braintree 文档
- 责任链模式

---

## 8. 本周验收标准

- [ ] 能口述状态机
- [ ] 能画 4 Node

---

## 9. AI Review 提示词

```text
我正在进行 Week 07：支付域 + Node 流水线 的学习。
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

**下周预习**：预习 RabbitMQ/Webhook。
