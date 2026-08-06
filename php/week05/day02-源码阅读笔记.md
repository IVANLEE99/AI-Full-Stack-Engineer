# Day 02 源码阅读笔记：PayRequest / OrderRequest

> 对应课程：`week05/day02.md` HTTP 客户端封装  
> 源码映射：`week05/OriginCodes/PayRequest.php`、`week05/OriginCodes/OrderRequest.php`  
> 目标：用“可复述”的方式理解 BFF 网关中的 `*Request` 封装。

---

## 1. 一句话结论

`PayRequest` 和 `OrderRequest` 本质上都是网关层 HTTP Client 包装器：  
**固定 baseURL + 约定 path + 透传参数/请求头/选项**，把 Controller 调用转发到内网微服务。

---

## 2. 阅读对象与定位

| 类 | 定位 | baseURL |
|---|---|---|
| `PayRequest` | 支付域请求封装（支付、退款、支付渠道、回调） | `pay.internal...` |
| `OrderRequest` | 订单域请求封装（购物车、下单、售后、统计、游客、门店等） | `order.internal...` |

共同点：

- 都继承 `BaseApi`，核心网络请求能力在父类；
- 大多数方法本身不写业务逻辑，只做“路由映射式封装”；
- 方法命名通常对应下游服务接口语义。

---

## 3. `PayRequest` 结构拆解

## 3.1 核心模式

典型方法结构：

```php
public function paymentMethods($params)
{
    return $this->get('pay/payment-methods', $params);
}
```

或：

```php
public function paymentRefund($params, $header)
{
    return $this->post('payment/refund', $params, $header, []);
}
```

即：

1. 确定 HTTP 方法（GET/POST）
2. 写固定 path
3. 可选透传 `$header`、`$options`

## 3.2 业务分组（复习用）

- 支付主流程：`paymentMethods`、`paymentParams`、`paySuccess`、`payFail`
- 支付控制：`paymentVoid`、`paymentRefund`、`paymentClose`、`submittedForSettlement`
- 退款域：`confirmRefund`、`paymentApplyRefund`、`artificialRefund`
- 渠道能力：Braintree / PayPal / Klarna / Airwallex / Worldpay
- 查询与导出：`payPageList`、`refundPageList`、`getPaymentDownloadUrl`

## 3.3 值得记住的细节

- 存在“单接口特殊超时”场景：`updateOrderSuccess` 设置了 `CURLOPT_TIMEOUT => 30`
- 有统一转发助手：`postData($uri, $params, $options = [])`
- 说明该封装不只是 path 映射，也支持请求层策略覆盖

---

## 4. `OrderRequest` 结构拆解

## 4.1 规模特征

`OrderRequest` 方法非常多，覆盖：

- 购物车（`cart/*`、`cart-new/*`）
- 下单与确认（`order/*`、`confirm/*`、`storeapi/order/*`）
- 查询（`order-query/*`）
- 售后/换货（`after-sale/*`、`after-exchange/*`、`after-sale-v2/*`）
- 统计看板（`statistics/*`）
- 游客流程（`tourist/*`）

这体现了真实项目里网关“长期演进后体积膨胀”的常见状态。

## 4.2 典型增强点：IP 注入

多个方法会先写入：

```php
$params['ip'] = $this->JPGetRealIp();
```

再调用下游接口。常见于购物车、交易确认、报价等场景。

可复述解释：

- 这类字段通常用于风控、日志归因、地区策略或链路审计；
- 放在 Request 封装层统一注入，比在每个 Controller 重复更可控。

## 4.3 下载链接与查询接口

和 `PayRequest` 类似，`OrderRequest` 也包含“下载 URL 直出”方法，例如：

- `getOrderListDownloadUrl`
- `getTrackingExceptionDownloadUrl`

用于导出类接口，通常返回可访问链接而非标准 JSON 数据。

---

## 5. 两个类对比总结

| 维度 | PayRequest | OrderRequest |
|---|---|---|
| 体量 | 中等 | 很大 |
| 领域聚焦 | 支付/退款/支付渠道 | 订单全链路 + 售后 + 游客 + 统计 |
| 设计风格 | 转发为主，分组较清晰 | 转发为主，但历史包袱更重 |
| 维护难点 | 第三方支付渠道差异 | 方法过多、重复语义、版本并存 |

---

## 6. 发现的可改进点（源码阅读价值）

> 这里是“阅读观察”，不是立刻改代码。

- 命名拼写不一致：如 `rufundLogList`、`dovnloadPdf`
- 个别接口语义待确认：如“新增”类动作出现 GET（例：`touristAddAddress`）
- 存在重复/兼容方法：不同方法命中相同 path（历史兼容痕迹）
- 参数与返回类型声明不完整（老项目风格），和现代 PHP 严格类型实践有差距
- 未使用方法或遗留代码痕迹（如某些 helper）

---

## 7. 架构层面的复习要点

### 7.1 为什么要有 `*Request` 层？

- 避免在 Controller 到处散落 HTTP 细节
- 集中管理服务地址、path、header、timeout、重试策略
- 让 Controller 保持“薄”：取参 -> 调 Request -> 返回

### 7.2 它和 Node.js 的类比

| PHP | Node.js |
|---|---|
| `PayRequest` / `OrderRequest` | `axios.create({ baseURL })` 后的业务 client |
| `BaseApi` | axios 封装基类（拦截器/错误处理/默认配置） |
| `get/post(path, params)` | `client.get/post(url, data)` |

---

## 8. 自测题（附简答）

1. **`getBaseUrl()` 在两个类中起什么作用？**  
   固定下游服务地址，确保同类接口都发往同一服务域。

2. **为什么多数方法只做一行 `return $this->post(...)`？**  
   因为 Request 层主要职责是“协议适配与转发”，不是承载业务规则。

3. **`OrderRequest` 为什么更容易变“胖”？**  
   订单域跨度大、历史久、接口版本迭代频繁，网关聚合点天然会叠加功能。

4. **IP 注入放在 Request 层而不是 Controller 层的价值？**  
   统一策略、减少重复、降低漏传风险。

5. **`PayRequest` 里单个接口设置 30 秒超时说明了什么？**  
   请求层可以做“按接口差异化配置”，不是只能用全局默认值。

6. **阅读这类文件最该关注哪三点？**  
   服务边界（baseURL）、路由映射（path 命名）、请求策略（header/options/超时）。

---

## 9. 20 分钟复盘练习

- [ ] 任选 `PayRequest` 3 个方法，画 `Controller -> Request -> path -> service` 链路
- [ ] 按 path 前缀给 `OrderRequest` 分组（`order/`、`order-query/`、`after-sale/`、`storeapi/`）
- [ ] 列 5 个“潜在技术债”点（命名、语义、重复、版本、类型）
- [ ] 口述：为什么 BFF 要保持薄 Controller

---

## 10. 最终复盘句

这两个文件的核心不是“会不会写 HTTP 请求”，而是：

**你能否看懂网关如何通过统一封装管理跨服务调用边界，并识别出历史项目里的演进痕迹与维护风险。**
