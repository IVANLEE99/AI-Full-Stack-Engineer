# 第 05 课：工具——用 Zod 和 ToolMessage 连接模型与业务函数

> 完成本课后，你能定义一个只读订单工具，解释模型请求与实际执行的区别，并手动完成一次有上限的工具调用循环。

[上一课：消息与 Prompt](04-messages-prompts.md) · [课程首页](../README.md) · [下一课：结构化输出](06-structured-output.md)

**前置知识**：你理解消息角色、Promise 和运行时输入校验，已完成[第 04 课](04-messages-prompts.md)。
本课使用 `langchain` 1.6.7 的 `tool`、`FakeToolCallingModel`，以及 Zod 4 schema。
默认读取匿名本地 fixture；不查询真实订单，不开放 shell、任意文件访问或写业务数据的工具。

## 1. 目标

- [ ] 用 `name`、`description`、`schema` 和执行函数定义工具。
- [ ] 用 Zod 拒绝不合法参数，而不是只依赖 TypeScript。
- [ ] 区分 `bindTools()`、模型返回 `tool_calls` 与真正执行函数。
- [ ] 保留原始 AIMessage，并用 `tool_call_id` 回传结果。
- [ ] 为循环、权限、异常和副作用设置明确边界。

## 2. 概念：工具调用是一份请求，不是执行权限

### 2.1 一个工具包含两类契约

| 部分 | 给谁看 | 解决什么问题 |
| --- | --- | --- |
| `name` | 模型与调度代码 | 用哪个稳定标识选择工具 |
| `description` | 模型与开发者 | 什么时候用、有什么限制 |
| `schema` | 模型适配器与运行时 | 参数字段、类型和约束 |
| 执行函数 | 你的服务端程序 | 通过允许的业务能力获取结果 |

模型通常接收的是工具描述与参数结构，不是你函数的源码。
它可以提出一个调用请求；你的程序验证并执行，业务系统仍然决定是否允许。
工具描述写“只读”有助于模型选择，但真正的只读必须由实现和权限保证。

### 2.2 TypeScript 与 Zod 的职责不同

TypeScript 帮你在开发期检查自己写的代码，但类型在运行时不会自动拦住模型返回的数据。
你不能用 `as OrderInput` 把未知参数“变安全”。

| 边界 | 你怎么检查 | 失败时怎么办 |
| --- | --- | --- |
| 外部工具参数 | Zod `parse` / `safeParse` | 拒绝执行，返回安全错误 |
| 工具名 | 精确白名单 | 不动态导入、不中转给 shell |
| 用户与资源权限 | 服务端认证上下文与业务授权 | 拒绝访问，不让模型代替用户选择身份 |
| 工具输出 | 最小字段、输出约束 | 不把完整记录、秘密或异常对象送回模型 |
| 写操作 | 幂等、审批、事务、审计 | 不因模型重试而重复扣款或修改状态 |

本课的 `OrderInputSchema` 限制 `orderId` 为 `demo-` 加三位数字。
这只是匿名练习数据格式，不是生产授权规则。
生产订单查询要使用当前用户的认证上下文，并调用既有服务封装。

### 2.3 三个动作要分开理解

| 动作 | 发生什么 | 没发生什么 |
| --- | --- | --- |
| `tool.invoke(args)` | 直接校验并运行本地工具 | 没有让模型决定工具 |
| `model.bindTools([tool])` | 返回带工具描述的模型 Runnable | 没执行工具函数 |
| `bound.invoke(messages)` | 返回可能带 `tool_calls` 的 AIMessage | 普通模型接口不会自动运行你的函数 |

真实模型可能不调用工具，也可能一轮请求多个工具，或给出不可用参数。
并非所有服务都支持强制调用指定工具；本课不靠 `tool_choice` 兼容性建立保证。
fake 模型则按预设脚本提出请求，不会自主分析问题。

### 2.4 手动循环的数据流

```text
消息历史 -> 绑定工具的模型 -> AIMessage
                              |
                         有 tool_calls？
                         否 -> 最终回复
                         是 -> 白名单/参数/权限检查 -> 工具函数
                               -> ToolMessage -> 放回历史 -> 下一轮
```

你先把模型返回的完整 AIMessage 放进历史，再追加各工具结果。
`ToolMessage.tool_call_id` 必须对应本次请求的 `tool_calls[i].id`。
你只放工具结果而丢掉请求消息，会破坏协议；你随意生成一个新 id 也无法建立正确关联。

本课采用 fail-fast 处理缺失 id；未知工具与无效参数返回简短、安全的错误内容。
你以后还要决定超时、业务失败、工具抛错时是否允许模型重试，以及重试次数。

## 3. 源码/示例阅读：从直接调用到一轮工具闭环

### 3.1 运行默认离线示例

```bash
npm run example:tool
```

你会阅读直接调用、参数拒绝、预设工具请求和 ToolMessage 回传四条路径。
这些是协议与边界练习，不是模型正确选择工具的能力测试。

| 文件 | 重点 |
| --- | --- |
| [工具示例](../examples/05-tool-schema.ts) | 最多三次模型调用的手动循环 |
| [工具定义与执行器](../src/tools.ts) | `OrderInputSchema`、白名单、id 关联与匿名 fixture |
| [模型工厂](../src/models.ts) | 离线 `FakeToolCallingModel` 与 live 模型的分支 |

fixture 中 `demo-001` 为“已发货”，`demo-002` 为“待发货”。
它们是本地编造的练习数据，不能当真实订单或真实配送承诺。

### 3.2 定义一个独立的只读工具

```typescript
import { tool } from "langchain";
import { z } from "zod";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
const OrderInputSchema = z.object({
  orderId: z.string().regex(/^demo-\d{3}$/),
}).strict();
const statuses: Record<string, string> = {
  "demo-001": "已发货",
  "demo-002": "待发货",
};
const lookupOrder = tool(({ orderId }) => JSON.stringify({
  orderId,
  status: statuses[orderId] ?? "匿名练习数据中未找到",
}), {
  name: "lookup_order",
  description: "只读查询匿名练习订单的配送状态，不查询个人信息，不修改订单。",
  schema: OrderInputSchema,
});
console.log(await lookupOrder.invoke({ orderId: "demo-001" }));
```

这里直接传参数对象，所以结果是执行函数返回的字符串。
完整工程使用 [src/tools.ts](../src/tools.ts) 中共享的 `lookupOrderTool`，还包含 `found` 等结果字段。
片段用于说明定义方式，不是让你替换共享 fixture 的实现。

按此版本的工具契约，把带 `type: "tool_call"` 和 `id` 的完整请求交给 `tool.invoke()` 时，框架可以自动包装执行结果。不要再包装一次已有的 ToolMessage。
本课改为传入已校验的 `args`，再显式构造 ToolMessage，方便你观察请求 ID 如何关联结果。

### 3.3 预设请求、执行、回传：一个最小闭环

下面片段在已有共享工具上演示一次闭环。
你把它作为 `examples/` 内的 TypeScript 练习时，相对导入用 `.js`；`tsx` 会加载对应源码。

```typescript
import { type BaseMessage, HumanMessage, ToolMessage } from "@langchain/core/messages";
import { FakeToolCallingModel } from "langchain";
import { configureRuntime } from "../src/runtime.js";
import { lookupOrderTool, OrderInputSchema } from "../src/tools.js";

configureRuntime([]);
const model = new FakeToolCallingModel({ toolCalls: [[{
  id: "call-demo-1", name: "lookup_order", args: { orderId: "demo-001" },
}], []] });
const bound = model.bindTools([lookupOrderTool]);
const messages: BaseMessage[] = [new HumanMessage("查询 demo-001 的配送状态。")];
const request = await bound.invoke(messages);
messages.push(request);
for (const call of request.tool_calls ?? []) {
  if (!call.id || call.name !== lookupOrderTool.name) throw new Error("拒绝未知调用。");
  const args = OrderInputSchema.parse(call.args);
  const content = await lookupOrderTool.invoke(args);
  messages.push(new ToolMessage({ content, name: call.name, tool_call_id: call.id }));
}
const finalReply = await bound.invoke(messages);
console.log(finalReply.type, finalReply.tool_calls?.length ?? 0);
```

这个片段只演示一轮，未加入通用错误恢复与重复循环。
共享示例才通过 `executeOrderCalls()` 统一处理未知工具和坏参数，并设置调用上限。
`FakeToolCallingModel` 的最终文字可能只是拼接历史；不要把它当作自然语言质量证据。

### 3.4 在完整循环里检查什么

| 检查点 | 你期望的行为 | 失败处理 |
| --- | --- | --- |
| 模型没有 tool_calls | 返回最终消息 | 不再空转 |
| 工具名未知 | 不执行任何未知函数 | 返回 `UNKNOWN_TOOL` 的安全内容 |
| orderId 结构错误 | 不读 fixture | 返回 `BAD_ARGS` 的安全内容 |
| tool call 缺 id | 不伪造关联 | 终止并报告协议问题 |
| 达到三次模型调用 | 不再调用模型 | 明确报错而非无限循环 |
| 单次等待过长 | 用取消信号限制等待 | 不承诺供应商已停止计费 |

三次是本练习的预算，不是生产推荐的固定数值。
单独限制模型轮数还不等于完整成本限制；一轮可能提出多个工具请求。
后续中间件课程会进一步区分模型调用数、工具调用数与其他预算。

### 3.5 可选真实联调与安全边界

你自行准备 `.env` 后，可用 `npm run example:tool -- --live` 观察所选模型是否提出工具请求。
这会发送匿名消息和工具描述到供应商，可能计费；本课不声称已执行真实联调。
工具本身仍是本地 fixture，不会变成真实订单 API；追踪仍默认关闭。

不要为“让 Agent 更能干”加入任意命令执行、任意 URL 抓取或无限文件读取工具。
更不要让模型通过工具参数提交 `userId` 就直接获得该用户权限。
生产工具优先暴露一个明确、最小、可审计的能力，而不是整个基础设施入口。

## 4. 练习：从参数到协议再到安全

### 练习 A：基础——确认参数拒绝发生在执行前

用 `safeParse` 分别检查 `demo-001`、`demo-abc`、数字 orderId 和额外字段。
**验收**：只有符合 strict schema 的参数通过；你不调用付费模型验证这些确定性规则。

### 练习 B：进阶——检查工具请求与结果配对

给本地 fake 脚本增加第二个只读请求，记录每个 id 与对应 ToolMessage。
**验收**：保留原始 AIMessage，每个请求恰好匹配一个结果，未知工具不执行。

### 练习 C：挑战——为未来写工具设置准入条件

只设计一个“申请退款”的边界清单，不实现真实退款接口。
**验收**：包含授权、金额高精度处理、幂等、人工确认、审计与预算；模型不能直接提交支付副作用。

## 5. 自测/复盘：五个执行边界问题

1. **`bindTools()` 会运行工具函数吗？**
   简答：不会；它给模型绑定描述，执行由你的循环或 Agent 运行时负责。
2. **Zod schema 通过是否表示有权限查询订单？**
   简答：不是；它验证数据形状，授权需要认证上下文与业务规则。
3. **为什么必须保留提出 tool_calls 的 AIMessage？**
   简答：它是工具结果关联的原始请求，丢失会破坏消息协议。
4. **`ToolMessage.tool_call_id` 能用一个新的随机 id 吗？**
   简答：不能；它要匹配对应请求的 id，而不是重新命名请求。
5. **fake 工具闭环成功能证明模型会正确选工具吗？**
   简答：不能；fake 请求按脚本预设，只验证执行、校验和回传路径。

**复盘句**：模型提出请求，你的程序验证、授权、执行，并把最小结果按协议回传。

## 6. AI Review 提示词

> 请审查我的工具实现与手动循环，不建议增加任意 shell 或文件工具。
> 检查工具白名单、Zod 校验、认证上下文、ToolMessage id、循环上限和错误内容。
> 区分工具描述中的承诺与代码真正执行的限制。
> 用一个恶意或不合法工具请求说明每条边界是否生效，不运行真实业务副作用。

## 资料与下一步

- [原始学习资料](https://github.com/xiaoyu0513-cell/AI_Learning_Data/tree/main/ai_coding/LangChain_%E5%B0%9A%E7%A1%85%E8%B0%B7)
- [JavaScript Tools](https://docs.langchain.com/oss/javascript/langchain/tools)
- [模型工具调用](https://docs.langchain.com/oss/javascript/langchain/models)
- [Zod 基础](https://zod.dev/basics)

[上一课：消息与 Prompt](04-messages-prompts.md) · [返回课程首页](../README.md) · [下一课：结构化输出](06-structured-output.md)
