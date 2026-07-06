# Week 03 Day 05：N+1 与类比日

> 所属周：Week 03：MySQL + Redis + ORM  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-core`  
> 类型：类比日  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 ORM 中常见的 N+1 查询问题，知道 Yii2 中 `with()` / eager loading 预加载的作用，并能把订单列表前端字段和 Repository / Model 查询来源对应起来。

今天你要真正掌握这一句话：

> N+1 问题就是先查 1 次列表，再在循环里为每条记录额外查 1 次关联数据；解决思路是提前批量预加载关联数据，而不是循环里反复查库。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 复习 ActiveRecord 关系：`hasOne()` / `hasMany()`
2. 理解什么是 N+1 查询
3. 用 JS 循环里 `await` 查库类比 N+1
4. 理解 eager loading / 预加载
5. 理解 Yii2 的 `with()`
6. 理解 `joinWith()` 与 `with()` 的大致区别
7. 对照订单列表前端字段
8. 找 Repository 中字段数据来源
9. 完成本周类比打卡
10. 完成自测和 AI Review

---

## 1. 学习内容

### 1.1 什么是 N+1 问题？

假设你要展示订单列表，每个订单都要展示商品名称。

错误思路：

```text
先查订单列表：1 次查询
然后循环每个订单，再查该订单商品：N 次查询
总计：1 + N 次查询
```

如果有 20 个订单：

```text
1 次查订单 + 20 次查商品 = 21 次查询
```

这就是 N+1 问题。

---

### 1.2 JS 类比：循环里 await 查库

Node 中不好的写法：

```js
const orders = await Order.findAll();

for (const order of orders) {
  order.goods = await OrderGoods.findAll({
    where: { order_id: order.id },
  });
}
```

问题：订单越多，查询越多。

更好的做法：

```js
const orders = await Order.findAll({
  include: [OrderGoods],
});
```

一次把关联商品预加载出来。

---

### 1.3 Yii2 中 N+1 的典型样子

假设 `Order` 有关系：

```php
public function getGoods()
{
    return $this->hasMany(OrderGoods::class, ['order_id' => 'id']);
}
```

不好的写法：

```php
$orders = Order::find()->limit(20)->all();

foreach ($orders as $order) {
    $goods = $order->goods;
}
```

如果 `$order->goods` 是懒加载，就可能每个订单额外查一次商品。

---

### 1.4 `with()` 是什么？

`with()` 用来预加载关联数据。

```php
$orders = Order::find()
    ->with('goods')
    ->limit(20)
    ->all();
```

意思：

```text
查订单时，提前把 goods 关联也查出来。
```

这样循环里访问：

```php
foreach ($orders as $order) {
    $goods = $order->goods;
}
```

就不需要每次再单独查。

---

### 1.5 `with()` 和 `joinWith()` 简单区别

小白阶段先这样记：

| 方法 | 重点 |
|---|---|
| `with()` | 预加载关联数据，减少 N+1 |
| `joinWith()` | JOIN 关联表，可用于关联条件筛选/排序 |

例如：

```php
Order::find()->with('goods')->all();
```

重点是加载关联商品。

```php
Order::find()->joinWith('goods')->where(['order_goods.sku' => 'ABC'])->all();
```

重点是用关联表字段参与查询。

---

### 1.6 前端字段从哪里来？

订单列表页可能展示：

| 前端字段 | 可能来源 |
|---|---|
| 订单号 | `order.order_no` |
| 订单状态 | `order.status` |
| 下单时间 | `order.created_at` |
| 商品名 | `order_goods.goods_name` |
| 商品图片 | `order_goods.image` |
| 支付金额 | `order.pay_amount` |
| 收货人 | `order_address.name` |

你今天要练的是：看到前端字段，能反查后端数据来源。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议对照这些路径：

- `mall-core/common/repositorys/order/OrderRepository.php`
- `mall-core/common/models/order/Order.php`
- `mall-core/common/models/order/OrderGoods.php`
- `mall-core/common/models/order/OrderAddress.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 3. 练习任务

### 练习 1：写出 N+1 示例

```php
$orders = Order::find()->limit(20)->all();

foreach ($orders as $order) {
    $goods = $order->goods;
}
```

解释为什么可能有 N+1：

```text
先查 1 次订单列表，再循环 20 个订单分别查商品，总查询数可能是 21 次。
```

---

### 练习 2：用 with() 改写

```php
$orders = Order::find()
    ->with('goods')
    ->limit(20)
    ->all();
```

解释：

```text
提前批量加载 goods 关联，避免循环中重复查库。
```

---

### 练习 3：订单列表字段对照表

| 前端字段 | 后端表 | Model | Repository 方法 | 备注 |
|---|---|---|---|---|
| 订单号 | order | Order |  |  |
| 商品名 | order_goods | OrderGoods |  |  |
| 收货人 | order_address | OrderAddress |  |  |
| 订单状态 | order | Order |  |  |
| 支付金额 | order | Order |  |  |

---

### 练习 4：完成本周类比打卡

```text
本周概念：ActiveRecord + Repository + N+1 + Redis 缓存
Node 等价：Sequelize Model + DAO + include 预加载 + ioredis
差异：Yii2 AR 用 find()->where()->one()/all() 链式查询；Repository 收口查询；with() 解决关联预加载
理解自评：1 / 2 / 3 / 4 / 5
```

---

## 4. JS/Node.js 类比

| 概念 | Yii2 / PHP | Node / JS 类比 |
|---|---|---|
| N+1 | 循环访问 `$order->goods` 触发多次查询 | 循环里 `await findAll()` |
| eager loading | `with('goods')` | Sequelize `include` |
| Repository | 查询收口层 | DAO / Prisma repository |
| 前端字段反查 | 页面字段 → Repository → Model | 页面字段 → API → DAO → DB |

---

## 5. AI Review 提问

```text
我正在学习 N+1 查询和 Yii2 with() 预加载。

我写了 N+1 示例、with() 改写，并整理了订单列表字段对照表。
请你按资深 PHP/MySQL 工程师标准帮我检查：

1. 我对 N+1 的理解是否正确？
2. with() 的使用场景是否准确？
3. 字段对照表是否合理？
4. Yii2 with() 和 Sequelize include 类比是否准确？
5. 真实项目里还要如何发现和优化 N+1？

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 6. 今日产出

- [ ] N+1 示例说明
- [ ] `with()` 改写示例
- [ ] 订单列表字段对照表
- [ ] Repository 数据来源记录
- [ ] 本周类比打卡
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 N+1 问题
- [ ] 能写出一个 N+1 示例
- [ ] 能用 `with()` 改写
- [ ] 能说明 `with()` 和 `joinWith()` 的初步区别
- [ ] 能对照前端字段找后端表/Model
- [ ] 能用 Sequelize include 类比 Yii2 with

---

## 8. 今日自测题

### 8.1 什么是 N+1？

参考答案：先查 1 次主列表，再为 N 条记录分别查关联数据，导致总共 1+N 次查询。

### 8.2 Yii2 中 `with()` 的作用是什么？

参考答案：预加载关联数据，减少循环访问关联时产生的额外查询。

### 8.3 Node 中 N+1 常见写法是什么？

参考答案：循环里 `await` 查询关联数据。

### 8.4 Sequelize 中类似 `with()` 的概念是什么？

参考答案：`include`。

### 8.5 `joinWith()` 通常用于什么？

参考答案：需要用关联表字段参与筛选或排序时。

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
我正在进行 Week 03 Day 05：N+1 与类比日 的学习。
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
