# Week 18 Day 01：BM25 关键词检索

> 所属周：Week 18：Hybrid Search + Rerank  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/rag`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

实现关键词检索路。

今天你要真正掌握这一句话：

> BM25 是一套“打分公式”，它根据**关键词在文档里出现的次数（词频 TF）**和**这个词在整个语料里有多稀有（逆文档频率 IDF）**给每篇文档打分，分数越高越相关；它只认字面上的词，不理解语义，所以叫“关键词检索”。

上一周你把文档切块、做了向量检索（语义检索）。但语义检索有个毛病：用户搜一个**精确的编号、型号、专有名词**时，它常常搜不准。这一周我们要补上另一条腿——关键词检索，最后把两条腿合起来变成 Hybrid Search。今天先把第一条腿 BM25 跑通。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先搞懂“检索”到底在干什么：给一个问题，从一堆文档里挑出最相关的几篇
2. 理解关键词检索 vs 语义检索的区别，以及各自的短板
3. 理解“词频 TF”和“逆文档频率 IDF”这两个最基础的概念
4. 理解 BM25 公式为什么在 TF-IDF 基础上做了改良
5. 用 Python 手写一个最小 BM25，跑通打分
6. 用现成库 `rank_bm25` 验证自己写的对不对
7. 把它封装成一个“关键词检索路”的函数
8. 用 Node.js 的 `lunr` / 数组 filter 做类比
9. 写今日笔记和自测

> 环境说明：本周示例以 **Python** 为主（RAG 生态的事实标准），PHP 作为你的后端主线用来做“工程化”类比理解。你只需要能跑 `python3` 即可。

先确认环境可用：

```bash
python3 --version
```

你应该看到类似：

```text
Python 3.10.x
```

再装两个今天要用的库：

```bash
pip install rank_bm25 jieba
```

- `rank_bm25`：现成的 BM25 实现，用来对照验证。
- `jieba`：中文分词。英文用空格切词就行，中文必须先分词。

---

## 1. 学习内容

### 1.1 检索到底在干什么

先建立最朴素的画面。假设你有一个小知识库，里面有 5 条 FAQ：

```python
docs = [
    "如何重置账户密码",
    "订单支付失败怎么办",
    "如何修改绑定的手机号",
    "退款一般多久到账",
    "忘记密码如何找回",
]
```

用户问：

```text
密码忘了怎么办
```

“检索”这一步要做的事，就是：**从这 5 条里，按“和问题的相关程度”排个序，把最相关的排前面。** 这里第 1 条和第 5 条都跟“密码”有关，应该排前面。

注意：检索**不负责生成答案**，它只负责“挑文档”。生成答案是后面 LLM 干的事（Day04 会讲）。今天只做“挑文档”这一步。

小白重点：

> 一个 RAG 系统的核心就两步——先**检索**（找到相关文档），再**生成**（让 LLM 基于这些文档回答）。今天学的是第一步里的一种方法。

---

### 1.2 关键词检索 vs 语义检索

检索有两大流派，你必须先分清：

| 对比项 | 关键词检索（BM25） | 语义检索（向量） |
|---|---|---|
| 靠什么匹配 | 字面上的词是否相同 | 意思是否接近 |
| “密码”和“口令” | 认为不相关（字不同） | 认为相关（意思近） |
| “SKU-8842”这种编号 | 非常准（字完全一样） | 常常搜不准 |
| 需要什么 | 分词 + 倒排索引 | Embedding 模型 + 向量库 |
| 速度/成本 | 快、便宜、可离线 | 相对慢、要调模型 |
| 典型短板 | 换个说法就搜不到 | 精确词/编号/罕见词搜不准 |

一句话总结：

> 语义检索擅长“理解意思”，关键词检索擅长“精确命中”。两者互补，所以工业界普遍两个一起用（这就是本周的 Hybrid Search）。

举个直观例子。用户搜 `iPhone 15 Pro 保修政策`：

- 语义检索可能把“手机售后服务条款”排上来（意思对，但可能漏掉精确型号）。
- 关键词检索会牢牢锁定包含 `iPhone`、`15`、`Pro`、`保修` 这些词的文档。

---

### 1.3 词频 TF：一个词出现得越多越相关？

TF = Term Frequency，词频，就是**某个词在一篇文档里出现了几次**。

直觉：一篇文档里“密码”出现了 5 次，另一篇只出现 1 次，前者大概率更相关。

```python
doc = "重置密码 修改密码 密码找回"
# “密码”这个词的 TF = 3
```

但这个直觉有个坑：出现 10 次真的比出现 5 次相关一倍吗？不一定。出现次数多到一定程度，边际收益递减。BM25 后面会用一个“饱和函数”来处理这个坑（1.5 节讲）。

---

### 1.4 逆文档频率 IDF：越稀有的词越值钱

IDF = Inverse Document Frequency，逆文档频率。

直觉：像“的”“怎么办”这种词几乎每篇文档都有，它们对区分文档没什么帮助；而“SKU-8842”这种词只在极少数文档里出现，一旦命中，信息量很大。

- **常见词**（几乎每篇都有）→ IDF 低 → 不值钱
- **稀有词**（只在少数文档出现）→ IDF 高 → 很值钱

IDF 的经典公式（BM25 版本）：

```text
IDF(词) = ln( (N - n + 0.5) / (n + 0.5) + 1 )
```

其中：

- `N`：文档总数
- `n`：包含这个词的文档数

小白重点：你不用背公式，只要记住结论——**一个词在越少的文档里出现，它的 IDF 越高，命中它得分越高。**

---

### 1.5 BM25：在 TF-IDF 上做的三点改良

BM25 = Best Matching 25，可以粗暴理解成“加强版 TF-IDF”。它的完整打分公式（对一篇文档 D、一个查询 Q）：

```text
score(D, Q) = Σ  IDF(词) × ( TF × (k1 + 1) )
             词∈Q            ─────────────────────────────────
                             TF + k1 × (1 - b + b × |D| / avgdl)
```

看不懂没关系，只记三个改良点：

1. **TF 饱和**（参数 `k1`）：词频高到一定程度就“封顶”，避免刷词。`k1` 常取 1.2~2.0。
2. **文档长度归一化**（参数 `b`）：长文档天然包含更多词，容易蒙对，BM25 会按文档长度打折。`b` 常取 0.75。
3. **保留 IDF**：稀有词依然更值钱。

其中 `|D|` 是当前文档长度，`avgdl` 是所有文档的平均长度。

对比表：

| 方法 | 有 TF | 有 IDF | TF 饱和 | 长度归一化 |
|---|---|---|---|---|
| 纯 TF | ✅ | ❌ | ❌ | ❌ |
| TF-IDF | ✅ | ✅ | ❌ | ❌ |
| **BM25** | ✅ | ✅ | ✅ | ✅ |

---

### 1.6 分词：BM25 的前置步骤

BM25 是对“词”打分的，所以必须先把句子切成词。

英文很简单，按空格切：

```python
"how to reset password".split()
# ['how', 'to', 'reset', 'password']
```

中文没有空格，必须用分词器（jieba）：

```python
import jieba

list(jieba.cut("如何重置账户密码"))
# ['如何', '重置', '账户', '密码']
```

小白重点：

> 中文 BM25 的效果，一半取决于分词质量。分错词，检索就会跑偏。这是关键词检索特有的“坑”，语义检索没有这个问题。

---

### 1.7 手写一个最小 BM25

现在把上面的概念串起来，手写一遍（不依赖库，帮你彻底理解）。新建 `bm25_mini.py`：

```python
import math
from collections import Counter
import jieba


def tokenize(text: str) -> list[str]:
    """中文分词：把句子切成词列表。"""
    return list(jieba.cut(text))


class MiniBM25:
    def __init__(self, docs: list[str], k1: float = 1.5, b: float = 0.75):
        self.k1 = k1
        self.b = b
        # 每篇文档分词后的词列表
        self.doc_tokens = [tokenize(d) for d in docs]
        self.docs = docs
        self.N = len(docs)
        # 每篇文档长度
        self.doc_len = [len(t) for t in self.doc_tokens]
        # 平均文档长度
        self.avgdl = sum(self.doc_len) / self.N
        # 计算每个词的文档频率 df（出现在多少篇文档里）
        self.df = Counter()
        for tokens in self.doc_tokens:
            for word in set(tokens):  # 去重，只统计“出现过”
                self.df[word] += 1

    def idf(self, word: str) -> float:
        n = self.df.get(word, 0)
        return math.log((self.N - n + 0.5) / (n + 0.5) + 1)

    def score(self, query: str, index: int) -> float:
        """给第 index 篇文档，针对 query 打分。"""
        query_tokens = tokenize(query)
        tokens = self.doc_tokens[index]
        freq = Counter(tokens)  # 该文档每个词的 TF
        dl = self.doc_len[index]
        s = 0.0
        for word in query_tokens:
            if word not in freq:
                continue
            tf = freq[word]
            numerator = tf * (self.k1 + 1)
            denominator = tf + self.k1 * (1 - self.b + self.b * dl / self.avgdl)
            s += self.idf(word) * numerator / denominator
        return s

    def search(self, query: str, top_k: int = 3):
        scores = [(i, self.score(query, i)) for i in range(self.N)]
        scores.sort(key=lambda x: x[1], reverse=True)
        return [(self.docs[i], round(sc, 4)) for i, sc in scores[:top_k]]


if __name__ == "__main__":
    docs = [
        "如何重置账户密码",
        "订单支付失败怎么办",
        "如何修改绑定的手机号",
        "退款一般多久到账",
        "忘记密码如何找回",
    ]
    bm25 = MiniBM25(docs)
    for doc, score in bm25.search("密码忘了怎么办"):
        print(f"{score}\t{doc}")
```

运行：

```bash
python3 bm25_mini.py
```

你应该看到“密码”相关的两条排在最前面，类似：

```text
1.6xxx	忘记密码如何找回
1.2xxx	如何重置账户密码
0.xxxx	订单支付失败怎么办
```

小白重点：分数的绝对值不重要，**重要的是排序**。检索只关心谁排前面。

---

### 1.8 用现成库 `rank_bm25` 验证

手写是为了理解，实际项目用库。新建 `bm25_lib.py`：

```python
from rank_bm25 import BM25Okapi
import jieba


def tokenize(text: str) -> list[str]:
    return list(jieba.cut(text))


docs = [
    "如何重置账户密码",
    "订单支付失败怎么办",
    "如何修改绑定的手机号",
    "退款一般多久到账",
    "忘记密码如何找回",
]

# 库要求传入“已分词”的语料
tokenized_corpus = [tokenize(d) for d in docs]
bm25 = BM25Okapi(tokenized_corpus)

query = "密码忘了怎么办"
scores = bm25.get_scores(tokenize(query))

# 按分数排序输出
ranked = sorted(zip(docs, scores), key=lambda x: x[1], reverse=True)
for doc, score in ranked[:3]:
    print(f"{round(score, 4)}\t{doc}")
```

运行：

```bash
python3 bm25_lib.py
```

排序结果应该和你手写的一致（分数绝对值可能略有差异，因为库的默认 `k1`/`b` 和 IDF 变体略不同）。

小白重点：

> 手写版和库版**排序结果一致**就说明你理解对了。这就是“先手写理解、再用库落地”的学习方法。

---

### 1.9 封装成“关键词检索路”

本周最后要做 Hybrid Search，所以今天就把 BM25 封装成一个标准接口，返回统一的结构。新建 `keyword_search.py`：

```python
from rank_bm25 import BM25Okapi
import jieba


class KeywordSearcher:
    """关键词检索路：基于 BM25。"""

    def __init__(self, docs: list[str]):
        self.docs = docs
        self.tokenized = [self._tokenize(d) for d in docs]
        self.bm25 = BM25Okapi(self.tokenized)

    @staticmethod
    def _tokenize(text: str) -> list[str]:
        return list(jieba.cut(text))

    def search(self, query: str, top_k: int = 5) -> list[dict]:
        scores = self.bm25.get_scores(self._tokenize(query))
        results = [
            {"id": i, "text": self.docs[i], "score": float(score)}
            for i, score in enumerate(scores)
        ]
        results.sort(key=lambda x: x["score"], reverse=True)
        return results[:top_k]


if __name__ == "__main__":
    docs = [
        "如何重置账户密码",
        "订单支付失败怎么办",
        "如何修改绑定的手机号",
        "退款一般多久到账",
        "忘记密码如何找回",
    ]
    searcher = KeywordSearcher(docs)
    for r in searcher.search("密码忘了怎么办", top_k=3):
        print(r)
```

统一返回结构 `{"id", "text", "score"}` 很关键——Day02 做 Hybrid 时，语义检索路也会返回同样的结构，这样两路才能干净地合并。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与理解 BM25 原理。

如果有余力，可以阅读 `rank_bm25` 库的源码入口（通常在你的 site-packages 下）：

```text
rank_bm25/__init__.py
```

阅读时重点找这些内容：

1. `BM25Okapi` 的 `k1`、`b`、`epsilon` 默认值是多少
2. `_calc_idf` 是怎么算 IDF 的，和我们手写的公式是否一致
3. `get_scores` 里那段打分循环，和 1.5 节的公式怎么对应

建议在笔记里写出对照表：

| 概念 | 我手写的实现 | 库里对应的代码 |
|---|---|---|
| IDF | `idf()` 方法 | `_calc_idf` |
| TF 饱和 | `k1` 那段除法 | `get_scores` 循环体 |
| 长度归一化 | `b × dl / avgdl` | `get_scores` 里 `doc_len` 相关 |

---

## 3. 练习任务

### 练习 1：跑通手写 BM25

按 1.7 节写出 `bm25_mini.py` 并运行，确认“密码”相关文档排在最前。

目标：看懂 `score()` 里每一步在算什么。

---

### 练习 2：手写版对照库版

同时跑 `bm25_mini.py` 和 `bm25_lib.py`，对比两者的排序结果。

目标：确认排序一致，理解“绝对分数不重要，排序才重要”。

---

### 练习 3：观察 IDF 的作用

在 docs 里加一条包含罕见词的文档，例如：

```python
docs.append("SKU-8842 商品缺货登记")
```

然后搜 `SKU-8842`，观察它是否稳稳排第一。

目标：亲眼看到“稀有词命中 → 高分”，理解 IDF 的威力。

---

### 练习 4：调参数 k1 和 b

修改 `MiniBM25(docs, k1=..., b=...)`，试试 `k1=0.5` 和 `k1=3.0`、`b=0` 和 `b=1`，观察排序变化。

目标：直观感受两个参数的作用（`b=0` 时完全不管文档长度）。

---

### 练习 5：找一个 BM25 的“失败 case”

构造一个查询，让 BM25 搜不准。例如文档里写“口令重置”，用户搜“密码重置”，BM25 因为字不同可能搜不到。

目标：亲手制造关键词检索的短板，为 Day02 引入语义检索埋伏笔。把这个 case 记进笔记。

---

## 4. JS/Node.js 类比

| Python / RAG | Node.js 类比 | 说明 |
|---|---|---|
| BM25 | `lunr.js` 的评分 | Node 全文搜索库 lunr 内部也用类 TF-IDF/BM25 打分 |
| `jieba.cut` | `nodejieba` / `segment` | 中文分词 |
| 倒排索引 | Elasticsearch / MeiliSearch | 生产级全文检索引擎 |
| `Counter` 统计词频 | `Map` + reduce | 统计每个词出现次数 |
| `bm25.get_scores` | `array.map(scoreFn)` | 给每篇文档算一个分 |
| 排序取 top_k | `arr.sort().slice(0, k)` | 取最相关的前 k 条 |

一个最朴素的 Node 类比（不含 IDF，仅示意“词频匹配”）：

```js
function keywordScore(query, doc) {
  const words = query.split(" ");
  return words.reduce((sum, w) => sum + (doc.includes(w) ? 1 : 0), 0);
}

const docs = ["reset password", "payment failed", "forgot password"];
const ranked = docs
  .map((d) => ({ text: d, score: keywordScore("forgot password", d) }))
  .sort((a, b) => b.score - a.score);

console.log(ranked);
```

小白重点：这个 JS 版只是“数命中几个词”，没有 IDF 和长度归一化。BM25 就是把它做“对”的完整版本。

---

## 5. AI Review 提问

完成练习后，把你的 BM25 代码贴给 AI，然后问：

```text
我正在学习 Week 18 Day 01：BM25 关键词检索。

请你按资深工程师标准帮我检查：

1. 我手写的 BM25 打分公式对不对？IDF、TF 饱和、长度归一化是否都实现了？
2. 我的中文分词处理是否合理？有没有需要加停用词过滤的地方？
3. 我封装的 KeywordSearcher 返回结构，是否方便后面做 Hybrid Search？
4. 关键词检索的短板我理解得对吗？我构造的失败 case 合理吗？
5. 如果换成生产环境（几十万文档），我现在这个内存版 BM25 会有什么问题？该用什么替代？

请用中文输出：
- 我理解正确的地方
- 我理解错误或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [ ] `bm25_mini.py`：手写最小 BM25
- [ ] `bm25_lib.py`：用 `rank_bm25` 验证
- [ ] `keyword_search.py`：封装好的关键词检索路
- [ ] 一个 BM25 失败 case（记进笔记）
- [ ] BM25 三点改良（TF 饱和 / IDF / 长度归一化）笔记
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能用一句话说清 BM25 在干什么
- [ ] 能解释 TF 和 IDF 的直觉含义
- [ ] 能说出 BM25 相比 TF-IDF 的三点改良
- [ ] 能说清关键词检索 vs 语义检索的区别和各自短板
- [ ] 能跑通手写版 BM25 并让相关文档排前
- [ ] 手写版与库版排序一致
- [ ] 封装出统一返回结构的 `KeywordSearcher`
- [ ] 关键词路可用（能对一个 query 返回 top_k）
- [ ] 构造出一个 BM25 的失败 case

---

## 8. 今日自测题

### 8.1 BM25 和语义检索最本质的区别是什么？

参考答案：

> ✅ BM25 靠“字面上的词是否相同”匹配，不理解意思；语义检索靠向量距离，理解意思。所以搜精确编号 BM25 强，换个说法搜同义内容语义检索强。

---

### 8.2 IDF 为什么能提升检索质量？

参考答案：

> ✅ IDF 让稀有词更值钱、常见词更廉价。像“的、怎么办”这种到处都有的词几乎不提供区分度，而“SKU-8842”这种只在少数文档出现的词一旦命中信息量很大。IDF 就是用来体现这个差异的。

---

### 8.3 BM25 里的参数 b 控制什么？

参考答案：

> ✅ `b` 控制文档长度归一化的强度。`b=1` 时完全按长度打折（长文档扣分多），`b=0` 时完全不管文档长度。常用 0.75，是个折中值。

---

### 8.4 中文用 BM25 前必须做什么？为什么？

参考答案：

> ✅ 必须先分词（如用 jieba）。因为 BM25 是对“词”打分的，中文没有空格分隔，不分词就无法得到词。分词质量直接影响检索效果。

---

### 8.5 为什么说 BM25 分数的绝对值不重要？

参考答案：

> ✅ 检索的目的是“排序”，只要相关文档排在前面就行。分数会随 `k1`/`b`/语料规模变化，不同实现绝对值也不同，但相对排序是稳定的、有意义的。

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
我正在进行 Week 18 Day 01：BM25 关键词检索 的学习。
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
