# Week 10：售后服务 + Console 任务

> 强度建议：约 20h/周（周一到周五各 3h + 周末 5h）  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review  
> 公开说明：使用匿名仓库代号，不包含公司、品牌、内网域名或本地绝对路径。

---

## 1. 本周定位

- 阶段：第三阶段：业务域深入
- 主仓库/项目：`aftersale-service`
- 本周目标：理解售后策略、状态机、Console。

### 为什么本周要学这些

- 售后是练复杂业务的好材料。

---

## 2. 本周需要掌握的知识点

1. AfterSaleService
2. processingType
3. 状态机
4. Console
5. 支付回调

### php-pro 能力对齐

- 策略替代 switch
- API vs Console 分工

---

## 3. 必读代码/文件路径

- `aftersale-service/common/services/processingType/concrete/ReturnGoodsRefund.php`
- `aftersale-service/console/controllers/OmsController.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 4. 七天详细学习安排

| 天 | 类型 | 主题 |
|----|------|------|
| [Day 1（周一）](./day01.md) | 概念入门 | AfterSaleService 概览 |
| [Day 2（周二）](./day02.md) | 源码阅读 | processingType 策略模式 |
| [Day 3（周三）](./day03.md) | 编码练习 | 售后状态机 |
| [Day 4（周四）](./day04.md) | 架构理解 | Console 与 API 分工 |
| [Day 5（周五）](./day05.md) | 类比日 | 支付回调链路 |
| [Day 6（周六）](./day06.md) | 项目实战 | 售后流程图项目 |
| [Day 7（周日）](./day07.md) | 复盘预习 | 验收与预习 |

### Day 1（周一）：AfterSaleService 概览

**类型**：概念入门  
**今日目标**：浏览售后核心 Service 结构。

**学习内容**：
- 理解售后域复杂度

**源码阅读**：
- `aftersale-service/common/services/AfterSaleService.php`

**练习任务**：
- 浏览 AfterSaleService
- 列 10 个 public 方法职责

**JS/Node 类比**：
- 大 Service≈需策略模式拆分

**AI Review 提问**：
- 复杂度体现在哪？

**今日产出**：
- 方法清单

**今日完成标准**：
- [ ] 能列 10 个方法

---

### Day 2（周二）：processingType 策略模式

**类型**：源码阅读  
**今日目标**：读 ReturnGoodsRefund 与 Reissue。

**学习内容**：
- Strategy Pattern 资料

**源码阅读**：
- `aftersale-service/common/services/processingType/concrete/ReturnGoodsRefund.php`

**练习任务**：
- 读 ReturnGoodsRefund
- 读 Reissue
- 对比接口实现

**JS/Node 类比**：
- processingType≈switch 拆成 Strategy class

**AI Review 提问**：
- 新增类型如何扩展？

**今日产出**：
- 策略对比表

**今日完成标准**：
- [ ] 能解释策略模式

---

### Day 3（周三）：售后状态机

**类型**：编码练习  
**今日目标**：读 AfterSaleStatusEnum 并画图。

**学习内容**：
- 对比订单状态机

**源码阅读**：
- `aftersale-service/common/enums/AfterSaleStatusEnum.php`

**练习任务**：
- 读枚举
- 画售后状态流转图

**JS/Node 类比**：
- 枚举≈TS enum

**AI Review 提问**：
- 状态是否完备？

**今日产出**：
- 状态机图

**今日完成标准**：
- [ ] 能画状态流转

---

### Day 4（周四）：Console 与 API 分工

**类型**：架构理解  
**今日目标**：理解 Console 批处理场景。

**学习内容**：
- Yii Console 命令格式

**源码阅读**：
- `aftersale-service/console/controllers/OmsController.php`

**练习任务**：
- 读 OmsController
- 理解 php yii controller/action
- 对比 API 入口

**JS/Node 类比**：
- Console≈node scripts/*.js

**AI Review 提问**：
- 何时用 Console？

**今日产出**：
- API vs Console 笔记

**今日完成标准**：
- [ ] 能区分分工

---

### Day 5（周五）：支付回调链路

**类型**：类比日  
**今日目标**：读 actionPayPaidNotify 画时序图。

**学习内容**：
- 复习支付回调

**练习任务**：
- 读支付回调到售后更新链路
- 画时序图
- 完成类比打卡

**JS/Node 类比**：
- 事件驱动≈Webhook 触发下游

**AI Review 提问**：
- 链路清晰吗？

**今日产出**：
- 回调时序图

**今日完成标准**：
- [ ] 能说明回调链路

---

### Day 6（周六）：售后流程图项目

**类型**：项目实战  
**今日目标**：选一个售后类型画完整流程。

**学习内容**：
- 选退货退款或补发

**练习任务**：
- 画申请→审核→处理→完成流程图

**JS/Node 类比**：
- 流程图≈业务 onboarding

**AI Review 提问**：
- 流程是否遗漏分支？

**今日产出**：
- 售后流程图

**今日完成标准**：
- [ ] 流程图完成

---

### Day 7（周日）：验收与预习

**类型**：复盘预习  
**今日目标**：验收并预习 ThinkPHP。

**学习内容**：
- 预习 TP8 手册

**练习任务**：
- 勾选验收

**JS/Node 类比**：
- 准备好学 TP8 吗？

**AI Review 提问**：
- 周总结

**今日产出**：
- 完成验收

**今日完成标准**：

---

## 5. JS/Node.js 类比学习（本周总览）

策略≈switch 拆 class；Console≈CLI 脚本。

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

- [ ] 售后流程图
- [ ] 策略对比表

---

## 7. 推荐学习资料

- Strategy Pattern
- 售后源码

---

## 8. 本周验收标准

- [ ] 能区分 API/Console
- [ ] 完成流程图

---

## 9. AI Review 提示词

```text
我正在进行 Week 10：售后服务 + Console 任务 的学习。
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

**下周预习**：预习 ThinkPHP 8。
