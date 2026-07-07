# Week 11 Day 06：store-api CRUD 项目

> 所属周：Week 11：ThinkPHP 8 门店 API  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`store-api`  
> 类型：项目实战  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

独立选择一个门店或商品接口，追踪完整 CRUD 链路，画出从路由、Controller、Validate、Service、Model/ModelJoin 到响应返回的流程图。

今天你要真正掌握这一句话：

> 能独立追踪 TP8 CRUD 链路，说明你已经能把 Yii2 阶段积累的业务域阅读方法迁移到新框架；以后遇到 Laravel、Symfony、TP8 也能按同一套分层思维拆解。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 选择一个门店或商品接口
2. 找到对应路由
3. 找到 Controller action
4. 找到 Validate scene
5. 找到 Service 方法
6. 找到 Model / ModelJoin 查询或写入
7. 记录入参、出参、错误返回
8. 画接口流程图
9. 用 AI Review 检查链路是否完整

---

## 1. 学习内容

### 1.1 CRUD 是什么？

CRUD 指：

| 操作 | 含义 | 常见接口 |
|---|---|---|
| Create | 创建 | 新增门店 |
| Read | 查询 | 门店列表/详情 |
| Update | 更新 | 编辑门店 |
| Delete | 删除 | 删除/禁用门店 |

后台管理系统大量接口都是 CRUD。

---

### 1.2 TP8 CRUD 链路模板

```text
前端后台页面
  ↓
HTTP 请求
  ↓
route 路由
  ↓
middleware 鉴权/权限
  ↓
Controller action
  ↓
Validate scene
  ↓
Service
  ↓
Model / ModelJoin
  ↓
统一响应
```

---

### 1.3 追踪时要记录什么？

| 层级 | 记录内容 |
|---|---|
| Route | URL、HTTP 方法 |
| Middleware | 是否需要登录、权限 |
| Controller | action 名、取参、返回 |
| Validate | 使用哪个 scene |
| Service | 方法职责、业务规则 |
| Model | 表、字段、查询条件 |
| Response | code/data/msg 格式 |

---

### 1.4 CRUD 风险点

| 操作 | 风险 | 关注点 |
|---|---|---|
| Create | 重复创建、非法字段 | 唯一性、Validate |
| Read | 越权查看、查询慢 | 权限过滤、索引 |
| Update | 越权修改、脏数据 | 权限、字段白名单 |
| Delete | 误删除 | 软删除、二次确认 |

门店接口可能还要关注：

- 商户归属
- 门店状态
- 经纬度合法性
- 手机号/地址隐私
- 列表分页性能

---

### 1.5 流程图模板

```text
Route: GET /admin/store/index
  ↓
StoreController::index()
  ↓
OfflineStore Validate scene:list
  ↓
StoreService::list($params)
  ↓
StoreModelJoin::listWithMerchant($filters)
  ↓
返回分页 data
```

按你选择的真实接口修正。

---

### 1.6 迁移已有业务域思维

你已经学过：

- BFF 路由表
- 订单时序图
- 支付状态机
- 售后流程图

现在读 TP8 CRUD，也可以用同样方法：

```text
入口 → 校验 → 业务 → 数据 → 响应 → 风险
```

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议选择：

- 门店列表
- 门店新增
- 门店编辑
- 商品列表
- 商品状态切换

记录：

| 层级 | 文件/方法 | 记录 |
|---|---|---|
| Route |  |  |
| Controller |  |  |
| Validate |  |  |
| Service |  |  |
| Model |  |  |

---

## 3. 练习任务

### 练习 1：追踪完整 CRUD

选择一个接口，记录全链路。

### 练习 2：画接口流程图

要求包含 Route、Controller、Validate、Service、Model、Response。

### 练习 3：标注风险

列出该接口至少 5 个风险点。

---

## 4. JS/Node.js 类比

- CRUD 链路 ≈ 联调文档
- Controller ≈ route handler
- Validate ≈ DTO/Zod schema
- Service ≈ use case service
- ModelJoin ≈ repository query object

---

## 5. AI Review 提问

```text
我正在追踪 store-api 的一个 CRUD 接口。
我已经记录 Route、Controller、Validate scene、Service、Model/ModelJoin、Response，并画了流程图。
请你检查：
1. 链路是否完整？
2. 分层职责是否清楚？
3. 是否遗漏权限、中间件、Validate 或响应封装？
4. 这个接口有哪些性能和越权风险？
5. 这张图是否适合联调和新人 onboarding？
```

---

## 6. 今日产出

- [ ] CRUD 全链路记录
- [ ] 接口流程图
- [ ] 入参/出参表
- [ ] 风险点清单
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能独立追踪 TP8 CRUD 链路
- [ ] 能画接口流程图
- [ ] 能说明每层职责
- [ ] 能标注 Validate 和 ModelJoin
- [ ] 能列出至少 5 个接口风险点

---

## 8. 今日自测题

### 8.1 CRUD 分别指哪四种操作？

参考答案：

> ✅ Create（创建，如新增门店）、Read（查询，如门店列表/详情）、Update（更新，如编辑门店）、Delete（删除，如删除/禁用门店）。后台管理系统大量接口都是 CRUD。

---

### 8.2 追踪一条 TP8 CRUD 链路时，从入口到响应会经过哪些层？

参考答案：

> ✅ route 路由 → middleware 中间件 → Controller action → Validate scene → Service → Model / ModelJoin → 统一响应。

---

### 8.3 Delete 操作有什么典型风险，应该怎么防？

参考答案：

> ✅ 主要风险是误删除。常见做法是用软删除代替物理删除，并在前端做二次确认，同时校验数据归属，防止越权删除。

---

### 8.4 门店接口除了通用 CRUD 风险，还要额外关注哪些点？

参考答案：

> ✅ 商户归属、门店状态、经纬度合法性、手机号/地址等隐私字段、列表分页性能。

---

### 8.5 追踪 CRUD 链路可以复用前面学过的哪套业务域阅读方法？

参考答案：

> ✅ 用“入口 → 校验 → 业务 → 数据 → 响应 → 风险”这套方法，和之前读 BFF 路由表、订单时序图、支付状态机、售后流程图是同一思路。

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
我正在进行 Week 11 Day 06：store-api CRUD 项目 的学习。
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
