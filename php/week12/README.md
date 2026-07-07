# Week 12：跨服务调用 / InternalService / 全链路串联

> 强度建议：约 20h/周（周一到周五各 3h + 周末 5h）  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review  
> 公开说明：使用匿名仓库代号，不包含公司、品牌、内网域名或本地绝对路径。

---

## 1. 本周定位

- 阶段：第三阶段：业务域深入
- 主仓库/项目：`全部后端`
- 本周目标：把 BFF、TP8、订单、支付、售后服务串成完整跨服务链路，理解 `PayInternal`、`InternalServiceHelper`、服务间 HTTP 调用、鉴权、超时/重试/熔断、错误传递和 `trace_id/request_id`。

### 为什么本周要学这些

前面已经分别学过：

- Week 05：BFF 网关、HTTP Client、薄 Controller
- Week 08：支付、Webhook、MQ、幂等
- Week 10：售后服务、状态机、支付回调
- Week 11：TP8 门店 API、`InternalServiceHelper`

本周要做的是“串起来”：

```text
前端
  ↓
BFF / mall-gateway
  ↓
TP8 / mall-core / order-service
  ↓
PayInternal / InternalServiceHelper
  ↓
pay-service / aftersale-service / user-service
  ↓
MQ / Webhook / DB / 第三方渠道
```

你要从“会看单个服务”升级到“能看跨服务协作”。

---

## 2. 本周需要掌握的知识点

1. `PayInternal`：订单/核心服务调用支付服务的 Internal Client
2. `InternalServiceHelper`：TP8 门店服务调用内网服务的统一 Helper
3. 服务间 HTTP 调用：baseURL、path、method、headers、body
4. 服务间鉴权：internal token、HMAC、timestamp、signature
5. 超时 / 重试 / 熔断：稳定性与雪崩保护
6. 幂等：支付、退款、售后高风险操作的重复调用保护
7. 错误传递：下游错误如何转换成上游/用户可理解错误
8. `trace_id / request_id`：全链路日志追踪
9. BFF → TP8 → 支付/售后服务的完整链路图
10. 架构文档与排障流程

### php-pro 能力对齐

- 禁止业务 Service 到处写裸 HTTP 请求
- 跨服务调用必须走统一 Client/Helper
- 支付、退款、售后必须关注幂等和错误处理中间态
- 内网接口也要鉴权和记录调用方
- 所有跨服务调用要有 timeout
- 高风险操作不能盲目 retry
- 日志必须包含 `trace_id`、`request_id`、业务 ID
- 架构图和文档必须脱敏

---

## 3. 必读代码/文件路径

- `mall-core/common/api/PayInternal.php`
- `store-api/app/common/library/helper/InternalServiceHelper.php`
- `mall-gateway/services/http/PayRequest.php`
- `mall-gateway/services/http/OrderRequest.php`
- `pay-service/pay-api/controllers/outer/StripeController.php`
- `aftersale-service/common/services/AfterSaleService.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 4. 七天详细学习安排

| 天 | 类型 | 主题 |
|----|------|------|
| [Day 1（周一）](./day01.md) | 概念入门 | PayInternal 跨服务调用 |
| [Day 2（周二）](./day02.md) | 源码阅读 | InternalServiceHelper |
| [Day 3（周三）](./day03.md) | 编码练习 | 从 BFF 到 TP8 到支付/售后的全链路追踪 |
| [Day 4（周四）](./day04.md) | 架构理解 | 服务间鉴权 / 超时 / 重试 / 熔断 |
| [Day 5（周五）](./day05.md) | 类比日 | 错误传递 / trace_id / request_id 类比日 |
| [Day 6（周六）](./day06.md) | 项目实战 | 跨服务架构文档项目 |
| [Day 7（周日）](./day07.md) | 复盘预习 | 阶段③验收与 FastAPI 预习 |

### Day 1（周一）：PayInternal 跨服务调用

**类型**：概念入门  
**今日目标**：理解 `PayInternal` 如何封装订单/核心服务对支付服务的内网 HTTP 调用。

**学习内容**：
- 为什么 Service 不应该到处写裸 HTTP
- `PayInternal` 的职责
- 服务间 HTTP 的 baseURL/path/method/headers/body
- `capture` 调用的参数、返回和错误处理
- 服务间鉴权、timeout、trace_id

**源码阅读**：
- `mall-core/common/api/PayInternal.php`

**练习任务**：
- 读 `PayInternal`
- 追踪一次 `capture`
- 记录 path/参数/headers/返回
- 判断 capture 是否适合自动重试

**JS/Node 类比**：
- `PayInternal` ≈ `payServiceClient.post('/internal/pay/capture')`

**今日产出**：
- `capture` 调用笔记
- 服务间 HTTP 调用要素表

**今日完成标准**：
- [ ] 能追踪 `capture`
- [ ] 能解释 `PayInternal` 不是支付业务本身，而是支付服务 client

---

### Day 2（周二）：InternalServiceHelper

**类型**：源码阅读  
**今日目标**：理解 TP8 `store-api` 如何通过 `InternalServiceHelper` 调用内网服务。

**学习内容**：
- 门店与核心服务、订单、支付、售后的关系
- Helper 和 Service 的职责边界
- 可调用服务清单
- 公共 headers、鉴权、trace_id、timeout
- Helper 上帝类风险

**源码阅读**：
- `store-api/app/common/library/helper/InternalServiceHelper.php`

**练习任务**：
- 读 `InternalServiceHelper`
- 列 public 方法清单
- 列可调用服务
- 画 Helper 调用流程

**JS/Node 类比**：
- `InternalServiceHelper` ≈ `internalServiceClient`

**今日产出**：
- 服务清单
- Helper 调用流程图

**今日完成标准**：
- [ ] 能列出内网服务
- [ ] 能说明 Helper 不应该承载业务规则

---

### Day 3（周三）：从 BFF 到 TP8 到支付/售后的全链路追踪

**类型**：编码练习  
**今日目标**：画一次结账/支付/售后请求的完整跨服务时序图。

**学习内容**：
- 合并 Week 05、08、10、11 成果
- 同步 HTTP 与异步 MQ/Webhook 边界
- 每一跳 path、参数、headers、timeout、错误处理
- trace_id 全链路传递

**练习任务**：
- 画 BFF → TP8/订单 → 支付/售后服务时序图
- 每一跳填表
- 标注 trace_id/request_id
- 标注 MQ/Webhook 异步节点

**JS/Node 类比**：
- 全链路 ≈ Express BFF → NestJS service → axios internal client → payment service

**今日产出**：
- 结账全链路图
- 每一跳调用表

**今日完成标准**：
- [ ] 能标注每一跳
- [ ] 能区分同步与异步

---

### Day 4（周四）：服务间鉴权 / 超时 / 重试 / 熔断

**类型**：架构理解  
**今日目标**：掌握跨服务调用的安全和稳定性策略。

**学习内容**：
- internal token / HMAC / timestamp / signature
- timeout 为什么必须有
- retry 为什么必须结合幂等
- 熔断如何保护上游
- 支付、退款、售后接口策略差异

**练习任务**：
- 写接口策略表
- 判断 capture/refund/status 是否能重试
- 设计一组服务间 headers

**JS/Node 类比**：
- HMAC ≈ axios interceptor 加签
- 熔断 ≈ circuit breaker pattern
- 幂等键 ≈ Stripe Idempotency-Key

**今日产出**：
- 服务间调用策略表
- 幂等键设计说明

**今日完成标准**：
- [ ] 能解释 timeout/retry/熔断区别
- [ ] 能判断支付/退款不能盲目重试

---

### Day 5（周五）：错误传递 / trace_id / request_id 类比日

**类型**：类比日  
**今日目标**：理解跨服务错误如何传递，日志如何通过 trace_id 串联。

**学习内容**：
- 用户可见错误、业务错误、系统错误、下游原始错误
- 统一错误结构
- `trace_id`、`request_id`、业务 ID 区别
- HTTP/MQ/Webhook 中如何传递 trace_id
- 排障日志字段设计

**练习任务**：
- 设计支付超时错误结构
- 写全链路日志规范草案
- 画错误传递链路

**JS/Node 类比**：
- `trace_id` ≈ correlation id
- 统一错误结构 ≈ error middleware normalized response

**今日产出**：
- 错误结构设计
- 日志规范草案

**今日完成标准**：
- [ ] 能区分 trace_id/request_id/业务 ID
- [ ] 能说明下游错误如何转换给用户

---

### Day 6（周六）：跨服务架构文档项目

**类型**：项目实战  
**今日目标**：输出跨服务调用与全链路追踪架构文档 v1。

**学习内容**：
- 架构文档模板
- 服务角色总览
- 服务调用关系图
- Internal Client 清单
- 结账支付链路和售后/退款链路
- 排障流程
- 脱敏检查

**练习任务**：
- 写架构文档 v1
- 完成 Internal Client 清单
- 写一条排障流程
- 检查文档是否脱敏

**JS/Node 类比**：
- 架构文档 ≈ onboarding doc + incident runbook

**今日产出**：
- 跨服务架构文档 v1
- 排障流程

**今日完成标准**：
- [ ] 文档覆盖 BFF、TP8、订单、支付、售后
- [ ] 文档可公开、已脱敏

---

### Day 7（周日）：阶段③验收与 FastAPI 预习

**类型**：复盘预习  
**今日目标**：完成第三阶段验收，并预习 FastAPI。

**学习内容**：
- 阶段③能力自评
- 完整跨服务链路复画
- `PayInternal` / `InternalServiceHelper` 复盘
- FastAPI 基础定位

**练习任务**：
- 完成阶段③自评表
- 画一条完整跨服务链路
- 写阶段总结
- 写 FastAPI 预习笔记

**JS/Node 类比**：
- FastAPI ≈ Python 生态里的 NestJS/Express API 框架
- Pydantic ≈ Zod/class-validator

**今日产出**：
- 阶段③自评表
- 第三阶段总结
- FastAPI 预习笔记

**今日完成标准**：
- [ ] 能说明是否具备进入 FastAPI/AI 后端阶段的基础

---

## 5. JS/Node.js 类比学习（本周总览）

| PHP / 架构概念 | JS/Node.js 类比 | 差异 |
|---|---|---|
| `PayInternal` | `payServiceClient` | PHP 可能用静态类/Helper，Node 常用 axios instance |
| `InternalServiceHelper` | `internalServiceClient` | Helper 容易变大，需关注边界 |
| 服务间鉴权 | axios interceptor 加签 | 签名算法和密钥管理要看项目 |
| timeout | axios timeout / AbortController | PHP worker 更怕长期阻塞 |
| retry | axios-retry | 支付/退款必须结合幂等 |
| 熔断 | circuit breaker | PHP 项目可能通过组件或网关实现 |
| trace_id | correlation id | 要贯穿 HTTP/MQ/Webhook |
| 错误传递 | error middleware | 下游 raw error 不应直接给用户 |

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

- [ ] `PayInternal::capture()` 调用笔记
- [ ] `InternalServiceHelper` 服务清单
- [ ] BFF → TP8 → 支付/售后全链路图
- [ ] 服务间调用策略表
- [ ] 错误传递与日志规范草案
- [ ] 跨服务架构文档 v1
- [ ] 阶段③自评与总结
- [ ] FastAPI 预习笔记

---

## 7. 推荐学习资料

- Building Microservices：服务边界、BFF、稳定性
- Release It!：超时、熔断、稳定性模式
- Stripe API Idempotency：幂等键设计
- OpenTelemetry 概念：trace/span/correlation id
- FastAPI 官方文档：下一阶段预习

---

## 8. 本周验收标准

- [ ] 能解释 `PayInternal` 的职责
- [ ] 能解释 `InternalServiceHelper` 的职责
- [ ] 能画出 BFF → TP8/订单 → 支付/售后服务的链路
- [ ] 能标注每一跳 path、参数、headers、timeout
- [ ] 能说明内网服务为什么要鉴权
- [ ] 能判断哪些接口不能盲目重试
- [ ] 能解释幂等键的作用
- [ ] 能区分 `trace_id`、`request_id`、业务 ID
- [ ] 能说明下游错误如何转换给上游和用户
- [ ] 能写出一份脱敏的跨服务架构文档

---

## 9. AI Review 提示词

```text
我正在进行 Week 12：跨服务调用 / InternalService / 全链路串联 的学习。
我已经整理了 PayInternal、InternalServiceHelper、服务间鉴权、超时/重试/熔断、错误传递、trace_id/request_id，并画了从 BFF 到 TP8 到支付/售后的全链路。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 我的跨服务链路是否完整？
2. BFF、TP8、订单、支付、售后职责是否混淆？
3. 服务间调用策略是否遗漏安全或稳定性风险？
4. trace_id/request_id 和错误传递是否适合线上排障？
5. 架构文档是否脱敏且适合团队 onboarding？

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

**下周预习**：FastAPI。重点先理解 route、Pydantic、依赖注入、异步接口、AI 服务与 PHP/Node 业务后端的跨服务调用。
