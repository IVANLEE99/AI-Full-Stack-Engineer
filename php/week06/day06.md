# Week 06 Day 06：下单全链路时序图

> 所属周：Week 06：订单域  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`mall-core`  
> 类型：项目实战  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

追踪 `trade/confirm` 到 `trade/place` 的下单全链路，画出包含前端、BFF 网关、订单 Controller、Form 校验、OrderService、库存/金额/支付准备等节点的时序图。

今天你要真正掌握这一句话：

> 下单链路不是一个接口，而是一组连续步骤：确认订单负责展示可下单信息，提交订单负责真正创建订单；两者都必须经过参数校验、金额确认、库存检查、幂等/锁和状态初始化。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Week 05 的网关路由表
2. 明确 `confirm` 和 `place` 的区别
3. 从前端结账页开始追踪 `trade/confirm`
4. 从前端提交按钮开始追踪 `trade/place`
5. 标注网关、订单服务、Form、Service、Repository 节点
6. 记录每一步入参和出参
7. 标注金额、库存、优惠券、地址、幂等、锁等风险点
8. 画完整时序图
9. 用 AI Review 检查是否遗漏异步步骤和关键风险

---

## 1. 学习内容

### 1.1 `confirm` 和 `place` 有什么区别？

电商下单通常不是一步完成。

常见流程：

```text
进入结账页：confirm
点击提交订单：place
```

区别：

| 接口 | 作用 | 是否真正创建订单 |
|---|---|---|
| `trade/confirm` | 确认订单信息，展示商品、地址、优惠、金额 | 通常不创建 |
| `trade/place` | 提交订单，真正生成订单记录 | 是 |

`confirm` 更像“预览/确认”：

```text
你要买什么？多少钱？地址是什么？优惠券能不能用？
```

`place` 更像“提交/落库”：

```text
确认无误，创建订单、写订单明细、初始化状态。
```

---

### 1.2 下单链路有哪些参与者？

完整链路可能包含：

| 角色 | 职责 |
|---|---|
| 前端结账页 | 展示订单确认信息，提交订单 |
| BFF 网关 | 鉴权、公参、转发订单请求 |
| OrderController | 接收订单接口请求 |
| OrderConfirmForm | 校验确认订单参数 |
| OrderService | 编排订单业务 |
| Repository/Model | 查询商品、地址、订单、优惠券等数据 |
| Redis 锁 | 防重复提交 |
| 库存服务/逻辑 | 校验和扣减库存 |
| 支付域 | 创建支付前置数据或等待后续支付 |

小白重点：不要只画 Controller 和 Service，要把前端、网关、校验、锁、数据层也画出来。

---

### 1.3 `trade/confirm` 时序怎么画？

确认订单大致流程：

```text
前端结账页
  ↓ 请求 trade/confirm
BFF 网关
  ↓ 鉴权 + 注入 user_id/site_id
OrderController::confirm
  ↓
OrderConfirmForm::validate
  ↓
OrderService::confirm
  ↓
查询商品/地址/优惠/运费/配置
  ↓
计算展示金额
  ↓
返回确认页数据
```

确认页返回通常包含：

- 商品信息
- 收货地址
- 优惠券列表或选中优惠
- 运费
- 商品金额
- 应付金额
- 可用支付方式提示

注意：确认页金额只是“当前计算结果”，最终提交时后端仍要重新校验。

---

### 1.4 `trade/place` 时序怎么画？

提交订单大致流程：

```text
前端点击提交订单
  ↓ 请求 trade/place
BFF 网关
  ↓ 鉴权 + 公参
OrderController::place
  ↓
Form 校验
  ↓
获取 Redis 锁 / 幂等校验
  ↓
OrderService::place
  ↓
重新校验商品/地址/优惠/库存/金额
  ↓
开启事务
  ↓
创建订单主表
  ↓
创建订单明细
  ↓
扣减或锁定库存
  ↓
初始化订单状态：待支付
  ↓
提交事务
  ↓
释放锁
  ↓
返回 order_id / pay 参数
```

真实项目可能顺序不同，但关键思想是：提交订单时必须重新校验，不信任确认页缓存和前端传来的金额。

---

### 1.5 每步入参和出参怎么标注？

时序图不是只画箭头，还要标注数据。

示例：

| 步骤 | 入参 | 出参 |
|---|---|---|
| 前端 → 网关 `confirm` | `goods_id`、`sku_id`、`num`、`address_id` | 请求进入网关 |
| 网关 → 订单服务 | 参数 + `user_id`、`site_id` | 转发请求 |
| Form 校验 | 请求参数 | 校验成功/失败 |
| Service confirm | 合法参数 | 商品、金额、优惠、地址 |
| 前端 → 网关 `place` | confirm token/商品/地址/支付方式 | 提交订单请求 |
| Service place | 下单参数 | `order_id`、订单状态 |

---

### 1.6 下单链路必须标注哪些风险？

| 风险 | 为什么重要 | 应在哪里处理 |
|---|---|---|
| 金额篡改 | 前端金额不能信 | Service 重新计算 |
| 库存不足 | 防止超卖 | 库存/订单服务 |
| 重复提交 | 防止重复订单 | Redis 锁/幂等 |
| 地址越权 | 不能用别人的地址 | Form + Service |
| 优惠券越权 | 不能用别人的券 | 优惠券/订单服务 |
| 状态初始化 | 新订单应是待支付等合法状态 | OrderService |
| 事务一致性 | 主表/明细/库存要一致 | Service/Repository |

---

### 1.7 时序图模板

你可以用文本画：

```text
前端        BFF网关        OrderController      Form        OrderService       DB/Redis
 |             |                  |              |              |              |
 | confirm     |                  |              |              |              |
 |-----------> |                  |              |              |              |
 |             | 鉴权+公参         |              |              |              |
 |             |----------------> |              |              |              |
 |             |                  | validate     |              |              |
 |             |                  |------------> |              |              |
 |             |                  |              | confirm      |              |
 |             |                  |----------------------------> |              |
 |             |                  |              |              | 查询/计算      |
 |             |                  |              |              |------------> |
 | 返回确认数据 |                  |              |              |              |
 |<----------- |<---------------- |              |              |              |
```

提交订单再画一张，重点标注锁、事务和状态初始化。

---

### 1.8 JS/Node.js 类比

Node/NestJS 项目也会画类似时序图：

```text
Frontend → API Gateway → OrderController → DTO Validation → OrderService → Repository → DB/Redis
```

时序图是联调必备文档，能帮助前端、后端、测试一起确认：

- 请求先后顺序
- 每步参数
- 失败点
- 谁负责排查

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议结合以下对象追踪：

- 网关路由表中的结账接口
- `OrderController`
- `OrderConfirmForm`
- `OrderService`
- `OrderRepository`
- 锁/幂等相关代码

记录：

| 节点 | 文件/方法 | 入参 | 出参 | 风险 |
|---|---|---|---|---|
| confirm |  |  |  |  |
| place |  |  |  |  |

---

## 3. 练习任务

### 练习 1：追踪 confirm 与 place 全链路

分别记录：

```text
接口：
前端参数：
网关注入：
Controller：
Form：
Service：
Repository/Model：
返回数据：
失败场景：
```

### 练习 2：画时序图

要求包含：

- 前端
- BFF 网关
- OrderController
- Form 校验
- OrderService
- Redis 锁/幂等
- Repository/DB
- 返回结果

### 练习 3：标注每步入参出参

至少标注 8 个步骤。

---

## 4. JS/Node.js 类比

- 时序图 ≈ 联调必备文档
- `trade/confirm` ≈ checkout preview API
- `trade/place` ≈ create order API
- Form 校验 ≈ DTO/Joi/Zod validation
- Redis 锁 ≈ idempotency/lock middleware 或 service guard

---

## 5. AI Review 提问

```text
我正在画下单全链路时序图。
我已经追踪了 trade/confirm 和 trade/place，并标注了前端、网关、OrderController、Form、OrderService、Redis 锁、Repository/DB 的入参出参。
请你检查：
1. 我的时序图是否完整？
2. 是否遗漏异步步骤或支付准备步骤？
3. 金额、库存、幂等、状态初始化风险是否标注清楚？
4. confirm 和 place 的边界是否正确？
5. 真实项目联调还应该补哪些信息？
```

---

## 6. 今日产出

- [ ] `trade/confirm` 链路记录
- [ ] `trade/place` 链路记录
- [ ] 下单全链路时序图
- [ ] 入参/出参标注表
- [ ] 风险点标注表
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 完成下单时序图
- [ ] 能口述 `confirm` 和 `place` 的区别
- [ ] 能说明下单链路每个节点职责
- [ ] 能标注金额、库存、幂等、状态风险
- [ ] 能解释为什么提交订单时必须重新校验

---

## 8. 今日自测题

### 8.1 `trade/confirm` 和 `trade/place` 有什么区别？

参考答案：

> ✅ `trade/confirm` 是确认订单，负责展示商品、地址、优惠、金额等信息，通常不真正创建订单，更像“预览/确认”。`trade/place` 是提交订单，真正生成订单记录、写订单明细、初始化状态，是真正落库的一步。

---

### 8.2 下单全链路一般包含哪些参与者？

参考答案：

> ✅ 常见参与者有：前端结账页、BFF 网关、OrderController、OrderConfirmForm、OrderService、Repository/Model、Redis 锁、库存服务、支付域。画时序图时不要只画 Controller 和 Service，要把前端、网关、校验、锁、数据层都画出来。

---

### 8.3 为什么提交订单（place）时后端必须重新校验，而不能信任确认页的数据？

参考答案：

> ✅ 因为确认页金额只是“当前计算结果”，前端传来的金额、库存、优惠等都可能被篡改或已经过期。提交订单会真正扣库存、生成订单，所以必须重新校验商品、地址、优惠、库存、金额，防止金额篡改和超卖。

---

### 8.4 下单链路中“重复提交”风险应该在哪里处理？

参考答案：

> ✅ 应在后端用 Redis 锁或幂等 token 处理。前端按钮 disabled 只能提升体验，无法保证安全。获取锁/幂等校验通常在进入 `OrderService::place` 创建订单之前完成。

---

### 8.5 时序图除了画箭头，还应该标注什么？

参考答案：

> ✅ 还要标注每一步的入参和出参，以及关键风险点（金额、库存、优惠券、地址、幂等/锁、状态初始化、事务一致性）。时序图是联调必备文档，能帮前端、后端、测试确认请求顺序、参数、失败点和排查责任。

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
我正在进行 Week 06 Day 06：下单全链路时序图 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 06 README](./README.md)
