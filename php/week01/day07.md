# Week 01 Day 07：验收与预习

> 所属周：Week 01：PHP 8 + Composer + OOP  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-core`  
> 类型：复盘预习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

完成 Week 01 的整体验收、自评和查漏补缺，确认自己已经掌握 PHP 基础语法、Composer、PSR-4、OOP、Trait、Exception、PSR-12 和 Todo REST API 的最小实战流程；同时预习 Week 02 的 Yii2 生命周期。

今天你要真正掌握这一句话：

> 学习不是看完就结束，而是要能用自己的话解释、能写出最小代码、能跑通接口、能说出 PHP 与 Node 的类比和差异；Week 01 的目标是建立 PHP 工程心智模型。

---

## 0. 今日学习路线

建议按下面顺序复盘：

1. 回顾 Day 01：PHP 类型、`strict_types`、Composer autoload
2. 回顾 Day 02：OOP、继承、多态、interface、abstract
3. 回顾 Day 03：namespace、use、PSR-4
4. 回顾 Day 04：Trait、Exception、BaseService/BaseRepository
5. 回顾 Day 05：PSR-12、REST API 骨架
6. 回顾 Day 06：Todo REST API CRUD
7. 按 checklist 做 Week 01 验收
8. 写一页周总结
9. 列出 3 个还没搞懂的问题
10. 预习 Yii2 入口、Module、Controller、Filter
11. 把总结和问题交给 AI Review

---

## 1. 学习内容

### 1.1 为什么要做周验收？

如果只是每天看文档，很容易产生错觉：

```text
我好像都看懂了
```

但真正掌握要满足 3 个条件：

1. 能用自己的话解释
2. 能写出最小示例
3. 能在项目里找到对应代码

例如你说自己懂 PSR-4，就应该能回答：

```text
App\Services\UserService 对应哪个文件？
为什么需要 composer dump-autoload？
vendor/autoload.php 做了什么？
```

如果答不上来，就说明还需要补。

---

### 1.2 Day 01 复盘：PHP 类型与 Composer

你应该能解释这些概念：

| 概念 | 你是否能解释 |
|---|---|
| PHP 变量为什么有 `$` |  |
| `string` / `int` / `float` / `bool` / `array` / `null` |  |
| `var_dump()` 的作用 |  |
| `strict_types=1` 的作用 |  |
| `composer.json` 是什么 |  |
| `vendor/` 是什么 |  |
| `vendor/autoload.php` 是什么 |  |
| PSR-4 是什么 |  |

最低要求：

> 你要能说出 Composer≈npm，vendor≈node_modules，composer.json≈package.json，PSR-4≈namespace 到路径的映射规则。

---

### 1.3 Day 02 复盘：OOP

你应该能解释：

| 概念 | 你是否能解释 |
|---|---|
| class |  |
| object |  |
| `$this->name` |  |
| `__construct` |  |
| `public` / `protected` / `private` |  |
| `extends` |  |
| 多态 |  |
| `interface` |  |
| `abstract class` |  |
| 单例模式 |  |

最低要求：

> 你要能写出 Animal / Dog / Cat 继承和多态示例，也能写出 PaymentInterface + StripePayment + PaypalPayment 示例。

---

### 1.4 Day 03 复盘：namespace 与 PSR-4

你应该能完成下面映射：

配置：

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
```

填写：

| 类名 | 文件路径 |
|---|---|
| `App\User` | `src/User.php` |
| `App\Services\UserService` | `src/Services/UserService.php` |
| `App\Repositories\UserRepository` | `src/Repositories/UserRepository.php` |
| `App\Controllers\OrderController` | `src/Controllers/OrderController.php` |

最低要求：

> 看到一个完整类名，你能推导文件路径；看到一个文件路径，你能反推 namespace。

---

### 1.5 Day 04 复盘：Trait、Exception、基类

你应该能解释：

| 概念 | 你是否能解释 |
|---|---|
| Trait 解决什么问题 |  |
| Trait 和继承的区别 |  |
| `throw new Exception()` |  |
| `try/catch/finally` |  |
| 统一错误返回 |  |
| Service 是什么 |  |
| Repository 是什么 |  |
| BaseService 的作用 |  |
| BaseRepository 的作用 |  |

最低要求：

> 你要能说出：Service 写业务逻辑，Repository 写数据访问，Trait 复用横切能力，Exception 表达异常流程。

---

### 1.6 Day 05 复盘：PSR-12 与 REST 骨架

你应该能解释：

| 概念 | 你是否能解释 |
|---|---|
| PSR-12 是什么 |  |
| PSR-12 和 PSR-4 的区别 |  |
| PHP 文件为什么不写 `?>` |  |
| class/method 大括号格式 |  |
| REST API 是什么 |  |
| GET / POST / PUT / DELETE |  |
| 统一 JSON 响应 |  |
| Controller / Service / Response 分层 |  |

最低要求：

> 你要能搭出 Todo API 的目录骨架，并解释每个目录的职责。

---

### 1.7 Day 06 复盘：Todo REST API CRUD

你应该能完成这些接口测试：

| 接口 | 是否跑通 |
|---|---|
| `GET /todos` |  |
| `GET /todos/1` |  |
| `POST /todos` |  |
| `PUT /todos/1` |  |
| `DELETE /todos/1` |  |
| `GET /not-found` |  |
| `POST /todos` 空参数错误 |  |

最低要求：

> 你要能启动 PHP 内置服务器，并用 curl 测试至少 5 个接口。

---

### 1.8 Week 02 预习：Yii2 是什么？

Yii2 是一个 PHP Web 框架。

你可以先用前端/Node 类比：

| Yii2 | Node/Express/Nest 类比 |
|---|---|
| `web/index.php` | `server.js` / 应用入口 |
| Application | Express app / Nest application |
| Module | Router module / Nest module |
| Controller | Controller / route handler |
| action | handler function |
| behaviors / Filter | middleware / guard |
| Form Model | Zod/Joi schema / DTO validation |

Week 02 会重点学习：

1. 请求从 `index.php` 进入
2. Yii2 加载配置
3. 根据 URL 找到 Module
4. 根据 Controller 和 action 执行方法
5. 在 action 前经过 Filter / behaviors
6. Form 做参数校验

你今天只需要先建立大图：

```text
请求 → index.php → Application → Module → Controller → action → Response
```

---

## 2. 源码阅读

本日无指定新源码阅读，重点复盘本周内容。

建议回看：

- `mall-core/composer.json`
- `mall-core/common/BaseService.php`
- `mall-core/common/BaseRepository.php`

预习 Week 02 时可先浏览：

- `mall-gateway/frontapi/web/index.php`
- `mall-gateway/frontapi/config/modules/Modules.php`
- `mall-gateway/frontapi/modules/AuthApiController.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 3. 练习任务

### 练习 1：逐项验收 PHP 基础

完成下面 checklist：

- [ ] 我能运行 `php xxx.php`
- [ ] 我能解释 `declare(strict_types=1)`
- [ ] 我能解释 PHP `array` 和 JS Array/Object 的区别
- [ ] 我能解释 `composer.json`
- [ ] 我能解释 `vendor/autoload.php`
- [ ] 我能解释 PSR-4

如果有任意一项不确定，回看 Day 01。

---

### 练习 2：逐项验收 OOP

完成下面 checklist：

- [ ] 我能写一个 class
- [ ] 我能写构造函数
- [ ] 我能解释 `$this->name`
- [ ] 我能写继承
- [ ] 我能解释多态
- [ ] 我能写 interface
- [ ] 我能写 abstract class
- [ ] 我能解释单例模式

如果有任意一项不确定，回看 Day 02。

---

### 练习 3：逐项验收工程化

完成下面 checklist：

- [ ] 我能写 namespace
- [ ] 我能写 use
- [ ] 我能从类名推导文件路径
- [ ] 我能解释 Trait
- [ ] 我能写 try/catch/finally
- [ ] 我能区分 Service 和 Repository
- [ ] 我能说出 PSR-12 的作用

如果有任意一项不确定，回看 Day 03-Day 05。

---

### 练习 4：Todo API 最终验收

启动项目：

```bash
cd todo-api
php -S localhost:8000 -t public
```

另开终端执行：

```bash
curl http://localhost:8000/todos
curl http://localhost:8000/todos/1
curl -X POST http://localhost:8000/todos -H 'Content-Type: application/json' -d '{"title":"Write README"}'
curl -X PUT http://localhost:8000/todos/1 -H 'Content-Type: application/json' -d '{"title":"Learn PHP deeply","done":true}'
curl -X DELETE http://localhost:8000/todos/1
curl http://localhost:8000/not-found
```

记录结果：

| 测试项 | 结果 | 是否通过 |
|---|---|---|
| GET /todos |  |  |
| GET /todos/1 |  |  |
| POST /todos |  |  |
| PUT /todos/1 |  |  |
| DELETE /todos/1 |  |  |
| 404 |  |  |

---

### 练习 5：写 Week 01 周总结

用下面模板：

```markdown
# Week 01 周总结

## 本周我学会了什么

1. 
2. 
3. 

## PHP 和 Node 最像的地方


## PHP 和 Node 最大的差异


## 我最清楚的概念


## 我最不清楚的概念


## Todo API 是否跑通


## 下周学习 Yii2 前，我最想问的 3 个问题

1. 
2. 
3. 
```

---

### 练习 6：预习 Yii2 请求链路

先不用深入，只画一个简图：

```text
浏览器/前端请求
  ↓
frontapi/web/index.php
  ↓
Yii Application
  ↓
Module
  ↓
Controller
  ↓
actionXxx()
  ↓
Response
```

然后写出你现在的问题：

```text
1. index.php 具体做了什么？
2. Module 是怎么匹配 URL 的？
3. behaviors / Filter 和 Express middleware 到底哪里一样哪里不一样？
```

---

## 4. JS/Node.js 类比

| Week 01 PHP 概念 | Node/JS 类比 | 你需要记住的差异 |
|---|---|---|
| PHP runtime | Node.js runtime | PHP 常用于请求生命周期内执行 |
| Composer | npm/pnpm | Composer 还承担 autoload 生成 |
| `composer.json` | `package.json` | PHP autoload 配置很关键 |
| `vendor/` | `node_modules/` | 第三方依赖目录 |
| namespace | ES Module / 路径分层 | PHP 是语言级命名空间 |
| `use` | import | PHP use 是类名别名 |
| PSR-4 | import 路径规则 | namespace 映射文件路径 |
| class | ES6 class | PHP 类型和可见性更强 |
| interface | TS interface | PHP interface 运行时存在 |
| Trait | mixin / composable | PHP 是语言级混入 |
| Exception | throw Error | 思想类似 |
| Service | NestJS Service | 业务逻辑层 |
| Repository | DAO / Prisma Repository | 数据访问层 |
| REST API | Express router | HTTP 资源操作思想一致 |

---

## 5. AI Review 提问

完成周总结后，把总结、Todo API 代码、curl 测试结果贴给 AI，然后问：

```text
我完成了 Week 01：PHP 8 + Composer + OOP 的学习。

我本周学习了：
- PHP 类型与 strict_types
- Composer / vendor / autoload / PSR-4
- class / interface / abstract / 多态
- Trait / Exception
- PSR-12
- Todo REST API

请你按资深 PHP 后端工程师标准帮我做 Week 01 验收：

1. 我的理解是否达到进入 Yii2 学习的最低要求？
2. 我的 PHP ↔ Node.js 类比有哪些准确，哪些不准确？
3. 我的 Todo API 代码有哪些明显问题？
4. 我应该在 Week 02 前补哪些短板？
5. 请给我一个 1-5 分的阶段评分，并说明原因。

请用中文输出：验收结果、问题清单、补课建议、Week 02 学习提醒。
```

---

## 6. 今日产出

今天结束前，你应该产出：

- [ ] Week 01 周总结
- [ ] PHP 基础验收 checklist
- [ ] OOP 验收 checklist
- [ ] 工程化验收 checklist
- [ ] Todo API curl 测试记录
- [ ] Yii2 请求链路预习图
- [ ] 下周 3 个问题
- [ ] AI Review 验收记录

---

## 7. 今日完成标准

- [ ] 完成 Week 01 全部笔记回顾
- [ ] 完成 PHP 基础自测
- [ ] 完成 OOP 自测
- [ ] 完成 Composer / PSR-4 自测
- [ ] Todo API 至少 5 个接口可测试
- [ ] 能用自己的话解释 PHP 与 Node 的核心类比
- [ ] 写出 Week 01 周总结
- [ ] 画出 Yii2 请求链路预习图
- [ ] 明确 Week 02 要重点解决的问题

---

## 8. 今日自测题

### 8.1 Week 01 最核心的 3 个概念是什么？

参考答案：

> Composer/PSR-4 自动加载、PHP OOP、REST API 分层实践。

---

### 8.2 为什么 Composer autoload 对 PHP 工程很重要？

参考答案：

> 因为它让 PHP 能根据 namespace 自动找到类文件，避免手写大量 `require`，是现代 PHP 工程组织的基础。

---

### 8.3 PHP interface 和 TypeScript interface 最大区别是什么？

参考答案：

> PHP interface 在运行时存在并约束类实现；TypeScript interface 主要是编译期类型，编译后通常消失。

---

### 8.4 Trait 适合解决什么问题？

参考答案：

> Trait 适合复用多个类都需要的横切能力，例如日志、时间、格式化等公共方法。

---

### 8.5 Service 和 Repository 的区别是什么？

参考答案：

> Service 负责业务逻辑和流程编排；Repository 负责数据访问和查询封装。

---

### 8.6 Todo API 的 5 个基础接口是什么？

参考答案：

```text
GET /todos
GET /todos/{id}
POST /todos
PUT /todos/{id}
DELETE /todos/{id}
```

---

### 8.7 Week 02 要学的 Yii2 请求链路大概是什么？

参考答案：

```text
请求 → index.php → Yii Application → Module → Controller → action → Response
```

---

## 9. 学习记录

| 记录项 | 内容 |
|--------|------|
| 本周最清楚的概念 |  |
| 本周最卡的概念 |  |
| JS/Node 类比是否帮助理解 |  |
| Todo API 是否跑通 |  |
| 实际耗时 |  |
| 下周要补的问题 |  |
| 自评分（1-5） |  |

---

## 10. AI Review 提示词

```text
我正在进行 Week 01 Day 07：验收与预习 的学习。
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
