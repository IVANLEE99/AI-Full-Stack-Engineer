# EXPLAIN 实战精读：用户订单列表查询

> 所属：7 天掌握 MySQL  
> 对应：[day05.md](./day05.md) 第 6 节「EXPLAIN 速查」  
> 相关：[innodb-clustered-secondary-index.md](./innodb-clustered-secondary-index.md) · [btree-day05-walkthrough.md](./btree-day05-walkthrough.md)

---

## 0. 查询与 EXPLAIN 原文

```sql
EXPLAIN
SELECT *
FROM orders
WHERE user_id = 1 AND status = 1
ORDER BY created_at DESC
LIMIT 10;
```

| id | select_type | table | partitions | type | possible_keys | key | key_len | ref | rows | filtered | Extra |
|----|-------------|-------|------------|------|---------------|-----|---------|-----|------|----------|-------|
| 1 | SIMPLE | orders | NULL | ref | idx_orders_user_created,idx_orders_status_created | idx_orders_user_created | 8 | const | 3 | 42.86 | Using where; Backward index scan |

---

## 1. 先看表上真实索引（shop_lab）

来自 [labs/01_schema.sql](./labs/01_schema.sql)：

```text
PRIMARY KEY (id)                          -- 聚簇索引，整行在这里
KEY idx_orders_user_created (user_id, created_at, id)
KEY idx_orders_status_created (status, created_at)
```

**没有** `(user_id, status, created_at)`。  
所以本例实际选用的是 `idx_orders_user_created`。

---

## 2. 一句话总结这份计划

> 用二级索引按 `user_id=1` 定位，再**反向**扫该用户的 `created_at` 顺序；  
> `status=1` 在扫到的行上再过滤；最后按主键回表取 `SELECT *` 整行；最多 10 条。

**入门结论：这是一份健康、合理的执行计划。**

---

## 3. 逐列解读

| 列 | 值 | 含义 |
|----|-----|------|
| **id** | `1` | 第 1 个（也是唯一）查询块 |
| **select_type** | `SIMPLE` | 简单查询，无子查询 / UNION |
| **table** | `orders` | 访问的表 |
| **partitions** | `NULL` | 未分区 |
| **type** | `ref` | 用非唯一索引做等值查找（`user_id = const`），优于 `ALL` / 纯 `index` |
| **possible_keys** | 两个 idx | 优化器认为这两个都可能用 |
| **key** | `idx_orders_user_created` | **实际选用**的索引 |
| **key_len** | `8` | 查找只用了索引前缀 **8 字节** → `user_id`（BIGINT） |
| **ref** | `const` | `user_id` 与常量 `1` 比较 |
| **rows** | `3` | 估计索引侧约命中 / 涉及 **3** 行（`user_id=1`） |
| **filtered** | `42.86` | 估计其中约 **42.86%** 还能通过剩余 WHERE（主要是 `status=1`） |
| **Extra** | 见下节 | 附加行为 |

---

## 4. Extra 拆开

```text
Using where; Backward index scan
```

| 片段 | 含义 |
|------|------|
| **Using where** | 索引定位后还要用 WHERE 再滤（这里主要是 **`status = 1`**；选用索引里没有 status 参与 `ref` 查找） |
| **Backward index scan** | 按索引 **反向**扫描，匹配 `ORDER BY created_at DESC`，减少/避免 filesort |

本结果中**没有**：

| 没有出现 | 说明 |
|----------|------|
| `Using index` | **不是覆盖索引**（`SELECT *` 必须回表） |
| `Using filesort` | 排序很可能靠索引逆序扫完成，较好 |
| `Using temporary` | 未建临时表做排序/去重 |

---

## 5. 执行路径（按时间顺序）

```text
1. 选中 idx_orders_user_created (user_id, created_at, id)

2. type=ref, key_len=8
   → 只按 user_id = 1 定到叶子上的一段

3. Backward index scan
   → 在这段叶子上按 created_at 从大到小扫
   （同一 user_id 下 created_at 在索引中有序）

4. Using where
   → 每条检查 status = 1，不满足就丢

5. SELECT *
   → 用叶子里的主键 id 回聚簇索引取整行

6. LIMIT 10
   → 凑够 10 条（或扫完该段）就停
```

示意：

```text
二级索引叶子（user_id=1 段，树上按 created_at 升序）
... → (1, 旧时间, id) → (1, 较新, id) → (1, 最新, id)

Backward scan：从「最新」往「旧」走
每走一条：status==1？ → 是则回表取整行 → 计入结果
到 10 条或段结束
```

---

## 6. 关键数字怎么理解

### 6.1 `key_len = 8`

联合索引 `(user_id, created_at, id)` 若多列都用于**查找条件**，`key_len` 会更大。  
现在只有 **8**，说明 **等值查找只用了 `user_id`**。

`created_at` 仍然有用：服务于 **ORDER BY 方向上的索引扫描**，不是 `ref` 的等值条件。

### 6.2 `rows = 3` 与 `filtered = 42.86`

粗算：估计最终约 `3 × 42.86% ≈ 1.3` 行量级通过 `status` 过滤。

注意：`rows` / `filtered` 都是**估计值**，数据少时波动很正常。  
入门阶段优先看：是否选对索引、有没有 filesort、type 是否合理。

### 6.3 为什么不选 `idx_orders_status_created`？

该索引是 `(status, created_at)`：

- 能很好匹配 `status = 1`
- 但不能按 `user_id` 收窄
- 也难同时很好服务「某用户 + 按时间倒序」

优化器判断：先按 `user_id` 缩到很少几行，再滤 `status`、按时间倒序，更合适 → 选用 `idx_orders_user_created`。

---

## 7. 这份计划好不好？

| 项 | 评价 |
|----|------|
| `type=ref` | 好，不是全表扫 |
| 用上 user 侧索引 | 好 |
| `Backward index scan` | 好，`ORDER BY DESC` 吃到了索引顺序 |
| 无 `Using filesort` | 好 |
| `Using where` 滤 status | 可接受；数据量小时无妨 |
| `SELECT *` 回表 | 正常；列多/行多时回表会变贵 |

---

## 8. 若想更贴合这条 SQL（可选）

当前访问路径：

```text
user_id = ? AND status = ? ORDER BY created_at DESC
```

更贴的候选索引：

```sql
KEY idx_orders_user_status_created (user_id, status, created_at)
```

可能带来：

- `user_id + status` 都进入索引查找（`key_len` 更大）
- `ORDER BY created_at DESC` 仍可能 backward scan
- `Using where` 可能减轻

是否真更好，必须用**改前后 EXPLAIN + 真实数据量**对比（mysql-pro：有证据再改索引）。

列表若只需部分列，可避免 `SELECT *`，争取覆盖索引（`Extra: Using index`）。

---

## 9. 和 day05 知识点的对应

| 概念 | 在本 EXPLAIN 中的体现 |
|------|----------------------|
| 二级索引 | `key = idx_orders_user_created` |
| 回表 | `SELECT *` 且无 `Using index` |
| 最左前缀 | `key_len=8` → 查找只用到 `user_id` |
| 排序与索引 | `Backward index scan` 服务 `ORDER BY created_at DESC` |
| 优化器选择 | `possible_keys` 有两个，最终选 user 侧索引 |

---

## 10. 一句话速记

| 观察 | 结论 |
|------|------|
| 用了哪个索引 | `idx_orders_user_created (user_id, created_at, id)` |
| 等值用到哪一列 | 主要是 `user_id`（`key_len=8`） |
| status 怎么办 | 索引扫到后 `Using where` 过滤 |
| 排序怎么办 | `Backward index scan`，基本免 filesort |
| 取整行怎么办 | `SELECT *` → 回表聚簇索引 |

**这不是索引失效，而是：用 user+时间索引做定位和倒序扫描，再用 where 滤状态。**

---

## 相关文件

- [day05.md](./day05.md)
- [btree-day05-walkthrough.md](./btree-day05-walkthrough.md)
- [innodb-clustered-secondary-index.md](./innodb-clustered-secondary-index.md)
- [labs/01_schema.sql](./labs/01_schema.sql)
- [mysql-pro-agent.md](./mysql-pro-agent.md)
