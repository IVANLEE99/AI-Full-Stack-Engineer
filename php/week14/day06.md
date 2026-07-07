# Week 14 Day 06：MCP 交付项目

> 所属周：Week 14：MCP Protocol + MCP Server  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`mcp-server`  
> 类型：项目实战  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

把本周学的东西打包成一个可交付的 MCP Server：完善工具、写好配置文档，让别人照着文档就能在 Cursor 里跑起来。

今天你要真正掌握这一句话：

> 一个「可交付」的 MCP Server = 可运行的 Server 代码 + 至少一个经过测试的只读 Tool + 一份让别人零基础也能接入的配置文档（含 .example 配置、启动命令、验证步骤）。交付的标准不是「我能跑」，而是「别人照文档也能跑」。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 明确「交付」的验收标准
2. 整理项目目录结构
3. 完善并补测工具（至少 1 个稳定可用）
4. 写配置文档 README（接入步骤）
5. 准备 .example 配置模板
6. 端到端自测：删掉缓存、照文档重跑一遍
7. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 什么叫「可交付」

学习期的代码只要自己能跑就行；交付级的代码要满足别人也能跑。区别在于文档和可复现性。

| 维度 | 学习级 | 交付级 |
|---|---|---|
| 代码 | 能跑就行 | 结构清晰、有错误处理 |
| 配置 | 硬编码也行 | 抽成配置 + .example 模板 |
| 文档 | 无 | 有接入步骤、验证方法 |
| 可复现 | 只有我的机器能跑 | 换台机器照文档能跑 |
| 安全 | 不管 | 只读、脱敏、密钥不入库 |

小白重点：

> 判断交付是否合格的最简单方法：找一个没参与的人（或第二天的自己），只给他文档，看他能不能独立跑通。跑不通就是文档不合格。

---

### 1.2 交付项目目录结构

一个规整的 MCP Server 交付目录（Node 版示意）：

```text
mcp-server-demo/
├── src/
│   ├── index.ts          # Server 入口：注册 listTools / callTool
│   ├── tools/
│   │   ├── getUserById.ts # 工具：按 ID 查用户（只读）
│   │   └── listOrders.ts  # 工具：查订单列表（只读）
│   └── db.ts             # 数据库连接（只读账号）
├── mcp.config.example.json # 给用户抄的配置模板
├── .env.example          # 环境变量模板
├── .gitignore            # 忽略 .env / 真实配置
├── package.json
└── README.md             # 接入文档（核心交付物）
```

Python 版对应：

```text
mcp-server-demo/
├── server.py             # Server 入口
├── tools/
│   ├── get_user.py
│   └── list_orders.py
├── db.py
├── mcp.config.example.json
├── .env.example
├── .gitignore
├── requirements.txt
└── README.md
```

小白重点：

> `.example` 文件是交付的关键。真实配置（含连接串/密钥）永远不提交，只提交去掉敏感值的模板，让用户复制改名后填自己的值。

---

### 1.3 完善工具：加错误处理

交付级工具要能优雅处理异常，而不是直接崩。给 Day 03 的工具补错误处理：

```js
// src/tools/getUserById.ts
export async function getUserById(args) {
  // 1. 参数校验
  if (typeof args.id !== "number" || args.id <= 0) {
    return {
      content: [{ type: "text", text: "参数错误：id 必须是正整数" }],
      isError: true,
    };
  }

  try {
    // 2. 参数化查询（只读）
    const rows = await db.query(
      "SELECT id, name, email FROM users WHERE id = ? LIMIT 1",
      [args.id]
    );
    if (rows.length === 0) {
      return { content: [{ type: "text", text: `未找到 id=${args.id} 的用户` }] };
    }
    // 3. 脱敏后返回
    return {
      content: [{ type: "text", text: JSON.stringify(rows[0], null, 2) }],
    };
  } catch (e) {
    // 4. 错误兜底：不把内部堆栈暴露给 AI
    return {
      content: [{ type: "text", text: "查询失败，请检查数据库连接" }],
      isError: true,
    };
  }
}
```

Python 版：

```python
async def get_user_by_id(args: dict) -> dict:
    id_ = args.get("id")
    if not isinstance(id_, int) or id_ <= 0:
        return {"content": [{"type": "text", "text": "参数错误：id 必须是正整数"}],
                "isError": True}
    try:
        rows = await db.query(
            "SELECT id, name, email FROM users WHERE id = ? LIMIT 1", [id_]
        )
        if not rows:
            return {"content": [{"type": "text", "text": f"未找到 id={id_} 的用户"}]}
        return {"content": [{"type": "text", "text": json.dumps(rows[0], ensure_ascii=False)}]}
    except Exception:
        return {"content": [{"type": "text", "text": "查询失败，请检查数据库连接"}],
                "isError": True}
```

对比表：

| 处理点 | 学习级 | 交付级 |
|---|---|---|
| 参数非法 | 直接查询报错 | 提前返回友好提示 |
| 查无数据 | 返回空/报错 | 返回「未找到」文案 |
| DB 异常 | 抛堆栈 | 兜底文案，不泄露内部信息 |

---

### 1.4 写配置文档 README

交付文档的核心是「接入步骤」。一份合格的 MCP README 至少包含：

````text
# XX MCP Server

## 功能
提供只读查询工具：按 ID 查用户、查订单列表（仅连 dev 库）。

## 前置要求
- Node.js 18+（或 Python 3.10+）
- 可访问的 dev 数据库

## 安装
```bash
npm install
npm run build
```

## 配置
1. 复制配置模板：
   cp .env.example .env
2. 填入你的 dev 数据库连接（只读账号）。

## 在 Cursor 中接入
把下面配置加入 Cursor 的 mcp 配置文件：
```json
{
  "mcpServers": {
    "demo": {
      "command": "node",
      "args": ["/你的路径/mcp-server-demo/dist/index.js"]
    }
  }
}
```

## 验证
1. 重启 Cursor
2. 在设置里确认 demo Server 状态为绿灯
3. 对话中说：查一下 id 为 1 的用户
4. 应看到 AI 调用 get_user_by_id 并返回结果

## 提供的工具
| 工具名 | 说明 | 参数 |
|---|---|---|
| get_user_by_id | 按 ID 查用户 | id: number |
| list_orders | 查订单列表 | limit?: number |

## 安全说明
- 仅连接 dev 库，只读，不做写操作
- 敏感字段已脱敏
````

小白重点：

> 文档里最容易漏的是「验证」这一步。写清楚「怎么确认接入成功」，别人才不会卡在「装完了但不知道有没有生效」。

---

### 1.5 交付前的自检清单

交付前逐条打钩：

| 检查项 | 通过标准 |
|---|---|
| Server 能独立启动 | `node dist/index.js` 不报错 |
| listTools 返回工具 | 能列出所有工具 |
| 至少 1 个工具可调用 | 手动/Cursor 调用成功 |
| 参数校验生效 | 传非法参数有友好提示 |
| 错误不崩 | DB 断开时返回兜底文案 |
| .example 配置齐全 | 有 .env.example / mcp.config.example.json |
| 真实密钥未提交 | `.gitignore` 忽略 .env |
| README 完整 | 含安装/配置/接入/验证 |
| 只读 & 脱敏 | 无写操作，无敏感字段 |
| 换环境可复现 | 照文档在干净环境能跑通 |

---

## 2. 源码阅读

本日无指定源码阅读，重点完成交付项目。

回看本周所有产出，按 1.5 清单逐条对照：

1. Day 02 学的 listTools/callTool 结构 → 现在能自己写全吗？
2. Day 03 的工具 → 加了错误处理和脱敏吗？
3. Day 04 的 Cursor 配置 → 抽成 .example 了吗？
4. Day 05 的安全红线 → 全部满足吗？

---

## 3. 练习任务

### 练习 1：整理项目结构

按 1.2 把你的代码整理成规范目录。至少有 `src/`（或对应）、`tools/`、`.example` 配置、`README.md`。

---

### 练习 2：给工具加错误处理

参照 1.3，给你的工具补齐：参数校验、查无数据提示、异常兜底。手动测试三种情况：

1. 正常参数 → 返回数据
2. 非法参数（如 id 传字符串）→ 友好提示
3. 制造异常（如故意写错表名）→ 兜底文案，不崩

---

### 练习 3：写 README 接入文档

按 1.4 模板写完整 README，必须包含「验证」章节。要求：文档写完后，假装自己是新人，只看文档能复述出接入流程。

---

### 练习 4：端到端复现测试

这是今天最重要的一步：

1. 关掉当前 Cursor 里的 MCP 配置
2. 删掉本地 build 产物（`dist/` 或缓存）
3. 完全照着自己写的 README，从头 install → build → 配置 → 接入
4. 记录每一步是否卡住，把卡点补进文档

目标：文档能让「干净环境」跑通。

---

## 4. JS/Node.js 类比

| MCP 交付概念 | 后端工程类比 | 说明 |
|---|---|---|
| 可交付 Server | 可部署的服务 | 别人也能跑起来 |
| .example 配置 | `.env.example` | 提交模板不提交密钥 |
| README 接入文档 | API 接入文档 | 让调用方快速上手 |
| 错误兜底 | 全局异常处理 | 不把堆栈暴露给外部 |
| 交付自检清单 | 上线 checklist | 发布前逐项确认 |
| 端到端复现 | CI 干净环境构建 | 保证可复现，不依赖本机状态 |

---

## 5. AI Review 提问

```text
我正在学习 MCP Day 06：把 MCP Server 做成可交付项目。

请你按资深工程师标准帮我检查：

1. 我的项目目录结构是否清晰、符合交付标准？
2. 工具的错误处理（参数校验/查无数据/异常兜底）是否到位？
3. 我的 README 接入文档是否完整？新人能否照着跑通？
4. .example 配置和密钥管理是否安全（真实密钥没提交）？
5. 我做的端到端复现测试是否足够证明「别人也能跑」？

请用中文输出：
- 我做对的地方
- 我做错或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

- [ ] 规整的 MCP Server 项目目录（练习 1）
- [ ] 带完整错误处理的工具 + 三种情况测试记录（练习 2）
- [ ] 完整 README 接入文档（含验证章节）（练习 3）
- [ ] 端到端复现测试记录 + 文档补漏（练习 4）
- [ ] 交付前自检清单（全部打钩）
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说清「学习级」和「交付级」代码的区别
- [ ] 项目目录规整，含 .example 配置和 README
- [ ] 至少 1 个工具带完整错误处理，三种情况都测过
- [ ] README 含安装/配置/接入/验证四部分
- [ ] 真实密钥未提交，`.gitignore` 生效
- [ ] Cursor 调用成功
- [ ] 端到端复现测试通过（干净环境照文档能跑通）

---

## 8. 今日自测题

### 8.1 「可交付」的 MCP Server 和「能跑」的区别是什么？

参考答案：

> ✅ 能跑只要自己机器能运行；可交付要求别人只看文档、在干净环境也能跑通，即可复现性 + 完整文档 + 安全配置。

---

### 8.2 为什么要提供 .example 配置文件？

参考答案：

> ✅ 真实配置含连接串/密钥，不能提交到仓库。`.example` 是去掉敏感值的模板，用户复制改名后填自己的值，既方便接入又不泄露密钥。

---

### 8.3 交付级工具的错误处理要覆盖哪几种情况？

参考答案：

> ✅ 至少三种：参数非法（提前校验返回提示）、查无数据（友好文案）、运行异常（兜底文案，不把内部堆栈/表名等暴露给 AI）。

---

### 8.4 README 里最容易漏、但最重要的是哪一部分？

参考答案：

> ✅ 「验证」章节。写清楚怎么确认接入成功（如 Cursor 里 Server 绿灯、说某句话能触发工具），否则用户装完不知道有没有生效。

---

### 8.5 为什么要做「端到端复现测试」？

参考答案：

> ✅ 为了证明交付合格。删掉本地缓存、完全照自己写的文档重跑一遍，能暴露文档里遗漏的步骤，保证换台机器/换个人也能跑通。

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
我正在进行 Week 14 Day 06：MCP 交付项目 的学习。
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
