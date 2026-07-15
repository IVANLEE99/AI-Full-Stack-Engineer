# PHP `isset`、`unset`、`preg_match` 与 `?:` 详解

> 配套:`week01/php-builtin-functions.md`
> 目标:理解变量存在性判断、变量删除、正则匹配和三元运算符的正确用法与常见陷阱

---

## 1. 快速总览

| 写法 | 类型 | 主要作用 |
|---|---|---|
| `isset($value)` | 语言结构 | 判断变量是否存在且不为 `null` |
| `unset($value)` | 语言结构 | 删除变量或数组元素 |
| `preg_match($pattern, $subject)` | 内置函数 | 使用正则表达式进行一次匹配 |
| `$condition ? $a : $b` | 三元运算符 | 根据条件返回两个值中的一个 |
| `$value ?: $default` | 简写三元运算符 | 值为假值时使用默认值 |

注意:`isset` 和 `unset` 严格来说不是函数,而是 PHP 的**语言结构(language construct)**。它们看起来像函数,但由 PHP 语法直接处理。

---

# 第一部分:`isset`

## 2. `isset` 是什么?

`isset` 用来判断一个变量是否满足以下两个条件:

1. 变量或数组键已经定义;
2. 它的值不是 `null`。

语法:

```php
isset(mixed $var, mixed ...$vars): bool
```

虽然写法类似函数,但 `isset` 是语言结构。

最简单的示例:

```php
$name = "Tom";

var_dump(isset($name));
// bool(true)
```

变量不存在:

```php
var_dump(isset($unknown));
// bool(false)
```

变量存在但值是 `null`:

```php
$name = null;

var_dump(isset($name));
// bool(false)
```

所以可以记住:

```text
isset($value) 为 true
=
$value 已定义并且 $value !== null
```

---

## 3. `isset` 判断不同值

```php
$a = 0;
$b = "";
$c = false;
$d = [];
$e = null;

var_dump(isset($a)); // true
var_dump(isset($b)); // true
var_dump(isset($c)); // true
var_dump(isset($d)); // true
var_dump(isset($e)); // false
var_dump(isset($f)); // false,$f 未定义
```

注意:`0`、空字符串、`false` 和空数组虽然可能被认为是“空值”,但它们都不是 `null`,因此 `isset` 返回 `true`。

---

## 4. 使用 `isset` 判断数组键

`isset` 经常用于判断请求参数或数组字段是否存在:

```php
$user = [
    "name" => "Tom",
    "age" => 0,
];

var_dump(isset($user["name"])); // true
var_dump(isset($user["age"]));  // true
var_dump(isset($user["email"])); // false
```

如果键存在但值是 `null`,`isset` 仍返回 `false`:

```php
$user = [
    "email" => null,
];

var_dump(isset($user["email"]));
// false
```

这意味着 `isset` 无法区分下面两种情况:

```php
$user = [];                  // email 键不存在
$user = ["email" => null];  // email 键存在,值为 null
```

两种情况都会得到:

```php
isset($user["email"]); // false
```

如果需要区分“键不存在”和“键存在但值为 null”,应使用 `array_key_exists`:

```php
$user = ["email" => null];

isset($user["email"]);                  // false
array_key_exists("email", $user);       // true
```

对比:

| 数组状态 | `isset($arr["key"])` | `array_key_exists("key", $arr)` |
|---|---:|---:|
| 键不存在 | false | false |
| 键存在,值为 `null` | false | true |
| 键存在,值为 `0` | true | true |
| 键存在,值为 `false` | true | true |

---

## 5. `isset` 可以同时判断多个变量

```php
$name = "Tom";
$age = 18;

var_dump(isset($name, $age));
// true
```

只有所有变量都存在且不为 `null`,结果才是 `true`:

```php
$name = "Tom";
$age = null;

var_dump(isset($name, $age));
// false
```

它可以理解为:

```php
isset($name, $age)
```

近似等于:

```php
isset($name) && isset($age)
```

---

## 6. 为什么不用直接读取变量?

直接读取不存在的数组键可能产生警告:

```php
$title = $payload["title"];
// 如果 title 不存在:Undefined array key "title"
```

先用 `isset` 判断:

```php
if (isset($payload["title"])) {
    $title = $payload["title"];
}
```

现代 PHP 中,读取带默认值的字段通常更适合使用 `??`:

```php
$title = $payload["title"] ?? "";
```

这近似等价于:

```php
$title = isset($payload["title"])
    ? $payload["title"]
    : "";
```

---

## 7. `isset` 与 `empty` 的区别

`isset` 判断的是:

> 是否已定义并且不为 `null`?

`empty` 判断的是:

> 这个值是不是 PHP 认为的“假值”?

```php
$value = 0;

isset($value); // true:变量存在,也不是 null
empty($value); // true:0 被认为是空值
```

常见值对比:

| 值 | `isset` | `empty` |
|---|---:|---:|
| `null` | false | true |
| `0` | true | true |
| `0.0` | true | true |
| `"0"` | true | true |
| `""` | true | true |
| `false` | true | true |
| `[]` | true | true |
| `"hello"` | true | false |

因此:

- 判断“参数有没有提供”:考虑 `isset` 或 `array_key_exists`;
- 判断“值是否属于 PHP 假值”:使用 `empty`;
- 业务校验时要谨慎使用 `empty`,因为合法的 `0` 也会被判空。

---

# 第二部分:`unset`

## 8. `unset` 是什么?

`unset` 用来删除变量、数组元素或对象的可访问属性。

语法:

```php
unset(mixed $var, mixed ...$vars): void
```

它也是 PHP 语言结构,不是普通函数。

---

## 9. 删除普通变量

```php
$name = "Tom";

var_dump(isset($name)); // true

unset($name);

var_dump(isset($name)); // false
```

`unset($name)` 之后,变量 `$name` 不再存在。

如果之后直接读取:

```php
unset($name);
echo $name;
```

可能得到未定义变量警告。

---

## 10. 删除数组元素

```php
$user = [
    "id" => 1,
    "name" => "Tom",
    "email" => "tom@example.com",
];

unset($user["email"]);

print_r($user);
```

结果:

```php
[
    "id" => 1,
    "name" => "Tom",
]
```

删除不存在的数组键通常不会报错:

```php
unset($user["unknown"]);
```

---

## 11. 删除多个变量或元素

```php
$a = 1;
$b = 2;

unset($a, $b);
```

数组也可以一次删除多个键:

```php
unset($user["email"], $user["password"]);
```

这常用于接口输出前删除敏感字段:

```php
unset($user["password_hash"], $user["reset_token"]);
```

不过真实项目中,更推荐明确构造允许输出的字段,而不是先取全部字段再删除敏感数据:

```php
// 更安全:白名单输出
$response = [
    "id" => $user["id"],
    "name" => $user["name"],
];
```

---

## 12. `unset` 删除索引数组元素后的坑

```php
$items = ["a", "b", "c"];

unset($items[1]);

print_r($items);
```

结果:

```php
[
    0 => "a",
    2 => "c",
]
```

注意:删除索引 1 后,PHP **不会自动重排索引**。

如果直接进行 JSON 编码:

```php
echo json_encode($items);
```

会输出 JSON 对象:

```json
{"0":"a","2":"c"}
```

这是因为键不再是从 0 开始的连续整数。

如果希望输出 JSON 数组,需要使用 `array_values` 重新编号:

```php
$items = array_values($items);

echo json_encode($items);
// ["a","c"]
```

---

## 13. `unset` 与赋值为 `null` 的区别

```php
$name = "Tom";
$name = null;
```

这表示变量仍然被定义过,只是值变成 `null`。

```php
$name = "Tom";
unset($name);
```

这表示变量本身被删除。

但由于 `isset` 对“未定义”和“值为 null”都返回 `false`,下面结果相同:

```php
$name = null;
isset($name); // false

unset($name);
isset($name); // false
```

如果是数组键,可以用 `array_key_exists` 看出区别:

```php
$data["name"] = null;
array_key_exists("name", $data); // true

unset($data["name"]);
array_key_exists("name", $data); // false
```

---

## 14. 在函数内使用 `unset`

PHP 默认按值传递参数。删除函数里的局部参数,不会删除外部变量:

```php
function removeValue(string $value): void
{
    unset($value);
}

$name = "Tom";
removeValue($name);

echo $name;
// Tom
```

即使参数按引用传递,`unset` 也只是解除函数内部变量名的绑定,不会直接删除调用方变量:

```php
function removeReference(string &$value): void
{
    unset($value);
}

$name = "Tom";
removeReference($name);

var_dump($name);
// string(3) "Tom"
```

如果想改变调用方的值,应明确赋值:

```php
function clearValue(?string &$value): void
{
    $value = null;
}
```

---

# 第三部分:`preg_match`

## 15. `preg_match` 是什么?

`preg_match` 使用 PCRE 正则表达式在字符串中查找**第一次匹配**。

函数签名可以简化理解为:

```php
preg_match(
    string $pattern,
    string $subject,
    array &$matches = null,
    int $flags = 0,
    int $offset = 0,
): int|false
```

参数:

| 参数 | 作用 |
|---|---|
| `$pattern` | 正则表达式,包含定界符,例如 `'/^abc$/'` |
| `$subject` | 被检查的字符串 |
| `$matches` | 可选,通过引用接收匹配结果 |
| `$flags` | 可选,控制匹配结果格式 |
| `$offset` | 可选,从字符串哪个位置开始搜索 |

---

## 16. 返回值必须记清楚

`preg_match` 有三种返回结果:

| 返回值 | 含义 |
|---:|---|
| `1` | 匹配成功 |
| `0` | 没有匹配 |
| `false` | 正则表达式执行出错 |

基本示例:

```php
$result = preg_match('/php/', 'I am learning php');

var_dump($result);
// int(1)
```

没有匹配:

```php
$result = preg_match('/java/', 'I am learning php');

var_dump($result);
// int(0)
```

正则出错:

```php
$result = preg_match('/[/', 'test');

var_dump($result);
// bool(false),并可能产生警告
```

最稳妥的判断方式:

```php
$result = preg_match($pattern, $subject);

if ($result === false) {
    // 正则执行出错
} elseif ($result === 1) {
    // 匹配成功
} else {
    // 没有匹配
}
```

不要仅写:

```php
if (!preg_match($pattern, $subject)) {
    // ❌ 这里无法区分 0(没匹配)和 false(正则错误)
}
```

如果正则是代码中固定且确认正确的模式,简单业务校验中常见以下写法:

```php
if (preg_match('/^\d+$/', $value) !== 1) {
    echo "必须全部是数字";
}
```

---

## 17. 正则表达式的组成

```php
$pattern = '/^\d+$/';
```

可以拆成:

```text
/       正则定界符开始
^       字符串开头
\d      数字字符
+       前面的内容出现一次或多次
$       字符串结尾
/       正则定界符结束
```

因此它表示:

> 整个字符串必须全部由一个或多个数字组成。

示例:

```php
preg_match('/^\d+$/', '123');  // 1
preg_match('/^\d+$/', '12a');  // 0
preg_match('/^\d+$/', '');     // 0
```

---

## 18. 常用正则符号

| 符号 | 含义 | 示例 |
|---|---|---|
| `^` | 字符串开头 | `^abc` |
| `$` | 字符串结尾 | `abc$` |
| `.` | 除换行外的任意字符 | `a.c` |
| `\d` | 数字 | `\d+` |
| `\D` | 非数字 | `\D+` |
| `\w` | 字母、数字、下划线 | `\w+` |
| `\s` | 空白字符 | `\s+` |
| `[]` | 字符集合 | `[abc]` |
| `[^]` | 排除字符集合 | `[^0-9]` |
| `*` | 出现 0 次或多次 | `a*` |
| `+` | 出现 1 次或多次 | `a+` |
| `?` | 出现 0 次或 1 次 | `a?` |
| `{n}` | 恰好 n 次 | `\d{6}` |
| `{n,m}` | n 到 m 次 | `\d{6,12}` |
| `()` | 捕获分组 | `(abc)` |
| `|` | 或 | `cat|dog` |

---

## 19. 使用 `$matches` 获取匹配结果

```php
$path = '/todos/123';

$result = preg_match('/^\/todos\/(\d+)$/', $path, $matches);

print_r($matches);
```

结果:

```php
[
    0 => "/todos/123",
    1 => "123",
]
```

含义:

- `$matches[0]`:完整匹配结果 `/todos/123`;
- `$matches[1]`:第一个圆括号捕获的内容 `123`。

在路由中使用:

```php
if (preg_match('/^\/todos\/(\d+)$/', $path, $matches) === 1) {
    $id = (int) $matches[1];

    echo "Todo ID:" . $id;
}
```

正则:

```php
'/^\/todos\/(\d+)$/'
```

可以拆成:

```text
^          从字符串开头开始
\/todos\/ 必须是 /todos/
(\d+)      捕获一个或多个数字
$          到字符串结尾为止
```

它能匹配:

```text
/todos/1
/todos/123
```

不能匹配:

```text
/todos
/todos/abc
/api/todos/1
/todos/1/edit
```

> PHP 正则可以换用其他定界符来减少斜杠转义。例如路由正则写成 `~^/todos/(\d+)$~`,通常比 `/^\/todos\/(\d+)$/` 更易读。

```php
if (preg_match('~^/todos/(\d+)$~', $path, $matches) === 1) {
    $id = (int) $matches[1];
}
```

---

## 20. 命名捕获组

除了数字下标,还可以给捕获组命名:

```php
$path = '/todos/123';

preg_match(
    '~^/todos/(?<id>\d+)$~',
    $path,
    $matches,
);

print_r($matches);
```

结果包含:

```php
[
    0 => "/todos/123",
    "id" => "123",
    1 => "123",
]
```

于是可以写:

```php
$id = (int) $matches["id"];
```

命名捕获组在复杂路由里比 `$matches[1]` 更清楚。

---

## 21. 正则修饰符

正则结束定界符后可以添加修饰符:

```php
preg_match('/php/i', 'PHP');
```

常见修饰符:

| 修饰符 | 作用 |
|---|---|
| `i` | 忽略英文字母大小写 |
| `m` | 多行模式,改变 `^` 和 `$` 的行为 |
| `s` | 让 `.` 也能匹配换行符 |
| `u` | 按 UTF-8 处理模式和字符串 |

中文文本正则通常建议使用 `u`:

```php
preg_match('/^[\p{Han}]+$/u', '你好');
```

`` 等无效 UTF-8 数据可能使带 `u` 的匹配失败,因此外部输入仍需正确处理编码问题。

---

## 22. `preg_match` 和其他方式怎么选择?

简单判断不必使用正则:

```php
str_contains($text, "php");
str_starts_with($path, "/todos");
str_ends_with($file, ".php");
ctype_digit($id);
```

只有模式较复杂时再用 `preg_match`:

```php
preg_match('~^/todos/(\d+)$~', $path, $matches);
```

选择建议:

| 需求 | 推荐方式 |
|---|---|
| 是否包含固定文本 | `str_contains` |
| 是否以固定文本开头 | `str_starts_with` |
| 是否全部为数字字符 | `ctype_digit` 或正则 |
| 从动态路由提取数字 ID | `preg_match` |
| 校验复杂格式 | `preg_match` |
| 查找所有匹配 | `preg_match_all` |
| 正则替换 | `preg_replace` |

> 不建议尝试用一个简单正则完整验证所有合法邮箱。一般业务可优先使用 `filter_var($email, FILTER_VALIDATE_EMAIL)`。

---

## 23. `preg_match` 错误处理

当 `preg_match` 返回 `false` 时,可以通过 `preg_last_error_msg()` 获取最近一次 PCRE 错误描述(PHP 8+):

```php
$result = preg_match($pattern, $subject);

if ($result === false) {
    throw new RuntimeException(preg_last_error_msg());
}
```

它与 JSON 错误处理的思路类似:

```text
json_last_error_msg()  → 最近一次 JSON 操作错误
preg_last_error_msg()  → 最近一次正则操作错误
```

---

# 第四部分:`?:` 三元运算符

## 24. 完整三元运算符

完整语法:

```php
条件 ? 条件为真时的值 : 条件为假时的值
```

示例:

```php
$age = 20;
$status = $age >= 18 ? "adult" : "child";

echo $status;
// adult
```

等价的 `if/else` 写法:

```php
if ($age >= 18) {
    $status = "adult";
} else {
    $status = "child";
}
```

三元运算符是一个**表达式**,会产生一个值,因此适合赋值或作为参数:

```php
$message = $success ? "成功" : "失败";

echo $success ? "yes" : "no";

jsonResponse(
    $success ? 0 : 500,
    $success ? "success" : "error",
);
```

---

## 25. 简写三元运算符(Elvis 运算符)

PHP 允许省略中间部分:

```php
$value ?: $default
```

它近似等价于:

```php
$value ? $value : $default
```

示例:

```php
$name = "Tom";
$result = $name ?: "Guest";

// Tom
```

当 `$name` 是 PHP 假值时,返回默认值:

```php
$name = "";
$result = $name ?: "Guest";

// Guest
```

这种简写有时被称为 Elvis 运算符,因为 `?:` 看起来像一张侧着的脸。

---

## 26. `?:` 会把哪些值当成假?

简写三元运算符根据 PHP 的布尔转换规则判断左侧值。

以下值都会触发默认值:

```php
false
0
0.0
""
"0"
[]
null
```

示例:

```php
0 ?: 100;         // 100
"" ?: "default"; // "default"
"0" ?: "default"; // "default"
[] ?: [1, 2];     // [1, 2]
null ?: "none";  // "none"
```

这是 `?:` 最重要的坑:合法的 `0`、`"0"`、`false` 和空数组都会被替换。

例如页码:

```php
$page = 0;
$result = $page ?: 1;

// 得到 1,而不是 0
```

如果业务上 `0` 是合法值,通常不应使用 `?:`。

---

## 27. `?:` 与 `??` 的区别

这两个运算符看起来相似,但判断规则不同。

### `?:` 判断“是不是假值”

```php
$result = $value ?: $default;
```

只要 `$value` 是 PHP 假值,就使用默认值。

### `??` 判断“是否不存在或为 null”

```php
$result = $value ?? $default;
```

只有变量不存在或值为 `null` 时才使用默认值。

对比:

| `$value` | `$value ?: "default"` | `$value ?? "default"` |
|---|---|---|
| 未定义 | 可能产生警告后返回默认值 | `"default"` |
| `null` | `"default"` | `"default"` |
| `0` | `"default"` | `0` |
| `"0"` | `"default"` | `"0"` |
| `false` | `"default"` | `false` |
| `""` | `"default"` | `""` |
| `[]` | `"default"` | `[]` |
| `"Tom"` | `"Tom"` | `"Tom"` |

接口参数读取通常优先用 `??`:

```php
$title = $payload["title"] ?? "";
```

因为它不会把 `0` 或 `false` 当作“没有提供”。

例如 Todo 的完成状态:

```php
$done = $payload["done"] ?? false;
```

如果请求明确传入 `false`,结果仍然是 `false`。

---

## 28. `?:` 与完整三元运算符的区别

完整三元运算符允许“条件”和“返回值”不同:

```php
$label = $age >= 18 ? "成年人" : "未成年人";
```

简写 `?:` 会把左侧值同时作为条件和成功结果:

```php
$name = $inputName ?: "Guest";
```

可以理解为:

```php
$name = $inputName
    ? $inputName
    : "Guest";
```

---

## 29. 三元运算符的可读性

适合三元运算符:

```php
$status = $done ? "completed" : "pending";
```

逻辑复杂时使用 `if/else` 更清楚:

```php
if ($score >= 90) {
    $grade = "A";
} elseif ($score >= 80) {
    $grade = "B";
} else {
    $grade = "C";
}
```

不要为了少写几行而制造难读的嵌套:

```php
// ❌ 难读,而且无括号的嵌套三元在 PHP 8 中不允许
$result = $a ? $b : $c ? $d : $e;
```

如果确实需要嵌套,必须明确加括号,但通常仍建议改成 `if/elseif/else`:

```php
$result = $a ? $b : ($c ? $d : $e);
```

---

## 30. 运算符优先级注意事项

复杂表达式里应主动加括号,不要依赖记忆运算符优先级:

```php
$message = "Result:" . ($success ? "success" : "error");
```

比下面更容易正确理解:

```php
$message = "Result:" . $success ? "success" : "error";
```

推荐原则:

> 三元表达式与字符串拼接、算术或其他逻辑运算组合时,使用括号明确意图。

---

# 第五部分:综合示例

## 31. Todo 路由中的综合使用

```php
$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$uri = $_SERVER["REQUEST_URI"] ?? "/";
$path = parse_url($uri, PHP_URL_PATH);

if (preg_match('~^/todos/(?<id>\d+)$~', $path, $matches) === 1) {
    $id = (int) $matches["id"];

    if ($method === "DELETE") {
        unset($todos[$id]);

        $message = isset($todos[$id])
            ? "delete failed"
            : "deleted";

        echo json_encode([
            "code" => 0,
            "message" => $message,
            "data" => null,
        ], JSON_UNESCAPED_UNICODE);
    }
}
```

各知识点:

```php
$_SERVER["REQUEST_METHOD"] ?? "GET"
// ??:键不存在或为 null 时使用默认值

preg_match('~^/todos/(?<id>\d+)$~', $path, $matches) === 1
// preg_match:判断并提取动态路由中的数字 ID

unset($todos[$id])
// unset:删除对应 Todo

isset($todos[$id])
// isset:检查数组键删除后是否仍存在

$condition ? "a" : "b"
// 完整三元运算符:根据条件选择消息
```

---

## 32. 请求参数校验示例

```php
$payload = json_decode(
    file_get_contents("php://input") ?: "{}",
    true,
) ?: [];

if (!isset($payload["title"])) {
    echo "缺少 title 字段";
    return;
}

$title = trim((string) $payload["title"]);

if ($title === "") {
    echo "title 不能为空";
    return;
}

if (isset($payload["priority"])) {
    $priority = (string) $payload["priority"];

    if (preg_match('/^(low|medium|high)$/', $priority) !== 1) {
        echo "priority 格式错误";
        return;
    }
}

$done = $payload["done"] ?? false;
$message = $done ? "已完成" : "未完成";
```

注意这段代码中的选择:

- `isset($payload["title"])`:判断是否提供字段;
- `$title === ""`:精确判断整理后的标题是否为空;
- `preg_match(...) !== 1`:要求正则明确匹配成功;
- `$payload["done"] ?? false`:保留请求传来的 `false`;
- `$done ? ... : ...`:根据布尔值选择显示文字。

---

## 33. 前端 JavaScript 类比

| PHP | JavaScript 近似写法 |
|---|---|
| `isset($data["id"])` | `data.id !== undefined && data.id !== null` |
| `unset($data["id"])` | `delete data.id` |
| `preg_match('/^\d+$/', $id)` | `/^\d+$/.test(id)` |
| `$ok ? "yes" : "no"` | `ok ? "yes" : "no"` |
| `$value ?: "default"` | `value || "default"` |
| `$value ?? "default"` | `value ?? "default"` |

尤其注意:

```text
PHP ?:  ≈ JavaScript ||
PHP ??  ≈ JavaScript ??
```

二者都要区分“假值”和“null / 未定义”。

---

## 34. 核心检查清单

- [ ] `isset` 判断变量“已定义且不为 null”;
- [ ] `isset` 无法区分“键不存在”和“键存在但值为 null”;
- [ ] 需要区分时使用 `array_key_exists`;
- [ ] `unset` 删除变量或数组元素,索引数组不会自动重排;
- [ ] `unset` 后输出 JSON 列表时,可能需要 `array_values`;
- [ ] `preg_match` 返回 `1`、`0` 或 `false`,推荐使用严格比较;
- [ ] 提取路由参数时,从 `$matches[1]` 或命名捕获组读取;
- [ ] 简单字符串判断优先使用 `str_contains` / `str_starts_with` 等函数;
- [ ] 完整三元运算符是 `$condition ? $a : $b`;
- [ ] 简写 `$value ?: $default` 会替换所有 PHP 假值;
- [ ] `$value ?? $default` 只处理不存在或 `null`,通常更适合读取请求参数;
- [ ] 不要堆叠难读的嵌套三元表达式。

---

## 返回

- [PHP 后端高频内置函数速查表](./php-builtin-functions.md)
- [PHP `$_SERVER` 详解](./php-server-superglobal.md)
- [返回 Week 01 Day 06](./day06.md)
