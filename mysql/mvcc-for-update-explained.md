# MVCC 直觉与 SELECT ... FOR UPDATE 详解

> 所属：7 天掌握 MySQL  
> 对应：[day06.md](./day06.md) 第 4–5 节「MVCC 直觉」「锁与 FOR UPDATE」  
> 相关：[innodb-clustered-secondary-index.md](./innodb-clustered-secondary-index.md)

---

## 0. 先读结论

InnoDB 里读分两类：

| 类型 | SQL 例子 | 看到什么 | 会不会加锁 |
|------|----------|----------|------------|
| **快照读** | 普通 `SELECT` | 某个「版本视图」下的数据 | 不加行锁 |
| **当前读** | `SELECT ... FOR UPDATE`、`UPDATE`、`DELETE` | **最新已提交**的数据 | 会加锁 |

```text
普通 SELECT  →  MVCC：读历史版本，不挡别人写
FOR UPDATE   →  当前读：读最新 + 加排他锁，挡住别人改同一行
```

**一句话：** MVCC 让读不阻塞写；`FOR UPDATE` 让读变成「我要独占改这行」。

---

## 1. MVCC 解决什么问题？

没有 MVCC 时，读和写会互相挡：

```text
事务 A 在读行
事务 B 想改同一行 → 必须等 A 读完
→ 读多写多的系统吞吐很差
```

MVCC 的思路：**读的时候不直接锁行，而是读某一时刻的「快照版本」**。

---

## 2. 三个关键零件（不钻源码）

```text
┌─────────────────────────────────────────┐
│  行数据（聚簇索引叶子页）                  │
│  每行隐含：trx_id（最后修改它的事务 ID）   │
└─────────────────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────────┐
│  undo log（历史版本链）                   │
│  旧值1 ← 旧值2 ← 当前值                   │
│  用于：回滚 + 给快照读提供旧版本            │
└─────────────────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────────┐
│  Read View（读视图）                      │
│  「我能看见哪些 trx_id 的修改？」          │
└─────────────────────────────────────────┘
```

**白话：**

1. 每次修改一行，旧值进 **undo log**，形成版本链。
2. 普通 `SELECT` 时，InnoDB 根据 **Read View** 判断：当前版本能不能看？不能就看 undo 里的旧版本。
3. 多个事务可同时读，各看各的快照，**读不加锁**。

---

## 3. Read View 与隔离级别

MySQL 8 默认 **REPEATABLE READ（RR）**。

| 隔离级别 | Read View 建立时机 | 同一事务内再读 |
|----------|-------------------|----------------|
| **READ COMMITTED** | **每次 SELECT** 都新建 | 可能变（不可重复读） |
| **REPEATABLE READ** | **第一次一致性读**时建立 | 同一快照，值不变 |

**RR 下快照读示例：**

```sql
USE shop_lab;

START TRANSACTION;
SELECT stock FROM products WHERE id = 1;  -- 假设 stock=100，建立快照

-- 别的会话 COMMIT 把 stock 改成 99

SELECT stock FROM products WHERE id = 1;  -- 仍是 100（快照读）
COMMIT;

-- 事务外再 SELECT → 才是 99
```

对应 day06 三种读异常：

| 异常 | 含义 |
|------|------|
| **脏读** | 读到别人未提交的数据 |
| **不可重复读** | 同一事务内两次读同一行，值变了 |
| **幻读** | 同一事务内两次范围读，行数变了 |

RR + 快照读可避免不可重复读；幻读靠 MVCC + 间隙锁等 **大幅缓解**，面试不要背成「绝对没有」。

---

## 4. 三种日志（和 MVCC 的关系）

| 日志 | 作用 |
|------|------|
| **undo log** | 回滚 + MVCC 历史版本 |
| **redo log** | 崩溃恢复，保证已提交不丢 |
| **binlog** | 主从复制、按时间点恢复 |

MVCC 读旧版本靠 **undo**；提交持久靠 **redo**。  
redo 与 binlog 通过两阶段提交保持一致（理解「为什么需要」即可）。

---

## 5. MVCC 管不了什么？

MVCC 主要管 **普通 SELECT**，不能保证「我读完后别人不能往范围里插新行」——那是幻读。

InnoDB 在 RR 下还会配合：

| 锁 | 作用 |
|----|------|
| **间隙锁 Gap** | 锁索引间隙，防幻插 |
| **临键锁 Next-Key** | 行锁 + 间隙锁 |

带锁的当前读、范围条件时行为要单独分析。

---

## 6. SELECT ... FOR UPDATE 是什么？

把普通 `SELECT` 变成 **当前读 + 排他锁（X 锁）**：

```sql
START TRANSACTION;
SELECT id, stock FROM products WHERE id = 1 FOR UPDATE;
-- 读到最新已提交值，并对 id=1 加排他锁
UPDATE products SET stock = stock - 1 WHERE id = 1;
COMMIT;
```

含义：**先占住这行，别人不能改，直到 COMMIT / ROLLBACK。**

### 6.1 三种读方式对比

```sql
-- ① 快照读：不加锁，可能读到事务开始时的旧值
SELECT stock FROM products WHERE id = 1;

-- ② 共享当前读：加 S 锁，别人可读，不能写
SELECT stock FROM products WHERE id = 1 LOCK IN SHARE MODE;
-- MySQL 8+ 等价：FOR SHARE

-- ③ 排他当前读：加 X 锁，别人不能 FOR UPDATE 也不能写
SELECT stock FROM products WHERE id = 1 FOR UPDATE;
```

### 6.2 双会话实验（day06 实验 B）

```sql
-- 会话1
START TRANSACTION;
SELECT * FROM products WHERE id = 1 FOR UPDATE;

-- 会话2
START TRANSACTION;
SELECT * FROM products WHERE id = 1 FOR UPDATE;  -- 阻塞，直到会话1 COMMIT/ROLLBACK
```

### 6.3 锁到什么？

取决于 **WHERE 能否走索引**：

| 情况 | 锁范围 |
|------|--------|
| `WHERE id = 1`（主键命中） | 通常只锁 **这一行** |
| `WHERE user_id = 1`（二级索引） | 匹配行 + 可能间隙 |
| **没索引** 的 WHERE | 可能锁很多行甚至全表（极危险） |

**工程原则：** `FOR UPDATE` 的 WHERE 必须能精准走索引。

---

## 7. 什么时候用 FOR UPDATE？

| 场景 | 推荐 |
|------|------|
| 先读后写，中间有业务判断 | `FOR UPDATE` 或条件 `UPDATE` |
| 纯扣减，只要「够就减」 | **条件 UPDATE 往往更好** |
| 只读报表 | 普通 `SELECT` |
| 支付回调、幂等写 | 唯一键 + 短事务，不一定需要 `FOR UPDATE` |

### 写法 A：FOR UPDATE（经典两阶段）

```sql
START TRANSACTION;
SELECT stock FROM products WHERE id = 1 FOR UPDATE;
-- 应用判断 stock >= 1
UPDATE products SET stock = stock - 1 WHERE id = 1;
COMMIT;
```

### 写法 B：条件 UPDATE（更常用）

```sql
UPDATE products
SET stock = stock - 1
WHERE id = 1 AND stock >= 1;
-- affected rows = 1 → 成功；= 0 → 库存不足
```

`UPDATE` 本身也是 **当前读 + X 锁**，持锁时间通常更短。

| | FOR UPDATE + UPDATE | 条件 UPDATE |
|--|---------------------|-------------|
| 持锁时间 | 更长（含应用逻辑） | 更短 |
| 代码 | 两步，要在事务里 | 一步，简单 |
| 适用 | 读后要做复杂判断 | 原子扣减、抢库存 |

mysql-pro 原则：**只有不变量真的需要「先占锁再判断」时才用 `FOR UPDATE`。**

---

## 8. 常见坑

### 坑 1：事务里做远程调用

```text
BEGIN
  SELECT ... FOR UPDATE   ← 锁已持有
  调支付 HTTP（3 秒）    ← 锁白占 3 秒
  UPDATE ...
COMMIT
```

事务内不做 HTTP/RPC/长耗时 IO。

### 坑 2：没索引导致锁全表

```sql
SELECT * FROM orders WHERE remark = 'xxx' FOR UPDATE;
-- 若 remark 无索引 → 锁范围爆炸
```

### 坑 3：死锁

```text
T1: 锁商品 A → 等用户 B
T2: 锁用户 B → 等商品 A
→ InnoDB 检测死锁 → 回滚成本较低的一方
```

处理：固定加锁顺序、短事务、捕获死锁错误后有限次幂等重试。

### 坑 4：以为 FOR UPDATE 能防所有并发

它只防 **同一行 / 同一索引范围** 的并发写；跨表一致性还要靠事务边界 + 唯一约束 + 业务幂等。

---

## 9. 下单扣库存：完整心智模型

```text
用户下单
  │
  ├─ 普通 SELECT 查商品展示价
  │     → 快照读，MVCC，快，不加锁
  │
  └─ 真正扣库存
        │
        ├─ 方案1: UPDATE ... WHERE stock >= 1  （推荐）
        │
        └─ 方案2: SELECT FOR UPDATE → 判断 → UPDATE
```

**下单事务边界（伪代码）：**

```text
Service.placeOrder:
  校验参数与幂等 order_no
  BEGIN
    锁定/扣减库存（条件 UPDATE 或 FOR UPDATE）
    写 orders
    写 order_items（快照价）
    写 order_addresses（快照地址）
  COMMIT
  事后：删缓存、发消息（事务外，考虑失败补偿）
```

---

## 10. 时序直觉（快照读 vs FOR UPDATE）

```text
时间线 ──────────────────────────────────────────────►

事务1 (RR):
  START
  SELECT stock        ← 快照读，建立 Read View，stock=100
  [事务2 把 stock 改成 99 并 COMMIT]
  SELECT stock        ← 仍是 100（同一快照）
  COMMIT

事务1:
  START
  SELECT ... FOR UPDATE  ← 当前读，stock=99，加 X 锁
  [事务2 想 FOR UPDATE 同一行 → 阻塞]
  UPDATE ...
  COMMIT                ← 事务2 才获得锁
```

---

## 11. 5 行速记

```text
MVCC：多版本 + Read View + undo，快照读不加锁，RR 下同一事务读一致。
当前读：FOR UPDATE / UPDATE 看最新数据并加锁。
FOR UPDATE：排他锁，挡住别人改同一行；WHERE 必须走索引。
扣库存：优先条件 UPDATE；复杂判断才 FOR UPDATE。
死锁：固定锁顺序 + 短事务 + 幂等重试。
```

---

## 12. 本地实验对照（day06 第 8 节）

**实验 A：未提交更新可见性（RC）**

```sql
-- 会话1
SET TRANSACTION ISOLATION LEVEL READ COMMITTED;
START TRANSACTION;
UPDATE products SET stock = stock - 1 WHERE id = 1;
-- 先不 COMMIT

-- 会话2
SELECT stock FROM products WHERE id = 1;  -- RC：仍是旧值
-- 会话1 COMMIT 后，会话2 再读 → 新值
```

**实验 B：** 见上文 §6.2。
