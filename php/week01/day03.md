# Week 01 Day 03：namespace 与 Composer 依赖

> 所属周：Week 01：PHP 8 + Composer + OOP  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-core`  
> 类型：编码练习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 PHP 的 `namespace`、`use`、Composer autoload 和 PSR-4 映射规则，能从一个类名推导出它所在的文件路径，也能从文件路径反推出它大概率对应的 namespace。

今天你要真正掌握这一句话：

> PHP 的 `namespace` 解决「类名冲突」和「工程分层命名」问题，Composer 根据 `composer.json` 里的 PSR-4 规则，把 `App\Services\UserService` 这样的类名自动映射到 `src/Services/UserService.php` 文件。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解为什么需要 namespace
2. 理解 PHP 没有 namespace 时会遇到什么问题
3. 学会写 `namespace Xxx\Yyy;`
4. 学会写 `use Xxx\Yyy\ClassName;`
5. 复习 Composer autoload
6. 理解 PSR-4 映射规则
7. 练习从类名推导文件路径
8. 练习从文件路径反推类名
9. 阅读 `mall-core/composer.json`
10. 阅读 `BaseRepository.php` 的 namespace 和 class 结构
11. 画一张 namespace → 文件路径映射图
12. 完成今日自测和 AI Review

---

## 1. 学习内容

### 1.1 为什么需要 namespace？

先想一个问题：如果项目里有两个类都叫 `UserService`，怎么办？

例如：

```text
支付模块有 UserService
订单模块也有 UserService
```

如果都直接叫：

```php
class UserService
{
}
```

PHP 就分不清你要用哪一个。

所以需要 namespace，把类放到不同「命名空间」下面：

```php
namespace App\Pay\Services;

class UserService
{
}
```

另一个：

```php
namespace App\Order\Services;

class UserService
{
}
```

这样完整类名就变成：

```text
App\Pay\Services\UserService
App\Order\Services\UserService
```

它们名字最后都叫 `UserService`，但完整路径不同，所以不会冲突。

---

### 1.2 namespace 可以类比什么？

你是前端，可以这样类比：

| PHP | JS/Node 类比 | 说明 |
|---|---|---|
| `namespace App\Services;` | 文件模块作用域 / 目录分层 | 给类一个逻辑路径 |
| `use App\Services\UserService;` | `import UserService from ...` | 引入一个类 |
| `App\Services\UserService` | `src/services/UserService` | 类的完整路径标识 |
| PSR-4 | import 路径解析规则 | namespace 到文件路径的约定 |

但要注意：

> JS 的 import 通常直接写文件路径；PHP 的 namespace 是语言级命名，文件路径由 Composer PSR-4 规则映射出来。

---

### 1.3 最小 namespace 示例

项目结构：

```text
php-namespace-demo/
├── composer.json
├── index.php
└── src/
    └── Services/
        └── UserService.php
```

`composer.json`：

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
```

`src/Services/UserService.php`：

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

`index.php`：

```php
<?php

declare(strict_types=1);

require __DIR__ . "/vendor/autoload.php";

use App\Services\UserService;

$userService = new UserService();

echo $userService->getName() . PHP_EOL;
```

运行前先执行：

```bash
composer dump-autoload
```

再运行：

```bash
php index.php
```

期望输出：

```text
Tom
```

---

### 1.4 namespace 写在哪里？

通常写在 PHP 文件顶部：

```php
<?php

declare(strict_types=1);

namespace App\Services;

class UserService
{
}
```

常见顺序：

```text
<?php

declare(strict_types=1);

namespace ...;

use ...;

class ...
```

也就是：

1. `<?php`
2. `declare(strict_types=1);`
3. `namespace`
4. `use`
5. `class`

---

### 1.5 `use` 是什么？

`use` 可以让你少写完整类名。

如果不用 `use`：

```php
$service = new \App\Services\UserService();
```

用了 `use`：

```php
use App\Services\UserService;

$service = new UserService();
```

它类似 JS 的：

```js
import UserService from './services/UserService';

const service = new UserService();
```

不过 PHP 的 `use` 引入的是类名别名，不是直接加载文件。真正加载文件的是 Composer autoload。

---

### 1.6 `use ... as ...` 起别名

如果两个类短名一样，可以起别名。

```php
use App\Pay\Services\UserService as PayUserService;
use App\Order\Services\UserService as OrderUserService;

$payUserService = new PayUserService();
$orderUserService = new OrderUserService();
```

这类似 JS：

```js
import { UserService as PayUserService } from './pay/UserService';
import { UserService as OrderUserService } from './order/UserService';
```

---

### 1.7 Composer autoload 再复习

Composer 是 PHP 的依赖管理工具，也负责自动加载类。

核心入口是：

```php
require __DIR__ . "/vendor/autoload.php";
```

这行代码的作用：

> 告诉 PHP：以后遇到未知类名时，交给 Composer 根据 PSR-4 规则自动找文件。

没有这行，下面代码通常会报类找不到：

```php
use App\Services\UserService;

$service = new UserService();
```

---

### 1.8 PSR-4 是什么？

PSR-4 是 PHP 社区的一套自动加载规范。

它规定：

> namespace 前缀可以映射到某个目录，后续 namespace 片段映射成子目录，类名映射成 `.php` 文件。

例如：

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
```

意思是：

```text
App\ 这个 namespace 前缀，对应 src/ 目录
```

所以：

```text
App\Services\UserService
```

会映射为：

```text
src/Services/UserService.php
```

---

### 1.9 PSR-4 映射步骤拆解

以这个类名为例：

```text
App\Services\UserService
```

配置：

```json
"App\\": "src/"
```

步骤：

1. 类名以 `App\` 开头
2. `App\` 对应 `src/`
3. 剩余部分是 `Services\UserService`
4. 把 `\` 换成目录 `/`
5. 类名最后加 `.php`
6. 得到 `src/Services/UserService.php`

表格：

| 步骤 | 结果 |
|---|---|
| 完整类名 | `App\Services\UserService` |
| 匹配前缀 | `App\` |
| 前缀对应目录 | `src/` |
| 剩余 namespace | `Services\UserService` |
| 转成路径 | `Services/UserService.php` |
| 最终文件 | `src/Services/UserService.php` |

---

### 1.10 多个 PSR-4 前缀

真实项目里可能有多个前缀：

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "src/",
      "Common\\": "common/",
      "Modules\\": "modules/"
    }
  }
}
```

那么：

| 类名 | 文件路径 |
|---|---|
| `App\Services\UserService` | `src/Services/UserService.php` |
| `Common\BaseService` | `common/BaseService.php` |
| `Modules\Pay\PayController` | `modules/Pay/PayController.php` |

你阅读企业项目时，要先找 `composer.json` 的 `autoload.psr-4`。

---

### 1.11 修改 autoload 后为什么要 `composer dump-autoload`？

当你修改了 `composer.json` 的 autoload 配置后，要执行：

```bash
composer dump-autoload
```

它会重新生成：

```text
vendor/autoload.php
vendor/composer/...
```

小白理解：

> `composer dump-autoload` 就是让 Composer 重新整理 namespace 到文件路径的地图。

---

### 1.12 Repository 是什么？

今天会读到 `BaseRepository.php`，你先不用完全掌握 Repository 模式，只要先知道：

> Repository 是数据库访问层，负责把查询、保存、更新等 DB 操作封装起来，让 Service 不直接写复杂 SQL 或 ORM 查询。

类比前端/Node：

| PHP 后端 | Node 类比 |
|---|---|
| Repository | DAO / Model query helper |
| Service | 业务逻辑层 |
| Model / ActiveRecord | Sequelize Model / Prisma Model |

简单理解：

```text
Controller 接请求
Service 写业务
Repository 查数据库
Model 映射数据表
```

---

## 2. 源码阅读

- `mall-core/composer.json`
- `mall-core/common/BaseRepository.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

### 2.1 阅读 `composer.json`

重点找：

```json
"autoload": {
  "psr-4": {
  }
}
```

记录表：

| namespace 前缀 | 映射目录 | 举例类名 | 推导文件路径 |
|---|---|---|---|
|  |  |  |  |
|  |  |  |  |

如果看到类似：

```json
"common\\": "common/"
```

你要能推导：

```text
common\BaseRepository
→ common/BaseRepository.php
```

如果真实项目 namespace 大小写和目录不完全一样，要以项目实际配置为准。

---

### 2.2 阅读 `BaseRepository.php`

先看文件顶部：

```php
<?php

namespace ...;

use ...;

class BaseRepository
{
}
```

记录这些内容：

| 观察点 | 记录 |
|---|---|
| namespace 是什么 |  |
| class 名是什么 |  |
| use 了哪些类 |  |
| 是否 extends 其他类 |  |
| public 方法有哪些 |  |
| protected 方法有哪些 |  |

---

### 2.3 观察 Repository 的职责

读的时候重点问自己：

1. 它是不是封装数据库查询？
2. 它有没有通用的查询方法？
3. 它有没有和 Model / ActiveRecord 交互？
4. 它是不是给具体业务 Repository 继承的？

你不需要今天完全看懂全部 DB 逻辑，只要能回答：

> BaseRepository 是后续业务 Repository 的基础类，用来复用通用 DB 访问能力。

---

## 3. 练习任务

### 练习 1：创建 namespace demo

创建目录：

```bash
mkdir php-namespace-demo
cd php-namespace-demo
mkdir -p src/Services
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

创建 `src/Services/UserService.php`：

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

创建 `index.php`：

```php
<?php

declare(strict_types=1);

require __DIR__ . "/vendor/autoload.php";

use App\Services\UserService;

$service = new UserService();

echo $service->getName() . PHP_EOL;
```

执行：

```bash
composer dump-autoload
php index.php
```

期望输出：

```text
Tom
```

---

### 练习 2：增加第二个类

创建目录：

```bash
mkdir -p src/Repositories
```

创建 `src/Repositories/UserRepository.php`：

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

class UserRepository
{
    public function findNameById(int $id): string
    {
        return "User#" . $id;
    }
}
```

修改 `index.php`：

```php
<?php

declare(strict_types=1);

require __DIR__ . "/vendor/autoload.php";

use App\Repositories\UserRepository;
use App\Services\UserService;

$service = new UserService();
$repository = new UserRepository();

echo $service->getName() . PHP_EOL;
echo $repository->findNameById(1) . PHP_EOL;
```

运行：

```bash
php index.php
```

期望输出：

```text
Tom
User#1
```

---

### 练习 3：手写类名 → 文件路径映射表

根据配置：

```json
"App\\": "src/"
```

填写：

| 类名 | 文件路径 |
|---|---|
| `App\User` |  |
| `App\Services\UserService` |  |
| `App\Repositories\UserRepository` |  |
| `App\Controllers\OrderController` |  |
| `App\Models\Order` |  |

参考答案：

| 类名 | 文件路径 |
|---|---|
| `App\User` | `src/User.php` |
| `App\Services\UserService` | `src/Services/UserService.php` |
| `App\Repositories\UserRepository` | `src/Repositories/UserRepository.php` |
| `App\Controllers\OrderController` | `src/Controllers/OrderController.php` |
| `App\Models\Order` | `src/Models/Order.php` |

---

### 练习 4：文件路径 → 类名反推

根据配置：

```json
"App\\": "src/"
```

填写：

| 文件路径 | 类名 |
|---|---|
| `src/Services/OrderService.php` |  |
| `src/Repositories/OrderRepository.php` |  |
| `src/Controllers/UserController.php` |  |
| `src/Models/User.php` |  |

参考答案：

| 文件路径 | 类名 |
|---|---|
| `src/Services/OrderService.php` | `App\Services\OrderService` |
| `src/Repositories/OrderRepository.php` | `App\Repositories\OrderRepository` |
| `src/Controllers/UserController.php` | `App\Controllers\UserController` |
| `src/Models/User.php` | `App\Models\User` |

---

### 练习 5：画目录与 namespace 图

画一张类似这样的图：

```text
composer.json
└── autoload.psr-4
    └── App\  =>  src/
        ├── Services\UserService        => src/Services/UserService.php
        ├── Repositories\UserRepository => src/Repositories/UserRepository.php
        └── Controllers\OrderController => src/Controllers/OrderController.php
```

这个图是今天最重要的产出之一。

---

## 4. JS/Node.js 类比

| PHP 概念 | Node/JS 类比 | 差异 |
|---|---|---|
| `namespace App\Services` | 模块路径 / 文件作用域 | PHP namespace 是语言级命名 |
| `use App\Services\UserService` | `import UserService from ...` | PHP use 是类名别名，不是文件路径 |
| `composer.json` autoload | package/export/import 解析配置 | Composer 负责生成自动加载器 |
| `vendor/autoload.php` | Node 模块加载机制 | PHP 需要显式 require |
| PSR-4 | 路径约定 | namespace 片段映射目录 |
| Repository | DAO / 数据访问封装 | PHP 项目常放在 Repository 层 |

---

## 5. AI Review 提问

完成练习后，把你的映射表和代码贴给 AI，然后问：

```text
我正在学习 PHP namespace、use、Composer autoload 和 PSR-4。

请你按资深 PHP 后端工程师标准帮我检查：

1. 我的 namespace 到文件路径映射是否正确？
2. 我对 use 和 import 的类比是否准确？
3. 我对 vendor/autoload.php 的理解是否正确？
4. 我阅读 composer.json 的重点是否正确？
5. 我对 Repository 层的初步理解是否合理？

请用中文输出：
- 我理解正确的地方
- 我理解错误或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

今天结束前，你应该产出：

- [ ] `php-namespace-demo` 示例项目
- [ ] `UserService.php` 示例类
- [ ] `UserRepository.php` 示例类
- [ ] 类名 → 文件路径映射表
- [ ] 文件路径 → 类名反推表
- [ ] `mall-core/composer.json` autoload 笔记
- [ ] `BaseRepository.php` 阅读笔记
- [ ] namespace 与 Node import 类比笔记

---

## 7. 今日完成标准

- [ ] 能解释 namespace 解决什么问题
- [ ] 能写 `namespace App\Services;`
- [ ] 能写 `use App\Services\UserService;`
- [ ] 能解释 `use` 和 `import` 的相似点与差异
- [ ] 能解释 `vendor/autoload.php`
- [ ] 能解释 PSR-4
- [ ] 能从类名推导文件路径
- [ ] 能从文件路径反推类名
- [ ] 能读懂 `composer.json` 的 `autoload.psr-4`
- [ ] 能说出 Repository 的大概职责

---

## 8. 今日自测题

### 8.1 namespace 解决什么问题？

参考答案：

> namespace 用来给类分组和避免类名冲突，让不同模块可以拥有相同短类名，但完整类名不同。

---

### 8.2 `use App\Services\UserService;` 的作用是什么？

参考答案：

> 它给完整类名 `App\Services\UserService` 建立一个短别名，让代码里可以直接写 `new UserService()`。

---

### 8.3 `use` 会直接加载文件吗？

参考答案：

> 不会。`use` 主要是类名别名。真正负责加载文件的是 Composer 的 `vendor/autoload.php`。

---

### 8.4 PSR-4 是什么？

参考答案：

> PSR-4 是 PHP 自动加载规范，用来规定 namespace 前缀如何映射到目录。

---

### 8.5 如果 `App\` 映射到 `src/`，那么 `App\Services\UserService` 对应哪个文件？

参考答案：

```text
src/Services/UserService.php
```

---

### 8.6 修改 `composer.json` 的 autoload 后要执行什么命令？

参考答案：

```bash
composer dump-autoload
```

---

### 8.7 Repository 层大概负责什么？

参考答案：

> Repository 负责封装数据库访问逻辑，让 Service 层不要直接写复杂查询。

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
我正在进行 Week 01 Day 03：namespace 与 Composer 依赖 的学习。
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
