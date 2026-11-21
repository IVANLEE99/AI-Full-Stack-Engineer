# 08｜Middleware：把预算、错误与上下文放到正确边界

[上一章：Agents](07-agents.md) · [课程首页](../README.md) · [下一章：上下文与记忆](09-context-memory.md)

> 完成本章后，你能预测中间件的执行顺序，区分可重试故障与必须终止的错误，并阻止失控调用。
> 版本基线：`langchain@1.6.7`、`@langchain/core@1.5.0`；Node.js ≥ 22.12，ESM + strict TypeScript。
> 本章默认离线。模型替身返回固定或脚本化结果，不代表真实模型判断；不自动启用追踪。

## 1. 目标与前置知识

你熟悉 Express/Koa middleware，但 Agent middleware 包住的不是一次 HTTP 请求。
一次 Agent 请求可能经过多轮模型调用和多个工具执行，因此挂载位置决定语义。

- [ ] 选择 `beforeAgent`、`beforeModel`、`wrapModelCall` 或 `wrapToolCall`。
- [ ] 解释多个中间件的前置、包装与后置顺序。
- [ ] 分开模型额度、工具额度、图步骤和实际下游重试。
- [ ] 把可恢复工具失败转换为安全结果，不吞掉严重错误。
- [ ] 避免在重试、并发和恢复时重复执行写操作。

先读[第 07 章](07-agents.md)，并按[课程首页](../README.md)准备依赖。
下方 TypeScript 片段分别保存到 `examples/scratch-08.ts`，每次替换文件内容。
你在 `langchain/` 下运行 `npm exec -- tsx examples/scratch-08.ts`。
相对导入用 `.js`；`configureRuntime([])` 强制片段离线。

## 2. 概念：hook 改状态，wrapper 包执行

### 2.1 把横切逻辑放在正确位置

| 扩展点 | 何时运行 | 典型输入 | 适合承担的职责 |
|---|---|---|---|
| `beforeAgent` | 每次 Agent invocation 开始 | 状态、runtime | 校验调用上下文、准备本轮状态 |
| `beforeModel` | 每轮模型步骤之前 | 状态、runtime | 检查上下文预算、返回状态更新 |
| `wrapModelCall` | 包装一次模型步骤 | request、handler | 修改本次提示词、模型选择、局部错误处理 |
| `afterModel` | 模型返回后、工具执行前 | 更新后的状态 | 检查响应、更新统计状态 |
| `wrapToolCall` | 包装一个工具调用 | toolCall、state、runtime | 资源授权、审计、工具错误处理 |
| `afterAgent` | 正常完成本次 invocation | 最终状态 | 整理输出、正常完成后的状态更新 |

`beforeModel` 返回的是部分状态更新，不是模型请求对象。
`wrapModelCall` 则把 request 传给 handler，得到 `AIMessage` 或允许的控制流结果。
你改变本次提示词时，优先构造新 request，不直接修改持久化消息历史。
`afterAgent` 不是 JavaScript `finally`：异常或中断时不要依赖它释放资源。

### 2.2 顺序是契约，不是数组装饰

假设你注册 `[A, B]`，且两者都有相关 hook，正常路径是：

```text
beforeAgent：A → B
每轮 beforeModel：A → B
wrapModelCall：A 进入 → B 进入 → model → B 返回 → A 返回
每轮 afterModel：B → A
需要工具时：每个工具经过对应 wrapToolCall 包装，再回到下一轮模型
afterAgent：B → A
```

第一个 wrapper 在最外层，和 Koa 的洋葱模型相似。
外层错误处理可以看到内层重试耗尽后的故障；顺序反过来，语义也会改变。
跳转、异常和人工中断可以提前离开正常路径，不保证全部后置 hook 执行。
多个工具可能并发执行，不能用跨调用的普通变量假定全局顺序。

### 2.3 state 和 context 的区别

| 数据 | 放在哪里 | 生命周期 | 示例 |
|---|---|---|---|
| 本轮与历史业务状态 | `stateSchema` | 使用 checkpointer 时可跨 invocation | 消息、累计模型步骤数 |
| 服务端认证主体与本次策略 | `contextSchema` / `runtime.context` | 本次调用，不自动持久化 | 匿名租户 ID、角色、预算策略 |
| 某次模型请求的临时视图 | `wrapModelCall` 的 request | 当前模型步骤 | 临时系统提示词、工具子集 |

Zod 验证“值长什么样”，不验证“这个人有没有权限”。
你从认证后的 Node handler 构造 context，不从用户消息中的“我是管理员”构造权限。
不要把 token、完整用户档案或下游连接串写进 state。

## 3. 源码与示例阅读：用可观察事件确认顺序

### 3.1 运行本章入口

```bash
npm run example:middleware
```

入口是 [examples/08-middleware.ts](../examples/08-middleware.ts)。
你先看一次正常模型步骤，再看限流与错误分支。
固定替身适合确认顺序和异常传播，不适合验证真实供应商的退避行为。

| 阅读位置 | 重点 | 检查问题 |
|---|---|---|
| `createMiddleware()` | 注册 hook、Schema 与名称 | 名称是否唯一？返回类型是否正确？ |
| `beforeModel` | 部分状态更新或拒绝 | 是每轮检查，还是仅入口检查？ |
| `wrapModelCall` | `await handler(request)` | 捕获的是哪一层错误？ |
| model/tool limit middleware | run 与 thread 计数 | 上限是否仍允许正常收尾？ |
| [src/models.ts](../src/models.ts) | SDK 超时和重试 | 有没有多层重试相乘？ |

包内可读 `node_modules/langchain/dist/agents/middleware.d.ts` 与 `agents/ReactAgent.js`。
`createMiddleware` 声明给出 hook 返回值；图构建源码说明 before 顺序和 after 逆序。

### 3.2 一个可以检查顺序的片段

这里的事件数组仅用于单次离线教学，不是多租户审计存储。

```typescript
import { createAgent, createMiddleware } from "langchain";
import { FakeListChatModel } from "@langchain/core/utils/testing";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
const events: string[] = [];
const observe = (name: string) => createMiddleware({
  name,
  beforeModel: () => { events.push(`${name}.before`); },
  afterModel: () => { events.push(`${name}.after`); },
  wrapModelCall: async (request, handler) => {
    events.push(`${name}.enter`);
    try { return await handler(request); }
    finally { events.push(`${name}.leave`); }
  },
});
const agent = createAgent({
  model: new FakeListChatModel({ responses: ["固定回答"] }),
  tools: [],
  middleware: [observe("outer"), observe("inner")],
});
await agent.invoke({ messages: [{ role: "user", content: "检查顺序" }] });
console.log(events);
```

你应检查顺序为 `outer.before → inner.before → outer.enter → inner.enter`，
然后是 `inner.leave → outer.leave → inner.after → outer.after`。
如果你删除 wrapper 内的 `await`，`finally` 会过早运行，错误也不能按预期被局部捕获。
真实审计只保留匿名主体、工具名、调用标识与状态；不要打印原始 request 或完整消息。

### 3.3 错误处理不等于重试

工具返回“没有找到资料”是正常业务结果，不一定是异常。
工具临时不可用可以转换为安全的错误 `ToolMessage`，让模型解释能力受限。
鉴权失败、程序错误或审批中断则需要保留其失败/控制流语义。

```typescript
import { createAgent, FakeToolCallingModel, tool, toolErrorMiddleware, toolCallLimitMiddleware, modelCallLimitMiddleware } from "langchain";
import { z } from "zod";
import { ToolMessage } from "@langchain/core/messages";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
class TemporaryLookupError extends Error {}
const probe = tool(async () => { throw new TemporaryLookupError(); }, {
  name: "read_only_probe", description: "模拟只读查询临时不可用，无业务副作用。",
  schema: z.object({}).strict(),
});
const agent = createAgent({
  model: new FakeToolCallingModel({ toolCalls: [
    [{ name: "read_only_probe", args: {}, id: "call-probe-1" }], [],
  ] }),
  tools: [probe],
  middleware: [
    toolErrorMiddleware({ tools: [probe], onError: (error) => {
      if (error instanceof TemporaryLookupError) return "LOOKUP_UNAVAILABLE：暂时无法查询，请稍后重试。";
    } }),
    toolCallLimitMiddleware({ runLimit: 1, exitBehavior: "error" }),
    modelCallLimitMiddleware({ runLimit: 3, exitBehavior: "error" }),
  ],
});
const state = await agent.invoke({ messages: [{ role: "user", content: "测试只读故障" }] });
console.log(state.messages.filter((message) => ToolMessage.isInstance(message)).map((message) => message.content));
```

你检查工具结果带有安全错误码，而不是供应商原始报错、URL 或凭证。
`onError` 返回内容才转换错误；没有返回内容时，原始异常继续传播。
本版本 `toolErrorMiddleware` 不重试，并保留 LangGraph 控制流信号。
你自写 wrapper 时也不要用宽泛 `catch` 把 `interrupt` 转成“调用失败”。

### 3.4 重试必须有明确边界

| 失败类别 | 是否自动重试 | 原因或前提 |
|---|---|---|
| 可识别的短暂网络故障、限流 | 可选、有限次 | 遵守退避、总超时、供应商规则 |
| Schema 校验失败 | 不做网络盲重试 | 输入未修正，重试通常没有意义 |
| 401/403 或资源越权 | 不重试 | 权限问题不应消耗预算或尝试绕过 |
| 工具没有找到记录 | 通常不重试 | 正常业务结果，不是网络故障 |
| 退款、写入、发送通知 | 默认不重试 | 除非已有幂等键与事务/状态检查 |
| 用户取消、图中断 | 不重试 | 它们不是暂时性服务错误 |

若你选择 tool retry，错误转换 wrapper 放在外侧，retry 放在内侧。
具体顺序是 `toolErrorMiddleware` 在前，`toolRetryMiddleware({ onFailure: "error" })` 在后；仅为可选扩展。
不要同时启用 Agent retry、模型 SDK retry 和 HTTP client retry，再声称最多只调用两次。
本课程真实模型工厂只配置一层 SDK 有限重试；离线测试没有执行供应商网络重试。
模型步骤计数与实际 API 尝试不是同一指标，费用预算还需要 token 与重试统计。

## 4. 练习：从观察到策略

### 练习 A｜基础：预测顺序

交换 `outer` 与 `inner` 的注册顺序，先写预测，再运行片段。
给 wrapper 内加入一个固定异常，观察哪些 leave/after 事件出现。
**验收**：你能解释 `finally` 为什么会运行，以及 `afterModel` 为什么不是故障清理函数。

### 练习 B｜进阶：区分失败语义

在故障片段中，把 `TemporaryLookupError` 改为普通程序错误，再对比行为。
保持模型与工具上限，不添加自动重试。
**验收**：临时故障被转换；未列入处理范围的错误继续传播；日志不输出原始敏感信息。

### 练习 C｜挑战：设计重试与授权顺序

为“只读知识检索”设计授权、审计、重试、错误转换和超时的顺序说明。
再说明同一策略为什么不能直接用于“提交退款”。
**验收**：拒绝权限发生在行动前；重试仅包住可重试操作；写操作需要人工审批和幂等设计。

## 5. 自测与复盘

1. **`beforeModel` 和 `wrapModelCall` 都在调用前，为什么要分开？**
   简答：前者更新/检查图状态；后者包装当前模型请求和执行结果，生命周期不同。
2. **注册 `[A, B]`，哪个 wrapper 在外层？**
   简答：A 在外层，先进入、后返回；before 按正序，after 按逆序。
3. **错误被转换为 `ToolMessage`，代表查询成功吗？**
   简答：不代表。它保留了可供模型解释的失败结果，前端与审计仍要标记查询失败。
4. **run/model limit 能精确限制账单中的 API 请求数吗？**
   简答：不能。SDK 内部重试和 token 用量不等于图上的模型步骤计数。
5. **为什么不能在所有异常上套三次 retry？**
   简答：越权、参数错误、中断与副作用不是暂时故障，盲重试可能重复写入或绕过用户意图。

用一句话复盘：**中间件不是“统一 try/catch”，而是执行边界与失败语义的组合。**

## 6. 常见坑与可选扩展

| 坑 | 后果 | 更合适的做法 |
|---|---|---|
| 直接修改 `state.messages` | 破坏 reducer/检查点语义 | 返回更新，或仅修改当前 request 视图 |
| 多个 middleware 名称相同 | 编译或初始化失败 | 为每个实例使用唯一名称 |
| 在单例变量存用户身份 | 并发请求串租户 | 用认证后的 runtime context |
| 把原始错误字符串发给模型 | 泄漏地址、请求体或秘密 | 只返回安全类别与下一步建议 |
| 用 `afterAgent` 关闭连接 | 异常路径遗留资源 | 在拥有资源的作用域用 `try/finally` |

真实模型联调、生产限流存储和 LangSmith 追踪都是可选项，本章不声称已测试。
`npm run example:middleware -- --live` 可能产生费用；配置方法见[课程首页](../README.md)。
`--trace` 必须与 `--live` 同时使用，且只上传匿名练习输入。
跨实例总预算应由服务端共享存储/计费系统维护，不能用一个进程的数组或计数器代替。

## 7. AI Review 提示词与资料

> 请审查我的 Node.js Agent 中间件。按 before/wrap/after 顺序解释正常、异常、中断三条路径。检查状态更新、认证上下文、调用上限、SDK 重试和幂等边界。区分业务无结果与系统故障，指出哪个 catch 会吞掉控制流，哪个日志会泄漏信息。不要接入真实服务或添加无限重试。

- [原课程参考仓库](https://github.com/xiaoyu0513-cell/AI_Learning_Data/tree/main/ai_coding/LangChain_%E5%B0%9A%E7%A1%85%E8%B0%B7)：保留 Middleware 主题，按 JS 生命周期重新组织。
- [官方 JS Middleware](https://docs.langchain.com/oss/javascript/langchain/middleware)
- [官方 JS Built-in middleware](https://docs.langchain.com/oss/javascript/langchain/middleware/built-in)
- [官方 JS Custom middleware](https://docs.langchain.com/oss/javascript/langchain/middleware/custom)

[上一章：Agents](07-agents.md) · [下一章：上下文与记忆](09-context-memory.md)
