# Day 03：多表 JOIN 与子查询

> 所属：7 天掌握 MySQL  
> 类型：手写 SQL  
> 建议时长：3–4h  
> 对应路线：阶段 2（多表查询，面试重点）

---

## 今日目标

掌握 `INNER JOIN` / `LEFT JOIN`、一对多导致的行膨胀、子查询与 `EXISTS`，并能把订单+明细+地址查清楚。

**今日核心句：**

> JOIN 是在用键把关系拼起来；一对多会「行变多」，必须先想清结果粒度，再写 SELECT 和聚合。

---

## 0. 今日路线

1. 复习 shop_lab 表关系
2. INNER vs LEFT
3. 一对多行膨胀
4. 多表 JOIN 订单详情
5. 子查询三类位置
6. EXISTS / IN 反连接
7. UNION 简介
8. 练习 + 自测

---

## 1. 表关系心智图

```text
users (1) ──── (N) orders (1) ──── (N) order_items
                      │
                      └──── (1) order_addresses   -- 下单时地址快照
products (1) ──── (N) order_items
```

| 关系 | 例子 | 查询粒度提醒 |
|------|------|----------------|
| 1:1 | order ↔ order_addresses | 行数通常 ≈ 订单数 |
| 1:N | order → order_items | JOIN 后行数 = 明细行数 |
| N:N | 需中间表（本 lab 未建 tags 关联） | 经中间表两次 1:N |

---

## 2. INNER JOIN

两边都匹配才返回。

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

无用户的订单（脏数据）不会出现在结果中。

---

## 3. LEFT JOIN

以左表为主，右表无匹配则右列 `NULL`。

```sql
-- 所有订单 + 地址（可能无地址）
SELECT
  o.order_no,
  a.receiver_name,
  a.city
FROM orders o
LEFT JOIN order_addresses a ON a.order_id = o.id
ORDER BY o.id;
```

**易错：** 在 `WHERE` 里写右表条件会把 `LEFT JOIN` 变成实质 `INNER JOIN`。

```sql
-- 错误示范：丢掉没有地址的订单
SELECT o.order_no, a.city
FROM orders o
LEFT JOIN order_addresses a ON a.order_id = o.id
WHERE a.city = '上海';   -- 过滤掉 a.city IS NULL 的行

-- 若业务是「只要上海地址订单」，应改用 INNER JOIN 或把条件放 ON：
SELECT o.order_no, a.city
FROM orders o
LEFT JOIN order_addresses a
  ON a.order_id = o.id AND a.city = '上海';
```

---

## 4. 一对多：行膨胀

```sql
SELECT
  o.order_no,
  oi.product_name,
  oi.quantity,
  oi.unit_price
FROM orders o
INNER JOIN order_items oi ON oi.order_id = o.id
WHERE o.order_no = 'O202601010001';
```

一个订单 2 个商品 → 结果 2 行。  
若再 `SUM(o.total_amount)` 而不改粒度，会 **把订单金额加重复**。

**正确统计姿势：**

```sql
-- 按订单粒度先聚合明细，再关联
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

---

## 5. 典型三表：订单详情

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

---

## 6. 子查询

### 6.1 WHERE 子查询

```sql
-- 买过「键盘」的用户
SELECT id, name, email
FROM users
WHERE id IN (
  SELECT o.user_id
  FROM orders o
  JOIN order_items oi ON oi.order_id = o.id
  WHERE oi.product_name = '机械键盘'
);
```

### 6.2 FROM 子查询（派生表）

```sql
SELECT user_id, cnt
FROM (
  SELECT user_id, COUNT(*) AS cnt
  FROM orders
  GROUP BY user_id
) t
WHERE cnt >= 2;
```

### 6.3 SELECT 标量子查询（慎用，注意 N 次执行感）

```sql
SELECT
  o.order_no,
  (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_cnt
FROM orders o
LIMIT 20;
```

### 6.4 EXISTS（半连接，常优于大 IN 列表）

```sql
-- 至少有一笔已支付订单的用户
SELECT u.id, u.name
FROM users u
WHERE EXISTS (
  SELECT 1
  FROM orders o
  WHERE o.user_id = u.id
    AND o.status IN (1, 2, 3)
);
```

### 6.5 反连接：没有订单的用户

```sql
SELECT u.id, u.name
FROM users u
LEFT JOIN orders o ON o.user_id = u.id
WHERE o.id IS NULL;

-- 或
SELECT u.id, u.name
FROM users u
WHERE NOT EXISTS (
  SELECT 1 FROM orders o WHERE o.user_id = u.id
);
```

---

## 7. UNION / UNION ALL

```sql
SELECT email AS contact, 'user' AS source FROM users
UNION ALL
SELECT receiver_phone, 'address' FROM order_addresses;
```

- `UNION`：合并并去重（更贵）
- `UNION ALL`：合并不去重（通常更快）

---

## 8. 与 ORM 的对应（预告 week03）

```text
JOIN 查列表字段     ≈ joinWith() 当关联列参与过滤/排序
仅减少 N+1 查询     ≈ with() 预加载
循环里再查关联      ≈ N+1（Day5/week03 再深挖）
```

---

## 9. 今日练习

1. 列出每笔订单的：`order_no`、用户名、城市、明细商品名（多行）  
2. 统计每笔订单的明细行数与购买件数  
3. 找出从未下单的用户  
4. 找出购买过「USB-C 线」的用户邮箱  
5. 解释：订单 JOIN 明细后再 `SUM(total_amount)` 为何可能翻倍  
6. 写一条 `EXISTS`：存在「已取消」订单的用户  

---

## 10. 自测清单

- [ ] 能口述 INNER 与 LEFT 差异
- [ ] 能指出 LEFT + WHERE 右表条件的陷阱
- [ ] 能解释 1:N JOIN 行膨胀
- [ ] 会用 IN / EXISTS / 反连接
- [ ] 能写订单+用户+地址+明细的综合查询

---

## 11. 5 行复盘

```text
今天最清楚：
行膨胀例子：
LEFT JOIN 陷阱：
最难的一条 SQL：
明天预习：ER 与表设计
```

## 12. AI Review 提问

```text
我写了订单明细 JOIN SQL（贴 SQL）。
请检查：粒度是否正确、会不会重复计金额、LEFT/INNER 是否用对、有无隐式笛卡尔积。
```

---

## 下一步

→ [Day 04：ER 与表结构设计](./day04.md)
