# `UNION` / `UNION ALL` 用法详解（PHP + MySQL）

> 场景：在 PHP 后端里把多条 `SELECT` 结果拼接成一个结果集  
> 目标：搞清语义差异、性能差异、排序分页写法、常见坑与实战模板

---

## 1. 先读结论

| 语法 | 行为 | 性能 |
|---|---|---|
| `UNION` | 合并后**去重** | 通常更慢（需要去重） |
| `UNION ALL` | 合并后**不去重** | 通常更快 |

默认建议：

- 不需要去重时，优先 `UNION ALL`
- 需要业务去重时，才用 `UNION`

---

## 2. 什么是 `UNION`

`UNION` 用于把多条 `SELECT` 的结果“竖向拼接”。

```sql
SELECT email AS contact, 'user' AS source FROM users
UNION
SELECT receiver_phone AS contact, 'address' AS source FROM order_addresses;
```

结果特点：

1. 两个结果集合并
2. 重复行会被去掉

---

## 3. 什么是 `UNION ALL`

`UNION ALL` 与 `UNION` 的差别只有一点：**不去重**。

```sql
SELECT email AS contact, 'user' AS source FROM users
UNION ALL
SELECT receiver_phone AS contact, 'address' AS source FROM order_addresses;
```

结果特点：

1. 两个结果集合并
2. 重复行保留

---

## 4. 两边 `SELECT` 必须满足的规则

### 4.1 列数必须一致

```sql
-- 正确：两边都 2 列
SELECT id, name FROM users
UNION ALL
SELECT id, sku FROM products;
```

### 4.2 对应列类型要兼容

- `INT` 对 `INT`、`VARCHAR` 对 `VARCHAR` 更稳
- 不一致时建议显式 `CAST`，避免隐式转换问题

```sql
SELECT CAST(id AS CHAR) AS item_id FROM users
UNION ALL
SELECT sku AS item_id FROM products;
```

### 4.3 列名以第一条 `SELECT` 为准

结果集列名继承第一条查询的别名。

---

## 5. 排序与分页正确写法

`UNION` / `UNION ALL` 合并后的全局排序与分页，应该写在最外层。

```sql
SELECT contact, source
FROM (
  SELECT email AS contact, 'user' AS source FROM users
  UNION ALL
  SELECT receiver_phone AS contact, 'address' AS source FROM order_addresses
) t
ORDER BY contact
LIMIT 20 OFFSET 0;
```

注意：

- 子查询内部的 `ORDER BY` 通常不保证最终顺序（除非配合 `LIMIT` 做局部裁剪）
- 最终展示顺序要靠外层 `ORDER BY`

---

## 6. 去重到底去什么

`UNION` 去重是按“整行”去重，不是按某一列。

例如：

```sql
SELECT 'a' AS x, 1 AS y
UNION
SELECT 'a' AS x, 2 AS y;
```

结果会保留 2 行，因为 `(a,1)` 与 `(a,2)` 不是同一行。

如果你想按单列去重，通常应：

1. 先 `UNION ALL`
2. 外层按目标列 `GROUP BY` 或 `DISTINCT`

---

## 7. `UNION` vs `OR`（什么时候不用 UNION）

很多场景可以写成一条 `SELECT ... WHERE ... OR ...`，也可以写成两条后 `UNION ALL`。

### 7.1 典型可替代示例

```sql
-- 写法 A：OR
SELECT id, order_no
FROM orders
WHERE status = 1 OR status = 2;

-- 写法 B：UNION ALL
SELECT id, order_no FROM orders WHERE status = 1
UNION ALL
SELECT id, order_no FROM orders WHERE status = 2;
```

经验：

- 简单条件优先 `OR`（可读性好）
- 当不同分支命中不同索引、且 `OR` 计划差时，可评估 `UNION ALL`
- 结论以 `EXPLAIN` 为准

---

## 8. PHP 里怎么写（PDO）

```php
<?php
declare(strict_types=1);

$sql = <<<SQL
SELECT contact, source
FROM (
  SELECT email AS contact, 'user' AS source
  FROM users
  WHERE status = :user_status

  UNION ALL

  SELECT receiver_phone AS contact, 'address' AS source
  FROM order_addresses
  WHERE receiver_phone IS NOT NULL
) t
ORDER BY contact
LIMIT :limit OFFSET :offset
SQL;

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':user_status', 1, PDO::PARAM_INT);
$stmt->bindValue(':limit', 20, PDO::PARAM_INT);
$stmt->bindValue(':offset', 0, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

要点：

- 用参数绑定，避免 SQL 注入
- `LIMIT/OFFSET` 也用整型绑定
- 排序放最外层，保证结果稳定

---

## 9. Yii2 查询构建思路（简写）

Yii2 中常见做法是先写原生 SQL（复杂 `UNION` 更直观），再用命令执行：

```php
$sql = "
SELECT contact, source FROM (
  SELECT email AS contact, 'user' AS source FROM users
  UNION ALL
  SELECT receiver_phone AS contact, 'address' AS source FROM order_addresses
) t
ORDER BY contact
";

$rows = Yii::$app->db->createCommand($sql)->queryAll();
```

在 Repository 层封装时，明确：

1. 方法返回粒度（一行代表什么）
2. 是否允许重复
3. 排序和分页是否稳定

---

## 10. 常见坑

### 10.1 把 `UNION` 当 `UNION ALL` 用

不需要去重却写了 `UNION`，白白增加去重成本。

### 10.2 两边列顺序不一致

`UNION` 按位置对齐，不按列名对齐。

```sql
-- 危险：列语义错位
SELECT id, name FROM users
UNION ALL
SELECT name, id FROM products;
```

### 10.3 用 `SELECT *`

两边表结构变化后容易出错。建议显式列出字段。

### 10.4 合并后不排序就分页

会导致分页不稳定、翻页重复/漏数据。必须加外层确定性 `ORDER BY`。

---

## 11. 性能建议

1. 能用 `UNION ALL` 就不要 `UNION`
2. 每个分支都只取必要列，减少 I/O
3. 每个分支 `WHERE` 条件对应索引
4. 大结果集分页尽量用 keyset（基于游标）而非大 offset
5. 用 `EXPLAIN` 验证每个分支与外层排序成本

---

## 12. 实战模板

### 12.1 合并两类通知（不去重）

```sql
SELECT id, title, created_at, 'order' AS type
FROM order_notifications
WHERE user_id = :uid

UNION ALL

SELECT id, title, created_at, 'system' AS type
FROM system_notifications
WHERE user_id = :uid
ORDER BY created_at DESC
LIMIT :limit OFFSET :offset;
```

### 12.2 合并后按业务键去重

```sql
SELECT biz_key, MAX(created_at) AS latest_time
FROM (
  SELECT biz_key, created_at FROM source_a
  UNION ALL
  SELECT biz_key, created_at FROM source_b
) t
GROUP BY biz_key;
```

---

## 13. 一句话速记

- `UNION` = 合并 + 去重
- `UNION ALL` = 合并，不去重（通常更快）
- 列数一致、类型兼容、顺序对齐
- 合并后排序分页写外层
- 性能判断看 `EXPLAIN`，不是凭感觉

