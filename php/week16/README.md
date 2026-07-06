# Week 16：编排模式对比

> 强度建议：约 20h/周（周一到周五各 3h + 周末 5h）  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review  
> 公开说明：使用匿名仓库代号，不包含公司、品牌、内网域名或本地绝对路径。

---

## 1. 本周定位

- 阶段：第四阶段：AI Backend
- 主仓库/项目：`pay-service + ai-lab`
- 本周目标：对比 LangGraph 与 NodeExecutionEngine。

### 为什么本周要学这些

- 支付/售后/Agent 都是流程编排。

---

## 2. 本周需要掌握的知识点

1. LangGraph
2. NodeEngine
3. Context
4. 条件分支

### php-pro 能力对齐

- 节点单一职责
- Context 清晰

---

## 3. 必读代码/文件路径

- `pay-service/common/services/pay/PayService.php`
- `aftersale-service/common/services/nodes/`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 4. 七天详细学习安排

| 天 | 类型 | 主题 |
|----|------|------|
| Day 1（周一） | 概念入门 | LangGraph 入门 |
| Day 2（周二） | 源码阅读 | PHP Node 链复习 |
| Day 3（周三） | 编码练习 | 编排模式对比 |
| Day 4（周四） | 架构理解 | 售后 Node 链 |
| Day 5（周五） | 类比日 | 阶段总结与类比日 |
| Day 6（周六） | 项目实战 | 编排模式交付 |
| Day 7（周日） | 复盘预习 | 验收与预习 |

### Day 1（周一）：LangGraph 入门

**类型**：概念入门  
**今日目标**：实现 3 步 Agent 工作流。

**学习内容**：
- LangGraph State/Node/Edge

**练习任务**：
- 实现 3 步工作流
- 理解 state 传递

**JS/Node 类比**：
- LangGraph State≈Redux store

**AI Review 提问**：
- 工作流正确吗？

**今日产出**：
- LangGraph Demo

**今日完成标准**：
- [ ] 3 步可运行

---

### Day 2（周二）：PHP Node 链复习

**类型**：源码阅读  
**今日目标**：重读支付 NodeExecutionEngine。

**学习内容**：
- 复习 W7 笔记

**源码阅读**：
- `pay-service/common/services/pay/PayService.php`

**练习任务**：
- 重读 PayService Node 链
- 列 Context 字段

**JS/Node 类比**：
- PHP Node≈同步 middleware 管道

**AI Review 提问**：
- Context 变化清楚吗？

**今日产出**：
- Context 字段表

**今日完成标准**：
- [ ] 能列 Context

---

### Day 3（周三）：编排模式对比

**类型**：编码练习  
**今日目标**：写两种模式伪代码与异同。

**学习内容**：
- 对比分析

**练习任务**：
- 写伪代码对比
- 列 3 处相同与 3 处不同

**JS/Node 类比**：
- 编排≈状态机+节点

**AI Review 提问**：
- 对比有深度吗？

**今日产出**：
- 对比文档

**今日完成标准**：
- [ ] 能说出 3 处异同

---

### Day 4（周四）：售后 Node 链

**类型**：架构理解  
**今日目标**：读 3 个售后 Node 并画图。

**学习内容**：
- 售后 nodes 目录

**源码阅读**：
- `aftersale-service/common/services/nodes/`

**练习任务**：
- 读 3 个 Node
- 画售后申请 Node 链

**JS/Node 类比**：
- 支付 vs 售后 Node 异同

**AI Review 提问**：
- 链路清晰吗？

**今日产出**：
- 售后 Node 图

**今日完成标准**：
- [ ] 能画售后链

---

### Day 5（周五）：阶段总结与类比日

**类型**：类比日  
**今日目标**：完成编排对比文档与阶段④自评。

**学习内容**：
- 阶段自评

**练习任务**：
- 完成对比文档
- 完成类比打卡
- 阶段④自评

**JS/Node 类比**：
- 阶段复盘

**AI Review 提问**：
- 达到阶段目标吗？

**今日产出**：
- 对比文档
- 阶段总结

**今日完成标准**：
- [ ] 完成阶段自评

---

### Day 6（周六）：编排模式交付

**类型**：项目实战  
**今日目标**：完善 LangGraph Demo 与文档。

**学习内容**：
- 交付标准

**练习任务**：
- 完善 Demo 与文档

**JS/Node 类比**：
- 交付≈可复现

**AI Review 提问**：
- 达标吗？

**今日产出**：
- 编排交付包

**今日完成标准**：
- [ ] Demo+文档完成

---

### Day 7（周日）：验收与预习

**类型**：复盘预习  
**今日目标**：验收并预习 RAG。

**学习内容**：
- 预习 Embeddings

**练习任务**：
- 勾选验收

**JS/Node 类比**：
- 准备好学 RAG 吗？

**AI Review 提问**：
- 周总结

**今日产出**：
- 完成验收

**今日完成标准**：

---

## 5. JS/Node.js 类比学习（本周总览）

LangGraph State≈Redux；PHP Node≈middleware pipeline。

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

- [ ] LangGraph Demo
- [ ] 编排对比文档
- [ ] 阶段④总结

---

## 7. 推荐学习资料

- LangGraph 文档

---

## 8. 本周验收标准

- [ ] 能说出 3 处异同
- [ ] 完成对比文档

---

## 9. AI Review 提示词

```text
我正在进行 Week 16：编排模式对比 的学习。
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

**下周预习**：预习 RAG Embedding。
