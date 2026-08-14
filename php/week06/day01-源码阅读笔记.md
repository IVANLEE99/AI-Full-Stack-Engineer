# Day 01 源码阅读笔记：OrderController（订单入口）

> 对应课程：`week06/day01.md` OrderController 结构与 action  
> 源码映射：`week06/OriginCodes/OrderController.php`（**order-api 新版本，约 1699 行**）  
> 公开路径：`order-api/controllers/OrderController.php`  
> 命名空间：`AppOrderApi\controllers`  
> 目标：看清订单 Controller 是入口编排，还是已经把复杂业务写进来了。

> 说明：此前本地映射曾是 BFF 网关 `OrderController`。本次按 **mall-core / order-api** 真实文件重新阅读。

---

## 1. 一句话结论

这份 `OrderController` 才是课程要的 **订单服务 HTTP 入口**：  
**取参 → Redis 锁 → Form 校验 → 调 Service → `endSuccess` / `endFail`，并用 `try/catch` 转错误。**

整体符合「入口编排」，但并不处处都薄：

- 标准写操作（取消、收货、下单）较规范
- `actionModifyAddress`、`actionGetServiceGuarantee`、`actionCancelRepeat` 已混入状态判断、差价计算、展示文案、直接写库

---

## 2. 类级结构（练习 1）

```text
类名：OrderController
继承：BaseApiController
命名空间：AppOrderApi\controllers
action 数量：约 48 个 public action
最重要的 3 个 action：
  1. actionTradePlace（现行下单）
  2. actionGoodsListCheck（下单前商品校验）
  3. actionOrderCancel / actionOrderReceive（订单生命周期）
统一响应方法：endSuccess / endFail / endValidate
异常处理方式：绝大多数 action 有 try/catch，失败转 20000 或业务 code
Form：有（OrderConfirmForm / PlaceOrderForm / OrderForm / AddressForm / ValetOrderForm / AfterSaleForm）
Service：有（OrderService / PlaceOrderService / ConfirmOrderService 等）
锁：LockHandleRedis（普通 lock + 下单 paymentLocked）
```

实际链路（与课程一致）：

```text
前端 / BFF
  ↓
order-api OrderController
  ↓ 锁 + Form
OrderService / PlaceOrderService / ConfirmOrderService
  ↓
Repository / Redis / 其他域 API
  ↓
endSuccess / endFail
```

---

## 3. 标准薄模板 vs 过厚反例

### 3.1 较好：`actionTradePlace`（下单）

```text
action：actionTradePlace
入参：POST；日志会去掉 card_data_info
锁：paymentLocked(eid, 8)
Form：PlaceOrderForm::TradePlaceValidate
Service：PlaceOrderService::tradePlace
出参：code==1 → endSuccess(data, info)；失败企业微信告警 + endFail
错误码：校验失败 endValidate(800000 或 form.error_code)；锁冲突 810507；异常 20000
是否过厚：入口层可接受。有兼容处理（coupon_user_id=undefined、默认 source=2、清购物车缓存），核心下单在 Service
```

职责分类：

| 分类 | 代码 | 适合 Controller？ |
|---|---|---|
| 取参 / 日志脱敏 | post + unset card_data_info | ✅ |
| 防重 | paymentLocked | ✅ |
| 入口兼容 | undefined coupon、默认 source | ⚠️ 可接受 |
| Form 校验 | PlaceOrderForm | ✅ |
| 调用 | PlaceOrderService::tradePlace | ✅ |
| 返回 / 告警 | endSuccess / endFail + 企微 | ✅ |
| 金额/库存/建单 | 不在本 action | ✅ 已下沉 |

### 3.2 较好：`actionOrderCancel`

取参 → 校验 `order_no` → 锁 → `OrderForm::orderCancelValidate` → 映射取消来源 → `OrderService::orderCancel` → 解锁 → 统一返回。  
校验失败会发企业微信。没有直接改状态或写 SQL。

### 3.3 过厚：`actionModifyAddress`（约 120 行）

Controller 里做了：

- `AddressForm` 校验
- **直接** `OrderRepository::getOrderByOrderNo`
- 禁售规则 `ShippingRuleService`
- 订单状态判断（支付中不可改）
- 查地址、查售后是否改过地址
- **差价/运费税计算**（`newAddressOrderPayAmountChange`、`OrderShippingFeeAndTaxChange`）
- 未支付 / 已支付不同错误码与前端按钮文案
- 部分状态走 `OrderService::modifyAddress`，其它走 `AfterSaleApi::userModifyAddress`

这已经是业务编排，应大部分下沉到 Service。

### 3.4 已关闭：`actionTradeConfirm`

一进来：

```php
$this->endFail(293838202, 'forbidden');
```

下面整段 `ConfirmOrderService::tradeConfirm` 被注释。  
确认页入口已废弃；现行主链路是 **TradePlace**。

---

## 4. 阅读记录总表（按 day01 模板，抓重点）

| action | 入参 | 调用 Form | 调用 Service | 返回 | 是否过厚 |
|---|---|---|---|---|---|
| `actionGoodsListCheck` | POST | `OrderConfirmForm::orderGoodsListCheckValidate` | `OrderService::goodsListCheck` | code 1/800305 成功，其它失败 | ✅ 薄偏标准 |
| `actionValetorder` | POST | `ValetOrderForm` + `AddressForm` | `PlaceOrderService::valetOrder` | try/catch + 0 元单企微 | ⚠️ 略厚（门店字段、邮编、地址检查） |
| `actionValetOrderCalculate` | POST | 无 | `OrderService::valetOrderCalculate` | 统一返回 | ✅ |
| `actionLocationTypeList` | 无 | 无 | 无，读 `Yii::$app->params` | 成功 | ✅ 配置直出 |
| `actionOrderCancel` | POST | `OrderForm::orderCancelValidate` | `OrderService::orderCancel` | 统一返回 | ✅ |
| `actionOrderRefund` | POST | `AfterSaleForm::canApplyValidate` | `AfterSaleService::applyAfterSale` | 统一返回 | ✅ 入口映射售后类型 |
| `actionOrderReceive` | POST | `OrderForm::orderReceivedValidate` | `OrderService::orderReceive` | 统一返回 | ✅ |
| `actionModifyAddress` | POST | `AddressForm` | Service + Repository + AfterSaleApi | 多错误码/文案 | ❌ 过厚 |
| `actionDelayPaidHandle` | POST | 无 | `OrderService::delayUpdatePaidSuccess` | 统一返回 | ✅ MQ 回调入口 |
| `actionUpdateBillAddress` | requestParams | 无 | token 换 userId → `updateBillAddress` | 统一返回 | ✅ |
| `actionAddOrderRemarks` | POST | `OrderForm::orderRemarksValidate` | `OrderService::addRemarks` | 统一返回 | ✅ |
| `actionTradeConfirm` | — | 注释掉 | 注释掉 | **直接 forbidden** | 已关闭 |
| `actionTradePlace` | POST | `PlaceOrderForm::TradePlaceValidate` | `PlaceOrderService::tradePlace` | 成功清购物车缓存 | ✅ 主下单入口 |
| `actionGaReportedResult` | POST | 手写必填 | **直接 GaEventLogRepository insert** | 成功/失败 | ⚠️ 跳过 Service |
| `actionGetPayStatus` | POST | 手写 token/order_no | `OrderService::getPayStatus` | 成功 | ✅ |
| `actionOrderHandle` | rawBody JSON | 无 | `PayConfirmService::innerNotifyHandle` | 支付域回调 | ✅ |
| `actionGetServiceGuarantee` | POST | 手写必填 | `ProductTermsOfService` 后 **在 Controller 排 icon/sort** | 成功 | ❌ 展示逻辑过厚 |
| `actionExchangePlace` | POST | `PlaceOrderForm::exchangePlaceValidate` | `PlaceOrderService::exchangePlace` | 统一返回 | ✅ |
| `actionReissuePlace` | POST | `PlaceOrderForm::reissuePlaceValidate` | `PlaceOrderService::reissuePlace` | 统一返回 | ✅ |
| `actionCancelRepeat` | params | 无 | **直接 OrderRepository update + 写日志** | 成功 | ❌ 写库在 Controller |
| `actionGetOrderInfo` | requestParams | 无 | `udesk\OrderService::getOrderByNo` | 统一返回 | ✅ |

其余查询/修改类（快照、轨迹、仓库、自提、补发通知、异常检测等）多为：锁或基础校验 → 对应 Service → 统一返回，整体偏薄。

---

## 5. 锁、Form、响应约定

### 5.1 锁

| 场景 | 锁 key | 方法 |
|---|---|---|
| 商品校验 | `eid` | `lock(..., 3)` |
| 取消/收货/改址 | `order_no` | `lock` |
| 下单 | `eid` | **`paymentLocked(..., 8)`** |
| 代客下单 | 地址 email | `lock(..., 3)` |
| 换货/补发 | `after_sale_no` | `lock` |

锁住时统一 `810507`：`Frequent operation, please try again later!`

注意：`actionUpdatePickUpInfo` 在成功路径 `endSuccess()` 后才 `unlock`，若 `endSuccess` 结束进程，可能漏解锁（依赖 TTL）。

### 5.2 Form 出现位置

| Form | 典型 action |
|---|---|
| `OrderConfirmForm` | `goodsListCheck`（`tradeConfirm` 已注释） |
| `PlaceOrderForm` | `tradePlace` / `exchangePlace` / `reissuePlace` |
| `OrderForm` | 取消、收货、备注、取货方式、自提地址 |
| `AddressForm` | 代客下单、修改地址 |
| `ValetOrderForm` | 代客下单 |
| `AfterSaleForm` | 未发货退款入口 |

### 5.3 返回

- 业务成功：下游 `code == 1` → `endSuccess($data, $info)`
- 校验失败：`endValidate(800000, firstError)`，常顺带企微
- 锁冲突：`810507`
- 未登录：`401`
- 未捕获异常：`20000` + trace（有的会把 trace 返回前端，有信息泄露风险）

`actionGoodsListCheck` 特例：`code == 800305` 也走 `endSuccess`（带业务提示码）。

---

## 6. 判断标准对照（课程表）

| 问题 | 本文件答案 |
|---|---|
| action 是否超过 100 行？ | `modifyAddress` 是；`tradePlace` / `valetorder` 接近 |
| 是否直接写 SQL/Repository？ | **有**：`CancelRepeat` 直接 update；`GaReportedResult` 直接 insert；`ModifyAddress` 直接查订单 |
| 是否计算订单金额？ | **有**：改地址差价/运费税在 Controller 分支里调用并组装前端文案 |
| 是否扣库存？ | 主下单在 `PlaceOrderService`，Controller 未见直接扣库存 |
| 是否有大量 if/else 业务分支？ | `ModifyAddress`、`Valetorder`、`GetServiceGuarantee` 是 |

结论：

- **主下单 / 取消 / 收货**：合格的入口编排
- **改地址 / 服务条款拼装 / 取消重复标**：过厚，应下沉 Service
- **`TradeConfirm` 已关闭**，不要再把它当现行确认页

---

## 7. 与上一版（BFF）对照

| 维度 | 旧映射（网关） | 本文件（order-api） |
|---|---|---|
| 命名空间 | `fecshop\app\frontapi\...` | `AppOrderApi\controllers` |
| 调用 | `OrderRequest` HTTP | `OrderService` / `PlaceOrderService` |
| Form | 无 | 有 |
| 锁 | 无 | `LockHandleRedis` |
| try/catch | 几乎无 | 普遍有 |
| confirm/place | Abandoned | confirm **forbidden**；place **现行** |

两层不要混：网关薄转发 → 本文件才是订单域入口。

---

## 8. Node.js 类比

| PHP | Node.js |
|---|---|
| `OrderController` | 订单 HTTP controller |
| Form `*Validate` | Joi / Zod / class-validator |
| `LockHandleRedis` | Redis `SET key NX EX` |
| `PlaceOrderService::tradePlace` | `OrderService.place()` |
| `endSuccess` / `endFail` | `res.json({ code, data, info })` |
| 企微告警 | 失败时打 Sentry / 群机器人 |

---

## 9. 自测题（附简答）

1. **本文件职责？**  
   订单服务 HTTP 入口：接请求、加锁、Form 校验、调 Service、统一返回。

2. **现行下单 action 是哪个？**  
   `actionTradePlace`。`actionTradeConfirm` 已 forbidden。

3. **`try/catch` 干什么？**  
   把库存不足、校验失败、系统异常转成前端能展示的 `endFail`，不是吞错。

4. **哪些逻辑不该留在 Controller？**  
   改地址差价计算、状态分流、服务条款 icon 排序、直接 `OrderRepository::update`。

5. **锁冲突错误码？**  
   `810507`。

---

## 10. 20 分钟复盘练习

- [ ] 口述 `TradePlace`：锁 → Form → PlaceOrderService → 清购物车缓存
- [ ] 在 `ModifyAddress` 里标出 3 段应下沉 Service 的代码
- [ ] 列出用到的 Form 与对应 action
- [ ] 说明为什么 `TradeConfirm` 不能再当主链路

---

## 11. 最终复盘句

order-api 的 `OrderController` 已经具备课程要的骨架：**锁 + Form + Service + 统一响应**。  
读它时重点不是行数，而是分清：哪些 action 只是入口，哪些已经把订单规则写进了 Controller。
