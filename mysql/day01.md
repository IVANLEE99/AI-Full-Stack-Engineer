# Day 01：环境、库表概念与 CRUD

> 所属：7 天掌握 MySQL  
> 类型：概念入门 + 手敲  
> 建议时长：3–4h  
> 对应路线：阶段 1（数据库理论 + MySQL 基础）

---

## 今日目标

装好 MySQL、理解库/表/行/列/主键/约束，并独立完成增删改查。

**今日核心句：**

> 关系型数据库用「表 + 关系 + 约束」保证结构化数据的正确性；CRUD 是一切业务 SQL 的底座，先写对，再谈快。

---

## 0. 今日路线

1. 确认 MySQL 可连接
2. 理解 DBMS / SQL / 表结构
3. 学会 DDL：建库、建表、改表
4. 学会 DML：INSERT / UPDATE / DELETE
5. 学会 DQL：基础 SELECT
6. 理解主键、唯一、非空、默认值
7. 导入 `labs` 实验库
8. 完成练习与自测

---

## 1. 概念地图

| 概念 | 一句话 |
|------|--------|
| 数据库 Database | 一组相关表的容器 |
| DBMS | 管理数据库的软件，如 MySQL |
| 表 Table | 二维结构：行 = 记录，列 = 字段 |
| 主键 PK | 唯一标识一行，非空 |
| 外键 FK | 引用另一表主键，表达关联（生产可逻辑外键） |
| 约束 | 规则：唯一、非空、检查、默认值 |
| SQL | 与数据库对话的语言 |

**SQL 四类（先记名字）：**

| 类别 | 作用 | 常见语句 |
|------|------|----------|
| DDL | 定义结构 | `CREATE` `ALTER` `DROP` |
| DML | 改数据 | `INSERT` `UPDATE` `DELETE` |
| DQL | 查数据 | `SELECT` |
| DCL | 权限 | `GRANT` `REVOKE`（本周了解即可） |

**关系型 vs 非关系型：**

- MySQL：表关联、事务、强约束 → 订单/账务/用户主数据
- Redis：KV/缓存/计数 → 热点读、会话、限流（**真相源仍是 MySQL**）

---

## 2. 连接与基本命令

```sql
-- 查看版本
SELECT VERSION();

-- 看有哪些库
SHOW DATABASES;

-- 创建并使用库
CREATE DATABASE IF NOT EXISTS shop_lab
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE shop_lab;

-- 看当前库的表
SHOW TABLES;
```

字符集记住一条：**业务表统一 `utf8mb4`**，避免 emoji 与部分中文问题。

---

## 3. 建表：从用户表开始

```sql
CREATE TABLE users (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  email         VARCHAR(128)    NOT NULL COMMENT '登录邮箱',
  name          VARCHAR(64)     NOT NULL COMMENT '昵称',
  status        TINYINT         NOT NULL DEFAULT 1 COMMENT '1正常 0禁用',
  created_at    DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at    DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
                  ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='用户表';
```

**逐字段详解（类型、约束、引擎、字符集）：**  
→ [create-table-users-explained.md](./create-table-users-explained.md)

**字段类型选择（Day 1 最小集）：**

| 场景 | 推荐 |
|------|------|
| 主键 | `BIGINT UNSIGNED` + 自增，或业务雪花 ID |
| 金额 | `DECIMAL(12,2)`，**禁止 float/double 存钱** |
| 状态枚举 | `TINYINT` + 注释，或 `ENUM`（团队规范统一即可） |
| 短字符串 | `VARCHAR(n)` |
| 时间 | `DATETIME(3)` 或 `TIMESTAMP`（注意时区策略） |
| 是否删除 | `is_deleted` / `deleted_at`（软删要写进约定） |

**存储引擎：** 业务表默认 **InnoDB**（事务、行锁、崩溃恢复）。MyISAM 仅了解历史即可。

---

## 4. CRUD 手敲

### 4.1 INSERT

```sql
INSERT INTO users (email, name, status)
VALUES ('tom@example.com', 'Tom', 1);

INSERT INTO users (email, name)
VALUES
  ('alice@example.com', 'Alice'),
  ('bob@example.com', 'Bob');
```

### 4.2 SELECT

```sql
SELECT id, email, name, status
FROM users
WHERE status = 1
ORDER BY id DESC
LIMIT 10;
```

原则：

- 需要什么字段选什么字段，少用 `SELECT *`（尤其大表与线上）
- 过滤用 `WHERE`，排序用 `ORDER BY`，截断用 `LIMIT`

### 4.3 UPDATE

```sql
-- 先预览再更新（安全习惯）
SELECT id, name, status FROM users WHERE email = 'bob@example.com';

UPDATE users
SET status = 0, name = 'Bob-Disabled'
WHERE email = 'bob@example.com'
LIMIT 1;
```

**铁律：** 生产更新必须有足够窄的 `WHERE`；先 `SELECT` 预览影响行数。

### 4.4 DELETE

```sql
SELECT id FROM users WHERE email = 'bob@example.com';

DELETE FROM users
WHERE email = 'bob@example.com'
LIMIT 1;
```

软删常见写法（业务更常用）：

```sql
UPDATE users SET status = 0 WHERE id = 3 LIMIT 1;
```

`TRUNCATE` / `DROP`：结构级破坏操作，本教程仅在本地实验库使用，且先确认库名。

**详解（DELETE vs TRUNCATE vs DROP、确认库名、本地安全练习）：**  
→ [truncate-drop-explained.md](./truncate-drop-explained.md)

---

## 5. 约束：正确性的第一道防线

```sql
-- 主键：唯一 + 非空
-- UNIQUE：业务唯一键，如 email、order_no
-- NOT NULL：必填
-- DEFAULT：缺省值

-- 演示唯一冲突
INSERT INTO users (email, name) VALUES ('tom@example.com', 'Dup');
-- 期望：Duplicate entry ... for key 'uk_users_email'
```

应用层校验 **不能替代** 数据库唯一约束。并发下只有库约束真正防重。

---

## 6. 导入实验库

在项目根或 `mysql/` 下执行：

```bash
mysql -u root -p < labs/01_schema.sql
mysql -u root -p shop_lab < labs/02_seed.sql
```

验证：

```sql
USE shop_lab;
SHOW TABLES;
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM orders;
SELECT COUNT(*) FROM order_items;
```

---

## 7. 与 ORM 的关系（预告）

手写 SQL 是底座。框架里常见等价：

```text
SELECT ... WHERE id = ?
  ≈ Yii2: Order::find()->where(['id' => $id])->one()
  ≈ Sequelize: Order.findOne({ where: { id } })
```

本教程前 5 天以 SQL 为主；读 week03 时再映射到 ActiveRecord / Repository。

---

## 8. 今日练习

在 `shop_lab` 中完成（可写在笔记本或 `labs/03_practice.sql` 的 Day1 区）：

1. 新建一张 `tags` 表：`id`, `name`(唯一), `created_at`
2. 插入 3 条标签
3. 查询所有标签按 `id` 倒序
4. 把其中一条 `name` 改掉
5. 删除一条标签
6. 故意插入重复 `name`，观察唯一索引报错

---

## 9. 自测清单

- [ ] 能解释 Database / Table / Row / Column / PK
- [ ] 能区分 DDL / DML / DQL
- [ ] 能独立 `CREATE TABLE`（含主键、唯一、默认时间）
- [ ] 更新/删除前会先 `SELECT` 预览
- [ ] 金额会想到 `DECIMAL`，字符集会想到 `utf8mb4`
- [ ] 实验库 `shop_lab` 已导入成功

---

## 10. 5 行复盘

```text
今天最清楚：
今天最卡：
我手敲过的语句数：
与业务的联系：
明天预习：过滤、排序、分页、GROUP BY
```

## 11. AI Review 提问

```text
请检查我 Day1 的建表 SQL：类型、主键、唯一约束、字符集、时间字段是否合理？
有哪些会在生产踩坑的默认值/NULL 问题？
```

---

## 下一步

→ [Day 02：查询进阶](./day02.md)
