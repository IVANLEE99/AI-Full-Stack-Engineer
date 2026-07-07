# Week 10 Day 02：processingType 策略模式

> 所属周：Week 10：售后服务 + Console 任务  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`aftersale-service`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

读懂 `processingType` 策略模式，理解 `ReturnGoodsRefund`、`Reissue` 等不同售后处理类型为什么要拆成独立策略类，以及新增售后类型时应该如何扩展。

今天你要真正掌握这一句话：

> 售后 processingType 策略模式的价值是：把“退货退款、仅退款、补发、换货”等不同处理逻辑从大 Service 的 switch/if 中拆出去，让每种售后类型有独立、可测试、可扩展的处理类。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾昨天售后域为什么复杂
2. 理解什么是策略模式
3. 打开 `ReturnGoodsRefund.php`，看它处理什么售后类型
4. 打开 `Reissue.php`，和退货退款做对比
5. 找它们是否实现同一个接口或继承同一个基类
6. 对比每个策略的入参、校验、状态变化和输出
7. 思考新增售后类型要改哪些地方
8. 用 Node.js handlers map / strategy class 做类比
9. 用 AI Review 检查扩展方式是否合理

---

## 1. 学习内容

### 1.1 什么是 processingType？

`processingType` 可以理解为售后处理类型。

常见类型：

| processingType | 含义 |
|---|---|
| return_goods_refund | 退货退款 |
| refund_only | 仅退款 |
| reissue | 补发 |
| exchange | 换货 |
| repair | 维修 |

不同类型业务差异很大。

例如：

- 退货退款：需要用户寄回商品、仓库收货、再触发退款
- 仅退款：不需要物流，审核通过后可能直接退款
- 补发：不退款，而是创建补发单/物流任务

---

### 1.2 如果不用策略模式会怎样？

大 Service 中可能出现：

```php
<?php

if ($type === 'return_goods_refund') {
    // 退货退款逻辑
} elseif ($type === 'reissue') {
    // 补发逻辑
} elseif ($type === 'refund_only') {
    // 仅退款逻辑
}
```

问题：

- `AfterSaleService` 越来越长
- 新增类型要改老代码
- 每个类型测试困难
- 很容易改坏别的售后类型
- 状态流转散落在 if/else 中

策略模式把它拆成：

```text
ReturnGoodsRefund Strategy
Reissue Strategy
RefundOnly Strategy
```

---

### 1.3 策略模式怎么理解？

策略模式的核心：

```text
同一个接口，不同实现。
```

伪代码：

```php
<?php

interface ProcessingTypeInterface
{
    public function handle(AfterSaleContext $context): AfterSaleResult;
}
```

退货退款：

```php
<?php

final class ReturnGoodsRefund implements ProcessingTypeInterface
{
    public function handle(AfterSaleContext $context): AfterSaleResult
    {
        // 退货退款逻辑
    }
}
```

补发：

```php
<?php

final class Reissue implements ProcessingTypeInterface
{
    public function handle(AfterSaleContext $context): AfterSaleResult
    {
        // 补发逻辑
    }
}
```

---

### 1.4 ReturnGoodsRefund 关注什么？

退货退款通常关注：

- 是否允许退货
- 是否需要上传凭证
- 退货地址
- 用户退货物流单号
- 仓库是否收到货
- 可退金额
- 退款状态
- 支付退款调用

流程可能是：

```text
用户申请退货退款
  ↓
客服审核通过
  ↓
用户寄回商品
  ↓
仓库确认收货
  ↓
触发退款
  ↓
售后完成
```

---

### 1.5 Reissue 关注什么？

补发通常关注：

- 是否需要用户退货
- 补发商品/数量
- 收货地址
- 物流单号
- 是否创建补发订单/任务
- 是否通知仓库
- 是否通知用户

补发不一定涉及退款。

流程可能是：

```text
用户申请补发
  ↓
客服审核通过
  ↓
创建补发任务
  ↓
仓库发货
  ↓
用户收货
  ↓
售后完成
```

---

### 1.6 策略对比表

| 对比项 | ReturnGoodsRefund | Reissue |
|---|---|---|
| 是否退款 | 是 | 通常否 |
| 是否退货 | 是 | 通常否 |
| 是否需要物流 | 用户退货物流 | 商家补发物流 |
| 是否调用支付退款 | 是 | 否 |
| 是否创建发货任务 | 可能否 | 是 |
| 核心风险 | 超额退款、重复退款 | 重复补发、地址错误 |
| 完成条件 | 退款成功 | 补发完成 |

---

### 1.7 新增类型如何扩展？

假设新增“维修”类型：

1. 新增 processingType 常量
2. 新增 `Repair` 策略类
3. 实现统一接口
4. 注册到工厂/映射表
5. 增加状态流转规则
6. 增加表单校验和权限校验
7. 增加测试和 AI Review

重点：尽量少改已有策略类。

---

### 1.8 Node.js 类比

Node 中 handlers map：

```js
const strategies = {
  return_goods_refund: new ReturnGoodsRefundStrategy(),
  reissue: new ReissueStrategy(),
};

const strategy = strategies[processingType];
await strategy.handle(context);
```

PHP 的策略模式类似。

---

## 2. 源码阅读

- `aftersale-service/common/services/processingType/concrete/ReturnGoodsRefund.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

建议同时阅读：

- `Reissue.php`
- processingType 接口/基类
- processingType 工厂/映射表

阅读记录：

| 类 | 实现接口/继承 | 核心方法 | 状态变化 | 是否退款 | 风险 |
|---|---|---|---|---|---|
| ReturnGoodsRefund |  |  |  |  |  |
| Reissue |  |  |  |  |  |

---

## 3. 练习任务

### 练习 1：读 ReturnGoodsRefund

记录它的输入、核心方法、状态变化、退款触发点。

### 练习 2：读 Reissue

记录它与退货退款的差异。

### 练习 3：对比接口实现

完成策略对比表，说明新增售后类型如何扩展。

---

## 4. JS/Node.js 类比

- processingType ≈ switch 拆成 Strategy class
- Strategy class ≈ handler object
- processingType factory ≈ handlers map
- ReturnGoodsRefund ≈ refund strategy
- Reissue ≈ reissue shipment strategy

---

## 5. AI Review 提问

```text
我正在学习售后 processingType 策略模式。
我已经对比 ReturnGoodsRefund 和 Reissue，并整理了新增售后类型的扩展步骤。
请你检查：
1. 我对策略模式的理解是否正确？
2. ReturnGoodsRefund 和 Reissue 的职责边界是否清楚？
3. 新增类型时还需要改哪些配置、状态和权限？
4. 哪些逻辑不应该继续留在 AfterSaleService 大类里？
5. 与 Node handlers map 的类比是否准确？
```

---

## 6. 今日产出

- [ ] `ReturnGoodsRefund` 阅读笔记
- [ ] `Reissue` 阅读笔记
- [ ] 策略对比表
- [ ] 新增售后类型 checklist
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释策略模式
- [ ] 能说明 processingType 是什么
- [ ] 能对比 ReturnGoodsRefund 与 Reissue
- [ ] 能说明新增售后类型要改哪些地方
- [ ] 能用 Node handlers map 类比策略模式

---

## 8. 今日自测题

### 8.1 什么是 processingType？

参考答案：

> ✅ processingType 表示售后处理类型，例如退货退款、仅退款、补发、换货、维修。不同类型的业务处理逻辑差别很大，用它来区分走哪种处理流程。

---

### 8.2 策略模式的核心思想是什么？

参考答案：

> ✅ 同一个接口，不同实现。定义一个统一接口（如 `ProcessingTypeInterface`），每种售后类型写一个实现类，各自处理自己的逻辑，调用方按类型选择对应策略。

---

### 8.3 不用策略模式、全写在大 Service 里会有什么问题？

参考答案：

> ✅ Service 会因大量 `if/elseif` 越来越长；新增类型要改老代码，容易改坏已有类型；每个类型难以单独测试；状态流转逻辑散落在各分支里，维护成本高。

---

### 8.4 ReturnGoodsRefund 和 Reissue 的核心区别是什么？

参考答案：

> ✅ 退货退款需要用户寄回、仓库收货、再调用支付退款；补发通常不退款，而是创建补发任务/物流单由商家发货。前者核心风险是超额/重复退款，后者是重复补发和地址错误。

---

### 8.5 新增一个“维修”售后类型要做哪些扩展？

参考答案：

> ✅ 新增 processingType 常量、新建 `Repair` 策略类并实现统一接口、注册到工厂/映射表、补充状态流转规则、增加表单校验和权限校验、补测试。重点是尽量不改已有策略类。

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
我正在进行 Week 10 Day 02：processingType 策略模式 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 10 README](./README.md)
