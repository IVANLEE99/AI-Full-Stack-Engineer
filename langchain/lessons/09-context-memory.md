# 09｜上下文与记忆：让会话连续，但不让租户串线

[上一章：Middleware](08-middleware.md) · [课程首页](../README.md) · [下一章：RAG](10-rag.md)

> 完成本章后，你能让同一会话保留消息、让不同会话隔离，并解释跨会话偏好怎样被显式读取和注入。
> 版本基线：`langchain@1.6.7`、`@langchain/langgraph@1.7.5`；Node.js ≥ 22.12，ESM + strict TypeScript。
> `MemorySaver` 与层级 `InMemoryStore` 都只保存在当前进程。离线替身不理解历史，不代表真实记忆能力。

## 1. 目标与前置知识

你可能已经写过 Pinia store、session 或数据库用户偏好表。
LLM 应用同样需要区分“这次请求的上下文”“这段会话的状态”和“跨会话的记录”。

- [ ] 区分上下文工程、短期记忆与长期记忆。
- [ ] 使用 `MemorySaver` 与 `configurable.thread_id` 延续会话。
- [ ] 使用 namespace 和 key 组织跨会话偏好。
- [ ] 从服务端认证主体构造 thread 和 namespace 的权限边界。
- [ ] 说明进程重启、上下文裁剪、并发与删除的实际行为。

先读[Agents](07-agents.md)和[Middleware](08-middleware.md)。
安装与 Node 版本切换见[课程首页](../README.md)。
下方 TypeScript 片段分别保存到 `examples/scratch-09.ts`，每次替换整个文件内容。
你在 `langchain/` 下用 `npm exec -- tsx examples/scratch-09.ts` 运行，保留相对导入的 `.js`。
`configureRuntime([])` 强制离线，不加载读者的真实服务配置。

## 2. 概念：存过，不等于模型看过

### 2.1 三种不同的数据生命周期

| 数据 | 机制 | 范围 | Node.js 类比 |
|---|---|---|---|
| 本次认证主体与策略 | runtime context | 一次 invocation | 认证后构造的 request context |
| 会话消息与图状态 | checkpointer | 一个 thread | 服务端 session + 工作流状态快照 |
| 跨会话偏好或已确认事实 | Store | namespace + key | 带访问范围的 Repository |
| 本次模型能看到的内容 | 模型 request | 一个模型步骤 | 查询后组装的响应视图 |

上下文工程是选择、压缩、排列模型本次需要的信息。
短期记忆是 thread 内的状态连续性，通常包含消息历史。
长期记忆是跨 thread 的记录组织方式，不等于“永不丢失”。
如果你使用内存 Store，跨 thread 可读取，但进程退出仍然会丢失。
持久化、访问控制、模型注入是三件不同的事，没有任何一个组件自动包办全部。

### 2.2 会话状态的数据流

```text
认证后的 Node handler
  → 校验该用户能访问 thread
  → 本次 messages + configurable.thread_id
  → checkpointer 读取该 thread 的旧状态
  → reducer 合并新消息，执行 Agent
  → checkpointer 保存新状态
  → 返回当前输出
```

`thread_id` 放在 `configurable` 中，仍然使用这个下划线字段名。
`systemPrompt`、`responseFormat` 与 `structuredResponse` 则是 JS API 的驼峰字段。
不要为了统一命名而把 `thread_id` 改成 `threadId`。

### 2.3 长期记录的数据流

```text
用户明确确认一项可保存偏好
  → 服务端确认租户、用户、同意范围
  → 校验记录 Schema → store.put(namespace, key, value)
下一次会话
  → 按认证主体构造相同 namespace
  → store.get / scoped search → 校验旧数据
  → 只选择需要的字段注入本次模型请求
```

给 `createAgent()` 传入 `store` 只是提供存储能力。
工具或中间件还要显式读取 `runtime.store`，并决定哪些字段进入模型请求。
模型不能凭借“连接了数据库”就自动知道数据库内容。

## 3. 源码与示例阅读：先验证状态，不验证替身理解

### 3.1 运行入口

```bash
npm run example:memory
```

入口是 [examples/09-memory.ts](../examples/09-memory.ts)。
你观察同一个 thread 的消息累计，以及另一个 thread 的独立历史。
完整示例观察 namespace 命中与跨用户未命中；下方片段再加入租户维度。
这些检查证明键空间隔离与传递，不证明授权已实现或模型能准确回忆事实。

| 阅读对象 | 输入 | 输出 | 重点 |
|---|---|---|---|
| `MemorySaver` | thread 配置、图状态 | 检查点 | 是否复用同一个 saver 实例？ |
| `agent.invoke()` | 本轮新消息 | 合并后的状态 | 是否把旧消息重复发送了一遍？ |
| `InMemoryStore` | namespace、key | item 或 null | 访问范围来自谁？ |
| Store item | `value` | 待校验记录 | 旧数据是否仍满足当前 Schema？ |
| [src/runtime.ts](../src/runtime.ts) | 执行参数 | 离线/联调边界 | 是否意外上传会话历史？ |

层级 Store 从 `@langchain/langgraph` 导入。
`langchain` 根模块还导出一个 core KV 同名 `InMemoryStore`，不要把它与 namespace Store 混用。
本章阅读的是 `node_modules/@langchain/langgraph-checkpoint/dist/store/base.d.ts` 的 namespace API。

### 3.2 同一 thread 累计，新 thread 隔离

下方响应预先固定；即使替身说“已记录”，你仍要检查真正保存的消息。

```typescript
import { createAgent } from "langchain";
import { FakeListChatModel } from "@langchain/core/utils/testing";
import { MemorySaver } from "@langchain/langgraph";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
const agent = createAgent({
  model: new FakeListChatModel({ responses: ["固定回复一", "固定回复二", "固定回复三"] }),
  tools: [], checkpointer: new MemorySaver(),
});
const threadA = { configurable: { thread_id: "tenant-demo/user-demo/thread-a" } };
const first = await agent.invoke({
  messages: [{ role: "user", content: "请先使用 Node.js 类比" }],
}, threadA);
const second = await agent.invoke({
  messages: [{ role: "user", content: "继续解释" }],
}, threadA);
const other = await agent.invoke({
  messages: [{ role: "user", content: "开始新会话" }],
}, { configurable: { thread_id: "tenant-demo/user-demo/thread-b" } });
console.log([first.messages.length, second.messages.length, other.messages.length]);
```

你应检查这三个长度为 `2、4、2`：两条 user 消息分别各产生一条 AI 消息。
第二轮只传新消息；运行时从检查点恢复旧消息，不需要你手动再拼一次全部历史。
如果每个 HTTP 请求都创建新的 `MemorySaver`，下一次请求就找不到上一次的内存状态。

### 3.3 namespace 不是授权系统

这个片段只保存匿名练习偏好，不记录姓名、邮箱、电话或真实行为画像。
你先验证记录，再按精确 namespace 读取，最后选择适合注入的指令。

```typescript
import { InMemoryStore } from "@langchain/langgraph";
import { z } from "zod";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
const Preference = z.object({
  language: z.literal("zh-CN"), examples: z.literal("nodejs"),
}).strict();
const store = new InMemoryStore();
const namespace = ["tenant-demo", "user-demo", "preferences"];
await store.put(namespace, "style", Preference.parse({ language: "zh-CN", examples: "nodejs" }));
const item = await store.get(namespace, "style");
const preference = item ? Preference.parse(item.value) : null;
const instruction = preference?.examples === "nodejs" ? "你先使用 Node.js 类比。" : "你使用通用说明。";
const otherTenant = await store.get(["tenant-other", "user-demo", "preferences"], "style");
console.log({ instruction, otherTenant });
```

`otherTenant` 应为 `null`，因为相同 key 位于不同 namespace。
这只证明键空间不同，不证明调用方有权读取第一个 namespace。
生产代码必须拒绝调用方提交任意租户 ID；namespace 由认证后的服务端构造。
`store.search()` 使用 namespace **前缀**，范围比精确 `get()` 更宽。
不要在用户请求路径中使用空前缀或只含通用类别的前缀搜索所有人的记录。

### 3.4 边界设计：thread 和 namespace 一起检查

| 边界 | 安全做法 | 常见错误 |
|---|---|---|
| thread 所有权 | 服务端验证 thread → tenant/user 的映射 | 接受浏览器传来的任何 `thread_id` |
| 长期记录范围 | namespace 包含认证后的 tenant/user | 使用 `['preferences']` 保存全部用户偏好 |
| context | handler 校验后注入 | 根据用户自称角色赋权 |
| 检索过滤 | 在检索前或检索执行时限制授权范围 | 检索所有租户，再只过滤最终回答 |
| 日志与追踪 | 匿名化、最小化、显式开启 | 自动上传完整会话与 Store item |

把租户 ID 写进字符串是一种组织约定，不是密码学隔离或授权检查。
即便你共享 checkpointer，thread 命名唯一也不能代替资源权限验证。
多个用户的服务实例可以共享持久化组件，但每次读取都必须限定正确主体。

## 4. 概念深化：上下文变长之后怎么办

### 4.1 模型视图不等于完整检查点

| 策略 | 作用 | 代价与验证点 |
|---|---|---|
| 裁剪近期消息 | 控制本轮上下文大小 | 可能丢失早期约束；保留工具请求/结果配对 |
| 总结历史 | 把较长历史压缩为摘要 | 摘要会丢信息，也可能误总结，需要评估 |
| 按需读取长期记录 | 只注入相关偏好/事实 | 需要 Schema、范围、过期策略 |
| 检索知识文档 | 补充可引用事实 | 这是 RAG，不是聊天历史无限堆叠 |

你可以仅修改模型 request 的消息视图，同时保留完整检查点用于恢复。
如果你真的删除状态中的消息，需要使用对应 reducer 支持的更新方式，不直接改数组。
不能只删除 `ToolMessage` 而保留引用它的工具请求，反之也不能。
摘要模型调用可能计费；本章没有运行真实总结服务。

### 4.2 并发、重启和删除

同一 thread 的并发写入不应依靠“JavaScript 是单线程”来解决。
异步请求仍然交错，部署多个进程后更没有单进程顺序保证。
你在应用层按 thread 串行化，或选择明确支持所需并发语义的部署方式。

重启会清空 `MemorySaver` 和 `InMemoryStore`，离线示例没有生产持久化承诺。
用户要求删除记忆时，你分别处理长期记录、会话检查点、缓存与可选追踪系统。
`store.delete(namespace, key)` 不会顺带删除全部聊天检查点。
清空前端 Pinia/localStorage 也不会删除服务端保存的历史。

### 4.3 PostgreSQL 是可选扩展

你完成内存练习后，再考虑数据库连接、迁移、备份和保留周期。
本项目没有把 PostgreSQL 作为前置依赖，也没有在本章验证数据库连接。
如果你扩展，短期检查点使用 `PostgresSaver`，长期记录使用 `PostgresStore`，不是同一个类。
前者导入入口是 `@langchain/langgraph-checkpoint-postgres`，后者是 `@langchain/langgraph-checkpoint-postgres/store`。
这个可选包不在本章基础安装中；按目标版本官方说明安装和初始化，不照抄 Python import。
数据库“保存了”仍不代表“权限正确”“已注入模型”或“摘要事实正确”。

## 5. 练习：连续性、范围与可删除性

### 练习 A｜基础：验证历史累计

增加第三轮新消息，再换一个全新 thread。
记录每轮消息数量，不以固定回复中的“记得”字样作为证据。
**验收**：同一 thread 累计，新 thread 独立；你没有重复发送旧消息。

### 练习 B｜进阶：阻止跨租户偏好读取

给两个匿名租户保存相同 user/key、不同偏好。
设计服务端函数，只接受认证主体和业务 key，不接受调用方自选 namespace。
**验收**：读取和 search 都限定主体；拒绝宽前缀；缺失记录返回默认偏好。

### 练习 C｜挑战：写出记忆生命周期

为“保存偏好 → 使用 → 过期 → 用户删除”写四阶段说明。
标出短期状态、长期记录、摘要和追踪分别在哪一步更新或删除。
**验收**：有用户同意、最小化字段、保留期和跨系统删除范围；不声称内存组件提供持久化。

## 6. 自测与复盘

1. **长期记忆是不是一定写在数据库里？**
   简答：不是。长期指跨 thread 的记录范围，是否持久化取决于 Store 实现。
2. **为什么第二轮只发送新消息？**
   简答：同一 thread 的检查点恢复旧状态，重复提交历史可能导致消息重复或错误更新。
3. **把 user ID 放进 namespace 就完成授权了吗？**
   简答：没有。服务端仍要验证主体，且不能允许用户选择他人的 namespace。
4. **传入 `store` 后，模型会自动知道偏好吗？**
   简答：不会。你需要显式读取、验证并选择字段注入本次模型请求。
5. **删除一个 Store key 能抹掉全部会话数据吗？**
   简答：不能。长期记录、检查点、缓存和追踪是独立的数据保存范围。

用一句话复盘：**检查点延续会话，Store 组织跨会话记录，上下文工程决定本次模型看到什么。**

## 7. 常见坑、AI Review 与资料

| 现象 | 原因 | 处理 |
|---|---|---|
| 每轮都像新会话 | saver 实例或 thread 不一致 | 复用组件并验证 thread 映射 |
| 消息重复增加 | 反复发送全部历史 | 只提交本轮新消息 |
| 重启后偏好消失 | 使用内存 Store | 明确演示边界，再做持久化扩展 |
| A 用户看到 B 的记录 | 宽 namespace 或未经授权的 thread | 服务端范围校验，修复检索入口 |
| 偏好已保存却不影响回答 | 没有显式读取/注入，或替身不理解输入 | 检查 request 视图，不把固定回答当评测 |

> 请审查我的 Node.js 记忆方案。按认证主体、thread、namespace、模型请求四层检查数据流。区分内存与持久化、状态与 context，找出跨租户 search、重复历史、并发写入和删除范围的问题。不要读取真实用户数据或连接数据库；要求我提供匿名隔离测试与重启后的预期行为。

- [原课程参考仓库](https://github.com/xiaoyu0513-cell/AI_Learning_Data/tree/main/ai_coding/LangChain_%E5%B0%9A%E7%A1%85%E8%B0%B7)：保留上下文与记忆主题。
- [官方 JS Short-term memory](https://docs.langchain.com/oss/javascript/langchain/short-term-memory)
- [官方 JS Long-term memory](https://docs.langchain.com/oss/javascript/langchain/long-term-memory)
- [官方 JS Context engineering](https://docs.langchain.com/oss/javascript/langchain/context-engineering)
- [官方 JS LangGraph Persistence](https://docs.langchain.com/oss/javascript/langgraph/persistence)

[上一章：Middleware](08-middleware.md) · [下一章：RAG](10-rag.md)
