# Week 03 Day 02：ActiveRecord 模型

> 所属周：Week 03：MySQL + Redis + ORM  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-core`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

掌握 Yii2 ActiveRecord 的基础概念和链式查询写法，能读懂 `Order.php` Model 的表映射、字段、关系方法，并能把 Yii2 AR 和 Sequelize Model 做类比。

今天你要真正掌握这一句话：

> ActiveRecord 是把数据库表映射成 PHP 类的 ORM 模式；`Order::find()->where(...)->one()` 就像 Sequelize 的 `Order.findOne({ where: ... })`，都是用对象和链式 API 代替手写 SQL。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解 ORM 是什么
2. 理解 ActiveRecord 是什么
3. 理解 Model 类和数据库表的关系
4. 学会 `tableName()`
5. 学会 `find()` / `where()` / `one()` / `all()`
6. 学会 `select()` / `orderBy()` / `limit()`
7. 理解 `asArray()` 的作用
8. 理解关联关系：`hasOne()` / `hasMany()`
9. 阅读 `mall-core/common/models/order/Order.php`
10. 对比 Sequelize `findOne()` / `findAll()`
11. 列出 5 个常用 AR 查询方法
12. 完成今日自测和 AI Review

---

## 1. 学习内容

### 1.1 ORM 是什么？

ORM 是 Object Relational Mapping，对象关系映射。

小白理解：

> ORM 把数据库表变成代码里的类，把表中的一行数据变成一个对象。

比如数据库表 `orders`：

| id | order_no | status |
|---|---|---|
| 1 | O001 | 1 |

在 PHP 里可能变成：

```php
$order = Order::find()->where(['id' => 1])->one();

echo $order->order_no;
echo $order->status;
```

你不用手写：

```sql
SELECT * FROM orders WHERE id = 1 LIMIT 1;
```

ORM 帮你生成或封装了查询。

---

### 1.2 ActiveRecord 是什么？

ActiveRecord 是一种 ORM 模式。

在 Yii2 中，一个 ActiveRecord Model 通常对应一张数据库表。

例如：

```php
class Order extends \yii\db\ActiveRecord
{
    public static function tableName(): string
    {
        return 'order';
    }
}
```

含义：

```text
Order 这个 PHP 类，对应数据库里的 order 表。
```

类比 Sequelize：

```js
const Order = sequelize.define('Order', {
  order_no: DataTypes.STRING,
  status: DataTypes.INTEGER,
});
```

---

### 1.3 Model 类和表的关系

Yii2 AR 常见结构：

```php
namespace common\models\order;

use yii\db\ActiveRecord;

class Order extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%order}}';
    }
}
```

你重点看：

| 代码 | 含义 |
|---|---|
| `class Order extends ActiveRecord` | Order 是 AR Model |
| `tableName()` | 指定对应数据库表 |
| `{{%order}}` | 表名，可能带表前缀 |
| `$order->id` | 访问行里的 id 字段 |
| `$order->order_no` | 访问行里的 order_no 字段 |

---

### 1.4 `find()` 是什么？

Yii2 查询通常从 `find()` 开始：

```php
$query = Order::find();
```

`find()` 返回一个查询对象，你可以继续链式调用：

```php
Order::find()
    ->where(['id' => 1])
    ->one();
```

小白理解：

```text
Order::find() ≈ 我要开始查 order 表
```

---

### 1.5 `where()` 是什么？

`where()` 用来加查询条件。

```php
Order::find()
    ->where(['id' => 1])
    ->one();
```

大概等价 SQL：

```sql
SELECT * FROM order WHERE id = 1 LIMIT 1;
```

多个条件：

```php
Order::find()
    ->where([
        'user_id' => 100,
        'status' => 1,
    ])
    ->all();
```

等价：

```sql
SELECT * FROM order WHERE user_id = 100 AND status = 1;
```

---

### 1.6 `one()` 和 `all()`

`one()`：查一条。

```php
$order = Order::find()->where(['id' => 1])->one();
```

返回：

```text
Order 对象 或 null
```

`all()`：查多条。

```php
$orders = Order::find()->where(['user_id' => 100])->all();
```

返回：

```text
Order 对象数组
```

Sequelize 类比：

| Yii2 | Sequelize |
|---|---|
| `one()` | `findOne()` |
| `all()` | `findAll()` |

---

### 1.7 `select()`、`orderBy()`、`limit()`

只查部分字段：

```php
Order::find()
    ->select(['id', 'order_no', 'status'])
    ->where(['user_id' => 100])
    ->all();
```

排序：

```php
Order::find()
    ->where(['user_id' => 100])
    ->orderBy(['created_at' => SORT_DESC])
    ->all();
```

限制数量：

```php
Order::find()
    ->orderBy(['created_at' => SORT_DESC])
    ->limit(10)
    ->all();
```

大概 SQL：

```sql
SELECT * FROM order ORDER BY created_at DESC LIMIT 10;
```

---

### 1.8 `asArray()` 是什么？

默认情况下，AR 查询返回对象：

```php
$order = Order::find()->where(['id' => 1])->one();

echo $order->order_no;
```

如果加 `asArray()`，返回数组：

```php
$order = Order::find()
    ->where(['id' => 1])
    ->asArray()
    ->one();

echo $order['order_no'];
```

对比：

| 写法 | 返回 |
|---|---|
| 不加 `asArray()` | AR 对象 |
| 加 `asArray()` | PHP 关联数组 |

什么时候用 `asArray()`？

- 只读数据
- 不需要调用对象方法
- 想减少对象开销
- 要直接组装响应数组

---

### 1.9 `hasOne()` 和 `hasMany()`

AR 可以定义表关系。

一个订单有一个地址：

```php
public function getAddress()
{
    return $this->hasOne(OrderAddress::class, ['order_id' => 'id']);
}
```

一个订单有多个商品：

```php
public function getGoods()
{
    return $this->hasMany(OrderGoods::class, ['order_id' => 'id']);
}
```

含义：

| 方法 | 关系 |
|---|---|
| `hasOne()` | 一对一 |
| `hasMany()` | 一对多 |

小白理解：

```text
Order hasOne Address
Order hasMany Goods
```

---

### 1.10 AR 的优点和注意点

优点：

- 少写 SQL
- 查询链式可读
- 表和类映射清楚
- 关系查询方便

注意点：

- 复杂查询可能不如 SQL 直观
- 滥用关系查询可能导致 N+1 问题
- 大数据量查询要注意性能
- 不要在 Controller 里到处直接写 AR 查询，最好收口到 Repository

---

## 2. 源码阅读

- `mall-core/common/models/order/Order.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

### 2.1 阅读目标

打开 `Order.php` 后，重点找：

1. namespace 是什么
2. class 是否继承 ActiveRecord
3. `tableName()` 返回什么
4. 是否有 `rules()`
5. 是否有 `attributeLabels()`
6. 是否有 `hasOne()` / `hasMany()` 关系
7. 是否有状态常量
8. 是否有业务辅助方法

---

### 2.2 Order Model 阅读记录表

| 观察点 | 记录 |
|---|---|
| 文件路径 | `mall-core/common/models/order/Order.php` |
| namespace |  |
| class 名 |  |
| 父类 |  |
| tableName |  |
| 主要字段 |  |
| 状态常量 |  |
| hasOne 关系 |  |
| hasMany 关系 |  |

---

### 2.3 常用 AR 方法记录

至少列 5 个：

| 方法 | 作用 | 示例 |
|---|---|---|
| `find()` | 开始查询 | `Order::find()` |
| `where()` | 添加条件 | `->where(['id' => 1])` |
| `one()` | 查一条 | `->one()` |
| `all()` | 查多条 | `->all()` |
| `asArray()` | 返回数组 | `->asArray()` |
| `orderBy()` | 排序 | `->orderBy(['id' => SORT_DESC])` |
| `limit()` | 限制数量 | `->limit(10)` |

---

## 3. 练习任务

### 练习 1：写基础 AR 查询

```php
$order = Order::find()
    ->where(['id' => 1])
    ->one();
```

写出大概 SQL：

```sql
SELECT * FROM order WHERE id = 1 LIMIT 1;
```

---

### 练习 2：写列表查询

```php
$orders = Order::find()
    ->where(['user_id' => 100])
    ->orderBy(['created_at' => SORT_DESC])
    ->limit(10)
    ->all();
```

解释：

```text
查询用户 100 最新的 10 条订单。
```

---

### 练习 3：写 asArray 查询

```php
$order = Order::find()
    ->select(['id', 'order_no', 'status'])
    ->where(['id' => 1])
    ->asArray()
    ->one();
```

解释返回：

```php
[
    'id' => 1,
    'order_no' => 'O001',
    'status' => 1,
]
```

---

### 练习 4：Yii2 AR vs Sequelize 对比

Yii2：

```php
$order = Order::find()
    ->where(['order_no' => $orderNo])
    ->one();
```

Sequelize：

```js
const order = await Order.findOne({
  where: { order_no: orderNo },
});
```

对比表：

| 操作 | Yii2 AR | Sequelize |
|---|---|---|
| 查一条 | `one()` | `findOne()` |
| 查多条 | `all()` | `findAll()` |
| 条件 | `where([...])` | `where: {...}` |
| 排序 | `orderBy()` | `order` |
| 限制 | `limit()` | `limit` |

---

### 练习 5：读 Order Model 并写笔记

写：

```text
Order.php 对应的表：
主键字段：
订单号字段：
用户 ID 字段：
状态字段：
创建时间字段：
有哪些关系方法：
有哪些常量：
我最不理解的方法：
```

---

## 4. JS/Node.js 类比

| Yii2 ActiveRecord | Sequelize / Node 类比 | 差异 |
|---|---|---|
| `Order` Model | Sequelize `Order` Model | 都映射表 |
| `tableName()` | model table config | Yii2 用静态方法声明 |
| `Order::find()` | `Order.findAll()` 查询入口 | Yii2 先返回 query 对象 |
| `where()` | `where: {}` | 条件表达方式不同 |
| `one()` | `findOne()` | 查一条 |
| `all()` | `findAll()` | 查多条 |
| `asArray()` | `raw: true` | 返回普通数组/对象 |
| `hasOne()` / `hasMany()` | association | 表关系定义 |

---

## 5. AI Review 提问

完成 Order Model 阅读后，把笔记贴给 AI：

```text
我正在学习 Yii2 ActiveRecord。

我阅读了 Order.php Model，并整理了 tableName、字段、关系方法和常用查询方法。
请你按资深 Yii2 后端工程师标准帮我检查：

1. 我对 ActiveRecord 的理解是否正确？
2. 我对 Order::find()->where()->one() 的 SQL 类比是否准确？
3. 我对 asArray() 的理解是否正确？
4. 我对 hasOne/hasMany 的理解是否准确？
5. Yii2 AR 和 Sequelize 的类比有哪些容易误导的地方？

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 6. 今日产出

- [ ] `Order.php` Model 阅读笔记
- [ ] tableName / 字段 / 关系方法记录表
- [ ] 5 个常用 AR 查询方法
- [ ] Yii2 AR vs Sequelize 对照表
- [ ] 3 个 AR 查询示例及 SQL 类比
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 ORM 是什么
- [ ] 能解释 ActiveRecord 是什么
- [ ] 能说明 Model 类和数据库表的关系
- [ ] 能解释 `tableName()`
- [ ] 能读懂 `find()->where()->one()`
- [ ] 能读懂 `find()->where()->all()`
- [ ] 能解释 `asArray()`
- [ ] 能解释 `hasOne()` / `hasMany()`
- [ ] 能用 Sequelize 类比 Yii2 AR
- [ ] 能读懂 `Order.php` 的基本结构

---

## 8. 今日自测题

### 8.1 ORM 是什么？

参考答案：

> ORM 是对象关系映射，用代码里的类和对象来表示数据库表和行。

---

### 8.2 ActiveRecord 中一个 Model 通常对应什么？

参考答案：

> 通常对应数据库中的一张表。

---

### 8.3 `Order::find()->where(['id' => 1])->one()` 大概对应什么 SQL？

参考答案：

```sql
SELECT * FROM order WHERE id = 1 LIMIT 1;
```

---

### 8.4 `one()` 和 `all()` 有什么区别？

参考答案：

> `one()` 查一条，返回一个对象或 null；`all()` 查多条，返回对象数组。

---

### 8.5 `asArray()` 有什么作用？

参考答案：

> 让查询结果返回 PHP 关联数组，而不是 ActiveRecord 对象。

---

### 8.6 `hasMany()` 表示什么关系？

参考答案：

> 表示一对多关系，例如一个订单有多个订单商品。

---

### 8.7 为什么不建议 Controller 里到处直接写 AR 查询？

参考答案：

> 因为查询逻辑会分散，难维护；企业项目通常把查询收口到 Repository。

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
我正在进行 Week 03 Day 02：ActiveRecord 模型 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 03 README](./README.md)
