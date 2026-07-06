# Week 03 Day 01：MySQL 与索引基础

> 所属周：Week 03：MySQL + Redis + ORM  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-core`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 MySQL 基础查询、`SELECT`、`WHERE`、`JOIN`、索引的基本作用，并能读懂 `OrderRepository.php` 里最基础的查询结构。

今天你要真正掌握这一句话：

> MySQL 是后端保存业务数据的核心工具，Repository 负责把业务需要的数据查询封装起来；索引就像书的目录，可以让数据库更快找到数据，但索引不是越多越好。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解数据库、表、行、列是什么
2. 理解 `SELECT` 查询
3. 理解 `WHERE` 条件过滤
4. 理解 `ORDER BY`、`LIMIT`
5. 理解主键、外键、订单相关表
6. 理解 `JOIN` 是什么
7. 理解为什么需要索引
8. 理解索引适合加在哪些字段上
9. 阅读 `OrderRepository.php` 前 100 行
10. 找出第一个复杂查询的表、条件、返回字段
11. 写一个 JOIN 练习题
12. 完成今日自测和 AI Review

---

## 1. 学习内容

### 1.1 数据库是什么？

数据库可以理解为专门保存结构化数据的系统。

电商项目里常见数据：

- 用户数据
- 商品数据
- 订单数据
- 支付数据
- 售后数据
- 配置数据

这些数据通常会放在 MySQL 的表中。

---

### 1.2 表、行、列是什么？

一张用户表可以长这样：

| id | name | email | created_at |
|---|---|---|---|
| 1 | Tom | tom@example.com | 2026-01-01 10:00:00 |
| 2 | Alice | alice@example.com | 2026-01-02 11:00:00 |

类比：

| MySQL | Excel / JS 类比 |
|---|---|
| database | 一个工作簿 / 一个项目数据库 |
| table | 一张表 / 一个数组集合 |
| row | 一行数据 / 一个对象 |
| column | 字段 / 对象属性 |

JS 类比：

```js
const users = [
  { id: 1, name: 'Tom', email: 'tom@example.com' },
  { id: 2, name: 'Alice', email: 'alice@example.com' },
];
```

MySQL 表类似一个很大的、可查询的对象数组。

---

### 1.3 SELECT 是什么？

`SELECT` 用来查询数据。

查询所有字段：

```sql
SELECT * FROM users;
```

查询指定字段：

```sql
SELECT id, name, email FROM users;
```

小白理解：

```text
SELECT 要哪些字段
FROM 从哪张表查
```

JS 类比：

```js
users.map(user => ({
  id: user.id,
  name: user.name,
  email: user.email,
}));
```

---

### 1.4 WHERE 是什么？

`WHERE` 用来过滤数据。

```sql
SELECT id, name FROM users WHERE id = 1;
```

意思是：

```text
从 users 表中查 id 等于 1 的用户
```

JS 类比：

```js
users.filter(user => user.id === 1);
```

常见条件：

| SQL | 含义 |
|---|---|
| `id = 1` | 等于 1 |
| `status != 0` | 不等于 0 |
| `price > 100` | 大于 100 |
| `created_at >= '2026-01-01'` | 大于等于某日期 |
| `name LIKE '%Tom%'` | 模糊匹配 |
| `id IN (1,2,3)` | 在集合中 |

---

### 1.5 ORDER BY 和 LIMIT

排序：

```sql
SELECT * FROM orders ORDER BY created_at DESC;
```

表示按创建时间倒序。

限制数量：

```sql
SELECT * FROM orders ORDER BY created_at DESC LIMIT 10;
```

表示只取最新 10 条。

分页常见写法：

```sql
SELECT * FROM orders ORDER BY created_at DESC LIMIT 20 OFFSET 40;
```

意思是跳过 40 条，取 20 条。

JS 类比：

```js
orders
  .sort((a, b) => b.created_at - a.created_at)
  .slice(40, 60);
```

---

### 1.6 主键是什么？

主键是表里唯一标识一行数据的字段。

常见：

```text
id
```

例如订单表：

| id | order_no | user_id | status |
|---|---|---|---|
| 1 | O202601010001 | 100 | 1 |
| 2 | O202601010002 | 101 | 0 |

`id` 通常是主键。

特点：

- 唯一
- 不为空
- 用来快速定位一行数据

---

### 1.7 订单相关表怎么理解？

电商订单通常不止一张表。

可能有：

```text
order
order_goods
order_address
```

含义：

| 表 | 作用 |
|---|---|
| `order` | 订单主表，保存订单号、用户、金额、状态 |
| `order_goods` | 订单商品表，保存订单中买了哪些商品 |
| `order_address` | 订单地址表，保存收货地址快照 |

为什么要拆多张表？

因为一个订单可能包含多个商品。

```text
一个 order
  ↓
多个 order_goods
```

这就是一对多关系。

---

### 1.8 JOIN 是什么？

`JOIN` 用来把多张表关联起来查询。

假设：

`orders` 表：

| id | order_no | user_id |
|---|---|---|
| 1 | O001 | 100 |

`order_goods` 表：

| id | order_id | goods_name |
|---|---|---|
| 1 | 1 | iPhone |
| 2 | 1 | Case |

查询订单和商品：

```sql
SELECT
    o.id,
    o.order_no,
    og.goods_name
FROM orders o
LEFT JOIN order_goods og ON og.order_id = o.id
WHERE o.id = 1;
```

结果：

| id | order_no | goods_name |
|---|---|---|
| 1 | O001 | iPhone |
| 1 | O001 | Case |

---

### 1.9 LEFT JOIN 怎么理解？

`LEFT JOIN` 表示：

> 以左边表为主，即使右边表没有匹配数据，也保留左边数据。

例如订单没有商品数据时：

```sql
SELECT *
FROM orders o
LEFT JOIN order_goods og ON og.order_id = o.id;
```

仍然会返回订单，只是商品字段可能是 `NULL`。

常见 JOIN：

| JOIN 类型 | 小白理解 |
|---|---|
| `INNER JOIN` | 两边都匹配才返回 |
| `LEFT JOIN` | 左边保留，右边没有则 NULL |
| `RIGHT JOIN` | 右边保留，左边没有则 NULL |

企业项目最常见：`LEFT JOIN` 和 `INNER JOIN`。

---

### 1.10 索引是什么？

索引可以理解为书的目录。

如果一本书没有目录，你要找“订单状态”这一节，只能从第一页翻到最后。

如果有目录，你可以很快定位页码。

数据库索引也是类似：

> 索引帮助 MySQL 更快找到满足条件的数据。

例如经常这样查：

```sql
SELECT * FROM orders WHERE order_no = 'O001';
```

那 `order_no` 就适合建索引。

---

### 1.11 哪些字段适合加索引？

常见适合索引的字段：

| 字段类型 | 示例 | 原因 |
|---|---|---|
| 主键 | `id` | 唯一定位 |
| 唯一业务号 | `order_no` | 经常按订单号查 |
| 外键关联字段 | `user_id`、`order_id` | JOIN 常用 |
| 状态字段组合 | `status + created_at` | 列表筛选排序 |
| 时间字段 | `created_at` | 常按时间排序/范围查询 |

不适合盲目加索引的情况：

- 很少查询的字段
- 区分度很低的字段单独索引，例如纯 `gender`
- 太多索引导致写入变慢

---

### 1.12 索引不是越多越好

索引优点：

- 查询更快
- 排序可能更快
- JOIN 可能更快

索引缺点：

- 占用空间
- 插入/更新/删除会变慢
- 索引太多维护成本高

所以你要记住：

> 索引是为查询模式服务的，不是所有字段都要加。

---

### 1.13 Repository 和 SQL 的关系

在企业 PHP 项目中，Service 通常不直接写 SQL。

常见结构：

```text
Controller
  ↓
Service
  ↓
Repository
  ↓
Model / ActiveRecord
  ↓
MySQL
```

Repository 的作用：

- 封装查询
- 隐藏 SQL/ORM 细节
- 给 Service 提供清晰方法

例如：

```php
$order = OrderRepository::instance()->getOrderObjByNo($orderNo);
```

小白理解：

> Service 问 Repository 要数据，Repository 负责怎么查。

---

## 2. 源码阅读

- `mall-core/common/repositorys/order/OrderRepository.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

### 2.1 阅读目标

今天只读前 100 行，不求完全看懂。

重点找：

1. namespace 是什么
2. class 名是什么
3. 继承了哪个 BaseRepository
4. use 了哪些 Model
5. 前 100 行有哪些 public 方法
6. 第一个复杂查询用了哪些表
7. 查询条件有哪些
8. 返回值大概是什么

---

### 2.2 Repository 阅读记录表

| 观察点 | 记录 |
|---|---|
| 文件路径 | `mall-core/common/repositorys/order/OrderRepository.php` |
| namespace |  |
| class 名 |  |
| 父类 |  |
| 关联 Model |  |
| 前 100 行 public 方法 |  |
| 第一个复杂查询方法 |  |

---

### 2.3 复杂查询拆解表

找到第一个复杂查询后，按表格拆：

| 拆解项 | 记录 |
|---|---|
| 方法名 |  |
| 查询主表 |  |
| JOIN 表 |  |
| WHERE 条件 |  |
| ORDER BY |  |
| LIMIT |  |
| 返回字段 |  |
| 返回类型 |  |
| 业务含义 |  |

---

## 3. 练习任务

### 练习 1：写基础 SELECT

```sql
SELECT id, order_no, user_id, status
FROM orders
WHERE user_id = 100;
```

解释：

```text
查询 user_id=100 的订单 id、订单号、用户 ID、状态。
```

---

### 练习 2：写排序和限制

```sql
SELECT id, order_no, created_at
FROM orders
WHERE user_id = 100
ORDER BY created_at DESC
LIMIT 10;
```

解释：

```text
查询用户 100 最新的 10 条订单。
```

---

### 练习 3：写一个 JOIN 练习题

题目：查询订单和订单商品名称。

```sql
SELECT
    o.id,
    o.order_no,
    og.goods_name,
    og.quantity
FROM orders o
LEFT JOIN order_goods og ON og.order_id = o.id
WHERE o.order_no = 'O001';
```

你要能解释：

| SQL 片段 | 含义 |
|---|---|
| `orders o` | orders 表起别名 o |
| `order_goods og` | order_goods 表起别名 og |
| `LEFT JOIN` | 关联订单商品表 |
| `og.order_id = o.id` | 商品表通过 order_id 关联订单 id |
| `WHERE o.order_no = 'O001'` | 只查指定订单号 |

---

### 练习 4：判断哪些字段适合索引

假设订单表常见查询：

```sql
SELECT * FROM orders WHERE order_no = ?;
SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC;
SELECT * FROM orders WHERE status = ? AND created_at >= ?;
```

你可以建议索引：

| 查询 | 可能索引 |
|---|---|
| 按订单号查 | `order_no` |
| 查用户订单列表 | `user_id, created_at` |
| 按状态和时间筛选 | `status, created_at` |

注意：真实索引设计要结合数据量、区分度和执行计划。

---

### 练习 5：解释 OrderRepository 第一个复杂查询

用自己的话写：

```text
这个方法叫：
它查询的主表是：
它 JOIN 了：
它的 WHERE 条件是：
它返回：
它的业务意义是：
如果我是前端，我会在什么页面用到这些数据：
```

---

## 4. JS/Node.js 类比

| MySQL / PHP 概念 | Node/JS 类比 | 差异 |
|---|---|---|
| MySQL table | 对象数组 | 数据库有索引、事务、SQL 引擎 |
| row | object | 一行就是一条记录 |
| column | object property | 字段有类型和约束 |
| SELECT | map/filter 查询 | SQL 在数据库端执行 |
| WHERE | `.filter()` | SQL 可利用索引 |
| JOIN | 多数组关联 | 数据库负责高效关联 |
| index | Map / 目录 | 提升查询但影响写入 |
| Repository | DAO / Prisma repository | 封装查询逻辑 |
| ActiveRecord | Sequelize Model | ORM 对表记录建模 |

---

## 5. AI Review 提问

完成 SQL 练习和 Repository 阅读后，把内容贴给 AI：

```text
我正在学习 Week 03 Day 01：MySQL 与索引基础。

我读了 OrderRepository.php 前 100 行，并写了一个 JOIN 查询练习。
请你按资深 PHP 后端和 MySQL 工程师标准帮我检查：

1. 我对 SELECT / WHERE / JOIN 的理解是否正确？
2. 我对索引作用的理解是否准确？
3. 我拆解的 OrderRepository 查询是否合理？
4. 我建议的索引是否有明显问题？
5. 真实项目里读 Repository 查询还应该关注什么？

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 6. 今日产出

- [ ] 基础 SELECT 练习
- [ ] ORDER BY / LIMIT 练习
- [ ] JOIN 查询练习
- [ ] 索引判断表
- [ ] `OrderRepository.php` 前 100 行阅读笔记
- [ ] 第一个复杂查询拆解表
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 database / table / row / column
- [ ] 能写基础 `SELECT`
- [ ] 能写 `WHERE`
- [ ] 能写 `ORDER BY` 和 `LIMIT`
- [ ] 能解释 `JOIN`
- [ ] 能解释 `LEFT JOIN`
- [ ] 能解释索引像目录
- [ ] 能说出索引不是越多越好
- [ ] 能读懂 Repository 里一个基础查询的大概含义
- [ ] 能写一个订单与订单商品的 JOIN 查询

---

## 8. 今日自测题

### 8.1 `SELECT` 是做什么的？

参考答案：

> `SELECT` 用来从数据库表中查询数据。

---

### 8.2 `WHERE` 是做什么的？

参考答案：

> `WHERE` 用来筛选符合条件的数据。

---

### 8.3 `LEFT JOIN` 是什么意思？

参考答案：

> 以左表为主，即使右表没有匹配数据，也保留左表记录，右表字段为 NULL。

---

### 8.4 索引是什么？

参考答案：

> 索引类似书的目录，可以帮助 MySQL 更快找到数据。

---

### 8.5 索引为什么不是越多越好？

参考答案：

> 因为索引会占用空间，并且插入、更新、删除数据时需要维护索引，会影响写入性能。

---

### 8.6 Repository 负责什么？

参考答案：

> Repository 负责封装数据库查询和数据访问逻辑，让 Service 不直接写复杂查询。

---

### 8.7 `orders` 和 `order_goods` 通常是什么关系？

参考答案：

> 一对多关系，一个订单可以有多个订单商品记录。

---

## 9. 学习记录

| 记录项 | 内容 |
|--------|------|
| 今日最清楚的概念 |  |
| 今日最卡的概念 |  |
| JS/Node 类比是否帮助理解 |  |
| 实际耗时 |  |
| 明日要补的问题 |  |

---

## 10. AI Review 提示词

```text
我正在进行 Week 03 Day 01：MySQL 与索引基础 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 03 README](./README.md)
