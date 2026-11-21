# 第 03 课：LangSmith——先理解调用链，再显式开启追踪

> 完成本课后，你能说明一条追踪包含什么、它会把什么数据发到哪里，并用本地可观测信息排查调用失败。

[上一课：模型调用](02-models.md) · [课程首页](../README.md) · [下一课：消息与 Prompt](04-messages-prompts.md)

**前置知识**：你已运行[模型离线示例](02-models.md)，理解输入消息与 `AIMessage`。
本课使用 LangSmith JavaScript SDK 的概念，默认示例不上传任何 LangSmith 追踪。
真实模型联调、云端追踪和账号配置属于你可选的操作，不是离线验收条件。

## 1. 目标

- [ ] 区分应用日志、一次 run、完整 trace 与评测。
- [ ] 沿着输入、Prompt、模型、解析器定位故障。
- [ ] 理解追踪可能包含消息、工具参数、结果与元数据。
- [ ] 保持默认离线，只在 `--live --trace` 时选择上传。
- [ ] 为未来接入设计最小数据、脱敏与退出策略。

## 2. 概念：可观测不等于把一切上传

### 2.1 日志、run、trace、评测分别是什么

| 概念 | 你观察什么 | 不能直接得出的结论 |
| --- | --- | --- |
| 应用日志 | 错误类别、请求标识、业务阶段 | 一行日志不能完整重建调用关系 |
| run | 某一步的输入、输出、耗时、状态 | 一次成功不表示整条业务链成功 |
| trace | 一次业务操作关联的父子 run | 能看到链路不表示回答真实 |
| 评测 | 用数据集和规则比较结果质量 | 单条追踪不能替代系统评测 |

把 trace 类比为一次前端请求的分布式调用链比较合适。
父 run 描述“生成回复”，子 run 可能描述 Prompt 格式化、模型调用和输出解析。
工具调用、检索和重试也可以成为你需要检查的阶段。

### 2.2 沿输入与输出排错，而不只盯最终答案

```text
用户输入 -> Prompt 组装 -> 模型调用 -> 输出解析 -> 业务验证 -> 响应
               |             |           |           |
            变量错误       网络/能力     格式错误     事实/权限
```

你看到“输出为空”时，不要立刻改 Prompt。
可能是只提取文本而丢掉了工具调用，也可能是解析器预期对象但拿到了字符串。
你需要先知道最后一个成功阶段，以及失败阶段实际接收到的形状。

| 故障 | 你优先观察的边界 | 应保留的安全线索 |
| --- | --- | --- |
| 缺少变量 | Prompt 输入 | 字段名、模板版本，不是完整用户数据 |
| 模型拒绝请求 | 供应商边界 | 错误类别、脱敏状态、模型配置版本 |
| JSON 不合法 | 模型 -> 解析器 | 是否完成、解析错误类别 |
| 结构合法但事实错误 | 业务验证 | 证据来源、规则编号、是否人工复核 |
| 任务耗时异常 | 各阶段起止 | 时长、重试次数、调用数 |

### 2.3 追踪本身也是一次数据外发

真实模型调用已经会发送所选消息给模型供应商。
开启 LangSmith 还可能把这些运行数据发送到追踪服务，形成另一条数据处理链。
即使不填真实姓名，文档片段、工具结果和业务编号也可能泄露信息。

| 位置 | 容易夹带的敏感内容 | 本教程的做法 |
| --- | --- | --- |
| 消息输入 | 用户问题、历史、检索原文 | 只使用匿名练习输入 |
| 工具参数 | 用户标识、订单信息、搜索条件 | 本地匿名 fixture、只读白名单 |
| 工具输出 | 个人信息、内部字段 | 最小返回，避免完整数据库记录 |
| metadata/tags | 租户名、内部域名、文件路径 | 仅放课程编号、模式和版本标识 |
| 错误对象 | 请求体、服务地址、凭证片段 | 默认只输出安全分类，不打印原始对象 |

不要假设“日志里没有密钥”就等于“所有追踪内容已脱敏”。
你还要检查回调、嵌套 run、异常文本、附件、保留期限和团队访问权限。

### 2.4 本项目的模式边界

| 执行方式 | 模型 API | LangSmith 上传 | 是否读取 `.env` |
| --- | --- | --- | --- |
| 无额外参数 | 不调用，使用 fake | 关闭 | 不读取 |
| `--live` | 调用，可能计费 | 关闭 | 读取 |
| `--trace` 单独使用 | 拒绝执行 | 不应开始上传 | 在加载配置前拒绝 |
| `--live --trace` | 调用，可能计费 | 显式开启 | 读取 |

[运行时](../src/runtime.ts)在启动时根据有效参数覆盖当前版本识别的四个环境开关：`LANGSMITH_TRACING`、`LANGSMITH_TRACING_V2`、`LANGCHAIN_TRACING` 和 `LANGCHAIN_TRACING_V2`。
不选 `--trace` 时，这四项都设为 `false`；如果只传 `--trace`，则直接报错，不执行任务。
`env.example` 中保留关闭的默认值；你不需要为了学习本课先开通账号。

如果你以后在另一份应用里手动挂载追踪回调，也要检查该回调是否绕过默认开关。
这份教程的运行边界不是对所有第三方插件的网络行为作全局保证。

## 3. 源码/示例阅读：本地证据优先

### 3.1 运行离线追踪主题示例

```bash
npm run example:trace
```

这里的 `trace` 是课程脚本名称，不代表默认启用云端上传。
你通过 fake 模型与本地输出学习调用结构；没有云端记录是符合默认设计的结果。
不要把“没看到上传”单独作为无网络行为的证明，仍应阅读运行时开关。

| 文件 | 你沿哪条路径阅读 |
| --- | --- |
| [LangSmith 示例](../examples/03-langsmith.ts) | 本地模型任务与显式 `traceable()` 包装分支 |
| [运行时](../src/runtime.ts) | 参数校验 -> 加载配置 -> 设置追踪 -> 回调收尾 |
| [模型工厂](../src/models.ts) | 显式 live 才构造真实模型 |
| [环境模板](../env.example) | API key 与 project 的用途，不是可用值 |

### 3.2 不上传也能先观察阶段、耗时和状态

```typescript
import { FakeListChatModel } from "@langchain/core/utils/testing";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
const model = new FakeListChatModel({ responses: ["匿名练习回复"] });
const started = performance.now();
try {
  const reply = await model.invoke("解释追踪的用途。");
  console.log({ stage: "model", ok: true, messageType: reply.type });
} catch (error) {
  console.log({ stage: "model", ok: false, kind: error instanceof Error ? error.name : "UnknownError" });
  process.exitCode = 1;
} finally {
  console.log({ elapsedMs: Math.round(performance.now() - started) });
}
```

这段只记录阶段、状态、消息类型与时长，不输出完整消息和原始异常。
时长由你的本次环境决定，不是模型性能基准。
生产场景还需要安全请求标识，关联业务状态；不要用真实用户数据直接充当标识。

### 3.3 把调用名称与匿名元数据留在配置里

```typescript
import { FakeListChatModel } from "@langchain/core/utils/testing";
import { configureRuntime } from "../src/runtime.js";

configureRuntime([]);
const model = new FakeListChatModel({ responses: ["固定回复"] });
const result = await model.invoke("一个匿名的学习问题。", {
  runName: "lesson-03-model",
  tags: ["tutorial", "offline"],
  metadata: { lesson: "03", promptVersion: "v1" },
});
console.log(result.type);
```

这些配置描述调用，不会因为你设置了 `runName` 就自动上传。
它们也不是 Prompt 内容：你不能假设模型会读到 metadata 中的业务规则。
以后启用追踪时，配置能帮助你定位版本，但不应装入整份业务上下文。

### 3.4 可选：显式开启真实追踪

先把 `env.example` 复制为 `.env`，填写模型配置以及 `LANGSMITH_API_KEY`。
`LANGSMITH_PROJECT` 用于区分项目；它不是认证凭证。
检查所用工作区、目标服务配置、访问范围与数据政策，再选择是否继续。

```bash
npm run example:trace -- --live --trace
```

**这条命令会进行真实模型联调，并选择向 LangSmith 发送运行数据；本课不声称已替你执行。**
你只使用匿名问题，不把真实工单、源码、订单和整篇内部文档拿来试上传。
如果账号、网络、服务地址或模型能力不匹配，需要你按服务文档单独排错。

一次 trace 应能关联到本次调用及其子步骤，但界面、处理时间和元数据可用性可能随服务而变。
脚本退出前会等待追踪回调收尾；这不等于你获得了可靠持久化和无丢失承诺。
没有可用 token 使用量时标记“未知”，不要用字符数伪造费用或使用量。

### 3.5 有选择地隐藏，比默认全量上传更好

LangSmith Client 提供 `hideInputs`、`hideOutputs`、`hideMetadata` 与匿名化处理选项。
你需要结合实际集成方式把配置传给正确的客户端，并检查父子 run 是否都按预期处理。
只构造一个新 Client 但不让追踪器使用它，并不能修改另一个默认客户端的行为。

本教程不默认创建自定义上传通道，也不宣称已完成生产脱敏接入。
你将来接入时，应先用合成数据验证过滤结果，再考虑有限采样和保留期限。
隐藏输入输出后，metadata、错误、附件和运行时信息仍需要单独审查。

### 3.6 常见误区

- 为了学习概念，把全局追踪永久设为 `true`。
- 把“真实模型调用成功”误认为“云端追踪也成功”。
- 把“能看到 trace”误认为“回答已经通过事实核验”。
- 日志只打印“失败”，却没有阶段和安全错误类别。
- 排错时复制完整 SDK 异常到公开问题中。
- 删除 `.env` 后以为终端里继承的变量也会随之消失。

## 4. 练习：从本地排错到上传审查

### 练习 A：基础——做一份无敏感信息的阶段记录

运行离线示例，记录一次调用的模式、阶段、状态和输出类型。
**验收**：记录不含输入原文、密钥或原始异常；你明确它不是云端追踪证据。

### 练习 B：进阶——设计一次可定位的故障

让本地 Prompt 缺少变量，区分“模板失败”和“模型失败”，不启用真实 API。
**验收**：你指出失败发生在调用链哪一层，并保留字段名而非完整业务数据。

### 练习 C：挑战——写追踪准入清单

为一个假想知识助手设计“允许上传/必须删除/需要授权”三类字段清单。
**验收**：覆盖输入、工具结果、metadata、错误、访问权限与保留期限；不实际上传。

## 5. 自测/复盘：五个隐私与证据问题

1. **`npm run example:trace` 会默认上传吗？**
   简答：不会；脚本名不是开关，默认仍为离线且关闭追踪。
2. **`--live` 与 `--trace` 为什么分开？**
   简答：调用模型与上传运行数据是不同的数据外发，应分别显式选择。
3. **为什么不能把 metadata 当安全的隐藏区？**
   简答：它可能被记录和上传，不是不会泄密的私有存储。
4. **看到了成功 trace，是否说明答案真实？**
   简答：不说明；你还要检查证据和业务规则，并用数据集评测质量。
5. **隐藏 inputs/outputs 就完成脱敏了吗？**
   简答：没有；还要检查 metadata、错误、附件、子调用与访问保留策略。

**复盘句**：你收集足够定位问题的证据，但不给排错工具不必要的数据。

## 6. AI Review 提示词

> 请审查我的追踪设计，不要求我现在开启云端服务。
> 检查默认是否关闭、是否有显式参数、是否处理旧环境开关和回调收尾。
> 按输入、输出、工具结果、metadata、错误分别寻找泄密点。
> 区分本地日志、真实 API 联调、云端追踪和评测，各自需要什么证据。

## 资料与下一步

- [原始学习资料](https://github.com/xiaoyu0513-cell/AI_Learning_Data/tree/main/ai_coding/LangChain_%E5%B0%9A%E7%A1%85%E8%B0%B7)
- [LangSmith 可观测性](https://docs.langchain.com/langsmith/observability)
- [LangSmith 追踪快速开始](https://docs.langchain.com/langsmith/observability-quickstart)
- [LangSmith 数据脱敏](https://docs.langchain.com/langsmith/mask-inputs-outputs)

[上一课：模型调用](02-models.md) · [返回课程首页](../README.md) · [下一课：消息与 Prompt](04-messages-prompts.md)
