# 07｜Agents：让模型选择工具，让程序守住边界

[上一章：结构化输出](06-structured-output.md) · [课程首页](../README.md) · [下一章：Middleware](08-middleware.md)

> 完成本章后，你能运行一个只读订单 Agent，解释每一次工具调用的输入、输出和停止条件。
> 版本基线：`langchain@1.6.7`、`@langchain/core@1.5.0`、`@langchain/langgraph@1.7.5`；Node.js ≥ 22.12，ESM + strict TypeScript。
> 默认运行官方模型替身，不调用模型 API、不上传追踪。替身按脚本返回结果，不是具有推理能力的 LLM。

## 1. 目标与前置知识

你已经会写 Node handler，不必重新学习函数、异步或 JSON。
你需要把关注点从“函数怎么写”转到“谁决定调用函数，谁有权执行函数”。

- [ ] 区分固定工作流、模型工具调用与 Agent 循环。
- [ ] 用 `createAgent()` 连接模型、工具、系统提示词和中间件。
- [ ] 从消息历史中找到工具请求与对应结果。
- [ ] 用调用上限、白名单和只读实现限制行动范围。
- [ ] 知道哪些成功只能通过真实模型联调确认。

前置阅读：[工具与 Schema](05-tools.md)、[结构化输出](06-structured-output.md)。
如果依赖还没有准备好，先按[课程首页](../README.md)完成安装。
下方 TypeScript 片段可分别保存到 `examples/scratch-07.ts`，每次替换整个文件内容。
你在 `langchain/` 下用 `npm exec -- tsx examples/scratch-07.ts` 运行；相对导入保留 `.js`，由 `tsx` 解析对应 `.ts`。
片段中的 `configureRuntime([])` 强制离线，不接受真实调用参数。

## 2. 概念：Agent 不是另一个 HTTP 路由

### 2.1 谁控制下一步

| 方案 | 谁决定下一步 | 适用场景 | 你负责什么 |
|---|---|---|---|
| 固定 pipeline | 你的代码 | 分类、抽取、固定问答步骤 | 输入、输出、失败分支 |
| 单次 tool calling | 模型提出请求，你手动执行 | 学习工具协议、一次明确查询 | 校验参数、运行工具、回传结果 |
| Agent | 模型提出请求，运行时重复执行循环 | 多步只读查询、组合信息 | 权限、预算、终止、审计 |
| 显式 LangGraph | 你声明节点与路由 | 审批、恢复、确定性业务流程 | 状态、分支、检查点 |

一次 `bindTools()` 只是把工具定义交给模型，**不执行工具函数**。
`createAgent()` 则替你组织“调用模型 → 运行工具 → 再调用模型”的循环。
它内部建立 LangGraph 执行图，但没有替你建立业务授权系统。

### 2.2 一次循环的数据流

```text
HumanMessage：查询 demo-001 的配送状态
  → 模型收到 messages + 工具定义
  → AIMessage.tool_calls：name、args、id
  → 运行时选择白名单工具并校验 Schema
  → lookup_order 返回只读查询结果
  → ToolMessage：content + tool_call_id
  → 模型再次收到完整消息历史
  → 无工具请求的 AIMessage，结束本轮
```

模型生成的是工具调用请求，不是可以直接执行的 JavaScript。
`args` 是参数；`id` 是关联标识，不是订单号。
`ToolMessage.tool_call_id` 必须对应发起请求的那个调用。
一次模型响应可能提出多个调用，不能假设每轮只有一个工具。

### 2.3 不把“推理过程”当作业务证据

ReAct 描述的是交替决策与行动的模式，不承诺暴露模型的完整内部推理。
你审计工具名、经过校验的参数、执行状态、来源与耗时，而不是要求模型打印私有思维链。
模型说“已查询”不能证明工具运行过；消息中真实的工具结果才是可检查的数据。
即便工具运行成功，最终回复仍可能误读结果，你还要验证重要业务字段。

## 3. 源码与示例阅读：先看循环，再看回答

### 3.1 运行完整示例

```bash
npm run example:agent
```

入口是 [examples/07-agent.ts](../examples/07-agent.ts)。
你先确认运行模式为 offline，再检查消息类型、工具调用与最终状态。
不要把固定替身的最终文本当作模型查询能力的评测结果。

| 阅读位置 | 输入 | 输出 | 要回答的问题 |
|---|---|---|---|
| [src/runtime.ts](../src/runtime.ts) | CLI 参数 | 明确的执行模式 | 为什么有密钥也不会自动联调？ |
| [src/models.ts](../src/models.ts) | 模式与脚本 | 替身或 `ChatOpenAI` | 谁决定是否访问供应商？ |
| [src/tools.ts](../src/tools.ts) | `orderId` | 匿名订单 JSON | 参数和查询范围在哪里限制？ |
| `createAgent()` | model、tools、middleware | 可调用的 Agent | 谁组织下一轮？ |
| `agent.invoke()` | 新消息与运行配置 | 包含消息的最终状态 | 工具结果怎么进入历史？ |

包内阅读入口是 `node_modules/langchain/dist/agents/types.d.ts` 与 `agents/ReactAgent.js`。
优先核对类型声明的字段，不照搬旧注释中的 `llm`、`prompt` 或 Python 参数名。
本版本采用 `model`、`systemPrompt`；结构化响应使用 `responseFormat`，结果为 `structuredResponse`。

### 3.2 一个有上限的离线 Agent

这个片段复用只读 fixture 工具，模型第一次请求查询，第二次不再请求工具。
工具调用是预先编排的，不是替身理解问题后做出的选择。

```typescript
import { createAgent, FakeToolCallingModel, modelCallLimitMiddleware, toolCallLimitMiddleware } from "langchain";
import { lookupOrderTool } from "../src/tools.js";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
const agent = createAgent({
  model: new FakeToolCallingModel({ toolCalls: [
    [{ name: "lookup_order", args: { orderId: "demo-001" }, id: "call-demo-1" }],
    [],
  ] }),
  tools: [lookupOrderTool],
  systemPrompt: "你只查询匿名练习订单，不修改订单。",
  middleware: [
    modelCallLimitMiddleware({ runLimit: 3, exitBehavior: "error" }),
    toolCallLimitMiddleware({ runLimit: 1, exitBehavior: "error" }),
  ],
});
const state = await agent.invoke({
  messages: [{ role: "user", content: "查询 demo-001 的配送状态" }],
}, { recursionLimit: 12, signal: AbortSignal.timeout(5_000) });
console.log(state.messages.map((message) => message.getType()));
```

你应检查消息顺序包含 `human → ai → tool → ai`，而不是只检查最后一句话。
最后一条 `AIMessage` 没有待执行工具请求，运行时才有正常结束的依据。
这不是生产授权示例：`demo-001` 只是匿名数据，生产查询必须由鉴权后的既有服务封装执行。

### 3.3 三种上限解决不同问题

| 约束 | 限制对象 | 不保证什么 |
|---|---|---|
| `modelCallLimitMiddleware` | Agent 模型步骤数量 | 不等于全部 SDK 重试次数或精确 token 费用 |
| `toolCallLimitMiddleware` | 工具请求执行额度 | 不替代工具内的行级权限与幂等控制 |
| `recursionLimit` | 图执行 superstep 数量 | 不等于工具次数；中间件节点也消耗步骤 |
| `AbortSignal` 与下游超时 | 等待时长与取消信号 | 不能撤回已经发生的业务副作用 |

系统提示词是行为说明，不是权限边界。
你同时限制工具集合、Schema、资源范围、调用额度和每个下游服务的超时。
本版本工具限流的 `continue` 会把超额请求变成工具错误后继续交给模型判断。
教学示例用 `error`，避免误以为“被拦截一次”就必然结束循环。
不要在多个并行工具调用时依赖 `exitBehavior: "end"`；本版本该分支存在多调用限制。

### 3.4 观察流，不混用两套流 API

以下片段使用本版本实验性的 Agent v3 投影流。
它展示固定文本的流式输出，不测试供应商的 token 流或网络断线恢复。

```typescript
import { createAgent } from "langchain";
import { FakeListChatModel } from "@langchain/core/utils/testing";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
const agent = createAgent({
  model: new FakeListChatModel({ responses: ["这是固定的离线回答。"] }),
  tools: [],
});
const run = await agent.streamEvents(
  { messages: [{ role: "user", content: "展示流式回答" }] },
  { version: "v3" },
);
for await (const message of run.messages) {
  for await (const text of message.text) process.stdout.write(text);
}
const output = await run.output;
console.log("\n消息数量：", output.messages.length);
```

`run.messages`、`run.toolCalls` 和 `run.output` 是投影接口，不是 `streamMode` 的事件数组。
LangGraph 的 `graph.stream(..., { streamMode: "updates" })` 见[第 11 章](11-langgraph.md)。
普通 `AIMessage.content` 可能是内容块数组，不能在其他位置直接断言为字符串。
Vue/Nuxt 页面可以只展示文本与脱敏工具状态；SSE UI 适配属于可选扩展，不是本章前置条件。

## 4. 练习：按难度验证边界

### 练习 A｜基础：跟踪一轮调用

把工具脚本中的订单改为 `demo-002`，保持用户问题与脚本参数一致。
记录四条消息的类型，以及工具请求 `id` 和结果 `tool_call_id`。
**验收**：你能解释订单号与调用标识为什么不能混用，并找到只读查询的实际返回值。

### 练习 B｜进阶：让循环碰到预算

让替身连续两轮请求 `lookup_order`，保持工具 `runLimit: 1`。
再把第二轮改为无工具调用，对比两次行为。
**验收**：超额时程序以失败结束；正常分支结束；你没有把限流错误变成“查询成功”。
不要只提高 `recursionLimit` 来绕过预算。

### 练习 C｜挑战：设计一个可审计查询工具

设计 `lookup_order` 的生产边界说明，不接入真实订单库。
列出服务端认证主体、参数 Schema、资源授权、超时、脱敏日志和并发策略。
**验收**：用户输入不能决定租户；工具不提供任意 SQL、文件路径或 shell 命令；每项约束都有落点。

## 5. 自测与复盘

1. **`bindTools()` 和 `createAgent()` 的区别是什么？**
   简答：前者提供工具定义；后者组织模型与工具的循环，但不提供业务授权。
2. **为什么工具返回后还要再调用模型？**
   简答：模型需要读取结果、决定是否继续查询或生成最终回复；固定工作流也可选择直接返回工具结果。
3. **把“最多调用一次”写进提示词够吗？**
   简答：不够。提示词可被误解，程序必须独立执行调用上限与资源权限。
4. **为什么 `recursionLimit: 10` 不表示最多调用十次工具？**
   简答：它限制图 superstep，模型、工具与中间件节点的执行结构都影响步骤数。
5. **离线示例通过，证明真实模型可靠调用工具了吗？**
   简答：没有。它只证明运行时能处理给定的脚本调用；真实模型选择、Schema 支持与回答质量要单独联调。

用一句话复盘：**模型提出行动，运行时执行循环，你的业务代码决定权限与预算。**

## 6. 常见坑与可选联调

| 现象 | 常见原因 | 处理方式 |
|---|---|---|
| 有 API key 却仍返回固定结果 | 默认 offline | 这是安全设计，不是配置失效 |
| 替身调用与用户问题不一致 | 工具请求预先写在脚本中 | 同时修改脚本参数，不期待替身理解问题 |
| 工具成功但回答不准确 | 模型误读结果，或替身只是固定输出 | 验证来源与业务字段，不只看自然语言 |
| Agent 不断重复查询 | 没有停止请求或缺少额度约束 | 检查消息、工具上限与模型上限 |
| 把工具结果当成系统指令 | 外部结果包含提示注入 | 将其视为不可信数据，不增加权限 |

真实模型联调是可选项，本章不声称已执行供应商或追踪测试。
你先自行把 `env.example` 复制为 `.env`，再配置 `OPENAI_API_KEY` 与 `OPENAI_MODEL`。
`npm run example:agent -- --live` 才选择 `@langchain/openai` 的 `ChatOpenAI`，可能计费。
只有同时传入 `--live --trace` 才允许追踪上传；不要使用真实用户输入做联调。
更换 OpenAI-compatible 服务时，重新验证 tool calling 和 structured output 能力，不能只更换地址就假定兼容。

## 7. AI Review 提示词与资料

把你的匿名脚本、消息类型序列和预算设置交给 AI，并要求它审查而不是代替你执行。

> 你是 Node.js Agent 工程审阅者。请按输入、工具请求、工具执行、结果回传、停止条件检查我的示例。区分模型能力与固定替身行为，检查 Schema、调用关联 ID、资源授权、超时和限流。不要调用服务、建议无限循环或添加任意 shell/文件工具。指出缺失的可验证证据，并给出一个失败用例。

- [原课程参考仓库](https://github.com/xiaoyu0513-cell/AI_Learning_Data/tree/main/ai_coding/LangChain_%E5%B0%9A%E7%A1%85%E8%B0%B7)：保留 Agent 主题，改用当前 JS API。
- [官方 JS Agents](https://docs.langchain.com/oss/javascript/langchain/agents)
- [官方 JS Streaming](https://docs.langchain.com/oss/javascript/langchain/streaming)
- [官方 JS Middleware](https://docs.langchain.com/oss/javascript/langchain/middleware)

[上一章：结构化输出](06-structured-output.md) · [下一章：Middleware](08-middleware.md)
