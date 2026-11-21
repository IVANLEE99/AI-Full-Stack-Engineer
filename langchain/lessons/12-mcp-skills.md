# 12｜MCP 与 Skills：连接能力，不混淆协议、指令和权限

[上一章：LangGraph](11-langgraph.md) · [课程首页](../README.md) · [下一步：综合知识助手](../examples/13-knowledge-assistant.ts)

> 完成本章后，你能启动本地 MCP fixture、发现并限制只读工具、正确关闭连接，并区分 MCP、Skills 与 Deep Agents。
> 版本基线：`@langchain/mcp-adapters@2.0.3`、`langchain@1.6.7`；fixture server 使用 `@modelcontextprotocol/sdk@1.32.2`。
> 默认本地 stdio，无 API key、无远程连接、无自动追踪；模型工具请求来自官方脚本替身，不是真实推理。

## 1. 目标与前置知识

你熟悉 Node.js 子进程和 HTTP API，本章主要增加协议层与能力治理。
不要把“能发现一个工具”误认为“这个工具已经获得执行授权”。

- [ ] 区分 MCP host、client/adapter 与 server。
- [ ] 使用 MCPAdapter 2.x 的 `servers`、`listTools()` 与 `close()`。
- [ ] 理解 stdio 和 Streamable HTTP 的资源生命周期。
- [ ] 检查工具白名单、Schema、结果大小、超时与清理路径。
- [ ] 解释 Skills 的渐进加载与 Deep Agents 的更高层组合。

先读[工具](05-tools.md)、[Agents](07-agents.md)、[RAG](10-rag.md)和[LangGraph](11-langgraph.md)。
依赖与 Node.js ≥ 22.12 的环境准备见[课程首页](../README.md)。
下方 TypeScript 片段分别保存到 `examples/scratch-12.ts`，每次替换整个文件内容。
你在 `langchain/` 下运行 `npm exec -- tsx examples/scratch-12.ts`；保留相对导入 `.js`。
`configureRuntime([])` 强制离线；启动的只是仓库内已审阅的 fixture，不下载外部 server。

## 2. 概念：MCP 负责连接，不负责替你决定权限

### 2.1 三个角色

| 角色 | 本章对应对象 | 职责 |
|---|---|---|
| Host | 你的 Node.js 应用 | 管理模型、用户上下文、工具范围与交互 |
| Client / Adapter | `MCPAdapter` 管理的连接 | 协商协议、发现能力、把 MCP 工具适配到 LangChain |
| Server | 本地知识库 fixture | 提供已定义的工具、资源或提示模板 |

模型没有直接连接任意 server 的权限。
你决定 server 配置，adapter 发现能力，应用再决定哪些工具进入 Agent。
一个 host 可以连接多个 server，但同名工具必须区分，不能悄悄覆盖。

### 2.2 能力不止 tools

| MCP 能力 | 内容 | 在应用中的用途 |
|---|---|---|
| Tools | 带参数 Schema 的可调用操作 | 只读搜索、受控业务操作 |
| Resources | 有标识的可读取内容 | 受控资料、结构化资源 |
| Prompts | 可复用提示模板 | 为用户交互提供模板 |

`listTools()` 不会自动把所有 resources 读进上下文，也不自动加载全部 prompts。
适配后的工具仍需业务授权、参数验证和结果处理。
工具 description、annotations 与外部文档都是需要审阅的输入，不是授权令牌。

### 2.3 一次 MCP 工具调用的数据流

```text
受控 server 配置 → adapter 按需连接 → tools/list
  → 应用选择只读工具 → createAgent 的 tools
  → 模型提出 tool_call → Schema 校验与权限检查
  → adapter 发送 MCP tools/call → server 执行
  → MCP content blocks → LangChain 工具结果/ToolMessage
  → 模型读取结果 → 回答 → finally 关闭连接
```

MCP 返回可以包含文本、图片、资源链接或其他内容块，不保证都是一个字符串。
本 fixture 返回匿名资料的文本 JSON；不要把这个局部行为推广到所有 MCP server。
提示注入仍可能出现在工具描述和结果里，协议不会自动消除它。

## 3. 源码与示例阅读：先运行本地 stdio

### 3.1 运行入口

```bash
npm run example:mcp
```

入口是 [examples/12-mcp.ts](../examples/12-mcp.ts)，连接工厂是 [src/mcp.ts](../src/mcp.ts)。
server 定义在 [examples/mcp-server.ts](../examples/mcp-server.ts)。
你检查发现的工具名、实际工具结果，以及进程是否正常结束。
本例的 server 工具名是 `search`，适配后预期名称为 `knowledge_search`。
应用只接受这个已知只读契约，不自动执行其他发现的工具。

| 阅读位置 | 输入 | 输出 | 要检查什么 |
|---|---|---|---|
| server `registerTool()` | query Schema 与实现 | MCP 工具能力 | 是否只读，是否接受任意路径？ |
| `buildMcpAdapter(false)` | 本地受控配置 | adapter | 子进程命令是否固定？ |
| `listTools()` | server 名称范围 | LangChain 工具数组 | 名称、Schema、description 是否符合预期？ |
| `createAgent()` | 经过筛选的工具 | 工具循环 | 是否把所有远程能力直接暴露给模型？ |
| `finally` | 持有的 adapter | 关闭连接 | 发现失败/调用失败时也执行吗？ |

### 3.2 当前 API 与历史写法

| 本版本推荐 | 历史兼容项 | 本章选择 |
|---|---|---|
| `MCPAdapter` | `MultiServerMCPClient` 别名 | 使用新名称 |
| `{ servers: { name: connection } }` | 顶层旧连接映射 | 使用 `servers` |
| `listTools()` | `getTools()` | 使用 `listTools` |
| `listToolsets()` | `initializeConnections()` | 按需要发现分组能力 |
| `transport: "http"` | 旧材料中的 SSE 主线 | 使用 Streamable HTTP，SSE 仅作历史说明 |

不要照抄旧博客里的构造方式后再安装新版本包。
已安装的 `node_modules/@langchain/mcp-adapters/dist/client.d.ts` 与 `types.d.ts` 是本章签名依据。
构造 adapter 不立即打开连接；`listTools()`/`listToolsets()` 按需连接并发现能力。

### 3.3 一个有完整清理路径的本地连接

本 fixture 使用 SDK 1.x 的协议接口，因此显式配置 `mode: "legacy"`。
这里的 legacy 指 SDK 协议接口兼容，不表示 stdio 过时，也不表示使用旧 SSE 传输。

```typescript
import { fileURLToPath } from "node:url";
import { MCPAdapter } from "@langchain/mcp-adapters";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
const adapter = new MCPAdapter({
  prefixToolNameWithServerName: true,
  servers: { knowledge: {
    transport: "stdio", mode: "legacy",
    command: process.execPath,
    args: ["--import", "tsx", fileURLToPath(new URL("./mcp-server.ts", import.meta.url))],
    stderr: "pipe", defaultToolTimeout: 5_000,
    restart: { enabled: false },
    env: { LANGSMITH_TRACING: "false", LANGCHAIN_TRACING_V2: "false" },
  } },
});
try {
  const tools = await adapter.listTools("knowledge");
  const search = tools.find((tool) => tool.name === "knowledge_search");
  if (!search) throw new Error("缺少预期的只读工具，不执行其他能力。");
  console.log(await search.invoke({ query: "退货" }));
} catch {
  console.error("MCP_TOOL_FAILED：本地知识库调用失败，未展示原始异常。");
  process.exitCode = 1;
} finally {
  await adapter.close();
}
```

`process.execPath` 复用当前 Node 可执行文件，避免子进程意外退回系统默认旧版本。
命令和 server 路径由代码固定，不从用户 query 生成，不用 `npx` 临时下载未知 server。
如果工具发现或调用抛出错误，`finally` 仍负责关闭连接。
`defaultToolTimeout` 是工具等待边界，不等于全部启动、发现和业务动作的超时保证。
进程内的取消、远程取消和已经发生的副作用也不是同一件事。

### 3.4 stdout 与 stderr 不能混用

stdio server 的 stdout 专用于 MCP 协议帧。
你在 server 启动时用 `console.log()` 打印“服务启动成功”，就可能破坏协议解析。
调试信息走 stderr，而且仍需脱敏，不输出 token、真实资料或完整环境变量。
客户端脚本的课程输出与 server 协议 stdout 不在同一职责边界上。
只检查子进程存活不够：工具发现成功、调用成功和关闭成功是三个独立检查点。

## 4. 源码与示例阅读：让 Agent 只看到已批准的工具

### 4.1 发现结果不是直接授权列表

下面复用连接工厂，给 Agent 只开放预期的 `knowledge_search`。
替身工具请求预先固定，额度约束则由程序执行。

```typescript
import { createAgent, FakeToolCallingModel, modelCallLimitMiddleware, toolCallLimitMiddleware } from "langchain";
import { ToolMessage } from "@langchain/core/messages";
import { buildMcpAdapter } from "../src/mcp.js";
import { mcpErrorBoundaryMiddleware } from "../src/mcp-safety.js";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
const adapter = buildMcpAdapter(false);
try {
  const tools = await adapter.listTools("knowledge");
  const search = tools.find((tool) => tool.name === "knowledge_search");
  if (!search) throw new Error("缺少预期的只读知识检索工具。");
  const agent = createAgent({
    model: new FakeToolCallingModel({ toolCalls: [
      [{ name: search.name, args: { query: "退货" }, id: "call-mcp-1" }], [],
    ] }),
    tools: [search], systemPrompt: "你只查询匿名资料，不执行写操作。",
    middleware: [
      mcpErrorBoundaryMiddleware(),
      modelCallLimitMiddleware({ runLimit: 3, exitBehavior: "error" }),
      toolCallLimitMiddleware({ runLimit: 1, exitBehavior: "error" }),
    ],
  });
  const state = await agent.invoke({ messages: [{ role: "user", content: "查询退货资料" }] }, {
    recursionLimit: 12, signal: AbortSignal.timeout(15_000),
  });
  console.log(state.messages.filter((message) => ToolMessage.isInstance(message)).map((message) => message.content));
} finally {
  await adapter.close();
}
```

名称白名单能减少暴露面，但名称本身不证明远程实现可信。
生产应用还要审阅 server 来源、工具 Schema、权限、执行行为与结果大小。
`readOnlyHint`、`destructiveHint`、`idempotentHint` 等 annotations 是提示，不是强制安全机制。
不要因为 server 自称“只读”就允许任意数据库、shell、文件系统或网络访问。

### 4.2 错误必须在工具执行边界脱敏

Agent 可以把工具异常转换为 `status: "error"` 的 ToolMessage 并继续执行。
因此，最外层 `runExample()` 的 catch 不一定会收到原始异常；仅隐藏终端报错也不能阻止原文进入下一次模型输入。

本例使用 [mcpErrorBoundaryMiddleware](../src/mcp-safety.ts)：

- 抛出的普通工具异常转换为固定的 `MCP_TOOL_FAILED` 结果。
- 适配器直接返回的 error ToolMessage 同样替换正文，不保留 artifact 或原始服务响应。
- 保留工具调用 ID；LangGraph 中断、Command 与取消继续保持控制流语义，不伪装成普通业务错误。
- 远程成功返回的正文也不直接打印到终端；成功内容仍需要业务侧审查，错误脱敏不是通用个人信息过滤器。

[故障注入测试](../tests/mcp-safety.test.ts)同时检查最终状态和下一轮模型输入，不能只检查日志有没有报错。
这个中间件不替你清除 SDK 低层追踪中的异常；启用 LangSmith 前仍须独立审查和脱敏所有父子 run。

### 4.3 Streamable HTTP 是可选远程路径

| 模式 | 参数与配置 | 连接行为 | 费用/隐私边界 |
|---|---|---|---|
| 默认 | 无额外参数 | 本地 stdio fixture + 替身 | 无模型调用，仍运行本地子进程 |
| 远程 MCP | `--remote` 与 `MCP_SERVER_URL` | Streamable HTTP + 替身 | 显式访问远程服务，查询会发给该服务 |
| 真实模型 | `--live` | 本地 MCP + `ChatOpenAI` | 模型可能计费，工具结果可能发给模型 |
| 两者都用 | `--live --remote` | 远程 MCP + 真实模型 | 同时审查两类服务的数据与费用边界 |

你自行把 `env.example` 复制为 `.env`，只为需要的可选服务填写配置。
`npm run example:mcp -- --remote` 才读取并连接 `MCP_SERVER_URL`；环境中存在 URL 不会自动切换。
连接配置使用 `transport: "http"`，其含义是 Streamable HTTP，不是随意对任意 REST endpoint 发请求。
本章没有运行远程 MCP、真实模型或追踪联调，不能据此声称任何外部 endpoint 已兼容。
`--trace` 必须同时有 `--live`；远程 MCP 本身不要求启用 LangSmith。

传统 HTTP+SSE 传输只作为旧系统兼容背景，不是新教程主线。
Streamable HTTP 可以使用流式响应；看到 SSE 内容类型不等于使用传统单独 SSE endpoint。
浏览器 UI 的 SSE 适配、MCP HTTP 传输和 Agent 的流 API 也不能相互替代。
你部署远程 server 时再考虑认证、Origin 校验、会话、网络边界和连接复用，不以“能连上”作为安全验收。
HTTP 的协议会话不是聊天 `thread_id`，也不是用户授权凭据；两种身份边界分别校验。
URL 格式合法不等于网络目标获准，仍需地址准入、重定向和 SSRF 防护。
`adapter.config` 是运行配置快照，不是自动脱敏日志对象；不要打印含认证信息的配置。

`close()` 关闭当前客户端连接并清理发现缓存，不会停止远端部署的服务。
关闭后的 adapter 仍可再次发现和重新连接，因此清理之后不要无意调用 `listTools()`。
断线重连也不代表可以重放写工具；远程副作用需要自己的幂等与执行状态检查。

## 5. 概念：Skills 与 Deep Agents 在不同层

### 5.1 Skills 是分层指令，不是 MCP 协议

Skill 通常是某项任务的说明包，例如“先检索政策、逐项检查例外、最后输出审阅结果”。
常见入口是 `SKILL.md` 的名称、描述与正文，相关参考按需加载。
渐进披露先暴露简短说明，命中任务后再加载正文和必要资料，避免把所有说明塞进每次上下文。
具体发现、触发与加载方式由宿主/SDK 实现，不是 MCP 的统一传输规则。

| 层 | 主要回答的问题 | 本课程是否需要新增依赖 |
|---|---|---|
| Prompt | 这次回答按什么约束组织？ | 不需要 |
| Skill | 这个任务按什么步骤与参考完成？ | 基础概念不需要，自动加载需宿主支持 |
| MCP | 怎样连接、发现和调用外部能力？ | 已包含 adapter 与本地 fixture |
| Deep Agents | 怎样组合规划、子任务、上下文与工具？ | 可选 SDK，不在本课程基础依赖中 |

Skill 可以指导如何使用 MCP 工具，但不能给工具增加权限。
MCP server 的一个 prompt 也不会自动变成可发现、渐进加载的 Skill。
`createAgent()` 不会仅因为目录里放了 `SKILL.md` 就自动加载它。
你读取外部 Skill 时，和读取工具描述一样检查其来源与越权指令。

### 5.2 Deep Agents 不是“更高级 MCP”

Deep Agents 是更高层的 Agent 组合，常涉及规划、任务委派、文件式上下文管理和子 Agent。
它可以使用 MCP 提供的能力，也可以使用普通本地工具，两者并不绑定。
它的 Skills 支持属于可选 SDK/宿主能力，不是本章 MCPAdapter 的方法。
本课程没有安装或运行 Deep Agents，因此不提供未经验证的 SDK 调用片段。
你先完成只读工具、预算、状态隔离与恢复，再评估是否需要额外规划复杂度。
不要为了“更像 Agent”添加不受限制的 shell/文件工具或自动扩展权限。

## 6. 练习：协议正确之后，再验证治理

### 练习 A｜基础：发现、调用与关闭

运行本地片段，记录 server 原工具名、适配后的名称和参数 Schema。
将白名单名称故意改错，观察拒绝未知能力的路径。
**验收**：没有匹配工具时不调用其他工具，异常后仍正常退出，不遗留子进程。

### 练习 B｜进阶：验证故障边界

在受控练习副本中模拟 server 启动失败或工具调用失败，不连接远程服务。
检查启动命令、stdout/stderr、工具超时与 finally 清理四个位置。
**验收**：你能区分发现失败、调用失败、取消和正常空结果；不盲重试未知写操作。

### 练习 C｜挑战：设计一个政策审阅 Skill

设计分层说明：短描述、审阅步骤、按需参考资料、预期输出。
只允许已有只读知识检索工具，要求无证据时转人工，并写出恶意资料的拒绝规则。
**验收**：Skill 不声称自动获得权限，不依赖 MCP 特有 Skill 方法，不引入任意 shell/文件操作。

## 7. 自测与复盘

1. **MCPAdapter 构造后，是否已经连接所有 server？**
   简答：没有。工具/资源发现等操作按需建立连接，构造本身不执行发现。
2. **为什么 stdio server 不能随意打印 stdout？**
   简答：stdout 是协议帧通道，混入日志会导致解析故障；调试走脱敏 stderr。
3. **`readOnlyHint: true` 是否保证远程工具不写数据？**
   简答：不保证。它是提示，真正行为、权限与副作用仍需实现和部署边界约束。
4. **Skills 是 MCP 的一种 transport 吗？**
   简答：不是。Skills 是任务指令和参考的分层组织，MCP 是能力连接协议。
5. **为什么 `adapter.close()` 放在 finally？**
   简答：工具发现、Agent 或调用失败也需要关闭连接/子进程，不能只在正常输出后清理。

用一句话复盘：**MCP 连接能力，Skills 组织做法，Deep Agents 组合任务；授权始终留在应用边界。**

## 8. AI Review 提示词与资料

> 请审查我的 Node.js MCP 集成。按配置、连接、发现、工具筛选、调用、结果、关闭逐跳检查。核对 MCPAdapter 2.x 方法和 Streamable HTTP 配置，区分 fixture 的 legacy 协议接口与旧 SSE。找出任意命令、URL、路径、工具描述注入和清理遗漏。最后解释 Skill/Deep Agents 是否真的必要，不把它们当成权限授予。不要连接远程服务或执行未知工具。

- [原课程参考仓库](https://github.com/xiaoyu0513-cell/AI_Learning_Data/tree/main/ai_coding/LangChain_%E5%B0%9A%E7%A1%85%E8%B0%B7)：保留 MCP 与 Skills 主题，明确 JS 版本边界。
- [官方 JS MCP](https://docs.langchain.com/oss/javascript/langchain/mcp)
- [MCP 架构](https://modelcontextprotocol.io/docs/learn/architecture)
- [MCP 传输规范](https://modelcontextprotocol.io/specification/latest/basic/transports)
- [官方 JS Deep Agents](https://docs.langchain.com/oss/javascript/deepagents/overview)
- [官方 JS Skills](https://docs.langchain.com/oss/javascript/deepagents/skills)

[上一章：LangGraph](11-langgraph.md) · [下一步：综合知识助手](../examples/13-knowledge-assistant.ts) · [课程首页](../README.md)
