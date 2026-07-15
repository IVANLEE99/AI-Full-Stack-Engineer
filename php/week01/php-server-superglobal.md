# PHP `$_SERVER` 详解

> 配套:`week01/day06.md`
> 目标:理解 `$_SERVER` 的含义、常见字段、HTTP 请求处理方式和安全注意事项

---

## 1. 一句话理解

`$_SERVER` 是 PHP 的一个**超全局变量(superglobal)**,里面保存服务器环境、HTTP 请求、脚本路径等信息。

正确写法是:

```php
$_SERVER
```

不是:

```php
_SERVER
```

其中:

- `$`:表示 PHP 变量
- `_SERVER`:变量名称
- `$_SERVER`:由 PHP 自动填充的关联数组

---

## 2. 什么是“超全局变量”?

普通变量通常只能在当前作用域使用:

```php
$name = "Tom";

function test(): void
{
    // 这里不能直接使用外面的 $name
}
```

而 `$_SERVER` 在函数、方法和全局作用域中都能直接访问,不需要使用 `global`:

```php
function showMethod(): void
{
    echo $_SERVER["REQUEST_METHOD"];
}
```

这就是“超全局”的含义。

PHP 常见超全局变量还有:

```php
$_GET
$_POST
$_COOKIE
$_FILES
$_SESSION
$_ENV
$_REQUEST
$_SERVER
```

---

## 3. `$_SERVER` 的数据结构

`$_SERVER` 本质上是一个**关联数组**:

```php
[
    "REQUEST_METHOD" => "GET",
    "REQUEST_URI" => "/todos?page=1",
    "HTTP_HOST" => "localhost:8000",
    "REMOTE_ADDR" => "127.0.0.1",
    // ...
]
```

因此,要通过数组键读取数据:

```php
$method = $_SERVER["REQUEST_METHOD"];
```

这表示读取当前 HTTP 请求的方法,例如:

```text
GET
POST
PUT
PATCH
DELETE
```

---

## 4. 最常用的 `$_SERVER` 字段

### 4.1 `REQUEST_METHOD`

当前请求使用的 HTTP 方法:

```php
$method = $_SERVER["REQUEST_METHOD"];

echo $method;
```

如果浏览器发送:

```http
GET /todos
```

那么:

```php
$_SERVER["REQUEST_METHOD"] === "GET";
```

如果发送:

```http
POST /todos
```

那么:

```php
$_SERVER["REQUEST_METHOD"] === "POST";
```

可以据此编写简单路由:

```php
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    echo "处理查询请求";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    echo "处理创建请求";
}
```

### 4.2 `REQUEST_URI`

当前请求的 URI,通常包括路径和查询参数:

```php
echo $_SERVER["REQUEST_URI"];
```

访问:

```text
http://localhost:8000/todos?page=2
```

通常得到:

```text
/todos?page=2
```

注意,`REQUEST_URI` 不只是路径,它可能还带有查询字符串。

如果只想要路径,可以使用:

```php
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

echo $path;
// /todos
```

路由示例:

```php
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$method = $_SERVER["REQUEST_METHOD"];

if ($method === "GET" && $path === "/todos") {
    // 处理 GET /todos
}
```

这里分别取得:

- `$path`:请求路径,例如 `/todos`
- `$method`:请求方法,例如 `GET`

### 4.3 `QUERY_STRING`

取得 URL 中 `?` 后面的原始查询字符串:

```php
echo $_SERVER["QUERY_STRING"] ?? "";
```

访问:

```text
/todos?page=2&status=done
```

结果是:

```text
page=2&status=done
```

不过实际开发中,读取查询参数通常直接使用 `$_GET`:

```php
$page = $_GET["page"] ?? 1;
$status = $_GET["status"] ?? null;
```

区别如下:

```php
$_SERVER["QUERY_STRING"]; // "page=2&status=done"
$_GET;                    // ["page" => "2", "status" => "done"]
```

### 4.4 `HTTP_HOST`

当前请求的主机名,可能包含端口:

```php
echo $_SERVER["HTTP_HOST"];
```

访问:

```text
http://localhost:8000/todos
```

可能得到:

```text
localhost:8000
```

注意:`HTTP_HOST` 来源于客户端发送的 `Host` 请求头,**不能直接当成可信数据**。

不推荐直接拼接:

```php
$url = "https://" . $_SERVER["HTTP_HOST"] . "/reset-password";
```

如果必须生成绝对 URL,生产环境最好使用配置文件中的固定域名。

### 4.5 `SERVER_NAME`

服务器配置的主机名:

```php
echo $_SERVER["SERVER_NAME"];
```

它和 `HTTP_HOST` 不完全相同:

| 字段 | 来源 |
|---|---|
| `HTTP_HOST` | 客户端 HTTP 请求头 |
| `SERVER_NAME` | Web 服务器配置 |

具体值会受到 Nginx、Apache、PHP-FPM 等环境影响。

### 4.6 `SERVER_PORT`

服务器接收请求的端口:

```php
echo $_SERVER["SERVER_PORT"];
```

可能是:

```text
80
443
8000
```

PHP 内置服务器:

```bash
php -S localhost:8000
```

通常得到:

```php
$_SERVER["SERVER_PORT"] === "8000";
```

注意:`$_SERVER` 中的很多值看起来是数字,但实际上是**字符串**。

### 4.7 `REMOTE_ADDR`

发起请求的客户端 IP:

```php
$ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
```

本地开发时可能是:

```text
127.0.0.1
```

或者:

```text
::1
```

但在 Nginx、CDN、负载均衡器后面,它可能只是代理服务器的 IP。

有些应用会检查:

```php
$_SERVER["HTTP_X_FORWARDED_FOR"]
```

但这个请求头可以被伪造,不能无条件信任。生产环境应该只信任已配置的代理服务器,并由框架或可信代理中间件解析真实 IP。

### 4.8 `HTTP_USER_AGENT`

浏览器或客户端发送的 User-Agent:

```php
$userAgent = $_SERVER["HTTP_USER_AGENT"] ?? "";
```

可能得到:

```text
Mozilla/5.0 ...
```

它可以用于日志记录和简单分析,但也来自客户端,可能被伪造。

### 4.9 `HTTP_ACCEPT`

表示客户端愿意接收的数据类型:

```php
$accept = $_SERVER["HTTP_ACCEPT"] ?? "";
```

例如:

```text
application/json
```

或者:

```text
text/html,application/xhtml+xml
```

可以用来判断客户端期望 JSON 还是 HTML,但真实项目一般由框架处理内容协商。

### 4.10 `HTTP_AUTHORIZATION`

可能保存客户端传入的认证信息:

```php
$authorization = $_SERVER["HTTP_AUTHORIZATION"] ?? null;
```

例如:

```text
Bearer eyJhbGciOi...
```

解析 Bearer Token:

```php
$authorization = $_SERVER["HTTP_AUTHORIZATION"] ?? "";

if (str_starts_with($authorization, "Bearer ")) {
    $token = substr($authorization, 7);
}
```

这个字段是否存在会受到 Web 服务器和 PHP 运行方式影响。有些环境不会自动将 `Authorization` 头传递给 PHP,需要额外配置 Nginx、Apache 或 PHP-FPM。

### 4.11 `CONTENT_TYPE`

请求体的数据类型:

```php
$contentType = $_SERVER["CONTENT_TYPE"] ?? "";
```

发送 JSON 时通常为:

```text
application/json
```

可能还带字符集:

```text
application/json; charset=utf-8
```

所以不要只做严格相等判断:

```php
// 不够稳妥
if ($contentType === "application/json") {
}
```

可以写:

```php
if (str_starts_with($contentType, "application/json")) {
    // 按 JSON 解析
}
```

### 4.12 `CONTENT_LENGTH`

请求体的字节长度:

```php
$contentLength = $_SERVER["CONTENT_LENGTH"] ?? "0";
```

它常用于文件上传或限制请求体大小,但不是所有请求都包含这个字段。

### 4.13 `SCRIPT_NAME`

当前执行脚本对应的 URL 路径:

```php
echo $_SERVER["SCRIPT_NAME"];
```

可能得到:

```text
/index.php
```

### 4.14 `SCRIPT_FILENAME`

当前执行脚本在服务器中的完整文件路径:

```php
echo $_SERVER["SCRIPT_FILENAME"];
```

可能得到:

```text
/Users/yihuan/project/public/index.php
```

这是文件系统路径,不是 URL。

### 4.15 `DOCUMENT_ROOT`

Web 服务器设置的网站根目录:

```php
echo $_SERVER["DOCUMENT_ROOT"];
```

例如:

```text
/Users/yihuan/project/public
```

但在不同服务器配置、CLI 模式或测试环境中,它可能不存在或不符合预期。拼文件路径时,更推荐使用:

```php
__DIR__
```

例如:

```php
require __DIR__ . "/../vendor/autoload.php";
```

---

## 5. HTTP 请求头为什么会出现在 `$_SERVER`?

多数 HTTP 请求头会被 PHP 转换成:

```text
HTTP_ + 大写请求头名称
```

同时将中划线 `-` 转成下划线 `_`。

例如:

| HTTP 请求头 | `$_SERVER` 中的键 |
|---|---|
| `User-Agent` | `HTTP_USER_AGENT` |
| `Accept` | `HTTP_ACCEPT` |
| `Host` | `HTTP_HOST` |
| `X-Request-Id` | `HTTP_X_REQUEST_ID` |

例如客户端发送:

```http
X-Request-Id: abc-123
```

PHP 中可以读取:

```php
$requestId = $_SERVER["HTTP_X_REQUEST_ID"] ?? null;
```

不过 `Content-Type` 和 `Content-Length` 通常是例外:

```php
$_SERVER["CONTENT_TYPE"];
$_SERVER["CONTENT_LENGTH"];
```

它们一般没有 `HTTP_` 前缀。

---

## 6. 为什么读取时经常使用 `??`?

并不是所有 `$_SERVER` 键在每个环境中都存在。

直接读取不存在的键:

```php
$agent = $_SERVER["HTTP_USER_AGENT"];
```

可能产生警告:

```text
Undefined array key "HTTP_USER_AGENT"
```

更安全的写法是使用空合并运算符:

```php
$agent = $_SERVER["HTTP_USER_AGENT"] ?? "";
```

含义是:

> 如果这个键存在且值不为 `null`,就使用它;否则使用空字符串。

常见写法:

```php
$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$uri = $_SERVER["REQUEST_URI"] ?? "/";
$agent = $_SERVER["HTTP_USER_AGENT"] ?? "unknown";
```

不过对于 Web 环境中必须存在的字段,也可以在缺失时直接报错,而不是随便提供默认值:

```php
$method = $_SERVER["REQUEST_METHOD"]
    ?? throw new RuntimeException("Missing REQUEST_METHOD");
```

---

## 7. 使用 `$_SERVER` 读取 JSON 请求体

`$_SERVER` 保存请求信息,但不会直接保存完整的 JSON 请求体。

如果前端发送:

```http
POST /todos
Content-Type: application/json

{"title":"Learn PHP"}
```

PHP 中可以这样读取:

```php
$contentType = $_SERVER["CONTENT_TYPE"] ?? "";

if (!str_starts_with($contentType, "application/json")) {
    http_response_code(415);
    echo "Unsupported Media Type";
    return;
}

$rawBody = file_get_contents("php://input");

try {
    $data = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    http_response_code(400);
    echo "Invalid JSON";
    return;
}

$title = $data["title"] ?? "";
```

各部分职责:

```php
$_SERVER["CONTENT_TYPE"]          // 请求体是什么格式
file_get_contents("php://input") // 读取原始请求体
json_decode(...)                  // JSON 字符串转换成 PHP 数据
```

---

## 8. 一个简单的原生 PHP 路由示例

```php
<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$uri = $_SERVER["REQUEST_URI"] ?? "/";
$path = parse_url($uri, PHP_URL_PATH);

if ($method === "GET" && $path === "/todos") {
    echo json_encode([
        "code" => 0,
        "message" => "success",
        "data" => [],
    ], JSON_UNESCAPED_UNICODE);

    return;
}

if ($method === "POST" && $path === "/todos") {
    echo json_encode([
        "code" => 0,
        "message" => "created",
        "data" => null,
    ], JSON_UNESCAPED_UNICODE);

    return;
}

http_response_code(404);

echo json_encode([
    "code" => 404,
    "message" => "Not Found",
    "data" => null,
], JSON_UNESCAPED_UNICODE);
```

这里 `$_SERVER` 主要负责提供两个路由条件:

```php
$method // 做什么:GET、POST、PUT、DELETE
$path   // 对谁做:/todos、/users 等
```

---

## 9. Web 环境和 CLI 环境不同

在浏览器、Nginx、Apache 或 PHP 内置服务器中运行时,通常会有:

```php
$_SERVER["REQUEST_METHOD"]
$_SERVER["REQUEST_URI"]
$_SERVER["HTTP_HOST"]
```

但从命令行执行:

```bash
php index.php
```

并没有 HTTP 请求,所以这些键可能不存在。

CLI 中常见的是:

```php
$_SERVER["argv"]; // 命令行参数
$_SERVER["argc"]; // 参数数量
```

示例:

```bash
php test.php hello
```

```php
var_dump($_SERVER["argv"]);
// ["test.php", "hello"]
```

因此,不要假设所有环境都有相同的 `$_SERVER` 字段。

---

## 10. 安全注意事项

`$_SERVER` 是 PHP 自动生成的,但其中一部分内容来自客户端请求,**不代表全部可信**。

以下字段通常可以被客户端控制或伪造:

```php
$_SERVER["HTTP_HOST"]
$_SERVER["HTTP_USER_AGENT"]
$_SERVER["HTTP_X_FORWARDED_FOR"]
$_SERVER["HTTP_REFERER"]
$_SERVER["HTTP_X_REQUEST_ID"]
```

因此:

1. 不要把它们直接拼进 SQL。
2. 输出到 HTML 前要使用 `htmlspecialchars`。
3. 不要仅凭 User-Agent 做身份认证。
4. 不要无条件相信 `X-Forwarded-For`。
5. 不要直接使用 `HTTP_HOST` 生成密码重置链接。
6. 日志中记录请求头时,注意换行符等日志注入问题。
7. 路由判断时,应解析 `REQUEST_URI`,不要直接拿完整 URI 与路径比较。

例如:

```php
// 不够正确,因为 REQUEST_URI 可能是 /todos?page=1
if ($_SERVER["REQUEST_URI"] === "/todos") {
}
```

推荐:

```php
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

if ($path === "/todos") {
}
```

---

## 11. `$_SERVER`、`$_GET`、`$_POST` 的区别

访问:

```text
POST /todos?page=2
```

请求体:

```text
title=Learn+PHP
```

对应:

```php
$_SERVER["REQUEST_METHOD"]; // "POST"
$_SERVER["REQUEST_URI"];    // "/todos?page=2"

$_GET["page"];              // "2"
$_POST["title"];            // "Learn PHP"
```

可以这样理解:

| 变量 / 数据源 | 保存什么 |
|---|---|
| `$_SERVER` | 请求方法、URI、请求头、服务器和脚本信息 |
| `$_GET` | URL 查询参数 |
| `$_POST` | 表单格式的 POST 请求体 |
| `php://input` | 原始请求体,JSON 通常从这里读取 |
| `$_FILES` | 上传的文件 |
| `$_COOKIE` | Cookie |

---

## 12. 前端类比

在 Node.js / Express 中:

```js
app.get('/todos', (req, res) => {
  console.log(req.method);
  console.log(req.url);
  console.log(req.headers);
  console.log(req.ip);
});
```

与 PHP 大致对应:

```text
Express req.method       ≈ $_SERVER["REQUEST_METHOD"]
Express req.url          ≈ $_SERVER["REQUEST_URI"]
Express req.headers.host ≈ $_SERVER["HTTP_HOST"]
Express req.ip           ≈ $_SERVER["REMOTE_ADDR"](代理场景需额外处理)
```

---

## 13. 核心记忆

```php
$method = $_SERVER["REQUEST_METHOD"];              // GET / POST 等
$uri = $_SERVER["REQUEST_URI"];                    // /todos?page=1
$path = parse_url($uri, PHP_URL_PATH);              // /todos
$contentType = $_SERVER["CONTENT_TYPE"] ?? "";     // application/json
$clientIp = $_SERVER["REMOTE_ADDR"] ?? "unknown"; // 客户端 / 代理 IP
```

你当前阶段最需要掌握的是:

> `$_SERVER` 是 PHP 自动提供的超全局关联数组;在原生 PHP API 中,通常通过 `REQUEST_METHOD` 获取请求方法,通过 `REQUEST_URI` 获取 URI,再使用 `parse_url` 提取路径并完成路由匹配。

---

## 返回

- [返回 Week 01 Day 06](./day06.md)
- [PHP 后端高频内置函数速查表](./php-builtin-functions.md)
