# Week 01：PHP 8 + Composer + OOP

> 强度建议：约 20h/周（周一到周五各 3h + 周末 5h）  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review  
> 公开说明：使用匿名仓库代号，不包含公司、品牌、内网域名或本地绝对路径。

---

## 1. 本周定位

- 阶段：第一阶段：PHP + Yii2/TP 基础
- 主仓库/项目：`mall-core`
- 本周目标：建立 PHP 语法、OOP 与工程化基础，能读懂 composer.json、namespace、autoload 和基础类。

### 为什么本周要学这些

- 第一周先建立 PHP 语言手感，不急着啃业务。
- 重点是把 PHP 工程结构与 Node 工程结构对应起来。
- 读 BaseService/BaseRepository 是理解后续所有业务代码的骨架。

---

## 2. 本周需要掌握的知识点

1. PHP 8 语法与类型
2. OOP：class/interface/abstract
3. Composer 与 PSR-4
4. PSR-12 编码规范
5. Trait 与 Exception

### php-pro 能力对齐

- 按 PSR-12 书写
- 方法补 return type
- 异常后统一错误结构

---

## 3. 必读代码/文件路径

- `mall-core/composer.json`
- `mall-core/common/BaseService.php`
- `mall-core/common/BaseRepository.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 4. 七天详细学习安排

| 天 | 类型 | 主题 |
|----|------|------|
| [Day 1（周一）](./day01.md) | 概念入门 | PHP 8 类型系统与工程入口 |
| [Day 2（周二）](./day02.md) | 源码阅读 | OOP 与 ES6 Class 对比 |
| [Day 3（周三）](./day03.md) | 编码练习 | namespace 与 Composer 依赖 |
| [Day 4（周四）](./day04.md) | 架构理解 | Trait、Exception 与企业基类 |
| [Day 5（周五）](./day05.md) | 类比日 | PSR-12 与类比日 |
| [Day 6（周六）](./day06.md) | 项目实战 | Todo REST API 实战 |
| [Day 7（周日）](./day07.md) | 复盘预习 | 验收与预习 |

### Day 1（周一）：PHP 8 类型系统与工程入口

**类型**：概念入门  
**今日目标**：理解 PHP 类型、strict types、Composer autoload。

**学习内容**：
- PHP Manual：Types/Variables/Functions
- Composer：PSR-4 autoload

**源码阅读**：
- `mall-core/composer.json`

**练习任务**：
- 确认 php -v、composer -V
- 写 namespace→文件路径映射示例
- 列 PHP 与 JS 类型差异 10 条

**JS/Node 类比**：
- Composer≈npm
- vendor/≈node_modules
- PSR-4≈exports+import

**AI Review 提问**：
- autoload 与 Node import 类比准确吗？
- strict_types 作用是什么？

**今日产出**：
- 类型差异笔记
- autoload 示例

**今日完成标准**：
- [ ] 能解释 vendor/autoload.php
- [ ] 能解释 PSR-4
- [ ] 能说出 3 个 PHP8 特性

---

### Day 2（周二）：OOP 与 ES6 Class 对比

**类型**：源码阅读  
**今日目标**：掌握继承、多态、interface、abstract。

**学习内容**：
- PHP Classes/Interfaces
- ES6 Class 对照

**源码阅读**：
- `mall-core/common/BaseService.php`

**练习任务**：
- 写 Animal/Dog/Cat 示例
- 写 interface+两实现
- 读 BaseService 标单例代码

**JS/Node 类比**：
- PHP class≈ES6 class
- interface≈TS interface

**AI Review 提问**：
- PHP interface 与 TS 差异？
- 单例在 Node 如何实现？

**今日产出**：
- OOP 练习代码
- BaseService 笔记

**今日完成标准**：
- [ ] 能写 interface 层次
- [ ] 能解释多态
- [ ] 能说明单例用途

---

### Day 3（周三）：namespace 与 Composer 依赖

**类型**：编码练习  
**今日目标**：理解 namespace、use、PSR-4 映射。

**学习内容**：
- PSR-4 规范
- composer autoload 配置

**源码阅读**：
- `mall-core/composer.json`
- `mall-core/common/BaseRepository.php`

**练习任务**：
- 追踪类 namespace 到文件
- 画目录与 namespace 图

**JS/Node 类比**：
- namespace≈ES Module
- use≈import
- autoload≈自动 require

**AI Review 提问**：
- namespace 映射图正确吗？
- Repository 命名合理吗？

**今日产出**：
- 映射图
- Repository 笔记

**今日完成标准**：
- [ ] 能推导类文件路径
- [ ] 能解释 PSR-4
- [ ] 能读懂 BaseRepository

---

### Day 4（周四）：Trait、Exception 与企业基类

**类型**：架构理解  
**今日目标**：掌握 Trait、异常、基类职责。

**学习内容**：
- PHP Traits/Exceptions
- PSR-12 摘要

**源码阅读**：
- `mall-core/common/BaseService.php`
- `mall-core/common/BaseRepository.php`

**练习任务**：
- 写 Trait 示例
- 写 try/catch 统一返回
- 对比 Service vs Repository

**JS/Node 类比**：
- Trait≈Mixin
- Exception≈throw Error
- 统一错误≈res.json({code,msg})

**AI Review 提问**：
- Trait 与 Mixin 差异？
- 为何统一异常处理？

**今日产出**：
- Trait 示例
- 异常示例
- 基类对比表

**今日完成标准**：
- [ ] 能解释 Trait
- [ ] 能写 return type
- [ ] 能区分 Service/Repository

---

### Day 5（周五）：PSR-12 与类比日

**类型**：类比日  
**今日目标**：速读 PSR-12，启动 Todo API，完成类比打卡。

**学习内容**：
- PSR-12
- REST 基础

**练习任务**：
- 建 Todo 项目骨架
- 统一 JSON 响应
- 完成类比打卡

**JS/Node 类比**：
- REST≈Express router
- 统一响应≈res.json

**AI Review 提问**：
- 目录结构符合 PSR-12 吗？
- 类比准确吗？

**今日产出**：
- Todo 骨架
- 类比打卡

**今日完成标准**：
- [ ] 目录清晰
- [ ] 有统一响应
- [ ] 完成打卡

---

### Day 6（周六）：Todo REST API 实战

**类型**：项目实战  
**今日目标**：完成 CRUD + 测试 + README。

**学习内容**：
- REST 方法/状态码
- 请求体读取

**练习任务**：
- 实现 5 个 CRUD
- curl/Postman 全测
- 写 README

**JS/Node 类比**：
- CRUD≈Express REST
- curl≈Postman 调试

**AI Review 提问**：
- 代码质量如何？
- 缺哪些企业级改进？

**今日产出**：
- 可运行 Todo API
- 测试记录
- README

**今日完成标准**：
- [ ] 5 接口可用
- [ ] 错误有状态码
- [ ] README 可运行

---

### Day 7（周日）：验收与预习

**类型**：复盘预习  
**今日目标**：验收、自评、预习 Yii2。

**学习内容**：
- 回顾笔记
- 预习 Yii2 结构概述

**练习任务**：
- 逐项验收
- 写周总结
- 列下周 3 个问题

**JS/Node 类比**：
- 周总结≈Sprint Review

**AI Review 提问**：
- 准备好学 Yii2 了吗？
- PHP 短板？

**今日产出**：
- 周总结
- 疑难点清单

**今日完成标准**：
- [ ] 完成验收
- [ ] 完成自评
- [ ] 明确下周目标

---

## 5. JS/Node.js 类比学习（本周总览）

Composer≈npm；namespace≈ES Module；Trait≈Mixin。

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

- [ ] Todo API
- [ ] PSR-4 笔记
- [ ] PHP↔JS 对照
- [ ] 周总结

---

## 7. 推荐学习资料

- PHP 8.x 手册
- Composer 文档
- PSR-12
- 《Modern PHP》

---

## 8. 本周验收标准

- [ ] 能解释 Composer vs npm
- [ ] 能解释 PSR-4
- [ ] 能说出 3 个 PHP8 特性
- [ ] Todo API 可用

---

## 9. AI Review 提示词

```text
我正在进行 Week 01：PHP 8 + Composer + OOP 的学习。
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

**下周预习**：预习 Yii2 生命周期、Module、Filter；确认 mall-gateway 可访问。
