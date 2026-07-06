# Week 05 Day 05：Laravel 对比与类比日

> 所属周：Week 05：BFF 网关架构  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`mall-gateway`  
> 类型：类比日  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

完成 Laravel `Http::` 客户端与项目 `*Request` 封装的对照，并把本周 BFF、HTTP Client、薄 Controller、鉴权、公参、反查链路做一次类比整理。

今天你要真正掌握这一句话：

> Laravel `Http::` 是通用 HTTP 客户端，项目里的 `*Request` 是面向内网服务的业务化封装；前者像工具箱，后者像团队为某个服务定制好的 SDK。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Week 05 Day 01-04 的 BFF 笔记
2. 阅读 Laravel HTTP Client 的基本用法
3. 对比 `Http::get/post` 与项目 `PayRequest` / `OrderRequest`
4. 理解通用 HTTP 客户端和业务 Request 封装的区别
5. 整理 Laravel、PHP 项目、Node/axios 三方类比
6. 完成本周类比打卡表
7. 总结 BFF 网关里每个组件的职责
8. 找出自己还不理解的 3 个点
9. 用 AI Review 检查对照是否抓住关键差异

---

## 1. 学习内容

### 1.1 Laravel HTTP Client 是什么？

Laravel 提供了 `Http` facade，用来发 HTTP 请求。

例如：

```php
<?php

use Illuminate\Support\Facades\Http;

$response = Http::get('https://api.example.com/users', [
    'page' => 1,
]);

$data = $response->json();
```

它解决的是：

```text
在 Laravel 项目里更方便地发 HTTP 请求。
```

常见能力：

| 能力 | 示例 |
|---|---|
| GET 请求 | `Http::get($url, $params)` |
| POST 请求 | `Http::post($url, $data)` |
| 设置 header | `Http::withHeaders([...])` |
| 设置 token | `Http::withToken($token)` |
| 设置超时 | `Http::timeout(3)` |
| 解析 JSON | `$response->json()` |

---

### 1.2 项目 `*Request` 封装是什么？

项目里的 `PayRequest`、`OrderRequest` 通常不是通用工具，而是针对内网服务的业务封装。

例如：

```php
<?php

$methods = $this->payRequest->methods([
    'user_id' => $userId,
]);
```

它隐藏了：

- 支付服务 baseURL
- 具体 path
- 公共 header
- 超时
- 响应解析
- 错误处理

你可以把它理解成：

```text
项目内部服务 SDK。
```

---

### 1.3 `Http::` vs `*Request` 对照

| 对比项 | Laravel `Http::` | 项目 `*Request` |
|---|---|---|
| 定位 | 通用 HTTP 客户端 | 内网服务业务客户端 |
| 使用方式 | 每次指定 URL/path | 方法名封装具体 path |
| baseURL | 调用时设置或配置 | 类里统一定义 |
| 业务含义 | 较弱 | 较强 |
| 复用方式 | 链式调用 | 类方法复用 |
| 错误处理 | 开发者自行决定 | 通常基类统一处理 |
| 类比 | HTTP 工具箱 | 服务 SDK |

例子：

```php
<?php

// Laravel 通用写法
$response = Http::baseUrl('http://pay-service.internal')
    ->timeout(3)
    ->get('/pay/methods', ['user_id' => $userId]);
```

项目封装写法：

```php
<?php

$methods = $this->payRequest->methods(['user_id' => $userId]);
```

第二种更适合大型项目，因为调用方不需要知道底层 URL 细节。

---

### 1.4 为什么项目还要封装 `*Request`？

你可能会问：既然 Laravel 已经有 `Http::`，为什么还要封装？

原因是：企业项目需要统一规范。

如果到处写：

```php
Http::get('http://pay-service.internal/pay/methods');
Http::post('http://order-service.internal/order/create');
```

会导致：

- URL 散落
- 调用方重复写参数
- 错误处理不一致
- 日志不一致
- 服务迁移时改动范围大

封装后：

```php
$this->payRequest->methods($params);
$this->orderRequest->create($params);
```

好处：

| 好处 | 说明 |
|---|---|
| 更可读 | 方法名直接表达业务 |
| 更好维护 | 服务地址集中管理 |
| 更好测试 | 可以 mock Request 类 |
| 更少重复 | header、timeout、日志统一 |
| 更安全 | 鉴权、公参可集中处理 |

---

### 1.5 Node.js / axios 对照

Node 中也有类似分层。

通用 axios：

```js
const res = await axios.get('http://pay-service.internal/pay/methods', {
  params: { user_id: userId },
});
```

封装 payClient：

```js
const payClient = axios.create({
  baseURL: 'http://pay-service.internal',
  timeout: 3000,
});

const methods = await payClient.get('/pay/methods', {
  params: { user_id: userId },
});
```

再封装业务 SDK：

```js
const payApi = {
  methods(userId) {
    return payClient.get('/pay/methods', {
      params: { user_id: userId },
    });
  },
};
```

PHP 项目里的 `PayRequest` 更接近最后这个业务 SDK。

---

### 1.6 本周类比打卡

请完成这张表：

| PHP/BFF 概念 | Laravel 类比 | Node.js 类比 | 你的理解 |
|---|---|---|---|
| BFF 网关 | API 层 | Express/NestJS 聚合层 |  |
| `BaseApi` | Controller 基类/middleware | middleware + response helper |  |
| `PayController` | Controller action | route handler |  |
| `PayRequest` | 封装后的 HTTP client | payApi / axios client |  |
| `Http::` | 通用 HTTP Client | axios/fetch |  |
| 鉴权 | middleware/guard | auth middleware |  |
| 公参注入 | request context | `req.context` |  |
| 白名单 | except routes | public routes |  |

---

### 1.7 对照时最容易犯的错误

常见误区：

| 误区 | 修正 |
|---|---|
| 认为 `Http::` 和 `PayRequest` 完全一样 | `Http::` 更通用，`PayRequest` 更业务化 |
| 认为 BFF 可以写所有业务 | BFF 负责聚合和适配，核心规则在下游服务 |
| 认为白名单就是不需要安全 | 白名单只是不走普通登录，也可能需要签名 |
| 认为 Controller 越全越好 | 网关 Controller 应该薄 |
| 认为 baseURL 可以随便写 | 应集中配置，便于环境切换和维护 |

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议回看：

- `PayRequest.php`
- `OrderRequest.php`
- `PayController.php`
- `AuthApiController.php`
- Laravel HTTP Client 文档基础用法

记录：

| 对照对象 | 你学到的点 | 不理解的点 |
|---|---|---|
| Laravel `Http::` |  |  |
| 项目 `PayRequest` |  |  |
| axios/fetch |  |  |

---

## 3. 练习任务

### 练习 1：写 1 页 Laravel `Http::` vs `*Request` 对照

至少包含：

- 定位差异
- baseURL 管理
- path 封装
- 参数传递
- header/token
- 超时
- 错误处理
- 适用场景

### 练习 2：完成本周类比打卡

完成 `PHP/BFF → Laravel → Node.js` 三列表。

### 练习 3：写一个三版本调用示例

同一个“获取支付方式”接口，分别用：

1. Laravel `Http::`
2. 项目 `PayRequest`
3. Node axios

写出伪代码即可。

---

## 4. JS/Node.js 类比

- Laravel `Http::` ≈ axios/fetch
- 项目 `*Request` ≈ 封装好的 service client / SDK
- `BaseRequest` ≈ axios instance + interceptor
- BFF Controller ≈ Express route handler
- 公参注入 ≈ middleware 设置 `req.context`

---

## 5. AI Review 提问

```text
我正在做 Laravel Http:: 与项目 *Request 的对照。
我已经整理了通用 HTTP 客户端、业务 Request 封装、axios/fetch 的类比。
请你检查：
1. 我的对照是否抓住关键差异？
2. 哪些地方我把通用 HTTP 客户端和业务 SDK 混淆了？
3. 我的 Laravel 和 Node.js 类比是否准确？
4. BFF 网关中 *Request 封装还应注意哪些工程风险？
5. 我本周的 BFF 知识图谱还缺什么？
```

---

## 6. 今日产出

- [ ] Laravel `Http::` vs `*Request` 对照笔记
- [ ] 本周类比打卡表
- [ ] 三版本调用示例
- [ ] 本周 BFF 知识图谱
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 完成 Laravel `Http::` 对照
- [ ] 能解释 `Http::` 和 `*Request` 的定位差异
- [ ] 能用 axios/fetch 类比两种写法
- [ ] 能完成本周类比打卡
- [ ] 能说出 BFF 网关至少 5 个核心概念

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
我正在进行 Week 05 Day 05：Laravel 对比与类比日 的学习。
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
