# 第 02 课：模型调用——invoke、batch、stream 与供应商配置

> 完成本课后，你能选择合适的调用方式，区分消息与文本，并把真实供应商联调放在明确的费用边界后面。

[上一课：概览](01-overview.md) · [课程首页](../README.md) · [下一课：LangSmith](03-langsmith.md)

**前置知识**：完成[第 01 课](01-overview.md)的环境检查；你理解 Promise 和消息输入。
**版本口径**：本课对应 `@langchain/core` 1.5.0、`@langchain/openai` 1.8.5，使用 ESM/TypeScript。
运行命令时，你位于 `langchain/` 目录，使用 Node.js 22.12 或更高版本。

## 1. 目标

- [ ] 用 `invoke()` 等待一条完整模型消息。
- [ ] 用 `batch()` 处理多条独立输入并限制并发。
- [ ] 用 `stream()` 消费异步消息块。
- [ ] 解释模型名、API key、base URL、超时与重试的职责。
- [ ] 区分离线协议测试与真实模型能力验证。

## 2. 概念：调用方式不同，输出形状也不同

### 2.1 Chat Model 输入与输出

你可以给聊天模型传文本、消息数组，或由 Prompt 生成的消息值。
当业务涉及系统指令、历史或工具时，消息数组比拼接长字符串更容易检查角色边界。

| 方法 | 输入 | 返回 | 常见场景 |
| --- | --- | --- | --- |
| `invoke(input)` | 一条模型输入 | `Promise<AIMessage>` | 后端等待完整回答 |
| `batch(inputs, config)` | 多条独立模型输入 | `Promise<AIMessage[]>` | 多篇摘要、批量分类 |
| `stream(input)` | 一条模型输入 | Promise，解析后为异步可迭代流 | 渐进式输出文本或消息块 |

这里描述的是聊天模型的接口，不是所有 Runnable 的固定返回类型。
例如，Prompt 的 `invoke()` 返回 Prompt 值，文本解析器的 `invoke()` 返回字符串。

### 2.2 batch 是多个任务，不是一段对话

你给 `batch()` 的每个元素是一条独立调用输入。
若要保留对话历史，应把历史放在同一份消息输入中，而不是把每一轮分别塞进 batch。

`maxConcurrency` 限制同时运行的任务数，不等于请求每分钟限额，也不保证不会触发 429。
返回数组按输入位置对应；执行完成的先后顺序不一定与输入顺序相同。
共享可变状态的 fake model 不适合用来测并发时序，正式并发测试需要明确的任务标识。

你熟悉的 `Promise.all()` 遇到拒绝会让等待它的 Promise 拒绝，但不会自动取消其他已启动任务。
因此“批量等待失败”不等于“剩下的模型请求都停止了”。
生产批处理还需要任务状态、有限重试、失败记录，以及必要时的取消机制。

### 2.3 stream 是消息增量，不是 HTTP SSE

模型流让你逐块读取 `AIMessageChunk`。
你再决定如何通过 HTTP 流、SSE 或其他协议交给前端；LangChain 的流对象本身不是 SSE 响应。

| 层次 | 你处理的东西 | 不要混淆的对象 |
| --- | --- | --- |
| 模型层 | 消息块、内容块、工具调用增量 | 完整 `AIMessage` |
| 服务端接口层 | 编码后的事件/字节、结束与错误信号 | 模型内部类型 |
| Vue/Nuxt 前端 | 可展示文本、状态与业务错误 | API key、完整模型元数据 |

一次 chunk 不保证对应一个 token，也不保证包含完整 JSON。
你可能收到空文本块、工具参数增量或最后才到达的使用量信息。
本课的 fake 流按预置文本拆分，不能据此估算真实模型的 token 数或网络时延。

### 2.4 AIMessage 的 content 不总是字符串

你在纯文本练习里经常看到字符串，但多模态与不同供应商可能返回内容块数组。
工具调用也不应靠从 `content` 中查找某个字符串来判断。

- 展示文本：提取字符串或文本内容块。
- 处理工具：检查 `tool_calls`，并验证参数。
- 处理图片、音频或推理块：按具体供应商与块类型处理。
- 处理使用量：读取可用元数据，不凭字符数猜成本。

[模型辅助函数](../src/models.ts)中的 `messageText()` 只做文本投影。
你丢弃了非文本块，不能再把投影后的字符串当完整模型消息。

### 2.5 供应商配置是服务端边界

本教程主线用 `ChatOpenAI`；不同模型仍可能具有不同的能力和参数限制。

| 配置 | 本项目来源 | 你检查什么 |
| --- | --- | --- |
| API key | `OPENAI_API_KEY` | 服务端保存、不写进代码、不打印 |
| 模型名 | `OPENAI_MODEL` | 账号有权限，模型支持所需能力 |
| 可选服务地址 | `OPENAI_BASE_URL` -> `configuration.baseURL` | 可信地址、正确路径、不含 URL 凭证 |
| 超时 | `timeout`，毫秒 | 不让请求无限等待 |
| 重试 | `maxRetries` | 有限次数，避免与上层重试叠加 |

不要为了“更稳定”随意给所有模型加 `temperature: 0`。
并非所有模型都接受相同采样参数；低温度也不构成事实正确或完全确定的保证。
你先使用最小配置，再逐项检查所选模型的支持范围。

## 3. 源码/示例阅读：沿调用边界验证输出

### 3.1 运行离线示例

```bash
npm run example:model
```

你不需要 `.env`，示例使用官方 `FakeListChatModel`。
它返回预设内容，不读取模型服务、不理解你输入的问题。
你应观察三种调用的输出形状，而不是比较回答质量。

| 文件 | 阅读重点 |
| --- | --- |
| [模型示例](../examples/02-models.ts) | invoke、batch、stream 的调用和消费方式 |
| [模型工厂](../src/models.ts) | `createLessonModel()` 与 `createLiveModel()` 的选择 |
| [运行时](../src/runtime.ts) | `--live`、`.env` 加载、禁止隐式追踪 |
| [环境模板](../env.example) | 需要你自行填写的字段，而不是可用凭证 |

### 3.2 invoke 与 batch：完整结果都要等待

按[第 01 课的练习方式](01-overview.md#33-固定回复也能帮你理解调用协议)，每次复制一个片段到 `examples/scratch.ts` 用 `tsx` 运行。

```typescript
import { FakeListChatModel } from "@langchain/core/utils/testing";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
const model = new FakeListChatModel({ responses: ["固定练习回复"] });
const one = await model.invoke("解释一次模型调用。", {
  signal: AbortSignal.timeout(20_000),
});
const many = await model.batch(
  ["给资料 A 写摘要。", "给资料 B 写摘要。"],
  { maxConcurrency: 2 },
);
console.log(one.type, one.content);
console.log(many.map((message) => message.content));
```

`one` 是消息，`many` 是消息数组；这里没有把字符串直接当业务返回对象。
这份替身只配置一条固定回复，所以不同问题仍可能得到完全相同的内容。

### 3.3 stream：先获取流，再异步消费

```typescript
import { FakeListChatModel } from "@langchain/core/utils/testing";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
const model = new FakeListChatModel({ responses: ["这是离线流式练习。"] });
const stream = await model.stream("给出一条练习回复。");
for await (const chunk of stream) {
  if (typeof chunk.content === "string") {
    process.stdout.write(chunk.content);
  }
}
process.stdout.write("\n");
```

这个最小片段只展示 fake 的文本块。
你接入真实模型时，需要决定如何处理非文本内容、流中错误和客户端断开。
对于结构化 JSON，先收集完整结果并验证，再执行任何有副作用的业务操作。

### 3.4 配置真实模型，但不把构造等同于联调成功

下面的独立片段只定义配置工厂，不发送请求。
它读取进程环境；完整示例只在你指定 `--live` 后加载 `.env` 并调用工厂。

```typescript
import { ChatOpenAI } from "@langchain/openai";

function configuredModel() {
  const apiKey = process.env.OPENAI_API_KEY?.trim();
  const model = process.env.OPENAI_MODEL?.trim();
  const baseURL = process.env.OPENAI_BASE_URL?.trim();
  if (!apiKey || !model) {
    throw new Error("真实联调需要 OPENAI_API_KEY 与 OPENAI_MODEL。");
  }
  return new ChatOpenAI({
    apiKey,
    model,
    ...(baseURL ? { configuration: { baseURL } } : {}),
    timeout: 20_000,
    maxRetries: 1,
  });
}
```

实际工厂还通过运行时辅助函数验证 URL 格式，避免把完整地址或凭证写进错误。
你将 `env.example` 复制为 `.env` 后填写自己的配置，再运行 `npm run example:model -- --live`。
**这一步可产生费用，需要你自行联调；离线测试不验证账号、模型或供应商网络。**
不用追踪时，不加 `--trace`；真实模型调用与追踪上传是两种不同的数据外发。

### 3.5 更换供应商时，你迁移的是能力契约

| 迁移方式 | 可以复用的部分 | 必须重新验证的部分 |
| --- | --- | --- |
| 更换 OpenAI 模型 | 消息、Prompt、外围业务流程 | 采样参数、工具、结构化输出、使用量 |
| OpenAI-compatible 服务 | 兼容时可复用 `ChatOpenAI` 配置形状 | base URL、模型标识、协议子集、错误行为 |
| 使用其他原生集成 | 业务输入输出和测试用例 | 对应集成包、配置、内容块与能力 |

“兼容 OpenAI”通常只描述某些接口形状，不承诺所有工具调用和 JSON Schema 选项都支持。
保留应用层的消息/结果处理，但不要用换一个地址代替能力验收。

### 3.6 排错表

| 现象 | 先检查 | 不要这样处理 |
| --- | --- | --- |
| 依赖加载时报新语法错误 | Node 版本、当前终端实际使用的可执行文件 | 盲目改 node_modules |
| 缺少配置 | `.env` 位置、字段名、是否选择 `--live` | 把密钥硬编码 |
| 401/403 | key、账号权限、供应商地址 | 公开原始请求和凭证 |
| 404/参数不支持 | 模型标识、地址路径、能力限制 | 认为换模型一定无缝 |
| 429/超时 | 并发、配额、有限重试与超时 | 加无界循环重复请求 |
| 前端显示对象或空文本 | 是否错误处理内容块或元数据 | 强转 `content as string` |

## 4. 练习：三种调用，三种验收

### 练习 A：基础——记录类型而不是只看文字

运行离线示例，记录 invoke、batch、stream 每层对象的类型与用途。
**验收**：你能解释为什么 batch 结果不能直接当作一条历史消息。

### 练习 B：进阶——把并发限制写进任务设计

设计十条独立摘要任务，把并发限制为 2，并记录每条任务的输入位置。
**验收**：你说明一条任务失败时其他任务是否仍可能继续，以及如何保存失败状态。

### 练习 C：挑战——设计一个不泄密的服务端出口

为 Nuxt 服务端设计“开始、增量、结束、错误”四种事件，不实现真实模型调用。
**验收**：事件不含密钥、原始供应商错误对象；流中断不触发无限重试。

## 5. 自测/复盘：五个边界问题

1. **`invoke()` 的返回值一定是字符串吗？**
   简答：聊天模型返回 `AIMessage`；文本解析器等其他 Runnable 才可能返回字符串。
2. **`batch()` 会自动把多条输入串成对话吗？**
   简答：不会；每条输入独立，历史必须显式放进对应输入。
3. **收到十个 chunk 就消费了十个 token 吗？**
   简答：不是；chunk 划分与 token 数不是一一对应。
4. **把 base URL 换成兼容服务就能保证工具调用可用吗？**
   简答：不能；你需要重新验证该服务与模型的能力。
5. **超时是否保证供应商停止处理并停止计费？**
   简答：不保证；客户端等待结束与服务端执行结束不是同一件事。

**复盘句**：你先理解输入与输出的形状，再决定并发、流式展示与供应商策略。

## 6. AI Review 提示词

> 请检查我的 Node.js 模型调用代码，只审查异步、输出类型、并发和配置边界。
> 标出未等待的 Promise、把内容块强转文本、把 batch 当历史、无界重试等问题。
> 区分 fake 协议测试与真实能力验证，不建议我为了通过测试直接调用付费服务。
> 对每个问题说明具体输入、错误输出及最小修正。

## 资料与下一步

- [原始学习资料](https://github.com/xiaoyu0513-cell/AI_Learning_Data/tree/main/ai_coding/LangChain_%E5%B0%9A%E7%A1%85%E8%B0%B7)
- [JavaScript Models](https://docs.langchain.com/oss/javascript/langchain/models)
- [ChatOpenAI 集成](https://docs.langchain.com/oss/javascript/integrations/chat/openai)
- [JavaScript Messages](https://docs.langchain.com/oss/javascript/langchain/messages)

[上一课：概览](01-overview.md) · [返回课程首页](../README.md) · [下一课：LangSmith](03-langsmith.md)
