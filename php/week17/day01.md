# Week 17 Day 01：Embedding 原理

> 所属周：Week 17：Embedding + Chunk  
> 阶段：第四阶段：AI Backend（RAG 起步）  
> 主仓库/项目：`ai-lab/rag`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解什么是 Embedding（文本向量化），能调用 Embedding API 把一句话变成一串数字（向量），并能用余弦相似度比较两段文本"意思有多接近"。

今天你要真正掌握这一句话：

> Embedding 就是把一段文本变成一个固定长度的浮点数数组（向量），语义越接近的文本，向量在空间里离得越近；我们靠"余弦相似度"来量化这种远近。这就像给每段文字发一张"语义身份证"，之后做检索时不再比字面，而是比这张身份证。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先搞清楚"为什么传统关键词搜索不够用"，从而理解 Embedding 要解决什么问题
2. 理解什么是"向量"：一串数字而已，别怕
3. 理解 Embedding：文本 → 向量的映射
4. 用 Python 调用 Embedding API，把一句话向量化
5. 理解向量的"维度"是什么意思
6. 理解余弦相似度：怎么用数字判断两段文本像不像
7. 亲手算一次余弦相似度，跑通 3 句话的相似度对比
8. 用 Node.js 类比，把这套东西接到你已有的知识上
9. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 为什么需要 Embedding：关键词搜索的天花板

先想一个真实场景。你有一个知识库，里面有一句话：

```text
如何重置账户密码
```

用户在搜索框输入：

```text
忘记登录口令怎么办
```

传统的关键词搜索（比如 SQL 的 `LIKE '%密码%'`）会怎么样？

```sql
SELECT * FROM docs WHERE content LIKE '%忘记登录口令%';
```

结果是：**搜不到**。因为"密码"和"口令"字面不同，"重置"和"忘记"字面也不同。可是人一看就知道这俩说的是一回事。

关键词搜索的问题在于：它只会比"字面长得像不像"，不会比"意思像不像"。

Embedding 要解决的，正是这个问题：

> 把"意思"变成可以计算的东西。

小白重点：

> Embedding = 让机器理解"语义"的第一步。它把文字翻译成数字，之后"意思接近"就变成了"数字接近"，可以用数学去算。

---

### 1.2 什么是"向量"：别被名字吓到

"向量"听起来很数学，其实对我们来说就是一句话：

> 向量 = 一串固定长度的数字（一个浮点数数组）。

比如下面就是一个 4 维向量：

```python
vector = [0.12, -0.98, 0.34, 0.05]
```

在 Embedding 的世界里，一段文本会被变成这样一串数字，只不过通常很长，比如 1536 个数字（1536 维）。

用 Node.js 类比，你完全可以把向量理解成：

```js
const vector = [0.12, -0.98, 0.34, 0.05]; // 就是一个 number[]
```

它就是一个普通数组，没有任何魔法。

小白重点：

> 看到"向量"两个字，你心里就翻译成"一个 number 数组"，立刻就不怕了。

---

### 1.3 什么是 Embedding：文本 → 向量

Embedding（中文常译"嵌入"或"向量化"）就是一个函数：

```text
输入：一段文本（字符串）
输出：一个固定长度的向量（浮点数数组）
```

可以画成这样：

```text
"如何重置账户密码"  ──Embedding模型──▶  [0.021, -0.113, 0.087, ... ]（1536 个数）
"忘记登录口令怎么办" ──Embedding模型──▶  [0.019, -0.109, 0.091, ... ]（1536 个数）
```

关键性质：**语义接近的文本，向量也接近。** 上面两句话意思相近，所以它们的向量会非常像。

这个"函数"是一个训练好的神经网络模型（比如 `text-embedding-3-small`），我们不需要自己训练，直接调 API 用就行。

小白重点：

> Embedding 模型对我们来说是个黑盒："给它一句话，还我一串数字"。我们今天只负责会用它，不负责造它。

---

### 1.4 环境准备：装 Python 和依赖

RAG（检索增强生成）这一阶段，业界主流生态在 Python 侧（向量库、Embedding SDK、切分工具都最全）。所以本周我们用 Python 做核心练习，再用 PHP/Node 视角去理解。别担心，Python 语法我们边用边讲。

先确认 Python 可用：

```bash
python3 --version
```

你应该看到类似：

```text
Python 3.10.x
```

创建一个本周的练习目录并装依赖：

```bash
mkdir -p ai-lab/rag && cd ai-lab/rag
python3 -m venv venv
source venv/bin/activate        # Windows 用 venv\Scripts\activate
pip install openai numpy
```

这里：

- `venv` 是 Python 的虚拟环境，类似 Node 的 `node_modules` 隔离，避免污染全局。
- `openai` 是调用 Embedding API 的官方 SDK。
- `numpy` 是做向量数学计算的库（算余弦相似度会用到）。

小白重点：

> `python3 -m venv venv` + `source venv/bin/activate` ≈ Node 里进入一个干净的项目目录。装的包只属于这个项目。

---

### 1.5 第一次调用 Embedding API

我们用一个"兼容 OpenAI 接口"的 Embedding 服务（很多国内外服务商都兼容这套接口，你只要替换 `base_url` 和 `api_key` 即可，本文用占位符脱敏）。

新建 `embed_hello.py`：

```python
import os
from openai import OpenAI

# 从环境变量读取密钥，绝不硬编码在代码里
client = OpenAI(
    api_key=os.environ["EMBED_API_KEY"],       # 你的密钥（脱敏，放环境变量）
    base_url=os.environ.get("EMBED_BASE_URL"), # 可选：兼容服务的地址
)

def embed(text: str) -> list[float]:
    resp = client.embeddings.create(
        model="text-embedding-3-small",
        input=text,
    )
    return resp.data[0].embedding

vec = embed("如何重置账户密码")
print("维度:", len(vec))
print("前 5 个数字:", vec[:5])
```

设置密钥后运行（密钥用你自己的，这里脱敏）：

```bash
export EMBED_API_KEY="sk-xxxxxxxx"    # 脱敏占位
python3 embed_hello.py
```

你会看到类似输出：

```text
维度: 1536
前 5 个数字: [0.0213, -0.0117, 0.0384, -0.0052, 0.0091]
```

恭喜，你已经把一句中文变成了 1536 个数字。

小白重点：

> `resp.data[0].embedding` 就是那个向量（number 数组）。整个调用的本质是：发一段文本，收一个数组。

安全提醒：

> API 密钥绝不能写死在代码里、更不能提交到 Git。永远用环境变量。这是企业项目的红线。

---

### 1.6 理解"维度"：1536 是什么意思

刚才输出了 `维度: 1536`。维度就是这个向量里有多少个数字。

不同模型维度不同：

| 模型（示例） | 维度 | 特点 |
|---|---|---|
| `text-embedding-3-small` | 1536 | 便宜、够用，入门首选 |
| `text-embedding-3-large` | 3072 | 更准，更贵 |
| 某些开源小模型 | 384 / 768 | 本地可跑，维度低 |

你要记住两条规则：

1. **同一个知识库，必须用同一个模型**。1536 维的向量和 3072 维的向量无法直接比较，就像你不能拿"厘米"和"英寸"直接相减。
2. 维度不是越高越好，高维更准但更慢更贵。入门用 `small` 完全够。

小白重点：

> 维度 = 向量数组的长度（`len(vec)`）。整个项目里必须锁定同一个 Embedding 模型，否则向量对不上。

---

### 1.7 余弦相似度：用数字判断"像不像"

现在每段文本都是一个向量了。怎么比较两个向量"接近"程度？最常用的是 **余弦相似度（cosine similarity）**。

它衡量的是两个向量"方向"是否一致，取值范围 `-1 到 1`：

| 余弦值 | 含义 |
|---|---|
| 接近 1 | 方向几乎相同 → 语义非常接近 |
| 接近 0 | 方向垂直 → 基本无关 |
| 接近 -1 | 方向相反 → 语义相反（文本场景较少见） |

公式（看不懂没关系，下面有代码）：

```text
cos(A, B) = (A · B) / (|A| * |B|)
```

其中：

- `A · B` 是点积：对应位置相乘再求和
- `|A|` 是向量长度：每个数平方求和再开根号

用 numpy 实现非常短：

```python
import numpy as np

def cosine_similarity(a: list[float], b: list[float]) -> float:
    a = np.array(a)
    b = np.array(b)
    return float(np.dot(a, b) / (np.linalg.norm(a) * np.linalg.norm(b)))
```

小白重点：

> 你不需要背公式。记住结论：**余弦值越接近 1，两段文本意思越接近。** 检索的本质就是"找余弦值最大的那几段"。

---

### 1.8 完整示例：比较 3 句话的相似度

把前面拼起来，做一个能跑的完整脚本 `similarity_demo.py`：

```python
import os
import numpy as np
from openai import OpenAI

client = OpenAI(
    api_key=os.environ["EMBED_API_KEY"],
    base_url=os.environ.get("EMBED_BASE_URL"),
)

def embed(text: str) -> list[float]:
    resp = client.embeddings.create(model="text-embedding-3-small", input=text)
    return resp.data[0].embedding

def cosine_similarity(a, b) -> float:
    a, b = np.array(a), np.array(b)
    return float(np.dot(a, b) / (np.linalg.norm(a) * np.linalg.norm(b)))

query = "忘记登录口令怎么办"
candidates = [
    "如何重置账户密码",   # 语义非常接近
    "查看本月账单明细",   # 有点关系（都是账户）
    "今天天气怎么样",     # 完全无关
]

q_vec = embed(query)
for c in candidates:
    c_vec = embed(c)
    score = cosine_similarity(q_vec, c_vec)
    print(f"{score:.4f}  <-  {c}")
```

运行：

```bash
python3 similarity_demo.py
```

输出会类似（数值会有出入，但排序稳定）：

```text
0.8931  <-  如何重置账户密码
0.4720  <-  查看本月账单明细
0.1203  <-  今天天气怎么样
```

看到没？虽然"口令/密码"、"忘记/重置"字面完全不同，但余弦相似度高达 0.89，机器"看懂"了它们是一个意思。这就是 Embedding 相比关键词搜索的核心优势。

小白重点：

> 这个 demo 就是整个 RAG 检索的最小内核：**把查询和候选都向量化，谁的余弦相似度高，谁就更相关。** 后面几天做的向量数据库、Top-K 召回，本质都是把这一步做得更快、更规模化。

---

### 1.9 Embedding 常见坑（提前打预防针）

| 坑 | 现象 | 解法 |
|---|---|---|
| 混用模型 | 相似度全乱、报维度不一致 | 全项目锁定同一个 Embedding 模型 |
| 密钥硬编码 | 泄露、被盗刷 | 一律用环境变量 |
| 文本太长 | API 报 token 超限 | 先切 Chunk（明天的课） |
| 没做重试 | 网络抖动导致偶发失败 | 加重试/退避（后续课补） |
| 空文本向量化 | 报错或拿到无意义向量 | 调用前判空、trim |

小白重点：

> 今天最容易踩的是"混用模型"和"密钥硬编码"。先把这两条刻进肌肉记忆。

---

## 2. 源码阅读

- OpenAI Embeddings 官方文档：`platform.openai.com/docs/guides/embeddings`
- 官方 Python SDK 仓库：`openai/openai-python` 中 `resources/embeddings.py`

> 说明：路径为公开代号 + 相对位置，按你本地/线上文档映射查找。

阅读时重点找这些内容：

1. `embeddings.create` 接收哪些参数（`model`、`input`）
2. `input` 是否支持传入**数组**一次性向量化多条文本（支持，能省很多请求）
3. 返回结构 `data[].embedding` 长什么样
4. 是否有 `dimensions` 参数可以裁剪维度
5. 计费是按什么算的（token 数）

建议在笔记里写出类似表格：

| API 要素 | 含义 | Node/JS 类比 |
|---|---|---|
| `model` | 用哪个向量化模型 | 选一个函数实现 |
| `input`（字符串） | 单条文本向量化 | `embed("...")` |
| `input`（数组） | 批量向量化 | `Promise.all([...])` 但一次请求搞定 |
| `data[i].embedding` | 第 i 条的向量 | 返回的 `number[]` |

---

## 3. 练习任务

### 练习 1：跑通第一次向量化

按 1.5 完成 `embed_hello.py`，能打印出维度和前 5 个数字。

目标：确认 API 通、能拿到向量。

---

### 练习 2：批量向量化

把 `input` 改成一个数组，一次请求向量化多条文本：

```python
resp = client.embeddings.create(
    model="text-embedding-3-small",
    input=["苹果", "香蕉", "iPhone", "编程语言 Python"],
)
for i, item in enumerate(resp.data):
    print(i, len(item.embedding))
```

目标：理解一次请求可以处理多条，省钱省时间。

---

### 练习 3：完成相似度 demo

按 1.8 完成 `similarity_demo.py`，观察 3 句话的相似度排序。

目标：亲眼看到"语义接近 → 余弦值高"。

---

### 练习 4：找一组反例

自己构造 3 句话：两句意思接近但用词完全不同，一句完全无关。跑一遍相似度，验证 Embedding 是否"看懂"了语义。

目标：加深"比语义不比字面"的直觉。

---

### 练习 5：手算一次点积（不用 numpy）

用纯 Python 手写余弦相似度，理解公式：

```python
import math

def cosine(a, b):
    dot = sum(x * y for x, y in zip(a, b))
    na = math.sqrt(sum(x * x for x in a))
    nb = math.sqrt(sum(y * y for y in b))
    return dot / (na * nb)

print(cosine([1, 0, 1], [1, 0, 0]))  # 约 0.707
```

目标：理解余弦相似度就是"点积除以两个长度"，没有魔法。

---

## 4. JS/Node.js 类比

| Python / Embedding 世界 | Node.js / JS 类比 | 说明 |
|---|---|---|
| Embedding 向量 | `number[]` | 就是一个浮点数数组 |
| `client.embeddings.create` | `await openai.embeddings.create(...)` | Node 官方 SDK 用法几乎一样 |
| `resp.data[0].embedding` | `resp.data[0].embedding` | 返回结构一致 |
| `numpy` 做向量运算 | 自己写 `reduce` 或用 `ml-matrix` | JS 没内置向量库，得手写或装包 |
| 余弦相似度 | `dot(a,b)/(norm(a)*norm(b))` | 数学完全相同 |
| `venv` 虚拟环境 | 项目目录 + `node_modules` | 依赖隔离思路一致 |
| 环境变量存密钥 | `process.env.EMBED_API_KEY` | 安全做法完全一致 |

Node 版调用（对照理解）：

```js
import OpenAI from "openai";

const client = new OpenAI({
  apiKey: process.env.EMBED_API_KEY,
  baseURL: process.env.EMBED_BASE_URL,
});

const resp = await client.embeddings.create({
  model: "text-embedding-3-small",
  input: "如何重置账户密码",
});

console.log(resp.data[0].embedding.length); // 1536
```

看，和 Python 几乎一比一。这一周你在 Python 学到的 Embedding 概念，可以无缝搬到 Node。

---

## 5. AI Review 提问

完成练习后，把你的代码和理解贴给 AI，然后问：

```text
我正在学习 RAG Day 01：Embedding 原理。

请你按资深 AI 后端工程师标准帮我检查：

1. 我对 Embedding（文本→向量）的理解是否正确？
2. 我对"维度"和"必须锁定同一模型"的理解对吗？
3. 我的余弦相似度实现是否正确？
4. 我用 Node/JS 做的类比有没有误导？
5. 如果这是企业 RAG 项目，向量化环节我还要注意什么（限流、重试、成本、脱敏）？

请用中文输出：
- 我理解正确的地方
- 我理解错误或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [✅] `embed_hello.py`：第一次向量化，打印维度
- [✅] 批量向量化示例
- [✅] `similarity_demo.py`：3 句话相似度对比
- [✅] 手写余弦相似度（不用 numpy）
- [✅] Embedding vs 关键词搜索 的对比笔记
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能用一句话解释 Embedding 是什么
- [ ] 能说出"向量就是 number 数组"
- [ ] 能成功调用 Embedding API 并拿到向量
- [ ] 能说出向量"维度"的含义
- [ ] 能解释为什么必须锁定同一个模型
- [ ] 能解释余弦相似度，并知道"越接近 1 越相似"
- [ ] 能跑通 3 句话的相似度对比并解释排序
- [ ] 能说出密钥必须放环境变量的原因

---

## 8. 今日自测题

### 8.1 Embedding 到底是什么？

参考答案：

> ✅ Embedding 是把一段文本变成一个固定长度浮点数数组（向量）的过程。语义越接近的文本，向量在空间里越接近。它让"意思"变成了可以用数学计算的东西。

---

### 8.2 为什么 Embedding 比关键词搜索强？

参考答案：

> ✅ 关键词搜索只比字面（"密码"和"口令"字面不同就搜不到），Embedding 比语义。语义接近的文本即使用词完全不同，向量也接近，因此能被检索到。

---

### 8.3 "维度"是什么意思？

参考答案：

> ✅ 维度就是向量数组里数字的个数，例如 `text-embedding-3-small` 是 1536 维，即 `len(vec) == 1536`。同一项目必须锁定同一模型，否则维度不一致无法比较。

---

### 8.4 余弦相似度的取值范围和含义？

参考答案：

> ✅ 取值 -1 到 1。越接近 1，两向量方向越一致，语义越接近；接近 0 表示基本无关。检索时就是找余弦相似度最大的若干条。

---

### 8.5 API 密钥应该怎么放？

参考答案：

> ✅ 放环境变量，绝不硬编码进代码，也绝不提交到 Git。这是防止密钥泄露被盗刷的基本红线。

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
我正在进行 Week 17 Day 01：Embedding 原理 的学习。
请你扮演资深 AI 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险（成本、限流、脱敏）
4. 真实企业 RAG 项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 17 README](./README.md)
