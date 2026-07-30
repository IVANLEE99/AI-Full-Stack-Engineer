# INNER JOIN 与 LEFT JOIN 详解

> 所属：7 天掌握 MySQL  
> 对应：[day03.md](./day03.md) · [mysql-pro-agent.md](./mysql-pro-agent.md)  
> 实验库：`shop_lab`（[labs/01_schema.sql](./labs/01_schema.sql)）

---

## 0. 先读结论（30 秒）

| JOIN 类型 | 一句话 |
|-----------|--------|
| **INNER JOIN** | 两边都匹配上才出现在结果里 |
| **LEFT JOIN** | 以左表为主，右表没匹配则右列填 `NULL` |

**mysql-pro 原则（写 JOIN 前先想）：**

1. 从**业务结果**出发，不是从表出发
2. 先想清楚**结果粒度**（一行代表什么）
3. 一对多 JOIN 会让行数变多，聚合前必须警惕
4. `LEFT JOIN` 后在 `WHERE` 写右表条件，可能悄悄变成 `INNER JOIN`

---

## 1. JOIN 是什么

JOIN 用**关联键**把多张表拼成一张「宽表」结果。

```text
users (1) ──── (N) orders (1) ──── (N) order_items
                      │
                      └──── (1) order_addresses
```

常见关联：

```text
orders.user_id      = users.id
order_items.order_id = orders.id
order_addresses.order_id = orders.id
```

**今日核心句（Day03）：**

> JOIN 是在用键把关系拼起来；一对多会「行变多」，必须先想清结果粒度，再写 SELECT 和聚合。

---

## 2. JOIN 基本语法

```sql
SELECT 列列表
FROM 左表 [别名]
[INNER | LEFT] JOIN 右表 [别名] ON 关联条件
[WHERE ...]
[ORDER BY ...];
```

- `ON`：定义**怎么连**（表与表如何匹配）
- `WHERE`：定义**连完之后**再过滤哪些行

`INNER JOIN` 可简写为 `JOIN`，含义相同。

### 2.1 `JOIN` 与 `INNER JOIN` 是什么关系？

在 MySQL 里，**`JOIN` 就是 `INNER JOIN` 的简写**，没有第三种默认含义：

```sql
-- 以下两种写法完全等价
FROM orders o JOIN users u ON u.id = o.user_id
FROM orders o INNER JOIN users u ON u.id = o.user_id
```

| 写法 | 含义 |
|------|------|
| `JOIN` | 内连接（简写，最常见） |
| `INNER JOIN` | 内连接（完整写法，更醒目） |
| `LEFT JOIN` | 左外连接（**不能**简写成 `JOIN`） |
| `RIGHT JOIN` | 右外连接（少用，可改写成 `LEFT JOIN`） |

教程和面试里看到单独一个 `JOIN`，按 **INNER JOIN** 理解即可。

### 2.2 没有 `ON` 会怎样？

```sql
-- 危险：缺少 ON，产生笛卡尔积
SELECT * FROM orders o, users u;
SELECT * FROM orders o CROSS JOIN users u;
```

每一行订单会和**每一个**用户组合，行数 = 订单数 × 用户数。  
**mysql-pro：** 写 JOIN 必须显式 `ON` 关联键，避免隐式笛卡尔积。

---

## 3. INNER JOIN（内连接）

### 3.1 含义

**两边都有匹配行**才进入结果；任一侧无匹配则整行丢弃。

可记：**交集**——只保留能配上的。

### 3.2 示例：订单 + 用户

```sql
USE shop_lab;

SELECT
  o.order_no,
  u.name AS user_name,
  o.total_amount
FROM orders o
INNER JOIN users u ON u.id = o.user_id
WHERE o.status = 1
ORDER BY o.id
LIMIT 20;
```

解读：

| 部分 | 作用 |
|------|------|
| `FROM orders o` | 左表：订单 |
| `INNER JOIN users u ON u.id = o.user_id` | 用 `user_id` 找对应用户 |
| `WHERE o.status = 1` | 只要已支付订单 |

结果：每笔订单带上一行用户信息。若 `user_id` 指向不存在的用户（脏数据），该订单**不会出现**。

### 3.3 适用场景

- 必须两边都有数据才有意义
- 订单列表要带用户名、邮箱
- 订单明细必须挂商品/订单主表
- 业务上「没有地址就不展示」→ 用 `INNER JOIN order_addresses` 而非 `LEFT JOIN`

### 3.4 图示（概念）

```text
orders          users
┌────┐          ┌────┐
│ O1 │──匹配──▶│ U1 │  ✅ 出现在结果
│ O2 │──匹配──▶│ U2 │  ✅
│ O3 │──无用户─▶  ?  │  ❌ 被 INNER JOIN 丢掉
└────┘          └────┘
```

---

## 4. LEFT JOIN（左连接）

### 4.1 含义

**以左表为基准**：左表每一行都会保留；右表有匹配则填值，无匹配则右表列全是 `NULL`。

可记：**左表全要，右表尽量填**。

### 4.2 示例：所有订单 + 地址（可能没有）

```sql
SELECT
  o.order_no,
  a.receiver_name,
  a.city
FROM orders o
LEFT JOIN order_addresses a ON a.order_id = o.id
ORDER BY o.id;
```

- 有地址的订单：`receiver_name`、`city` 有值
- 没有地址的订单：这两列为 `NULL`，但**订单行仍在**

### 4.3 适用场景

- 左表是主数据，右表是「可有可无」的扩展
- 查所有用户，并看是否下过单（右表无单则订单列为 NULL）
- 统计「有地址 / 无地址」的订单分布
- 1:1 或 1:0..1 关系里，主表行必须全保留

### 4.4 图示（概念）

```text
orders          order_addresses
┌────┐          ┌────────┐
│ O1 │──匹配──▶│ 地址 A │  ✅ O1 + 地址
│ O2 │──无地址─▶  NULL  │  ✅ O2 + NULL（仍保留）
│ O3 │──匹配──▶│ 地址 B │  ✅ O3 + 地址
└────┘          └────────┘
```

---

## 5. INNER JOIN vs LEFT JOIN 对照

| 对比项 | INNER JOIN | LEFT JOIN |
|--------|------------|-----------|
| 左表无匹配 | 不返回左表该行 | 仍返回，右列 NULL |
| 右表无匹配 | 不返回 | 仍返回左表行 |
| 结果行数 | ≤ 匹配后的积 | ≥ 左表行数（未再 JOIN 扩行时） |
| 典型意图 | 只要「两边都有」 | 要「左表全部 + 右表补充」 |
| 简写 | `JOIN` = `INNER JOIN` | 无简写 |

### 5.1 同一需求两种写法

**需求 A：只要有关联用户的订单** → `INNER JOIN users`

**需求 B：所有订单，有用户就显示名，没有就 NULL** → `LEFT JOIN users`

---

## 6. 最大易错点：LEFT JOIN + WHERE 右表条件

### 6.1 错误示范

```sql
-- 看起来是 LEFT JOIN，实际丢掉了「无地址」的订单
SELECT o.order_no, a.city
FROM orders o
LEFT JOIN order_addresses a ON a.order_id = o.id
WHERE a.city = '上海市';
```

为什么错？

- `LEFT JOIN` 后，无地址订单的 `a.city` 是 `NULL`
- `WHERE a.city = '上海市'` 会过滤掉 `NULL`
- 无地址订单全部被扔掉 → **效果等同 INNER JOIN**

### 6.2 正确写法

**业务：所有订单，但只把上海地址填在右列（非上海或无地址为 NULL）**

```sql
SELECT o.order_no, a.city
FROM orders o
LEFT JOIN order_addresses a
  ON a.order_id = o.id AND a.city = '上海市';
```

**业务：只要上海地址的订单（没地址或外地的都不要）**

```sql
SELECT o.order_no, a.city
FROM orders o
INNER JOIN order_addresses a
  ON a.order_id = o.id AND a.city = '上海市';
```

### 6.3 规则（mysql-pro）

> Treat `LEFT JOIN` filters carefully so they do not accidentally become inner joins.

| 过滤意图 | 条件放哪 |
|----------|----------|
| 影响「是否保留左表行」 | 不要放 `WHERE` 写右表非空条件 |
| 只影响右表如何匹配 | 放 `ON` |
| 明确不要左表无匹配行 | 直接用 `INNER JOIN` |

---

## 7. 一对多 JOIN：行膨胀

### 7.1 现象

```sql
SELECT
  o.order_no,
  oi.product_name,
  oi.quantity
FROM orders o
INNER JOIN order_items oi ON oi.order_id = o.id
WHERE o.order_no = 'O202601010001';
```

若该订单有 **2 个商品** → 结果 **2 行**（`order_no` 重复出现）。

这是 1:N 关系的正常现象，不是 bug。

### 7.2 聚合陷阱

```sql
-- 危险：订单金额会被加重复！
SELECT o.order_no, SUM(o.total_amount)
FROM orders o
JOIN order_items oi ON oi.order_id = o.id
GROUP BY o.order_no;
```

2 个明细行 → `total_amount` 被加 2 次。

**mysql-pro：** Verify that joins do not silently duplicate parent rows.

### 7.3 正确姿势

先按订单粒度聚合明细，再 JOIN：

```sql
SELECT o.order_no, o.total_amount, x.item_cnt, x.item_qty
FROM orders o
JOIN (
  SELECT order_id,
         COUNT(*) AS item_cnt,
         SUM(quantity) AS item_qty
  FROM order_items
  GROUP BY order_id
) x ON x.order_id = o.id
WHERE o.id = 1;
```

或：只 `SUM` 明细字段（`quantity`、`unit_price`），不要 `SUM` 订单头字段。

---

## 8. 三表 JOIN 实战：订单详情

```sql
SELECT
  o.order_no,
  u.email,
  a.receiver_name,
  a.city,
  oi.product_name,
  oi.quantity,
  oi.unit_price,
  (oi.quantity * oi.unit_price) AS line_amount
FROM orders o
JOIN users u ON u.id = o.user_id
LEFT JOIN order_addresses a ON a.order_id = o.id
JOIN order_items oi ON oi.order_id = o.id
WHERE o.order_no = 'O202601010001'
ORDER BY oi.id;
```

| JOIN | 类型 | 原因 |
|------|------|------|
| `orders` → `users` | INNER | 订单必须有用户 |
| `orders` → `order_addresses` | LEFT | 订单要全保留，地址可能没有 |
| `orders` → `order_items` | INNER | 只要「有明细」的展示；若订单可无明细则用 LEFT |

结果粒度：**一行 = 一个订单的一个商品明细**（1 个订单 N 个商品 → N 行）。

---

## 9. 怎么选 INNER 还是 LEFT

按业务问三个问题：

1. **左表这一行，右表没有匹配时，还要不要出现在结果里？**
   - 要 → `LEFT JOIN`
   - 不要 → `INNER JOIN`

2. **右表条件是「匹配规则」还是「结果过滤」？**
   - 匹配规则 → 放 `ON`
   - 过滤掉整行 → 放 `WHERE`（且想清楚是否还要 LEFT）

3. **结果一行代表什么？**
   - 一个订单？一个用户？一个明细？  
   - 1:N JOIN 后行数会变，写 `GROUP BY` / `SUM` 前先定粒度

---

## 10. 与 Yii2 / ORM 的对应（预告）

| SQL | Yii2 大致对应 |
|-----|----------------|
| `INNER JOIN` | `joinWith()` / `innerJoinWith()` |
| `LEFT JOIN` | `joinWith()` 默认左连接场景 |
| 预加载防 N+1 | `with()`（不拼宽表，分次查询） |

见 [php/week03/day05.md](../php/week03/day05.md)。

---

## 11. 练习自测

### 11.1 写 SQL

1. 查所有已支付订单及下单用户姓名（`INNER JOIN`）
2. 查所有订单及地址城市，包括没有地址的（`LEFT JOIN`）
3. 查订单 `O202601010001` 的所有商品明细

### 11.2 判断题

1. `LEFT JOIN` 后 `WHERE a.city = '上海'` 会保留无地址订单吗？  
   **答：不会。**

2. 订单 JOIN 2 条明细后，`COUNT(*)` 是 1 还是 2？  
   **答：2（若不 GROUP BY 订单）。**

3. `JOIN` 和 `INNER JOIN` 一样吗？  
   **答：一样。** `JOIN` 是 `INNER JOIN` 的简写。

---

## 12. mysql-pro 写 JOIN 检查清单

摘自 [mysql-pro-agent.md](./mysql-pro-agent.md) 核心 SQL 规范，写 JOIN 前对照：

| # | 原则 | 说明 |
|---|------|------|
| 1 | 从业务结果出发 | Start from the required result and business invariants |
| 2 | 理解连接基数 | Join cardinality and row multiplication must be understood |
| 3 | 防静默重复行 | Verify that joins do not silently duplicate parent rows |
| 4 | LEFT 过滤放对位置 | Treat `LEFT JOIN` filters carefully so they do not accidentally become inner joins |
| 5 | 避免隐式类型转换 | Avoid implicit type and collation conversions in predicates and joins |
| 6 | 只选需要的列 | Select only required columns instead of defaulting to `SELECT *` |
| 7 | 分页要有确定排序 | Make ordering deterministic when using pagination or limits |

写完后自问三句：

1. 结果**一行代表什么**？（订单 / 用户 / 明细？）
2. 1:N JOIN 后行数会不会变多？`SUM` 会不会重复计？
3. `LEFT JOIN` 的右表条件是在 `ON` 还是 `WHERE`？会不会误变 INNER？

---

## 13. 一句话速记

- **JOIN** = **INNER JOIN**（简写与全称等价）
- **INNER JOIN**：两边都有才要
- **LEFT JOIN**：左表全要，右表能填就填
- **ON** 管怎么连，**WHERE** 管连完再筛
- **LEFT JOIN + WHERE 右表条件** = 容易变 INNER
- **1:N JOIN** = 行变多，聚合前先想粒度

---

## 相关文件

- [day03.md](./day03.md)
- [mysql-pro-agent.md](./mysql-pro-agent.md)
- [day02-group-by-order-stats-explained.md](./day02-group-by-order-stats-explained.md)
- [er-diagram-10min-quickstart.md](../php/er-diagram-10min-quickstart.md)
- [labs/01_schema.sql](./labs/01_schema.sql)
- [labs/02_seed.sql](./labs/02_seed.sql)
