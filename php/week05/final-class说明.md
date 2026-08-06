# PHP：`final class` 用法说明

> 参考：`php-pro.md` 的现代 PHP 实践（严格类型、明确边界、可维护性）  
> 关联学习：`week05/day02.md`（HTTP 客户端封装中的 `PayRequest`、`PayController`）

---

## 1. `final class` 是什么？

在类名前加 `final`，表示**这个类不能被继承**。

```php
<?php

declare(strict_types=1);

final class PayRequest
{
    public function methods(array $params): array
    {
        return $this->get('/pay/methods', $params);
    }
}
```

如果有人尝试继承：

```php
class VipPayRequest extends PayRequest {}
```

PHP 会直接报错（Fatal error）：

```text
Class VipPayRequest may not inherit from final class PayRequest
```

一句话记忆：**`final class` = 这个类就是最终实现，不允许子类再改行为。**

---

## 2. 和 `final method` 的区别

PHP 里 `final` 可以修饰类，也可以修饰方法：

| 写法 | 作用 |
|---|---|
| `final class Foo` | 整个类不能被继承 |
| `final public function bar()` | 类可以继承，但这个方法不能被重写 |

示例：

```php
<?php

declare(strict_types=1);

class BaseApi
{
    final public function success(array $data): array
    {
        return ['code' => 0, 'data' => $data];
    }

    public function handle(): array
    {
        return $this->success([]);
    }
}

class PayController extends BaseApi
{
    // 允许重写 handle()
    public function handle(): array
    {
        return $this->success(['pay' => true]);
    }

    // 不允许重写 success()，会报错
    // public function success(array $data): array { ... }
}
```

选择口诀：

- 整个类都不该被继承 → `final class`
- 类可以继承，但某个方法行为必须固定 → `final method`

---

## 3. 为什么要用 `final class`？

结合 `php-pro.md` 的工程思路，`final class` 的核心价值是**把设计边界写死**，减少“被随意继承改坏”的风险。

### 3.1 意图更明确

看到 `final class PayRequest`，读代码的人立刻知道：

- 这是支付 HTTP 客户端的**最终实现**
- 不应该通过 `extends PayRequest` 来扩展

扩展方式应改为：

- 新建另一个 `OrderRequest`
- 或通过组合（注入不同 client / config）
- 或通过接口（`implements PaymentClientInterface`）

而不是靠继承链越叠越长。

### 3.2 保护不变量

某些类的行为必须稳定，子类重写后可能破坏约束：

```php
final class Response
{
    public static function json(int $httpStatus, int $code, string $message, mixed $data = null): void
    {
        http_response_code($httpStatus);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            ['code' => $code, 'message' => $message, 'data' => $data],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
```

如果允许继承并重写 `json()`，不同接口可能返回不同 JSON 结构，前端解析成本会急剧上升。

### 3.3 降低复杂度

不用 `final` 时，维护者总要考虑：

- 子类会不会重写这个方法？
- 父类改动会不会破坏子类？
- 调用时实际执行的是哪个版本？

`final class` 直接消除“继承扩展”这条分支，代码更容易推理。

### 3.4 与现代 PHP 质量目标一致

`php-pro.md` 强调：

- 严格类型（`declare(strict_types=1)`）
- 明确的参数/返回类型
- 减少隐式行为和意外扩展

`final class` 属于同一类工程纪律：**能确定的边界就明确写出来。**

---

## 4. Week 05 里的典型场景

在 BFF / HTTP 封装学习中，常见写法如下。

### 4.1 HTTP 客户端类

```php
<?php

declare(strict_types=1);

final class PayRequest extends BaseRequest
{
    protected function getBaseUrl(): string
    {
        return 'http://pay-service.internal';
    }

    public function methods(array $params): array
    {
        return $this->get('/pay/methods', $params);
    }
}
```

为什么适合 `final`：

- 一个服务对应一个 `*Request` 类，职责已经固定
- 不需要 `extends PayRequest` 来做“特殊版支付客户端”
- 真要区分场景，应新建类或通过配置/组合解决

### 4.2 薄 Controller

```php
<?php

declare(strict_types=1);

final class PayController extends BaseApi
{
    public function actionMethods(): array
    {
        $userId = $this->getUserId();

        $methods = $this->payRequest->methods([
            'user_id' => $userId,
        ]);

        return $this->success($methods);
    }
}
```

为什么适合 `final`：

- Controller 只做鉴权、取参、转发、返回
- 网关层不应通过继承 Controller 来“偷偷加业务”
- 业务变化应发生在下游微服务，不在 BFF 继承链里堆逻辑

### 4.3 和真实源码的差异

课程伪代码常用 `final class`，但老项目源码里很多类**并没有**加 `final`，例如：

```php
class PayRequest extends BaseApi
{
    protected function getBaseUrl()
    {
        return 'http://pay.internal.example.com/';
    }
}
```

读源码时要区分两层：

| 层次 | 说明 |
|---|---|
| 理解遗留代码 | 老项目没写 `final`，不代表设计上鼓励继承 |
| 新代码质量目标 | 按 `php-pro` 思路，能 `final` 的尽量 `final` |

---

## 5. 什么时候适合 / 不适合用

### 5.1 适合用 `final class`

| 类型 | 原因 |
|---|---|
| `*Request` HTTP 封装类 | 职责单一，按服务拆分，不需要继承扩展 |
| 薄 Controller | 防止在网关层通过继承塞业务 |
| 统一响应工具类 | 保证 JSON 结构一致 |
| DTO / Value Object | 数据结构固定，不应被随意改行为 |
| 工厂的具体实现类 | 创建逻辑明确，不希望被继承篡改 |

### 5.2 先别用 `final class`

| 场景 | 原因 |
|---|---|
| 框架明确要求可继承的基类 | 例如某些 Controller / Model 基类设计就是给子类扩展 |
| 抽象基类 | 抽象类本身就是要被继承，不能同时是 `final` |
| 老项目大量依赖继承链 | 贸然加 `final` 可能破坏现有扩展 |
| 你还没想清楚扩展策略 | 先写普通 class，确认不需要继承后再改 `final` |

---

## 6. 和 `readonly class` 的区别

PHP 8.2+ 还有 `readonly class`，两者不是一回事：

```php
<?php

declare(strict_types=1);

final readonly class UserDto
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
```

| 特性 | `final class` | `readonly class` |
|---|---|---|
| 能否被继承 | 不能 | 可以（除非同时写 `final`） |
| 属性能否修改 | 普通属性仍可改 | 实例属性初始化后不可改 |
| 主要目的 | 禁止继承扩展 | 禁止对象状态被篡改 |

可以组合使用：

```php
final readonly class OrderSnapshot { /* ... */ }
```

表示：**既不能继承，实例状态也不能改。**

---

## 7. 常见错误与排查

### 7.1 继承 final 类报错

```text
Class Child may not inherit from final class Parent
```

处理：

- 不要 `extends` 这个类
- 改用组合、接口或新建独立类

### 7.2 抽象类不能是 final

```php
// 语法错误
final abstract class BaseRepository {}
```

抽象类存在的目的就是被继承，和 `final` 冲突。

### 7.3 误以为 final 类不能实例化

`final` 只禁止继承，不禁止 `new`：

```php
$payRequest = new PayRequest(); // 可以
```

### 7.4 在老项目里机械加 final

如果已有代码存在：

```php
class CustomPayRequest extends PayRequest {}
```

你把 `PayRequest` 改成 `final class` 会直接破坏兼容。  
正确做法：先全局搜索 `extends PayRequest`，确认无继承后再改。

---

## 8. 和 Node.js / TypeScript 的类比

| PHP | TypeScript / Node.js | 说明 |
|---|---|---|
| `final class` | 没有直接等价关键字 | TS 更依赖 lint 规则或 `private constructor` 约束 |
| `final method` | 类似 `final` 方法（TS 无原生） | PHP 在语言层面强制 |
| 组合优于继承 | composition over inheritance | 两边现代后端都推荐 |
| `PayRequest` | `axios.create({ baseURL })` 封装实例 | 通常也不会去“继承 axios 实例” |

PHP 的 `final` 是**编译/运行期硬约束**；TS 更多靠团队规范和工具检查。

---

## 9. 5 分钟速记

1. `final class` = 禁止继承。
2. `final method` = 禁止重写单个方法。
3. 适合：Request 封装、薄 Controller、Response 工具类、DTO。
4. 不适合：抽象基类、框架扩展点、已有继承链的老类。
5. 和 `readonly class` 不同：前者管继承，后者管属性只读。
6. 课程伪代码常用 `final`，老源码可能没有，读代码时要区分“现状”和“目标”。

---

## 10. 自测题（附简短答案）

1. **`final class` 和 `final method` 区别？**  
   前者禁止整个类被继承；后者只禁止某个方法被重写。

2. **为什么 `PayRequest` 适合做成 final？**  
   一个服务一个 Request 类，职责固定，扩展应靠新建类或组合，不应靠继承。

3. **抽象类能写 final 吗？**  
   不能，`final abstract class` 是矛盾的。

4. **final 类还能 new 吗？**  
   可以，`final` 只限制继承，不限制实例化。

5. **老项目源码没写 final，说明设计鼓励继承吗？**  
   不一定，可能只是历史写法；新代码仍可按现代实践使用 `final`。

6. **`final class` 和 `readonly class` 能一起用吗？**  
   可以，例如 `final readonly class UserDto`。

---

## 11. 复习清单

- [ ] 能解释 `final class` 的语法和报错信息
- [ ] 能区分 `final class` 与 `final method`
- [ ] 能说明 Week 05 里 `PayRequest`、`PayController` 为何适合 final
- [ ] 能说出哪些场景不应使用 final
- [ ] 能区分 `final class` 与 `readonly class`
- [ ] 能在读老源码时区分“遗留写法”和“现代目标写法”
