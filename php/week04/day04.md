# Week 04 Day 04：Laravel 对比

> 所属周：Week 04：配置中心 + 站点 API  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-core`  
> 类型：架构理解  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

通过 Laravel 的 `config()` 函数对比项目中的 `g_config()`，理解“框架配置”和“业务配置”的区别，并能写出一份 `config() vs g_config()` 对照笔记。

今天你要真正掌握这一句话：

> Laravel 的 `config()` 更常读取应用/框架级配置，项目里的 `g_config()` 更常读取业务级动态配置；两者都能提供默认值，但使用场景和配置来源不同。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解 Laravel `config()` 解决什么问题
2. 回顾项目里的 `g_config()` 解决什么问题
3. 对比“框架配置”和“业务配置”
4. 学会 Laravel `config('app.name')` 这种点号读取方式
5. 对比 `config()` 默认值和 `g_config()` 默认值
6. 找 3 个配置影响业务的例子
7. 写 `config() vs g_config()` 对照表
8. 用 Node.js 的 config 包/环境变量做类比
9. 用 AI Review 检查对照是否准确

---

## 1. 学习内容

### 1.1 Laravel `config()` 是什么？

Laravel 中常见写法：

```php
<?php

$appName = config('app.name');
```

意思是读取配置文件里的：

```php
<?php

// config/app.php
return [
    'name' => env('APP_NAME', 'Laravel'),
];
```

所以：

```php
config('app.name')
```

可以理解为：

```text
读取 config/app.php 中 name 这个配置。
```

如果你熟悉 JS，可以类比：

```js
const appName = config.get('app.name');
```

---

### 1.2 `g_config()` 回顾

项目里的 `g_config()` 常见形式：

```php
<?php

$value = g_config('site', 'show_coupon', false);
```

它更像是在读取业务配置：

```text
site 模块下 show_coupon 这个业务开关，如果没有配置，默认 false。
```

它可能来自：

- 数据库配置表
- 配置中心
- 本地缓存
- 后台管理系统保存的配置

小白重点：`g_config()` 通常不是框架自带函数，而是项目封装出来的业务配置读取函数。

---

### 1.3 框架配置 vs 业务配置

这是今天最重要的区别。

| 对比项 | 框架配置 | 业务配置 |
|---|---|---|
| 主要用途 | 支撑应用运行 | 控制业务行为 |
| 示例 | app name、DB、cache、queue | banner、支付开关、风控阈值 |
| 修改频率 | 低 | 中到高 |
| 修改者 | 开发/运维 | 开发/运营/后台管理员 |
| 配置来源 | config 文件、env | DB、配置中心、后台系统 |
| 读取方式 | `config('app.name')` | `g_config('site', 'name', '默认值')` |

例子：

```php
<?php

// 框架/应用配置
$appEnv = config('app.env');

// 业务配置
$showCoupon = g_config('site', 'show_coupon', false);
```

不要把它们混在一起理解。

---

### 1.4 Laravel `config()` 的默认值

Laravel `config()` 可以传第二个参数作为默认值：

```php
<?php

$timezone = config('app.timezone', 'UTC');
```

意思是：

```text
读取 app.timezone；如果不存在，返回 UTC。
```

这和 `g_config()` 的第三个参数有相似点：

```php
<?php

$timezone = config('app.timezone', 'UTC');
$showCoupon = g_config('site', 'show_coupon', false);
```

但区别是：

| 函数 | 默认值位置 | 常见用途 |
|---|---|---|
| `config($key, $default)` | 第二个参数 | 框架/应用配置 |
| `g_config($module, $key, $default)` | 第三个参数 | 业务配置 |

---

### 1.5 点号 key 和 module/key 的区别

Laravel 常用点号：

```php
<?php

config('database.connections.mysql.host');
```

项目 `g_config()` 常用模块 + key：

```php
<?php

g_config('pay', 'enable_stripe', false);
```

对比：

| 写法 | 结构 |
|---|---|
| `config('app.name')` | `文件名.配置项` |
| `config('database.connections.mysql.host')` | 多层数组路径 |
| `g_config('pay', 'enable_stripe', false)` | `模块 + 配置 key + 默认值` |

小白重点：点号不是特殊魔法，本质上是在读取多层配置数组。

---

### 1.6 什么时候用 `config()`，什么时候用 `g_config()`？

可以按这个规则判断：

```text
支撑应用运行 → config()
控制业务展示/开关/阈值 → g_config()
```

例子：

| 场景 | 推荐读取方式 | 原因 |
|---|---|---|
| APP 名称 | `config('app.name')` | 应用基础配置 |
| DB host | `config('database.connections.mysql.host')` | 基础设施配置 |
| 是否展示优惠券入口 | `g_config('site', 'show_coupon', false)` | 业务开关 |
| 支付渠道是否开启 | `g_config('pay', 'enable_xxx', false)` | 业务开关 |
| 队列连接名 | `config('queue.default')` | 框架运行配置 |
| 首页 banner | `g_config('site', 'banner', [])` | 运营配置 |

---

### 1.7 Node.js 类比

Node 项目中可能有：

```js
const appName = config.get('app.name');
const dbHost = process.env.DB_HOST;
const showCoupon = await featureFlags.get('site.show_coupon', false);
```

类比到 PHP：

```php
<?php

$appName = config('app.name');
$dbHost = config('database.connections.mysql.host');
$showCoupon = g_config('site', 'show_coupon', false);
```

大致可以这样对应：

| PHP/Laravel | Node.js 类比 |
|---|---|
| `config()` | `config.get()` |
| `env()` | `process.env` |
| `g_config()` | feature flag / remote config service |
| `config/*.php` | config files |
| 业务配置表 | remote settings table |

---

### 1.8 今日对照笔记模板

你最终要写出这样的对照：

| 对比项 | Laravel `config()` | 项目 `g_config()` |
|---|---|---|
| 示例 | `config('app.name')` | `g_config('site', 'name', '默认商城')` |
| 参数 | key + default | module + key + default |
| 配置来源 | `config/*.php` / `.env` | DB / 配置中心 / 缓存 |
| 主要用途 | 应用运行配置 | 业务动态配置 |
| 是否适合运营改 | 通常不适合 | 通常适合 |
| 风险 | 改错会影响应用运行 | 改错会影响业务行为 |

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议参考：

- Laravel 官方 Configuration 文档
- 当前项目中 `g_config()` 的使用示例
- 当前项目中 `ConfigHelper` 的常量定义

记录：

| 配置示例 | 类型 | 读取方式 | 说明 |
|---|---|---|---|
|  | 框架配置 |  |  |
|  | 业务配置 |  |  |
|  | 环境变量 |  |  |

---

## 3. 练习任务

### 练习 1：写对照笔记

完成表格：

| 对比项 | `config()` | `g_config()` |
|---|---|---|
| 参数形式 |  |  |
| 默认值位置 |  |  |
| 配置来源 |  |  |
| 常见用途 |  |  |
| 是否适合动态调整 |  |  |
| 举例 |  |  |

### 练习 2：找 3 个配置影响业务的例子

| 配置 | 读取方式 | 影响业务 |
|---|---|---|
|  |  |  |
|  |  |  |
|  |  |  |

### 练习 3：判断用哪个函数

| 场景 | 用 `config()` 还是 `g_config()` | 原因 |
|---|---|---|
| 数据库 host |  |  |
| 首页 banner |  |  |
| 队列连接名 |  |  |
| 是否开启优惠券 |  |  |
| APP_ENV |  |  |
| 支付渠道开关 |  |  |

---

## 4. JS/Node.js 类比

- `config()` ≈ Node 的 `config.get('app.name')`
- `env()` ≈ `process.env`
- `g_config()` ≈ feature flags / remote config
- Laravel `config/*.php` ≈ Node 项目的配置文件
- 业务配置中心 ≈ 可动态调整的 remote settings 平台

---

## 5. AI Review 提问

```text
我正在写 Laravel config() 和项目 g_config() 的对照笔记。
请你检查：
1. 我对 config() 的理解是否正确？
2. 我对 g_config() 的理解是否正确？
3. 哪些场景我选错了读取方式？
4. 框架配置和业务配置的边界是否清楚？
5. 我的 Node.js 类比是否准确？
```

---

## 6. 今日产出

- [ ] Laravel `config()` 学习笔记
- [ ] `config()` vs `g_config()` 对照表
- [ ] 3 个配置影响业务的例子
- [ ] 读取方式判断练习
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 Laravel `config()` 的作用
- [ ] 能解释项目 `g_config()` 的作用
- [ ] 能区分框架配置和业务配置
- [ ] 能说出 `config()` 和 `g_config()` 默认值参数差异
- [ ] 能判断至少 6 个场景该用哪种配置读取方式
- [ ] 能写出完整对照笔记

---

## 8. 学习记录

| 记录项 | 内容 |
|--------|------|
| 今日最清楚的概念 |  |
| 今日最卡的概念 |  |
| JS/Node 类比是否帮助理解 |  |
| 实际耗时 |  |
| 明日要补的问题 |  |

---

## 9. AI Review 提示词

```text
我正在进行 Week 04 Day 04：Laravel 对比 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 04 README](./README.md)
