# Week 06 Day 03：OrderConfirmForm 参数校验

> 所属周：Week 06：订单域  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`mall-core`  
> 类型：编码练习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

对照前端结账页字段，读懂 `OrderConfirmForm` 如何通过 Yii2 `rules()` / `scenarios()` 做后端参数校验，理解为什么订单接口不能只依赖前端校验。

今天你要真正掌握这一句话：

> Form 校验是订单接口的第一道后端防线：前端可以提示用户，后端必须最终确认参数完整、类型正确、场景匹配，并把错误以统一格式返回给前端。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解前端结账页会提交哪些字段
2. 理解为什么后端必须再次校验
3. 复习 Yii2 Form Model 的 `rules()`
4. 理解 `scenarios()` 为什么存在
5. 打开 `OrderConfirmForm.php`，列字段清单
6. 对照前端字段和 Form rules
7. 标注必填、类型、默认值、错误提示
8. 类比 Node.js 的 Joi/Zod schema
9. 用 AI Review 检查校验是否覆盖订单风险

---

## 1. 学习内容

### 1.1 结账页通常提交哪些字段？

前端结账页可能提交：

| 字段 | 含义 |
|---|---|
| `goods_id` | 商品 ID |
| `sku_id` | 商品规格 ID |
| `num` | 购买数量 |
| `address_id` | 收货地址 ID |
| `coupon_id` | 优惠券 ID |
| `remark` | 买家备注 |
| `pay_type` | 支付方式 |
| `delivery_type` | 配送方式 |
| `invoice` | 发票信息 |
| `source` | 下单来源，如 H5/App |

但前端传来的任何字段都不能直接信任。

原因：

- 用户可以篡改请求参数
- 前端 bug 可能传错类型
- 恶意用户可能传负数数量
- 优惠券、金额、库存必须以后端为准

---

### 1.2 为什么不能只靠前端校验？

前端校验只是用户体验，后端校验才是安全边界。

前端可以做：

```js
if (!addressId) {
  showToast('请选择收货地址');
}
```

但攻击者可以绕过前端，直接请求接口：

```bash
curl -X POST /order/confirm -d 'address_id=&num=-999'
```

所以后端必须校验：

```php
<?php

if (!$form->validate()) {
    return $this->endFail($form->getFirstError());
}
```

小白重点：前端校验负责“友好提示”，后端校验负责“保护系统”。

---

### 1.3 Yii2 `rules()` 怎么理解？

Yii2 Form Model 常见：

```php
<?php

public function rules(): array
{
    return [
        [['goods_id', 'sku_id', 'num', 'address_id'], 'required'],
        [['goods_id', 'sku_id', 'num', 'address_id'], 'integer'],
        ['num', 'compare', 'compareValue' => 1, 'operator' => '>='],
        ['remark', 'string', 'max' => 255],
    ];
}
```

可以翻译成：

| rule | 含义 |
|---|---|
| `required` | 必填 |
| `integer` | 必须是整数 |
| `compare >= 1` | 数量不能小于 1 |
| `string max 255` | 字符串长度限制 |

---

### 1.4 `scenarios()` 是什么？

同一个 Form 可能服务多个接口：

- 确认订单
- 创建订单
- 重新计算优惠
- 切换地址

不同场景需要校验的字段不完全一样。

Yii2 可以用 `scenarios()` 控制不同场景可用字段：

```php
<?php

public function scenarios(): array
{
    return [
        'confirm' => ['goods_id', 'sku_id', 'num', 'address_id', 'coupon_id'],
        'create' => ['confirm_token', 'pay_type', 'remark'],
    ];
}
```

Node.js 类比：

```js
const confirmSchema = z.object({ ... });
const createSchema = z.object({ ... });
```

---

### 1.5 如何对照前端字段和 Form rules？

你要输出这样一张表：

| 前端字段 | Form 属性 | 是否必填 | 类型 | 规则 | 错误提示 |
|---|---|---|---|---|---|
| `goods_id` | `$goods_id` | 是 | int | required/integer | 商品不能为空 |
| `sku_id` | `$sku_id` | 是 | int | required/integer | 规格不能为空 |
| `num` | `$num` | 是 | int | >= 1 | 数量必须大于 0 |
| `address_id` | `$address_id` | 是 | int | required/integer | 请选择地址 |
| `coupon_id` | `$coupon_id` | 否 | int | integer | 优惠券不合法 |

如果你找不到某个字段，就写“未找到/待确认”，不要编造。

---

### 1.6 订单参数校验的风险点

订单参数校验要特别注意：

| 风险 | 示例 | 后端应做什么 |
|---|---|---|
| 数量异常 | `num=-1` | 校验 `num >= 1` |
| 金额篡改 | 前端传 `amount=1` | 不信任前端金额 |
| 地址越权 | 用别人的 `address_id` | 校验地址属于当前用户 |
| 优惠券越权 | 用别人的 `coupon_id` | 校验优惠券归属和状态 |
| 商品失效 | 商品已下架 | Service 再查商品状态 |
| 库存不足 | 数量超过库存 | Service/库存系统校验 |

Form 负责基础校验，Service 负责业务校验。两者都重要。

---

### 1.7 校验失败如何返回给前端？

常见流程：

```text
前端提交参数
  ↓
OrderConfirmForm 加载参数
  ↓
validate() 校验
  ↓
失败：返回第一条错误信息
成功：进入 OrderService
```

伪代码：

```php
<?php

$form = new OrderConfirmForm();
$form->load($this->request->post(), '');

if (!$form->validate()) {
    return $this->endFail($form->getFirstError());
}
```

前端收到：

```json
{
  "code": 400,
  "info": "请选择收货地址",
  "data": null
}
```

前端可以展示 Toast 或表单错误。

---

### 1.8 Node.js 类比：Joi/Zod schema

Node 中可能这样写：

```js
const schema = z.object({
  goods_id: z.number().int(),
  sku_id: z.number().int(),
  num: z.number().int().min(1),
  address_id: z.number().int(),
  coupon_id: z.number().int().optional(),
});

const params = schema.parse(req.body);
```

Yii2 Form 类似：

```php
<?php

$form = new OrderConfirmForm();
$form->load($params, '');
$form->validate();
```

类比：

| Yii2 | Node.js |
|---|---|
| Form Model | Joi/Zod schema class |
| `rules()` | schema rules |
| `scenarios()` | 不同接口的不同 schema |
| `validate()` | `schema.parse()` / `validate()` |
| `getFirstError()` | validation error message |

---

## 2. 源码阅读

- `order-api/forms/OrderConfirmForm.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| Form 属性 | 前端字段 | 必填 | 类型 | rules | scenario | 错误提示 |
|---|---|---|---|---|---|---|
|  |  |  |  |  |  |  |
|  |  |  |  |  |  |  |

---

## 3. 练习任务

### 练习 1：读 `OrderConfirmForm`

记录：

```text
Form 类名：
属性数量：
rules 数量：
scenarios：
最重要的 5 个字段：
```

### 练习 2：列前端字段 vs Form rules 对照表

至少对照 10 个字段。

### 练习 3：标注必填与错误码/错误信息

| 字段 | 是否必填 | 失败时前端应展示什么 |
|---|---|---|
|  |  |  |

---

## 4. JS/Node.js 类比

- `OrderConfirmForm` ≈ 后端 Joi/Zod schema
- `rules()` ≈ schema validation rules
- `scenarios()` ≈ 不同接口使用不同 schema
- `validate()` ≈ schema validate/parse
- `getFirstError()` ≈ validation error message

---

## 5. AI Review 提问

```text
我正在学习 OrderConfirmForm 参数校验。
我已经把前端结账页字段和 Form rules 做了对照，并标注了必填、类型和错误提示。
请你检查：
1. 我的字段对照是否完整？
2. 哪些字段只做了基础校验，还需要 Service 做业务校验？
3. 校验失败后前端如何展示更合理？
4. scenarios 的理解是否正确？
5. 与 Joi/Zod schema 的类比是否准确？
```

---

## 6. 今日产出

- [ ] `OrderConfirmForm` 阅读笔记
- [ ] 10+ 字段对照表
- [ ] 必填/类型/错误提示表
- [ ] Form 校验 vs Service 业务校验边界表
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能对照 10+ 个前端字段和 Form rules
- [ ] 能解释 `rules()` 的作用
- [ ] 能解释 `scenarios()` 的作用
- [ ] 能区分基础参数校验和业务校验
- [ ] 能说出订单参数校验的 5 个风险点
- [ ] 能用 Joi/Zod 类比 Yii2 Form

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
我正在进行 Week 06 Day 03：OrderConfirmForm 参数校验 的学习。
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
