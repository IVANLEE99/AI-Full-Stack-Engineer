# 验证记录

> 验证日期：2026-11-21。使用匿名 fixture，不调用付费模型、远程 MCP、数据库或云端追踪。

## 1. 环境与证据范围

- 当前运行环境：Node.js 26.13.1、npm 11.20.0。
- 项目声明最低 Node.js 22.12；本次没有在最低版本上单独复测，不能据此声称全部 Node 22 补丁版本均已验证。
- 依赖安装需要网络；安装后的默认示例使用本地模型替身、词项向量和进程内状态。
- 批量示例和默认测试额外禁用 HTTP fetch；这不是对任意第三方代码的完整网络沙箱。

## 2. 当前执行记录

| 项目 | 状态 | 说明 |
|---|---|---|
| TypeScript 类型检查 | 已通过 | strict + NodeNext；全部示例、测试与验证脚本进入检查范围 |
| 离线自动化测试 | 已通过 | 6 个测试文件、53 个测试；覆盖 MCP 错误脱敏、下一轮模型输入、取消/中断/Command、缺失 ID，以及 null/undefined 拒绝值与 null 取消原因 |
| 批量离线示例 | 已通过 | 13 个 CLI 示例全部执行成功；每个子程序加 HTTP fetch 失败门禁 |
| 文档链接和命令 | 已通过 | 20 篇课程文档、268 个本地链接；核对脚本名称、代码围栏与路径 |
| npm ci 重新安装 | 已通过 | 根据 lockfile 重新安装后，再次执行完整 check 与离线示例，整条命令退出码为 0 |

复现安装命令使用 `npm ci --no-audit --no-fund`。npm 对 esbuild/fsevents 的安装脚本给出未单独授权提示；本次没有绕过权限或修改全局策略，当前平台的类型检查、测试及示例仍全部通过。其他平台若缺少二进制，应按自己的权限策略先审查具体安装脚本。

实施中修正了 Vitest 的 Vite peer 依赖未显式安装，以及 08 中间件示例的图步骤预算过小。此后从 lockfile 重新安装并全量复测。

最终独立审查发现 MCP 错误原文可经 error ToolMessage 绕过统一错误出口。现已增加 [工具执行错误边界](src/mcp-safety.ts)，同时处理抛出的异常和直接返回的错误消息，在进入下一轮模型输入前替换正文并去掉 artifact；取消、GraphInterrupt 与 Command 保持原语义。配套 [故障注入测试](tests/mcp-safety.test.ts)检查最终状态与下一轮模型输入。

聚焦独立复核已返回：它验证了错误正文、artifact、metadata 移除，调用 ID、成功结果、Command、中断与通常取消的保持，同时发现 `isGraphBubbleUp(null)` 的空值边界。现已在判断前保留取消原因并排除 null/undefined，补齐直接调用和 Agent 内空值拒绝、执行中 null 取消原因的回归测试。

最终复测：53 项测试、13 个离线示例、类型检查和 268 个本地文档链接全部通过。审查发现项均已修复并完成回归，没有等待中的审查任务；这仍不代表可选真实服务已联调。

## 3. 为什么本地通过仍不等于真实服务验证

- FakeListChatModel 返回固定字符串，结构化方法主要解析 JSON。输出另经 Zod 校验，不宣称供应商原生 JSON Schema 已验证。
- FakeToolCallingModel 产生预设 tool_calls。它验证 ID 关联、工具执行、循环和错误控制，不验证模型是否选对工具。
- KeywordEmbeddings 使用固定四维词项计数，只验证检索数据流和来源；不能推断语义召回率或真实问答准确率。
- MemorySaver/InMemoryStore 不跨进程持久化，thread_id 与 namespace 不提供真实鉴权。
- MCP 测试仅连接仓库内的 stdio fixture，不代表未知远程服务安全或兼容。
- LangGraph 的审批只改变演示状态，不执行退款、订单修改或其他真实副作用。

## 4. 未执行项

真实模型与 Embedding API、LangSmith 云端上传、远程 MCP、PostgreSQL checkpoint/store、其他供应商和生产部署均未联调。外部文档链接的可访问性未在最终验收中验证；API 依据 lockfile 对应声明与本地执行核对。

读者复现：

```bash
nvm use
npm ci
npm run check
npm run examples:offline
```

真实服务命令只能由读者在确认账号、费用、匿名数据和权限边界后显式选择，不能用密钥“修复”本地离线失败。
