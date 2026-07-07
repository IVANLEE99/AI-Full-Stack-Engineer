# Week 15 Day 02：MCP Tool 集成

> 所属周：Week 15：Agent + Tool Calling  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`ai-lab/customer-agent`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

实现「查订单」意图，让 Agent 真正调用一个 Tool 查到订单，并把结果格式化返回给用户。

今天你要真正掌握这一句话：

> 一个 Tool 由「三样东西」组成：给模型看的 JSON 描述（tool schema）、后端真正执行的函数、以及把执行结果以 `role:"tool"` 回喂模型的那一步；把这三样接起来，Agent 就能查到真实订单了。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解昨天的 Agent Loop，今天是给它接上第一个真工具
2. 理解什么是 MCP，它和 Tool Calling 是什么关系
3. 理解一个 Tool 的三段式结构（schema + 执行 + 回喂）
4. 写出「查订单」的 tool schema
5. 写出「查订单」的执行函数（先用假数据 mock）
6. 把它接进昨天的 Agent Loop，跑通一次查订单
7. 把订单结果格式化成人话
8. 写今日笔记与自测

---

## 1. 学习内容

### 1.1 什么是 MCP，和 Tool Calling 什么关系

MCP（Model Context Protocol，模型上下文协议）是一种「让 Agent 统一接入外部工具/数据源」的协议规范。你可以先用一个粗略但够用的理解：

```text
Tool Calling ：模型「决定要调工具」的机制（模型侧）
MCP          ：把工具「标准化打包成服务」的协议（工具侧）
```

对比表：

| 对比项 | 直接写 Tool | MCP Tool |
|---|---|---|
| 工具放在哪 | 和 Agent 写在一个项目里 | 独立的 MCP Server，可复用 |
| 谁能用 | 只有当前 Agent | 任何支持 MCP 的客户端都能接 |
| 类比 | 项目内部的一个函数 | 一个独立的微服务/API |

小白重点：

> 不管是「直接写的 Tool」还是「MCP Tool」，对 Agent Loop 来说流程都一样：模型返回 tool_calls → 后端执行 → 回喂结果。MCP 只是把「后端执行」这一步换成「去调一个独立的 MCP Server」。今天我们先用本地 mock 函数跑通，理解流程比协议细节更重要。

用一张图看 MCP 的位置：

```text
Agent Loop ──(tool_calls)──▶ MCP Client ──(协议请求)──▶ MCP Server
                                                          │
                                                    真正查订单 DB
                                                          │
Agent Loop ◀──(role:tool)── MCP Client ◀──(协议返回)──────┘
```

---

### 1.2 一个 Tool 的三段式结构

这是今天最重要的心智模型。任何一个 Tool 都由三段组成：

```text
第 1 段：Tool Schema（给模型看的说明书）
  → 告诉模型：有个工具叫 get_order，需要 order_id，能查订单

第 2 段：执行函数（后端真正干活）
  → function getOrder(order_id) { 去查 DB / MCP，返回订单对象 }

第 3 段：结果回喂（把返回值发回模型）
  → messages.push({ role:"tool", tool_call_id, content: JSON 结果 })
```

三段缺一不可：

| 缺哪段 | 后果 |
|---|---|
| 缺 Schema | 模型根本不知道有这个工具，永远不会调 |
| 缺执行函数 | 模型要求调工具，但你无法执行，程序报错 |
| 缺回喂 | 工具执行了，但模型看不到结果，无法生成回答 |

---

### 1.3 写「查订单」的 Tool Schema

先定义给模型看的说明书，用 Node/JS 示例：

```js
// 查订单工具的 schema（给模型看的说明书）
const getOrderTool = {
  type: "function",
  function: {
    name: "get_order",
    description: "根据订单号查询订单的状态、物流信息和金额。当用户询问订单进度、发货情况时使用。",
    parameters: {
      type: "object",
      properties: {
        order_id: {
          type: "string",
          description: "订单号，通常以字母开头，例如 A123456",
        },
      },
      required: ["order_id"],
    },
  },
};
```

小白重点：

> `description` 是模型判断「要不要用这个工具」的关键。写得越具体（说清什么场景用），模型调用得越准。把它当成写给同事看的接口注释来对待。

---

### 1.4 写「查订单」的执行函数（先用 mock）

真实项目会连数据库或 MCP Server，学习阶段先用假数据把流程跑通：

```js
// 后端真正执行的函数。学习阶段用 mock 数据
async function getOrder(orderId) {
  // 真实场景：这里会去调 MCP Server 或查数据库
  // const res = await mcpClient.call("get_order", { order_id: orderId });

  // mock：假装数据库里有这条订单
  const fakeDb = {
    A123456: { order_id: "A123456", status: "已发货", eta: "预计明天送达", amount: 199.0 },
    A999999: { order_id: "A999999", status: "待支付", eta: null, amount: 59.9 },
  };

  const order = fakeDb[orderId];
  if (!order) {
    // 查不到也要返回结构化结果，让模型知道「没查到」
    return { found: false, message: "未查询到该订单" };
  }
  return { found: true, ...order };
}
```

小白重点：

> 查不到订单时，不要抛异常让程序崩，而要返回一个「结构化的没查到」结果（`found:false`）。这样模型能看懂并礼貌回复用户「没找到这个订单」。工具函数要尽量「不崩，只返回状态」。

---

### 1.5 把工具接进 Agent Loop（今天的核心）

现在把昨天的 Agent Loop 和今天的 get_order 接起来，形成一个能跑的最小闭环：

```js
const tools = [getOrderTool]; // 目前只有一个工具

// 工具名 → 执行函数 的映射表（后面加工具就往这里加）
const toolExecutors = {
  get_order: async (args) => getOrder(args.order_id),
};

async function runAgent(userMessage) {
  const messages = [
    { role: "system", content: "你是电商客服助手，可以帮用户查询订单。" },
    { role: "user", content: userMessage },
  ];

  for (let step = 0; step < 5; step++) {
    const response = await callLLM({ messages, tools });
    const msg = response.choices[0].message;
    messages.push(msg);

    // 模型直接回答了，结束
    if (!msg.tool_calls) {
      return msg.content;
    }

    // 逐个执行模型要求的工具
    for (const call of msg.tool_calls) {
      const fnName = call.function.name;
      const args = JSON.parse(call.function.arguments); // arguments 是字符串，要 parse

      const executor = toolExecutors[fnName];
      const result = executor
        ? await executor(args)
        : { error: `未知工具: ${fnName}` }; // 防御：模型点了不存在的工具

      messages.push({
        role: "tool",
        tool_call_id: call.id, // 必须带上，模型靠它对应是哪次调用
        content: JSON.stringify(result),
      });
    }
    // 继续循环，带着工具结果再问模型
  }

  return "抱歉，暂时无法处理您的请求，请稍后再试。";
}
```

跑一次的效果：

```text
用户：帮我查下订单 A123456
→ 模型返回 tool_calls: get_order(order_id="A123456")
→ 后端执行 getOrder("A123456") 得到 {found:true, status:"已发货", eta:"预计明天送达"}
→ 回喂给模型
→ 模型回答：您的订单 A123456 已发货，预计明天送达。
```

---

### 1.6 把订单结果格式化成「人话」

有两种格式化方式，要分清：

方式一：交给模型自己组织语言（推荐，最自然）。你只要把结构化 JSON 回喂给模型，模型会自己写成通顺的话。上面的例子就是这种。

方式二：后端预格式化（当你想控制固定话术时用）：

```js
function formatOrder(order) {
  if (!order.found) {
    return "抱歉，没有查询到这个订单，请核对订单号后重试。";
  }
  const eta = order.eta ? `，${order.eta}` : "";
  return `您的订单 ${order.order_id} 当前状态：${order.status}${eta}，实付金额 ¥${order.amount}。`;
}
```

对比：

| 方式 | 优点 | 缺点 | 适用 |
|---|---|---|---|
| 模型自己组织 | 自然、能结合上下文 | 话术不完全可控 | 大多数客服场景 |
| 后端预格式化 | 话术固定、可控 | 生硬、不灵活 | 金额/隐私等需要精确表述 |

小白重点：

> 学习阶段先用「方式一」跑通闭环，理解 Agent 能力；等你需要精确控制金额、隐私字段表述时，再引入「方式二」做后端格式化。

---

## 2. 源码阅读

- `ai-lab/customer-agent/src/agent.ts`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. `tools` 数组里每个工具的 schema 结构（name / description / parameters）
2. 有没有一张「工具名 → 执行函数」的映射表（类似 `toolExecutors`）
3. `JSON.parse(arguments)` 出现在哪
4. `role: "tool"` 的消息是怎么构造的，有没有带 `tool_call_id`
5. 工具执行失败时，代码是抛异常还是返回结构化错误

建议在笔记里填一张表：

| 源码里的东西 | 对应今天哪个概念 |
|---|---|
| tool schema | 三段式第 1 段（说明书） |
| toolExecutors 映射 | 三段式第 2 段（执行） |
| `role:"tool"` 消息 | 三段式第 3 段（回喂） |
| `JSON.parse(arguments)` | 参数是字符串要解析 |
| `tool_call_id` | 对应是哪一次调用 |

---

## 3. 练习任务

### 练习 1：写出 get_order 的 tool schema

参照 1.3，独立写出 `get_order` 的完整 schema。自检：

- [ ] `name` 是 `get_order`
- [ ] `description` 说清了「什么场景用」
- [ ] `parameters` 里 `order_id` 是 string 且在 `required` 里

---

### 练习 2：实现 get_order 执行函数（mock）

参照 1.4，写一个带 mock 数据的 `getOrder(orderId)`：

步骤：

1. 准备一个假 DB（对象），放 2~3 条订单
2. 查得到 → 返回 `{found:true, ...订单}`
3. 查不到 → 返回 `{found:false, message:"未查询到该订单"}`
4. 用 `getOrder("A123456")` 和 `getOrder("不存在")` 各测一次

示例测试：

```js
console.log(await getOrder("A123456")); // {found:true, status:"已发货", ...}
console.log(await getOrder("XXX"));      // {found:false, message:"未查询到该订单"}
```

---

### 练习 3：接进 Agent Loop 跑通查订单

参照 1.5，把 schema 和执行函数接进 Agent Loop。

步骤：

1. 把 `getOrderTool` 放进 `tools`
2. 在 `toolExecutors` 里注册 `get_order`
3. 运行 `runAgent("帮我查下订单 A123456")`
4. 观察是否走完「模型要求调工具 → 执行 → 回喂 → 最终回答」

目标：亲眼看到一次完整的 Tool Calling 闭环。

> 说明：如果暂时没有真实模型 API，可以先把 `callLLM` 也 mock 成「返回一个固定的 tool_calls，再返回一句最终回答」，重点是把流程跑顺。

---

### 练习 4：查不到订单的处理

运行 `runAgent("帮我查下订单 NOTEXIST")`，观察：

- 执行函数是否返回了 `found:false` 而不是崩溃
- 模型是否礼貌地告诉用户「没查到」

目标：理解「工具函数不崩、只返回状态」的重要性。

---

## 4. JS/Node.js 类比

| Tool 概念 | Node.js / JS 类比 | 说明 |
|---|---|---|
| tool schema | OpenAPI / 接口文档 | 描述接口名、参数、用途 |
| 执行函数 | Express 里的一个 service 函数 | 真正查库/调 API |
| toolExecutors 映射 | 路由表 `{ path: handler }` | 名字找到对应处理函数 |
| `JSON.parse(arguments)` | `JSON.parse(req.body)` | 拿到的是字符串要解析 |
| `role:"tool"` 回喂 | 把 service 返回值塞回响应 | 让上层看到结果 |
| MCP Server | 一个独立的微服务 | 工具独立部署、可复用 |

一句话类比：

> Tool Calling ≈ 模型帮你「决定调哪个 API、传什么参数」，然后你像平时写 Node 后端一样真正去调那个 API，再把返回值告诉模型。

---

## 5. AI Review 提问

```text
我正在学习 Week 15 Day 02：MCP Tool 集成。
这是我实现的 get_order 工具（schema + 执行函数）和接入 Agent Loop 的代码：
（贴上你的代码）

请你按资深后端工程师标准帮我检查：
1. 我的 tool schema 的 description 是否清楚到能让模型正确调用？
2. 执行函数在「查不到订单」时是否做了合理处理（返回结构化而非抛异常）？
3. 回喂结果时 tool_call_id 是否正确带上？
4. 用「MCP Server ≈ 微服务」类比是否准确？
5. 真实项目里查订单还要考虑什么（越权查别人订单、参数校验、超时、脱敏）？

请用中文输出：我做对的地方、问题清单、修改建议、下一步练习。
```

---

## 6. 今日产出

- [ ] get_order 的 tool schema
- [ ] get_order 的执行函数（含查不到的处理）
- [ ] 接入 Agent Loop 后能跑通查订单
- [ ] Tool 三段式结构笔记
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说出 Tool 的三段式结构（schema / 执行 / 回喂）
- [ ] 能写出 get_order 的完整 schema
- [ ] 能写出 mock 版执行函数并处理查不到的情况
- [ ] 能把工具接进 Agent Loop 跑通一次查订单
- [ ] 能解释 MCP 和 Tool Calling 的关系
- [ ] 能说清 `tool_call_id` 的作用

---

## 8. 今日自测题

### 8.1 一个 Tool 由哪三段组成？

参考答案：

> ✅ 1）给模型看的 schema（说明书）；2）后端真正执行的函数；3）把执行结果以 `role:"tool"` 回喂模型。三段缺一不可。

---

### 8.2 tool schema 里的 `description` 有什么用？

参考答案：

> ✅ 它是模型判断「要不要用这个工具、什么场景用」的主要依据。描述越具体、越贴近使用场景，模型调用得越准确。

---

### 8.3 查不到订单时，执行函数应该怎么处理？

参考答案：

> ✅ 不要抛异常让程序崩溃，而要返回一个结构化的「没查到」结果（如 `{found:false, message:"未查询到该订单"}`），这样模型能读懂并礼貌回复用户。

---

### 8.4 MCP 和 Tool Calling 是什么关系？

参考答案：

> ✅ Tool Calling 是模型侧「决定调工具」的机制；MCP 是工具侧「把工具标准化打包成独立服务」的协议。对 Agent Loop 而言流程一样，MCP 只是把「后端执行」换成「调 MCP Server」。

---

### 8.5 回喂工具结果时为什么要带 `tool_call_id`？

参考答案：

> ✅ 一轮里模型可能同时要求调多个工具，`tool_call_id` 让模型知道这条结果对应的是哪一次调用请求，避免结果和调用对不上。

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
我正在进行 Week 15 Day 02：MCP Tool 集成 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 15 README](./README.md)
