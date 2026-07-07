# Week 15 Day 06：客服 Agent Demo

> 所属周：Week 15：Agent + Tool Calling  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`ai-lab/customer-agent`  
> 类型：项目实战  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

把 Day01~Day05 学的所有零件（Agent Loop + get_order/get_product 工具 + System Prompt + 意图路由 + 错误处理与兜底）拼成**一个能当面演示的完整客服 Agent Demo**，并写一份别人照着就能跑起来的使用文档。

今天你要真正掌握这一句话：

> Demo 不是「又写一堆新代码」，而是「把前 5 天的零件组装成一个可运行、可讲解、可复现的整体」——一个入口函数 `runAgent(userInput)`，内部串起 System Prompt、工具清单、Agent Loop、错误兜底，对外只暴露「输入一句话、返回一句人话」这一个简单接口。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先回顾前 5 天各产出了什么零件，列一张「零件清单」
2. 设计 Demo 的项目结构（文件怎么分）
3. 把零件按依赖顺序组装：Prompt → Tools → Router → Loop → Fallback
4. 写一个命令行交互入口（能连续对话）
5. 准备 5 条演示脚本（覆盖查订单、查商品、FAQ、越权、查不到）
6. 跑一遍，录下每条的实际输出
7. 写使用文档（README）
8. 写今日笔记与自测

---

## 1. 学习内容

### 1.1 先盘点：前 5 天我们造了哪些零件

组装之前，先把散落在各天的零件收拢到一张表。这一步能帮你发现「哪块还没写、哪块要改接口」。

| 零件 | 来自哪天 | 作用 |
|---|---|---|
| Agent Loop（`runAgent` 的 while 循环） | Day01 | 反复调模型、执行工具、回喂，直到出最终答案 |
| `get_order` 工具（schema + 执行函数） | Day02 | 查订单状态、物流、金额 |
| System Prompt（六段式） | Day03 | 定义人设、能力、边界、语气、兜底 |
| `get_product` 工具 + `toolRouter` 分发表 | Day04 | 查商品 + 统一路由 + FAQ 兜底 |
| `executeToolSafe` + `MAX_TURNS` + `friendlyMessage` | Day05 | 错误处理、超时保护、友好话术 |

小白重点：

> 今天几乎不产出「新知识」，产出的是「整合能力」。企业里真正难的不是写单个函数，而是把一堆零件拼成一个不崩、可维护、能演示的系统。这份「零件清单」就是你的装配图纸。

---

### 1.2 设计 Demo 的项目结构

把所有代码堆在一个文件里能跑，但没法讲清楚。按职责拆成几个文件，演示时也好一块块讲：

```text
ai-lab/customer-agent/
├── src/
│   ├── prompt.js       # System Prompt（Day03 的六段式 + Day04 的 FAQ）
│   ├── tools.js        # 工具 schema + 执行函数（get_order / get_product）
│   ├── router.js       # toolRouter 分发表 + executeToolSafe（Day04/05）
│   ├── agent.js        # runAgent 主循环（Day01 的 Loop + Day05 的兜底）
│   └── llm.js          # callLLM 封装（真实 API 或 mock）
├── demo.js             # 命令行交互入口，连续对话
└── README.md           # 使用文档
```

对比表：

| 文件 | 对应「零件清单」哪一行 | 一句话职责 |
|---|---|---|
| `prompt.js` | System Prompt | Agent 是谁、规则是什么 |
| `tools.js` | get_order / get_product | 工具说明书 + 真正干活的函数 |
| `router.js` | toolRouter / executeToolSafe | 按工具名分发，并兜住异常 |
| `agent.js` | Agent Loop + 兜底 | 串起全流程的主控 |
| `llm.js` | 调模型 | 把「调大模型」这件事封装起来 |
| `demo.js` | — | 让人能敲键盘和它对话 |

小白重点：

> 拆文件的标准是「一个文件一个职责」。判断拆得对不对，就看：跟别人讲这个文件时，能不能用一句话说清它干嘛。说不清，就说明它职责太杂，该拆。

---

### 1.3 组装第一步：Prompt 与 Tools

先把 Day03 的 System Prompt 和 Day04 的 FAQ 合到 `prompt.js`：

```js
// src/prompt.js
export const systemPrompt = `
你是「优选商城」的在线客服助手，名字叫小优。

# 你能做什么
- 查询订单状态和物流（使用 get_order 工具）
- 查询商品价格和库存（使用 get_product 工具）
- 回答常见问题（退换货、配送时效、发票）

# 你不能做什么
- 不能编造订单号、物流状态、价格等任何信息，没查到就如实说没查到。
- 不能承诺具体退款金额或时间，这类问题引导用户联系人工客服。
- 不回答与购物无关的话题，礼貌把话题引回购物。

# 什么时候调用工具
- 问订单/物流/发货 → 调 get_order（缺订单号就先问用户要）
- 问商品价格/库存/详情 → 调 get_product
- 没有工具结果时，绝不自己编造订单或商品数据。

# 常见问题（FAQ）
- 退换货：签收后 7 天无理由退换（生鲜、定制除外）。
- 配送时效：一般 48 小时内发货，偏远地区 3~5 天。
- 发票：下单后可在订单详情页申请电子发票。

# 说话风格
- 简洁亲切，用「您」称呼，每次回复 2~3 句。

# 遇到不确定时
- 查不到 → 如实告知，建议核对信息或联系人工客服。
- 超出能力 → 礼貌说明并引导人工客服。
`.trim();
```

再把 Day02、Day04 的工具收进 `tools.js`：

```js
// src/tools.js

// —— 工具 schema（给模型看的说明书）——
export const tools = [
  {
    type: "function",
    function: {
      name: "get_order",
      description: "根据订单号查询订单状态、物流和金额。用户问订单进度、发货情况时使用。",
      parameters: {
        type: "object",
        properties: {
          order_id: { type: "string", description: "订单号，例如 A123456" },
        },
        required: ["order_id"],
      },
    },
  },
  {
    type: "function",
    function: {
      name: "get_product",
      description: "根据商品名称或 ID 查询价格、库存和简介。",
      parameters: {
        type: "object",
        properties: {
          keyword: { type: "string", description: "商品名称或 ID，例如 iPhone 或 P1001" },
        },
        required: ["keyword"],
      },
    },
  },
];

// —— mock 数据（学习阶段用假 DB）——
const orderDB = {
  A123456: { order_id: "A123456", status: "已发货", eta: "预计明天送达", amount: 199.0 },
  A999999: { order_id: "A999999", status: "待支付", eta: null, amount: 59.9 },
};
const productDB = {
  P1001: { name: "优选蓝牙耳机", price: 199, stock: 42 },
  P1002: { name: "优选保温杯", price: 89, stock: 0 },
};

// —— 执行函数（Day02/04：查不到也返回结构化结果，不抛异常）——
export function queryOrder(orderId) {
  if (!orderId) return { ok: false, error: "MISSING_ARG", message: "缺少订单号" };
  const order = orderDB[orderId];
  if (!order) return { ok: false, error: "NOT_FOUND", message: "查无此订单" };
  return { ok: true, data: order };
}

export function queryProduct(keyword) {
  if (!keyword) return { ok: false, error: "MISSING_ARG", message: "缺少商品关键词" };
  const found = Object.entries(productDB).find(
    ([id, p]) => id === keyword || p.name.includes(keyword)
  );
  if (!found) return { ok: false, error: "NOT_FOUND", message: "未找到该商品" };
  const [id, p] = found;
  return { ok: true, data: { id, ...p, in_stock: p.stock > 0 } };
}
```

小白重点：

> 注意工具执行函数统一返回 Day05 的 `{ ok, data | error, message }` 结构。整个 Demo 从头到尾用这一套「结果协议」，上层代码就不用为每个工具写不同的判断。

---

### 1.4 组装第二步：Router 与错误兜底

把 Day04 的分发表和 Day05 的安全执行合到 `router.js`：

```js
// src/router.js
import { queryOrder, queryProduct } from "./tools.js";

// 工具名 → 执行函数（加新工具只需加一行）
const toolRouter = {
  get_order: (args) => queryOrder(args.order_id),
  get_product: (args) => queryProduct(args.keyword),
};

// 统一执行入口：兜住异常、统一返回结构
export async function executeToolSafe(name, args) {
  const handler = toolRouter[name];
  if (!handler) {
    return { ok: false, error: "UNKNOWN_TOOL", message: `未知工具：${name}` };
  }
  try {
    return await handler(args); // 执行函数本身已返回 {ok,...}
  } catch (err) {
    console.error(`[tool:${name}] 执行失败`, err); // 真实错误进日志
    return { ok: false, error: "TOOL_EXEC_ERROR", message: "工具执行出错" };
  }
}

// 技术错误码 → 用户友好话术（Day05）
const friendlyMessage = {
  TIMEOUT: "系统有点忙，稍等一下再试试好吗～",
  TOOL_EXEC_ERROR: "查询时出了点小问题，我帮您转接人工客服可以吗？",
  UNKNOWN_TOOL: "这个功能我暂时还不支持哦～",
  DEFAULT: "不好意思，出了点小状况，您可以稍后重试或联系人工客服。",
};
export const toUserMessage = (code) => friendlyMessage[code] || friendlyMessage.DEFAULT;
```

---

### 1.5 组装第三步：Agent 主循环

把 Day01 的 Loop 和 Day05 的 `MAX_TURNS` 兜底合到 `agent.js`，这是整个 Demo 的心脏：

```js
// src/agent.js
import { systemPrompt } from "./prompt.js";
import { tools } from "./tools.js";
import { executeToolSafe } from "./router.js";
import { callLLM } from "./llm.js";

const MAX_TURNS = 5; // Day05：安全绳，防死循环

// history 让 Demo 支持多轮对话（Day03）
export async function runAgent(userInput, history = []) {
  const messages = [
    { role: "system", content: systemPrompt },
    ...history,
    { role: "user", content: userInput },
  ];

  for (let turn = 0; turn < MAX_TURNS; turn++) {
    const msg = await callLLM({ messages, tools });
    messages.push(msg);

    // 出口 1：模型直接回答 → 结束
    if (!msg.tool_calls) {
      return { reply: msg.content, messages };
    }

    // 出口 2：模型要求调工具 → 执行、回喂、继续循环
    for (const call of msg.tool_calls) {
      let args = {};
      try {
        args = JSON.parse(call.function.arguments); // 参数是字符串，要 parse
      } catch {
        args = {}; // Day05：解析失败也不崩，交给工具去报 MISSING_ARG
      }
      const result = await executeToolSafe(call.function.name, args);
      messages.push({
        role: "tool",
        tool_call_id: call.id, // Day02：对应是哪次调用
        content: JSON.stringify(result),
      });
    }
  }

  // 出口 3：转满 MAX_TURNS 还没结果 → 兜底
  return { reply: "抱歉，这个问题我暂时处理不了，已为您转接人工客服。", messages };
}
```

小白重点：

> 整个 Demo 对外只暴露 `runAgent(userInput, history)` 一个函数：给它一句话（和历史），还你一句人话。前 5 天所有复杂度都被封在这个函数里面。这就是「好接口」——把复杂藏起来，只留一个简单的口子。

---

### 1.6 组装第四步：命令行交互入口

`demo.js` 让你能真的敲键盘和 Agent 对话，多轮之间保留 history：

```js
// demo.js
import readline from "node:readline/promises";
import { stdin as input, stdout as output } from "node:process";
import { runAgent } from "./src/agent.js";

const rl = readline.createInterface({ input, output });
let history = [];

console.log("🛎️  优选商城客服小优已上线（输入 exit 退出）\n");

while (true) {
  const userInput = await rl.question("你：");
  if (userInput.trim() === "exit") break;

  const { reply, messages } = await runAgent(userInput, history);
  console.log("小优：" + reply + "\n");

  // 保留本轮对话进 history，实现多轮上下文
  history = messages.slice(1); // 去掉 system，其余作为下一轮历史
}

rl.close();
console.log("已退出，感谢使用～");
```

小白重点：

> 多轮对话的关键就是把上一轮的 `messages`（去掉 system）当成下一轮的 `history` 传进去。这样用户说「那它多少钱」时，Agent 还记得上一句在聊哪个商品。

---

### 1.7 没有真实模型 API 时：mock 掉 callLLM

学习阶段如果还没接真实模型，可以在 `llm.js` 里 mock，让 Demo 照样能演示完整流程：

```js
// src/llm.js（mock 版：按关键词假装模型的决策）
export async function callLLM({ messages }) {
  const lastUser = [...messages].reverse().find((m) => m.role === "user")?.content || "";
  const lastTool = [...messages].reverse().find((m) => m.role === "tool");

  // 如果刚拿到工具结果，就"总结成人话"（模拟模型的第 2 轮）
  if (lastTool) {
    const r = JSON.parse(lastTool.content);
    if (!r.ok) return { role: "assistant", content: "没有查到相关信息，请核对一下～" };
    return { role: "assistant", content: "为您查到啦：" + JSON.stringify(r.data) };
  }

  // 第 1 轮：按关键词决定要不要调工具（模拟模型的意图判断）
  if (/订单|物流|发货/.test(lastUser)) {
    const id = (lastUser.match(/[A-Z]\d+/) || ["A123456"])[0];
    return mockToolCall("get_order", { order_id: id });
  }
  if (/多少钱|价格|库存|有货|P\d+/.test(lastUser)) {
    const kw = (lastUser.match(/P\d+/) || ["耳机"])[0];
    return mockToolCall("get_product", { keyword: kw });
  }
  // 其余走 FAQ / 直接回答
  return { role: "assistant", content: "关于这个问题：签收后 7 天可无理由退换哦～" };
}

function mockToolCall(name, args) {
  return {
    role: "assistant",
    tool_calls: [
      { id: "call_" + Date.now(), type: "function", function: { name, arguments: JSON.stringify(args) } },
    ],
  };
}
```

小白重点：

> mock 的目的不是"以假乱真"，而是"把流程跑顺"。等你接上真实模型，只需替换 `llm.js` 这一个文件，其余全不用动——这正是把「调模型」单独封装成一个文件的好处。

---

### 1.8 准备演示脚本

演示时别现场即兴，准备 5 条固定台词，覆盖主要能力和边界：

| # | 你输入 | 演示的能力 | 期望表现 |
|---|---|---|---|
| 1 | 帮我查下订单 A123456 到哪了 | 查订单（正常） | 调 get_order，回「已发货，预计明天送达」 |
| 2 | P1001 这个多少钱、有货吗 | 查商品（正常） | 调 get_product，回价格 199、有货 |
| 3 | 你们支持七天无理由退货吗 | FAQ 兜底 | 不调工具，用 FAQ 回答 |
| 4 | 我这订单能退多少钱？现在告诉我 | 边界/防越权 | 不编金额，引导人工客服 |
| 5 | 帮我查下订单 NOTEXIST | 查不到兜底 | 回「没查到，请核对订单号」 |

小白重点：

> 好的 Demo 脚本要「有正有反」：既展示能干什么（1、2、3），也展示不会乱来（4、5）。评委/面试官往往更看重后者——一个不会瞎编、不会越权的 Agent 才敢上线。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

不过今天你要「读自己的代码」——把组装好的 5 个文件从头读一遍，检查零件是否都接上了：

1. `agent.js` 的 `runAgent` 是否引用了 prompt / tools / router / llm 四方
2. `router.js` 里每个工具是否都在 `toolRouter` 注册了
3. 循环三个出口是否齐全（直接回答 / 调工具 / MAX_TURNS 兜底）
4. 错误话术 `toUserMessage` 有没有在兜底处真正用上
5. `demo.js` 的多轮 history 是否正确去掉了 system

建议在笔记里填一张「装配自检表」：

| 装配点 | 应该接哪个零件 | 我接对了吗 |
|---|---|---|
| System Prompt | prompt.js | |
| 工具清单 tools | tools.js | |
| 工具执行 | router.js 的 executeToolSafe | |
| 主循环 | agent.js 的 runAgent | |
| 调模型 | llm.js 的 callLLM | |
| 多轮上下文 | demo.js 的 history | |

---

## 3. 练习任务

### 练习 1：把 5 个文件组装起来

按 1.2 的结构建好文件，把 1.3~1.7 的代码填进去。自检：

- [ ] 5 个 `src/*.js` + `demo.js` 都在
- [ ] `import`/`export` 路径都对得上
- [ ] 没有把所有代码堆在一个文件里

---

### 练习 2：跑通 5 条演示脚本

运行 `node demo.js`，依次输入 1.8 表格里的 5 句话，把实际输出记下来：

| # | 你输入 | Agent 实际回复 | 是否符合期望 | 备注 |
|---|---|---|---|---|
| 1 | | | | |
| 2 | | | | |
| 3 | | | | |
| 4 | | | | |
| 5 | | | | |

目标：5 条全部符合期望。不符合的，回到对应零件（Prompt / 工具 / 兜底）去改。

---

### 练习 3：测一轮「多轮上下文」

连续输入两句，验证 Agent 记得上文：

```text
你：P1001 多少钱
小优：（回价格 199）
你：那还有货吗          ← 没说商品，但 Agent 应知道还在聊 P1001
小优：（回 P1001 有货/缺货）
```

如果第二句 Agent「忘了」在聊哪个商品，检查 `demo.js` 里 history 是否正确回传。

---

### 练习 4：写使用文档 README

给你的 Demo 写一份 `README.md`，让别人不看你的脸也能跑起来。至少包含：

```markdown
# 优选商城客服 Agent Demo

## 这是什么
一个会自己决定调用工具（查订单/查商品）的 AI 客服 Demo。

## 怎么跑
1. 安装 Node 18+
2. （可选）配置真实模型 API，否则默认用 mock
3. 运行：node demo.js
4. 输入示例：帮我查下订单 A123456

## 支持的能力
- 查订单 / 查商品 / 常见问题
- 查不到、越权、超时都有友好兜底

## 文件说明
（把 1.2 的表格搬进来）
```

小白重点：

> 「能跑」和「别人能跑」是两码事。README 的价值是把你脑子里的「隐性知识」写成显性步骤。写不出来，往往说明你的 Demo 还有没理清的地方。

---

## 4. JS/Node.js 类比

| Demo 概念 | Node.js / JS 类比 | 说明 |
|---|---|---|
| `runAgent` 单一入口 | Express app 的一个 handler | 复杂全藏在里面，对外只一个口子 |
| 拆成 5 个文件 | 按职责分 controller/service/util | 一个文件一个职责 |
| `toolRouter` | 路由表 / 依赖注入容器 | 名字找到对应实现 |
| `llm.js` 可替换 | 依赖抽象 / 适配器模式 | mock 和真实实现同一接口 |
| 多轮 history | session / 会话状态 | 记住上下文 |
| README | 项目的 onboarding 文档 | 别人照着就能跑 |

一句话类比：

> 组装 Demo ≈ 把写好的一堆 service、util、路由拼成一个能 `node demo.js` 启动的完整应用；`runAgent` 就是那个对外的 handler，前 5 天的零件都是它内部依赖的模块。这和你在 PHP 阶段把 Controller、Service、Model 拼成一个能跑的接口，是同一种「装配」能力。

---

## 5. AI Review 提问

```text
我正在学习 Week 15 Day 06：客服 Agent Demo。
我把前 5 天的零件组装成了一个完整 Demo（prompt/tools/router/agent/llm + demo 入口），
并跑通了 5 条演示脚本。代码和 README 如下：
（贴上你的项目结构、agent.js、以及 5 条脚本的实际输出）

请你按资深后端工程师标准帮我检查：
1. 我的文件拆分（职责划分）是否合理？有没有职责混在一起的文件？
2. runAgent 作为唯一入口，接口设计得好不好？循环三个出口是否都覆盖了？
3. 5 条演示脚本里，"防越权/查不到"这两条的表现是否真的安全（没编造、没暴露内部错误）？
4. 用"Express handler + 按职责分模块"类比是否准确？
5. 这个 Demo 距离能上线还差什么（鉴权、日志、限流、会话持久化、监控）？

请用中文输出：我做得好的地方、问题清单、修改建议、下一步。
```

---

## 6. 今日产出

- [ ] 按职责拆分的 5 个 `src/*.js` 文件
- [ ] 命令行交互入口 `demo.js`（支持多轮）
- [ ] 5 条演示脚本的实际输出记录
- [ ] 一次多轮上下文的验证记录
- [ ] Demo 的 README 使用文档
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能把前 5 天的零件组装成一个可运行的整体
- [ ] `runAgent` 作为唯一入口，能「输入一句话、返回一句人话」
- [ ] 查订单 / 查商品 / FAQ 三种意图都能正确响应
- [ ] 越权、查不到、超时都走友好兜底，不暴露内部错误
- [ ] 支持至少两轮的上下文记忆
- [ ] 写出别人照着就能跑起来的 README

---

## 8. 今日自测题

### 8.1 组装 Demo 这一天，主要产出的是什么能力？

参考答案：

> ✅ 不是新知识，而是「整合能力」——把前 5 天散落的零件（Prompt、工具、路由、循环、兜底）拼成一个可运行、可讲解、可复现的整体。

---

### 8.2 为什么要把代码拆成 prompt/tools/router/agent/llm 多个文件？

参考答案：

> ✅ 一个文件一个职责，讲得清、改得动、好测试。判断拆得对不对：能不能用一句话说清每个文件干嘛。堆在一个文件里虽然能跑，但没法维护和讲解。

---

### 8.3 Demo 对外只暴露一个 `runAgent` 函数有什么好处？

参考答案：

> ✅ 「把复杂藏起来，只留一个简单口子」。前 5 天所有复杂度都封在 runAgent 内部，调用方只需「给一句话、拿一句话」，这是好接口的标志。

---

### 8.4 多轮对话是怎么实现的？

参考答案：

> ✅ 把上一轮的 messages（去掉 system）当作下一轮的 history 传回 runAgent。这样 Agent 能记得之前聊过什么，用户说「那它多少钱」时才知道在指哪个商品。

---

### 8.5 演示脚本为什么要包含「越权」和「查不到」这类反面场景？

参考答案：

> ✅ 好 Demo 要有正有反。正面展示能干什么，反面展示不会乱来（不编金额、不暴露堆栈）。一个不会瞎编、不会越权的 Agent 才敢上线，反面场景往往更被看重。

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
我正在进行 Week 15 Day 06：客服 Agent Demo 的学习。
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
