# Week 09 Day 03：注册 Node 链

> 所属周：Week 09：用户服务 + 注册 Node 链  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`user-service`  
> 类型：编码练习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 `RegisterService` 与 `nodes` 目录如何把注册流程拆成多个节点，掌握注册链路中的参数校验、重复用户检查、密码/验证码处理、创建用户、生成 token 和缓存写入。

今天你要真正掌握这一句话：

> 注册不是简单 insert 用户表，而是一条身份创建流水线：每个 Node 负责一个安全步骤，任何一步失败都应该中断，避免重复注册、弱密码、验证码绕过或脏用户数据。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Week 07 的 PayService Node 链思想
2. 理解注册为什么也适合拆成 Node 链
3. 打开 `RegisterService.php`，找到 NodeExecutionEngine
4. 列出注册前 5 个 Node
5. 记录每个 Node 读取和写入的 Context 字段
6. 画注册 Node 顺序图
7. 标注重复手机号/邮箱、验证码、密码、隐私数据风险
8. 用 Express middleware 管道做类比
9. 用 AI Review 检查 Node 顺序是否合理

---

## 1. 学习内容

### 1.1 注册流程为什么复杂？

用户注册可能包含：

- 参数校验
- 手机号/邮箱格式校验
- 验证码校验
- 密码强度检查
- 检查用户是否已存在
- 创建用户记录
- 初始化用户资料
- 生成 token/session
- 写入 Redis 缓存
- 发送欢迎通知或埋点事件

如果都写在一个大函数里，后续很难维护。

---

### 1.2 注册 Node 链怎么理解？

注册链可以理解成：

```text
RegisterService
  ↓
NodeExecutionEngine
  ↓
ValidateInputNode
  ↓
CheckUserExistsNode
  ↓
VerifyCodeNode
  ↓
CreateUserNode
  ↓
GenerateTokenNode
```

实际 Node 名称以源码为准。

每个 Node 做一件事：

| Node | 职责 |
|---|---|
| 参数校验 Node | 检查手机号、邮箱、密码、验证码是否存在 |
| 重复用户检查 Node | 检查手机号/邮箱是否已注册 |
| 验证码 Node | 校验短信/邮箱验证码 |
| 创建用户 Node | 写用户表、用户资料表 |
| token Node | 生成登录态并返回 |

---

### 1.3 注册 Context 放什么？

Context 可能包含：

| 字段 | 含义 |
|---|---|
| `phone` | 手机号 |
| `email` | 邮箱 |
| `password` | 密码或密码 hash 前数据 |
| `verify_code` | 验证码 |
| `register_source` | 注册来源 |
| `user_id` | 创建后的用户 ID |
| `token` | 登录 token |
| `profile` | 初始用户资料 |

注意：密码、验证码、token 都是敏感字段，日志中必须谨慎处理。

---

### 1.4 Node 顺序为什么重要？

错误顺序会带来风险。

例如：

```text
先创建用户，再校验验证码
```

这会导致验证码失败也可能留下脏用户。

更合理的顺序：

```text
参数校验 → 验证码校验 → 重复用户检查 → 创建用户 → 生成 token
```

有些项目会先查重复用户再校验验证码，具体要看业务和防刷策略。

---

### 1.5 注册链路的安全风险

| 风险 | 说明 | 应对 |
|---|---|---|
| 重复注册 | 同一手机号/邮箱创建多个用户 | 唯一索引 + Node 检查 |
| 验证码暴力破解 | 重复尝试验证码 | 限流 + 过期 + 次数限制 |
| 弱密码 | 容易被撞库 | 密码强度规则 |
| 明文密码 | 数据泄露严重 | hash 存储 |
| 日志泄露 | 打印验证码/token | 日志脱敏 |
| 注册刷量 | 机器人注册 | 验证码、风控、频率限制 |

---

### 1.6 注册成功后做什么？

注册成功后通常会：

- 返回 `user_id`
- 返回 token/session
- 初始化用户资料
- 写用户缓存
- 记录注册来源
- 发送欢迎事件
- 触发新用户权益

这些后续动作有些可以同步，有些适合 MQ 异步。

---

### 1.7 Node.js 类比

Express middleware：

```js
validateInput → verifyCode → checkUserExists → createUser → issueToken
```

每个 middleware 读写 `req.context`，失败时中断。

PHP 注册 Node 链类似。

---

## 2. 源码阅读

- `user-service/common/services/RegisterService.php`（C 端注册主链）
- `user-service/common/services/user/RegisterService.php`（邮箱订阅、重置密码与绑定等）
- `user-service/common/services/contexts/RegisterContext.php`（注册流水线上下文）
- `user-service/common/services/nodes/register/*`（注册节点实现）

> 说明：路径均为公开代号 + 相对路径。本地参考源码见 [RegisterService.php](./RegisterService.php)。

阅读记录：

| Node | 输入（Context 字段） | 输出（Context 写入） | 职责 | 失败/异常处理 |
|---|---|---|---|---|
| `RegisterParamsCheckNode` | `context->channelType` | 无（通过校验） | 基础参数校验，验证注册渠道类型是否合法（`LoginChannel`） | `throw new Exception(4444)`，引擎中断并返回通用错误 |
| `SetGuestInfoNode` | `context->userId`（由请求 Header/Token 解析） | `context->guestInfo` | 检查并提取当前访问是否已有游客（tourist）身份，以便后续**游客平滑转正**，避免数据冗余和丢单 | 无游客身份则置空数组，不阻断流程 |
| `SetThirdInfoNode` | `context->channelType`, `context->params`（如 `id_token`/`access_token`/`quick_token`） | `context->thirdInfo`, `context->email` | 解析并校验第三方 OAuth 凭证（Google/Facebook/Apple/Twitter/Firebase/Quick），提取统一身份数据与邮箱 | 凭证无效或解析失败抛 `70001`，引擎回滚并中断 |
| `ThirdInfoIsRegisterNode` | `context->thirdInfo` (`identifier`, `identity_type`) | `context->previouslyRegistered`, `context->userId` | 查 `user_auths` 表判断三方身份是否已绑定过；若已注册，置标记并复用已有 `userId` | 未注册则跳过，已注册则后续免除重复新建用户 |
| `RegisterNode` | `context->email`, `context->params['password']`, `context->guestInfo`, `context->thirdInfo` | `context->userId`, `context->userData` | 核心建号与数据落库：①查重；②游客转正 (`update`) 或新用户落库 (`insert`)；③密码 Hash；④写 `user_auths`；⑤**调用 `setUserDetailRedis` 预热缓存**；⑥发 MQ 广播 | 遇到重复注册直接复用已有用户；落库异常抛出并触发引擎节点回滚 |
| `RegisterSubscribeNode` | `context->email`, `context->params` | `context->subscribeResponse` | 为注册用户自动完成营销邮箱订阅，并调用订阅服务发放新人优惠券礼包 | 订阅失败抛异常中断注册（确保营销券原子性绑定） |
| `ReturnDataFormatNode` | `context->userId`, `context->email`, `context->subscribeResponse`, `context->userData` | `context->response` | 组装统一 API 响应体，调用 `JwtService::createToken` 签发 180 天正式 JWT，挂载订阅券与用户标签 | 格式化无外部依赖，不抛异常 |

---

## 3. 练习任务

### 练习 1：列出注册前 5 个 Node

记录名称、职责、输入和输出：

1. **`RegisterParamsCheckNode`（参数校验节点）**
   - **职责**：作为注册管线首道关卡，阻断缺失关键参数的非法请求。
   - **输入**：`$context->channelType`
   - **输出**：无（验证成功后进入下一节点）
2. **`SetGuestInfoNode`（游客身份提取节点）**
   - **职责**：提取当前设备绑定的游客账号信息，为「游客转正」做准备，继承原游客的加购记录与浏览足迹。
   - **输入**：`$context->userId`（从请求 Header `token` 解析）
   - **输出**：`$context->guestInfo`（若非游客则为空）
3. **`SetThirdInfoNode`（第三方身份解析节点）**
   - **职责**：统一解析 Google、Apple、Facebook、Twitter、Firebase 等三方 OAuth 授权 Token，提取用户唯一标识 `identifier` 与三方主邮箱。
   - **输入**：`$context->channelType`, `$context->params`
   - **输出**：`$context->thirdInfo`, `$context->email`
4. **`ThirdInfoIsRegisterNode`（三方查重绑定节点）**
   - **职责**：查询 `user_auths` 表，判断该三方凭证是否已绑定老用户，防止三方登录反复触发重复新建账号。
   - **输入**：`$context->thirdInfo`
   - **输出**：`$context->previouslyRegistered` (bool), `$context->userId` (int)
5. **`RegisterNode`（用户入库与事务落网节点）**
   - **职责**：核心落库节点。若已有游客则更新 `type=1` 转正；若新用户则加盐 Hash 密码写入 `user` 表并插入 `user_auths`；立即主动刷新 Redis 用户详情缓存；触发注册 MQ 广播和会员积分赠送。
   - **输入**：`$context->email`, 密码明文, `$context->guestInfo`, `$context->thirdInfo`
   - **输出**：`$context->userId`, `$context->userData`

### 练习 2：画注册 Node 顺序图

依据 `\common\services\RegisterService::register` 真实执行链路绘制：

```text
HTTP POST /register/email-register 或 code-register
  ↓
RegisterController (参数提取、验证码前置验证、注入 channel_type)
  ↓
\common\services\RegisterService::register($params)
  ↓
RegisterContext 实例化与基础参数注入
  ↓
NodeExecutionEngine 开启节点执行引擎
  ↓
[Node 1] RegisterParamsCheckNode
  └─ 校验 channelType 必填，防空请求打穿
  ↓
[Node 2] SetGuestInfoNode
  └─ 判断当前 token 是否为游客 (type=3)，提取 guestInfo
  ↓
[Node 3] SetThirdInfoNode
  └─ 验签 OAuth Token / 解析 Apple、Google、Firebase 信息，补齐 email
  ↓
[Node 4] ThirdInfoIsRegisterNode
  └─ 查 user_auths 表，判断三方 identifier 是否已注册
  ↓
[Node 5] RegisterNode
  ├─ 邮箱查重：若已注册则标记 previouslyRegistered
  ├─ 游客转正 (update) 或 新建用户 (insert) + 密码 Hash 加密
  ├─ 绑定记录落库 (user_auths)
  ├─ 主动刷新用户缓存: UserService::setUserDetailRedis(userId)
  ├─ 发送 MQ 事件广播: RabbitMq::send('direct.user_register')
  └─ 赠送注册欢迎积分与绑定设备信息
  ↓
[Node 6] RegisterSubscribeNode
  └─ 执行邮箱订阅 (SubscribeService::subscribe)，领取新人专属券
  ↓
[Node 7] ReturnDataFormatNode
  └─ 签发正式登录 JWT (JwtService::createToken, 有效期 180 天)
  └─ 组装 user_id, email, token, subscribe_info, coupon_alias_format
  ↓
统一输出: { code: 1, data: {...}, info: 'success', request_id: '...' }
```

### 练习 3：标注敏感字段

在 `RegisterContext` 与请求参数流转中，以下字段绝对禁止明文记入持久化日志或向前端未经脱敏回显：

| 敏感字段 | 来源/位置 | 敏感等级 | 处理规范 | 风险后果 |
|---|---|---|---|---|
| `params['password']` | 客户端请求入参 | 🔴 极高（核心凭据） | 绝对**禁止打印进任何文本日志**（包含 debug 日志）；入库前必须由 `BaseFunction::passwordCrypt` 执行加盐不可逆散列 | 日志泄露或数据库泄露引发撞库与用户账号全量失窃 |
| `params['code']` | 验证码输入 | 🟠 高（临时验证凭证） | 只能在内存中用于比较；严禁打入业务日志；验证通过后立即废弃 | 攻击者监听日志即可绕过邮箱/手机实名鉴权 |
| `params['id_token']` / `access_token` | 第三方 OAuth 入参 | 🟠 高（第三方授权凭据） | 验签与获取用户身份后即丢弃，不可明文保存或拼接进 URL 日志 | 攻击者可利用未失效 Token 伪造三方身份发起请求 |
| `response['token']` | `JwtService::createToken` | 🟠 高（平台主会话凭证） | 有效期长达 180 天，禁止打印在 Nginx 访问日志与普通业务日志中 | 令牌被截获后可在 180 天内长期非法冒充用户 |
| `email` / `phone` | 用户注册入参 | 🟡 中（PII 个人隐私） | 数据库中必须由 `UserSensitiveFormat` 加密存储；前端回显或调试日志中需调用 `getCryptoStr` 掩码脱敏 | 遭受数据爬取或隐私合规重罚（如 GDPR / CCPA 违规） |
| `thirdInfo['identifier']` | Apple sub / Google sub / OpenID | 🟡 中（第三方用户唯一标识） | 返回给前端时必须掩码处理（如 `getCryptoStr`：保留首 2 尾 2，中间 `****`） | 第三方账户 ID 泄露导致跨应用撞库关联追踪 |

---

## 4. JS/Node.js 类比

- 注册链 ≈ 多步 middleware
- Context ≈ `req.context`
- Node 失败 ≈ throw / next(error)
- CreateUserNode ≈ userRepository.create
- GenerateTokenNode ≈ authService.issueToken

---

## 5. AI Review 提问

```text
我正在学习 RegisterService 注册 Node 链。
我已经列出前 5 个 Node、Context 字段和注册顺序图。
请你检查：
1. Node 顺序是否合理？
2. 哪些步骤必须在创建用户之前完成？
3. 重复注册如何防止？
4. 哪些字段必须脱敏或不能写日志？
5. 与 Express middleware 管道的类比是否准确？
```

---

## 6. 今日产出

- [x] 注册 Node 图
- [x] 5 个 Node 职责表
- [x] 注册 Context 字段表
- [x] 注册安全风险清单
- [x] AI Review 记录

---

## 7. 今日完成标准

- [x] 能列 5 个注册 Node
- [x] 能解释 Node 顺序为什么重要
- [x] 能说明重复注册如何防止
- [x] 能标注密码、验证码、token 等敏感字段
- [x] 能用 middleware 类比注册链

---

## 8. 今日自测题

### 8.1 为什么注册流程适合拆成 Node 链，而不是写在一个大函数里？

参考答案：

> ✅ 注册包含参数校验、验证码校验、重复用户检查、创建用户、生成 token、写缓存等很多步骤。拆成 Node 链后每个 Node 只负责一个安全步骤，任何一步失败都能中断，代码更清晰也更好维护和复用。

---

### 8.2 注册 Node 的顺序为什么很重要？举个反例。

参考答案：

> ✅ 顺序错了会带来风险。例如「先创建用户再校验验证码」，一旦验证码失败就会留下脏用户数据。更合理的顺序是：参数校验 → 验证码校验 → 重复用户检查 → 创建用户 → 生成 token。

---

### 8.3 如何防止同一个手机号/邮箱重复注册？

参考答案：

> ✅ 双重保障：数据库层加唯一索引兜底，业务层用「重复用户检查 Node」提前拦截。只靠某一层都不够可靠。

---

### 8.4 注册 Context 中哪些字段属于敏感字段，不能随意打印日志？

参考答案：

> ✅ 密码（或 hash 前的明文）、验证码、token 都是敏感字段，写日志前必须脱敏或直接不打印，否则日志泄露会造成严重安全问题。

---

### 8.5 密码为什么必须 hash 存储，而不是明文？

参考答案：

> ✅ 明文存储一旦数据库泄露，所有用户密码直接暴露，还可能被撞库攻击其它网站。hash 存储（如 bcrypt）即使泄露也很难还原原始密码。

---

## 9. 学习记录

| 记录项 | 内容 |
|--------|------|
| 今日最清楚的概念 | 注册 Node 流水线编排设计：`RegisterParamsCheckNode` → `SetGuestInfoNode`（游客平滑转正） → `SetThirdInfoNode` → `ThirdInfoIsRegisterNode` → `RegisterNode`（落库+缓存预热+MQ） → `RegisterSubscribeNode` → `ReturnDataFormatNode` |
| 今日最卡的概念 | 命名空间历史遗留的「多套 RegisterService 并存」坑：Controller 实际调用的是顶层 `\common\services\RegisterService::register`，而非 `user\RegisterService`；以及游客转正与新注册在落库节点的分流处理 |
| JS/Node 类比是否帮助理解 | 非常形象，每个 Node 就是一个带上下文的 Express/Koa 中间件处理函数，Context 共享请求状态，任何一步抛异常都会触发执行引擎回滚与中断 |
| 实际耗时 | 约 3h（主链 7 个 Node 逐行核对 + 跨控制器反查） |
| 明日要补的问题 | Day 04：OAuth 第三方登录流程中各服务（Google/Apple/FB）的 token 验签与身份映射细节 |

---

## 10. AI Review 提示词

```text
我正在进行 Week 09 Day 03：注册 Node 链 的学习。
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
