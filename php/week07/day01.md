# Week 07 Day 01：PayController 分类

> 所属周：Week 07：支付域 + Node 流水线  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`pay-service`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解支付域里 `client`、`internal`、`outer` 三类 Controller 的使用场景，能判断一个支付接口是给前端用户、内部服务，还是第三方支付平台回调用的。

今天你要真正掌握这一句话：

> 支付 Controller 分类的核心是“调用方不同，安全模型不同”：client 面向用户端，internal 面向内网服务，outer 面向第三方回调，每一类的鉴权、验签、参数和风险都不同。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Week 06 的订单与支付衔接点
2. 理解支付域为什么要区分接口调用方
3. 阅读 `PayController.php` 的 action 列表
4. 按 client/internal/outer 给 action 分类
5. 理解每一类接口的鉴权方式
6. 理解支付回调为什么通常属于 outer
7. 对比 Node.js 里的 user routes、internal routes、webhook routes
8. 输出 PayController action 清单
9. 用 AI Review 检查分类是否合理

---

## 1. 学习内容

### 1.1 支付域为什么要分类？

支付接口不是都给前端调用的。

常见调用方有三类：

| 类型 | 调用方 | 示例 |
|---|---|---|
| client | 用户端 / BFF 网关 / App | 创建支付、查询支付方式 |
| internal | 内部订单服务、风控服务、后台服务 | 订单服务通知支付服务创建支付单 |
| outer | 第三方支付平台 | Stripe/Braintree/PayPal 回调通知 |

调用方不同，安全方式也不同：

- 用户端接口：通常需要用户登录态或网关鉴权
- 内网接口：通常需要服务间鉴权、内网白名单、签名或 token
- 外部回调：通常不走用户登录，但必须验签

---

### 1.2 client Controller 怎么理解？

client 类接口通常服务用户端流程。

例如：

```text
获取支付方式
创建支付参数
查询当前用户支付结果
```

它的特点：

| 特点 | 说明 |
|---|---|
| 面向用户 | 通常和当前用户订单相关 |
| 需要鉴权 | 要知道是谁在支付 |
| 参数来自前端 | 订单号、支付方式、渠道等 |
| 返回给前端 | payment intent、client secret、支付跳转信息 |

伪代码：

```php
<?php

public function actionCreate(): array
{
    $userId = $this->requireLogin();
    $orderId = (int)$this->request->post('order_id');

    $result = $this->payService->createForUser($userId, $orderId);

    return $this->endSuccess($result);
}
```

---

### 1.3 internal Controller 怎么理解？

internal 类接口服务内部系统之间的调用。

例如订单服务调用支付服务：

```text
订单服务 → 支付服务：为订单创建支付单
支付服务 → 订单服务：通知订单支付成功
```

它的特点：

| 特点 | 说明 |
|---|---|
| 调用方是服务 | 不是浏览器用户直接访问 |
| 不一定有用户登录态 | 但必须有服务间安全校验 |
| 参数更接近业务对象 | order_id、amount、currency、merchant_id |
| 风险很高 | 涉及金额和订单状态 |

小白重点：internal 不是“不需要安全”，而是“安全方式不是用户登录”。

---

### 1.4 outer Controller 怎么理解？

outer 常用于接收第三方支付平台回调。

例如：

```text
Stripe → pay-service/outer/stripe/webhook
PayPal → pay-service/outer/paypal/notify
Braintree → pay-service/outer/braintree/webhook
```

它的特点：

| 特点 | 说明 |
|---|---|
| 调用方是第三方平台 | 不是用户，也不是内部服务 |
| 不走用户登录鉴权 | 第三方没有用户 session |
| 必须验签 | 防止伪造回调 |
| 必须幂等 | 第三方可能重复通知 |
| 必须校验金额 | 防止订单金额和支付金额不一致 |

outer 可以类比 Webhook。

---

### 1.5 三类 Controller 对比表

| 分类 | 调用方 | 鉴权方式 | 典型接口 | 最大风险 |
|---|---|---|---|---|
| client | 前端/BFF | 用户 token/session | 创建支付、查支付方式 | 越权支付他人订单 |
| internal | 内网服务 | 服务间 token/签名/内网 ACL | 创建支付单、同步状态 | 内部接口被滥用 |
| outer | 第三方平台 | webhook 签名验签 | 支付回调 | 伪造回调、重复回调 |

分类时重点问：

```text
是谁调用这个接口？它凭什么被信任？失败或被伪造会造成什么后果？
```

---

### 1.6 支付单与订单关系预热

支付 Controller 常围绕“支付单”工作。

简单关系：

```text
订单 order
  ↓ 创建支付
支付单 payment
  ↓ 第三方渠道
Stripe/Braintree/PayPal
  ↓ 回调成功
支付单成功 → 订单状态改为已支付
```

一个订单可能对应一个或多个支付尝试，例如用户换支付方式或第一次失败后重试。

所以支付接口通常要带：

- `order_id`
- `pay_id` / `payment_id`
- `amount`
- `currency`
- `channel`
- `transaction_id`

---

### 1.7 Node.js 类比

Node/NestJS 项目也会分：

```text
/user/pay/create       → 用户端接口
/internal/pay/create   → 内部服务接口
/webhooks/stripe       → 第三方回调接口
```

类比：

| PHP 支付 Controller | Node.js 类比 |
|---|---|
| client | user-facing routes |
| internal | internal service routes |
| outer | webhook routes |
| outer 验签 | Stripe webhook signature verification |

---

## 2. 源码阅读

- `pay-service/pay-api/controllers/PayController.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| action | HTTP 方法 | 分类 client/internal/outer | 调用方 | 鉴权/验签 | 备注 |
|---|---|---|---|---|---|
|  |  |  |  |  |  |

---

## 3. 练习任务

### 练习 1：读 PayController

记录：

```text
类名：
继承：
action 数量：
最重要的 5 个 action：
统一响应方法：
```

### 练习 2：列全部 action 与 HTTP 方法

| action | HTTP 方法 | 入参 | 返回 | 备注 |
|---|---|---|---|---|
|  |  |  |  |  |

### 练习 3：按 client/internal/outer 分类

每个 action 都写明分类原因。

---

## 4. JS/Node.js 类比

- outer ≈ Webhook routes，无用户鉴权，但必须验签
- internal ≈ 内网服务互调接口
- client ≈ 面向用户/BFF 的 API
- 支付回调 ≈ Stripe webhook / PayPal IPN notify

---

## 5. AI Review 提问

```text
我正在学习 PayController 分类。
我已经列出 action 清单，并按 client/internal/outer 分类。
请你检查：
1. 我的分类是否合理？
2. 哪些 action 的调用方判断可能错误？
3. client/internal/outer 的安全模型有什么区别？
4. outer 回调为什么不能只靠白名单？
5. 真实支付系统中 Controller 分类还要注意什么？
```

---

## 6. 今日产出

- [ ] PayController action 清单
- [ ] client/internal/outer 分类表
- [ ] 三类接口安全模型对照
- [ ] 支付单与订单关系初步笔记
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说出 client/internal/outer 三类区别
- [ ] 能解释 outer 为什么类似 Webhook
- [ ] 能说明 outer 必须验签
- [ ] 能按调用方给 action 分类
- [ ] 能说出支付单和订单的基本关系

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
我正在进行 Week 07 Day 01：PayController 分类 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 07 README](./README.md)
