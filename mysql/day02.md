# Day 02：查询进阶——过滤、排序、分页、聚合

> 所属：7 天掌握 MySQL  
> 类型：手写 SQL  
> 建议时长：3–4h  
> 对应路线：阶段 2（SQL 实战前半）

---

## 今日目标

熟练使用 `WHERE` 条件族、`ORDER BY`、`LIMIT/OFFSET`、`GROUP BY` + 聚合函数 + `HAVING`，并能解释 `NULL` 行为。

**今日核心句：**

> 查询 = 选列 + 过滤 + 关联（明天）+ 分组聚合 + 排序截断；先保证结果语义正确，再谈分页性能。

---

## 0. 今日路线

1. 复习 SELECT 骨架
2. WHERE 全套运算符
3. NULL 与三值逻辑
4. ORDER BY + LIMIT 分页
5. DISTINCT / CASE WHEN
6. GROUP BY 与聚合
7. HAVING vs WHERE
8. 在 `shop_lab` 完成练习

---

## 1. SELECT 骨架（先背结构）

```sql
SELECT   -- 要哪些列 / 表达式
FROM     -- 从哪张表
WHERE    -- 行级过滤
GROUP BY -- 分组
HAVING   -- 分组后过滤
ORDER BY -- 排序
LIMIT    -- 截断
;
```

执行逻辑心智模型（简化）：

```text
FROM → WHERE → GROUP BY → HAVING → SELECT → ORDER BY → LIMIT
```

---

## 2. WHERE 条件族

```sql
USE shop_lab;

-- 比较
SELECT * FROM products WHERE price >= 100.00;
SELECT * FROM orders WHERE status != 0;

-- 逻辑
SELECT * FROM products
WHERE price BETWEEN 50 AND 200
  AND status = 1;

-- IN
SELECT * FROM orders WHERE status IN (0, 1, 2);

-- 模糊（注意：前导 % 往往难用普通 BTree 索引）
SELECT * FROM users WHERE name LIKE 'A%';
SELECT * FROM users WHERE email LIKE '%@example.com';

-- 空值
SELECT * FROM order_addresses WHERE address_line2 IS NULL;
SELECT * FROM order_addresses WHERE address_line2 IS NOT NULL;
```

| 写法 | 含义 |
|------|------|
| `=` `<>` `!=` `>` `<` `>=` `<=` | 比较 |
| `AND` `OR` `NOT` | 逻辑（注意括号） |
| `IN (...)` | 集合 |
| `BETWEEN a AND b` | 闭区间 |
| `LIKE` | 模糊；`%` 任意、`_` 单字符 |
| `IS NULL` / `IS NOT NULL` | 判空（**不能** `= NULL`） |

---

## 3. NULL：三值逻辑

```sql
-- 错误直觉：
-- WHERE col = NULL     -- 永远不是 TRUE
-- 正确：
WHERE col IS NULL
```

聚合时：

- `COUNT(*)` 计行数
- `COUNT(col)` **不计** `NULL`
- `SUM/AVG` 忽略 `NULL`

```sql
SELECT
  COUNT(*) AS total_rows,
  COUNT(address_line2) AS non_null_line2
FROM order_addresses;
```

---

## 4. 排序与分页

```sql
-- 最新订单
SELECT id, order_no, user_id, total_amount, created_at
FROM orders
ORDER BY created_at DESC, id DESC
LIMIT 20;

-- 第 3 页，每页 10 条（OFFSET 分页）
SELECT id, order_no, created_at
FROM orders
ORDER BY created_at DESC, id DESC
LIMIT 10 OFFSET 20;
```

**工程注意：**

1. 有 `LIMIT` 时尽量保证 **排序键确定性**（加上 `id` 作 tie-breaker）
2. 深分页 `OFFSET` 很大时会变慢 → Day5 提 keyset 分页
3. `ORDER BY` 字段最好能被索引覆盖（Day5）

---

## 5. 别名、DISTINCT、CASE

```sql
SELECT
  o.order_no AS no,
  o.total_amount AS amount
FROM orders o
WHERE o.status = 1;

-- 去重状态列表
SELECT DISTINCT status FROM orders ORDER BY status;

-- 状态文案
SELECT
  order_no,
  CASE status
    WHEN 0 THEN '待支付'
    WHEN 1 THEN '已支付'
    WHEN 2 THEN '已发货'
    WHEN 3 THEN '已完成'
    WHEN 4 THEN '已取消'
    ELSE '未知'
  END AS status_text
FROM orders
LIMIT 20;
```

---

## 6. 分组与聚合

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

常用聚合：

| 函数 | 作用 |
|------|------|
| `COUNT(*)` | 行数 |
| `COUNT(DISTINCT x)` | 去重计数 |
| `SUM` `AVG` `MAX` `MIN` | 汇总 |

**规则：** `SELECT` 中非聚合列必须出现在 `GROUP BY` 中（MySQL 在 ONLY_FULL_GROUP_BY 模式下严格）。

---

## 7. HAVING vs WHERE

```sql
-- WHERE：分组前过滤行
-- HAVING：分组后过滤组

SELECT user_id, COUNT(*) AS cnt
FROM orders
WHERE status <> 4          -- 先丢掉取消单
GROUP BY user_id
HAVING COUNT(*) >= 2       -- 再留订单数≥2 的用户
ORDER BY cnt DESC;
```

---

## 8. 实用字符串 / 时间函数（够用即可）

```sql
SELECT
  CONCAT(name, '<', email, '>') AS label,
  UPPER(LEFT(name, 1)) AS initial,
  DATE(created_at) AS day,
  DATE_FORMAT(created_at, '%Y-%m') AS ym
FROM users
LIMIT 5;

SELECT *
FROM orders
WHERE created_at >= '2026-01-01'
  AND created_at <  '2026-02-01';
```

时间范围优先用 **半开区间** `[start, end)`，避免边界重复与时区坑。

---

## 9. 今日练习（shop_lab）

1. 查询价格 ≥ 100 且上架的商品，按价格降序，取 5 条  
2. 统计每个 `status` 的订单数量  
3. 找出订单总额 ≥ 200 的用户及其订单数（`HAVING`）  
4. 用 `CASE` 输出订单状态中文  
5. 查询 2026 年 1 月创建的订单（用半开区间）  
6. 解释：`COUNT(*)` 与 `COUNT(address_line2)` 在 `order_addresses` 上为何可能不同  

参考骨架见 [labs/03_practice.sql](./labs/03_practice.sql)。

---

## 10. 自测清单

- [ ] 能默写 SELECT 子句顺序
- [ ] 知道 `= NULL` 无效，要用 `IS NULL`
- [ ] 能写分页 SQL，并说明为何加第二排序键
- [ ] 能区分 WHERE 与 HAVING
- [ ] 能写 `GROUP BY` + `SUM/COUNT` 统计

---

## 11. 5 行复盘

```text
今天最清楚：
今天最卡：
NULL 相关踩坑：
一条我最得意的 SQL：
明天预习：JOIN 与子查询
```

## 12. AI Review 提问

```text
这是我 Day2 写的 3 条统计 SQL（贴 SQL）。
请指出：结果语义是否可能错、NULL 处理、分页是否确定排序、是否有全表扫风险。
```

---

## 下一步

→ [Day 03：多表 JOIN 与子查询](./day03.md)
