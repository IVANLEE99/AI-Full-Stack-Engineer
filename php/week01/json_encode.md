# PHP `json_encode` 详解

> 配套：`week01/day05.md` 的统一 JSON 响应部分  
> 目标：彻底搞懂 `json_encode` 是什么、怎么用、常见坑和常用参数

---

## 1. 一句话理解

`json_encode` 是 PHP 内置函数,作用是把 **PHP 的数组 / 对象 / 标量** 转换成 **JSON 格式的字符串**。

```php
$data = ["name" => "Tom", "age" => 18];
echo json_encode($data);
// 输出:{"name":"Tom","age":18}
```

反过来,把 JSON 字符串解析回 PHP 变量的函数是 `json_decode`。

```text
PHP 变量  --- json_encode --->  JSON 字符串
JSON 字符串 --- json_decode ---> PHP 变量
```

前端类比:

```text
json_encode  ≈  JSON.stringify()
json_decode  ≈  JSON.parse()
```

---

## 2. 函数签名

```php
json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
```

| 参数 | 含义 |
|---|---|
| `$value` | 要编码的数据(数组、对象、字符串、数字、布尔、null) |
| `$flags` | 选项标志,多个用 `|` 组合,比如 `JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT` |
| `$depth` | 最大嵌套深度,默认 512,一般不用管 |

返回值:成功返回 JSON 字符串,**失败返回 `false`**(注意判断失败)。

> 上面签名里的第一个参数 `mixed $value` 用到了 `mixed` 类型,下一节专门解释它。

---

## 2.5 `mixed` 类型是什么?

`mixed` 是 PHP 8.0 引入的类型,表示「任意类型」,相当于告诉 PHP「这个位置什么类型都可以」。

### 它等价于哪些类型的集合

`mixed` 等于下面所有类型的联合:

```php
object | resource | array | string | int | float | bool | null | callable
```

所以写 `mixed` 就等于说「我接受任何值,包括 `null`」。

### 为什么需要它

PHP 7 里,如果一个参数可能是数组、字符串、数字甚至 `null`,你只能**不写类型**:

```php
// PHP 7:没法准确表达,只能留空
function jsonResponse($data) { }
```

留空的问题是:别人读代码时不知道你是「故意接受任意类型」还是「忘了写类型」。PHP 8 的 `mixed` 让这个意图变得明确:

```php
// PHP 8:明确表示「我就是要接受任意类型」
function jsonResponse(mixed $data): void { }
```

这正是 day05 里 `Response::json` 和本文件 `json_encode` 签名用它的原因 —— `data` / `value` 可能是数组、对象、字符串、数字或 `null`,全都要支持:

```php
public static function json(int $code, string $message, mixed $data = null): void
```

### 前端类比

```text
PHP mixed  ≈  TypeScript any / unknown
```

更接近 `unknown`,因为它表达的是「类型未知 / 任意」,而不是「放弃类型检查」。

### 几个使用规则(容易踩坑)

1. **`mixed` 已经包含 `null`,不能再写成可空**

```php
function foo(?mixed $x) {}   // ❌ 报错:mixed 本身就含 null
function foo(mixed $x) {}    // ✅
```

2. **作返回类型时,子类可以收窄它(协变)**

父类返回 `mixed`,子类重写时可以返回更具体的类型,反过来不行:

```php
class A {
    public function get(): mixed { return null; }
}

class B extends A {
    public function get(): string { return "ok"; }  // ✅ 允许收窄
}
```

3. **不要滥用**

`mixed` 会关闭该位置的类型检查,能写具体类型就写具体类型。它适合「确实无法确定类型」的通用工具函数(统一响应、序列化、缓存存取),不适合业务逻辑里本该明确的参数。

---

## 3. 数组 → JSON 的关键规则:对象还是数组?

这是最容易踩的点。`json_encode` 会根据 PHP 数组的 **键** 决定输出成 JSON 对象 `{}` 还是 JSON 数组 `[]`。

### 3.1 关联数组(字符串键)→ JSON 对象 `{}`

```php
echo json_encode(["code" => 0, "message" => "ok"]);
// {"code":0,"message":"ok"}
```

### 3.2 索引数组(从 0 开始的连续整数键)→ JSON 数组 `[]`

```php
echo json_encode([1, 2, 3]);
// [1,2,3]

echo json_encode(["a", "b", "c"]);
// ["a","b","c"]
```

### 3.3 索引不连续 / 不从 0 开始 → 变成对象 `{}`

```php
echo json_encode([1 => "a", 2 => "b"]);
// {"1":"a","2":"b"}   注意变成对象了!

$arr = ["x", "y"];
unset($arr[0]);
echo json_encode($arr);
// {"1":"y"}   删掉 0 后键不连续,也变对象了
```

> 记住:只有「从 0 开始且连续」的整数键数组才会输出成 `[]`,否则一律输出成 `{}`。这在写接口时很关键,比如列表接口本该返回 `[]`,却因为过滤后键不连续变成了 `{}`,前端就会解析出错。

修正办法:用 `array_values()` 重排键。

```php
echo json_encode(array_values($arr));
// ["y"]
```

---

## 4. 常用 flags(标志)

### 4.1 `JSON_UNESCAPED_UNICODE` — 中文不转义

默认情况下中文会被转成 `\uXXXX`:

```php
echo json_encode(["msg" => "成功"]);
// {"msg":"成功"}
```

加上标志后中文原样输出:

```php
echo json_encode(["msg" => "成功"], JSON_UNESCAPED_UNICODE);
// {"msg":"成功"}
```

### 4.2 `JSON_UNESCAPED_SLASHES` — 斜杠不转义

默认 `/` 会被转义成 `\/`:

```php
echo json_encode(["url" => "https://a.com/x"]);
// {"url":"https:\/\/a.com\/x"}

echo json_encode(["url" => "https://a.com/x"], JSON_UNESCAPED_SLASHES);
// {"url":"https://a.com/x"}
```

### 4.3 `JSON_PRETTY_PRINT` — 格式化输出(带缩进)

```php
echo json_encode(["code" => 0, "data" => [1, 2]], JSON_PRETTY_PRINT);
```

输出:

```json
{
    "code": 0,
    "data": [
        1,
        2
    ]
}
```

调试时好用,正式接口一般不加(浪费带宽)。

### 4.4 组合使用

多个标志用 `|` 连接,这是接口开发最常见的写法:

```php
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
```

### 4.5 `JSON_THROW_ON_ERROR` — 出错时抛异常(PHP 7.3+)

默认失败返回 `false`,加这个标志改成抛 `JsonException`,方便 try/catch。

```php
try {
    $json = json_encode($data, JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    // 处理编码失败
}
```

---

## 5. 各种数据类型的编码结果

```php
json_encode(null);           // null
json_encode(true);           // true
json_encode(123);            // 123
json_encode(1.5);            // 1.5
json_encode("hello");        // "hello"
json_encode([]);             // []      空数组是 []
json_encode((object)[]);     // {}      空对象是 {}
json_encode(["a" => 1]);     // {"a":1}
```

一个细节:空数组 `[]` 编码成 `[]`,但如果你想要空对象 `{}`,需要强转成对象 `(object)[]` 或用 `JSON_FORCE_OBJECT` 标志。

---

## 6. 对象的编码

普通对象会把 **public 属性** 编码进去,private / protected 属性会被忽略。

```php
class User
{
    public int $id = 1;
    public string $name = "Tom";
    private string $secret = "xxx";
}

echo json_encode(new User());
// {"id":1,"name":"Tom"}   secret 不会出现
```

如果想自定义对象的 JSON 结构,让类实现 `JsonSerializable` 接口:

```php
class Money implements \JsonSerializable
{
    public function __construct(private int $cents) {}

    public function jsonSerialize(): mixed
    {
        return $this->cents / 100;  // 分转元
    }
}

echo json_encode(["price" => new Money(1999)]);
// {"price":19.99}
```

---

## 7. 失败处理

`json_encode` 失败会返回 `false`。常见失败原因:

- 数据里含无效的 UTF-8 字节
- 出现循环引用(对象 A 引用 B,B 又引用 A)
- 嵌套超过 `$depth` 深度

判断错误:

```php
$json = json_encode($data);
if ($json === false) {
    echo json_last_error_msg();  // 打印错误原因
}
```

### 7.1 查错函数:`json_last_error_msg` 与 `json_last_error`

`json_encode` 失败返回 `false`、`json_decode` 失败返回 `null`,这两个返回值本身**看不出错在哪**。而且 `null` 有歧义:JSON 字符串 `"null"` 解码出来也是 `null`,分不清是"解码成功得到 null"还是"解码失败"。PHP 提供了一对函数来补上"错误原因":

| 函数 | 返回 | 用途 |
|---|---|---|
| `json_last_error()` | **整数错误码**(常量) | 程序判断具体是哪种错误 |
| `json_last_error_msg()` | **人类可读的字符串** | 打印 / 记录日志给人看 |

`json_last_error_msg()` 直接给一句人话:

```php
$json = json_encode($data);
if ($json === false) {
    echo json_last_error_msg();
    // 例如:Malformed UTF-8 characters, possibly incorrectly encoded
}
```

`json_last_error()` 返回常量,适合精确分支处理:

```php
$data = json_decode($input);

switch (json_last_error()) {
    case JSON_ERROR_NONE:
        // 无错误
        break;
    case JSON_ERROR_SYNTAX:
        echo "JSON 语法错误";
        break;
    case JSON_ERROR_UTF8:
        echo "非法 UTF-8 字符";
        break;
    default:
        echo json_last_error_msg();  // 兜底,直接打印文字
}
```

常见错误码:

| 常量 | 含义 |
|---|---|
| `JSON_ERROR_NONE` | 无错误(值为 0) |
| `JSON_ERROR_DEPTH` | 超过最大嵌套深度 |
| `JSON_ERROR_SYNTAX` | 语法错误(解码时最常见) |
| `JSON_ERROR_UTF8` | 含非法 UTF-8 字符(编码时最常见) |
| `JSON_ERROR_RECURSION` | 出现循环引用 |

**重要坑点**:这两个函数返回的是**上一次** JSON 操作的全局状态,要**紧挨着** JSON 调用之后立刻检查,中间别再插入其他 `json_*` 操作,否则状态会被覆盖:

```php
json_encode($a);              // 假设这次失败
json_encode($b);              // 又调了一次,状态被刷新
echo json_last_error_msg();   // ❌ 反映的是 $b 的结果,不是 $a
```

### 7.2 现代写法:`JSON_THROW_ON_ERROR`

PHP 7.3+ 更推荐用异常代替手动查错,代码更干净,也不用担心上面的"状态被覆盖"坑:

```php
try {
    $json = json_encode($data, JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    echo $e->getMessage();  // 等价于 json_last_error_msg() 的内容
}
```

前端类比:

```text
JS:  JSON.parse() 失败会直接 throw SyntaxError,用 try/catch 捕获
PHP: 老写法返回 false/null + json_last_error_msg() 查原因
     新写法(JSON_THROW_ON_ERROR)才和 JS 一样抛异常
```

---

## 8. 在接口里的完整用法(呼应 day05)

```php
function jsonResponse(int $code, string $message, mixed $data = null): void
{
    // 1. 先声明返回类型是 JSON,并指定 UTF-8 编码
    header('Content-Type: application/json; charset=utf-8');

    // 2. 把统一结构的数组编码成 JSON 字符串并输出
    echo json_encode([
        "code" => $code,
        "message" => $message,
        "data" => $data,
    ], JSON_UNESCAPED_UNICODE);
}
```

逐行说明:

- `header(...)`:告诉客户端「我返回的是 JSON」,否则可能被当成纯文本 / HTML。**必须在任何 echo 输出之前调用**,否则会报 headers already sent。
- `json_encode([...], JSON_UNESCAPED_UNICODE)`:把 `code / message / data` 三段式数组转成 JSON,中文不转义。
- `echo`:把 JSON 字符串写进 HTTP 响应体,客户端就收到了。

调用示例:

```php
jsonResponse(0, "success", ["id" => 1, "title" => "Learn PHP"]);
```

输出:

```json
{"code":0,"message":"success","data":{"id":1,"title":"Learn PHP"}}
```

---

## 9. 小结 / 检查清单

- [ ] `json_encode` 把 PHP 变量转成 JSON 字符串,`json_decode` 是反向
- [ ] 关联数组 → `{}`,连续索引数组 → `[]`,键不连续会意外变成 `{}`(用 `array_values` 修正)
- [ ] 中文默认转义成 `\uXXXX`,用 `JSON_UNESCAPED_UNICODE` 保留原文
- [ ] 多个 flags 用 `|` 组合
- [ ] 对象只编码 public 属性,想自定义就实现 `JsonSerializable`
- [ ] 失败返回 `false`,用 `json_last_error_msg()` 查原因,或加 `JSON_THROW_ON_ERROR` 抛异常
- [ ] 接口里 `header()` 必须在 `echo` 之前调用

---

## 返回

- [返回 Week 01 Day 05](./day05.md)
