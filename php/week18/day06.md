# Week 18 Day 06：FAQ 项目交付

> 所属周：Week 18：Hybrid Search + Rerank  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/rag`  
> 类型：项目实战  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

完善 FAQ Agent 与优化笔记。

今天你要真正掌握这一句话：

> 交付一个项目，不是“代码能跑”就完事，而是要把整条链（BM25 → Hybrid → Rerank → 生成 → 引用）串成一个**可配置、有兜底、能演示、有文档**的完整包；别人拿到你的项目，看着 README 就能跑起来、看着优化笔记就知道你为什么这么调。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 明确“交付”和“能跑”的区别
2. 把前五天的零散代码整合成一个 FAQ Agent 模块
3. 把关键参数抽成配置（权重、Top-k、阈值）
4. 加兜底：检索为空、LLM 失败、无答案时怎么办
5. 写一个可演示的入口（命令行问答）
6. 写优化笔记：记录调参过程和结论
7. 写 README：别人怎么跑起来
8. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 “能跑”和“交付”的区别

前五天你写了一堆脚本，散落各处。今天要把它们收拢成一个能交付的项目。

| 维度 | 能跑（demo） | 可交付（项目） |
|---|---|---|
| 结构 | 一个大文件 | 分模块、职责清晰 |
| 参数 | 写死在代码里 | 抽成配置 |
| 异常 | 崩了就崩了 | 有兜底提示 |
| 使用 | 自己知道怎么跑 | 有 README |
| 调参 | 凭记忆 | 有优化笔记 |

小白重点：交付的核心是“**别人不用问你，就能用起来**”。

---

### 1.2 整合成一个 FAQ Agent 模块

把 Day04 的 Agent 补全成一个完整类，参数集中在构造函数：

```python
# faq_agent.py
class FaqAgent:
    def __init__(self, docs, config=None):
        self.docs = docs
        # 参数集中管理，方便调优和交付
        self.config = config or {
            "recall_top_k": 20,   # 粗召回数量
            "final_top_k": 3,     # 精排后送入 LLM 的数量
            "bm25_weight": 0.5,   # Hybrid 关键词权重
            "vec_weight": 0.5,    # Hybrid 语义权重
            "min_score": 0.1,     # 低于此分数视为无相关文档
            "temperature": 0.2,
        }
        self._build_index()

    def _build_index(self):
        # 建 BM25 索引 + 向量索引（省略细节，复用前几天代码）
        ...

    def retrieve(self, query, top_k=None):
        # Hybrid 粗召回 + Rerank 精排，返回带 id 的文档
        ...

    def answer(self, query):
        contexts = self.retrieve(query, top_k=self.config["final_top_k"])
        # 兜底见 1.3
        if not contexts or contexts[0]["score"] < self.config["min_score"]:
            return {"answer": "抱歉，知识库里暂时没有相关信息。", "sources": []}
        return self._generate(query, contexts)
```

小白重点：把 `bm25_weight`、`top_k` 这些参数从代码里抽出来放进 `config`，就是从“demo”走向“可交付”的关键一步——调参不用改代码，交付时别人也能按需改。

---

### 1.3 加兜底：三种失败场景

生产项目最怕“没兜底”。至少处理三种情况：

```python
def answer(self, query):
    # 兜底 1：输入为空
    if not query or not query.strip():
        return {"answer": "请输入你的问题。", "sources": []}

    contexts = self.retrieve(query)

    # 兜底 2：没检索到相关文档（避免 LLM 硬编）
    if not contexts or contexts[0]["score"] < self.config["min_score"]:
        return {"answer": "抱歉，知识库里暂时没有相关信息。", "sources": []}

    # 兜底 3：LLM 调用失败
    try:
        return self._generate(query, contexts)
    except Exception as e:
        return {"answer": "服务暂时不可用，请稍后再试。", "sources": [], "error": str(e)}
```

对比有无兜底：

| 场景 | 无兜底 | 有兜底 |
|---|---|---|
| 空输入 | 报错崩溃 | 提示“请输入问题” |
| 无相关文档 | LLM 瞎编 | 明确告知“没有相关信息” |
| LLM 超时 | 500 错误 | 友好提示“稍后再试” |

小白重点：“检索为空就明确说没有”是防幻觉的最后一道闸——宁可说不知道，也不让 LLM 硬编。

---

### 1.4 可演示入口

给项目一个能直接跑的命令行入口：

```python
# main.py
from faq_agent import FaqAgent
from knowledge_base import DOCS  # 你的知识库文档列表

def main():
    agent = FaqAgent(DOCS)
    print("FAQ 助手已就绪，输入问题（输入 q 退出）")
    while True:
        query = input("\n你问：").strip()
        if query.lower() == "q":
            break
        result = agent.answer(query)
        print(f"回答：{result['answer']}")
        if result["sources"]:
            print("引用来源：")
            for s in result["sources"]:
                print(f"  [{s['index']}] {s['title']}")

if __name__ == "__main__":
    main()
```

小白重点：一个 `python main.py` 就能演示的入口，是交付的门面。别人不用读你全部代码，跑一下就懂它能干嘛。

---

### 1.5 写优化笔记

优化笔记记录你“调了什么、为什么、结果如何”。示例：

```markdown
# FAQ Agent 优化笔记

## 基线
- 纯语义检索，准确率 60%

## 优化 1：加入 BM25 混合（Day02）
- 改动：Hybrid 融合，权重 0.5/0.5
- 结果：准确率 60% → 68%
- 结论：专有名词类问题召回明显变好

## 优化 2：加 Rerank 精排（Day03）
- 改动：粗召回 20，Rerank 取 Top-3
- 结果：准确率 68% → 76%
- 结论：语义相近但答非所问的干扰文档被排下去了

## 待优化
- "咋改密码"类口语化问法仍偶尔漏召回，考虑加同义词表
```

小白重点：优化笔记是交付的“灵魂”。它证明你的参数不是拍脑袋，而是评估驱动出来的。面试和 review 时，这份笔记最能体现你的工程思维。

---

### 1.6 写 README

README 让别人 5 分钟跑起来：

```markdown
# FAQ Agent

基于 Hybrid Search + Rerank 的知识库问答，回答附引用来源。

## 快速开始
1. 安装依赖：pip install -r requirements.txt
2. 配置 API Key：export LLM_API_KEY=你的key
3. 运行：python main.py

## 目录结构
- faq_agent.py    核心 Agent
- knowledge_base.py  知识库文档
- eval_set.py     评估集
- main.py         命令行入口

## 参数说明
见 faq_agent.py 中 config（权重、Top-k、阈值均可调）
```

小白重点：API Key 用环境变量注入，**绝不能写进代码提交**。这是交付的安全底线。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

可对照 `ai-lab/rag` 项目里已有的 README、config 文件，看成熟项目怎么组织参数和文档。

---

## 3. 练习任务

### 练习 1：整合 FAQ Agent

把前五天的代码收拢成一个 `FaqAgent` 类，参数集中到 `config`。

目标：一个类完成检索+精排+生成+引用。

---

### 练习 2：补齐三种兜底

实现空输入、无相关文档、LLM 失败三种兜底。

目标：任何异常输入都不崩，给出友好提示。

---

### 练习 3：写命令行入口

写 `main.py`，实现循环问答 + 打印引用。

目标：`python main.py` 能直接演示。

---

### 练习 4：写优化笔记

按 1.5 节格式，把本周的调参过程和准确率变化记下来。

目标：产出一份评估驱动的优化笔记。

---

### 练习 5：写 README

写清依赖、启动、目录结构、参数说明。

目标：别人照 README 能独立跑起来。

---

## 4. JS/Node.js 类比

| Python / 交付 | Node.js 类比 | 说明 |
|---|---|---|
| config 字典 | config.js / .env | 参数集中管理 |
| try/except 兜底 | try/catch + 错误中间件 | 异常不外泄 |
| main.py 入口 | index.js / npm start | 项目启动入口 |
| requirements.txt | package.json | 依赖清单 |
| README | README | 使用文档 |
| 环境变量注入 Key | process.env | 密钥不进代码 |

小白重点：交付一个 Python 项目和交付一个 Node 项目，思路完全一致——配置抽离、异常兜底、入口清晰、文档齐全、密钥外置。语言不同，工程标准相同。

---

## 5. AI Review 提问

```text
我正在学习 Week 18 Day 06：FAQ 项目交付。

请你按资深工程师标准帮我检查：

1. 我的 FaqAgent 类职责划分清晰吗？
2. 我的参数配置抽离得合理吗？漏了哪些该配的参数？
3. 我的三种兜底够吗？还有哪些异常场景没覆盖？
4. 我的优化笔记能说明调参是评估驱动的吗？
5. 我的 README 能让别人独立跑起来吗？密钥处理安全吗？

请用中文输出：
- 我理解正确的地方
- 我理解错误或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

- [ ] 整合后的 `faq_agent.py`（含 config）
- [ ] 三种兜底逻辑
- [ ] 命令行入口 `main.py`
- [ ] 优化笔记（含准确率变化）
- [ ] README
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说清“能跑”和“可交付”的区别
- [ ] FAQ Agent 整合成一个模块，参数抽成 config
- [ ] 覆盖空输入、无文档、LLM 失败三种兜底
- [ ] `python main.py` 能演示问答并显示引用
- [ ] 有优化笔记，能说明每次调参的依据和效果
- [ ] 有 README，密钥通过环境变量注入不进代码

---

## 8. 今日自测题

### 8.1 “能跑”和“可交付”最大的区别是什么？

参考答案：

> ✅ 可交付意味着别人不用问你就能用起来：结构清晰、参数可配、有兜底、有 README 和优化笔记。能跑只是自己电脑上不报错。

---

### 8.2 为什么要把参数抽成 config？

参考答案：

> ✅ 调参不用改代码，交付时别人也能按需调整。权重、Top-k、阈值这些集中管理，也方便做评估对比。

---

### 8.3 “检索为空就明确说没有”有什么意义？

参考答案：

> ✅ 这是防幻觉的最后一道闸。没相关文档时若还让 LLM 硬答，它会瞎编。明确告知“没有相关信息”比编一个错答案更可靠。

---

### 8.4 优化笔记为什么重要？

参考答案：

> ✅ 它证明参数是评估驱动出来的，不是拍脑袋。记录了每次改动的依据和准确率变化，体现工程思维，也方便后人接手。

---

### 8.5 API Key 应该怎么处理？

参考答案：

> ✅ 通过环境变量注入（如 export LLM_API_KEY=xxx），绝不写进代码或提交到仓库。这是交付的安全底线。

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
我正在进行 Week 18 Day 06：FAQ 项目交付 的学习。
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
