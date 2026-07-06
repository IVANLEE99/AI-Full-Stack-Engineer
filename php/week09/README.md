# Week 09：用户服务 + 注册 Node 链

> 强度建议：约 20h/周（周一到周五各 3h + 周末 5h）  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review  
> 公开说明：使用匿名仓库代号，不包含公司、品牌、内网域名或本地绝对路径。

---

## 1. 本周定位

- 阶段：第三阶段：业务域深入
- 主仓库/项目：`user-service`
- 本周目标：理解登录、注册链、OAuth、缓存。

### 为什么本周要学这些

- 用户域连接鉴权与订单归属。

---

## 2. 本周需要掌握的知识点

1. UserController
2. RegisterService
3. OAuth
4. JWT
5. Redis 缓存

### php-pro 能力对齐

- 登录关注安全
- 缓存不失效权限

---

## 3. 必读代码/文件路径

- `user-service/user-api/controllers/UserController.php`
- `user-service/common/services/user/RegisterService.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 4. 七天详细学习安排

| 天 | 类型 | 主题 |
|----|------|------|
| Day 1（周一） | 概念入门 | UserController 与 quickLogin |
| Day 2（周二） | 源码阅读 | UserService 与 Redis 缓存 |
| Day 3（周三） | 编码练习 | 注册 Node 链 |
| Day 4（周四） | 架构理解 | OAuth 第三方登录 |
| Day 5（周五） | 类比日 | JWT 与类比日 |
| Day 6（周六） | 项目实战 | 用户域总结项目 |
| Day 7（周日） | 复盘预习 | 验收与预习 |

### Day 1（周一）：UserController 与 quickLogin

**类型**：概念入门  
**今日目标**：追踪用户登录入口。

**学习内容**：
- 复习用户域结构
- 理解 client/internal 控制器

**源码阅读**：
- `user-service/user-api/controllers/UserController.php`

**练习任务**：
- 读 UserController
- 追踪 actionQuickLogin 全链路
- 记录 code/data/info

**JS/Node 类比**：
- quickLogin≈社交/快捷登录 API

**AI Review 提问**：
- 链路完整吗？

**今日产出**：
- 登录链路笔记

**今日完成标准**：
- [ ] 能追踪 quickLogin

---

### Day 2（周二）：UserService 与 Redis 缓存

**类型**：源码阅读  
**今日目标**：理解用户缓存读写与失效。

**学习内容**：
- Redis 缓存策略
- 读 UserService getUserInfoByRedis

**源码阅读**：
- `user-service/common/redis/user/UserDetailRedis.php`

**练习任务**：
- 读 UserDetailRedis
- 列缓存 key 规则
- 说明何时写入/失效

**JS/Node 类比**：
- UserDetailRedis≈ioredis 用户缓存封装

**AI Review 提问**：
- 缓存失效策略合理吗？

**今日产出**：
- 缓存策略笔记

**今日完成标准**：
- [ ] 能说明失效策略

---

### Day 3（周三）：注册 Node 链

**类型**：编码练习  
**今日目标**：理解 RegisterService 与 nodes 目录。

**学习内容**：
- 复习 NodeExecutionEngine
- 读 RegisterService

**源码阅读**：
- `user-service/common/services/user/RegisterService.php`

**练习任务**：
- 列出注册前 5 个 Node
- 画注册 Node 顺序图

**JS/Node 类比**：
- 注册链≈多步 middleware

**AI Review 提问**：
- Node 顺序正确吗？

**今日产出**：
- 注册 Node 图

**今日完成标准**：
- [ ] 能列 5 个 Node

---

### Day 4（周四）：OAuth 第三方登录

**类型**：架构理解  
**今日目标**：理解社交登录回调流程。

**学习内容**：
- OAuth 2.0 基础
- 浏览 login 相关 Service

**练习任务**：
- 梳理 Google/Facebook 登录回调
- 对比前端社交登录 SDK

**JS/Node 类比**：
- OAuth≈Passport.js 策略

**AI Review 提问**：
- 回调安全注意什么？

**今日产出**：
- OAuth 流程笔记

**今日完成标准**：
- [ ] 能说明回调流程

---

### Day 5（周五）：JWT 与类比日

**类型**：类比日  
**今日目标**：学习 JWT，对比 Session，完成打卡。

**学习内容**：
- jwt.io 介绍
- 找 token 生成与验证代码

**练习任务**：
- 对比 JWT vs Session
- 完成类比打卡

**JS/Node 类比**：
- JWT≈无状态 token

**AI Review 提问**：
- 何时用 JWT？

**今日产出**：
- JWT 笔记
- 类比打卡

**今日完成标准**：
- [ ] 能说明 JWT 用途

---

### Day 6（周六）：用户域总结项目

**类型**：项目实战  
**今日目标**：完成注册 Node 图与用户域总结。

**学习内容**：
- 整理本周笔记

**练习任务**：
- 完善注册 Node 图
- 写 1 页用户域总结

**JS/Node 类比**：
- 总结≈知识沉淀

**AI Review 提问**：
- 总结是否可复用？

**今日产出**：
- 用户域总结

**今日完成标准**：
- [ ] Node 图完成

---

### Day 7（周日）：验收与预习

**类型**：复盘预习  
**今日目标**：验收并预习售后。

**学习内容**：
- 预习 AfterSaleService

**练习任务**：
- 勾选验收
- 写总结

**JS/Node 类比**：
- 准备好学售后吗？

**AI Review 提问**：
- 周总结

**今日产出**：
- 完成验收

**今日完成标准**：

---

## 5. JS/Node.js 类比学习（本周总览）

注册链≈多步 middleware；缓存≈ioredis。

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

- [ ] 注册 Node 图
- [ ] 缓存策略笔记

---

## 7. 推荐学习资料

- JWT 介绍
- 用户服务源码

---

## 8. 本周验收标准

- [ ] 能说明缓存失效
- [ ] 完成 Node 图

---

## 9. AI Review 提示词

```text
我正在进行 Week 09：用户服务 + 注册 Node 链 的学习。
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

**下周预习**：预习售后策略模式。
