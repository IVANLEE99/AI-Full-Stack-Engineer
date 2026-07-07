# Week 15 Day 05：错误处理与类比日

> 所属周：Week 15：Agent + Tool Calling  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`ai-lab/customer-agent`  
> 类型：类比日  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

给客服 Agent 加上完善的错误处理（fallback），让它在「工具报错、超时、参数缺失、模型抽风」时都能给用户一个友好的交代，而不是崩溃或胡说。今天也是类比日，把本周学的 Agent 概念用 Node 语言系统化梳理一遍。

今天你要真正掌握这一句话：

> Agent 的每一层都可能出错（模型层、工具层、网络层），fallback 的本质是：在每一层用 try/catch 兜住异常，把技术错误翻译成用户能懂的友好话术，同时把真实错误记进日志给自己排查。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 搞清楚 Agent 有哪几层会出错
2. 学会给「工具执行」加 try/catch
3. 学会处理「工具返回业务错误」（查不到、缺货）
4. 学会处理「超时」和「重试」
5. 把技术错误翻译成友好话术（错误话术表）
6. 加一个「最大轮次」保护，防止 Agent 死循环
7. 做本周类比打卡
8. 写今日笔记与自测

---

## 1. 学习内容

### 1.1 Agent 会在哪几层出错

一个请求从用户到回复，中间要经过好几层，每层都可能翻车：

```text
用户输入
  │
  ├─(A) 调模型 API    → 网络超时 / 限流 / key 失效 / 返回格式怪异
  │
  ├─(B) 解析 tool_call → 参数缺失 / JSON 解析失败 / 工具名不存在
  │
  ├─(C) 执行工具      → 数据库查不到 / 第三方接口挂了 / 业务异常（缺货）
  │
  └─(D) 模型总结      → 又一次调模型，可能再超时
```

小白重点：

> 别只想着「工具会不会报错」。调模型本身（A、D）也是网络请求，也会超时限流。错误处理要覆盖每一层，不能只保护中间那一段。

---

### 1.2 给工具执行加 try/catch

昨天的 `executeTool` 太乐观了，今天给它包上防护：

```js
async function executeToolSafe(name, args) {
  const handler = toolRouter[name];
  if (!handler) {
    return { ok: false, error: "UNKNOWN_TOOL", message: `未知工具：${name}` };
  }
  try {
    const data = await handler(args);
    return { ok: true, data };
  } catch (err) {
    // 关键：真实错误记日志，但不直接抛给用户
    console.error(`[tool:${name}] 执行失败`, err);
    return { ok: false, error: "TOOL_EXEC_ERROR", message: "工具执行出错" };
  }
}
```

小白重点：

> 统一返回 `{ ok, data | error, message }` 结构，调用方一看 `ok` 就知道成没成，不用到处 try/catch。这叫「把异常转成返回值」，链路更清晰。

---

### 1.3 处理业务错误（查不到、缺货）

有些「错误」不是异常，是正常的业务结果，比如订单号不存在、商品缺货。这些也要喂回给模型，让它用人话解释：

```js
function queryOrder(orderNo) {
  if (!orderNo) {
    return { ok: false, error: "MISSING_ARG", message: "缺少订单号" };
  }
  const order = orderDB[orderNo];
  if (!order) {
    return { ok: false, error: "NOT_FOUND", message: "查无此订单" };
  }
  return { ok: true, data: order };
}
```

回喂给模型时，把这个结果如实告诉它：

```js
messages.push({
  role: "tool",
  tool_call_id: call.id,
  content: JSON.stringify({ ok: false, error: "NOT_FOUND", message: "查无此订单" }),
});
// 模型看到后会说："没有查到这个订单号，请核对一下订单号是否正确～"
```

小白重点：

> 「查不到」不该抛异常让程序崩，而是作为一种正常结果告诉模型，让模型转成友好话术。异常留给「真的出故障」的情况（数据库连不上）。

---

### 1.4 处理超时与重试

调模型或调工具都可能卡住，要设超时；偶发失败可以重试一两次：

```js
// 给任意 Promise 加超时
function withTimeout(promise, ms) {
  const timeout = new Promise((_, reject) =>
    setTimeout(() => reject(new Error("TIMEOUT")), ms)
  );
  return Promise.race([promise, timeout]);
}

// 带重试的调用
async function callWithRetry(fn, retries = 2) {
  for (let i = 0; i <= retries; i++) {
    try {
      return await withTimeout(fn(), 8000);
    } catch (err) {
      if (i === retries) throw err; // 最后一次还失败，才真正抛出
      console.warn(`第 ${i + 1} 次失败，重试...`, err.message);
    }
  }
}
```

对比表：

| 错误类型 | 该重试吗 | 原因 |
|---|---|---|
| 网络超时 | ✅ 可以 | 偶发，重试常能成功 |
| 限流 429 | ✅ 但要退避等待 | 立刻重试还会被限流 |
| 参数错误 400 | ❌ 不要 | 重试还是错，白费 |
| key 失效 401 | ❌ 不要 | 得改配置，重试无意义 |
| 缺货/查无此单 | ❌ 不要 | 这是正常业务结果 |

小白重点：

> 不是所有错误都值得重试。「偶发性错误」（超时、限流）重试有用；「确定性错误」（参数错、权限错、业务结果）重试纯属浪费。

---

### 1.5 把技术错误翻译成友好话术

用户不该看到 `TOOL_EXEC_ERROR` 或一串堆栈。做一张翻译表：

```js
const friendlyMessage = {
  TIMEOUT:         "系统有点忙，稍等一下再试试好吗～",
  TOOL_EXEC_ERROR: "查询时出了点小问题，我帮您转接人工客服可以吗？",
  NOT_FOUND:       "没有查到相关信息，麻烦您核对一下～",
  MISSING_ARG:     "还需要您提供一下具体信息哦，比如订单号～",
  RATE_LIMIT:      "当前咨询人数较多，请稍后再试～",
  DEFAULT:         "不好意思，出了点小状况，您可以稍后重试或联系人工客服。",
};

function toUserMessage(code) {
  return friendlyMessage[code] || friendlyMessage.DEFAULT;
}
```

小白重点：

> 记住两条黄金原则：（1）给用户看友好话术，绝不暴露堆栈/内部错误码；（2）真实错误一定要写日志（含错误码、参数、时间），否则线上出问题你无从查起。

---

### 1.6 加最大轮次保护，防止死循环

Agent 循环里，模型可能一直要求调工具，永不停止（比如工具老报错、模型反复重试）。必须设上限：

```js
const MAX_TURNS = 5;

async function runAgent(userInput) {
  const messages = [ /* system, user... */ ];
  for (let turn = 0; turn < MAX_TURNS; turn++) {
    const res = await callModel(messages);
    if (!res.tool_calls) return res.content; // 出结果，正常结束
    // ... 执行工具、回喂 ...
  }
  // 到这说明转了 5 圈还没结果 → 兜底
  return "抱歉，这个问题我暂时处理不了，已为您转接人工客服。";
}
```

小白重点：

> `MAX_TURNS` 是 Agent loop 的「安全绳」。没有它，一旦模型和工具陷入互踢，就会无限调用、烧钱、卡死。这是生产 Agent 的必备保护。

---

### 1.7 完整的分层兜底策略（总结）

```text
┌─ 调模型超时/限流   → 重试 N 次，仍失败 → "系统忙，稍后再试"
├─ tool_call 解析失败 → 记日志 → "没太理解，请换个说法"
├─ 工具执行异常      → try/catch → 记日志 → "查询出错，转人工"
├─ 业务结果为空      → 如实回喂 → 模型说 "没查到，请核对"
└─ 超过最大轮次      → 强制结束 → "暂时处理不了，转人工"
```

---

## 2. 源码阅读

- `ai-lab/customer-agent/src/agent.ts`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. 调模型/调工具处有没有 try/catch 或超时保护
2. 有没有重试逻辑，重试了哪些错误类型
3. 有没有 MAX_TURNS 之类的循环上限
4. 错误是记日志了还是直接抛给了用户
5. 用户看到的兜底话术在哪定义

建议在笔记里填一张表：

| 出错层 | 现有保护 | 是否重试 | 用户话术 |
|---|---|---|---|
| 调模型 |  |  |  |
| 解析 tool_call |  |  |  |
| 执行工具 |  |  |  |
| 循环失控 |  |  |  |

---

## 3. 练习任务

### 练习 1：给工具加防护

把昨天的 `executeTool` 改成 `executeToolSafe`，统一返回 `{ ok, data | error, message }`，并在 catch 里 `console.error` 记录真实错误。

---

### 练习 2：模拟各种失败

故意制造 4 种错误，观察 Agent 的反应：

| # | 制造方式 | 期望用户看到 |
|---|---|---|
| 1 | 查一个不存在的订单号 | 「没查到，请核对订单号」 |
| 2 | 让 queryOrder 里 `throw new Error("db down")` | 「查询出错，转人工」+ 日志有 db down |
| 3 | 把工具超时设成 1ms 触发 TIMEOUT | 「系统忙，稍后再试」 |
| 4 | 让模型每轮都要求调工具（模拟死循环） | 转满 5 轮后「暂时处理不了，转人工」 |

记录表：

| 场景 | 用户看到的话术 | 日志里有没有真实错误 | 是否符合预期 |
|---|---|---|---|
| 1 |  |  |  |
| 2 |  |  |  |
| 3 |  |  |  |
| 4 |  |  |  |

---

### 练习 3：错误话术表

补全 `friendlyMessage` 映射，确保每个错误码都有对应的人话，且都有 DEFAULT 兜底。检查：用户在任何情况下都看不到堆栈或内部错误码。

---

### 练习 4：本周类比打卡

用本周类比打卡模板，把「Agent + Tool Calling」这个大概念梳理一遍：

```text
本周概念：Agent + Tool Calling（会调用工具的 AI 客服）
Node 等价：一个 while 循环里反复调 LLM API，LLM 返回"要调哪个函数+参数"，
          我执行函数、把结果喂回去，直到 LLM 给出最终答复；错误处理 = 每层 try/catch + 友好话术
差异：普通 API 是"我决定调哪个函数"，Agent 是"LLM 决定调哪个函数"；
     控制权从代码转移到了模型，所以更要靠 System Prompt 约束 + fallback 兜底
我能用自己的话解释吗：是 / 否
理解自评：1 / 2 / 3 / 4 / 5
```

---

## 4. JS/Node.js 类比

| 错误处理概念 | Node.js / JS 类比 | 说明 |
|---|---|---|
| 工具 try/catch | Express 里的 try/catch 中间件 | 兜住 handler 抛的异常 |
| 统一 `{ok, error}` 返回 | Result 模式 / `[err, data]` | 把异常转成返回值 |
| 超时保护 | `Promise.race` + setTimeout | 卡住不能无限等 |
| 重试 | axios-retry / p-retry | 偶发失败自动再试 |
| MAX_TURNS | 循环上限 / 防死循环计数 | 避免无限递归 |
| 友好话术表 | 错误码 → i18n 文案映射 | 技术错误翻译成用户语言 |
| 记日志 | logger.error(err) | 真实错误留给自己排查 |

一句话类比：

> Agent 的错误处理 ≈ 一个健壮的 Express 服务：每层 try/catch、超时用 Promise.race、偶发失败重试、给客户端返回友好文案、给日志留真实堆栈——只是这里的「路由」由模型决定，所以还要多一根 MAX_TURNS 安全绳。

---

## 5. AI Review 提问

```text
我正在学习 Week 15 Day 05：Agent 的错误处理与 fallback。
我给 Agent 加了：工具 try/catch、超时+重试、MAX_TURNS 上限、友好话术表。
代码如下：（贴上 executeToolSafe / callWithRetry / runAgent 的循环 / friendlyMessage）

请你按资深后端工程师标准帮我检查：
1. 我的分层错误处理（模型层/工具层/循环层）有没有遗漏的出错点？
2. 我的重试策略（哪些重试、哪些不重试）合理吗？
3. MAX_TURNS 的值和兜底话术设计得怎么样？
4. 我有没有不小心把内部错误/堆栈暴露给用户？
5. 用「健壮的 Express 服务」类比是否准确？
6. 生产环境还需要补什么（限流退避、熔断、告警、幂等）？

请用中文输出：我做对的地方、问题清单、修改建议、下一步练习。
```

---

## 6. 今日产出

- [ ] executeToolSafe（带 try/catch + 统一返回结构）
- [ ] withTimeout + callWithRetry
- [ ] MAX_TURNS 循环上限
- [ ] friendlyMessage 错误话术表
- [ ] 4 种失败场景的测试记录
- [ ] 本周类比打卡记录
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说出 Agent 会在哪几层出错
- [ ] 能给工具执行加 try/catch 并统一返回结构
- [ ] 能区分「该重试」和「不该重试」的错误
- [ ] 能给 Agent loop 加 MAX_TURNS 保护
- [ ] 用户在任何失败场景都能看到友好话术，看不到堆栈
- [ ] 真实错误都记进了日志
- [ ] 完成本周类比打卡

---

## 8. 今日自测题

### 8.1 Agent 主要会在哪几层出错？

参考答案：

> ✅ 调模型层（超时/限流/key 失效）、解析 tool_call 层（参数缺失/JSON 错/工具名不存在）、执行工具层（数据库挂/第三方挂/业务异常）、模型总结层（又一次调模型）。错误处理要覆盖每一层。

---

### 8.2 「查无此订单」应该抛异常吗？

参考答案：

> ✅ 不该。这是正常的业务结果，应作为返回值（`{ok:false, error:"NOT_FOUND"}`）如实喂回给模型，让模型用人话解释。异常只留给「真的出故障」（数据库连不上）。

---

### 8.3 哪些错误值得重试，哪些不值得？

参考答案：

> ✅ 偶发性错误值得重试：网络超时、限流（要退避）。确定性错误不该重试：参数错 400、权限错 401、以及缺货/查无此单等业务结果——重试还是一样的结果，纯浪费。

---

### 8.4 MAX_TURNS 是干什么的？

参考答案：

> ✅ 它是 Agent loop 的循环上限（安全绳）。防止模型和工具互踢导致无限调用、烧钱、卡死。超过上限就强制结束并走兜底话术（转人工）。

---

### 8.5 处理错误时对「用户」和「日志」分别该怎么做？

参考答案：

> ✅ 给用户看友好话术，绝不暴露堆栈或内部错误码；同时把真实错误（错误码、参数、时间、堆栈）写进日志，否则线上出问题无从排查。两手都要抓。

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
我正在进行 Week 15 Day 05：错误处理与类比日 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 15 README](./README.md)
