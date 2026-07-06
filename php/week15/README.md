# Week 15：Agent + Tool Calling

> 强度建议：约 20h/周（周一到周五各 3h + 周末 5h）  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review  
> 公开说明：使用匿名仓库代号，不包含公司、品牌、内网域名或本地绝对路径。

---

## 1. 本周定位

- 阶段：第四阶段：AI Backend
- 主仓库/项目：`ai-lab/customer-agent`
- 本周目标：构建客服 Agent 调 MCP。

### 为什么本周要学这些

- 从调 LLM 升级为 LLM 调工具。

---

## 2. 本周需要掌握的知识点

1. Agent 架构
2. Tool Calling
3. Prompt
4. 多场景
5. Fallback

### php-pro 能力对齐

- System Prompt 约束边界
- 记录 tool_call

---

## 3. 必读代码/文件路径

- `ai-lab/customer-agent/src/agent.ts`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 4. 七天详细学习安排

| 天 | 类型 | 主题 |
|----|------|------|
| Day 1（周一） | 概念入门 | Agent 架构设计 |
| Day 2（周二） | 源码阅读 | MCP Tool 集成 |
| Day 3（周三） | 编码练习 | System Prompt 工程 |
| Day 4（周四） | 架构理解 | 多场景路由 |
| Day 5（周五） | 类比日 | 错误处理与类比日 |
| Day 6（周六） | 项目实战 | 客服 Agent Demo |
| Day 7（周日） | 复盘预习 | 验收与预习 |

### Day 1（周一）：Agent 架构设计

**类型**：概念入门  
**今日目标**：画客服 Agent 架构图。

**学习内容**：
- OpenAI Function Calling

**练习任务**：
- 画架构图
- 写 Agent 循环伪代码

**JS/Node 类比**：
- Agent 循环≈while+tool_call

**AI Review 提问**：
- 架构合理吗？

**今日产出**：
- 架构图

**今日完成标准**：
- [ ] 能画架构

---

### Day 2（周二）：MCP Tool 集成

**类型**：源码阅读  
**今日目标**：实现查订单意图与 Tool 调用。

**学习内容**：
- 接入 W14 MCP Tool

**练习任务**：
- 实现意图识别
- 调用 MCP 查订单
- 格式化返回

**JS/Node 类比**：
- Tool Calling≈LLM 调 API

**AI Review 提问**：
- 调用链正确吗？

**今日产出**：
- 集成代码

**今日完成标准**：
- [ ] 查订单可用

---

### Day 3（周三）：System Prompt 工程

**类型**：编码练习  
**今日目标**：编写客服 Agent Prompt。

**学习内容**：
- Prompt 最佳实践

**练习任务**：
- 写 System Prompt
- 约束边界与语气
- 测试 3 轮对话

**JS/Node 类比**：
- System Prompt≈全局 middleware 上下文

**AI Review 提问**：
- Prompt 够清晰吗？

**今日产出**：
- Prompt 文档

**今日完成标准**：
- [ ] 边界明确

---

### Day 4（周四）：多场景路由

**类型**：架构理解  
**今日目标**：实现查商品与 FAQ 兜底。

**学习内容**：
- 意图分类

**练习任务**：
- 实现 3 种意图路由
- 测试各场景

**JS/Node 类比**：
- 意图路由≈switch+策略

**AI Review 提问**：
- 路由准确吗？

**今日产出**：
- 测试记录

**今日完成标准**：
- [ ] 3 场景通过

---

### Day 5（周五）：错误处理与类比日

**类型**：类比日  
**今日目标**：加 fallback，完成打卡。

**学习内容**：
- Tool 失败处理

**练习任务**：
- 实现 fallback
- 完成类比打卡

**JS/Node 类比**：
- fallback≈try/catch 友好提示

**AI Review 提问**：
- 兜底够吗？

**今日产出**：
- fallback 代码

**今日完成标准**：
- [ ] 失败有友好提示

---

### Day 6（周六）：客服 Agent Demo

**类型**：项目实战  
**今日目标**：可演示的 Agent Demo。

**学习内容**：
- Demo 脚本

**练习任务**：
- 完善 Demo
- 写使用文档

**JS/Node 类比**：
- Demo≈可展示成果

**AI Review 提问**：
- 可演示吗？

**今日产出**：
- Agent Demo

**今日完成标准**：
- [ ] 3 意图正确

---

### Day 7（周日）：验收与预习

**类型**：复盘预习  
**今日目标**：验收并预习 LangGraph。

**学习内容**：
- 预习 LangGraph

**练习任务**：
- 勾选验收

**JS/Node 类比**：
- 准备好学编排吗？

**AI Review 提问**：
- 周总结

**今日产出**：
- 完成验收

**今日完成标准**：

---

## 5. JS/Node.js 类比学习（本周总览）

Agent 循环≈while+tool_call+feed back。

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

- [ ] Agent Demo
- [ ] Prompt 文档
- [ ] 测试场景

---

## 7. 推荐学习资料

- OpenAI Function Calling
- Anthropic Tool Use

---

## 8. 本周验收标准

- [ ] 3 意图正确响应
- [ ] 有 fallback

---

## 9. AI Review 提示词

```text
我正在进行 Week 15：Agent + Tool Calling 的学习。
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

**下周预习**：预习 LangGraph。
