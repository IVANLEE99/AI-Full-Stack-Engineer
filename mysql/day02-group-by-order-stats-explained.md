# 分组聚合 SQL 逐段详解：每个用户订单数与成交额

> 所属：7 天掌握 MySQL  
> 对应：[day02.md](./day02.md) 第 6 节「分组与聚合」  
> 学习目标：看懂 `GROUP BY` + 聚合函数 + `WHERE` + `ORDER BY` 的组合用法

---

## 完整语句

```sql
-- 每个用户订单数与成交额（已支付及以后）
SELECT
  user_id,
  COUNT(*) AS order_cnt,
  SUM(total_amount) AS paid_sum,
  AVG(total_amount) AS paid_avg,
  MAX(total_amount) AS paid_max
FROM orders
WHERE status IN (1, 2, 3)
GROUP BY user_id
ORDER BY paid_sum DESC;
```

---

## 1. 这条 SQL 在干什么

一句话：**按用户统计「已支付及以后」订单的成交情况，并按成交总额从高到低排序。**

输出每一行代表一个用户，包含：

| 字段 | 含义 |
|------|------|
| `user_id` | 用户 ID |
| `order_cnt` | 该用户的订单数 |
| `paid_sum` | 该用户的成交总额 |
| `paid_avg` | 该用户的平均客单价 |
| `paid_max` | 该用户的最高单笔金额 |

---

## 2. 执行顺序（重要）

SQL **不是**按你书写的顺序执行，数据库大致按下面顺序处理：

```text
FROM orders
  ↓
WHERE status IN (1, 2, 3)     -- 先过滤行
  ↓
GROUP BY user_id              -- 再按用户分组
  ↓
SELECT 聚合函数               -- 对每组做统计
  ↓
ORDER BY paid_sum DESC        -- 最后排序
```

记忆口诀：

- `WHERE`：分组**前**过滤明细行
- `GROUP BY`：把多行合成一组
- `SELECT` 里的聚合函数：对每组算一个值
- `ORDER BY`：对最终结果排序

---

## 3. 逐段拆解

### 3.1 `FROM orders`

数据来源是 `orders` 订单表。

### 3.2 `WHERE status IN (1, 2, 3)`

只保留状态为 1、2、3 的订单。结合 day02 的状态定义：

| status | 含义 |
|--------|------|
| 0 | 待支付 |
| 1 | 已支付 |
| 2 | 已发货 |
| 3 | 已完成 |
| 4 | 已取消 |

`IN (1, 2, 3)` 表示：**已支付、已发货、已完成**——即「已支付及以后」的订单。

待支付（0）和已取消（4）不参与统计。

### 3.3 `GROUP BY user_id`

把过滤后的订单按 `user_id` 分成一组一组。

例如原始数据：

| user_id | order_no | total_amount | status |
|---------|----------|--------------|--------|
| 1001 | O001 | 200 | 1 |
| 1001 | O002 | 300 | 2 |
| 1008 | O003 | 500 | 3 |

分组后：

- 组 1：`user_id = 1001`，包含 O001、O002 两行
- 组 2：`user_id = 1008`，包含 O003 一行

### 3.4 `SELECT` 里的聚合函数

| 写法 | 作用 | 上例结果 |
|------|------|----------|
| `COUNT(*) AS order_cnt` | 该组有多少行（订单数） | 1001 → 2，1008 → 1 |
| `SUM(total_amount) AS paid_sum` | 该组金额总和 | 1001 → 500，1008 → 500 |
| `AVG(total_amount) AS paid_avg` | 该组金额平均值 | 1001 → 250，1008 → 500 |
| `MAX(total_amount) AS paid_max` | 该组最大单笔金额 | 1001 → 300，1008 → 500 |

`AS` 给结果列起别名，方便在 `ORDER BY` 和前端展示里引用。

### 3.5 `ORDER BY paid_sum DESC`

按 `paid_sum`（成交总额）**降序**排列，成交额最高的用户排在最前面。

`DESC` = descending（降序）；升序用 `ASC`。

---

## 4. 结果示例

| user_id | order_cnt | paid_sum | paid_avg | paid_max |
|--------:|----------:|---------:|---------:|---------:|
| 1001 | 5 | 3200.00 | 640.00 | 1299.00 |
| 1008 | 3 | 2100.00 | 700.00 | 980.00 |
| 1020 | 2 | 1500.00 | 750.00 | 900.00 |

解读：

- 用户 1001：5 单，总成交 3200，平均客单价 640，最高单笔 1299
- 用户 1008：3 单，总成交 2100

---

## 5. 和 `HAVING` 的区别

这条 SQL 用的是 `WHERE`，在**分组前**过滤。

如果要在**分组后**再筛组，用 `HAVING`：

```sql
-- 只保留订单数 >= 2 的用户
SELECT
  user_id,
  COUNT(*) AS order_cnt,
  SUM(total_amount) AS paid_sum
FROM orders
WHERE status IN (1, 2, 3)
GROUP BY user_id
HAVING COUNT(*) >= 2
ORDER BY paid_sum DESC;
```

| 子句 | 过滤对象 | 时机 |
|------|----------|------|
| `WHERE` | 明细行 | 分组前 |
| `HAVING` | 分组结果 | 分组后 |

---

## 6. 常见注意点

### 6.1 `ONLY_FULL_GROUP_BY` 规则

`SELECT` 里出现的非聚合列，必须出现在 `GROUP BY` 中。

这条 SQL 只有 `user_id` 是非聚合列，且已在 `GROUP BY user_id` 里，所以合规。

错误示例：

```sql
-- 错误：order_no 不在 GROUP BY 里
SELECT user_id, order_no, COUNT(*)
FROM orders
GROUP BY user_id;
```

### 6.2 `NULL` 值

如果 `total_amount` 为 `NULL`：

- `SUM` / `AVG` / `MAX` 会忽略 `NULL`
- `COUNT(*)` 仍然计行数

若要把 `NULL` 当 0 处理：

```sql
SUM(COALESCE(total_amount, 0))
```

### 6.3 小数精度

`AVG` 可能返回很多位小数，展示时建议：

```sql
ROUND(AVG(total_amount), 2) AS paid_avg
```

---

## 7. 推荐增强写法

```sql
SELECT
  user_id,
  COUNT(*) AS order_cnt,
  ROUND(SUM(COALESCE(total_amount, 0)), 2) AS paid_sum,
  ROUND(AVG(COALESCE(total_amount, 0)), 2) AS paid_avg,
  ROUND(MAX(COALESCE(total_amount, 0)), 2) AS paid_max
FROM orders
WHERE status IN (1, 2, 3)
GROUP BY user_id
ORDER BY paid_sum DESC;
```

改动点：

- `COALESCE`：把 `NULL` 金额当 0
- `ROUND(..., 2)`：金额保留两位小数

---

## 8. 性能提示（数据量大时）

可考虑索引：

```sql
-- 先按 status 过滤，再按 user_id 分组
INDEX idx_status_user (status, user_id)
```

如果常加时间范围，可设计：

```sql
INDEX idx_status_created_user (status, created_at, user_id)
```

---

## 9. 一句话速记

- `WHERE` 先筛行，`GROUP BY` 再分组，聚合函数算每组，`ORDER BY` 最后排序
- `COUNT(*)` 数订单，`SUM` 算总额，`AVG` 算均价，`MAX` 找最高单笔
- `status IN (1,2,3)` = 只统计已支付及以后的订单

---

## 相关文件

- [day02.md](./day02.md)
- [day02.md#7-having-vs-where](./day02.md)（HAVING 对比）
- [create-table-users-explained.md](./create-table-users-explained.md)（建表详解）
