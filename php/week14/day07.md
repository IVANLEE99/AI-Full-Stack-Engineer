# Week 14 Day 07：验收与预习

> 所属周：Week 14：MCP Protocol + MCP Server  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`mcp-server`  
> 类型：复盘预习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

对本周 MCP 学习做整体验收，确认每个知识点真的掌握了；然后预习下一步 Agent 与 Function Calling，把 MCP 和 Agent 的关系串起来。

今天你要真正掌握这一句话：

> MCP 解决的是「AI 怎么标准化地连接到工具和数据」，Function Calling 解决的是「AI 怎么决定调用哪个工具、传什么参数」；Agent 则是把「思考 → 调用工具 → 看结果 → 再思考」这个循环自动跑起来。MCP 是 Agent 的「手」，Function Calling 是「大脑发指令的方式」。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 用一句话复述本周每天学到的核心
2. 对照验收清单逐条打钩
3. 补齐没掌握的薄弱点
4. 预习 Function Calling 是什么
5. 理解 MCP 与 Function Calling 的关系
6. 预习 Agent 循环（ReAct 思想）
7. 写本周总结和自测

---

## 1. 学习内容

### 1.1 本周知识地图回顾

先用一句话概括每天，检验是否真掌握：

| Day | 主题 | 一句话核心 |
|---|---|---|
| Day 01 | MCP 协议概述 | MCP 是 AI 连接工具/数据的标准协议，核心角色是 Host/Client/Server，Server 暴露 Tool/Resource/Prompt |
| Day 02 | 现有 Server 源码 | Server 靠 listTools 声明能力、callTool 执行调用，底层是 JSON-RPC |
| Day 03 | 新增只读 Tool | 一个 Tool = 名字 + 描述 + inputSchema + 执行函数，只读且参数化查询 |
| Day 04 | Cursor 集成 | 在 mcp 配置里声明 command/args，Cursor 作为 Host 启动 Server 并调用 |
| Day 05 | 安全规范 | 只连 dev/只读/脱敏/密钥不入库，是 MCP 的安全红线 |
| Day 06 | 交付项目 | 可交付 = 可运行代码 + 测试过的工具 + 让别人能跑通的文档 |

小白重点：

> 如果某一行你复述不出来，就是薄弱点，回去补。验收不是走过场，是真的能讲给别人听。

---

### 1.2 本周核心概念自检

用问答形式快速自检，答不上来的标记为薄弱：

```text
Q: MCP 里 Host、Client、Server 分别是谁？
A: Host 是承载 AI 的应用（如 Cursor）；Client 是 Host 内与 Server 通信的连接器；Server 是提供工具/数据的进程。

Q: listTools 和 callTool 各干什么？
A: listTools 告诉 AI「有哪些工具、参数是什么」；callTool 执行「用这些参数调用某个工具」。

Q: 为什么 MCP 底层用 JSON-RPC？
A: 它是一套简单的「请求-响应」标准格式（method + params + id），双方约定好就能互通，不用各自发明协议。

Q: stdio 和 SSE 传输的区别？
A: stdio 走标准输入输出，适合本地进程（如 Cursor 启动本地 Server）；SSE/HTTP 走网络，适合远程 Server。

Q: MCP 的安全红线有哪些？
A: 只连 dev、只读、脱敏、密钥不入库、不做写/删操作。
```

---

### 1.3 预习：Function Calling 是什么

Function Calling 是大模型的一种能力：你把「可用函数的描述」告诉模型，模型在需要时会输出「我要调用哪个函数、参数是什么」的结构化 JSON，由你的程序去执行。

一个最简示意（伪代码）：

```js
// 1. 告诉模型有哪些函数可用
const tools = [{
  name: "get_weather",
  description: "查询某城市天气",
  parameters: {
    type: "object",
    properties: { city: { type: "string" } },
    required: ["city"],
  },
}];

// 2. 模型收到「北京天气如何」后，不直接答，而是返回：
const modelReply = {
  tool_call: { name: "get_weather", arguments: { city: "北京" } },
};

// 3. 你的程序执行这个函数，把结果再喂回模型
const result = getWeather("北京");
// 4. 模型拿到结果，生成最终自然语言回答
```

对比表：

| 对比项 | 普通对话 | Function Calling |
|---|---|---|
| 模型输出 | 直接自然语言 | 结构化「调用哪个函数」 |
| 谁执行 | 无需执行 | 你的程序执行函数 |
| 结果去向 | 直接给用户 | 喂回模型再生成回答 |

小白重点：

> Function Calling 里模型不真的执行函数，它只是「说要调用哪个、传什么参数」。真正执行的是你的代码。这点和 MCP 的 callTool 完全一致。

---

### 1.4 预习：MCP 与 Function Calling 的关系

很多人会混淆两者，其实是互补的：

| 维度 | Function Calling | MCP |
|---|---|---|
| 定位 | 模型「决定调用什么」的能力 | 工具「怎么被标准化连接」的协议 |
| 谁定义工具 | 你在请求里临时描述 | Server 用 listTools 声明 |
| 复用性 | 每个应用各写各的 | 一个 Server 多个 Host 复用 |
| 关系 | 大脑发指令的方式 | 手能拿到的工具集 |

把它们串起来：

```text
用户提问
   ↓
模型（Function Calling 能力）决定：调用 get_user_by_id，参数 id=1
   ↓
Host 通过 MCP Client 向 MCP Server 发 callTool 请求
   ↓
MCP Server 执行只读查询，返回结果
   ↓
模型拿到结果，生成最终回答
```

小白重点：

> MCP 让工具变成「可插拔的标准件」，Function Calling 让模型「会用这些标准件」。两者合起来，才是一个能干活的 AI 系统。

---

### 1.5 预习：Agent 循环（ReAct 思想）

Agent 就是把「思考-行动-观察」自动循环起来，直到任务完成：

```text
循环开始
  ├─ Thought（思考）：我需要先查用户，再查他的订单
  ├─ Action（行动）：调用 get_user_by_id(id=1)
  ├─ Observation（观察）：拿到用户，id=1，name=Tom
  ├─ Thought：现在查他的订单
  ├─ Action：调用 list_orders(userId=1)
  ├─ Observation：拿到 3 条订单
  └─ Thought：信息够了，生成最终回答
循环结束
```

对比表：

| 概念 | 一次调用 | Agent 循环 |
|---|---|---|
| 工具调用次数 | 1 次 | 多次，直到任务完成 |
| 谁决定下一步 | 无 | 模型根据观察结果决定 |
| 适合场景 | 单一查询 | 多步骤、需推理的任务 |

小白重点：

> 你本周做的 MCP Tool，就是 Agent 循环里「Action」能调用的东西。学好 MCP，下周学 Agent 才有「手」可用。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成验收与预习。

可选：回看你 Day 03/Day 06 写的 Tool 代码，问自己：

1. 如果要接入 Agent 循环，这个 Tool 的返回值格式够清晰吗？
2. 描述（description）写得够好吗？模型能靠它判断何时调用吗？

---

## 3. 练习任务

### 练习 1：本周验收打钩

对照下面清单逐条确认，做不到的回去补：

| 验收项 | 是否掌握 |
|---|---|
| 能解释 MCP 的 Host/Client/Server |  |
| 能说清 listTools / callTool 的作用 |  |
| 知道 MCP 底层是 JSON-RPC |  |
| 能区分 stdio 和 SSE 传输 |  |
| 能自己写一个只读 Tool（含 inputSchema） |  |
| 能在 Cursor 里配置并调用 MCP Server |  |
| 能背出 MCP 安全红线 |  |
| 有一个可交付的 MCP 项目（含 README） |  |

---

### 练习 2：画本周知识地图

用一张图（手绘或文字）把本周概念串起来，要求包含：

```text
Host → Client → Server → Tool
                          ├─ listTools
                          └─ callTool
JSON-RPC（底层）
stdio / SSE（传输）
安全红线（只读/dev/脱敏）
```

---

### 练习 3：预习笔记

用自己的话写清楚三个问题（每个 3-5 句）：

1. Function Calling 是什么？模型在里面做什么、不做什么？
2. MCP 和 Function Calling 是什么关系？
3. Agent 循环（Thought-Action-Observation）是怎么运转的？

---

### 练习 4：串联思考题

回答：「我本周做的 MCP Tool，在下周的 Agent 里扮演什么角色？」写 3-5 句，说清 MCP 是 Agent 的「手」这个关系。

---

## 4. JS/Node.js 类比

| 概念 | 后端工程类比 | 说明 |
|---|---|---|
| 本周验收 | 上线前回归测试 | 逐项确认功能都在 |
| Function Calling | RPC 客户端存根 | 声明能调什么，由底层执行 |
| MCP Server | 微服务 | 对外提供标准化能力 |
| Agent 循环 | 编排/工作流引擎 | 多步调用直到完成 |
| Tool 描述 | 接口文档 | 让调用方知道何时怎么用 |

---

## 5. AI Review 提问

```text
我正在做 MCP Day 07：本周验收 + 预习 Agent/Function Calling。

请你按资深工程师标准帮我检查：

1. 我对本周 MCP 知识（Host/Client/Server、listTools/callTool、JSON-RPC、传输、安全）的复述是否准确？
2. 我对 Function Calling 的理解对吗？特别是「模型只决定调用、不真执行」这点。
3. 我理解的 MCP 与 Function Calling 的关系准确吗？
4. 我对 Agent 循环（Thought-Action-Observation）的理解对吗？
5. 我说「MCP 是 Agent 的手」这个类比准确吗？

请用中文输出：
- 我理解正确的地方
- 我理解错误或不完整的地方
- 修改建议
- 下周学习 Agent 前的准备建议
```

---

## 6. 今日产出

- [ ] 本周验收清单（全部打钩）（练习 1）
- [ ] 本周知识地图（练习 2）
- [ ] Function Calling / MCP 关系 / Agent 循环 预习笔记（练习 3）
- [ ] 「MCP 是 Agent 的手」串联思考（练习 4）
- [ ] 本周总结
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能用一句话复述本周每天的核心
- [ ] 本周验收清单全部达标（不达标的已补齐）
- [ ] 能说清 Function Calling 是什么、模型在其中做什么
- [ ] 能说清 MCP 与 Function Calling 的关系
- [ ] 能说清 Agent 循环（Thought-Action-Observation）
- [ ] 能解释「MCP 是 Agent 的手」这个类比
- [ ] 完成本周总结，明确下周学习方向

---

## 8. 今日自测题

### 8.1 MCP 和 Function Calling 各解决什么问题？

参考答案：

> ✅ MCP 解决「AI 怎么标准化地连接工具和数据」（协议层）；Function Calling 解决「AI 怎么决定调用哪个工具、传什么参数」（模型能力层）。两者互补。

---

### 8.2 在 Function Calling 里，模型会真的执行函数吗？

参考答案：

> ✅ 不会。模型只输出「要调用哪个函数、参数是什么」的结构化 JSON，真正执行函数的是你的程序。这点和 MCP 的 callTool 一致。

---

### 8.3 Agent 循环包含哪三个环节？

参考答案：

> ✅ Thought（思考下一步）、Action（调用工具）、Observation（观察结果），循环往复直到任务完成。也叫 ReAct 模式。

---

### 8.4 为什么说「MCP 是 Agent 的手」？

参考答案：

> ✅ Agent 循环里的 Action 需要真正能调用的工具，MCP Server 暴露的 Tool 就是这些可调用的能力。没有工具，Agent 只能空想；MCP 提供了标准化的「手」去实际操作。

---

### 8.5 本周学的 MCP Tool，为下周 Agent 打下了什么基础？

参考答案：

> ✅ 打下了「可执行工具」的基础。Agent 的核心是循环调用工具，而本周写的只读 Tool（带 inputSchema、参数化查询、错误处理）正是 Agent 循环里能直接复用的 Action 目标。

---

## 9. 学习记录

| 记录项 | 内容 |
|--------|------|
| 今日最清楚的概念 |  |
| 今日最卡的概念 |  |
| JS/Node 类比是否帮助理解 |  |
| 实际耗时 |  |
| 明日要补的问题 |  |

---

## 10. AI Review 提示词

```text
我正在进行 Week 14 Day 07：验收与预习 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 14 README](./README.md)
