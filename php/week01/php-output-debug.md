# PHP 中 echo、print_r、var_dump 详细说明

在 PHP 里，常见的输出/调试方式有三个：

```php
echo
print_r
var_dump
```

它们都可以把内容输出出来，但适合的场景不一样。

---

## 1. echo 是什么？

`echo` 用来输出**简单内容**，比如：

- 字符串
- 数字
- 拼接后的文本

例如：

```php
echo "Hello PHP";
```

输出：

```text
Hello PHP
```

再比如：

```php
$name = "Tom";

echo "用户名：" . $name;
```

输出：

```text
用户名：Tom
```

---

## 2. echo 适合输出什么？

`echo` 适合输出简单值：

```php
echo "hello";
echo 123;
echo 3.14;
echo true;
```

例如异常处理中常见写法：

```php
echo "错误：" . $e->getMessage();
```

意思是：输出异常对象里的错误消息。

---

## 3. echo 不适合直接输出数组

例如：

```php
$user = [
    "id" => 1,
    "name" => "Tom",
];

echo $user;
```

这样不合适。

因为 `$user` 是数组，不是字符串。

PHP 可能会提示：

```text
Warning: Array to string conversion
```

所以数组一般不要直接用 `echo` 输出。

---

## 4. print_r 是什么？

`print_r` 主要用来打印**数组**或**对象**的结构。

它比 `echo` 更适合调试数组。

例如：

```php
$user = [
    "id" => 1,
    "name" => "Tom",
];

print_r($user);
```

输出：

```text
Array
(
    [id] => 1
    [name] => Tom
)
```

这样可以清楚看到数组里面有哪些 key 和 value。

---

## 5. print_r 的常见使用场景

### 5.1 输出函数返回的数组

```php
function errorResponse(int $code, string $message): array
{
    return [
        "code" => $code,
        "message" => $message,
        "data" => null,
    ];
}

print_r(errorResponse(400, "参数错误"));
```

输出类似：

```text
Array
(
    [code] => 400
    [message] => 参数错误
    [data] =>
)
```

---

### 5.2 输出业务方法返回的数组

```php
$user = [
    "id" => 1,
    "display_name" => "Tom",
];

print_r($user);
```

输出：

```text
Array
(
    [id] => 1
    [display_name] => Tom
)
```

---

## 6. print_r($value, true) 是什么意思？

普通写法：

```php
print_r($user);
```

意思是：直接输出 `$user`。

但是如果写成：

```php
print_r($user, true);
```

意思是：**不要直接输出，而是把打印结果作为字符串返回**。

例如：

```php
$user = [
    "id" => 1,
    "name" => "Tom",
];

$text = print_r($user, true);

echo "用户信息：" . $text;
```

这里的 `$text` 会保存数组打印后的字符串。

---

## 7. 为什么有时 print_r 要加 true？

如果你想把 `print_r` 的结果和字符串拼接，就需要加 `true`。

例如：

```php
echo "数组内容：" . print_r($user, true) . PHP_EOL;
```

可以理解成：

```php
$text = print_r($user, true);

echo "数组内容：" . $text . PHP_EOL;
```

如果不加 `true`：

```php
echo "数组内容：" . print_r($user) . PHP_EOL;
```

`print_r($user)` 会先直接输出数组，然后返回 `true`，拼接结果可能会变得不好理解。

---

## 8. var_dump 是什么？

`var_dump` 也是用来调试的。

它比 `print_r` 更详细。

它不仅会输出值，还会输出：

- 数据类型
- 字符串长度
- 数组元素数量
- 对象结构

例如：

```php
$name = "Tom";

var_dump($name);
```

输出：

```text
string(3) "Tom"
```

意思是：

```text
这是一个 string 字符串
长度是 3
值是 Tom
```

---

## 9. var_dump 输出不同类型

### 9.1 输出整数

```php
$age = 18;

var_dump($age);
```

输出：

```text
int(18)
```

---

### 9.2 输出小数

```php
$price = 99.9;

var_dump($price);
```

输出：

```text
float(99.9)
```

---

### 9.3 输出布尔值

```php
$isVip = true;

var_dump($isVip);
```

输出：

```text
bool(true)
```

---

### 9.4 输出 null

```php
$data = null;

var_dump($data);
```

输出：

```text
NULL
```

---

### 9.5 输出数组

```php
$user = [
    "id" => 1,
    "name" => "Tom",
];

var_dump($user);
```

输出类似：

```text
array(2) {
  ["id"]=>
  int(1)
  ["name"]=>
  string(3) "Tom"
}
```

这里可以看到：

```text
数组有 2 个元素
id 的值是 int 类型
name 的值是 string 类型，长度是 3
```

---

## 10. print_r 和 var_dump 的区别

同一个数组：

```php
$user = [
    "id" => 1,
    "name" => "Tom",
    "is_vip" => true,
    "email" => null,
];
```

使用 `print_r`：

```php
print_r($user);
```

输出类似：

```text
Array
(
    [id] => 1
    [name] => Tom
    [is_vip] => 1
    [email] =>
)
```

使用 `var_dump`：

```php
var_dump($user);
```

输出类似：

```text
array(4) {
  ["id"]=>
  int(1)
  ["name"]=>
  string(3) "Tom"
  ["is_vip"]=>
  bool(true)
  ["email"]=>
  NULL
}
```

区别：

```text
print_r 更清爽，适合快速看数组内容。
var_dump 更详细，适合看类型和值。
```

---

## 11. echo、print_r、var_dump 对比表

| 方法 | 主要用途 | 适合输出什么 | 是否显示类型 | 常见场景 |
|---|---|---|---|---|
| `echo` | 正常输出内容 | 字符串、数字 | 不显示 | 页面输出、命令行输出 |
| `print_r` | 调试数组/对象 | 数组、对象 | 基本不显示详细类型 | 快速查看数组结构 |
| `var_dump` | 深度调试 | 任意类型 | 显示详细类型 | 查看变量类型和值 |

---

## 12. 完整示例对比

代码：

```php
<?php

declare(strict_types=1);

$user = [
    "id" => 1,
    "name" => "Tom",
    "is_vip" => true,
    "age" => 18,
    "email" => null,
];

echo $user["name"];

echo PHP_EOL;

print_r($user);

echo PHP_EOL;

var_dump($user);
```

输出大概是：

```text
Tom

Array
(
    [id] => 1
    [name] => Tom
    [is_vip] => 1
    [age] => 18
    [email] =>
)

array(5) {
  ["id"]=>
  int(1)
  ["name"]=>
  string(3) "Tom"
  ["is_vip"]=>
  bool(true)
  ["age"]=>
  int(18)
  ["email"]=>
  NULL
}
```

---

## 13. 什么时候用哪个？

### 13.1 输出普通文本：用 echo

```php
echo "登录成功";
echo "错误：" . $e->getMessage();
```

---

### 13.2 查看数组内容：用 print_r

```php
print_r($user);
print_r($list);
print_r($service->getProfile(1));
```

---

### 13.3 想看类型和值：用 var_dump

```php
var_dump($user);
var_dump($age);
var_dump($isVip);
var_dump($data);
```

---

## 14. 对应异常代码里的用法

### 14.1 echo 输出异常消息

```php
echo "错误消息：" . $e->getMessage() . PHP_EOL;
```

这里适合用 `echo`，因为 `$e->getMessage()` 返回的是字符串。

---

### 14.2 print_r 输出数组响应

```php
print_r(errorResponse(400, $e->getMessage()));
```

这里适合用 `print_r`，因为 `errorResponse()` 返回的是数组。

---

### 14.3 print_r 输出用户数组

```php
print_r($service->getProfile(1));
```

这里适合用 `print_r`，因为 `getProfile()` 返回的是数组。

---

### 14.4 var_dump 查看更详细的类型

如果你想看更详细的信息，可以改成：

```php
var_dump($service->getProfile(1));
```

输出会变成类似：

```text
array(2) {
  ["id"]=>
  int(1)
  ["display_name"]=>
  string(3) "Tom"
}
```

这样能看到：

```text
id 是 int 类型
display_name 是 string 类型
```

---

## 15. 小白记法

```text
echo：给用户看的输出。
print_r：给程序员看的数组结构。
var_dump：给程序员看的详细调试信息。
```

再简单一点：

```text
echo 看结果。
print_r 看数组。
var_dump 看类型和值。
```
