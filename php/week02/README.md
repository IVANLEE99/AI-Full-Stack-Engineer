# Week 02：Yii2 生命周期与 Filter

> 强度建议：约 20h/周（周一到周五各 3h + 周末 5h）  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review  
> 公开说明：使用匿名仓库代号，不包含公司、品牌、内网域名或本地绝对路径。

---

## 1. 本周定位

- 阶段：第一阶段：PHP + Yii2/TP 基础
- 主仓库/项目：`mall-gateway`
- 本周目标：理解 Yii2 请求从入口到 action 的完整生命周期与 Filter 链。

### 为什么本周要学这些

- 后续接口多是 Yii2，先懂生命周期再读 Controller。
- Filter 最接近 Express middleware。

---

## 2. 本周需要掌握的知识点

1. Application 生命周期
2. Module/Controller 路由
3. behaviors/Filter
4. BaseForm 校验
5. Laravel Middleware 对比

### php-pro 能力对齐

- 鉴权不可随意改
- 区分公开/登录/内网接口
- 记录免登录理由

---

## 3. 必读代码/文件路径

- `mall-gateway/frontapi/web/index.php`
- `mall-gateway/frontapi/config/modules/Modules.php`
- `mall-gateway/frontapi/modules/AuthApiController.php`
- `mall-gateway/frontapi/modules/Pay/controllers/PayController.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 4. 七天详细学习安排

| 天 | 类型 | 主题 |
|----|------|------|
| Day 1（周一） | 概念入门 | Yii2 入口与启动 |
| Day 2（周二） | 源码阅读 | Module 与路由 |
| Day 3（周三） | 编码练习 | behaviors 与 Filter |
| Day 4（周四） | 架构理解 | BaseForm 校验 |
| Day 5（周五） | 类比日 | Laravel 对比与类比日 |
| Day 6（周六） | 项目实战 | 鉴权白名单与路径图 |
| Day 7（周日） | 复盘预习 | 验收与预习 |

### Day 1（周一）：Yii2 入口与启动

**类型**：概念入门  
**今日目标**：理解 index.php 与配置合并。

**学习内容**：
- Yii2 结构概述
- runtime-overview

**源码阅读**：
- `mall-gateway/frontapi/web/index.php`

**练习任务**：
- 追踪 config 加载链
- 画启动流程图
- 对比 Express 启动

**JS/Node 类比**：
- index.php≈server.js
- config merge≈多配置合并

**AI Review 提问**：
- 启动流程图准确吗？

**今日产出**：
- 启动流程图
- config 笔记

**今日完成标准**：
- [ ] 能说出入口做了什么
- [ ] 能解释配置合并

---

### Day 2（周二）：Module 与路由

**类型**：源码阅读  
**今日目标**：掌握模块化路由与 action 命名。

**学习内容**：
- Controllers/Modules
- URL 规则

**源码阅读**：
- `mall-gateway/frontapi/config/modules/Modules.php`

**练习任务**：
- 列已注册 Module
- 解释 pay/pay/methods 映射
- 写 3 个路由表

**JS/Node 类比**：
- Module≈router 前缀
- actionXxx≈handler

**AI Review 提问**：
- 路由映射正确吗？

**今日产出**：
- 路由表
- Module 笔记

**今日完成标准**：
- [ ] 能解释 Module 路由
- [ ] 能推导 3 个 URL

---

### Day 3（周三）：behaviors 与 Filter

**类型**：编码练习  
**今日目标**：理解 Filter 链与 beforeAction。

**学习内容**：
- Filters/Behaviors

**源码阅读**：
- `mall-gateway/frontapi/modules/AuthApiController.php`

**练习任务**：
- 列 behaviors
- 画 Filter 顺序
- 读一个 Filter 源码

**JS/Node 类比**：
- behaviors≈app.use(middleware)
- beforeAction≈前置钩子

**AI Review 提问**：
- Filter 顺序对吗？

**今日产出**：
- Filter 链图

**今日完成标准**：
- [ ] 能画 Filter 链
- [ ] 能解释 beforeAction

---

### Day 4（周四）：BaseForm 校验

**类型**：架构理解  
**今日目标**：掌握 rules/scenarios 与错误返回。

**学习内容**：
- Yii2 验证
- Zod/Joi 对照

**练习任务**：
- 找 Form 列 rules
- 写校验对照表
- 手写 Form 示例

**JS/Node 类比**：
- BaseForm≈Zod
- scenarios≈不同 schema 子集

**AI Review 提问**：
- 与 Zod 差异？

**今日产出**：
- Form 对照表
- Form 示例

**今日完成标准**：
- [ ] 能解释 rules/scenarios

---

### Day 5（周五）：Laravel 对比与类比日

**类型**：类比日  
**今日目标**：写 Middleware vs behaviors 对照。

**学习内容**：
- Laravel Middleware 文档

**练习任务**：
- 写 1 页对照笔记
- 完成类比打卡
- 整理白名单初稿

**JS/Node 类比**：
- Laravel middleware≈Yii2 Filter

**AI Review 提问**：
- 对照抓住关键差异吗？

**今日产出**：
- 对照笔记
- 类比打卡

**今日完成标准**：
- [ ] 完成对照
- [ ] 完成打卡

---

### Day 6（周六）：鉴权白名单与路径图

**类型**：项目实战  
**今日目标**：整理 5 个免登录接口与完整路径图。

**学习内容**：
- 鉴权基类
- 白名单配置

**源码阅读**：
- `mall-gateway/frontapi/modules/AuthApiController.php`

**练习任务**：
- 列 5 接口及原因
- 画 index→action 路径
- 标 Filter

**JS/Node 类比**：
- 白名单≈public routes

**AI Review 提问**：
- 免登录接口安全吗？

**今日产出**：
- 白名单文档
- 路径图

**今日完成标准**：
- [ ] 5 接口清晰
- [ ] 路径图完整

---

### Day 7（周日）：验收与预习

**类型**：复盘预习  
**今日目标**：验收并预习 MySQL/AR。

**学习内容**：
- 回顾 Filter/Form
- 预习 ActiveRecord

**练习任务**：
- 勾选验收
- 写总结
- 列 OrderRepository 计划

**JS/Node 类比**：
- 预习≈读下一章

**AI Review 提问**：
- 准备好学数据库吗？

**今日产出**：
- 周总结
- 预习清单

**今日完成标准**：
- [ ] 完成验收
- [ ] 完成自评

---

## 5. JS/Node.js 类比学习（本周总览）

behaviors()≈Express middleware；BaseForm≈Zod；actionXxx≈router handler。

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

- [ ] 生命周期图
- [ ] Filter 链图
- [ ] Laravel 对照
- [ ] 白名单文档

---

## 7. 推荐学习资料

- Yii2 权威指南
- Laravel Middleware

---

## 8. 本周验收标准

- [ ] 能画 Filter 链
- [ ] 能解释 Module 路由
- [ ] 完成 Laravel 对照

---

## 9. AI Review 提示词

```text
我正在进行 Week 02：Yii2 生命周期与 Filter 的学习。
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

**下周预习**：预习 MySQL 索引、JOIN；准备读 OrderRepository。
