# Week 09 Day 06：用户域总结项目

> 所属周：Week 09：用户服务 + 注册 Node 链  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`user-service`  
> 类型：项目实战  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

完成注册 Node 图与用户域总结，把 quickLogin、用户缓存、注册链、OAuth、JWT/Session、user_id 归属校验和隐私数据保护串成一张完整用户域知识图。

今天你要真正掌握这一句话：

> 用户域不是孤立的登录注册模块，而是所有业务域的身份底座：订单、支付、通知、售后都依赖可信的 `user_id`、清晰的权限边界和安全的用户资料处理。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Week 09 Day 01-05 的笔记
2. 完善注册 Node 顺序图
3. 整理 quickLogin / OAuth / 注册三条登录注册链路
4. 整理 token / session / JWT 的差异
5. 整理用户资料缓存和隐私字段脱敏
6. 整理 user_id 如何进入订单、支付、通知链路
7. 列出用户域常见越权风险
8. 写 1 页用户域总结
9. 用 AI Review 检查总结是否可复用

---

## 1. 学习内容

### 1.1 用户域知识地图

本周你学到的内容可以整理成：

```text
用户入口
  ├─ quickLogin
  ├─ 注册 RegisterService Node 链
  └─ OAuth 第三方登录

登录态
  ├─ JWT
  ├─ Session
  └─ Redis token

用户资料
  ├─ UserService
  ├─ UserDetailRedis
  └─ 脱敏/隐私保护

权限边界
  ├─ user_id 公参
  ├─ 资源归属校验
  └─ 越权风险控制
```

小白重点：用户域要同时考虑“能登录”和“登录后只能访问自己的东西”。

---

### 1.2 三条身份入口链路

| 链路 | 输入 | 核心处理 | 输出 |
|---|---|---|---|
| quickLogin | 手机号/验证码/快捷凭证 | 校验凭证，查找或创建用户 | user_id + token |
| 注册链 | 手机号/邮箱/密码/验证码 | Node 链校验并创建用户 | user_id + token |
| OAuth | 第三方 code | 换 profile，绑定本地用户 | user_id + token |

虽然入口不同，最终都要落到：

```text
本地 user_id + 本系统 token/session
```

---

### 1.3 注册 Node 图最终版

你可以整理成：

```text
RegisterService
  ↓
ValidateInputNode：校验手机号/邮箱/密码/验证码是否存在
  ↓
VerifyCodeNode：校验短信/邮箱验证码
  ↓
CheckUserExistsNode：检查是否已注册
  ↓
CreateUserNode：写用户表和用户资料
  ↓
GenerateTokenNode：生成 token/session
  ↓
WriteUserCacheNode：写用户缓存
  ↓
PublishRegisterEventNode：投递注册成功事件（可选）
```

按源码实际 Node 名称修正即可。

---

### 1.4 user_id 如何贯穿业务？

登录成功后：

```text
token → BFF 鉴权 → 解析 user_id → 注入公参 → 订单/支付/通知使用
```

业务中必须校验归属：

| 业务 | 必须校验什么 |
|---|---|
| 查询订单 | 订单是否属于当前 user_id |
| 发起支付 | 支付单/订单是否属于当前 user_id |
| 查看用户资料 | 是否查看自己的资料或有管理员权限 |
| 售后退款 | 售后单/订单是否属于当前 user_id |
| 通知列表 | 通知是否属于当前 user_id |

---

### 1.5 权限边界与越权风险

常见越权：

```text
GET /order/detail?order_id=1002
```

如果后端只按 `order_id` 查询，不校验 `user_id`，A 用户可能看到 B 用户订单。

正确查询：

```php
<?php

$order = $orderRepository->findOne([
    'id' => $orderId,
    'user_id' => $currentUserId,
]);
```

权限原则：

```text
前端隐藏按钮不是权限控制；后端每个敏感资源都要做归属校验。
```

---

### 1.6 隐私数据保护总结

用户域要保护：

- 手机号
- 邮箱
- 真实姓名
- 地址
- 第三方 open_id
- token / refresh token
- 密码 hash

处理原则：

| 场景 | 处理 |
|---|---|
| 返回前端 | 按需要脱敏 |
| 写日志 | 不打印 token/验证码/密码 |
| 缓存 | 控制 TTL 和字段范围 |
| 后台查看 | 按权限展示 |
| 导出数据 | 审批和脱敏 |

---

### 1.7 用户域总结模板

请写：

```markdown
# Week 09 用户域总结

## 1. 用户域解决什么问题

## 2. quickLogin 链路

## 3. 注册 Node 链

## 4. OAuth 第三方登录链路

## 5. JWT / Session / Redis token 对比

## 6. 用户缓存和隐私字段

## 7. user_id 如何进入订单和支付链路

## 8. 常见越权风险和防护
```

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议回看：

- `UserController.php`
- `UserDetailRedis.php`
- `RegisterService.php`
- token 生成/验证代码
- OAuth login 相关 Service

---

## 3. 练习任务

### 练习 1：完善注册 Node 图

补齐 Node 名称、输入、输出、失败提示。

### 练习 2：写 1 页用户域总结

不少于 500 字。

### 练习 3：列越权风险清单

| 场景 | 风险 | 后端校验 |
|---|---|---|
| 查询订单 |  |  |
| 查询支付单 |  |  |
| 修改资料 |  |  |
| 售后申请 |  |  |
| 查看通知 |  |  |

---

## 4. JS/Node.js 类比

- 用户域总结 ≈ Auth/User module knowledge base
- 注册 Node 图 ≈ registration middleware pipeline
- user_id 公参 ≈ `req.user.id`
- 越权校验 ≈ resource ownership guard
- 隐私脱敏 ≈ response serializer / DTO

---

## 5. AI Review 提问

```text
我正在整理用户域总结。
我已经完成注册 Node 图、quickLogin/OAuth/JWT/Session 对照、用户缓存和越权风险清单。
请你检查：
1. 总结是否可复用给新人学习？
2. user_id 贯穿 BFF、订单、支付的链路是否清楚？
3. 权限边界和越权风险是否覆盖到位？
4. 隐私数据保护是否遗漏关键字段？
5. 下一步学习售后域前还要补什么？
```

---

## 6. 今日产出

- [ ] 注册 Node 图最终版
- [ ] 用户域总结
- [ ] user_id 业务链路图
- [ ] 越权风险清单
- [ ] 隐私字段保护清单
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] Node 图完成
- [ ] 能总结 quickLogin、注册、OAuth 三条入口
- [ ] 能说明 JWT、Session、Redis token 差异
- [ ] 能说明 user_id 如何贯穿订单和支付
- [ ] 能列出至少 5 个越权风险
- [ ] 能列出至少 5 个隐私保护字段

---

## 8. 今日自测题

### 8.1 用户域为什么是所有业务域的身份底座？

参考答案：

> ✅ 因为订单、支付、通知、售后等所有业务都依赖登录后生成的 `user_id` 来确认“这个操作是谁发起的、这个资源属于谁”。身份错了，后面所有归属和权限判断都会错，所以用户域是整个系统的安全边界。

---

### 8.2 quickLogin、注册链、OAuth 三条入口的共同产出是什么？

参考答案：

> ✅ 虽然输入不同（快捷凭证、手机号邮箱密码、第三方 code），但最终都要落到同一个结果：本地 `user_id` + 本系统签发的 token/session。第三方 token 不能直接当本系统登录态用。

---

### 8.3 为什么按 `order_id` 查询订单时必须带上 `user_id`？

参考答案：

> ✅ 如果只按 `order_id` 查，A 用户改一下 URL 里的 order_id 就能看到 B 用户的订单，这是典型越权。正确做法是查询条件里同时限制 `user_id`，只返回属于当前登录用户的资源。

---

### 8.4 “前端隐藏按钮”能不能算权限控制？

参考答案：

> ✅ 不能。前端隐藏只是界面上看不到，请求接口仍然可以被直接构造调用。真正的权限控制必须在后端做，每个敏感资源都要校验归属和权限。

---

### 8.5 用户资料返回前端和写日志时分别要注意什么？

参考答案：

> ✅ 返回前端时手机号、邮箱等敏感字段要按场景脱敏，只给必要字段；写日志时绝不能打印 token、验证码、密码等敏感信息。缓存也要控制 TTL 和字段范围。

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
我正在进行 Week 09 Day 06：用户域总结项目 的学习。
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
