# Week 14 Day 04：Cursor MCP 集成

> 所属周：Week 14：MCP Protocol + MCP Server  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`mcp-server`  
> 类型：架构理解  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

把你自己写的 MCP Server 配置进 Cursor，让 Cursor 里的 AI 真正调用到你的工具。

今天你要真正掌握这一句话：

> Cursor 是 MCP Client。你只要在一个配置文件里告诉它「用什么命令启动我的 Server」，Cursor 就会在后台把 Server 拉起来、通过 stdio 建立连接、自动 listTools，然后在你和 AI 对话时按需 callTool。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 理解 Cursor 在 MCP 里扮演的角色（Client + Host）
2. 看懂 MCP 配置文件的结构
3. 找到 Cursor 的 MCP 配置位置
4. 写好指向你 Server 的配置
5. 重启 / 刷新，确认工具被识别
6. 在对话里触发一次工具调用
7. 排查常见问题（路径、命令、绿灯）
8. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 Cursor 是 MCP 里的谁

回顾 Week 14 的三个角色：

| 角色 | 谁来当 | 职责 |
|---|---|---|
| Host / Client | Cursor | 发起连接、管理会话、决定何时调工具 |
| Server | 你写的 server.js | 提供工具、执行工具 |
| 大模型 | Cursor 背后的模型 | 决策调哪个工具、组织回答 |

小白重点：

> 你今天不用改任何 Server 代码。Cursor 作为现成的 Client，会替你完成「启动 Server、握手、listTools、callTool」的全过程。你的工作只是写好一份「怎么启动我的 Server」的配置。

---

### 1.2 MCP 配置文件长什么样

MCP 配置的核心是一个 JSON，声明「有哪些 server，各自怎么启动」。示例（对应骨架里的 `mcp.config.example.json`）：

```json
{
  "mcpServers": {
    "dev-db": {
      "command": "node",
      "args": ["/绝对路径/mcp-server/server.js"],
      "env": {
        "APP_ENV": "dev"
      }
    }
  }
}
```

逐字段说明：

| 字段 | 含义 | 类比 |
|---|---|---|
| `mcpServers` | 所有要连接的 Server 集合 | 一张「外接工具箱」清单 |
| `dev-db` | 这个 Server 的自定义名字 | 服务别名 |
| `command` | 启动用的可执行程序 | `node` / `python` / `npx` |
| `args` | 启动参数（脚本路径等） | 命令行后面跟的参数 |
| `env` | 注入的环境变量 | 进程环境变量 |

小白重点：

> Cursor 读到这份配置后，本质上就是在后台执行 `node /路径/server.js`，然后接管它的 stdin/stdout 走 stdio 传输。所以配置的核心是「一条能在终端跑通的启动命令」。

---

### 1.3 Python 版 Server 怎么配

如果你的 Server 是 Python 写的（用官方 python-sdk），配置类似：

```json
{
  "mcpServers": {
    "dev-db": {
      "command": "python",
      "args": ["/绝对路径/mcp-server/server.py"],
      "env": {
        "APP_ENV": "dev"
      }
    }
  }
}
```

或者用 uv 管理环境时：

```json
{
  "mcpServers": {
    "dev-db": {
      "command": "uv",
      "args": ["run", "--directory", "/绝对路径/mcp-server", "server.py"]
    }
  }
}
```

对比表：

| 语言 | command | args 典型值 |
|---|---|---|
| Node | `node` | `["server.js"]` |
| Node（发布为 npm 包） | `npx` | `["-y", "my-mcp-server"]` |
| Python | `python` | `["server.py"]` |
| Python（uv） | `uv` | `["run", "server.py"]` |

小白重点：

> 不管什么语言，配置的三要素永远是「用什么命令、带什么参数、给什么环境变量」。这也是为什么 MCP 生态里各种语言的 Server 都能被同一个 Cursor 加载。

---

### 1.4 在 Cursor 里放配置

Cursor 支持两种作用域：

| 作用域 | 位置 | 适用 |
|---|---|---|
| 全局 | Cursor Settings → MCP（写入用户级配置文件） | 所有项目通用的工具 |
| 项目级 | 项目根目录下 `.cursor/mcp.json` | 只给当前项目用的工具 |

项目级 `.cursor/mcp.json` 内容就是 1.2 那份 JSON。推荐练习时用项目级，改动影响范围小、方便清理。

操作步骤：

1. 在项目根目录新建 `.cursor/` 目录
2. 里面新建 `mcp.json`
3. 粘贴 1.2 的配置，把路径改成你本机 server.js 的绝对路径
4. 保存

小白重点：

> 项目级配置放进 `.cursor/mcp.json`。注意里面若含敏感信息（如 env 里的 token），应加进 `.gitignore`，不要提交到仓库。今天我们只连 dev 模拟数据，没有真实密钥，但要养成习惯。

---

### 1.5 让 Cursor 识别工具

保存配置后：

1. 打开 Cursor 的 MCP 设置界面
2. 找到你命名的 server（如 `dev-db`）
3. 确认它前面是「绿灯 / Enabled」状态
4. 若是红灯，通常是命令或路径错了（见 1.7 排查）
5. 展开后应能看到你的工具列表（list_tables、get_user_by_id 等）

看到工具列表 = Cursor 已成功启动 Server 并完成了 listTools。

---

### 1.6 在对话里触发工具调用

在 Cursor 的 AI 对话框里，用自然语言触发：

```text
用 dev-db 工具帮我查一下 id=2 的用户是谁
```

AI 的处理流程（对应 Day 01 学的时序）：

1. AI 看到已连接工具里有 `get_user_by_id`
2. 判断该调用它，参数 `{ id: 2 }`
3. Cursor 向你的 Server 发 callTool 请求
4. Server 返回结果文本
5. AI 用自然语言回答你

Cursor 通常会弹出「是否允许调用该工具」的确认，点允许即可。

小白重点：

> 你会亲眼看到「你写的 server.js 里的一行 text」变成了 AI 回答的一部分。这就是 MCP 打通的全链路：自然语言 → 工具调用 → 结构化结果 → 自然语言。

---

### 1.7 常见问题排查

| 现象 | 可能原因 | 解决 |
|---|---|---|
| server 红灯 / 启动失败 | command 不在 PATH | 用绝对路径或确认 node/python 已装 |
| 找不到脚本 | args 路径写错 | 改成 server.js 的绝对路径 |
| 工具列表为空 | Server 报错退出 | 先在终端手动跑一遍启动命令看报错 |
| 改了配置没生效 | Cursor 未刷新 | 重启 Cursor 或刷新 MCP 面板 |
| 调用无反应 | 未点「允许调用」 | 在弹窗里授权 |
| 中文乱码 | 编码问题 | 确认输出 UTF-8 |

排查黄金法则：

> 先脱离 Cursor，直接在终端跑 `node /路径/server.js`。如果它自己都启动不了，Cursor 肯定也连不上。用 Day 02 学的 Inspector 单独验证 Server 是否健康，再回来接 Cursor。

---

## 2. 源码阅读

- `ai-workspace/mcp.config.example.json`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找：

1. `mcpServers` 下配了几个 server
2. 每个 server 的 command / args / env
3. 是否用了绝对路径
4. env 里有没有区分环境的变量（如 APP_ENV=dev）
5. 有没有把敏感配置和示例配置分开（`.example` 后缀的用意）

建议笔记表格：

| 配置项 | 值 | 作用 | 备注 |
|---|---|---|---|
| server 名 |  |  |  |
| command |  |  |  |
| args |  |  |  |
| env |  |  |  |

小白重点：

> `.example.json` 是「示例配置」，用来给别人参考格式；真实配置往往叫 `mcp.config.json` 并被 gitignore。这是团队协作里「提交模板、不提交密钥」的常见做法。

---

## 3. 练习任务

### 练习 1：写好项目级配置

在项目根目录建 `.cursor/mcp.json`，指向你 Day 03 的 server.js：

```json
{
  "mcpServers": {
    "dev-db": {
      "command": "node",
      "args": ["/你的绝对路径/mcp-server/server.js"],
      "env": { "APP_ENV": "dev" }
    }
  }
}
```

保存后在 Cursor MCP 面板确认 `dev-db` 亮绿灯、能看到工具列表。

---

### 练习 2：完成一次真实调用

在 Cursor 对话框输入：

```text
用 dev-db 帮我查 id=2 的用户
```

记录：

1. AI 是否正确选择了 get_user_by_id
2. 传的参数是什么
3. 返回结果是否和 Inspector 里一致
4. 截图或复制对话保存进笔记

---

### 练习 3：制造并修复一个故障

故意把 args 路径改错（比如少一个字母），保存后观察：

1. Cursor 面板变成什么状态
2. 工具列表还在吗
3. 改回正确路径，确认恢复

写下结论：配置错误时 Cursor 的表现，以及你怎么定位的。

---

### 练习 4：先用终端验证再接 Cursor

按 1.7 黄金法则，先在终端跑：

```bash
node /你的路径/mcp-server/server.js
```

确认无报错（进程挂起等待输入是正常的，Ctrl+C 退出）。再回到 Cursor。体会「先单独验证 Server、再接 Client」的排查顺序。

---

## 4. JS/Node.js 类比

| MCP 概念 | 后端 / 工程类比 | 说明 |
|---|---|---|
| Cursor 作为 Client | API 网关 / 调用方 | 发起请求的一端 |
| mcp.json 配置 | 服务注册配置 / docker-compose | 声明怎么启动依赖 |
| command + args | 启动脚本命令 | 一条能跑通的命令 |
| env 注入 | 环境变量 / .env | 区分 dev/test/prod |
| 绿灯 = 已连接 | 健康检查通过 | 依赖就绪 |
| `.cursor/mcp.json` | 项目级配置文件 | 只影响当前项目 |
| `.example.json` | 配置模板 | 提交模板不提交密钥 |

---

## 5. AI Review 提问

```text
我正在学习 MCP Day 04：把自己写的 MCP Server 配置进 Cursor 并成功调用。

请你按资深工程师标准帮我检查：

1. 我的 mcp.json 配置结构和字段是否正确？
2. 用项目级 .cursor/mcp.json 还是全局配置，哪个更适合练习？为什么？
3. 我把 server.js 路径、command、env 配得对不对？
4. 敏感信息（如未来的 token）应该怎么处理才安全？
5. 排查连接失败时，我的思路（先终端单独验证 Server）是否正确？

请用中文输出：
- 我做对的地方
- 我做错或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

- [ ] 可用的 `.cursor/mcp.json` 配置（练习 1）
- [ ] 一次成功的 Cursor 工具调用记录（练习 2）
- [ ] 故障制造与修复的排查笔记（练习 3）
- [ ] 「先终端验证再接 Cursor」的实践结论（练习 4）
- [ ] 配置字段说明表（源码阅读）
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说清 Cursor 在 MCP 里是 Client / Host
- [ ] 能看懂并写出 mcpServers 配置（command/args/env）
- [ ] 能在 Cursor 里让 server 亮绿灯、看到工具列表
- [ ] 能用自然语言在对话里触发一次工具调用
- [ ] 能定位常见连接失败原因
- [ ] 知道敏感配置要 gitignore、用 .example 模板

---

## 8. 今日自测题

### 8.1 Cursor 在 MCP 里是什么角色？

参考答案：

> ✅ Cursor 是 Client / Host。它负责启动 Server、建立连接、listTools，并在与 AI 对话时按需 callTool。Server 是你写的 server.js。

---

### 8.2 mcp.json 配置的三个核心要素是什么？

参考答案：

> ✅ command（用什么命令启动）、args（带什么参数，通常是脚本路径）、env（注入什么环境变量）。本质是一条能在终端跑通的启动命令。

---

### 8.3 项目级配置放在哪里？和全局配置有什么区别？

参考答案：

> ✅ 项目级放在项目根目录 `.cursor/mcp.json`，只影响当前项目；全局配置在 Cursor 用户级设置里，影响所有项目。练习推荐用项目级，影响小、好清理。

---

### 8.4 Cursor 里 server 红灯 / 工具列表为空，怎么排查？

参考答案：

> ✅ 先脱离 Cursor，在终端直接跑启动命令（或用 Inspector）确认 Server 本身健康；再检查 command 是否在 PATH、args 路径是否正确、配置改后是否刷新。

---

### 8.5 为什么会有 `.example.json` 这种文件？

参考答案：

> ✅ 它是配置模板，提交进仓库供他人参考格式；真实配置（可能含密钥）用另一个文件名并被 gitignore。这样做到「提交模板、不提交密钥」。

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
我正在进行 Week 14 Day 04：Cursor MCP 集成 的学习。
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
