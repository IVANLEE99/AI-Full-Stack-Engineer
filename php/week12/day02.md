# Week 12 Day 02：InternalServiceHelper

> 所属周：Week 12：跨服务调用 / InternalService / 全链路串联  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`store-api`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 TP8 `store-api` 中的 `InternalServiceHelper` 如何作为门店服务调用核心服务、订单服务、支付服务或售后服务的统一入口，掌握“Helper / Internal Client / 服务门面”的职责边界。

今天你要真正掌握这一句话：

> `InternalServiceHelper` 是门店服务访问内网服务的统一门面，类似 Node.js 项目里的 `internalServiceClient`：它应该负责服务地址、公共请求头、鉴权、trace_id、超时和错误规范，而不应该承载具体业务规则。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Week 11 TP8 的 Controller → Service → Model 分层
2. 理解为什么 TP8 门店服务需要调用其他服务
3. 找到 `InternalServiceHelper.php`，先看 public 方法清单
4. 记录它能调用哪些内网服务
5. 拆解一次 Helper 发起 HTTP 请求的流程
6. 理解公共 headers：鉴权、trace_id、request_id、service name
7. 理解 Helper 和 Service 的职责边界
8. 对比 `PayInternal` 与 `InternalServiceHelper`
9. 完成服务清单和风险检查

---

## 1. 学习内容

### 1.1 为什么 store-api 需要 `InternalServiceHelper`？

Week 11 学过 TP8 `store-api`，它主要负责门店相关 API。

但门店业务不可能只依赖自己，比如：

| 门店场景 | 可能需要调用 |
|---|---|
| 查看门店订单 | 订单服务 |
| 发起门店退款 | 售后/支付服务 |
| 查询支付状态 | 支付服务 |
| 获取用户信息 | 用户服务 |
| 同步商品库存 | 商品/核心服务 |

如果每个 Service 都自己写 HTTP：

```php
<?php

$response = HttpClient::post('http://order-service/internal/order/list', $params);
```

代码很快会失控。

`InternalServiceHelper` 的价值是：

```text
把门店服务访问其他内网服务的方式统一收口。
```

---

### 1.2 Helper 是什么？它和 Service 有什么区别？

在这里，你可以先这样理解：

| 角色 | 主要职责 |
|---|---|
| Controller | 接收前端/后台请求，调用 Service |
| Service | 组织门店业务逻辑 |
| InternalServiceHelper | 负责调用其他内网服务 |
| 其他服务 | 真正处理订单、支付、售后等领域逻辑 |

错误写法：

```php
<?php

final class StoreService
{
    public function refund(array $params): array
    {
        // 直接拼 URL、签名、curl、处理网络错误
        // 业务代码和 HTTP 细节混在一起
    }
}
```

更清晰的写法：

```php
<?php

final class StoreService
{
    public function refund(array $params): array
    {
        // 业务判断留在 Service
        $this->checkStorePermission($params['store_id']);

        // 跨服务调用交给 Helper
        return InternalServiceHelper::afterSaleRefund($params);
    }
}
```

小白重点：

```text
Service 管业务，Helper 管调用。
```

---

### 1.3 `InternalServiceHelper` 应该封装哪些能力？

一个成熟的内网调用 Helper 通常包含：

| 能力 | 说明 |
|---|---|
| 服务地址管理 | 知道 order/pay/user/aftersale 服务地址 |
| path 拼接 | 统一拼接 internal API path |
| HTTP method | GET/POST/PUT/DELETE |
| 公共 headers | trace_id、request_id、调用方服务名 |
| 服务间鉴权 | token/signature/HMAC |
| timeout | 避免无限等待 |
| retry | 对安全接口做有限重试 |
| 错误转换 | 统一下游错误格式 |
| 日志记录 | 记录请求、耗时、失败原因 |

注意：它不应该封装具体业务规则，比如“退款金额怎么算”。

---

### 1.4 第一步：先列 public 方法清单

读 `InternalServiceHelper.php` 时，先不要从第一行读到最后一行。

先列出 public 方法，例如：

```text
getOrderList()
getPayStatus()
createAfterSale()
getUserInfo()
```

记录模板：

| 方法名 | 调用哪个服务 | 大概用途 | 风险等级 |
|---|---|---|---|
| `getOrderList` | order-service | 查询订单列表 | 中 |
| `getPayStatus` | pay-service | 查询支付状态 | 中 |
| `createRefund` | aftersale/pay | 发起退款 | 高 |
| `getUserInfo` | user-service | 查询用户信息 | 中 |

小白重点：方法名通常就是“服务清单入口”。

---

### 1.5 第二步：拆解一次 HTTP 调用

假设 Helper 里有：

```php
<?php

public static function getPayStatus(array $params): array
{
    return self::post('/internal/pay/status', $params);
}
```

你要继续找 `post()` 做了什么。

一个简化版本：

```php
<?php

private static function post(string $path, array $params): array
{
    $headers = self::buildHeaders($params);

    return HttpClient::post(self::baseUrl() . $path, [
        'headers' => $headers,
        'json' => $params,
        'timeout' => 2,
    ]);
}
```

你要记录：

| 阅读点 | 记录 |
|---|---|
| path 从哪里来 |  |
| baseURL 从哪里来 |  |
| headers 怎么生成 |  |
| timeout 是多少 |  |
| 失败时怎么处理 |  |
| 是否记录日志 |  |

---

### 1.6 第三步：理解服务间鉴权 headers

常见 headers：

```text
X-Service-Name: store-api
X-Trace-Id: xxx
X-Request-Id: xxx
X-Timestamp: 1720000000
X-Signature: abcdef
```

含义：

| Header | 作用 |
|---|---|
| `X-Service-Name` | 告诉下游谁在调用 |
| `X-Trace-Id` | 串联整条请求链路 |
| `X-Request-Id` | 标识当前这一跳请求 |
| `X-Timestamp` | 防止旧请求被重放 |
| `X-Signature` | 证明请求没有被篡改 |

小白理解：

```text
headers 就像服务间通信时携带的身份证、通行证和快递单号。
```

---

### 1.7 第四步：错误处理应该统一

下游可能返回不同错误：

```json
{"code":"ORDER_NOT_FOUND","message":"order not found"}
```

```json
{"err_no":1001,"err_msg":"payment timeout"}
```

如果 Helper 不统一，上层 Service 会很痛苦。

推荐统一成：

```php
<?php

[
    'success' => false,
    'code' => 'PAY_TIMEOUT',
    'message' => '支付服务暂时不可用',
    'trace_id' => $traceId,
    'raw' => $rawResponse, // 只给日志，不直接给用户
]
```

错误处理原则：

1. 给用户的 message 要友好。
2. 给日志的 raw error 要完整。
3. 保留 trace_id 和下游 request_id。
4. 高风险操作不要因为错误格式不明就当成功。

---

### 1.8 第五步：Helper 与 `PayInternal` 的区别

| 对比项 | `PayInternal` | `InternalServiceHelper` |
|---|---|---|
| 所在上下文 | 核心/订单侧调用支付 | TP8 门店服务调用内网服务 |
| 目标服务 | 主要是支付服务 | 可能多个服务 |
| 角色 | 支付服务 client | 内网服务调用门面 |
| 风险重点 | 支付扣款/退款/状态 | 服务清单、权限、统一协议 |
| 类比 | `payServiceClient` | `internalServiceClient` |

---

### 1.9 第六步：不要让 Helper 变成“上帝类”

`InternalServiceHelper` 很容易越来越大，最后所有服务调用都塞进去。

如果它太大，可以考虑拆分：

```text
OrderInternalClient
PayInternalClient
UserInternalClient
AfterSaleInternalClient
```

或者保持一个门面，但内部委托：

```text
InternalServiceHelper
  ├─ orderClient
  ├─ payClient
  ├─ userClient
  └─ afterSaleClient
```

判断是否需要拆分：

| 信号 | 说明 |
|---|---|
| 一个文件几千行 | 维护困难 |
| 方法命名混乱 | 服务边界不清 |
| 不同服务鉴权不同 | 应拆出独立 client |
| 错误处理分支很多 | 需要按服务封装 |

---

## 2. 源码阅读

- `store-api/app/common/library/helper/InternalServiceHelper.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读目标：

| 阅读点 | 记录 |
|---|---|
| public 方法列表 |  |
| 可调用服务 |  |
| baseURL 配置来源 |  |
| headers 构造方式 |  |
| 鉴权方式 |  |
| timeout/retry |  |
| 错误处理 |  |
| trace_id/request_id |  |

---

## 3. 练习任务

### 练习 1：列可调用服务

| 方法名 | 目标服务 | path | 用途 | 风险 |
|---|---|---|---|---|
|  |  |  |  |  |

### 练习 2：画 Helper 调用流程

```text
StoreService
  ↓
InternalServiceHelper::xxx()
  ↓
buildHeaders(trace_id/signature)
  ↓
HTTP Client
  ↓
目标内网服务
  ↓
统一错误/成功返回
```

### 练习 3：检查职责边界

| 代码行为 | 应该在 Service | 应该在 Helper | 原因 |
|---|---|---|---|
| 判断门店权限 |  |  |  |
| 拼接内网 path |  |  |  |
| 添加 trace_id |  |  |  |
| 计算退款金额 |  |  |  |
| 设置 timeout |  |  |  |

---

## 4. JS/Node.js 类比

- `InternalServiceHelper` ≈ `internalServiceClient`
- `buildHeaders()` ≈ axios request interceptor
- 服务门面 ≈ facade / SDK wrapper
- TP8 Service 调 Helper ≈ NestJS Service 调 internal client

---

## 5. AI Review 提问

```text
我正在学习 TP8 的 InternalServiceHelper。
我已经列出 public 方法、目标服务、path、headers、timeout 和错误处理。
请你检查：
1. 我是否分清了 Service 和 Helper 的职责？
2. Helper 是否有上帝类风险？
3. 服务间鉴权字段是否完整？
4. 错误处理是否适合上层业务使用？
5. trace_id/request_id 是否能串起全链路？
```

---

## 6. 今日产出

- [ ] `InternalServiceHelper` 方法清单
- [ ] 可调用服务表
- [ ] Helper 调用流程图
- [ ] headers/鉴权字段表
- [ ] 职责边界检查表
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 `InternalServiceHelper` 的价值
- [ ] 能列出它能调用哪些服务
- [ ] 能拆解一次 Helper HTTP 调用
- [ ] 能说明 headers 中 trace_id/request_id/签名的作用
- [ ] 能判断哪些逻辑不应该放 Helper
- [ ] 能对比 `PayInternal` 和 `InternalServiceHelper`

---

## 8. 今日自测题

### 8.1 `InternalServiceHelper` 在门店服务里扮演什么角色？

参考答案：

> ✅ 它是 TP8 门店服务访问其他内网服务（订单、支付、用户、售后、核心）的统一门面/客户端，负责服务地址、公共请求头、鉴权、trace_id、超时和错误规范，把跨服务调用方式统一收口。

---

### 8.2 Helper 和 Service 的职责边界是什么？

参考答案：

> ✅ 一句话记忆：Service 管业务，Helper 管调用。Service 负责门店业务判断（如权限校验、状态判断），跨服务的 HTTP 调用细节（拼 URL、签名、curl、网络错误处理）交给 `InternalServiceHelper`，两者不要混在一起。

---

### 8.3 为什么门店服务不能每个 Service 自己写 HTTP 请求？

参考答案：

> ✅ 因为门店业务要调用订单、支付、用户、售后等多个服务，如果每个 Service 都自己拼 URL、写签名、处理超时，代码会很快失控，且地址变更、鉴权升级时要到处改。收口到 Helper 后只需改一处。

---

### 8.4 `InternalServiceHelper` 应该封装哪些能力？

参考答案：

> ✅ 服务地址管理、path 拼接、公共请求头（鉴权、trace_id、request_id、service name）、超时设置、统一的错误规范和返回结构解析等。它不应该承载具体门店业务规则。

---

### 8.5 `InternalServiceHelper` 和 `PayInternal` 有什么区别和联系？

参考答案：

> ✅ 两者都是内网调用的客户端封装。`PayInternal` 通常专注于订单域调用支付服务这一条线；`InternalServiceHelper` 是门店服务面向多个内网服务的统一门面，覆盖面更广。共同点是都把地址、鉴权、trace_id、超时、错误收口，不写业务逻辑。

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
我正在进行 Week 12 Day 02：InternalServiceHelper 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 12 README](./README.md)
