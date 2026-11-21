# LangChain Node.js 教程：从模型调用到知识库 Agent

> 写给已有 Vue / Nuxt / Node.js 经验、准备学习 LLM 应用工程的开发者。\
> 参考 [Python 原课程](https://github.com/xiaoyu0513-cell/AI_Learning_Data/tree/main/ai_coding/LangChain_%E5%B0%9A%E7%A1%85%E8%B0%B7) 的 11 个主题，按 LangChain.js API 重新编写；额外增加 LangGraph 桥接课。\
> 形式：**12 篇专题正文 + 7 天练习工作簿 + 13 个可运行 Node.js/TypeScript 示例**。\
> 版本核对日期：2026-11-21。依赖以 [package.json](package.json) 和 [package-lock.json](package-lock.json) 为准。\
> 公开边界：只使用匿名订单、模拟回答与本地政策资料；默认不调用模型、不上传追踪。

## 1. 课程定位：先学应用工程，不先堆一个万能 Agent

学完基础路线，你应能解释并实现以下闭环：

1. 初始化聊天模型，处理消息、批处理、流式输出与错误。
2. 用 PromptTemplate 与 Runnable 组织输入输出，而非拼接散乱字符串。
3. 定义带 Zod Schema 的只读工具，区分“模型要求调用”与“应用实际执行”。
4. 把模型输出当外部数据，分别验证语法、结构、来源与业务意义。
5. 使用 `createAgent` 编排工具循环，并控制调用预算。
6. 区分线程检查点、长期记忆、运行上下文与权限。
7. 完成文档加载 → 切片 → 向量 → 检索 → 回答的 RAG 管道。
8. 用 LangGraph 表达可控分支、人工暂停与恢复；用 MCP 连接已审阅的工具。

**不包含**：模型训练、微调实操、生产鉴权、支付/退款执行、无限制 shell 工具、向量数据库运维、完整 Web UI 部署。七天是基础训练节奏，不是七天精通全部 Agent 生态。

前置知识只需：ESM、Promise、`async/await`、对象与数组、基本 TypeScript、HTTP 和环境变量。不用先学 Python。

## 2. 知识地图与原课程对照

| 原课程主题 | Node.js 正文 | 本课重点 |
|---|---|---|
| 01 概述 | [01 · 全景与环境](lessons/01-overview.md) | LangChain / LangGraph / LangSmith，何时用 RAG/Agent |
| 02 模型创建与调用 | [02 · 模型](lessons/02-models.md) | `initChatModel`、`ChatOpenAI`、invoke/batch/stream |
| 03 LangSmith | [03 · 追踪](lessons/03-langsmith.md) | trace、metadata、显式上传、脱敏与回调收尾 |
| 04 Message 与提示词 | [04 · 消息和 Prompt](lessons/04-messages-prompts.md) | 消息角色、历史、模板、LCEL/Runnable `.pipe()` |
| 05 Tools | [05 · 工具](lessons/05-tools.md) | Zod、bindTools、ToolMessage、手动闭环 |
| 06 结构化输出 | [06 · 结构化数据](lessons/06-structured-output.md) | TypeScript vs 运行时校验，模型 vs Agent 输出 |
| 07 Agent | [07 · Agent](lessons/07-agents.md) | `createAgent`、自动工具循环、终止与流式 |
| 08 Middleware | [08 · 中间件](lessons/08-middleware.md) | hook 顺序、错误转换、预算、摘要、人工审批 |
| 09 上下文与记忆 | [09 · 记忆](lessons/09-context-memory.md) | MemorySaver、thread_id、namespace、Postgres 边界 |
| 10 RAG | [10 · 检索增强](lessons/10-rag.md) | Document、splitter、Embedding、retriever、引用 |
| Node 版新增 | [11 · LangGraph](lessons/11-langgraph.md) | StateSchema、节点增量、条件边、interrupt/resume |
| 11 MCP 与 Skills | [12 · 协议和技能](lessons/12-mcp-skills.md) | MCPAdapter 2.x、stdio/HTTP、Skills/Deep Agents 分层 |

**不是机械翻译的地方：**

- `conda/pip` → Node.js/npm；Python 单独同步/异步接口 → Promise/AsyncIterable。
- Pydantic/TypedDict → Zod + `z.infer`；TS 类型声明本身不会验证模型 JSON。
- `create_agent` → `createAgent`，读取 `structuredResponse`；协议键 `thread_id`、`tool_call_id` 仍保留下划线。
- 旧 `LLMChain`/`AgentExecutor` 不作为新项目主线；用 Runnable 和 v1 Agent。
- 不翻译原课的整个 Python 环境快照、不复制真实连接串、不依赖所有 loader 或外部向量服务。

### 版本与运行环境

| 组件 | 本项目固定版本 | 用途 |
|---|---|---|
| Node.js | `.nvmrc` 为 26.13.1；最低声明 ≥22.12 | 当前实际验证环境；最低版本未单独复测 |
| TypeScript / tsx | 7.0.2 / 4.23.15 | strict、NodeNext、直接运行 `.ts` |
| langchain / core | 1.6.7 / 1.5.0 | Agent、工具、消息、Prompt、Runnable |
| OpenAI integration | 1.8.5 | 主线真实聊天/Embedding 适配器 |
| LangGraph | 1.7.5 | 状态、检查点、审批与恢复 |
| textsplitters | 1.0.2 | 递归字符切片 |
| classic | 1.0.62 | **仅用于 MemoryVectorStore**，不启用旧 Chain/Agent |
| MCP adapters / server SDK | 2.0.3 / 1.32.2 | 适配器 2.x 与本地 SDK 1.x fixture 的协议兼容 |
| LangSmith / Zod | 0.13.1 / 4.6.7 | 可选追踪、运行时 Schema |
| Vitest / Vite | 5.1.1 / 8.3.8 | 离线测试；Vite 仅是测试运行器依赖，不构建 UI |

各包版本号不必相同。升级后重新执行完整验收；“能兼容 OpenAI 基础聊天”不等于支持工具、强制工具选择、原生 JSON Schema 或 Embedding。

## 3. 必读代码与快速运行

### 3.1 安装一次，先跑无密钥路径

以下从仓库根执行；若已经进入 `langchain/`，跳过 `cd`：

```bash
cd langchain
nvm install
nvm use
node --version
npm ci
npm run example:prompt
npm run example:tool
npm run example:rag
```

- 不用 nvm 时，选择满足要求的 Node.js，记录你实际使用的版本。
- `npm ci` 下载依赖，需要网络；**离线示例**指安装完成后的执行不依赖外部模型服务。
- 不要在仓库根运行 `npm install`；这是独立工程，没有根 workspace。
- npm 若提示安装脚本未授权，先检查具体包/脚本并按自己的权限策略处理，勿全局关闭安全检查。
- `.ts` 由 tsx 执行；NodeNext 的相对 import 写 `.js`，不是让你手工生成同名文件。

### 3.2 按数据流读这些文件

| 顺序 | 文件 | 阅读问题 |
|---|---|---|
| 1 | [runtime.ts](src/runtime.ts)、[models.ts](src/models.ts) | 哪个显式开关才会触发服务调用？ |
| 2 | [04-prompt-pipeline.ts](examples/04-prompt-pipeline.ts) | PromptValue → 模型 → parser 如何串起来？ |
| 3 | [tools.ts](src/tools.ts)、[05-tool-schema.ts](examples/05-tool-schema.ts) | tool_calls、参数和 ToolMessage ID 如何关联？ |
| 4 | [structured.ts](src/structured.ts)、[06 示例](examples/06-structured-output.ts) | Schema 合法与有事实依据有何不同？ |
| 5 | [07 Agent](examples/07-agent.ts)、[08 中间件](examples/08-middleware.ts) | 框架替你做了哪部分循环，预算由谁控制？ |
| 6 | [09 记忆](examples/09-memory.ts) | 线程历史与用户 namespace 分别存什么？ |
| 7 | [rag.ts](src/rag.ts)、[10 示例](examples/10-rag-local.ts) | 来源怎样贯穿加载、切片、检索与回答？ |
| 8 | [graph.ts](src/graph.ts)、[11 示例](examples/11-langgraph.ts) | 暂停后怎样用同一线程恢复？ |
| 9 | [mcp.ts](src/mcp.ts)、[错误边界](src/mcp-safety.ts)、[本地 server](examples/mcp-server.ts) | 发现、执行、错误脱敏与关闭连接分别在哪里？ |
| 10 | [综合助手](examples/13-knowledge-assistant.ts) | 检索工具、结构化结果、引用与流式怎样组合？ |

这里的“源码阅读”是本地应用示例与已安装 SDK 声明，不要求先逐行读模型或框架内核。

## 4. 七天详细安排

| 天 | 工作簿 | 正文 | 必做产出 | 建议投入 |
|---|---|---|---|---|
| Day 1 | [环境与调用](day01.md) | 01–02 | 调用职责图、invoke/batch/stream 输出比较 | 2h |
| Day 2 | [消息与追踪](day02.md) | 03–04 | Prompt 管道、历史占位符、追踪准入清单 | 2h |
| Day 3 | [工具与结构](day03.md) | 05–06 | 工具调用闭环、非法参数与输出校验反例 | 2h |
| Day 4 | [Agent 与治理](day04.md) | 07–08 | 自动循环、hook 顺序、调用上限验证 | 2.5h |
| Day 5 | [上下文与记忆](day05.md) | 09 | thread 与 namespace 隔离证据 | 2h |
| Day 6 | [RAG 管道](day06.md) | 10 | 文档切片、命中/无命中、引用上下文 | 3h |
| Day 7 | [图与协议](day07.md) | 11–12 | 审批恢复、本地 MCP、综合助手复盘 | 3h |

每天按：目标 → 概念 → 源码阅读 → 分级练习 → 自测/复盘 → AI Review。精力不足时先完成必做与失败路径，生产持久化、多模态、PDF/CSV、其他供应商放到后续专题复习。

## 5. JS / Node 类比与类比的边界

| 熟悉的东西 | LangChain 中的对象 | 类比到哪里停止 |
|---|---|---|
| 模板函数 | PromptTemplate | 还需要保留消息角色和历史，不只返回字符串 |
| 函数组合 | Runnable `.pipe()` | 附带统一调用、流式、配置、追踪语义 |
| Express handler | `tool` callback | 模型只提交调用请求，权限仍属于服务端 |
| DTO / TS interface | Zod Schema + `z.infer` | interface 不检查外部 JSON，Schema 才执行校验 |
| 编排服务 | `createAgent` | Agent 的下一步由模型决定，必须有上限和安全边界 |
| 会话状态 | checkpointer | 保存 thread 状态，不自动获得长期用户记忆 |
| KV 命名空间 | store | namespace 不等于鉴权，用户边界由应用保证 |
| 搜索索引 | 向量库/retriever | 相似度不是事实可信度，也不是直接答案 |
| 工作流状态机 | LangGraph | 节点返回增量；恢复可能重新执行节点代码 |
| 受控子进程/HTTP client | MCP adapter | 工具自报“只读”不等于实施了权限控制 |

## 6. 示例、配置和学习产出

### 6.1 全部入口

所有命令都在 `langchain/` 下执行，默认不需要密钥：

| npm 命令 | 观察内容 | 可选真实路径 |
|---|---|---|
| `example:overview` | 消息构造与环境 | 无需 |
| `example:model` | invoke、batch、stream、usage | `--live` |
| `example:trace` | 本地调用任务 | `--live --trace` 才上传 |
| `example:prompt` | 模板、历史、Runnable | `--live` |
| `example:tool` | 手动工具调用闭环、参数校验 | `--live` |
| `example:structured` | 固定 JSON + 显式 Zod 校验 | `--live` |
| `example:agent` | Agent 自动执行只读工具 | `--live` |
| `example:middleware` | hooks 与预算 | `--live` |
| `example:memory` | 同线程/新线程、store namespace | `--live`，仍是内存存储 |
| `example:rag` | 本地文档与词项向量、模拟生成 | `--live` 替换聊天和 Embedding |
| `example:graph` | 规则分支、审批与恢复 | 无需模型 |
| `example:mcp` | 本地 MCP fixture + Agent | `--remote`、`--live` 分别显式选择 |
| `example:assistant` | 检索工具 + 结构化回答 + 记忆 + 流式 | `--live` **仅替换聊天模型** |

批量运行：

```bash
npm run examples:offline
```

该命令不给子程序传任何 live/trace/remote 参数，并额外禁用 HTTP `fetch`。它不是完整网络沙箱；不运行未知第三方 server。MCP 示例只启动仓库内的只读 fixture，结束时关闭子进程。

### 6.2 配置只在你选择真实联调时准备

环境模板使用 [env.example](env.example)。本环境不允许助手写入受保护的 `.env*` 文件，因此提供了普通文件名模板；**本地默认练习完全不需要复制它**。

你需要真实服务时，自行复制为 `.env`；已有文件不覆盖：

```bash
cp -n env.example .env
```

| 配置 | 用途 |
|---|---|
| `OPENAI_API_KEY` | 所选兼容服务的访问密钥，只留在服务端 |
| `OPENAI_MODEL` | 你的服务实际提供的聊天模型名，没有硬编码付费模型 |
| `OPENAI_BASE_URL` | 可选自定义 HTTP(S) API 地址；空值使用适配器默认地址 |
| `OPENAI_EMBEDDING_MODEL` | 仅真实 RAG 示例需要，聊天模型不能代替它 |
| `LANGSMITH_API_KEY` / `LANGSMITH_PROJECT` | 仅显式追踪时使用 |
| `MCP_SERVER_URL` | 仅 `--remote` 使用的已审阅 Streamable HTTP 服务 |
| `DATABASE_URL` | PostgreSQL 扩展阅读占位符，默认示例不连接数据库 |

读者选择真实模式的例子（可能计费、可能外发匿名资料）：

```bash
npm run example:model -- --live
npm run example:structured -- --live
npm run example:rag -- --live
npm run example:trace -- --live --trace
```

默认忽略终端遗留的四种追踪开关，仅在 `--live --trace` 时启用。仅 `--trace` 会拒绝执行。改变 API base URL 不会让服务自动支持工具、JSON Schema 或 Embedding。

### 6.3 学习产出

- 一份“模型决定什么 / 代码执行什么”的职责说明。
- 一套带合法/非法输入的工具与工单 Schema。
- 一份线程、用户和检索来源的隔离记录。
- 一个默认离线的知识库助手，以及“不该回答”的反例。
- 一份实际执行的验收记录，不把本地模拟描述成生产经验。

## 7. 推荐资料与延伸顺序

1. [原课程](https://github.com/xiaoyu0513-cell/AI_Learning_Data/tree/main/ai_coding/LangChain_%E5%B0%9A%E7%A1%85%E8%B0%B7)：用于知识坡度对照，不照搬 Python 导入和字段。
2. [LangChain JS 概述](https://docs.langchain.com/oss/javascript/langchain/overview)、[模型](https://docs.langchain.com/oss/javascript/langchain/models)、[工具](https://docs.langchain.com/oss/javascript/langchain/tools)、[Agent](https://docs.langchain.com/oss/javascript/langchain/agents)。
3. [结构化输出](https://docs.langchain.com/oss/javascript/langchain/structured-output)、[短期记忆](https://docs.langchain.com/oss/javascript/langchain/short-term-memory)、[长期记忆](https://docs.langchain.com/oss/javascript/langchain/long-term-memory)、[RAG](https://docs.langchain.com/oss/javascript/langchain/retrieval)。
4. [LangGraph JS](https://docs.langchain.com/oss/javascript/langgraph/overview)、[MCP](https://docs.langchain.com/oss/javascript/langchain/mcp)、[LangSmith](https://docs.langchain.com/langsmith/observability)。
5. [LangChain.js 源码](https://github.com/langchain-ai/langchainjs)、[Zod](https://zod.dev/)。签名核对优先看实际 lockfile 对应的声明。

扩展建议：评估集与检索指标 → 真实 Embedding/向量库 → 鉴权和数据边界 → PostgresSaver/PostgresStore → Web 流适配 → 必要时 Deep Agents。不要第一天安装所有供应商和数据库。

## 8. 验收标准与证据边界

```bash
npm run check
npm run examples:offline
```

[测试](tests/)覆盖运行开关、消息/模板、Schema、工具 ID、自动循环、中间件阶段和预算、线程/namespace、检索来源/空结果、图分支/审批恢复以及本地 MCP 调用。[静态文档检查](scripts/check-docs.ts)核对本地链接、脚本名称、代码围栏与路径规则。

| 你要证明什么 | 本地能否验证 | 额外证据 |
|---|---|---|
| 输入类型、模板变量、工具 Schema | 能 | 无需密钥的测试 |
| 工具循环、错误结果、终止、hook | 能 | 预设 `AIMessage.tool_calls`，不代表真实模型选择正确 |
| thread / namespace 隔离 | 能 | 内存状态，进程退出即消失 |
| RAG 管道、相对来源、空结果 | 能 | **词项模拟向量**；不代表中文语义检索质量 |
| 本地 MCP 发现、调用、清理 | 能 | 已审阅 fixture，不保证未知远程服务可信 |
| 真实模型正确率、拒答、费用、延迟 | 不能由离线验证证明 | 真实模型 + 数据集 + 用户选择的预算 |
| 云端追踪、远程 MCP、数据库持久化 | 未默认联调 | 对应账号、网络、部署与访问边界 |

**特别注意：**

- `FakeListChatModel.withStructuredOutput()` 不执行完整 Schema 校验，示例另做 `TicketSchema.parse()`。
- `KeywordEmbeddings` 是四维词项计数，0.1 阈值仅为模拟配置；真实向量需要重新评估。
- 综合助手来源集合只演示一次回答；生产多轮/多租户不能把它当全局授权集合。
- Agent `streamEvents({ ... }, { version: "v3" })` 在固定版本下仍是实验性接口；不混同 LangGraph 的 `streamMode`。
- 事实、权限和副作用边界不靠提示词兜底。示例不会实际退款、修改订单、执行任意 shell 或读取任意文件。

当前执行记录见 [验证记录](VERIFICATION.md)，明确区分通过、失败与未执行项。

## 9. AI Review 提示词

> 我正在完成 LangChain Node.js 学习练习。请基于我提供的脱敏代码、输入和实际输出，检查模型/工具/Agent 的职责边界、运行时校验、工具 ID、调用预算、线程与用户隔离、检索来源及失败路径。区分本地替身测试与真实服务验证。用“问题 → 依据 → 最小修正 → 验证步骤”回答；不要要求我上传 .env、自动调用付费模型、开启追踪或执行真实退款。

## 10. 复盘与继续学习

最后用 3 分钟说明：

1. 用户问题从入口到最终结构化回答，跨了哪些边界？
2. 哪些结果是模拟的，哪些行为已经在本地验证，哪些还没有证据？
3. 换一个供应商、数据库或 MCP server，哪些契约必须重新验收？
4. 怎样拒绝无证据的问题、未知工具或未经授权的写操作？
5. 什么时候直接模型调用或固定工作流，比 Agent 更简单可靠？

**开始学习**：[Day 1](day01.md)；**按主题学习**：[01 · 全景](lessons/01-overview.md)；**直接看完整闭环**：[综合示例](examples/13-knowledge-assistant.ts)。
