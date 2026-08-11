# Day 03 源码阅读笔记：PayController（薄 Controller）

> 对应课程：`week05/day03.md` 薄 Controller 实践  
> 源码映射：`week05/OriginCodes/PayController.php`  
> 公开路径：`mall-gateway/frontapi/modules/Pay/controllers/PayController.php`  
> 目标：用“可复述”的方式判断网关 action 是否足够薄。

---

## 1. 一句话结论

`PayController` 是典型 BFF 薄 Controller：  
**鉴权（父类）→ 取参 → 可选上下文增强（IP / log_str）→ 调 `PayRequest` / `NewPayRequest` → 统一返回。**  
核心支付规则不在 Controller 里。

---

## 2. 类级定位

| 阅读点 | 结论 |
|---|---|
| 类名 | `PayController` |
| 继承 | `AuthApiController`（鉴权与统一响应入口在父类） |
| 依赖 | `PayRequest`、`NewPayRequest`（新旧支付客户端并存） |
| action 数量 | 36 个 public action + 1 个 private `JPGetRealIp()` |
| 总体风格 | 绝大多数是纯转发；少数回调因第三方协议返回 HTML |

调用链：

```text
前端请求
  → PayController::actionXxx
  → PayRequest / NewPayRequest
  → 支付内网服务
  → endSuccess / endFail（或 loading.html / [ok]）
```

---

## 3. 阅读记录总表

| action | 行数 | 做了哪些事 | 是否足够薄 | 备注 |
|---|---:|---|---|---|
| `actionMethods` | 10 | GET 取参 → `NewPayRequest::checkStandPaymentMethods` → 返回 | ✅ | 标准纯转发 |
| `actionPaymentParams` | 11 | POST 取参 + 注入 IP → 调服务 → 返回 | ✅ | 网关补充客户端 IP |
| `actionAffirmConfirm` | 17 | 取参 + `log_str` → 调服务 → try/catch | ✅ | 模板化转发 |
| `actionCallBackAffirmConfirm` | 15 | POST → 调服务 → 返回 | ✅ | 回调转发 |
| `actionSendEmail` | 15 | POST → `sendEmail` → 返回 | ✅ | 不在网关写邮件逻辑 |
| `actionBraintreeCreateTransaction` | 17 | 取参 + `log_str` → 调服务 → 返回 | ✅ | 渠道支付入口 |
| `actionPaypalQuickPayment` | 17 | 取参 + `log_str` → 调服务 → 返回 | ✅ | 透传 request_id |
| `actionBankTransfer` | 17 | 取参 + `log_str` → 调服务 → 返回 | ✅ | 线下转账转发 |
| `actionSuccess` | 17 | GET → `paySuccess` → 返回 | ✅ | 成功页数据 |
| `actionFail` | 15 | GET → `payFail` → 返回 | ✅ | 失败页数据 |
| `actionGetMethodIconList` | 15 | GET → 图标列表 → 返回 | ✅ | 纯查询转发 |
| `actionSimulatePay` | 15 | GET → 模拟支付 → 返回 | ✅ | 测试接口 |
| `actionGetPayStatus` | 10 | POST → 查支付状态 → 返回 | ✅ | 最薄样板之一 |
| `actionCreateKlarnaTransaction` | 14 | `getParams/getHeaders` → 调服务 | ✅ | Klarna 下单 |
| `actionKlarnaPay` | 16 | 切到 `NewPayRequest::klarnaPay` | ✅ | 新旧客户端迁移痕迹 |
| `actionUpdateKlarnaSession` | 14 | 更新 Klarna 会话 | ✅ | 纯转发 |
| `actionUpdateOrderSuccess` | 14 | `NewPayRequest::updatePaymentSuccess` | ✅ | 订单更新在下游 |
| `actionAirwallexLookUp` | 17 | 切到 `NewPayRequest` 处理异步通知 | ✅ | 迁移注释清晰 |
| `actionCallBackAirwallexReturnUrl` | 24 | 合并 POST/GET/rawBody → 回调 → loading 页 | ⚠️ | 协议适配，非业务变厚 |
| `actionAirwallexQuery` | 17 | 轮询查询 | ✅ | 纯转发 |
| `actionWorldpayConfirm` | 16 | 空 body 校验 → 回调 → loading 页 | ⚠️ | 3DS 跳转中间页 |
| `actionWorldpayWebhook` | 22 | rawBody + header → webhook → `echo '[ok]'` | ⚠️ | 第三方协议要求特殊响应 |
| `actionWorldpayQuery` | 15 | 轮询查询 | ✅ | 纯转发 |
| `actionGetBasePaymentMethodList` | 15 | 基础支付方式列表 | ✅ | 纯转发 |
| `actionGetPaymentMethodList` | 15 | 带参数支付方式列表 | ✅ | 纯转发 |
| `actionPay` | 18 | 取参 + header + log + IP → `pay` | ✅ | 核心支付入口，仍无业务计算 |
| `actionStartPayment` | 16 | 支付前数据处理 | ✅ | 数据处理在下游 |
| `actionAuthorize` | 16 | 3DS 授权 | ✅ | 纯转发 |
| `actionPaymentLogging` | 16 | 支付日志 | ✅ | 日志落下游 |
| `actionPaymentFailReport` | 16 | 失败上报 | ✅ | 纯转发 |
| `actionPaymentEventLogging` | 16 | 事件日志 | ✅ | 纯转发 |
| `actionGetPaymentFailEventParams` | 16 | 组装失败上报参数 | ✅ | 组装逻辑在下游 |
| `actionGetKlarnaInitData` | 16 | Klarna 初始化数据 | ✅ | 纯转发 |
| `actionStripeConfirmCallback` | 15 | Stripe 3DS 回调 → loading 页 | ⚠️ | 回调页场景 |
| `actionConfirm` | 18 | 取参 + log + IP → `confirm` | ✅ | 支付确认入口 |
| `actionUseepayConfirm` | 11 | Useepay 回调 → loading 页 | ✅ | 中间页，短小 |

**统计摘要：**

- 行数范围：约 10～24 行
- 平均约 15 行 / action
- 超过 20 行：仅 `actionCallBackAirwallexReturnUrl`(24)、`actionWorldpayWebhook`(22)
- **没有**出现 50～80 行以上的厚 action

---

## 4. 典型 action 五类拆分

以 `actionPay` 为例：

| 分类 | 代码 | 是否适合 Controller |
|---|---|---|
| 鉴权 | 继承 `AuthApiController` | ✅ |
| 取参 | `request->post()`、`getHeaders()` | ✅ |
| 基础增强 | 注入 `log_str`、`ip_address` | ✅ 网关上下文补充 |
| 调用服务 | `NewPayRequest::instance()->pay(...)` | ✅ |
| 返回响应 | `endSuccess` / `endFail` | ✅ |

**未出现在 Controller 中的：**

- 金额计算
- 订单状态机
- 支付渠道选择规则
- 库存扣减
- 直接 SQL / ORM

---

## 5. 标准薄转发模板（可手写复现）

```php
public function actionCreate(): array
{
    // 1. 鉴权：通常由 AuthApiController / middleware 完成
    // 2. 取参
    $params = Yii::$app->request->post();
    $header = $this->getHeaders();

    // 3. 网关层上下文增强（可选）
    $params['log_str'] = uniqid('pay_create_');
    $params['ip_address'] = $this->JPGetRealIp();

    // 4. 调用支付服务封装
    $result = NewPayRequest::instance()->create($params, $header);

    // 5. 统一返回
    if (1 === $result['code']) {
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

它做了：登录边界、取参、调用、返回。  
它没有做：判断订单能否支付、计算金额、改订单状态、写支付流水。

---

## 6. 三个值得注意的点

### 6.1 新旧客户端并存

多处注释：

```text
//切换到newpay
```

说明 `PayRequest` → `NewPayRequest` 正在迁移。读代码时要以**实际调用类**为准，不要只看旧方法名。

### 6.2 回调 action 可能返回 HTML，不是 JSON

例如：

- `actionCallBackAirwallexReturnUrl`
- `actionWorldpayConfirm`
- `actionStripeConfirmCallback`
- `actionUseepayConfirm`

这些返回 `loading.html`，属于支付跳转/3DS 协议适配，**不等于业务变厚**。

### 6.3 模板重复多

大量 action 结构几乎相同：`try/catch` + `code` 判断 + `endSuccess/endFail`。  
职责仍然清晰，但后续可抽公共方法减少重复。

---

## 7. 不应出现在网关的逻辑（本文件验证）

| 不应出现的逻辑 | 本文件是否出现 | 应放在哪里 |
|---|---|---|
| 订单金额计算 | ❌ 未出现 | 订单/价格服务 |
| 支付状态流转规则 | ❌ 未出现 | 支付服务 |
| 库存扣减 | ❌ 未出现 | 库存/商品服务 |
| 优惠券核销 | ❌ 未出现 | 营销服务 |
| 直接 SQL / 事务 | ❌ 未出现 | 具体业务服务 |

网关实际在做的：

- 传递身份上下文（父类鉴权）
- 传递 IP、header、log 标记
- 基础空 body 校验（部分 webhook）
- 调用下游并统一包装响应
- 适配第三方回调响应格式

---

## 8. Node.js / Express 类比

```js
app.post('/pay/pay', auth, async (req, res) => {
  const result = await payClient.pay({
    ...req.body,
    ip_address: getRealIp(req),
    log_str: uniqid('pay_pay_'),
  }, req.headers);

  if (result.code === 1) {
    return res.json({ code: 0, data: result.data });
  }
  return res.json({ code: result.code, message: result.info });
});
```

| PHP | Node.js |
|---|---|
| `PayController` action | Express route handler |
| `AuthApiController` | `auth` middleware |
| `PayRequest` / `NewPayRequest` | `payClient` |
| `endSuccess` / `endFail` | 统一 response helper |
| 返回 `loading.html` | 支付跳转中间页 |

---

## 9. 自测题（附简答）

1. **为什么说 PayController 整体偏薄？**  
   多数 action 只有取参、调 Request、返回；没有金额、状态机、SQL 等核心业务。

2. **行数超过 20 就一定不薄吗？**  
   不一定。回调类可能因协议适配（合并参数、返回 HTML/`[ok]`）变长，但仍可不承载业务规则。

3. **`ip_address` / `log_str` 算不算业务变厚？**  
   不算。这是入口层上下文增强，方便下游风控、审计和排障。

4. **为什么同时存在 `PayRequest` 和 `NewPayRequest`？**  
   迁移期并存；注释已标明逐步切到 newpay。

5. **判断 action 是否偏厚的简单信号？**  
   出现复杂业务判断、金额计算、状态流转、直接查库，或行数很长且职责混杂。

---

## 10. 20 分钟复盘练习

- [ ] 任选 3 个 action，标出鉴权 / 取参 / 调用 / 返回
- [ ] 手写一个纯转发 `actionCreate` 伪代码
- [ ] 列出 5 条不应出现在网关的逻辑
- [ ] 口述：回调返回 HTML 为什么仍可能算“薄”

---

## 11. 最终复盘句

`PayController` 的价值不在“写支付业务”，而在：

**把前端支付入口收敛成稳定、可观测、可迁移的薄转发层，让真正的支付规则留在支付服务。**
