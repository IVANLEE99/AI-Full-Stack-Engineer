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

- `user-service/common/services/user/RegisterService.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读记录：

| Node | 输入 | 输出 | 职责 | 失败时提示 |
|---|---|---|---|---|
|  |  |  |  |  |

---

## 3. 练习任务

### 练习 1：列出注册前 5 个 Node

记录名称、职责、输入和输出。

### 练习 2：画注册 Node 顺序图

```text
RegisterService
  ↓
ValidateInputNode
  ↓
VerifyCodeNode
  ↓
CheckUserExistsNode
  ↓
CreateUserNode
  ↓
GenerateTokenNode
```

按源码实际顺序修正。

### 练习 3：标注敏感字段

列出 Context 中哪些字段不能打印日志。

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

- [ ] 注册 Node 图
- [ ] 5 个 Node 职责表
- [ ] 注册 Context 字段表
- [ ] 注册安全风险清单
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能列 5 个注册 Node
- [ ] 能解释 Node 顺序为什么重要
- [ ] 能说明重复注册如何防止
- [ ] 能标注密码、验证码、token 等敏感字段
- [ ] 能用 middleware 类比注册链

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
| 今日最清楚的概念 |  |
| 今日最卡的概念 |  |
| JS/Node 类比是否帮助理解 |  |
| 实际耗时 |  |
| 明日要补的问题 |  |

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
