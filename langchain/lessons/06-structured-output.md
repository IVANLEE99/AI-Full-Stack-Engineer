# 第 06 课：结构化输出——从一段回复到可验证的业务对象

> 完成本课后，你能用 Zod 定义工单契约，区分 TypeScript、JSON 解析与运行时校验，并选择模型级或 Agent 级结构化输出。

[上一课：工具](05-tools.md) · [课程首页](../README.md) · [下一课：Agent](07-agents.md)

**前置知识**：你能读懂 Zod 工具 schema，知道工具请求需要验证后再执行。
本课对应 `langchain` 1.6.7、`@langchain/core` 1.5.0、`@langchain/openai` 1.8.5。
默认示例使用固定 JSON 回复替身；供应商原生约束与真实模型质量不属于离线验证范围。

## 1. 目标

- [ ] 区分 TS 类型、JSON 语法、schema 合法性与事实正确性。
- [ ] 用同一份 Zod schema 推导类型并验证运行时数据。
- [ ] 使用模型级 `withStructuredOutput()`，并明确 fake 的局限。
- [ ] 理解 Agent 的 `responseFormat` 与 `structuredResponse`。
- [ ] 为非法数据、拒答、缺少事实与重试建立失败策略。

## 2. 概念：你至少要跨过四道检查

### 2.1 “有类型”不是“已验证”

| 检查层 | 它能证明什么 | 仍然不能证明什么 |
| --- | --- | --- |
| TypeScript 类型 | 开发期代码符合声明的约束 | 外部字符串真的包含这些字段 |
| `JSON.parse()` | 字符串符合 JSON 语法 | 字段名称、枚举与范围正确 |
| Zod `parse()` / `safeParse()` | 运行时数据满足 schema | 结论有证据、分类符合业务事实 |
| 业务验证/评测 | 来源、权限、业务规则符合要求 | 所有未来输入永远正确 |

`JSON.parse()` 后写一个 `as Ticket` 只是告诉编译器相信你，不会产生新的运行时检查。
你把未知结果标为 `unknown`，通过 schema 验证后再进入业务逻辑，更容易维持边界。

### 2.2 用工单对象建立一个具体契约

本课沿用 [src/structured.ts](../src/structured.ts) 的 `TicketSchema`。

| 字段 | 类型/约束 | 业务含义 |
| --- | --- | --- |
| `category` | `order` / `refund` / `other` | 咨询分类，不是订单业务状态 |
| `summary` | 非空字符串，JavaScript `length` 不超过 200 | 简短中文摘要，不应包含个人信息 |
| `requiresHuman` | boolean | 是否需要人工复核或处理 |
| 其他字段 | strict schema 拒绝 | 避免悄悄接受未经约定的扩展 |

`describe("不包含个人信息")` 是语义说明，不是自动脱敏规则。
Zod 的字符串长度检查也不等于按人类读到的字数计数；复杂 Unicode 字符需要单独处理。
你若要可靠禁止个人信息，还需输入最小化、专门检查及必要的人工审核。

### 2.3 模型级与 Agent 级结构化输出

| 方式 | 配置入口 | 调用结果在哪 | 适用场景 |
| --- | --- | --- | --- |
| 模型级 | `model.withStructuredOutput(schema)` | 通常直接返回解析后的对象 | 一次提取、分类、固定字段回答 |
| 模型级诊断变体 | 支持时用 `includeRaw: true` | `{ raw, parsed }` 等约定 | 需要受控检查原始消息与解析结果 |
| Agent 级 | `createAgent({ responseFormat: schema, ... })` | 返回状态里的 `structuredResponse` | 使用工具后交付结构化最终结果 |

JavaScript 字段是 `responseFormat`、`structuredResponse`、`systemPrompt`。
不要照搬 Python 示例里的 `response_format`、`structured_response`、`system_prompt`。
注意供应商底层请求可能有蛇形字段，但它们不是 `createAgent()` 的配置入口。

你不必为了结构化输出先创建 Agent。
如果只需要从一段文本提取三个字段，模型级调用通常更简单。
Agent 级输出解决的是“完成可能包含工具循环的任务后，最后交付什么”。

### 2.4 原生约束与工具策略不是一回事

| 策略 | 大致机制 | 你需要核对什么 |
| --- | --- | --- |
| 供应商原生策略 | 使用模型服务支持的结构化响应能力 | 模型、端点、JSON Schema 子集与拒答行为 |
| 工具策略 | 用一个格式工具的参数承载最终对象 | 工具调用能力、解析错误处理与调用预算 |
| 本地显式验证 | 对返回值执行 Zod parse | schema 是否覆盖真实业务要求 |

Agent 可按模型能力处理裸 schema，也能用 `providerStrategy()` 或 `toolStrategy()` 显式选择。
你不能因此推断所有模型都支持原生结构化输出，或工具与结构化输出可任意组合。
兼容服务的支持范围尤其需要真实联调，不能从类名和类型检查直接推断。

原生约束减少格式错误，但不让“已发货”变成已核验事实。
模型拒答、网络中断、输出截断和校验失败仍需要独立处理。

## 3. 源码/示例阅读：先验证确定性，再讨论模型能力

### 3.1 运行默认离线示例

```bash
npm run example:structured
```

完整示例以固定 JSON 回复模拟模型输出，经过 `withStructuredOutput()` 后再次执行 `TicketSchema.parse()`。
真实模式显式选择 `functionCalling` 方法，因此需要所选模型支持工具调用；fake 不验证这项能力。
默认离线路径不调用供应商、不上传追踪；它不能证明真实模型会按相同结构回答。

| 文件 | 你重点阅读什么 |
| --- | --- |
| [结构化输出示例](../examples/06-structured-output.ts) | 模型封装 -> 返回值 -> 显式 Zod 验证 |
| [共享 schema](../src/structured.ts) | 三个字段、strict、类型推导与语义限制 |
| [模型工厂](../src/models.ts) | 固定 JSON 与真实模型的分支 |
| [下一课 Agent 示例](../examples/07-agent.ts) | 工具循环与最终状态，为后续做准备 |

### 3.2 一份 schema，同时服务开发期与运行时

```typescript
import { z } from "zod";

const TicketSchema = z.object({
  category: z.enum(["order", "refund", "other"]),
  summary: z.string().min(1).max(200).describe("中文工单摘要，不包含个人信息"),
  requiresHuman: z.boolean(),
}).strict();
type Ticket = z.infer<typeof TicketSchema>;
const incoming: unknown = JSON.parse('{"category":"order","summary":"匿名配送咨询","requiresHuman":false}');
const result = TicketSchema.safeParse(incoming);
if (!result.success) throw new Error("工单结构不合法。");
const ticket: Ticket = result.data;
console.log(ticket.category, ticket.requiresHuman);
console.log(TicketSchema.safeParse({ category: "unknown" }).success);
```

`z.infer` 避免你维护两份容易不一致的字段声明。
`safeParse()` 返回成功/失败分支，适合接口返回可控的验证错误。
`parse()` 失败会抛错，适合在明确的错误边界中尽快中止。

### 3.3 模型级：fake 也要经过显式校验

下面片段按位于 `examples/` 的练习文件组织，相对导入使用 NodeNext 的 `.js` 写法。
它使用项目真实共享 schema，不猜测辅助函数的字段。

```typescript
import { FakeListChatModel } from "@langchain/core/utils/testing";
import { configureRuntime } from "../src/runtime.js";
import { TicketSchema } from "../src/structured.js";

configureRuntime([]);
const model = new FakeListChatModel({ responses: [JSON.stringify({
  category: "order",
  summary: "咨询匿名练习订单配送状态",
  requiresHuman: false,
})] });
const structuredModel = model.withStructuredOutput(TicketSchema, { method: "functionCalling" });
const rawValue: unknown = await structuredModel.invoke("把 demo-001 的配送咨询整理成工单。");
const ticket = TicketSchema.parse(rawValue);
console.log(ticket);
```

**本版本 fake 的关键限制**：`FakeListChatModel.withStructuredOutput()` 主要对固定文本执行 `JSON.parse()`。
它不会按传入的 schema 验证字段，也不会因为 `.describe()` 就理解分类规则。
因此你保留显式 `TicketSchema.parse()`，用它证明运行时边界，而不假装验证了供应商约束。

这个 fake 实现也不能用来验证 `includeRaw` 的真实行为。
你需要原始消息诊断时，先检查实际模型集成契约；不要为了配合教程把 fake 返回值强转成 `{ raw, parsed }`。
即使真实集成支持 includeRaw，也不要把 raw 原文直接写入公开日志。

### 3.4 Agent 级：最终对象在 structuredResponse

下面仍为离线演示，用官方 fake 工具模型预设一次格式工具请求。
你不手写框架内部的格式工具名，而从已创建的策略读取该名称。

```typescript
import { createAgent, FakeToolCallingModel, toolStrategy } from "langchain";
import { configureRuntime } from "../src/runtime.js";
import { TicketSchema } from "../src/structured.js";

configureRuntime([]);
const formats = toolStrategy(TicketSchema, { handleError: false });
const format = formats[0];
if (!format) throw new Error("没有可用的格式策略。");
const model = new FakeToolCallingModel({ toolCalls: [[{
  id: "ticket-call-1",
  name: format.name,
  args: { category: "order", summary: "匿名订单配送咨询", requiresHuman: false },
}]] });
const agent = createAgent({
  model,
  tools: [],
  systemPrompt: "你把匿名咨询整理成工单。",
  responseFormat: formats,
});
const state = await agent.invoke({ messages: [{ role: "user", content: "查询 demo-001。" }] });
console.log(TicketSchema.parse(state.structuredResponse));
```

这个 Agent 没有业务工具，只有结构化输出策略使用的格式工具。
它演示 JavaScript 的状态字段，而不是建议你用 Agent 替代简单分类函数。
`handleError: false` 让格式错误立即失败，避免本地练习进入难解释的自动修复循环。

真实模型是否能同时可靠执行业务工具和最终结构化输出，需要按能力与预算单独验收。
下一课再拆 Agent 的模型、工具、消息历史与终止条件。

### 3.5 建立失败分类，不用一句“格式不对”概括一切

| 失败 | 示例 | 你怎么处理 |
| --- | --- | --- |
| JSON 语法错误 | 被截断、额外自然语言 | 拒绝进入业务逻辑，记录安全类别 |
| schema 错误 | `requiresHuman: "false"` | 校验失败，不用类型断言掩盖 |
| 语义错误 | 摘要包含原文没有的配送承诺 | 核对输入/证据，标记人工复核 |
| 模型拒答或能力不支持 | 服务未产生预期结构 | 按模型契约识别，不强造默认对象 |
| 外部服务失败 | 超时、配额、网络错误 | 有限重试或降级，不泄露原始请求 |

不建议无条件把字符串 `"false"` 强制转换成布尔值。
例如常规 JavaScript 布尔转换里，非空字符串 `"false"` 的结果仍为 true。
你先决定是否允许转换；本课坚持严格输入，不默默修复来自模型的错误类型。

### 3.6 可选真实联调

你自行按 `env.example` 准备 `.env` 后，可以选择 `npm run example:structured -- --live`。
这可能计费，需要检查模型/端点的结构化输出支持；本课不声称已实际测试供应商响应。
默认仍不上传 LangSmith，除非你另行显式选择允许追踪的参数。
离线验证覆盖解析和 schema，不覆盖真实模型准确率、拒答形式或服务可用性。

## 4. 练习：从合法 JSON 到可信业务结果

### 练习 A：基础——构造四类验证样本

分别验证合法工单、未知 category、字符串布尔值和包含额外字段的对象。
**验收**：strict schema 只放行约定的数据；你能区分 JSON 解析失败和 Zod 校验失败。

### 练习 B：进阶——让固定输出故意出错

把 fake 回复中的 `summary` 设为空字符串或超过 200 的长度。
**验收**：`withStructuredOutput()` 可能仍返回 JSON 对象，但后续显式 parse 会拒绝。

### 练习 C：挑战——增加一条非格式的业务检查

给工单增加“摘要不得声称已完成真实退款”的业务审核方案，不实现真实退款。
**验收**：你说明 schema 合法但语义错误的反例，并定义转人工和安全降级策略。

## 5. 自测/复盘：五个可信度问题

1. **`as Ticket` 会在运行时验证模型输出吗？**
   简答：不会；类型断言不产生校验逻辑，外部数据需要 schema parse。
2. **JSON 语法正确，为什么还要 Zod？**
   简答：JSON 不限制你的枚举、长度、必填字段、布尔类型或额外字段。
3. **模型级结果与 Agent 级结果从哪里读取？**
   简答：模型封装通常直接返回对象；Agent 最终对象位于 `structuredResponse`。
4. **为什么 fake 结构化输出后还要显式 parse？**
   简答：本版本 fake 主要解析 JSON，不执行传入 schema 的完整运行时验证。
5. **schema 通过能证明配送状态或退款事实吗？**
   简答：不能；事实需要可信数据源、证据和业务规则验证。

**复盘句**：你把模型结果当未知数据，依次验证语法、结构和业务意义，再让它进入应用。

## 6. AI Review 提示词

> 请审查我的结构化输出边界，分别检查 TypeScript、JSON 解析、Zod 和业务验证。
> 找出类型断言、无条件默认值、字符串布尔转换和无限修复重试的问题。
> 核对 JS 字段 responseFormat/structuredResponse/systemPrompt，不照搬 Python 字段。
> 区分 fake 协议证据和真实供应商能力，对无法离线证明的部分明确标注。

## 资料与下一步

- [原始学习资料](https://github.com/xiaoyu0513-cell/AI_Learning_Data/tree/main/ai_coding/LangChain_%E5%B0%9A%E7%A1%85%E8%B0%B7)
- [JavaScript Structured output](https://docs.langchain.com/oss/javascript/langchain/structured-output)
- [ChatOpenAI 集成](https://docs.langchain.com/oss/javascript/integrations/chat/openai)
- [Zod 基础](https://zod.dev/basics)

[上一课：工具](05-tools.md) · [返回课程首页](../README.md) · [下一课：Agent](07-agents.md)
