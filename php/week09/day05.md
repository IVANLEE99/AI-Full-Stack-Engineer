# Week 09 Day 05：JWT 与类比日

> 所属周：Week 09：用户服务 + 注册 Node 链  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`user-service`  
> 类型：类比日  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

学习 JWT 的结构、签名、过期时间和验证流程，对比 Session 的服务端存储模式，理解什么时候适合用 JWT，什么时候更适合用 Session 或 Redis token。

今天你要真正掌握这一句话：

> JWT 是一种带签名的无状态 token，它可以让服务端验证“这个 token 是否由我签发且未被篡改”，但它不等于万能登录方案，过期、吊销、泄露和隐私字段都必须谨慎设计。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解 token 和 session 都是为了解决登录态
2. 打开 jwt.io，观察 JWT 三段结构
3. 理解 header、payload、signature
4. 找项目中 token 生成与验证代码
5. 对比 JWT 与 Session / Redis token
6. 理解 access token、refresh token 和过期时间
7. 标注 JWT 中不能放哪些敏感字段
8. 完成 JWT vs Session 类比打卡
9. 用 AI Review 检查何时使用 JWT 的判断

---

## 1. 学习内容

### 1.1 登录态解决什么问题？

HTTP 请求本身是无状态的。

用户第一次登录后，后续请求需要证明：

```text
我还是刚才那个已经登录的用户。
```

常见做法：

| 方案 | 思路 |
|---|---|
| Session | 服务端存登录态，客户端保存 session_id |
| Redis token | 服务端 Redis 存 token → user_id 映射 |
| JWT | token 本身携带 user_id、过期时间，并带签名 |

---

### 1.2 JWT 三段结构

JWT 通常长这样：

```text
header.payload.signature
```

三段分别是：

| 部分 | 含义 |
|---|---|
| header | 算法、token 类型 |
| payload | 用户信息、过期时间等声明 |
| signature | 服务端签名，防篡改 |

payload 例子：

```json
{
  "sub": "1001",
  "iat": 1710000000,
  "exp": 1710003600
}
```

注意：payload 只是 Base64URL 编码，不是加密，不能放密码、手机号、身份证等敏感信息。

---

### 1.3 JWT 验证流程

后端收到请求：

```text
Authorization: Bearer xxx.yyy.zzz
```

验证步骤：

1. 拆分三段
2. 使用服务端 secret/public key 校验 signature
3. 检查 `exp` 是否过期
4. 读取 `sub` / `user_id`
5. 可选：检查用户状态、token 黑名单、权限版本
6. 将 `user_id` 注入请求上下文

伪代码：

```php
<?php

$payload = Jwt::verify($token, $secret);
$userId = (int)$payload['sub'];
```

---

### 1.4 JWT vs Session

| 对比项 | JWT | Session |
|---|---|---|
| 登录态存哪里 | token 里携带声明 | 服务端存储 |
| 服务端是否查存储 | 可不查 | 必须查 session 存储 |
| 吊销难度 | 相对困难，需要黑名单/版本号 | 删除 session 即可 |
| token 大小 | 较大 | session_id 较小 |
| 适合场景 | API、微服务、跨服务验证 | 传统 Web、强服务端控制 |
| 泄露风险 | 泄露后直到过期前可用 | 泄露 session_id 也危险，但可服务端删除 |

小白重点：JWT 的优势是无状态，代价是吊销和安全控制更复杂。

---

### 1.5 access token 与 refresh token

常见设计：

| token | 作用 | 有效期 |
|---|---|---|
| access token | 访问 API | 短，比如 15 分钟-2 小时 |
| refresh token | 换新的 access token | 长，比如 7-30 天 |

这样可以降低 access token 泄露风险。

但 refresh token 更敏感，必须安全存储、可吊销、可轮换。

---

### 1.6 JWT 不能放什么？

不要放：

- 密码
- 验证码
- 手机号完整值
- 邮箱完整值
- 身份证
- 地址
- 支付信息
- 权限过细且长期不变的敏感信息

可以放最小必要字段：

- `sub` / `user_id`
- `iat`
- `exp`
- `jti` token id
- `role` 或权限版本号，视情况而定

---

### 1.7 Node.js 类比

Node 中：

```js
const token = jwt.sign(
  { sub: user.id },
  process.env.JWT_SECRET,
  { expiresIn: '1h' }
);

const payload = jwt.verify(token, process.env.JWT_SECRET);
```

PHP 中思想相同，只是库不同。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议查找：

- token 生成代码
- token 验证代码
- token 过期时间配置
- Redis token / session 相关代码
- BFF 鉴权中间件或 Filter

记录：

| 阅读点 | 记录 |
|---|---|
| token 类型 |  |
| payload 字段 |  |
| 过期时间 |  |
| 验证位置 |  |
| 是否支持吊销 |  |

---

## 3. 练习任务

### 练习 1：对比 JWT vs Session

完成至少 8 项对比。

### 练习 2：完成类比打卡

| PHP/Auth 概念 | Node.js 类比 | 风险 |
|---|---|---|
| JWT |  |  |
| Session |  |  |
| Redis token |  |  |
| refresh token |  |  |

### 练习 3：设计一个安全 payload

写出你认为可以放入 JWT 的字段，并说明哪些字段不能放。

---

## 4. JS/Node.js 类比

- JWT ≈ 无状态 token
- Session ≈ 服务端登录态
- Redis token ≈ 服务端 token 存储
- `Authorization: Bearer` ≈ API token 传递方式
- refresh token ≈ 长期换票凭证

---

## 5. AI Review 提问

```text
我正在学习 JWT，并对比 Session 和 Redis token。
我已经整理了 JWT 三段结构、payload 字段、过期时间、吊销风险和 Node.js 类比。
请你检查：
1. 我对 JWT 的理解是否正确？
2. 何时适合用 JWT，何时更适合 Session/Redis token？
3. 我的 payload 字段设计是否安全？
4. token 泄露、吊销、刷新应该如何处理？
5. JWT 和 Node jsonwebtoken 的类比是否准确？
```

---

## 6. 今日产出

- [ ] JWT 结构笔记
- [ ] JWT vs Session 对照表
- [ ] token 生成与验证代码阅读记录
- [ ] 安全 payload 设计
- [ ] 类比打卡表
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说明 JWT 用途
- [ ] 能解释 header/payload/signature
- [ ] 能说明 JWT 和 Session 的差异
- [ ] 能说出 JWT 不能放哪些敏感字段
- [ ] 能说明 token 过期和吊销风险

---

## 8. 今日自测题

### 8.1 JWT 由哪三段组成？各自作用是什么？

参考答案：

> ✅ JWT 是 `header.payload.signature` 三段。header 存算法和 token 类型，payload 存用户信息和过期时间等声明，signature 是服务端用密钥生成的签名，用来验证 token 未被篡改且确实由自己签发。

---

### 8.2 为什么 JWT 的 payload 不能放密码、手机号等敏感信息？

参考答案：

> ✅ payload 只是 Base64URL 编码，不是加密，任何人拿到 token 都能解码看到里面的内容。所以密码、验证码、手机号、身份证等敏感字段绝对不能放进 payload。

---

### 8.3 JWT 和 Session 最大的区别是什么？

参考答案：

> ✅ JWT 是无状态的，登录态信息带在 token 里，服务端验证签名即可、不一定要查存储；Session 是有状态的，登录态存在服务端，客户端只保存 session_id，每次都要查服务端存储。JWT 吊销更难，Session 删掉即可失效。

---

### 8.4 access token 和 refresh token 分别有什么用？

参考答案：

> ✅ access token 用来访问 API，有效期短（如 15 分钟到 2 小时），降低泄露风险；refresh token 有效期长（如 7-30 天），用来在 access token 过期后换取新的 access token，避免用户频繁重新登录。refresh token 更敏感，需要安全存储和可吊销。

---

### 8.5 JWT 无状态的优势和代价分别是什么？

参考答案：

> ✅ 优势是服务端不用查存储就能验证 token，适合 API、微服务和跨服务验证；代价是吊销困难，token 一旦签发在过期前都有效，想提前失效就需要引入黑名单或版本号等额外机制。

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
我正在进行 Week 09 Day 05：JWT 与类比日 的学习。
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
