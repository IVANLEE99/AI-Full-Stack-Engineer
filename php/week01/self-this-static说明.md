# PHP：`self::$instance`、`self`、`$this` 与 `::` 用法说明

## 1. `self::$instance` 是什么？

`self::$instance` 通常出现在「单例模式」里：

```php
class Database
{
    private static ?Database $instance = null;

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }

        return self::$instance;
    }
}
```

这里：

```php
self::$instance
```

意思是：

> 访问当前类自己的静态属性 `$instance`。

拆开看：

| 写法 | 含义 |
|---|---|
| `self` | 当前类 |
| `::` | 访问类成员的操作符 |
| `$instance` | 静态属性名 |

所以：

```php
self::$instance
```

可以理解为：

```php
Database::$instance
```

只是写成 `self` 更灵活，表示「当前这个类」。

---

## 2. `self` 和 `$this` 的区别

### 2.1 `$this`

`$this` 表示：

> 当前对象。

只能在对象方法里使用。

```php
class User
{
    public string $name = "Tom";

    public function sayName(): void
    {
        echo $this->name;
    }
}

$user = new User();
$user->sayName();
```

这里：

```php
$this->name
```

表示访问当前对象的 `$name` 属性。

---

### 2.2 `self`

`self` 表示：

> 当前类。

通常用来访问静态属性、静态方法、类常量。

```php
class User
{
    public static string $type = "admin";

    public static function getType(): string
    {
        return self::$type;
    }
}
```

这里：

```php
self::$type
```

表示访问当前类的静态属性 `$type`。

---

## 3. `self` 和 `$this` 对比表

| 对比项 | `$this` | `self` |
|---|---|---|
| 表示 | 当前对象 | 当前类 |
| 常用于 | 普通属性、普通方法 | 静态属性、静态方法、类常量 |
| 访问属性 | `$this->name` | `self::$name` |
| 访问方法 | `$this->say()` | `self::say()` |
| 是否需要 `new` 对象 | 需要 | 不一定 |
| 是否能在 `static` 方法里用 | 不能 | 能 |

---

## 4. `::` 的用法

`::` 叫做：

> 范围解析操作符，Scope Resolution Operator。

它用来访问类级别的东西。

---

### 4.1 访问静态属性

```php
class User
{
    public static string $name = "Tom";
}

echo User::$name;
```

注意：静态属性前面要有 `$`。

正确：

```php
User::$name
```

错误：

```php
User::name
```

---

### 4.2 访问静态方法

```php
class User
{
    public static function hello(): void
    {
        echo "Hello";
    }
}

User::hello();
```

---

### 4.3 访问类常量

```php
class User
{
    public const ROLE = "admin";
}

echo User::ROLE;
```

注意：类常量没有 `$`。

---

## 5. `->` 和 `::` 的区别

| 符号 | 用途 | 示例 |
|---|---|---|
| `->` | 对象访问 | `$user->name` |
| `::` | 类访问 | `User::$name` |

示例：

```php
class User
{
    public string $name = "Tom";
    public static string $type = "admin";

    public function sayName(): void
    {
        echo $this->name;
    }

    public static function sayType(): void
    {
        echo self::$type;
    }
}

$user = new User();

echo $user->name;   // 对象属性
$user->sayName();   // 对象方法

echo User::$type;   // 静态属性
User::sayType();    // 静态方法
```

---

## 6. 一句话记忆

```php
$this->name
```

表示：

> 从当前对象身上拿 `name`。

```php
self::$instance
```

表示：

> 从当前类身上拿静态属性 `$instance`。

```php
User::hello()
```

表示：

> 直接调用 `User` 类上的静态方法 `hello()`。
