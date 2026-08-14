# Day 01 源码阅读笔记：OrderController（订单入口）

> 对应课程：`week06/day01.md` OrderController 结构与 action  
> 源码映射：`week06/OriginCodes/OrderController.php`  
> 公开路径（课程）：`order-api/controllers/OrderController.php`  
> 实际映射：BFF `mall-gateway/frontapi/modules/Order/controllers/OrderController.php`  
> 目标：看清订单 Controller 是入口编排，还是已经把复杂业务写进来了。

---

## 1. 一句话结论

这份 `OrderController` 是 **BFF 薄入口**：  
**取参 → 调 `OrderRequest`（偶发 `NewUserRequest`）→ `endSuccess` / `endFail`。**  
没有 Form、没有本地 `OrderService`，也没有金额/库存/状态机。旧下单 `actionConfirm` / `actionPlace` 已直接返回 `Abandoned`。

---

## 2. 路径纠正（必先记住）

| 课程预期 | 实际这份源码 |
|---|---|
| `mall-core` 订单服务 Controller | 网关 `frontapi` OrderController |
| Form 校验 | **没有 Form** |
| 调用 `OrderService` | 调用 **`OrderRequest` HTTP 客户端** |
| action 内 `try/catch` | **几乎没有**；靠下游 `code` |
| 复杂下单在本类 | `confirm/place` 已废弃；门店下单仍在 |

课程链路：

```text
前端 → BFF → OrderController → Form → OrderService → Repository → DB
```

本文件实际链路：

```text
前端 → BFF OrderController → OrderRequest → 订单内网服务 → endSuccess/endFail
```

Day 02 的 `OrderService` 才是课程说的业务编排层；今天这份是网关侧入口。

---

## 3. 类级结构（练习 1）

```text
类名：OrderController
继承：AuthApiController（默认登录 + 签名 Filter）
action 数量：38 个 public + 1 个 private JPGetRealIp()
最重要的 3 个 action：
  1. actionStoreTradePlace（现存下单入口之一）
  2. actionList / actionGetList（订单列表，新旧并存）
  3. actionCancel / actionOrderReceive（订单生命周期）
统一响应方法：endSuccess / endFail
异常处理方式：本文件几乎无 try/catch；用下游返回的 code 分支
Form：无
Service：无本地 OrderService
```

依赖：

- `OrderRequest`：绝大多数转发
- `NewUserRequest`：仅 `actionAddPickUpAddress` 在 `type==2` 时走订阅
- `AfterSaleRequest`：**use 了但未使用**

---

## 4. 阅读记录总表（按 day01 模板）

| action | 入参 | 调用 Form | 调用对象 | 返回 | 是否过厚 |
|---|---|---|---|---|---|
| `actionGoodsListCheck` | POST | 无 | `OrderRequest::goodsListCheck` | `endSuccess` / `endFail` | ✅ 薄 |
| `actionCartGoodsCheck` | POST | 无 | `cartGoodsCheck` | 同上 | ✅ 薄 |
| `actionConfirm` | — | — | 未执行到 `orderConfirm` | **直接 `endFail(404, 'Abandoned')`** | 已废弃 |
| `actionPlace` | — | — | 未执行到 `orderPlace` | 同上 | 已废弃 |
| `actionList` | `getParams()` | 无 | `orderList` | 同上 | ✅ 薄 |
| `actionDetail` | `getParams()` | 无 | `orderDetail` | 同上 | ✅ 薄 |
| `actionCancel` | params + headers | 无 | `cancelOrder` | 同上 | ✅ 薄 |
| `actionOrderReceive` | params + headers | 无 | `orderReceive` | 同上 | ✅ 薄 |
| `actionModifyAddress` | params + headers | 无 | `modifyAddress` | 有 `endSuccess` 无 `return` | ✅ 薄 |
| `actionOrderRefund` | `getParams()` | 无 | `orderRefund` | 同上 | ✅ 薄 |
| `actionUpdateBillAddress` | params + headers | 无 | `new OrderRequest()->updateBillAddress` | 先失败后成功 | ✅ 薄 |
| `actionOrderBillAddressDetail` | params + headers | 无 | `orderBillAddressDetail` | 同上 | ✅ 薄 |
| `actionOrderAddressDetail` | params + headers | 无 | `orderAddressDetail` | 同上 | ✅ 薄 |
| `actionOldOrderUrl` | params + headers | 无 | `oldOrderUrl` | 同上 | ✅ 薄 |
| `actionGaReportedResult` | params + headers | 无 | `gaReportedResult` | 同上 | ✅ 薄 |
| `actionUpdateLocationType` | params + headers | 无 | `updateLocationType` | 同上 | ✅ 薄 |
| `actionGetPayStatus` | POST | 无 | `getPayStatus` | 成功带 info | ✅ 薄 |
| `actionGetTransSnapshot` | POST | 无 | `getTransSnapshot` | 同上 | ✅ 薄 |
| `actionGetOrderGoodsTrackInfo` | POST | 无 | `getOrderGoodsTrackInfo` | 同上 | ✅ 薄 |
| `actionGetOrderDetailTrackInfo` | POST | 无 | `getOrderDetailTrackInfo` | 同上 | ✅ 薄 |
| `actionGetOrderRecommendGoods` | POST | 无 | `getOrderRecommendGoods` | 同上 | ✅ 薄 |
| `actionGetOrderServiceGuarantee` | POST | 无 | `getOrderServiceGuarantee` | 同上 | ✅ 薄 |
| `actionGetServiceGuarantee` | POST | 无 | `getServiceGuarantee` | 同上 | ✅ 薄 |
| `actionAddPickUpAddress` | params + headers | 无 | `type==2` → `NewUserRequest::subscribe`，否则 `addPickUpAddress` | 空 data 转 null | ⚠️ 略厚（入口分流） |
| `actionGetOrderGoodsAftershipInfo` | POST + headers | 无 | `getOrderGoodsAftershipInfo` | 同上 | ✅ 薄 |
| `actionForAfterSaleToUser` | `getParams()` + headers | 无 | `forAfterSaleToUser` | 同上 | ✅ 薄 |
| `actionGetShipAndBillAddress` | GET + headers | 无 | `getShipAndBillAddress` | 同上 | ✅ 薄 |
| `actionRepeat` | params + headers | 无 | `repeat` | 空 data 转 null | ✅ 薄 |
| `actionGetInvoice` | params + headers | 无 | `getInvoice` | 同上 | ✅ 薄 |
| `actionGetList` | params + headers | 无 | `getOrderList`（v4） | 同上 | ✅ 薄 |
| `actionGetOrderBanner` | params + headers | 无 | `getOrderBanner` | 同上 | ✅ 薄 |
| `actionGetInfoSecurity` | params + headers | 无 | `getInfoSecurity` | 同上 | ✅ 薄 |
| `actionGetOrderGoodsCardList` | params + headers | 无 | `getOrderGoodsCardList` | 空 data 转 `[]` | ✅ 薄 |
| `actionGetThisItemHelp` | params + headers | 无 | `getThisItemHelp` | 空 data 转 null | ✅ 薄 |
| `actionOrderAnomalyDetect` | params + headers | 无 | `orderAnomalyDetect` | 同上 | ✅ 薄 |
| `actionGetOrderInfo` | params + headers | 无 | `getOrderInfo` | 先失败后成功 | ✅ 薄 |
| `actionStoreTradeConfirm` | POST + 注入 `ip_address` | 无 | `orderStoreTradeConfirm` | 统一返回 | ✅ 薄 |
| `actionStoreTradePlace` | POST + 注入 `ip_address` | 无 | `orderStoreTradePlace` | 统一返回 | ✅ 薄 |

统计：

- 绝大多数 action 约 8～15 行
- **没有**超过 100 行的厚 action
- 没有直接 SQL、金额计算、库存扣减

---

## 5. 练习 2：追踪 `actionStoreTradePlace`

```text
action：actionStoreTradePlace（门店下单）
入参：Yii::$app->request->post()，并注入 ip_address
Form：无
Service：无本地 OrderService
调用：OrderRequest::instance()->orderStoreTradePlace($params)
下游 path（Week 05 OrderRequest）：storeapi/order/trade-place
出参：成功 endSuccess($result['data'])
错误：endFail(code, info, data, error)
是否过厚：否
```

代码职责分类：

| 分类 | 代码 | 适合 Controller？ |
|---|---|---|
| 取参 | `$params = request->post()` | ✅ |
| 上下文增强 | `$params['ip_address'] = JPGetRealIp()` | ✅ |
| 调用 | `OrderRequest::orderStoreTradePlace` | ✅ |
| 返回 | `endSuccess` / `endFail` | ✅ |
| 业务逻辑 | 无金额/库存/状态流转 | ✅ 未混入 |

废弃对照：

```text
actionConfirm / actionPlace
一进来就 return endFail(404, 'Abandoned')
后面的 OrderRequest 调用是死代码
说明旧「确认页 / 下单」已迁走
```

---

## 6. 标准薄转发模板

```php
public function actionDetail()
{
    $params = $this->getParams();
    $result = OrderRequest::instance()->orderDetail($params);

    if (1 == $result['code']) {
        return $this->endSuccess($result['data']);
    }

    return $this->endFail(
        $result['code'],
        $result['info'],
        $result['data'],
        $result['error'] ?? ''
    );
}
```

成功约定：下游 `code == 1` → `endSuccess`。  
失败约定：其它 code → `endFail(code, info, data, error)`。

`try/catch` 的课程价值仍成立：把异常变成前端能理解的响应。  
本文件把这一步下沉到了 `BaseApi` / 订单服务，网关只判断业务 `code`。

---

## 7. 三个值得注意的点

### 7.1 旧下单已废弃，门店下单还在

- `actionConfirm` / `actionPlace`：404 Abandoned
- `actionStoreTradeConfirm` / `actionStoreTradePlace`：仍转发，并注入 IP

读代码时不要把死代码当成现行下单主链路。

### 7.2 列表双入口

| action | Request 方法 | 含义 |
|---|---|---|
| `actionList` | `orderList` | 旧查询 |
| `actionGetList` | `getOrderList` | v4 列表 |

同类能力并存，是演进痕迹。

### 7.3 实例化与工具方法不统一

- 多数：`OrderRequest::instance()`
- 地址类：`new OrderRequest()`
- `JPGetRealIp()` 与 `PayController` 重复；`AuthApiController` 已有 `getRealIp()`
- 部分 `endSuccess` 不写 `return`，依赖父类内部结束请求

唯一略偏的 action：`actionAddPickUpAddress` 用 `type` 在订单自提和用户订阅之间分流。这是入口编排，还不算核心订单规则。

---

## 8. 判断标准对照

| 问题 | 本文件答案 |
|---|---|
| action 是否超过 100 行？ | 否 |
| 是否直接写 SQL？ | 否 |
| 是否计算订单金额？ | 否 |
| 是否扣库存？ | 否 |
| 是否有大量 if/else 业务分支？ | 仅 `AddPickUpAddress` 的 type 分流 |

结论：**作为 BFF Controller，整体合格偏薄。**  
真正的订单规则应到 Day 02 的 `OrderService` 里找。

---

## 9. Node.js 类比

| PHP | Node.js |
|---|---|
| `OrderController` | 订单 route 集合 |
| `AuthApiController` | auth + signature middleware |
| `OrderRequest` | `orderClient`（axios 封装） |
| `endSuccess` / `endFail` | `res.json({ code, data, info })` |
| 无 Form | 校验若存在，应在下游 service / schema |
| `Abandoned` | 旧路由直接 404 / gone |

---

## 10. 自测题（附简答）

1. **这份 OrderController 的核心职责是什么？**  
   订单域 HTTP 入口：接请求、调 `OrderRequest`、统一返回。不承载金额/库存规则。

2. **它调用 Form / OrderService 了吗？**  
   没有。课程预期在 `mall-core`；这份是网关转发层。

3. **`actionPlace` 还能下单吗？**  
   不能。直接 `endFail(404, 'Abandoned')`。现存相关入口是门店 `actionStoreTradePlace`。

4. **为什么这里几乎没有 `try/catch`？**  
   网关只转发并判断下游 `code`；异常转换更多在 `BaseApi` / 订单服务。

5. **怎么判断 action 好不好？**  
   好：取参 → 调用 Request → 统一返回。坏：在 Controller 里查商品、算价、扣库存、改状态。

---

## 11. 20 分钟复盘练习

- [ ] 口述本文件与课程「Form → Service」链路的差异
- [ ] 独立追踪 `actionStoreTradePlace` 到 `OrderRequest` path
- [ ] 标出 2 个废弃 action 和 2 个仍在用的下单/确认 action
- [ ] 列出 5 条不应出现在本 Controller 的逻辑，并确认源码中未出现

---

## 12. 最终复盘句

网关 `OrderController` 的价值不是“写订单业务”，而是：

**把订单相关 HTTP 入口收敛成薄转发层，让真正的校验、锁、状态机留在订单服务。**
