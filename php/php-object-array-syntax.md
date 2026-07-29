# PHP：`->`、`=>`、`[]`、链式调用、`foreach` 与常用控制语法

> 结合 Week 03 的 Redis / Repository 代码，说明 PHP 中最常见的语法符号与循环、分支写法。

---

## 1. `->` 对象访问符

**作用：访问对象的属性或方法。**

左边必须是**对象**，右边是属性名或方法名。

```php
$redis->get($key);                        // 调用 $redis 对象的 get 方法
$orderRepository->getOrderById($id);      // 调用仓库对象的方法
$this->getConnection();                  // 在类内部，$this 是当前对象
```

`getOrderDetail.php` 示例：

```php
$cache = $redis->get($key);
$order = $orderRepository->getOrderById($id);
$redis->set($key, json_encode($order));
$redis->expire($key, 60 * 60 * 24);
```

这里的 `$redis`、`$orderRepository` 都是**对象实例**，用 `->` 调它们的方法。

**记忆：** `->` = 「这个对象的某个方法/属性」。

---

## 2. `=>` 键值对箭头

**作用：在数组里表示「键 => 值」。**

```php
$order = [
    'order_id' => 1001,
    'order_no' => 'ORD2024001',
    'status'   => 'paid',
];
```

- 左边：键（key）
- 右边：值（value）

也常见于 Yii 校验规则：

```php
[['order_no'], 'string', 'max' => 32]
//                              ↑ 这是数组里的一个键值对
```

### 和 `->` 的区别

| 符号 | 用在哪 | 含义 |
|---|---|---|
| `->` | 对象 | 访问对象成员 |
| `=>` | 数组 | 定义键值对 |

---

## 3. `[]` 方括号

在 PHP 里用途很多，常见有 3 种：

### (1) 定义数组

```php
$order = ['id' => 1, 'name' => 'test'];  // 关联数组
$list = [1, 2, 3];                        // 索引数组
```

### (2) 读取数组元素

```php
$order['order_id'];     // 用字符串键
$list[0];               // 用数字下标
self::$key['order']['loop_pay_status'];  // 多维数组逐层取
```

### (3) PHP 7.4+ 短数组类型声明（函数参数/返回值）

```php
function getOrderDetail(int $id): array  // 返回类型是 array
{
    return $order;  // 返回一个数组
}
```

**记忆：** `[]` 基本都和**数组**有关——创建、取值、或声明类型。

---

## 4. 链式调用（Method Chaining）

**作用：前一个方法返回对象，立刻再调下一个方法，写成一串。**

`OrderRedis.php` 示例：

```php
return $this->getConnection()->setex($key, $expire, 1);
```

拆开看：

```php
// 第一步：getConnection() 返回 Redis 连接对象
$conn = $this->getConnection();

// 第二步：在连接对象上调用 setex
$conn->setex($key, $expire, 1);

// 链式写法：两步合成一行
$this->getConnection()->setex($key, $expire, 1);
```

`OrderRepository.php` 里更长的链：

```php
$this->getConnection()->createCommand()->insert(Order::tableName(), $data)->execute();
```

执行顺序：

```text
getConnection()     → 返回 DB 连接
    ->createCommand()   → 返回命令对象
        ->insert(...)       → 返回命令对象（可继续链）
            ->execute()           → 真正执行 SQL
```

**前提：** 中间每个方法都要 `return $this` 或 `return 另一个对象`，才能继续 `->`。

---

## 5. 串起来看一段完整例子

```php
function getOrderDetail(int $id): array
{
    $key = 'order:detail:' . $id;           // 字符串拼接

    $cache = $redis->get($key);             // -> 调对象方法
    if ($cache !== false) {
        return json_decode($cache, true);   // 把 JSON 字符串转成数组 []
    }

    $order = $orderRepository->getOrderById($id);  // -> 查数据库

    $redis->set($key, json_encode($order));        // -> 写入缓存
    $redis->expire($key, 60 * 60 * 24);

    return $order;  // 返回 array，例如 ['order_id' => 1, 'order_no' => '...']
}
```

如果 `$order` 是：

```php
['order_id' => 1001, 'order_no' => 'ORD001']
```

那 `$order['order_no']` 用 `[]` 取值，得到 `'ORD001'`。

---

## 6. `foreach` 循环

**作用：遍历数组（或可遍历对象），逐个取出每个元素。**

### (1) 只取值

```php
$orders = [
    ['order_id' => 1, 'order_no' => 'A001'],
    ['order_id' => 2, 'order_no' => 'A002'],
];

foreach ($orders as $order) {
    echo $order['order_no'];
}
```

- `$orders`：要遍历的数组
- `$order`：每次循环当前那一项（自己起的变量名）
- 循环体里用 `$order['order_no']` 访问字段

### (2) 同时取键和值

```php
$labels = [
    'order_id' => '订单 ID',
    'order_no' => '订单号',
];

foreach ($labels as $key => $value) {
    echo $key . ' => ' . $value;
}
```

这里的 `=>` 又出现了：**左边是键，右边是值**。

| 写法 | 含义 |
|---|---|
| `foreach ($arr as $item)` | 只要值 |
| `foreach ($arr as $key => $item)` | 键和值都要 |

### (3) 嵌套 `foreach`

`OrderRepository.php` 里的典型写法：

```php
foreach ($list as $v) {
    $keyStr = "";
    foreach ($groupBy as $group) {
        $keyStr .= $v[$group] ?? '';
    }
    $return[$keyStr] = $v;
}
```

外层遍历 `$list` 每一行，内层遍历 `$groupBy` 每个分组字段，拼出 `$keyStr` 作为返回数组的键。

### (4) 引用遍历 `&$v`

```php
foreach ($statusList as &$v) {
    $v['label'] = '已支付';
}
unset($v);  // 用完建议 unset，避免后面误用引用
```

`&$v` 表示直接修改原数组里的元素，而不是改副本。

### (5) 和 N+1 的关系（Day 05）

不好的写法：

```php
$orders = Order::find()->limit(20)->all();

foreach ($orders as $order) {
    $goods = $order->goods;  // 懒加载时，每个订单可能再查一次库
}
```

预加载后，循环写法不变，但不再反复查库：

```php
$orders = Order::find()->with('goods')->limit(20)->all();

foreach ($orders as $order) {
    $goods = $order->goods;  // 数据已提前加载
}
```

**重点：** `foreach` 本身没问题，问题在于循环体里是否做了重复的数据库查询。

### (6) `foreach` vs `for`

| | `foreach` | `for` |
|---|---|---|
| 适用 | 数组、可遍历对象 | 知道次数或下标范围 |
| 写法 | `foreach ($arr as $item)` | `for ($i = 0; $i < count($arr); $i++)` |
| 推荐 | 遍历数组时优先用 | 数字下标、固定次数循环 |

```php
// foreach：更直观
foreach ($orders as $order) { ... }

// for：需要下标时用
for ($i = 0; $i < count($orders); $i++) {
    $order = $orders[$i];
}
```

---

## 7. `.=` 字符串拼接赋值

**作用：把右边的字符串拼到左边变量后面，等价于 `$a = $a . $b`。**

```php
$keyStr = "";
$keyStr .= $v[$group] ?? '';
```

拆开看：

```php
// 这两行完全等价
$keyStr .= $v[$group] ?? '';
$keyStr = $keyStr . ($v[$group] ?? '');
```

`OrderRepository.php` 里拼分组键的完整流程：

```php
$keyStr = "";
foreach ($groupBy as $group) {
    $keyStr .= $v[$group] ?? '';  // 每次循环往后追加一段字符串
}
$return[$keyStr] = $v;
```

假设 `$groupBy = ['site', 'order_status']`，某行 `$v = ['site' => 'us', 'order_status' => 'paid']`：

```text
第 1 次循环：$keyStr = '' . 'us'        → 'us'
第 2 次循环：$keyStr = 'us' . 'paid'   → 'uspaid'
```

### 和 `.` 的区别

| 写法 | 含义 | 示例 |
|---|---|---|
| `.` | 拼接两个字符串，不修改原变量 | `$full = $prefix . $suffix` |
| `.=` | 拼到原变量上 | `$full .= $suffix` |

同类写法还有 `+=`、`-=`、`*=`，都是「先算再赋回自己」：

```php
$total += 10;   // $total = $total + 10
$count -= 1;    // $count = $count - 1
```

---

## 8. `for` 循环

**作用：按条件重复执行，适合「知道要循环几次」或「按下标遍历」。**

基本结构：

```php
for (初始化; 条件; 每次循环后执行) {
    // 循环体
}
```

`OrderRepository.php` 里生成 24 小时统计数据的例子：

```php
for ($i = 0; $i < 24; $i++) {
    if ($i < 10) {
        $hourTmp = sprintf('0%s', $i);
    } else {
        $hourTmp = strval($i);
    }
    $hour[]     = sprintf('%s:00', $hourTmp);
    $thisYear[] = bcsub($thisYearData[$hourTmp]['usd_pay_amount'] ?? '0', ...);
    $lastYear[] = bcsub($lastYearData[$hourTmp]['usd_pay_amount'] ?? '0', ...);
}
```

三部分含义：

| 部分 | 示例 | 作用 |
|---|---|---|
| 初始化 | `$i = 0` | 循环开始前执行一次 |
| 条件 | `$i < 24` | 为 `true` 才进入循环体 |
| 递增 | `$i++` | 每次循环体结束后 `$i` 加 1 |

执行顺序：

```text
初始化 → 判断条件 → 执行循环体 → 递增 → 再判断条件 → ...
```

### 按下标遍历数组

```php
for ($i = 0; $i < count($orders); $i++) {
    $order = $orders[$i];
    echo $order['order_no'];
}
```

### 什么时候用 `for`

| 场景 | 推荐 |
|---|---|
| 遍历数组每一项 | `foreach` |
| 固定次数（如 24 小时） | `for` |
| 需要当前下标 `$i` | `for` |
| 倒序、步长不为 1 | `for`（如 `$i -= 2`） |

---

## 9. `switch` / `case` 多分支

**作用：根据一个变量的值，走不同分支。比一堆 `if / elseif` 更清晰。**

基本结构：

```php
switch ($变量) {
    case 值1:
        // 分支 1
        break;
    case 值2:
        // 分支 2
        break;
    default:
        // 都不匹配时（可选）
        break;
}
```

`OrderRepository.php` 里按门店角色类型拼查询条件：

```php
switch ($type) {
    case 1: // 总部人员
        $query->andWhere(['>', 'affiliated_store_id', 0])
            ->andWhere(['>', 'affiliated_staff_id', 0]);
        break;
    case 2: // 店员
        $query->andWhere(['in', 'affiliated_store_id', $item['staff_store_ids']])
            ->andWhere(['=', 'affiliated_staff_id', $item['store_staff_id']]);
        break;
    case 3: // 店员 + 店长混合
        $query->andWhere(['or', $assistantCondition, $managerCondition]);
        break;
    case 4: // 店长
        $query->andWhere(['in', 'affiliated_store_id', $item['manager_store_ids']]);
        break;
}
```

### 执行规则

1. `$type` 与哪个 `case` 的值**相等**（`==`），就进入那个分支
2. **`break` 必须写**，否则会「穿透」继续执行下一个 `case`
3. 所有 `case` 都不匹配时，走 `default`（如果有）

### `break` 穿透示例（反面教材）

```php
switch ($status) {
    case 'paid':
        echo '已支付';
        // 忘了 break，会继续执行 case 'cancelled'
    case 'cancelled':
        echo '已取消';
        break;
}
```

如果 `$status === 'paid'`，会连续输出「已支付」「已取消」。

### `switch` vs `if / elseif`

| | `switch ($x)` | `if / elseif` |
|---|---|---|
| 适合 | 同一个变量多种固定取值 | 复杂条件、范围判断 |
| 示例 | `case 1:`、`case 2:` | `$age > 18`、`$score >= 90` |
| 可读性 | 枚举型分支更清晰 | 灵活 |

```php
// switch：适合等值判断
switch ($type) {
    case 1: ...; break;
    case 2: ...; break;
}

// if：适合范围、组合条件
if ($amount > 1000) {
    ...
} elseif ($amount > 100) {
    ...
}
```

PHP 8+ 也可用 `match` 做类似分支（表达式、必须覆盖、默认用 `default`），初学先掌握 `switch` 即可。

---

## 10. 一句话速记

| 符号 / 语法 | 一句话 |
|---|---|
| `->` | 对象的方法/属性 |
| `=>` | 数组的键值对 |
| `[]` | 数组本身，或从数组里取值 |
| 链式调用 | `A()->B()->C()`，前一步返回对象，后一步接着调 |
| `foreach` | 遍历数组，`as` 后是当前项，`$key => $value` 可同时取键值 |
| `.=` | 字符串拼到变量后面，等价于 `$a = $a . $b` |
| `for` | `for (初始化; 条件; 递增)`，适合固定次数或下标循环 |
| `switch / case` | 按变量值走分支，每个 `case` 后记得 `break` |

---

## 相关文件

- `php/week03/getOrderDetail.php`
- `php/week03/day05.md`（N+1 与 `foreach` 示例）
- `php/week03/common/redis/order/OrderRedis.php`
- `php/week03/common/repositorys/order/OrderRepository.php`
- `php/week01/self-this-static说明.md`（`$this`、`self`、`::` 的补充阅读）
