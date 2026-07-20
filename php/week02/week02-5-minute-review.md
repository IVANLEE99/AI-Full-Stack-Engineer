# Week 02：Yii2 生命周期与 Filter——5 分钟复习

> 复习范围：`php/week02/day01.md` ～ `day07.md`

## 第 1 分钟：完整请求主线

```text
HTTP 请求
  ↓
web/index.php
  ↓
Composer autoload
  ↓
Yii.php
  ↓
加载并合并 config
  ↓
new yii\web\Application($config)
  ↓
$application->run()
  ↓
解析 route
  ↓
Module
  ↓
Controller
  ↓
beforeAction / Filter
  ↓
action 内执行 Form 校验
  ↓
Service
  ↓
Response
```

注意：Form 校验通常由 Controller/action 显式调用，不是 Yii2 自动在所有请求中执行。

## 第 2 分钟：入口与路由

入口文件中的几个关键部分：

| 部分 | 作用 |
|---|---|
| `vendor/autoload.php` | 自动加载项目类和 Composer 依赖 |
| `Yii.php` | 加载 Yii2 框架核心 |
| `Application($config)` | 根据配置初始化应用和组件 |
| `run()` | 真正开始处理当前请求 |

默认路由通常是：

```text
module/controller/action
```

例如：

```text
pay/pay/methods
│   │   └─ actionMethods()
│   └───── PayController
└───────── pay Module
```

短横线通常转换为 PascalCase：

```text
order-goods → OrderGoodsController
quick-login → actionQuickLogin()
```

但外部 URL 可能被 `urlManager.rules` 重写，因此实际阅读代码时应检查：

1. Module 注册
2. URL rules
3. Controller
4. action

## 第 3 分钟：behaviors、Filter 与 `beforeAction()`

三个概念不要混淆：

- `behaviors()`：声明当前 Controller 挂载哪些 Behavior/Filter
- `Controller::beforeAction()`：进入 action 前的生命周期入口
- `Filter::beforeAction()`：执行登录、验签、权限等具体检查

常见精确流程如下：

```text
进入 Controller::beforeAction()
  ↓
执行 parent 前面的 Controller 自定义代码
  ↓
调用 parent::beforeAction()
  ↓
触发 EVENT_BEFORE_ACTION
  ↓
执行 Filter::beforeAction()
  ↓
执行 parent 后面的 Controller 自定义代码
  ↓
执行 action
```

Filter 返回值：

- `true`：继续后续 Filter 和 action
- `false`：中断 action，通常还要准备错误响应

重要结论：

> Filter 不一定永远先于 Controller 自定义代码；相对顺序取决于代码位于 `parent::beforeAction()` 的前面还是后面。

## 第 4 分钟：Form 校验与鉴权安全

Form 的基本流程：

```php
$form = new CreateUserForm();
$form->load($payload, '');

if (!$form->validate()) {
    return $form->getErrors();
}
```

核心职责：

| 方法/概念 | 作用 |
|---|---|
| `rules()` | 定义必填、类型、长度、范围等规则 |
| `load($data, '')` | 从数组根部加载当前场景的安全属性 |
| `validate()` | 执行规则并返回 `bool` |
| `getErrors()` | 获取字段错误信息 |
| `scenarios()` | 为 create/update 等场景选择不同字段集合 |
| `BaseForm` | 统一参数加载、校验入口和错误格式 |

注意：

- `safe` 只允许批量赋值，不代表数据合法
- 校验不等于类型转换
- 校验失败后不能继续进入 Service

白名单只表示：

```text
免用户登录态/token 校验
≠
免所有安全检查
```

例如支付 webhook 仍然需要验签、防重放、幂等、限流或来源限制；token 有效也不代表用户一定有权访问某个订单。

## 第 5 分钟：闭眼自测

尝试不看上文回答：

1. `autoload.php` 和 `Yii.php` 分别做什么？
2. `pay/pay/methods` 三段分别对应什么？
3. `behaviors()`、Controller 的 `beforeAction()`、Filter 的 `beforeAction()` 有什么区别？
4. 为什么不能死记“Filter 永远先于 Controller”？
5. `rules()`、`validate()`、`getErrors()` 分别负责什么？
6. 为什么支付回调免登录后仍然必须验签？
7. token 有效是否代表用户一定有权查看某个订单？

## 一句话总结

> Yii2 请求就是：入口启动应用，路由找到 action，Filter 负责前置检查，Form 保证参数合法，Service 执行业务，最后返回 Response。
