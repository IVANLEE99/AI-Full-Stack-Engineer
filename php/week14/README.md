# Week 14：MCP Protocol + MCP Server

> 强度建议：约 20h/周（周一到周五各 3h + 周末 5h）  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review  
> 公开说明：使用匿名仓库代号，不包含公司、品牌、内网域名或本地绝对路径。

---

## 1. 本周定位

- 阶段：第四阶段：AI Backend
- 主仓库/项目：`mcp-server`
- 本周目标：开发 dev 只读 MCP Tool。

### 为什么本周要学这些

- MCP 是 Agent 调用系统能力的入口。

---

## 2. 本周需要掌握的知识点

1. MCP 协议
2. Tool 注册
3. stdio
4. Cursor 集成
5. 安全边界

### php-pro 能力对齐

- 禁止生产库
- Tool 参数 schema 化

---

## 3. 必读代码/文件路径

- `mcp-server/*/src/index.ts`
- `ai-workspace/mcp.config.example.json`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 4. 七天详细学习安排

| 天 | 类型 | 主题 |
|----|------|------|
| Day 1（周一） | 概念入门 | MCP 协议概述 |
| Day 2（周二） | 源码阅读 | 现有 MCP Server 源码 |
| Day 3（周三） | 编码练习 | 新增 dev 只读 Tool |
| Day 4（周四） | 架构理解 | Cursor MCP 集成 |
| Day 5（周五） | 类比日 | 安全规范与类比日 |
| Day 6（周六） | 项目实战 | MCP 交付项目 |
| Day 7（周日） | 复盘预习 | 验收与预习 |

### Day 1（周一）：MCP 协议概述

**类型**：概念入门  
**今日目标**：理解 Server/Client/Tool。

**学习内容**：
- MCP 官方文档

**练习任务**：
- 读协议概述
- 列 MCP 与 REST 差异

**JS/Node 类比**：
- MCP≈AI 工具调用协议

**AI Review 提问**：
- 理解正确吗？

**今日产出**：
- MCP 概念笔记

**今日完成标准**：
- [ ] 能解释 MCP

---

### Day 2（周二）：现有 MCP Server 源码

**类型**：源码阅读  
**今日目标**：读 listTools 与 callTool。

**学习内容**：
- MCP TypeScript SDK

**源码阅读**：
- `mcp-server/*/src/index.ts`

**练习任务**：
- 读 mcp-server 一个 Server 的 index.ts
- 梳理 Tool 注册流程

**JS/Node 类比**：
- listTools≈OpenAPI 列接口

**AI Review 提问**：
- 注册流程清晰吗？

**今日产出**：
- 源码笔记

**今日完成标准**：
- [ ] 能说明注册流程

---

### Day 3（周三）：新增 dev 只读 Tool

**类型**：编码练习  
**今日目标**：实现并测试新 Tool。

**学习内容**：
- dev 环境安全规范

**练习任务**：
- 实现 dev 只读查询 Tool
- 定义 input schema

**JS/Node 类比**：
- Tool≈带 schema 的函数

**AI Review 提问**：
- 参数设计合理吗？

**今日产出**：
- Tool 源码

**今日完成标准**：
- [ ] Tool 可调用

---

### Day 4（周四）：Cursor MCP 集成

**类型**：架构理解  
**今日目标**：配置并在 Cursor 中调用。

**学习内容**：
- Cursor MCP 配置
- mcp.config.example.json

**源码阅读**：
- `ai-workspace/mcp.config.example.json`

**练习任务**：
- 配置 MCP
- 在 Cursor 测试调用
- 排查问题

**JS/Node 类比**：
- Cursor 集成≈IDE 插件

**AI Review 提问**：
- 调用成功吗？

**今日产出**：
- 配置说明

**今日完成标准**：
- [ ] Cursor 能调用

---

### Day 5（周五）：安全规范与类比日

**类型**：类比日  
**今日目标**：写 MCP 使用规范，完成打卡。

**学习内容**：
- mcp-routing 安全原则

**练习任务**：
- 写 dev/测试 only 规范
- 完成类比打卡

**JS/Node 类比**：
- 禁止生产库≈安全红线

**AI Review 提问**：
- 规范完整吗？

**今日产出**：
- 安全规范
- 类比打卡

**今日完成标准**：
- [ ] 确认未连生产

---

### Day 6（周六）：MCP 交付项目

**类型**：项目实战  
**今日目标**：完成 Tool + 配置文档。

**学习内容**：
- 交付清单

**练习任务**：
- 完善 Tool
- 写 MCP 配置文档

**JS/Node 类比**：
- MCP 交付≈可复用工具

**AI Review 提问**：
- 达到验收吗？

**今日产出**：
- MCP 交付包

**今日完成标准**：
- [ ] Cursor 调用成功

---

### Day 7（周日）：验收与预习

**类型**：复盘预习  
**今日目标**：验收并预习 Agent。

**学习内容**：
- 预习 Function Calling

**练习任务**：
- 勾选验收

**JS/Node 类比**：
- 准备好做 Agent 吗？

**AI Review 提问**：
- 周总结

**今日产出**：
- 完成验收

**今日完成标准**：

---

## 5. JS/Node.js 类比学习（本周总览）

MCP Tool≈OpenAI tools[]；stdio≈child_process。

### 本周类比打卡模板

```text
本周概念：
Node 等价：
差异：
我能用自己的话解释吗：是 / 否
理解自评：1 / 2 / 3 / 4 / 5
```

---

## 6. 本周产出物

- [ ] MCP Tool
- [ ] 配置说明
- [ ] 安全规范

---

## 7. 推荐学习资料

- MCP 规范
- MCP TS SDK

---

## 8. 本周验收标准

- [ ] Cursor 能调用
- [ ] 未连生产

---

## 9. AI Review 提示词

```text
我正在进行 Week 14：MCP Protocol + MCP Server 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：理解是否正确、JS 类比是否准确、是否遗漏风险、真实项目需注意什么。
请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 10. 周日复盘与下周预习

| 复盘项 | 记录 |
|--------|------|
| 本周最清楚的概念 |  |
| 本周最卡的概念 |  |
| JS/Node 类比是否帮助理解 |  |
| 本周产出是否完成 |  |
| 自评分（1-5） |  |

**下周预习**：预习 Agent Tool Calling。
