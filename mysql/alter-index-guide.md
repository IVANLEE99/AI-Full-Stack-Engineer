# MySQL 如何改索引：ALTER 实战指南

> 所属：7 天掌握 MySQL  
> 对应：[day05.md](./day05.md) 第 7 节「为 shop_lab 设计索引」  
> 相关：[explain-orders-user-status-demo.md](./explain-orders-user-status-demo.md) · [innodb-clustered-secondary-index.md](./innodb-clustered-secondary-index.md)

---

## 0. 先读结论

MySQL **没有**「直接改索引列顺序」的语法。  
常见做法是：**删旧索引 → 建新索引**（或先建新名字、验证后再删旧的）。

| 场景 | 推荐做法 |
|------|----------|
| 本地 lab 练手 | 一条 `ALTER` 里 `DROP KEY` + `ADD KEY` |
| 生产 / 大表 | 先 `ADD` 新名 → `EXPLAIN` 对比 → 再 `DROP` 旧索引 |
| 报错 1061 Duplicate key name | 索引已存在，不必再 ADD；换名字或先删再建 |

---

## 1. 先看现在有什么

```sql
USE shop_lab;

SHOW INDEX FROM orders;

-- 或看建表语句
SHOW CREATE TABLE orders\G
```

确认：

- 索引名（同一表不能重复）
- 列顺序（左前缀原则）
- 是否已有你要建的组合

避免重复执行 `ADD KEY` 触发：

```text
ERROR 1061 (42000): Duplicate key name 'idx_orders_user_created'
```

`shop_lab` 建表时已有：

```text
KEY idx_orders_user_created (user_id, created_at, id)
KEY idx_orders_status_created (status, created_at)
```

所以 day05 练习里**不必再 ADD 同名索引**，直接 `EXPLAIN` 即可。

---

## 2. 三种常见改法

### 方式 A：删了再建（同名替换）

适合：要把 `idx_orders_user_created` 的列组合改掉。

```sql
ALTER TABLE orders
  DROP KEY idx_orders_user_created,
  ADD KEY idx_orders_user_created (user_id, status, created_at);
```

一条 `ALTER` 里写 `DROP` + `ADD`，比分两次稍省事。  
本地 lab 数据少，可以这样练。

### 方式 B：先建新索引，再删旧的（更稳）

适合：生产或大表，避免短暂没有可用索引。

```sql
-- 1. 先加新名字
ALTER TABLE orders
  ADD KEY idx_orders_user_status_created (user_id, status, created_at);

-- 2. EXPLAIN 对比，确认新索引更好

-- 3. 再删旧索引
ALTER TABLE orders
  DROP KEY idx_orders_user_created;
```

### 方式 C：只加不删（多索引并存）

适合：不同查询各用各的索引。

```sql
ALTER TABLE orders
  ADD KEY idx_orders_user_status_created (user_id, status, created_at);
```

已有 `idx_orders_user_created` 时**不要同名再 ADD**，用**新名字**。

---

## 3. 1061 报错怎么处理

`idx_orders_user_created` **已经存在**，不必再建。

若要改成 `(user_id, status, created_at)`：

```sql
ALTER TABLE orders
  DROP KEY idx_orders_user_created,
  ADD KEY idx_orders_user_created (user_id, status, created_at);
```

或保留旧的、另加新名（方式 B / C）。

---

## 4. 改完必须验证

```sql
EXPLAIN
SELECT *
FROM orders
WHERE user_id = 1 AND status = 1
ORDER BY created_at DESC
LIMIT 10;
```

重点看：

| 列 | 看什么 |
|----|--------|
| `key` | 是否用了预期索引 |
| `type` | 是否从 `ALL` 降到 `ref` / `range` |
| `rows` | 预估扫描行数是否下降 |
| `Extra` | 是否少了 `Using filesort`、是否出现 `Backward index scan` 等 |

**规则：** 有 EXPLAIN 证据再留新索引，别凭感觉。

延伸阅读：[explain-orders-user-status-demo.md](./explain-orders-user-status-demo.md)

---

## 5. 删除 / 重命名

```sql
-- 删索引
ALTER TABLE orders DROP KEY idx_orders_status_created;
```

MySQL 没有「只改列、保留名字」的语法；改列等价于 DROP + ADD：

```sql
ALTER TABLE orders
  DROP KEY old_name,
  ADD KEY new_name (user_id, created_at, id);
```

MySQL 8.0+ 支持**只改索引名、不改列**：

```sql
ALTER TABLE orders
  RENAME INDEX idx_orders_user_created TO idx_orders_user_created_v2;
```

改列仍要 `DROP KEY` + `ADD KEY`。

---

## 6. 注意点

| 点 | 说明 |
|----|------|
| 索引名唯一 | 同一表不能两个同名索引 |
| 写放大 | 索引越多，INSERT / UPDATE / DELETE 越慢 |
| 冗余索引 | `(user_id, created_at, id)` 与 `(user_id, status, created_at)` 可能重叠，要对比查询再留 |
| 大表 | 生产上 `ALTER` 可能锁表 / 耗时长，要评估维护窗口 |
| lab | `shop_lab` 数据少，随便练；改前改后都跑一遍 EXPLAIN |

---

## 7. day05 练习推荐流程

```sql
-- 1. 基线 EXPLAIN
EXPLAIN
SELECT id, order_no, status, created_at
FROM orders
WHERE user_id = 1
ORDER BY created_at DESC
LIMIT 10;

-- 2. 加候选索引（新名字，避免 1061）
ALTER TABLE orders
  ADD KEY idx_orders_user_status_created (user_id, status, created_at);

-- 3. 再 EXPLAIN，对比 key / rows / Extra

-- 4. 若新索引更好且旧的可删（可选）
ALTER TABLE orders DROP KEY idx_orders_user_created;
```

对比前后记录在笔记：**type / key / rows / Extra**。

---

## 8. 一句话总结

改索引 = `SHOW INDEX` 看清现状 → `DROP KEY` + `ADD KEY`（或新名并存）→ `EXPLAIN` 验证；同名索引已存在就换名字或先删再建。
