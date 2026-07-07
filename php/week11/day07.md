# Week 11 Day 07：验收与预习

> 所属周：Week 11：ThinkPHP 8 门店 API  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`store-api`  
> 类型：复盘预习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

完成 Week 11 ThinkPHP 8 / 框架迁移思维验收，确认自己能从 Yii2 业务域阅读迁移到 TP8 项目，能解释 TP8 路由、Controller、Validate、Model/ORM、Middleware、Service 分层和 Yii2 对照，并预习跨服务调用。

今天你要真正掌握这一句话：

> 框架迁移能力的核心是抽象能力：你能把 Yii2、TP8、Node/NestJS 都看成“路由 → Controller → 校验 → Service → 数据层 → 响应”的不同实现，就能快速读懂新项目。

---

## 0. 今日学习路线

建议按下面顺序完成：

1. 回顾 Week 11 Day 01-06 的笔记
2. 按验收表检查 TP8 核心能力
3. 重新口述 TP8 请求链路
4. 检查自己是否能解释 Validate scene
5. 检查自己是否能解释 Model/ModelJoin
6. 检查自己是否能对比 Yii2 与 TP8
7. 检查自己是否能独立追踪 CRUD
8. 写 Week 11 周总结
9. 预习跨服务调用：PayInternal、InternalServiceHelper、服务间鉴权

---

## 1. 学习内容

### 1.1 Week 11 学了什么？

Week 11 的主题是：

```text
ThinkPHP 8 门店 API + 框架迁移思维
```

本周知识地图：

| 天数 | 主题 | 核心能力 |
|---|---|---|
| Day 01 | TP8 架构 | 理解 TP8 目录和请求链路 |
| Day 02 | StoreController 到 StoreService | 追踪 TP8 CRUD 链路 |
| Day 03 | Validate scene | 理解 TP8 参数校验分组 |
| Day 04 | Model 与 ModelJoin | 理解 ThinkORM 和复杂查询封装 |
| Day 05 | Yii2 vs TP8 对比 | 建立框架迁移对照表 |
| Day 06 | store-api CRUD 项目 | 独立追踪一条接口 |

---

### 1.2 Week 11 验收表

请填写：

| 能力项 | 自评分 0-4 | 证据 | 需要补什么 |
|---|---:|---|---|
| 能说明 TP8 项目结构 |  |  |  |
| 能找到 Route/Controller/Validate/Service/Model |  |  |  |
| 能追踪 StoreController 到 StoreService |  |  |  |
| 能解释 Validate scene |  |  |  |
| 能对比 Yii2 Form scenarios |  |  |  |
| 能解释 Model 与 ModelJoin |  |  |  |
| 能说明复杂查询为什么不放 Controller |  |  |  |
| 能写 Yii2 vs TP8 对照表 |  |  |  |
| 能独立追踪一个 CRUD 接口 |  |  |  |
| 能用 Node/NestJS 做分层类比 |  |  |  |

评分标准：

```text
0 = 完全不懂
1 = 看过但说不清
2 = 能跟着教程理解
3 = 能独立解释并完成练习
4 = 能在真实项目中熟练应用
```

---

### 1.3 TP8 主链路口述稿

请尝试口述：

```text
HTTP 请求进入 TP8 项目。
路由把 URL 映射到 Controller action。
Middleware 负责鉴权、权限、公参、日志等横切逻辑。
Controller 获取请求参数，选择 Validate scene 校验。
校验通过后调用 Service。
Service 负责编排业务规则，调用 Model 或 ModelJoin。
Model/ThinkORM 负责单表或关联查询。
最终 Controller 使用统一响应格式返回 code/data/msg。
```

---

### 1.4 框架迁移检查清单

以后读任何 PHP 框架项目，先问：

| 问题 | 目标 |
|---|---|
| 入口在哪里？ | 找 public/index.php / route |
| 路由在哪里？ | URL 如何到 Controller |
| Controller 放哪里？ | 接收请求的类 |
| 参数校验在哪里？ | Validate/Form/Request |
| 业务层在哪里？ | Service/use case |
| 数据层在哪里？ | Model/Repository/ORM |
| 中间件在哪里？ | 鉴权、权限、日志 |
| 统一响应在哪里？ | BaseController/helper |
| 内部服务调用怎么封装？ | HTTP client/InternalServiceHelper |

---

### 1.5 预习跨服务调用

下周将学习跨服务调用。

你可以提前关注：

| 主题 | 预习问题 |
|---|---|
| PayInternal | 内部支付服务接口如何调用？ |
| InternalServiceHelper | 如何封装内网 HTTP 请求？ |
| 服务间鉴权 | 内部服务如何证明调用方可信？ |
| 超时重试 | 内部调用失败怎么办？ |
| 错误传递 | 下游错误如何返回给上游？ |
| 链路追踪 | trace_id 如何贯穿多个服务？ |

这会把前面的 BFF、订单、支付、售后、TP8 项目串成跨服务全链路。

---

### 1.6 Week 11 周总结模板

请写不少于 500 字总结：

```markdown
# Week 11 ThinkPHP 8 / 框架迁移思维周总结

## 1. 我理解的 TP8 项目结构

## 2. TP8 请求链路

## 3. Validate scene 与 Yii2 Form scenarios 对比

## 4. Model/ModelJoin 与 Repository 思维

## 5. Yii2 vs TP8 vs Node/NestJS 分层对照

## 6. 我独立追踪的一条 CRUD 链路

## 7. 我还不清楚的 3 个问题

## 8. 下周跨服务调用学习目标
```

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议预习：

- PayInternal 相关代码
- InternalServiceHelper
- 内部服务调用配置
- 服务间鉴权 header
- trace_id / request_id 相关逻辑

记录：

| 预习对象 | 你观察到的内容 | 下周想解决的问题 |
|---|---|---|
| PayInternal |  |  |
| InternalServiceHelper |  |  |
| 服务间鉴权 |  |  |

---

## 3. 练习任务

### 练习 1：勾选验收

完成 Week 11 验收表，每项写自评分和证据。

### 练习 2：写总结

不少于 500 字，包含：

- TP8 结构
- 请求链路
- Validate scene
- ModelJoin
- Yii2 vs TP8
- CRUD 链路
- 跨服务预习目标

### 练习 3：列跨服务调用 3 个预习目标

| 目标 | 为什么重要 |
|---|---|
| 理解 InternalServiceHelper | 内部 HTTP 调用封装 |
| 理解服务间鉴权 | 防止内部接口被伪造调用 |
| 理解 trace_id | 多服务排查问题必备 |

---

## 4. JS/Node.js 类比

- TP8 验收 ≈ 新框架 onboarding milestone
- Validate scene ≈ DTO/schema group
- ModelJoin ≈ repository query object
- InternalServiceHelper ≈ internal service client
- 跨服务调用 ≈ microservice-to-microservice HTTP/RPC call

---

## 5. AI Review 提问

```text
我正在做 Week 11 ThinkPHP 8 / 框架迁移思维验收。
我已经整理 TP8 路由、Controller、Validate、Service、Model/ModelJoin、Middleware、Yii2 对照和一个 CRUD 全链路。
请你检查：
1. 我是否真正掌握了框架迁移阅读方法？
2. Yii2 与 TP8 的对照是否准确？
3. Validate scene 和 ModelJoin 是否理解到位？
4. 我独立追踪 CRUD 链路是否完整？
5. 下周跨服务调用应该重点关注哪些风险？

请用中文输出：验收结论、薄弱项、补强建议、跨服务调用学习重点。
```

---

## 6. 今日产出

- [ ] Week 11 验收表
- [ ] TP8 周总结
- [ ] Yii2 vs TP8 vs Node 分层对照表
- [ ] CRUD 全链路复盘
- [ ] 跨服务调用 3 个预习目标
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 完成 Week 11 验收
- [ ] 能说明 TP8 Route/Controller/Validate/Service/Model
- [ ] 能解释 Validate scene
- [ ] 能解释 Model/ModelJoin
- [ ] 能完成 Yii2 与 TP8 对照
- [ ] 能独立追踪一个 CRUD 接口
- [ ] 明确下周跨服务调用学习目标

---

## 8. 今日自测题

### 8.1 Week 11 的整体主题是什么？

参考答案：

> ✅ ThinkPHP 8 门店 API + 框架迁移思维。核心是把 Yii2 阶段积累的业务域阅读方法迁移到 TP8，并能和 Node/NestJS 做类比。

---

### 8.2 请口述一条完整的 TP8 请求主链路。

参考答案：

> ✅ HTTP 请求进入项目 → 路由把 URL 映射到 Controller action → Middleware 做鉴权/权限/公参/日志 → Controller 取参并选择 Validate scene 校验 → Service 编排业务 → Model/ModelJoin 查询数据 → Controller 用统一格式返回 code/data/msg。

---

### 8.3 框架迁移能力的核心是什么？

参考答案：

> ✅ 抽象能力。能把 Yii2、TP8、Node/NestJS 都看成“路由 → Controller → 校验 → Service → 数据层 → 响应”的不同实现，就能快速读懂新项目，而不是死记某个框架的 API。

---

### 8.4 读一个陌生 PHP 框架项目时，应该先问哪几个问题？

参考答案：

> ✅ 入口在哪里、路由在哪里、Controller 放哪里、参数校验在哪里、业务层在哪里、数据层在哪里、中间件在哪里、统一响应在哪里、内部服务调用怎么封装。

---

### 8.5 下周跨服务调用需要重点关注哪些主题？

参考答案：

> ✅ PayInternal 内部支付接口调用、InternalServiceHelper 内网 HTTP 封装、服务间鉴权、超时重试、错误传递、trace_id 链路追踪。

---

## 9. 学习记录

| 记录项 | 内容 |
|--------|------|
| 今日最清楚的概念 |  |
| 今日最卡的概念 |  |
| JS/Node 类比是否帮助理解 |  |
| 实际耗时 |  |
| 明日要补的问题 |  |

---

## 10. AI Review 提示词

```text
我正在进行 Week 11 Day 07：验收与预习 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 11 README](./README.md)
