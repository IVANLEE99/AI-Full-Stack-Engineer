# Day 04 源码阅读笔记：AuthApiController（鉴权基类、白名单与反查）

> 对应课程：`week05/day04.md` 鉴权、公参与反查链路  
> 源码映射：`week05/OriginCodes/AuthApiController.php`  
> 公开路径：`mall-gateway/frontapi/modules/AuthApiController.php`  
> 目标：讲清一个请求「需不需要登录、凭什么判断、白名单放行了什么、没放行什么」，并能反查 `pay/pay/methods`。

---

## 1. 一句话结论

`AuthApiController` 是 frontapi 模块的**鉴权基类**，全文只做三件事：

```text
1. 用一张 104 条的路径白名单，决定本次请求要不要挂 LoginAuthFilter
2. 无论如何都挂 VerifySignatureFilter（签名校验，不参与白名单）
3. 提供一个取真实 IP 的工具方法给子类用
```

它**不做登录态解析**，也不做公参注入 —— 这两件事分别在 `LoginAuthFilter` 和 `BaseApi` / `BaseApiController` 里。

一句话记住它的策略：

> **默认要登录 → 白名单 path 跳过登录 → 无论是否登录都验签。**  
> 它只负责「这单要不要过安检」这个判断本身，不负责安检怎么做。

---

## 2. 类级定位

| 阅读点 | 结论 |
|---|---|
| 类名 | `AuthApiController` |
| 命名空间 | `fecshop\app\frontapi\modules` —— 只属于 frontapi（前台 API） |
| 继承 | `BaseApiController`（本文件未提供，需另读） |
| 子类示例 | `PayController extends AuthApiController`（Day 03 的薄 Controller 基本都是它的子类） |
| 成员 | 1 个白名单数组、1 个登录开关、3 个方法 |
| 方法 | `beforeAction()`、`behaviors()`、`getRealIp()` |
| 核心职责 | 登录开关、白名单判定、挂载 Filter |
| 不做的事 | 具体业务、直接读 token、完整公参注入 |
| 状态 | 纯请求期状态，不碰 DB、不碰下游服务 |

请求进入顺序（概念）：

```text
请求进入
  → AuthApiController::beforeAction（判断是否白名单）
  → behaviors 挂载 Filter（登录可选 + 签名必选）
  → 具体 Controller::actionXxx
  → *Request 转发内网服务
```

精确到 Yii2 生命周期的调用链：

```text
前端请求 /pay/pay/methods
  → Yii2 路由解析 → PayController
  → Controller::runAction()
    → AuthApiController::beforeAction()   ← 本文件：白名单判定
    → Controller::beforeAction()          ← 触发 EVENT_BEFORE_ACTION
      → ensureBehaviors()                 ← 这时才真正挂 Filter
        → LoginAuthFilter（本次是否需要）
        → VerifySignatureFilter（永远需要）
  → PayController::actionMethods()
  → NewPayRequest::checkStandPaymentMethods()
  → 支付内网服务
```

---

## 3. 全文结构速览

| 区块 | 行号 | 作用 | 评价 |
|---|---|---|---|
| `$freeLoginAuthApiList` | 11–128 | 免登录路径白名单，104 条 | 核心，也是最大维护负担 |
| `$loginAuth = true` | 131 | 默认需要登录（**fail-closed**） | ✅ 正确默认值 |
| `beforeAction()` | 133–139 | 命中白名单则关掉登录校验 | 关键在**顺序**，见 5.3 |
| `behaviors()` | 145–160 | 按开关挂 Filter，签名 Filter 无条件挂 | 设计意图清晰 |
| `getRealIp()` | 167–176 | 从 `X-Forwarded-For` 取客户端 IP | 与 `BaseApi::JPGetRealIp()` 重复实现 |

---

## 4. 阅读记录总表（按 day04 模板）

| 阅读点 | 记录 |
|---|---|
| token 从哪里读取 | **本文件不读 token**。登录态解析在 `LoginAuthFilter` 内完成（请求头/参数携带，具体字段需读 Filter） |
| 用户 ID 如何得到 | **本文件不解析**。登录通过后由 Filter 写入控制器上下文（由 `@property array $member` 推断），供子 Controller 使用 |
| 白名单在哪里定义 | `AuthApiController::$freeLoginAuthApiList`，104 条全路径字符串，`private` |
| 公参有哪些 | 本类几乎不做公参注入；仅提供 `getRealIp()`。`user-agent` / `ip_address` / `partition-for` / `hm-request-id` 等公参在 `BaseApi::generateRequestId()` 里拼 header |
| 公参如何传给下游 | 子 action 自行补字段（如 `ip_address`、`log_str`），再经继承 `BaseApi` 的 `*Request` 统一拼进 curl header |
| Filter/behavior 在哪里配置 | `AuthApiController::behaviors()`：`loginAuthFilter` 条件挂载、`VerifySignature` 无条件挂载；父类 behavior 由 `parent::behaviors()` 合并 |

---

## 5. 核心机制拆解

### 5.1 默认要登录

```php
private $loginAuth = true;
```

凡继承 `AuthApiController` 的接口，默认都会走登录鉴权。**新增接口默认受保护**，这是这里唯一的正确默认值（fail-closed）。

### 5.2 白名单在 `beforeAction` 关闭登录

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

1. 取 `getPathInfo()`，例如 `pay/pay/methods`（无前导斜杠、无域名、无 query）
2. `strtolower` 后与白名单 `in_array` 比对
3. 命中则本次请求 `$loginAuth = false`

### 5.3 顺序即正确性：Yii2 的 behavior 是懒加载的

```php
if (in_array(...)) {
    $this->loginAuth = false;          // 先改状态
}
return parent::beforeAction($action);  // 再调父类
```

**为什么不能反过来写？** 因为 Yii2 的 behavior 是**懒加载**的：

```text
parent::beforeAction()
  → $this->trigger(EVENT_BEFORE_ACTION)
    → $this->ensureBehaviors()
      → 第一次调用 $this->behaviors()   ← 这里才读取 $loginAuth
        → attachBehavior(...)
```

`behaviors()` 只在**第一次触发事件时**被调用一次，结果缓存在 `_behaviors` 里。如果先 `parent::beforeAction()`，`behaviors()` 就已经按 `loginAuth = true` 把 `LoginAuthFilter` 挂上去了，再改开关没有任何作用。

> 这是本文件唯一一处「顺序即正确性」的代码。用 Laravel / Express 的思维很容易写反 —— 那边的 middleware 是提前注册好的，运行时改状态确实无效；这里恰好因为懒加载才留出了这个窗口。

对应 Node 写法（Express 里做不到这件事，只能在中间件内部自己判断）：

```js
// Express：匹配在中间件内部完成，没有“先改状态再挂 middlewares”的窗口
app.use((req, res, next) => {
  const isPublic = PUBLIC_ROUTES.has(req.path);
  if (isPublic) return next();     // 等价于不挂 loginAuthFilter
  return loginAuth(req, res, next);
});
```

### 5.4 `behaviors()` 挂两个 Filter

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
> **白名单 ≠ 安全放行。**

### 5.5 IP 工具

`getRealIp()` 从 `HTTP_X_FORWARDED_FOR` / `REMOTE_ADDR` 取客户端 IP。  
注意：`PayController` 另有 `JPGetRealIp()`，`BaseApi` 也有同名逻辑，说明工具方法存在重复沉淀（详见第 13 节）。

---

## 6. 反查 `pay/pay/methods`（纠正版）

Day 04 课程示例曾写「需要登录」。  
**对照本源码：`pay/pay/methods` 在白名单中。**

```php
'pay/pay/methods', //获取支付列表   ← 第 24 行
```

完整链路：

```text
前端 GET /pay/pay/methods
  ↓
AuthApiController::beforeAction
  - pathInfo = "pay/pay/methods"，命中白名单 → loginAuth = false
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

对照一个**非白名单**接口，例如 `pay/pay/pay`：pathInfo 不命中 → `loginAuth` 保持 `true` → 两个 Filter 都挂 → 必须带有效登录态 + 签名。

---

## 7. 白名单实测数据

对 `$freeLoginAuthApiList` 做统计（脚本解析字符串字面量）：

| 指标 | 数值 |
|---|---:|
| 条目总数 | 104 |
| 去重后 | 103 |
| 重复项 | `order/order/get-order-info` 出现 2 次（第 119、125 行） |

按模块分布：

| 前缀 | 条数 | 性质 |
|---|---:|---|
| `pay/pay` | 17 | 支付方式、支付参数、第三方回调/webhook |
| `order/order` | 15 | 订单查询、地址修改、发票 |
| `order/after-sale` | 12 | 售后全流程 |
| `user/favorate` | 12 | 收藏（单条 SKU） |
| `market/coupon` | 11 | 优惠券查询与领取 |
| `user/favorate-list` | 8 | 收藏夹 |
| `order/after-exchange` | 4 | 换货 |
| `order/added-service` | 4 | 增值服务 |
| `order/invite-comments` | 4 | 邀请评价 |
| `store/store` | 4 | 门店授权 |
| `user/code` | 3 | 登录 / 验证 / 注册 |
| 其余 9 个前缀 | 各 1–2 | 杂项（`order/trade`、`order/confirm`、`order/pay`、`order/order-v3`、`order/feedback`、`order/impact`、`order/delivery-or-pick-up`、`pay/payment-request`、`pay/pay-credential`） |

白名单长度说明一件事：**这不是「少数公开接口」，而是 frontapi 里相当大一部分接口都免登录。**
所以真正的问题不是「白名单机制对不对」，而是「这份名单凭什么是这样」。

---

## 8. 白名单分层：104 条其实有 5 种完全不同性质

把列表按「为什么可以免登录」重新分类，比按模块分组更有用：

| 层级 | 例子 | 免登录的合理理由 | 白名单后谁来兜底 |
|---|---|---|---|
| **A. 本来就必须公开** | `user/code/login`、`user/code/verify`、`user/code/register` | 登录前不可能有登录态 | 无（但有频控/验证码） |
| **B. 第三方回调** | `pay/pay/worldpay-webhook`、`pay/pay/stripe-confirm-callback`、`pay/pay/airwallex-*`、`pay/pay/useepay-confirm` | 请求方是支付渠道，不是用户 | **签名/来源校验**（本文件的 `VerifySignatureFilter` 或渠道 SDK 验签） |
| **C. 游客可用** | `pay/pay/methods`、`market/coupon/get-item-coupon-list`、`user/favorate/*` | 未登录也要能看支付方式、商品券、加收藏 | 下游按 device/token 判身份，或只读写匿名数据 |
| **D. 订单归属校验** | `order/order-v3/detail`、`order/order/get-order-info`、`order/after-sale/*` | 查单/售后用「订单号 + 邮箱/手机」验证身份，不依赖登录态 | **下游必须校验订单归属** |
| **E. 写操作 —— 最危险** | `order/order/modify-address`、`order/order/update-bill-address`、`store/store/store-cash-payment-authorize`、`store/store/clearance-price-authorize` | 名字上是「放权路由」，语义却是**改地址、现金支付授权、清仓价授权** | 下游必须强校验，否则就是越权 |

`pay/pay/methods` 属于 C 层，这解释了 Day 03 的现象：

```php
// PayController::actionMethods() —— 只取 GET 参数，完全不碰 $this->member
$params = Yii::$app->request->get();
$result = NewPayRequest::instance()->checkStandPaymentMethods($params);
```

白名单接口的 action **不能依赖 `$this->member`**，因为 `LoginAuthFilter` 压根没挂，用户上下文是空的。这是反查时判断「某个 action 是否真的能公开」的硬标准。

---

## 9. 鉴权与白名单标注表（支付相关）

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

## 10. 公参注入：本文件边界说明

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

## 11. 风险清单（对照 day04.md 1.7 节）

| 风险 | 本文件的具体位置 | 判断 |
|---|---|---|
| 应鉴权接口进了白名单 | `order/order/modify-address`、`order/order/update-bill-address` | ⚠️ 高危，必须确认下游有订单归属校验 |
| 白名单接口缺少签名校验 | 无 | ✅ 因为 `VerifySignatureFilter` 是**无条件挂载**的，与登录白名单解耦 |
| 同一接口多个变体绕过 | 用 `getPathInfo()` 做**全等匹配**，不是前缀匹配 | ✅ 不会误放行 `pay/pay/methods/xxx` |
| 名单写错导致「该公开的反而不公开」 | 大小写不一致 / 末尾斜杠 / `.html` 后缀 | ⚠️ 失配方向是 fail-closed（更安全），但会变成线上 401 |
| 测试接口进生产 | `pay/pay/simulate-pay`（模拟支付） | ⚠️ 需确认是否按环境开关 |
| 现金支付 / 清仓价授权免登录 | `store/store/store-cash-payment-authorize`、`store/store/clearance-price-authorize` | ⚠️ 需确认门店侧有独立授权凭证 |
| 用户数据接口免登录 | `user/favorate*`（共 20 条） | ⚠️ 需确认下游是否二次鉴权 |
| 课程示例与源码不一致 | `pay/pay/methods` 实际免登录 | ✅ 以源码为准 |

几个具体到行的问题：

1. **`order/order/get-order-info` 重复两次**（119、125 行）—— 无害，但说明这张表没有 lint / 去重检查，维护靠人肉。
2. **注释与内容错配**：25、26 行两条都注释「获取增值服务列表」（`order/added-service/list` 与 `order/added-service/service-list`）；119 行上方注释「//申请售后」下面跟的却是 `get-order-info`。
3. **`user/favorate`** 是历史拼写错误（应为 favorite），全项目沿用，**不要单方面改**，否则与路由不一致。
4. **`strtolower()` 与全小写名单**：如果以后有人往名单里加 `Pay/Pay/Methods`，会**永远匹配不上**（静默失效，接口反而要求登录）。`strtolower` 只处理了请求侧，没处理配置侧。
5. **`in_array()` 未加 strict**：这里全是字符串，实际安全；但严格写法 `in_array($path, $list, true)` 能避免未来混入数字键时的隐式转换。
6. **`@property array $member` 标在了 `behaviors()` 的方法 docblock 上** —— 注解本身应该是类级 docblock，IDE 才能识别。这条注释正好提示了：`$member` 是 `LoginAuthFilter` 挂到控制器上的属性。
7. **`VerifySignatureFilter::className()`** 与上一行的 `LoginAuthFilter::class` 风格不一致；`className()` 自 Yii 2.0.14 起已废弃，统一用 `::class`。

---

## 12. 白名单机制本身的工程评价

| 维度 | 现状 | 更好的做法 |
|---|---|---|
| 存放位置 | 硬编码在基类 `private` 数组 | 放 `params`/配置文件，或按模块拆分后由基类合并 |
| 可扩展性 | `private` → 子类**无法**追加自己的公开接口 | `protected static` + `array_merge`，允许模块自声明 |
| 可读性 | 104 条平铺，靠注释分组 | 按 A–E 五层分组并标注「兜底校验方式」 |
| 可验证性 | 无测试 | 单测断言：每条白名单路径都能路由到真实 action（防止改名后残留死条目） |
| 匹配方式 | 每个请求 `in_array` 线性扫描 104 条 | 微秒级开销，当前**没必要**优化；若涨到千级再改 `array_flip` + `isset` |
| 安全默认值 | `$loginAuth = true` | ✅ 保持，新增接口默认受保护 |

一句话：**机制是好的（默认拒绝 + 白名单放行 + 签名独立），坏在名单靠人肉维护。**

---

## 13. `getRealIp()` 逐行

```php
$ip = getenv("HTTP_X_FORWARDED_FOR");
if (empty($ip)) {
    $ip = getenv("REMOTE_ADDR");
}
$ip = explode(',', $ip);
return trim($ip[0]) ?? '';
```

| 观察点 | 说明 |
|---|---|
| 与 `BaseApi::JPGetRealIp()` 重复 | 同一段逻辑（甚至同样的 `explode(',')` + `trim($ip[0])`）在多处各写一份，改一处漏一处 |
| `X-Forwarded-For` 取第一个 | 只有在边缘网关**覆盖写**该头时才可信；若代理是**追加**，攻击者自带的第一个值会被当成真实 IP → 风控/限流可被绕过 |
| `?? ''` 是无效防御 | `explode()` 永远至少返回一个元素，`trim()` 也永不返回 null，这行判断实际不生效 |
| `getenv("HTTP_X_...")` | 依赖 `variables_order` 含 `S`；Yii2 里更稳的是 `Yii::$app->request->headers->get('X-Forwarded-For')`，或配好 `trustedHosts` 后用 `Yii::$app->request->userIP` |
| docblock 说「仅适用于 cgi 运行方式」 | 说明作者知道 CLI 下 `getenv` 不可靠；在 console 场景应走 `Yii::$app->request->isConsoleRequest` 分支 |
| `protected` | 子类可直接用，Day 03 里 `PayController` 就是用类似方法给支付参数注入 `ip_address` |

---

## 14. Node.js 类比

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

## 15. 自测题（附简答）

1. **`AuthApiController` 自己读 token 吗？**  
   不读。委托给 `LoginAuthFilter`。

2. **为什么 `$this->loginAuth = false` 必须写在 `parent::beforeAction()` 之前？**  
   因为父类 `beforeAction` 触发 `EVENT_BEFORE_ACTION` → `ensureBehaviors()` → 此时才第一次调用 `behaviors()` 并挂载 Filter。写反了 Filter 已经按 `true` 挂完，开关失效。

3. **白名单跳过了什么？没跳过什么？**  
   只跳过「用户登录校验」（`LoginAuthFilter`）。`VerifySignatureFilter` 无条件挂载，签名/来源校验照旧生效。

4. **`pay/pay/methods` 要不要登录？**  
   按本源码：不要，它在 `$freeLoginAuthApiList` 第 24 行。课程示例的「需要登录」是错的，以源码为准。

5. **为什么支付回调常进白名单？**  
   第三方回调通常没有用户登录态，但仍必须做签名或来源校验。

6. **为什么不能只用前缀匹配（如 `pay/pay/*`）？**  
   前缀匹配会把未来的写接口一起放行（例如新增 `pay/pay/refund` 自动免登录）。全等匹配 + 默认拒绝（fail-closed）是更安全的默认值。

7. **`pay/pay/methods` 免登录说明 Controller 里不能做什么？**  
   不能依赖 `$this->member` / `user_id`（登录 Filter 未挂，身份为空），否则要么报错要么被传入空值。

8. **这张表最大的工程风险是什么？**  
   不是性能，也不是 `in_array`，而是**人肉维护的 104 条里混进了语义上是写操作的接口**（改地址、现金支付授权、清仓价授权），一旦下游忘了做归属校验，就是越权。

9. **`getRealIp()` 为什么可能被伪造？**  
   直接信任 `X-Forwarded-For` 的第一段。只有当边缘代理**重写**该头而不是追加时才可信。

10. **公参注入主要在本文件吗？**  
    不是。本文件几乎只负责登录开关与 Filter 挂载；公参在 `BaseApi::generateRequestId()` 里拼 header。

---

## 16. 20 分钟复盘练习

- [ ] 口述 `beforeAction` + `behaviors` 的执行关系，并说明为什么顺序不能反
- [ ] 独立画出 `pay/pay/methods` 完整反查图（含白名单结论）
- [ ] 从白名单里挑 3 个支付回调接口，说明为何免登录但仍要签名
- [ ] 从白名单里挑 2 个写操作接口，写出「下游必须做什么校验」
- [ ] 列出下一步要读的文件：`LoginAuthFilter`、`VerifySignatureFilter`、`BaseApiController`

---

## 17. 下一步该读什么

| 文件 | 要回答的问题 |
|---|---|
| `BaseApiController` | 统一响应 `endSuccess/endFail`、`getHeaders()`、`getParams()` 在哪；公参注入是否在这里 |
| `filters/LoginAuthFilter` | token 从哪个 header/参数读、校验失败返回什么、`$member` 怎么挂上去 |
| `filters/common/VerifySignatureFilter` | 签名算法、密钥来源、是否对登录/注册接口有独立豁免、是否覆盖全部白名单接口 |
| `NewPayRequest::checkStandPaymentMethods` | `pay/pay/methods` 最终打到哪个内网 path |

---

## 18. 最终复盘句

`AuthApiController` 用 4 个成员定义了 BFF 的安全边界：

**默认登录 + 全等匹配的白名单 + 无条件挂载的签名校验 + 一处「顺序即正确性」的模板技巧。**

它的价值是把「默认登录 + 白名单例外 + 始终验签」收敛成网关统一入口策略，让具体业务 Controller 只关心转发，不各自实现鉴权开关。  
它真正脆弱的地方不是代码，而是那张 104 条的名单 —— **名单里每多一条写接口，安全边界就往后退一格。**
