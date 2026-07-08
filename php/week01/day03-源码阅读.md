# Week 01 Day 03 · 源码阅读笔记

> 对应 day03 第 2 节「源码阅读」
> 阅读文件：
> - `mall-core/composer.json`（本地：[php/week01/composer.json](composer.json)）
> - `mall-core/common/BaseRepository.php`（本地：[php/week01/BaseRepository.php](BaseRepository.php)）

---

## 2.1 阅读 composer.json

### 重要发现：这份 composer.json 没有 psr-4

day03 教程让我们去找 `autoload.psr-4`，但真实项目的 `autoload` 段是这样的：

```json
"autoload": {
  "files": [
    "common/libraries/App/fun_helpers.php"
  ]
}
```

只有 `files` 自动加载，**没有 `psr-4` 配置**。

`files` 的含义：Composer 在生成 autoloader 时，会无条件 `require` 这里列的每个文件。所以 `common/libraries/App/fun_helpers.php` 里的全局函数（helper 函数）在项目任何地方都能直接调用，不需要 `use`。

| 观察点 | 记录 |
|---|---|
| 是否有 psr-4 | 否，只有 `files` |
| files 加载了什么 | `common/libraries/App/fun_helpers.php`（全局 helper 函数） |
| files 的作用 | 每次请求都直接 require，注册全局函数 |

### 那 `common\BaseRepository` 是怎么被找到的？

既然 composer.json 里没有 `"common\\": "common/"` 这样的 psr-4，`common` 这个 namespace 的映射来自 **Yii2 高级模板（yii2-app-advanced）自身**，而不是 Composer：

- 这个项目 `type` 是 `yiisoft/yii2-app-advanced`。
- Yii2 框架启动时会用 `Yii::setAlias()` 注册别名，其中 `@common` → 项目根的 `common/` 目录。
- Yii 自带的 autoloader 按「namespace 头一段当别名」的规则去找文件：`common\BaseRepository` → `@common/BaseRepository.php` → `common/BaseRepository.php`。

所以映射逻辑上和 PSR-4 是一回事（namespace 前缀对应目录），只是**注册方式在框架层，不在 composer.json**。

| namespace 前缀 | 映射目录 | 映射来源 | 举例类名 | 推导文件路径 |
|---|---|---|---|---|
| `common\` | `common/` | Yii2 alias `@common`（框架注册） | `common\BaseRepository` | `common/BaseRepository.php` |
| `frontend\` | `frontend/` | Yii2 alias `@frontend` | `frontend\models\User` | `frontend/models/User.php` |
| `backend\` | `backend/` | Yii2 alias `@backend` | `backend\controllers\SiteController` | `backend/controllers/SiteController.php` |
| `console\` | `console/` | Yii2 alias `@console` | `console\controllers\MigrateController` | `console/controllers/MigrateController.php` |

> 结论：读企业项目时先找 `autoload.psr-4` 是对的，但如果找不到，要意识到框架（如 Yii2）可能用自己的 alias/autoloader 完成映射。

### require 段观察（补充）

这份 `require` 依赖非常多（支付、Google/Facebook SDK、AWS、Elasticsearch、队列、JWT 等），能看出这是一个功能齐全的电商后端（fecshop 系）。PHP 版本要求 `^7.0`。

---

## 2.2 阅读 BaseRepository.php

文件顶部结构：

```php
<?php

namespace common;

use Yii;
use yii\db\Connection;

abstract class BaseRepository
{
    // ...
}
```

| 观察点 | 记录 |
|---|---|
| namespace 是什么 | `common` |
| class 名是什么 | `BaseRepository`，且是 **`abstract`（抽象类）** |
| use 了哪些类 | `Yii`、`yii\db\Connection` |
| 是否 extends 其他类 | 否（没有 extends，本身作为基类被继承） |
| 常量 | `DEL_FLAG_0 = 0`、`DEL_FLAG_1 = 1`（软删除标志：0 正常 / 1 删除） |
| 属性 | `private static $useSlave = null;`（是否走从库） |
| public 方法 | `instance($useSlave = false)`（静态，单例入口）、`getDb()` |
| protected 方法 | `getSlaveDb()` |
| abstract 方法 | `abstract public getConnection()`（强制子类实现） |

### 逐个方法解读

- `instance($useSlave = false)`：静态方法，单例入口。用 Yii 的依赖注入容器 `Yii::$container`，如果还没注册单例就 `setSingleton(static::class)`，然后 `get()` 返回同一个实例。`static::class` 用了后期静态绑定，所以子类调用 `子类::instance()` 拿到的是子类的单例。
- `getDb()`：`$useSlave` 为真时返回从库连接 `Yii::$app->dbFecshopSlave`，否则返回 `null`（默认走主库，交给框架默认连接）。
- `getSlaveDb()`：protected，直接返回从库连接，供子类内部使用。
- `getConnection()`：`abstract`，本类不实现，**强制每个具体 Repository 子类自己给出用哪个数据库连接**。

---

## 2.3 观察 Repository 的职责

回答 day03 的四个自测问题：

1. **它是不是封装数据库查询？**
   是。它是持久化基类，统一管理数据库连接（主库/从库）。

2. **它有没有通用的查询方法？**
   这个 base 类本身没有直接的 `find/save/update` 查询方法，但它提供了通用能力：单例获取（`instance`）、连接选择（`getDb` / `getSlaveDb`）、软删除常量（`DEL_FLAG_*`）。具体查询留给子类。

3. **它有没有和 Model / ActiveRecord 交互？**
   间接有。它 `use yii\db\Connection`，`getConnection()` 返回的连接就是子类做查询时要用的 DB 连接。

4. **它是不是给具体业务 Repository 继承的？**
   是。它是 `abstract`，且有 `abstract getConnection()`，所以**不能直接实例化**，必须被具体业务 Repository（如 `OrderRepository`、`UserRepository`）继承并实现 `getConnection()`。

一句话总结：

> `BaseRepository` 是后续业务 Repository 的抽象基类，用单例 + 主从库连接选择的方式，复用通用的数据库访问能力；每个子类必须实现 `getConnection()` 指定自己的连接。

---

## 本节小结（与教程示例的差异）

| 教程示例（理想）| 本项目真实情况 |
|---|---|
| composer.json 里有 `autoload.psr-4` | 只有 `autoload.files`，**没有 psr-4** |
| namespace → 路径 由 Composer PSR-4 映射 | 由 Yii2 框架的 alias（`@common` 等）映射 |
| namespace 常见 `App\Services\...`（首字母大写多段）| 这里是单段小写 `common` |
| Repository 直接写查询方法 | base 类只管连接与单例，查询交给子类 |

核心认知不变：**namespace 前缀对应一个目录，剩余部分对应子目录 + 文件名**——只是「谁来完成这个映射」在不同项目里可能是 Composer，也可能是框架。
