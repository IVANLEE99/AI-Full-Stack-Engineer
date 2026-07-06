# Week 06 Day 02：OrderService 业务编排

> 所属周：Week 06：订单域  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`mall-core`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 `OrderService` 在订单域中的业务编排职责，能读懂 Service 方法如何组织参数校验结果、库存/金额/优惠/地址等业务信息，并按统一格式返回给 Controller。

今天你要真正掌握这一句话：

> `OrderService` 是订单业务的编排中心：Controller 只负责入口，Repository/Model 只负责数据，而订单创建、确认、状态判断、返回结构组织通常应该由 Service 来承接。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾昨天 `OrderController` 的 action 链路
2. 理解 Service 层为什么是订单业务核心
3. 打开 `OrderService.php`，先看 public 方法列表
4. 读前 200 行，记录方法名、参数、返回值
5. 理解 `['code', 'data', 'info']` 这类统一业务响应
6. 画出 Service 调用 Repository/Model 的关系
7. 判断哪些逻辑应该放在 Service 而不是 Controller
8. 用 NestJS Service 做类比
9. 用 AI Review 检查方法职责是否理解准确

---

## 1. 学习内容

### 1.1 Service 层解决什么问题？

如果 Controller 直接处理订单业务，会变得很厚：

```php
<?php

// 不推荐：Controller 里做太多业务
// 查商品、查库存、算价格、校验优惠券、创建订单、写明细、返回结果
```

更好的方式是：

```php
<?php

$result = $this->orderService->create($params);

if ($result['code'] !== 0) {
    return $this->endFail($result['info']);
}

return $this->endSuccess($result['data']);
```

Controller 只管入口，Service 负责业务编排。

---

### 1.2 OrderService 常见职责

订单 Service 通常负责：

| 职责 | 示例 |
|---|---|
| 组织下单参数 | 用户、商品、地址、优惠券 |
| 校验业务规则 | 是否可下单、库存是否足够 |
| 计算或调用金额计算 | 商品金额、优惠、运费、应付金额 |
| 创建订单 | 订单主表、订单明细 |
| 状态判断 | 待支付、已支付、已取消 |
| 调用 Repository | 查询订单、写订单数据 |
| 返回统一结果 | `code/data/info` |

小白重点：Service 是读懂订单业务的关键位置。

---

### 1.3 `['code','data','info']` 返回格式怎么理解？

很多项目会让 Service 返回类似：

```php
<?php

return [
    'code' => 0,
    'data' => [
        'order_id' => 1001,
    ],
    'info' => 'success',
];
```

失败时：

```php
<?php

return [
    'code' => 10001,
    'data' => null,
    'info' => '库存不足',
];
```

三个字段含义：

| 字段 | 含义 |
|---|---|
| `code` | 业务状态码，0 通常表示成功 |
| `data` | 成功时返回的数据 |
| `info` | 成功或失败说明，失败时给前端提示 |

注意：这是业务响应包装，不等同于 HTTP 状态码。

---

### 1.4 Service 如何调用 Repository？

常见链路：

```text
OrderService
  ↓
OrderRepository
  ↓
Order Model / DB
```

示例：

```php
<?php

final class OrderService
{
    public function detail(int $orderId): array
    {
        $order = $this->orderRepository->findById($orderId);

        if ($order === null) {
            return [
                'code' => 404,
                'data' => null,
                'info' => '订单不存在',
            ];
        }

        return [
            'code' => 0,
            'data' => $order,
            'info' => 'success',
        ];
    }
}
```

Service 关心业务判断，Repository 关心数据查询。

---

### 1.5 哪些逻辑应该在 Service？

适合放在 Service：

| 逻辑 | 原因 |
|---|---|
| 判断订单是否存在 | 业务判断 |
| 判断订单是否可支付 | 状态规则 |
| 组织订单详情返回结构 | 业务聚合 |
| 调用多个 Repository | 编排数据来源 |
| 调用配置/库存/优惠能力 | 业务编排 |
| 返回业务错误码 | 统一业务语义 |

不适合放在 Service：

- 直接处理 HTTP request/response
- 写大量 HTML/前端展示逻辑
- 直接拼接路由
- 负责 Controller 鉴权入口

---

### 1.6 阅读 OrderService 的方法清单

读源码时先列 public 方法：

| 方法名 | 参数 | 返回 | 你理解的职责 |
|---|---|---|---|
|  |  |  |  |
|  |  |  |  |

不要急着看懂每个私有方法，先建立地图。

读每个方法时问：

1. 它给哪个 Controller/action 调用？
2. 它处理哪个业务场景？
3. 它调用了哪些 Repository/Model？
4. 它返回 `code/data/info` 吗？
5. 它有没有事务、锁、状态判断？

---

### 1.7 Node.js / NestJS 类比

NestJS 中：

```ts
@Injectable()
export class OrderService {
  async create(dto: CreateOrderDto) {
    // 校验业务、调用 repository、返回结果
  }
}
```

PHP 中：

```php
<?php

final class OrderService
{
    public function create(array $params): array
    {
        // 校验业务、调用 repository、返回结果
    }
}
```

类比：

| PHP | Node/NestJS |
|---|---|
| `OrderController` | Controller |
| `OrderService` | Injectable Service |
| Repository | Repository/DAO/Prisma service |
| `code/data/info` | service result object |

---

## 2. 源码阅读

- `mall-core/common/services/order/OrderService.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| public 方法 | 参数 | 返回格式 | 调用的 Repository/Model | 职责 |
|---|---|---|---|---|
|  |  |  |  |  |
|  |  |  |  |  |
|  |  |  |  |  |

---

## 3. 练习任务

### 练习 1：读 OrderService 前 200 行

记录你看到的：

```text
类名：
依赖对象：
public 方法：
私有辅助方法：
统一返回格式：
```

### 练习 2：列 5 个 public 方法及职责

| 方法 | 你理解的职责 | 输入 | 输出 |
|---|---|---|---|
|  |  |  |  |

### 练习 3：画 Service 调用 Repository 关系

```text
OrderService::xxx()
  ↓
OrderRepository::xxx()
  ↓
Order Model / DB
```

---

## 4. JS/Node.js 类比

- `OrderService` ≈ NestJS Service
- Repository ≈ Prisma/TypeORM Repository
- `['code','data','info']` ≈ 统一 service result object
- Controller 调 Service ≈ route handler 调 service method

---

## 5. AI Review 提问

```text
我正在阅读 OrderService。
我已经列出 5 个 public 方法、职责、参数、返回格式和 Repository 调用关系。
请你检查：
1. 我对 Service 层职责的理解是否正确？
2. 哪些逻辑应该在 Service 而不是 Controller？
3. 哪些逻辑应该继续下沉到 Repository/Model？
4. code/data/info 返回格式有什么优缺点？
5. 真实订单服务里最容易出现哪些设计问题？
```

---

## 6. 今日产出

- [ ] `OrderService` 方法清单
- [ ] 5 个 public 方法职责说明
- [ ] Service → Repository 调用关系图
- [ ] `code/data/info` 返回格式理解笔记
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能列出 5 个 `OrderService` 方法职责
- [ ] 能解释 Service 层为什么存在
- [ ] 能说明 `code/data/info` 的含义
- [ ] 能画出 Service 调用 Repository 的关系
- [ ] 能判断哪些逻辑应在 Service 而非 Controller

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
我正在进行 Week 06 Day 02：OrderService 业务编排 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 06 README](./README.md)
