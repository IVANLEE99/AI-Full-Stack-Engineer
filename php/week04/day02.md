# Week 04 Day 02：配置 API 全链路

> 所属周：Week 04：配置中心 + 站点 API  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-core`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

追踪 `ConfigController` 从接收前端请求到调用 Service、读取配置、返回 JSON 的完整链路，理解“站点配置 API”如何把后端配置变成前端可用的数据。

今天你要真正掌握这一句话：

> 配置 API 的全链路通常是：前端请求 settings/config 接口 → Controller 接收参数 → Service 组织业务配置 → Helper/配置中心读取数据 → Controller 返回统一 JSON 给前端。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解前端为什么需要“配置 API”
2. 明确站点配置通常包含哪些内容
3. 找到 `ConfigController.php` 的入口方法
4. 顺着 Controller 追踪到 Service 或 Helper
5. 记录每一层负责什么，不要只抄代码
6. 画出“前端 → Controller → Service → 配置读取 → 响应”的链路图
7. 对比 Node.js 里的 `/settings` 接口
8. 检查配置如何影响前端展示
9. 用 AI Review 检查你的链路是否完整

---

## 1. 学习内容

### 1.1 前端为什么需要配置 API？

前端页面经常需要一些“不是写死在前端代码里”的信息，例如：

| 配置 | 前端用途 |
|---|---|
| 站点名称 | 页面标题、分享标题 |
| logo 地址 | 顶部导航、登录页展示 |
| banner 列表 | 首页轮播图 |
| 客服联系方式 | 售后页、订单页展示 |
| 是否展示某个功能 | 控制按钮、入口、活动位 |
| 支付渠道开关 | 控制支付方式列表 |

如果这些内容都写死在前端，运营想改一个 banner 就要重新发前端版本。更合理的方式是：

```text
前端启动或进入页面时，请求后端配置 API。
```

例如：

```text
GET /site/config
GET /frontapi/config
GET /settings
```

后端返回：

```json
{
  "site_name": "Demo Mall",
  "logo": "https://example.com/logo.png",
  "show_coupon": true,
  "support_phone": "400-000-0000"
}
```

小白重点：配置 API 是前端和后端配置系统之间的桥梁。

---

### 1.2 什么是 ConfigController？

`ConfigController` 通常是配置 API 的入口。

你可以先把它理解成：

```text
专门处理配置相关 HTTP 请求的 Controller。
```

在 PHP MVC 项目中，请求链路通常是：

```text
HTTP 请求
  ↓
路由
  ↓
ConfigController
  ↓
Service / Helper
  ↓
配置来源
  ↓
JSON 响应
```

如果类里有类似方法：

```php
<?php

public function actionIndex(): array
{
    return $this->success($this->configService->getSiteConfig());
}
```

你要读懂：

- `actionIndex()` 是接口入口
- `$this->configService->getSiteConfig()` 是业务配置组织逻辑
- `success()` 是统一响应封装

---

### 1.3 怎么追踪 Controller → Service？

读源码时不要从第一行读到最后一行，而是带问题追踪。

第一步，找入口方法：

| 你要找什么 | 常见形式 |
|---|---|
| 默认配置接口 | `actionIndex()` / `actionConfig()` |
| 站点配置接口 | `actionSite()` / `actionSetting()` |
| 前端配置接口 | `actionFrontend()` |

第二步，找方法内部调用了谁：

```php
<?php

$config = $this->configService->getSiteConfig();
```

或者：

```php
<?php

$config = ConfigService::getSiteConfig();
```

第三步，跳到 Service 方法，看它是否继续调用：

```php
<?php

$name = g_config('site', 'name', '默认商城');
$logo = g_config('site', 'logo', '');
```

最终你要画出：

```text
ConfigController::actionXxx()
  ↓
ConfigService::getXxxConfig()
  ↓
g_config('site', 'xxx', default)
  ↓
返回给前端
```

---

### 1.4 每一层分别负责什么？

配置链路里最容易混淆的是：Controller 和 Service 到底谁负责什么？

| 层级 | 主要职责 | 不建议做什么 |
|---|---|---|
| Controller | 接收请求、校验基础参数、返回响应 | 写大量业务拼装逻辑 |
| Service | 组织配置、处理业务含义、决定返回结构 | 直接输出 HTTP 响应 |
| Helper/配置函数 | 读取配置值、处理默认值 | 知道具体页面展示逻辑 |
| 前端 | 使用配置展示页面 | 直接读数据库或配置中心 |

一个比较清晰的写法：

```php
<?php

final class ConfigController
{
    public function actionSite(): array
    {
        $config = $this->configService->getSiteConfig();

        return $this->success($config);
    }
}
```

Service 负责组织：

```php
<?php

final class ConfigService
{
    public function getSiteConfig(): array
    {
        return [
            'site_name' => g_config('site', 'name', '默认商城'),
            'logo' => g_config('site', 'logo', ''),
            'show_coupon' => g_config('site', 'show_coupon', false),
        ];
    }
}
```

---

### 1.5 配置如何影响前端？

配置 API 最终会改变前端展示或行为。

例如：

```json
{
  "show_coupon": false
}
```

前端可能会做：

```js
if (config.show_coupon) {
  showCouponEntry();
}
```

这意味着后端一个配置值，会影响前端是否展示优惠券入口。

你阅读配置 API 时，要记录：

| 配置字段 | 后端来源 | 前端影响 |
|---|---|---|
| `site_name` | `site.name` | 页面标题 |
| `logo` | `site.logo` | 顶部 logo |
| `show_coupon` | `site.show_coupon` | 优惠券入口 |
| `support_phone` | `site.support_phone` | 客服联系方式 |

小白重点：不要只看“返回了什么字段”，还要想“这个字段会影响哪个页面”。

---

### 1.6 Node.js 类比：settings 接口

Node/Express 中可能这样写：

```js
app.get('/settings', async (req, res) => {
  const config = await configService.getSiteConfig();

  res.json({
    code: 0,
    data: config,
  });
});
```

PHP 中类似：

```php
<?php

public function actionSettings(): array
{
    $config = $this->configService->getSiteConfig();

    return $this->success($config);
}
```

类比关系：

| PHP | Node.js |
|---|---|
| `ConfigController` | Express route/controller |
| `ConfigService` | config service |
| `g_config()` | config provider / env / remote config client |
| `success($data)` | `res.json({ code, data })` |

---

### 1.7 今日链路图模板

你最终要画出类似：

```text
前端首页
  ↓ 请求 /site/config
ConfigController::actionSite()
  ↓ 调用
ConfigService::getSiteConfig()
  ↓ 调用
g_config('site', 'name', '默认商城')
g_config('site', 'logo', '')
g_config('site', 'show_coupon', false)
  ↓ 返回
{
  site_name,
  logo,
  show_coupon
}
  ↓
前端展示页面
```

如果你能独立画出这个图，说明你已经开始具备读 PHP 企业项目链路的能力。

---

## 2. 源码阅读

- `site-api/controllers/ConfigController.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| 阅读点 | 记录 |
|---|---|
| Controller 类名 |  |
| 入口方法 |  |
| 调用的 Service/Helper |  |
| 返回字段 |  |
| 使用了哪些配置 key |  |
| 前端受影响页面 |  |

---

## 3. 练习任务

### 练习 1：追踪 ConfigController → Service

记录格式：

```text
入口方法：
调用的 Service：
Service 方法：
读取的配置：
返回字段：
```

### 练习 2：记录配置如何影响前端

| 配置字段 | 前端页面 | 影响内容 |
|---|---|---|
|  |  |  |
|  |  |  |
|  |  |  |

### 练习 3：画配置 API 全链路图

要求至少包含：

- 前端请求
- Controller
- Service
- `g_config()` 或配置读取逻辑
- 返回 JSON
- 前端使用配置

---

## 4. JS/Node.js 类比

- 配置 API ≈ 前端 settings 接口
- `ConfigController` ≈ Express `/settings` route controller
- `ConfigService` ≈ Node 里的 settings/config service
- `g_config()` ≈ config provider / remote config client

---

## 5. AI Review 提问

```text
我正在追踪 ConfigController 配置 API 全链路。
我已经画出了前端 → Controller → Service → g_config → JSON 响应的流程。
请你检查：
1. 我的链路是否完整？
2. Controller 和 Service 的职责是否分清楚？
3. 哪些配置字段可能影响前端展示？
4. 我的 Node settings 接口类比是否准确？
5. 真实项目里配置 API 有哪些风险？
```

---

## 6. 今日产出

- [ ] 配置 API 链路图
- [ ] `ConfigController` 阅读笔记
- [ ] Controller → Service 调用记录
- [ ] 配置字段影响前端的表格
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能找到 `ConfigController` 的入口方法
- [ ] 能追踪 Controller 调用了哪个 Service/Helper
- [ ] 能说清楚每一层的职责
- [ ] 能列出至少 3 个返回给前端的配置字段
- [ ] 能说明配置字段如何影响前端页面
- [ ] 能画出完整配置 API 链路

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
我正在进行 Week 04 Day 02：配置 API 全链路 的学习。
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
