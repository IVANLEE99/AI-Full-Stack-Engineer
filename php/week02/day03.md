# Week 02 Day 03：behaviors 与 Filter

> 所属周：Week 02：Yii2 生命周期与 Filter  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-gateway`  
> 类型：编码练习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 Yii2 中 `behaviors()`、Filter、`beforeAction()` 的执行机制，能画出请求进入 Controller action 前经历了哪些前置处理，并能用 Express middleware 链来类比。

今天你要真正掌握这一句话：

> Yii2 的 behaviors/Filter 类似 Express middleware：请求真正进入 action 前，会先经过日志、鉴权、用户状态、参数校验等前置链路；其中某个 Filter 返回 false，就可以中断后续 action 执行。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 复习 Yii2 请求到 Controller/action 的路径
2. 理解为什么需要 Filter
3. 理解 `behaviors()` 是什么
4. 理解 Filter 是什么
5. 理解 `beforeAction()` 的执行时机
6. 理解 Filter 如何中断请求
7. 阅读 `AuthApiController.php`
8. 找出它声明了哪些 behaviors
9. 画出 Filter 执行链
10. 选择一个 Filter 读源码
11. 和 Express middleware 做类比
12. 完成今日自测和 AI Review

---

## 1. 学习内容

### 1.1 为什么需要 Filter？

一个接口真正执行业务前，通常要先做很多公共检查：

- 记录访问日志
- 校验 token
- 判断用户是否登录
- 判断用户状态是否正常
- 校验签名
- 检查接口是否在白名单
- 统一处理异常

如果每个 action 都手写这些逻辑，会非常重复。

例如不好的写法：

```php
public function actionDetail(): array
{
    // 1. 记录日志
    // 2. 校验 token
    // 3. 检查用户状态
    // 4. 真正业务逻辑
}

public function actionList(): array
{
    // 1. 记录日志
    // 2. 校验 token
    // 3. 检查用户状态
    // 4. 真正业务逻辑
}
```

Filter 就是把这些公共前置逻辑抽出来。

---

### 1.2 Express middleware 类比

Express 里常见：

```js
app.use(logger);
app.use(auth);
app.use(checkUserStatus);

router.get('/orders', orderHandler);
```

请求顺序：

```text
request
  ↓
logger
  ↓
auth
  ↓
checkUserStatus
  ↓
orderHandler
```

Yii2 里类似：

```text
request
  ↓
Filter 1
  ↓
Filter 2
  ↓
Filter 3
  ↓
actionXxx()
```

---

### 1.3 behaviors() 是什么？

在 Yii2 Controller 中，经常会看到：

```php
public function behaviors(): array
{
    return [
        'log' => [
            'class' => LogFilter::class,
        ],
        'auth' => [
            'class' => TokenFilter::class,
        ],
    ];
}
```

你可以先这样理解：

> `behaviors()` 是 Controller 声明自己要挂载哪些行为/Filter 的地方。

也就是告诉 Yii2：

```text
这个 Controller 的 action 执行前，请先跑这些 Filter。
```

---

### 1.4 Filter 是什么？

Filter 是一种在 action 前后执行的组件。

它常见作用：

| Filter 类型 | 作用 |
|---|---|
| 日志 Filter | 记录请求参数、响应、耗时 |
| Token Filter | 校验登录 token |
| UserStatus Filter | 检查用户是否禁用 |
| AccessControl | 权限控制 |
| VerbFilter | 限制 HTTP 方法 |

Yii2 内置也有一些 Filter，例如：

- `yii\filters\AccessControl`
- `yii\filters\VerbFilter`

企业项目也会写自己的 Filter。

---

### 1.5 beforeAction() 是什么？

`beforeAction()` 会在 action 执行前调用。

简化示例：

```php
public function beforeAction($action): bool
{
    if (!parent::beforeAction($action)) {
        return false;
    }

    // 你的前置逻辑

    return true;
}
```

重点：

- 返回 `true`：继续执行 action
- 返回 `false`：中断 action

这和 Express middleware 中不调用 `next()` 很像。

Express：

```js
function auth(req, res, next) {
  if (!req.user) {
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
    if (!$this->isLogin()) {
        Yii::$app->response->statusCode = 401;
        return false;
    }

    return parent::beforeAction($action);
}
```

---

### 1.6 Filter 如何中断请求？

假设 token 不合法：

```php
public function beforeAction($action): bool
{
    if (!$this->checkToken()) {
        Yii::$app->response->data = [
            'code' => 401,
            'message' => 'token invalid',
        ];

        return false;
    }

    return true;
}
```

当返回 `false` 时，Yii2 不会继续执行目标 action。

这就是鉴权 Filter 的核心价值。

---

### 1.7 behaviors 和 beforeAction 的关系

小白可以先这样理解：

```text
behaviors()：声明这个 Controller 挂哪些 Filter
Filter::beforeAction()：Filter 真正执行前置逻辑
Controller::beforeAction()：Controller 自己也可以做前置逻辑
```

请求进入 action 前，大致会发生：

```text
Yii Application
  ↓
找到 Controller/action
  ↓
加载 Controller behaviors
  ↓
执行 Filter beforeAction
  ↓
执行 Controller beforeAction
  ↓
执行 actionXxx
```

真实 Yii2 内部顺序更复杂，但今天先掌握这个心智模型即可。

---

### 1.8 常见 Filter 链示例

在网关项目中，你可能看到类似链路：

```text
LogStrFilter
  ↓
UserStatusFilter
  ↓
TokenFilter
  ↓
actionXxx()
```

含义：

| Filter | 可能职责 |
|---|---|
| LogStrFilter | 记录请求日志、链路 ID、耗时 |
| UserStatusFilter | 检查用户状态是否正常 |
| TokenFilter | 校验登录态/token |

你今天要做的，就是在 `AuthApiController.php` 中找到类似配置，并画出来。

---

## 2. 源码阅读

- `mall-gateway/frontapi/modules/AuthApiController.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

### 2.1 阅读目标

今天阅读这个文件，重点回答：

1. 它是哪个 Controller 的基类？
2. 它有没有 `behaviors()`？
3. `behaviors()` 返回了哪些 Filter？
4. Filter 的顺序是什么？
5. 有没有 `beforeAction()`？
6. 哪些接口可以免登录？
7. token 是在哪里解析的？

---

### 2.2 找 behaviors()

搜索：

```php
public function behaviors()
```

或者：

```php
behaviors()
```

看到后整理：

| 顺序 | behavior 名称 | class | 作用猜测 |
|---|---|---|---|
| 1 |  |  |  |
| 2 |  |  |  |
| 3 |  |  |  |

---

### 2.3 找 beforeAction()

搜索：

```php
beforeAction
```

记录：

| 观察点 | 记录 |
|---|---|
| 是否调用 parent::beforeAction |  |
| 是否判断登录态 |  |
| 是否读取 token |  |
| 是否写入用户信息 |  |
| 返回 false 的场景 |  |

---

### 2.4 找免登录白名单

很多网关基类会有免登录接口列表，例如：

```php
freeLoginAuthApiList
```

或者类似命名。

你要记录 5 个免登录接口：

| 接口 | 为什么可能免登录 |
|---|---|
|  |  |
|  |  |
|  |  |
|  |  |
|  |  |

常见免登录原因：

- 登录接口本身不能要求已登录
- 注册接口不能要求已登录
- 支付回调来自第三方
- 公共配置接口给未登录首页使用
- 商品详情等公开内容

---

## 3. 练习任务

### 练习 1：列出 behaviors

整理表格：

| 序号 | behavior key | Filter class | 作用 | 是否可能中断请求 |
|---|---|---|---|---|
| 1 |  |  |  |  |
| 2 |  |  |  |  |
| 3 |  |  |  |  |

---

### 练习 2：画 Filter 顺序图

画出你看到的真实顺序：

```text
HTTP 请求
  ↓
index.php
  ↓
Yii Application
  ↓
Module / Controller / action
  ↓
Filter A
  ↓
Filter B
  ↓
Filter C
  ↓
Controller beforeAction
  ↓
actionXxx()
```

Mermaid 模板：

```mermaid
flowchart TD
    A[HTTP 请求] --> B[Yii Application]
    B --> C[Controller/action]
    C --> D[Filter 1]
    D --> E[Filter 2]
    E --> F[Filter 3]
    F --> G[beforeAction]
    G --> H[actionXxx]
```

---

### 练习 3：读一个 Filter 源码

从 behaviors 中选一个 Filter，打开它的 class 文件。

记录：

| 观察点 | 记录 |
|---|---|
| Filter 类名 |  |
| namespace |  |
| 继承哪个类 |  |
| 是否有 beforeAction |  |
| 主要判断逻辑 |  |
| 返回 false 的场景 |  |
| JS middleware 类比 |  |

---

### 练习 4：写一个伪 Filter

不用放进项目，写一个帮助理解的伪代码：

```php
<?php

declare(strict_types=1);

class TokenFilter
{
    public function beforeAction($action): bool
    {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if ($token === '') {
            echo json_encode([
                'code' => 401,
                'message' => 'token required',
            ]);

            return false;
        }

        return true;
    }
}
```

重点理解：

> Filter 的职责不是写业务，而是决定请求能不能继续进入业务 action。

---

### 练习 5：写 Express middleware 对照

```js
function tokenMiddleware(req, res, next) {
  const token = req.headers.authorization;

  if (!token) {
    res.status(401).json({ code: 401, message: 'token required' });
    return;
  }

  next();
}
```

写出对照：

| Yii2 Filter | Express middleware |
|---|---|
| `beforeAction()` | middleware function |
| `return true` | `next()` |
| `return false` | 不调用 `next()` 并直接响应 |
| `Yii::$app->request` | `req` |
| `Yii::$app->response` | `res` |

---

## 4. JS/Node.js 类比

| Yii2 概念 | Express/Nest 类比 | 差异 |
|---|---|---|
| `behaviors()` | `app.use()` / decorator guard list | Yii2 在 Controller 中声明 |
| Filter | middleware / guard | Yii2 Filter 是类，常有 `beforeAction` |
| `beforeAction()` | middleware 前置逻辑 | 返回 bool 控制是否继续 |
| `return true` | `next()` | 继续执行 action |
| `return false` | 不调用 `next()` | 中断 action |
| 免登录白名单 | public routes | 需要小心维护 |
| TokenFilter | auth middleware | 鉴权逻辑前置 |

---

## 5. AI Review 提问

完成 Filter 链图后，把你的 behaviors 表和流程图贴给 AI，然后问：

```text
我正在学习 Yii2 behaviors 与 Filter。

我阅读了 AuthApiController.php，整理了 behaviors 表，并画了 Filter 执行链。
请你按资深 Yii2 后端工程师标准帮我检查：

1. 我列出的 Filter 顺序是否正确？
2. 我对 behaviors() 的理解是否准确？
3. 我对 beforeAction 返回 true/false 的理解是否正确？
4. 我和 Express middleware 的类比是否会误导？
5. 免登录白名单有哪些安全风险需要注意？

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 6. 今日产出

今天结束前，你应该产出：

- [ ] `AuthApiController.php` 阅读笔记
- [ ] behaviors 列表
- [ ] Filter 执行顺序图
- [ ] 一个 Filter 源码阅读表
- [ ] 免登录接口清单，至少 5 个
- [ ] Yii2 Filter vs Express middleware 对照表
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释为什么需要 Filter
- [ ] 能解释 `behaviors()` 是什么
- [ ] 能解释 Filter 是什么
- [ ] 能解释 `beforeAction()` 什么时候执行
- [ ] 能说出 `return true` 和 `return false` 的区别
- [ ] 能画出 Filter 执行链
- [ ] 能读懂一个 Filter 的大概逻辑
- [ ] 能说出至少 3 种常见 Filter 职责
- [ ] 能用 Express middleware 类比 Yii2 Filter
- [ ] 能说明免登录白名单的安全风险

---

## 8. 今日自测题

### 8.1 Yii2 的 `behaviors()` 是什么？

参考答案：

> `behaviors()` 是 Controller 声明要挂载哪些行为或 Filter 的地方，请求进入 action 前 Yii2 会执行这些 Filter。

---

### 8.2 Filter 主要解决什么问题？

参考答案：

> Filter 用来抽取日志、鉴权、权限、用户状态检查等公共前置/后置逻辑，避免每个 action 重复写。

---

### 8.3 `beforeAction()` 返回 `true` 表示什么？

参考答案：

> 表示前置检查通过，可以继续执行目标 action。

---

### 8.4 `beforeAction()` 返回 `false` 表示什么？

参考答案：

> 表示中断请求，不继续执行目标 action，通常已经设置了错误响应。

---

### 8.5 Yii2 Filter 和 Express middleware 最大相似点是什么？

参考答案：

> 都是在真正业务 handler/action 前执行公共逻辑，并可以决定是否继续往下执行。

---

### 8.6 免登录白名单为什么有风险？

参考答案：

> 如果误把敏感接口加入免登录白名单，未登录用户也能访问，可能造成数据泄露或越权操作。

---

### 8.7 常见 Filter 有哪些？

参考答案：

> 日志 Filter、Token 鉴权 Filter、用户状态 Filter、权限 Filter、HTTP 方法限制 Filter。

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
我正在进行 Week 02 Day 03：behaviors 与 Filter 的学习。
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
