# 11｜LangGraph：用显式状态图控制分支、暂停与恢复

[上一章：RAG](10-rag.md) · [课程首页](../README.md) · [下一章：MCP 与 Skills](12-mcp-skills.md)

> 完成本章后，你能运行一个确定性状态图，把需要人工审核的分支暂停，再用同一 thread 恢复。
> 版本基线：`@langchain/langgraph@1.7.5`、`@langchain/core@1.5.0`；Node.js ≥ 22.12，ESM + strict TypeScript。
> 本章不调用模型，不执行真实退款，不需要 API key；检查点只保存在当前进程。

## 1. 目标与前置知识

本章是从原课程 Agent/RAG 主题到可控工作流的桥接，不要求你先学习图算法。
你已经会用条件分支和状态机；LangGraph 把它们变为可以暂停、恢复和观察的执行结构。

- [ ] 用 `StateSchema` 定义状态和节点更新类型。
- [ ] 用 `StateGraph` 注册节点、边与条件路由。
- [ ] 理解节点返回部分更新，而不是直接修改共享对象。
- [ ] 用 `interrupt()`、检查点和 `Command({ resume })` 形成恢复流程。
- [ ] 识别节点重跑、权限、幂等与流事件的边界。

先读[Agents](07-agents.md)与[短期记忆](09-context-memory.md)，安装见[课程首页](../README.md)。
下方 TypeScript 片段分别保存到 `examples/scratch-11.ts`，每次替换文件内容。
你在 `langchain/` 下运行 `npm exec -- tsx examples/scratch-11.ts`；相对导入保留 `.js`。
`configureRuntime([])` 关闭隐式追踪；图运行不因环境中存在密钥而改用真实服务。

## 2. 概念：模型决策之外，还有程序控制流

### 2.1 什么时候需要显式图

| 场景 | 更合适的入口 | 原因 |
|---|---|---|
| 一次固定抽取 | prompt pipeline | 步骤简单，没有恢复需求 |
| 让模型组合只读工具 | `createAgent()` | 使用预构建模型/工具循环 |
| 条件路由、人工审批、暂停恢复 | `StateGraph` | 你显式声明允许的步骤与状态 |
| 数据库事务与真实退款 | 既有业务服务 | 图不是事务引擎或业务授权替代品 |

`createAgent()` 使用 LangGraph，但你不必为所有 Agent 都重写底层图。
当“下一步必须由业务规则决定”，而不是“交给模型自行尝试”时，再向下使用图 API。
模型可以参与某个节点，但不必参与每个节点；本章全部用固定规则。

### 2.2 一个节点的契约

```text
旧状态 State
  → 节点读取所需字段
  → 节点返回 Partial Update
  → channel / reducer 合并更新
  → 路由读取更新后的状态
  → 下一个节点或 END
```

默认标量字段通常采用最后值更新语义。
需要合并数组、计数或并行结果时，你声明合适的 reducer，例如 `ReducedValue`。
多个并行节点同时更新没有合并规则的同一标量，可能产生冲突；不是自动“最后一个异步结果获胜”。
消息历史需要消息专用合并语义，不能把普通数组拼接等同于消息 ID 更新与删除。

### 2.3 五个常用对象

| 对象 | 职责 | 不承担的职责 |
|---|---|---|
| `StateSchema` | 状态字段、默认值和更新类型 | 认证与资源授权 |
| `StateGraph` | 节点、边、路由与编译 | 外部数据库事务 |
| `START` / `END` | 入口和结束标识 | 任意业务动作 |
| checkpointer | 保存 thread 的执行状态 | 完全保证外部操作只执行一次 |
| `interrupt` / `Command` | 请求外部输入并恢复 | 判断审批人是否真的有权批准 |

## 3. 源码与示例阅读：先运行确定性路由

### 3.1 运行完整入口

```bash
npm run example:graph
```

入口是 [examples/11-langgraph.ts](../examples/11-langgraph.ts)，图定义在 [src/graph.ts](../src/graph.ts)。
你观察普通咨询的节点增量，再观察退款演示分支暂停与恢复。
`resumeApproval(true)` 在示例中只是测试输入，不代表真实人工同意，更不会执行退款。

| 阅读对象 | 输入 | 输出 | 要检查的问题 |
|---|---|---|---|
| `ReviewState` | question 与默认字段 | 已声明的状态形状 | 哪些字段由输入提供？ |
| `classify` | question | topic 更新 | 路由规则是否确定？ |
| `reply` | 普通咨询状态 | 固定 answer | 是否调用了模型？ |
| `review` | 待审核状态 | 中断或审核后的 answer | 哪一行会在恢复时重跑？ |
| `compile()` | 完整图、checkpointer | 可调用图 | 是否复用检查点与 thread？ |

包内阅读入口是 `node_modules/@langchain/langgraph/dist/state/schema.d.ts` 与 `graph/state.d.ts`。
当前章节使用 `StateSchema`；旧材料中的 `Annotation.Root` 不需要强行逐行移植。
`typeof schema.State`、`typeof schema.Update` 是 TypeScript 类型入口，不是当前运行状态的取值方式。

### 3.2 状态、节点和路由放在一起看

这个片段只演示路由，人工分支返回提示，不做暂停或任何写操作。

```typescript
import { StateSchema, StateGraph, START, END } from "@langchain/langgraph";
import { z } from "zod";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
const State = new StateSchema({
  question: z.string().min(1),
  topic: z.enum(["general", "refund"]).default("general"),
  answer: z.string().default(""),
});
const graph = new StateGraph(State)
  .addNode("classify", (state) => ({
    topic: state.question.includes("退款") ? "refund" as const : "general" as const,
  }))
  .addNode("reply", () => ({ answer: "普通咨询的固定回复。" }))
  .addNode("review", () => ({ answer: "该分支需要人工审核，仅演示路由。" }))
  .addEdge(START, "classify")
  .addConditionalEdges("classify", (state) => state.topic === "refund" ? "review" : "reply", ["review", "reply"])
  .addEdge("reply", END).addEdge("review", END)
  .compile();
console.log(await graph.invoke({ question: "配送咨询" }));
console.log(await graph.invoke({ question: "申请退款" }));
```

`classify` 只返回 topic，旧 question 仍保留。
条件边返回已声明的节点名，不执行那些节点中的业务逻辑。
两次 `invoke()` 使用这个无检查点图分别执行，不会形成共享会话历史。
你新增分支时，同时定义状态、目标节点、可达边和终止条件，不只修改一个提示词。

## 4. 示例阅读：暂停不是在内存里等待 Promise

### 4.1 interrupt 的执行语义

`interrupt(payload)` 首次执行时向图运行时抛出控制流信号。
图保存检查点，并把等待外部输入的状态暴露给调用方。
它不是等待浏览器回复的长时间 Promise，也不是普通服务错误。
当你用 `Command({ resume: value })` 恢复时，节点重新执行到中断位置，那里返回 resume 值。

```text
invoke(input, thread T)
  → review 节点 → interrupt(payload)
  → 保存检查点，当前调用返回暂停信息
稍后：认证后的审批入口
  → 校验主体、thread 与待审批事项
  → invoke(Command({ resume: decision }), 同一 thread T)
  → review 节点重跑 → interrupt 返回 decision
  → 节点产生更新 → END
```

### 4.2 同一 thread 恢复

下面只有一个中断，不涉及并行审批或真实退款。
恢复值先通过 Zod 校验，避免把任意输入当成批准决定。

```typescript
import { StateSchema, StateGraph, MemorySaver, interrupt, Command, START, END } from "@langchain/langgraph";
import { z } from "zod";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
const State = new StateSchema({ request: z.string(), result: z.string().default("") });
const Decision = z.object({ approved: z.boolean() }).strict();
const graph = new StateGraph(State)
  .addNode("review", (state) => {
    const decision = Decision.parse(interrupt({ kind: "refund-review", request: state.request }));
    return { result: decision.approved ? "演示已批准，未执行真实退款。" : "演示已拒绝。" };
  })
  .addEdge(START, "review").addEdge("review", END)
  .compile({ checkpointer: new MemorySaver() });
const config = { configurable: { thread_id: "tenant-demo/user-demo/review-1" } };
await graph.invoke({ request: "匿名退款审批演示" }, config);
const snapshot = await graph.getState(config);
console.log(snapshot.tasks.flatMap((task) => task.interrupts));
const completed = await graph.invoke(new Command({ resume: { approved: true } }), config);
console.log(completed.result);
```

你先检查中断 payload，再检查同一 thread 的完成结果。
不要把第二次调用改成普通 user 消息；那是新输入，不是这个等待点的 resume 值。
换一个 thread 会失去所需检查点关联，不能借此恢复原审批。
恢复需要同一份可访问的检查点；使用内存 saver 时，进程退出后无法继续。

### 4.3 最重要的坑：中断前代码会重跑

如果你在 `interrupt()` 前发送通知、写数据库或调用退款服务，恢复时可能重复执行。
你把副作用放在明确的业务步骤中，并使用既有幂等键、状态检查与事务策略。
不能因为“图有检查点”就声称外部动作 exactly-once。
本章只返回演示文本，因此不会产生重复退款问题。

不要用宽泛 `try/catch` 把 interrupt 转换为普通错误结果。
如果必须捕获，保留并重新抛出图控制流信号；不要自动 retry 审批中断。
多个中断的恢复还涉及顺序/中断标识，属于进阶扩展；不能盲目用一个布尔值恢复所有待审批任务。
生产恢复入口单独认证审批人，验证 thread 所有权与待审批事项；模型不得自行批准自身行动。

## 5. 观察执行，不把节点增量当成 token

| API/模式 | 输出视角 | 本章是否包含 |
|---|---|---|
| `graph.invoke()` | 本次最终状态或暂停状态 | 是 |
| `graph.stream(..., { streamMode: "updates" })` | 每个节点产生的状态增量 | 完整示例使用 |
| `graph.stream(..., { streamMode: "values" })` | 每一步的完整状态视图 | 可选观察方式 |
| `graph.stream(..., { streamMode: "messages" })` | 模型消息块与 metadata | 本图无模型，不产生真实 token 流 |
| `agent.streamEvents(..., { version: "v3" })` | Agent 的实验性投影流 | 见第 07 章，不是同一接口 |

`updates` 可以支持前端展示“已分类、等待人工、已完成”，不意味着每条事件都应发给浏览器。
你在服务端删除内部状态、隐私字段和不应暴露的工具信息后，再做可选 SSE UI 适配。
这里的浏览器 SSE 与 MCP 的传输选择是两个不同问题。
部署服务、队列、PostgreSQL 检查点与 UI 都是可选扩展，不是运行本章的前置条件。

## 6. 练习：可达、可恢复、不可越权

### 练习 A｜基础：新增一个确定性分支

给路由片段新增“保修”分支，返回固定提示。
先列出每个节点的输入/更新，再修改图。
**验收**：三类输入都到达预期节点并终止，不出现未知目标节点或无终止循环。

### 练习 B｜进阶：拒绝与错误恢复值

把审批片段的 `approved` 改成 false，再用不满足 Schema 的值测试失败路径。
每次独立实验使用新的 thread，避免把已完成状态误当作等待状态。
**验收**：拒绝与批准输出不同，错误输入不被当作批准，没有执行真实业务动作。

### 练习 C｜挑战：设计恢复入口

设计一个 Node.js 审批 API 的契约说明，不接入真实服务。
包含认证主体、thread 所有权、待审批事项、恢复 Schema、幂等键和审计记录。
**验收**：用户不能恢复他人的 thread；模型不能自批；恢复重跑不会重复触发业务副作用。

## 7. 自测与复盘

1. **节点为什么返回部分更新而不是完整新状态？**
   简答：运行时按 channel/reducer 合并更新，未更新字段保持原值，避免节点互相覆盖状态。
2. **`StateSchema` 是否代替业务权限校验？**
   简答：不代替。它约束数据形状，授权由认证后的服务端实施。
3. **`interrupt()` 是普通异常吗？**
   简答：不是。它是可恢复控制流信号，吞掉它会破坏暂停语义。
4. **恢复为何要使用同一 thread？**
   简答：thread 关联暂停检查点和等待位置，新的 thread 不包含原执行状态。
5. **有检查点就能保证退款只执行一次吗？**
   简答：不能。节点可能重跑，外部副作用仍需要幂等和业务事务设计。

用一句话复盘：**图决定允许的步骤，检查点保存执行位置，恢复入口决定谁能继续行动。**

## 8. 常见坑、AI Review 与资料

| 现象 | 检查方向 |
|---|---|
| 图不能编译或目标不存在 | 是否注册节点、声明路由目标并连接可达边 |
| 恢复时没有待处理事项 | thread、saver 实例、进程生命周期是否一致 |
| 审批“自动成功” | 是否吞掉中断或把模型输出当成人工批准 |
| 恢复后重复写入 | interrupt 前是否有副作用，幂等策略是否缺失 |
| `recursionLimit` 很快耗尽 | 节点/中间件步骤与工具调用次数被混淆 |

> 请审查我的 LangGraph 状态图。逐节点列出输入、部分更新、路由和结束条件，再解释首次执行、中断、同 thread 恢复、错误 resume 四种路径。标出会重跑的代码和可能重复的副作用。检查审批主体与 thread 权限，不把脚本中的 approved=true 当作真实人的同意。不要调用真实业务服务。

- [原课程参考仓库](https://github.com/xiaoyu0513-cell/AI_Learning_Data/tree/main/ai_coding/LangChain_%E5%B0%9A%E7%A1%85%E8%B0%B7)：本章补充原主题之间的显式控制流桥接。
- [官方 JS LangGraph Overview](https://docs.langchain.com/oss/javascript/langgraph/overview)
- [官方 JS Graph API](https://docs.langchain.com/oss/javascript/langgraph/graph-api)
- [官方 JS Interrupts](https://docs.langchain.com/oss/javascript/langgraph/interrupts)
- [官方 JS Persistence](https://docs.langchain.com/oss/javascript/langgraph/persistence)

[上一章：RAG](10-rag.md) · [下一章：MCP 与 Skills](12-mcp-skills.md)
