# 第 04 课：消息、Prompt 与 Runnable——让数据流看得见

> 完成本课后，你能把业务输入转换为带角色的消息，显式插入历史，再用 `.pipe()` 串起 Prompt、模型和文本解析器。

[上一课：LangSmith](03-langsmith.md) · [课程首页](../README.md) · [下一课：工具](05-tools.md)

**前置知识**：你理解模型调用返回消息，而不是总返回字符串；已完成[第 02 课](02-models.md)。
本课使用 `@langchain/core` 1.5.0 的 Prompt、消息和 Runnable API，默认只运行固定回复替身。
你在 `langchain/` 目录使用项目锁定的依赖，不从 Python 示例照搬导入与字段名。

## 1. 目标

- [ ] 区分系统指令、用户输入、助手回复与工具结果。
- [ ] 用 `ChatPromptTemplate` 格式化变量与角色。
- [ ] 用 `MessagesPlaceholder` 插入真正的消息历史。
- [ ] 解释 `prompt.pipe(model).pipe(parser)` 每一步的输入输出。
- [ ] 知道模板、历史和解析器各自不负责什么。

## 2. 概念：Prompt 不是简单的字符串拼接

### 2.1 消息角色表达来源，不代替安全策略

| 语义角色 | 常见 JS 类/角色 | 你放什么 |
| --- | --- | --- |
| 系统指令 | `SystemMessage` / `system` | 任务范围、回答约束、语言 |
| 用户输入 | `HumanMessage` / `user` | 本次问题、需要处理的数据 |
| 助手回复 | `AIMessage` / `assistant` | 模型返回的内容与工具调用 |
| 工具结果 | `ToolMessage` / `tool` | 对应某次工具调用的执行结果 |

LangChain 类的 `type` 与供应商 API 的 `role` 名称不必完全相同。
例如你在标准消息对象中使用 `role: "user"`，常见类名却是 `HumanMessage`。
你优先使用框架支持的消息形式，不自己发明混合字段。

你不要把不可信的网页片段或用户指令提升为系统消息。
但即使正确区分角色，也不能保证模型不受提示注入影响；真正的授权仍应在工具和服务端执行。

### 2.2 模板负责组装，不负责训练

`ChatPromptTemplate` 把一份有变量的声明转换成消息序列。
你可以在调用模型前检查格式化结果，定位缺变量、顺序不对或历史拼接错误。

| 对象/方法 | 输入 | 输出 | 是否调用模型 |
| --- | --- | --- | --- |
| `ChatPromptTemplate.fromMessages(...)` | 消息模板声明 | Prompt 模板对象 | 否 |
| `prompt.formatMessages(values)` | 变量对象 | 消息数组 | 否 |
| `prompt.invoke(values)` | 变量对象 | `ChatPromptValue` | 否 |
| `model.invoke(promptValue)` | 消息值 | `AIMessage` | 是；fake 则为本地替身 |
| `StringOutputParser.invoke(message)` | 消息/模型文本输出 | 文本 | 否 |

把模板类比为服务端的“请求构建器”比较合适。
它改善结构与可维护性，不让模型学会新知识，也不自动存储上一次调用。

### 2.3 MessagesPlaceholder 不是 `{history}` 字符串

普通 `{question}` 变量通常填入文本。
`MessagesPlaceholder("history")` 展开的是一组消息，让每条消息保留角色与顺序。

你把历史 JSON 字符串塞进用户消息，模型也许读得懂，但程序已经丢失了可检查的消息边界。
历史包含工具调用时，这种丢失尤其容易破坏调用与结果的对应关系。

| 历史策略 | 你传入什么 | 适用情况 |
| --- | --- | --- |
| 必填占位符 | 每次传 `history`，首次可传 `[]` | 希望缺失时尽早报错 |
| 可选占位符 | `new MessagesPlaceholder({ variableName: "history", optional: true })` | 允许首次调用省略历史 |
| 应用自行裁剪 | 经过筛选的消息数组 | 控制上下文长度与数据暴露 |

占位符不等于记忆数据库。
历史由你传入；后续课程才使用 thread/checkpointer 管理跨调用状态。

### 2.4 Runnable 与 LCEL：共同接口，不是新的业务数据类型

Runnable 给可组合的组件提供 `invoke`、`batch`、`stream` 等接口。
LCEL（LangChain Expression Language）是这种组合方式的名称；在 JavaScript 中，你使用 `.pipe()` 连接步骤。
不要把 Python 的 `prompt | model | parser` 原样放进 JS，`|` 在这里不是管道运算符。

```text
{ question, history }
  -> Prompt -> ChatPromptValue
  -> Chat Model -> AIMessage
  -> StringOutputParser -> string
```

你构造 `.pipe()` 链时还没有调用模型，最终 `invoke()` 才启动执行。
每一层都有自己的输入输出；“都能 invoke”不代表它们接受相同参数。

| Node.js 类比 | 相似处 | 边界 |
| --- | --- | --- |
| `array.map()` 的转换链 | 每步把输出交给下一步 | Runnable 可能异步、流式、联网 |
| Express middleware | 都适合拆步骤 | Runnable 不依赖 `req/res/next` |
| 构建 HTTP 请求 DTO | 变量映射与校验可独立测试 | 模型输出不是确定的业务真值 |

## 3. 源码/示例阅读：先检查模板，再调用模型

### 3.1 运行本地 Prompt 流水线

```bash
npm run example:prompt
```

这个示例使用 `FakeListChatModel`，不需要密钥，也不上传追踪。
你改变 Prompt 后，固定回复不一定改变；这不是 Prompt 生效的质量验证。
你要看格式化的消息与流水线返回类型是否符合设计。

| 文件 | 你重点阅读的内容 |
| --- | --- |
| [Prompt 流水线示例](../examples/04-prompt-pipeline.ts) | 模板、历史、fake 模型、解析器的连接 |
| [模型工厂](../src/models.ts) | fake 回复为何不由 Prompt 推理生成 |
| [运行时](../src/runtime.ts) | 默认离线与追踪关闭 |

### 3.2 不调用模型，先验证模板输出

```typescript
import { ChatPromptTemplate } from "@langchain/core/prompts";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
const prompt = ChatPromptTemplate.fromMessages([
  ["system", "你面向熟悉 Node.js 的开发者，用中文解释概念。"],
  ["human", "请解释 {topic}，并给出一个 {framework} 类比。"],
]);
const messages = await prompt.formatMessages({
  topic: "检索增强生成",
  framework: "Nuxt 服务端",
});
console.log(messages.map((message) => ({
  type: message.type,
  content: message.content,
})));
```

输入字段是 `topic` 和 `framework`，输出是两条消息。
你不用申请密钥，就能测试变量是否替换、角色是否保留，以及是否误放了敏感数据。
缺失变量应在模板阶段暴露，而不是等到模型答非所问才发现。

### 3.3 把带角色的历史放进流水线

```typescript
import { AIMessage, HumanMessage } from "@langchain/core/messages";
import { StringOutputParser } from "@langchain/core/output_parsers";
import { ChatPromptTemplate, MessagesPlaceholder } from "@langchain/core/prompts";
import { FakeListChatModel } from "@langchain/core/utils/testing";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
const prompt = ChatPromptTemplate.fromMessages([
  ["system", "你用简短中文解释技术概念。"],
  new MessagesPlaceholder("history"),
  ["human", "{question}"],
]);
const model = new FakeListChatModel({ responses: ["这是固定的流水线练习回复。"] });
const chain = prompt.pipe(model).pipe(new StringOutputParser());
const answer = await chain.invoke({
  history: [new HumanMessage("我熟悉 Node.js。"), new AIMessage("收到。")],
  question: "Runnable 可以类比为什么？",
});
console.log(typeof answer, answer);
```

顺序是系统消息、两条历史消息、本次问题。
解析器把文本输出交给调用方，不保留完整 `AIMessage` 的工具调用或元数据。
你需要使用量、原始消息或工具请求时，不要太早把消息压缩为字符串。

### 3.4 业务字段与模板字段可以显式映射

你现有接口可能接收 `{ query }`，而模板使用 `{ question }`。
不要靠模型猜字段，也不要因为命名不同就重写所有业务接口。

```typescript
import { RunnableLambda } from "@langchain/core/runnables";
import { ChatPromptTemplate } from "@langchain/core/prompts";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
const prompt = ChatPromptTemplate.fromMessages([
  ["human", "请解释：{question}"],
]);
const normalize = RunnableLambda.from((input: { query: string }) => {
  const question = input.query.trim();
  if (!question) throw new Error("query 不能为空。");
  return { question };
});
const prepared = await normalize.pipe(prompt).invoke({ query: "  工具调用是什么？  " });
console.log(prepared.toChatMessages().map((message) => message.content));
```

这个链在 Prompt 处结束，没有模型调用。
你在数据边界做可确定的转换，把自然语言任务留给后面的模型层。
如果输入来自 HTTP 请求，`{ query: string }` 只是编译期约定；你仍需运行时校验外部数据。

### 3.5 为什么有时要用普通 async 函数

`.pipe()` 不是必须使用的语法目标。
你要并行读取业务上下文、处理复杂异常或执行权限检查时，普通 `async` 函数可能更清晰。
保持每一步的契约比把所有逻辑塞进一条表达式更重要。

你也不要认为整个 chain 的 `stream()` 必然逐 token 输出。
流的行为取决于每个组件是否支持增量转换；缓冲或等待完整结果的步骤会改变输出时机。
本教程在模型流和后续图状态流之间保持区分，不把所有流都称为同一种事件协议。

### 3.6 常见坑与检查位置

| 坑 | 发生在哪 | 最小修正 |
| --- | --- | --- |
| 把 `{history}` 当消息历史 | 模板构建 | 使用 MessagesPlaceholder 并传消息数组 |
| 省略必填 history | 调用模板 | 首次传 `[]`，或明确声明 optional |
| 模板包含字面量 JSON 花括号 | 模板解析 | 默认 f-string 风格中用 `{{`、`}}` 表示字面量括号 |
| 用 Python 的 `|` 拼链 | JS 表达式 | 改用 `.pipe()` |
| 一开始就转成字符串 | 输出解析 | 保留消息直到工具/元数据处理完 |
| 把历史无限追加 | 上下文组装 | 明确长度、裁剪与敏感数据策略 |
| 把检索片段当系统指令 | 信任边界 | 作为不可信数据，并在工具层实施权限 |

## 4. 练习：按数据契约逐步组合

### 练习 A：基础——只测试模板

给模板增加 `audience` 变量，格式化出消息数组，不调用模型。
**验收**：你检查角色、顺序、变量值，并让缺失变量的测试失败。

### 练习 B：进阶——比较两种历史输入

分别传入 `history: []` 与两轮匿名历史，再比较模板输出。
**验收**：消息数量与顺序正确；你不把 fake 回复是否变化当成历史有效性证据。

### 练习 C：挑战——设计一个业务输入适配层

把 `{ query, recentMessages }` 转换为 `{ question, history }`，限制问题长度和历史条数。
**验收**：校验发生在模型前，空输入可预测地失败，敏感数据不会进入系统消息。

## 5. 自测/复盘：五个数据流问题

1. **`ChatPromptTemplate.invoke()` 会调用模型吗？**
   简答：不会；它产生 Prompt 值，模型调用是下一层的职责。
2. **为什么 history 要用消息数组而不是 JSON 字符串？**
   简答：数组保留角色、顺序和工具关联，更容易验证协议。
3. **构造 `.pipe()` 时会立刻产生模型费用吗？**
   简答：不会；实际执行时才可能触发真实模型请求。
4. **`StringOutputParser` 会验证 JSON Schema 吗？**
   简答：不会；它面向文本输出，结构校验需要单独的 schema 和处理流程。
5. **系统消息写“禁止越权”能取代权限检查吗？**
   简答：不能；模型指令不是确定的安全边界，授权必须由程序执行。

**复盘句**：你让每一步只承担一个清晰的数据转换，缺字段和错类型就在边界暴露。

## 6. AI Review 提示词

> 请按输入 -> 变量映射 -> Prompt -> 模型 -> 解析器逐层审查我的链。
> 每层列出实际输入输出类型，指出角色提升、历史丢失和过早转字符串的问题。
> 不以 fake 回答内容评价 Prompt 质量，不把 TypeScript 类型当作外部输入校验。
> 如果普通 async 函数更清楚，请给出拆分建议而不是堆更多 Runnable。

## 资料与下一步

- [原始学习资料](https://github.com/xiaoyu0513-cell/AI_Learning_Data/tree/main/ai_coding/LangChain_%E5%B0%9A%E7%A1%85%E8%B0%B7)
- [JavaScript Messages](https://docs.langchain.com/oss/javascript/langchain/messages)
- [JavaScript API 参考：查找 ChatPromptTemplate、MessagesPlaceholder 与 RunnableSequence](https://reference.langchain.com/javascript/)
- [JavaScript Models：调用与组合接口](https://docs.langchain.com/oss/javascript/langchain/models)

[上一课：LangSmith](03-langsmith.md) · [返回课程首页](../README.md) · [下一课：工具](05-tools.md)
