# 6.4 `EXISTS`（半连接，常优于大 `IN` 列表）

> 场景：PHP + MySQL 查询优化  
> 目标：理解 `EXISTS` 的语义、和 `IN` 的区别、在 Repository/Service 层如何安全落地

---

## 1. `EXISTS` 是什么

`EXISTS` 用于判断「子查询是否至少返回 1 行」。

- 返回行数 > 0：条件为 `TRUE`
- 返回 0 行：条件为 `FALSE`

它不关心子查询里具体选了什么列，通常写 `SELECT 1` 即可。

```sql
SELECT u.id, u.name
FROM users u
WHERE EXISTS (
  SELECT 1
  FROM orders o
  WHERE o.user_id = u.id
);
```

含义：只查「下过单」的用户。

---

## 2. 为什么叫“半连接（Semi Join）”

上面 SQL 的结果只返回 `users` 的行，不会把 `orders` 的列拼出来。

可以理解为：

- `INNER JOIN`：把两表“拼接”出结果行
- `EXISTS`：只判断“右表是否存在匹配”

所以常说 `EXISTS` 是“半连接”语义：只用右表做存在性判断，不扩展输出列。

---

## 3. `EXISTS` vs `IN`（重点）

## 3.1 语义对照

```sql
-- EXISTS 写法
SELECT u.id
FROM users u
WHERE EXISTS (
  SELECT 1
  FROM orders o
  WHERE o.user_id = u.id
);

-- IN 写法
SELECT u.id
FROM users u
WHERE u.id IN (
  SELECT o.user_id
  FROM orders o
);
```

在很多场景下两者结果一致。

## 3.2 什么时候 `EXISTS` 常更稳

- 子查询结果集很大（大 `IN` 列表）
- 只需要“是否存在”，不需要子查询返回值本身
- 子查询与外层有相关条件（`o.user_id = u.id`）

原因（直观理解）：

- `EXISTS`：匹配到一行即可“短路”
- 大 `IN`：可能需要构造/处理更大的候选集合

> MySQL 8 优化器会对 `IN` / `EXISTS` 做改写，最终谁更快要以 `EXPLAIN` 为准。  
> 但“存在性判断优先考虑 `EXISTS`”是很实用的经验法则。

---

## 4. `NOT EXISTS`（反连接）

查“没有子记录”的场景非常常见：

```sql
SELECT u.id, u.name
FROM users u
WHERE NOT EXISTS (
  SELECT 1
  FROM orders o
  WHERE o.user_id = u.id
);
```

含义：找没下过单的用户。

这通常比 `NOT IN` 更安全，尤其当子查询字段可能出现 `NULL` 时。

---

## 5. `NOT IN` 的 `NULL` 坑（高频面试点）

```sql
-- 假设子查询中出现 NULL
WHERE u.id NOT IN (SELECT user_id FROM orders);
```

如果 `orders.user_id` 里有 `NULL`，`NOT IN` 结果可能“全不匹配”或出现你不期望的行为（受三值逻辑影响）。

规避方式：

1. 优先用 `NOT EXISTS`
2. 或在 `NOT IN` 子查询里明确排除 `NULL`

```sql
WHERE u.id NOT IN (
  SELECT user_id
  FROM orders
  WHERE user_id IS NOT NULL
);
```

---

## 6. PHP 里怎么用（PDO / Yii2）

## 6.1 PDO 参数化（推荐）

```php
<?php
declare(strict_types=1);

$sql = <<<SQL
SELECT u.id, u.name
FROM users u
WHERE EXISTS (
  SELECT 1
  FROM orders o
  WHERE o.user_id = u.id
    AND o.status = :status
)
SQL;

$stmt = $pdo->prepare($sql);
$stmt->execute(['status' => 1]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

要点：

- 用绑定参数，避免 SQL 注入
- 相关条件放子查询里，语义更清晰

## 6.2 Yii2 Query Builder 示例

```php
$subQuery = (new \yii\db\Query())
    ->select('1')
    ->from(['o' => 'orders'])
    ->where('o.user_id = u.id')
    ->andWhere(['o.status' => 1]);

$rows = (new \yii\db\Query())
    ->select(['u.id', 'u.name'])
    ->from(['u' => 'users'])
    ->where(['exists', $subQuery])
    ->all();
```

---

## 7. 索引建议（决定上限）

`EXISTS` 快不快，核心看关联列索引。

示例中至少要有：

- `orders(user_id)`
- 若还按状态过滤，可考虑复合索引：`orders(user_id, status)`

没有索引时，`EXISTS` 也可能慢。

---

## 8. `EXPLAIN` 怎么看

建议每次都做证据化验证：

```sql
EXPLAIN
SELECT u.id
FROM users u
WHERE EXISTS (
  SELECT 1
  FROM orders o
  WHERE o.user_id = u.id
);
```

重点观察：

- 是否使用了预期索引（`key`）
- 扫描行数（`rows`）是否合理
- 是否出现全表扫描导致放大成本

---

## 9. 何时用哪种写法（实战决策）

| 需求 | 推荐 |
|---|---|
| 判断是否有子记录 | `EXISTS` |
| 判断是否无子记录 | `NOT EXISTS` |
| 明确是小集合常量列表（如 1,2,3） | `IN` |
| 需要拼出右表字段 | `JOIN` |

---

## 10. 一句话速记

- `JOIN` 是“拼表”，`EXISTS` 是“判存在”
- 大多数存在性判断，先想 `EXISTS / NOT EXISTS`
- `NOT IN` 遇到 `NULL` 要格外小心
- 性能结论以 `EXPLAIN` 为准，不靠猜

