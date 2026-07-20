# Day 06：事务、MVCC 与锁

> 所属：7 天掌握 MySQL  
> 类型：并发基础  
> 建议时长：3–4h  
> 对应路线：阶段 4 事务/锁 + 工程事务边界

---

## 今日目标

理解 ACID、隔离级别、脏读/不可重复读/幻读、MVCC 直觉、行锁/间隙锁、死锁与短事务原则。

**今日核心句：**

> 事务定义「哪些写必须一起成功」；隔离级别定义「并发下能看到多脏的数据」；锁与 MVCC 是实现手段——业务上优先短事务、固定加锁顺序、可重试的幂等写。

---

## 0. 今日路线

1. ACID
2. 显式事务边界
3. 隔离级别与三种读异常
4. MVCC 直觉
5. 锁类型与 FOR UPDATE
6. 死锁与重试
7. 下单伪代码事务边界
8. 练习（本地会话实验）

---

## 1. ACID

| 特性 | 含义 | 工程直觉 |
|------|------|----------|
| Atomicity 原子性 | 全成或全撤 | 扣库存 + 写订单同行失败则回滚 |
| Consistency 一致性 | 约束与业务不变量保持 | 唯一键、金额平衡 |
| Isolation 隔离性 | 并发事务互不干扰的程度 | 级别可配 |
| Durability 持久性 | 提交后不丢 | redo log 等 |

---

## 2. 事务怎么写

```sql
USE shop_lab;

START TRANSACTION;

UPDATE products
SET stock = stock - 1
WHERE id = 1 AND stock >= 1;

-- 检查影响行数；在应用层 rowCount == 0 则回滚

INSERT INTO orders (order_no, user_id, status, total_amount)
VALUES ('O-TEST-001', 1, 0, 99.00);

COMMIT;
-- 或 ROLLBACK;
```

会话参数：

```sql
SELECT @@autocommit;          -- 1 时每条自动提交
SELECT @@transaction_isolation;
-- MySQL 8 默认通常：REPEATABLE-READ
```

**应用层原则（mysql-pro）：**

- 事务内不做 HTTP/RPC/长耗时 IO
- 事务尽量短
- 需要加锁时 **顺序一致**（都先锁商品再锁用户…）
- 重试必须 **幂等**（同 order_no 唯一）

---

## 3. 隔离级别

| 级别 | 脏读 | 不可重复读 | 幻读 | 备注 |
|------|------|------------|------|------|
| READ UNCOMMITTED | 可能 | 可能 | 可能 | 基本不用 |
| READ COMMITTED | 否 | 可能 | 可能 | 很多别的库默认 |
| REPEATABLE READ | 否 | 否* | 视情况* | **InnoDB 默认** |
| SERIALIZABLE | 否 | 否 | 否 | 最严，吞吐差 |

\* InnoDB RR 下通过 MVCC + 间隙锁等机制大幅缓解幻读；面试要会讲，但不要背成「绝对没有」。

**读异常白话：**

- **脏读**：读到别人未提交的数据  
- **不可重复读**：同一事务内两次读同一行，值变了（被别人提交改了）  
- **幻读**：同一事务内两次范围读，行数变了（被别人插入）

---

## 4. MVCC 直觉（不钻源码）

快照读（普通 `SELECT`）：

```text
事务启动/第一次读时拿到一个「版本视图」
→ 只看见该视图下已提交的数据
→ 别人后续提交的修改对你这轮快照不可见（RR 下）
```

当前读（会加锁）：

```sql
SELECT ... FOR UPDATE;
SELECT ... LOCK IN SHARE MODE;  -- 或 FOR SHARE
UPDATE / DELETE
```

当前读看到的是最新提交数据，并对行（及可能间隙）加锁。

**undo log：** 回滚 + 历史版本链  
**redo log：** 崩溃恢复，保障已提交持久  
**binlog：** 复制与时间点恢复（逻辑日志）

两阶段提交：保证 redo 与 binlog 一致（面试考点，理解「为什么需要」即可）。

---

## 5. 锁：先建立正确粒度

| 锁 | 直觉 |
|----|------|
| 共享锁 S | 多人可读，互斥写 |
| 排他锁 X | 独占写 |
| 行锁 | InnoDB 常规，粒度细 |
| 表锁 | 粗；DDL/部分操作 |
| 间隙锁 Gap | 锁索引间隙，防幻插 |
| 临键锁 Next-Key | 行锁 + 间隙 |

业务最常用模式：

```sql
START TRANSACTION;
SELECT id, stock FROM products WHERE id = 1 FOR UPDATE;
-- 应用判断 stock
UPDATE products SET stock = stock - 1 WHERE id = 1;
COMMIT;
```

或单语句原子更新：

```sql
UPDATE products
SET stock = stock - 1
WHERE id = 1 AND stock >= 1;
-- 根据 affected rows 判断是否抢到库存
```

后者往往更简单、持锁更短。

---

## 6. 死锁

两个事务互相等待对方持有的锁。

```text
T1: 锁行 A → 等行 B
T2: 锁行 B → 等行 A
→ InnoDB 检测死锁 → 回滚成本较低的一方 → 应用收到死锁错误
```

处理：

1. **业务上** 固定加锁顺序  
2. 缩短事务  
3. 降低锁范围（精准 WHERE、合适索引 → 锁更少行）  
4. 捕获死锁错误后 **有限次幂等重试**  
5. 分析 `SHOW ENGINE INNODB STATUS` 中最近死锁日志（运维场景）

死锁是并发系统常态，不是「出现即设计失败」——关键是可诊断、可安全重试。

---

## 7. 下单事务边界示例（伪代码）

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

错误示范：

```text
BEGIN
  调支付中心 HTTP   ← 拉长事务、易超时持锁
  写订单
COMMIT
```

---

## 8. 本地双会话实验（强烈建议做）

开两个 `mysql` 客户端窗口。

**实验 A：未提交更新可见性**

```sql
-- 会话1
SET TRANSACTION ISOLATION LEVEL READ COMMITTED;
START TRANSACTION;
UPDATE products SET stock = stock - 1 WHERE id = 1;
-- 先不 COMMIT

-- 会话2
SELECT stock FROM products WHERE id = 1;  -- RC 下仍是旧值（未提交不可见）
-- 会话1 COMMIT 后，会话2 再读看是否变化
```

**实验 B：FOR UPDATE 阻塞**

```sql
-- 会话1
START TRANSACTION;
SELECT * FROM products WHERE id = 1 FOR UPDATE;

-- 会话2
START TRANSACTION;
SELECT * FROM products WHERE id = 1 FOR UPDATE;  -- 阻塞直到会话1结束
```

观察阻塞与提交后放行。超时可调 `innodb_lock_wait_timeout`（lab 了解即可）。

---

## 9. 今日练习

1. 用事务写：扣库存 + 插入订单，刻意制造库存不足并 `ROLLBACK`  
2. 解释默认隔离级别名称与含义  
3. 对比「条件 UPDATE 扣库存」与「SELECT FOR UPDATE + UPDATE」优劣  
4. 设计一个会死锁的两事务顺序，并给出调整后的加锁顺序  
5. 回答：唯一键 `order_no` 如何帮助支付回调幂等？  

---

## 10. 自测清单

- [ ] 能解释 ACID 各一字版含义
- [ ] 会写 START TRANSACTION / COMMIT / ROLLBACK
- [ ] 能区分快照读与当前读
- [ ] 知道默认 RR，能举脏读/不可重复读例子
- [ ] 理解死锁处理：顺序、短事务、幂等重试
- [ ] 事务内不做远程调用

---

## 11. 5 行复盘

```text
今天最清楚：
我亲手复现的现象：
短事务原则：
幂等点（order_no）：
明天预习：备份/主从/验收
```

## 12. AI Review 提问

```text
这是我的下单事务伪代码（贴出）。
请检查：事务边界、锁顺序、幂等、失败回滚、缓存更新时机是否安全。
```

---

## 下一步

→ [Day 07：生产实践与 7 天验收](./day07.md)
