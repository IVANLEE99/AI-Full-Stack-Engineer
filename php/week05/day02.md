# Week 05 Day 02：HTTP 客户端封装

> 所属周：Week 05：BFF 网关架构  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`mall-gateway`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

掌握 `*Request` 类如何把 BFF 网关中的一次业务调用转发到内网服务，理解 HTTP Client 封装里的 `baseURL`、`path`、参数、超时、错误处理和统一响应解析。

今天你要真正掌握这一句话：

> `PayRequest`、`OrderRequest` 这类 HTTP 客户端封装，可以类比 `axios.create({ baseURL })`：先固定目标服务地址，再用不同方法拼接 path 和参数，最终把网关请求转发给内网服务。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解为什么网关不直接到处写 curl/http 请求
2. 理解 HTTP Client 封装解决什么重复问题
3. 阅读 `PayRequest.php`，找 baseURL 从哪里来
4. 阅读 `OrderRequest.php`，对比两个 Request 类结构
5. 理解 `getBaseUrl()` 与 path 拼接
6. 理解参数、header、token、超时如何传递
7. 用 Laravel HTTP Client 和 axios 做类比
8. 画出“Controller → Request 类 → 内网服务”的调用图
9. 总结封装层还应该包含哪些能力

---

## 1. 学习内容

### 1.1 为什么需要 HTTP 客户端封装？

在 BFF 网关中，经常需要调用内网服务：

```text
支付服务
订单服务
商品服务
用户服务
库存服务
```

如果每个 Controller 里都直接写 HTTP 请求，会出现大量重复：

```php
<?php

$url = 'http://pay-service/api/pay/methods';
$params = ['user_id' => $userId];
$response = http_post($url, $params);
```

问题是：

- 服务地址到处散落，改地址很痛苦
- 超时配置不统一
- header/token 处理不统一
- 错误处理不统一
- 日志记录不统一
- 响应解析不统一

所以项目通常会封装成：

```php
<?php

$methods = $this->payRequest->getMethods($userId);
```

Controller 不关心具体 URL 和 HTTP 细节，只关心业务调用。

---

### 1.2 `*Request` 类是什么？

在 `mall-gateway` 这类项目里，`PayRequest.php`、`OrderRequest.php` 通常代表：

```text
专门调用某个内网服务的 HTTP 客户端封装类。
```

例如：

| 类 | 可能负责 |
|---|---|
| `PayRequest` | 调用支付服务 |
| `OrderRequest` | 调用订单服务 |
| `GoodsRequest` | 调用商品服务 |
| `UserRequest` | 调用用户服务 |

你可以把它理解成：

```text
一个服务一个 Request 类，一个方法对应一个内网接口。
```

示例伪代码：

```php
<?php

final class PayRequest
{
    public function getMethods(array $params): array
    {
        return $this->get('/pay/methods', $params);
    }
}
```

---

### 1.3 `getBaseUrl()` 是什么？

`baseURL` 是目标服务的基础地址。

例如支付服务地址：

```text
http://pay-service.internal
```

具体接口 path：

```text
/pay/methods
```

最终请求地址：

```text
http://pay-service.internal/pay/methods
```

PHP 伪代码：

```php
<?php

final class PayRequest extends BaseRequest
{
    protected function getBaseUrl(): string
    {
        return 'http://pay-service.internal';
    }

    public function methods(array $params): array
    {
        return $this->get('/pay/methods', $params);
    }
}
```

小白重点：`baseURL` 决定“请求哪个服务”，`path` 决定“请求服务里的哪个接口”。

---

### 1.4 path 拼接要注意什么？

拼接 URL 时最容易出问题的是 `/`。

例如：

```text
baseURL = http://pay-service.internal/
path    = /pay/methods
```

如果简单拼接，可能得到：

```text
http://pay-service.internal//pay/methods
```

虽然有些客户端能兼容，但规范写法应该统一处理。

你读源码时可以观察：

| 问题 | 记录 |
|---|---|
| baseURL 末尾有没有 `/` |  |
| path 开头有没有 `/` |  |
| 是否有统一拼接方法 |  |
| 是否会自动 trim `/` |  |

---

### 1.5 封装层应该包含哪些能力？

一个成熟的 HTTP Request 封装通常不只是“发请求”。

常见能力：

| 能力 | 说明 |
|---|---|
| baseURL 管理 | 每个内网服务有自己的基础地址 |
| path 拼接 | 避免 URL 拼错 |
| 参数传递 | GET query、POST body |
| header 注入 | token、traceId、语言、渠道 |
| 超时设置 | 防止接口一直卡住 |
| 重试策略 | 对可重试错误做有限重试 |
| 日志记录 | 记录请求耗时、错误信息 |
| 响应解析 | 统一解析 `code/message/data` |
| 异常转换 | 把 HTTP 错误转成业务可理解的异常 |

今天你不需要全部实现，但要知道读代码时可以看这些点。

---

### 1.6 Controller 如何调用 Request 类？

BFF 中常见链路：

```text
PayController
  ↓
PayRequest
  ↓
支付内网服务
```

伪代码：

```php
<?php

final class PayController extends BaseApi
{
    public function actionMethods(): array
    {
        $userId = $this->getUserId();

        $methods = $this->payRequest->methods([
            'user_id' => $userId,
        ]);

        return $this->success($methods);
    }
}
```

Controller 负责：

- 鉴权
- 取参数
- 调用 Request 类
- 返回响应

Request 类负责：

- 拼目标地址
- 发 HTTP 请求
- 解析内网服务响应

---

### 1.7 Laravel HTTP Client 对照

Laravel 中可能这样写：

```php
<?php

$response = Http::baseUrl('http://pay-service.internal')
    ->timeout(3)
    ->get('/pay/methods', [
        'user_id' => $userId,
    ]);
```

项目 `PayRequest` 封装后可能变成：

```php
<?php

$methods = $this->payRequest->methods([
    'user_id' => $userId,
]);
```

对比：

| Laravel HTTP | 项目 `*Request` |
|---|---|
| `Http::baseUrl()` | `getBaseUrl()` |
| `->get($path, $params)` | `$this->get($path, $params)` |
| `->timeout(3)` | 基类统一 timeout |
| 手动处理响应 | 基类统一解析响应 |

---

### 1.8 Node.js / axios 类比

Node 中常见：

```js
const payClient = axios.create({
  baseURL: 'http://pay-service.internal',
  timeout: 3000,
});

const res = await payClient.get('/pay/methods', {
  params: { user_id: userId },
});
```

PHP 中 `PayRequest` 类似：

```php
<?php

final class PayRequest extends BaseRequest
{
    protected function getBaseUrl(): string
    {
        return 'http://pay-service.internal';
    }

    public function methods(array $params): array
    {
        return $this->get('/pay/methods', $params);
    }
}
```

类比关系：

| PHP | Node.js |
|---|---|
| `PayRequest` | `payClient` |
| `getBaseUrl()` | `baseURL` |
| `get('/path')` | `axios.get('/path')` |
| `BaseRequest` | axios instance wrapper/interceptor |

---

## 2. 源码阅读

- `mall-gateway/services/http/PayRequest.php`
- `mall-gateway/services/http/OrderRequest.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| 阅读点 | `PayRequest` | `OrderRequest` |
|---|---|---|
| baseURL 来源 |  |  |
| 继承的基类 |  |  |
| 方法数量 |  |  |
| path 写法 |  |  |
| 参数传递方式 |  |  |
| 响应解析方式 |  |  |

---

## 3. 练习任务

### 练习 1：读 `PayRequest.php` 全文

记录：

```text
类名：
继承：
baseURL 来源：
方法列表：
每个方法对应的 path：
我看不懂的地方：
```

### 练习 2：对比 `OrderRequest.php`

| 对比项 | PayRequest | OrderRequest |
|---|---|---|
| 目标服务 |  |  |
| baseURL |  |  |
| 常见方法 |  |  |
| path 命名 |  |  |
| 参数结构 |  |  |

### 练习 3：解释 baseURL 与 path 拼接

写出 3 个例子：

| baseURL | path | 最终 URL |
|---|---|---|
|  |  |  |
|  |  |  |
|  |  |  |

---

## 4. JS/Node.js 类比

- `PayRequest` ≈ `axios.create({ baseURL: payServiceUrl })`
- `OrderRequest` ≈ `axios.create({ baseURL: orderServiceUrl })`
- `BaseRequest` ≈ axios 实例基类 + interceptor
- `getBaseUrl()` ≈ 固定服务地址
- path 方法 ≈ 具体 API 方法封装

---

## 5. AI Review 提问

```text
我正在学习 BFF 网关里的 HTTP 客户端封装。
我已经阅读了 PayRequest 和 OrderRequest，并对比了 baseURL、path、参数和响应处理。
请你检查：
1. 我对 *Request 类职责的理解是否正确？
2. getBaseUrl() 和 path 拼接是否理解准确？
3. 这个封装层还应该包含哪些能力？
4. 与 axios.create({ baseURL }) 的类比是否准确？
5. 真实企业项目里 HTTP 客户端封装最容易遗漏哪些风险？
```

---

## 6. 今日产出

- [ ] `PayRequest.php` 阅读笔记
- [ ] `OrderRequest.php` 对比表
- [ ] baseURL 与 path 拼接示例
- [ ] Controller → Request → 内网服务调用图
- [ ] HTTP 封装能力清单
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释为什么需要 HTTP 客户端封装
- [ ] 能说出 `PayRequest` / `OrderRequest` 的职责
- [ ] 能解释 `getBaseUrl()` 与 path 拼接
- [ ] 能说出 baseURL 配置位置或查找方法
- [ ] 能列出 HTTP 封装至少 5 个能力
- [ ] 能用 axios 或 Laravel HTTP Client 做准确类比

---

## 8. 今日自测题

### 8.1 为什么 BFF 网关不建议在每个 Controller 里直接写 HTTP 请求？

参考答案：因为服务地址、超时、header、错误处理、日志、响应解析等会到处散落且不统一。封装成 `*Request` 类后，Controller 只关心业务调用，底层细节集中管理，改地址或加日志时改一处即可。

### 8.2 `PayRequest`、`OrderRequest` 这类 `*Request` 类的职责是什么？

参考答案：每个类专门封装对某一个内网服务的调用，通常“一个服务一个 Request 类，一个方法对应一个内网接口”，对外隐藏 baseURL、path 和 HTTP 细节。

### 8.3 `getBaseUrl()` 和 path 分别决定什么？

参考答案：baseURL 决定“请求哪个服务”（如 `http://pay-service.internal`），path 决定“请求这个服务里的哪个接口”（如 `/pay/methods`），两者拼接成最终请求地址。

### 8.4 一个成熟的 HTTP Request 封装通常还包含哪些能力（至少说 3 个）？

参考答案：baseURL 管理、path 拼接、参数传递、header/token 注入、超时设置、重试策略、日志记录、统一响应解析、异常转换等，不只是“发请求”。

### 8.5 项目里的 `PayRequest` 和 axios 的 `axios.create({ baseURL })` 是什么类比关系？

参考答案：两者都是先固定目标服务地址，再用具体方法拼接 path 和参数发请求。`PayRequest` 相当于在 axios 实例（固定 baseURL 和 timeout）之上再封装出的业务客户端。

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
我正在进行 Week 05 Day 02：HTTP 客户端封装 的学习。
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
