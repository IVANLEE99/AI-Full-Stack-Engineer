# Week 14 Day 01：MCP 协议概述

> 所属周：Week 14：MCP Protocol + MCP Server  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`mcp-server`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 MCP 协议里的 Server / Client / Tool 三个核心角色，能画出一次完整的调用链路。

今天你要真正掌握这一句话：

> MCP（Model Context Protocol）是一套让 AI 模型和外部工具对话的标准协议。Client（AI 一侧，比如 Cursor）向 Server（你写的工具服务）发请求，Server 通过 `listTools` 告诉 AI 有哪些能力，通过 `callTool` 真正执行某个能力；这就像浏览器（Client）请求后端 API（Server），只不过说话的规则换成了 JSON-RPC。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先搞清楚 MCP 要解决什么问题
2. 理解 MCP 里的三个角色：Host / Client / Server
3. 理解 MCP 的两个核心动作：`listTools` 和 `callTool`
4. 理解 MCP 的五类能力：Tool / Resource / Prompt（今天重点是 Tool）
5. 理解底层消息格式：JSON-RPC 2.0
6. 理解传输方式：stdio 和 SSE
7. 用 Node/Python 类比，把 MCP 和你熟悉的 API 概念对上号
8. 列出 MCP 和 REST API 的差异表
9. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 MCP 要解决什么问题

先想一个场景。你在用 AI 编程助手（比如 Cursor），你问它：

```text
帮我看看 users 表里有多少条 dev 环境的测试数据
```

AI 模型本身是不能直接连你的数据库的。它只会「说话」，不会「动手」。它需要一个中间人：

```text
AI 模型  →  某个能真正执行查询的程序  →  数据库
```

在 MCP 出现之前，每个工具（数据库、文件系统、Git、内部接口）都要各自实现一套「怎么把能力暴露给 AI」的对接方式，非常乱。

MCP 就是来统一这件事的。它规定了一套标准协议，让：

- 任何 AI 客户端（Cursor、Claude Desktop、你自己的 Agent）
- 都能用同一种方式，去调用任何一个符合 MCP 的工具服务

小白重点：

> MCP 之于「AI 调用工具」，就像 USB 之于「电脑连接外设」。有了统一接口，鼠标、键盘、U 盘都能插同一个口。

---

### 1.2 理解 MCP 的三个角色

MCP 里有三个角色，务必记牢：

| 角色 | 英文 | 是谁 | 类比 |
|---|---|---|---|
| 宿主 | Host | 运行 AI 的应用，比如 Cursor、Claude Desktop | 浏览器程序本身 |
| 客户端 | Client | Host 内部负责和某个 Server 通信的连接器 | 浏览器里的一个 HTTP 连接 |
| 服务端 | Server | 你写的、真正提供工具能力的程序 | 后端 API 服务 |

它们的关系：

```text
┌─────────────────── Host（Cursor）───────────────────┐
│                                                     │
│   AI 模型                                            │
│     │                                               │
│   Client A ───────► Server A（数据库工具）           │
│   Client B ───────► Server B（文件系统工具）         │
│   Client C ───────► Server C（Git 工具）             │
│                                                     │
└─────────────────────────────────────────────────────┘
```

关键点：

- 一个 Host 里可以有多个 Client
- 一个 Client 只连一个 Server
- 你作为后端工程师，主要工作是写 Server

小白重点：

> 你今后要写的东西叫 MCP Server。Client 和 Host 通常是现成的（Cursor 就自带）。

---

### 1.3 理解两个核心动作：listTools 和 callTool

Server 和 Client 之间，最核心的对话只有两句：

**第一句：Client 问「你有哪些能力？」**

```text
Client → Server:  listTools
Server → Client:  我有 3 个工具：query_users、list_tables、ping
```

**第二句：Client 说「帮我执行这个能力」**

```text
Client → Server:  callTool（name=query_users, arguments={ env: "dev" }）
Server → Client:  执行结果：一共 42 条
```

用一张图表示一次完整调用：

```text
用户: 帮我数一下 dev 环境的用户
   │
   ▼
AI 模型: 我需要一个能查用户的工具
   │
   ▼
Client → Server: listTools           ← 先看有哪些工具
   │
   ▼
Server → Client: [query_users, ...]   ← 返回工具清单
   │
   ▼
AI 模型: query_users 正好合适，参数填 env=dev
   │
   ▼
Client → Server: callTool(query_users, {env:"dev"})
   │
   ▼
Server: 执行查询 SELECT count(*) ...
   │
   ▼
Server → Client: { count: 42 }
   │
   ▼
AI 模型: dev 环境一共有 42 个用户
```

小白重点：

> `listTools` ≈ 后端提供一份「接口文档」；`callTool` ≈ 真正去请求某个接口。你只要记住这两个动作，就抓住了 MCP 的 80%。

---

### 1.4 理解 MCP 的三类能力：Tool / Resource / Prompt

MCP Server 能对外暴露的东西不止 Tool，主要有三类：

| 能力类型 | 作用 | 类比 | 谁触发 |
|---|---|---|---|
| Tool（工具） | 让 AI 执行一个动作，可能有副作用 | 后端的 POST 接口 / 函数 | AI 模型自己决定调用 |
| Resource（资源） | 让 AI 读取一份数据/文件内容 | 后端的 GET 接口 / 静态文件 | 应用或用户挑选后读取 |
| Prompt（提示词模板） | 预置好的提示词模板，供用户选用 | 常用话术模板 | 用户主动选择 |

举例说明区别：

```text
Tool     ：create_ticket    → 创建一张工单（会写数据，有副作用）
Resource ：file:///readme    → 读取 README 内容（只读）
Prompt   ：code_review       → 一键套用「代码审查」提示词模板
```

小白重点：

> 本周我们主攻 Tool，因为它最常用、最能体现「AI 动手做事」。Resource 和 Prompt 先知道概念即可。区分 Tool 和 Resource 的关键：Tool 会「做事」，Resource 只「给数据看」。

---

### 1.5 理解底层消息格式：JSON-RPC 2.0

Client 和 Server 到底用什么格式说话？答案是 **JSON-RPC 2.0**。

你不需要背它，但要能看懂。一条 JSON-RPC 请求长这样：

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/list",
  "params": {}
}
```

对应的响应长这样：

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "tools": [
      { "name": "query_users", "description": "查询 dev 用户数量" }
    ]
  }
}
```

拆开看每个字段：

| 字段 | 含义 | 类比 REST |
|---|---|---|
| `jsonrpc` | 协议版本，固定 `"2.0"` | 无对应，属于协议头 |
| `id` | 请求编号，用来把响应和请求配对 | 无对应（HTTP 靠连接配对） |
| `method` | 要调用的方法名，如 `tools/list` | 相当于「路径 + 动作」 |
| `params` | 方法参数 | 相当于 body / query |
| `result` | 成功时的返回 | HTTP 200 的 body |
| `error` | 失败时的错误对象 | HTTP 4xx/5xx + body |

出错时返回的是 `error` 而不是 `result`：

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "error": {
    "code": -32602,
    "message": "Invalid params: env is required"
  }
}
```

小白重点：

> JSON-RPC 和 REST 最大的不同：REST 靠「URL + HTTP 方法」区分操作，JSON-RPC 靠 body 里的 `method` 字段区分操作。所有请求可以走同一个通道。

---

### 1.6 理解传输方式：stdio 与 SSE

Client 和 Server 之间的消息，靠什么「管道」传输？MCP 支持两种：

**方式 1：stdio（标准输入输出）**

Server 作为一个子进程被 Host 启动，双方通过进程的标准输入（stdin）和标准输出（stdout）交换 JSON-RPC 消息。

```text
Cursor（Host）
   │ 启动子进程: node server.js
   │
   │  ── 写 stdin ──►  ┌──────────────┐
   │                   │  MCP Server  │
   │  ◄── 读 stdout ── └──────────────┘
```

特点：

- 本地运行，最简单，最常用
- 不占端口，不走网络
- 适合「跑在你自己电脑上的工具」

**方式 2：SSE / HTTP（网络传输）**

Server 作为一个 HTTP 服务运行，Client 通过网络连接，用 SSE（Server-Sent Events）接收服务端推送。

```text
Cursor（Host）  ── HTTP/SSE ──►  远程 MCP Server（部署在服务器上）
```

特点：

- 可远程部署，多人共享
- 需要考虑鉴权、网络安全
- 适合「团队共用的工具服务」

对比表：

| 对比项 | stdio | SSE/HTTP |
|---|---|---|
| 运行位置 | 本地子进程 | 本地或远程服务 |
| 传输通道 | stdin/stdout | HTTP + SSE |
| 是否占端口 | 否 | 是 |
| 配置复杂度 | 低 | 中高 |
| 适用场景 | 个人本地工具 | 团队共享工具 |
| 安全要求 | 低 | 高（需鉴权） |

小白重点：

> 我们本周全程用 stdio。你只要记住：stdio 就是「Host 把 Server 当成一个子程序启动，然后通过它的输入输出管道说话」。

---

### 1.7 用 Node/Python 类比 MCP 的全貌

把上面所有概念串起来，用一个最小的 Node 版 MCP Server 骨架感受一下（今天只看，不用运行）：

```ts
// server.ts —— 一个 MCP Server 的最小骨架（TypeScript + 官方 SDK）
import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  ListToolsRequestSchema,
  CallToolRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";

// 1. 创建 Server 实例（相当于 new Express()）
const server = new Server(
  { name: "demo-server", version: "1.0.0" },
  { capabilities: { tools: {} } }
);

// 2. 响应 listTools：告诉 Client 我有哪些工具
server.setRequestHandler(ListToolsRequestSchema, async () => {
  return {
    tools: [
      {
        name: "ping",
        description: "健康检查，返回 pong",
        inputSchema: { type: "object", properties: {} },
      },
    ],
  };
});

// 3. 响应 callTool：真正执行某个工具
server.setRequestHandler(CallToolRequestSchema, async (request) => {
  if (request.params.name === "ping") {
    return { content: [{ type: "text", text: "pong" }] };
  }
  throw new Error("Unknown tool");
});

// 4. 用 stdio 传输启动
const transport = new StdioServerTransport();
await server.connect(transport);
```

对照你熟悉的 Express：

```js
// Express 版对照：注册路由 ≈ 注册 Tool
const app = express();

app.get("/tools", (req, res) => {          // ≈ listTools
  res.json({ tools: ["ping"] });
});

app.post("/tools/ping", (req, res) => {    // ≈ callTool
  res.json({ result: "pong" });
});

app.listen(3000);                          // ≈ server.connect(transport)
```

Python 版骨架（官方 `mcp` 库，供 Python 背景的同学参考）：

```python
# server.py —— Python 版 MCP Server 最小骨架
from mcp.server.fastmcp import FastMCP

mcp = FastMCP("demo-server")

@mcp.tool()
def ping() -> str:
    """健康检查，返回 pong"""
    return "pong"

if __name__ == "__main__":
    mcp.run()  # 默认用 stdio 传输
```

小白重点：

> 注册 Tool ≈ 注册路由。`listTools` ≈ 列出所有路由，`callTool` ≈ 命中并执行某条路由。你已经会写 Express/Yii2 的路由，MCP Server 的心智模型是一样的。

---

### 1.8 MCP 和 REST API 的差异

这是今天最重要的对比，务必自己也能复述：

| 对比项 | REST API | MCP |
|---|---|---|
| 使用者 | 前端 / 其他后端 | AI 模型 |
| 定位方式 | URL 路径 + HTTP 方法 | JSON-RPC 的 `method` 字段 |
| 能力发现 | 靠人读 OpenAPI 文档 | 靠 `listTools` 让 AI 自动发现 |
| 描述给谁看 | 给开发者看 | 给 AI 看（description 很关键） |
| 传输 | HTTP | stdio 或 SSE/HTTP |
| 消息格式 | 自由（通常 JSON） | 固定 JSON-RPC 2.0 |
| 调用发起者 | 人写代码调用 | AI 自主决定调用 |

一个关键差异要特别强调：

> 在 REST 里，`description`（接口说明）是写给人看的，写不写、写好写坏不影响机器调用。在 MCP 里，`description` 是写给 AI 看的，写得不清楚，AI 就不知道什么时候该用这个工具，或者会用错参数。**Tool 的 description 是生产力，不是注释。**

---

## 2. 源码阅读

- MCP 官方文档（概念页）

> 说明：本日重点是概念，官方文档以核心概念为主。学习时可对照官方 `modelcontextprotocol` 的介绍页，抓住 Server / Client / Tool / Transport 四个词。

阅读时重点找这些内容：

1. Host / Client / Server 各自的职责边界
2. `tools/list` 和 `tools/call` 两个方法的定义
3. Tool 的三要素：`name`、`description`、`inputSchema`
4. stdio 传输的启动方式
5. JSON-RPC 请求/响应的字段结构

建议你在笔记里写出这张表：

| MCP 概念 | 一句话解释 | Node/REST 类比 |
|---|---|---|
| Host | 运行 AI 的应用 | 浏览器 |
| Client | Host 里连某个 Server 的连接器 | 一个 HTTP 连接 |
| Server | 你写的工具服务 | 后端 API |
| Tool | 一个可被 AI 调用的能力 | 一个接口 / 函数 |
| listTools | 列出所有工具 | 读 OpenAPI 文档 |
| callTool | 执行某个工具 | 请求某个接口 |
| JSON-RPC | 底层消息格式 | HTTP 报文 |
| stdio | 本地进程管道传输 | 本地 socket |

---

## 3. 练习任务

### 练习 1：画出一次完整调用链路

在笔记里，用文字或箭头画出「用户提问 → AI → listTools → callTool → 返回结果」的完整链路。要求至少包含 6 个节点。

参考答案见 1.3 的那张图。自己先画，再对照。

---

### 练习 2：给三个能力分类

判断下面每个能力属于 Tool、Resource 还是 Prompt：

| 能力 | 你的判断 |
|---|---|
| 读取项目 README 的内容 |  |
| 在数据库里创建一条测试订单 |  |
| 套用「帮我写单元测试」的话术模板 |  |
| 查询 dev 环境用户数量 |  |
| 读取一份 JSON 配置文件 |  |

参考答案：

| 能力 | 类型 | 理由 |
|---|---|---|
| 读取项目 README 的内容 | Resource | 只读数据 |
| 在数据库里创建一条测试订单 | Tool | 有副作用的动作 |
| 套用「帮我写单元测试」的话术模板 | Prompt | 提示词模板 |
| 查询 dev 环境用户数量 | Tool | 执行查询动作 |
| 读取一份 JSON 配置文件 | Resource | 只读数据 |

---

### 练习 3：手写一条 JSON-RPC 请求和响应

假设有一个工具 `list_tables`，不需要参数，返回表名数组。请手写：

1. Client 发出的 `tools/call` 请求
2. Server 返回的成功响应

参考答案：

请求：

```json
{
  "jsonrpc": "2.0",
  "id": 7,
  "method": "tools/call",
  "params": {
    "name": "list_tables",
    "arguments": {}
  }
}
```

响应：

```json
{
  "jsonrpc": "2.0",
  "id": 7,
  "result": {
    "content": [
      { "type": "text", "text": "users, orders, products" }
    ]
  }
}
```

---

### 练习 4：列 MCP 与 REST 差异 8 条

自己先填，再对照 1.8 的表格。至少列出 8 条差异。

| # | 维度 | REST | MCP |
|---|---|---|---|
| 1 | 使用者 |  |  |
| 2 | 定位方式 |  |  |
| 3 | 能力发现 |  |  |
| 4 | description 给谁看 |  |  |
| 5 | 传输 |  |  |
| 6 | 消息格式 |  |  |
| 7 | 调用发起者 |  |  |
| 8 | 参数校验 |  |  |

---

### 练习 5：用大白话解释 MCP

用不超过 3 句话，向一个完全不懂技术的朋友解释「MCP 是什么」。

参考版本：

> MCP 是一套让 AI 助手能真正「动手做事」的标准。以前 AI 只会聊天，有了 MCP，它能通过一个叫 Server 的小程序去查数据库、读文件、调接口。这就像给 AI 装了一双手，而 MCP 规定了这双手怎么和各种工具握手。

---

## 4. JS/Node.js 类比

| MCP 概念 | Node.js / Web 类比 | 说明 |
|---|---|---|
| MCP Server | Express/Koa 应用 | 提供能力的服务端 |
| Tool | 一个路由 / 一个导出函数 | 可被调用的能力单元 |
| listTools | 列出所有路由 / 读 OpenAPI | 能力发现 |
| callTool | 请求某个接口 / 调用某个函数 | 真正执行 |
| inputSchema | 参数校验（zod / joi） | 描述并约束入参 |
| JSON-RPC | HTTP 报文 | 底层消息格式 |
| stdio 传输 | 本地进程 stdin/stdout | 本地通信管道 |
| SSE 传输 | EventSource / 长连接推送 | 网络通信管道 |
| Client | 一个 fetch 客户端实例 | 发起调用的一方 |
| Host | 浏览器 / 应用外壳 | 运行 AI 的宿主 |

---

## 5. AI Review 提问

完成练习后，把你的笔记和理解贴给 AI，然后问：

```text
我正在学习 MCP Day 01：协议概述，理解了 Server/Client/Tool、listTools/callTool、JSON-RPC、stdio。

请你按资深工程师标准帮我检查：

1. 我对 Host/Client/Server 三个角色的区分是否准确？
2. 我理解的 listTools 和 callTool 流程对不对？
3. 我对 Tool/Resource/Prompt 的分类是否正确？
4. 我用 REST/Express 做的类比有没有误导？
5. 如果这是企业里给团队用的 MCP Server，我还要注意哪些概念？

请用中文输出：
- 我理解正确的地方
- 我理解错误或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [ ] MCP 三角色（Host/Client/Server）笔记
- [ ] 一张完整调用链路图（练习 1）
- [ ] 能力分类表（练习 2）
- [ ] 手写的 JSON-RPC 请求/响应（练习 3）
- [ ] MCP 与 REST 差异表 8 条（练习 4）
- [ ] MCP 大白话解释（练习 5）
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说出 MCP 要解决什么问题
- [ ] 能区分 Host / Client / Server
- [ ] 能解释 listTools 和 callTool 的作用
- [ ] 能区分 Tool / Resource / Prompt
- [ ] 能看懂一条 JSON-RPC 请求的每个字段
- [ ] 能说出 stdio 和 SSE 两种传输的区别
- [ ] 能用 REST/Express 做出准确类比
- [ ] 能列出至少 8 条 MCP 与 REST 的差异

---

## 8. 今日自测题

### 8.1 MCP 里的 Server 是谁写的？它扮演什么角色？

参考答案：

> ✅ Server 是后端工程师（也就是你）写的程序，负责真正提供工具能力。它响应 Client 的 `listTools`（报告有哪些工具）和 `callTool`（执行工具），是「动手做事」的一方。

---

### 8.2 listTools 和 callTool 分别做什么？

参考答案：

> ✅ `listTools` 让 Client/AI 发现 Server 有哪些工具，返回工具清单（名字、描述、参数结构）。`callTool` 让 Client 请求执行某个具体工具，Server 执行后返回结果。前者是「看菜单」，后者是「点菜」。

---

### 8.3 Tool 和 Resource 有什么区别？

参考答案：

> ✅ Tool 是让 AI 执行一个动作，可能有副作用（如写数据、调接口）；Resource 是让 AI 读取一份只读数据（如文件内容）。区分关键：Tool 会「做事」，Resource 只「给数据看」。

---

### 8.4 JSON-RPC 里的 `method` 字段起什么作用？和 REST 的路径有什么不同？

参考答案：

> ✅ `method` 指明要调用哪个方法（如 `tools/list`、`tools/call`）。REST 靠 URL 路径 + HTTP 方法区分操作，JSON-RPC 靠 body 里的 `method` 区分操作，所有请求可以走同一个通道。

---

### 8.5 为什么说 Tool 的 description 是「生产力」而不是「注释」？

参考答案：

> ✅ 因为 description 是写给 AI 看的。AI 靠它判断什么时候该用这个工具、怎么填参数。description 写得含糊，AI 就会漏用或用错。在 REST 里 description 只给人看，可有可无；在 MCP 里它直接决定 AI 能否正确调用。

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
我正在进行 Week 14 Day 01：MCP 协议概述 的学习。
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
