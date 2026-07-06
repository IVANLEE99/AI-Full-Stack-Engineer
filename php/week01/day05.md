# Week 01 Day 05：PSR-12 与类比日

> 所属周：Week 01：PHP 8 + Composer + OOP  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-core`  
> 类型：类比日  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

速读 PSR-12 编码规范，理解 PHP 项目为什么强调统一代码风格；同时启动 Todo REST API 项目骨架，并完成本周的 PHP ↔ JS/Node.js 类比打卡。

今天你要真正掌握这一句话：

> PSR-12 不是为了“好看”，而是为了让团队里的 PHP 代码在文件结构、namespace、class、method、缩进和换行上保持一致；REST API 则是后端对前端暴露资源操作能力的通用方式。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解为什么需要编码规范
2. 理解 PSR 是什么
3. 重点掌握 PSR-12 中最常见的格式要求
4. 对比 PHP 代码风格和 JS/TS/ESLint/Prettier
5. 理解 REST API 是什么
6. 理解资源、HTTP 方法、路径、JSON 响应
7. 创建 Todo API 项目骨架
8. 设计统一 JSON 响应结构
9. 完成本周 JS 类比打卡
10. 用 AI Review 检查目录结构和类比是否准确

---

## 1. 学习内容

### 1.1 为什么需要编码规范？

如果团队里每个人写 PHP 风格都不一样，代码会很难读。

比如有人这样写：

```php
<?php
class User{public function getName(){return "Tom";}}
```

有人这样写：

```php
<?php

class User
{
    public function getName()
    {
        return "Tom";
    }
}
```

功能一样，但风格不同，长期维护会很痛苦。

编码规范的作用是：

- 统一格式
- 降低阅读成本
- 降低团队协作成本
- 减少无意义的代码风格争论
- 方便自动化工具检查和格式化

前端类比：

```text
PSR-12 ≈ ESLint + Prettier + 团队代码规范
```

---

### 1.2 PSR 是什么？

PSR 是 PHP Standards Recommendation，中文可以理解为 PHP 标准建议。

它由 PHP-FIG 社区制定，用来规范 PHP 项目的通用写法。

今天重点只看 PSR-12。

你可以先记住：

| 规范 | 作用 |
|---|---|
| PSR-4 | 自动加载规范，namespace 映射文件路径 |
| PSR-12 | 编码风格规范，规定代码怎么排版 |

前几天你学过 PSR-4，今天学 PSR-12。

---

### 1.3 PSR-12 文件开头规范

现代 PHP 文件推荐这样开头：

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;

class UserService
{
}
```

常见顺序：

1. `<?php`
2. 空行
3. `declare(strict_types=1);`
4. 空行
5. `namespace ...;`
6. 空行
7. `use ...;`
8. 空行
9. `class ...`

你要避免把顺序写乱。

---

### 1.4 PHP 文件通常不写结束标签

如果整个文件都是 PHP 代码，通常不写：

```php
?>
```

原因是：结束标签后如果不小心多了空格或换行，可能会导致额外输出，影响响应头、JSON 输出等。

推荐：

```php
<?php

declare(strict_types=1);

class User
{
}
```

不推荐：

```php
<?php

class User
{
}
?>
```

---

### 1.5 class 大括号格式

PSR-12 推荐 class 左大括号换行：

```php
class UserService
{
    public function getName(): string
    {
        return "Tom";
    }
}
```

不要写成：

```php
class UserService {
    public function getName(): string {
        return "Tom";
    }
}
```

这和很多 JS 风格不同。

JS 常见：

```js
class UserService {
  getName() {
    return 'Tom';
  }
}
```

PHP PSR-12 常见：

```php
class UserService
{
    public function getName(): string
    {
        return "Tom";
    }
}
```

---

### 1.6 方法格式

推荐：

```php
public function getUserName(int $id): string
{
    return "User#" . $id;
}
```

重点：

- 方法要写可见性：`public` / `protected` / `private`
- 参数尽量写类型
- 返回值尽量写类型
- 方法体使用 4 个空格缩进

不推荐：

```php
function getUserName($id){return "User#".$id;}
```

---

### 1.7 缩进和换行

PHP 常用 4 个空格缩进：

```php
if ($age >= 18) {
    echo "adult";
} else {
    echo "child";
}
```

数组也要保持清晰：

```php
$user = [
    "id" => 1,
    "name" => "Tom",
    "age" => 18,
];
```

这种写法比一行塞满更适合企业项目维护。

---

### 1.8 命名习惯

常见命名习惯：

| 类型 | PHP 常见写法 | 示例 |
|---|---|---|
| class | PascalCase | `UserService` |
| method | camelCase | `getUserName()` |
| variable | camelCase 或 snake_case，按项目规范 | `$userName` / `$user_name` |
| constant | UPPER_SNAKE_CASE | `STATUS_PAID` |
| interface | 通常带 Interface 后缀 | `PaymentInterface` |
| trait | 通常带 Trait 后缀 | `LogTrait` |

注意：真实老项目里可能会有历史风格，学习时先观察项目现有风格，不要强行改全项目。

---

### 1.9 REST API 是什么？

REST API 可以先理解为：

> 前端通过 HTTP 请求操作后端资源。

比如 Todo 是一种资源：

```text
/todos
```

对 Todo 做增删改查：

| 操作 | HTTP 方法 | 路径 | 含义 |
|---|---|---|---|
| 列表 | GET | `/todos` | 获取 Todo 列表 |
| 详情 | GET | `/todos/1` | 获取 ID=1 的 Todo |
| 创建 | POST | `/todos` | 创建 Todo |
| 更新 | PUT/PATCH | `/todos/1` | 更新 ID=1 的 Todo |
| 删除 | DELETE | `/todos/1` | 删除 ID=1 的 Todo |

前端类比 Express：

```js
router.get('/todos', listTodos);
router.post('/todos', createTodo);
router.put('/todos/:id', updateTodo);
router.delete('/todos/:id', deleteTodo);
```

PHP 原生也可以根据 `$_SERVER['REQUEST_METHOD']` 和 URL path 来分发。

---

### 1.10 统一 JSON 响应

后端 API 不应该每个接口返回不同格式。

推荐统一结构：

```json
{
  "code": 0,
  "message": "success",
  "data": {}
}
```

错误响应：

```json
{
  "code": 400,
  "message": "参数错误",
  "data": null
}
```

PHP 示例：

```php
function jsonResponse(int $code, string $message, mixed $data = null): void
{
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        "code" => $code,
        "message" => $message,
        "data" => $data,
    ], JSON_UNESCAPED_UNICODE);
}
```

`JSON_UNESCAPED_UNICODE` 的作用是让中文不要被转义成 `\uXXXX`。

---

### 1.11 Todo API 骨架先做什么？

今天不是要完整做完所有接口，而是先搭骨架。

建议目录：

```text
todo-api/
├── composer.json
├── public/
│   └── index.php
├── src/
│   ├── Controllers/
│   │   └── TodoController.php
│   ├── Services/
│   │   └── TodoService.php
│   └── Support/
│       └── Response.php
└── README.md
```

对应 namespace：

```text
App\Controllers\TodoController => src/Controllers/TodoController.php
App\Services\TodoService       => src/Services/TodoService.php
App\Support\Response           => src/Support/Response.php
```

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

但你可以回看：

- `week01/day01.md`：Composer、autoload、PSR-4
- `week01/day02.md`：class、interface、abstract
- `week01/day03.md`：namespace、use、PSR-4 映射
- `week01/day04.md`：Trait、Exception、Service/Repository

今天的重点是把前 4 天学过的东西组织成一个小项目骨架。

---

## 3. 练习任务

### 练习 1：创建 Todo API 项目骨架

创建目录：

```bash
mkdir todo-api
cd todo-api
mkdir -p public src/Controllers src/Services src/Support
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

生成 autoload：

```bash
composer dump-autoload
```

---

### 练习 2：创建统一响应类

创建文件：

```text
src/Support/Response.php
```

内容：

```php
<?php

declare(strict_types=1);

namespace App\Support;

class Response
{
    public static function json(int $code, string $message, mixed $data = null): void
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            "code" => $code,
            "message" => $message,
            "data" => $data,
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function success(mixed $data = null): void
    {
        self::json(0, "success", $data);
    }

    public static function error(int $code, string $message): void
    {
        self::json($code, $message, null);
    }
}
```

重点理解：

- `Response` 是工具类
- `success()` 统一成功响应
- `error()` 统一错误响应
- `json()` 统一输出 JSON

---

### 练习 3：创建 TodoService

创建文件：

```text
src/Services/TodoService.php
```

内容：

```php
<?php

declare(strict_types=1);

namespace App\Services;

class TodoService
{
    public function list(): array
    {
        return [
            ["id" => 1, "title" => "Learn PHP", "done" => false],
            ["id" => 2, "title" => "Learn Composer", "done" => false],
        ];
    }
}
```

今天先用内存假数据，不接数据库。

---

### 练习 4：创建 TodoController

创建文件：

```text
src/Controllers/TodoController.php
```

内容：

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\TodoService;
use App\Support\Response;

class TodoController
{
    public function __construct(
        private TodoService $todoService,
    ) {}

    public function index(): void
    {
        Response::success($this->todoService->list());
    }
}
```

重点理解：

- Controller 接请求
- Service 做业务
- Response 统一输出

---

### 练习 5：创建入口文件

创建文件：

```text
public/index.php
```

内容：

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\TodoController;
use App\Services\TodoService;
use App\Support\Response;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && $path === '/todos') {
    $controller = new TodoController(new TodoService());
    $controller->index();
    return;
}

Response::error(404, 'Not Found');
```

启动 PHP 内置服务器：

```bash
php -S localhost:8000 -t public
```

另开一个终端测试：

```bash
curl http://localhost:8000/todos
```

期望输出类似：

```json
{"code":0,"message":"success","data":[{"id":1,"title":"Learn PHP","done":false},{"id":2,"title":"Learn Composer","done":false}]}
```

---

### 练习 6：完成本周类比打卡

填写：

```text
本周概念：Composer + namespace + OOP + Trait
Node 等价：npm + import/export + class + mixin/composable
差异：PHP namespace 是语言级命名，Composer 用 PSR-4 做自动加载；PHP interface/abstract 在运行时仍有约束意义
我能用自己的话解释吗：是 / 否
理解自评：1 / 2 / 3 / 4 / 5
```

---

## 4. JS/Node.js 类比

| PHP 今日概念 | Node/JS 类比 | 差异 |
|---|---|---|
| PSR-12 | ESLint + Prettier + 团队规范 | PSR-12 是 PHP 社区通用规范 |
| class 大括号换行 | JS class 通常同行 | PHP 风格更偏后端传统 |
| `composer.json` autoload | package/import 解析 | Composer 生成 autoload 文件 |
| REST API | Express router | 思想一致，语法不同 |
| `public/index.php` | `server.js` / app entry | PHP 内置服务器从入口脚本处理请求 |
| Controller | Express route handler / NestJS Controller | 负责接请求和返回响应 |
| Service | NestJS Service | 负责业务逻辑 |
| Response 工具类 | `res.json()` helper | 统一 JSON 输出 |

---

## 5. AI Review 提问

完成项目骨架后，把目录结构和关键代码贴给 AI，然后问：

```text
我正在学习 PHP PSR-12 和 REST API 项目骨架。

这是我的 Todo API 目录结构和代码。
请你按资深 PHP 后端工程师标准帮我检查：

1. 我的目录结构是否符合 PSR-4 和基础分层习惯？
2. 我的 PHP 代码是否基本符合 PSR-12？
3. Controller / Service / Response 的职责划分是否合理？
4. 我的 REST API 路由设计是否清晰？
5. 如果要继续扩展 CRUD，下一步应该怎么做？

请用中文输出：
- 问题清单
- 修改建议
- 更好的目录结构建议
- 下一步练习任务
```

---

## 6. 今日产出

今天结束前，你应该产出：

- [ ] `todo-api/` 项目骨架
- [ ] `composer.json`
- [ ] `src/Support/Response.php`
- [ ] `src/Services/TodoService.php`
- [ ] `src/Controllers/TodoController.php`
- [ ] `public/index.php`
- [ ] `/todos` 接口 curl 测试结果
- [ ] 本周 JS 类比打卡
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 PSR-12 解决什么问题
- [ ] 能写出 PSR-12 风格的 class/method 基本格式
- [ ] 能说明 PHP 文件为什么通常不写结束标签
- [ ] 能解释 REST API 的基本思想
- [ ] 能说出 GET/POST/PUT/DELETE 分别对应什么操作
- [ ] 能创建 Todo API 基础目录结构
- [ ] 能写统一 JSON 响应类
- [ ] 能跑通 `GET /todos`
- [ ] 能完成本周 JS 类比打卡

---

## 8. 今日自测题

### 8.1 PSR-12 是什么？

参考答案：

> PSR-12 是 PHP 编码风格规范，用来统一文件结构、namespace、class、method、缩进和换行等代码风格。

---

### 8.2 PSR-12 和 PSR-4 有什么区别？

参考答案：

> PSR-12 规范代码格式；PSR-4 规范 namespace 到文件路径的自动加载映射。

---

### 8.3 为什么 PHP 文件通常不写 `?>`？

参考答案：

> 为了避免结束标签后多余空格或换行导致意外输出，影响响应头或 JSON 输出。

---

### 8.4 REST API 中 `GET /todos` 表示什么？

参考答案：

> 表示获取 Todo 列表。

---

### 8.5 `POST /todos` 表示什么？

参考答案：

> 表示创建一个新的 Todo。

---

### 8.6 为什么要统一 JSON 响应结构？

参考答案：

> 统一响应结构可以让前端稳定解析接口结果，减少不同接口返回格式不一致带来的适配成本。

---

### 8.7 Controller 和 Service 的区别是什么？

参考答案：

> Controller 负责接收请求、调用 Service、返回响应；Service 负责业务逻辑。

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
我正在进行 Week 01 Day 05：PSR-12 与类比日 的学习。
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
