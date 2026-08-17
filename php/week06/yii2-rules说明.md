# Yii2 `rules()` 写法说明

> 对应课程：`week06/day03.md` OrderConfirmForm 参数校验  
> 关联源码：`week06/OriginCodes/OrderConfirmForm.php`  
> 目标：看懂 Yii2 Form 里「字段 + 校验器 + 选项」这种声明式规则。

---

## 1. 示例代码

```php
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

这是 **Yii2 Model/Form 的声明式校验规则**。`rules()` 返回一组规则，框架在 `$form->validate()` 时按顺序执行，全部通过才算校验成功。

---

## 2. 每一条规则的结构

通用形状：

```php
[被校验的属性, 校验器名称, 选项1 => 值1, 选项2 => 值2, ...]
```

| 位置 | 含义 |
|---|---|
| 第 1 个元素 | 字段名，或字段名数组 |
| 第 2 个元素 | 校验器：`required` / `integer` / `string` / `compare` … |
| 后面的键值 | 该校验器的配置 |

所以：

```php
[['goods_id', 'sku_id', 'num', 'address_id'], 'required']
```

读成：**这 4 个字段都要走 `required` 校验器。**

一个字段写一条也可以：

```php
['num', 'compare', 'compareValue' => 1, 'operator' => '>=']
```

读成：**只校验 `num`，用 `compare`，比较值是 1，运算符是 `>=`。**

多字段写成数组是为了少重复：`[['a','b','c'], 'required']` 等价于对 a/b/c 各写一条 `required`。

---

## 3. 逐行翻译

| 行 | 白话 |
|---|---|
| 第 1 条 | `goods_id`、`sku_id`、`num`、`address_id` **不能空** |
| 第 2 条 | 这 4 个字段必须是 **整数**（或能当成整数的值） |
| 第 3 条 | `num` 必须 **≥ 1**，禁止 0、负数 |
| 第 4 条 | `remark` 是字符串，最长 255；**没写 required，所以可空** |

对应结账语义：

- 商品、规格、数量、地址必须有，且是整数
- 数量至少买 1 件
- 备注可选，但不能超长

---

## 4. 四个校验器分别做什么

### 4.1 `required`：必填

空值会失败，通常包括：

- 没传
- `null`
- `''` 空字符串
- 只含空白的字符串（默认会 trim）

注意：`0` 一般 **不算空**。所以 `num=0` 能过 `required`，但过不了后面的 `compare >= 1`。

### 4.2 `integer`：整数

要求值是整数，或能转成整数的字符串，例如 `"3"`。  
`"3.5"`、`"abc"`、数组会失败。

它管的是 **类型**，不管大小。`num=-999` 对 `integer` 是合法的，所以还要第三条。

### 4.3 `compare`：和某个值比较

```php
['num', 'compare', 'compareValue' => 1, 'operator' => '>=']
```

| 配置 | 含义 |
|---|---|
| `compareValue` | 拿来比的常数 |
| `operator` | `>=` `>` `<=` `<` `==` `===` `!=` |

也可以跟另一个字段比：

```php
['end_at', 'compare', 'compareAttribute' => 'start_at', 'operator' => '>']
```

### 4.4 `string`：字符串 + 长度

```php
['remark', 'string', 'max' => 255]
```

还可配 `min`、`length`。  
`remark` 不在 `required` 里：空着可以通过；一旦有值，就要满足字符串且 ≤ 255。

---

## 5. 执行顺序

Yii2 **按数组从上到下**跑规则，同一字段可以叠多条：

```text
num
  → required     有没有值？
  → integer      是不是整数？
  → compare >= 1 是不是至少 1？
```

所以 `-999`：

1. `required` 过（有值）
2. `integer` 过（是整数）
3. `compare` **失败**（小于 1）

空字符串 `num=""`：

1. `required` **失败**，后面通常不再对这个字段继续有意义地校验

这就是为什么「必填、类型、范围」要拆成三条，而不是指望一个校验器包打天下。

---

## 6. 和 `validate()` 怎么接上

Controller 里典型用法：

```php
$form = new OrderConfirmForm();
$form->load($this->request->post(), '');

if (!$form->validate()) {
    return $this->endFail($form->getFirstError());
}
```

流程：

1. `load()` / `setAttributes()` 把请求填进 Form 属性
2. `validate()` 读取 `rules()` 逐条执行
3. 失败时错误挂在 `$form->errors` 上
4. `getFirstError()` / `getSimpleFirstError()` 取出给前端

**没有 `validate()`，`rules()` 不会自己跑。**

---

## 7. 和真实 `OrderConfirmForm` 的差异

课程示例把规则写死在 `rules()` 里。本地源码是 **按场景动态塞规则**：

```php
public function rules()
{
    return array_merge($this->commonRules, $this->ruleArray);
}

public function orderConfirmValidate($params = [])
{
    $this->ruleArray = [
        [['eid', 'token', 'source'], 'required'],
        ['pf', 'in', 'range' => ['pc', 'm', 'android', 'ios']],
        ['address_id', 'integer'],
        ['source', 'in', 'range' => [1, 2, 3, 4]],
        ['location_type', 'integer', 'min' => 1, 'max' => 5],
        ['coupon_user_id', 'integer'],
        ['commit', 'string', 'length' => [0, 400]],
    ];

    $this->setAttributes($params);
    return $this->validate();
}
```

对照：

| 课程示例 | 真实 Form |
|---|---|
| 固定 `rules()` | `$ruleArray` + 方法名区分场景 |
| `scenarios()` | 用 `orderConfirmValidate` / `tradeConfirmValidate` 代替 |
| `goods_id` / `sku_id` / `num` | 商品校验场景主要要 `eid` + `goods_list` |
| `remark` max 255 | 备注字段叫 `commit`，长度 100 或 400 |

额外常见校验器：

```php
['pf', 'in', 'range' => ['pc', 'm', 'android', 'ios']]
['source', 'in', 'range' => [1, 2, 3, 4]]
['location_type', 'integer', 'min' => 1, 'max' => 5]
['commit', 'string', 'length' => [0, 400]]
```

| 写法 | 含义 |
|---|---|
| `'in', 'range' => [...]` | 值必须落在枚举里 |
| `'integer', 'min' => 1, 'max' => 5` | 整数且有上下限（可替代部分 `compare`） |
| `'string', 'length' => [0, 400]` | 字符串长度区间 |

---

## 8. 和 Joi / Zod 的类比

同一段课程规则，用 Zod 近似是：

```ts
z.object({
  goods_id: z.number().int(),
  sku_id: z.number().int(),
  num: z.number().int().min(1),
  address_id: z.number().int(),
  remark: z.string().max(255).optional(),
});
```

| Yii2 | Zod/Joi |
|---|---|
| `rules()` 数组 | schema 对象 |
| `'required'` | 字段默认 required；Zod 用 `.optional()` 表示可空 |
| `'integer'` | `z.number().int()` |
| `'compare' >= 1` | `.min(1)` |
| `'string', 'max' => 255` | `z.string().max(255)` |
| `'in', 'range' => [...]` | `z.enum([...])` |

Yii2 是「字段 + 校验器名 + 选项」；Zod 是链式 API。语义一样：声明约束，校验时一次性套上。

---

## 9. 容易踩的坑

1. **只写 `integer` 挡不住负数**  
   必须再写 `compare`，或 `'integer', 'min' => 1`。

2. **没出现在 `required` 里就不是必填**  
   想让 `remark` 必填，要再加 `['remark', 'required']`。

3. **HTTP 参数全是字符串**  
   `"1"` 往往能过 `integer`（Yii 会尝试转换）。这不等于已经安全。

4. **`rules()` 只管格式，不管业务**  
   地址是否属于当前用户、SKU 是否上架、库存够不够，应在 Service/Node 里做。Form 是第一道门，不是全部。

5. **动态 `$ruleArray` 必须先赋值再 `validate()`**  
   真实 Form 每个 `*Validate()` 方法都是：设规则 → `setAttributes` → `validate()`。

---

## 10. 一句话记住

```text
[字段们, 校验器, 选项…]
```

课程这段就是：

> 商品/规格/数量/地址必填且为整数；数量至少为 1；备注可选，最长 255。

这是订单接口后端防线的「参数形状」层；金额、库存、优惠仍然要以后端 Service 为准。
