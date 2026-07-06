# Week 06 Day 01：OrderController 结构与 action

> 所属周：Week 06：订单域  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`mall-core`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

读懂订单 `OrderController` 的整体结构，理解订单接口 action 如何接收参数、调用 Form/Service、处理异常，并通过 `endSuccess` / `endFail` 返回统一响应。

今天你要真正掌握这一句话：

> `OrderController` 是订单域的 HTTP 入口，它不应该承载复杂订单规则，而应该负责“接请求、校验入口、调用订单服务、统一返回结果”。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Week 05 的 BFF 网关链路
2. 理解订单域为什么比普通配置接口复杂
3. 打开 `OrderController.php`，先看类结构和 action 列表
4. 找一个和下单相关的 action
5. 记录 action 的入参、调用对象、返回格式和错误处理
6. 理解 `try/catch` 在订单接口中的作用
7. 理解 `endSuccess` / `endFail` 和 Node.js `res.json()` 的关系
8. 画出 Controller → Form → Service 的链路
9. 用 AI Review 检查 action 是否混入过多业务逻辑

---

## 1. 学习内容

### 1.1 订单 Controller 为什么重要？

订单是电商系统的核心域之一。一个订单接口通常会关联：

- 用户身份
- 商品信息
- 收货地址
- 优惠券
- 库存
- 金额
- 支付
- 订单状态

所以订单 Controller 比普通配置接口更容易变复杂。

你学习 `OrderController` 时不要只看代码行数，而要看清楚：

```text
这个 action 到底只是入口编排，还是已经把复杂业务写进来了？
```

---

### 1.2 Controller 在订单链路中的位置

订单请求常见链路：

```text
前端结账页
  ↓
BFF 网关
  ↓
OrderController
  ↓
OrderConfirmForm / OrderCreateForm
  ↓
OrderService
  ↓
Repository / Model / DB
  ↓
返回订单结果
```

Controller 主要负责：

| 职责 | 示例 |
|---|---|
| 接收请求 | 获取 `goods_id`、`address_id`、`coupon_id` |
| 调用 Form | 校验参数是否合法 |
| 调用 Service | 创建订单、确认订单、查询订单 |
| 捕获异常 | `try/catch` 捕获业务异常 |
| 返回响应 | `endSuccess()` / `endFail()` |

不建议 Controller 做：金额计算、库存扣减、订单状态流转、复杂 SQL。

---

### 1.3 `try/catch` 模式怎么理解？

订单接口经常会失败，例如：

- 参数错误
- 商品不存在
- 库存不足
- 地址无效
- 优惠券不可用
- 重复提交
- 服务异常

所以 action 中可能会出现：

```php
<?php

public function actionCreate(): array
{
    try {
        $form = new OrderConfirmForm();
        $form->load($this->request->post(), '');

        if (!$form->validate()) {
            return $this->endFail($form->getFirstError());
        }

        $result = $this->orderService->create($form->toArray());

        return $this->endSuccess($result['data']);
    } catch (\Throwable $e) {
        return $this->endFail($e->getMessage());
    }
}
```

小白重点：`try/catch` 不是为了吞掉错误，而是为了把后端异常转换成前端能理解的错误响应。

---

### 1.4 `endSuccess` / `endFail` 是什么？

它们通常是统一响应方法。

成功响应类似：

```php
<?php

return $this->endSuccess([
    'order_id' => 1001,
]);
```

前端可能收到：

```json
{
  "code": 0,
  "data": {
    "order_id": 1001
  },
  "info": "success"
}
```

失败响应类似：

```php
<?php

return $this->endFail('库存不足');
```

前端可能收到：

```json
{
  "code": 400,
  "data": null,
  "info": "库存不足"
}
```

Node.js 类比：

```js
res.json({ code: 0, data, info: 'success' });
res.json({ code: 400, data: null, info: '库存不足' });
```

---

### 1.5 如何阅读一个 action？

用固定模板读：

| 阅读点 | 你要记录什么 |
|---|---|
| action 名称 | 如 `actionConfirm` / `actionCreate` |
| 请求参数 | 前端传了哪些字段 |
| 参数校验 | 是否使用 Form / rules |
| 调用 Service | 调用了哪个 Service 方法 |
| 返回格式 | 成功和失败分别怎么返回 |
| 错误码/错误信息 | 前端如何展示错误 |
| 是否过厚 | 是否包含金额、库存、状态流转等逻辑 |

---

### 1.6 订单 action 的好坏判断

较好的 action：

```text
参数读取清晰 → Form 校验 → Service 处理业务 → 统一返回
```

较差的 action：

```text
Controller 里直接查商品、算价格、扣库存、创建订单、改状态、写日志
```

判断标准：

| 问题 | 如果答案是“是”，要警惕 |
|---|---|
| action 是否超过 100 行？ | 可能过厚 |
| 是否直接写 SQL？ | 应考虑 Repository/Model |
| 是否计算订单金额？ | 应下沉到 Service |
| 是否扣库存？ | 应由库存/订单服务处理 |
| 是否有大量 if/else 业务分支？ | 应抽到 Service |

---

## 2. 源码阅读

- `order-api/controllers/OrderController.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| action | 入参 | 调用 Form | 调用 Service | 返回 | 是否过厚 |
|---|---|---|---|---|---|
|  |  |  |  |  |  |
|  |  |  |  |  |  |

---

## 3. 练习任务

### 练习 1：读 OrderController 结构

记录：

```text
类名：
继承：
action 数量：
最重要的 3 个 action：
统一响应方法：
异常处理方式：
```

### 练习 2：选 1 个 action 追踪到 Service

记录：

```text
action：
入参：
Form：
Service 方法：
出参：
错误码/错误信息：
```

### 练习 3：判断 action 是否过厚

列出 action 中每段代码属于：取参、校验、调用、返回、业务逻辑。

---

## 4. JS/Node.js 类比

- `OrderController` ≈ Express/NestJS 订单 router/controller 集合
- `endSuccess()` ≈ `res.json({ code: 0, data })`
- `endFail()` ≈ `res.json({ code, info })`
- Form 校验 ≈ Joi/Zod/class-validator schema
- `OrderService` ≈ 订单业务 service

---

## 5. AI Review 提问

```text
我正在阅读 OrderController。
我已经选择了一个 action，记录了入参、Form、Service、返回格式和错误处理。
请你检查：
1. 我的 action 链路是否完整？
2. Controller 中是否存在多余业务逻辑？
3. 哪些逻辑应该移动到 OrderService？
4. endSuccess/endFail 与 Node res.json 的类比是否准确？
5. 真实订单接口最应该关注哪些风险？
```

---

## 6. 今日产出

- [ ] `OrderController` 结构笔记
- [ ] 1 个 action 全链路追踪表
- [ ] 入参/出参/错误码记录
- [ ] action 是否过厚的判断表
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说明 `OrderController` 的职责
- [ ] 能追踪 1 个 action 到 Service
- [ ] 能解释 `try/catch` 在订单接口中的作用
- [ ] 能解释 `endSuccess` / `endFail`
- [ ] 能判断 action 中哪些逻辑不应属于 Controller

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
我正在进行 Week 06 Day 01：OrderController 结构与 action 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 06 README](./README.md)
