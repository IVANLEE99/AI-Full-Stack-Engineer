# `CREATE TABLE users` 逐段详解

> 所属：7 天掌握 MySQL  
> 对应：[day01.md](./day01.md) 建表示例  
> 相关：[labs/01_schema.sql](./labs/01_schema.sql) · [mysql-pro-agent.md](./mysql-pro-agent.md)

---

## 完整语句

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

---

## 1. 整句在干什么

| 部分 | 含义 |
|------|------|
| `CREATE TABLE users` | 创建一张名叫 `users` 的表 |
| `( ... )` | 列定义 + 表级约束 |
| `ENGINE=... CHARSET=...` | 存储引擎与字符集等表选项 |
| 末尾 `;` | 语句结束 |

执行成功后：库里多一张空表，结构固定；之后 `INSERT` 必须符合这些类型和约束。

关键字大小写不敏感：`create table` 与 `CREATE TABLE` 等价。表名/列名建议统一小写，避免 Linux 上大小写敏感问题。

---

## 2. 列定义通式

每一列大致是：

```text
列名  数据类型  [NULL|NOT NULL]  [DEFAULT 默认值]  [AUTO_INCREMENT]  [COMMENT '注释']
```

| 片段 | 作用 |
|------|------|
| 列名 | 字段名，建议小写+下划线 |
| 数据类型 | 能存什么、占多少空间、比较规则 |
| `NOT NULL` | 不允许 `NULL` |
| `DEFAULT` | 插入时不写该列时用的默认值 |
| `AUTO_INCREMENT` | 插入时不写则自动生成递增整数（通常配主键） |
| `COMMENT` | 元数据注释，方便人和工具阅读，**不参与业务逻辑** |

---

## 3. 逐列说明

### 3.1 `id`

```sql
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键'
```

| 属性 | 含义 | 为何这样选 |
|------|------|------------|
| `BIGINT` | 8 字节整数，范围很大 | 用户量长期增长，比 `INT` 更宽裕 |
| `UNSIGNED` | 无符号，只存 ≥0 | id 不需要负数，上限大约翻倍 |
| `NOT NULL` | 不能为空 | 主键必须有值 |
| `AUTO_INCREMENT` | 省略 id 时自动 +1 | 插入省事，保证有唯一编号 |
| `COMMENT '主键'` | 说明 | 文档化 |

**和后面的关系：** 表末尾 `PRIMARY KEY (id)` 把 `id` 定为主键；InnoDB 下主键通常是**聚簇索引**（数据按主键顺序组织）。

**注意：**

- 自增值在回滚/删行后**可能出现空洞**，不要假设 id 连续。
- 不要把「业务编号」和自增主键强行绑死；业务唯一用 `email` 等字段 + `UNIQUE`。

---

### 3.2 `email`

```sql
email VARCHAR(128) NOT NULL COMMENT '登录邮箱'
```

| 属性 | 含义 |
|------|------|
| `VARCHAR(128)` | 变长字符串，最多 128 字符（utf8mb4 下按字符计） |
| `NOT NULL` | 必须有邮箱 |
| 另有 `UNIQUE KEY uk_users_email (email)` | **全局唯一**，防重复注册 |

**为何 `VARCHAR` 不是 `CHAR`：** 邮箱长短不一，变长更省空间。  
**为何 128：** 够用多数邮箱；过长会增大二级索引体积。

**唯一约束的意义：**

- 应用层「查重」挡不住并发双插；
- 数据库 `UNIQUE` 才是并发下的最终防线。

---

### 3.3 `name`

```sql
name VARCHAR(64) NOT NULL COMMENT '昵称'
```

| 属性 | 含义 |
|------|------|
| `VARCHAR(64)` | 昵称最长 64 |
| `NOT NULL` | 强制有昵称 |

若业务允许「稍后填昵称」，可改成 `NULL` 或 `DEFAULT ''`，但空字符串和 `NULL` 语义不同，团队要统一。

---

### 3.4 `status`

```sql
status TINYINT NOT NULL DEFAULT 1 COMMENT '1正常 0禁用'
```

| 属性 | 含义 |
|------|------|
| `TINYINT` | 1 字节小整数，适合枚举状态 |
| `NOT NULL` | 状态必须明确 |
| `DEFAULT 1` | 不写 status 时默认「正常」 |
| `COMMENT` | 约定：1=正常，0=禁用 |

**设计点：**

- 状态用数字省空间、索引友好；含义写在 `COMMENT` 或代码常量里。
- 以后若有更多状态（2=待审…），扩展注释与应用枚举即可。
- 也可用 `ENUM`；很多团队更偏好 `TINYINT` + 文档。

---

### 3.5 `created_at`

```sql
created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
```

| 属性 | 含义 |
|------|------|
| `DATETIME(3)` | 日期时间，**3 位毫秒** |
| `NOT NULL` | 创建时间必有 |
| `DEFAULT CURRENT_TIMESTAMP(3)` | 插入时不写则用当前时间（含毫秒） |

**`DATETIME` vs `TIMESTAMP`（简记）：**

| | `DATETIME` | `TIMESTAMP` |
|--|------------|-------------|
| 时区 | 通常按字面存 | 与时区转换关系更紧 |
| 范围 | 更大 | 历史上有 2038 一类限制需查版本 |
| 本例 | 选 `DATETIME(3)` | 也可，团队统一即可 |

`(3)` 表示精度到毫秒；`(0)` 则只到秒。

---

### 3.6 `updated_at`

```sql
updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
            ON UPDATE CURRENT_TIMESTAMP(3)
```

| 属性 | 含义 |
|------|------|
| `DEFAULT CURRENT_TIMESTAMP(3)` | 插入时默认当前时间 |
| `ON UPDATE CURRENT_TIMESTAMP(3)` | **行被 UPDATE 时自动刷新**为当前时间 |

典型审计字段：

- `created_at`：何时创建（一般不变）
- `updated_at`：最后修改时间（自动维护）

---

## 4. 表级约束

### 4.1 `PRIMARY KEY (id)`

```sql
PRIMARY KEY (id)
```

| 作用 | 说明 |
|------|------|
| 唯一标识一行 | 不能重复、不能 `NULL` |
| 一张表一个主键 | InnoDB 强烈建议有显式主键 |
| 自动建主键索引 | `WHERE id = ?` 极快 |

也可写成列内联：`id ... PRIMARY KEY`，效果同类。

### 4.2 `UNIQUE KEY uk_users_email (email)`

```sql
UNIQUE KEY uk_users_email (email)
```

| 片段 | 含义 |
|------|------|
| `UNIQUE KEY` | 唯一索引 |
| `uk_users_email` | 索引名，方便管理 |
| `(email)` | 建在 email 列上 |

命名习惯：`uk_表_列`（唯一）、`idx_表_列`（普通索引）。

本列 `email` 为 `NOT NULL`，因此是真正的业务全局唯一。

---

## 5. 表选项（右括号之后）

```sql
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='用户表';
```

### 5.1 `ENGINE=InnoDB`

| 点 | 说明 |
|----|------|
| 默认业务引擎 | 事务、行级锁、崩溃恢复 |
| 对比 MyISAM | 老引擎，无完整事务，新业务基本不用 |

### 5.2 `DEFAULT CHARSET=utf8mb4`

| 点 | 说明 |
|----|------|
| `utf8mb4` | 完整 Unicode，含 emoji |
| 避免老的 `utf8`（utf8mb3） | 三字节，emoji 会出问题 |

### 5.3 `COLLATE=utf8mb4_unicode_ci`

| 点 | 说明 |
|----|------|
| 排序/比较规则 | 字符串比较与排序 |
| `_ci` | case-insensitive，大小写不敏感（常见） |
| 影响 | `WHERE email =`、`ORDER BY name`、唯一约束如何判等 |

### 5.4 `COMMENT='用户表'`

表级注释，给人和工具看。

---

## 6. 插入时各列如何取值

```sql
INSERT INTO users (email, name) VALUES ('tom@example.com', 'Tom');
```

| 列 | 实际值来源 |
|----|------------|
| `id` | `AUTO_INCREMENT` 自动 |
| `email` / `name` | 语句写入 |
| `status` | `DEFAULT 1` |
| `created_at` / `updated_at` | `CURRENT_TIMESTAMP(3)` |

再插同一邮箱：

```sql
INSERT INTO users (email, name) VALUES ('tom@example.com', 'Tom2');
-- 期望：Duplicate entry ... for key 'uk_users_email'
```

`UPDATE` 一行后，`updated_at` 会因 `ON UPDATE` 自动变化。

---

## 7. 设计意图

```text
用户身份     → id（代理主键，稳定、短）
登录唯一键   → email + UNIQUE（业务防重）
展示名       → name
账号状态     → status（软禁用，不必急着物理删）
审计时间     → created_at / updated_at
引擎与字符集 → InnoDB + utf8mb4（事务与国际化）
```

典型 **用户主数据表** 最小骨架；订单表用 `user_id` 引用 `id`（物理外键或逻辑外键按团队规范）。

与 [mysql-pro-agent.md](./mysql-pro-agent.md) 对齐：主键/唯一在库内强制、utf8mb4、类型服务语义。

---

## 8. 等价写法（帮助理解）

列级主键：

```sql
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '主键',
```

列级唯一：

```sql
email VARCHAR(128) NOT NULL UNIQUE COMMENT '登录邮箱',
```

单独写 `PRIMARY KEY` / `UNIQUE KEY` 的好处：索引名稳定、多列唯一时更清晰。

---

## 9. 常见改动与取舍

| 需求 | 可能改法 | 注意 |
|------|----------|------|
| 软删除 | 加 `deleted_at DATETIME(3) NULL` | 删除后再注册同邮箱要设计唯一策略 |
| 手机登录 | 加 `phone` + `UNIQUE` | 与 email 二选一登录需业务规则 |
| 密码 | 加 `password_hash VARCHAR(...)` | 只存哈希，不存明文 |
| 更多状态 | 仍用 `TINYINT`，扩展注释 | 或独立状态字典表 |
| 金额字段 | 本表无 | 钱必须用 `DECIMAL`，禁止 `FLOAT` |

---

## 10. 关键字大小写

| 对象 | 规则 |
|------|------|
| `CREATE` / `BIGINT` / `NOT NULL` 等关键字 | 大小写不敏感，小写亦可 |
| 表名、列名 | 建议固定小写蛇形；Linux 上可能大小写敏感 |
| 大写关键字 | **风格**，非语法强制 |

---

## 11. 速记表

| 你看到的 | 一句话 |
|----------|--------|
| `BIGINT UNSIGNED AUTO_INCREMENT` | 自增数字主键，够大、非负 |
| `VARCHAR(n)` | 变长字符串，n 是上限 |
| `TINYINT` + `DEFAULT` | 小状态值 + 默认正常 |
| `DATETIME(3)` + `CURRENT_TIMESTAMP(3)` | 带毫秒的时间，可自动填 |
| `ON UPDATE CURRENT_TIMESTAMP(3)` | 改行时自动更新修改时间 |
| `PRIMARY KEY` | 一行一个身份 |
| `UNIQUE KEY` | 业务唯一（邮箱不重复） |
| `ENGINE=InnoDB` | 要事务与行锁的引擎 |
| `utf8mb4` | 全面 Unicode |

---

## 12. 动手验证

```sql
-- 若在空库练习，先 USE 你的库，再执行本文 CREATE TABLE

SHOW CREATE TABLE users\G
DESCRIBE users;
SHOW INDEX FROM users;

INSERT INTO users (email, name) VALUES ('a@example.com', 'A');
INSERT INTO users (email, name) VALUES ('a@example.com', 'B');  -- 应失败

SELECT * FROM users;

UPDATE users SET name = 'A2' WHERE email = 'a@example.com';
SELECT id, name, created_at, updated_at FROM users;  -- updated_at 应变新
```

教程实验库完整结构见 [labs/01_schema.sql](./labs/01_schema.sql)（含 `users` 及订单相关表）。

---

## 13. 相关文档

- [day01.md](./day01.md) — 环境、CRUD、约束入门  
- [day04.md](./day04.md) — ER、类型选择、唯一与快照  
- [labs/01_schema.sql](./labs/01_schema.sql) — 完整 shop_lab 建表  
- [mysql-pro-agent.md](./mysql-pro-agent.md) — 工程级 Schema 规则  
