# Week 18 Day 03：Rerank 精排

> 所属周：Week 18：Hybrid Search + Rerank  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/rag`  
> 类型：编码练习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

对 Top-20 重排序。

今天你要真正掌握这一句话：

> Rerank（精排）是在“粗排”拿到 Top-20 候选后，用一个**更贵但更准**的模型（cross-encoder / rerank 模型）把 query 和每一篇候选文档**成对**送进去打分，再重新排序，只留下最相关的几篇；它牺牲速度换准确率，所以只对少量候选做，不对全库做。

前两天你做出了粗排——BM25、语义、Hybrid 融合，能从全库里快速捞出 Top-20。但 Top-20 的顺序还不够精细。今天加一层精排，把真正最相关的那几篇顶到最前面，供后面的 LLM 生成使用。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 理解“召回 → 粗排 → 精排”两阶段检索的分工
2. 理解 bi-encoder（双塔）和 cross-encoder（交叉）的根本区别
3. 理解为什么 cross-encoder 更准但更慢，只能用于精排
4. 用本地 cross-encoder 模型对 Top-20 重排序
5. 也了解一下调用云端 Rerank API 的方式（脱敏）
6. 对比精排前后的排序变化
7. 理解 Rerank 在 RAG 里的位置（在检索和生成之间）
8. 写今日笔记和自测

装今天要用的库：

```bash
pip install sentence-transformers
```

`sentence-transformers` 里带了 `CrossEncoder`，可以本地跑精排，无需联网调 API。

---

## 1. 学习内容

### 1.1 两阶段检索：召回、粗排、精排

工业级检索几乎都是“漏斗”结构：

```text
全库（几十万篇）
   │  召回 + 粗排：BM25 / 向量 / Hybrid，快，但不够精
   ▼
Top-20（候选池）
   │  精排 Rerank：cross-encoder，慢，但很准
   ▼
Top-3~5（最终喂给 LLM）
```

为什么要分两段：

- 全库太大，只能用**快**的方法（向量近似、BM25）先粗筛。
- 粗筛出的 Top-20 已经很小，可以用**慢而准**的方法精细排序。

小白重点：

> 精排不是替代粗排，而是**接在粗排后面**。没有粗排先缩小范围，精排根本跑不动（对几十万篇做 cross-encoder 会慢到无法接受）。

---

### 1.2 bi-encoder vs cross-encoder

这是今天最核心的概念，务必分清。

**bi-encoder（双塔，昨天的语义检索用的就是它）**：

```text
query  ──►[编码器]──► 向量A
文档   ──►[编码器]──► 向量B      （文档向量可离线预先算好）
                     然后算 A·B 相似度
```

query 和文档**分开**编码，各自变成一个向量，再算相似度。好处是文档向量能提前算好存起来，检索时只编码 query，很快。坏处是 query 和文档从没“见过面”，交互信息丢失，精度有限。

**cross-encoder（交叉，今天精排用它）**：

```text
[query + 文档] 拼在一起 ──►[编码器]──► 一个相关性分数
```

query 和文档**拼成一句**一起送进模型，模型能看到两者每个词之间的交互，判断更准。坏处是每来一对都要重新算，无法预存，慢。

对比表：

| 维度 | bi-encoder（粗排/召回） | cross-encoder（精排） |
|---|---|---|
| query 和文档 | 分开编码 | 拼在一起编码 |
| 文档向量能否预存 | ✅ 能 | ❌ 不能 |
| 速度 | 快（适合全库） | 慢（只能少量） |
| 精度 | 一般 | 高 |
| 用在哪一阶段 | 召回 / 粗排 | 精排 |

---

### 1.3 用本地 cross-encoder 做精排

新建 `rerank.py`：

```python
from sentence_transformers import CrossEncoder


class Reranker:
    """精排：用 cross-encoder 对候选文档重新打分。"""

    def __init__(self):
        # 一个常用的多语言 rerank 模型，第一次会自动下载
        self.model = CrossEncoder("BAAI/bge-reranker-base")

    def rerank(self, query: str, candidates: list[dict], top_k: int = 5) -> list[dict]:
        """
        candidates: 粗排给出的候选 [{"id","text","score"}, ...]
        返回精排后的 top_k。
        """
        # 构造 [query, doc] 成对输入
        pairs = [[query, c["text"]] for c in candidates]
        # 一次性给所有 pair 打分
        scores = self.model.predict(pairs)

        reranked = []
        for c, s in zip(candidates, scores):
            item = dict(c)
            item["rerank_score"] = float(s)
            reranked.append(item)

        reranked.sort(key=lambda x: x["rerank_score"], reverse=True)
        return reranked[:top_k]


if __name__ == "__main__":
    query = "怎么把登录密码改掉"
    candidates = [
        {"id": 0, "text": "订单支付失败怎么办", "score": 0.9},
        {"id": 1, "text": "如何重置账户口令", "score": 0.7},   # 真正最相关
        {"id": 2, "text": "如何修改绑定的手机号", "score": 0.8},
        {"id": 3, "text": "退款一般多久到账", "score": 0.6},
    ]
    reranker = Reranker()
    for r in reranker.rerank(query, candidates, top_k=3):
        print(f"{r['rerank_score']:.3f}  {r['text']}")
```

运行：

```bash
python3 rerank.py
```

观察：粗排里“如何重置账户口令”只排第 3（score 0.7 落后），但精排后它应该被顶到第 1——因为 cross-encoder 真正理解了“把登录密码改掉”≈“重置账户口令”。

小白重点：注意 `rerank()` 保留了原来的 `score`，又加了一个 `rerank_score`。这样你能对比精排前后名次的变化，也方便调试。

---

### 1.4 接进 Hybrid Search：完整粗排 + 精排链路

把昨天的 `HybridSearcher` 和今天的 `Reranker` 串起来。新建 `search_pipeline.py`：

```python
from hybrid_search import HybridSearcher
from rerank import Reranker


class SearchPipeline:
    def __init__(self, docs: list[str]):
        self.hybrid = HybridSearcher(docs)
        self.reranker = Reranker()

    def search(self, query: str, top_k: int = 3) -> list[dict]:
        # 第一阶段：粗排，多取候选（Top-20）
        candidates = self.hybrid.search(query, top_k=20)
        # 第二阶段：精排，缩到 Top-k
        return self.reranker.rerank(query, candidates, top_k=top_k)


if __name__ == "__main__":
    docs = [
        "如何重置账户口令",
        "订单支付失败怎么办",
        "如何修改绑定的手机号",
        "退款一般多久到账",
        "忘记密码如何找回",
        "SKU-8842 商品缺货登记",
    ]
    pipe = SearchPipeline(docs)
    for r in pipe.search("登录密码改不了", top_k=3):
        print(f"{r['rerank_score']:.3f}  {r['text']}")
```

这就是一条完整的检索链：**Hybrid 粗排（BM25 + 向量 + RRF）→ cross-encoder 精排**。明天的 FAQ Agent 就在这条链的末尾接上 LLM 生成。

---

### 1.5 了解云端 Rerank API（脱敏）

除了本地模型，很多云厂商提供 Rerank API（如 Cohere Rerank、通义、百度等）。调用形式大同小异：

```python
import os
import requests

def cloud_rerank(query: str, documents: list[str], top_k: int = 5) -> list[dict]:
    # 密钥从环境变量读，绝不写死在代码里
    api_key = os.environ["RERANK_API_KEY"]
    resp = requests.post(
        "https://api.example-rerank.com/v1/rerank",  # 脱敏占位
        headers={"Authorization": f"Bearer {api_key}"},
        json={
            "model": "rerank-multilingual-v1",
            "query": query,
            "documents": documents,
            "top_n": top_k,
        },
        timeout=10,
    )
    resp.raise_for_status()
    # 返回通常是 [{"index": 原始下标, "relevance_score": 分数}, ...]
    return resp.json()["results"]
```

本地模型 vs 云端 API：

| 维度 | 本地 cross-encoder | 云端 Rerank API |
|---|---|---|
| 成本 | 只花机器算力 | 按调用量付费 |
| 数据隐私 | 数据不出内网 | 数据发给第三方 |
| 运维 | 要管模型、显存 | 免运维 |
| 延迟 | 取决于本地硬件 | 取决于网络 + 服务 |
| 适合 | 数据敏感 / 量大 | 快速起步 / 量小 |

小白重点：企业知识库涉及内部数据时，通常倾向本地 rerank 模型，避免数据外发。

---

### 1.6 Rerank 在 RAG 里的位置

把整周的拼图拼起来看：

```text
用户 query
   │
   ├─► BM25 关键词路 ┐
   │                 ├─► RRF 融合 ─► Top-20 ─► Rerank 精排 ─► Top-3
   └─► 向量语义路   ┘                                          │
                                                               ▼
                                                        喂给 LLM 生成答案 + 引用
                                                        （Day04 FAQ Agent）
```

Rerank 卡在“检索”和“生成”的中间，作用是**保证喂给 LLM 的那 3 篇是全场最相关的**。喂错文档，LLM 再强也答不对——这就是所谓 “garbage in, garbage out”。

---

## 2. 源码阅读

- `ai-lab/rag/rerank.py`（若无则读 `search.py` 中精排相关片段）

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. 用的是本地 cross-encoder 还是云端 API
2. 精排的输入候选是多少条（一般 20~50）
3. 精排后保留几条（一般 3~5）
4. 有没有保留原始粗排分数，方便对比
5. 精排失败/超时时有没有降级（退回粗排结果）

建议在笔记里写出这张表：

| 参数 | 值 | 作用 |
|---|---|---|
| 精排输入候选数 |  | 太多则慢，太少则漏 |
| 精排输出条数 |  | 喂给 LLM 的上下文条数 |
| rerank 模型 |  | 本地 or 云端 |
| 超时降级策略 |  | 保证可用性 |

---

## 3. 练习任务

### 练习 1：跑通本地精排

按 1.3 节写出 `rerank.py`，确认“如何重置账户口令”被 cross-encoder 顶到第 1。

目标：亲眼看到精排纠正了粗排的排序错误。

---

### 练习 2：串成完整链路

按 1.4 节写出 `search_pipeline.py`，跑 Hybrid 粗排 + Rerank 精排。

目标：拥有一条从 query 到 Top-3 的完整检索链。

---

### 练习 3：对比精排前后

对同一 query，打印“粗排 Top-5”和“精排 Top-5”，并排比较名次变化。

目标：量化精排带来的排序改变，记进笔记。

---

### 练习 4：测量延迟

用 `time.perf_counter()` 分别测“只粗排”和“粗排 + 精排”的耗时。

目标：亲身感受精排用速度换准确率的代价。

---

### 练习 5：构造精排能救回的 case

设计一个 query，让粗排把最相关文档排在第 4 名开外，但精排能把它顶回前 3。

目标：为 Rerank 的价值找到最有说服力的证据。

---

## 4. JS/Node.js 类比

| Python / RAG | Node.js 类比 | 说明 |
|---|---|---|
| 两阶段检索 | 数据库“索引筛选 + 内存精算” | 先粗筛再精算，同样思路 |
| bi-encoder | 提前算好的缓存字段 | 可预存，查得快 |
| cross-encoder | 请求时实时计算的贵操作 | 只对少量数据做 |
| `CrossEncoder.predict(pairs)` | 批量调用打分函数 | 一次算一批 pair |
| 云端 Rerank API | 调第三方排序服务 | `fetch` + 密钥 |

Node 里精排的等价结构：

```js
async function rerank(query, candidates, topK = 5) {
  const pairs = candidates.map((c) => [query, c.text]);
  const scores = await model.predict(pairs); // 假设有个打分模型
  return candidates
    .map((c, i) => ({ ...c, rerankScore: scores[i] }))
    .sort((a, b) => b.rerankScore - a.rerankScore)
    .slice(0, topK);
}
```

小白重点：精排本质就是“对候选重新算一个更准的分再排序”，和你在 Node 里对数组 `.map().sort().slice()` 是一模一样的流程，只是打分函数更聪明也更贵。

---

## 5. AI Review 提问

```text
我正在学习 Week 18 Day 03：Rerank 精排。

请你按资深工程师标准帮我检查：

1. 我能说清 bi-encoder 和 cross-encoder 的区别吗？为什么精排必须用 cross-encoder？
2. 为什么精排只能对少量候选做，不能对全库做？我理解对吗？
3. 我的精排代码有没有保留原始分数、方便对比？
4. 精排输入取 20、输出取 3~5，这个数量级合理吗？
5. 生产环境里精排超时或模型挂了，我该怎么降级？

请用中文输出：
- 我理解正确的地方
- 我理解错误或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

- [ ] `rerank.py`：本地 cross-encoder 精排
- [ ] `search_pipeline.py`：Hybrid 粗排 + Rerank 精排完整链路
- [ ] 精排前后排序对比表
- [ ] 延迟测量结果（粗排 vs 粗排+精排）
- [ ] 一个“精排能救回”的 case
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能画出“召回 → 粗排 → 精排”的漏斗
- [ ] 能说清 bi-encoder 和 cross-encoder 的区别
- [ ] 能解释为什么精排慢但准、只能用于少量候选
- [ ] 精排代码可用，能对 Top-20 重排序
- [ ] 完整检索链路可跑通
- [ ] 能对比精排前后的排序变化
- [ ] 知道精排失败时该如何降级

---

## 8. 今日自测题

### 8.1 bi-encoder 和 cross-encoder 有什么本质区别？

参考答案：

> ✅ bi-encoder 把 query 和文档分开编码成两个向量再算相似度，文档向量可预存，速度快但精度有限，用于召回/粗排；cross-encoder 把 query 和文档拼在一起送进模型，能捕捉两者的交互，更准但无法预存、很慢，用于精排。

---

### 8.2 为什么不直接对全库做 cross-encoder 精排？

参考答案：

> ✅ cross-encoder 每来一对 query-文档都要实时计算，对几十万篇跑一遍会慢到无法接受。所以要先用快的方法粗筛出 Top-20，再对这少量候选做精排。

---

### 8.3 精排在 RAG 流程里处于什么位置？

参考答案：

> ✅ 在“检索”和“生成”之间。它保证喂给 LLM 的那几篇是全场最相关的。喂错文档，LLM 再强也答不好，所以精排直接影响最终答案质量。

---

### 8.4 精排为什么要保留原始粗排分数？

参考答案：

> ✅ 便于对比精排前后的名次变化，验证精排是否真的起了作用，也方便调试和评估。

---

### 8.5 精排模型超时了怎么办？

参考答案：

> ✅ 应该有降级策略：当精排超时或出错时，退回使用粗排的排序结果，保证系统仍能返回答案，只是排序略差。可用性优先。

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
我正在进行 Week 18 Day 03：Rerank 精排 的学习。
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
