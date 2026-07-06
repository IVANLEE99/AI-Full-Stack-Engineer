# Week 11 Day 03：Validate scene 分组

> 所属周：Week 11：ThinkPHP 8 门店 API  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`store-api`  
> 类型：编码练习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 ThinkPHP 8 Validate 与 `scene` 分组机制，能把 `OfflineStore` 验证器和 Yii2 Form `rules/scenarios`、Node.js Zod/Joi schema 做对照。

今天你要真正掌握这一句话：

> TP8 的 Validate scene 解决的是“同一个资源在新增、编辑、删除、查询等不同接口中需要校验不同字段”的问题；它类似 Yii2 scenarios，也类似 Zod 的 pick/partial/extend。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Yii2 Form rules/scenarios
2. 阅读 ThinkPHP 验证器文档
3. 打开 `OfflineStore.php` Validate 类
4. 找 `$rule`、`$message`、`$scene` 或 scene 方法
5. 区分新增、编辑、列表、状态切换等不同场景
6. 对比 Controller 里如何选择 scene
7. 对比 Yii2 Form 和 Zod/Joi
8. 整理字段、规则、场景、错误提示表
9. 用 AI Review 检查 scene 如何选择

---

## 1. 学习内容

### 1.1 为什么需要 Validate？

门店 API 不能直接相信前端传参。

常见参数风险：

| 参数 | 风险 |
|---|---|
| `name` | 为空、过长、包含非法字符 |
| `phone` | 格式错误 |
| `status` | 传入不存在的状态 |
| `longitude/latitude` | 经纬度格式错误 |
| `address` | 为空或过长 |
| `id` | 非整数或越权修改 |

Validate 的作用是把这些基础参数校验集中起来。

---

### 1.2 TP8 Validate 基本结构

验证器通常类似：

```php
<?php

namespace app\admin\validate\store;

use think\Validate;

class OfflineStore extends Validate
{
    protected $rule = [
        'name' => 'require|max:100',
        'phone' => 'require|mobile',
        'status' => 'in:0,1',
    ];

    protected $message = [
        'name.require' => '门店名称不能为空',
        'phone.mobile' => '手机号格式错误',
    ];

    protected $scene = [
        'add' => ['name', 'phone', 'status'],
        'edit' => ['id', 'name', 'phone', 'status'],
    ];
}
```

---

### 1.3 scene 是什么？

同一个门店资源，不同接口需要校验不同字段：

| 场景 | 需要字段 |
|---|---|
| 新增门店 | name、phone、address、status |
| 编辑门店 | id、name、phone、address、status |
| 删除门店 | id |
| 切换状态 | id、status |
| 列表查询 | page、limit、keyword、status |

scene 就是为这些接口选择不同字段组合。

---

### 1.4 Controller 如何选择 scene？

伪代码：

```php
<?php

$this->validate($params, OfflineStore::class . '.add');
```

或：

```php
<?php

validate(OfflineStore::class)
    ->scene('edit')
    ->check($params);
```

你要在 Controller 中找：

- 调用了哪个 Validate 类
- 使用了哪个 scene
- 校验失败如何返回

---

### 1.5 Yii2 Form 对照

| 概念 | Yii2 | TP8 |
|---|---|---|
| 验证类 | Form Model | Validate 类 |
| 规则 | `rules()` | `$rule` |
| 场景 | `scenarios()` | `$scene` / `sceneXxx()` |
| 错误消息 | `getFirstError()` | `getError()` |
| 使用位置 | Controller/Form load | Controller validate/check |

---

### 1.6 Node.js 类比

Zod 中可能写：

```js
const baseSchema = z.object({
  name: z.string().min(1),
  phone: z.string(),
  status: z.enum(['0', '1']),
});

const addSchema = baseSchema.pick({ name: true, phone: true, status: true });
const editSchema = baseSchema.extend({ id: z.number() });
```

TP8 scene 类似在同一个验证器里挑选字段。

---

## 2. 源码阅读

- `store-api/app/admin/validate/store/OfflineStore.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| 字段 | 规则 | 错误提示 | 使用 scene |
|---|---|---|---|
|  |  |  |  |

---

## 3. 练习任务

### 练习 1：读 OfflineStore Validate

记录 `$rule`、`$message`、`$scene`。

### 练习 2：理解 scene

列出每个 scene 对应哪个接口。

### 练习 3：对比 Yii2 Form

完成 Yii2 Form vs TP8 Validate 对照表。

---

## 4. JS/Node.js 类比

- Validate scene ≈ Zod `pick()` / `partial()`
- `$rule` ≈ schema rules
- `$message` ≈ validation error messages
- Yii2 `scenarios()` ≈ TP8 `$scene`
- Controller 选择 scene ≈ route handler 选择 DTO/schema

---

## 5. AI Review 提问

```text
我正在学习 TP8 Validate scene。
我已经阅读 OfflineStore Validate，并对比了 Yii2 Form scenarios 和 Zod pick。
请你检查：
1. 我对 scene 的理解是否正确？
2. 新增、编辑、删除、状态切换分别应该选择什么 scene？
3. 哪些字段校验应该放 Validate，哪些应该放 Service？
4. 与 Yii2 Form / Zod 的类比是否准确？
5. 真实项目中 Validate 最容易遗漏哪些安全问题？
```

---

## 6. 今日产出

- [ ] Validate 笔记
- [ ] 字段规则表
- [ ] scene 与接口对照表
- [ ] Yii2 Form vs TP8 Validate 对照
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 scene
- [ ] 能读懂 Validate 的规则和错误提示
- [ ] 能说明不同接口为什么选不同 scene
- [ ] 能对比 Yii2 Form scenarios
- [ ] 能用 Zod/Joi 类比 TP8 Validate

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
我正在进行 Week 11 Day 03：Validate scene 分组 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 11 README](./README.md)
