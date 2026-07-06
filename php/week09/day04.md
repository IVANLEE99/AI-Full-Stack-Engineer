# Week 09 Day 04：OAuth 第三方登录

> 所属周：Week 09：用户服务 + 注册 Node 链  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`user-service`  
> 类型：架构理解  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 Google/Facebook 等第三方登录的 OAuth 回调流程，知道前端授权、后端换取用户信息、绑定或创建本地用户、生成 token 的完整链路，并识别回调安全风险。

今天你要真正掌握这一句话：

> OAuth 第三方登录的本质是：第三方平台证明“这个外部账号是谁”，你的系统再把它绑定到本地 `user_id`，最终仍然由你的用户服务签发本系统 token。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解 OAuth 解决什么问题
2. 区分前端 SDK、授权 code、access token、本地 token
3. 梳理 Google/Facebook 登录回调流程
4. 找 login 相关 Service，理解如何换取第三方用户信息
5. 理解第三方账号如何绑定本地用户
6. 标注 `state`、redirect_uri、回调验签/校验等安全点
7. 对比 Passport.js strategy
8. 画第三方登录完整流程图
9. 用 AI Review 检查回调安全风险

---

## 1. 学习内容

### 1.1 OAuth 第三方登录是什么？

用户不想重新注册账号，可以用 Google/Facebook 登录。

流程大概是：

```text
用户点击 Google 登录
  ↓
跳转到 Google 授权页
  ↓
用户同意授权
  ↓
Google 回调你的系统并带上 code
  ↓
后端用 code 换 access token
  ↓
后端用 access token 获取第三方用户资料
  ↓
绑定/创建本地用户
  ↓
你的系统生成自己的 token
```

小白重点：Google 的 token 不是你系统的 token。最终给前端使用的，通常还是你自己系统签发的 token。

---

### 1.2 OAuth 中几个关键概念

| 概念 | 含义 |
|---|---|
| client_id | 第三方平台分配给你的应用 ID |
| client_secret | 第三方平台分配的密钥，必须放后端 |
| redirect_uri | 授权成功后的回调地址 |
| code | 授权码，用来换 access token |
| access_token | 调第三方 API 获取用户信息的凭证 |
| state | 防 CSRF 的随机值 |
| open_id / provider_user_id | 第三方平台中的用户唯一 ID |

---

### 1.3 前端 SDK 和后端 Service 分工

前端适合：

- 展示第三方登录按钮
- 跳转授权页
- 获取授权 code
- 把 code 交给后端

后端必须：

- 校验 state
- 用 code 换 access token
- 使用 client_secret
- 获取第三方用户信息
- 查找/创建本地用户
- 生成本系统 token

敏感信息如 `client_secret` 不能放前端。

---

### 1.4 第三方账号如何绑定本地用户？

通常会有一张绑定关系表：

| 字段 | 含义 |
|---|---|
| `user_id` | 本地用户 ID |
| `provider` | google/facebook/apple |
| `provider_user_id` | 第三方用户 ID |
| `email` | 第三方邮箱 |
| `created_at` | 绑定时间 |

登录时：

```text
根据 provider + provider_user_id 查绑定
  ↓ 找到：登录对应 user_id
  ↓ 找不到：创建本地用户并绑定
```

注意：不要只用 email 判断账号归属，因为第三方邮箱验证状态、邮箱变更、不同平台邮箱重复都可能带来风险。

---

### 1.5 回调安全注意什么？

| 风险 | 说明 | 应对 |
|---|---|---|
| CSRF | 攻击者伪造授权回调 | 使用 `state` 校验 |
| redirect_uri 被篡改 | code 被发到错误地址 | 后端固定/校验 redirect_uri |
| code 重放 | 同一个 code 被重复使用 | code 短时有效，后端只处理一次 |
| client_secret 泄露 | 第三方应用被盗用 | 只放后端配置 |
| 绑定错用户 | 第三方账号绑定到错误本地用户 | 明确绑定流程和确认 |
| 隐私泄露 | 返回过多第三方资料 | 最小化保存字段 |

---

### 1.6 Passport.js 类比

Node 中常用 Passport.js：

```js
passport.use(new GoogleStrategy(..., async (accessToken, refreshToken, profile, done) => {
  const user = await authService.findOrCreateByGoogle(profile);
  done(null, user);
}));
```

PHP 项目里的 OAuth Service 类似：

```text
拿到第三方 profile → find or create 本地 user → issue token
```

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议浏览：

- login 相关 Service
- Google/Facebook callback 处理
- 第三方账号绑定表/Repository
- token 生成逻辑

记录：

| 节点 | 记录 |
|---|---|
| 回调入口 |  |
| code 换 token |  |
| 获取第三方 profile |  |
| 绑定/创建本地用户 |  |
| 签发本地 token |  |
| 安全校验 |  |

---

## 3. 练习任务

### 练习 1：梳理 Google/Facebook 登录回调

画出从前端点击登录到后端返回本地 token 的流程。

### 练习 2：对比前端社交登录 SDK

| 任务 | 前端 SDK | 后端 Service |
|---|---|---|
| 展示登录按钮 |  |  |
| 保存 client_secret |  |  |
| code 换 access token |  |  |
| 创建本地用户 |  |  |
| 签发本地 token |  |  |

### 练习 3：列回调安全风险

至少列 5 个风险和应对策略。

---

## 4. JS/Node.js 类比

- OAuth ≈ Passport.js strategy
- provider profile ≈ Google/Facebook profile
- 本地 token ≈ 应用自己的 JWT/session
- `state` ≈ CSRF 防护随机值
- provider_user_id ≈ 第三方账号唯一 ID

---

## 5. AI Review 提问

```text
我正在学习 OAuth 第三方登录。
我已经画出 Google/Facebook 登录回调流程，并区分了第三方 access token 和本地 token。
请你检查：
1. 回调流程是否完整？
2. 前端 SDK 和后端 Service 分工是否正确？
3. state、redirect_uri、client_secret 的安全点是否理解到位？
4. 第三方账号绑定本地用户是否有风险？
5. 与 Passport.js strategy 的类比是否准确？
```

---

## 6. 今日产出

- [ ] OAuth 流程笔记
- [ ] Google/Facebook 登录回调图
- [ ] 前端 SDK vs 后端 Service 分工表
- [ ] 第三方账号绑定风险表
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说明 OAuth 回调流程
- [ ] 能区分第三方 token 和本地 token
- [ ] 能说明 `state` 的作用
- [ ] 能说明 client_secret 为什么不能放前端
- [ ] 能解释第三方账号如何绑定本地用户

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
我正在进行 Week 09 Day 04：OAuth 第三方登录 的学习。
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
