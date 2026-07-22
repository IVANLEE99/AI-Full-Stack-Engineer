# 7 天掌握 MySQL 教程

> 目标：用 7 天建立 **可上岗的 MySQL 能力**——会设计表、会写正确 SQL、会看执行计划、懂事务与锁、知道生产安全边界。  
> 强度建议：约 3–4h/天（工作日）+ 周末可加练 2h  
> 方法：概念 → 手敲 SQL → EXPLAIN 证据 → 小复盘  
> 对齐资料：
>
> - [鱼皮 2026 MySQL 学习路线](https://github.com/liyupi/codefather/blob/main/%E5%AD%A6%E4%B9%A0%E8%B7%AF%E7%BA%BF/2026%E5%B9%B4%E6%9C%80%E6%96%B0MySQL%E6%95%B0%E6%8D%AE%E5%BA%93%E5%AD%A6%E4%B9%A0%E8%B7%AF%E7%BA%BF%E9%9B%B6%E5%9F%BA%E7%A1%80%E5%88%B0%E7%B2%BE%E9%80%9A%E4%B8%80%E6%9D%A1%E9%BE%99%EF%BC%88%E4%B8%87%E4%BA%BA%E6%94%B6%E8%97%8F%E2%AD%90%EF%B8%8F%EF%BC%89.md)
> - 本仓库 [mysql-pro-agent.md](./mysql-pro-agent.md)
> - 项目周课 [php/week03](../php/week03/README.md)（Yii2 AR / Repository / Redis）

---

## 1. 你将掌握什么

7 天后你应能独立做到：

1. 安装/连接 MySQL 8，完成库表 CRUD
2. 写出带 `WHERE` / `JOIN` / 聚合 / 子查询的正确 SQL
3. 按业务画 ER，设计主键、唯一约束、合理类型
4. 为真实查询设计索引，并用 `EXPLAIN` 验证
5. 解释事务 ACID、隔离级别、常见锁与死锁处理思路
6. 知道备份、主从、读写分离、慢查询的生产边界（会讲原理，不盲目上分库分表）

**优先级（与 mysql-pro 一致）**

1. 数据正确性与完整性  
2. 安全与可恢复  
3. 有证据的性能  
4. 可维护、可回滚  

---

## 2. 路线压缩逻辑

鱼皮路线原约 40–60 天（理论 → SQL → 生产 → 原理 → 面试）。本教程压缩为 7 天 **后端开发向精炼版**：

| 原阶段 | 本教程落点 |
|--------|------------|
| 阶段 1 理论与基础 | Day 1 |
| 阶段 2 SQL 实战 | Day 2–3 |
| 库表设计（跨阶段） | Day 4 |
| 阶段 4 索引/优化核心 | Day 5 |
| 事务锁 + 阶段 4 原理精选 | Day 6 |
| 阶段 3 生产 + 复盘 | Day 7 |

> 「先会用再深入」：Day 1–4 以能写、能查、能设计为主；Day 5–6 补原理与诊断；Day 7 对齐生产与面试骨架。  
> 高可用细配、分库分表落地、DBA 运维不作为 7 天必会项，只建立判断框架。

---

## 3. 七天总览

| 天 | 文件 | 主题 | 类型 | 建议时长 |
|----|------|------|------|----------|
| Day 1 | [day01.md](./day01.md) | 环境 + 概念 + CRUD + 约束 | 概念入门 | 3–4h |
| Day 2 | [day02.md](./day02.md) | 查询进阶：过滤/排序/分页/聚合 | 手写 SQL | 3–4h |
| Day 3 | [day03.md](./day03.md) | 多表 JOIN + 子查询 + 防重复行 | 手写 SQL | 3–4h |
| Day 4 | [day04.md](./day04.md) | ER 设计 + 范式 + 类型选择 | 建模实战 | 3–4h |
| Day 5 | [day05.md](./day05.md) | 索引 + EXPLAIN + 慢查询思路 | 性能诊断 | 3–4h |
| Day 6 | [day06.md](./day06.md) | 事务 + MVCC + 锁 + 死锁 | 并发基础 | 3–4h |
| Day 7 | [day07.md](./day07.md) | 生产实践 + 验收 + 延伸地图 | 复盘 | 3–4h |

---

## 4. 环境准备（Day 0，约 30–60 分钟）

任选其一即可。

### 4.1 macOS Homebrew（摘要）

```bash
# macOS Homebrew 示例（默认 formula，当前多为 MySQL 9.x）
brew install mysql
brew services start mysql
mysql -u root          # 新装通常无密码；若已设置密码再改用 mysql -u root -p
```

跟本教程更贴合的 **8.4**（keg-only，需把 bin 加进 PATH）：

```bash
brew install mysql@8.4
brew services start mysql@8.4
export PATH="$(brew --prefix mysql@8.4)/bin:$PATH"
mysql -u root
```

**完整说明（安装、PATH、services、导入 labs、排错、卸载）：**  
→ [macos-homebrew-mysql.md](./macos-homebrew-mysql.md)

**官方安装包 root 密码遗忘重置（skip-grant-tables）：**  
→ [reset-official-mysql-root.md](./reset-official-mysql-root.md)

### 4.2 Docker（备选）

```bash
docker run --name mysql8 \
  -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=shop_lab \
  -p 3306:3306 -d mysql:8.4
```

### 4.3 客户端与实验库

推荐客户端（任选）：

- 命令行 `mysql`
- MySQL Workbench / DBeaver / TablePlus
- 在线练手：[SQL 自学网](https://sqlmother.yupi.icu/)

初始化实验库：

```bash
# 无密码时去掉 -p
mysql -u root -p < labs/01_schema.sql
mysql -u root -p shop_lab < labs/02_seed.sql
```

---

## 5. 每日学习节奏（固定模板）

```text
1. 读今日目标与核心句（10 min）
2. 跟文档敲一遍 SQL / 画图（90–120 min）
3. 完成「今日练习」不看答案先写（40–60 min）
4. 用 EXPLAIN 或自测清单验收（20 min）
5. 写 5 行复盘 + 1 个 AI Review 问题（10 min）
```

**硬规则**

- 参数化思维：业务里禁止字符串拼接 SQL
- 先正确再优化；优化必须有前后证据
- 生产级 `DROP` / 全表 `UPDATE` / `DELETE` 无谓词 = 高风险，本教程只在本地 lab 演示
- 不要用开发小数据代替生产基数做结论

---

## 6. 实验库业务域（贯穿 7 天）

电商迷你模型（与 week03 订单域对齐）：

```text
users ──1:N──> orders ──1:N──> order_items
                    │
                    └──1:1──> order_addresses（历史快照）
products
```

脚本目录：

- [labs/01_schema.sql](./labs/01_schema.sql) — 建库建表
- [labs/02_seed.sql](./labs/02_seed.sql) — 示例数据
- [labs/03_practice.sql](./labs/03_practice.sql) — 每日练习题骨架（含答案分区）

---

## 7. 与 Yii2 / week03 的衔接

本教程以 **纯 SQL + 原理** 为主。学完后可直接回 week03：

| 本教程 | week03 / 工程映射 |
|--------|-------------------|
| SELECT/JOIN | Repository 查询与 `joinWith` |
| 索引/EXPLAIN | 慢接口与 N+1 根因分析 |
| ER / 订单拆表 | `order` / `order_goods` / `order_address` |
| 事务边界 | Service 编排 + 短事务 |
| 缓存一致性 | Redis cache-aside（MySQL 为真相源） |

数据访问分层提醒：

```text
Controller → Service（业务决策）→ Repository（SQL/持久化）→ MySQL
```

---

## 8. 推荐资料（精选，不贪多）

**必练**

- [SQL 自学网](https://sqlmother.yupi.icu/)
- 本目录 labs 脚本

**视频（选看）**

- [鱼皮数据库导学](https://www.bilibili.com/video/BV1iJSLBbEyD/)
- 黑马 / 尚硅谷 MySQL 入门篇（只看与当日主题对应章节）

**书籍（深挖）**

- 《MySQL 是怎样运行的》—— 索引/事务/锁
- 《高性能 MySQL》—— 索引与运维进阶

**文档**

- [MySQL 8 官方文档](https://dev.mysql.com/doc/)

**面试突击（Day 7 后）**

- [面试鸭 MySQL 题库](https://www.mianshiya.com/bank/1791003439968264194)

---

## 9. 7 天验收标准

- [ ] 能手写建表语句（主键、唯一、索引、合理类型）
- [ ] 能写多表 `LEFT JOIN` / `INNER JOIN` 且说清结果行数为何膨胀
- [ ] 能解释「为什么给这个查询加这组联合索引」
- [ ] 能读懂 `EXPLAIN` 关键列：`type` / `key` / `rows` / `Extra`
- [ ] 能说明事务何时开、隔离级别默认是什么、死锁如何处理
- [ ] 能说出备份恢复、主从延迟、读写分离的一致性风险
- [ ] 完成一份「迷你订单库」设计说明（表 + 关键索引 + 3 条核心 SQL）

---

## 10. AI Review 总提示词

```text
我在学习「7 天掌握 MySQL」教程（本地 mysql/ 目录）。
请你扮演 mysql-pro：优先数据正确性、可恢复性、有证据的性能。
检查：概念是否正确、SQL 是否有注入/笛卡尔积/误伤全表风险、
索引建议是否基于真实访问路径、事务边界是否合理。
用中文输出：问题清单、修正建议、下一步最小练习。
```

---

## 11. 目录结构

```text
mysql/
├── README.md                         # 本文件
├── macos-homebrew-mysql.md           # macOS Homebrew 安装与排错详解
├── reset-official-mysql-root.md      # 官方包 root 密码重置
├── create-table-users-explained.md    # CREATE TABLE users 逐段详解
├── mysql-pro-agent.md                # MySQL 专家 Agent 规范
├── day01.md … day07.md               # 每日教程
└── labs/
    ├── 01_schema.sql
    ├── 02_seed.sql
    └── 03_practice.sql
```

**建议进度**：连续 7 天；若中断，从最近未完成的 Day 的「今日练习」重做 30 分钟再继续。

从 [Day 1](./day01.md) 开始。
