# Day 7：用图、MCP 与知识助手完成综合验收

> 今天你要交付一份可以复现的基础能力证据，而不是给一个固定回复套上“生产级助手”的名字。

**对应正文**：[11 · LangGraph](lessons/11-langgraph.md)、[12 · MCP 与 Skills](lessons/12-mcp-skills.md)。
**综合入口**：[13-knowledge-assistant.ts](examples/13-knowledge-assistant.ts)；它是综合示例，不是新增第十三章。
**前置**：[Day 4](day04.md) 的执行边界、[Day 5](day05.md) 的记忆、[Day 6](day06.md) 的检索证据。
七天是压缩基础训练；你还需要真实模型评估、安全审查和部署验证，才能讨论生产使用。

## 1. 今日目标

- [ ] 你能解释确定性路由、暂停、同 thread 恢复与状态更新。
- [ ] 你能通过本地 stdio 调用一个受限 MCP 工具，并定位连接关闭逻辑。
- [ ] 你能沿检索 → Agent → 结构化结果 → 引用验证说明知识助手的数据流。
- [ ] 你能把默认离线验证和未执行的线上 / 数据库验证分开交付。

## 2. 限时学习顺序（约 180 分钟）

| 时间 | 你做什么 | 你留下什么 |
|---|---|---|
| 0–30 分钟 | 阅读 11 与 graph helper | 普通路径 / 需审批路径状态变化 |
| 30–65 分钟 | 阅读 12，运行本地 MCP | 工具发现、参数、结果与关闭位置 |
| 65–100 分钟 | 阅读综合入口与 schema | 字段、来源白名单、记忆与限制 |
| 100–155 分钟 | 做三组综合练习 | 正常 / 拒绝 / 无证据等边界证据 |
| 155–180 分钟 | 整理验收、自测与复盘 | 能力范围与下一阶段缺口 |

若暂停恢复或 MCP 边界还说不清，今天不继续接真实外部服务。

## 3. 默认运行与联网开关

在 `langchain/` 执行；以下命令不需要模型 key，也不需要远程 MCP 服务。

```bash
npm run example:graph
npm run example:mcp
npm run example:assistant
```

graph 仅执行本地规则；MCP 默认启动课程自带的 stdio 子进程。
assistant 默认使用本地词项检索与固定假输出；即使切换 `--live`，这个综合示例仍不自动改用真实 Embedding。
只有你自主选择并核对数据去向后，才考虑以下可选入口；它们不是本日必做验证。

```bash
npm run example:assistant -- --live
npm run example:mcp -- --remote
```

`--live` 允许聊天供应商调用并可能计费；`--remote` 配合 `MCP_SERVER_URL` 连接外部服务。
二者独立：远程 MCP 不代表聊天模型也变成 live；追踪仍需单独显式 `--live --trace`。
远程工具可能访问敏感数据或产生副作用；名称相同、`readOnlyHint` 或描述“只读”都不能替代信任与鉴权。

## 4. 概念速查：把编排、协议、指令分开

| 概念 | 输入 → 输出 / 职责 | 今天的边界 |
|---|---|---|
| `StateSchema` / `StateGraph` | 状态 → 节点更新与路由 | 路由可以是确定性代码，不必有 LLM |
| `interrupt` / `Command` | 暂停载荷 → 人工决策后恢复 | 依赖检查点与同一 thread，不是弹窗 UI |
| MCP | 客户端 ↔ 服务端工具协议 | 暴露能力不等于授予无限执行权限 |
| stdio | 子进程标准输入输出 | stdout 专供协议，日志不要污染它 |
| Streamable HTTP | 客户端 ↔ HTTP 服务 | 当前远程传输主线；SSE 仅作旧版本背景 |
| Skills | 分层指令与按需加载资料 | 不是 MCP 协议，也不是权限控制机制 |
| 结构化回答 | answer + citations + needsReview | schema 通过不代表事实正确 |

本地 server 使用 SDK 1.x，因此适配器选择 `mode: 'legacy'` 的兼容接口。
这里的 `legacy` 不表示“使用旧 SSE”；它与传输选择是两件事。
图的 `stream(..., { streamMode: 'updates' })` 与 Agent 最新 `streamEvents()` 是不同 API。
综合示例使用锁定版本的实验性 v3 Agent 投影流；不要把 `streamMode` 塞进这个接口。

## 5. 源码 / 示例阅读

| 阅读文件 | 你定位什么 | 你回答什么 |
|---|---|---|
| [11-langgraph.ts](examples/11-langgraph.ts)、[graph.ts](src/graph.ts) | 状态、条件边、interrupt、Command | 哪个节点暂停，决策怎样回到它？ |
| [12-mcp.ts](examples/12-mcp.ts)、[mcp.ts](src/mcp.ts)、[错误边界](src/mcp-safety.ts) | 连接、工具白名单、错误脱敏、finally | 原始异常会不会进入下一轮模型或日志？失败后在哪里关闭适配器？ |
| [mcp-server.ts](examples/mcp-server.ts) | `search` 的 query schema | 子进程能接收路径或命令吗？ |
| [13-knowledge-assistant.ts](examples/13-knowledge-assistant.ts) | 检索工具、Agent、stream、记忆 | 来源白名单何时收集？ |
| [structured.ts](src/structured.ts)、[rag.ts](src/rag.ts) | 回答 schema、引用检查、context | 什么是 schema 之外的业务约束？ |

适配器的 server 名为 `knowledge`，服务端注册 `search`，客户端工具名为 `knowledge_search`。
综合示例使用进程内的 `search_knowledge`，不要把两者名称或实现位置混为一谈。
综合入口只演示单次回答；其引用集合生命周期不能未经修改就当作多轮 / 多租户的生产实现。

## 6. 三个分级练习

### A · 基础：走通图的正常、暂停与拒绝路径

- **输入**：“配送咨询”与“申请退款”；给审批请求使用独立 thread。
- **操作**：先看普通问题到 reply；再看退款问题在 review 暂停，用同 thread 恢复。
- **预期行为**：普通路径结束；审批路径先返回暂停状态，恢复为批准或拒绝；没有真实退款。
- **加测**：默认示例演示批准，在练习副本改为拒绝，记录结果差异。
- **证据**：写出节点顺序与 thread 标签；说明 interrupt 前的代码可能重跑，不能先做非幂等写操作。

### B · 进阶：把 MCP 当协议边界审查

- **输入**：本地 `knowledge_search`，参数 `{"query":"退货"}`；再用“明天天气”作范围外查询。
- **操作**：运行默认入口，检查工具发现与结果；在练习副本只调用预期的只读工具。
- **预期行为**：退货查询返回带相对来源的结果，范围外查询不编造政策；空 query 不通过 schema。
- **资源边界**：定位 `finally` 的 `adapter.close()`；错误路径也必须释放客户端与子进程资源。
- **故障注入**：参考 [MCP 错误测试](tests/mcp-safety.test.ts)，分别模拟抛异常和直接返回 error ToolMessage；原文不得出现在最终状态、日志或下一轮模型输入中，但中断与取消不能被吞掉。
- **信任边界**：列出远程服务审核、认证与出网控制要求；今天不把“工具名相同”当成远程安全证明。

### C · 挑战：验收知识助手并主动破坏引用

- **输入**：“收到后几天内可以申请退货？”；保留默认离线固定脚本。
- **操作**：跟踪检索调用、工具证据、`responseFormat`、最终 `structuredResponse` 与引用校验。
- **预期行为**：结构对象包含 `answer/citations/needsReview`，引用是本次检索允许的相对来源。
- **破坏测试**：用 `fixtures/knowledge/not-in-results.md` 作引用，必须被 `validateCitations` 拒绝，即使 schema 合法。
- **空证据测试**：允许来源集合为空时，合法兜底应为非空“资料不足”回答、`citations: []`、`needsReview: true`。
- **边界**：直接验证上述对象；若改问句却不改假脚本，它仍按固定脚本检索 / 回答，不能据此评价动态行为。

## 7. 综合证据清单与易踩坑

- [ ] 我留下图的普通、批准、拒绝路径及同 thread 恢复依据。
- [ ] 我记录本地 MCP 的工具名、query、来源，以及正常 / 异常清理位置。
- [ ] 我区分了 MCP 工具与进程内工具，没有把 Skills 说成协议。
- [ ] 我保留结构化回答、来源白名单、非法引用与无证据对象的校验结果。
- [ ] 我区分实际运行、源码推断、尚未执行的供应商 / 追踪 / 数据库 / 远程服务检查。

引用白名单只证明路径属于允许集合，不证明每个回答断言都被原文支持。
同进程记忆不会跨重启保存；同 thread 恢复也不自动提供鉴权或人工审批 UI。
暂停恢复前的副作用需要幂等设计，不能靠“这段代码之前跑过了”假定不会重跑。
最终验收写你实际观察到的行为和失败边界，不编造测试数量、真实语义召回率或线上质量指标。

## 8. 五道自测（附简答）

1. **LangGraph 一定需要 LLM 吗？** 不需要；今天使用确定性路由与人工暂停演示。
2. **恢复为什么要求同一 thread？** 恢复需要定位原检查点与待继续的执行状态。
3. **stdio 的 stdout 能打印调试信息吗？** 不能随意打印；它是协议通道，日志应走 stderr 等通道。
4. **Skills 等于 MCP 吗？** 不等于；前者组织分层指令，后者定义客户端与服务端的能力交互。
5. **合法引用就等于真实回答吗？** 不等于；还要逐条核对事实、条件和本次检索证据。

## 9. 五行复盘模板

```text
这七天我能复现的输入 → 状态 → 输出：
我真正触发并记录的失败边界：
我仍无法证明的模型 / 检索能力：
我上线前必须补的权限 / 持久化 / 评估：
下一周我选择深挖的一个问题：
```

## 10. AI Review 提示词

> 我完成七天基础训练。下面是脱敏的图执行、MCP 调用、结构化回答与失败路径证据。
> 请沿输入 → 检索 → 工具 → 状态 → 输出逐段检查，特别关注恢复副作用、连接清理、引用与权限边界。
> 区分固定假输出的流程验证和真实模型评估，不把七天训练或 schema 通过说成生产就绪。
> 给出“已被证据支持 / 尚未验证 / 最优先补齐”的结论，不补造运行结果，不要求我暴露凭据。

参考：[官方 JS LangGraph 文档](https://docs.langchain.com/oss/javascript/langgraph/overview)、[官方 JS MCP 文档](https://docs.langchain.com/oss/javascript/langchain/mcp)。

**上一天**：[Day 6：本地 RAG](day06.md) · **下一站**：[回课程入口选择深入主题](README.md)。
