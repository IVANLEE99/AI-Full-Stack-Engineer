# Week 05：BFF 网关架构

> 强度建议：约 20h/周（周一到周五各 3h + 周末 5h）  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review  
> 公开说明：使用匿名仓库代号，不包含公司、品牌、内网域名或本地绝对路径。

---

## 1. 本周定位

- 阶段：第二阶段：网关 + 微服务
- 主仓库/项目：`mall-gateway`
- 本周目标：理解 BFF 鉴权、转发、薄 Controller。

### 为什么本周要学这些

- 前端请求先进网关。
- 网关不做核心业务。

---

## 2. 本周需要掌握的知识点

1. BFF
2. HTTP Client
3. 薄 Controller
4. 鉴权公参
5. Laravel HTTP Client

### php-pro 能力对齐

- 网关不写业务
- 跨服务走封装
- 白名单谨慎

---

## 3. 必读代码/文件路径

- `mall-gateway/common/BaseApi.php`
- `mall-gateway/services/http/PayRequest.php`
- `mall-gateway/services/http/OrderRequest.php`
- `mall-gateway/frontapi/modules/Pay/controllers/PayController.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 4. 七天详细学习安排

| 天 | 类型 | 主题 |
|----|------|------|
| Day 1（周一） | 概念入门 | BFF 模式与网关职责 |
| Day 2（周二） | 源码阅读 | HTTP 客户端封装 |
| Day 3（周三） | 编码练习 | 薄 Controller 实践 |
| Day 4（周四） | 架构理解 | 鉴权、公参与反查链路 |
| Day 5（周五） | 类比日 | Laravel 对比与类比日 |
| Day 6（周六） | 项目实战 | 结账 API 路由表项目 |
| Day 7（周日） | 复盘预习 | 验收与预习 |

### Day 1（周一）：BFF 模式与网关职责

**类型**：概念入门  
**今日目标**：理解 BFF 为何存在、网关与微服务边界。

**学习内容**：
- 阅读 BFF 模式与 Building Microservices 相关章节
- 理解 frontapi 与内网服务分工

**源码阅读**：
- `mall-gateway/common/BaseApi.php`

**练习任务**：
- 读 mall-gateway README
- 画 BFF 架构图：前端→网关→微服务
- 列出网关 5 项职责

**JS/Node 类比**：
- BFF≈专为前端定制的 API 层
- 网关≈Express 聚合层

**AI Review 提问**：
- 网关是否承担了不该承担的业务？

**今日产出**：
- BFF 架构图
- 职责清单

**今日完成标准**：
- [ ] 能说明 BFF 价值
- [ ] 能区分网关与微服务

---

### Day 2（周二）：HTTP 客户端封装

**类型**：源码阅读  
**今日目标**：掌握 *Request 类如何转发到内网服务。

**学习内容**：
- 阅读 Yii2 HTTP Client 基础
- 阅读 Laravel HTTP Client 作对照

**源码阅读**：
- `mall-gateway/services/http/PayRequest.php`
- `mall-gateway/services/http/OrderRequest.php`

**练习任务**：
- 读 PayRequest.php 全文
- 读 OrderRequest.php 并对比结构
- 解释 getBaseUrl() 与 path 拼接

**JS/Node 类比**：
- PayRequest≈axios.create({ baseURL })
- BaseApi≈axios 实例基类

**AI Review 提问**：
- 封装层还应包含哪些能力？

**今日产出**：
- PayRequest 笔记
- OrderRequest 对比表

**今日完成标准**：
- [ ] 能解释 HTTP 封装
- [ ] 能说出 baseURL 配置位置

---

### Day 3（周三）：薄 Controller 实践

**类型**：编码练习  
**今日目标**：理解网关 Controller 为何保持精简。

**学习内容**：
- 复习 Module 路由
- 找一个 PayController action 逐行分析

**源码阅读**：
- `mall-gateway/frontapi/modules/Pay/controllers/PayController.php`

**练习任务**：
- 统计 PayController 各 action 行数
- 手写一个「纯转发」action 伪代码
- 说明哪些逻辑不应出现在网关

**JS/Node 类比**：
- 薄 Controller≈只做鉴权+取参+转发
- 业务逻辑≈下游微服务

**AI Review 提问**：
- 我设计的伪代码是否足够薄？

**今日产出**：
- 行数统计表
- 转发 action 伪代码

**今日完成标准**：
- [ ] 能解释「薄」的含义
- [ ] 能举反例

---

### Day 4（周四）：鉴权、公参与反查链路

**类型**：架构理解  
**今日目标**：从前端 API 反查网关到内网完整路径。

**学习内容**：
- 阅读 AuthApiController 中 token 与公参注入
- 从 mall-pc 找一个支付 API

**源码阅读**：
- `mall-gateway/frontapi/modules/AuthApiController.php`

**练习任务**：
- 反查 pay/pay/methods 完整链路
- 画路由链路图
- 标注鉴权与白名单

**JS/Node 类比**：
- 公参注入≈middleware 统一设置 req.context
- 反查≈前端 devtools 追请求

**AI Review 提问**：
- 链路是否完整？白名单是否合理？

**今日产出**：
- 路由链路图
- 白名单初稿

**今日完成标准**：
- [ ] 能独立反查一条 API
- [ ] 能标注 Filter/鉴权

---

### Day 5（周五）：Laravel 对比与类比日

**类型**：类比日  
**今日目标**：完成 Laravel Http vs *Request 对照与类比打卡。

**学习内容**：
- 阅读 Laravel HTTP Client 文档 2h
- 回顾 BFF 笔记

**练习任务**：
- 写 1 页 Laravel Http:: vs *Request 对照
- 完成本周类比打卡

**JS/Node 类比**：
- Laravel Http::≈*Request 封装

**AI Review 提问**：
- 对照是否抓住关键差异？

**今日产出**：
- 对照笔记
- 类比打卡

**今日完成标准**：
- [ ] 完成对照
- [ ] 完成打卡

---

### Day 6（周六）：结账 API 路由表项目

**类型**：项目实战  
**今日目标**：输出至少 5 个结账相关 API 路由表。

**学习内容**：
- 明确路由表字段：前端URL、网关action、HTTP client、目标服务、备注

**练习任务**：
- 完成 5+ API 路由表
- 自测能否根据表反查代码

**JS/Node 类比**：
- 路由表≈API 地图

**AI Review 提问**：
- 路由表是否可用于 onboarding？

**今日产出**：
- 结账 API 路由表

**今日完成标准**：
- [ ] 覆盖 5 个 API
- [ ] 表结构清晰

---

### Day 7（周日）：验收与预习

**类型**：复盘预习  
**今日目标**：验收本周并预习订单域。

**学习内容**：
- 对照验收标准
- 预习 OrderController

**练习任务**：
- 勾选验收
- 写周总结
- 列订单域 3 个目标

**JS/Node 类比**：
- 预习≈提前读下一章

**AI Review 提问**：
- 准备好学订单吗？

**今日产出**：
- 周总结

**今日完成标准**：
- [ ] 完成验收
- [ ] 明确下周目标

---

## 5. JS/Node.js 类比学习（本周总览）

薄 Controller≈Express proxy；*Request≈axios.create。

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

- [ ] API 路由表
- [ ] BFF 边界笔记
- [ ] Laravel HTTP 对照

---

## 7. 推荐学习资料

- Building Microservices
- Laravel HTTP Client

---

## 8. 本周验收标准

- [ ] 能说明网关边界
- [ ] 完成路由表

---

## 9. AI Review 提示词

```text
我正在进行 Week 05：BFF 网关架构 的学习。
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

**下周预习**：预习订单域 OrderController。
