# Week 05 Day 01：BFF 模式与网关职责

> 所属周：Week 05：BFF 网关架构  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`mall-gateway`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 BFF 为什么存在，能说清楚「前端」「BFF 网关」「内网微服务」各自负责什么，并能判断一个逻辑应该放在网关还是放在后端业务服务。

今天你要真正掌握这一句话：

> BFF（Backend For Frontend）不是简单的“转发层”，而是专门为前端页面/客户端定制 API 的后端层；它负责协议适配、参数整理、接口聚合、鉴权入口和响应格式统一，但不应该承载核心业务规则。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解没有 BFF 时前端会遇到什么问题
2. 理解 BFF 的定义：Backend For Frontend
3. 理解网关、BFF、微服务之间的关系
4. 画出「前端 → BFF → 内网服务」调用链
5. 阅读 `mall-gateway/common/BaseApi.php` 的公共能力
6. 判断哪些职责适合放在 BFF，哪些不适合
7. 用 Express 聚合层类比 PHP 网关
8. 完成 BFF 架构图和职责清单
9. 用 AI Review 检查你的边界判断

---

## 1. 学习内容

### 1.1 如果没有 BFF，前端会遇到什么问题？

假设一个商城首页需要展示：

- 用户信息
- banner 列表
- 商品分类
- 推荐商品
- 购物车数量

如果没有 BFF，前端可能要直接请求很多内网接口：

```text
GET /user/profile
GET /site/banner
GET /goods/categories
GET /goods/recommend
GET /cart/count
```

这会带来几个问题：

| 问题 | 表现 |
|---|---|
| 请求太多 | 页面加载慢，前端代码复杂 |
| 接口粒度不适合页面 | 后端服务返回的是业务数据，不一定刚好适合 UI |
| 多端差异难处理 | H5、小程序、App 需要的数据结构可能不同 |
| 安全边界模糊 | 前端不应该知道太多内网服务细节 |
| 错误处理分散 | 每个接口失败都要前端单独处理 |

BFF 的价值就是：在前端和内网服务之间加一层「面向前端场景」的后端。

---

### 1.2 BFF 是什么？

BFF 全称是：

```text
Backend For Frontend
```

直译是：

```text
专门服务前端的后端
```

它通常做这些事情：

1. 接收前端请求
2. 校验登录态、签名、参数
3. 调用一个或多个内网服务
4. 整理成前端需要的结构
5. 返回统一 JSON 响应

可以把 BFF 想成前端的「专属后端助手」。

例如前端只想要一个接口：

```text
GET /frontapi/home
```

BFF 内部再去调用：

```text
site-service: getBanner()
goods-service: getCategories()
goods-service: getRecommendGoods()
cart-service: getCartCount()
```

最后返回：

```json
{
  "banner": [],
  "categories": [],
  "recommend_goods": [],
  "cart_count": 3
}
```

小白重点：BFF 的接口形态通常更贴近页面，而微服务接口通常更贴近业务能力。

---

### 1.3 BFF、网关、微服务怎么分工？

先看整体结构：

```text
浏览器 / App / 小程序
        ↓
BFF / API Gateway（mall-gateway）
        ↓
订单服务 / 商品服务 / 支付服务 / 用户服务
        ↓
数据库 / 缓存 / MQ / 第三方接口
```

职责可以这样理解：

| 层级 | 主要职责 | 不应该做什么 |
|---|---|---|
| 前端 | 展示页面、收集用户输入 | 不直接访问内网服务 |
| BFF/网关 | 鉴权、参数校验、接口聚合、响应适配 | 不写复杂核心业务规则 |
| 微服务 | 处理订单、支付、库存等核心业务 | 不关心具体页面怎么展示 |
| 数据层 | 存储和查询数据 | 不承担接口编排 |

举例：

- 「用户是否登录」适合在 BFF 做入口校验
- 「订单能不能支付」应该在订单/支付服务做
- 「首页要返回哪些字段」适合在 BFF 做适配
- 「库存是否足够」应该在库存/商品服务做

---

### 1.4 frontapi 和内网服务怎么理解？

在很多 PHP 企业项目里，你会看到类似：

```text
frontapi  → 面向前端用户的接口
adminapi  → 面向后台管理系统的接口
service   → 内部业务服务
```

`frontapi` 的特点：

- URL 面向页面或客户端场景
- 返回结构更适合前端直接使用
- 通常需要处理登录态、设备、语言、渠道等信息
- 不应该暴露内部服务的复杂字段

内网服务的特点：

- 更关注业务能力
- 接口可能更细、更稳定
- 不直接暴露给公网前端
- 负责真正的数据修改和业务校验

一个常见例子：

```text
前端请求：GET /frontapi/order/detail?id=1001

BFF 内部可能调用：
- order-service：查询订单主体
- pay-service：查询支付状态
- logistics-service：查询物流信息
- user-service：查询收货人展示信息
```

BFF 最终把这些数据整理成页面需要的订单详情。

---

### 1.5 网关适合承担哪些职责？

适合放在网关/BFF 的职责：

| 职责 | 说明 |
|---|---|
| 统一鉴权入口 | 检查 token/session 是否有效 |
| 参数基础校验 | 检查必填参数、类型、格式 |
| 接口聚合 | 一个前端接口聚合多个后端服务 |
| 响应格式统一 | 统一 `code/message/data` |
| 协议适配 | HTTP JSON 转内部 RPC/Service 调用 |
| 灰度/渠道适配 | H5、App、小程序返回不同字段 |
| 限流/风控入口 | 做基础频率控制或风险标记 |

例如 PHP 伪代码：

```php
<?php

declare(strict_types=1);

final class HomeApi extends BaseApi
{
    public function index(): array
    {
        $userId = $this->requireLogin();

        return [
            'banner' => $this->siteService->getBanners(),
            'categories' => $this->goodsService->getCategories(),
            'cart_count' => $this->cartService->countByUser($userId),
        ];
    }
}
```

这里 BFF 做的是：登录校验 + 多服务聚合 + 返回页面结构。

---

### 1.6 网关不适合承担哪些职责？

不适合放在网关/BFF 的职责：

| 不适合的职责 | 应该放在哪里 | 原因 |
|---|---|---|
| 订单价格计算 | 订单服务 | 价格规则是核心业务 |
| 库存扣减 | 库存/商品服务 | 需要事务和并发控制 |
| 支付状态流转 | 支付服务 | 涉及资金和状态机 |
| 优惠券核销 | 营销/优惠券服务 | 涉及业务规则和一致性 |
| 数据库事务 | 具体业务服务 | BFF 不应该直接控制多个服务数据库 |

错误示例：

```php
// 不推荐：BFF 里直接计算最终订单金额
$finalAmount = $goodsPrice - $couponAmount + $shippingFee;
```

更好的方式：

```php
// 推荐：让订单服务计算，BFF 只负责调用和展示
$orderPreview = $this->orderService->preview($userId, $goodsItems, $couponId);
```

小白重点：BFF 可以“编排”，但不要“拥有核心业务规则”。

---

### 1.7 阅读 `BaseApi.php` 时看什么？

今天指定阅读：

```text
mall-gateway/common/BaseApi.php
```

不要一上来就试图看懂每一行。先找这些问题的答案：

| 阅读问题 | 你要找什么 |
|---|---|
| 它是不是所有 API 的基类？ | 看类名、继承关系、Controller 基类 |
| 是否有统一响应方法？ | 如 `success()`、`error()`、`json()` |
| 是否有参数读取方法？ | 如 `getParam()`、`post()`、`request()` |
| 是否有登录校验？ | 如 `getUserId()`、`checkLogin()` |
| 是否处理异常？ | try/catch、错误码、日志 |

建议你边读边记录：

| 方法名 | 作用 | 属于网关职责吗？ | 备注 |
|---|---|---|---|
|  |  |  |  |

如果看不懂方法内部实现，先写下“这个方法大概负责什么”，不要卡死在细节。

---

### 1.8 Node.js / Express 类比

如果你熟悉 Node，可以把 BFF 类比成 Express 聚合层：

```js
app.get('/frontapi/home', async (req, res) => {
  const userId = req.user.id;

  const [banner, categories, cartCount] = await Promise.all([
    siteService.getBanners(),
    goodsService.getCategories(),
    cartService.countByUser(userId),
  ]);

  res.json({
    code: 0,
    data: { banner, categories, cartCount },
  });
});
```

PHP BFF 做的事情类似，只是框架、语法、服务调用方式不同：

```php
<?php

declare(strict_types=1);

public function home(): array
{
    $userId = $this->getUserId();

    return $this->success([
        'banner' => $this->siteService->getBanners(),
        'categories' => $this->goodsService->getCategories(),
        'cart_count' => $this->cartService->countByUser($userId),
    ]);
}
```

类比要点：

| PHP BFF | Node/Express 类比 |
|---|---|
| Controller / Api 类 | Express route handler |
| BaseApi | 公共 middleware + response helper |
| Service 调用 | 调用内部 service/client |
| `success()` | `res.json()` 的统一封装 |
| 登录校验 | auth middleware |

---

## 2. 源码阅读

- `mall-gateway/common/BaseApi.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读目标：

| 文件 | 重点 |
|---|---|
| `BaseApi.php` | 找统一响应、参数读取、登录校验、错误处理 |

阅读记录：

| 方法/属性 | 你理解的作用 | 是否属于网关公共能力 |
|---|---|---|
|  |  |  |
|  |  |  |
|  |  |  |

---

## 3. 练习任务

### 练习 1：画 BFF 架构图

用文本或画图工具画出：

```text
前端 H5 / App / 小程序
        ↓
mall-gateway / frontapi
        ↓
用户服务 / 商品服务 / 订单服务 / 支付服务
        ↓
MySQL / Redis / MQ / 第三方支付
```

要求：每一层写 2-3 个职责。

### 练习 2：列出网关 5 项职责

参考表格：

| 职责 | 示例 | 是否核心业务 |
|---|---|---|
| 鉴权 | 检查 token | 否 |
| 参数校验 | 检查 `goods_id` | 否 |
| 接口聚合 | 首页聚合 banner + 商品 | 否 |
| 响应统一 | `code/message/data` | 否 |
| 业务规则 | 库存扣减 | 是，不应放网关 |

### 练习 3：判断逻辑应该放哪里

请判断下面逻辑应该放在 BFF 还是微服务：

| 逻辑 | 放哪里 | 原因 |
|---|---|---|
| 检查用户是否登录 |  |  |
| 计算订单最终金额 |  |  |
| 首页聚合多个服务结果 |  |  |
| 扣减库存 |  |  |
| 统一返回 JSON 格式 |  |  |

### 练习 4：阅读 `BaseApi.php`

找出至少 3 个公共方法，并写下：

```text
方法名：
作用：
为什么适合放在 BaseApi：
如果没有它，每个接口会重复写什么：
```

---

## 4. JS/Node.js 类比

| BFF 概念 | Node.js 类比 | 注意差异 |
|---|---|---|
| BFF 网关 | Express/NestJS 聚合层 | PHP 项目可能用 Yii2/TP/Swoole 等实现 |
| BaseApi | middleware + response helper | PHP 常通过继承基类复用 |
| frontapi | 面向前端的 routes | 命名和目录结构因项目而异 |
| 内网服务 | service/client 调用 | 可能是 RPC、HTTP、SDK 或类调用 |
| 统一响应 | `res.json({ code, data })` | 企业项目通常有统一错误码体系 |

---

## 5. AI Review 提问

```text
我正在学习 BFF 模式与网关职责。
我已经画了“前端 → BFF → 微服务”的架构图，并列出了网关职责。
请你检查：
1. 我对 BFF 的理解是否正确？
2. 哪些职责我错误地放到了网关？
3. 哪些职责应该下沉到订单/支付/库存等微服务？
4. 我的 Node/Express 类比是否准确？
5. 真实企业项目中 BFF 最容易变成“大泥球”的原因是什么？

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 6. 今日产出

- [ ] BFF 架构图
- [ ] 网关 5 项职责清单
- [ ] BFF vs 微服务职责边界表
- [ ] `BaseApi.php` 阅读笔记
- [ ] 1 个首页聚合接口伪代码
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能用自己的话解释 BFF 是什么
- [ ] 能说明为什么前端不应该直接调用多个内网服务
- [ ] 能区分 BFF、网关、微服务的职责
- [ ] 能列出至少 5 个网关适合承担的职责
- [ ] 能列出至少 3 个不应该放在网关的核心业务逻辑
- [ ] 能读懂 `BaseApi.php` 至少 3 个公共方法的目的
- [ ] 能用 Express 聚合层类比 PHP BFF

---

## 8. 今日自测题

### 8.1 BFF 的全称是什么？

参考答案：Backend For Frontend，专门服务前端的后端层。

### 8.2 BFF 和微服务最大的区别是什么？

参考答案：BFF 面向前端页面/客户端场景，负责聚合和适配；微服务面向核心业务能力，负责真正的业务规则和数据一致性。

### 8.3 为什么订单金额计算不应该放在 BFF？

参考答案：订单金额是核心业务规则，涉及优惠、运费、价格、活动和一致性，应该由订单服务或定价服务负责。

### 8.4 `BaseApi.php` 这类基类通常解决什么问题？

参考答案：统一参数读取、登录校验、响应格式、错误处理等公共逻辑，避免每个接口重复实现。

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
我正在进行 Week 05 Day 01：BFF 模式与网关职责 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 05 README](./README.md)
