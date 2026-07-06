# Week 09 Day 01：UserController 与 quickLogin

> 所属周：Week 09：用户服务 + 注册 Node 链  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`user-service`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

追踪用户登录入口 `UserController::actionQuickLogin` 的完整链路，理解用户服务如何生成或识别 `user_id`，并把登录结果返回给 BFF、订单、支付等后续业务使用。

今天你要真正掌握这一句话：

> 用户服务的核心价值是建立可信身份：登录成功后生成的 `user_id` / token 会贯穿 BFF、订单归属、支付归属、通知和权限判断，任何越权风险都从身份边界开始。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 BFF 中的鉴权和公参注入
2. 理解用户域为什么是一切业务的身份源头
3. 打开 `UserController.php`，先看 action 列表
4. 找到 `actionQuickLogin` 或快捷登录入口
5. 追踪 Controller → Service → Repository/Model
6. 记录登录入参、出参、`code/data/info`
7. 理解 token/session 如何返回给前端
8. 标注用户与订单/支付归属的关系
9. 用 AI Review 检查链路是否完整

---

## 1. 学习内容

### 1.1 用户服务为什么重要？

前面你已经学过：

```text
BFF 鉴权 → 注入 user_id → 创建订单 → 发起支付 → 发送通知
```

这里最关键的就是 `user_id`。

如果身份错了，会出现严重问题：

| 问题 | 后果 |
|---|---|
| token 解析错 | A 用户变成 B 用户 |
| 未校验订单归属 | A 可以看 B 的订单 |
| 支付归属错误 | A 可以支付/查询 B 的支付单 |
| 用户资料泄露 | 手机号、邮箱、地址被越权读取 |

所以用户服务不是“登录页面背后的接口”这么简单，它是业务安全边界。

---

### 1.2 UserController 负责什么？

`UserController` 通常负责用户相关 HTTP 入口：

- 登录
- 注册
- 快捷登录
- 用户资料
- token 刷新
- 退出登录
- 绑定手机号/邮箱

Controller 的职责仍然是入口层：

| 职责 | 示例 |
|---|---|
| 接收参数 | 手机号、验证码、第三方 code |
| 基础校验 | 参数是否存在、格式是否正确 |
| 调用 Service | 登录、注册、查用户 |
| 返回响应 | `code/data/info` |

核心身份规则应放在 Service 或 Node 链中。

---

### 1.3 quickLogin 是什么？

`quickLogin` 可以理解为快捷登录。

常见方式：

| 快捷登录类型 | 输入 |
|---|---|
| 手机验证码登录 | 手机号 + 验证码 |
| 第三方登录 | Google/Facebook code |
| App 一键登录 | 手机运营商 token |
| 临时游客登录 | 设备 ID / guest token |

快捷登录的关键问题：

```text
如何确认这个登录凭证是真的？如何找到或创建对应用户？如何返回可信 token？
```

---

### 1.4 quickLogin 链路怎么追？

你可以按这个模板：

```text
前端提交 quickLogin
  ↓
UserController::actionQuickLogin()
  ↓
QuickLoginForm / 参数校验
  ↓
UserService / LoginService
  ↓
校验验证码/第三方 code
  ↓
查找或创建用户
  ↓
生成 token/session
  ↓
返回 user_id、token、用户资料
```

记录重点：

| 节点 | 你要看什么 |
|---|---|
| Controller | 入参和返回格式 |
| Form | 校验手机号/code/token |
| Service | 登录业务逻辑 |
| Repository/Model | 用户如何查找/创建 |
| Token | 如何生成和过期 |

---

### 1.5 `code/data/info` 登录返回怎么理解？

成功可能返回：

```json
{
  "code": 0,
  "data": {
    "user_id": 1001,
    "token": "xxx.yyy.zzz",
    "nickname": "Tom"
  },
  "info": "success"
}
```

失败可能返回：

```json
{
  "code": 10001,
  "data": null,
  "info": "验证码错误"
}
```

你要记录：

- token 字段叫什么
- user_id 字段叫什么
- 过期时间是否返回
- 用户资料是否脱敏
- 错误码如何区分

---

### 1.6 用户与订单/支付归属

登录后拿到 `user_id`，后续所有敏感资源都要校验归属：

```php
<?php

if ($order->user_id !== $currentUserId) {
    return $this->endFail('无权访问该订单');
}
```

不要只靠前端传 `user_id`。

正确方式：

```text
从 token/session 解析当前用户 → 后端自己注入 user_id → 查询时按 user_id 限制
```

---

### 1.7 Node.js 类比

Express 中：

```js
app.post('/quick-login', async (req, res) => {
  const result = await authService.quickLogin(req.body);
  res.json({ code: 0, data: result });
});
```

后续 middleware：

```js
req.user = verifyToken(token);
```

PHP 用户服务的 `quickLogin` 也是身份建立入口。

---

## 2. 源码阅读

- `user-service/user-api/controllers/UserController.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| action | 入参 | 调用 Service | 返回 data | 风险 |
|---|---|---|---|---|
| `actionQuickLogin` |  |  |  |  |

---

## 3. 练习任务

### 练习 1：读 UserController

记录 action 列表和每个 action 用途。

### 练习 2：追踪 `actionQuickLogin` 全链路

记录：

```text
入口：
入参：
Form/校验：
Service：
用户查找/创建：
token/session：
返回 code/data/info：
```

### 练习 3：记录身份归属风险

列出用户、订单、支付、通知中至少 5 个需要 `user_id` 校验的地方。

---

## 4. JS/Node.js 类比

- quickLogin ≈ 社交/快捷登录 API
- UserController ≈ Auth/User route controller
- token 生成 ≈ JWT/session issuance
- `user_id` 公参 ≈ `req.user.id`
- 归属校验 ≈ resource ownership check

---

## 5. AI Review 提问

```text
我正在追踪 UserController::actionQuickLogin。
我已经记录了入参、Service、用户查找/创建、token 返回和 code/data/info。
请你检查：
1. 登录链路是否完整？
2. token/session 的理解是否正确？
3. 哪些用户资料需要脱敏？
4. user_id 如何传递到订单和支付链路？
5. 哪些地方可能产生越权风险？
```

---

## 6. 今日产出

- [ ] UserController action 清单
- [ ] quickLogin 全链路笔记
- [ ] code/data/info 返回记录
- [ ] user_id 归属校验风险表
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能追踪 quickLogin 全链路
- [ ] 能说明登录成功后返回什么
- [ ] 能解释 user_id 如何进入后续业务
- [ ] 能说出至少 5 个越权风险点
- [ ] 能用 Node Auth API 类比 quickLogin

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
我正在进行 Week 09 Day 01：UserController 与 quickLogin 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 09 README](./README.md)
