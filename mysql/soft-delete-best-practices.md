# 软删除最佳实践

> 所属：7 天掌握 MySQL  
> 对应：[day04.md](./day04.md) 第 7 节「审计与软删」  
> 对齐：[mysql-pro-agent.md](./mysql-pro-agent.md)（Audit fields, soft-delete fields）

---

## 0. 先读结论

软删最佳实践压成两件事：

1. **唯一约束怎么设计**（删后能否复用 email / 手机号）
2. **查询怎么默认排除已删数据**

| 问题 | 推荐方向 |
|------|----------|
| 删后能否再注册同一 email？ | 先定产品规则，再选部分唯一或删时改写 |
| 业务查询漏过滤已删？ | Repository / ORM 默认带 `deleted_at IS NULL` |
| 只靠应用层先查再插防重？ | **不行**，并发下会穿；唯一性交给数据库约束 |

---

## 1. 先定业务规则

软删前先回答：

> 用户软删后，**能不能立刻用同一个 email 再注册？**

| 业务决定 | 库表策略方向 |
|----------|--------------|
| **允许**再注册同 email | 唯一约束只约束「未删除」行，或删时改写 email |
| **不允许**再注册 | 普通 `UNIQUE(email)` 即可，软删后仍占坑 |

不要先写 `deleted_at`，再被唯一键打脸。

---

## 2. 唯一键：三种常用做法

### 方案 A：部分唯一（推荐，MySQL 8.0.13+ 函数索引）

只对「未删除」行保证 email 唯一：

```sql
-- 未删除：deleted_at IS NULL 时 email 唯一
-- 已删除：不参与该唯一约束（可再注册同 email）
ALTER TABLE users
  ADD COLUMN deleted_at DATETIME(3) NULL DEFAULT NULL,
  ADD UNIQUE KEY uk_users_email_active ((IF(deleted_at IS NULL, email, NULL)));
```

思路：已删行在唯一表达式里变成 `NULL`，MySQL 唯一索引允许多个 `NULL`，所以不挡新注册。

也可用**生成列**再挂 `UNIQUE`（可读性更好，便于排查）：

```sql
ALTER TABLE users
  ADD COLUMN email_active VARCHAR(128)
    GENERATED ALWAYS AS (IF(deleted_at IS NULL, email, NULL)) STORED,
  ADD UNIQUE KEY uk_users_email_active (email_active);
```

具体语法以你本机 MySQL 版本 `EXPLAIN` / 建表验证为准。

### 方案 B：软删时改写 email（兼容性好）

```sql
UPDATE users
SET deleted_at = NOW(3),
    email = CONCAT(email, '__deleted__', id)  -- 释放原 email
WHERE id = ? AND deleted_at IS NULL;
```

特点：

- 原 `UNIQUE(email)` 仍可用
- 已删账号 email 被改掉，新用户可注册原邮箱
- 登录、找回密码、审计展示建议另存 `email_original` 快照

### 方案 C：不允许删后复用

```sql
UNIQUE KEY uk_users_email (email)
```

软删只改 `deleted_at`，**不改 email**。同邮箱无法再注册——产品要接受这一点。

---

## 3. 查询：默认过滤已删（硬约定）

### 3.1 约定

```text
凡读「有效业务数据」的查询，默认：
  WHERE deleted_at IS NULL
  -- 或 is_deleted = 0
```

例外才显式查已删：后台回收站、审计、合规导出。

### 3.2 落地位置（推荐优先级）

| 层级 | 做法 |
|------|------|
| **Repository 默认** | `find*` / `list*` 自动带软删条件 |
| **ORM 全局 scope** | Yii2 / Laravel soft-delete scope，默认过滤 |
| **禁止** | 业务 Service 里偶尔记得加、偶尔忘 |

反例：

```sql
-- 危险：列表漏过滤，已删用户又出现
SELECT * FROM users WHERE status = 1;
```

正例：

```sql
SELECT * FROM users
WHERE status = 1
  AND deleted_at IS NULL;
```

唯一查找也要带：

```sql
SELECT * FROM users
WHERE email = ?
  AND deleted_at IS NULL
LIMIT 1;
```

否则可能命中「已软删但仍占 email」的旧行，登录/注册逻辑会乱。

### 3.3 Repository 方法命名建议

| 方法 | 含义 |
|------|------|
| `findById($id)` | 默认只查未删除 |
| `findByIdWithTrashed($id)` | 含已删除（回收站/审计） |
| `findOnlyTrashed(...)` | 只查已删除 |

把「是否含已删」写进方法名，避免调用方猜。

---

## 4. 字段选型建议

| 方案 | 说明 |
|------|------|
| `deleted_at DATETIME(3) NULL` | **更推荐**：`NULL`=未删，非空=删除时间，信息量更大 |
| `is_deleted TINYINT` | 也可以，但往往还要另存删除时间 |
| `deleted_by` | 可选，审计谁删的 |

常见审计列组合：

```text
created_at / updated_at
created_by / updated_by   -- 可选
deleted_at / deleted_by   -- 软删
version                   -- 乐观锁可选
```

索引示例（按真实查询再调）：

```sql
-- 常见列表：未删除 + 状态
KEY idx_users_deleted_status (deleted_at, status, id)
```

---

## 5. 关联与级联

- 软删用户 **不等于** 自动软删其订单；订单是历史事实，通常保留
- 子表要不要跟着软删，按业务定，并写进 Repository / 设计文档
- 物理 FK `ON DELETE CASCADE` 和软删容易冲突；生产常见**逻辑外键** + 文档约定（见 [day04.md](./day04.md)）

---

## 6. 软删 vs 真删 vs 禁用

| 手段 | 适用 |
|------|------|
| 软删 `deleted_at` | 要留痕、可恢复、审计 |
| 状态禁用 `status = 0` | 账号停用但仍占唯一键、仍可登录拦截 |
| 真删 `DELETE` | 合规清理、过期归档；需备份与可回放 |

业务「下线账号」优先软删或禁用；`TRUNCATE` / `DROP` 与日常软删无关，见 [truncate-drop-explained.md](./truncate-drop-explained.md)。

---

## 7. mysql-pro 对齐清单

1. **先定产品规则**：删后能否复用唯一业务键（email / 手机号）
2. **唯一策略三选一**：部分唯一 / 删时改写 / 不允许复用——写进设计说明
3. **Repository / ORM 默认过滤**已删；查回收站走单独方法
4. **注册 / 登录查询**必须带「未删除」条件
5. **审计字段齐全**：`created_at` / `updated_at` / `deleted_at`（可选 `deleted_by`）
6. **真删**只留给合规清理任务，并备份、可回放
7. **唯一性交给数据库约束**，不要只靠应用层「先查再插」

---

## 8. 和订单域的关系

| 场景 | 建议 |
|------|------|
| 用户软删 | 历史订单仍在，关联用 `user_id` |
| 订单地址 | 本来就是快照，不跟用户当前地址软删绑定 |
| 商品下架 | 常用 `status`，不一定软删；已下单明细靠快照字段 |

详见：[database-normal-forms-5min.md](./database-normal-forms-5min.md)、[day04.md](./day04.md)

---

## 9. 一句话速记

- **要删后可再注册同 email** → 部分唯一，或软删时改写 email
- **所有正常业务查询** → Repository 默认 `deleted_at IS NULL`
- **唯一性交给数据库**，应用层过滤是补充，不是替代

---

## 相关文件

- [day04.md](./day04.md)
- [day01.md](./day01.md)
- [create-table-users-explained.md](./create-table-users-explained.md)
- [truncate-drop-explained.md](./truncate-drop-explained.md)
- [mysql-pro-agent.md](./mysql-pro-agent.md)
- [database-normal-forms-5min.md](./database-normal-forms-5min.md)
