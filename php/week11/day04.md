# Week 11 Day 04：Model 与 ModelJoin

> 所属周：Week 11：ThinkPHP 8 门店 API  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`store-api`  
> 类型：架构理解  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 ThinkPHP 8 中 Model、ThinkORM 和 ModelJoin 的职责，知道简单数据读写放 Model，复杂联表查询可以封装到 ModelJoin 或专门查询类中，避免 Service 里堆 SQL 细节。

今天你要真正掌握这一句话：

> Service 负责业务编排，Model/ModelJoin 负责数据访问和复杂查询封装；把联表细节从 Service 中抽离出来，可以让业务代码更清晰、更容易复用和优化。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Yii2 ActiveRecord / Repository 的职责
2. 阅读 ThinkORM 基础概念
3. 找 `OfflineStore` Model，理解表映射和基础查询
4. 找一个 ModelJoin 或复杂查询封装
5. 对比 Service 中调用 ModelJoin 的方式
6. 分析为什么联表查询不建议直接写在 Controller/Service 里
7. 记录查询条件、关联表、返回字段、索引风险
8. 用 Prisma/TypeORM query builder 做类比
9. 用 AI Review 检查封装位置是否合理

---

## 1. 学习内容

### 1.1 Model 负责什么？

TP8 Model 通常负责一张表或一个核心数据对象。

常见职责：

- 定义表名
- 定义主键
- 定义字段类型转换
- 封装基础查询
- 定义关联关系
- 处理数据访问细节

示例伪代码：

```php
<?php

namespace app\common\model\store;

use think\Model;

class OfflineStore extends Model
{
    protected $name = 'offline_store';
    protected $pk = 'id';
}
```

小白重点：Model 是和数据库表最接近的一层。

---

### 1.2 ModelJoin 是什么？

有些列表接口需要联表查询：

- 门店表
- 城市表
- 商户表
- 管理员表
- 统计表

如果每个 Service 都自己写 join，会重复且难维护。

ModelJoin 可以理解成：

```text
专门封装复杂联表查询的查询对象。
```

它可能负责：

- join 哪些表
- select 哪些字段
- where 条件怎么拼
- order 怎么排
- 分页怎么做
- 返回结构怎么统一

---

### 1.3 为什么联表不放 Service？

Service 应该表达业务：

```php
<?php

$list = $this->storeJoin->getStoreList($filters);
```

而不是在 Service 中写一大段：

```php
<?php

Db::name('offline_store')
    ->alias('s')
    ->leftJoin('merchant m', 'm.id = s.merchant_id')
    ->leftJoin('city c', 'c.id = s.city_id')
    ->where(...)
    ->field(...)
    ->paginate(...);
```

这样 Service 会变得像 SQL 脚本，不利于复用和测试。

---

### 1.4 分层建议

| 层级 | 适合放什么 | 不适合放什么 |
|---|---|---|
| Controller | 取参、校验、调用 Service | SQL / join |
| Service | 业务规则、调用查询对象、组织返回 | 大量数据库拼接细节 |
| Model | 单表基础能力 | 复杂业务编排 |
| ModelJoin | 联表查询、列表查询、聚合查询 | HTTP 请求处理 |

---

### 1.5 复杂查询要关注性能

读 ModelJoin 时要看：

| 关注点 | 为什么 |
|---|---|
| join 表数量 | 表越多越慢、越容易错 |
| where 条件 | 是否能走索引 |
| select 字段 | 是否避免 `select *` |
| order 字段 | 排序字段是否有索引 |
| 分页方式 | 深分页可能慢 |
| 权限条件 | 是否按商户/门店/用户限制范围 |

---

### 1.6 Node.js 类比

Prisma/TypeORM 中也会封装复杂查询：

```ts
class StoreRepository {
  async listWithMerchant(filters) {
    return this.prisma.store.findMany({
      include: { merchant: true, city: true },
      where: filters,
    });
  }
}
```

TP8 ModelJoin 类似 repository/query object。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议阅读：

- OfflineStore Model
- 一个 ModelJoin 类
- StoreService 调用 ModelJoin 的位置

记录：

| 查询类 | 关联表 | 查询条件 | 返回字段 | 性能风险 |
|---|---|---|---|---|
|  |  |  |  |  |

---

## 3. 练习任务

### 练习 1：读 OfflineStore Model

记录表名、主键、字段、关联方法。

### 练习 2：读一个 ModelJoin

记录 join 表、where 条件、select 字段、分页方式。

### 练习 3：说明为何联表不放 Service

写出至少 3 个理由。

---

## 4. JS/Node.js 类比

- ModelJoin ≈ 复杂查询封装 / repository query object
- ThinkORM Model ≈ Prisma/TypeORM model
- Service 调用 ModelJoin ≈ Service 调 Repository
- 联表查询封装 ≈ query builder 封装

---

## 5. AI Review 提问

```text
我正在学习 TP8 Model 与 ModelJoin。
我已经阅读 OfflineStore Model 和一个 ModelJoin，并整理了联表查询、筛选、分页和返回字段。
请你检查：
1. Model 与 ModelJoin 职责是否理解正确？
2. 联表查询放 ModelJoin 是否合理？
3. 哪些查询逻辑仍然应该留在 Service？
4. 这个列表查询有哪些索引和性能风险？
5. 与 Repository/query object 的类比是否准确？
```

---

## 6. 今日产出

- [ ] OfflineStore Model 阅读笔记
- [ ] ModelJoin 笔记
- [ ] 复杂查询职责划分表
- [ ] 查询性能风险表
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释联表封装
- [ ] 能说明 Model 和 ModelJoin 区别
- [ ] 能说明为什么复杂查询不放 Controller
- [ ] 能列出列表查询 5 个性能关注点
- [ ] 能用 Repository/query object 类比 ModelJoin

---

## 8. 学习记录

| 记录项 | 内容 |
|--------|------|
| 今日最清楚的概念 |  |
| 今日最卡的概念 |  |
| JS/Node 类比是否帮助理解 |  |
| 实际耗时 |  |
| 明日要补的问题 |  |

---

## 9. AI Review 提示词

```text
我正在进行 Week 11 Day 04：Model 与 ModelJoin 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 11 README](./README.md)
