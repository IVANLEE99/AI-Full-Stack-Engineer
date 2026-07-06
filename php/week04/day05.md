# Week 04 Day 05：阶段总结与类比日

> 所属周：Week 04：配置中心 + 站点 API  
> 阶段：第一阶段：PHP + Yii2/TP 基础  
> 主仓库/项目：`mall-core`  
> 类型：类比日  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

整理 Week 01 到 Week 04 的学习笔记，独立读通一条 CSR 链路，并用 JS/Node.js 的分层思维类比 PHP 企业项目中的 Controller、Service、Repository、Model。

今天你要真正掌握这一句话：

> CSR 链路不是几个目录名的组合，而是一条请求从 Controller 进入、由 Service 组织业务、通过 Repository/Model 读取数据、最后返回响应的后端工程主线。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Week 01：PHP 语法、Composer、OOP、namespace
2. 回顾 Week 02：Yii2 入口、Controller、路由、Filter
3. 回顾 Week 03：Service、Repository、Model、业务阅读
4. 回顾 Week 04：配置读取、配置 API、配置中心
5. 选择一条真实或模拟接口链路
6. 按 Controller → Service → Repository → Model 追踪
7. 用 Node/Express 分层方式做类比
8. 整理配置项清单和阶段总结
9. 用 AI Review 检查是否可以进入下一阶段

---

## 1. 学习内容

### 1.1 为什么要做阶段总结？

学习后端很容易出现一种情况：

```text
每天都看了一些概念，但串不起来。
```

阶段总结的目的就是把零散知识串成一条线。

你前 4 周已经接触到：

| 周次 | 主题 | 你应该能掌握什么 |
|---|---|---|
| Week 01 | PHP 基础 | 变量、类型、函数、OOP、Composer |
| Week 02 | Yii2 基础 | 入口、路由、Controller、Filter |
| Week 03 | 项目分层 | Service、Repository、Model、业务链路 |
| Week 04 | 配置中心 | `g_config()`、配置 API、动态配置 |

今天要做的是：把这些知识合并成“我能读懂一个 PHP 接口”的能力。

---

### 1.2 CSR 是什么？

这里的 CSR 指：

```text
Controller → Service → Repository → Model
```

不要和前端里的 Client Side Rendering 混淆。

在后端项目里，它可以这样理解：

| 层 | 作用 | 类比 |
|---|---|---|
| Controller | 接收 HTTP 请求，返回响应 | Express route/controller |
| Service | 组织业务逻辑 | service class |
| Repository | 封装数据查询 | DAO / repository |
| Model | 表示数据表或领域对象 | ORM model |

请求链路：

```text
前端请求
  ↓
Controller
  ↓
Service
  ↓
Repository
  ↓
Model / DB
  ↓
返回数据
```

小白重点：读项目时，你不是在读“文件”，而是在追踪“请求怎么流动”。

---

### 1.3 Controller 负责什么？

Controller 是 HTTP 入口。

它通常负责：

- 接收请求参数
- 做基础参数校验
- 调用 Service
- 返回统一响应

示例：

```php
<?php

public function actionDetail(): array
{
    $orderId = (int)$this->request->get('order_id');

    $detail = $this->orderService->getDetail($orderId);

    return $this->success($detail);
}
```

Controller 不应该写太多复杂业务，例如：

- 订单金额计算
- 库存扣减
- 优惠券核销
- 大量 SQL 查询

这些应该下沉到 Service 或更底层。

---

### 1.4 Service 负责什么？

Service 是业务组织层。

它通常负责：

- 编排多个 Repository/Model
- 处理业务规则
- 组合返回数据
- 调用配置、缓存、第三方服务

示例：

```php
<?php

final class OrderService
{
    public function getDetail(int $orderId): array
    {
        $order = $this->orderRepository->findById($orderId);
        $items = $this->orderItemRepository->findByOrderId($orderId);

        return [
            'order' => $order,
            'items' => $items,
        ];
    }
}
```

小白重点：Service 是你理解业务的核心入口。

---

### 1.5 Repository 和 Model 负责什么？

Repository 更关注“怎么查数据”。

Model 更关注“数据结构或 ORM 映射”。

示例：

```php
<?php

final class OrderRepository
{
    public function findById(int $orderId): ?Order
    {
        return Order::findOne(['id' => $orderId]);
    }
}
```

这里：

- `OrderRepository` 封装查询方法
- `Order` 可能是 ActiveRecord Model
- Service 不需要知道复杂查询细节

分层的好处：

| 好处 | 说明 |
|---|---|
| 易读 | 每层职责清楚 |
| 易改 | 查询变化时主要改 Repository |
| 易测 | Service 可以 mock Repository |
| 易复用 | 多个 Service 可复用查询方法 |

---

### 1.6 如何独立读通一条 CSR 链路？

按这个步骤来：

1. 找接口 URL 或 Controller 方法
2. 看 Controller 接收哪些参数
3. 找 Controller 调用了哪个 Service
4. 跳到 Service 方法，记录业务步骤
5. 找 Service 调用了哪些 Repository/Model
6. 看 Repository 查询了哪些表或字段
7. 回到 Service，看返回结构
8. 回到 Controller，看响应格式

记录模板：

| 层级 | 文件/类/方法 | 作用 |
|---|---|---|
| Controller |  |  |
| Service |  |  |
| Repository |  |  |
| Model |  |  |
| Response |  |  |

---

### 1.7 把配置链路放进 CSR

Week 04 学的配置并不是孤立知识，它会出现在 Service 或 Controller 中。

例如：

```php
<?php

$showCoupon = g_config('site', 'show_coupon', false);
```

它可能影响 Service 返回：

```php
<?php

return [
    'show_coupon' => $showCoupon,
    'goods' => $goodsList,
];
```

所以完整链路可能是：

```text
Controller
  ↓
Service
  ↓     ↘
Repository   g_config()
  ↓          ↓
Model/DB     配置中心/缓存
  ↓          ↓
返回组合数据给前端
```

---

### 1.8 Node.js 类比

Node/Express 项目可能这样分层：

```text
router/controller → service → repository/dao → model/db
```

PHP 项目中的 CSR 类似：

```text
Controller → Service → Repository → Model
```

对比：

| PHP | Node.js |
|---|---|
| Controller | route handler / controller |
| Service | service class |
| Repository | dao / repository |
| Model | Sequelize/Prisma model |
| `g_config()` | config service / feature flag |

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议你从前几周读过的接口里任选一条，例如：

- 配置 API 链路
- 订单详情链路
- 商品列表链路
- 用户信息链路

记录：

| 链路节点 | 记录 |
|---|---|
| 请求 URL/入口方法 |  |
| Controller |  |
| Service |  |
| Repository |  |
| Model/数据表 |  |
| 配置读取 |  |
| 返回字段 |  |

---

## 3. 练习任务

### 练习 1：独立读通一条 CSR 链路

按这个格式输出：

```text
我选择的接口：
Controller：
Service：
Repository：
Model/表：
配置读取：
返回字段：
我不理解的地方：
```

### 练习 2：完成类比打卡

| PHP 概念 | JS/Node 类比 | 差异 |
|---|---|---|
| Controller |  |  |
| Service |  |  |
| Repository |  |  |
| Model |  |  |
| Composer |  |  |
| namespace |  |  |
| `g_config()` |  |  |

### 练习 3：列配置项清单

| module | key | default | 影响页面/业务 | 风险 |
|---|---|---|---|---|
|  |  |  |  |  |
|  |  |  |  |  |
|  |  |  |  |  |

---

## 4. JS/Node.js 类比

- CSR ≈ Controller → Service → Repository → Model
- Controller ≈ Express route/controller
- Service ≈ 业务 service
- Repository ≈ DAO/Prisma/Sequelize 查询封装
- Model ≈ ORM Model
- 配置读取 ≈ config service / feature flags

---

## 5. AI Review 提问

```text
我正在做 Week 01-04 阶段总结。
我已经独立追踪了一条 Controller → Service → Repository → Model 链路，并整理了 JS/Node 类比。
请你检查：
1. 我的 CSR 链路是否完整？
2. 每一层职责是否理解正确？
3. 是否把 Controller 和 Service 的职责混淆了？
4. 我的 Node.js 类比是否准确？
5. 我是否已经具备进入 BFF/微服务阶段的基础？
```

---

## 6. 今日产出

- [ ] CSR 链路笔记
- [ ] JS/Node 类比打卡表
- [ ] 配置项清单
- [ ] Week 01-04 阶段总结
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能独立读通一条 CSR 链路
- [ ] 能解释 Controller、Service、Repository、Model 的职责
- [ ] 能说出配置读取在链路中可能出现的位置
- [ ] 能把 PHP 分层和 Node.js 分层做准确类比
- [ ] 能整理 Week 01-04 的学习收获和薄弱点

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
我正在进行 Week 04 Day 05：阶段总结与类比日 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 04 README](./README.md)
