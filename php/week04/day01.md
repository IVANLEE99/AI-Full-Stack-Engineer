# Week 04 Day 01：g_config 函数

> 所属周：Week 04：配置中心 + 站点 API  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-core`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解项目中的配置读取函数 `g_config()`：它通常接收 `module`、`key`、`default` 三类信息，用来从配置系统中读取业务配置，并在没有配置时返回默认值。

今天你要真正掌握这一句话：

> `g_config(module, key, default)` 可以类比 `process.env.KEY ?? default`，但它通常比环境变量更业务化：支持按模块分组、默认值兜底、配置中心或数据库动态更新。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解为什么不能把配置硬编码在代码里
2. 理解什么是业务配置
3. 理解 `module / key / default`
4. 阅读 `fun_helpers.php` 中的 `g_config()`
5. 阅读 `ConfigHelper` 中的模块常量
6. 找 3 个真实配置项
7. 对比 Node 的 `process.env`
8. 画出配置读取流程
9. 完成今日自测和 AI Review

---

## 1. 学习内容

### 1.1 为什么需要配置？

如果业务开关写死在代码里：

```php
$isPayEnabled = true;
```

以后要关闭支付能力，就必须改代码、发版、部署。

更好的方式是配置化：

```php
$isPayEnabled = g_config('pay', 'enable', true);
```

这样业务开关可以通过配置系统调整，而不是每次都改代码。

---

### 1.2 什么是业务配置？

常见业务配置：

| 配置 | 示例 | 作用 |
|---|---|---|
| 功能开关 | 是否开启优惠券 | 控制功能是否展示 |
| 支付渠道 | 是否开启 Stripe | 控制支付方式 |
| 首页配置 | banner、导航 | 控制前端展示 |
| 风控阈值 | 最大下单金额 | 控制业务规则 |
| 文案配置 | 售后提示语 | 动态调整文案 |

---

### 1.3 `g_config(module, key, default)` 怎么理解？

常见形式：

```php
$value = g_config($module, $key, $default);
```

三个参数：

| 参数 | 含义 | 示例 |
|---|---|---|
| `module` | 配置所属模块 | `pay` / `site` / `order` |
| `key` | 配置项名称 | `enable_stripe` |
| `default` | 默认值 | `false` |

例子：

```php
$enabled = g_config('pay', 'enable_stripe', false);
```

意思：

```text
读取 pay 模块下 enable_stripe 配置；如果不存在，默认 false。
```

---

### 1.4 为什么需要 default？

配置系统可能：

- 配置不存在
- 配置中心暂时不可用
- 本地环境没有配置
- 新配置还没同步

如果没有默认值，代码可能报错。

有默认值：

```php
$enabled = g_config('site', 'show_banner', true);
```

即使配置不存在，也能继续运行。

---

### 1.5 Node.js 类比

Node 中常见：

```js
const enabled = process.env.ENABLE_STRIPE ?? 'false';
```

PHP 项目：

```php
$enabled = g_config('pay', 'enable_stripe', false);
```

对比：

| PHP | Node |
|---|---|
| `g_config('pay', 'enable', false)` | `process.env.PAY_ENABLE ?? false` |
| 按模块分组 | 通常靠变量名前缀 |
| 支持默认值 | `?? default` |
| 可能来自配置中心/DB | 通常来自 env 文件/环境变量 |

---

### 1.6 `ConfigHelper` 是什么？

项目里可能有 `ConfigHelper.php`，用来定义配置模块或 key 常量。

例如：

```php
class ConfigHelper
{
    public const MODULE_SITE = 'site';
    public const MODULE_ORDER = 'order';
    public const MODULE_PAY = 'pay';
}
```

这样写的好处：

```php
g_config(ConfigHelper::MODULE_PAY, 'enable_stripe', false);
```

比到处写字符串更安全：

```php
g_config('pay', 'enable_stripe', false);
```

常量可以减少拼写错误。

---

### 1.7 配置读取流程

可以先画成：

```text
业务代码
  ↓
g_config(module, key, default)
  ↓
ConfigHelper / 配置读取逻辑
  ↓
本地缓存 / DB / 配置中心
  ↓
找到配置：返回配置值
找不到配置：返回 default
```

---

## 2. 源码阅读

- `mall-core/common/libraries/App/fun_helpers.php`
- `mall-core/common/libraries/App/Utils/ConfigHelper.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读目标：

| 文件 | 重点 |
|---|---|
| `fun_helpers.php` | 找 `g_config()` 的参数和返回逻辑 |
| `ConfigHelper.php` | 找 module/key 常量 |

记录：

| 配置项 | 记录 |
|---|---|
| `g_config` 参数 |  |
| 默认值如何处理 |  |
| 配置来源 |  |
| ConfigHelper 模块常量 |  |

---

## 3. 练习任务

### 练习 1：列 3 个配置项

| module | key | default | 含义 | 影响 |
|---|---|---|---|---|
| `site` |  |  | 站点配置 | 前端展示 |
| `order` |  |  | 订单配置 | 下单逻辑 |
| `pay` |  |  | 支付配置 | 支付渠道 |

---

### 练习 2：写伪代码

```php
$showBanner = g_config('site', 'show_banner', true);

if ($showBanner) {
    // 返回 banner 配置给前端
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

---

## 4. JS/Node.js 类比

| PHP 配置 | Node 类比 | 差异 |
|---|---|---|
| `g_config()` | `process.env` / config service | 更业务化 |
| `module` | env prefix | PHP 按模块分组 |
| `key` | env key | 配置项名 |
| `default` | `?? default` | 兜底值 |
| `ConfigHelper` | config constants | 避免魔法字符串 |

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
```

---

## 6. 今日产出

- [ ] `g_config()` 阅读笔记
- [ ] `ConfigHelper` 模块常量表
- [ ] 3 个配置项表格
- [ ] `g_config` vs `process.env` 对照
- [ ] 配置读取流程图
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 `g_config()` 解决什么问题
- [ ] 能解释 `module / key / default`
- [ ] 能说明为什么需要默认值
- [ ] 能读懂 `ConfigHelper` 常量
- [ ] 能列出 3 个业务配置项
- [ ] 能用 `process.env` 类比 `g_config()`

---

## 8. 今日自测题

### 8.1 `g_config()` 常见三个参数是什么？

参考答案：`module`、`key`、`default`。

### 8.2 default 有什么作用？

参考答案：配置不存在或读取失败时兜底，避免业务直接报错。

### 8.3 `ConfigHelper` 的意义是什么？

参考答案：集中定义模块和配置常量，减少字符串拼写错误。

### 8.4 `g_config()` 和 `process.env` 最大差异是什么？

参考答案：`g_config()` 通常更业务化、可按模块分组，并可能支持动态配置来源。

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
