# B+Tree 在 Day05 五条 SQL 上怎么走（图解）

> 所属：7 天掌握 MySQL  
> 对应：[day05.md](./day05.md)  
> 相关：[innodb-clustered-secondary-index.md](./innodb-clustered-secondary-index.md)

---

## 0. 先定索引前提

下文默认有这条联合二级索引（day05 示例）：

```sql
KEY idx_orders_user_status_created (user_id, status, created_at)
```

叶子节点近似存：

```text
(user_id, status, created_at, 主键 id)
```

聚簇索引（主键）叶子存整行。

B+Tree 粗结构：

```text
        [非叶子：目录键]
       /       |        \
  [叶子有序页] ↔ [叶子有序页] ↔ [叶子有序页]
   ↑
  叶子按 (user_id, status, created_at, id) 排序，页与页用链表相连
```

---

## SQL 1：等值最左 —— 能走索引

```sql
SELECT * FROM orders WHERE user_id = 1;
```

### 怎么走

```text
1. 从根/非叶子按 user_id=1 往下定位
2. 落到叶子上「user_id=1」的第一段
3. 沿叶子链表扫完所有 user_id=1 的条目
4. 每个条目拿出主键 id → 回表（聚簇索引）取整行
```

```text
二级索引叶子（示意）
(1, 0, 2026-01-10, id=3)
(1, 1, 2026-01-11, id=1)  ← 从这里扫 user_id=1 段
(1, 1, 2026-01-18, id=6)
(2, 0, 2026-01-12, id=3)  ← user_id 变了，停
```

**结论：** 最左列等值 → 能用索引；`SELECT *` → 通常要回表。

---

## SQL 2：最左连续等值 —— 能走索引

```sql
SELECT * FROM orders
WHERE user_id = 1 AND status = 1;
```

### 怎么走

```text
1. 先定位到 (user_id=1, status=1) 的区间起点
2. 只扫 status=1 这一段（比只扫 user_id 更窄）
3. 回表取整行
```

```text
(1, 0, ...)  ← status≠1，跳过/不进入这段
(1, 1, 2026-01-11, id=1)  ← 命中
(1, 1, 2026-01-18, id=6)  ← 命中
(1, 2, ...)  ← status 变了，停
```

**结论：** `(user_id, status)` 最左连续等值 → 很好用。

---

## SQL 3：等值 + 范围 + 排序 —— 理想路径

```sql
SELECT user_id, status, created_at, id
FROM orders
WHERE user_id = 1 AND status = 1 AND created_at > '2026-01-01'
ORDER BY created_at DESC
LIMIT 10;
```

### 怎么走

```text
1. 定位到 (user_id=1, status=1, created_at>'2026-01-01') 区间
2. 在叶子上按 created_at 有序扫描（范围）
3. 所需列都在二级索引叶子里 → 可能覆盖，少回表
4. 若优化器能反向扫叶子，ORDER BY created_at DESC 可能免 filesort
```

**结论：** 等值列在前、范围列在后；返回列在索引内 → 覆盖索引机会大。

`EXPLAIN` 期望关注：`key=idx_...`，`Extra` 可能有 `Using index`。

---

## SQL 4：跳过最左 —— 通常用不好

```sql
SELECT * FROM orders WHERE status = 1;
```

### 怎么走（概念）

```text
联合索引排序键是 (user_id, status, created_at)
只给 status，没有 user_id：
  → 无法从根目录精确落到「status=1」的连续一段
  → user_id=1 的 status=1、user_id=2 的 status=1……散落在各处
  → 优化器常放弃该联合索引，改全表扫或其他索引
```

```text
叶子里 status=1 并不连成一块：
(1, 0, ...)
(1, 1, ...)  ← status=1
(2, 1, ...)  ← status=1，但中间隔着别的 user_id 段
(3, 0, ...)
```

**结论：** 跳过最左列 → 这条联合索引通常**帮不上忙**（除非 Index Skip Scan 等特殊情况，入门先当不能用）。

若常按 `status` 查，应另建如 `idx_orders_status_created (status, created_at)`（shop_lab 已有类似索引）。

---

## SQL 5：最左有、中间断 —— 部分可用

```sql
SELECT * FROM orders
WHERE user_id = 1 AND created_at > '2026-01-01';
```

### 怎么走

```text
1. 能用最左 user_id=1 定位到该用户整段
2. status 没给等值 → 无法在索引里继续「收窄到某一 status」
3. 在 user_id=1 的叶子段上，对每条用 created_at 过滤（或优化器选择其他计划）
4. 再回表
```

```text
user_id=1 这一大段都要看：
(1, 0, 2026-01-10, ...)  ← 检查 created_at
(1, 1, 2026-01-11, ...)  ← 检查 created_at
(1, 1, 2026-01-18, ...)  ← 检查 created_at
```

**结论：** `user_id` 能用；`created_at` 因中间 `status`「断档」，不一定形成漂亮的索引范围扫描——**以 EXPLAIN 为准**。

更好匹配：索引改成 `(user_id, created_at, id)`（shop_lab 查询 A 的候选），或查询补上 `status`。

---

## 对照总表

| # | SQL 要点 | B+Tree 怎么走 | 最左前缀 | 回表？ |
|---|----------|---------------|----------|--------|
| 1 | `user_id=?` | 定段 + 扫叶子 | ✅ | `SELECT *` 通常要 |
| 2 | `user_id=? AND status=?` | 更窄定段 | ✅ | 通常要 |
| 3 | 再加 `created_at` 范围 + 覆盖列 | 范围扫叶子 | ✅ | 可能免 |
| 4 | 只有 `status=?` | 难定连续段 | ❌ | — |
| 5 | `user_id=? AND created_at>?`（跳 status） | 只用到 user_id 段 | 部分 | 通常要 |

---

## 和聚簇索引的一次完整对照

```sql
SELECT * FROM orders WHERE id = 6;
```

```text
只走聚簇索引 B+Tree
  → 叶子就是整行
  → 不回表
```

```sql
SELECT * FROM orders WHERE user_id = 1;
```

```text
二级索引 B+Tree → 得到主键
  → 聚簇索引 B+Tree → 取整行（回表）
```

---

## 一句话速记

- B+Tree：**目录在上面，有序数据在叶子，叶子可横向扫**
- 联合索引按列顺序拼成排序键 → **最左前缀**
- 二级索引找到主键后 → **回表**；列都在叶子里 → **覆盖**
- 记不住就对每条 SQL 画：「定到哪一段叶子 → 扫多远 → 要不要回表」

---

## 相关文件

- [day05.md](./day05.md)
- [innodb-clustered-secondary-index.md](./innodb-clustered-secondary-index.md)
- [mysql-pro-agent.md](./mysql-pro-agent.md)
- [labs/01_schema.sql](./labs/01_schema.sql)
