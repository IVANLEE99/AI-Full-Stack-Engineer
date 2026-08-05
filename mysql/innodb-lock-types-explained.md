# InnoDB 锁类型详解：S/X、行锁、间隙锁与临键锁

> 所属：7 天掌握 MySQL  
> 对应：[day06.md](./day06.md) 第 5 节「锁：先建立正确粒度」  
> 相关：[mvcc-for-update-explained.md](./mvcc-for-update-explained.md) · [innodb-clustered-secondary-index.md](./innodb-clustered-secondary-index.md)

---

## 0. 先读结论

InnoDB 的锁可以从 **两个维度** 理解：

```text
维度1：锁的「模式」—— S / X（读锁 vs 写锁，能不能共存）
维度2：锁的「范围」—— 行 / 表 / 间隙 / 临键（锁多大一块）
```

| 锁 | 一句话 |
|----|--------|
| **共享锁 S** | 多人能读，不能写；`FOR SHARE` |
| **排他锁 X** | 独占；`FOR UPDATE` / `UPDATE` / `DELETE` |
| **行锁** | InnoDB 常规，锁命中行；WHERE 要走索引 |
| **表锁** | 锁整表；DDL、粗粒度，业务少碰 |
| **间隙锁 Gap** | 锁索引空隙，RR 下防别人 INSERT 幻行 |
| **临键锁 Next-Key** | 行锁 + 前面那段间隙；RR 范围扫描默认 |

---

## 1. 共享锁 S vs 排他锁 X（锁的「模式」）

决定 **锁之间能不能共存**，不是「锁多大」。

| | 别人加 S | 别人加 X |
|--|---------|---------|
| **我已持有 S** | ✅ 可以 | ❌ 不行 |
| **我已持有 X** | ❌ 不行 | ❌ 不行 |

**直觉：**

- **S（Shared）**：我在读，你们也可以读，但别改。
- **X（Exclusive）**：我要改，你们都别碰。

**对应 SQL：**

```sql
-- 加 S 锁（共享当前读）
SELECT * FROM products WHERE id = 1 LOCK IN SHARE MODE;
-- MySQL 8+：FOR SHARE

-- 加 X 锁（排他当前读）
SELECT * FROM products WHERE id = 1 FOR UPDATE;

-- UPDATE / DELETE 默认也是 X 锁
UPDATE products SET stock = stock - 1 WHERE id = 1;
```

**双会话示例：**

```text
会话1: SELECT ... FOR SHARE     → 持有 S
会话2: SELECT ... FOR SHARE     → ✅ 也能读
会话3: UPDATE / FOR UPDATE      → ❌ 阻塞，等会话1 结束
```

```text
会话1: SELECT ... FOR UPDATE    → 持有 X
会话2: FOR SHARE / FOR UPDATE / UPDATE → ❌ 全阻塞
```

**记忆：** S 是「共读」；X 是「独占」。写操作永远是 X。

---

## 2. 行锁 vs 表锁（锁的「粒度」）

| 锁 | 粒度 | 谁用 | 直觉 |
|----|------|------|------|
| **行锁** | 一行 | InnoDB 默认 | 只挡命中那一行（或几行） |
| **表锁** | 整张表 | DDL、部分语句 | 一锁锁全表，并发差 |

**行锁例子：**

```sql
UPDATE products SET stock = stock - 1 WHERE id = 1;
-- 理想：只锁 id=1 这一行
```

**表锁例子：**

```sql
ALTER TABLE products ADD COLUMN xxx ...;  -- DDL，可能锁表
LOCK TABLES products WRITE;               -- 显式表锁（很少用）
```

**工程要点：**

- InnoDB 业务读写默认是 **行级锁**
- `WHERE` 走不到索引 → 可能锁很多行甚至全表扫描
- 加锁语句的 **WHERE 必须走索引**（见 [day05.md](./day05.md)）

---

## 3. 间隙锁 Gap Lock

间隙锁主要存在于 **RR（REPEATABLE READ）** 下，且针对 **当前读**（`FOR UPDATE`、`UPDATE` 等）。  
普通快照 `SELECT` **不加** 间隙锁（靠 MVCC）。

### 3.1 什么是「间隙」？

索引是有序的。相邻两个索引键值之间有一段空区间：

```text
索引 user_id 上的值：

    (1)        (5)        (10)       (20)
     │          │          │          │
     └─间隙1───┘└─间隙2───┘└─间隙3───┘
```

间隙锁锁的是这些 **开区间**，不是某一行本身。

### 3.2 为什么需要？

防止 **幻读**：别人往你扫过的范围里 **INSERT** 新行。

```sql
-- 会话1（RR）
START TRANSACTION;
SELECT * FROM orders WHERE user_id = 5 FOR UPDATE;

-- 会话2
INSERT INTO orders (user_id, ...) VALUES (7, ...);
-- 若 7 落在被锁间隙里 → 阻塞
```

### 3.3 注意

- **RC 隔离级别没有间隙锁**（幻读更容易出现）
- 主键/唯一索引 **等值命中且行存在** 时，可能只锁行、不锁间隙
- 间隙锁之间 **不互斥**（多个事务可同时锁同一间隙，挡的是 INSERT）

---

## 4. 临键锁 Next-Key Lock

```text
Next-Key Lock = Record Lock（行锁）+ Gap Lock（间隙锁）
```

InnoDB 在 RR 下，**范围扫描** 时默认加临键锁：

```text
索引值:  1    5    10    20

临键锁 (-∞, 1]  → 间隙 (-∞,1] + 行 1
临键锁 (1, 5]   → 间隙 (1,5]  + 行 5
临键锁 (5, 10]  → 间隙 (5,10] + 行 10
```

**直觉：** 锁住这一行，也锁住它前面的那段空隙——别人既改不了这行，也插不进间隙。

**例子：**

```sql
SELECT * FROM orders WHERE user_id >= 5 AND user_id < 10 FOR UPDATE;
```

可能锁住 `user_id=5`、`7`（若存在）对应的行，以及它们之间的间隙，防止插入 `user_id=6`、`8` 等新行。

---

## 5. 六者关系总图

```text
                    InnoDB 锁
                       │
         ┌─────────────┴─────────────┐
         │                           │
    锁的模式（S/X）              锁的范围
         │                           │
    ┌────┴────┐              ┌──────┴──────┐
    S 共享锁   X 排他锁        行锁    表锁
                              │
                         RR 下当前读还可能：
                              │
                    ┌─────────┴─────────┐
               间隙锁 Gap          临键锁 Next-Key
               （防幻插）          （行锁 + 间隙）
```

**叠加示例：**

```text
FOR UPDATE WHERE id = 1（主键等值）
  → X 锁 + 行锁（通常不加间隙）

FOR UPDATE WHERE user_id BETWEEN 5 AND 10（范围扫描）
  → X 锁 + 多行行锁 + 多个临键锁（含间隙）
```

---

## 6. 与 MVCC / FOR UPDATE 对照

| 操作 | 模式 | 范围 | 挡什么 |
|------|------|------|--------|
| 普通 `SELECT` | 无锁（MVCC） | — | 不挡别人读写 |
| `FOR SHARE` | S | 行（+可能间隙） | 挡别人写 |
| `FOR UPDATE` | X | 行（+可能间隙） | 挡别人读写 |
| `UPDATE` | X | 行（+可能间隙） | 挡别人读写 |

延伸阅读：[mvcc-for-update-explained.md](./mvcc-for-update-explained.md)

---

## 7. shop_lab 实战对照

### 7.1 主键扣库存（简单，推荐）

```sql
UPDATE products
SET stock = stock - 1
WHERE id = 1 AND stock >= 1;
```

- 模式：**X**
- 范围：**行锁**（`id` 主键命中）
- 一般 **不涉及间隙锁**

### 7.2 范围扫描（锁多、要小心）

```sql
SELECT * FROM orders WHERE status = 1 FOR UPDATE;
```

- 范围扫描 → 可能锁多行 + 临键锁
- 并发差、死锁风险高 → 需要合适索引缩小范围

### 7.3 day06 实验 B

```sql
-- 会话1
START TRANSACTION;
SELECT * FROM products WHERE id = 1 FOR UPDATE;

-- 会话2
START TRANSACTION;
SELECT * FROM products WHERE id = 1 FOR UPDATE;  -- 阻塞
```

这是 **X 行锁** 互斥，不是间隙锁场景。

---

## 8. 面试 / 实战常问

**Q：为什么 RR 下还会死锁？**  
A：不只锁一行；间隙锁 + 临键锁会让多个事务在「范围」上交叉等待。

**Q：怎么减少锁？**  
A：短事务、精准 WHERE、走索引、能用条件 `UPDATE` 就别长时间 `FOR UPDATE`、固定加锁顺序。

**Q：RC 和 RR 锁差在哪？**  
A：RC 没有间隙锁；RR 有间隙锁/临键锁，当前读下幻读被压住，但锁更多、死锁概率更高。

**Q：怎么查看当前锁？**  
A：生产可用 `performance_schema` / `information_schema`；lab 可看 `SHOW ENGINE INNODB STATUS` 里最近死锁段。

---

## 9. 5 行速记

```text
S/X：模式——共读 vs 独占；FOR SHARE vs FOR UPDATE。
行锁/表锁：粒度——InnoDB 默认行锁，WHERE 要走索引。
间隙锁：RR 下锁索引空隙，防 INSERT 幻行。
临键锁：行锁 + 间隙，范围扫描默认。
减锁：短事务、精准索引、条件 UPDATE 优于长持锁 FOR UPDATE。
```
