# Week 14 Day 03：新增 dev 只读 Tool

> 所属周：Week 14：MCP Protocol + MCP Server  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`mcp-server`  
> 类型：编码练习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

亲手给 MCP Server 新增一个「dev 环境只读」工具，定义好 inputSchema，并用 Inspector 测通。

今天你要真正掌握这一句话：

> 新增一个 Tool 只需要做两件事：在 listTools 里加一条「菜单描述」，在 callTool 里加一段「按名字执行的逻辑」。而「只读 + 只连 dev」是这个工具最重要的安全边界，必须写进代码里、而不是靠自觉。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先明确「只读工具」的定义和边界
2. 想清楚这个工具要解决什么问题（用例）
3. 设计工具的 name / description / inputSchema
4. 在 listTools 里注册这个工具
5. 在 callTool 里实现执行逻辑
6. 加上参数校验和只读保护
7. 用 Inspector 测试正常与异常路径
8. 补充边界用例（空结果、非法参数）
9. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 什么是「只读 Tool」

只读工具指：**只查询、不改动任何数据**的工具。

| 类型 | 允许的操作 | 禁止的操作 |
|---|---|---|
| 只读工具 | SELECT、count、list、search | INSERT、UPDATE、DELETE、DROP |
| 写操作工具 | 上面全部 | —— |

为什么第一个工具一定要选只读？

> 因为 MCP 工具是给 AI 调用的。AI 可能理解错、可能被诱导。只读工具即使被误调用，最坏结果也只是「查了不该查的」，不会「改坏数据」。这是把风险降到最低的起手式。

小白重点：

> 「只读」不是口头承诺，要在代码里落实：只写查询逻辑，绝不引入任何修改数据的代码路径。

---

### 1.2 想清楚用例

我们今天做的工具叫 `get_user_by_id`：按用户 ID 查询单个用户信息（仅限 dev 环境模拟数据）。

用例：

- 用户对 AI 说：「帮我看看 dev 环境里 id=2 的用户是谁」
- AI 判断该调用 `get_user_by_id`，参数 `{ id: 2 }`
- 工具返回该用户信息
- AI 组织成自然语言回答

设计要点：

| 问题 | 决定 |
|---|---|
| 工具名 | `get_user_by_id` |
| 需要什么参数 | 一个整数 id |
| id 有约束吗 | 必须是正整数 |
| 查不到怎么办 | 返回 isError 文本，而不是崩溃 |
| 会改数据吗 | 不会，纯查询 |

---

### 1.3 设计 inputSchema

先设计参数结构：

```js
inputSchema: {
  type: "object",
  properties: {
    id: {
      type: "integer",
      minimum: 1,
      description: "要查询的用户 ID，必须是正整数",
    },
  },
  required: ["id"],
}
```

逐项说明：

| 片段 | 含义 |
|---|---|
| `type: "integer"` | id 必须是整数 |
| `minimum: 1` | 至少为 1（不接受 0 和负数） |
| `description` | 告诉 AI 这个字段的含义 |
| `required: ["id"]` | id 必填 |

对照 zod 写法（很多项目更喜欢用 zod）：

```js
import { z } from "zod";
const GetUserInput = z.object({
  id: z.number().int().min(1).describe("要查询的用户 ID，必须是正整数"),
});
```

对照 PHP 校验心智：

```php
// Yii2 rules 示意
public function rules(): array
{
    return [
        ['id', 'required'],
        ['id', 'integer', 'min' => 1],
    ];
}
```

小白重点：

> inputSchema 写得越严，AI 越不容易传错参数，你后端也越少做防御。`minimum: 1` 这类约束就是在「用 schema 做第一道校验」。

---

### 1.4 在 listTools 里注册

在 Day 02 的 `server.js` 基础上，往 tools 数组里加一条：

```js
server.setRequestHandler(ListToolsRequestSchema, async () => {
  return {
    tools: [
      // ... 原有的 list_tables、count_users ...
      {
        name: "get_user_by_id",
        description:
          "按用户 ID 查询单个用户信息（仅 dev 环境只读数据）。当用户想根据 ID 查某个用户时使用。",
        inputSchema: {
          type: "object",
          properties: {
            id: {
              type: "integer",
              minimum: 1,
              description: "要查询的用户 ID，必须是正整数",
            },
          },
          required: ["id"],
        },
      },
    ],
  };
});
```

小白重点：

> `description` 是写给 AI 看的「使用说明书」。写清楚「什么时候用它」，AI 才知道何时该调这个工具。这里特意点明「仅 dev 环境只读」，帮 AI 建立正确预期。

---

### 1.5 在 callTool 里实现执行逻辑

在 callTool 的分发里加一段：

```js
server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  // ... 原有的 list_tables、count_users 分支 ...

  if (name === "get_user_by_id") {
    const id = args?.id;

    // 参数校验（第二道防线，schema 之外再兜底）
    if (typeof id !== "number" || !Number.isInteger(id) || id < 1) {
      return {
        content: [{ type: "text", text: "id 必须是正整数" }],
        isError: true,
      };
    }

    // 只读查询（仅 dev 模拟数据，绝不涉及写操作）
    const user = FAKE_DB.users.find((u) => u.id === id);

    if (!user) {
      return {
        content: [{ type: "text", text: `未找到 id=${id} 的用户` }],
        isError: true,
      };
    }

    return {
      content: [
        {
          type: "text",
          text: `用户 id=${user.id}，姓名=${user.name}，环境=${user.env}`,
        },
      ],
    };
  }

  throw new Error(`Unknown tool: ${name}`);
});
```

这段代码的三层结构：

| 层 | 作用 | 对应代码 |
|---|---|---|
| 校验层 | 挡住非法参数 | `if (typeof id !== ...)` |
| 查询层 | 只读取数据 | `FAKE_DB.users.find(...)` |
| 返回层 | 组织人和 AI 都能读的文本 | `content: [...]` |

小白重点：

> 即使 inputSchema 已经约束了 id，callTool 里仍要再校验一遍。因为「不能假设调用方一定守规矩」，这是后端开发的基本纪律，和写 REST 接口一样。

---

### 1.6 把只读边界写进代码

为了让「只读」这件事更明确、更难被破坏，可以把数据封装成只读访问：

```js
// 只暴露查询方法，不暴露任何写方法
const userRepo = {
  findById(id) {
    return FAKE_DB.users.find((u) => u.id === id) ?? null;
  },
  // 注意：这里故意不提供 create / update / delete
};
```

然后 callTool 里用：

```js
const user = userRepo.findById(id);
```

小白重点：

> 「只读」不只是承诺，还可以用「只提供查询方法」的封装来物理隔离。工具代码根本拿不到写数据的入口，自然就写不坏。这叫「用设计约束替代自觉」。

---

### 1.7 一个完整的 dev 只读工具最终形态

把关键片段拼起来，你的新工具应该长这样（省略号处沿用 Day 02 的骨架）：

```js
// listTools 里
{
  name: "get_user_by_id",
  description: "按用户 ID 查询单个用户信息（仅 dev 环境只读数据）。...",
  inputSchema: {
    type: "object",
    properties: {
      id: { type: "integer", minimum: 1, description: "要查询的用户 ID..." },
    },
    required: ["id"],
  },
}

// callTool 里
if (name === "get_user_by_id") {
  const id = args?.id;
  if (typeof id !== "number" || !Number.isInteger(id) || id < 1) {
    return { content: [{ type: "text", text: "id 必须是正整数" }], isError: true };
  }
  const user = userRepo.findById(id);
  if (!user) {
    return { content: [{ type: "text", text: `未找到 id=${id} 的用户` }], isError: true };
  }
  return {
    content: [{ type: "text", text: `用户 id=${user.id}，姓名=${user.name}，环境=${user.env}` }],
  };
}
```

---

## 2. 源码阅读

本日以「写」为主，源码阅读聚焦你自己新增的部分。改完后回头审查：

1. listTools 里新工具的 description 是否写清了「何时用」和「只读/dev」边界
2. inputSchema 是否约束了类型和范围
3. callTool 里是否有独立的参数校验
4. 是否有任何一行代码可能改动数据（应该没有）
5. 查不到数据时是否优雅返回 isError，而不是崩溃

对照检查表：

| 检查项 | 通过？ |
|---|---|
| 工具名清晰、语义化 |  |
| description 说明了用途和边界 |  |
| inputSchema 有类型和范围约束 |  |
| callTool 有二次校验 |  |
| 无任何写操作 |  |
| 空结果有友好处理 |  |

---

## 3. 练习任务

### 练习 1：加上并测通 get_user_by_id

按 1.4 和 1.5 把工具加进 Day 02 的 `server.js`，然后：

```bash
npx @modelcontextprotocol/inspector node server.js
```

在 Inspector 里：

1. 确认 Tools 面板出现了 `get_user_by_id`
2. 调用 `{ "id": 2 }`，应返回 Jerry 的信息
3. 调用 `{ "id": 99 }`，应返回「未找到」的 isError
4. 调用 `{ "id": -1 }`，应返回「id 必须是正整数」的 isError

把四步结果记进笔记。

---

### 练习 2：再加一个只读工具 search_users_by_env

自己动手，仿照今天的模式，新增一个 `search_users_by_env` 工具：

- 参数：`env`（字符串，enum 为 dev/test）
- 功能：返回该环境所有用户的姓名列表
- 只读，查不到返回空列表提示

写完用 Inspector 测通。这一步是「不看讲解、独立复现」，是今天最重要的练习。

参考骨架（尽量先自己写，卡住再看）：

```js
// listTools
{
  name: "search_users_by_env",
  description: "列出某个环境下的所有用户姓名（仅 dev/test 只读）。",
  inputSchema: {
    type: "object",
    properties: {
      env: { type: "string", enum: ["dev", "test"], description: "环境名" },
    },
    required: ["env"],
  },
}

// callTool
if (name === "search_users_by_env") {
  const env = args?.env;
  if (env !== "dev" && env !== "test") {
    return { content: [{ type: "text", text: "env 只能是 dev 或 test" }], isError: true };
  }
  const names = FAKE_DB.users.filter((u) => u.env === env).map((u) => u.name);
  const text = names.length ? names.join(", ") : `${env} 环境暂无用户`;
  return { content: [{ type: "text", text }] };
}
```

---

### 练习 3：故意破坏只读边界，再修回来

在 callTool 里临时写一行「修改数据」的代码（比如 `user.name = "Hacked"`），观察它会改动 FAKE_DB。然后删掉，改用只提供 `findById` 的 `userRepo` 封装（1.6），体会「物理隔离」如何防止误改。

参考结论：

> 直接操作 FAKE_DB 时，工具有能力改数据；改用只暴露查询方法的 repo 后，工具根本拿不到写入口，从设计上杜绝了误改。

---

### 练习 4：整理工具清单表

把你现在拥有的所有工具整理成一张表：

| 工具名 | 参数 | 功能 | 只读？ | 环境限制 |
|---|---|---|---|---|
| list_tables | 无 | 列出表名 | 是 | dev |
| count_users | env | 统计用户数 | 是 | dev/test |
| get_user_by_id | id | 按 ID 查用户 | 是 | dev |
| search_users_by_env | env | 按环境列用户 | 是 | dev/test |

---

## 4. JS/Node.js 类比

| MCP 概念 | 后端类比 | 说明 |
|---|---|---|
| 新增 Tool | 新增一个接口 | 报菜单 + 写逻辑 |
| listTools 加一条 | 路由表加一行 | 声明能力 |
| callTool 加一个分支 | 加一个 controller 方法 | 实现逻辑 |
| inputSchema | 请求参数校验 schema | 约束入参 |
| 二次校验 | controller 里再校验 | 不信任调用方 |
| 只读封装 repo | Repository 只暴露查询方法 | 用设计约束权限 |
| isError 返回 | 业务错误返回 | 优雅失败 |

---

## 5. AI Review 提问

```text
我正在学习 MCP Day 03：给 MCP Server 新增一个 dev 环境只读工具 get_user_by_id。

请你按资深工程师标准帮我检查：

1. 我的工具 name 和 description 是否清晰、能让 AI 正确判断何时调用？
2. 我的 inputSchema 参数设计是否合理、约束是否足够？
3. callTool 里的二次校验有没有必要、是否写对？
4. 我的「只读」边界是否真的守住了、有没有可能改动数据的路径？
5. 空结果和非法参数的处理是否优雅？

请用中文输出：
- 我做对的地方
- 我做错或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

- [ ] 新增的 `get_user_by_id` 工具（listTools + callTool）
- [ ] 自主完成的 `search_users_by_env` 工具（练习 2）
- [ ] Inspector 正常 + 异常路径测试记录（练习 1）
- [ ] 只读封装 repo 的实践与结论（练习 3）
- [ ] 工具清单表（练习 4）
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能独立在 listTools 和 callTool 里新增一个工具
- [ ] 能为工具设计合理的 inputSchema（类型 + 范围 + 必填）
- [ ] callTool 里有独立的参数二次校验
- [ ] 工具是纯只读，无任何写操作
- [ ] 查不到数据时返回 isError，而不是崩溃
- [ ] 能用 Inspector 测通正常和异常路径
- [ ] 能不看讲解，独立复现第二个只读工具

---

## 8. 今日自测题

### 8.1 新增一个 Tool 需要改哪两个地方？

参考答案：

> ✅ listTools（加一条菜单描述：name/description/inputSchema）和 callTool（加一段按 name 分发的执行逻辑）。

---

### 8.2 inputSchema 已经校验了，callTool 为什么还要再校验？

参考答案：

> ✅ 因为不能假设调用方一定守规矩，二次校验是后端基本纪律。schema 是声明式约束，callTool 里的校验是执行前的兜底，两者互补。

---

### 8.3 为什么第一个工具要选只读？

参考答案：

> ✅ 因为工具是给 AI 调用的，AI 可能理解错或被诱导。只读工具即使被误调用，最坏也只是查了不该查的，不会改坏数据，风险最低。

---

### 8.4 怎么用「设计」而不是「自觉」来保证只读？

参考答案：

> ✅ 把数据访问封装成只暴露查询方法的 repository（如只有 findById，没有 create/update/delete）。工具代码拿不到写入口，从设计上杜绝误改。

---

### 8.5 查不到用户时该怎么返回？

参考答案：

> ✅ 返回 `{ content: [{ type:"text", text:"未找到..." }], isError: true }`，让 AI 知道失败原因，而不是抛异常或崩溃。

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
我正在进行 Week 14 Day 03：新增 dev 只读 Tool 的学习。
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
