# Day 05：索引、EXPLAIN 与查询优化入门

> 所属：7 天掌握 MySQL  
> 类型：性能诊断  
> 建议时长：3–4h  
> 对应路线：阶段 4 索引原理 + 阶段 3 慢查询分析

---

## 今日目标

理解 B+Tree 索引、最左前缀、覆盖索引与失效场景；会用 `EXPLAIN` 读计划；能为真实查询设计联合索引。

**今日核心句：**

> 索引是面向访问路径的数据结构，不是「给 WHERE 里出现的列各加一个」；优化必须有 EXPLAIN / 行数 / 耗时证据。

---

## 0. 今日路线

1. 为什么需要索引
2. InnoDB 聚簇索引 vs 二级索引
3. 联合索引与最左前缀
4. 覆盖索引与回表
5. 常见失效场景
6. EXPLAIN 字段解读
7. 为 shop_lab 查询设计索引
8. 深分页与 keyset
9. 练习

---

## 1. 索引是什么

没有索引：全表扫描（像一页页翻书）。  
有索引：按有序目录定位，再取行。

代价：

- 占用磁盘与 buffer pool
- 拖慢 INSERT/UPDATE/DELETE（维护索引）
- 过多冗余索引 = 只付成本不获益

**规则：** 先有查询，再有索引；不为臆测列建索引。

---

## 2. InnoDB 两种索引

| 类型 | 含义 |
|------|------|
| 聚簇索引 | 叶子节点 **就是整行**；通常主键 |
| 二级索引 | 叶子是 **(索引列, 主键)**；再回表取整行 |

故：主键尽量稳定、尽量短；无业务主键可用自增 BIGINT。

---

## 3. 联合索引与最左前缀

```sql
-- 假设
KEY idx_orders_user_status_created (user_id, status, created_at)
```

| 查询条件 | 能否较好使用该索引 |
|----------|-------------------|
| `user_id = ?` | 能 |
| `user_id = ? AND status = ?` | 能 |
| `user_id = ? AND status = ? AND created_at > ?` | 能 |
| `status = ?` | 通常 **不能** 有效用最左 |
| `user_id = ? AND created_at > ?` | 可用 user_id，status 断档后范围需看优化器 |

**经验顺序（不是教条）：**

1. 等值过滤列  
2. 范围列（`>`、`BETWEEN`、`LIKE 'a%'`）通常放后面  
3. 考虑排序/分组是否可被同一索引满足  

不要死记「高基数列永远放最前」——要看 **完整访问路径**（过滤 + 排序 + 覆盖）。

---

## 4. 回表与覆盖索引

```sql
-- 若只查索引中的列 + 主键，可能「覆盖索引」避免回表
SELECT user_id, status, created_at
FROM orders
WHERE user_id = 1 AND status = 1
ORDER BY created_at DESC
LIMIT 10;
```

`EXPLAIN` 中 `Extra: Using index` 常表示覆盖索引（含义以实际版本为准，需结合 type/key 看）。

`SELECT *` 往往迫使回表。

---

## 5. 常见索引失效 / 低效场景

```sql
-- 1) 对索引列做函数/运算
WHERE DATE(created_at) = '2026-01-01'   -- 差
WHERE created_at >= '2026-01-01' AND created_at < '2026-01-02'  -- 好

-- 2) 隐式类型转换
WHERE phone = 13800138000   -- phone 是 VARCHAR 时可能翻车

-- 3) 前导模糊
WHERE name LIKE '%Tom%'     -- 难用 BTree

-- 4) 负向条件过多导致优化器放弃
WHERE status <> 4           -- 不一定失效，但选择性差时近乎全扫

-- 5) OR 两侧索引不对称
WHERE user_id = 1 OR order_no = 'X'  -- 可能索引合并或放弃，需看计划
```

---

## 6. EXPLAIN 速查

```sql
EXPLAIN
SELECT *
FROM orders
WHERE user_id = 1 AND status = 1
ORDER BY created_at DESC
LIMIT 10;
```

| 列 | 关注点 |
|----|--------|
| `type` | `const`/`eq_ref`/`ref`/`range` 通常可接受；`ALL` 全表要警惕 |
| `key` | 实际用了哪个索引 |
| `possible_keys` | 候选索引 |
| `rows` | 估计扫描行数（越准越好，但是估计） |
| `filtered` | 估计过滤比例 |
| `Extra` | `Using filesort` / `Using temporary` / `Using index` / `Using where` |

MySQL 8：

```sql
EXPLAIN FORMAT=TREE
SELECT ...;

-- 会真正执行！勿对生产高危语句随意用
-- EXPLAIN ANALYZE SELECT ...;
```

**安全：** `EXPLAIN ANALYZE` 会执行语句；只在本地 lab 或已授权的只读副本上对安全 SELECT 使用。

---

## 7. 为 shop_lab 设计索引

先看查询，再 `ALTER`：

```sql
-- 查询 A：用户订单列表
SELECT id, order_no, status, total_amount, created_at
FROM orders
WHERE user_id = ?
ORDER BY created_at DESC, id DESC
LIMIT 20;

-- 候选索引
-- KEY idx_orders_user_created (user_id, created_at, id)
```

```sql
-- 查询 B：按订单号
SELECT * FROM orders WHERE order_no = ?;
-- 已有 UNIQUE(order_no) 足够
```

```sql
-- 查询 C：明细按订单
SELECT * FROM order_items WHERE order_id = ?;
-- KEY idx_order_items_order_id (order_id)
```

实验：

```sql
EXPLAIN SELECT id, order_no, status, created_at
FROM orders
WHERE user_id = 1
ORDER BY created_at DESC
LIMIT 10;

-- 若缺索引，在 lab 添加后对比 rows / type
ALTER TABLE orders
  ADD KEY idx_orders_user_created (user_id, created_at, id);
```

对比前后 `EXPLAIN`，记在笔记：**type / key / rows / Extra**。

---

## 8. 深分页：OFFSET vs Keyset

```sql
-- OFFSET 深分页：越往后越慢
SELECT * FROM orders ORDER BY id DESC LIMIT 10 OFFSET 100000;

-- Keyset（seek）：记住上一页最后一条
SELECT * FROM orders
WHERE id < ?
ORDER BY id DESC
LIMIT 10;
```

列表接口优先 keyset；管理后台偶发深翻页可接受 OFFSET。

---

## 9. 慢查询优化清单（实战顺序）

1. 确认 SQL **结果正确**
2. 看是否多余列、多余 JOIN、错误粒度
3. `EXPLAIN` 看访问路径
4. 加/改 **一个** 最贴合的索引
5. 再测耗时与 rows
6. 仍慢：拆查询、缓存、汇总表、架构（读写分离等 Day7）

禁止一上来 `FORCE INDEX` 或乱改全局参数。

---

## 10. N+1 提示（与 ORM）

```text
1 次查订单列表 + 循环 N 次查明细 = N+1
→ SQL 侧：JOIN 或 IN 批量
→ Yii2：with() / joinWith()
→ 统计总查询次数，不只看单条耗时
```

---

## 11. 今日练习

1. 对「用户 1 的最近 10 笔订单」做 EXPLAIN，记录 type/key/rows  
2. 添加（或确认）合适联合索引，再 EXPLAIN 对比  
3. 写一条会 `Using filesort` 的查询，再改索引或排序列消除它（能消则消）  
4. 用半开区间查某天订单，避免 `DATE(created_at)`  
5. 把 OFFSET 翻页改写为 keyset 形式  
6. （选做）在 SQL 自学网刷 5 道索引相关题  

---

## 12. 自测清单

- [ ] 能解释聚簇索引 vs 二级索引
- [ ] 能用最左前缀判断联合索引是否命中
- [ ] 能读懂 EXPLAIN 的 type/key/rows/Extra
- [ ] 知道函数包列、前导 % 的危害
- [ ] 能说明 keyset 分页好处

---

## 13. 5 行复盘

```text
今天最清楚：
我对比的 EXPLAIN 前后差异：
一条我否决的「乱加索引」理由：
覆盖索引例子：
明天预习：事务与锁
```

## 14. AI Review 提问

```text
查询如下（贴 SQL + EXPLAIN 结果）。
请判断索引是否匹配访问路径，有无冗余索引，是否存在只改 SQL 不必加索引的方案。
```

---

## 下一步

→ [Day 06：事务、MVCC 与锁](./day06.md)
