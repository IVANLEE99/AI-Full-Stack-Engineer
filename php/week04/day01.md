# Week 04 Day 01：g_config 函数

> 所属周：Week 04：配置中心 + 站点 API  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-core`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解项目中的配置读取函数 `g_config()`：它通常接收 `module`、`key`、`default` 三类信息，用来从配置系统中读取业务配置，并在没有配置时返回默认值。你今天不需要背源码，而是要知道业务代码为什么要通过配置函数读取开关、文案、阈值和渠道设置。

今天你要真正掌握这一句话：

> `g_config(module, key, default)` 可以类比 `process.env.KEY ?? default`，但它通常比环境变量更业务化：支持按模块分组、默认值兜底、配置中心或数据库动态更新。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解为什么不能把业务配置硬编码在代码里
2. 理解什么是“业务配置”，它和普通代码常量有什么区别
3. 掌握 `module / key / default` 三个参数的含义
4. 用 3 个例子模拟 `g_config()` 的读取结果
5. 阅读 `fun_helpers.php` 中的 `g_config()` 函数入口
6. 阅读 `ConfigHelper` 中的模块常量或配置 key 常量
7. 在项目里找 3 个真实配置项，记录它们影响什么业务
8. 对比 Node.js 的 `process.env` 和配置服务
9. 画出配置读取流程，并完成今日自测

---

## 1. 学习内容

### 1.1 为什么需要配置？

先看一个最简单的问题：如果支付开关写死在代码里，会发生什么？

```php
<?php

$isPayEnabled = true;
```

这表示支付永远开启。以后如果业务临时要求关闭支付能力，你就必须：

1. 修改代码
2. 提交代码
3. 发版
4. 部署
5. 验证线上效果

这对一个简单开关来说太重了。

更好的方式是配置化：

```php
<?php

$isPayEnabled = g_config('pay', 'enable', true);
```

这句话的意思是：

```text
从 pay 模块读取 enable 配置；如果没有配置，就默认 true。
```

这样业务开关可以通过配置系统调整，而不是每次都改代码。

小白重点：配置的本质是“经常需要调整，但不希望每次都改代码的业务参数”。

---

### 1.2 什么是业务配置？

业务配置不是 PHP 语法概念，而是企业项目里的工程概念。

常见业务配置：

| 配置类型 | 示例 | 作用 |
|---|---|---|
| 功能开关 | 是否开启优惠券 | 控制功能是否展示或可用 |
| 支付渠道 | 是否开启 Stripe / 微信支付 | 控制支付方式 |
| 首页配置 | banner、导航、推荐位 | 控制前端展示 |
| 风控阈值 | 最大下单金额、最大退款次数 | 控制业务规则 |
| 文案配置 | 售后提示语、弹窗文案 | 动态调整文案 |
| 运营配置 | 活动开始/结束时间 | 控制运营活动 |

你可以把业务配置理解成后台管理系统里的“旋钮”：

```text
代码负责提供能力，配置负责调整能力的开关和参数。
```

---

### 1.3 `g_config(module, key, default)` 怎么理解？

常见形式：

```php
<?php

$value = g_config($module, $key, $default);
```

三个参数分别是：

| 参数 | 含义 | 示例 |
|---|---|---|
| `module` | 配置所属模块 | `pay` / `site` / `order` |
| `key` | 配置项名称 | `enable_stripe` |
| `default` | 默认值 | `false` |

例子：

```php
<?php

$enabled = g_config('pay', 'enable_stripe', false);
```

可以翻译成中文：

```text
读取 pay 模块下 enable_stripe 配置；如果不存在，默认 false。
```

再看一个站点配置例子：

```php
<?php

$siteName = g_config('site', 'name', '默认商城');
```

可以翻译成：

```text
读取 site 模块下 name 配置；如果没有配置，就使用“默认商城”。
```

---

### 1.4 为什么需要 `default`？

配置系统可能出现这些情况：

- 配置不存在
- 配置中心暂时不可用
- 本地开发环境没有配置
- 新配置还没同步
- 数据库里配置值为空
- 配置 key 写错了

如果没有默认值，业务代码可能直接报错，或者返回空数据。

有默认值时：

```php
<?php

$showBanner = g_config('site', 'show_banner', true);

if ($showBanner) {
    // 返回 banner 配置给前端
}
```

即使配置不存在，也能继续运行。

但是默认值也不能乱写。比如：

```php
<?php

$allowRefund = g_config('order', 'allow_refund', true);
```

如果退款开关配置读取失败，默认 `true` 可能会放开退款能力，带来风险。

所以默认值要根据业务风险设计：

| 场景 | 默认值建议 | 原因 |
|---|---|---|
| 首页 banner 展示 | `true` 或空数组 | 失败影响较小 |
| 支付渠道开启 | `false` | 避免错误开放支付方式 |
| 退款能力 | `false` | 避免资金风险 |
| 文案配置 | 默认文案 | 保证用户能看到提示 |

---

### 1.5 用伪代码理解 `g_config()` 内部流程

真实项目里的 `g_config()` 可能比较复杂，但你可以先按这个流程理解：

```php
<?php

function g_config(string $module, string $key, mixed $default = null): mixed
{
    $value = findConfigFromCacheOrDatabase($module, $key);

    if ($value === null) {
        return $default;
    }

    return $value;
}
```

这不是要求你背实现，而是帮助你理解：

```text
先查配置 → 找到就返回 → 找不到就返回 default
```

流程图：

```text
业务代码
  ↓
g_config(module, key, default)
  ↓
检查本地缓存 / Redis / DB / 配置中心
  ↓
找到配置：返回配置值
找不到配置：返回 default
```

小白重点：你读源码时先找“入口、参数、返回值、兜底逻辑”，不要一开始就陷入缓存细节。

---

### 1.6 `ConfigHelper` 是什么？

项目里可能有 `ConfigHelper.php`，用来定义配置模块或 key 常量。

例如：

```php
<?php

final class ConfigHelper
{
    public const MODULE_SITE = 'site';
    public const MODULE_ORDER = 'order';
    public const MODULE_PAY = 'pay';
}
```

这样写的好处：

```php
<?php

$enabled = g_config(ConfigHelper::MODULE_PAY, 'enable_stripe', false);
```

比到处写字符串更安全：

```php
<?php

$enabled = g_config('pay', 'enable_stripe', false);
```

为什么更安全？因为字符串容易写错：

```php
<?php

// pay 写成了 py，代码不一定立刻报错，但配置会读不到
$enabled = g_config('py', 'enable_stripe', false);
```

常量可以减少魔法字符串，让 IDE 更容易提示，也方便后续统一修改。

---

### 1.7 Node.js 类比：`process.env` 和配置服务

Node 中常见写法：

```js
const enabled = process.env.ENABLE_STRIPE ?? 'false';
```

PHP 项目：

```php
<?php

$enabled = g_config('pay', 'enable_stripe', false);
```

对比：

| PHP | Node |
|---|---|
| `g_config('pay', 'enable', false)` | `process.env.PAY_ENABLE ?? false` |
| 按模块分组 | 通常靠变量名前缀 |
| 支持默认值 | `?? default` |
| 可能来自配置中心/DB | 通常来自 env 文件/环境变量 |
| 更偏业务配置 | 更偏环境配置 |

但要注意：`process.env` 和 `g_config()` 不是完全一样。

`process.env` 更常用于：

- 数据库连接
- Redis 地址
- API 密钥
- 运行环境

`g_config()` 更常用于：

- 业务开关
- 页面展示配置
- 支付渠道配置
- 风控阈值
- 运营文案

---

### 1.8 阅读源码时怎么做笔记？

今天指定阅读：

```text
mall-core/common/libraries/App/fun_helpers.php
mall-core/common/libraries/App/Utils/ConfigHelper.php
```

阅读 `g_config()` 时先找：

1. 函数名是不是 `g_config`
2. 参数有几个
3. 默认值参数叫什么
4. 函数调用了哪个类或方法
5. 找不到配置时返回什么
6. 是否使用缓存
7. 是否有类型转换

记录模板：

| 问题 | 记录 |
|---|---|
| `g_config()` 在哪个文件 |  |
| 参数列表 |  |
| 默认值如何处理 |  |
| 配置来源 |  |
| 是否使用缓存 |  |
| 最终返回值 |  |

阅读 `ConfigHelper` 时先找：

| 问题 | 记录 |
|---|---|
| 有哪些 module 常量 |  |
| 有哪些 key 常量 |  |
| 哪些模块最常用 |  |
| 是否有注释说明 |  |

---

## 2. 源码阅读

- `mall-core/common/libraries/App/fun_helpers.php`
- `mall-core/common/libraries/App/Utils/ConfigHelper.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读目标：

| 文件 | 重点 |
|---|---|
| `fun_helpers.php` | 找 `g_config()` 的参数、调用链和返回逻辑 |
| `ConfigHelper.php` | 找 module/key 常量，理解为什么不用散落字符串 |

记录：

| 配置项 | 记录 |
|---|---|
| `g_config` 参数 |  |
| 默认值如何处理 |  |
| 配置来源 |  |
| ConfigHelper 模块常量 |  |
| 你看不懂的地方 |  |

---

## 3. 练习任务

### 练习 1：列 3 个配置项

| module | key | default | 含义 | 影响 |
|---|---|---|---|---|
| `site` |  |  | 站点配置 | 前端展示 |
| `order` |  |  | 订单配置 | 下单逻辑 |
| `pay` |  |  | 支付配置 | 支付渠道 |

要求：至少写清楚“这个配置会影响哪个页面或哪段业务”。

---

### 练习 2：写 3 段伪代码

#### 站点 banner 开关

```php
<?php

$showBanner = g_config('site', 'show_banner', true);

if ($showBanner) {
    // 返回 banner 配置给前端
}
```

#### 支付渠道开关

```php
<?php

$enableStripe = g_config('pay', 'enable_stripe', false);

if (!$enableStripe) {
    // 不展示 Stripe 支付方式
}
```

#### 订单金额阈值

```php
<?php

$maxOrderAmount = g_config('order', 'max_amount', 10000);

if ($orderAmount > $maxOrderAmount) {
    // 提示订单金额超过限制
}
```

---

### 练习 3：对比 `process.env`

| 对比项 | `g_config()` | `process.env` |
|---|---|---|
| 分组 | module | 变量名前缀 |
| 默认值 | 第三个参数 | `?? default` |
| 动态性 | 可能动态更新 | 通常启动时固定 |
| 业务含义 | 强 | 弱 |
| 常见用途 | 业务开关/文案/阈值 | 环境变量/密钥/连接信息 |

---

### 练习 4：设计默认值

请为下面配置选择默认值，并说明原因：

| 配置 | 你的 default | 原因 |
|---|---|---|
| 是否展示首页 banner |  |  |
| 是否开启支付 |  |  |
| 是否允许退款 |  |  |
| 默认站点名称 |  |  |
| 最大下单金额 |  |  |

---

## 4. JS/Node.js 类比

| PHP 配置 | Node 类比 | 差异 |
|---|---|---|
| `g_config()` | `process.env` / config service | 更业务化，可能动态更新 |
| `module` | env prefix | PHP 按模块分组更明确 |
| `key` | env key | 配置项名 |
| `default` | `?? default` | 兜底值 |
| `ConfigHelper` | config constants | 避免魔法字符串 |
| 配置中心/DB | remote config service | 可运营后台动态调整 |

---

## 5. AI Review 提问

```text
我正在学习 g_config 配置读取函数。
我整理了 module/key/default 表，并对比了 process.env。
请你检查：
1. 我对 g_config 参数的理解是否正确？
2. g_config 和 process.env 的类比是否准确？
3. 哪些配置适合动态配置，哪些不适合？
4. 默认值设计有什么风险？
5. 真实项目里配置读取还要注意什么？

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 6. 今日产出

- [ ] `g_config()` 阅读笔记
- [ ] `ConfigHelper` 模块常量表
- [ ] 3 个配置项表格
- [ ] 3 段 `g_config()` 伪代码
- [ ] `g_config` vs `process.env` 对照
- [ ] 配置读取流程图
- [ ] 默认值风险分析表
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 `g_config()` 解决什么问题
- [ ] 能解释 `module / key / default`
- [ ] 能说明为什么需要默认值
- [ ] 能判断默认值设置是否有业务风险
- [ ] 能读懂 `ConfigHelper` 常量的意义
- [ ] 能列出 3 个业务配置项
- [ ] 能用 `process.env` 类比 `g_config()`，并说出两者差异
- [ ] 能画出配置读取流程

---

## 8. 今日自测题

### 8.1 `g_config()` 常见三个参数是什么？

参考答案：`module`、`key`、`default`。

### 8.2 default 有什么作用？

参考答案：配置不存在或读取失败时兜底，避免业务直接报错。

### 8.3 `ConfigHelper` 的意义是什么？

参考答案：集中定义模块和配置常量，减少字符串拼写错误，也方便 IDE 提示和统一维护。

### 8.4 `g_config()` 和 `process.env` 最大差异是什么？

参考答案：`g_config()` 通常更业务化、可按模块分组，并可能支持动态配置来源；`process.env` 更常用于环境配置，通常在进程启动时确定。

### 8.5 支付开关的默认值为什么通常不建议设为 `true`？

参考答案：如果配置读取失败，默认 `true` 可能错误开放支付能力，涉及资金风险；更安全的默认值通常是 `false`。

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
我正在进行 Week 04 Day 01：g_config 函数 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 04 README](./README.md)
