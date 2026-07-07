# Week 17 Day 03：向量存储

> 所属周：Week 17：Embedding + Chunk  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/rag`  
> 类型：编码练习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

接入 ChromaDB 或 PGVector（向量存储）。

今天你要真正掌握这一句话：

> 向量数据库就是"专门为向量搜索优化的数据库"。你把每个 Chunk 的向量、原文、metadata 一起存进去，检索时给它一个查询向量，它就用相似度算法帮你快速找出最接近的 Top-K 条。它对 RAG 的意义，就像 MySQL 对普通后端一样，是存取的基础设施。

前两天你会切 Chunk、会做 Embedding。今天把它们"存起来"，并且能"查出来"。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解普通数据库为什么不适合向量搜索
2. 理解向量库的三件套：向量、文档、metadata
3. 用 ChromaDB 跑通"写入 + 查询"最小闭环
4. 理解 collection、distance、Top-K 这几个概念
5. 了解 PGVector 方案（PostgreSQL 扩展）
6. 对比 ChromaDB 与 PGVector 的取舍
7. 封装一个自己的 `VectorStore` 类
8. 把昨天的 `chunks.json` 全量入库
9. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 为什么普通数据库不行

假设你把向量存进 MySQL 的一个字段里。用户来查询时，你想找"最相似的 10 条"，只能：

```text
把查询向量 和 表里每一条向量 逐个算相似度 → 排序 → 取前 10
```

如果表里有 100 万条，每次查询都要算 100 万次相似度。这叫**暴力扫描**，慢到无法用于生产。

向量数据库的价值在于：它用了专门的**近似最近邻（ANN）索引**（如 HNSW），能在不扫描全表的情况下，快速找出"大概率最相似"的那几条。牺牲一点点精度，换来几百倍的速度。

小白重点：

> 向量库 ≠ 会存向量的普通数据库。它的核心是"快速相似度检索的索引结构"。这就是它存在的理由。

---

### 1.2 向量库存什么：三件套

每一条记录（对应昨天的一个 Chunk）通常存三样东西：

| 字段 | 内容 | 作用 |
|---|---|---|
| embedding（向量） | 1536 维浮点数组 | 用来算相似度、做检索 |
| document（原文） | Chunk 的文本 | 召回后要拿它喂给大模型 |
| metadata | doc_id、source、title... | 过滤、展示来源、引用标注 |

还有一个 `id`，作为这条记录的唯一标识（对应昨天的 `doc-001-0`）。

```text
{
  id:        "doc-001-3",
  embedding: [0.021, -0.13, ...],   # 1536 维
  document:  "下单流程：用户下单后...",
  metadata:  { "doc_id": "doc-001", "title": "下单流程", "chunk_index": 3 }
}
```

---

### 1.3 用 ChromaDB 跑通最小闭环

ChromaDB 是最适合入门的向量库：纯 Python 装一下就能用，不需要单独部署数据库。

安装：

```bash
pip install chromadb
```

写入 + 查询的完整最小示例：

```python
import chromadb

# 1. 创建一个本地持久化的客户端（数据存到 ./chroma_data 目录）
client = chromadb.PersistentClient(path="./chroma_data")

# 2. 创建/获取一个 collection（相当于一张"表"）
collection = client.get_or_create_collection(name="kb_demo")

# 3. 写入数据（这里先用假向量演示，真实场景用 Day01 的 Embedding）
collection.add(
    ids=["c1", "c2", "c3"],
    embeddings=[
        [0.1, 0.2, 0.3],
        [0.9, 0.8, 0.7],
        [0.11, 0.19, 0.31],
    ],
    documents=[
        "订单模块负责创建和管理订单",
        "退款流程会校验订单状态",
        "下单后系统先锁定库存",
    ],
    metadatas=[
        {"title": "订单模块"},
        {"title": "退款流程"},
        {"title": "下单流程"},
    ],
)

# 4. 查询：给一个查询向量，找最相似的 2 条
result = collection.query(
    query_embeddings=[[0.1, 0.2, 0.31]],
    n_results=2,
)

print(result["documents"])
print(result["distances"])
```

`collection.add` 就是写入，`collection.query` 就是检索。这就是向量库的核心两个动作。

小白重点：

> `collection` 相当于 MySQL 的一张表。一个知识库项目可以有多个 collection（比如按业务线分）。

---

### 1.4 理解 distance 与 Top-K

`query` 返回的 `distances`（距离）是关键。距离越小，表示越相似。

ChromaDB 默认用 L2（欧氏距离），也可以配成 cosine（余弦距离，和 Day01 学的余弦相似度对应）：

```python
collection = client.get_or_create_collection(
    name="kb_demo",
    metadata={"hnsw:space": "cosine"},  # 指定用余弦距离
)
```

`n_results=2` 就是 **Top-K** 里的 K——返回最相似的前 K 条。这是 RAG 检索的核心动作：给一个问题向量，取最相关的 K 个 Chunk。

| 概念 | 含义 |
|---|---|
| distance | 查询向量与库中向量的距离，越小越相似 |
| Top-K | 返回距离最小的前 K 条 |
| cosine space | 用余弦距离（方向相似度），文本检索常用 |

小白重点：cosine 距离 ≈ `1 - 余弦相似度`。余弦相似度越接近 1（越相似），cosine 距离就越接近 0。方向一致，含义一致。

---

### 1.5 结合真实 Embedding 写入

把 Day01 的 Embedding 和昨天的 `chunks.json` 接起来。这里用 OpenAI 兼容接口做示例（密钥用环境变量，不要写死）：

```python
import os
import json
import chromadb
from openai import OpenAI

ai = OpenAI(api_key=os.environ["OPENAI_API_KEY"])

def embed(texts: list[str]) -> list[list[float]]:
    """批量把文本转成向量"""
    resp = ai.embeddings.create(
        model="text-embedding-3-small",
        input=texts,
    )
    return [d.embedding for d in resp.data]

# 读昨天切好的 chunks
with open("chunks.json", encoding="utf-8") as f:
    chunks = json.load(f)

texts = [c["text"] for c in chunks]
vectors = embed(texts)

client = chromadb.PersistentClient(path="./chroma_data")
collection = client.get_or_create_collection(
    name="knowledge_base",
    metadata={"hnsw:space": "cosine"},
)

collection.add(
    ids=[c["id"] for c in chunks],
    embeddings=vectors,
    documents=texts,
    metadatas=[c["metadata"] for c in chunks],
)

print(f"已入库 {len(chunks)} 个 chunk")
```

小白重点：`embed()` 支持传一个列表批量向量化，比一条条调 API 快很多，也省钱。生产里一定要批量。

---

### 1.6 了解 PGVector 方案

如果你的项目已经在用 PostgreSQL，可以不额外引入 ChromaDB，直接给 Postgres 装 `pgvector` 扩展，让它支持向量类型和向量索引。

建表（SQL）：

```sql
-- 启用扩展
CREATE EXTENSION IF NOT EXISTS vector;

-- 建表，vector(1536) 表示 1536 维向量
CREATE TABLE kb_chunks (
    id          TEXT PRIMARY KEY,
    document    TEXT NOT NULL,
    metadata    JSONB,
    embedding   vector(1536)
);

-- 建向量索引（HNSW），指定用余弦距离
CREATE INDEX ON kb_chunks
USING hnsw (embedding vector_cosine_ops);
```

查询（`<=>` 是余弦距离运算符）：

```sql
SELECT id, document, embedding <=> '[0.1, 0.2, ...]' AS distance
FROM kb_chunks
ORDER BY distance
LIMIT 5;
```

小白重点：PGVector 的最大好处是**和你的业务数据同库**——可以在一条 SQL 里同时做"向量相似 + 普通字段过滤"，事务、备份、权限都复用现有 Postgres 那一套。

---

### 1.7 ChromaDB vs PGVector 怎么选

| 对比项 | ChromaDB | PGVector |
|---|---|---|
| 上手难度 | 极低，pip 装完即用 | 需要 Postgres + 装扩展 |
| 部署 | 内嵌/单机友好 | 依赖已有 Postgres |
| 和业务数据 | 独立存储 | 同库，能和业务表联查 |
| 事务/权限 | 弱 | 复用 Postgres 成熟能力 |
| 适合 | 学习、原型、小规模 | 已有 PG 的生产项目 |

给你的建议：

> 学习和本周项目用 ChromaDB（跑得快、心智负担小）。等你进真实企业项目、发现团队已经在用 Postgres，就优先考虑 PGVector。

---

### 1.8 封装一个 VectorStore 类

把写入和查询封装起来，后面几天直接复用。这体现了"接口稳定、实现可换"的工程思维（今天用 Chroma，将来换 PGVector 只改这一个类）：

```python
import chromadb

class VectorStore:
    def __init__(self, name: str = "knowledge_base", path: str = "./chroma_data"):
        client = chromadb.PersistentClient(path=path)
        self.col = client.get_or_create_collection(
            name=name,
            metadata={"hnsw:space": "cosine"},
        )

    def add(self, ids, embeddings, documents, metadatas):
        self.col.add(
            ids=ids,
            embeddings=embeddings,
            documents=documents,
            metadatas=metadatas,
        )

    def query(self, query_embedding, top_k: int = 5):
        res = self.col.query(
            query_embeddings=[query_embedding],
            n_results=top_k,
        )
        # 拍平成好用的结构
        return [
            {
                "id": res["ids"][0][i],
                "document": res["documents"][0][i],
                "metadata": res["metadatas"][0][i],
                "distance": res["distances"][0][i],
            }
            for i in range(len(res["ids"][0]))
        ]

    def count(self) -> int:
        return self.col.count()
```

用起来就很清爽：

```python
store = VectorStore()
print("当前库里有", store.count(), "条")
hits = store.query(query_vector, top_k=3)
for h in hits:
    print(h["distance"], h["metadata"]["title"], h["document"][:20])
```

小白重点：这个 `VectorStore` 就是你 RAG 系统的"存储层接口"。业务代码只依赖它的 `add / query`，不关心底层是 Chroma 还是 PG。这正是明后天 indexer 和召回测试要用的基础件。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

不过建议你顺手看两处官方文档，抄下关键 API：

1. ChromaDB `get_or_create_collection` / `add` / `query` 的参数
2. `hnsw:space` 支持哪几种距离（`l2` / `cosine` / `ip`）

整理成表格：

| API / 参数 | 作用 | 我的备注 |
|---|---|---|
| `PersistentClient(path=...)` | 本地持久化客户端 |  |
| `hnsw:space` | 距离度量方式 |  |
| `n_results` | Top-K 数量 |  |
| `where` | metadata 过滤条件 |  |

---

## 3. 练习任务

### 练习 1：跑通 ChromaDB 最小闭环

照 1.3 的代码，用假向量跑通写入 + 查询，确认能打印出 `documents` 和 `distances`。

目标：确认环境 OK，理解 add / query 两个动作。

---

### 练习 2：把 chunks.json 全量入库

用 1.5 的代码，把昨天的 `chunks.json` 全部 Embedding 并写入 `knowledge_base`。写完打印 `store.count()`。

目标：完成"切分 → 向量化 → 入库"的第一次真实闭环。

---

### 练习 3：实现并使用 VectorStore 类

把 1.8 的 `VectorStore` 落成一个文件 `vector_store.py`，然后用它做一次查询，打印 Top-3 的 title 和 distance。

目标：产出可复用的存储层，明后天直接 import。

---

### 练习 4：加 metadata 过滤

ChromaDB 的 `query` 支持 `where` 过滤。试着只在某个 `doc_id` 范围内检索：

```python
res = self.col.query(
    query_embeddings=[query_embedding],
    n_results=top_k,
    where={"doc_id": "doc-001"},   # 只在这篇文档内检索
)
```

目标：理解"向量相似 + 结构化过滤"结合的威力，这是企业知识库的常见需求（按部门、按权限过滤）。

---

## 4. JS/Node.js 类比

| Python / RAG | Node.js / JS 类比 | 说明 |
|---|---|---|
| `collection` | 一张数据表 / 一个索引 | 存储的逻辑容器 |
| `collection.add` | `INSERT` / `bulkInsert` | 批量写入 |
| `collection.query` | `similaritySearch()` | 相似度检索 |
| `n_results` | `topK` 参数 | 返回前 K 条 |
| `where` 过滤 | `WHERE` 子句 | metadata 结构化过滤 |
| ChromaDB JS 客户端 | `chromadb`（npm 也有） | Node 生态有对等库 |
| LangChain `VectorStore` | LangChain.js `VectorStore` | 抽象接口思想一致 |

一句话类比：

> 向量库的 `query` 就是 LangChain 里的 `similaritySearch`——把 SQL 的"精确匹配 + WHERE"换成了"相似度排序 + Top-K"。

---

## 5. AI Review 提问

完成练习后，把你的 `vector_store.py` 和入库脚本贴给 AI，然后问：

```text
我正在学习 RAG 的向量存储，用的是 ChromaDB。这是我的存储层封装和入库脚本。

请你按资深工程师标准帮我检查：

1. 我的 metadata 字段设计是否合理？做召回引用和权限过滤够用吗？
2. 我选 cosine 距离对文本检索合适吗？
3. 入库时批量 Embedding 的写法有没有性能/成本问题？
4. VectorStore 这个封装，将来换成 PGVector 好不好替换？
5. 如果要上生产（几十万条），我还要注意哪些点（索引、重复写入、更新删除）？

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [✅] 跑通的 ChromaDB 最小闭环脚本
- [✅] `chunks.json` 全量入库脚本
- [✅] 可复用的 `vector_store.py`
- [✅] 一次带 metadata 过滤的查询
- [✅] ChromaDB vs PGVector 选型笔记
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [✅] 能说清为什么要用向量库而不是 MySQL
- [✅] 能说出向量库存的"三件套 + id"
- [✅] 能用 ChromaDB 完成写入和查询
- [✅] 理解 distance、Top-K、cosine space
- [✅] 能把真实 Embedding 结果入库
- [✅] 知道 PGVector 是什么、适合什么场景
- [✅] 能封装并使用 `VectorStore` 类
- [✅] 能做 metadata 过滤检索

---

## 8. 今日自测题

### 8.1 为什么不用 MySQL 存向量做检索？

参考答案：

> ✅ MySQL 没有向量索引，只能暴力扫描全表逐条算相似度，数据量一大就慢到不可用。向量库用 ANN 索引（如 HNSW），能不扫全表就快速找出最相似的几条。

---

### 8.2 向量库里一条记录通常存哪几样东西？

参考答案：

> ✅ id（唯一标识）、embedding（向量，用于检索）、document（原文，召回后喂给大模型）、metadata（来源/标题等，用于过滤和引用）。

---

### 8.3 `n_results` / Top-K 是什么意思？

参考答案：

> ✅ 检索时返回距离最小（最相似）的前 K 条。RAG 就是靠它取回最相关的 K 个 Chunk 交给大模型。

---

### 8.4 cosine 距离和余弦相似度什么关系？

参考答案：

> ✅ cosine 距离约等于 `1 - 余弦相似度`。相似度越接近 1（越相似），cosine 距离越接近 0。文本检索常用它。

---

### 8.5 ChromaDB 和 PGVector 怎么选？

参考答案：

> ✅ 学习/原型/小规模用 ChromaDB，装完即用、心智负担小；已有 Postgres 的生产项目用 PGVector，能和业务数据同库联查、复用事务和权限。

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
我正在进行 Week 17 Day 03：向量存储 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 17 README](./README.md)
