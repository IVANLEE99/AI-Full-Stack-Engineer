# Week 03 Day 03：`OrderRepository` 源码阅读

> 学习主题：Repository 模式
>
> 目标源码：`php/week03/common/repositorys/order/OrderRepository.php`
>
> 关联源码：`php/week03/common/models/order/Order.php`

## 1. 阅读结论

`OrderRepository` 是订单领域的数据访问入口，主要通过 Yii2 ActiveRecord 和 Query Builder 操作 `order` 表，同时查询 `order_goods`、`order_amount`、`payment` 等关联表。

标准链路是：

```text
Controller
  ↓
Service
  ↓
OrderRepository
  ↓
Order / OrderGoods / OrderAmount ActiveRecord
  ↓
dbFecshop
  ↓
MySQL
```

但当前源码不是一个完全“纯粹”的 Repository。除了数据访问，它还承担了：

- 订单状态常量和文案映射
- 多语言翻译
- Redis 缓存读取和写入
- 业务状态判断
- 统计报表结果整形
- 图表数据组装
- 调用 Service 或其他 Repository 的常量

因此学习时要区分：

```text
Repository 模式的理想职责
≠
当前历史项目中 Repository 的全部实际职责
```

## 2. 文件概览

| 观察点 | 实际内容 |
|---|---|
| 文件路径 | `php/week03/common/repositorys/order/OrderRepository.php` |
| namespace | `common\repositorys\order` |
| class 名 | `OrderRepository` |
| 父类 | `\common\BaseRepository` |
| 文件长度 | 约 1,986 行 |
| public 方法 | 74 个 |
| 核心 Model | `Order` |
| 关联 Model | `OrderAmount`、`OrderGoods` 等 |
| 数据库连接 | 最终来自 `Order::getDb()`，即 `dbFecshop` |
| 查询方式 | ActiveRecord、Query Builder、少量原生 SQL |
| 缓存依赖 | `UserRedis`、`StockRedis` |

当前学习目录没有提供 `BaseRepository.php`，所以父类中的 `getDb()`、`instance()` 等具体实现需要回主项目确认。

## 3. 引入的依赖

源码顶部引入：

```php
use App\Utils\BaseFunction;
use common\models\order\Order;
use common\models\order\OrderAddress;
use common\models\order\OrderAmount;
use common\models\order\OrderGoods;
use common\models\pay\Payment;
use common\redis\order\StockRedis;
use common\redis\user\UserRedis;
use common\repositorys\faq\FaqUserQuestionRepository;
use common\repositorys\pay\PaymentRepository;
use common\services\pay\PaymentService;
use yii\db\ActiveRecord;
```

可以按职责分类：

| 类型 | 依赖 | 作用 |
|---|---|---|
| 工具 | `BaseFunction` | 格式化语言代码 |
| 主 Model | `Order` | 查询和更新订单表 |
| 关联 Model | `OrderAmount`、`OrderGoods` | 金额、商品关联查询 |
| Redis | `UserRedis`、`StockRedis` | 订单计数缓存、触发后续同步 |
| 其他数据层 | `FaqUserQuestionRepository` | 使用交易状态常量 |
| Service | `PaymentService` | 使用支付类型常量 |

需要注意：Repository 反向依赖 Service 会增加层间耦合。数据访问层通常不应依赖上层业务 Service；共享常量更适合放在领域常量、枚举或 Value Object 中。

部分 `use` 在当前文件中没有找到实际调用，可能是历史遗留，需要结合完整项目和静态分析确认后再清理。

## 4. 类继承和数据库连接

类定义：

```php
class OrderRepository extends \common\BaseRepository
```

数据连接方法：

```php
public function getConnection()
{
    return Order::getDb();
}
```

而 `Order::getDb()` 返回：

```php
Yii::$app->get('dbFecshop');
```

所以主要数据访问链路是：

```text
OrderRepository::getConnection()
  ↓
Order::getDb()
  ↓
Yii::$app->get('dbFecshop')
```

源码中有两种执行方式：

```php
->one();
->all();
```

以及：

```php
->one($this->getDb());
->all($this->getDb());
```

不显式传连接时，`Order` 查询通常会使用 `Order::getDb()`。显式传入的 `$this->getDb()` 来自 `BaseRepository`，它是否与 `getConnection()` 完全一致，需要查看父类实现确认。

## 5. 常量和状态映射

Repository 定义了大量订单领域常量：

### 5.1 订单状态

```php
const STATUS_UNPAID = '0';
const STATUS_PAID_AUDIT = '1';
const STATUS_UNDELIVERY = '2';
const STATUS_CLOSED = '4';
const STATUS_COMPLETED = '6';
const STATUS_PAID_SUCCESS = '7';
const STATUS_DELIVERY_SECTION = '8';
const STATUS_DELIVERY_ALL = '9';
const STATUS_IN_STOCK = '10';
const STATUS_PAYMENT_PROCESSING = 11;
```

这里大多数状态是字符串，`STATUS_PAYMENT_PROCESSING` 却是整数。源码大量使用宽松比较 `==`、`!=`，虽然可能暂时兼容，但类型不一致会增加隐藏错误风险。

### 5.2 其他常量

- 订单类型
- 支付状态
- 游客/注册用户状态
- 关单类型
- 售后支持状态
- 销售类型
- 税费类型
- 门店优惠状态

这些常量确实会影响查询条件和更新数据，但从职责边界看，更适合放在订单领域枚举、领域常量类或 Order Model 附近，而不是让 Repository 同时成为状态定义中心。

## 6. 方法分类

### 6.1 新增与更新

| 方法 | 作用 | 返回值 |
|---|---|---|
| `insert()` | 直接插入订单数据 | 最后插入 ID |
| `update()` | 按条件批量更新 | 受影响行数 |
| `updateAmount()` | 更新订单金额 | 受影响行数 |
| `updateOrderStatus()` | 更新订单状态 | 受影响行数 |
| `updateOrderPaidAudit()` | 将订单更新为支付待审核 | `false` 或受影响行数 |
| `closeTestOrder()` | 关闭测试订单并写 Redis | 受影响行数 |
| `updatePaymentNo()` | 更新支付单号 | 受影响行数 |
| `updateOrderPaid()` | 更新为支付成功 | 受影响行数 |
| `updateOrderCancel()` | 更新为关闭状态 | 受影响行数 |
| `updateOrderPaidAt()` | 更新支付时间 | 受影响行数 |

### 6.2 查询单条数据

| 方法 | 查询条件 | 返回形式 |
|---|---|---|
| `getOrderObjByNo()` | `order_no` | `Order` 对象或 `null` |
| `getOrderByOrderNo()` | `order_no` | 数组、`null`，空参数时返回 `[]` |
| `getOrderByPaymentNo()` | `payment_no` | 数组或 `null` |
| `getOrderByOrderNoAndUserId()` | 订单号，可选用户 ID | 数组或 `null` |
| `getOrderObjById()` | `order_id` | `Order` 对象或 `null` |
| `getLastOrderByUserId()` | 用户 ID | 最新订单数组或 `null` |
| `getOneByCondition()` | 动态条件 | 数组或 `null` |
| `getLastOrderPaidAt()` | 用户和时间范围 | 最大支付时间标量 |

### 6.3 查询列表

| 方法 | 作用 |
|---|---|
| `getPageList()` | 动态条件分页 |
| `getOrderByOrderNos()` | 按订单号查询列表 |
| `getOrderArrayByOrderNos()` | 按订单号集合查询数组列表 |
| `getOrderByUserId()` | 查询用户订单列表 |
| `getOrderListByOrderNos()` | JOIN 订单商品后查询 |
| `getReplenishOrderList()` | 动态条件查询补发订单 |
| `getListByBusinessNo()` | 按业务单号查询 AR 对象列表 |
| `getList()` | 按订单号集合查询数组列表 |
| `getListByCondition()` | 动态条件查询列表 |
| `getWhereList()` | 动态条件查询列表 |
| `getListByMinIdAndMaxId()` | 按主键范围分批读取 |
| `getPaidListByUserId()` | 查询已支付订单列表 |

### 6.4 统计和聚合

| 方法 | 作用 |
|---|---|
| `getCount()` | 动态条件计数 |
| `getOrderStatusGroupCountByUserId()` | 按订单状态分组计数 |
| `getOrderGroupCountByUserIds()` | 按用户分组计数 |
| `getReturnOrderCountByUserId()` | 退款订单计数 |
| `getCouponUseStatisticsData()` | 优惠券使用统计 |
| `getOrderStatisticsData()` | 动态维度统计 |
| `countOrderData()` | 按支付方式、站点和日期统计 |
| `getPlaceGroupList()` | 下单数据分组统计 |
| `getHourSaleData()` | 小时销售额统计 |
| `getSaleData()` | 指定时间销售额统计 |
| `sumSkuSaleCount()` | SKU 销售数量统计 |
| `getPurchaseCountGroupByUser()` | 用户购买次数统计 |
| `storeOrderStatistics*()` | 门店维度统计 |

### 6.5 缓存、业务判断和结果组装

| 方法 | 实际职责 |
|---|---|
| `getCountFromCache()` | Redis 缓存旁路读取 |
| `getStatusMpping()` | 状态文案翻译 |
| `getOrderStatusStr()` | 返回本地化状态文案 |
| `getTradingStatus()` | 根据订单状态计算业务状态 |
| `dataComparisonOfLastYear()` | 组装图表序列 |

最后这一组已经超出纯数据访问职责，是理解当前 Repository 边界不够清晰的关键证据。

## 7. 代表方法一：`insert()`

源码：

```php
public function insert($data)
{
    $this->getConnection()
        ->createCommand()
        ->insert(Order::tableName(), $data)
        ->execute();

    return $this->getConnection()->getLastInsertID();
}
```

大致 SQL：

```sql
INSERT INTO `order` (...columns)
VALUES (...values);
```

特点：

- 使用 Query Builder 生成 INSERT
- 返回最后插入 ID
- 没有创建 `Order` 对象
- 不会自动执行 `Order::validate()`
- 不会经过 ActiveRecord 的 `beforeSave()`、`afterSave()` 等模型事件

所以调用方必须确认：

- 数据已经完成校验
- 必填字段完整
- 时间戳等字段已经设置
- 是否确实不需要 AR 行为和事件

## 8. 代表方法二：`update()`

源码：

```php
public function update($updateData, $where)
{
    return Order::updateAll($updateData, $where);
}
```

大致 SQL：

```sql
UPDATE `order`
SET ...
WHERE ...;
```

`updateAll()` 返回受影响行数，不是严格的布尔值。

这意味着返回 `0` 可能表示：

- 没有匹配记录
- 数据库判断新旧值没有变化
- 更新未产生受影响行

它不一定能简单等价为“执行失败”。此外，`updateAll()` 不执行 AR 实例级校验和保存事件。

## 9. 代表方法三：`getPageList()`

核心源码：

```php
$order = Order::find();

foreach ($where as $condition) {
    $order->andWhere($condition);
}

$order->orderBy(['order_id' => SORT_DESC]);

return $order
    ->offset(($page - 1) * $pageSize)
    ->limit($pageSize)
    ->asArray()
    ->all($this->getDb());
```

大致 SQL：

```sql
SELECT ...
FROM `order`
WHERE ...
ORDER BY `order_id` DESC
LIMIT :page_size OFFSET :offset;
```

执行步骤：

```text
Order::find()
  ↓
逐个添加查询条件
  ↓
选择字段
  ↓
设置排序
  ↓
计算 offset 和 limit
  ↓
返回数组列表
```

注意：页数很深时，OFFSET 分页可能扫描和丢弃大量记录。批处理场景可以参考后面的主键游标方法 `getListByMinIdAndMaxId()`。

## 10. 代表方法四：对象查询与数组查询

### 10.1 返回 AR 对象

```php
public function getOrderObjByNo($orderNo)
{
    return Order::find()
        ->andWhere(['order_no' => $orderNo])
        ->one();
}
```

大致 SQL：

```sql
SELECT *
FROM `order`
WHERE `order_no` = :order_no
LIMIT 1;
```

返回：

```text
Order 对象或 null
```

### 10.2 返回数组

```php
public function getOrderByOrderNo($orderNo, $field = '*')
{
    if (empty($orderNo)) {
        return [];
    }

    return Order::find()
        ->select($field)
        ->where(['order_no' => $orderNo])
        ->asArray()
        ->one($this->getDb());
}
```

返回：

```text
关联数组、null，或者在参数为空时返回 []
```

因此调用方不能只按方法名判断返回类型，还需要检查：

- 是否调用 `asArray()`
- 终结方法是 `one()` 还是 `all()`
- 是否存在提前返回
- PHPDoc 是否与实现一致

## 11. 代表方法五：`getLastOrderByUserId()`

源码：

```php
return Order::find()
    ->andWhere(['=', 'user_id', $userId])
    ->andWhere(['!=', 'order_type', self::ORDER_TYPE_REISSUE])
    ->andWhere(['=', 'del_flag', 0])
    ->select($fields)
    ->orderBy(['order_id' => SORT_DESC])
    ->asArray()
    ->one($this->getDb());
```

它表达的查询意图是：

```text
查询指定用户
  ↓
排除补发订单
  ↓
排除逻辑删除数据
  ↓
按 order_id 倒序
  ↓
取第一条
```

这里的 `orderBy()` 很重要。如果只是 `where(...)->one()` 而没有排序，“第一条”不等于“最新一条”。

## 12. 代表方法六：分组统计

`getOrderStatusGroupCountByUserId()`：

```php
return Order::find()
    ->andWhere(['=', 'user_id', $userId])
    ->andWhere(['=', 'site', $site])
    ->andWhere(['in', 'order_status', $statusList])
    ->groupBy(['order_status'])
    ->select('count(order_status) as order_count, order_status')
    ->asArray()
    ->all();
```

大致 SQL：

```sql
SELECT
    COUNT(`order_status`) AS `order_count`,
    `order_status`
FROM `order`
WHERE `user_id` = :user_id
  AND `site` = :site
  AND `order_status` IN (...)
GROUP BY `order_status`;
```

结果不是订单列表，而是每个状态对应的订单数量。

## 13. 代表方法七：JOIN 查询

`getCouponUseStatisticsData()` 使用两个 LEFT JOIN：

```php
Order::find()
    ->alias('a')
    ->join('left join', 'coupon_user b', 'a.coupon_user_id = b.id')
    ->join('left join', 'order_amount c', 'a.order_no = c.order_no')
    ->andWhere(['between', 'a.paid_at', $startTimestamp, $endTimestamp])
    ->andWhere(['=', 'a.is_paid', self::IS_PAID_OVER])
    ->groupBy('b.coupon_code')
    ->asArray()
    ->all($this->getDb());
```

查询关系：

```text
order a
  ├─ LEFT JOIN coupon_user b
  │    ON a.coupon_user_id = b.id
  └─ LEFT JOIN order_amount c
       ON a.order_no = c.order_no
```

阅读 JOIN 时按顺序检查：

1. 主表是谁？
2. 别名是什么？
3. JOIN 条件是否正确？
4. 一对多 JOIN 是否会放大行数？
5. WHERE 是否改变了 LEFT JOIN 的语义？
6. GROUP BY 是否与选出字段兼容？
7. 关联字段是否有合适索引？

## 14. 代表方法八：Redis 缓存旁路

`getCountFromCache()`：

```php
$orderCount = UserRedis::instance()->getUserOrderCount($userId);

if (!empty($orderCount)) {
    return $orderCount;
}

$orderCount = Order::find()
    ->andWhere(['=', 'user_id', $userId])
    ->andWhere(['=', 'del_flag', 0])
    ->count('*', $this->getDb());

if ($orderCount > 0) {
    UserRedis::instance()->setUserOrderCount($userId, $orderCount);
}

return $orderCount;
```

流程：

```text
读取 Redis
  ├─ 有非零计数：直接返回
  └─ 没有：查询 MySQL
               ↓
          大于 0 时写 Redis
               ↓
              返回
```

这里 `0` 不会被缓存，因为：

- `empty(0)` 为 `true`
- 只有 `$orderCount > 0` 才写缓存

因此没有订单的用户每次都可能重新查询 MySQL。这可能是业务选择，也可能造成缓存穿透，需要结合请求量和缓存策略确认。

还应继续查找：

- 新增订单后是否删除或更新计数缓存
- 删除订单后是否失效缓存
- Redis key 的 TTL
- 并发回源时是否需要保护

## 15. 代表方法九：主键游标式分批读取

`getListByMinIdAndMaxId()`：

```php
$orderList = Order::find()
    ->andWhere(['>', 'order_id', $minId])
    ->select($column)
    ->limit($limit)
    ->orderBy(['order_id' => SORT_ASC])
    ->asArray()
    ->all();
```

如果提供 `$maxId`，还会添加：

```php
['<=', 'order_id', $maxId]
```

它比很深的 OFFSET 更适合批处理：

```text
上一批最大 order_id
  ↓
查询 order_id > 上一批最大值
  ↓
按 order_id 正序取固定数量
  ↓
继续下一批
```

这属于 keyset/cursor pagination 思路。

## 16. 代表方法十：原生 SQL 入口

```php
public function getDataBySql(string $sql = '')
{
    return $this->getConnection()
        ->createCommand($sql)
        ->queryOne();
}
```

这个方法直接接受完整 SQL 字符串并执行。

风险取决于调用方：

- 如果 SQL 完全由可信常量构造，风险相对可控
- 如果把请求参数拼进 SQL，会产生 SQL 注入风险
- 通用原生 SQL 方法会绕过 Repository 对具体查询语义的封装

更安全的方向是：

```php
$command = $connection->createCommand(
    'SELECT * FROM `order` WHERE `order_no` = :order_no',
    [':order_no' => $orderNo],
);
```

不要只因为方法位于 Repository 中，就默认传入的 SQL 是安全的。

## 17. 返回类型规律

| 查询结尾 | 常见返回值 |
|---|---|
| `one()` | AR 对象或 `null` |
| `asArray()->one()` | 关联数组或 `null` |
| `all()` | AR 对象数组 |
| `asArray()->all()` | 关联数组列表 |
| `count()` | 数字字符串或整数，取决于框架和驱动 |
| `scalar()` | 单个标量或 `false/null` |
| `max()` | 最大值标量或 `null` |
| `updateAll()` | 受影响行数 |
| `getLastInsertID()` | 最后插入 ID，常为字符串 |

当前源码的命名能提供部分提示：

- 名称包含 `Obj` 的方法通常返回 AR 对象
- 名称包含 `List` 的方法通常返回多条数据
- 名称包含 `Count` 的方法通常返回计数或分组统计
- 名称包含 `update` 的方法通常返回受影响行数

但并不完全统一。例如 `getListByBusinessNo()` 返回 AR 对象数组，而许多其他 `getList*()` 返回普通数组。因此调用前仍要查看实现和 PHPDoc。

## 18. Repository 与 Service 的职责对照

### 18.1 适合放在 Repository

- 根据订单号查询订单
- 根据用户 ID 查询订单列表
- 按状态统计订单数量
- 封装 JOIN 和聚合查询
- 插入、更新订单数据
- 提供稳定的数据访问方法

### 18.2 更适合放在 Service、领域对象或展示层

- 判断订单能否退款
- 决定订单状态如何流转
- 翻译订单状态文案
- 调用其他业务服务
- 组装前端图表结构
- 判断客服问答中的交易状态

当前源码中的下列方法体现了职责混合：

| 方法 | 混合的职责 |
|---|---|
| `getStatusMpping()` | 多语言/展示文案 |
| `getOrderStatusStr()` | 多语言/展示文案 |
| `updateOrderPaidAudit()` | 状态流转判断 |
| `closeTestOrder()` | 更新 DB 后协调 Redis |
| `dataComparisonOfLastYear()` | 业务计算和图表结构 |
| `getTradingStatus()` | 业务状态判断 |

阅读历史项目时，先准确理解现状；不要因为理想分层不同就立即大规模重构。重构前必须查清调用方、事务边界、缓存一致性和兼容要求。

## 19. 源码中值得关注的问题

以下内容是静态源码阅读结论，未结合全部调用方、表结构和生产配置；不确定项明确标记为“需确认”。

### 19.1 明确问题：平台过滤条件没有生效

`getOrderByUserIdOrDesc($userId, $pf)` 中写的是：

```php
if (!empty($site)) {
    $order->andWhere(['in', 'platform', [$pf]]);
}
```

方法没有 `$site` 参数或局部变量，因此条件通常不会进入，传入的 `$pf` 不会参与筛选。

影响：方法可能返回该用户其他平台的最新已支付订单。

修复方向：判断 `$pf`，并根据业务决定使用 `=` 还是 `IN`。

### 19.2 高风险接口：直接执行任意 SQL

`getDataBySql($sql)` 直接执行传入字符串，没有绑定参数。

影响：如果调用方拼接外部输入，可能发生 SQL 注入；即使输入可信，也会让查询语义和依赖无法从方法名判断。

修复方向：为具体查询建立明确方法，并使用绑定参数。是否已构成可利用漏洞，需要继续追踪所有调用方。

### 19.3 需确认：读取后再更新存在并发窗口

`updateOrderPaidAudit()` 先读取订单，确认状态是未支付，再按 `order_no` 更新：

```text
SELECT 当前状态
  ↓
PHP 判断
  ↓
UPDATE WHERE order_no = ?
```

两个并发请求可能在读取后、更新前改变状态。

更稳妥的思路是把旧状态放进 UPDATE 条件：

```sql
UPDATE `order`
SET `order_status` = :new_status
WHERE `order_no` = :order_no
  AND `order_status` = :expected_status;
```

然后根据受影响行数判断状态迁移是否成功。是否还需要事务或行锁，要根据完整业务链路确认。

### 19.4 需确认：用户归属条件是可选的

`getOrderByOrderNoAndUserId()` 只有 `$userId` 非空时才加用户条件。

如果用户接口遗漏用户 ID，查询会退化为只按订单号查询。Repository 不负责完整授权，但调用方必须确保资源归属校验不会被跳过。

### 19.5 空条件可能导致宽查询

以下通用方法允许空条件：

- `getOneByCondition()`
- `getByCondition()`
- `getListByCondition()`
- `getWhereList()`

尤其 `getListByCondition([], ...)` 可能查询整张订单表。

修复方向：调用方强制条件和分页，或在 Repository 中拒绝不符合预期的空条件。

### 19.6 分组查询与 SQL mode

`getOrderGroupByUserIds()`：

```php
->groupBy(['user_id', 'site'])
->select('user_id, site, order_no')
```

`order_no` 既没有聚合，也没有出现在 `GROUP BY` 中。

- 开启 `ONLY_FULL_GROUP_BY` 时可能报错
- 未开启时返回哪个 `order_no` 不确定

如果真正需求是每组最新订单，应明确使用聚合、子查询或窗口函数。

### 19.7 `updateAll()` 绕过 AR 校验和事件

`insert()` 使用 Query Builder，`update()` 使用 `updateAll()`。两者都不会自动执行 ActiveRecord 实例级校验和保存事件。

这不一定是错误，但调用方不能假设 `Order::rules()`、Behavior 或 Observer 已经执行。

### 19.8 缓存零值与失效策略

`getCountFromCache()` 不缓存零值，并且当前文件中没有展示所有订单变更后的计数缓存失效逻辑。

需确认：

- 是否会对零订单用户造成重复回源
- 插入、删除或逻辑删除订单后缓存是否及时失效
- TTL 和容忍的旧数据时间是多少

### 19.9 返回类型和命名不完全一致

例子：

- `getOrderObjByNo()` 的 PHPDoc 包含 `array`，但实现没有 `asArray()`
- 多个更新方法注释写 `bool`，实际返回受影响行数
- `getByRelatedOrderNo($businessNo)` 实际查询的是 `business_no`
- `getStatusMpping`、`$laguage`、`$pamentNo` 存在拼写问题

影响：调用者容易误判返回值或查询语义。

### 19.10 无排序的 `one()` 结果不稳定

例如 `getOrderObjByUserId()` 根据用户 ID 查询后直接 `one()`，没有排序。

如果用户有多条订单，它只是返回某一条匹配记录，不保证是最新或最早。方法命名也没有表达选择规则。

### 19.11 需确认：取消订单时设置 `is_paid = 1`

`updateOrderCancel()` 同时设置：

```php
'order_status' => self::STATUS_CLOSED,
'is_paid' => 1,
```

从字段直觉看存在疑问，但可能有特定业务语义。必须结合调用方、状态机和历史数据确认，不能直接修改。

### 19.12 结果索引可能发生字符串碰撞

部分统计方法把多个分组值直接拼接为数组 key：

```php
$keyStr .= $v[$group] ?? '';
```

例如 `['1', '23']` 和 `['12', '3']` 都可能得到 `123`。如果分组值组合存在这种情况，后面的结果会覆盖前面的结果。

修复方向：使用不可混淆的分隔符、JSON 编码或嵌套数组结构。

## 20. 索引和性能阅读思路

不能只看代码就断言应该创建某个索引，但可以从查询模式提出候选并通过 `EXPLAIN` 验证。

| 查询模式 | 候选关注点 |
|---|---|
| `WHERE order_no = ?` | `order_no` 唯一或普通索引 |
| `WHERE payment_no = ?` | `payment_no` 索引 |
| `WHERE user_id = ?` | `user_id` 及列表排序组合 |
| `WHERE user_id = ? ORDER BY order_id DESC` | 与用户过滤、排序匹配的复合索引候选 |
| `WHERE paid_at BETWEEN ...` | 时间范围及状态组合索引候选 |
| JOIN `order_no` | 两侧关联字段索引 |
| GROUP BY 状态、用户、站点 | 结合过滤条件和分组字段评估 |

验证步骤：

1. 查看真实表结构和现有索引
2. 获取代表性参数与数据量
3. 执行 `EXPLAIN`
4. 检查扫描行数、访问类型、key、Extra
5. 评估新增索引的写入和存储成本
6. 再决定是否修改索引

不要因为字段出现在 `WHERE` 中就直接添加索引。

## 21. N+1 阅读思路

当前 Repository 已提供：

- 批量按订单号查询
- JOIN `order_goods`
- 聚合查询
- 按用户集合查询

这些能力可以帮助调用方避免循环逐条查询。

但 N+1 是否发生，还要查看 Service 如何调用。例如：

```php
foreach ($orderNos as $orderNo) {
    $orders[] = $repository->getOrderObjByNo($orderNo);
}
```

即使每个 Repository 方法本身都正确，这种调用方式仍会产生 N 次查询。更好的方式是调用批量查询方法：

```php
$orders = $repository->getOrderArrayByOrderNos($orderNos);
```

所以：

> N+1 是调用链级别的问题，不能只看一个 Repository 方法判断。

## 22. 完整方法拆解：`getOrderObjByNo()`

| 拆解项 | 记录 |
|---|---|
| 方法名 | `getOrderObjByNo` |
| 参数 | `$orderNo`，订单号 |
| 使用 Model | `Order` |
| 查询条件 | `order_no = $orderNo` |
| 是否 JOIN | 否 |
| 是否排序 | 否；订单号应唯一，需数据库约束确认 |
| 返回类型 | `Order` 对象或 `null` |
| 大致 SQL | `SELECT * FROM order WHERE order_no = ? LIMIT 1` |
| Service 用途 | 获取订单对象，继续执行业务流程或更新对象 |
| 性能关注 | `order_no` 应有合适索引，最好由唯一约束保证唯一性 |

## 23. Day 03 阅读记录

### 23.1 Repository 是什么？

> Repository 是数据访问层，对上提供有业务语义的方法，对下封装 ActiveRecord、Query Builder 和 SQL，让 Service 不需要知道具体查询实现。

### 23.2 为什么 Service 不直接写 SQL？

如果 Service 到处直接写 SQL 或 AR 查询，会导致：

1. 查询逻辑分散
2. 相同查询重复实现
3. 数据结构变化影响大量 Service
4. 业务流程和持久化细节混在一起
5. 测试和替换数据来源更困难

Repository 的价值：

1. 收口数据访问
2. 提供稳定的方法语义
3. 统一返回类型和查询约定
4. 统一处理 JOIN、分页、索引和缓存策略
5. 让 Service 专注业务流程

### 23.3 Repository 应该做什么？

- 查询单条或多条订单
- 保存和更新订单
- 封装分页、JOIN、分组和聚合
- 隐藏 ActiveRecord 与 SQL 细节
- 为 Service 提供稳定数据接口

### 23.4 Repository 不应该做什么？

- 决定用户能否退款
- 编排完整支付或下单流程
- 生成前端展示文案
- 组装特定图表协议
- 依赖上层 Service 执行业务决策

## 24. 自测题

尝试不看答案口头回答：

1. `OrderRepository` 在分层架构中的上下游分别是谁？
2. `getConnection()` 最终使用哪个数据库组件？
3. `getOrderObjByNo()` 和 `getOrderByOrderNo()` 的返回类型有什么区别？
4. 为什么 `updateAll()` 返回 `0` 不能简单理解为异常？
5. `insert()` 为什么不会自动执行 `Order::rules()`？
6. `getPageList()` 的深分页可能有什么性能问题？
7. `getCountFromCache()` 为什么不会缓存零订单用户？
8. `getListByMinIdAndMaxId()` 为什么适合批处理？
9. `getDataBySql()` 的安全风险取决于什么？
10. 为什么 `getTradingStatus()` 更像 Service 或领域逻辑？
11. 当前代码中哪个变量错误导致平台过滤没有生效？
12. 为什么判断 N+1 必须继续查看 Service 调用方式？

## 25. 一句话总结

> `OrderRepository` 把订单的新增、更新、单条查询、列表、JOIN 和统计查询封装在 ActiveRecord 之上，但当前大类还混合了缓存、翻译和业务判断；阅读时既要学会查询链，也要识别返回类型、分层边界、并发、安全和性能风险。
