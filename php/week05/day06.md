# Week 05 Day 06：结账 API 路由表项目

> 所属周：Week 05：BFF 网关架构  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`mall-gateway`  
> 类型：项目实战  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

输出一份至少覆盖 5 个结账相关 API 的路由表，能从前端 URL 反查到网关 action、HTTP Client、目标内网服务，并说明每个接口在结账流程中的作用。

今天你要真正掌握这一句话：

> API 路由表就是 BFF 网关的“地图”：它把前端 URL、网关 Controller/action、Request 客户端、目标服务、鉴权要求和业务说明整理到一张表里，让新同事和自己都能快速定位接口链路。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解为什么需要 API 路由表
2. 明确结账流程包含哪些关键接口
3. 设计路由表字段
4. 从前端 URL 反查网关 Controller/action
5. 从 action 追踪到 `PayRequest` / `OrderRequest`
6. 从 Request 类追踪到内网服务 path
7. 标注鉴权、公参、白名单和风险
8. 整理至少 5 个 API
9. 用路由表反向检查自己是否能定位代码

---

## 1. 学习内容

### 1.1 为什么需要 API 路由表？

BFF 网关里的接口可能很多。

如果没有路由表，新同事看到前端请求：

```text
/pay/pay/methods
/order/order/preview
/order/order/create
```

可能不知道：

- 这个 URL 对应哪个 Controller？
- 这个 action 调用了哪个 Request 类？
- 最终去了哪个内网服务？
- 是否需要登录？
- 哪些参数是前端传的，哪些是网关注入的？
- 出问题应该看哪个服务日志？

API 路由表就是用来解决这些问题的。

小白重点：路由表不是为了写文档而写文档，而是为了“能快速反查代码和排查问题”。

---

### 1.2 结账流程通常有哪些 API？

一个商城结账流程可能包括：

```text
选择商品
  ↓
确认结算页
  ↓
获取可用支付方式
  ↓
创建订单
  ↓
创建支付单
  ↓
查询支付状态
  ↓
查看订单结果
```

对应 API 可能是：

| 场景 | 示例 URL |
|---|---|
| 获取结算预览 | `/order/order/preview` |
| 创建订单 | `/order/order/create` |
| 获取支付方式 | `/pay/pay/methods` |
| 创建支付 | `/pay/pay/create` |
| 查询支付状态 | `/pay/pay/status` |
| 查询订单详情 | `/order/order/detail` |

实际 URL 以项目为准，今天可以先用这些作为练习模板。

---

### 1.3 路由表应该有哪些字段？

推荐字段：

| 字段 | 含义 |
|---|---|
| 前端 URL | 浏览器/前端请求的路径 |
| HTTP 方法 | GET / POST |
| 网关模块 | 如 Pay / Order |
| 网关 Controller/action | 入口代码位置 |
| 鉴权 | 是否需要登录 |
| 公参 | user_id、site_id、lang 等 |
| HTTP Client | 如 PayRequest / OrderRequest |
| 目标服务 | 支付服务、订单服务等 |
| 内网 path | Request 类最终调用的 path |
| 备注 | 业务说明、风险、排查建议 |

---

### 1.4 如何填写一条路由记录？

以前端 URL 为例：

```text
/pay/pay/methods
```

你可以这样拆：

| 字段 | 示例 |
|---|---|
| 前端 URL | `/pay/pay/methods` |
| HTTP 方法 | GET |
| 网关模块 | Pay |
| Controller/action | `PayController::actionMethods()` |
| 鉴权 | 需要登录 |
| 公参 | `user_id`、`site_id` |
| HTTP Client | `PayRequest::methods()` |
| 目标服务 | pay-service |
| 内网 path | `/pay/methods` |
| 备注 | 获取当前用户可用支付方式 |

如果某些字段你暂时无法确认，就写“待确认”，不要编造。

---

### 1.5 结账 API 路由表示例

你最终可以输出类似：

| 前端 URL | 方法 | 网关 action | HTTP Client | 目标服务 | 鉴权 | 备注 |
|---|---|---|---|---|---|---|
| `/order/order/preview` | POST | `OrderController::actionPreview()` | `OrderRequest::preview()` | order-service | 是 | 结算预览 |
| `/order/order/create` | POST | `OrderController::actionCreate()` | `OrderRequest::create()` | order-service | 是 | 创建订单 |
| `/pay/pay/methods` | GET | `PayController::actionMethods()` | `PayRequest::methods()` | pay-service | 是 | 获取支付方式 |
| `/pay/pay/create` | POST | `PayController::actionCreate()` | `PayRequest::create()` | pay-service | 是 | 创建支付单 |
| `/pay/pay/status` | GET | `PayController::actionStatus()` | `PayRequest::status()` | pay-service | 是 | 查询支付状态 |

注意：上表是学习模板，真实项目要以源码为准修正。

---

### 1.6 如何用路由表反查代码？

拿一条记录：

```text
/order/order/create
```

反查步骤：

1. 找 `OrderController::actionCreate()`
2. 看 action 取了哪些参数
3. 看是否 `requireLogin()` 或 `getUserId()`
4. 看调用 `OrderRequest::create()` 还是其他服务
5. 跳到 `OrderRequest` 看内网 path
6. 记录目标服务和错误处理

如果路由表写得好，你应该能在 1-2 分钟内定位到关键代码。

---

### 1.7 路由表也要标注风险

结账链路涉及订单和支付，风险比普通展示接口更高。

建议标注：

| 风险点 | 说明 |
|---|---|
| 鉴权 | 未登录不能创建订单/支付 |
| 幂等 | 重复点击不能创建重复订单或重复支付 |
| 金额 | 金额必须由后端服务计算，不能信前端 |
| 状态 | 支付状态要以后端/支付回调为准 |
| 日志 | 创建订单、支付失败要能追踪 |

备注字段可以写：

```text
高风险：涉及资金，必须检查鉴权、金额来源、幂等和日志。
```

---

### 1.8 Node.js 类比：API 地图

Node/NestJS 项目也常需要 API map：

| Route | Controller | Service Client | Target Service |
|---|---|---|---|
| `POST /checkout/create` | `CheckoutController.create` | `orderClient.create` | order-service |

PHP BFF 的路由表本质一样。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议结合这些文件反查：

- `mall-gateway/frontapi/modules/Pay/controllers/PayController.php`
- `mall-gateway/services/http/PayRequest.php`
- `mall-gateway/services/http/OrderRequest.php`
- 结账/订单相关 Controller

记录：

| 文件 | 你找到的信息 |
|---|---|
| PayController |  |
| PayRequest |  |
| OrderRequest |  |
| OrderController |  |

---

## 3. 练习任务

### 练习 1：完成 5+ API 路由表

至少覆盖：

- 结算预览
- 创建订单
- 获取支付方式
- 创建支付
- 查询支付状态

表格字段：

| 前端 URL | 方法 | 网关 action | HTTP Client | 目标服务 | 鉴权 | 备注 |
|---|---|---|---|---|---|---|
|  |  |  |  |  |  |  |

### 练习 2：自测能否根据表反查代码

随机选 2 条记录，按表格反查到代码，并记录是否成功。

### 练习 3：标注风险

| API | 风险等级 | 风险原因 | 建议检查点 |
|---|---|---|---|
|  |  |  |  |

---

## 4. JS/Node.js 类比

- 路由表 ≈ API 地图
- 网关 action ≈ route handler/controller method
- HTTP Client ≈ service client / SDK
- 目标服务 ≈ downstream microservice
- 鉴权/公参 ≈ middleware context

---

## 5. AI Review 提问

```text
我正在整理结账 API 路由表。
我已经列出了前端 URL、网关 action、HTTP Client、目标服务、鉴权和备注。
请你检查：
1. 这张路由表是否可用于新人 onboarding？
2. 字段是否足够完整？
3. 哪些结账 API 风险标注不够？
4. 是否遗漏了幂等、金额、鉴权、日志等关键检查？
5. 如何让这张表长期可维护？
```

---

## 6. 今日产出

- [ ] 结账 API 路由表
- [ ] 2 条 API 反查记录
- [ ] 结账链路风险标注表
- [ ] API 地图维护建议
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 路由表覆盖至少 5 个 API
- [ ] 表结构包含前端 URL、网关 action、HTTP Client、目标服务、备注
- [ ] 能根据表反查至少 2 条 API 的代码
- [ ] 能标注结账链路中的鉴权、金额、幂等风险
- [ ] 能说明路由表对 onboarding 和排查问题的价值

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
我正在进行 Week 05 Day 06：结账 API 路由表项目 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 05 README](./README.md)
