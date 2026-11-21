# 第 01 课：把 LLM 能力接进你熟悉的 Node.js 应用

> 完成本课后，你能运行一个不需要密钥的示例，并判断一个需求应该先用 Prompt、RAG、工具、Agent，还是考虑微调。

[课程首页](../README.md) · [下一课：模型调用](02-models.md)

**前置知识**：你会写 Node.js 异步函数，使用 npm，并能读懂 TypeScript 类型。
本课不重讲 JavaScript 语法，而是把你已有的 Vue/Nuxt/Node 经验迁移到 LLM 应用工程。

**版本口径**：Node.js 最低 22.12；项目 `.nvmrc` 记录 Node 26.13.1。
本教程使用 ESM、严格 TypeScript、`tsx`，以项目的 `package-lock.json` 为准。
`langchain` 为 1.6.7，`@langchain/core` 为 1.5.0，`@langchain/openai` 为 1.8.5。
原始资料提供学习主题；你使用的是当前 JavaScript API，不是逐行翻译的 Python 代码。

## 1. 目标

- [ ] 运行离线示例，不申请模型账号、不填写密钥。
- [ ] 区分模型能力、应用编排与业务系统的职责。
- [ ] 解释 LLM、RAG、Agent、微调分别解决什么问题。
- [ ] 理解 Node 的 Promise 与 Python 协程的调用差异。
- [ ] 确认真实调用与追踪都需要你显式选择。

## 2. 概念：先决定你要解决什么问题

### 2.1 LLM 是有边界的能力，不是可靠数据库

你把消息发送给模型，模型生成回答、结构化字段，或工具调用请求。
模型能做摘要、分类、改写和推理，但不能仅凭一句“请准确回答”获得你公司的实时订单状态。

对比你熟悉的后端接口：

| 对比点 | 常规业务 API | LLM 调用 |
| --- | --- | --- |
| 输入 | 明确字段与约束 | 消息、上下文、可用工具等 |
| 输出 | 按业务代码生成 | 按模型能力和采样配置生成 |
| 一致性 | 通常能定义确定结果 | 相同输入不保证相同回答 |
| 真实性 | 取决于数据库和业务规则 | 需要检索、工具或人工核验支撑 |
| 开销 | 请求耗时与资源占用 | 还受输入/输出长度、模型、重试影响 |
| 安全边界 | 鉴权、授权、事务 | 原有边界仍然必须存在 |

你应让模型负责语言任务，让业务代码负责权限、金额、状态迁移与副作用。
例如，模型可以提出“查询订单”，但你的服务决定当前用户能否查询该订单。

### 2.2 Prompt、RAG、工具、Agent、微调怎么选

| 需求 | 优先选择 | 你需要补上的工程能力 |
| --- | --- | --- |
| 给一段现成文本写摘要 | Prompt + 模型调用 | 输入限制、输出验证、失败处理 |
| 回答会更新的内部规则 | RAG | 文档清洗、检索、引用、无证据策略 |
| 获取某个订单的当前状态 | 只读工具/API | 参数校验、鉴权、超时、审计 |
| 自主决定查询哪些信息、下一步做什么 | Agent | 工具白名单、预算、终止条件、人工确认 |
| 流程固定但存在分支与审批 | 显式工作流/LangGraph | 状态、路由、检查点、恢复 |
| 大量样本体现稳定风格或特定任务模式 | 先评估，再考虑微调 | 数据集、评测、成本与版本管理 |

这些方案不是互斥选项。
你可以让 Agent 使用检索工具，也可以把 RAG 放在固定工作流里，不让模型决定流程。
如果只有一次检索和一次回答，先写确定的流水线，通常比引入 Agent 更容易测试。

**微调不等于给模型装数据库**。
你通过训练样本调整行为模式，不因此获得实时库存、精确权限或可靠的事实引用。
先建立基线和评测集；没有证据说明 Prompt/RAG 无法解决问题时，不急着训练模型。
本教程不执行微调，也不把“接入一个模型”描述为“训练了一个模型”。

### 2.3 RAG 的核心是把证据带进输入

你先从资料中检索相关片段，再把问题和片段一起交给模型。

```text
资料 -> 分段 -> 建立检索索引
问题 -> 检索相关片段 -> 组装消息 -> 模型 -> 回答与来源
```

RAG 不保证检索到正确资料，也不保证模型忠于资料。
你还要检查来源、相关性、权限，以及“没有证据时是否明确拒答”。
第 10 课的离线向量示例使用关键词嵌入替身，不能用它证明语义检索效果。

### 2.4 LangChain 与 LangGraph 各做什么

| 组件 | 你用它做什么 | Node.js 类比 |
| --- | --- | --- |
| `@langchain/openai` | 适配模型供应商调用 | 第三方 API 客户端 |
| `@langchain/core` | 消息、Prompt、Runnable 等公共协议 | 应用内共享接口与基础工具 |
| `langchain` | 创建 Agent、工具与中间件 | 带有约定的应用编排层 |
| `@langchain/langgraph` | 显式状态图、分支、检查点、恢复 | 可恢复的状态机/工作流引擎 |
| LangSmith | 可选的追踪、评测与排错 | 面向 LLM 调用链的可观测平台 |

你不需要先把所有包背下来。
先看每一步接收什么、返回什么，再看哪个包提供这一层实现。
本教程仅在本地向量库中使用 `@langchain/classic`，不以它作为新 Agent API 的入口。

### 2.5 Node Promise 与 Python async 的迁移点

| Python 资料中的写法/概念 | Node.js/TypeScript 对应 | 注意事项 |
| --- | --- | --- |
| 调用 `async def` 返回协程对象 | 调用 `async function` 返回 Promise | Node 函数会执行到第一个暂停点；Python 协程通常需等待或调度才执行 |
| `await model.ainvoke(...)` | `await model.invoke(...)` | JS 不通过 `a` 前缀区分异步方法 |
| `async for` | `for await...of` | 流是异步可迭代对象，不是完整字符串 |
| `asyncio.gather(...)` | `Promise.all(...)` | 并发等待不等于创建 CPU 线程 |
| 捕获异常 | `try/catch` 捕获被等待的 Promise 拒绝 | 忘记 `await` 可能绕过当前错误处理 |
| 上下文中的取消 | `AbortSignal` 等调用配置 | 客户端中止不保证服务端停止计费 |

你在 Nuxt 服务端调用模型，不把密钥放进客户端组件或公开的运行时配置。
模型调用是网络 I/O；复杂文本处理仍可能阻塞 Node 事件循环，需要单独考虑资源限制。

## 3. 源码/示例阅读：先跑通，再沿数据流阅读

### 3.1 最短离线路径

在仓库的 `langchain/` 目录中，先确认 Node 版本，再按[课程首页](../README.md)安装锁定的依赖（首次安装可能访问 npm registry）。依赖就绪后，这两条命令不请求模型服务：

```bash
node --version
npm run example:overview
```

使用 nvm 时，按 `.nvmrc` 切换版本，然后检查 `node --version`；旧版 Node 可能连依赖都无法加载。
概览示例会输出 Node 版本与 `system`、`human`、`ai` 三类手工构造的消息，不会执行模型推理。
默认运行不需要 `.env`，也不会因终端已有密钥而切换到真实模型。

### 3.2 阅读哪些文件

| 文件 | 你重点查看什么 |
| --- | --- |
| [概览示例](../examples/01-overview.ts) | 环境信息、消息对象与离线模式 |
| [运行时入口](../src/runtime.ts) | 参数解析、环境加载、追踪开关、错误出口 |
| [模型工厂](../src/models.ts) | fake 与 live 的选择，而不是根据密钥猜测模式 |
| [依赖清单](../package.json) | npm 脚本、Node 要求、固定包版本 |
| [环境模板](../env.example) | 只有可选联调才需要的配置项 |

按“命令 -> 运行时 -> 模型/消息 -> 输出”的顺序读。
不要从整个依赖源码开始；先确定应用代码在哪个边界交给框架。

### 3.3 固定回复也能帮你理解调用协议

这个片段补上概览脚本没有执行的模型接口调用。后续各课的 TypeScript 片段都可以**一次复制一段**到 `examples/scratch.ts`，在 `langchain/` 下运行 `npm exec -- tsx examples/scratch.ts`，无需另建工程。
`configureRuntime([])` 固定使用离线配置，不加载 `.env`，也不继承终端的自动追踪开关。

```typescript
import { HumanMessage, SystemMessage } from "@langchain/core/messages";
import { FakeListChatModel } from "@langchain/core/utils/testing";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
const model = new FakeListChatModel({
  responses: ["这是固定练习回复，没有调用真实模型。"],
});
const reply = await model.invoke([
  new SystemMessage("你用中文解释概念。"),
  new HumanMessage("RAG 与微调有什么区别？"),
]);
console.log(reply.type, reply.content);
```

输入是消息数组，输出是 `AIMessage`，不是业务接口的 `{ data }`。
这里的内容由你预先写死，不来自推理；即使改了问题，替身仍返回固定文本。
你用它验证消息与异步调用的连接，不用它评价模型能力。
真实消息的 `content` 还可能是内容块数组，下一课会处理这个区别。

### 3.4 把“允许联网”设计成显式操作

运行时遵循下面的模式检查；这个缩小片段自身不会联网。

```typescript
const live = process.argv.includes("--live");
const trace = process.argv.includes("--trace");
if (trace && !live) {
  throw new Error("追踪上传必须同时显式选择 --live。");
}
console.log({ live, trace, mode: live ? "live" : "offline" });
```

[共享入口](../src/runtime.ts)还会拒绝未知参数。对于支持联调的示例，你用 `--live` 选择真实模型；要允许追踪上传，还需同时传入 `--trace`。
概览脚本只构造消息；下一课的模型示例才有真实调用分支。即使终端已有密钥或启用了 `LANGSMITH_TRACING`，默认运行也不选择这些分支。

### 3.5 常见误区

- 把离线测试通过写成“模型回答准确”：替身只证明程序路径。
- 把 RAG 当训练：检索片段进入本次上下文，不直接更新模型权重。
- 把 Agent 当鉴权层：工具仍由你的服务校验权限。
- 把所有需求交给 Agent：固定流程不必增加自主决策和循环。
- 把密钥放进前端：只在服务端读取，且不要打印整个环境变量对象。

## 4. 练习：从能运行到能做取舍

### 练习 A：基础——画清边界

运行概览示例，记录模式、输入消息类型与输出类型。
**验收**：你能指出哪里是本地 fixture，哪里只有加 `--live` 才可能访问模型服务。

### 练习 B：进阶——给需求做最小方案选择

分别设计“文章摘要”“不断更新的退货规则问答”“查询订单配送状态”的最小方案。
**验收**：每个方案说明数据从哪里来、模型做什么，以及没有证据或调用失败时怎么办。

### 练习 C：挑战——拆掉不必要的 Agent

把“查文档 -> 回答 -> 人工复核”的固定流程写成步骤清单，不实现联网代码。
**验收**：明确哪个分支可由代码决定，什么情况下才值得让模型选择工具。

## 5. 自测/复盘：先回答，再看简答

1. **只有实时事实缺失，首先该考虑微调吗？**
   简答：通常先考虑检索或业务工具；微调不能代替实时数据源。
2. **为什么这份教程默认用 fake model？**
   简答：稳定验证数据流、校验和错误处理，避免密钥、费用与网络依赖。
3. **Node 的 `invoke()` 没有 `a` 前缀，是同步调用吗？**
   简答：不是；它返回 Promise，你通过 `await` 获取结果和捕获失败。
4. **LangChain 能自动让订单修改符合业务权限吗？**
   简答：不能；授权、幂等、事务和审计仍由业务系统负责。
5. **填好 `.env` 是否意味着示例开始联网和上传追踪？**
   简答：不是；本项目用显式参数控制，默认既不加载 `.env` 也不追踪。

**复盘句**：你把语言能力接进可验证的业务流程，而不是把业务规则交给模型猜。

## 6. AI Review 提示词

> 请评审我的 LLM 需求拆分，不替我直接升级成 Agent。
> 我会给出需求、数据源、模型职责和失败策略。
> 请检查是否混淆 RAG 与微调，是否漏掉权限边界，是否用 fake 结果冒充模型质量证据。
> 每个问题给一个最小修正，并让我解释修正前后的输入与输出。

## 资料与下一步

- [原始学习资料：LangChain 尚硅谷](https://github.com/xiaoyu0513-cell/AI_Learning_Data/tree/main/ai_coding/LangChain_%E5%B0%9A%E7%A1%85%E8%B0%B7)
- [LangChain JavaScript 概览](https://docs.langchain.com/oss/javascript/langchain/overview)
- [LangGraph JavaScript 概览](https://docs.langchain.com/oss/javascript/langgraph/overview)
- [Node.js ESM](https://nodejs.org/api/esm.html)

[返回课程首页](../README.md) · [下一课：模型调用](02-models.md)
