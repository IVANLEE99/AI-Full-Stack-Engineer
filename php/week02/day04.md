# Week 02 Day 04：BaseForm 校验

> 所属周：Week 02：Yii2 生命周期与 Filter  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-gateway`  
> 类型：架构理解  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

掌握 Yii2 Form Model / BaseForm 的参数校验思想，理解 `rules()`、`scenarios()`、错误返回的作用，并能用 Zod / Joi 做类比。

今天你要真正掌握这一句话：

> Yii2 Form Model 就像后端版 Zod/Joi schema：Controller 先把请求参数交给 Form 校验，校验通过才进入业务逻辑，校验失败就返回统一错误，避免脏数据进入 Service。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解为什么后端必须做参数校验
2. 理解 Yii2 Form Model 是什么
3. 理解 `rules()` 是什么
4. 理解常见 validator：`required`、`integer`、`string`、`boolean`、`in`
5. 理解 `scenarios()` 是什么
6. 理解同一个 Form 在不同 action 中使用不同 rules 子集
7. 理解校验失败如何返回错误
8. 找一个项目里的 Form 类，列出 rules
9. 写一个简单 Form 示例
10. 用 Zod/Joi 对照理解
11. 完成今日自测和 AI Review

---

## 1. 学习内容

### 1.1 为什么后端必须做参数校验？

前端已经校验了，后端为什么还要校验？

因为：

- 用户可以绕过前端，直接用 curl/Postman 调接口
- 恶意请求可能传错误类型或超长字符串
- 前端版本可能旧，传来的字段不完整
- 多端请求来源不同，不能只信一个前端
- 数据进入 Service / DB 前必须保证格式正确

例如下单接口需要：

```json
{
  "goods_id": 123,
  "quantity": 2,
  "address_id": 10
}
```

但用户可能传：

```json
{
  "goods_id": "abc",
  "quantity": -99
}
```

如果不校验，后续业务会出错，甚至产生安全问题。

---

### 1.2 Yii2 Form Model 是什么？

Yii2 里常用 Form Model 专门做请求参数校验。

它通常是一个类：

```php
class OrderConfirmForm extends BaseForm
{
    public int $goods_id;
    public int $quantity;

    public function rules(): array
    {
        return [
            [['goods_id', 'quantity'], 'required'],
            [['goods_id', 'quantity'], 'integer'],
        ];
    }
}
```

你可以先这样理解：

> Form Model 是请求参数的结构说明 + 校验规则集合。

前端类比：

```ts
const schema = z.object({
  goods_id: z.number(),
  quantity: z.number().int().positive(),
});
```

---

### 1.3 `rules()` 是什么？

`rules()` 是 Yii2 Form Model 中定义校验规则的方法。

常见写法：

```php
public function rules(): array
{
    return [
        [['name', 'email'], 'required'],
        ['email', 'email'],
        ['age', 'integer'],
        ['name', 'string', 'max' => 50],
    ];
}
```

规则含义：

| 规则 | 含义 |
|---|---|
| `required` | 必填 |
| `email` | 必须是邮箱格式 |
| `integer` | 必须是整数 |
| `number` | 必须是数字 |
| `string` | 必须是字符串 |
| `boolean` | 必须是布尔值 |
| `in` | 必须在指定范围内 |
| `default` | 设置默认值 |
| `safe` | 允许批量赋值但不强校验 |

---

### 1.4 rules 写法拆解

这一条：

```php
[['name', 'email'], 'required']
```

表示：

```text
name 和 email 都必填
```

这一条：

```php
['age', 'integer']
```

表示：

```text
age 必须是整数
```

这一条：

```php
['status', 'in', 'range' => [0, 1, 2]]
```

表示：

```text
status 只能是 0、1、2
```

---

### 1.5 最小 Form 示例

假设有一个创建用户接口，需要校验：

- `name` 必填，字符串，最长 50
- `age` 必填，整数
- `email` 必填，邮箱格式

PHP Form：

```php
<?php

declare(strict_types=1);

use yii\base\Model;

class CreateUserForm extends Model
{
    public string $name = '';
    public int $age = 0;
    public string $email = '';

    public function rules(): array
    {
        return [
            [['name', 'age', 'email'], 'required'],
            ['name', 'string', 'max' => 50],
            ['age', 'integer', 'min' => 1],
            ['email', 'email'],
        ];
    }
}
```

使用方式大概是：

```php
$form = new CreateUserForm();
$form->load(Yii::$app->request->post(), '');

if (!$form->validate()) {
    return $form->getErrors();
}
```

---

### 1.6 `load()` 是什么？

Yii2 Form 常见用法：

```php
$form->load($data, '');
```

作用：

> 把请求参数填充到 Form 对象的属性上。

第二个参数传 `''`，表示参数不是嵌套在表单名下面，而是直接从数组根部读取。

例如请求：

```php
$data = [
    'name' => 'Tom',
    'age' => 18,
];
```

执行：

```php
$form->load($data, '');
```

相当于：

```php
$form->name = 'Tom';
$form->age = 18;
```

---

### 1.7 `validate()` 是什么？

```php
$form->validate()
```

作用：

> 按 `rules()` 定义的规则检查 Form 属性是否合法。

返回值：

| 返回值 | 含义 |
|---|---|
| `true` | 校验通过 |
| `false` | 校验失败 |

校验失败后可以取错误：

```php
$form->getErrors();
```

可能返回：

```php
[
    'email' => ['Email is not a valid email address.'],
]
```

---

### 1.8 `scenarios()` 是什么？

同一个 Form 可能被多个接口复用，但不同接口需要的字段不同。

例如用户资料 Form：

- 创建用户：`name`、`email`、`password` 都必填
- 更新用户：只允许更新 `name`、`email`
- 修改密码：只校验 `old_password`、`new_password`

这时可以用 scenarios。

```php
public function scenarios(): array
{
    return [
        'create' => ['name', 'email', 'password'],
        'update' => ['name', 'email'],
        'changePassword' => ['old_password', 'new_password'],
    ];
}
```

使用：

```php
$form->scenario = 'create';
```

小白理解：

> scenarios 就是同一个 Form 的不同校验模式。

---

### 1.9 scenarios 类比 Zod

Zod 里你可能会这样拆：

```ts
const baseUserSchema = z.object({
  name: z.string(),
  email: z.string().email(),
  password: z.string(),
});

const createUserSchema = baseUserSchema;
const updateUserSchema = baseUserSchema.pick({
  name: true,
  email: true,
});
```

Yii2 scenarios 类似：

```php
public function scenarios(): array
{
    return [
        'create' => ['name', 'email', 'password'],
        'update' => ['name', 'email'],
    ];
}
```

对比：

| Yii2 | Zod |
|---|---|
| `rules()` | schema 规则 |
| `scenarios()` | `.pick()` / `.partial()` / 多 schema |
| `validate()` | `schema.parse()` / `safeParse()` |
| `getErrors()` | `error.issues` |

---

### 1.10 BaseForm 是什么？

企业项目通常不会直接每个 Form 都继承 `yii\base\Model`，而是封装一个 `BaseForm`。

`BaseForm` 可能负责：

- 统一加载参数
- 统一校验入口
- 统一错误格式
- 统一获取第一条错误
- 统一场景处理

类似：

```php
class BaseForm extends Model
{
    public function validateForm(array $data): bool
    {
        $this->load($data, '');
        return $this->validate();
    }

    public function getFirstErrorMessage(): string
    {
        $errors = $this->getFirstErrors();
        return reset($errors) ?: '参数错误';
    }
}
```

小白理解：

> BaseForm 是所有参数校验 Form 的基础类，用来统一项目里的校验流程和错误输出。

---

## 2. 源码阅读

本日无指定固定源码阅读，重点完成练习与复盘。

建议你在项目里找一个 Form 类，例如：

```text
*Form.php
```

可以优先找：

- 订单确认 Form
- 登录 Form
- 支付 Form
- 地址 Form
- 商品查询 Form

---

### 2.1 阅读 Form 的步骤

打开一个 Form 类后，按顺序看：

1. class 名是什么？
2. 继承了谁？是 `BaseForm` 还是 `Model`？
3. 有哪些 public 属性？
4. `rules()` 里定义了哪些规则？
5. 有没有 `scenarios()`？
6. 有没有自定义校验方法？
7. 校验失败后错误在哪里被返回？

---

### 2.2 Form 阅读记录表

| 观察点 | 记录 |
|---|---|
| Form 类名 |  |
| 继承类 |  |
| 适用接口/action |  |
| 字段列表 |  |
| 必填字段 |  |
| 类型校验 |  |
| scenarios |  |
| 自定义校验 |  |
| 错误返回方式 |  |

---

## 3. 练习任务

### 练习 1：找一个 Form 并列 rules

找到一个真实 Form，列出：

| 字段 | 是否必填 | 类型 | 其他规则 | 前端字段含义 |
|---|---|---|---|---|
|  |  |  |  |  |
|  |  |  |  |  |

---

### 练习 2：写 CreateUserForm 示例

```php
<?php

declare(strict_types=1);

use yii\base\Model;

class CreateUserForm extends Model
{
    public string $name = '';
    public int $age = 0;
    public string $email = '';

    public function rules(): array
    {
        return [
            [['name', 'age', 'email'], 'required'],
            ['name', 'string', 'max' => 50],
            ['age', 'integer', 'min' => 1],
            ['email', 'email'],
        ];
    }
}
```

写完后你要能解释每一条规则。

---

### 练习 3：写 scenarios 示例

```php
public function scenarios(): array
{
    return [
        'create' => ['name', 'email', 'password'],
        'update' => ['name', 'email'],
    ];
}
```

解释：

| scenario | 字段 | 用途 |
|---|---|---|
| `create` | `name,email,password` | 创建用户 |
| `update` | `name,email` | 更新用户 |

---

### 练习 4：写 Zod 对照

TypeScript：

```ts
import { z } from 'zod';

const createUserSchema = z.object({
  name: z.string().max(50),
  age: z.number().int().min(1),
  email: z.string().email(),
});
```

对照表：

| Yii2 rules | Zod |
|---|---|
| `required` | 字段默认必填 |
| `string`, `max` | `z.string().max()` |
| `integer`, `min` | `z.number().int().min()` |
| `email` | `z.string().email()` |

---

### 练习 5：写校验失败返回示例

```php
$form = new CreateUserForm();
$form->load($payload, '');

if (!$form->validate()) {
    return [
        'code' => 400,
        'message' => '参数错误',
        'errors' => $form->getErrors(),
    ];
}
```

你要理解：

> 校验失败时，不应该继续进入 Service。

---

## 4. JS/Node.js 类比

| Yii2 Form | Zod/Joi 类比 | 差异 |
|---|---|---|
| Form Model | schema object | Yii2 是 PHP class |
| public 属性 | schema 字段 | Yii2 属性承载请求参数 |
| `rules()` | schema rules | Yii2 用数组描述规则 |
| `validate()` | `parse()` / `validate()` | 返回 bool，错误通过 getErrors 获取 |
| `scenarios()` | 多 schema / `.pick()` | 同一个 Form 支持不同场景 |
| `BaseForm` | 项目封装的 validate helper | 统一错误和加载逻辑 |

---

## 5. AI Review 提问

完成 Form 表和示例后，把内容贴给 AI：

```text
我正在学习 Yii2 BaseForm / Form Model 参数校验。

我找了一个 Form 类，整理了 rules 和 scenarios，并写了 Yii2 Form vs Zod 对照表。
请你按资深 Yii2 后端工程师标准帮我检查：

1. 我对 rules() 的理解是否正确？
2. 我对 scenarios() 的理解是否准确？
3. 我列出的字段和前端参数对应关系是否合理？
4. Yii2 Form 和 Zod/Joi 的类比是否会误导？
5. 真实项目中 Form 校验还要注意哪些安全和边界问题？

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 6. 今日产出

- [ ] 一个真实 Form 的 rules 表
- [ ] Form 字段与前端字段对应表
- [ ] `CreateUserForm` 示例
- [ ] scenarios 示例
- [ ] Yii2 Form vs Zod/Joi 对照表
- [ ] 校验失败返回示例
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释为什么后端必须做参数校验
- [ ] 能解释 Yii2 Form Model 是什么
- [ ] 能解释 `rules()`
- [ ] 能说出 5 个常见 validator
- [ ] 能解释 `validate()`
- [ ] 能解释 `getErrors()`
- [ ] 能解释 `scenarios()`
- [ ] 能用 Zod/Joi 类比 Yii2 Form
- [ ] 能找一个真实 Form 并列出字段规则

---

## 8. 今日自测题

### 8.1 后端为什么不能只依赖前端校验？

参考答案：

> 因为用户可以绕过前端直接请求接口，前端版本也可能不一致，所以后端必须做最终参数校验。

---

### 8.2 Yii2 的 `rules()` 是什么？

参考答案：

> `rules()` 用来定义 Form Model 中各字段的校验规则，例如必填、类型、长度、范围等。

---

### 8.3 `required` 表示什么？

参考答案：

> 字段必填，不能为空。

---

### 8.4 `validate()` 返回什么？

参考答案：

> 返回 bool，`true` 表示校验通过，`false` 表示校验失败。

---

### 8.5 `getErrors()` 用来做什么？

参考答案：

> 获取校验失败的字段错误信息。

---

### 8.6 scenarios 解决什么问题？

参考答案：

> 解决同一个 Form 在不同接口或不同场景下需要校验不同字段的问题。

---

### 8.7 BaseForm 的意义是什么？

参考答案：

> BaseForm 用来统一所有 Form 的参数加载、校验入口和错误返回格式，减少重复代码。

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
我正在进行 Week 02 Day 04：BaseForm 校验 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 02 README](./README.md)
