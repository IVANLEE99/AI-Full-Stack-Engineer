# Yii2：Filter `beforeAction()` 为什么看起来先于 Controller `beforeAction()`

## 结论

常见代码中会看到这样的顺序：

```text
执行 Filter beforeAction
  ↓
执行 Controller beforeAction 的自定义逻辑
```

更准确地说，Filter 并不是在 Controller 的 `beforeAction()` 方法之外、完全独立地先执行，而是：

> Controller 的 `beforeAction()` 调用父类实现时，Yii2 触发 `EVENT_BEFORE_ACTION` 事件；挂载在这个事件上的 Filter 会在事件触发阶段执行自己的 `beforeAction()`。

## 执行流程

```text
Controller::beforeAction() 开始执行
  ↓
调用 parent::beforeAction($action)
  ↓
触发 EVENT_BEFORE_ACTION
  ↓
ActionFilter 收到事件
  ↓
执行 Filter::beforeAction($action)
  ↓
父类 beforeAction() 返回
  ↓
执行 Controller 中 parent::beforeAction() 后面的代码
  ↓
执行 action
```

Yii2 的 Controller 逻辑可以简化为：

```php
public function beforeAction($action)
{
    $event = new ActionEvent($action);

    // 触发事件，已挂载的 Filter 会在这里收到通知
    $this->trigger(self::EVENT_BEFORE_ACTION, $event);

    return $event->isValid;
}
```

Filter 通常通过事件监听前置阶段：

```php
public function events()
{
    return [
        Controller::EVENT_BEFORE_ACTION => 'beforeFilter',
    ];
}
```

`beforeFilter()` 随后会根据条件调用具体 Filter 的 `beforeAction()`。

## 为什么 Filter 看起来更早？

业务 Controller 常见写法如下：

```php
public function beforeAction($action)
{
    if (!parent::beforeAction($action)) {
        return false;
    }

    // Controller 自己的前置逻辑
    $this->prepareSomething();

    return true;
}
```

实际顺序是：

```text
进入 Controller::beforeAction()
  ↓
调用 parent::beforeAction()
  ↓
触发事件并执行 Filter
  ↓
parent::beforeAction() 返回
  ↓
执行 prepareSomething()
```

因此，Filter 会先于 `parent::beforeAction()` 后面的 Controller 自定义代码执行。

## 位置决定顺序

如果 Controller 代码放在调用父类之前：

```php
public function beforeAction($action)
{
    // 这段代码会先执行
    $this->prepareSomething();

    return parent::beforeAction($action);
}
```

那么顺序会变成：

```text
Controller 自定义代码
  ↓
Filter
  ↓
action
```

所以不能简单记成“Filter 永远先于 Controller”。应该记成：

> Filter 在父类 `beforeAction()` 触发 `EVENT_BEFORE_ACTION` 时执行；Controller 自定义代码与它的先后，取决于 `parent::beforeAction()` 的位置。

## `parent::beforeAction()` 为什么重要？

如果重写 Controller 的 `beforeAction()` 却不调用父类：

```php
public function beforeAction($action)
{
    return true;
}
```

可能导致父类中的标准生命周期逻辑和事件触发逻辑不执行，相关 Filter 也可能不会执行。

通常推荐：

```php
public function beforeAction($action): bool
{
    if (!parent::beforeAction($action)) {
        return false;
    }

    // 当前 Controller 的额外检查
    return true;
}
```

## 两个 `beforeAction()` 的职责区别

| 方法 | 角色 | 主要职责 |
|---|---|---|
| `Controller::beforeAction()` | 生命周期入口 | 进入 action 前触发统一事件，并决定是否继续 |
| `Filter::beforeAction()` | 过滤器检查逻辑 | 执行登录、权限、签名等具体检查 |

## 最终记忆

```text
behaviors()：决定挂载哪些 Filter
Controller::beforeAction()：进入前置生命周期并触发事件
Filter::beforeAction()：执行具体的公共检查
action：执行业务逻辑
```

一句话总结：

> Filter 不是神秘地“跑到 Controller 前面”，而是在 Controller 的父类 `beforeAction()` 触发事件时被调用；`parent::beforeAction()` 放在哪里，决定了 Filter 与 Controller 自定义代码的相对顺序。
