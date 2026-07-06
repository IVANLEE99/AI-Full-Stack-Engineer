# Week 01 Day 02：OOP 与 ES6 Class 对比

> 所属周：Week 01：PHP 8 + Composer + OOP  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-core`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

掌握 PHP OOP 的核心概念：`class`、属性、方法、构造函数、继承、多态、`interface`、`abstract class`，并能和 ES6 Class / TypeScript interface 做类比。

今天你要真正掌握这一句话：

> PHP 的 class 和 ES6 class 很像，但 PHP 的 interface / abstract / visibility 是后端工程分层的基础；企业项目里的 Service、Repository、Controller 本质上都是靠这些 OOP 规则组织起来的。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解什么是 OOP
2. 学会写最小 PHP class
3. 学会属性和方法
4. 学会构造函数 `__construct`
5. 学会访问控制：`public` / `protected` / `private`
6. 学会继承：`extends`
7. 学会多态：同一个父类/接口，不同实现
8. 学会 interface：定义能力规范
9. 学会 abstract class：定义半成品基类
10. 阅读 `BaseService.php`，理解单例模式
11. 写 Animal / Dog / Cat 练习
12. 完成今日自测和 AI Review

---

## 1. 学习内容

### 1.1 什么是 OOP？

OOP 是 Object-Oriented Programming，中文叫「面向对象编程」。

你可以先这样理解：

> OOP 是把一组相关的数据和行为封装到一个对象里，然后通过 class 复用这些结构。

例如，一个用户有数据：

```text
name
age
email
```

也有行为：

```text
login()
logout()
getProfile()
```

用面向对象写法，就可以把它们放进一个 `User` 类里。

---

### 1.2 PHP class 最小示例

PHP：

```php
<?php

declare(strict_types=1);

class User
{
    public string $name = "Tom";

    public function sayHello(): string
    {
        return "Hello, " . $this->name;
    }
}

$user = new User();

echo $user->sayHello();
```

输出：

```text
Hello, Tom
```

这里有几个重点：

| PHP 语法 | 含义 |
|---|---|
| `class User` | 定义一个 User 类 |
| `public string $name` | 定义公开属性 `$name`，类型是 string |
| `public function sayHello(): string` | 定义公开方法，返回 string |
| `$this->name` | 访问当前对象的属性 |
| `new User()` | 创建对象实例 |
| `$user->sayHello()` | 调用对象方法 |

---

### 1.3 和 ES6 Class 对比

PHP：

```php
class User
{
    public string $name = "Tom";

    public function sayHello(): string
    {
        return "Hello, " . $this->name;
    }
}

$user = new User();
echo $user->sayHello();
```

JavaScript：

```js
class User {
  name = "Tom";

  sayHello() {
    return "Hello, " + this.name;
  }
}

const user = new User();
console.log(user.sayHello());
```

核心类比：

| 对比项 | PHP | JavaScript |
|---|---|---|
| 定义类 | `class User {}` | `class User {}` |
| 创建对象 | `new User()` | `new User()` |
| 当前对象 | `$this` | `this` |
| 访问属性 | `$this->name` | `this.name` |
| 调用方法 | `$user->sayHello()` | `user.sayHello()` |
| 属性变量 | `$name` | `name` |
| 方法可见性 | `public/private/protected` | JS 主要用 `#private` 或约定 |

---

### 1.4 `$this->` 是什么？

在 PHP 类里面，访问当前对象的属性或方法，用：

```php
$this->属性名
$this->方法名()
```

注意：属性名前面不用再写 `$`。

正确：

```php
$this->name
```

错误：

```php
$this->$name
```

完整例子：

```php
<?php

declare(strict_types=1);

class Counter
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count = $this->count + 1;
    }
}

$counter = new Counter();
$counter->increment();
$counter->increment();

echo $counter->count; // 2
```

`void` 表示这个方法没有返回值。

---

### 1.5 构造函数 `__construct`

构造函数会在 `new` 对象时自动执行。

PHP：

```php
<?php

declare(strict_types=1);

class User
{
    public string $name;
    public int $age;

    public function __construct(string $name, int $age)
    {
        $this->name = $name;
        $this->age = $age;
    }

    public function profile(): string
    {
        return $this->name . " is " . $this->age . " years old";
    }
}

$user = new User("Tom", 18);

echo $user->profile();
```

输出：

```text
Tom is 18 years old
```

JavaScript 对比：

```js
class User {
  constructor(name, age) {
    this.name = name;
    this.age = age;
  }

  profile() {
    return `${this.name} is ${this.age} years old`;
  }
}

const user = new User("Tom", 18);
console.log(user.profile());
```

---

### 1.6 PHP 8 构造函数属性提升

PHP 8 支持更简洁的写法：

```php
<?php

declare(strict_types=1);

class User
{
    public function __construct(
        public string $name,
        public int $age,
    ) {}

    public function profile(): string
    {
        return $this->name . " is " . $this->age . " years old";
    }
}

$user = new User("Tom", 18);

echo $user->profile();
```

这等价于：

```php
class User
{
    public string $name;
    public int $age;

    public function __construct(string $name, int $age)
    {
        $this->name = $name;
        $this->age = $age;
    }
}
```

小白先记住：

> 构造函数属性提升就是把「声明属性」和「构造函数赋值」合并到一行。

---

### 1.7 访问控制：public / protected / private

PHP 里类成员通常会写访问控制：

| 关键字 | 作用 | 谁能访问 |
|---|---|---|
| `public` | 公开 | 类内部、子类、外部都能访问 |
| `protected` | 受保护 | 类内部、子类能访问，外部不能访问 |
| `private` | 私有 | 只有当前类内部能访问 |

例子：

```php
<?php

declare(strict_types=1);

class User
{
    public string $name = "Tom";
    protected string $role = "member";
    private string $password = "secret";

    public function getPasswordMask(): string
    {
        return "******";
    }
}

$user = new User();

echo $user->name; // 可以
// echo $user->role; // 不可以：protected
// echo $user->password; // 不可以：private
```

企业项目里，通常会这样用：

- `public`：对外暴露的方法，例如 Service 的业务方法
- `protected`：给子类复用的方法或属性
- `private`：当前类内部细节，不希望外部依赖

---

### 1.8 为什么不要所有东西都 public？

如果所有属性和方法都 public，外部代码就能随便改内部状态。

错误示例：

```php
class Order
{
    public int $status = 0;
}

$order = new Order();
$order->status = 999; // 外部乱改，状态不合法
```

更好的写法：

```php
class Order
{
    private int $status = 0;

    public function pay(): void
    {
        if ($this->status !== 0) {
            throw new RuntimeException("订单不能支付");
        }

        $this->status = 1;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
```

这样状态只能通过 `pay()` 改，逻辑更安全。

---

### 1.9 继承：extends

继承表示：子类拥有父类的属性和方法。

```php
<?php

declare(strict_types=1);

class Animal
{
    public function eat(): string
    {
        return "eating";
    }
}

class Dog extends Animal
{
    public function bark(): string
    {
        return "wang wang";
    }
}

$dog = new Dog();

echo $dog->eat();  // 从 Animal 继承
echo "\n";
echo $dog->bark(); // Dog 自己的方法
```

JavaScript 对比：

```js
class Animal {
  eat() {
    return "eating";
  }
}

class Dog extends Animal {
  bark() {
    return "wang wang";
  }
}
```

---

### 1.10 方法重写 Override

子类可以重写父类方法。

```php
<?php

declare(strict_types=1);

class Animal
{
    public function speak(): string
    {
        return "some sound";
    }
}

class Dog extends Animal
{
    public function speak(): string
    {
        return "wang wang";
    }
}

class Cat extends Animal
{
    public function speak(): string
    {
        return "miao miao";
    }
}

echo (new Dog())->speak(); // wang wang
echo "\n";
echo (new Cat())->speak(); // miao miao
```

这就是多态的基础。

---

### 1.11 多态是什么？

多态可以理解为：

> 同一个父类类型，实际对象不同，执行出来的行为也不同。

例子：

```php
<?php

declare(strict_types=1);

class Animal
{
    public function speak(): string
    {
        return "some sound";
    }
}

class Dog extends Animal
{
    public function speak(): string
    {
        return "wang wang";
    }
}

class Cat extends Animal
{
    public function speak(): string
    {
        return "miao miao";
    }
}

function makeAnimalSpeak(Animal $animal): void
{
    echo $animal->speak() . PHP_EOL;
}

makeAnimalSpeak(new Dog());
makeAnimalSpeak(new Cat());
```

输出：

```text
wang wang
miao miao
```

`makeAnimalSpeak()` 只要求参数是 `Animal`，但传进去的可以是 Dog，也可以是 Cat。

这就是多态。

---

### 1.12 interface 是什么？

`interface` 是「能力规范」。

它只规定：你必须有哪些方法。

它不关心：你内部怎么实现。

例子：

```php
<?php

declare(strict_types=1);

interface PaymentInterface
{
    public function pay(int $amount): bool;
}

class StripePayment implements PaymentInterface
{
    public function pay(int $amount): bool
    {
        echo "Pay by Stripe: " . $amount . PHP_EOL;
        return true;
    }
}

class PaypalPayment implements PaymentInterface
{
    public function pay(int $amount): bool
    {
        echo "Pay by Paypal: " . $amount . PHP_EOL;
        return true;
    }
}

function checkout(PaymentInterface $payment): void
{
    $payment->pay(100);
}

checkout(new StripePayment());
checkout(new PaypalPayment());
```

重点：

```php
implements PaymentInterface
```

表示这个类承诺实现接口里的方法。

---

### 1.13 PHP interface vs TypeScript interface

TypeScript：

```ts
interface Payment {
  pay(amount: number): boolean;
}

class StripePayment implements Payment {
  pay(amount: number): boolean {
    return true;
  }
}
```

PHP：

```php
interface PaymentInterface
{
    public function pay(int $amount): bool;
}

class StripePayment implements PaymentInterface
{
    public function pay(int $amount): bool
    {
        return true;
    }
}
```

| 对比项 | PHP interface | TypeScript interface |
|---|---|---|
| 检查时间 | 运行前/加载类时会校验 | 编译期校验 |
| 编译后是否存在 | 存在，运行时有意义 | 编译后通常消失 |
| 是否可约束类 | 可以 | 可以 |
| 是否可约束对象结构 | 主要约束类 | 常用于约束对象结构 |
| 命名习惯 | `PaymentInterface` | `Payment` |

你可以先记住：

> PHP interface 更像企业后端里的「服务契约」，不是单纯给编辑器看的类型提示。

---

### 1.14 abstract class 是什么？

`abstract class` 是「半成品类」。

它可以：

1. 已经实现一部分通用逻辑
2. 留下一些方法要求子类实现

例子：

```php
<?php

declare(strict_types=1);

abstract class BaseController
{
    public function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    abstract public function handle(): array;
}

class UserController extends BaseController
{
    public function handle(): array
    {
        return [
            "code" => 0,
            "data" => ["name" => "Tom"],
        ];
    }
}

$controller = new UserController();

echo $controller->json($controller->handle());
```

重点：

```php
abstract public function handle(): array;
```

表示：父类只定义方法规范，具体逻辑由子类完成。

---

### 1.15 interface 和 abstract class 怎么选？

小白可以先这样判断：

| 场景 | 用什么 |
|---|---|
| 只想规定必须有哪些方法 | `interface` |
| 想提供一些公共代码，又要求子类实现一部分方法 | `abstract class` |
| 多个完全不同的类拥有同一种能力 | `interface` |
| 多个类本来就是同一类东西的子类 | `abstract class` / `extends` |

例子：

- 支付能力：`PaymentInterface`
- 日志能力：`LoggerInterface`
- 基础 Controller：`abstract BaseController`
- 基础 Service：`BaseService`

---

### 1.16 单例模式是什么？

单例模式的目标是：

> 一个类在程序里只创建一个实例，后续都复用这个实例。

简化版 PHP 单例：

```php
<?php

declare(strict_types=1);

class ConfigService
{
    private static ?ConfigService $instance = null;

    private function __construct()
    {
    }

    public static function instance(): ConfigService
    {
        if (self::$instance === null) {
            self::$instance = new ConfigService();
        }

        return self::$instance;
    }

    public function get(string $key): string
    {
        return "value of " . $key;
    }
}

$config = ConfigService::instance();

echo $config->get("app.name");
```

重点语法：

| 语法 | 含义 |
|---|---|
| `private static ?ConfigService $instance = null` | 类级别静态属性，保存唯一实例 |
| `private function __construct()` | 禁止外部 `new ConfigService()` |
| `public static function instance()` | 对外提供获取实例的方法 |
| `self::$instance` | 访问当前类的静态属性 |
| `ConfigService::instance()` | 调用静态方法 |

---

### 1.17 单例在 Node 里怎么类比？

Node 模块天然有缓存机制。

例如：

```js
// configService.js
class ConfigService {
  get(key) {
    return `value of ${key}`;
  }
}

module.exports = new ConfigService();
```

其他文件：

```js
const configService = require('./configService');

console.log(configService.get('app.name'));
```

Node 会缓存模块，所以多次 `require` 拿到的是同一个对象。

类比：

| PHP | Node.js |
|---|---|
| `Service::instance()` | `require()` 缓存对象 / 单例导出 |
| `private static $instance` | 模块级变量 |
| `private __construct()` | 不导出 class，只导出实例 |

---

## 2. 源码阅读

- `mall-core/common/BaseService.php`

> 说明：路径为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

今天读 `BaseService.php` 不要求你完全看懂每一行，而是重点观察这些问题：

### 2.1 先看类名和命名空间

记录：

```text
namespace 是什么？
class 名是什么？
是否有 extends？
是否有 trait？
```

如果你看到类似：

```php
namespace common;

class BaseService
{
}
```

你要能说：

> 这是一个基础 Service 类，后续业务 Service 可能会继承它或复用它的能力。

---

### 2.2 找 `instance()` 方法

很多企业 PHP 项目里会有类似：

```php
public static function instance()
{
    // ...
}
```

你要观察：

1. 它是不是 `static` 方法？
2. 它是不是返回当前类实例？
3. 它内部有没有缓存对象？
4. 它是不是为了少写 `new XxxService()`？

记录表：

| 观察点 | 记录 |
|---|---|
| 是否有 `instance()` |  |
| 是否使用 `static` |  |
| 是否缓存实例 |  |
| 调用方式是什么 |  |
| 和 Node 单例怎么类比 |  |

---

### 2.3 找公共方法

记录 `BaseService.php` 里出现的 public 方法：

| 方法名 | 大概作用 | 是否有返回值类型 |
|---|---|---|
|  |  |  |
|  |  |  |

如果暂时看不懂方法细节，可以先只记录名字和猜测。

---

### 2.4 看它解决了什么问题

你读完后要能回答：

1. 为什么项目需要 `BaseService`？
2. 它是不是为了让所有 Service 复用同一套能力？
3. 它有没有做单例？
4. 它和业务逻辑 Service 的关系是什么？

小白理解：

> BaseService 就像前端项目里的 BaseApi、BaseStore、BaseModel，是所有同类模块共用的底座。

---

## 3. 练习任务

### 练习 1：写 Animal / Dog / Cat 示例

创建 `animal.php`：

```php
<?php

declare(strict_types=1);

class Animal
{
    public function __construct(
        public string $name,
    ) {}

    public function speak(): string
    {
        return "some sound";
    }
}

class Dog extends Animal
{
    public function speak(): string
    {
        return $this->name . " says wang wang";
    }
}

class Cat extends Animal
{
    public function speak(): string
    {
        return $this->name . " says miao miao";
    }
}

$dog = new Dog("Doggy");
$cat = new Cat("Kitty");

echo $dog->speak() . PHP_EOL;
echo $cat->speak() . PHP_EOL;
```

运行：

```bash
php animal.php
```

期望输出：

```text
Doggy says wang wang
Kitty says miao miao
```

你要理解：

- `Dog` 和 `Cat` 都继承了 `Animal`
- 它们都重写了 `speak()`
- 同一个方法名，在不同子类里有不同表现

---

### 练习 2：写多态函数

在上面的代码后面加：

```php
function printAnimalSound(Animal $animal): void
{
    echo $animal->speak() . PHP_EOL;
}

printAnimalSound(new Dog("A"));
printAnimalSound(new Cat("B"));
```

重点理解：

```php
Animal $animal
```

表示函数接收 Animal 类型。

但实际传入的是：

```php
new Dog("A")
new Cat("B")
```

这就是多态。

---

### 练习 3：写 interface + 两个实现

创建 `payment.php`：

```php
<?php

declare(strict_types=1);

interface PaymentInterface
{
    public function pay(int $amount): bool;
}

class StripePayment implements PaymentInterface
{
    public function pay(int $amount): bool
    {
        echo "Stripe pay: " . $amount . PHP_EOL;
        return true;
    }
}

class PaypalPayment implements PaymentInterface
{
    public function pay(int $amount): bool
    {
        echo "Paypal pay: " . $amount . PHP_EOL;
        return true;
    }
}

function checkout(PaymentInterface $payment): void
{
    $payment->pay(100);
}

checkout(new StripePayment());
checkout(new PaypalPayment());
```

运行：

```bash
php payment.php
```

你要理解：

- `PaymentInterface` 定义支付能力
- `StripePayment` 和 `PaypalPayment` 都实现这个能力
- `checkout()` 不关心具体支付渠道，只关心是否符合接口

---

### 练习 4：写 abstract class 示例

创建 `controller.php`：

```php
<?php

declare(strict_types=1);

abstract class BaseController
{
    public function success(array $data): array
    {
        return [
            "code" => 0,
            "data" => $data,
            "message" => "success",
        ];
    }

    abstract public function handle(): array;
}

class UserController extends BaseController
{
    public function handle(): array
    {
        return $this->success([
            "name" => "Tom",
            "age" => 18,
        ]);
    }
}

$controller = new UserController();

print_r($controller->handle());
```

你要理解：

- `BaseController` 提供公共的 `success()`
- `handle()` 留给子类实现
- `UserController` 继承公共能力，又实现自己的业务逻辑

---

### 练习 5：阅读 `BaseService.php` 并写笔记

写一张表：

| 问题 | 我的答案 |
|---|---|
| `BaseService` 的 namespace 是什么？ |  |
| 它的 class 名是什么？ |  |
| 它有没有 `instance()`？ |  |
| 它解决了什么重复问题？ |  |
| 它和 Node 单例怎么类比？ |  |
| 我还有哪里看不懂？ |  |

---

## 4. JS/Node.js 类比

| PHP OOP | JS/TS 类比 | 差异 |
|---|---|---|
| `class User {}` | `class User {}` | 类概念接近 |
| `new User()` | `new User()` | 创建实例相同 |
| `$this` | `this` | PHP 多 `$` |
| `$obj->method()` | `obj.method()` | PHP 对象访问用 `->` |
| `public` | 默认公开 | PHP 必须更明确 |
| `private` | `#private` / 闭包私有 | PHP 是传统后端 OOP 可见性 |
| `protected` | JS 无完全等价 | 子类可访问，外部不可访问 |
| `extends` | `extends` | 继承概念相同 |
| `interface` | TypeScript interface | PHP 运行时存在，TS 编译后消失 |
| `abstract class` | 抽象基类 / 模板方法 | JS 原生没有完全等价，需要约定 |
| `Service::instance()` | 模块单例 / DI 容器 | PHP 项目常见静态单例 |

---

## 5. AI Review 提问

完成练习后，把你的代码和笔记贴给 AI，然后问：

```text
我正在学习 PHP OOP：class、继承、多态、interface、abstract class、单例模式。

请你按资深 PHP 后端工程师标准帮我检查：

1. 我的 Animal/Dog/Cat 继承和多态示例是否正确？
2. 我的 PaymentInterface 示例是否符合 PHP interface 的用法？
3. 我的 abstract class 示例是否合理？
4. 我对 PHP interface 与 TypeScript interface 的类比是否准确？
5. 我阅读 BaseService.php 时应该重点关注哪些设计？

请用中文输出：
- 我理解正确的地方
- 我理解错误或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

今天结束前，你应该产出：

- [ ] `animal.php`：继承和多态示例
- [ ] `payment.php`：interface + 两个实现
- [ ] `controller.php`：abstract class 示例
- [ ] `BaseService.php` 阅读笔记
- [ ] PHP class vs ES6 class 对照表
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能写一个 PHP class
- [ ] 能解释 `$this->name`
- [ ] 能写构造函数 `__construct`
- [ ] 能解释 `public` / `protected` / `private`
- [ ] 能写 `extends` 继承
- [ ] 能解释多态
- [ ] 能写 `interface` 和 `implements`
- [ ] 能写一个简单 `abstract class`
- [ ] 能说明 PHP interface 与 TypeScript interface 的差异
- [ ] 能说明单例模式的用途
- [ ] 能读 `BaseService.php` 并写出观察笔记

---

## 8. 今日自测题

### 8.1 PHP 里 `$this->name` 是什么意思？

参考答案：

> `$this` 表示当前对象，`->name` 表示访问当前对象的 `name` 属性。

---

### 8.2 `public`、`protected`、`private` 有什么区别？

参考答案：

| 关键字 | 谁能访问 |
|---|---|
| `public` | 类内部、子类、外部都能访问 |
| `protected` | 类内部、子类能访问，外部不能访问 |
| `private` | 只有当前类内部能访问 |

---

### 8.3 什么是继承？

参考答案：

> 继承是子类通过 `extends` 复用父类的属性和方法。例如 `Dog extends Animal`，表示 Dog 是 Animal 的一种。

---

### 8.4 什么是多态？

参考答案：

> 多态是同一个父类或接口类型，传入不同子类对象时，会执行不同实现。例如 `Animal $animal` 可以接收 Dog 或 Cat，调用 `speak()` 时表现不同。

---

### 8.5 interface 是什么？

参考答案：

> interface 是能力规范。它规定一个类必须实现哪些方法，但不关心这些方法内部怎么写。

---

### 8.6 abstract class 和 interface 有什么区别？

参考答案：

> interface 主要定义方法规范，不提供具体实现；abstract class 可以提供部分公共实现，同时要求子类实现某些抽象方法。

---

### 8.7 单例模式解决什么问题？

参考答案：

> 单例模式让一个类在程序中只创建一个实例，并通过 `instance()` 等方法复用这个实例，常用于 Service、配置、工具类等场景。

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
我正在进行 Week 01 Day 02：OOP 与 ES6 Class 对比 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 01 README](./README.md)
