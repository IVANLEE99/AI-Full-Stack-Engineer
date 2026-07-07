# Week 18 Day 04：FAQ Agent

> 所属周：Week 18：Hybrid Search + Rerank  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/rag`  
> 类型：架构理解  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

实现检索 + 生成 + 引用。

今天你要真正掌握这一句话：

> FAQ Agent = 检索（Retrieval）+ 生成（Generation）+ 引用（Citation）。它先用前三天的检索链拿到最相关的几篇文档，把它们拼进 prompt 交给 LLM 生成答案，并强制要求答案**只能基于给定文档**、且要**标注引用来源**，这样答案可溯源、能防幻觉。

前三天你做出了一条“能把最相关文档排到最前”的检索链。今天在它末尾接上 LLM，做出一个真正能回答问题、还能说出“这句话出自哪篇文档”的 FAQ 机器人。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 理解 RAG 的三个字母：R（检索）A（增强）G（生成）
2. 理解为什么要“检索增强”，而不是直接问 LLM
3. 理解 prompt 里怎么把检索到的文档拼进去
4. 理解引用（Citation）是怎么做的、为什么重要
5. 写一个最小可跑的 FAQ Agent
6. 理解“无答案”场景：检索不到时要老实说不知道
7. 理解幻觉（hallucination）和如何用引用约束它
8. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 RAG 是什么，为什么需要它

RAG = Retrieval-Augmented Generation，检索增强生成。

不用 RAG，直接问 LLM 会有几个问题：

- **不知道你公司的私有知识**（LLM 没见过你的内部文档）。
- **可能编造**（幻觉，一本正经地胡说）。
- **无法溯源**（你不知道答案从哪来，没法核实）。

RAG 的思路是：**先检索，再让 LLM 基于检索到的真实文档作答**。

```text
不用 RAG：  用户问题 ─────────────► LLM ──► 答案（可能瞎编）

用 RAG：    用户问题 ─► 检索文档 ─► LLM ──► 答案（基于真实文档 + 引用）
```

小白重点：

> RAG 不是让 LLM 变聪明，而是**给它一份“开卷考试”的参考资料**，逼它照着资料回答，而不是凭记忆瞎猜。

---

### 1.2 FAQ Agent 的三段结构

```text
① Retrieval 检索：用 Day01-03 的链路拿 Top-3 文档
② Augment 增强：把 Top-3 拼进 prompt，作为“已知资料”
③ Generation 生成：LLM 基于资料作答，并标注引用
```

对应到代码就是三个步骤，我们逐个拆。

---

### 1.3 第一步：检索

直接复用 Day03 的 `SearchPipeline`：

```python
from search_pipeline import SearchPipeline


class FaqAgent:
    def __init__(self, docs: list[str]):
        self.pipeline = SearchPipeline(docs)
        self.docs = docs

    def retrieve(self, query: str, top_k: int = 3) -> list[dict]:
        return self.pipeline.search(query, top_k=top_k)
```

这一步的输出是 Top-3 候选，每条带 `id` 和 `text`，供下一步拼 prompt。

---

### 1.4 第二步：把文档拼进 prompt（增强）

关键是给每篇文档编号，让 LLM 引用时能写“来源 [1]”：

```python
    def build_prompt(self, query: str, contexts: list[dict]) -> str:
        # 给每篇资料编号 [1] [2] [3]
        blocks = []
        for i, c in enumerate(contexts, start=1):
            blocks.append(f"[{i}] {c['text']}")
        knowledge = "\n".join(blocks)

        return f"""你是企业知识库客服助手。请**只根据下面给出的资料**回答用户问题。
规则：
1. 答案必须来自资料，不能编造资料里没有的内容。
2. 在用到某条资料时，用 [编号] 标注引用来源，例如 [1]。
3. 如果资料里没有能回答问题的内容，就直接回答“抱歉，知识库中暂无相关信息”。

资料：
{knowledge}

用户问题：{query}

请回答："""
```

小白重点：这段 prompt 里有三条硬规则——**只用资料、标引用、无则说不知道**。这三条正是 FAQ Agent 和普通聊天机器人的区别。

---

### 1.5 第三步：调 LLM 生成（脱敏）

```python
import os
import requests

    def generate(self, prompt: str) -> str:
        api_key = os.environ["LLM_API_KEY"]  # 从环境变量读，不写死
        resp = requests.post(
            "https://api.example-llm.com/v1/chat/completions",  # 脱敏占位
            headers={"Authorization": f"Bearer {api_key}"},
            json={
                "model": "example-chat-model",
                "messages": [{"role": "user", "content": prompt}],
                "temperature": 0.1,  # 低温度，减少发挥，贴着资料答
            },
            timeout=30,
        )
        resp.raise_for_status()
        return resp.json()["choices"][0]["message"]["content"]
```

小白重点：`temperature` 设很低（0.1）。FAQ 场景要的是“照着资料准确回答”，不是创意写作，所以要压低随机性。

---

### 1.6 组装：完整的 answer 方法

```python
    def answer(self, query: str) -> dict:
        contexts = self.retrieve(query, top_k=3)

        # 兜底：检索不到就别硬答
        if not contexts:
            return {"answer": "抱歉，知识库中暂无相关信息", "sources": []}

        prompt = self.build_prompt(query, contexts)
        text = self.generate(prompt)

        # 把引用来源一并返回，便于前端展示
        sources = [
            {"index": i, "text": c["text"]}
            for i, c in enumerate(contexts, start=1)
        ]
        return {"answer": text, "sources": sources}


if __name__ == "__main__":
    docs = [
        "如何重置账户口令：进入设置-安全-重置密码，按短信验证码操作。",
        "订单支付失败：请检查银行卡余额或更换支付方式后重试。",
        "退款一般 3-7 个工作日到账。",
    ]
    agent = FaqAgent(docs)
    result = agent.answer("我密码忘了怎么办")
    print(result["answer"])
    print("引用来源：")
    for s in result["sources"]:
        print(f"  [{s['index']}] {s['text']}")
```

这样 `answer()` 返回一个字典：`answer` 是生成的答案文本，`sources` 是引用到的原文，前端可以直接渲染成“答案 + 参考来源”。

---

### 1.7 引用（Citation）为什么重要

引用做三件事：

| 作用 | 说明 |
|---|---|
| 可溯源 | 用户能点开来源核实，不用盲信 |
| 防幻觉 | 强制 LLM 贴着资料答，编不出来源就不敢瞎说 |
| 可调试 | 答错时你能快速定位是检索错了还是生成错了 |

小白重点：

> 没有引用的 RAG 答案是“不可信”的。企业知识库场景，引用几乎是硬性要求——答案必须能回答“这句话你从哪看到的”。

---

### 1.8 幻觉与“无答案”处理

幻觉（hallucination）指 LLM 编造资料里没有的内容。对抗它有三招，我们都用上了：

1. **prompt 硬约束**：明确写“只根据资料回答，不许编”。
2. **低 temperature**：减少自由发挥。
3. **无答案兜底**：检索不到或资料不含答案时，回答“暂无相关信息”，而不是硬凑。

对比：

| 场景 | 坏的做法 | 好的做法 |
|---|---|---|
| 资料里没有 | LLM 凭记忆编一个答案 | 老实说“知识库中暂无相关信息” |
| 资料有但不全 | 补充资料外的内容 | 只答资料覆盖的部分并引用 |
| 用户问闲聊 | 长篇发挥 | 引导回到知识库范围 |

---

## 2. 源码阅读

- `ai-lab/rag/faq_agent.py`（若无则读 `agent.py` / `rag_chain.py`）

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. 检索取 Top 几喂给 LLM
2. prompt 里如何给文档编号、如何要求引用
3. temperature 设成多少
4. 检索为空时怎么兜底
5. 返回结构里有没有把 sources 单独带出来

建议在笔记里写出这张表：

| 环节 | 项目里的做法 | 我的理解 |
|---|---|---|
| 检索条数 |  |  |
| prompt 约束 |  |  |
| 引用格式 |  |  |
| 无答案兜底 |  |  |

---

## 3. 练习任务

### 练习 1：跑通最小 FAQ Agent

按 1.6 节写出 `faq_agent.py`，用密码相关的问题测试，确认答案里带 `[1]` 之类的引用标注。

目标：拥有一个能检索 + 生成 + 引用的最小 Agent。

---

### 练习 2：测试“无答案”

问一个知识库里完全没有的问题（如“今天天气如何”），确认它老实回答“暂无相关信息”，而不是瞎编。

目标：验证防幻觉的兜底逻辑生效。

---

### 练习 3：验证引用正确性

挑 3 个问题，人工核对答案里标的 `[编号]` 是否真的对应正确的原文。

目标：确认引用不是摆设，是真的对得上。

---

### 练习 4：对比开卷 vs 闭卷

同一个私有知识问题，一次走 FAQ Agent（开卷），一次直接问 LLM（闭卷），对比答案质量。

目标：亲眼看到 RAG 的价值。

---

### 练习 5：调 temperature

把 temperature 从 0.1 调到 0.9，观察答案是否变得更“放飞”、更容易脱离资料。

目标：理解 temperature 对 FAQ 场景的影响。

---

## 4. JS/Node.js 类比

| Python / RAG | Node.js 类比 | 说明 |
|---|---|---|
| Retrieval | 查数据库拿相关记录 | 先取数据 |
| build_prompt | 拼接模板字符串 | 把数据填进模板 |
| LLM 生成 | 调外部 API 返回结果 | `fetch` + 密钥 |
| sources 返回 | API 返回里带 metadata | 附带来源信息 |
| 无答案兜底 | 查询为空返回默认值 | 空结果处理 |

Node 里 FAQ Agent 的骨架：

```js
async function answer(query) {
  const contexts = await pipeline.search(query, 3);
  if (contexts.length === 0) {
    return { answer: "抱歉，知识库中暂无相关信息", sources: [] };
  }
  const prompt = buildPrompt(query, contexts);
  const text = await callLLM(prompt);
  const sources = contexts.map((c, i) => ({ index: i + 1, text: c.text }));
  return { answer: text, sources };
}
```

小白重点：结构和你平时写的“查库 → 组装 → 调外部服务 → 返回带 metadata 的结果”接口一模一样。RAG 只是把“外部服务”换成了 LLM。

---

## 5. AI Review 提问

```text
我正在学习 Week 18 Day 04：FAQ Agent（检索 + 生成 + 引用）。

请你按资深工程师标准帮我检查：

1. 我的 RAG 三段结构（检索/增强/生成）理解对吗？
2. 我的 prompt 能有效约束 LLM 只用资料、标引用、无则说不知道吗？
3. 引用（Citation）我实现得对吗？sources 能对得上原文吗？
4. 我的防幻觉措施够不够？还漏了什么？
5. 真实企业 FAQ 系统里，还需要考虑哪些问题（多轮、权限、日志）？

请用中文输出：
- 我理解正确的地方
- 我理解错误或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

- [ ] `faq_agent.py`：检索 + 生成 + 引用的完整 Agent
- [ ] “无答案”场景测试记录
- [ ] 引用正确性人工核对结果
- [ ] 开卷 vs 闭卷对比记录
- [ ] temperature 影响观察
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能解释 RAG 是什么、为什么需要它
- [ ] 能说清 FAQ Agent 的三段结构
- [ ] Agent 能检索并生成答案
- [ ] 答案里带正确的引用标注
- [ ] 检索不到时能老实说“暂无相关信息”
- [ ] 能解释引用为什么能防幻觉、能溯源

---

## 8. 今日自测题

### 8.1 RAG 三个字母分别代表什么？

参考答案：

> ✅ Retrieval（检索）、Augmented（增强，把检索到的文档拼进 prompt）、Generation（生成）。核心是让 LLM 基于检索到的真实文档作答，而不是凭记忆瞎猜。

---

### 8.2 为什么不直接问 LLM，非要 RAG？

参考答案：

> ✅ 因为 LLM 不知道你的私有知识、可能编造（幻觉）、且无法溯源。RAG 给它一份“开卷参考资料”，逼它照资料回答，并能标注来源供核实。

---

### 8.3 引用（Citation）有什么作用？

参考答案：

> ✅ 可溯源（用户能核实）、防幻觉（逼 LLM 贴着资料答）、可调试（答错时能定位是检索问题还是生成问题）。企业知识库里几乎是硬性要求。

---

### 8.4 检索不到相关文档时，Agent 该怎么做？

参考答案：

> ✅ 应该老实回答“知识库中暂无相关信息”，而不是硬凑或凭记忆编造。这是防幻觉的重要兜底。

---

### 8.5 FAQ 场景为什么 temperature 要设低？

参考答案：

> ✅ FAQ 要的是准确、贴着资料回答，不是创意发挥。低 temperature 减少随机性，让答案更稳定、更少脱离资料。

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
我正在进行 Week 18 Day 04：FAQ Agent 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 18 README](./README.md)
