# Day 04 源码阅读笔记：AuthApiController（鉴权、白名单与反查）

> 对应课程：`week05/day04.md` 鉴权、公参与反查链路  
> 源码映射：`week05/OriginCodes/AuthApiController.php`  
> 公开路径：`mall-gateway/frontapi/modules/AuthApiController.php`  
> 目标：搞清登录鉴权默认怎么开、白名单怎么跳过、签名 Filter 何时生效，并能反查 `pay/pay/methods`。

---

## 1. 一句话结论

`AuthApiController` 是 frontapi 的鉴权基类：  
**默认要求登录 → 白名单 path 可跳过登录 → 无论是否登录都挂签名校验。**  
它本身不读 token、不解析 `user_id`，这些委托给 `LoginAuthFilter` / 父类。

---

## 2. 类级定位

| 阅读点 | 结论 |
|---|---|
| 类名 | `AuthApiController` |
| 继承 | `BaseApiController` |
| 子类示例 | `PayController extends AuthApiController` |
| 核心职责 | 登录开关、白名单、挂载 Filter |
| 不做的事 | 具体业务、直接读 token、完整公参注入 |

请求进入顺序（概念）：

```text
请求进入
  → AuthApiController::beforeAction（判断是否白名单）
  → behaviors 挂载 Filter（登录可选 + 签名必选）
  → 具体 Controller::actionXxx
  → *Request 转发内网服务
```

---

## 3. 阅读记录总表（按 day04 模板）

| 阅读点 | 记录 |
|---|---|
| token 从哪里读取 | **本文件未直接读取**。由 `LoginAuthFilter` 负责（`behaviors()` 条件挂载） |
| 用户 ID 如何得到 | **本文件未解析**。登录通过后由 Filter / 父类写入上下文，供子 Controller 使用 |
| 白名单在哪里定义 | 私有数组 `$freeLoginAuthApiList`（约 110+ 条 path） |
| 公参有哪些 | 本类几乎不做公参注入；仅提供 `getRealIp()` |
| 公参如何传给下游 | 子 action 自行补字段（如 `ip_address`），再经 `*Request` 转发 |
| Filter/behavior 在哪里配置 | `behaviors()`：条件挂 `loginAuthFilter`；始终挂 `VerifySignature` |

---

## 4. 核心机制拆解

### 4.1 默认要登录

```php
private $loginAuth = true;
```

凡继承 `AuthApiController` 的接口，默认都会走登录鉴权。

### 4.2 白名单在 `beforeAction` 关闭登录

```php
public function beforeAction($action)
{
    if (in_array(strtolower(\Yii::$app->request->getPathInfo()), $this->freeLoginAuthApiList)) {
        $this->loginAuth = false;
    }
    return parent::beforeAction($action);
}
```

匹配要点：

1. 取 `getPathInfo()`，例如 `pay/pay/methods`
2. `strtolower` 后与白名单 `in_array` 比对
3. 命中则本次请求 `$loginAuth = false`

### 4.3 `behaviors()` 挂两个 Filter

```php
if ($this->loginAuth) {
    $behaviors['loginAuthFilter'] = [
        'class' => LoginAuthFilter::class,
    ];
}

$behaviors['VerifySignature'] = [
    'class' => VerifySignatureFilter::className(),
];
```

| Filter | 何时生效 | 作用（按命名） |
|---|---|---|
| `LoginAuthFilter` | `$loginAuth === true` | 登录鉴权（读 token、校验、拿用户） |
| `VerifySignatureFilter` | **始终** | 请求签名校验 |

关键记忆：

> **白名单只跳过普通登录，不跳过签名校验。**  
> 白名单 ≠ 安全放行。

### 4.4 IP 工具

`getRealIp()` 从 `HTTP_X_FORWARDED_FOR` / `REMOTE_ADDR` 取客户端 IP。  
注意：`PayController` 另有 `JPGetRealIp()`，说明工具方法存在重复沉淀。

---

## 5. 反查 `pay/pay/methods`（纠正版）

Day 04 课程示例曾写「需要登录」。  
**对照本源码：`pay/pay/methods` 在白名单中。**

```text
'pay/pay/methods', //获取支付列表
```

完整链路：

```text
前端 GET /pay/pay/methods
  ↓
AuthApiController::beforeAction
  - path 命中白名单 → loginAuth = false
  ↓
behaviors
  - 不挂 LoginAuthFilter
  - 仍挂 VerifySignatureFilter
  ↓
PayController::actionMethods()
  - $params = Yii::$app->request->get()
  - NewPayRequest::instance()->checkStandPaymentMethods($params)
  ↓
支付内网服务
  ↓
endSuccess / endFail
```

练习 1 可填：

```text
前端 URL：/pay/pay/methods
模块：Pay
Controller：PayController
action：actionMethods
是否需要登录：否（白名单）
注入公参：本链路无明显公参注入；签名 Filter 仍生效
调用的 Request 类：NewPayRequest
内网服务 path：由 NewPayRequest::checkStandPaymentMethods 决定
返回字段：统一 code/info/data（经 endSuccess/endFail）
```

---

## 6. 鉴权与白名单标注表（支付相关）

| API | 是否需要登录 | 是否白名单 | 额外安全要求 |
|---|---|---|---|
| `pay/pay/methods` | 否 | 是 | 仍走签名校验 |
| `pay/pay/payment-params` | 否 | 是 | 仍走签名校验 |
| `pay/pay/get-pay-status` | 否 | 是 | 仍走签名校验 |
| `pay/pay/pay` | 是（未进白名单） | 否 | 登录 + 签名 |
| `pay/pay/confirm` | 是（未进白名单） | 否 | 登录 + 签名 |
| `pay/pay/worldpay-webhook` | 否 | 是 | 签名/来源校验；特殊响应 `[ok]` |
| `pay/pay/call-back-airwallex-return-url` | 否 | 是 | 支付回调必须校验，不能只靠免登录 |
| `pay/pay/stripe-confirm-callback` | 否 | 是 | 回调页场景 + 签名 |
| `user/code/login` | 否 | 是 | 登录接口本身不应要求已登录 |

---

## 7. 白名单域分组（复习用）

| 域 | 示例 path | 常见原因 |
|---|---|---|
| 支付列表/参数 | `pay/pay/methods`、`pay/pay/payment-params` | 结账页未登录也可展示支付方式 |
| 支付回调/轮询 | `airwallex-*`、`worldpay-*`、`stripe-confirm-callback` | 第三方回调无法带用户登录态 |
| 支付状态 | `pay/pay/get-pay-status` | 支付结果轮询 |
| 订单确认/售后 | `order/trade/confirm`、`order/after-sale/*` | 部分游客或分享链路 |
| 营销优惠券 | `market/coupon/*` | 活动页公开接口 |
| 收藏 | `user/favorate*` | 需警惕是否过度放权 |
| 登录验证码 | `user/code/login`、`user/code/verify` | 登录前接口 |
| 门店授权 | `store/store/store-*-authorize` | 特殊授权流 |

---

## 8. 公参注入：本文件边界说明

本文件**不是**公参注入主战场。

你能确认的：

- 有 IP 获取工具 `getRealIp()`
- 登录态由 Filter 处理（细节不在本文件）
- 子 Controller 可手动补 `ip_address`、`log_str` 等

你暂时看不到、需要继续追的：

- token 具体从 header 还是 query 读取 → `LoginAuthFilter`
- `user_id` / `site_id` / `lang` / `trace_id` 如何注入 → `BaseApiController` 或相关 Filter
- 签名算法与密钥来源 → `VerifySignatureFilter`

本地 `OriginCodes` 暂无上述 Filter / 父类文件，反查时标记为「下一跳」。

---

## 9. 风险观察

1. **白名单过长且集中在一个数组**：误加敏感接口成本极高。  
2. **收藏夹等用户数据接口也在免登录列表**：需确认下游是否二次鉴权。  
3. **存在重复条目**（如 `order/order/get-order-info` 出现两次），说明维护有历史包袱。  
4. **白名单只跳过登录，不代表安全**：支付回调类接口必须保留签名/来源校验。  
5. **课程示例与源码不一致**：`pay/pay/methods` 实际免登录，学习时以源码为准。

---

## 10. Node.js 类比

| PHP | Node.js |
|---|---|
| `AuthApiController` | 公共鉴权基类 / 中间件入口 |
| `$freeLoginAuthApiList` | `publicRoutes` / skip-auth list |
| `beforeAction` | auth 前判断是否 skip |
| `LoginAuthFilter` | `authMiddleware` |
| `VerifySignatureFilter` | `signatureMiddleware`（始终启用） |
| `getRealIp()` | 从 `x-forwarded-for` 取真实 IP |
| 反查链路 | 从前端 Network 一路追到 route + client |

---

## 11. 自测题（附简答）

1. **`AuthApiController` 自己读 token 吗？**  
   不读。委托给 `LoginAuthFilter`。

2. **白名单跳过了什么？没跳过什么？**  
   跳过登录 Filter；不跳过 `VerifySignatureFilter`。

3. **`pay/pay/methods` 要不要登录？**  
   按本源码：不要，它在 `$freeLoginAuthApiList` 中。

4. **为什么支付回调常进白名单？**  
   第三方回调通常没有用户登录态，但仍必须做签名或来源校验。

5. **公参注入主要在本文件吗？**  
   不是。本文件几乎只负责登录开关与 Filter 挂载。

---

## 12. 20 分钟复盘练习

- [ ] 口述 `beforeAction` + `behaviors` 的执行关系
- [ ] 独立画出 `pay/pay/methods` 完整反查图（含白名单结论）
- [ ] 从白名单里挑 3 个支付回调接口，说明为何免登录但仍要签名
- [ ] 列出下一步要读的文件：`LoginAuthFilter`、`VerifySignatureFilter`、`BaseApiController`

---

## 13. 最终复盘句

`AuthApiController` 的价值是：

**把“默认登录 + 白名单例外 + 始终验签”收敛成网关统一入口策略，让具体业务 Controller 只关心转发，不各自实现鉴权开关。**
