# Day 02 源码阅读笔记：OrderService（业务编排）

> 对应课程：`week06/day02.md` OrderService 业务编排  
> 源码映射：`week06/OriginCodes/OrderService.php`（约 5130 行）  
> 公开路径：`mall-core/common/services/order/OrderService.php`  
> 命名空间：`common\services\order`  
> 目标：建立 public 方法地图，理解 Context + Node 链，以及 `code/data/info` 约定。

---

## 1. 一句话结论

`OrderService` 是订单域的 **业务编排中心（偏门面）**：多数写操作走 **Context + Node 链 + `NodeExecutionEngine`**，再统一返回 `code/data/info`。

**真正建单不在本类**，而在 `PlaceOrderService`。本类承接：商品校验、取消、收货、退款关单、改址、支付轮询、代客试算、售后回写、金额更新。

成功码是 **`code == 1`**，不是课程示例里的 `0`。

---

## 2. 练习 1：前 200 行

```text
类名：OrderService
继承：BaseService（instance()、contextInit、returnSuccess/returnError/returnFormat）
依赖：大量 Repository / 其它 Service / Redis / MQ / Context / Node
public 方法：约 80+ 个
私有辅助：formatDayOrderStatistic、orderEsSearchWhere、pdfOrderGoodsFormat、orderConsistencyChangeNotify
统一返回：returnSuccess / returnError / returnFormat → ['code','data','info']
编排引擎：NodeExecutionEngine::executeEngine($context, $nodeChain)
```

商品校验骨架：

```text
goodsListCheck($params)
  → new GoodsListCheckContext + contextInit
  → Node 链：参数格式化 → 取商品 → 校验 → 限时活动 → 套装价 → ETA
  → executeEngine
  → code==1 returnSuccess；800305 库存不足；其它原样返回
```

---

## 3. 阅读记录总表（先建地图）

| public 方法 | 参数 | 返回格式 | 调用的 Repository/Model | 职责 |
|---|---|---|---|---|
| `goodsListCheck` | `$params` | `returnSuccess` / `800305` | 经 Node 查商品、校验 | 下单前商品/库存/活动校验 |
| `orderCancel` | `$params` | `returnSuccess` / `800400` / `401` | OrderGet/Goods/Address → 关单 Node | 未支付取消，限制关闭类型 |
| `orderRefund` | `$params` | 同上 | RefundCreate + OrderClosed | 已支付发货前整单退款关单 |
| `orderReceive` | `$params` | 同上 | ReceiveHandleNode | 确认收货，必须有 userId |
| `modifyAddress` | `$params` | 节点链结果 | ModifyAddressNode | 改址（Controller 仍偏厚） |
| `delayUpdatePaidSuccess` | 订单数据 | 业务码 | 支付成功改状态 | MQ：待发货 |
| `getPayStatus` | userId, orderNo, paymentNo | `returnSuccess` 结构体 | OrderRepository + PaymentRepository + PayService | 支付轮询（3DS/超时） |
| `valetOrderCalculate` | `$params` | `returnSuccess` | 长 Node 链（券/运费/税/增值） | 代客下单金额试算 |
| `updateOrderAmount` | orderNo, paymentDiscount | `600002` 或成功 | OrderRepository 直接查单 | 更新订单金额 |
| `orderAnomalyDetect` | params, orderNo, version | 异常也常 `returnSuccess(data)` | 地址 + 一致性 + 增值 + 重复单 | 下单前异常检测 |
| `afterSaleDoneNotify` | `$params` | 业务码 | 售后完成回写订单 | 售后域通知订单 |
| `capture` / `autoCapture` | 订单号等 | 业务码 | 支付捕获 | 第三方金额捕获 |

下单主链路：

```text
OrderController::actionTradePlace
  → PlaceOrderService::tradePlace
  （不在 OrderService）
```

---

## 4. 练习 2：必记 5 个 public 方法

| 方法 | 职责 | 输入 | 输出 |
|---|---|---|---|
| `goodsListCheck` | 编排商品校验节点；库存问题用 800305 | POST 商品参数 | `code/data/info`，成功 data 来自 `$context->response` |
| `orderCancel` | 校验关闭类型与登录，跑关单节点链 | order_no、closed_reason、source、token | 成功 1；非法 source `800400`；未登录 `401` |
| `orderReceive` | 确认收货编排 | order_no、token/user_id | 成功 1；无用户 `401` |
| `getPayStatus` | 查订单+支付单，组装轮询结果 | userId、orderNo、paymentNo | `returnSuccess({pay_status,...})`，超时也走成功结构 |
| `valetOrderCalculate` | 代客试算：商品→活动→券→运费→税 | 代客下单参数 | 成功把试算结果放 data |

---

## 5. 练习 3：调用关系图

复杂写操作：

```text
OrderController
  ↓
OrderService::orderCancel()
  ↓ contextInit + 关闭类型/登录判断
NodeExecutionEngine
  ↓
OperatorHandleNode
  → OrderGetNode
  → OrderGoodsGetNode
  → OrderAddressGetNode
  → CancelExchangeNode
  → OrderClosedNode
  → OrderChangeSendRabbitMQNode
  ↓
OrderRepository / OrderGoodsRepository / MQ
  ↓
DB
```

简单查询：

```text
OrderService::getPayStatus()
  ↓
OrderRepository::getOrderByOrderNo()
PaymentRepository::getByBusinessNo()
PayService::get3DSResult() / getThirdPaymentConfirm()
OrderRedis（轮询起始时间）
  ↓
returnSuccess({ pay_status, error_message, next_action, ... })
```

代客试算 Node 顺序（金额编排）：

```text
CheckStoreOrder
  → GoodsParamsFormat → GoodsListGet → GoodsListCheck
  → 限时活动 → Activity → 套装折扣 → 积分 → Coupon
  → 客服优惠 → ShippingFee → 增值服务 → ETA → 安装/保养
  → 店长优惠 → TaxHandle → ValetOrderCalculateNode
```

---

## 6. `code/data/info` 约定（和课程差异）

| 字段 | 本项目实际 |
|---|---|
| `code` | **1 = 成功**（课程示例常写 0） |
| `data` | 成功业务数据，常来自 `$context->response` |
| `info` | 文案；失败给前端/日志 |

常见业务码：

| code | 含义 |
|---|---|
| `1` | 成功 |
| `401` | 未登录 |
| `800305` | 商品校验库存未过（仍可能带 data） |
| `800400` | 关闭类型等参数不合法 |
| `800402` / `800403` | Service 捕获异常 |
| `600002` | 订单不存在 |
| `700002` | 支付单不存在 |

包装来自 `BaseService`：`returnSuccess` / `returnError` / `returnFormat`。

特例：`orderAnomalyDetect` 发现禁售/不一致/重复单时，仍 **`returnSuccess`，把异常放在 `data.exception_code`**，前端按 data 判断。  
「HTTP/业务成功码」和「有没有业务异常」不是同一层。

---

## 7. 分层对照（昨天 Controller → 今天 Service）

```text
actionGoodsListCheck       → OrderService::goodsListCheck
actionOrderCancel          → OrderService::orderCancel
actionOrderReceive         → OrderService::orderReceive
actionTradePlace           → PlaceOrderService::tradePlace   ← 不在本类
actionGetPayStatus         → OrderService::getPayStatus
actionValetOrderCalculate  → OrderService::valetOrderCalculate
```

| 类 | 角色 |
|---|---|
| `OrderController` | 锁、Form、HTTP 返回 |
| `OrderService` | 已有订单的生命周期编排 |
| `PlaceOrderService` | 创建订单 |
| `ConfirmOrderService` | 确认页（入口已 forbidden） |
| Context + Node | 可复用步骤与上下文 |

适合放 Service：能否取消、关单类型、收货、聚合多数据源、返回业务码。  
不该放 Service：HTTP、鉴权 Filter、前端 icon/文案拼装。

本文件基本不碰 HTTP，这点正确。偏胖的地方：

- `getPayStatus` 里大量支付渠道分支（更像 PayService）
- AB 实验、PDF、邮件、门店员工等挤在同一上帝类
- `modifyAddress` 差价判断仍有一半在 Controller

---

## 8. Node.js / NestJS 类比

| PHP | NestJS |
|---|---|
| `OrderService extends BaseService` | `@Injectable() OrderService` |
| `contextInit` + Context | DTO + 请求级 context |
| `NodeExecutionEngine` + Node 链 | pipeline / saga / 责任链 |
| `OrderRepository` | TypeORM / Prisma repository |
| `returnSuccess(['code'=>1])` | `{ ok: true, data }` 或 Result |

---

## 9. 自测题（附简答）

1. **`OrderService` 角色？**  
   订单业务编排中心；建单在 `PlaceOrderService`。

2. **`code/data/info`？**  
   业务包装；本项目成功是 **1**，不是 HTTP 状态码。

3. **Service vs Repository？**  
   Service 定规则、串节点；Repository 读写表。

4. **不该放 Service？**  
   HTTP、鉴权入口、前端展示拼装。

5. **怎么读最有效？**  
   先列 public 地图，再跟 1～2 条 Node 链，不要先钻私有方法。

---

## 10. 20 分钟复盘练习

- [ ] 口述 `goodsListCheck` 的 Node 顺序
- [ ] 画出 `orderCancel`：Service → Engine → Node → Repository
- [ ] 说明为什么 `TradePlace` 不在 `OrderService`
- [ ] 解释 `orderAnomalyDetect` 为何异常也 `returnSuccess`

---

## 11. 最终复盘句

读 `OrderService` 不要被 5000 行吓住：先抓住 **Context + Node 链 + `code==1`**，再记住 **建单在 PlaceOrderService，本类管订单生命周期**。
