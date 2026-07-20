# Day 04：ER 建模与表结构设计

> 所属：7 天掌握 MySQL  
> 类型：建模实战  
> 建议时长：3–4h  
> 对应路线：阶段 1 数据库设计 + 生产表结构实践

---

## 今日目标

能根据业务画 ER，设计主键/唯一键/关系，选择合适类型，并说清范式与有证据的反范式。

**今日核心句：**

> 表结构是业务不变量的固化：唯一性用约束保证，历史快照与可变主数据分离，类型为语义服务（钱用 DECIMAL）。

---

## 0. 今日路线

1. 从业务对象到实体
2. 1:1 / 1:N / N:N
3. ER 图画法（订单域）
4. 三大范式（够用版）
5. 类型与 NULL 语义
6. 审计字段、软删、快照
7. 对照 `labs/01_schema.sql` 精读
8. 独立设计一版「优惠券」表并自评

---

## 1. 建模三问

设计任何表前先回答：

1. **这行数据的业务身份是什么？**（主键 / 业务单号）
2. **哪些字段组合必须全局唯一？**（唯一约束）
3. **它与谁关联？关联失败时怎么办？**（外键策略 / 逻辑删除 / 快照）

---

## 2. 关系类型

### 2.1 一对一（1:1）

```text
orders 1 ── 1 order_addresses
```

常见原因：

- 主表瘦身（大字段拆出）
- 扩展属性低频访问
- **历史快照**（地址下单后不再随用户地址变更）

### 2.2 一对多（1:N）

```text
users 1 ── N orders
orders 1 ── N order_items
```

多的一方存外键：`orders.user_id`、`order_items.order_id`。

### 2.3 多对多（N:N）

```text
products N ── N tags
         └─ product_tags (product_id, tag_id)
```

中间表通常：

```sql
PRIMARY KEY (product_id, tag_id)
-- 或自增 id + UNIQUE(product_id, tag_id)
```

---

## 3. 订单域 ER（本教程标准答案骨架）

```text
┌─────────┐       ┌──────────────┐       ┌──────────────┐
│  users  │1    N │   orders     │1    N │ order_items  │
│─────────│───────│──────────────│───────│──────────────│
│ id PK   │       │ id PK        │       │ id PK        │
│ email UK│       │ order_no UK  │       │ order_id FK  │
│ name    │       │ user_id FK   │       │ product_id   │
└─────────┘       │ status       │       │ product_name │  ← 快照
                  │ total_amount │       │ unit_price   │  ← 快照
                  └──────┬───────┘       └──────────────┘
                         │1
                         │1
                  ┌──────┴───────┐
                  │order_addresses│
                  │ receiver_*    │  ← 全部快照
                  │ city / detail │
                  └──────────────┘

┌──────────┐
│ products │  商品主数据（可变）
│ price    │  下单后订单行不随其变更
└──────────┘
```

**为何 order_items 要存 product_name / unit_price？**

> 商品会改名改价；订单是法律/财务事实，必须冻结下单时点快照。

这与 week03「order_address 是历史快照」同一原则。

---

## 4. 范式：够用版

| 范式 | 白话 | 反例 |
|------|------|------|
| 1NF | 字段原子，不存「逗号分隔列表」当结构 | `items = '键盘,鼠标'` 当正式结构 |
| 2NF | 非键字段完全依赖整主键 | 联合主键表里存只依赖部分键的字段 |
| 3NF | 非键字段不依赖其他非键 | 订单表存 `user_email` 且随用户改邮箱而乱（除非快照） |

**有证据的反范式：**

- 订单行冗余商品名（快照需要）
- 高频列表页冗余计数字段（要用事务/异步保证一致）

反范式必须写清：**谁更新、何时失效、不一致窗口是否可接受**。

---

## 5. 类型选择清单（mysql-pro 对齐）

| 场景 | 推荐 | 避免 |
|------|------|------|
| 钱、汇率 | `DECIMAL(p,s)` | `FLOAT`/`DOUBLE` |
| 主键 | `BIGINT UNSIGNED` | 无意义随机串做聚簇主键且极散 |
| 状态 | `TINYINT` + 字典注释 | 魔法字符串漫天飞 |
| 手机/单号 | `VARCHAR` | 用整型存前导 0 |
| IP | 视版本：`VARBINARY`/`VARCHAR` | — |
| JSON 扩展 | `JSON` 存真正半结构化 | 用 JSON 替代稳定关系列 |
| 布尔 | `TINYINT(1)` / `BOOLEAN` | 三态却用布尔硬塞 |

**NULL 语义：**

- `NULL` = 未知 / 不适用，不是空字符串
- 唯一索引在 MySQL 中允许多个 `NULL`（版本与 SQL 模式相关，设计时要明确）
- 不要无意义地「全部可空」——可空列要有业务解释

---

## 6. 约束策略

```text
业务唯一      → UNIQUE（order_no, email）
引用完整性  → FK 或应用层保证 + 文档
金额非负     → 应用校验；高阶可用 CHECK（MySQL 8.0.16+）
状态机合法   → Service 层；库可用枚举/字典表
```

生产常见：**逻辑外键**（不建物理 FK）以换迁移灵活与批量写入性能——但必须在 Repository/文档写清关联约定。

---

## 7. 审计与软删

常见列：

```text
created_at / updated_at
created_by / updated_by   -- 可选
deleted_at / is_deleted   -- 软删
version                   -- 乐观锁可选
```

软删注意：

- 唯一键是否允许「删除后再注册同一 email」→ 往往需要 **部分唯一** 策略或把 email 改写
- 所有查询默认过滤已删数据

---

## 8. 精读实验 Schema

打开 [labs/01_schema.sql](./labs/01_schema.sql)，逐项回答：

1. 每张表主键是什么？
2. 哪些唯一键？防的是什么业务重复？
3. 哪些字段是快照？哪些是主数据？
4. 哪些列会建索引（Day5 再优化）？
5. 金额字段类型是否正确？

---

## 9. 今日实战：设计「优惠券」最小模型

业务（简化）：

- 运营创建优惠券模板（满减金额、有效期、总量）
- 用户领券（一人一券模板限领 1 张）
- 下单可绑定一张用户券，核销后不可再用

请你产出：

1. ER 草图（可文本）
2. `CREATE TABLE` 2–3 张表
3. 唯一约束说明（防超领、防重复核销）
4. 3 条核心 SQL：领券、查可用券、核销

写在笔记里，Day7 复盘时对照。

参考骨架（先自己写再看）：

```text
coupon_templates (id, code UK, title, threshold, amount, total, start_at, end_at)
user_coupons (id, user_id, template_id, status, obtained_at, used_order_id, UK(user_id, template_id))
```

---

## 10. 迁移意识（预告 Day7）

改表不是改代码：

```text
expand（加可空列/新表）→ 双写/回填 → 切换读 → contract（删旧列）
```

大表 DDL 要评估锁、磁盘、复制延迟。详见 Day7。

---

## 11. 自测清单

- [ ] 能画订单域 ER 并讲解 1:N 与快照
- [ ] 能说明为何金额用 DECIMAL
- [ ] 能区分主数据与历史快照
- [ ] 能为业务唯一场景设计 UNIQUE
- [ ] 完成优惠券最小设计草稿

---

## 12. 5 行复盘

```text
今天最清楚：
快照 vs 主数据：
我设计的唯一键：
范式/反范式取舍：
明天预习：索引与 EXPLAIN
```

## 13. AI Review 提问

```text
这是我的优惠券表设计（贴 DDL）。
请按 mysql-pro 检查：唯一约束、并发超领、类型、软删与唯一冲突、是否过度 JSON 化。
```

---

## 下一步

→ [Day 05：索引与 EXPLAIN](./day05.md)
