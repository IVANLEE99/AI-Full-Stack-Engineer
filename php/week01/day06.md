# Week 01 Day 06：Todo REST API 实战

> 所属周：Week 01：PHP 8 + Composer + OOP  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-core`  
> 类型：项目实战  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

完成一个可以运行、可以测试、结构清晰的 Todo REST API：支持列表、详情、创建、更新、删除，并写出 README 说明。

今天你要真正掌握这一句话：

> REST API 就是用 HTTP 方法表达对资源的操作；Todo 是资源，`GET/POST/PUT/DELETE` 分别对应查询、创建、更新、删除，PHP 代码要负责解析请求、调用业务逻辑、返回统一 JSON。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先复习 REST API 的资源和 HTTP 方法
2. 理解状态码：200、201、400、404、500
3. 理解 PHP 如何读取 URL、请求方法、JSON body
4. 创建 Todo API 项目结构
5. 写统一响应类 `Response`
6. 写 `TodoService` 管理内存数据
7. 写 `TodoController` 处理 CRUD
8. 写 `public/index.php` 做简易路由
9. 用 PHP 内置服务器启动项目
10. 用 curl 测试全部接口
11. 写 README
12. 用 AI Review 检查代码质量

---

## 1. 学习内容

### 1.1 REST API 再复习

REST API 的核心是「资源」。

Todo 资源可以设计成：

```text
/todos
/todos/{id}
```

常见 CRUD：

| 操作 | HTTP 方法 | 路径 | 含义 |
|---|---|---|---|
| 列表 | GET | `/todos` | 获取 Todo 列表 |
| 详情 | GET | `/todos/1` | 获取 ID=1 的 Todo |
| 创建 | POST | `/todos` | 创建 Todo |
| 更新 | PUT | `/todos/1` | 更新 ID=1 的 Todo |
| 删除 | DELETE | `/todos/1` | 删除 ID=1 的 Todo |

Node/Express 类比：

```js
router.get('/todos', listTodos);
router.get('/todos/:id', getTodo);
router.post('/todos', createTodo);
router.put('/todos/:id', updateTodo);
router.delete('/todos/:id', deleteTodo);
```

PHP 原生没有 Express router，所以今天用 `$_SERVER` 写一个最小路由。

---

### 1.2 HTTP 状态码

今天先掌握这些：

| 状态码 | 含义 | 使用场景 |
|---|---|---|
| 200 | OK | 查询、更新、删除成功 |
| 201 | Created | 创建成功 |
| 400 | Bad Request | 参数错误 |
| 404 | Not Found | 路由或资源不存在 |
| 500 | Server Error | 服务端异常 |

在 PHP 里设置状态码：

```php
http_response_code(404);
```

---

### 1.3 PHP 如何读取请求方法和路径

读取 HTTP 方法：

```php
$method = $_SERVER['REQUEST_METHOD'];
```

读取路径：

```php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
```

例如请求：

```text
GET /todos/1?foo=bar
```

`parse_url(..., PHP_URL_PATH)` 得到：

```text
/todos/1
```

---

### 1.4 PHP 如何读取 JSON body

前端 POST JSON：

```json
{
  "title": "Learn PHP"
}
```

PHP 读取：

```php
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);
```

说明：

| 代码 | 含义 |
|---|---|
| `php://input` | 原始请求 body |
| `file_get_contents()` | 读取 body 字符串 |
| `json_decode($rawBody, true)` | 转成 PHP 关联数组 |

如果 JSON 无效，`json_decode()` 可能返回 `null`。

---

### 1.5 今日项目结构

创建：

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

对应分层：

| 文件 | 职责 |
|---|---|
| `public/index.php` | 入口 + 简易路由 |
| `TodoController.php` | 接请求、取参数、返回响应 |
| `TodoService.php` | Todo 业务逻辑 |
| `Response.php` | 统一 JSON 输出 |
| `README.md` | 启动和测试说明 |

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议回看：

- `week01/day03.md`：namespace / Composer autoload
- `week01/day05.md`：PSR-12 / Todo API 骨架

今天要把 Day 05 的骨架扩展成完整 CRUD。

---

## 3. 练习任务

### 练习 1：创建项目和 composer.json

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

### 练习 2：写统一响应类

`src/Support/Response.php`：

```php
<?php

declare(strict_types=1);

namespace App\Support;

class Response
{
    public static function json(int $httpStatus, int $code, string $message, mixed $data = null): void
    {
        http_response_code($httpStatus);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function success(mixed $data = null, int $httpStatus = 200): void
    {
        self::json($httpStatus, 0, 'success', $data);
    }

    public static function error(int $httpStatus, string $message, int $code = 1): void
    {
        self::json($httpStatus, $code, $message, null);
    }
}
```

---

### 练习 3：写 TodoService

今天先用静态数组模拟数据库。

`src/Services/TodoService.php`：

```php
<?php

declare(strict_types=1);

namespace App\Services;

class TodoService
{
    private array $todos = [
        1 => ['id' => 1, 'title' => 'Learn PHP', 'done' => false],
        2 => ['id' => 2, 'title' => 'Learn Composer', 'done' => false],
    ];

    public function list(): array
    {
        return array_values($this->todos);
    }

    public function find(int $id): ?array
    {
        return $this->todos[$id] ?? null;
    }

    public function create(string $title): array
    {
        $id = max(array_keys($this->todos)) + 1;

        $todo = [
            'id' => $id,
            'title' => $title,
            'done' => false,
        ];

        $this->todos[$id] = $todo;

        return $todo;
    }

    public function update(int $id, string $title, bool $done): ?array
    {
        if (!isset($this->todos[$id])) {
            return null;
        }

        $this->todos[$id]['title'] = $title;
        $this->todos[$id]['done'] = $done;

        return $this->todos[$id];
    }

    public function delete(int $id): bool
    {
        if (!isset($this->todos[$id])) {
            return false;
        }

        unset($this->todos[$id]);

        return true;
    }
}
```

注意：这个版本的数据只存在单次请求中。因为每次 HTTP 请求都会重新创建对象，所以它不是持久化数据库。今天重点是练 REST 流程，不练数据库。

---

### 练习 4：写 TodoController

`src/Controllers/TodoController.php`：

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

    public function show(int $id): void
    {
        $todo = $this->todoService->find($id);

        if ($todo === null) {
            Response::error(404, 'Todo not found');
            return;
        }

        Response::success($todo);
    }

    public function store(array $payload): void
    {
        $title = trim((string)($payload['title'] ?? ''));

        if ($title === '') {
            Response::error(400, 'title is required');
            return;
        }

        Response::success($this->todoService->create($title), 201);
    }

    public function update(int $id, array $payload): void
    {
        $title = trim((string)($payload['title'] ?? ''));
        $done = (bool)($payload['done'] ?? false);

        if ($title === '') {
            Response::error(400, 'title is required');
            return;
        }

        $todo = $this->todoService->update($id, $title, $done);

        if ($todo === null) {
            Response::error(404, 'Todo not found');
            return;
        }

        Response::success($todo);
    }

    public function destroy(int $id): void
    {
        if (!$this->todoService->delete($id)) {
            Response::error(404, 'Todo not found');
            return;
        }

        Response::success(['deleted' => true]);
    }
}
```

---

### 练习 5：写入口和简易路由

`public/index.php`：

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\TodoController;
use App\Services\TodoService;
use App\Support\Response;

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$payload = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];

$controller = new TodoController(new TodoService());

if ($method === 'GET' && $path === '/todos') {
    $controller->index();
    return;
}

if ($method === 'POST' && $path === '/todos') {
    $controller->store($payload);
    return;
}

if (preg_match('#^/todos/(\d+)$#', $path, $matches)) {
    $id = (int)$matches[1];

    if ($method === 'GET') {
        $controller->show($id);
        return;
    }

    if ($method === 'PUT') {
        $controller->update($id, $payload);
        return;
    }

    if ($method === 'DELETE') {
        $controller->destroy($id);
        return;
    }
}

Response::error(404, 'Route not found');
```

启动：

```bash
php -S localhost:8000 -t public
```

---

### 练习 6：curl 测试全部接口

列表：

```bash
curl http://localhost:8000/todos
```

详情：

```bash
curl http://localhost:8000/todos/1
```

创建：

```bash
curl -X POST http://localhost:8000/todos \
  -H 'Content-Type: application/json' \
  -d '{"title":"Write README"}'
```

更新：

```bash
curl -X PUT http://localhost:8000/todos/1 \
  -H 'Content-Type: application/json' \
  -d '{"title":"Learn PHP deeply","done":true}'
```

删除：

```bash
curl -X DELETE http://localhost:8000/todos/1
```

错误测试：

```bash
curl http://localhost:8000/not-found
```

```bash
curl -X POST http://localhost:8000/todos \
  -H 'Content-Type: application/json' \
  -d '{}'
```

---

### 练习 7：写 README

`README.md` 至少写：

```markdown
# Todo API

## Start

```bash
composer dump-autoload
php -S localhost:8000 -t public
```

## APIs

| Method | Path | Description |
|---|---|---|
| GET | /todos | list todos |
| GET | /todos/{id} | show todo |
| POST | /todos | create todo |
| PUT | /todos/{id} | update todo |
| DELETE | /todos/{id} | delete todo |
```

---

## 4. JS/Node.js 类比

| PHP Todo API | Express 类比 | 说明 |
|---|---|---|
| `public/index.php` | `server.js` | 应用入口 |
| `$_SERVER['REQUEST_METHOD']` | `req.method` | 请求方法 |
| `parse_url($_SERVER['REQUEST_URI'])` | `req.path` | 请求路径 |
| `php://input` | `req.body` | 请求体 |
| `TodoController` | route handler/controller | 处理请求 |
| `TodoService` | service layer | 业务逻辑 |
| `Response::success()` | `res.json()` | 输出 JSON |
| `http_response_code()` | `res.status()` | 设置状态码 |

---

## 5. AI Review 提问

```text
我完成了一个 PHP 原生 Todo REST API，包含 GET/POST/PUT/DELETE、统一 JSON 响应、Controller/Service 分层和 README。

请你按资深 PHP 后端工程师标准帮我检查：

1. REST 路由设计是否合理？
2. Controller 和 Service 职责是否清晰？
3. 统一 JSON 响应是否合理？
4. 参数校验和错误状态码是否有明显问题？
5. 如果要从练习代码升级到企业项目，下一步应该改什么？

请用中文输出：问题清单、修改建议、下一步练习。
```

---

## 6. 今日产出

- [ ] 可运行 Todo API
- [ ] `GET /todos` 测试记录
- [ ] `GET /todos/{id}` 测试记录
- [ ] `POST /todos` 测试记录
- [ ] `PUT /todos/{id}` 测试记录
- [ ] `DELETE /todos/{id}` 测试记录
- [ ] 错误接口测试记录
- [ ] README
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 REST 的资源和 HTTP 方法
- [ ] 能说出 200 / 201 / 400 / 404 的含义
- [ ] 能读取 `$_SERVER['REQUEST_METHOD']`
- [ ] 能读取 JSON body
- [ ] 能写统一 JSON 响应
- [ ] 能写简单路由分发
- [ ] 5 个接口可用
- [ ] 错误有状态码
- [ ] README 可运行

---

## 8. 今日自测题

### 8.1 `GET /todos` 表示什么？

参考答案：获取 Todo 列表。

### 8.2 `POST /todos` 表示什么？

参考答案：创建一个新的 Todo。

### 8.3 PHP 里如何读取请求方法？

参考答案：

```php
$_SERVER['REQUEST_METHOD']
```

### 8.4 PHP 里如何读取 JSON body？

参考答案：

```php
$payload = json_decode(file_get_contents('php://input'), true);
```

### 8.5 `201` 状态码适合什么场景？

参考答案：资源创建成功。

### 8.6 Controller 和 Service 怎么分工？

参考答案：Controller 接请求和返回响应；Service 处理业务逻辑。

### 8.7 这个练习版 Todo API 有什么局限？

参考答案：数据没有持久化，每次请求都会重新初始化；路由很简陋；参数校验很基础；没有测试框架和异常统一处理。

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
我正在进行 Week 01 Day 06：Todo REST API 实战 的学习。
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
