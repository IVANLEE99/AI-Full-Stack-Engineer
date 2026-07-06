# Week 11 Day 05：Yii2 vs TP8 对比

> 所属周：Week 11：ThinkPHP 8 门店 API  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`store-api`  
> 类型：类比日  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

完成 Yii2 CSR 与 TP8 SMVC 的分层对比表，理解两个框架在路由、Controller、Validate/Form、Service、Model、Middleware、配置和内部服务调用上的异同。

今天你要真正掌握这一句话：

> Yii2 和 TP8 写法不同，但工程问题相同：请求入口、参数校验、业务编排、数据查询、鉴权中间件和服务调用都需要清晰分层；你要迁移的是“读代码方法”，不是死记框架 API。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Yii2 CSR 链路
2. 回顾 TP8 SMVC 链路
3. 写路由、Controller、Validate、Service、Model 对照
4. 阅读 `InternalServiceHelper.php`
5. 理解 TP8 项目中内部服务调用如何封装
6. 完成至少 5 项框架对比
7. 完成 JS/Node 类比打卡
8. 总结迁移阅读方法
9. 用 AI Review 检查对比是否准确

---

## 1. 学习内容

### 1.1 Yii2 CSR 回顾

你之前在 Yii2 项目中常见链路：

```text
Controller
  ↓
Service
  ↓
Repository
  ↓
Model / ActiveRecord
```

加上 Form：

```text
Controller → Form rules/scenarios → Service → Repository/Model
```

---

### 1.2 TP8 SMVC 怎么理解？

这里可以把 TP8 项目理解成：

```text
Service + Model + Validate + Controller
```

典型链路：

```text
Route
  ↓
Middleware
  ↓
Controller
  ↓
Validate scene
  ↓
Service
  ↓
Model / ModelJoin
```

---

### 1.3 分层对比表

| 关注点 | Yii2 | TP8 | 共同点 |
|---|---|---|---|
| 路由 | module/controller/action | route 文件/约定路由 | URL 映射到 Controller |
| Controller | `controllers` | `app/.../controller` | 接收请求、调用业务 |
| 参数校验 | Form Model | Validate | 校验输入 |
| 场景分组 | scenarios | scene | 不同接口不同字段 |
| Service | Service | Service | 业务编排 |
| 数据访问 | AR/Repository | Model/ModelJoin | 查询和持久化 |
| 中间件 | behavior/filter | middleware | 鉴权、日志、公参 |
| 配置 | config | config | 环境和业务配置 |

---

### 1.4 InternalServiceHelper 是什么？

`InternalServiceHelper` 这类工具通常封装内部服务调用。

可能职责：

- 拼接内部服务 URL
- 添加内部鉴权 header
- 统一请求超时
- 统一解析响应
- 记录日志
- 处理错误

它可以类比 Week 05 学过的 HTTP Client 封装：

```text
BFF 的 PayRequest / OrderRequest
  ↔
TP8 的 InternalServiceHelper
```

---

### 1.5 框架迁移阅读法

当你进入一个新 PHP 框架项目：

1. 找 README
2. 找入口和路由
3. 找 Controller
4. 找参数校验
5. 找 Service
6. 找 Model/ORM
7. 找中间件/鉴权
8. 找内部服务调用封装
9. 找统一响应和错误处理

这套方法比记住某个框架 API 更重要。

---

### 1.6 Node.js 类比

NestJS 对照：

| 后端通用概念 | NestJS | Yii2 | TP8 |
|---|---|---|---|
| 路由入口 | Controller decorator | route/module | route |
| 参数校验 | DTO/class-validator | Form | Validate |
| 业务层 | Service | Service | Service |
| 数据层 | Repository/Prisma | AR/Repository | Model/ModelJoin |
| 中间件 | Guard/Middleware | Filter/Behavior | Middleware |

---

## 2. 源码阅读

- `store-api/app/common/library/helper/InternalServiceHelper.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| 方法 | 作用 | 类比 Week 05 哪个概念 |
|---|---|---|
|  |  |  |

---

## 3. 练习任务

### 练习 1：写至少 5 项对比

建议完成 8-10 项。

### 练习 2：读 InternalServiceHelper

记录它如何调用内部服务、处理响应和错误。

### 练习 3：完成类比打卡

| 通用概念 | Yii2 | TP8 | Node/NestJS |
|---|---|---|---|
|  |  |  |  |

---

## 4. JS/Node.js 类比

- TP8 SMVC vs Yii2 CSR
- InternalServiceHelper ≈ internal service client
- Validate scene ≈ DTO/schema group
- ModelJoin ≈ repository query object
- Middleware ≈ auth guard / middleware

---

## 5. AI Review 提问

```text
我正在做 Yii2 vs TP8 对比。
我已经写了路由、Controller、Validate/Form、Service、Model/Repository、Middleware、配置、内部服务调用对照表，并阅读了 InternalServiceHelper。
请你检查：
1. 对比是否准确？
2. 哪些概念只是名字不同，本质相同？
3. 哪些概念在 Yii2 和 TP8 中差异最大？
4. InternalServiceHelper 应该如何类比 Week 05 的 HTTP Client 封装？
5. 以后读新 PHP 框架项目应该按什么步骤？
```

---

## 6. 今日产出

- [ ] Yii2 vs TP8 对比表
- [ ] InternalServiceHelper 阅读笔记
- [ ] 通用后端分层类比表
- [ ] 框架迁移阅读方法
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 完成 5 项以上对比
- [ ] 能说明 TP8 SMVC 与 Yii2 CSR 的对应关系
- [ ] 能解释 InternalServiceHelper 的作用
- [ ] 能把 TP8、Yii2、Node/NestJS 做三方类比
- [ ] 能总结新框架项目阅读方法

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
我正在进行 Week 11 Day 05：Yii2 vs TP8 对比 的学习。
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
