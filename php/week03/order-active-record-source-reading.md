# Week 03 Day 02：`Order` ActiveRecord 源码阅读

> 学习主题：Yii2 ActiveRecord 模型
>
> 目标源码：`php/week03/common/models/order/Order.php`
>
> 关联源码：`php/week03/common/repositorys/order/OrderRepository.php`

## 1. 阅读结论

这份 `Order.php` 是一个“薄 ActiveRecord Model”，主要负责：

- 映射数据库表
- 指定数据库连接
- 描述字段
- 定义字段校验规则
- 定义字段显示名称

本文件没有定义：

- `hasOne()` 关系
- `hasMany()` 关系
- 订单状态常量
- 复杂业务辅助方法
- 具体的查询方法

真正的订单查询主要封装在 `OrderRepository.php` 中。

## 2. 源码阅读记录表

| 观察点 | 实际内容 |
|---|---|
| 文件路径 | `php/week03/common/models/order/Order.php` |
| namespace | `common\models\order` |
| class 名 | `Order` |
| 直接父类 | `\common\BaseActiveRecord` |
| tableName | `order` |
| 数据库连接 | Yii 组件 `dbFecshop` |
| 主键字段 | 从注释和 Repository 推测为 `order_id`，最终应以表结构为准 |
| 订单号字段 | `order_no` |
| 用户字段 | `user_id` |
| 状态字段 | `order_status` |
| 金额字段 | `pay_amount`、`usd_pay_amount`、`cny_pay_amount` |
| 状态常量 | 本文件没有 |
| `hasOne()` 关系 | 本文件没有 |
| `hasMany()` 关系 | 本文件没有 |
| 业务辅助方法 | 本文件没有 |

## 3. namespace 与继承关系

源码：

```php
namespace common\models\order;

class Order extends \common\BaseActiveRecord
{
}
```

当前类没有直接继承 `yii\db\ActiveRecord`，而是继承项目封装的 `BaseActiveRecord`：

```text
Order
  ↓
BaseActiveRecord
  ↓
大概率是 yii\db\ActiveRecord
```

当前学习目录没有提供 `BaseActiveRecord.php`，因此最后一层继承关系需要回主项目查看源码确认。

`Order::find()` 没有写在当前文件中，是从父类继承来的查询入口。

## 4. 数据表映射

源码：

```php
public static function tableName()
{
    return 'order';
}
```

含义：

```text
Order PHP 类  ↔ MySQL 的 order 表
Order 对象    ↔ order 表中的一行
对象属性      ↔ order 表中的字段
```

Day 02 中的 `id`、`status` 是通用教学字段。当前项目实际使用的是：

```text
id     → order_id
status → order_status
```

阅读真实项目时，应以真实 Model、迁移文件和数据库表结构为准。

## 5. 数据库连接

源码：

```php
public static function getDb()
{
    return Yii::$app->get('dbFecshop');
}
```

说明该 Model 不使用默认的 `Yii::$app->db`，而是使用名为 `dbFecshop` 的数据库组件。

查询链路可以理解为：

```text
Order::find()
  ↓
确定模型对应 order 表
  ↓
Order::getDb()
  ↓
取得 dbFecshop 数据库连接
  ↓
生成并执行 SQL
```

在多数据库项目中，阅读 Model 时不能只看 `tableName()`，还要检查 `getDb()`。

## 6. 字段注释

文件顶部使用 `@property` 描述字段，例如：

```php
/**
 * @property int    $order_id
 * @property string $order_no
 * @property int    $user_id
 * @property int    $order_status
 * @property string $pay_amount
 */
```

使用时可以像普通对象属性一样访问：

```php
echo $order->order_id;
echo $order->order_no;
echo $order->order_status;
```

这些 `@property` 不是普通 PHP 属性声明，主要用于：

- 描述数据库字段
- IDE 自动提示
- 静态分析
- 帮助开发者阅读代码

实际数据通常由 ActiveRecord 根据数据库查询结果填充。

## 7. `rules()` 校验规则

### 7.1 必填字段

```php
[
    [
        'order_no',
        'site',
        'language',
        'to_usd_rate',
        'to_cny_rate',
        'order_status',
        'user_id',
        'pay_amount',
        'usd_pay_amount',
        'cny_pay_amount',
    ],
    'required',
]
```

这些字段在 Model 校验时不能为空。

### 7.2 数字字段

```php
[
    [
        'to_usd_rate',
        'to_cny_rate',
        'pay_amount',
        'usd_pay_amount',
        'cny_pay_amount',
        'total_weight',
        'captcha_score',
    ],
    'number',
]
```

金额、汇率和重量等字段必须符合数字格式。

### 7.3 整数字段

以下类型的字段使用 `integer` 校验：

- 订单状态
- 用户 ID
- 布尔状态标记
- 数量
- 时间戳
- 业务类型

需要注意：

> 校验数据是不是整数，与把数据转换成整数是两件事。

### 7.4 `safe`

```php
[['last_modify_time'], 'safe']
```

表示 `last_modify_time` 可以参与批量赋值，但没有主动检查其格式是否合法。

### 7.5 字符串长度

例如：

```php
[['order_no', 'related_order_no', 'payment_no', 'business_no'], 'string', 'max' => 32],
[['order_remark'], 'string', 'max' => 400],
[['closed_reason'], 'string', 'max' => 255],
```

这些规则限制字符串最大长度。

### 7.6 唯一校验

```php
[['order_no'], 'unique']
```

Yii2 会检查数据库中是否已经存在相同订单号。

但要注意：

> ActiveRecord 的 `unique` 校验不能代替 MySQL 唯一索引。

原因是两个并发请求可能同时通过应用层校验。最终的数据完整性仍应由数据库唯一约束保证。

## 8. `attributeLabels()`

源码示例：

```php
public function attributeLabels()
{
    return [
        'order_id' => 'Order ID',
        'order_no' => 'Order No',
        'user_id' => 'User ID',
    ];
}
```

作用是定义字段的显示名称，常用于：

- 表单校验错误
- 后台管理页面
- 自动生成的表单
- 字段展示

它不负责：

- 查询数据库
- 修改字段值
- 定义 MySQL 字段类型
- 创建数据库约束

## 9. `find()` 为什么不在当前文件中？

查询通常这样开始：

```php
Order::find()
```

但 `Order.php` 中没有定义 `find()`，因为该方法来自 ActiveRecord 继承链。

```text
Order::find()
  ↓
返回与 Order Model 绑定的查询对象
  ↓
继续添加 where/select/orderBy/limit 等条件
  ↓
调用 one/all/count 等终结方法
  ↓
执行 SQL
```

因此：

```php
$query = Order::find()->where(['order_no' => $orderNo]);
```

主要是在构造查询；调用下面的方法时才真正需要得到结果：

```php
$query->one();
$query->all();
$query->count();
```

## 10. Repository 中的真实查询

### 10.1 根据订单号查询对象

`OrderRepository::getOrderObjByNo()`：

```php
return Order::find()
    ->andWhere(['order_no' => $orderNo])
    ->one();
```

大致对应：

```sql
SELECT *
FROM `order`
WHERE `order_no` = :order_no
LIMIT 1;
```

返回值：

```text
Order 对象或 null
```

这里使用参数绑定思路，而不是把 `$orderNo` 直接拼进 SQL。

### 10.2 根据订单号查询数组

`OrderRepository::getOrderByOrderNo()`：

```php
return Order::find()
    ->select($field)
    ->where(['order_no' => $orderNo])
    ->asArray()
    ->one($this->getDb());
```

返回值：

```text
PHP 关联数组或 null
```

### 10.3 分页查询

`OrderRepository::getPageList()` 的核心链路：

```php
$order = Order::find();

$order->orderBy(['order_id' => SORT_DESC]);

return $order
    ->offset(($page - 1) * $pageSize)
    ->limit($pageSize)
    ->asArray()
    ->all($this->getDb());
```

它完成：

```text
构造订单查询
  ↓
添加动态 where 条件
  ↓
选择返回字段
  ↓
按 order_id 倒序
  ↓
计算 offset 和 limit
  ↓
返回数组列表
```

## 11. 对象与数组返回值

| 查询写法 | 返回值 |
|---|---|
| `one()` | 一个 AR 对象或 `null` |
| `all()` | AR 对象数组 |
| `asArray()->one()` | 一个关联数组或 `null` |
| `asArray()->all()` | 关联数组列表 |

例如：

```php
$order = Order::find()->where(['order_id' => 1])->one();
echo $order->order_no;
```

```php
$order = Order::find()
    ->where(['order_id' => 1])
    ->asArray()
    ->one();

echo $order['order_no'];
```

## 12. 当前源码没有关系方法

Day 02 要求检查 `hasOne()` 和 `hasMany()`，但当前 `Order.php` 中没有找到这两类关系方法。

因此不能写成：

```text
Order 已经定义 getAddress() 和 getGoods()
```

准确记录应该是：

```text
hasOne 关系：当前文件没有
hasMany 关系：当前文件没有
```

如果业务代码需要通过下面的形式访问关联数据：

```php
$order->goods;
$order->address;
```

就需要继续检查：

- 父类是否动态提供了关系
- Trait 是否提供了关系
- 项目中是否存在其他扩展 Model
- Repository 是否通过 JOIN 单独查询关系数据

## 13. ActiveRecord 与 Sequelize 类比

| Yii2 ActiveRecord | Sequelize | 说明 |
|---|---|---|
| `Order` | Sequelize `Order` Model | 映射数据库表 |
| `tableName()` | `tableName` 配置 | 指定表名 |
| `Order::find()` | 构造查询选项 | Yii2 先返回 Query 对象 |
| `where([...])` | `where: {...}` | 添加条件 |
| `one()` | `findOne()` | 查询一条 |
| `all()` | `findAll()` | 查询多条 |
| `asArray()` | `raw: true` | 返回普通数据结构 |
| `hasOne()` / `hasMany()` | associations | 定义模型关系 |

这个类比只用于帮助理解，API、返回类型、关联加载和生命周期并不完全相同。

## 14. 最重要的源码发现

1. `Order` 对应 `order` 表。
2. 它使用 `dbFecshop`，不是默认数据库连接。
3. 实际字段是 `order_id`、`order_status`，不是通用示例中的 `id`、`status`。
4. `find()` 是从 ActiveRecord 继承链获得的。
5. 查询链通常在 `one()`、`all()`、`count()` 等方法处执行并返回结果。
6. `rules()` 是应用层校验，不能代替 MySQL 约束。
7. 当前 Model 没有 `hasOne()`、`hasMany()`、状态常量和业务辅助方法。
8. 真正的查询逻辑主要收口在 `OrderRepository`。

## 15. 自测题

尝试不看答案口头回答：

1. `Order` 对应哪张表？
2. `Order` 使用哪个数据库组件？
3. 为什么当前文件中找不到 `find()`？
4. `one()` 和 `asArray()->one()` 的返回值有什么区别？
5. 当前项目的主键字段更可能是 `id` 还是 `order_id`？
6. `safe` 校验器做什么，又不做什么？
7. `rules()` 中的 `unique` 为什么不能替代 MySQL 唯一索引？
8. 当前 `Order` 是否已经定义订单商品和地址关系？
9. 为什么查询逻辑应该收口到 Repository，而不是散落在 Controller？

## 16. 一句话总结

> `Order` Model 负责把 `dbFecshop` 中的 `order` 表映射为 ActiveRecord，并定义字段校验和标签；具体查询由 Repository 调用继承来的 `find()` 构造，在 `one()`、`all()` 等终结方法处取得结果。
