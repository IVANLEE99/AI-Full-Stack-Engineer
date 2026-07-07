# Week 12 Day 07：阶段③验收与 FastAPI 预习

> 所属周：Week 12：跨服务调用 / InternalService / 全链路串联  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`全部后端`  
> 类型：复盘预习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

完成第三阶段验收，确认自己已经能从 BFF、TP8、订单、支付、售后多个服务视角理解跨服务调用，并能解释 `PayInternal`、`InternalServiceHelper`、服务间鉴权、超时/重试/熔断、错误传递和 `trace_id/request_id`。同时预习下一阶段 FastAPI，为后续 AI 后端学习做准备。

今天你要真正掌握这一句话：

> 第三阶段验收的核心不是背服务名，而是能独立画出一条跨服务业务链路，并说清每一跳的职责、风险、错误处理和可观测性。

---

## 0. 今日学习路线

1. 回顾 Week 05-12 的主题
2. 按能力维度做第三阶段自评
3. 重新画一条完整跨服务链路
4. 检查是否能解释 `PayInternal` 与 `InternalServiceHelper`
5. 检查是否能解释鉴权、timeout、retry、熔断、幂等
6. 检查是否能解释错误传递和 trace_id/request_id
7. 完成阶段③总结
8. 预习 FastAPI：理解它和 PHP/Laravel/TP8/NestJS 的位置关系
9. 用 AI Review 判断是否可以进入下一阶段

---

## 1. 学习内容

### 1.1 第三阶段学了什么？

第三阶段核心是业务域深入和跨服务协作。

| 周次 | 主题 | 核心能力 |
|---|---|---|
| Week 09 | 用户服务 | 登录、注册链、JWT、缓存 |
| Week 10 | 售后服务 | 售后状态机、策略模式、Console、回调 |
| Week 11 | TP8 门店 API | TP8 分层、Validate、ModelJoin、Internal Helper |
| Week 12 | 跨服务串联 | PayInternal、InternalServiceHelper、全链路追踪 |

如果 Week 05-08 是“理解网关和微服务基础”，Week 09-12 就是“把真实业务域串起来”。

---

### 1.2 阶段③验收能力表

请按 0-4 分自评：

```text
0 = 完全不懂
1 = 看过但说不清
2 = 跟着教程能理解
3 = 能独立解释并画图
4 = 能结合项目代码排查问题
```

| 能力项 | 自评分 0-4 | 证据 | 需要补什么 |
|---|---:|---|---|
| BFF 职责 |  |  |  |
| TP8 分层 |  |  |  |
| 订单链路 |  |  |  |
| 支付链路 |  |  |  |
| 售后链路 |  |  |  |
| `PayInternal` |  |  |  |
| `InternalServiceHelper` |  |  |  |
| 服务间鉴权 |  |  |  |
| timeout/retry/熔断 |  |  |  |
| 错误传递 |  |  |  |
| trace_id/request_id |  |  |  |
| MQ/Webhook |  |  |  |

没有证据的高分要降级。

---

### 1.3 阶段验收必须能画的一条链路

请选择一条：

1. 结账支付链路
2. 查询支付状态链路
3. 门店发起售后链路
4. 退款链路
5. Webhook 回调更新订单/售后链路

记录模板：

| 节点 | 记录 |
|---|---|
| 前端入口 |  |
| BFF Controller/Request |  |
| TP8/订单 Service |  |
| Internal Client/Helper |  |
| 下游服务 |  |
| MQ/Webhook |  |
| 鉴权方式 |  |
| timeout/retry |  |
| 错误传递 |  |
| trace_id/request_id |  |
| 我不懂的地方 |  |

---

### 1.4 阶段总结模板

```markdown
# 第三阶段学习总结

## 1. 我已经掌握的内容

## 2. 我能独立画出的链路

## 3. 我能解释的跨服务风险

## 4. 我还不熟的内容

## 5. 我读通的一条真实/模拟链路

## 6. PHP 与 Node.js 在微服务调用上的类比

## 7. 下一阶段 FastAPI 学习计划
```

建议不少于 800 字。

---

### 1.5 进入下一阶段前的最低标准

你至少要能回答：

| 问题 | 最低达标答案 |
|---|---|
| BFF 做什么？ | 鉴权、参数适配、转发/聚合，不写核心业务 |
| `PayInternal` 做什么？ | 封装订单/核心服务到支付服务的内网调用 |
| `InternalServiceHelper` 做什么？ | TP8 门店服务调用其他内网服务的统一 Helper |
| 内网为什么要鉴权？ | 防止未授权服务调用高风险接口 |
| timeout 为什么必须有？ | 防止下游慢拖垮上游 |
| 支付能随便重试吗？ | 不能，必须结合幂等键 |
| trace_id 是什么？ | 串联一次请求经过的所有服务日志 |
| request_id 是什么？ | 标识某个服务的一次请求或某一跳调用 |
| 下游错误能直接给用户吗？ | 不能，要转换成用户友好错误，原始错误进日志 |

---

### 1.6 FastAPI 预习：它是什么？

FastAPI 是 Python 生态里的现代 Web API 框架，常用于：

- AI 后端接口
- 模型推理服务
- 内部工具 API
- 数据处理服务
- 微服务接口

你可以先类比：

| PHP/Node 概念 | FastAPI 类比 |
|---|---|
| Laravel/TP8 Controller | FastAPI route function |
| Validate / FormRequest | Pydantic model |
| Service 层 | Python service module/class |
| Composer | pip/poetry/uv |
| PHP type hint | Python type hints |
| BFF/微服务 API | FastAPI app/router |

---

### 1.7 为什么下一阶段学 FastAPI？

因为 AI 后端常见结构是：

```text
前端 / BFF
  ↓
业务后端 PHP/Node
  ↓
AI 服务 FastAPI
  ↓
模型 / 向量库 / 工具调用
```

你前面学的跨服务能力仍然有用：

| 已学能力 | 在 FastAPI 阶段怎么用 |
|---|---|
| Internal Client | PHP/Node 调 AI 服务 |
| 服务间鉴权 | 保护模型接口 |
| timeout/retry | 模型推理可能慢，必须控制 |
| trace_id | 串联业务请求和 AI 请求 |
| 错误传递 | 模型失败要转成业务提示 |
| 架构文档 | 说明 AI 服务边界 |

---

### 1.8 3 天补弱计划

如果自评发现薄弱，可以按下面补：

| 天数 | 主题 | 任务 |
|---|---|---|
| Day 1 | 跨服务调用 | 重读 `PayInternal` 和 `InternalServiceHelper`，各填一张调用表 |
| Day 2 | 稳定性策略 | 为 capture/refund/status 三类接口写 timeout/retry/幂等策略 |
| Day 3 | 全链路排障 | 选择“支付后订单未成功”，用 trace_id 写完整排障流程 |

---

## 2. 源码阅读

本日无新增指定源码，重点回看：

- `mall-core/common/api/PayInternal.php`
- `store-api/app/common/library/helper/InternalServiceHelper.php`
- `mall-gateway/services/http/PayRequest.php`
- `mall-gateway/services/http/OrderRequest.php`
- 支付、订单、售后相关链路笔记

---

## 3. 练习任务

### 练习 1：完成阶段③自评表

至少覆盖 12 个能力项。

### 练习 2：画一条完整跨服务链路

要求包含：

- BFF
- TP8/订单/核心服务
- Internal Client
- 支付/售后服务
- MQ/Webhook 可选
- trace_id/request_id
- 错误传递

### 练习 3：写阶段总结

不少于 800 字。

### 练习 4：FastAPI 预习笔记

回答：

```text
FastAPI 适合做什么？
它和 Laravel/TP8/NestJS 的类比是什么？
PHP 后端调用 FastAPI AI 服务时要注意什么？
```

---

## 4. JS/Node.js 类比

- 阶段复盘 ≈ milestone review
- FastAPI ≈ Python 生态里的 NestJS/Express API 框架
- Pydantic ≈ Zod/class-validator
- trace_id ≈ correlation id
- Internal Client ≈ axios service client

---

## 5. AI Review 提问

```text
我正在做 Week 12 和第三阶段验收。
我已经完成自评表、跨服务链路图、阶段总结和 FastAPI 预习笔记。
请检查：
1. 我是否具备进入 FastAPI/AI 后端阶段的基础？
2. 哪些跨服务能力证据不足？
3. 我对 PayInternal/InternalServiceHelper 的理解是否准确？
4. 我对 trace_id/request_id 和错误传递是否理解正确？
5. 请给我一个 3 天补弱计划。
```

---

## 6. 今日产出

- [ ] 阶段③自评表
- [ ] 完整跨服务链路图
- [ ] 第三阶段学习总结
- [ ] FastAPI 预习笔记
- [ ] 3 天补弱计划
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 完成阶段③自评表
- [ ] 能画出一条跨服务链路
- [ ] 能解释 `PayInternal` 和 `InternalServiceHelper`
- [ ] 能说明鉴权、timeout、retry、熔断、幂等
- [ ] 能说明错误传递和 trace_id/request_id
- [ ] 写出不少于 800 字阶段总结
- [ ] 完成 FastAPI 预习

---

## 8. 今日自测题

### 8.1 第三阶段验收的核心到底考什么？

参考答案：

> ✅ 不是背服务名，而是能独立画出一条完整的跨服务业务链路（如结账支付、退款），并说清每一跳的职责、风险、错误处理和可观测性（trace_id/request_id）。能画能讲，才算过关。

---

### 8.2 用 0-4 分自评时，为什么“没有证据的高分要降级”？

参考答案：

> ✅ 因为自评容易高估。3 分意味着能独立解释并画图、4 分意味着能结合项目代码排查问题，这些都要有对应的图、笔记或代码证据。没有证据的高分往往是“看过但说不清”，如实降级才能暴露真正要补的短板。

---

### 8.3 请说清 `PayInternal` 和 `InternalServiceHelper` 的区别。

参考答案：

> ✅ 两者都是内网调用的客户端封装，不承载业务逻辑。`PayInternal` 是订单/核心服务专门调用支付服务的 Client（如 capture）；`InternalServiceHelper` 是 TP8 门店服务访问多个内网服务（订单/支付/售后/用户）的统一门面，负责地址、公共 header、鉴权、trace_id、超时和错误规范。

---

### 8.4 FastAPI 在整个技术栈里处于什么位置？和 PHP/Laravel/TP8/NestJS 是什么关系？

参考答案：

> ✅ FastAPI 是 Python 的 Web 框架，定位和 Laravel/TP8（PHP）、NestJS（Node.js）类似，都是用来写 HTTP API 服务的后端框架，同样有路由、请求校验、依赖注入、中间件等概念。学它是为下一阶段 AI 后端做准备，跨服务调用、鉴权、trace_id 这些思想可以直接迁移过去。

---

### 8.5 进入下一阶段前，最低标准是什么？

参考答案：

> ✅ 至少能独立画出并讲清一条完整跨服务链路，能解释 PayInternal、InternalServiceHelper、服务间鉴权、timeout/retry/熔断/幂等、错误传递、trace_id/request_id，并能区分同步 HTTP 与异步 MQ/Webhook 的边界。达不到就先补齐，不要急着开新阶段。

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
我正在进行 Week 12 Day 07：阶段③验收与 FastAPI 预习 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 12 README](./README.md)
