# `AuthApiController` 的 `beforeAction()` 与 Filter 执行流程

> 对应源码：`php/week02/frontapi/config/modules/AuthApiController.php`
> 学习重点：`behaviors()`、Controller 的 `beforeAction()`、Filter 的 `beforeAction()` 如何协作

---

## 1. 核心结论

这段流程可以先记成一句话：

> `AuthApiController::beforeAction()` 先判断当前路由是否免登录，再调用父类触发 Yii2 的 action 前置事件；挂载到该事件上的 Filter 依次检查请求，全部通过后才执行目标 action。

三个概念分别属于不同层次：

| 概念 | 层次 | 主要职责 |
|---|---|---|
| `behaviors()` | 配置层 | 声明当前 Controller 要挂载哪些 Behavior/Filter |
| `Controller::beforeAction()` | 生命周期层 | Controller 执行 action 前的统一入口 |
| `Filter::beforeAction()` | 过滤器执行层 | 完成登录、签名等某一项具体检查 |

需要注意：Behavior 不一定是 Filter；Filter 是专门参与 action 前后过滤的一类 Behavior。

---

## 2. 完整流程图

```text
HTTP 请求进入 Yii Application
  ↓
Yii2 解析路由
  ↓
找到并创建 Controller 和 action 对象
  ↓
Controller::runAction()
  ↓
Module 的 beforeAction() 检查通过
  ↓
动态调用 AuthApiController::beforeAction($action)
  ↓
读取并转为小写的 pathInfo
  ↓
检查路由是否在 freeLoginAuthApiList 中
  ├─ 在白名单：$loginAuth = false
  └─ 不在白名单：$loginAuth 保持 true
  ↓
调用 parent::beforeAction($action)
  ↓
父类触发 Controller::EVENT_BEFORE_ACTION
  ↓
Yii2 确保 behaviors 已实例化并附加到 Controller
  ↓
普通接口：父类 behaviors + LoginAuthFilter + VerifySignatureFilter
白名单接口：父类 behaviors + VerifySignatureFilter
  ↓
事件依次通知已挂载的 Filter
  ↓
ActionFilter::beforeFilter() 调用 Filter::beforeAction($action)
  ↓
所有父类逻辑和 Filter 是否都通过？
  ├─ 否：中断流程，不执行目标 action
  └─ 是：执行 actionXxx()
```

这张图省略了参数绑定、异常处理和 `afterAction()`，只关注 action 执行前的主线。

---

## 3. 第一步：Yii Application 解析路由

假设客户端请求：

```text
POST /user/code/login
```

Yii Application 接收请求后，会根据路由规则找到：

1. 对应的 Module
2. 对应的 Controller
3. 对应的 action

Yii2 随后创建 Controller 实例和 `$action` 对象。

这里的 `$action` 不是简单的字符串。它通常是 `yii\base\Action` 或其子类的对象，里面包含：

- action ID
- 所属 Controller
- 参数执行逻辑
- 最终要运行的方法

例如路由最终可能对应：

```php
public function actionLogin()
{
    // 登录业务
}
```

---

## 4. 第二步：进入 `Controller::runAction()`

Yii2 不会直接调用 `actionLogin()`，而是先进入 Controller 的 action 调度流程。

简化伪代码如下：

```php
$action = $this->createAction($id);

if ($modulesBeforeActionPassed && $this->beforeAction($action)) {
    $result = $action->runWithParams($params);
    $result = $this->afterAction($action, $result);
}
```

由此可以看出：

```php
$this->beforeAction($action)
```

必须得到真值，Yii2 才会继续执行：

```php
$action->runWithParams($params)
```

也就是目标业务 action。

在进入 Controller 的检查前，Yii2 还会执行所属 Module 的 `beforeAction()`。如果 Module 层已经返回 `false`，Controller 和业务 action 同样不会继续执行。

---

## 5. 第三步：为什么调用的是 `AuthApiController::beforeAction()`？

`runAction()` 中的调用形式是：

```php
$this->beforeAction($action)
```

`$this` 指向当前实际创建的 Controller 对象。PHP 会按照继承链寻找最具体的 `beforeAction()` 实现。

如果业务 Controller：

1. 继承 `AuthApiController`
2. 没有自己重写 `beforeAction()`

那么最终调用的就是：

```php
AuthApiController::beforeAction($action)
```

如果业务 Controller 自己重写了该方法，则会先进入业务 Controller 的实现。此时它是否调用 `parent::beforeAction($action)`，会直接影响 `AuthApiController` 和后续 Yii2 生命周期逻辑是否执行。

---

## 6. 第四步：判断当前路由是否免登录

`AuthApiController` 中的代码是：

```php
public function beforeAction($action)
{
    if (in_array(strtolower(\Yii::$app->request->getPathInfo()), $this->freeLoginAuthApiList)) {
        $this->loginAuth = false;
    }

    return parent::beforeAction($action);
}
```

### 6.1 读取 `pathInfo`

```php
\Yii::$app->request->getPathInfo()
```

它取得当前请求中的路径部分。例如：

```text
user/code/login
```

查询字符串不属于这里的路由值。例如：

```text
/user/code/login?source=email
```

用于白名单判断的主要路径仍是：

```text
user/code/login
```

### 6.2 统一转为小写

```php
strtolower($pathInfo)
```

这样可以减少请求路径大小写不同导致的匹配失败。

### 6.3 精确匹配白名单

```php
in_array($path, $this->freeLoginAuthApiList)
```

这是完整字符串匹配，不是前缀匹配。

白名单中存在：

```text
user/code/login
```

不会自动放行：

```text
user/code/login-by-email
```

### 6.4 这里只设置开关，不执行登录检查

如果命中白名单：

```php
$this->loginAuth = false;
```

这一步只改变 Controller 的状态，告诉后面的 `behaviors()` 不要添加 `LoginAuthFilter`。

它没有：

- 解析 token
- 查询用户
- 返回登录失败响应
- 直接执行目标 action

真正的登录检查属于 `LoginAuthFilter` 的职责。

---

## 7. 第五步：`parent::beforeAction($action)` 做什么？

白名单判断后，代码执行：

```php
return parent::beforeAction($action);
```

### 7.1 `parent` 指向谁？

当前继承关系是：

```php
class AuthApiController extends BaseApiController
```

因此 `parent` 首先指向 `BaseApiController`。

如果 `BaseApiController` 重写了 `beforeAction()`，会先执行它的代码；如果没有重写，或者它继续调用 `parent::beforeAction()`，PHP 会沿继承链找到更上层的 Yii2 Controller 实现。

当前学习目录中没有 `BaseApiController` 的源码，因此父类是否还有额外日志、参数或权限检查，需要在真实项目中继续确认。

### 7.2 Yii2 Controller 的标准逻辑

Yii2 Controller 的 `beforeAction()` 可以简化理解为：

```php
public function beforeAction($action)
{
    $event = new ActionEvent($action);
    $this->trigger(self::EVENT_BEFORE_ACTION, $event);

    return $event->isValid;
}
```

它主要做三件事：

1. 创建 action 前置事件对象
2. 触发 `EVENT_BEFORE_ACTION`
3. 返回事件最终是否有效

所以 `parent::beforeAction()` 不是一句无意义的固定写法。它把当前 Controller 接回 Yii2 的标准 action 生命周期。

### 7.3 为什么必须写 `return`？

父类可能返回：

```php
true
```

表示允许执行 action；也可能返回：

```php
false
```

表示某个父类逻辑或 Filter 拒绝请求。

当前方法必须把这个结果继续交给 `runAction()`：

```php
return parent::beforeAction($action);
```

如果只写：

```php
parent::beforeAction($action);
```

却没有返回结果，那么当前方法默认返回 `null`。`runAction()` 在条件判断中会把 `null` 当作假值，目标 action 可能因此不执行。

---

## 8. 第六步：`behaviors()` 在什么时候使用？

`AuthApiController` 声明：

```php
public function behaviors()
{
    $behaviors = parent::behaviors();

    if ($this->loginAuth) {
        $behaviors['loginAuthFilter'] = [
            'class' => LoginAuthFilter::class,
        ];
    }

    $behaviors['VerifySignature'] = [
        'class' => VerifySignatureFilter::className(),
    ];

    return $behaviors;
}
```

### 8.1 `behaviors()` 是配置，不是检查逻辑

调用 `behaviors()` 的结果是 Filter 配置数组。它告诉 Yii2：

```text
需要创建哪些 Filter
  ↓
使用什么类创建
  ↓
把它们附加到哪个 Controller
```

它不会因为返回了 `LoginAuthFilter` 配置，就立刻在这一行完成登录验证。

### 8.2 Yii2 通常延迟附加 Behavior

Yii2 的 Component 会在需要使用事件或 Behavior 时确保 behaviors 已经附加。

当父类执行：

```php
$this->trigger(self::EVENT_BEFORE_ACTION, $event);
```

Yii2 会确保 Controller 的 behaviors 已初始化。于是当前 `behaviors()` 会根据刚才设置好的 `$loginAuth` 生成配置。

这就是代码顺序必须是：

```text
先判断白名单并设置 $loginAuth
  ↓
再调用 parent::beforeAction()
  ↓
再由事件机制使用 behaviors
```

如果某段代码在白名单判断之前就提前初始化了当前 Controller 的 behaviors，那么 `LoginAuthFilter` 可能已经按照默认的 `$loginAuth = true` 被附加。当前设计依赖 Yii2 正常的延迟初始化顺序。

---

## 9. 第七步：普通接口和白名单接口挂载什么？

### 9.1 普通接口

路由不在白名单中时：

```php
$this->loginAuth === true
```

当前类追加：

```text
LoginAuthFilter
VerifySignatureFilter
```

最终集合是：

```text
parent::behaviors() 返回的 Behavior/Filter
+ LoginAuthFilter
+ VerifySignatureFilter
```

### 9.2 白名单接口

路由在白名单中时：

```php
$this->loginAuth === false
```

因此跳过：

```text
LoginAuthFilter
```

但仍然追加：

```text
VerifySignatureFilter
```

最终集合是：

```text
parent::behaviors() 返回的 Behavior/Filter
+ VerifySignatureFilter
```

对比表：

| 请求类型 | `LoginAuthFilter` | `VerifySignatureFilter` | 父类 Filter |
|---|---|---|---|
| 普通接口 | 执行 | 执行 | 仍可能执行 |
| 白名单接口 | 跳过 | 执行 | 仍可能执行 |

因此：

> 免登录不等于免签名，也不等于绕过所有父类检查，更不代表接口没有任何安全限制。

---

## 10. 第八步：Filter 的 `beforeAction()` 如何被调用？

Filter 通常继承 Yii2 的 `ActionFilter`。

Filter 被附加到 Controller 时，会监听：

```php
Controller::EVENT_BEFORE_ACTION
```

事件发生后，真实调用关系可以简化为：

```text
Controller 触发 EVENT_BEFORE_ACTION
  ↓
ActionFilter 收到事件
  ↓
ActionFilter::beforeFilter($event)
  ↓
Filter::beforeAction($event->action)
```

因此，Controller 并不是直接这样调用每个 Filter：

```php
$filter->beforeAction($action);
```

中间还有 Yii2 的事件监听机制和 `ActionFilter::beforeFilter()` 包装层。

两个同名方法的区别：

| 方法 | 所属对象 | 谁调用 | 作用 |
|---|---|---|---|
| `Controller::beforeAction()` | Controller | `runAction()` | 触发 action 前置生命周期 |
| `Filter::beforeAction()` | Filter | `ActionFilter::beforeFilter()` | 执行某项具体过滤检查 |

---

## 11. 第九步：Filter 如何中断 action？

Filter 的逻辑可能类似：

```php
public function beforeAction($action): bool
{
    if (!$this->checkLogin()) {
        return false;
    }

    return true;
}
```

### 返回 `true`

```text
当前 Filter 检查通过
  ↓
继续通知后面的事件处理器或 Filter
  ↓
最终允许执行 action
```

### 返回 `false`

`ActionFilter::beforeFilter()` 会把事件标记为无效和已处理。随后：

```text
停止当前前置事件链
  ↓
Controller::beforeAction() 最终返回 false
  ↓
runAction() 不执行目标 action
```

Filter 也可能抛出异常，例如登录异常或签名异常。异常会进入 Yii2 的异常处理流程，同样不会继续执行目标业务 action。

---

## 12. 第十步：所有检查通过后执行 action

当以下条件全部满足时：

1. Module 的 `beforeAction()` 通过
2. `AuthApiController` 自身逻辑完成
3. 父类 `beforeAction()` 通过
4. 所有已执行的 Filter 都通过
5. 没有抛出异常

`Controller::runAction()` 才会执行：

```php
$action->runWithParams($params);
```

最终进入业务方法，例如：

```php
actionLogin()
```

action 执行完成后，Yii2 还会进入 Controller、Filter 和 Module 的 action 后置流程。这属于 `afterAction()` 的学习范围。

---

## 13. 两条请求链路对比

### 13.1 普通接口

```text
请求普通接口
  ↓
loginAuth 保持 true
  ↓
加载父类 behaviors
  ↓
挂载 LoginAuthFilter
  ↓
挂载 VerifySignatureFilter
  ↓
登录检查通过？
  ├─ 否：中断
  └─ 是：继续
  ↓
签名检查通过？
  ├─ 否：中断
  └─ 是：执行 action
```

### 13.2 白名单接口

```text
请求白名单接口
  ↓
loginAuth 设置为 false
  ↓
加载父类 behaviors
  ↓
不挂载 LoginAuthFilter
  ↓
仍挂载 VerifySignatureFilter
  ↓
签名检查通过？
  ├─ 否：中断
  └─ 是：执行 action
```

图中的 Filter 顺序只表示当前类追加配置的直观顺序。完整项目还可能有父类 Behavior、手动注册的事件处理器或其他配置，因此最终执行顺序需要结合 `BaseApiController` 和相关 Filter 源码确认。

---

## 14. 常见误区

### 误区 1：`behaviors()` 就是在执行 Filter

错误理解：

```text
调用 behaviors() = 已经完成登录验证
```

正确理解：

```text
behaviors() 返回配置
  ↓
Yii2 创建并附加 Filter
  ↓
触发事件时 Filter 才执行检查
```

### 误区 2：两个 `beforeAction()` 是同一个方法

它们名称相同，但所属对象和职责不同：

```text
Controller::beforeAction() = 生命周期入口
Filter::beforeAction() = 具体检查逻辑
```

### 误区 3：白名单接口跳过所有检查

白名单只控制：

```text
LoginAuthFilter
```

当前文件中的 `VerifySignatureFilter` 仍然会被挂载，父类 behaviors 也没有被跳过。

### 误区 4：可以省略 `parent::beforeAction()`

如果不调用父类，Yii2 标准的 action 前置事件可能无法触发，依赖该事件的 Filter 也可能不执行。

### 误区 5：可以调用父类但不返回结果

只调用：

```php
parent::beforeAction($action);
```

无法把父类的允许/拒绝结果传回 `runAction()`。正确写法是：

```php
return parent::beforeAction($action);
```

---

## 15. 最终记忆版

```text
runAction() 准备运行业务
  ↓
Controller::beforeAction() 进入前置生命周期
  ↓
AuthApiController 先判断免登录白名单
  ↓
parent::beforeAction() 触发 Yii2 前置事件
  ↓
behaviors() 决定挂载哪些 Filter
  ↓
Filter::beforeAction() 分别执行登录、签名等检查
  ↓
任意一步失败：不执行 action
全部通过：执行 actionXxx()
```

一句话区分：

> `behaviors()` 决定“用谁检查”，Controller 的 `beforeAction()` 决定“何时进入前置流程”，Filter 的 `beforeAction()` 负责“具体检查什么”。
