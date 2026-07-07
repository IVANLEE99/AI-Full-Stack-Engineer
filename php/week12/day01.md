# Week 12 Day 01：PayInternal 跨服务调用

> 所属周：Week 12：跨服务调用 / InternalService / 全链路串联  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`全部后端`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 `PayInternal` 这一类跨服务调用封装：订单服务或核心业务代码不应该直接到处写 HTTP 请求，而应该通过统一的 Internal Client 调用支付服务，掌握服务间 HTTP 调用的 path、参数、鉴权、超时、错误返回与 `trace_id` 传递。

今天你要真正掌握这一句话：

> `PayInternal` 可以类比 Node.js 里的 `payServiceClient.post('/internal/pay/capture', payload)`：它不是业务本身，而是“订单域调用支付域”的内网 HTTP 客户端封装。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解为什么订单服务不能直接写裸 HTTP 请求
2. 理解 `PayInternal` 在跨服务调用中的位置
3. 掌握服务间 HTTP 调用的 5 个核心要素：baseURL、path、method、headers、body
4. 理解服务间鉴权：为什么内网接口也要验证调用方
5. 理解超时、重试、熔断分别解决什么问题
6. 追踪一次 `capture` 支付确认调用
7. 记录请求参数、返回结构和错误传递方式
8. 标注 `trace_id / request_id` 如何从上游传到支付服务
9. 用 Node.js axios client 做类比，并完成今日自测

---

## 1. 学习内容

### 1.1 为什么需要 `PayInternal`？

在单体项目里，订单代码可能直接调用支付类：

```php
<?php

$payService->capture($orderNo, $amount);
```

但在微服务或多应用架构里，订单和支付可能是两个独立服务：

```text
order-service / mall-core
  ↓ 内网 HTTP
pay-service
```

这时订单服务不能直接调用支付服务里的 PHP 类，只能通过 HTTP、RPC 或消息等方式通信。

最简单但不推荐的写法是：

```php
<?php

$response = HttpClient::post('https://pay-service/internal/capture', [
    'order_no' => $orderNo,
    'amount' => $amount,
]);
```

问题是：如果项目里到处都这样写，会出现很多风险：

| 问题 | 后果 |
|---|---|
| URL 到处写死 | 服务地址变更时难维护 |
| 超时时间不统一 | 某些接口可能卡住整个请求 |
| 鉴权不统一 | 内网接口可能被误调用 |
| 错误格式不统一 | 上游不知道如何处理失败 |
| 日志缺 `trace_id` | 出问题无法追踪完整链路 |
| 重试策略随意 | 可能重复扣款或重复退款 |

所以企业项目会封装类似：

```php
<?php

PayInternal::capture($params);
```

或者：

```php
<?php

$this->payInternal->capture($params);
```

它的作用不是“支付业务逻辑”，而是：

```text
统一封装订单服务调用支付服务的方式。
```

---

### 1.2 `PayInternal` 在系统中的位置

你可以把 `PayInternal` 放到全链路里看：

```text
前端
  ↓
BFF / mall-gateway
  ↓
TP8 / mall-core / order-service
  ↓
PayInternal
  ↓ 内网 HTTP
pay-service Internal API
  ↓
支付渠道 / 支付数据库 / MQ
```

小白重点：

```text
PayInternal 是调用支付服务的“客户端”，不是支付服务本身。
```

类比生活：

```text
订单服务 = 需要办理付款的人
PayInternal = 专门负责联系支付部门的窗口
支付服务 = 真正处理付款的部门
```

---

### 1.3 服务间 HTTP 调用的 5 个核心要素

每一次服务间 HTTP 调用，都可以拆成 5 部分：

| 要素 | 含义 | 示例 |
|---|---|---|
| baseURL | 目标服务基础地址 | `http://pay-service` |
| path | 目标接口路径 | `/internal/pay/capture` |
| method | 请求方法 | `POST` |
| headers | 请求头 | `Authorization`、`X-Trace-Id` |
| body/query | 请求参数 | `order_no`、`amount` |

伪代码：

```php
<?php

final class PayInternal
{
    public function capture(array $payload): array
    {
        return $this->post('/internal/pay/capture', $payload);
    }
}
```

你读源码时，不要一开始陷入所有细节，先找：

```text
它调哪个服务？
它请求哪个 path？
它用 GET 还是 POST？
它传了哪些参数？
它返回什么格式？
```

---

### 1.4 `capture` 是什么？

在支付语境里，`capture` 常表示“确认扣款 / 捕获支付”。

一个简化流程：

```text
用户提交订单
  ↓
订单创建待支付记录
  ↓
支付授权或发起支付
  ↓
订单服务调用 PayInternal::capture()
  ↓
支付服务确认支付成功或失败
  ↓
订单更新支付状态
```

不是所有支付系统都完全这样命名，但你可以先理解成：

```text
capture = 订单侧请求支付侧完成一次支付确认动作。
```

---

### 1.5 服务间鉴权：内网接口为什么也要鉴权？

很多小白会误以为：

```text
内网接口只在服务器之间调用，所以不用鉴权。
```

这是危险想法。

原因：

1. 内网也可能被误调用。
2. 某个服务被攻破后，可能横向调用其他服务。
3. 支付、退款、售后属于高风险业务。
4. 需要知道“谁调用了我”。

常见鉴权方式：

| 方式 | 说明 |
|---|---|
| 内部 token | 调用方带固定或动态 token |
| HMAC 签名 | 用密钥对请求参数和时间戳签名 |
| mTLS | 服务间双向证书认证 |
| 网关内网白名单 | 只允许特定服务来源 |
| 时间戳 + nonce | 防重放攻击 |

伪代码：

```php
<?php

$headers = [
    'X-Service-Name' => 'order-service',
    'X-Timestamp' => (string) time(),
    'X-Signature' => $this->sign($payload),
    'X-Trace-Id' => $traceId,
];
```

小白重点：

```text
内网接口不是“裸奔接口”，尤其是支付和退款。
```

---

### 1.6 超时、重试、熔断分别是什么？

跨服务调用一定要考虑失败。

| 机制 | 解决的问题 | 小白理解 |
|---|---|---|
| timeout 超时 | 下游太慢，不能无限等 | 等 2 秒不回就先放弃 |
| retry 重试 | 网络抖动、临时失败 | 安全场景再试一次 |
| circuit breaker 熔断 | 下游持续故障，保护系统 | 电闸跳闸，暂停继续打 |

#### 超时 timeout

```text
订单服务等支付服务最多 2 秒。
超过 2 秒就认为这次调用失败或未知。
```

#### 重试 retry

支付场景要非常小心：

```text
不是所有支付请求都能随便重试。
```

适合谨慎重试：查询支付状态、获取配置、幂等设计完善的接口。  
不适合盲目重试：扣款、退款、创建支付单但没有幂等号。

#### 熔断 circuit breaker

```text
支付服务已经大量超时 → 先断开一段时间 → 避免所有请求继续压垮它。
```

---

### 1.7 错误传递：不要把下游错误直接原样甩给用户

支付服务可能返回：

```json
{
  "code": "PAY_CHANNEL_TIMEOUT",
  "message": "Stripe request timeout",
  "request_id": "pay-xxx"
}
```

订单服务不应该直接把内部错误细节暴露给前端，而应该转换成业务可理解的错误：

```json
{
  "code": "PAY_PROCESSING",
  "message": "支付处理中，请稍后查看订单状态",
  "trace_id": "trace-xxx"
}
```

错误传递要保留两类信息：

| 信息 | 给谁看 | 作用 |
|---|---|---|
| 用户友好 message | 前端/用户 | 告诉用户下一步怎么办 |
| trace_id/request_id | 开发/排障 | 串联日志定位问题 |

---

### 1.8 `trace_id / request_id` 是什么？

`trace_id` 是全链路追踪 ID，用来把一次请求在多个服务里的日志串起来。

```text
BFF 收到前端请求：trace_id=T202607060001
  ↓
订单服务日志：trace_id=T202607060001
  ↓
PayInternal 请求头：X-Trace-Id=T202607060001
  ↓
支付服务日志：trace_id=T202607060001
```

`request_id` 有时表示某一跳自己的请求 ID：

```text
trace_id：整条链路同一个
request_id：某个服务或某次 HTTP 调用自己的 ID
```

小白重点：没有 `trace_id`，跨服务问题会非常难查。

---

### 1.9 手把手追踪 `PayInternal::capture()`

读源码时按这个表记录：

| 阅读点 | 你的记录 |
|---|---|
| 类名 | `PayInternal` |
| 方法名 | `capture` |
| 调用方 | 订单/结账相关 Service |
| 目标服务 | 支付服务 |
| HTTP method | GET / POST |
| path | 例如 `/internal/pay/capture` |
| 请求参数 | order_no、amount、currency、idempotency_key 等 |
| 请求头 | token/signature、trace_id、request_id |
| 超时设置 | 例如 2s / 3s |
| 是否重试 | 是/否，重试几次 |
| 返回格式 | code/message/data |
| 错误如何处理 | 抛异常 / 返回错误数组 / 转业务错误 |

如果你能填完这张表，就算源码细节没完全背下来，也已经抓住主线了。

---

## 2. 源码阅读

- `mall-core/common/api/PayInternal.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读重点：

| 文件 | 重点 |
|---|---|
| `PayInternal.php` | 找 capture 方法、path、参数、HTTP 封装、错误处理 |
| 调用方 Service | 找谁调用 `PayInternal::capture()` |
| 日志/上下文工具 | 找 trace_id/request_id 如何读取或传递 |

---

## 3. 练习任务

### 练习 1：完成 capture 调用笔记

| 项 | 记录 |
|---|---|
| 调用入口 |  |
| `PayInternal` 方法 |  |
| 目标 path |  |
| 请求参数 |  |
| 请求头 |  |
| 返回字段 |  |
| 错误处理 |  |
| trace_id 是否传递 |  |

### 练习 2：判断哪些请求可以重试

| 请求 | 是否适合自动重试 | 原因 |
|---|---|---|
| 查询支付状态 |  |  |
| 创建支付单 |  |  |
| capture 扣款 |  |  |
| 获取支付方式列表 |  |  |

### 练习 3：画链路图

要求至少包含：

```text
BFF → 订单/TP8 服务 → PayInternal → pay-service → 返回结果
```

并标注：trace_id、鉴权 header、timeout、错误返回。

---

## 4. JS/Node.js 类比

- `PayInternal` ≈ `payServiceClient`
- `capture()` ≈ `payClient.post('/internal/pay/capture')`
- 服务间鉴权 ≈ axios interceptor 添加签名 header
- timeout ≈ axios timeout
- trace_id ≈ request context 里的 correlation id

---

## 5. AI Review 提问

```text
我正在学习 PayInternal 跨服务调用。
我已经记录了 capture 的 path、参数、headers、返回值、错误处理和 trace_id 传递。
请你检查：
1. 我是否分清了业务逻辑和 HTTP Client 封装？
2. 我对服务间鉴权的理解是否正确？
3. capture 是否适合自动重试？为什么？
4. 我的错误传递设计是否会泄露内部细节？
5. trace_id/request_id 记录是否完整？
```

---

## 6. 今日产出

- [ ] `PayInternal::capture()` 调用笔记
- [ ] 服务间 HTTP 调用要素表
- [ ] 鉴权 header 记录
- [ ] 超时/重试/熔断风险笔记
- [ ] trace_id/request_id 链路图
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 `PayInternal` 解决什么问题
- [ ] 能说出为什么 Service 不应该到处写裸 HTTP
- [ ] 能追踪一次 `capture` 调用
- [ ] 能列出 path、参数、headers、返回结构
- [ ] 能说明内网服务也要鉴权
- [ ] 能解释 timeout/retry/circuit breaker 的区别
- [ ] 能说明 trace_id 和 request_id 的用途

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
我正在进行 Week 12 Day 01：PayInternal 跨服务调用 的学习。
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
