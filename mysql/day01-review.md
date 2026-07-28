# Day 01 · 5 分钟快速复习卡 ⏱️

> 用途：Day 1 学完后 5 分钟内快速回忆核心点。  
> 用法：先盖住答案自测第 8 节，答不出再回对应小节。  
> 配套：[day01.md](./day01.md) · [labs/01_schema.sql](./labs/01_schema.sql)

---

## 1. 基础概念（30 秒）

```text
database → table → row → column
表 ≈ 对象数组，行 ≈ 一个对象，列 ≈ 属性
```

| MySQL | 类比 |
|-------|------|
| database | 一个数据库 / 工作簿 |
| table | 一张表 / 数组集合 |
| row | 一行 / 一个对象 |
| column | 字段 / 对象属性 |

---

## 2. 查询四件套（1 分钟）

```sql
SELECT id, name FROM users        -- 要哪些列，从哪张表
WHERE  status = 1                 -- 过滤行
ORDER BY created_at DESC          -- 排序
LIMIT 10 OFFSET 20;               -- 取几条、跳几条
```

| 关键字 | 一句话 |
|--------|--------|
| `SELECT` | 要哪些字段 |
| `WHERE` | 筛哪些行 |
| `ORDER BY` | 怎么排（`ASC` 升 / `DESC` 降） |
| `LIMIT` / `OFFSET` | 取多少、跳多少 |

---

## 3. 建表要点（1 分钟）

```sql
id     BIGINT UNSIGNED AUTO_INCREMENT         -- 自增数字主键
email  VARCHAR(128) NOT NULL                  -- 变长字符串 + 非空
status TINYINT DEFAULT 1                      -- 小状态 + 默认值
created_at DATETIME(3) DEFAULT CURRENT_TIMESTAMP(3)
updated_at DATETIME(3) DEFAULT CURRENT_TIMESTAMP(3)
           ON UPDATE CURRENT_TIMESTAMP(3)     -- 改行自动刷新
PRIMARY KEY (id)                              -- 唯一身份
UNIQUE KEY uk_users_email (email)             -- 业务防重
) ENGINE=InnoDB CHARSET=utf8mb4               -- 事务引擎 + 全 Unicode
```

详解见 [create-table-users-explained.md](./create-table-users-explained.md)。

---

## 4. INSERT 对齐规则（30 秒）

> **列声明几个，VALUES 就给几个，一一对应。**

```sql
-- 有 DEFAULT / AUTO_INCREMENT 的列可省略
INSERT INTO users (email, name) VALUES ('a@x.com', 'A');

-- 报错 1136 (Column count doesn't match) = 列数 ≠ 值数
```

---

## 5. JOIN（1 分钟）

```sql
SELECT o.order_no, og.goods_name
FROM orders o
LEFT JOIN order_goods og ON og.order_id = o.id;
```

| JOIN | 记忆 |
|------|------|
| `INNER JOIN` | 两边都匹配才返回 |
| `LEFT JOIN` | 左边全保留，右边没有则 `NULL` |
| `RIGHT JOIN` | 右边全保留，左边没有则 `NULL` |

---

## 6. 索引（30 秒）

> 索引 = 书的目录，让查找更快，但不是越多越好。

- 经常出现在 `WHERE` / `JOIN ON` / `ORDER BY` 的列适合建
- 写多的表加太多索引会拖慢写入
- 唯一列用 `UNIQUE KEY`，普通列用普通索引

---

## 7. 危险操作（30 秒）⚠️

| 操作 | 后果 | 规则 |
|------|------|------|
| `DELETE` 无 `WHERE` | 清空所有行 | 先确认谓词 |
| `TRUNCATE` | 清空表、重置自增 | 仅本地 lab |
| `DROP` | 删表 / 库结构 | 先确认库名 |

详见 [truncate-drop-explained.md](./truncate-drop-explained.md)。

---

## 8. 一句话自测 ✅

盖住上面，试着回答：

1. `LEFT JOIN` 和 `INNER JOIN` 差在哪？
2. `updated_at` 为什么能在改行时自动变？
3. 报错 1136 怎么修？
4. 哪些字段适合加索引？
5. `TRUNCATE` 和 `DELETE` 的关键区别？

全答得出 → 今天过关。答不出 → 回对应小节看一眼即可。

---

## 9. 明日预告

Day 2：查询进阶——`WHERE` 复杂条件、`ORDER BY` 多列、`LIMIT` 分页、`GROUP BY` + 聚合函数（`COUNT` / `SUM` / `AVG`）。
