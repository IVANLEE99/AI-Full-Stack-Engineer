# Week 14 Day 02：现有 MCP Server 源码

> 所属周：Week 14：MCP Protocol + MCP Server  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`mcp-server`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

读懂一个真实 MCP Server 里 `listTools` 与 `callTool` 的实现，能画出 Tool 的注册与执行流程。

今天你要真正掌握这一句话：

> 一个 MCP Server 的骨架永远是三步：创建 Server 实例 → 注册 `ListTools` 处理器（告诉 AI 有哪些工具）→ 注册 `CallTool` 处理器（根据工具名分发执行）。看任何 MCP Server 源码，都先找这三块，其它都是细节。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先准备好可运行的 MCP SDK 环境
2. 通读一个最小可运行 Server 的完整源码
3. 定位并读懂 `ListToolsRequestSchema` 处理器
4. 理解 `inputSchema` 是怎么描述参数的
5. 定位并读懂 `CallToolRequestSchema` 处理器
6. 理解 callTool 里的「按名字分发」模式
7. 理解返回值 `content` 的结构
8. 理解错误处理（isError 与抛异常）
9. 梳理出完整的 Tool 注册与调用流程图
10. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 准备可运行环境

先确认 Node 环境可用：

```bash
node -v
npm -v
```

新建一个练习目录并初始化：

```bash
mkdir mcp-read-demo
cd mcp-read-demo
npm init -y
npm install @modelcontextprotocol/sdk zod
```

在 `package.json` 里加上 `"type": "module"`（用 ESM 语法）：

```json
{
  "type": "module",
  "dependencies": {
    "@modelcontextprotocol/sdk": "^1.0.0",
    "zod": "^3.23.0"
  }
}
```

小白重点：

> `zod` 是一个 JS 里非常常用的「运行时参数校验库」，作用类似 PHP 里对请求参数做 validate。MCP 里我们常用它来定义和校验 Tool 的入参。

---

### 1.2 通读一个最小可运行 Server

下面是一个完整、可运行的 MCP Server，包含两个只读工具。请先整体读一遍，建立全貌，后面再逐块拆解。把它保存为 `server.js`：

```js
// server.js —— 一个完整可运行的只读 MCP Server
import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  ListToolsRequestSchema,
  CallToolRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";

// —— 模拟数据（真实项目里会来自数据库，这里先写死）——
const FAKE_DB = {
  tables: ["users", "orders", "products"],
  users: [
    { id: 1, name: "Tom", env: "dev" },
    { id: 2, name: "Jerry", env: "dev" },
    { id: 3, name: "Alice", env: "test" },
  ],
};

// 1. 创建 Server 实例
const server = new Server(
  { name: "read-demo-server", version: "1.0.0" },
  { capabilities: { tools: {} } }
);

// 2. 注册 listTools 处理器
server.setRequestHandler(ListToolsRequestSchema, async () => {
  return {
    tools: [
      {
        name: "list_tables",
        description: "列出数据库里所有表名。当用户想知道有哪些表时使用。",
        inputSchema: {
          type: "object",
          properties: {},
        },
      },
      {
        name: "count_users",
        description:
          "按环境统计用户数量。当用户想知道某个环境有多少用户时使用。",
        inputSchema: {
          type: "object",
          properties: {
            env: {
              type: "string",
              enum: ["dev", "test"],
              description: "环境名，只允许 dev 或 test",
            },
          },
          required: ["env"],
        },
      },
    ],
  };
});

// 3. 注册 callTool 处理器
server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  if (name === "list_tables") {
    return {
      content: [{ type: "text", text: FAKE_DB.tables.join(", ") }],
    };
  }

  if (name === "count_users") {
    const env = args?.env;
    if (env !== "dev" && env !== "test") {
      return {
        content: [{ type: "text", text: "env 只能是 dev 或 test" }],
        isError: true,
      };
    }
    const count = FAKE_DB.users.filter((u) => u.env === env).length;
    return {
      content: [{ type: "text", text: `${env} 环境共有 ${count} 个用户` }],
    };
  }

  // 未知工具：抛错
  throw new Error(`Unknown tool: ${name}`);
});

// 4. 用 stdio 传输启动
const transport = new StdioServerTransport();
await server.connect(transport);
console.error("read-demo-server 已启动（stdio）");
```

小白重点：

> 注意最后一行用的是 `console.error` 而不是 `console.log`。因为 stdio 传输下，**stdout 被 JSON-RPC 消息占用了**，任何日志都必须走 stderr，否则会污染协议消息、导致 Client 解析失败。这是 MCP 新手最常踩的坑。

---

### 1.3 读懂 listTools 处理器

聚焦第 2 块代码：

```js
server.setRequestHandler(ListToolsRequestSchema, async () => {
  return { tools: [ /* 工具数组 */ ] };
});
```

它做的事：

- 当 Client 发来 `tools/list` 请求时，这个函数被调用
- 返回一个 `tools` 数组，每个元素描述一个工具

每个工具有三要素：

| 字段 | 作用 | 给谁看 |
|---|---|---|
| `name` | 工具的唯一标识，callTool 靠它分发 | 机器 |
| `description` | 工具用途说明，AI 靠它判断何时调用 | AI |
| `inputSchema` | 参数结构（JSON Schema） | AI + 校验 |

类比后端提供接口文档：

```js
// listTools 返回的工具清单 ≈ OpenAPI 的接口列表
{
  name: "count_users",          // ≈ operationId / 路径
  description: "按环境统计...",   // ≈ summary
  inputSchema: { ... }          // ≈ requestBody schema
}
```

小白重点：

> `listTools` 不执行任何业务逻辑，它只是「报菜单」。真正做事的是 callTool。

---

### 1.4 读懂 inputSchema

`inputSchema` 用的是 JSON Schema 格式，描述这个工具接受什么参数。看 `count_users` 的：

```js
inputSchema: {
  type: "object",
  properties: {
    env: {
      type: "string",
      enum: ["dev", "test"],
      description: "环境名，只允许 dev 或 test",
    },
  },
  required: ["env"],
}
```

逐行解释：

| 片段 | 含义 |
|---|---|
| `type: "object"` | 参数整体是一个对象 |
| `properties.env` | 有一个字段叫 env |
| `type: "string"` | env 是字符串 |
| `enum: ["dev","test"]` | env 只能取这两个值 |
| `description` | 告诉 AI 这个字段是什么 |
| `required: ["env"]` | env 是必填 |

对照你熟悉的校验写法：

```js
// zod 版（更简洁，很多 MCP 项目用它再转成 JSON Schema）
import { z } from "zod";
const schema = z.object({
  env: z.enum(["dev", "test"]).describe("环境名，只允许 dev 或 test"),
});
```

```php
// PHP 后端里类似的参数校验心智（Yii2 rules 示意）
public function rules(): array
{
    return [
        ['env', 'required'],
        ['env', 'in', 'range' => ['dev', 'test']],
    ];
}
```

小白重点：

> `inputSchema` 有双重作用：一是告诉 AI「这个工具要什么参数、每个参数什么含义」，二是可以用来做参数校验。写得越清楚，AI 填参数越准。

---

### 1.5 读懂 callTool 处理器

聚焦第 3 块代码，它的骨架是一个「按名字分发」的结构：

```js
server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  if (name === "list_tables") { /* 执行 A */ }
  if (name === "count_users") { /* 执行 B */ }

  throw new Error(`Unknown tool: ${name}`);
});
```

关键点：

- `request.params.name` 是要调用的工具名
- `request.params.arguments` 是 AI 传来的参数对象
- 根据 name 分发到不同的执行逻辑
- 匹配不到就抛错

这个「按名字分发」的模式，等价于后端里的路由分发：

```js
// Express 里靠路径分发，MCP 里靠 name 分发
switch (name) {
  case "list_tables": return handleListTables();
  case "count_users": return handleCountUsers(args);
  default: throw new Error("Unknown tool");
}
```

小白重点：

> callTool 是「一个入口 + 内部分发」。所有工具调用都进同一个函数，再靠 `name` 分流。工具多了以后，通常会把每个工具的逻辑抽成独立函数，callTool 只负责分发。

---

### 1.6 读懂返回值 content 结构

callTool 成功时返回的结构是固定的：

```js
return {
  content: [
    { type: "text", text: "dev 环境共有 2 个用户" }
  ],
};
```

`content` 是一个数组，每个元素是一段内容。最常用的是 `type: "text"`。也可以返回多段：

```js
return {
  content: [
    { type: "text", text: "查询完成" },
    { type: "text", text: "结果：users=2, orders=5" },
  ],
};
```

对比 REST 返回：

| 场景 | REST | MCP callTool |
|---|---|---|
| 成功返回 | `res.json({ count: 2 })` | `{ content: [{ type:"text", text:"..." }] }` |
| 返回结构 | 自由定义 | 固定 content 数组 |
| 内容类型 | 由 Content-Type 决定 | 由每段的 type 决定 |

小白重点：

> AI 最终看到的是 content 里的文本。所以返回的文本要「人和 AI 都能读懂」，比如 `dev 环境共有 2 个用户`，而不是干巴巴的 `2`。

---

### 1.7 读懂错误处理

callTool 有两种表达「出错」的方式：

**方式 1：返回 isError（业务错误，希望 AI 看到并处理）**

```js
return {
  content: [{ type: "text", text: "env 只能是 dev 或 test" }],
  isError: true,
};
```

**方式 2：抛异常（协议级/未知错误）**

```js
throw new Error(`Unknown tool: ${name}`);
```

区别：

| 方式 | 用于 | AI 能看到吗 | 类比 |
|---|---|---|---|
| `isError: true` | 业务失败，想让 AI 知道原因并调整 | 能，text 会给 AI | HTTP 200 + 业务错误码 |
| 抛异常 | 不该发生的错误（未知工具等） | 转成协议错误 | HTTP 500 |

小白重点：

> 参数不对、查不到数据这类「预期内的失败」，用 `isError: true` 返回文本，让 AI 有机会重试或换参数。真正的意外（未知工具、程序崩溃），才抛异常。

---

### 1.8 完整流程图

把今天读的东西串成一张图：

```text
启动阶段:
  new Server()  →  注册 ListTools 处理器  →  注册 CallTool 处理器
        →  server.connect(stdio)  →  等待请求

Client 连接后:
  ① Client → tools/list
        → ListTools 处理器返回 [list_tables, count_users]

  ② AI 决定调用 count_users(env=dev)
     Client → tools/call { name:"count_users", arguments:{env:"dev"} }
        → CallTool 处理器
            → 按 name 分发到 count_users 逻辑
            → 校验 env
            → 过滤统计
            → return { content:[{type:"text", text:"dev 环境共有 2 个用户"}] }
        → Client 收到结果 → AI 组织回答给用户
```

---

## 2. 源码阅读

- `mcp-server/*/src/index.ts`（或本练习的 `server.js`）

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。若暂无企业内 Server，可直接用 1.2 的 `server.js` 作为阅读对象。

阅读时按这个清单逐项定位：

1. 找到 `new Server(...)`：看它的 name、version、capabilities
2. 找到 `ListToolsRequestSchema` 的处理器：数一共注册了几个工具
3. 对每个工具，记录 `name` / `description` / `inputSchema`
4. 找到 `CallToolRequestSchema` 的处理器：看它怎么按 name 分发
5. 找到返回值：确认是 `{ content: [...] }` 结构
6. 找到错误处理：是 `isError` 还是抛异常
7. 找到传输启动：是 `StdioServerTransport` 还是 SSE

建议在笔记里整理成这张表：

| 工具名 | 用途（description 摘要） | 入参 | 有无副作用 |
|---|---|---|---|
| list_tables |  |  | 无（只读） |
| count_users |  |  | 无（只读） |

---

## 3. 练习任务

### 练习 1：跑通并观察 listTools

在 `mcp-read-demo` 目录里，用官方提供的调试器直接检查工具清单：

```bash
npx @modelcontextprotocol/inspector node server.js
```

这会打开一个网页版调试器（MCP Inspector）。在里面：

1. 点击连接
2. 打开 Tools 面板，确认能看到 `list_tables` 和 `count_users`
3. 观察每个工具的 description 和参数

小白重点：

> MCP Inspector 是官方调试工具，相当于 REST 世界里的 Postman。以后开发 Server 都靠它先测通，再接入 Cursor。

---

### 练习 2：在 Inspector 里调用 callTool

继续在 Inspector 里：

1. 选中 `count_users`，参数填 `{ "env": "dev" }`，执行，观察返回
2. 参数填 `{ "env": "prod" }`，执行，观察 isError 返回
3. 选中 `list_tables`，无参数执行，观察返回

把三次调用的返回结果抄进笔记。

---

### 练习 3：给源码加注释

把 `server.js` 复制一份，逐行加上你自己的中文注释，重点标注：

- 哪一块是 listTools
- 哪一块是 callTool
- 哪一行做了参数校验
- 哪一行是 stdio 启动

目标：能不看讲解，自己讲清楚每一块的作用。

---

### 练习 4：梳理 Tool 注册流程（文字版）

在笔记里用文字写出「从启动到一次成功调用」的完整流程，至少 6 步。参考 1.8 的流程图，但要用自己的话。

---

### 练习 5：找出「日志污染」风险

把 `server.js` 里的 `console.error(...)` 改成 `console.log(...)`，重新用 Inspector 连接，观察是否报错或行为异常。观察后改回 `console.error`。

参考结论：

> 用 `console.log` 会把日志写进 stdout，和 JSON-RPC 消息混在一起，可能导致 Client 解析失败或连接异常。stdio 模式下日志必须走 stderr（`console.error`）。

---

## 4. JS/Node.js 类比

| MCP Server 概念 | Node/后端 类比 | 说明 |
|---|---|---|
| `new Server()` | `new Express()` | 创建服务实例 |
| ListTools 处理器 | 返回 OpenAPI 接口列表的路由 | 能力清单 |
| CallTool 处理器 | 路由分发器 + controller | 按名字执行 |
| `request.params.name` | 请求路径 | 决定走哪个逻辑 |
| `request.params.arguments` | 请求 body | 入参 |
| `inputSchema` | zod/joi 校验 schema | 描述并约束入参 |
| `content` 返回 | `res.json(...)` | 响应体 |
| `isError: true` | HTTP 200 + 业务错误 | 预期内失败 |
| 抛异常 | HTTP 500 | 意外错误 |
| `console.error` 打日志 | 写日志到 stderr | 避免污染协议通道 |

---

## 5. AI Review 提问

```text
我正在学习 MCP Day 02：阅读一个真实 MCP Server 的源码，重点是 listTools 和 callTool。

请你按资深工程师标准帮我检查：

1. 我梳理的 Tool 注册流程是否正确、完整？
2. 我对 inputSchema 的理解对不对？
3. 我对 callTool「按 name 分发」模式的理解准不准？
4. 我对 isError 与抛异常两种错误处理的区分是否正确？
5. stdio 模式下不能用 console.log，我理解的原因对吗？

请用中文输出：
- 我理解正确的地方
- 我理解错误或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

- [ ] 可运行的 `server.js`（含两个只读工具）
- [ ] 工具清单表（name/用途/入参/副作用）
- [ ] 逐行加注释的源码副本（练习 3）
- [ ] Tool 注册与调用流程文字版（练习 4）
- [ ] 三次 Inspector 调用的结果记录（练习 2）
- [ ] 日志污染风险的观察结论（练习 5）
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能在源码里准确定位 listTools 和 callTool 两块
- [ ] 能说出 Tool 的三要素（name/description/inputSchema）
- [ ] 能读懂并解释一个 inputSchema
- [ ] 能说清 callTool「按 name 分发」的模式
- [ ] 能区分 isError 和抛异常两种错误处理
- [ ] 能用 MCP Inspector 连接并调用工具
- [ ] 能解释为什么 stdio 模式不能用 console.log

---

## 8. 今日自测题

### 8.1 一个 MCP Server 的骨架是哪三步？

参考答案：

> ✅ 创建 Server 实例 → 注册 ListTools 处理器（报菜单）→ 注册 CallTool 处理器（按 name 分发执行）。最后用 transport 启动连接。

---

### 8.2 Tool 的三要素是什么？各自给谁看？

参考答案：

> ✅ `name`（给机器，用于分发）、`description`（给 AI，用于判断何时调用）、`inputSchema`（给 AI 和校验，描述参数）。

---

### 8.3 callTool 里为什么普遍用「按 name 分发」？

参考答案：

> ✅ 因为所有工具调用都进同一个 CallTool 处理器，必须靠 `request.params.name` 判断到底调的是哪个工具，再分流到对应逻辑。等价于后端路由分发。

---

### 8.4 isError: true 和抛异常有什么区别？

参考答案：

> ✅ `isError: true` 用于预期内的业务失败（如参数不对），会把文本返回给 AI，让它有机会调整重试；抛异常用于意外错误（如未知工具），会转成协议级错误。前者像 HTTP 200+业务错误，后者像 HTTP 500。

---

### 8.5 stdio 模式下为什么日志要走 stderr？

参考答案：

> ✅ 因为 stdout 被 JSON-RPC 协议消息占用了。若用 console.log 往 stdout 写日志，会混进协议消息，导致 Client 解析失败。所以要用 console.error 走 stderr。

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
我正在进行 Week 14 Day 02：现有 MCP Server 源码 的学习。
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
