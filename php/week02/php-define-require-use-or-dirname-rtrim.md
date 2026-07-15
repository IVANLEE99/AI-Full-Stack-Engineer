# PHP `define` / `require` 与 `use` / `or` / `dirname` / `rtrim` 详解

> 配套：`week02/day01.md` Yii2 入口与启动、`week02/yii2-entry-startup.md`
> 目标：搞懂 Yii2 入口脚本里这几个常见语法元素分别是什么、怎么用、常见坑

这几个概念都会在 Yii2 入口脚本里遇到：

```php
<?php

defined('YII_DEBUG') or define('YII_DEBUG', true);   // define + or

require __DIR__ . '/../vendor/autoload.php';           // require
use yii\web\Application;                                // use

'basePath' => dirname(__DIR__),                         // dirname
$base = rtrim($path, '/');                              // rtrim
```

---

## 1. `define` — 定义常量

`define()` 用来定义一个**常量**(constant)。常量和变量的最大区别：常量一旦定义就**不能修改、不能删除**，而且使用时不带 `$` 符号。

```php
define('YII_DEBUG', true);

echo YII_DEBUG;   // 使用时直接写名字，不加 $
```

对比变量：

```php
$name = "Tom";      // 变量:带 $,可以随时改
$name = "Jerry";    // ✅ 允许

define('SITE', "a.com");
define('SITE', "b.com");   // ❌ 报错:常量不能重复定义
```

### 1.1 三个特点

```php
define('MAX_SIZE', 100);       // 1. 使用时不带 $
define('MAX_SIZE', 200);       // 2. 不可重定义(会警告)
// MAX_SIZE = 300;             // 3. 不能用 = 赋值
```

### 1.2 现代写法:`const`

PHP 里还有另一种定义常量的方式 `const`，在类里更常用：

```php
const MAX_SIZE = 100;          // 文件/类级别

class Order
{
    const STATUS_PAID = 1;     // 类常量
}

echo Order::STATUS_PAID;       // 用 :: 访问类常量
```

区别：

| | `define()` | `const` |
|---|---|---|
| 定义时机 | 运行时 | 编译时 |
| 能否放在 `if` 里 | ✅ 可以 | ❌ 不可以 |
| 类常量 | ❌ 不能定义类常量 | ✅ 可以 |

Yii2 入口用 `define` 正是因为要配合 `defined() or ...` 做**条件判断**（运行时才决定定不定义）。

### 1.3 前端类比

```text
PHP define/const  ≈  JS 的 const(但 PHP 常量是全局的,不受块作用域限制)
```

---

## 2. `require` 和 `use` — 完全不同的两件事

这两个特别容易被初学者混淆，其实**没有任何关系**。

### 2.1 `require` — 运行时“把文件内容搬进来”

`require` 在**运行时**读取并执行另一个 PHP 文件，相当于把那个文件的代码“粘贴”到当前位置。

```php
require __DIR__ . '/../vendor/autoload.php';   // 真的去磁盘读这个文件并执行
```

它操作的是**文件**（路径字符串，带引号、带 `.php`）。如果文件有 `return`，还能拿到返回值：

```php
$config = require __DIR__ . '/config/web.php';  // 拿到文件 return 的数组
```

`require` 家族：

| 写法 | 找不到文件时 |
|---|---|
| `require` | 报**致命错误**,脚本停止 |
| `require_once` | 同上,但保证只加载一次(重复 require 会跳过) |
| `include` | 只报**警告**,脚本继续 |
| `include_once` | 警告 + 只加载一次 |

> 加载配置、类文件、autoload 一般用 `require` / `require_once`，因为缺了就没法运行，必须立刻报错。

### 2.2 `use` — 编译时“给类名起别名 / 导入命名空间”

`use` 和文件无关，它是在**命名空间(namespace)** 层面工作的，作用是让你不用每次都写一长串完整类名。

```php
// 不用 use:每次都要写完整路径
$app = new yii\web\Application($config);

// 用 use 导入后:
use yii\web\Application;

$app = new Application($config);   // 直接用短名字
```

它操作的是**类名 / 命名空间**（不是文件路径，没有引号，没有 `.php`）。

`use` 的三种形态：

```php
use yii\web\Application;              // 导入类
use yii\helpers\ArrayHelper as AH;    // 起别名
use function App\Helpers\formatMoney; // 导入函数(较少见)
```

### 2.3 两者关系:配合但独立

```php
require __DIR__ . '/../vendor/autoload.php';  // ① 让 PHP“有能力”找到类文件
use yii\web\Application;                       // ② 让我能用短名字写这个类
$app = new Application($config);               // ③ 实际使用
```

- 没有 ①(autoload)，用到类时会“找不到类文件”
- 没有 ②(use)，代码能跑，只是得写全名 `new yii\web\Application(...)`

> 一句话：**`require` 管“文件在不在”，`use` 管“类名怎么写得短”**。

### 2.4 前端类比

```text
require file.php          ≈  Node 的 require('./file')(运行时读文件)
use App\Foo\Bar;          ≈  import { Bar } from '...'(导入符号,但不指定文件)
```

注意：JS 的 `import` 同时做了“找文件 + 导入符号”两件事；PHP 把这两件事拆成了 `require`（autoload 负责找文件）和 `use`（导入符号）。

---

## 3. `or` — 逻辑或(重点是它的“短路”和低优先级)

`or` 是逻辑或运算符，和 `||` 意思几乎一样，但**优先级极低**，这正是 Yii2 入口那行代码的关键。

### 3.1 短路特性

`A or B`：先算 `A`，如果 `A` 已经为 `true`，就**不再执行 B**（反正结果已经是 true 了）。

```php
defined('YII_DEBUG') or define('YII_DEBUG', true);
```

读作：“YII_DEBUG 已定义 **或者** 去定义它”。

- 如果 `defined('YII_DEBUG')` 是 `true`（外部已定义）→ 短路，右边 `define` 不执行
- 如果是 `false`（还没定义）→ 执行右边的 `define`

等价于：

```php
if (!defined('YII_DEBUG')) {
    define('YII_DEBUG', true);
}
```

### 3.2 `or` vs `||` 的坑:优先级不同

这是最容易踩的点。`=` 的优先级**高于** `or`，但**低于** `||`：

```php
$result = true || false;   // $result = (true || false) = true   ✅
$result = true or false;   // ($result = true) or false → $result = true(但容易误解)

$a = false or true;        // 实际是:($a = false) or true  →  $a 得到的是 false!
$a = false || true;        // $a = (false || true)  →  $a 得到 true
```

> 所以 `or` **几乎只用在** `defined() or define()`、`$fp = fopen(...) or die(...)` 这类“做副作用、不接收返回值”的场景。**判断条件、给变量赋值时永远用 `||`**，避免优先级陷阱。

### 3.3 前端类比

```text
PHP ||   ≈  JS ||(优先级正常)
PHP or   ≈  JS 没有这种“超低优先级的 or”
```

---

## 4. `dirname` — 取路径的“上一级目录”

`dirname()` 返回一个路径中**去掉最后一段后**的父目录部分。

```php
echo dirname('/usr/local/bin/php');
// /usr/local/bin

echo dirname('/var/www/html/index.php');
// /var/www/html   (去掉了 index.php)
```

### 4.1 在 Yii2 配置里的典型用法

```php
'basePath' => dirname(__DIR__),
```

拆开看：

- `__DIR__` = 当前文件所在目录，比如 `/project/config`
- `dirname(__DIR__)` = 再往上一层 = `/project`

也就是“配置文件在 `config/` 下，项目根目录是它的上一级”。

### 4.2 第二个参数:往上跳几层(PHP 7+)

```php
echo dirname('/a/b/c/d', 1);   // /a/b/c   (默认,上 1 层)
echo dirname('/a/b/c/d', 2);   // /a/b     (上 2 层)
echo dirname('/a/b/c/d', 3);   // /a       (上 3 层)
```

所以 `dirname(__DIR__, 2)` 常用来从深层目录一次跳回项目根。

### 4.3 和 `basename` 是一对

```php
$path = '/var/www/index.php';
echo dirname($path);    // /var/www     ← 目录部分
echo basename($path);   // index.php    ← 文件名部分
```

### 4.4 前端类比

```text
PHP dirname()   ≈  Node 的 path.dirname()
PHP basename()  ≈  Node 的 path.basename()
PHP __DIR__     ≈  Node 的 __dirname
```

---

## 5. `rtrim` — 去掉字符串**右侧**的字符

`rtrim()`（right trim）删除字符串**结尾**的指定字符，默认删空白。

```php
echo rtrim("hello   ");     // "hello"  (删掉右边空格)
echo rtrim("hello\n");      // "hello"  (删掉右边换行)
```

### 5.1 第二个参数:指定要删的字符

不传第二参数时删空白（空格、`\t`、`\n`、`\r` 等）；传了就删你指定的字符。

```php
echo rtrim("/api/users/", "/");   // "/api/users"   删掉结尾的 /
```

### 5.2 trim 三兄弟

| 函数 | 作用 |
|---|---|
| `trim($s)` | 删**两端** |
| `ltrim($s)` | 删**左侧**(left) |
| `rtrim($s)` | 删**右侧**(right) |

```php
trim("  hi  ");    // "hi"
ltrim("  hi  ");   // "hi  "
rtrim("  hi  ");   // "  hi"
```

### 5.3 常见用途:规范化路径

处理 URL / 目录路径时，常用它去掉结尾多余的斜杠，避免拼出 `//`：

```php
$base = rtrim($basePath, '/');    // 保证结尾没有 /
$url  = $base . '/todos';         // 干净地拼接
```

### 5.4 一个大坑:第二个参数是“字符集合”不是“字符串”

`rtrim($s, ".php")` **不是**删除结尾的 `".php"` 这个词，而是删除结尾出现的 **`.`、`p`、`h`** 这几个字符（任意组合）：

```php
echo rtrim("test.php", ".php");   // "test"      p、h、. 都被当成待删字符
echo rtrim("hello.php", ".php");  // "hello"     看起来对,其实是巧合
echo rtrim("graph", ".php");      // "gra"       ❌ 把 p、h 也删了!
```

> 想删除固定后缀，应该用 `str_ends_with` + `substr`，别用 `rtrim` 删后缀：
>
> ```php
> $name = "graph.php";
> if (str_ends_with($name, ".php")) {
>     $name = substr($name, 0, -4);   // "graph"
> }
> ```

### 5.5 前端类比

```text
PHP rtrim()   ≈  JS 的 str.trimEnd()(默认删空白时)
PHP ltrim()   ≈  JS 的 str.trimStart()
PHP trim()    ≈  JS 的 str.trim()
```

差异：JS 的 `trimEnd()` 只能删空白，PHP 的 `rtrim()` 还能指定删任意字符集合。

---

## 6. 小结 / 检查清单

- [ ] `define()` 定义常量(运行时、可条件定义)，使用时不带 `$`；`const` 是编译时
- [ ] `require` 管**文件**(运行时读文件，可接 `return` 值)；`use` 管**类名**(编译时导入符号)
- [ ] `require` 缺文件报致命错误；`include` 只报警告
- [ ] `or` 优先级比 `=` 还低，只用于副作用场景；赋值/判断一律用 `||`
- [ ] `dirname()` 取父目录，第二参数可往上跳多层；配 `__DIR__` 定位项目根
- [ ] `rtrim()` 删右侧字符，第二参数是**字符集合**不是后缀词，删后缀用 `str_ends_with` + `substr`

---

## 返回

- [返回 Week 02 Day 01](./day01.md)
- [Yii2 入口与启动详解](./yii2-entry-startup.md)
