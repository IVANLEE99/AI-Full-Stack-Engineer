# `TRUNCATE` / `DROP` 详解

> 对应：[day01.md](./day01.md) §4.4 中的提醒  
> 原文要点：`TRUNCATE` / `DROP` 是**结构级破坏操作**，本教程仅在本地实验库使用，且先确认库名。

---

## 0. 一句话

| 语句 | 干什么 |
|------|--------|
| `DELETE` | 按条件删**行**（表还在） |
| `TRUNCATE TABLE` | **清空整表数据**（表结构还在） |
| `DROP TABLE` | **拆掉整张表** |
| `DROP DATABASE` | **拆掉整个库** |

> **`DELETE` 删行；`TRUNCATE` 清空表数据；`DROP` 拆掉表/库。**  
> 后两者是重型操作：只在本地 `shop_lab` 练手，执行前先确认当前库名。

---

## 1. 各自干什么

| 语句 | 类别直觉 | 作用 | 表还在吗？ |
|------|----------|------|------------|
| `DELETE FROM users WHERE ...` | DML | 按条件删行 | 在，结构不变 |
| `TRUNCATE TABLE users` | 更接近 DDL | 清空整表数据 | 在，列/索引还在，数据没了 |
| `DROP TABLE users` | DDL | 删掉整张表 | 不在 |
| `DROP DATABASE shop_lab` | DDL | 删掉整个库 | 库没了 |

### 1.1 示例

```sql
-- 只删符合条件的行（可带 WHERE；InnoDB 事务内通常可回滚）
DELETE FROM users WHERE email = 'bob@example.com';

-- 清空 users 全部数据，表结构保留
TRUNCATE TABLE users;

-- 连表带数据一起毁掉
DROP TABLE users;

-- 整个库毁掉
DROP DATABASE shop_lab;
```

### 1.2 可选语法细节（了解即可）

```sql
TRUNCATE TABLE users;
-- 有的客户端也接受 TRUNCATE users;

DROP TABLE IF EXISTS users;          -- 不存在也不报错（建库脚本常用）
DROP DATABASE IF EXISTS shop_lab;
```

---

## 2. 为什么叫「结构级破坏」

「结构级」相对「只动几行数据」而言：`TRUNCATE` / `DROP` 动的是**整表数据或对象本身**，不是业务里常见的「删一条用户」。

| | `DELETE` | `TRUNCATE` | `DROP` |
|--|----------|------------|--------|
| 影响范围 | 行（可 `WHERE` / `LIMIT`） | 全表数据 | 表或库对象本身 |
| 能否 `WHERE` | 能 | **不能** | 不适用 |
| 事务回滚 | InnoDB 下通常可 | 多数场景**不能**当普通 DELETE 回滚 | DDL，难当行级撤销 |
| 自增 `AUTO_INCREMENT` | 一般不重置 | 常会**重置**为起始值 | 表没了 |
| 触发器 / 逐行开销 | 可能逐行、可触发器 | 通常整表处理，更快 | 直接移除对象 |
| 恢复 | 靠备份 / binlog / 事务 | 全表数据没了，靠备份 | 结构+数据都靠备份 |

因此 Day01 把它们和日常 `DELETE`、软删分开讲。

---

## 3. `DELETE` vs `TRUNCATE` 怎么选

| 场景 | 更合适 |
|------|--------|
| 删某个用户、某批订单 | `DELETE ... WHERE ...`（先 `SELECT` 预览） |
| 业务「禁用账号」但仍要留痕 | 软删：`UPDATE ... SET status = 0` |
| 本地练手，表数据弄乱了，想整表清空重灌 | `TRUNCATE TABLE ...` |
| 表设计错了，整张不要了 | `DROP TABLE ...` 再 `CREATE` |
| 实验库不要了 | `DROP DATABASE shop_lab`（再确认库名！） |

**生产原则（mysql-pro 对齐）：**

- 禁止无 `WHERE` 的宽 `DELETE` / `UPDATE` 当「清空」随便跑  
- `TRUNCATE` / `DROP` 视为高风险变更：备份、窗口、权限、双人确认  
- 应用账号通常**不应**有 `DROP` / `TRUNCATE` 权限  

---

## 4. 「仅在本地实验库使用」

意思是：

1. 在 `shop_lab` 这类**自己建的练习库**里，清空重来可以  
2. **不要**在公司生产库、共享测试库、系统库（如 `mysql`）上试  
3. 本机也要确认当前库是不是你以为的那个  

```sql
SELECT DATABASE();           -- 当前库名？期望 shop_lab
SHOW TABLES;
SELECT COUNT(*) FROM users;  -- 心里有数再动手
```

系统库点名（**永远不要当练习靶**）：

- `mysql`
- `sys`
- `performance_schema`
- `information_schema`（只读元数据，不能当业务库乱 drop）

---

## 5. 「先确认库名」

典型事故：

```sql
USE mysql;                 -- 系统库！
DROP TABLE ...;            -- 灾难

-- 或
DROP DATABASE production_orders;
```

习惯清单：

1. 看客户端是否显示当前库，或执行 `SELECT DATABASE();`
2. 破坏语句尽量带库名限定（仍要确认写对）：

```sql
TRUNCATE TABLE shop_lab.tags;
DROP TABLE IF EXISTS shop_lab.tags;
```

3. 生产变更走审批、备份、预览，不靠手滑  
4. 执行前再读一遍：对象名、库名、是 `TRUNCATE` 还是 `DROP`

---

## 6. 和 soft delete / Day01 CRUD 对照

```sql
-- 业务更常见：标记禁用，行还在
UPDATE users SET status = 0 WHERE id = 3 LIMIT 1;

-- 物理删一行（可条件、可预览）
SELECT id FROM users WHERE email = 'bob@example.com';
DELETE FROM users WHERE email = 'bob@example.com' LIMIT 1;

-- 整表清空（实验重置）
USE shop_lab;
TRUNCATE TABLE tags;

-- 表不要了
DROP TABLE IF EXISTS tags;

-- 库不要了（最重）
-- DROP DATABASE shop_lab;   -- 三思；确认不是生产库
```

Day01 推荐路径：

```text
日常删改 → DELETE / UPDATE（窄 WHERE）
业务下线 → 软删 status / deleted_at
练手重置 → TRUNCATE（本地 shop_lab）
表设计重来 → DROP TABLE + CREATE
```

---

## 7. 本地安全练习（推荐）

只动练习表，不动 `users` / `orders` 等若你还要接着做 Day02：

```sql
USE shop_lab;

-- 练习用 tags（Day01 练习若已建）
CREATE TABLE IF NOT EXISTS tags (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) NOT NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  UNIQUE KEY uk_tags_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tags (name) VALUES ('sql'), ('mysql'), ('index');
SELECT * FROM tags;

TRUNCATE TABLE tags;          -- 数据没了，表还在
SELECT COUNT(*) FROM tags;  -- 0

INSERT INTO tags (name) VALUES ('sql');  -- 自增 id 往往从 1 再起（实现相关，可观察）

DROP TABLE IF EXISTS tags;    -- 表也没了
SHOW TABLES;
```

若误 `TRUNCATE` 了还要 seed 的业务表，可重新导入：

```bash
mysql -u root -p shop_lab < labs/02_seed.sql
# 若表也被 DROP，需先 01_schema.sql 再 02_seed.sql
```

---

## 8. 权限与运维侧（扩展）

```sql
-- 查看当前用户（概念）
SELECT USER(), CURRENT_USER();
```

| 点 | 说明 |
|----|------|
| 应用账号 | 最小权限：`SELECT/INSERT/UPDATE/DELETE`，无 `DROP`/`TRUNCATE` |
| 迁移账号 | 才可能有 DDL |
| 备份 | `DROP`/`TRUNCATE` 之后，没有备份就很难恢复 |

对齐 [mysql-pro-agent.md](./mysql-pro-agent.md)：`DROP`、`TRUNCATE`、无谓词批量删改 = 高风险。

---

## 9. 常见误解

| 误解 | 更正 |
|------|------|
| `TRUNCATE` 就是 `DELETE` 不写 WHERE | 语义和实现都不同：更快、通常不回滚、常重置自增 |
| `DROP` 只删数据 | `DROP TABLE` 连结构一起没 |
| 本地随便 drop 系统库试试 | 可能搞坏本机 MySQL 账号与元数据 |
| 清空后数据一定能 Ctrl+Z | 没有事务/备份就没有撤销 |

---

## 10. 速查卡

```text
DELETE   → 删行，可 WHERE，业务默认手段（或软删）
TRUNCATE → 清空表数据，表还在，本地重置用
DROP     → 删表或删库，对象级销毁

执行前：
  1. SELECT DATABASE();
  2. SHOW TABLES; / 确认对象名
  3. 是否本地 shop_lab？
  4. 有无备份 / 能否重建（labs 脚本）？
```

---

## 11. 相关文档

- [day01.md](./day01.md) — CRUD 与约束  
- [create-table-users-explained.md](./create-table-users-explained.md) — 建表语句详解  
- [labs/01_schema.sql](./labs/01_schema.sql) / [02_seed.sql](./labs/02_seed.sql) — 误清后重建  
- [mysql-pro-agent.md](./mysql-pro-agent.md) — 安全与破坏性操作规则  
