# Week 11：ThinkPHP 8 门店 API

> 强度建议：约 20h/周（周一到周五各 3h + 周末 5h）  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review  
> 公开说明：使用匿名仓库代号，不包含公司、品牌、内网域名或本地绝对路径。

---

## 1. 本周定位

- 阶段：第三阶段：业务域深入
- 主仓库/项目：`store-api`
- 本周目标：理解 TP8 SMVC、Validate、ThinkORM。

### 为什么本周要学这些

- 掌握第二套 PHP 框架便于比较。

---

## 2. 本周需要掌握的知识点

1. TP8 架构
2. Validate scene
3. ThinkORM
4. ModelJoin
5. 跨服务 Helper

### php-pro 能力对齐

- Validate 不写业务
- 复杂查询隔离

---

## 3. 必读代码/文件路径

- `store-api/app/admin/controller/store/StoreController.php`
- `store-api/app/common/service/store/StoreService.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 4. 七天详细学习安排

| 天 | 类型 | 主题 |
|----|------|------|
| [Day 1（周一）](./day01.md) | 概念入门 | ThinkPHP 8 架构 |
| [Day 2（周二）](./day02.md) | 源码阅读 | StoreController 到 StoreService |
| [Day 3（周三）](./day03.md) | 编码练习 | Validate scene 分组 |
| [Day 4（周四）](./day04.md) | 架构理解 | Model 与 ModelJoin |
| [Day 5（周五）](./day05.md) | 类比日 | Yii2 vs TP8 对比 |
| [Day 6（周六）](./day06.md) | 项目实战 | store-api CRUD 项目 |
| [Day 7（周日）](./day07.md) | 复盘预习 | 验收与预习 |

### Day 1（周一）：ThinkPHP 8 架构

**类型**：概念入门  
**今日目标**：读 TP8 手册与 store-api README。

**学习内容**：
- ThinkPHP 8 架构概览

**源码阅读**：
- `store-api/README.md`

**练习任务**：
- 读 README
- 列目录职责
- 对比 Yii2 项目结构

**JS/Node 类比**：
- TP8≈另一套 PHP MVC

**AI Review 提问**：
- 结构差异？

**今日产出**：
- 目录职责表

**今日完成标准**：
- [ ] 能说明 TP8 结构

---

### Day 2（周二）：StoreController 到 StoreService

**类型**：源码阅读  
**今日目标**：追踪门店列表接口。

**学习内容**：
- SMVC 分层

**源码阅读**：
- `store-api/app/admin/controller/store/StoreController.php`
- `store-api/app/common/service/store/StoreService.php`

**练习任务**：
- 读 StoreController index()
- 追踪到 StoreService list()

**JS/Node 类比**：
- StoreService≈NestJS Service

**AI Review 提问**：
- 分层清晰吗？

**今日产出**：
- CRUD 链路笔记

**今日完成标准**：
- [ ] 能追踪 list 链路

---

### Day 3（周三）：Validate scene 分组

**类型**：编码练习  
**今日目标**：理解 TP8 Validate 与 scene。

**学习内容**：
- ThinkPHP 验证器文档

**源码阅读**：
- `store-api/app/admin/validate/store/OfflineStore.php`

**练习任务**：
- 读 OfflineStore Validate
- 理解 scene
- 对比 Yii2 Form

**JS/Node 类比**：
- Validate scene≈Zod pick()

**AI Review 提问**：
- scene 如何选？

**今日产出**：
- Validate 笔记

**今日完成标准**：
- [ ] 能解释 scene

---

### Day 4（周四）：Model 与 ModelJoin

**类型**：架构理解  
**今日目标**：理解复杂查询放置位置。

**学习内容**：
- ThinkORM 基础

**练习任务**：
- 读 OfflineStore Model
- 读一个 ModelJoin
- 说明为何联表不放 Service

**JS/Node 类比**：
- ModelJoin≈复杂查询封装

**AI Review 提问**：
- 是否合理？

**今日产出**：
- ModelJoin 笔记

**今日完成标准**：
- [ ] 能解释联表封装

---

### Day 5（周五）：Yii2 vs TP8 对比

**类型**：类比日  
**今日目标**：写分层对比表并完成打卡。

**学习内容**：
- 回顾 Yii2 CSR

**源码阅读**：
- `store-api/app/common/library/helper/InternalServiceHelper.php`

**练习任务**：
- 写至少 5 项对比
- 读 InternalServiceHelper
- 完成类比打卡

**JS/Node 类比**：
- TP8 SMVC vs Yii2 CSR

**AI Review 提问**：
- 对比准确吗？

**今日产出**：
- 对比表
- 类比打卡

**今日完成标准**：
- [ ] 完成 5 项对比

---

### Day 6（周六）：store-api CRUD 项目

**类型**：项目实战  
**今日目标**：独立追踪一条 CRUD 并画图。

**学习内容**：
- 选门店或商品接口

**练习任务**：
- 追踪完整 CRUD
- 画接口流程图

**JS/Node 类比**：
- CRUD 链路≈联调文档

**AI Review 提问**：
- 链路完整吗？

**今日产出**：
- CRUD 流程图

**今日完成标准**：
- [ ] 能独立追踪

---

### Day 7（周日）：验收与预习

**类型**：复盘预习  
**今日目标**：验收并预习跨服务。

**学习内容**：
- 预习 PayInternal

**练习任务**：
- 勾选验收

**JS/Node 类比**：
- 准备好串联全链路吗？

**AI Review 提问**：
- 周总结

**今日产出**：
- 完成验收

**今日完成标准**：

---

## 5. JS/Node.js 类比学习（本周总览）

TP Validate≈class-validator；SMVC≈NestJS 分层。

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

- [ ] CRUD 链路图
- [ ] Yii2 vs TP8 对比表

---

## 7. 推荐学习资料

- ThinkPHP 8 手册
- ThinkORM

---

## 8. 本周验收标准

- [ ] 能追踪 TP8 链路
- [ ] 完成对比表

---

## 9. AI Review 提示词

```text
我正在进行 Week 11：ThinkPHP 8 门店 API 的学习。
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

**下周预习**：预习跨服务协作。
