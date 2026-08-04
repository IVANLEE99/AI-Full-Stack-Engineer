# InnoDB 两种索引详解：聚簇索引 vs 二级索引

> 所属：7 天掌握 MySQL  
> 对应：[day05.md](./day05.md) 第 2 节「InnoDB 两种索引」  
> 对齐：[mysql-pro-agent.md](./mysql-pro-agent.md)（InnoDB clustered and secondary indexes）

---

## 0. 先读结论

InnoDB 两种核心索引都基于 B+Tree，关键区别在叶子节点：

| 类型 | 叶子节点存什么 |
|------|----------------|
| 聚簇索引（Clustered Index） | **整行数据** |
| 二级索引（Secondary Index） | **索引列值 + 主键值** |

因此：

- 按主键查通常一步到行（聚簇索引）
- 按二级索引查整行通常要回表

---

## 1. 聚簇索引（Clustered Index）

### 1.1 是什么

- InnoDB 每张表只有一棵聚簇索引树
- 通常由 `PRIMARY KEY` 承担
- 叶子页就是表数据行（data page）

没有显式主键时，InnoDB 会依次选择：

1. 第一个 `NOT NULL UNIQUE` 索引作为聚簇键
2. 都没有则生成隐藏 `ROW_ID`

### 1.2 结构直觉

```text
B+Tree（按主键有序）
      非叶子：主键区间目录
      叶子：主键 -> 整行
```

示意：

```text
id=1  -> {order_no=O..., user_id=1, status=1, ...}
id=2  -> {order_no=O..., user_id=2, status=0, ...}
```

### 1.3 为什么主键要“短且稳定”

| 建议 | 原因 |
|------|------|
| 主键尽量短 | 二级索引叶子都带主键，主键越长所有二级索引越胖 |
| 主键尽量稳定 | 改主键代价大，会影响聚簇与相关二级结构 |
| 常用自增 BIGINT | 插入顺序更友好，减少随机写与页分裂 |

---

## 2. 二级索引（Secondary Index）

### 2.1 是什么

- 非主键索引（普通索引、唯一索引等）
- 叶子保存：`(索引列, 主键)`
- 需要整行时，用叶子里的主键回聚簇索引再取行

### 2.2 结构直觉

假设：

```sql
KEY idx_orders_user_id (user_id)
```

叶子近似：

```text
user_id=1 -> id=101
user_id=1 -> id=205
user_id=2 -> id=88
```

查询：

```sql
SELECT * FROM orders WHERE user_id = 1;
```

步骤：

1. 走 `idx_orders_user_id` 找到主键集合（101, 205, ...）
2. 再按主键去聚簇索引取整行（回表）

---

## 3. 回表与覆盖索引

### 3.1 回表（table lookup）

当查询列不在二级索引叶子里时，必须回聚簇索引取整行。

`SELECT *` 往往会触发回表。

### 3.2 覆盖索引（covering index）

若查询需要的列都能从索引里拿到（含主键），可避免回表。

```sql
-- 索引 (user_id, status, created_at)，主键 id 默认为叶子附带
SELECT user_id, status, created_at, id
FROM orders
WHERE user_id = 1 AND status = 1
ORDER BY created_at DESC
LIMIT 10;
```

`EXPLAIN` 常见 `Extra: Using index`（仍需结合 `type/key/rows` 一起判断）。

---

## 4. 对照表（面试高频）

| 对比项 | 聚簇索引 | 二级索引 |
|--------|----------|----------|
| 数量 | 每表 1 棵 | 多棵 |
| 叶子内容 | 整行数据 | 索引列 + 主键 |
| 典型载体 | `PRIMARY KEY` | `KEY` / `UNIQUE KEY`（非主键） |
| 查到主键后 | 已是目标行 | 可能需回表 |
| 主键长度影响 | 聚簇树本身 | **所有二级索引体积** |

---

## 5. 查询路径示例（串起来看）

```sql
SELECT *
FROM orders
WHERE user_id = 1
ORDER BY id DESC
LIMIT 10;
```

可能路径：

```text
二级索引过滤 user_id
  ↓
拿到主键列表
  ↓
回聚簇索引取行
  ↓
排序/截断（是否可用索引顺序取决于索引设计）
```

结论：索引不是“给 WHERE 列都加一个”，而是围绕**完整访问路径**（过滤 + 排序 + 返回列）设计。

---

## 6. 写入代价（为什么不能乱建索引）

每新增一条索引都要付出：

- 更多磁盘占用
- 更多 Buffer Pool 占用
- `INSERT/UPDATE/DELETE` 维护成本上升

mysql-pro 要求：先有真实查询，再有索引；并用 `EXPLAIN` + 行数 + 耗时验证收益。

---

## 7. 常见误区

### 误区 1：主键越长越“业务友好”

错。主键太长会放大所有二级索引体积。

### 误区 2：按二级索引查一定快

不一定。若过滤选择性差 + 大量回表，可能仍慢。

### 误区 3：`SELECT *` 没影响

会破坏覆盖索引机会，增加回表成本。

### 误区 4：看到 `Using index` 就一定最优

仍要看 `rows`、`type`、`key` 是否合理，避免误判。

---

## 8. 实战建议（shop_lab）

1. `orders` 主键保持自增 `BIGINT UNSIGNED`
2. 为高频查询设计联合二级索引（如 `user_id, status, created_at`）
3. 列表查询先控制返回列，尽量争取覆盖索引
4. 每次改索引都保留改前改后 `EXPLAIN` 证据

---

## 9. 一句话速记

- 聚簇索引：叶子就是整行，一表一棵
- 二级索引：叶子是索引列 + 主键，查整行常要回表
- 主键短且稳定，二级索引才“轻”
- 索引优化要看完整访问路径与 `EXPLAIN`，不靠猜

---

## 相关文件

- [day05.md](./day05.md)
- [mysql-pro-agent.md](./mysql-pro-agent.md)
- [day03.md](./day03.md)
- [labs/01_schema.sql](./labs/01_schema.sql)

