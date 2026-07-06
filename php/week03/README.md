# Week 03：MySQL + Redis + ORM

> 强度建议：约 20h/周（周一到周五各 3h + 周末 5h）  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review  
> 公开说明：使用匿名仓库代号，不包含公司、品牌、内网域名或本地绝对路径。

---

## 1. 本周定位

- 阶段：第一阶段：PHP + Yii2/TP 基础
- 主仓库/项目：`mall-core`
- 本周目标：掌握 MySQL、ActiveRecord、Repository、Redis 与 N+1 问题。

### 为什么本周要学这些

- 业务后端核心是数据读写。
- Repository 是后续读代码主线。

---

## 2. 本周需要掌握的知识点

1. MySQL JOIN/索引
2. ActiveRecord
3. Repository
4. Redis
5. N+1/with()

### php-pro 能力对齐

- 查询注意索引
- Repository 不做业务
- 缓存考虑失效

---

## 3. 必读代码/文件路径

- `mall-core/common/repositorys/order/OrderRepository.php`
- `mall-core/common/models/order/Order.php`
- `mall-core/common/redis/order/OrderRedis.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 4. 七天详细学习安排

| 天 | 类型 | 主题 |
|----|------|------|
| Day 1（周一） | 概念入门 | MySQL 与索引基础 |
| Day 2（周二） | 源码阅读 | ActiveRecord 模型 |
| Day 3（周三） | 编码练习 | Repository 模式 |
| Day 4（周四） | 架构理解 | Redis 缓存 |
| Day 5（周五） | 类比日 | N+1 与类比日 |
| Day 6（周六） | 项目实战 | 订单 ER 图实战 |
| Day 7（周日） | 复盘预习 | 验收与预习 |

### Day 1（周一）：MySQL 与索引基础

**类型**：概念入门  
**今日目标**：理解 SELECT/JOIN/索引。

**学习内容**：
- 高性能 MySQL 索引章
- Yii2 DB 基础

**源码阅读**：
- `mall-core/common/repositorys/order/OrderRepository.php`

**练习任务**：
- 读 OrderRepository 前100行
- 写1个 JOIN 练习题
- 解释第一个复杂查询

**JS/Node 类比**：
- SQL≈任何后端都需
- 索引≈查询性能关键

**AI Review 提问**：
- 复杂查询理解对吗？

**今日产出**：
- SQL 练习
- 查询笔记

**今日完成标准**：
- [ ] 能解释 JOIN
- [ ] 能写基础查询

---

### Day 2（周二）：ActiveRecord 模型

**类型**：源码阅读  
**今日目标**：掌握 AR 链式查询。

**学习内容**：
- Yii2 ActiveRecord

**源码阅读**：
- `mall-core/common/models/order/Order.php`

**练习任务**：
- 读 Order Model
- 对比 Sequelize findOne
- 列 5 个常用查询方法

**JS/Node 类比**：
- AR≈Sequelize Model

**AI Review 提问**：
- AR 与 Sequelize 差异？

**今日产出**：
- Model 笔记

**今日完成标准**：
- [ ] 能读 AR 查询

---

### Day 3（周三）：Repository 模式

**类型**：编码练习  
**今日目标**：理解 Repository 职责与命名。

**学习内容**：
- Repository 模式

**源码阅读**：
- `mall-core/common/repositorys/order/OrderRepository.php`

**练习任务**：
- 找 getOrderObjByNo 等方法
- 解释为何 Service 不直接 SQL
- 写 Repository 职责表

**JS/Node 类比**：
- Repository≈DAO 层

**AI Review 提问**：
- 命名规范合理吗？

**今日产出**：
- Repository 清单

**今日完成标准**：
- [ ] 能解释 Repository 边界

---

### Day 4（周四）：Redis 缓存

**类型**：架构理解  
**今日目标**：理解 Redis 封装与使用场景。

**学习内容**：
- Redis 五大数据类型

**源码阅读**：
- `mall-core/common/redis/order/OrderRedis.php`

**练习任务**：
- 读 OrderRedis
- 列缓存读写场景
- 画缓存流程

**JS/Node 类比**：
- Redis≈ioredis
- 缓存≈减少 DB 压力

**AI Review 提问**：
- 何时该缓存？

**今日产出**：
- 缓存流程图

**今日完成标准**：
- [ ] 能说明缓存场景

---

### Day 5（周五）：N+1 与类比日

**类型**：类比日  
**今日目标**：理解 N+1 与 with() 预加载。

**学习内容**：
- N+1 问题
- eager loading

**练习任务**：
- 对照订单列表前端字段
- 找 Repository 数据来源
- 完成类比打卡

**JS/Node 类比**：
- N+1≈循环里 await 查库
- with()≈include/join 预加载

**AI Review 提问**：
- 字段对照准确吗？

**今日产出**：
- 字段对照表
- 类比打卡

**今日完成标准**：
- [ ] 能解释 N+1

---

### Day 6（周六）：订单 ER 图实战

**类型**：项目实战  
**今日目标**：画 ER 图并验证 SQL。

**学习内容**：
- ER 建模

**练习任务**：
- 画 order/order_goods/order_address ER
- 执行 SQL 验证

**JS/Node 类比**：
- ER≈数据模型设计图

**AI Review 提问**：
- ER 合理吗？

**今日产出**：
- ER 图
- SQL 验证记录

**今日完成标准**：
- [ ] ER 图完成

---

### Day 7（周日）：验收与预习

**类型**：复盘预习  
**今日目标**：验收并预习配置中心。

**学习内容**：
- 回顾 DB 笔记
- 预习 g_config

**练习任务**：
- 勾选验收
- 写总结

**JS/Node 类比**：
- 预习配置中心

**AI Review 提问**：
- 准备好学配置吗？

**今日产出**：
- 周总结

**今日完成标准**：
- [ ] 完成验收

---

## 5. JS/Node.js 类比学习（本周总览）

AR≈Sequelize；Repository≈DAO；Redis≈ioredis。

### 本周类比打卡模板

```text
本周概念：
Node 等价：
差异：
我能用自己的话解释吗：是 / 否
理解自评：1 / 2 / 3 / 4 / 5
```

---

## 6. 本周产出物

- [ ] ER 图
- [ ] Repository 清单
- [ ] 字段对照表
- [ ] 周总结

---

## 7. 推荐学习资料

- Yii2 ActiveRecord
- Redis 命令参考
- 《高性能 MySQL》

---

## 8. 本周验收标准

- [ ] 能解释 N+1
- [ ] 能对照前端字段
- [ ] 完成 ER 图

---

## 9. AI Review 提示词

```text
我正在进行 Week 03：MySQL + Redis + ORM 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：理解是否正确、JS 类比是否准确、是否遗漏风险、真实项目需注意什么。
请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 10. 周日复盘与下周预习

| 复盘项 | 记录 |
|--------|------|
| 本周最清楚的概念 |  |
| 本周最卡的概念 |  |
| JS/Node 类比是否帮助理解 |  |
| 本周产出是否完成 |  |
| 自评分（1-5） |  |

**下周预习**：预习 g_config、ConfigHelper、站点配置 API。
