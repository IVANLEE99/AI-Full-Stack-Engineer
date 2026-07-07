# Week 18 Day 02：Hybrid Search

> 所属周：Week 18：Hybrid Search + Rerank  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/rag`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

合并语义与关键词两路结果。

今天你要真正掌握这一句话：

> Hybrid Search 就是**同时跑关键词检索（BM25）和语义检索（向量）两条路，再把两路结果按某种规则融合成一个最终排序**；最常用、最稳的融合方法是 RRF（Reciprocal Rank Fusion，倒数排名融合），它只看“每条结果在各路里排第几名”，不看原始分数，因此不用担心两路分数量纲不同。

昨天你做出了关键词检索路（BM25），也亲手构造了它的失败 case（换个说法就搜不到）。今天补上语义检索路，然后把两条腿合起来。合起来之后，无论用户搜精确编号还是换说法，系统都能应付。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 复习昨天：BM25 的短板是什么
2. 快速搭一条语义检索路（向量检索），理解它的短板
3. 理解“两路分数不能直接相加”这个坑
4. 理解 RRF 融合：为什么用“排名的倒数”而不是原始分数
5. 用 Python 实现 RRF，把两路合并
6. 也看一眼“加权分数融合”（weighted sum），理解它的麻烦之处
7. 调 RRF 的参数 k 和两路权重，观察排序变化
8. 阅读 `ai-lab/rag/search.py` 风格的融合代码
9. 写今日笔记和自测

装今天要用的库：

```bash
pip install sentence-transformers rank_bm25 jieba numpy
```

- `sentence-transformers`：本地跑一个小的 Embedding 模型，做语义检索。
- 其余同 Day01。

> 说明：如果本地下载模型不方便，语义检索那一路也可以先用“假向量”（随机或手工构造）跑通融合逻辑——今天的重点是**融合**，不是 Embedding 本身。

---

## 1. 学习内容

### 1.1 复习：为什么单靠 BM25 不够

昨天的失败 case：文档写“口令重置”，用户搜“密码重置”，BM25 因为“密码”≠“口令”搜不到。

反过来，语义检索也有短板：用户搜精确编号 `SKU-8842`，语义模型可能把它和别的商品混在一起。

结论摆在这：

| 场景 | BM25 | 语义检索 |
|---|---|---|
| 精确编号 / 型号 | ✅ 强 | ❌ 弱 |
| 同义 / 换说法 | ❌ 弱 | ✅ 强 |
| 错别字 | ❌ 弱 | 🔶 一般 |
| 长尾专有名词 | ✅ 强 | ❌ 弱 |

既然强弱互补，那就两个都用——这就是 Hybrid Search 的动机。

---

### 1.2 快速搭一条语义检索路

语义检索三步：把每篇文档变成向量 → 把 query 也变成向量 → 算 query 向量和每篇文档向量的相似度，取最高的几篇。新建 `vector_search.py`：

```python
from sentence_transformers import SentenceTransformer
import numpy as np


class VectorSearcher:
    """语义检索路：基于向量相似度。"""

    def __init__(self, docs: list[str]):
        self.docs = docs
        # 一个轻量多语言模型，第一次会自动下载
        self.model = SentenceTransformer("paraphrase-multilingual-MiniLM-L12-v2")
        # 预先把所有文档编码成向量（离线建索引）
        self.doc_vecs = self.model.encode(docs, normalize_embeddings=True)

    def search(self, query: str, top_k: int = 5) -> list[dict]:
        q_vec = self.model.encode([query], normalize_embeddings=True)[0]
        # 归一化后，点积就是余弦相似度
        sims = self.doc_vecs @ q_vec
        results = [
            {"id": i, "text": self.docs[i], "score": float(s)}
            for i, s in enumerate(sims)
        ]
        results.sort(key=lambda x: x["score"], reverse=True)
        return results[:top_k]


if __name__ == "__main__":
    docs = [
        "如何重置账户口令",   # 注意：这里用“口令”而不是“密码”
        "订单支付失败怎么办",
        "如何修改绑定的手机号",
        "退款一般多久到账",
    ]
    searcher = VectorSearcher(docs)
    for r in searcher.search("密码忘了怎么办", top_k=3):
        print(r)
```

运行后你会发现：即使文档写的是“口令”，搜“密码”也能把它排上来。这正是 BM25 做不到的。

小白重点：注意 `VectorSearcher.search` 返回的结构 `{"id","text","score"}` 和昨天 `KeywordSearcher` **完全一样**——这是故意的，为融合做准备。

---

### 1.3 坑：两路分数不能直接相加

一个很自然的想法：把 BM25 分数和语义分数加起来排序。但这行不通，因为两者**量纲完全不同**：

- BM25 分数范围大概 0 ~ 十几，且随语料变化
- 余弦相似度范围固定在 -1 ~ 1

直接相加，等于让 BM25 “淹没”语义分数。

```python
# 反面教材：千万别这么干
final = bm25_score + cosine_score  # BM25 可能是 8.5，cosine 只有 0.6，比例失衡
```

要相加，得先把两路分数各自归一化到 [0,1]，再加权。但归一化又依赖当次结果的最大/最小值，不稳定。所以工业界更爱一个绕开分数的办法——RRF。

---

### 1.4 RRF：只看排名，不看分数

RRF（Reciprocal Rank Fusion，倒数排名融合）的想法极其简单：

> 不管每一路给多少分，只看每条文档在这一路里**排第几名**。排名越靠前，贡献的分数越高。

公式：

```text
RRF_score(文档 d) = Σ  1 / (k + rank_i(d))
                   每一路 i
```

其中：

- `rank_i(d)`：文档 d 在第 i 路里的排名（第 1 名 rank=1，第 2 名 rank=2……）
- `k`：一个平滑常数，通常取 60

举例：某文档在 BM25 里排第 1、在语义里排第 3，取 k=60：

```text
RRF = 1/(60+1) + 1/(60+3) = 0.01639 + 0.01587 = 0.03226
```

为什么这样做很聪明：

- 它**天然消除了量纲问题**——不管原始分数是 8.5 还是 0.6，只用名次。
- 一条文档只要在**任意一路排得靠前**，就能得到不错的融合分。
- 在两路都靠前的文档，会得到双份贡献，稳稳排到最前。

---

### 1.5 用 Python 实现 RRF 融合

新建 `hybrid_search.py`，把昨天的关键词路和今天的语义路合并：

```python
from keyword_search import KeywordSearcher
from vector_search import VectorSearcher


def rrf_fuse(rank_lists: list[list[dict]], k: int = 60, top_k: int = 5) -> list[dict]:
    """
    rank_lists: 多路检索结果，每一路是按分数降序排好的 [{"id","text","score"}, ...]
    返回融合后的排序。
    """
    scores: dict[int, float] = {}
    texts: dict[int, str] = {}
    for results in rank_lists:
        for rank, item in enumerate(results, start=1):  # rank 从 1 开始
            doc_id = item["id"]
            scores[doc_id] = scores.get(doc_id, 0.0) + 1.0 / (k + rank)
            texts[doc_id] = item["text"]
    fused = [
        {"id": doc_id, "text": texts[doc_id], "score": s}
        for doc_id, s in scores.items()
    ]
    fused.sort(key=lambda x: x["score"], reverse=True)
    return fused[:top_k]


class HybridSearcher:
    def __init__(self, docs: list[str]):
        self.keyword = KeywordSearcher(docs)
        self.vector = VectorSearcher(docs)

    def search(self, query: str, top_k: int = 5) -> list[dict]:
        # 每一路多取一些候选（比如 20），融合后再截断
        kw = self.keyword.search(query, top_k=20)
        vec = self.vector.search(query, top_k=20)
        return rrf_fuse([kw, vec], k=60, top_k=top_k)


if __name__ == "__main__":
    docs = [
        "如何重置账户口令",
        "订单支付失败怎么办",
        "如何修改绑定的手机号",
        "退款一般多久到账",
        "忘记密码如何找回",
        "SKU-8842 商品缺货登记",
    ]
    hs = HybridSearcher(docs)

    print("=== 搜“密码忘了怎么办”（考验同义能力）===")
    for r in hs.search("密码忘了怎么办", top_k=3):
        print(r)

    print("=== 搜“SKU-8842”（考验精确编号能力）===")
    for r in hs.search("SKU-8842", top_k=3):
        print(r)
```

运行：

```bash
python3 hybrid_search.py
```

你应该观察到：

- 搜“密码”时，写“口令”的那条也能排上来（语义路的功劳）。
- 搜“SKU-8842”时，那条精确命中的排第一（关键词路的功劳）。

两条腿都保住了。这就是 Hybrid Search 的价值。

---

### 1.6 对照：加权分数融合（weighted sum）

除了 RRF，还有一种“加权归一化分数”融合，看一眼理解它的麻烦：

```python
def minmax_norm(results: list[dict]) -> dict[int, float]:
    scores = [r["score"] for r in results]
    lo, hi = min(scores), max(scores)
    span = (hi - lo) or 1e-9
    return {r["id"]: (r["score"] - lo) / span for r in results}


def weighted_fuse(kw, vec, alpha=0.5, top_k=5):
    """alpha 是关键词路权重，(1-alpha) 是语义路权重。"""
    kw_norm = minmax_norm(kw)
    vec_norm = minmax_norm(vec)
    ids = set(kw_norm) | set(vec_norm)
    fused = []
    text_map = {r["id"]: r["text"] for r in kw + vec}
    for i in ids:
        s = alpha * kw_norm.get(i, 0) + (1 - alpha) * vec_norm.get(i, 0)
        fused.append({"id": i, "text": text_map[i], "score": s})
    fused.sort(key=lambda x: x["score"], reverse=True)
    return fused[:top_k]
```

对比：

| 方法 | 是否受量纲影响 | 是否要归一化 | 是否好调参 | 稳定性 |
|---|---|---|---|---|
| 直接相加分数 | ❌ 严重 | 不做就崩 | 差 | 差 |
| 加权归一化融合 | 🔶 归一化后缓解 | 必须 | 需要调 alpha | 中 |
| **RRF** | ✅ 完全不受 | 不需要 | 只有一个 k | 好 |

小白重点：

> 新手做 Hybrid 优先用 RRF——不用归一化、参数少、结果稳。等你需要精细控制两路权重时，再考虑加权融合。

---

### 1.7 调参：RRF 的 k 和两路权重

RRF 也能带权重，只要在某一路的贡献前乘个系数：

```python
def rrf_fuse_weighted(rank_lists, weights, k=60, top_k=5):
    scores, texts = {}, {}
    for results, w in zip(rank_lists, weights):
        for rank, item in enumerate(results, start=1):
            i = item["id"]
            scores[i] = scores.get(i, 0.0) + w * 1.0 / (k + rank)
            texts[i] = item["text"]
    fused = [{"id": i, "text": texts[i], "score": s} for i, s in scores.items()]
    fused.sort(key=lambda x: x["score"], reverse=True)
    return fused[:top_k]

# 例：更信任语义路
# rrf_fuse_weighted([kw, vec], weights=[0.4, 0.6])
```

- `k` 越大，排名靠前和靠后的差距越小（融合更“平”）；`k` 越小，越强调头部。默认 60 是经验值。
- 权重反映你更信任哪一路。做 FAQ 通常语义路稍重一点。

---

## 2. 源码阅读

- `ai-lab/rag/search.py`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. 有没有两个独立的检索函数（一个关键词、一个向量）
2. 融合用的是 RRF 还是加权分数
3. 每一路取了多少候选（`top_k` / `candidate_k`）
4. 融合的 `k` 常数和权重是多少，是写死的还是可配置的
5. 融合后是直接返回，还是又交给 Rerank（Day03 的内容）

建议在笔记里写出这张流程表：

| 步骤 | 输入 | 输出 | 对应代码 |
|---|---|---|---|
| 关键词检索 | query | top-N 候选 | `keyword_search()` |
| 语义检索 | query | top-N 候选 | `vector_search()` |
| 融合 | 两路候选 | 统一排序 | `rrf_fuse()` |
| 截断 | 统一排序 | top-k | `[:top_k]` |

---

## 3. 练习任务

### 练习 1：跑通语义检索路

按 1.2 节写出 `vector_search.py`，搜“密码”能命中写“口令”的文档。

目标：亲眼确认语义路解决了昨天 BM25 的失败 case。

---

### 练习 2：跑通 Hybrid Search

按 1.5 节写出 `hybrid_search.py`，分别搜“密码忘了怎么办”和“SKU-8842”。

目标：确认两种 query 都能被正确处理（同义 + 精确编号）。

---

### 练习 3：对比单路 vs 融合

把同一个 query 分别用 `KeywordSearcher`、`VectorSearcher`、`HybridSearcher` 搜一遍，把三份 top-3 并排列出。

目标：直观看到融合是如何“取两路之长”的。

---

### 练习 4：调 RRF 参数

试 `k=10` 和 `k=200`，再试 `weights=[0.3, 0.7]` 和 `[0.7, 0.3]`，观察排序变化。

目标：理解 k 和权重分别控制什么。

---

### 练习 5：构造“只有融合才对”的 case

设计一个 query，让单独的 BM25 或单独的语义都排不对，但融合后排对。

目标：为 Hybrid 的价值找到最有说服力的证据，记进笔记。

---

## 4. JS/Node.js 类比

| Python / RAG | Node.js 类比 | 说明 |
|---|---|---|
| Hybrid Search | Elasticsearch 的 hybrid query | ES 8+ 原生支持 BM25 + kNN 融合 |
| RRF | ES 的 `rank: { rrf: {} }` | ES 内置 RRF 融合 |
| 两路结果合并去重 | 两个数组按 id 合并 | `Map` 按 key 累加 |
| `sentence-transformers` | `@xenova/transformers` | Node 端也能跑 Embedding |
| 余弦相似度 | 向量点积函数 | 手写一个 dot product |

Node 里 RRF 的等价实现：

```js
function rrfFuse(rankLists, k = 60, topK = 5) {
  const scores = new Map();
  const texts = new Map();
  for (const results of rankLists) {
    results.forEach((item, idx) => {
      const rank = idx + 1;
      scores.set(item.id, (scores.get(item.id) || 0) + 1 / (k + rank));
      texts.set(item.id, item.text);
    });
  }
  return [...scores.entries()]
    .map(([id, score]) => ({ id, text: texts.get(id), score }))
    .sort((a, b) => b.score - a.score)
    .slice(0, topK);
}
```

小白重点：融合逻辑本身和语言无关，就是“按 id 累加倒数排名分”。你用 Node、PHP、Python 写出来结构都一样。

---

## 5. AI Review 提问

```text
我正在学习 Week 18 Day 02：Hybrid Search。

请你按资深工程师标准帮我检查：

1. 我的两路检索返回结构是否统一、是否方便融合？
2. 我理解“两路分数不能直接相加”这个坑对吗？RRF 是怎么绕开它的？
3. 我实现的 RRF 公式对不对？k 和权重的作用我说清了吗？
4. 我每一路取的候选数（比如 20）合理吗？取太少或太多会怎样？
5. 生产环境里，两路检索是串行还是并行跑？延迟怎么控制？

请用中文输出：
- 我理解正确的地方
- 我理解错误或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

- [ ] `vector_search.py`：语义检索路
- [ ] `hybrid_search.py`：RRF 融合的混合检索
- [ ] 单路 vs 融合的对比表
- [ ] 一个“只有融合才对”的 case（记进笔记）
- [ ] RRF 原理笔记（为什么用排名而非分数）
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说清 Hybrid Search 的动机（两路互补）
- [ ] 语义检索路可用
- [ ] 能解释“两路分数不能直接相加”的原因
- [ ] 能用一句话解释 RRF 为什么绕开了量纲问题
- [ ] 能手写出 RRF 融合函数
- [ ] 混合检索可用（同义词和精确编号两种 query 都能应付）
- [ ] 能说出 RRF 参数 k 和权重的作用
- [ ] 构造出一个“只有融合才对”的 case

---

## 8. 今日自测题

### 8.1 为什么需要 Hybrid Search，只用语义检索不行吗？

参考答案：

> ✅ 语义检索对精确编号、型号、罕见专有名词常常搜不准，而这些恰恰是 BM25 的强项。两者强弱互补，融合后覆盖面最广，所以工业界普遍两路一起用。

---

### 8.2 为什么不能把 BM25 分数和余弦相似度直接相加？

参考答案：

> ✅ 两者量纲完全不同（BM25 可能 0~十几，余弦固定 -1~1），直接相加会让 BM25 淹没语义分数。要相加必须先归一化，而归一化又不稳定，所以更推荐用不看分数的 RRF。

---

### 8.3 RRF 的核心思想是什么？

参考答案：

> ✅ 不看原始分数，只看每条文档在每一路里的排名，用 `1/(k+rank)` 累加。排名靠前贡献大，天然消除量纲问题；在多路都靠前的文档得到多份贡献，稳稳排最前。

---

### 8.4 RRF 里的 k 变大会怎样？

参考答案：

> ✅ k 越大，头部和尾部排名之间的分数差距越小，融合结果更“平缓”；k 越小越强调头部。常用经验值 60。

---

### 8.5 每一路只取 top-3 候选再融合，会有什么问题？

参考答案：

> ✅ 候选太少会漏掉“在这一路排第 5 但在另一路很靠前”的好文档。通常每路多取一些（如 20~50）做融合，最后再截断到 top-k，召回更全。

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
我正在进行 Week 18 Day 02：Hybrid Search 的学习。
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
