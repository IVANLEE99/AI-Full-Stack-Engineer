# Week 11 Day 01：ThinkPHP 8 架构

> 所属周：Week 11：ThinkPHP 8 门店 API  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`store-api`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

读懂 ThinkPHP 8 项目的基本目录结构和请求入口，能把前面学过的 Yii2 Controller/Form/Service/Model 思维迁移到 TP8 的 Route/Controller/Validate/Service/Model 结构中。

今天你要真正掌握这一句话：

> 换框架不是重新学后端，而是把已经掌握的分层思维迁移过去：请求仍然从路由进入 Controller，经 Validate 校验，再到 Service 编排业务，最后由 Model/ORM 读写数据。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先回顾 Yii2 项目中的 CSR 链路
2. 阅读 ThinkPHP 8 架构概览
3. 阅读 `store-api/README.md`
4. 列出 TP8 项目中的核心目录职责
5. 找路由、Controller、Validate、Service、Model 的位置
6. 对照 Yii2 项目结构
7. 用 Node/NestJS 分层做类比
8. 整理 TP8 项目阅读路线
9. 用 AI Review 检查结构差异理解是否准确

---

## 1. 学习内容

### 1.1 为什么现在学习 TP8？

前面你主要用 Yii2 思维读项目：

```text
Controller → Form/Validate → Service → Repository/Model
```

进入 TP8 后，目录和写法会变，但核心问题不变：

- 请求从哪里进入？
- 参数在哪里校验？
- 业务逻辑在哪里？
- 数据查询在哪里？
- 中间件在哪里做鉴权？
- 响应格式在哪里统一？

小白重点：不要被框架名称吓住，先找同类概念。

---

### 1.2 TP8 常见目录怎么理解？

不同项目结构会有差异，但常见有：

| 目录/文件 | 可能职责 |
|---|---|
| `app/` | 应用代码主目录 |
| `app/admin/controller` | 后台 Controller |
| `app/api/controller` | API Controller |
| `app/common/service` | 公共业务 Service |
| `app/common/model` | Model / ORM |
| `app/admin/validate` | 后台参数验证器 |
| `route/` | 路由定义 |
| `config/` | 配置 |
| `middleware/` | 中间件 |
| `public/` | Web 入口目录 |

你需要按项目 README 和实际目录修正。

---

### 1.3 TP8 请求链路

典型链路：

```text
HTTP 请求
  ↓
route 路由
  ↓
Middleware 中间件
  ↓
Controller
  ↓
Validate
  ↓
Service
  ↓
Model / ORM
  ↓
统一响应
```

这和 Yii2 的链路很像，只是类名和目录不同。

---

### 1.4 Yii2 与 TP8 第一层对照

| 概念 | Yii2 | TP8 |
|---|---|---|
| 路由 | `module/controller/action` | route 文件或约定路由 |
| Controller | `xxx/controllers` | `app/.../controller` |
| 参数校验 | Form Model rules/scenarios | Validate + scene |
| 业务层 | Service | Service |
| 数据层 | ActiveRecord/Repository | Model/ThinkORM |
| 中间件 | behaviors/filter | middleware |
| 配置 | config 文件 | config 目录 |

---

### 1.5 读 TP8 项目的第一步

不要一上来读业务代码，先建立地图：

| 问题 | 你要找什么 |
|---|---|
| 入口在哪里？ | `public/index.php` 或 README |
| 路由在哪里？ | `route/*.php` |
| Controller 放哪里？ | `app/admin/controller` |
| Validate 放哪里？ | `app/admin/validate` |
| Service 放哪里？ | `app/common/service` |
| Model 放哪里？ | `app/common/model` |
| 返回格式如何统一？ | BaseController / helper |

---

### 1.6 Node.js 类比

TP8 也可以类比 NestJS：

```text
Route → Controller → DTO/Validate → Service → Repository/Model
```

或者 Express：

```text
router → middleware → handler → service → model
```

后端分层思维是一致的。

---

## 2. 源码阅读

- `store-api/README.md`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| 目录/文件 | 职责 | Yii2 对应概念 |
|---|---|---|
|  |  |  |

---

## 3. 练习任务

### 练习 1：读 README

记录项目启动方式、目录结构、接口模块。

### 练习 2：列目录职责

至少列 10 个目录/文件职责。

### 练习 3：对比 Yii2 项目结构

完成 Yii2 vs TP8 对照表。

---

## 4. JS/Node.js 类比

- TP8 ≈ 另一套 PHP MVC
- Route ≈ Express router / NestJS route
- Validate ≈ Zod/Joi/DTO validation
- Service ≈ NestJS Service
- Model/ThinkORM ≈ ORM model

---

## 5. AI Review 提问

```text
我正在学习 ThinkPHP 8 架构。
我已经阅读 store-api README，并整理了 TP8 目录职责和 Yii2 对照表。
请你检查：
1. 我对 TP8 项目结构的理解是否正确？
2. 哪些目录职责容易和 Yii2 混淆？
3. TP8 Validate、Middleware、Model 分别对应 Yii2 哪些概念？
4. 我用 Node/NestJS 的类比是否准确？
5. 接下来读 store-api 应该按什么顺序？
```

---

## 6. 今日产出

- [ ] store-api README 阅读笔记
- [ ] TP8 目录职责表
- [ ] Yii2 vs TP8 初步对照表
- [ ] TP8 请求链路图
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说明 TP8 结构
- [ ] 能找到 Route/Controller/Validate/Service/Model 位置
- [ ] 能画出 TP8 请求链路
- [ ] 能把 TP8 与 Yii2 结构做基础对照
- [ ] 能用已有业务域阅读方法迁移到 TP8

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
我正在进行 Week 11 Day 01：ThinkPHP 8 架构 的学习。
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
