# Week 02 Day 05：Laravel 对比与类比日

> 所属周：Week 02：Yii2 生命周期与 Filter  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-gateway`  
> 类型：类比日  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

通过 Laravel Middleware 与 Yii2 behaviors / Filter 的对比，进一步理解「请求进入业务 action 前的前置处理链」这个通用后端模式，并完成本周 JS/Node 类比打卡和鉴权白名单初稿。

今天你要真正掌握这一句话：

> 不管是 Yii2 Filter、Laravel Middleware，还是 Express middleware，本质都是在业务处理函数之前插入一组可复用的前置逻辑，用来做日志、鉴权、权限、限流、参数处理和异常保护。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 复习 Yii2 behaviors / Filter
2. 了解 Laravel Middleware 是什么
3. 理解 Laravel Middleware 的执行方式
4. 对比 Yii2 Filter 和 Laravel Middleware 的写法
5. 对比 Yii2 Filter 和 Express middleware 的写法
6. 总结三者共同模式
7. 梳理免登录白名单的意义
8. 写 1 页 Middleware vs behaviors 对照笔记
9. 完成本周 JS 类比打卡
10. 用 AI Review 检查类比是否准确

---

## 1. 学习内容

### 1.1 先复习 Yii2 Filter

Yii2 Controller 里常见：

```php
public function behaviors(): array
{
    return [
        'auth' => [
            'class' => TokenFilter::class,
        ],
    ];
}
```

Filter 里可能有：

```php
public function beforeAction($action): bool
{
    if (!$this->checkToken()) {
        return false;
    }

    return true;
}
```

含义：

```text
请求进入 action 前，先执行 TokenFilter。
如果 token 校验失败，返回 false，中断 action。
```

---

### 1.2 Laravel Middleware 是什么？

Laravel Middleware 也是请求前置/后置处理机制。

一个简化版 Middleware：

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckToken
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->header('Authorization')) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }
}
```

重点：

| Laravel 代码 | 含义 |
|---|---|
| `handle()` | Middleware 入口方法 |
| `$request` | 当前请求对象 |
| `$next($request)` | 放行，继续执行下一个 middleware 或 controller |
| 不调用 `$next` | 中断请求，直接返回响应 |

---

### 1.3 Laravel Middleware 和 Express middleware 类比

Laravel：

```php
public function handle(Request $request, Closure $next)
{
    if (!$this->check($request)) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    return $next($request);
}
```

Express：

```js
function auth(req, res, next) {
  if (!req.headers.authorization) {
    res.status(401).json({ message: 'Unauthorized' });
    return;
  }

  next();
}
```

Yii2：

```php
public function beforeAction($action): bool
{
    if (!$this->checkToken()) {
        return false;
    }

    return true;
}
```

三者本质一致：

```text
检查通过 → 继续
检查失败 → 中断并返回错误
```

---

### 1.4 Yii2 Filter vs Laravel Middleware

| 对比项 | Yii2 Filter / behaviors | Laravel Middleware |
|---|---|---|
| 声明位置 | Controller 的 `behaviors()` 或配置中 | 路由、中间件组、Kernel 中 |
| 核心方法 | `beforeAction()` | `handle()` |
| 放行方式 | 返回 `true` | `return $next($request)` |
| 中断方式 | 返回 `false` 并设置响应 | 直接返回 response |
| 请求对象 | `Yii::$app->request` | `$request` |
| 响应对象 | `Yii::$app->response` | `response()` helper |
| 常见用途 | 鉴权、日志、用户状态 | 鉴权、CSRF、限流、日志 |

---

### 1.5 Yii2 behaviors 的特点

Yii2 中，Controller 可以直接声明 behaviors：

```php
public function behaviors(): array
{
    return [
        'verbs' => [
            'class' => VerbFilter::class,
            'actions' => [
                'delete' => ['POST'],
            ],
        ],
    ];
}
```

特点：

- 跟 Controller 绑定紧密
- 可以按 Controller/action 配置
- 适合 Yii2 的 action 生命周期
- 返回 `false` 可阻止 action 执行

---

### 1.6 Laravel Middleware 的特点

Laravel 里 Middleware 通常在路由或 middleware group 中注册：

```php
Route::middleware(['auth'])->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
});
```

特点：

- 更像管道 pipeline
- 和路由系统结合紧密
- `$next($request)` 语义和 Express `next()` 很像
- 可分全局 middleware、路由 middleware、中间件组

---

### 1.7 三种框架的共同模式

| 通用模式 | Yii2 | Laravel | Express |
|---|---|---|---|
| 前置处理 | Filter `beforeAction` | Middleware `handle` | middleware function |
| 继续执行 | `return true` | `$next($request)` | `next()` |
| 中断请求 | `return false` | `return response()` | `res.json(); return` |
| 请求对象 | `Yii::$app->request` | `$request` | `req` |
| 响应对象 | `Yii::$app->response` | `response()` | `res` |
| 典型用途 | 登录/权限/日志 | 登录/CSRF/限流 | 登录/日志/错误处理 |

你要记住：

> 框架不同，模式相同：业务 action 前面挂一串可复用前置逻辑。

---

### 1.8 免登录白名单是什么？

有些接口不能要求登录，例如：

- 登录接口
- 注册接口
- 验证码接口
- 首页公开配置接口
- 第三方支付回调
- 商品公开详情

所以项目里可能有白名单：

```php
$freeLoginAuthApiList = [
    'user/login',
    'user/register',
    'site/config',
];
```

TokenFilter 或 AuthApiController 会判断：

```text
如果当前接口在白名单里，就不强制登录。
否则必须校验 token。
```

---

### 1.9 白名单的风险

白名单很常见，但风险也很大。

如果误把敏感接口放进去：

```text
order/detail
user/profile
coupon/receive
```

可能导致：

- 未登录访问用户数据
- 越权查看订单
- 非法领取优惠券
- 绕过鉴权访问内部能力

所以白名单要记录原因。

推荐表格：

| 接口 | 是否免登录 | 免登录原因 | 风险 |
|---|---|---|---|
| `user/login` | 是 | 登录入口本身 | 暴力尝试，需要限流 |
| `site/config` | 是 | 首页公开配置 | 不能返回敏感配置 |
| `pay/webhook` | 是 | 第三方回调 | 必须验签 |

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议回看：

- `week02/day03.md`：Yii2 behaviors / Filter
- `mall-gateway/frontapi/modules/AuthApiController.php`

重点不是继续读很多代码，而是把 Yii2 Filter 与 Laravel/Express 的通用模式对齐。

---

## 3. 练习任务

### 练习 1：写 Yii2 Filter vs Laravel Middleware 对照笔记

用这个模板：

```markdown
# Laravel Middleware vs Yii2 behaviors / Filter

## 共同点

1. 都在业务 action/controller 前执行
2. 都能做鉴权、日志、权限检查
3. 都能中断请求

## 不同点

| 对比项 | Yii2 | Laravel |
|---|---|---|
| 声明位置 |  |  |
| 核心方法 |  |  |
| 放行方式 |  |  |
| 中断方式 |  |  |

## 我的理解

```

---

### 练习 2：写三框架对照代码

Yii2：

```php
public function beforeAction($action): bool
{
    if (!$this->checkToken()) {
        return false;
    }

    return true;
}
```

Laravel：

```php
public function handle($request, Closure $next)
{
    if (!$request->header('Authorization')) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    return $next($request);
}
```

Express：

```js
function auth(req, res, next) {
  if (!req.headers.authorization) {
    res.status(401).json({ message: 'Unauthorized' });
    return;
  }

  next();
}
```

写出你的总结：

```text
Yii2 的 return true ≈ Laravel 的 $next($request) ≈ Express 的 next()
Yii2 的 return false ≈ Laravel 直接 return response ≈ Express 不调用 next 并 res.json
```

---

### 练习 3：整理白名单初稿

从 `AuthApiController.php` 或相关鉴权逻辑中找免登录接口。

至少整理 5 个：

| 接口 | 免登录原因 | 风险点 | 是否需要额外保护 |
|---|---|---|---|
|  |  |  |  |
|  |  |  |  |
|  |  |  |  |
|  |  |  |  |
|  |  |  |  |

额外保护可能包括：

- 验签
- 限流
- 验证码
- 只返回公开字段
- IP 白名单

---

### 练习 4：完成本周类比打卡

填写：

```text
本周概念：Yii2 behaviors / Filter
Node 等价：Express middleware / NestJS Guard
差异：Yii2 behaviors 声明在 Controller，Filter beforeAction 返回 false 可中断；Express 通过 next() 控制继续；Laravel 通过 $next($request) 控制继续
我能用自己的话解释吗：是 / 否
理解自评：1 / 2 / 3 / 4 / 5
```

---

### 练习 5：写一段自己的理解

用 5 句话写：

```text
1. Yii2 behaviors 是...
2. Filter 的作用是...
3. Laravel Middleware 的作用是...
4. Express middleware 和它们的共同点是...
5. 白名单接口必须谨慎，因为...
```

---

## 4. JS/Node.js 类比

| 概念 | Yii2 | Laravel | Express |
|---|---|---|---|
| 中间件机制 | Filter / behaviors | Middleware | middleware |
| 声明方式 | `behaviors()` | route/kernel | `app.use()` |
| 核心方法 | `beforeAction()` | `handle()` | `(req,res,next)` |
| 继续执行 | `return true` | `$next($request)` | `next()` |
| 中断请求 | `return false` | `return response()` | `res.json(); return` |
| 请求对象 | `Yii::$app->request` | `$request` | `req` |
| 响应对象 | `Yii::$app->response` | `response()` | `res` |

---

## 5. AI Review 提问

完成对照笔记后，把内容贴给 AI：

```text
我正在学习 Laravel Middleware vs Yii2 behaviors / Filter。

我写了一份对照笔记，并整理了免登录白名单初稿。
请你按资深 PHP 后端工程师标准帮我检查：

1. 我对 Yii2 Filter、Laravel Middleware、Express middleware 的类比是否准确？
2. 我是否抓住了 return true / return false / next() / $next 的核心差异？
3. 我的白名单接口免登录原因是否合理？
4. 白名单还需要哪些安全保护？
5. 进入 Week 02 周末复盘前，我还应该补哪些概念？

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 6. 今日产出

- [ ] Laravel Middleware vs Yii2 Filter 对照笔记
- [ ] Yii2 / Laravel / Express 三框架对照代码
- [ ] 免登录白名单初稿，至少 5 个接口
- [ ] 本周 JS 类比打卡
- [ ] 自己的 5 句话总结
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 Laravel Middleware 是什么
- [ ] 能解释 Yii2 Filter 和 Laravel Middleware 的共同点
- [ ] 能说出 Yii2 `return true` 对应 Laravel/Express 的什么概念
- [ ] 能说出 Yii2 `return false` 对应 Laravel/Express 的什么概念
- [ ] 能写出三框架中间件对照代码
- [ ] 能整理至少 5 个免登录接口及原因
- [ ] 能说明白名单风险
- [ ] 完成本周类比打卡

---

## 8. 今日自测题

### 8.1 Laravel Middleware 的核心方法通常叫什么？

参考答案：

> `handle()`。

---

### 8.2 Laravel 中 `$next($request)` 类似 Express 的什么？

参考答案：

> 类似 Express 的 `next()`，表示继续执行下一个 middleware 或 controller。

---

### 8.3 Yii2 Filter 中 `return true` 类似什么？

参考答案：

> 类似 Laravel 的 `$next($request)` 或 Express 的 `next()`，表示继续执行。

---

### 8.4 Yii2 Filter 中 `return false` 表示什么？

参考答案：

> 表示中断请求，不继续执行目标 action。

---

### 8.5 白名单接口为什么要记录免登录原因？

参考答案：

> 因为白名单会绕过登录校验，如果没有明确原因和安全保护，可能导致敏感接口被未登录访问。

---

### 8.6 支付 webhook 可以免登录吗？为什么还要验签？

参考答案：

> 可以不走用户登录，因为它来自第三方平台；但必须验签，确保请求确实来自可信第三方，而不是伪造请求。

---

### 8.7 Yii2 behaviors 和 Express middleware 最大共同点是什么？

参考答案：

> 都是在业务处理函数前执行公共逻辑，并且可以决定是否继续执行后续业务。

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
我正在进行 Week 02 Day 05：Laravel 对比与类比日 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 02 README](./README.md)
