# Week 03 Day 03：Repository 模式

> 所属周：Week 03：MySQL + Redis + ORM  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-core`  
> 类型：编码练习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 Repository 模式的职责边界、命名习惯和它在企业 PHP 项目中的位置；能说明为什么 Service 不建议直接写 SQL / ActiveRecord 查询，而是通过 Repository 获取数据。

今天你要真正掌握这一句话：

> Repository 是数据访问层，它把“怎么查数据库”封装起来，让 Service 专心写业务流程；Service 问 Repository 要数据，Repository 再去调用 Model / ActiveRecord / SQL。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 复习 Controller / Service / Repository / Model 分层
2. 理解为什么 Service 不直接写 SQL
3. 理解 Repository 的职责
4. 理解 Repository 不应该做什么
5. 学习常见 Repository 方法命名
6. 阅读 `OrderRepository.php`
7. 找 `getOrderObjByNo` 等方法
8. 拆解一个 Repository 方法
9. 写一个简化版 Repository 示例
10. 用 DAO / Prisma repository 做类比
11. 完成今日自测和 AI Review

---

## 1. 学习内容

### 1.1 先复习后端分层

企业 PHP 项目常见分层：

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

每层职责：

| 层 | 职责 | 不应该做什么 |
|---|---|---|
| Controller | 接请求、取参数、返回响应 | 不写复杂业务和 SQL |
| Service | 业务逻辑、流程编排 | 不直接到处写复杂查询 |
| Repository | 数据访问、查询封装 | 不写业务决策 |
| Model / AR | 表映射、基础 ORM 能力 | 不承担完整业务流程 |

---

### 1.2 为什么 Service 不直接写 SQL？

如果 Service 直接写查询：

```php
class OrderService
{
    public function getOrderDetail(string $orderNo): array
    {
        $order = Order::find()
            ->where(['order_no' => $orderNo])
            ->one();

        // 后面还有很多业务逻辑...
    }
}
```

短期看方便，长期会有问题：

- SQL / AR 查询散落在多个 Service
- 同一个查询逻辑重复写
- 查询字段变更时要改很多地方
- Service 变得又管业务又管数据
- 测试和维护困难

更好的方式：

```php
class OrderService
{
    public function getOrderDetail(string $orderNo): array
    {
        $order = OrderRepository::instance()->getOrderObjByNo($orderNo);

        // Service 专心处理业务
    }
}
```

---

### 1.3 Repository 负责什么？

Repository 主要负责：

- 根据 ID / 编号查询对象
- 封装列表查询
- 封装 JOIN 查询
- 封装分页查询
- 封装保存 / 更新 / 删除
- 隔离 Model / AR 细节
- 给 Service 提供稳定的数据访问方法

例如：

```php
class OrderRepository
{
    public function getOrderObjByNo(string $orderNo): ?Order
    {
        return Order::find()
            ->where(['order_no' => $orderNo])
            ->one();
    }
}
```

Service 不需要知道内部怎么查，只调用方法。

---

### 1.4 Repository 不应该做什么？

Repository 不应该做复杂业务决策。

不推荐：

```php
class OrderRepository
{
    public function canUserRefund(string $orderNo): bool
    {
        // 判断订单状态、售后规则、支付状态、时间窗口...
    }
}
```

这种更像业务规则，应该放 Service 或策略类中。

Repository 应该更接近：

```text
查什么数据
按什么条件查
如何保存数据
```

而不是：

```text
业务上能不能退款
业务上能不能发货
业务上是否符合活动规则
```

---

### 1.5 常见 Repository 命名

常见方法命名：

| 方法名 | 含义 |
|---|---|
| `getById($id)` | 根据 ID 获取 |
| `getOrderObjByNo($orderNo)` | 根据订单号获取订单对象 |
| `findByUserId($userId)` | 根据用户 ID 查询 |
| `listByStatus($status)` | 根据状态查询列表 |
| `countByUserId($userId)` | 统计用户订单数量 |
| `save($model)` | 保存 |
| `updateById($id, $data)` | 根据 ID 更新 |
| `deleteById($id)` | 根据 ID 删除 |

命名目标：

> 让 Service 一眼看懂这个方法能拿到什么数据。

---

### 1.6 Repository 和 ActiveRecord 的关系

ActiveRecord 是底层 ORM 能力。

Repository 是项目封装层。

```text
OrderRepository
  ↓ 调用
Order::find()
  ↓ 生成 SQL
MySQL
```

Repository 不是替代 ActiveRecord，而是把 ActiveRecord 查询封装起来。

---

### 1.7 Repository 和 DAO 类比

Node 项目里你可能见过 DAO：

```js
class OrderDao {
  async findByOrderNo(orderNo) {
    return Order.findOne({ where: { order_no: orderNo } });
  }
}
```

PHP Repository 类似：

```php
class OrderRepository
{
    public function getOrderObjByNo(string $orderNo): ?Order
    {
        return Order::find()->where(['order_no' => $orderNo])->one();
    }
}
```

| PHP | Node 类比 |
|---|---|
| Repository | DAO / Repository |
| ActiveRecord Model | Sequelize Model |
| Service | NestJS Service |
| `getOrderObjByNo()` | `findByOrderNo()` |

---

## 2. 源码阅读

- `mall-core/common/repositorys/order/OrderRepository.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

### 2.1 阅读目标

今天重点看：

1. class 是否继承 `BaseRepository`
2. 用了哪些 Model
3. public 方法有哪些
4. 方法命名有什么规律
5. 哪些方法返回单个对象
6. 哪些方法返回列表
7. 哪些方法做复杂查询
8. Service 为什么会调用这些方法

---

### 2.2 Repository 方法清单

整理表格：

| 方法名 | 入参 | 返回 | 查询对象 | 业务含义 |
|---|---|---|---|---|
| `getOrderObjByNo` | 订单号 | 订单对象 | Order | 根据订单号查订单 |
|  |  |  |  |  |
|  |  |  |  |  |

---

### 2.3 拆解一个方法

任选一个方法，写：

| 拆解项 | 记录 |
|---|---|
| 方法名 |  |
| 参数 |  |
| 使用的 Model |  |
| 查询条件 |  |
| 是否 JOIN |  |
| 是否排序 |  |
| 返回类型 |  |
| Service 为什么需要它 |  |

---

## 3. 练习任务

### 练习 1：找 `getOrderObjByNo` 等方法

在 `OrderRepository.php` 中搜索：

```text
getOrderObjByNo
get
find
list
count
update
save
```

把方法按类型分类：

| 类型 | 方法 |
|---|---|
| 查单个对象 |  |
| 查列表 |  |
| 统计 |  |
| 更新 |  |
| 保存 |  |

---

### 练习 2：解释为什么 Service 不直接 SQL

用自己的话写：

```text
如果 Service 直接写 SQL，会导致：
1. 
2. 
3. 

使用 Repository 的好处是：
1. 
2. 
3. 
```

---

### 练习 3：写一个简化 Repository

```php
<?php

declare(strict_types=1);

class OrderRepository
{
    public function findByOrderNo(string $orderNo): ?array
    {
        // 这里用假数据模拟 DB 查询
        $orders = [
            'O001' => ['order_no' => 'O001', 'status' => 1],
        ];

        return $orders[$orderNo] ?? null;
    }
}

class OrderService
{
    public function __construct(
        private OrderRepository $orderRepository,
    ) {}

    public function getOrderStatusText(string $orderNo): string
    {
        $order = $this->orderRepository->findByOrderNo($orderNo);

        if ($order === null) {
            return '订单不存在';
        }

        return $order['status'] === 1 ? '已支付' : '未支付';
    }
}
```

重点看：

- Repository 管数据
- Service 管业务文案和状态判断

---

### 练习 4：写 Repository 职责表

| 应该放 Repository | 应该放 Service |
|---|---|
| 根据订单号查订单 | 判断订单是否能退款 |
| 查询订单列表 | 计算订单展示文案 |
| 统计订单数量 | 编排下单流程 |
| 保存订单 | 调用支付服务 |

继续补充 5 行。

---

## 4. JS/Node.js 类比

| PHP Repository | Node/JS 类比 | 差异 |
|---|---|---|
| Repository | DAO / Prisma repository | 命名因项目而异 |
| `getOrderObjByNo()` | `findByOrderNo()` | PHP 项目可能强调返回 Obj |
| ActiveRecord 查询 | Sequelize/Prisma 查询 | ORM API 不同 |
| Service 调 Repository | NestJS Service 调 DAO | 分层思想一致 |
| BaseRepository | BaseDAO | 封装通用查询能力 |

---

## 5. AI Review 提问

```text
我正在学习 Repository 模式。

我阅读了 OrderRepository.php，并整理了方法清单和职责边界。
请你按资深 PHP 后端工程师标准帮我检查：

1. 我对 Repository 职责的理解是否正确？
2. 我分类的方法是否合理？
3. 哪些逻辑应该放 Service，而不是 Repository？
4. Repository 和 DAO / Prisma repository 的类比是否准确？
5. 真实项目里 Repository 命名和返回类型要注意什么？

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 6. 今日产出

- [ ] `OrderRepository.php` 方法清单
- [ ] `getOrderObjByNo` 方法拆解
- [ ] Repository vs Service 职责表
- [ ] 简化版 Repository 练习代码
- [ ] Repository vs DAO 类比笔记
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 Repository 是什么
- [ ] 能解释为什么 Service 不直接 SQL
- [ ] 能说出 Repository 应该做什么
- [ ] 能说出 Repository 不应该做什么
- [ ] 能读懂一个 Repository 方法的大概含义
- [ ] 能按方法名判断查询目的
- [ ] 能用 DAO 层类比 Repository

---

## 8. 今日自测题

### 8.1 Repository 主要负责什么？

参考答案：封装数据库访问和查询逻辑。

### 8.2 Service 主要负责什么？

参考答案：业务逻辑和流程编排。

### 8.3 为什么不建议 Service 直接写 SQL？

参考答案：会让查询逻辑分散、重复、难维护，Service 职责也会变混乱。

### 8.4 `getOrderObjByNo` 从命名看是什么意思？

参考答案：根据订单号获取订单对象。

### 8.5 Repository 和 ActiveRecord 是什么关系？

参考答案：Repository 封装 ActiveRecord 查询，给 Service 提供更稳定的数据访问方法。

### 8.6 Repository 可以写业务判断吗？

参考答案：不建议。复杂业务判断应放在 Service 或策略类中。

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
我正在进行 Week 03 Day 03：Repository 模式 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 03 README](./README.md)
