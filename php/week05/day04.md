# Week 05 Day 04：鉴权、公参与反查链路

> 所属周：Week 05：BFF 网关架构  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`mall-gateway`  
> 类型：架构理解  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

从一个前端 API 出发，反查它在网关中的路由、Controller、鉴权、公参注入、HTTP Request 类和内网服务路径，理解一次 BFF 请求的完整入口链路。

今天你要真正掌握这一句话：

> 反查链路就是从浏览器或前端代码里的 URL 开始，沿着路由、Controller、Filter/鉴权、公参注入、Request 客户端一路追到内网服务，最终知道“这个接口到底经过了哪些层”。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解什么是“从前端 API 反查后端链路”
2. 理解鉴权、白名单、公参注入分别解决什么问题
3. 阅读 `AuthApiController.php` 或相关基类中的 token 处理
4. 找一个支付 API，例如 `pay/pay/methods`
5. 从前端 URL 反查到网关路由
6. 从网关 action 追到 `PayRequest`
7. 从 `PayRequest` 追到内网服务 path
8. 标注哪些节点做鉴权、哪些节点可白名单
9. 画完整链路图并让 AI Review 检查

---

## 1. 学习内容

### 1.1 什么是反查链路？

平时读后端代码，你可能从 Controller 开始看。但真实排查问题时，经常是从前端或接口 URL 开始：

```text
前端请求失败：/pay/pay/methods
```

你要能反查：

```text
这个 URL 对应哪个模块？
哪个 Controller？
哪个 action？
是否需要登录？
会注入哪些公共参数？
调用哪个 Request 类？
最终请求哪个内网服务？
```

这就是反查链路。

小白重点：反查链路是后端排查问题、理解项目、定位接口的核心能力。

---

### 1.2 鉴权是什么？

鉴权是判断“当前请求是谁发的，有没有权限访问”。

常见方式：

| 鉴权方式 | 说明 |
|---|---|
| token | 前端请求 header 或参数里携带 token |
| session | 服务端保存登录态 |
| JWT | token 中包含用户信息和签名 |
| API key | 服务间调用或开放平台常见 |

BFF 网关常见做法：

```text
请求进入网关
  ↓
读取 token
  ↓
校验 token 是否有效
  ↓
得到 user_id
  ↓
把 user_id 传给后续服务
```

伪代码：

```php
<?php

$userId = $this->requireLogin();
```

---

### 1.3 什么是白名单？

不是所有接口都需要登录。

例如：

- 登录接口
- 注册接口
- 首页配置接口
- 商品列表接口
- 支付回调接口（但需要签名校验）

这些接口可能在鉴权白名单里。

白名单可以理解为：

```text
这些接口可以跳过普通登录鉴权，但不代表没有任何安全校验。
```

注意：支付回调这类接口即使不走用户登录，也必须有签名校验或来源校验。

---

### 1.4 什么是公参注入？

公参就是很多接口都需要的公共参数。

常见公参：

| 公参 | 说明 |
|---|---|
| `user_id` | 当前登录用户 ID |
| `site_id` | 当前站点 |
| `lang` | 当前语言 |
| `channel` | 来源渠道，如 H5/App/小程序 |
| `device_id` | 设备标识 |
| `trace_id` | 链路追踪 ID |

公参注入就是在请求进入后，统一把这些信息放到上下文中，避免每个 action 重复解析。

Node.js 类比：

```js
app.use((req, res, next) => {
  req.context = {
    userId: parseToken(req.headers.authorization),
    lang: req.headers['x-lang'],
    traceId: req.headers['x-trace-id'],
  };
  next();
});
```

PHP 网关里可能通过基类、Filter、behavior 或 middleware 实现类似能力。

---

### 1.5 如何反查 `pay/pay/methods`？

假设前端请求：

```text
GET /pay/pay/methods
```

你可以按下面步骤反查：

1. 拆 URL

```text
/pay/pay/methods
  ↓
Pay module
PayController
actionMethods
```

2. 找 Controller 文件

```text
mall-gateway/frontapi/modules/Pay/controllers/PayController.php
```

3. 找 action

```php
<?php

public function actionMethods(): array
{
    // ...
}
```

4. 看是否有鉴权

```php
<?php

$userId = $this->getUserId();
```

5. 看调用哪个 Request 类

```php
<?php

$result = $this->payRequest->methods($params);
```

6. 跳到 `PayRequest` 看 path

```php
<?php

return $this->get('/pay/methods', $params);
```

最终画成：

```text
前端 /pay/pay/methods
  ↓
PayController::actionMethods()
  ↓ 鉴权/公参
PayRequest::methods()
  ↓
内网支付服务 /pay/methods
```

---

### 1.6 鉴权与白名单怎么标注？

画链路图时，不要只画调用，还要标注安全节点。

示例：

```text
前端请求 /pay/pay/methods
  ↓
AuthApiController / Filter
  - 需要登录：是
  - 白名单：否
  - 注入 user_id/site_id/lang/trace_id
  ↓
PayController::actionMethods()
  ↓
PayRequest::methods(params + 公参)
  ↓
支付服务
```

记录表：

| 节点 | 是否鉴权 | 是否白名单 | 注入公参 | 备注 |
|---|---|---|---|---|
| `/pay/pay/methods` | 是 | 否 | user_id、site_id | 支付方式与用户相关 |
| `/site/config` | 否/可选 | 是 | site_id、lang | 公共配置接口 |
| `/pay/callback` | 否 | 是 | trace_id | 但必须签名校验 |

---

### 1.7 常见风险

BFF 鉴权和链路反查里常见风险：

| 风险 | 后果 |
|---|---|
| 应鉴权接口进了白名单 | 未登录用户访问敏感数据 |
| 白名单接口缺少签名校验 | 被伪造请求 |
| 公参被前端伪造 | 用户越权、站点错乱 |
| trace_id 缺失 | 线上问题难追踪 |
| 网关和内网服务鉴权重复/冲突 | 排查困难 |

小白重点：白名单不是“安全放行”，只是“不走普通登录鉴权”。

---

## 2. 源码阅读

- `mall-gateway/frontapi/modules/AuthApiController.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| 阅读点 | 记录 |
|---|---|
| token 从哪里读取 |  |
| 用户 ID 如何得到 |  |
| 白名单在哪里定义 |  |
| 公参有哪些 |  |
| 公参如何传给下游 |  |
| Filter/behavior 在哪里配置 |  |

---

## 3. 练习任务

### 练习 1：反查 `pay/pay/methods` 完整链路

记录：

```text
前端 URL：
模块：
Controller：
action：
是否需要登录：
注入公参：
调用的 Request 类：
内网服务 path：
返回字段：
```

### 练习 2：画路由链路图

至少包含：

- 前端 URL
- 网关路由
- 鉴权/Filter
- Controller/action
- Request 类
- 内网服务

### 练习 3：标注鉴权与白名单

| API | 是否需要登录 | 是否白名单 | 额外安全要求 |
|---|---|---|---|
|  |  |  |  |
|  |  |  |  |
|  |  |  |  |

---

## 4. JS/Node.js 类比

- 公参注入 ≈ middleware 统一设置 `req.context`
- 鉴权 ≈ auth middleware
- 白名单 ≈ public routes / skip auth routes
- 反查 ≈ 从前端 devtools 的 network 请求一路追到 route handler 和 service client
- trace_id ≈ request id / correlation id

---

## 5. AI Review 提问

```text
我正在反查 BFF 网关 API 链路。
我选择了 pay/pay/methods，画出了前端 URL → 鉴权 → Controller → Request → 内网服务的完整路径。
请你检查：
1. 我的链路是否完整？
2. 鉴权和白名单标注是否合理？
3. 公参注入理解是否正确？
4. 哪些接口即使白名单也必须做签名或来源校验？
5. 与 Node middleware / devtools 追请求的类比是否准确？
```

---

## 6. 今日产出

- [ ] `pay/pay/methods` 完整反查链路
- [ ] 路由链路图
- [ ] 鉴权与白名单标注表
- [ ] 公参注入笔记
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能独立从前端 API 反查到网关 action
- [ ] 能从 action 追踪到 HTTP Request 类
- [ ] 能说明 token 鉴权大致流程
- [ ] 能说明公参注入解决什么问题
- [ ] 能标注哪些接口需要登录、哪些可能白名单
- [ ] 能说出白名单接口的安全风险

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
我正在进行 Week 05 Day 04：鉴权、公参与反查链路 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 05 README](./README.md)
