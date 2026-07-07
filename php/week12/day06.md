# Week 12 Day 06：跨服务架构文档项目

> 所属周：Week 12：跨服务调用 / InternalService / 全链路串联  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`全部后端`  
> 类型：项目实战  
> 建议时长：约 5h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

输出一份“跨服务调用与全链路追踪架构文档 v1”，把 BFF、TP8、订单、支付、售后、`PayInternal`、`InternalServiceHelper`、服务间鉴权、超时/重试/熔断、错误传递、trace_id/request_id 全部串起来。

今天你要真正掌握这一句话：

> 架构文档不是画漂亮图，而是让新人能按图追踪代码、让开发能知道边界、让线上问题能按 trace_id 定位到具体服务和具体请求。

---

## 0. 今日学习路线

1. 先确定文档读者：新人、自己、排障同事、架构评审
2. 写系统总览：BFF、TP8、订单、支付、售后分别做什么
3. 写服务调用图：谁调用谁
4. 写 Internal Client 清单：`PayInternal`、`InternalServiceHelper`
5. 写安全策略：服务间鉴权、签名、headers
6. 写稳定性策略：timeout、retry、熔断、幂等
7. 写错误传递规范：错误码、用户 message、下游 raw error
8. 写 trace 规范：trace_id/request_id/业务 ID
9. 做脱敏检查，确保文档可公开学习使用

---

## 1. 学习内容

### 1.1 架构文档应该解决什么问题？

一份好的跨服务架构文档，至少能回答：

| 问题 | 文档中对应章节 |
|---|---|
| 前端请求先到哪里？ | BFF 职责 |
| BFF 调哪些服务？ | 服务调用图 |
| 订单如何调用支付？ | `PayInternal` |
| TP8 门店如何调用内网服务？ | `InternalServiceHelper` |
| 内网接口怎么鉴权？ | 服务间鉴权 |
| 下游超时怎么办？ | timeout/retry/熔断 |
| 错误怎么传给前端？ | 错误传递 |
| 日志怎么串起来？ | trace_id/request_id |
| 支付/售后异步怎么处理？ | MQ/Webhook |

---

### 1.2 推荐文档结构

你可以直接按这个模板写：

```markdown
# 跨服务调用与全链路追踪架构文档 v1

## 1. 文档目标

## 2. 系统角色总览

## 3. 服务调用关系图

## 4. 关键 Internal Client

## 5. 典型链路：结账支付

## 6. 典型链路：售后/退款

## 7. 服务间鉴权规范

## 8. 超时 / 重试 / 熔断 / 幂等策略

## 9. 错误传递规范

## 10. trace_id / request_id / 业务 ID 规范

## 11. 排障流程

## 12. 脱敏说明

## 13. 待补问题
```

---

### 1.3 系统角色总览怎么写？

示例：

| 系统 | 职责 | 不应该做什么 |
|---|---|---|
| 前端 | 展示页面、发起用户操作 | 直接调用内网服务 |
| BFF / 网关 | 鉴权、参数适配、转发、聚合 | 写支付/订单核心逻辑 |
| TP8 / store-api | 门店业务 API、门店数据操作 | 直接散落裸 HTTP 调用 |
| order/mall-core | 订单业务、结账编排 | 处理支付渠道细节 |
| pay-service | 支付单、支付渠道、Webhook | 处理订单业务规则 |
| aftersale-service | 售后申请、审核、退款流程 | 直接绕过支付服务退款 |
| MQ | 异步解耦、事件通知 | 替代同步核心校验 |

---

### 1.4 服务调用关系图

文本版即可：

```text
前端
  ↓
BFF / mall-gateway
  ├─ OrderRequest → order / mall-core
  ├─ PayRequest → pay-service
  └─ StoreRequest → store-api

store-api / TP8
  ↓
InternalServiceHelper
  ├─ order-service
  ├─ pay-service
  ├─ user-service
  └─ aftersale-service

order / mall-core
  ↓
PayInternal
  ↓
pay-service
  ↓
MQ / Webhook / 支付渠道
  ↓
aftersale-service（部分退款/售后链路）
```

画图时要注意：不要写真实域名、真实 IP、真实公司名。

---

### 1.5 Internal Client 清单

| Client/Helper | 所在服务 | 目标服务 | 主要用途 | 风险 |
|---|---|---|---|---|
| `PayInternal` | order/mall-core | pay-service | capture、查询支付、退款相关 | 高 |
| `InternalServiceHelper` | store-api | 多个内网服务 | 门店调用订单/支付/售后/用户 | 中高 |
| `PayRequest` | BFF | pay-service | 前端支付相关转发 | 高 |
| `OrderRequest` | BFF | order-service | 订单/结账转发 | 高 |

---

### 1.6 典型链路写法：结账支付

```text
1. 前端提交支付请求，携带用户 token 和 order_no。
2. BFF 验证用户登录，生成或透传 trace_id。
3. BFF 调用订单/核心服务的结账接口。
4. 订单服务校验订单归属、金额、状态、是否可支付。
5. 订单服务通过 PayInternal 调用 pay-service capture。
6. PayInternal 添加服务间鉴权 headers、trace_id、timeout。
7. pay-service 验签、检查幂等、调用支付渠道或内部支付逻辑。
8. pay-service 返回成功、失败或处理中。
9. 订单服务根据结果更新订单状态或返回处理中。
10. BFF 转换成前端可理解的响应。
11. 如果后续有 Webhook/MQ，继续通过 trace_id 关联链路。
```

---

### 1.7 典型链路写法：售后/退款

```text
1. 前端或门店后台发起售后/退款申请。
2. BFF 或 store-api 接收请求并做权限校验。
3. 售后服务校验订单状态、售后类型、退款金额。
4. 售后服务创建售后单，进入审核/处理中状态。
5. 需要退款时，通过支付服务发起退款。
6. 支付服务使用幂等键防止重复退款。
7. 支付渠道回调或 MQ 通知退款结果。
8. 售后服务更新售后状态。
9. 用户侧查询到最新售后进度。
```

重点：退款链路比查询链路风险更高，必须强调幂等和错误处理中间态。

---

### 1.8 排障流程怎么写？

示例：用户反馈“支付后订单没变成功”。

排查步骤：

1. 从前端或接口响应拿到 `trace_id`。
2. 查 BFF 日志：请求是否到达，调用下游是否成功。
3. 查订单服务日志：订单校验是否通过，是否调用 PayInternal。
4. 查 pay-service 日志：是否收到 capture，渠道返回什么。
5. 查 MQ/Webhook 日志：是否收到支付成功事件。
6. 用 `order_no/pay_no` 查数据库状态。
7. 判断是同步返回失败、异步回调延迟，还是状态更新失败。

---

### 1.9 脱敏检查

文档必须避免：

- 真实公司名
- 真实内网域名
- 真实 IP
- 真实 token / secret
- 真实用户手机号/邮箱
- 真实订单号
- 未公开业务策略细节

推荐写法：

```text
pay-service
order-service
https://internal-pay.example
order_no=O1001
trace_id=T-demo-001
```

---

## 2. 源码阅读

本日无新增指定源码，重点整合前面阅读成果。

建议引用这些公开代号路径：

- `mall-gateway/services/http/PayRequest.php`
- `mall-gateway/services/http/OrderRequest.php`
- `mall-core/common/api/PayInternal.php`
- `store-api/app/common/library/helper/InternalServiceHelper.php`
- `pay-service/pay-api/controllers/outer/StripeController.php`
- `aftersale-service/common/services/AfterSaleService.php`

---

## 3. 练习任务

### 练习 1：输出架构文档 v1

按推荐模板写，不少于 1000 字。

### 练习 2：完成 Internal Client 清单

| Client | 调用方 | 目标服务 | path 示例 | 风险 | 是否有 trace_id |
|---|---|---|---|---|---|
|  |  |  |  |  |  |

### 练习 3：完成排障流程

选择一个问题：

- 支付后订单未成功
- 退款重复提交
- 售后状态未更新
- 门店查询订单超时

写出排障步骤。

---

## 4. JS/Node.js 类比

- 架构文档 ≈ onboarding doc + runbook
- Internal Client 清单 ≈ service SDK map
- 排障流程 ≈ incident playbook
- trace_id 规范 ≈ distributed tracing convention

---

## 5. AI Review 提问

```text
我已经写了跨服务调用与全链路追踪架构文档 v1。
请检查：
1. 服务边界是否清楚？
2. BFF、TP8、订单、支付、售后职责是否混淆？
3. Internal Client 清单是否完整？
4. 鉴权、超时、重试、熔断、幂等是否覆盖？
5. 文档是否已经脱敏，是否适合公开学习？
```

---

## 6. 今日产出

- [ ] 跨服务调用架构文档 v1
- [ ] 服务调用关系图
- [ ] Internal Client 清单
- [ ] 结账支付链路说明
- [ ] 售后/退款链路说明
- [ ] 排障流程
- [ ] 脱敏检查表
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 文档不少于 1000 字
- [ ] 覆盖 BFF、TP8、订单、支付、售后
- [ ] 覆盖 `PayInternal` 和 `InternalServiceHelper`
- [ ] 覆盖鉴权、超时、重试、熔断、错误传递、trace_id
- [ ] 有至少 1 条完整排障流程
- [ ] 已完成脱敏检查

---

## 8. 今日自测题

### 8.1 一份好的跨服务架构文档应该能回答哪些问题？

参考答案：

> ✅ 至少能回答：前端请求先到哪里（BFF 职责）、BFF 调哪些服务（服务调用图）、订单如何调支付（PayInternal）、TP8 门店如何调内网服务（InternalServiceHelper）、内网怎么鉴权、下游超时怎么办、错误怎么传给前端、日志怎么串起来、异步怎么处理。核心是让新人能按图追踪代码。

---

### 8.2 为什么说架构文档不是“画漂亮图”？

参考答案：

> ✅ 因为文档的真正价值是可用：让新人能按图追踪到具体代码、让开发知道各服务的职责边界、让线上问题能按 trace_id 定位到具体服务和具体请求。漂亮但对不上代码、无法用于排障的图没有意义。

---

### 8.3 系统角色总览里为什么要写“不应该做什么”？

参考答案：

> ✅ 因为职责边界是靠“该做什么 + 不该做什么”共同划清的。比如 BFF 不应该写支付/订单核心逻辑、TP8 不应该散落裸 HTTP 调用、前端不应该直接调内网服务。写清红线能防止后来者把逻辑放错层，避免架构腐化。

---

### 8.4 推荐的架构文档结构里，为什么“典型链路”要单独成章？

参考答案：

> ✅ 因为静态的服务调用图只说明“谁能调谁”，而典型链路（结账支付、售后退款）按时序展开一次真实请求的每一跳，包含 path、参数、鉴权、timeout、错误传递和 trace_id。这才是新人真正能照着追代码、排障同事能照着定位的部分。

---

### 8.5 为什么架构文档要专门写“脱敏说明”？

参考答案：

> ✅ 因为架构文档会涉及服务地址、鉴权方式、密钥来源、内部错误码等敏感信息。要用公开代号替代真实域名和服务名、不写真实密钥、对示例数据脱敏，确保文档可以安全地用于学习、评审和分享，不泄露内网结构。

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
我正在进行 Week 12 Day 06：跨服务架构文档项目 的学习。
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
