# Week 09 Day 02：UserService 与 Redis 缓存

> 所属周：Week 09：用户服务 + 注册 Node 链  
> 阶段：第三阶段：业务域深入  
> 主仓库/项目：`user-service`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解用户资料为什么需要 Redis 缓存，读懂 `UserDetailRedis` 这类封装如何设计缓存 key、写入、读取、失效和防止脏数据。

今天你要真正掌握这一句话：

> 用户缓存的目标是加速高频读取，但用户资料、手机号、邮箱、权限等敏感数据必须有清晰的 key 规则、失效策略和脱敏意识，不能为了快而牺牲正确性和隐私。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解用户资料为什么是高频读取数据
2. 理解缓存读写常见模式：cache aside
3. 打开 `UserDetailRedis.php`，找 key 规则
4. 阅读 UserService 里 `getUserInfoByRedis` 类似方法
5. 记录什么时候读缓存、什么时候回源 DB
6. 记录什么时候更新/删除缓存
7. 分析用户资料的隐私字段是否需要脱敏
8. 用 ioredis 用户缓存封装做类比
9. 用 AI Review 检查缓存失效策略是否合理

---

## 1. 学习内容

### 1.1 用户资料为什么适合缓存？

订单、支付、通知、BFF 都经常需要用户信息：

- `user_id`
- nickname
- avatar
- 手机号/邮箱
- 会员等级
- 用户状态

如果每次都查数据库，压力会很大。

缓存可以减少数据库访问：

```text
先查 Redis
  ↓ 命中：直接返回
  ↓ 未命中：查 DB → 写 Redis → 返回
```

这就是常见的 cache aside 模式。

---

### 1.2 `UserDetailRedis` 是什么？

它通常是用户详情缓存封装类，负责：

| 能力 | 说明 |
|---|---|
| 生成 key | 如 `user:detail:{user_id}` |
| 读取缓存 | 从 Redis 获取用户资料 |
| 写入缓存 | DB 查到后写 Redis |
| 删除缓存 | 用户资料更新后失效 |
| 设置 TTL | 防止缓存永久不更新 |

伪代码：

```php
<?php

$key = 'user:detail:' . $userId;
$cache = $redis->get($key);

if ($cache !== null) {
    return json_decode($cache, true);
}

$user = $userRepository->findById($userId);
$redis->setex($key, 3600, json_encode($user));

return $user;
```

---

### 1.3 缓存 key 规则怎么设计？

好的 key 要清晰、稳定、可定位。

示例：

| key | 含义 |
|---|---|
| `user:detail:1001` | 用户 1001 的详情 |
| `user:token:abc` | token 对应用户 |
| `user:profile:1001` | 用户公开资料 |
| `user:permission:1001` | 用户权限缓存 |

不要使用含义模糊的 key：

```text
u_1001
data_1001
cache_user
```

---

### 1.4 什么时候写入和失效？

常见策略：

| 场景 | 处理 |
|---|---|
| 第一次读取用户资料 | 查 DB 后写缓存 |
| 用户修改昵称/头像 | 更新 DB 后删除缓存 |
| 用户绑定手机号 | 更新 DB 后删除缓存 |
| 用户状态被封禁 | 必须立即删除/更新缓存 |
| token 失效 | 删除 token 缓存 |

重点：更新用户资料时不能只改 DB，不处理缓存，否则会读到旧数据。

---

### 1.5 用户缓存有哪些风险？

| 风险 | 后果 | 应对 |
|---|---|---|
| 缓存不失效 | 用户资料一直旧 | 更新后删除缓存 |
| 缓存穿透 | 不存在 user_id 频繁查 DB | 缓存空值或限流 |
| 缓存雪崩 | 大量 key 同时过期 | TTL 加随机值 |
| 敏感信息泄露 | 手机号/邮箱被滥用 | 按场景脱敏 |
| 权限缓存过期慢 | 被封禁用户仍可访问 | 权限类缓存短 TTL 或主动失效 |

---

### 1.6 隐私数据如何处理？

用户资料可能包含敏感信息：

- 手机号
- 邮箱
- 真实姓名
- 地址
- 第三方 open_id
- token

返回给前端时要按场景控制：

```text
订单详情需要收货人手机号，但用户列表不一定需要完整手机号。
```

脱敏示例：

```text
138****8888
u***@example.com
```

---

### 1.7 Node.js 类比

Node 中 `ioredis` 封装：

```js
async function getUserDetail(userId) {
  const key = `user:detail:${userId}`;
  const cached = await redis.get(key);
  if (cached) return JSON.parse(cached);

  const user = await userRepository.findById(userId);
  await redis.set(key, JSON.stringify(user), 'EX', 3600);
  return user;
}
```

PHP 的 `UserDetailRedis` 思路类似。

---

## 2. 源码阅读

- `user-service/common/redis/user/UserDetailRedis.php`
- `user-service/common/BaseRedis.php`
- `user-service/common/services/user/UserService.php`

> 说明：路径均为公开代号 + 相对路径。本地参考源码见 [UserDetailRedis.php](./UserDetailRedis.php)。

阅读记录：

| 阅读点 | 记录 |
|---|---|
| 缓存 key 规则 | `self::$key['user']['detail'] . $userId` → `bm:user:detail:by:id:{userId}`（前缀继承自 `common\BaseRedis::$key['user']['detail']`） |
| TTL | `86400 * 3` 秒（3 天 = 259,200 秒），通过 `redis->setex()` 设置 |
| 连接/数据库实例 | `\Yii::$app->redisUserDetail`，内部调用 `$connect->select(7)` 切换到 DB 7 号库进行命名空间隔离 |
| 读取方法 | `getUserDetail($userId)`：先从 Redis `get($key)`；未命中时触发**被动回源缓存**，调 `UserRepository::getUserById($userId)`，若查到则补齐 `user_id` 并调 `setUserDetail` 写回 Redis，最后返回解码的关联数组 |
| 写入方法 | `setUserDetail($userId, $userDetail)`：执行 `$redis->setex($key, 86400 * 3, @json_encode($userDetail, 320))`（320 即 `JSON_UNESCAPED_SLASHES \| JSON_UNESCAPED_UNICODE`） |
| 删除/失效方法 | `delUserDetail($userId)`：执行 `$redis->del($key)` |
| 是否缓存敏感字段 | ⚠️ **是，存在重大数据暴露风险**：回源读取的是 `UserRepository::getUserById`（默认 `select *` 且经 `fieldDecrypt` 还原明文），因此 Redis 中以明文 JSON 存储了该用户的完整表记录，**包含密码哈希（`password`）、明文手机号（`phone`）、明文邮箱（`email`）、真实姓名（`first_name`/`last_name`）** |

### 2.1 源码时序与两种读写策略对比

#### 策略 A：被动缓存（Read-Through / Cache Aside）
主要入口：`UserDetailRedis::getUserDetail($userId)`
```text
调用 getUserDetail(userId)
  ↓
Redis GET bm:user:detail:by:id:{userId}
  ├─ 命中：@json_decode($json, true) → 返回
  └─ 未命中（被动回源）：
       ↓
     UserRepository::getUserById(userId)  // MySQL: select * from user where id=... and del_flag=0
       ├─ DB 查无此人：直接 return []（❌ 未写空缓存，存在缓存穿透隐患）
       └─ DB 查到用户：
            补齐 user_id = id
            setUserDetail(userId, userDetail) // Redis SETEX 3 天
            返回 userDetail
```

#### 策略 B：主动预热（Write-Through）
主要入口：`UserService::setUserDetailRedis($userId)`
```text
调用 setUserDetailRedis(userId)
  ↓
UserRepository::getUserById(userId)   // 查 DB 最新数据
  ↓
UserDetailRedis::delUserDetail(userId) // 先删旧缓存
  ↓
UserDetailRedis::setUserDetail(userId, userDetail) // 写入新缓存（TTL 3 天）
  ↓
返回最新 userDetail
```

---

## 3. 练习任务

### 练习 1：读 `UserDetailRedis`

记录 key、TTL、get/set/delete 方法。

- **Key 结构**：`bm:user:detail:by:id:{userId}`（由 `BaseRedis::$key['user']['detail'] = 'bm:user:detail:by:id:'` 与整型 `$userId` 拼接）。
- **TTL 设定**：硬编码为 `86400 * 3` 秒（72 小时）。
- **DB 实例**：`\Yii::$app->redisUserDetail->select(7)`，与通用缓存/消息队列物理/逻辑库隔离。
- **获取（Get）**：`getUserDetail($userId)` 实现了**被动回源（Lazy Load）**。但注意：若数据库查空时直接 `return []`，未缓存空值标记，容易遭受**缓存穿透**。
- **写入（Set）**：`setUserDetail($userId, $userDetail)` 统一执行 `setex`，对数据做了 JSON 序列化（带参数 320）。
- **删除（Del）**：`delUserDetail($userId)` 直接调用 Redis `DEL` 命令，用于资料变更时的失效淘汰。

### 练习 2：列缓存 key 规则

| 数据 | key 规则 | TTL | 是否敏感 | 说明/业务场景 |
|---|---|---:|---|---|
| 用户详情 | `bm:user:detail:by:id:{userId}` | 3 天 (259200s) | ⚠️ 高度敏感（含密码哈希与解密 PII） | `UserDetailRedis`，加速 C 端与内部服务频繁查用户 |
| 登录态 Token | `bm:login:{token}`（或 JWT 客户端自包含） | 与登录有效期相同 | ⚠️ 敏感凭证 | `BaseRedis::$key['user']['login']`，服务端维护登录态映射 |
| 找回密码 Token | `bm:forget:password:{token}` | 15 分钟 (900s) | ⚠️ 高度敏感（可设密） | 邮件重置密码校验，验后即焚 |
| 访问授权 Token | `bm:user:access_token:{sn}:{userId}:{eid}` | 临时有效 | ⚠️ 敏感 | 针对特定业务单号与设备的授权访问 |
| 用户设备信息 | `bm:user:device:number:{userId}` | 长期 | 普通 | 记录 App Push 设备号与操作系统版本 |
| 邮箱验证码 | `bm:email:code:{email}:{businessCode}` | 5~15 分钟 | 敏感 | 注册、找回密码、绑定邮箱的图形/短信/邮件验证码 |

### 练习 3：说明何时写入/失效

真实代码库中的 6 个典型失效与写入场景：

1. **场景 1：用户个人资料更新（修改昵称、头像）**
   - **调用链路**：`UserController::actionUpdate` → `UserUpdateNode::invokeNode`
   - **动作**：`UserRepository::updateUserById(...)` 更新数据库后，执行 `UserDetailRedis::instance()->delUserDetail($context->userId);`。
   - **机制**：标准 Cache Aside 模式，先更 DB，再删缓存，由下次读取时被动重建。
2. **场景 2：用户修改或重置密码**
   - **调用链路**：`RegisterService::resetPassword` → `ResetPasswordNode::invokeNode`
   - **动作**：更新 DB 密码后，调用 `UserService::instance()->setUserDetailRedis($context->userId);`（内部先 `del` 后查 DB 再 `set`）。
   - **机制**：主动刷新预热，确保后续立刻用新密码/凭据读取时命中最新数据。
3. **场景 3：新用户注册落库**
   - **调用链路**：`RegisterService::register` → `RegisterNode::invokeNode` 或 `UserAddFormatNode`
   - **动作**：用户插入 DB 成功后，立即调用 `UserService::setUserDetailRedis($context->userId);`。
   - **机制**：新用户刚注册时紧接着就是登录成功并加载个人主页，主动预热避免首次读取穿透到 DB。
4. **场景 4：用户绑定邮箱 / 第三方账号设密**
   - **调用链路**：`RegisterService::bindEmail` 或 `SocialLinkService::setPassword`
   - **动作**：在更新 `User` 表和 `UserAuths` 表后，主动调用 `UserService::instance()->setUserDetailRedis($userId);`。
   - **机制**：用户认证方式增加（由游客/第三方转为正式邮箱用户），强制同步刷新缓存中的邮箱与状态。
5. **场景 5：首次读取或 TTL 到期自然失效**
   - **调用链路**：`UserDetailRedis::getUserDetail($userId)`
   - **动作**：Redis 查不到 key，回源 `UserRepository::getUserById($userId)`，查到后写入 Redis（TTL 3 天）。
   - **机制**：被动读穿透构建。
6. **场景 6：后台运营封禁 / 审核变更用户类型（高危潜在漏洞）**
   - **调用链路**：管理后台审核企业客户或封禁非法用户
   - **动作**：若后台管理系统只通过 SQL 直接改了 `user.status = 0` 或 `user.type`，而**未跨服务调用 `delUserDetail` 淘汰 Redis**，用户在前台仍能在 3 天内凭借缓存中的 `status=1` 继续正常访问下单！必须在管理端变更状态时发布 Redis 广播或 MQ 消息删除该缓存。

---

## 4. JS/Node.js 类比

- `UserDetailRedis` ≈ ioredis 用户缓存封装
- cache aside ≈ 先 Redis，未命中再 DB
- TTL ≈ Redis EX
- 删除缓存 ≈ cache invalidation
- 脱敏 ≈ response serializer / DTO

---

## 5. AI Review 提问

```text
我正在学习 UserService 与 Redis 用户缓存。
我已经整理了 UserDetailRedis 的 key、TTL、读写和失效策略。
请你检查：
1. 缓存 key 设计是否清晰？
2. 缓存失效策略是否合理？
3. 哪些用户字段不应该直接缓存或返回？
4. 权限和封禁状态缓存要注意什么？
5. 与 ioredis 用户缓存封装的类比是否准确？
```

---

## 6. 今日产出

- [x] `UserDetailRedis` 阅读笔记
- [x] 缓存 key 规则表
- [x] 写入/失效策略表
- [x] 隐私字段脱敏清单
- [x] AI Review 记录

---

## 7. 今日完成标准

- [x] 能说明用户缓存读写流程
- [x] 能说明缓存 key 规则
- [x] 能说明何时写入和失效
- [x] 能列出用户缓存 5 个风险
- [x] 能说明哪些用户资料需要脱敏

---

## 8. 今日自测题

### 8.1 cache aside 模式的读取流程是怎样的？

参考答案：

> ✅ 先查 Redis，命中就直接返回；未命中就回源查数据库，把结果写回 Redis，再返回。这样高频读取大部分走缓存，减少数据库压力。

---

### 8.2 好的缓存 key 应该长什么样？

参考答案：

> ✅ 要清晰、稳定、可定位，通常用带业务前缀的结构化命名，例如 `user:detail:1001`、`user:token:abc`。要避免 `u_1001`、`data_1001`、`cache_user` 这种含义模糊的 key。

---

### 8.3 用户修改了昵称或头像后，缓存要怎么处理？

参考答案：

> ✅ 更新数据库后要删除（或更新）对应的用户缓存，否则下次读取还会命中旧数据。核心原则是：更新用户资料时不能只改 DB 不处理缓存。

---

### 8.4 缓存穿透、缓存雪崩分别是什么，怎么应对？

参考答案：

> ✅ 缓存穿透是指查询不存在的 user_id 反复打到数据库，可以缓存空值或加限流；缓存雪崩是大量 key 同时过期导致数据库瞬间压力过大，可以给 TTL 加随机值错开过期时间。

---

### 8.5 用户资料里的手机号、邮箱返回给前端时要注意什么？

参考答案：

> ✅ 要按场景脱敏，不能无脑返回完整信息。例如手机号显示成 `138****8888`、邮箱显示成 `u***@example.com`。不同场景（订单收货人 vs 用户列表）需要的字段和脱敏程度也不同。

---

## 9. 学习记录

| 记录项 | 内容 |
|--------|------|
| 今日最清楚的概念 | Cache Aside 的两套变体实现：更新资料走「改 DB + `delUserDetail` 删缓存待读时重建」；重要动作（重置密码/注册）走「改 DB + `setUserDetailRedis` 主动预热」 |
| 今日最卡的概念 | 缓存穿透与敏感字段暴露的隐蔽性：`getUserById` 查 `select *` 并解密明文塞进 Redis，缓存直接包含密码哈希；且查空不写空缓存导致的穿透风险 |
| JS/Node 类比是否帮助理解 | 非常有帮助，Node.js 下 ioredis 的 `getUserDetail(id)` 封装、Cache Aside 中 `redis.setex` 与缓存失效逻辑完全一一对应 |
| 实际耗时 | 约 2.5h（源码精读 + 跨控制器/Node 追踪） |
| 明日要补的问题 | Day 03：注册 Node 链的具体执行顺序与各个 Node 的职责编排 |

---

## 10. AI Review 提示词

```text
我正在进行 Week 09 Day 02：UserService 与 Redis 缓存 的学习。
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
