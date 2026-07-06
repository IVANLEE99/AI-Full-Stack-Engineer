# Week 07 Day 03：PaymentFactory 渠道工厂

> 所属周：Week 07：支付域 + Node 流水线  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`pay-service`  
> 类型：编码练习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 `PaymentFactory` 如何把支付方式、支付渠道或支付公司映射到具体 SDK Service，掌握“工厂模式”在多支付渠道场景中的价值。

今天你要真正掌握这一句话：

> `PaymentFactory` 的作用是根据支付渠道选择正确的处理类：业务代码只说“我要用 Stripe 支付”，工厂负责找到 StripeService，而不是让调用方到处写 if/else 判断渠道。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解为什么支付系统会有多个渠道
2. 理解工厂模式解决什么问题
3. 打开 `PaymentFactory.php`，看输入是什么
4. 找支付渠道常量，如 Stripe、Braintree、PayPal
5. 找每个渠道对应哪个 Service/SDK 类
6. 画“支付方式 → 公司 → SDK Service”映射表
7. 思考新增一个支付渠道需要改哪些地方
8. 用 Node.js `handlers map` 做类比
9. 用 AI Review 检查映射是否完整

---

## 1. 学习内容

### 1.1 为什么支付需要渠道工厂？

支付系统通常不止一种支付方式：

- Stripe
- Braintree
- PayPal
- Apple Pay
- Google Pay
- 信用卡
- 本地钱包

如果业务代码直接写：

```php
<?php

if ($channel === 'stripe') {
    $service = new StripeService();
} elseif ($channel === 'braintree') {
    $service = new BraintreeService();
} elseif ($channel === 'paypal') {
    $service = new PayPalService();
}
```

问题是：

- if/else 到处都是
- 新增渠道要改很多地方
- 调用方需要知道具体类名
- 不方便测试和维护

工厂模式就是为了解决这些问题。

---

### 1.2 工厂模式怎么理解？

工厂模式可以理解为：

```text
我告诉工厂我要什么类型，工厂返回对应对象。
```

支付场景：

```php
<?php

$paymentService = PaymentFactory::make('stripe');
```

返回：

```text
StripeService 实例
```

调用方只关心：

```text
我现在要使用 stripe 渠道。
```

不关心：

```text
StripeService 类在哪里、怎么 new、依赖什么 SDK。
```

---

### 1.3 `PaymentFactory` 可能长什么样？

伪代码：

```php
<?php

final class PaymentFactory
{
    public static function make(string $channel): PaymentInterface
    {
        return match ($channel) {
            'stripe' => new StripeService(),
            'braintree' => new BraintreeService(),
            'paypal' => new PayPalService(),
            default => throw new InvalidArgumentException('不支持的支付渠道'),
        };
    }
}
```

或使用映射数组：

```php
<?php

private const MAP = [
    'stripe' => StripeService::class,
    'braintree' => BraintreeService::class,
    'paypal' => PayPalService::class,
];
```

---

### 1.4 支付方式、支付公司、SDK 的区别

这三个词容易混：

| 概念 | 含义 | 示例 |
|---|---|---|
| 支付方式 | 用户看到的支付入口 | 信用卡、PayPal、Apple Pay |
| 支付公司/渠道 | 背后的支付服务商 | Stripe、Braintree、PayPal |
| SDK Service | 项目中封装的调用类 | `StripeService`、`BraintreeService` |

例如：

```text
用户选择“信用卡”
  ↓
系统决定走 Stripe
  ↓
PaymentFactory 返回 StripeService
  ↓
StripeService 调 Stripe SDK/API
```

---

### 1.5 新增渠道要改哪些地方？

假设要新增 `Adyen` 渠道，通常要考虑：

| 位置 | 要做什么 |
|---|---|
| 渠道常量 | 新增 `CHANNEL_ADYEN` |
| 配置项 | 增加 API key、merchant id、回调密钥 |
| SDK Service | 新建 `AdyenService` |
| Factory | 把 `adyen` 映射到 `AdyenService` |
| 回调 Controller | 增加 Adyen webhook 入口 |
| 状态映射 | 映射 Adyen 状态到内部状态 |
| 测试 | 成功、失败、重复回调、金额不一致 |

小白重点：新增支付渠道不只是多一个类，还涉及配置、回调、安全、状态和测试。

---

### 1.6 工厂映射表模板

请整理：

| 支付方式 | 渠道常量 | 支付公司 | Service 类 | SDK/API | 回调入口 |
|---|---|---|---|---|---|
| 信用卡 | `stripe` | Stripe | `StripeService` | PaymentIntent | `/outer/stripe/webhook` |
| 信用卡 | `braintree` | Braintree | `BraintreeService` | Transaction/Sale | `/outer/braintree/webhook` |
| PayPal | `paypal` | PayPal | `PayPalService` | Order/Capture | `/outer/paypal/webhook` |

按源码实际内容修正。

---

### 1.7 Node.js 类比：handlers map

Node 中常见：

```js
const handlers = {
  stripe: new StripeService(),
  braintree: new BraintreeService(),
  paypal: new PayPalService(),
};

function getPaymentHandler(channel) {
  const handler = handlers[channel];
  if (!handler) throw new Error('不支持的支付渠道');
  return handler;
}
```

PHP 的 `PaymentFactory` 类似，只是写法不同。

---

## 2. 源码阅读

- `pay-service/common/factory/payment/PaymentFactory.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| 渠道常量 | Service 类 | 是否支持回调 | 配置来源 | 备注 |
|---|---|---|---|---|
|  |  |  |  |  |

---

## 3. 练习任务

### 练习 1：列所有支付渠道常量与 Service 类

| 渠道常量 | 支付公司 | Service 类 | 职责 |
|---|---|---|---|
|  |  |  |  |

### 练习 2：画“支付方式 → 公司 → SDK”映射表

至少列 3 条。

### 练习 3：说明新增渠道要改哪些地方

写出新增一个渠道的 checklist。

---

## 4. JS/Node.js 类比

- `PaymentFactory` ≈ 策略注册表 / handlers map
- 支付渠道常量 ≈ handler key
- `StripeService` ≈ stripe handler
- 不支持渠道异常 ≈ unknown handler error
- 新增渠道 ≈ 注册新 handler + webhook + config

---

## 5. AI Review 提问

```text
我正在学习 PaymentFactory 渠道工厂。
我已经整理了支付方式、渠道常量、Service 类和 SDK 的映射表。
请你检查：
1. 我对工厂模式的理解是否正确？
2. 支付方式、支付公司、SDK Service 是否区分清楚？
3. 新增渠道的改动点是否完整？
4. PaymentFactory 与 Node handlers map 的类比是否准确？
5. 真实支付系统新增渠道最容易遗漏什么？
```

---

## 6. 今日产出

- [ ] 渠道常量与 Service 类清单
- [ ] 支付方式 → 公司 → SDK 映射表
- [ ] 新增支付渠道 checklist
- [ ] PaymentFactory 阅读笔记
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释工厂映射的作用
- [ ] 能列出至少 3 个支付渠道映射
- [ ] 能说明新增渠道要改哪些地方
- [ ] 能区分支付方式、支付公司和 SDK Service
- [ ] 能用 Node handlers map 类比 PaymentFactory

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
我正在进行 Week 07 Day 03：PaymentFactory 渠道工厂 的学习。
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
