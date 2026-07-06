# Week 11 Day 02：StoreController 到 StoreService

> 所属周：Week 11：ThinkPHP 8 门店 API  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`store-api`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

追踪门店列表接口从 `StoreController::index()` 到 `StoreService::list()` 的完整链路，理解 TP8 项目中 Controller、Validate、Service、Model 如何分工。

今天你要真正掌握这一句话：

> 在 TP8 项目里读 CRUD 链路，仍然先从 Controller 找入口，再看参数校验和 Service 编排，最后追到 Model/ORM 查询；框架换了，分层阅读方法不变。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾昨天 TP8 目录结构
2. 找到 `StoreController.php`
3. 阅读 `index()` 方法入参、调用和返回
4. 找到 `StoreService.php` 中的 `list()` 方法
5. 记录 Controller 与 Service 的职责边界
6. 继续追踪到 Model / ModelJoin / ORM 查询
7. 记录分页、筛选、排序等列表接口常见逻辑
8. 对比 Yii2 CSR 链路和 NestJS Service
9. 用 AI Review 检查分层是否清晰

---

## 1. 学习内容

### 1.1 门店列表接口是什么？

门店列表通常是后台或管理端的 CRUD 查询接口。

它可能支持：

- 分页
- 关键词搜索
- 状态筛选
- 城市/区域筛选
- 排序
- 返回门店基础信息

接口链路可能是：

```text
GET /admin/store/index
  ↓
StoreController::index()
  ↓
StoreService::list($params)
  ↓
OfflineStore Model / ModelJoin
  ↓
返回分页列表
```

---

### 1.2 Controller 应该做什么？

`StoreController::index()` 通常负责：

| 职责 | 示例 |
|---|---|
| 获取请求参数 | page、limit、keyword、status |
| 调用 Validate | 校验参数类型和范围 |
| 调用 Service | `$service->list($params)` |
| 返回响应 | success/json |

不建议 Controller 直接写复杂查询或联表。

---

### 1.3 Service 应该做什么？

`StoreService::list()` 通常负责：

- 组织查询条件
- 处理业务筛选规则
- 调用 Model / ModelJoin
- 处理分页结构
- 格式化返回字段

Service 是业务编排层，不应关心 HTTP 细节。

---

### 1.4 列表接口常见参数

| 参数 | 含义 | 校验 |
|---|---|---|
| `page` | 当前页 | integer，>=1 |
| `limit` | 每页数量 | integer，限制最大值 |
| `keyword` | 搜索关键词 | string，长度限制 |
| `status` | 门店状态 | 枚举值 |
| `city_id` | 城市 | integer |

---

### 1.5 追踪链路模板

| 层级 | 文件/方法 | 职责 | 记录 |
|---|---|---|---|
| Route |  | URL 映射 |  |
| Controller | `StoreController::index()` | 接收请求 |  |
| Validate |  | 校验参数 |  |
| Service | `StoreService::list()` | 业务编排 |  |
| Model |  | 查询数据 |  |
| Response |  | 返回分页 |  |

---

### 1.6 Node.js 类比

```text
Express route → controller → service → repository/model
```

TP8：

```text
Route → StoreController → StoreService → OfflineStore Model
```

---

## 2. 源码阅读

- `store-api/app/admin/controller/store/StoreController.php`
- `store-api/app/common/service/store/StoreService.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| 节点 | 方法 | 入参 | 出参 | 备注 |
|---|---|---|---|---|
| Controller | `index()` |  |  |  |
| Service | `list()` |  |  |  |

---

## 3. 练习任务

### 练习 1：读 `StoreController::index()`

记录它如何取参、校验、调用 Service、返回。

### 练习 2：追踪到 `StoreService::list()`

记录查询条件、分页逻辑、调用的 Model。

### 练习 3：画 CRUD 链路图

```text
Route → Controller → Validate → Service → Model → Response
```

---

## 4. JS/Node.js 类比

- `StoreService` ≈ NestJS Service
- Controller index ≈ list route handler
- TP8 Validate ≈ DTO/Zod schema
- Model ≈ ORM model / repository

---

## 5. AI Review 提问

```text
我正在追踪 StoreController 到 StoreService 的门店列表接口。
我已经记录了 index()、list()、参数、分页、查询和返回结构。
请你检查：
1. CRUD 链路是否完整？
2. Controller 和 Service 分层是否清楚？
3. 哪些查询逻辑应该放 Model/ModelJoin？
4. 与 Yii2 CSR / NestJS Service 的类比是否准确？
5. 列表接口还有哪些性能和权限风险？
```

---

## 6. 今日产出

- [ ] CRUD 链路笔记
- [ ] Controller → Service 调用表
- [ ] 列表参数校验表
- [ ] 分层职责对照表
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能追踪 list 链路
- [ ] 能说明 Controller 与 Service 分工
- [ ] 能记录列表接口入参和出参
- [ ] 能说明分页/筛选逻辑放在哪里
- [ ] 能用 NestJS Service 类比 StoreService

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
我正在进行 Week 11 Day 02：StoreController 到 StoreService 的学习。
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
