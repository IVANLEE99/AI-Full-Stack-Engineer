# Week 01 Day 04：Trait、Exception 与企业基类

> 所属周：Week 01：PHP 8 + Composer + OOP  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-core`  
> 类型：架构理解  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

掌握 PHP 的 `Trait`、`Exception`、`try/catch/finally`，理解企业项目里为什么会有 `BaseService`、`BaseRepository` 这类「基类」，并能初步区分 Service 和 Repository 的职责。

今天你要真正掌握这一句话：

> Trait 用来横向复用代码，Exception 用来表达异常流程，BaseService/BaseRepository 用来沉淀企业项目的公共能力；它们共同解决的是「重复代码」「错误处理」「分层复用」这三个问题。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解为什么需要 Trait
2. 学会写最小 Trait
3. 理解 Trait 和继承的区别
4. 理解 Trait 和 JS Mixin / composable 的类比
5. 学会抛出异常：`throw new Exception()`
6. 学会捕获异常：`try/catch`
7. 学会 `finally` 的执行时机
8. 学会统一错误返回结构
9. 理解 Service 与 Repository 的职责差异
10. 阅读 `BaseService.php` 和 `BaseRepository.php`
11. 写 Trait 示例和异常示例
12. 完成今日自测和 AI Review

---

## 1. 学习内容

### 1.1 为什么需要 Trait？

假设你有很多类都需要写日志：

```php
class UserService
{
    public function log(string $message): void
    {
        echo "[LOG] " . $message . PHP_EOL;
    }
}

class OrderService
{
    public function log(string $message): void
    {
        echo "[LOG] " . $message . PHP_EOL;
    }
}
```

你会发现：`log()` 方法重复了。

这时可以把公共方法提取到 Trait：

```php
trait LogTrait
{
    public function log(string $message): void
    {
        echo "[LOG] " . $message . PHP_EOL;
    }
}
```

然后多个类复用：

```php
class UserService
{
    use LogTrait;
}

class OrderService
{
    use LogTrait;
}
```

Trait 的核心作用：

> 把多个类都需要的公共方法抽出来，让这些类通过 `use TraitName;` 复用。

---

### 1.2 最小 Trait 示例

创建 `trait-demo.php`：

```php
<?php

declare(strict_types=1);

trait LogTrait
{
    public function log(string $message): void
    {
        echo "[LOG] " . $message . PHP_EOL;
    }
}

class UserService
{
    use LogTrait;

    public function createUser(string $name): void
    {
        $this->log("Create user: " . $name);
    }
}

$service = new UserService();
$service->createUser("Tom");
```

运行：

```bash
php trait-demo.php
```

期望输出：

```text
[LOG] Create user: Tom
```

重点语法：

```php
use LogTrait;
```

表示把 `LogTrait` 中的方法「混入」到当前类。

---

### 1.3 Trait 和继承有什么区别？

继承：

```php
class Dog extends Animal
{
}
```

表达的是：

```text
Dog 是一种 Animal
```

Trait：

```php
class UserService
{
    use LogTrait;
}
```

表达的是：

```text
UserService 复用了 LogTrait 的日志能力
```

对比：

| 对比项 | extends 继承 | Trait |
|---|---|---|
| 关系 | is-a，是一种 | has ability，有某种能力 |
| 数量 | PHP 只能单继承 | 一个类可以 use 多个 Trait |
| 用途 | 建立父子类层次 | 复用横切能力 |
| 例子 | `Dog extends Animal` | `use LogTrait` |

小白记法：

> 继承解决「这个类属于哪一类」；Trait 解决「这个类也想拥有某个公共能力」。

---

### 1.4 Trait 和 JS Mixin / composable 类比

JS 里可以这样复用方法：

```js
const logMixin = {
  log(message) {
    console.log('[LOG]', message);
  }
};

class UserService {}
Object.assign(UserService.prototype, logMixin);

const service = new UserService();
service.log('Create user');
```

Vue 3 / Composition API 里更常见的是：

```js
function useLogger() {
  function log(message) {
    console.log('[LOG]', message);
  }

  return { log };
}
```

PHP Trait 类比：

```php
trait LogTrait
{
    public function log(string $message): void
    {
        echo $message;
    }
}
```

| PHP Trait | JS 类比 | 差异 |
|---|---|---|
| `trait LogTrait` | mixin object / composable | PHP 是语言级语法 |
| `use LogTrait;` | `Object.assign()` / 调用 composable | Trait 编译到类里 |
| Trait 方法 | mixin 方法 | 命名冲突时 PHP 需要显式解决 |

---

### 1.5 多个 Trait

一个类可以使用多个 Trait：

```php
<?php

declare(strict_types=1);

trait LogTrait
{
    public function log(string $message): void
    {
        echo "[LOG] " . $message . PHP_EOL;
    }
}

trait TimeTrait
{
    public function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

class ReportService
{
    use LogTrait;
    use TimeTrait;

    public function generate(): void
    {
        $this->log("Generate report at " . $this->now());
    }
}

$service = new ReportService();
$service->generate();
```

你可以理解为：

```text
ReportService 同时拥有日志能力和时间能力
```

---

### 1.6 Trait 命名冲突怎么办？

如果两个 Trait 里都有同名方法，PHP 会冲突。

```php
trait A
{
    public function hello(): string
    {
        return "A";
    }
}

trait B
{
    public function hello(): string
    {
        return "B";
    }
}
```

如果一个类同时 `use A, B`，需要明确选择：

```php
class Demo
{
    use A, B {
        A::hello insteadof B;
        B::hello as helloFromB;
    }
}
```

小白阶段只需要知道：

> Trait 很方便，但多个 Trait 有同名方法时会冲突，需要谨慎使用。

---

### 1.7 什么是 Exception？

Exception 是异常。

当代码遇到无法正常处理的情况时，可以抛出异常：

```php
throw new Exception("Something wrong");
```

例如：

```php
<?php

declare(strict_types=1);

function divide(int $a, int $b): float
{
    if ($b === 0) {
        throw new Exception("除数不能为 0");
    }

    return $a / $b;
}

echo divide(10, 2);
echo divide(10, 0);
```

第二次调用会抛异常。

---

### 1.8 try/catch 捕获异常

如果不想让程序直接崩掉，可以用 `try/catch`：

```php
<?php

declare(strict_types=1);

function divide(int $a, int $b): float
{
    if ($b === 0) {
        throw new Exception("除数不能为 0");
    }

    return $a / $b;
}

try {
    echo divide(10, 0);
} catch (Exception $e) {
    echo "捕获到异常：" . $e->getMessage() . PHP_EOL;
}
```

输出：

```text
捕获到异常：除数不能为 0
```

关键语法：

| 语法 | 含义 |
|---|---|
| `try {}` | 尝试执行可能出错的代码 |
| `throw new Exception()` | 抛出异常 |
| `catch (Exception $e)` | 捕获异常对象 |
| `$e->getMessage()` | 获取异常消息 |

---

### 1.9 finally 是什么？

`finally` 表示无论成功还是失败，最后都会执行。

```php
<?php

declare(strict_types=1);

try {
    echo "开始执行" . PHP_EOL;
    throw new Exception("出错了");
} catch (Exception $e) {
    echo "错误：" . $e->getMessage() . PHP_EOL;
} finally {
    echo "这里一定会执行" . PHP_EOL;
}
```

输出：

```text
开始执行
错误：出错了
这里一定会执行
```

常见用途：

- 关闭文件
- 释放资源
- 记录结束日志
- 清理临时状态

---

### 1.10 Exception 和 JS throw Error 类比

PHP：

```php
try {
    throw new Exception("failed");
} catch (Exception $e) {
    echo $e->getMessage();
}
```

JavaScript：

```js
try {
  throw new Error('failed');
} catch (e) {
  console.log(e.message);
}
```

对比：

| PHP | JS |
|---|---|
| `throw new Exception()` | `throw new Error()` |
| `catch (Exception $e)` | `catch (e)` |
| `$e->getMessage()` | `e.message` |
| `finally` | `finally` |

---

### 1.11 为什么企业项目要统一错误结构？

如果每个接口返回错误格式都不一样，前端很难处理。

坏例子：

```json
{"error": "参数错误"}
```

另一个接口：

```json
{"code": 400, "msg": "invalid"}
```

再一个接口：

```json
{"success": false, "message": "fail"}
```

前端需要写很多兼容逻辑。

更好的统一格式：

```json
{
  "code": 400,
  "message": "参数错误",
  "data": null
}
```

PHP 示例：

```php
<?php

declare(strict_types=1);

function errorResponse(int $code, string $message): array
{
    return [
        "code" => $code,
        "message" => $message,
        "data" => null,
    ];
}

try {
    throw new Exception("参数错误");
} catch (Exception $e) {
    print_r(errorResponse(400, $e->getMessage()));
}
```

---

### 1.12 Service 是什么？

Service 是业务逻辑层。

它通常负责：

- 处理业务规则
- 编排多个 Repository
- 调用外部服务
- 做状态判断
- 返回业务结果

例子：

```php
class OrderService
{
    public function createOrder(array $input): array
    {
        // 1. 校验业务条件
        // 2. 查商品库存
        // 3. 计算价格
        // 4. 创建订单
        // 5. 返回结果
        return ["code" => 0, "data" => []];
    }
}
```

小白理解：

> Service 写「业务怎么做」。

---

### 1.13 Repository 是什么？

Repository 是数据访问层。

它通常负责：

- 查数据库
- 保存数据库
- 更新数据库
- 封装复杂查询
- 隔离 ORM / SQL 细节

例子：

```php
class OrderRepository
{
    public function findByOrderNo(string $orderNo): ?array
    {
        // 真实项目里这里可能是 ORM 查询或 SQL
        return [
            "order_no" => $orderNo,
            "status" => 1,
        ];
    }
}
```

小白理解：

> Repository 写「数据怎么取」。

---

### 1.14 Service vs Repository

| 对比项 | Service | Repository |
|---|---|---|
| 中文 | 业务逻辑层 | 数据访问层 |
| 负责 | 业务规则、流程编排 | 查询、保存、更新数据 |
| 是否知道业务流程 | 知道 | 尽量不知道 |
| 是否直接处理 DB | 一般不直接 | 是 |
| Node 类比 | NestJS Service | DAO / Prisma repository |

简单记法：

```text
Controller：接请求
Service：做业务
Repository：查数据
Model：映射表
```

---

### 1.15 BaseService / BaseRepository 是什么？

企业项目里经常有：

```text
BaseService.php
BaseRepository.php
```

它们是基础类，用来放公共能力。

`BaseService` 可能包含：

- 单例 `instance()`
- 通用返回格式
- 公共工具方法
- 日志能力

`BaseRepository` 可能包含：

- 通用查询方法
- 通用保存方法
- 通用分页方法
- Model 访问封装

你可以类比前端：

| PHP | 前端/Node 类比 |
|---|---|
| `BaseService` | BaseApi / BaseStore / BaseService |
| `BaseRepository` | BaseDAO / BaseModel |
| 具体 Service | 某个业务模块 service |
| 具体 Repository | 某个表/聚合的数据访问类 |

---

## 2. 源码阅读

- `mall-core/common/BaseService.php`
- `mall-core/common/BaseRepository.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

### 2.1 阅读 `BaseService.php`

重点观察：

| 观察点 | 记录 |
|---|---|
| namespace 是什么 |  |
| class 名是什么 |  |
| 是否有 `instance()` |  |
| 是否使用 `static` |  |
| 是否有统一返回方法 |  |
| 是否有日志相关方法 |  |
| 是否有异常处理 |  |

今天不用强求完全看懂，只要先回答：

> BaseService 给所有 Service 提供了哪些公共能力？

---

### 2.2 阅读 `BaseRepository.php`

重点观察：

| 观察点 | 记录 |
|---|---|
| namespace 是什么 |  |
| class 名是什么 |  |
| 是否有查询相关方法 |  |
| 是否有保存/更新相关方法 |  |
| 是否依赖 Model / ActiveRecord |  |
| 是否有分页相关逻辑 |  |

今天只要先回答：

> BaseRepository 给所有 Repository 提供了哪些公共 DB 能力？

---

### 2.3 对比两个基类

写一张表：

| 对比项 | BaseService | BaseRepository |
|---|---|---|
| 所属层 | 业务逻辑层 | 数据访问层 |
| 主要职责 |  |  |
| 常见方法 |  |  |
| 是否应写 SQL/查询 | 一般不应该 | 可以 |
| Node 类比 | Service | DAO / Repository |

---

## 3. 练习任务

### 练习 1：写 Trait 示例

创建 `log-trait.php`：

```php
<?php

declare(strict_types=1);

trait LogTrait
{
    public function log(string $message): void
    {
        echo "[LOG] " . $message . PHP_EOL;
    }
}

class UserService
{
    use LogTrait;

    public function create(string $name): void
    {
        $this->log("Create user: " . $name);
    }
}

$service = new UserService();
$service->create("Tom");
```

运行：

```bash
php log-trait.php
```

期望输出：

```text
[LOG] Create user: Tom
```

---

### 练习 2：写 try/catch 示例

创建 `exception-demo.php`：

```php
<?php

declare(strict_types=1);

function divide(int $a, int $b): float
{
    if ($b === 0) {
        throw new Exception("除数不能为 0");
    }

    return $a / $b;
}

try {
    echo divide(10, 0) . PHP_EOL;
} catch (Exception $e) {
    echo "错误：" . $e->getMessage() . PHP_EOL;
} finally {
    echo "计算结束" . PHP_EOL;
}
```

运行：

```bash
php exception-demo.php
```

期望输出：

```text
错误：除数不能为 0
计算结束
```

---

### 练习 3：写统一错误返回

创建 `response-demo.php`：

```php
<?php

declare(strict_types=1);

function successResponse(array $data): array
{
    return [
        "code" => 0,
        "message" => "success",
        "data" => $data,
    ];
}

function errorResponse(int $code, string $message): array
{
    return [
        "code" => $code,
        "message" => $message,
        "data" => null,
    ];
}

try {
    throw new Exception("用户不存在");
} catch (Exception $e) {
    print_r(errorResponse(404, $e->getMessage()));
}
```

你要理解：

> 统一响应结构可以让前端稳定处理接口结果。

---

### 练习 4：Service vs Repository 对比

写两个简单类：

```php
<?php

declare(strict_types=1);

class UserRepository
{
    public function findById(int $id): array
    {
        return [
            "id" => $id,
            "name" => "Tom",
        ];
    }
}

class UserService
{
    public function __construct(
        private UserRepository $repository,
    ) {}

    public function getProfile(int $id): array
    {
        $user = $this->repository->findById($id);

        return [
            "id" => $user["id"],
            "display_name" => $user["name"],
        ];
    }
}

$service = new UserService(new UserRepository());

print_r($service->getProfile(1));
```

重点理解：

- Repository 负责取数据
- Service 负责加工业务返回

---

### 练习 5：阅读基类并写笔记

完成这张表：

| 问题 | 我的答案 |
|---|---|
| `BaseService` 解决什么重复问题？ |  |
| `BaseRepository` 解决什么重复问题？ |  |
| 哪些逻辑应该放 Service？ |  |
| 哪些逻辑应该放 Repository？ |  |
| Trait 适合放什么公共能力？ |  |
| Exception 在项目里如何统一处理？ |  |

---

## 4. JS/Node.js 类比

| PHP 概念 | JS/Node 类比 | 差异 |
|---|---|---|
| Trait | Mixin / composable | PHP 是语言级混入 |
| `use LogTrait;` | `Object.assign()` / 调用 composable | Trait 会成为类方法 |
| `throw new Exception()` | `throw new Error()` | 语法不同，思想类似 |
| `try/catch/finally` | `try/catch/finally` | 基本一致 |
| 统一错误返回 | `res.json({ code, message, data })` | 后端需全接口一致 |
| Service | NestJS Service / 业务层 | 负责业务流程 |
| Repository | DAO / Prisma Repository | 负责数据访问 |
| BaseService | BaseApi / BaseService | 沉淀公共业务能力 |
| BaseRepository | BaseDAO | 沉淀公共查询能力 |

---

## 5. AI Review 提问

完成练习后，把代码和笔记贴给 AI，然后问：

```text
我正在学习 PHP Trait、Exception、BaseService、BaseRepository。

请你按资深 PHP 后端工程师标准帮我检查：

1. 我的 Trait 示例是否适合这种复用场景？
2. 我的 try/catch/finally 示例是否正确？
3. 我的统一错误返回结构是否合理？
4. 我对 Service 和 Repository 的职责划分是否准确？
5. 我阅读 BaseService/BaseRepository 时还应该关注哪些点？

请用中文输出：
- 我理解正确的地方
- 我理解错误或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

今天结束前，你应该产出：

- [ ] `log-trait.php`：Trait 示例
- [ ] `exception-demo.php`：try/catch/finally 示例
- [ ] `response-demo.php`：统一响应示例
- [ ] `UserService` / `UserRepository` 分层示例
- [ ] `BaseService.php` 阅读笔记
- [ ] `BaseRepository.php` 阅读笔记
- [ ] Service vs Repository 对比表
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 Trait 解决什么问题
- [ ] 能写 `trait LogTrait`
- [ ] 能在 class 中 `use LogTrait;`
- [ ] 能解释 Trait 和继承的区别
- [ ] 能写 `throw new Exception()`
- [ ] 能写 `try/catch/finally`
- [ ] 能解释为什么要统一错误结构
- [ ] 能区分 Service 和 Repository
- [ ] 能说明 BaseService 的作用
- [ ] 能说明 BaseRepository 的作用
- [ ] 能用 JS Mixin / throw Error 类比 Trait / Exception

---

## 8. 今日自测题

### 8.1 Trait 解决什么问题？

参考答案：

> Trait 用来把多个类都需要的公共方法抽出来，让这些类通过 `use TraitName;` 复用，减少重复代码。

---

### 8.2 Trait 和继承有什么区别？

参考答案：

> 继承表达「某类是一种父类」，Trait 表达「某类拥有某种公共能力」。PHP 只能单继承，但一个类可以使用多个 Trait。

---

### 8.3 `throw new Exception()` 是什么意思？

参考答案：

> 表示抛出一个异常，告诉调用方当前流程无法正常继续，需要被 catch 或交给上层处理。

---

### 8.4 `finally` 什么时候执行？

参考答案：

> 无论 try 成功还是 catch 捕获异常，finally 最后都会执行。

---

### 8.5 为什么企业接口要统一错误返回？

参考答案：

> 因为前端和调用方需要稳定解析接口结果。如果每个接口错误格式不同，会增加大量兼容逻辑。

---

### 8.6 Service 和 Repository 的区别是什么？

参考答案：

> Service 负责业务逻辑和流程编排；Repository 负责数据库访问和查询封装。

---

### 8.7 BaseService / BaseRepository 的意义是什么？

参考答案：

> 它们是基础类，用来沉淀所有 Service 或 Repository 都需要的公共能力，减少重复代码并统一项目风格。

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
我正在进行 Week 01 Day 04：Trait、Exception 与企业基类 的学习。
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
