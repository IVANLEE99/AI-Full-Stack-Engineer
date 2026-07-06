# Week 01 Day 01：PHP 8 类型系统与工程入口

> 所属周：Week 01：PHP 8 + Composer + OOP  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-core`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 PHP 类型、`strict_types`、Composer autoload，并能用 Node.js/npm/import 做类比。

今天你要真正掌握这一句话：

> Composer 通过 `vendor/autoload.php` 读取 PSR-4 规则，把 PHP 的 namespace 自动映射到文件路径；这就像 Node 通过 import/require 找模块，但 PHP 更依赖 Composer 的 autoload 配置。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先知道 PHP 文件怎么运行
2. 理解 PHP 变量和基础类型
3. 理解 PHP 和 JS 类型系统的差异
4. 理解 `strict_types=1`
5. 理解 Composer 是什么
6. 理解 `vendor/autoload.php`
7. 理解 PSR-4：namespace 如何映射到文件路径
8. 做一个最小 demo，跑通自动加载
9. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 确认 PHP 和 Composer 是否可用

先在终端执行：

```bash
php -v
```

你应该看到类似：

```text
PHP 8.x.x ...
```

再执行：

```bash
composer -V
```

你应该看到类似：

```text
Composer version ...
```

如果这两个命令都能输出版本，说明今天的基础环境 OK。

---

### 1.2 理解 PHP 文件怎么运行

新建一个临时文件：

```php
<?php

echo "Hello PHP\n";
```

保存为：

```text
hello.php
```

运行：

```bash
php hello.php
```

你会看到：

```text
Hello PHP
```

你需要记住：PHP 文件一般以 `<?php` 开头。

如果整个文件都是 PHP 代码，通常不写结束标签：

```php
?>
```

这是 PHP 项目里的常见规范，避免输出多余空格导致问题。

---

### 1.3 理解 PHP 变量

PHP 变量必须以 `$` 开头：

```php
<?php

$name = "Tom";
$age = 18;

echo $name;
echo "\n";
echo $age;
```

和 JS 对比：

```js
const name = "Tom";
const age = 18;
```

| 对比项 | PHP | JS |
|---|---|---|
| 变量前缀 | `$name` | `name` |
| 字符串拼接 | `$a . $b` | `a + b` 或模板字符串 |
| 输出 | `echo` | `console.log` |
| 文件执行 | `php xxx.php` | `node xxx.js` |

---

### 1.4 掌握 PHP 基础类型

PHP 常见基础类型：

```php
<?php

$name = "Alice";      // string 字符串
$age = 20;            // int 整数
$price = 19.99;       // float 浮点数
$isVip = true;        // bool 布尔值
$items = [1, 2, 3];   // array 数组
$user = null;         // null
```

可以用 `var_dump()` 查看类型：

```php
<?php

$name = "Alice";
$age = 20;
$isVip = true;

var_dump($name);
var_dump($age);
var_dump($isVip);
```

输出类似：

```text
string(5) "Alice"
int(20)
bool(true)
```

小白重点：`var_dump()` 类似 JS 里的：

```js
console.log(typeof value, value);
```

但 PHP 的 `var_dump()` 信息更详细。

---

### 1.5 理解 PHP 数组

PHP 的 `array` 比 JS 数组更复杂。

#### 1.5.1 普通索引数组

```php
<?php

$names = ["Tom", "Jerry", "Alice"];

echo $names[0]; // Tom
```

类似 JS：

```js
const names = ["Tom", "Jerry", "Alice"];
console.log(names[0]);
```

#### 1.5.2 关联数组

```php
<?php

$user = [
    "name" => "Tom",
    "age" => 18,
    "is_vip" => true,
];

echo $user["name"];
```

类似 JS 对象：

```js
const user = {
  name: "Tom",
  age: 18,
  isVip: true,
};

console.log(user.name);
```

重点区别：PHP 里：

```php
[
    "name" => "Tom",
    "age" => 18,
]
```

更像 JS 的：

```js
{
  name: "Tom",
  age: 18,
}
```

但 PHP 统一叫 `array`。

---

### 1.6 理解 PHP 函数和类型声明

最基础函数：

```php
<?php

function add($a, $b)
{
    return $a + $b;
}

echo add(1, 2);
```

加上类型声明：

```php
<?php

function add(int $a, int $b): int
{
    return $a + $b;
}

echo add(1, 2);
```

这里：

```php
int $a
```

表示参数 `$a` 必须是整数。

```php
): int
```

表示函数返回值必须是整数。

对比 TypeScript：

```php
function add(int $a, int $b): int
{
    return $a + $b;
}
```

```ts
function add(a: number, b: number): number {
  return a + b;
}
```

| 对比项 | PHP | TypeScript |
|---|---|---|
| 类型检查时间 | 运行时 | 编译期 |
| 错误发生时机 | 执行代码时 | 编译/编辑时 |
| 是否影响运行 | 是 | TS 编译后类型消失 |

---

### 1.7 理解 `strict_types=1`

PHP 默认会做一些自动类型转换。

例如：

```php
<?php

function add(int $a, int $b): int
{
    return $a + $b;
}

echo add("1", "2");
```

这段代码在默认情况下可能会输出：

```text
3
```

因为 PHP 把字符串 `"1"` 和 `"2"` 自动转成整数。

开启严格模式：

```php
<?php

declare(strict_types=1);

function add(int $a, int $b): int
{
    return $a + $b;
}

echo add("1", "2");
```

这时会报错，因为你传的是字符串，不是整数。

你要记住：

> `strict_types=1` 的作用是让 PHP 在函数参数类型上更严格，减少隐式类型转换带来的 bug。

企业项目里，现代 PHP 文件通常建议这样开头：

```php
<?php

declare(strict_types=1);
```

---

### 1.8 至少掌握 3 个 PHP 8 特性

#### 特性 1：`match`

类似 JS 的 `switch`，但更像表达式：

```php
<?php

$status = 1;

$text = match ($status) {
    0 => "待支付",
    1 => "已支付",
    2 => "已取消",
    default => "未知状态",
};

echo $text;
```

JS 类比：

```js
const status = 1;

const text = {
  0: "待支付",
  1: "已支付",
  2: "已取消",
}[status] ?? "未知状态";
```

#### 特性 2：nullsafe operator：`?->`

PHP：

```php
<?php

$name = $user?->profile?->name;
```

JS：

```js
const name = user?.profile?.name;
```

都是为了避免空值访问错误。

#### 特性 3：named arguments

PHP：

```php
<?php

function createUser(string $name, int $age, bool $isVip): array
{
    return [
        "name" => $name,
        "age" => $age,
        "is_vip" => $isVip,
    ];
}

$user = createUser(
    name: "Tom",
    age: 18,
    isVip: true,
);
```

类似 JS 里传对象参数：

```js
function createUser({ name, age, isVip }) {
  return { name, age, isVip };
}

const user = createUser({
  name: "Tom",
  age: 18,
  isVip: true,
});
```

#### 特性 4：enum

PHP 8.1+：

```php
<?php

enum OrderStatus: int
{
    case Pending = 0;
    case Paid = 1;
    case Cancelled = 2;
}
```

TypeScript 类比：

```ts
enum OrderStatus {
  Pending = 0,
  Paid = 1,
  Cancelled = 2,
}
```

#### 特性 5：readonly

PHP 8.1+：

```php
<?php

class User
{
    public function __construct(
        public readonly string $name,
        public readonly int $age,
    ) {}
}
```

表示对象创建后，属性不能再被改。

TypeScript 类比：

```ts
class User {
  constructor(
    public readonly name: string,
    public readonly age: number,
  ) {}
}
```

---

### 1.9 理解 Composer 是什么

Composer 是 PHP 的依赖管理工具。

可以先把它理解成：

```text
Composer ≈ npm
composer.json ≈ package.json
vendor/ ≈ node_modules/
```

Node 项目常见结构：

```text
package.json
node_modules/
```

PHP 项目常见结构：

```text
composer.json
vendor/
```

---

### 1.10 理解 `composer.json`

一个简单的 `composer.json` 可能长这样：

```json
{
  "require": {
    "monolog/monolog": "^3.0"
  },
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
```

今天重点看两块。

#### `require`

```json
"require": {
  "monolog/monolog": "^3.0"
}
```

类似 npm 的：

```json
"dependencies": {
  "monolog": "^3.0"
}
```

#### `autoload`

```json
"autoload": {
  "psr-4": {
    "App\\": "src/"
  }
}
```

意思是：

```text
App\ 开头的类，都从 src/ 目录里找
```

例如：

```text
App\Services\UserService
```

会映射到：

```text
src/Services/UserService.php
```

---

### 1.11 理解 `vendor/autoload.php`

在 Composer 项目里，通常会看到：

```php
<?php

require __DIR__ . "/vendor/autoload.php";
```

这行的作用是：

> 引入 Composer 自动加载器，让 PHP 能根据 namespace 自动找到类文件。

Node/TypeScript 里你写：

```ts
import { UserService } from "./services/UserService";
```

Node 会根据路径找到文件。

PHP 里你写：

```php
use App\Services\UserService;
```

然后 Composer 根据 PSR-4 规则找到：

```text
src/Services/UserService.php
```

前提是你已经引入：

```php
require __DIR__ . "/vendor/autoload.php";
```

---

### 1.12 理解 namespace 和 use

假设有文件：

```text
src/Services/UserService.php
```

内容：

```php
<?php

declare(strict_types=1);

namespace App\Services;

class UserService
{
    public function getName(): string
    {
        return "Tom";
    }
}
```

入口文件：

```php
<?php

declare(strict_types=1);

require __DIR__ . "/vendor/autoload.php";

use App\Services\UserService;

$service = new UserService();

echo $service->getName();
```

这里：

```php
namespace App\Services;
```

表示这个类属于 `App\Services` 命名空间。

```php
use App\Services\UserService;
```

表示我要使用这个类。

---

### 1.13 PSR-4 映射规则

假设 `composer.json` 里有：

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
```

映射关系：

| 类名 | 文件路径 |
|---|---|
| `App\User` | `src/User.php` |
| `App\Services\UserService` | `src/Services/UserService.php` |
| `App\Controllers\OrderController` | `src/Controllers/OrderController.php` |
| `App\Models\Order` | `src/Models/Order.php` |

你要会说：

> PSR-4 规定了 namespace 前缀和目录的映射关系。Composer 根据这个规则，在运行时自动加载类文件。

---

## 2. 源码阅读

- `mall-core/composer.json`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. 是否有 `require`
2. 是否有 `autoload`
3. 是否有 `psr-4`
4. namespace 前缀是什么
5. namespace 前缀映射到哪个目录

建议你在笔记里写出类似表格：

| composer.json 配置 | 含义 | Node/npm 类比 |
|---|---|---|
| `require` | 项目依赖 | `dependencies` |
| `autoload.psr-4` | 自动加载规则 | import 路径解析规则 |
| `vendor/` | 第三方依赖目录 | `node_modules/` |
| `vendor/autoload.php` | 自动加载入口 | Node 模块解析器 |

---

## 3. 练习任务

### 练习 1：类型输出

写一个 `types.php`：

```php
<?php

declare(strict_types=1);

$name = "Tom";
$age = 18;
$price = 19.99;
$isVip = true;
$tags = ["new", "vip"];
$user = [
    "name" => "Tom",
    "age" => 18,
];

var_dump($name);
var_dump($age);
var_dump($price);
var_dump($isVip);
var_dump($tags);
var_dump($user);
```

运行：

```bash
php types.php
```

目标：看懂每个 `var_dump()` 输出的类型。

---

### 练习 2：strict types

写一个 `strict.php`：

```php
<?php

declare(strict_types=1);

function add(int $a, int $b): int
{
    return $a + $b;
}

echo add(1, 2) . PHP_EOL;
echo add("1", "2") . PHP_EOL;
```

运行后观察报错。

然后把：

```php
declare(strict_types=1);
```

删掉，再运行一次。

目标：理解严格模式和非严格模式的区别。

---

### 练习 3：Composer Autoload Demo

新建临时目录：

```bash
mkdir php-autoload-demo
cd php-autoload-demo
```

创建 `composer.json`：

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
```

创建目录：

```bash
mkdir -p src/Services
```

创建文件：

```text
src/Services/UserService.php
```

内容：

```php
<?php

declare(strict_types=1);

namespace App\Services;

class UserService
{
    public function getName(): string
    {
        return "Tom";
    }
}
```

创建入口文件：

```text
index.php
```

内容：

```php
<?php

declare(strict_types=1);

require __DIR__ . "/vendor/autoload.php";

use App\Services\UserService;

$userService = new UserService();

echo $userService->getName() . PHP_EOL;
```

生成自动加载文件：

```bash
composer dump-autoload
```

运行：

```bash
php index.php
```

你应该看到：

```text
Tom
```

---

### 练习 4：namespace 到文件路径映射

根据下面规则：

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
```

写出这些类对应的文件路径：

| 类名 | 文件路径 |
|---|---|
| `App\User` |  |
| `App\Services\UserService` |  |
| `App\Controllers\OrderController` |  |
| `App\Models\Order` |  |

参考答案：

| 类名 | 文件路径 |
|---|---|
| `App\User` | `src/User.php` |
| `App\Services\UserService` | `src/Services/UserService.php` |
| `App\Controllers\OrderController` | `src/Controllers/OrderController.php` |
| `App\Models\Order` | `src/Models/Order.php` |

---

### 练习 5：列 PHP 与 JS 类型差异 10 条

可以先用下面这版作为初稿：

| # | PHP | JS/TS 类比 | 差异 |
|---|---|---|---|
| 1 | 变量用 `$name` | `name` | PHP 变量必须 `$` 开头 |
| 2 | `echo` 输出 | `console.log` | `echo` 更简单，只负责输出 |
| 3 | `array` 可做数组也可做对象字典 | Array/Object | PHP 一个 `array` 覆盖两种常见用途 |
| 4 | `.` 字符串拼接 | `+` 或模板字符串 | PHP 拼接不能用 `+` |
| 5 | `null` | `null` | 类似，但 PHP 访问 null 属性通常会报错 |
| 6 | `declare(strict_types=1)` | TS strict | PHP 是运行时严格，TS 是编译期严格 |
| 7 | 函数参数类型 `int $a` | `a: number` | PHP 类型写在变量前 |
| 8 | 返回类型 `): int` | `): number` | 语法类似 |
| 9 | `?->` | `?.` | 空安全访问非常像 |
| 10 | `match` | switch/object map | PHP `match` 是表达式，且严格比较 |

---

## 4. JS/Node.js 类比

| PHP / Composer | Node.js / npm 类比 | 说明 |
|---|---|---|
| Composer | npm | PHP 依赖管理工具 |
| `composer.json` | `package.json` | 项目依赖和配置文件 |
| `vendor/` | `node_modules/` | 第三方依赖目录 |
| `composer install` | `npm install` | 安装依赖 |
| `composer dump-autoload` | 重新生成模块解析信息 | 更新 PHP 自动加载映射 |
| `vendor/autoload.php` | Node 模块解析机制 | PHP 需要显式 require 自动加载入口 |
| `namespace App\Services` | ES Module 文件/模块作用域 | PHP namespace 是语言级命名空间 |
| `use App\Services\UserService` | `import UserService from ...` | 引入类名 |
| PSR-4 | import 路径解析约定 | namespace 到文件路径的规范 |

---

## 5. AI Review 提问

完成练习后，把你的代码和理解贴给 AI，然后问：

```text
我正在学习 PHP Day 01：类型系统、strict_types、Composer autoload。

请你按资深 PHP 工程师标准帮我检查：

1. 我的 PHP 类型理解是否正确？
2. strict_types 的使用是否正确？
3. Composer autoload 和 PSR-4 的理解是否准确？
4. 我用 Node/npm/import 做的类比有没有误导？
5. 如果这是企业 PHP 项目，我还需要注意哪些规范？

请用中文输出：
- 我理解正确的地方
- 我理解错误或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [✅] PHP 类型差异笔记
- [✅] `types.php` 练习代码
- [✅] `strict.php` 练习代码
- [✅] Composer autoload demo
- [✅] namespace → 文件路径映射表
- [✅] PHP 与 JS 类型差异 10 条
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [✅] 能运行一个 PHP 文件
- [✅] 能说出 PHP 常见基础类型
- [✅] 能用 `var_dump()` 查看类型
- [✅] 能解释 `strict_types=1`
- [✅] 能说出至少 3 个 PHP 8 特性
- [✅] 能解释 Composer 和 npm 的类比
- [✅] 能解释 `vendor/autoload.php`
- [✅] 能解释 PSR-4
- [✅] 能写出 `App\Services\UserService` 对应的文件路径
- [✅] 能跑通一个 Composer autoload demo

---

## 8. 今日自测题

### 8.1 `vendor/autoload.php` 是什么？

参考答案：

> ✅ 它是 Composer 生成的自动加载入口文件。引入它之后，PHP 可以根据 composer.json 中的 PSR-4 规则，自动找到并加载类文件。

---

### 8.2 Composer 和 npm 的类比是什么？

参考答案：

```text
Composer ≈ npm
composer.json ≈ package.json
vendor/ ≈ node_modules/
composer install ≈ npm install
```

---

### 8.3 PSR-4 是什么？

参考答案：

> ✅ PSR-4 是 PHP 的自动加载规范，用来定义 namespace 前缀和目录之间的映射关系。例如 `App\` 映射到 `src/`，那么 `App\Services\UserService` 就会对应 `src/Services/UserService.php`。

---

### 8.4 `strict_types=1` 有什么用？

参考答案：

> ✅ 它让 PHP 在函数参数类型检查上更严格，减少字符串自动转数字等隐式转换导致的问题。

---

### 8.5 PHP 的 `array` 和 JS 的 Array 完全一样吗？

参考答案：

> ✅ 不完全一样。PHP 的 `array` 既可以表示 JS 的数组，也可以表示 JS 的对象字典，例如 `["name" => "Tom"]`。

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
我正在进行 Week 01 Day 01：PHP 8 类型系统与工程入口 的学习。
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
