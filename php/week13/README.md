# Week 13：FastAPI + LLM Gateway

> 强度建议：约 20h/周（周一到周五各 3h + 周末 5h）  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review  
> 公开说明：使用匿名仓库代号，不包含公司、品牌、内网域名或本地绝对路径。

---

## 1. 本周定位

- 阶段：第四阶段：AI Backend
- 主仓库/项目：`ai-lab/llm-gateway`
- 本周目标：搭建多模型 LLM Gateway。

### 为什么本周要学这些

- AI 需要稳定 HTTP 入口。

---

## 2. 本周需要掌握的知识点

1. FastAPI
2. Pydantic
3. 多模型
4. API Key
5. 错误处理

### php-pro 能力对齐

- API Key 不写死
- 超时统一处理

---

## 3. 必读代码/文件路径

- `ai-lab/llm-gateway/app/main.py`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 4. 七天详细学习安排

| 天 | 类型 | 主题 |
|----|------|------|
| Day 1（周一） | 概念入门 | FastAPI 入门 |
| Day 2（周二） | 源码阅读 | 多模型 /chat 接口 |
| Day 3（周三） | 编码练习 | Pydantic 请求验证 |
| Day 4（周四） | 架构理解 | 错误处理与 API Key |
| Day 5（周五） | 类比日 | 类比日与测试 |
| Day 6（周六） | 项目实战 | LLM Gateway 交付 |
| Day 7（周日） | 复盘预习 | 验收与预习 |

### Day 1（周一）：FastAPI 入门

**类型**：概念入门  
**今日目标**：搭建项目并实现 /health。

**学习内容**：
- FastAPI 教程前 5 章

**源码阅读**：
- `ai-lab/llm-gateway/app/main.py`

**练习任务**：
- 创建 llm-gateway 项目
- 实现 /health
- 对比 Express

**JS/Node 类比**：
- FastAPI≈Express/Fastify

**AI Review 提问**：
- 项目结构合理吗？

**今日产出**：
- 项目骨架

**今日完成标准**：
- [ ] /health 可访问

---

### Day 2（周二）：多模型 /chat 接口

**类型**：源码阅读  
**今日目标**：接入 OpenAI 与 Claude。

**学习内容**：
- OpenAI/Anthropic API 文档

**练习任务**：
- 实现 /chat 支持 model 参数
- 用 curl 测试两模型

**JS/Node 类比**：
- 统一 Gateway≈多供应商抽象

**AI Review 提问**：
- 切换模型如何实现？

**今日产出**：
- /chat 接口

**今日完成标准**：
- [ ] 能切换 2 模型

---

### Day 3（周三）：Pydantic 请求验证

**类型**：编码练习  
**今日目标**：给 /chat 加请求体校验。

**学习内容**：
- Pydantic 文档

**练习任务**：
- 定义 ChatRequest model
- 校验 messages/model 字段

**JS/Node 类比**：
- Pydantic≈Zod

**AI Review 提问**：
- 校验足够吗？

**今日产出**：
- 校验代码

**今日完成标准**：
- [ ] 非法请求被拒绝

---

### Day 4（周四）：错误处理与 API Key

**类型**：架构理解  
**今日目标**：加鉴权与统一错误格式。

**学习内容**：
- FastAPI 异常处理

**练习任务**：
- 实现 API Key 校验
- 统一错误响应

**JS/Node 类比**：
- API Key≈Bearer token

**AI Review 提问**：
- 密钥如何管理？

**今日产出**：
- 错误处理代码

**今日完成标准**：
- [ ] 错误格式统一

---

### Day 5（周五）：类比日与测试

**类型**：类比日  
**今日目标**：curl 测全部接口，完成打卡。

**学习内容**：
- 回顾 FastAPI 笔记

**练习任务**：
- 测试全部接口
- 完成类比打卡
- 写 README

**JS/Node 类比**：
- FastAPI≈Express

**AI Review 提问**：
- 接口设计问题？

**今日产出**：
- 测试记录
- README

**今日完成标准**：
- [ ] 全部接口通过

---

### Day 6（周六）：LLM Gateway 交付

**类型**：项目实战  
**今日目标**：完善 Gateway 并写文档。

**学习内容**：
- 明确交付标准

**练习任务**：
- 完善代码
- 补充 README
- 记录 curl 示例

**JS/Node 类比**：
- Gateway≈AI 统一入口

**AI Review 提问**：
- 达到验收吗？

**今日产出**：
- Gateway 交付包

**今日完成标准**：
- [ ] 可切换 2 模型

---

### Day 7（周日）：验收与预习

**类型**：复盘预习  
**今日目标**：验收并预习 MCP。

**学习内容**：
- 预习 MCP 规范

**练习任务**：
- 勾选验收

**JS/Node 类比**：
- 准备好学 MCP 吗？

**AI Review 提问**：
- 周总结

**今日产出**：
- 完成验收

**今日完成标准**：

---

## 5. JS/Node.js 类比学习（本周总览）

FastAPI≈Express；Pydantic≈Zod。

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

- [ ] Gateway 源码
- [ ] curl 测试记录

---

## 7. 推荐学习资料

- FastAPI 教程
- OpenAI/Anthropic API

---

## 8. 本周验收标准

- [ ] 能切换 2 模型
- [ ] 接口可测

---

## 9. AI Review 提示词

```text
我正在进行 Week 13：FastAPI + LLM Gateway 的学习。
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

**下周预习**：预习 MCP 协议。
