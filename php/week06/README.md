# Week 06：订单域

> 强度建议：约 20h/周（周一到周五各 3h + 周末 5h）  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review  
> 公开说明：使用匿名仓库代号，不包含公司、品牌、内网域名或本地绝对路径。

---

## 1. 本周定位

- 阶段：第二阶段：网关 + 微服务
- 主仓库/项目：`mall-core`
- 本周目标：深入订单下单、校验、锁、状态机。

### 为什么本周要学这些

- 订单是电商核心。
- 能串起商品/支付/用户。

---

## 2. 本周需要掌握的知识点

1. OrderController
2. OrderService
3. Form 校验
4. 分布式锁
5. 状态机

### php-pro 能力对齐

- 下单关注幂等
- 金额字段谨慎
- 错误码可理解

---

## 3. 必读代码/文件路径

- `order-api/controllers/OrderController.php`
- `mall-core/common/services/order/OrderService.php`
- `order-api/forms/OrderConfirmForm.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 4. 七天详细学习安排

| 天 | 类型 | 主题 |
|----|------|------|
| [Day 1（周一）](./day01.md) | 概念入门 | OrderController 结构与 action |
| [Day 2（周二）](./day02.md) | 源码阅读 | OrderService 业务编排 |
| [Day 3（周三）](./day03.md) | 编码练习 | OrderConfirmForm 参数校验 |
| [Day 4（周四）](./day04.md) | 架构理解 | 分布式锁与防重复提交 |
| [Day 5（周五）](./day05.md) | 类比日 | 订单状态机与类比日 |
| [Day 6（周六）](./day06.md) | 项目实战 | 下单全链路时序图 |
| [Day 7（周日）](./day07.md) | 复盘预习 | 验收与预习 |

### Day 1（周一）：OrderController 结构与 action

**类型**：概念入门  
**今日目标**：读懂订单 Controller 编排方式。

**学习内容**：
- 复习 Yii2 Controller 与 try/catch 模式
- 理解 endSuccess/endFail

**源码阅读**：
- `order-api/controllers/OrderController.php`

**练习任务**：
- 读 OrderController 结构
- 选 1 个 action 追踪到 Service
- 记录入参/出参/错误码

**JS/Node 类比**：
- OrderController≈Express router handler 集合
- endSuccess≈res.json({code,data})

**AI Review 提问**：
- action 里是否有多余业务逻辑？

**今日产出**：
- OrderController 笔记

**今日完成标准**：
- [ ] 能追踪 1 个 action 全链路

---

### Day 2（周二）：OrderService 业务编排

**类型**：源码阅读  
**今日目标**：理解 Service 层职责与返回约定。

**学习内容**：
- 阅读 Service 层设计规范
- 理解 ['code','data','info'] 返回格式

**源码阅读**：
- `mall-core/common/services/order/OrderService.php`

**练习任务**：
- 读 OrderService 前 200 行
- 列 5 个 public 方法及职责
- 画 Service 调用 Repository 关系

**JS/Node 类比**：
- OrderService≈NestJS Service
- 返回数组≈统一业务响应包装

**AI Review 提问**：
- 哪些逻辑应在 Service 而非 Controller？

**今日产出**：
- Service 方法清单

**今日完成标准**：
- [ ] 能列出 5 个方法职责

---

### Day 3（周三）：OrderConfirmForm 参数校验

**类型**：编码练习  
**今日目标**：对照前端结账页理解 Form 校验。

**学习内容**：
- 复习 Yii2 Form rules/scenarios
- 打开 mall-pc 结账页

**源码阅读**：
- `order-api/forms/OrderConfirmForm.php`

**练习任务**：
- 读 OrderConfirmForm
- 列前端字段 vs Form rules 对照表
- 标注必填与错误码

**JS/Node 类比**：
- OrderConfirmForm≈后端 Joi schema
- scenarios≈不同接口不同 schema

**AI Review 提问**：
- 校验失败前端如何展示？

**今日产出**：
- 字段对照表

**今日完成标准**：
- [ ] 能对照 10+ 字段

---

### Day 4（周四）：分布式锁与防重复提交

**类型**：架构理解  
**今日目标**：理解 LockHandleRedis 使用场景。

**学习内容**：
- 阅读 Redis 分布式锁原理
- 找使用锁的 Controller action

**练习任务**：
- 读 LockHandleRedis
- 解释为何下单需要锁
- 对比前端防重复点击

**JS/Node 类比**：
- LockHandleRedis≈Redis SET NX EX
- 防重复下单≈按钮 disabled + 后端锁

**AI Review 提问**：
- 锁粒度与超时是否合理？

**今日产出**：
- 锁使用场景笔记

**今日完成标准**：
- [ ] 能解释为何需要锁

---

### Day 5（周五）：订单状态机与类比日

**类型**：类比日  
**今日目标**：画订单状态流转图并完成类比打卡。

**学习内容**：
- 读 OrderRepository STATUS_MAPPING
- 对照前端订单状态 badge

**源码阅读**：
- `mall-core/common/repositorys/order/OrderRepository.php`

**练习任务**：
- 画订单状态机图
- 完成类比打卡

**JS/Node 类比**：
- STATUS_MAPPING≈前端 statusMap 常量

**AI Review 提问**：
- 状态映射是否完整？

**今日产出**：
- 状态机图
- 类比打卡

**今日完成标准**：
- [ ] 能解释主要状态
- [ ] 完成打卡

---

### Day 6（周六）：下单全链路时序图

**类型**：项目实战  
**今日目标**：追踪 trade/confirm → trade/place 并画图。

**学习内容**：
- 复习网关路由表
- 明确订单服务入口

**练习任务**：
- 追踪 confirm 与 place 全链路
- 画时序图含网关+订单服务
- 标注每步入参出参

**JS/Node 类比**：
- 时序图≈联调必备文档

**AI Review 提问**：
- 时序图是否遗漏异步步骤？

**今日产出**：
- 下单时序图

**今日完成标准**：
- [ ] 时序图完成
- [ ] 能口述链路

---

### Day 7（周日）：验收与预习

**类型**：复盘预习  
**今日目标**：验收并预习支付域。

**学习内容**：
- 对照验收
- 预习 PayService

**练习任务**：
- 勾选验收
- 写总结

**JS/Node 类比**：
- 准备好学支付吗？

**AI Review 提问**：
- 周总结

**今日产出**：
- 完成验收

**今日完成标准**：

---

## 5. JS/Node.js 类比学习（本周总览）

Form≈Joi；Lock≈Redis SET NX；statusMap≈前端 badge。

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

- [ ] 下单时序图
- [ ] Form 对照表
- [ ] 状态机图

---

## 7. 推荐学习资料

- 订单源码
- Yii2 Form

---

## 8. 本周验收标准

- [ ] 能对照前端字段
- [ ] 完成时序图

---

## 9. AI Review 提示词

```text
我正在进行 Week 06：订单域 的学习。
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

**下周预习**：预习支付 PayService。
