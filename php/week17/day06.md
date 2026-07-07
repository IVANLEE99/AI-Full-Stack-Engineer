# Week 17 Day 06：RAG 索引项目

> 所属周：Week 17：Embedding + Chunk  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/rag`  
> 类型：项目实战  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

完成 indexer 与测试报告。

今天你要真正掌握这一句话：

> 一个可交付的 indexer，是把本周所有零件（读文档 → 切 chunk → 算 Embedding → 存向量库 → 测召回）串成一条可以一键运行的流水线，并输出一份能证明"它确实好用"的测试报告。

前五天你把每个零件都造好了，今天把它们拼成一个完整的、能跑通的项目，并交付一份报告。这是本周的成果验收前的最后一次实战。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 想清楚 indexer 的输入、输出、流程
2. 设计项目目录结构
3. 组装 pipeline：load → chunk → embed → store
4. 加上命令行入口，做到"一键索引"
5. 用 10+ 片段真实跑一遍
6. 跑召回测试，生成测试报告
7. 写 README，交付项目
8. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 什么是 indexer

indexer（索引器）就是把"一堆原始文档"变成"可被语义检索的知识库"的程序。它是 RAG 系统的"入库"半边。

```text
输入：  docs/ 目录下的若干文档（.md / .txt）
        │
        ▼
  ┌─────────────────────────────────────────┐
  │  indexer pipeline                        │
  │  1. load    读取文档                      │
  │  2. chunk   切成片段（Day02 策略）         │
  │  3. embed   每片算 Embedding（Day01）      │
  │  4. store   存进向量库（Day03）            │
  └─────────────────────────────────────────┘
        │
        ▼
输出：  一个装好向量的 collection + 一份索引报告
```

小白重点：indexer 只负责"入库"。查询/召回是另一半（retriever）。今天重点交付 indexer，顺带用 retriever 跑测试报告。

---

### 1.2 设计项目目录

一个清晰的目录让项目像个"工程"而不是脚本堆：

```text
rag-indexer/
├── docs/               # 原始文档（你的知识库素材）
│   ├── php-basic.md
│   ├── composer.md
│   └── ...
├── src/
│   ├── loader.py       # 读文档
│   ├── chunker.py      # 切分（Day02）
│   ├── embedder.py     # Embedding（Day01）
│   ├── store.py        # 向量库封装（Day03）
│   └── indexer.py      # 组装 pipeline
├── tests/
│   └── test_cases.py   # 召回测试用例（Day04）
├── index.py            # 命令行入口（一键索引）
├── report.md           # 测试报告（今日产出）
└── README.md           # 项目说明
```

小白重点：这就像后端项目分 controller / service / model 一样，每个文件职责单一，方便维护和复用。

---

### 1.3 组装 pipeline

`src/indexer.py` 把各模块串起来：

```python
from src.loader import load_docs
from src.chunker import split_by_heading
from src.embedder import embed
from src.store import VectorStore


def build_index(docs_dir="docs", collection="kb"):
    # 1. load
    docs = load_docs(docs_dir)          # [{"source": "php-basic.md", "text": "..."}, ...]
    print(f"[load] 读取 {len(docs)} 篇文档")

    # 2. chunk
    chunks = []
    for doc in docs:
        for i, piece in enumerate(split_by_heading(doc["text"])):
            chunks.append({
                "id": f"{doc['source']}#{i}",
                "text": piece,
                "source": doc["source"],
            })
    print(f"[chunk] 切出 {len(chunks)} 个片段")

    # 3. embed
    vectors = embed([c["text"] for c in chunks])
    print(f"[embed] 生成 {len(vectors)} 个向量，维度 {len(vectors[0])}")

    # 4. store
    store = VectorStore(name=collection)
    store.reset()
    store.add(
        ids=[c["id"] for c in chunks],
        embeddings=vectors,
        documents=[c["text"] for c in chunks],
        metadatas=[{"source": c["source"]} for c in chunks],
    )
    print(f"[store] 已写入 collection='{collection}'，共 {store.count()} 条")
    return store
```

小白重点：每一步都 `print` 一行进度，跑起来你能清楚看到"读了几篇、切了几块、存了几条"，出问题也好定位。

---

### 1.4 命令行入口：一键索引

`index.py` 让别人（和未来的你）不用读代码就能用：

```python
import argparse
from src.indexer import build_index

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="RAG 知识库索引器")
    parser.add_argument("--docs", default="docs", help="文档目录")
    parser.add_argument("--collection", default="kb", help="向量库集合名")
    args = parser.parse_args()

    build_index(docs_dir=args.docs, collection=args.collection)
    print("✅ 索引完成")
```

运行：

```bash
python index.py --docs docs --collection kb
```

小白重点：`argparse` ≈ Node 里的 `process.argv` / `commander`。有了命令行入口，项目才算"可交付"，而不是要改代码才能跑。

---

### 1.5 loader 的小细节

读文档看似简单，但要注意编码和空文件：

```python
import os

def load_docs(docs_dir):
    docs = []
    for name in sorted(os.listdir(docs_dir)):
        if not name.endswith((".md", ".txt")):
            continue
        path = os.path.join(docs_dir, name)
        with open(path, "r", encoding="utf-8") as f:  # 明确 utf-8
            text = f.read().strip()
        if not text:                                   # 跳过空文件
            continue
        docs.append({"source": name, "text": text})
    return docs
```

小白重点：真实文档目录会混入空文件、非文本文件、不同编码。生产 indexer 一定要做这些"防脏数据"的过滤，否则一个坏文件就能让整条流水线崩。

---

### 1.6 生成测试报告

索引建好后，用 Day04 的测试用例跑召回，把结果写成 `report.md`：

```python
def write_report(store, test_cases, path="report.md"):
    lines = ["# RAG 索引测试报告\n", f"- collection 片段数：{store.count()}\n"]
    hit = 0
    for tc in test_cases:
        results = store.query(embed([tc["question"]])[0], top_k=3)
        got_sources = [m["source"] for m in results["metadatas"]]
        ok = tc["expect_source"] in got_sources
        hit += ok
        lines.append(f"\n## 问题：{tc['question']}\n")
        lines.append(f"- 期望来源：{tc['expect_source']}\n")
        lines.append(f"- 实际 Top-3 来源：{got_sources}\n")
        lines.append(f"- 结果：{'✅ Hit' if ok else '❌ Miss'}\n")
    lines.insert(2, f"- Hit@3：{hit}/{len(test_cases)} = {hit/len(test_cases):.0%}\n")

    with open(path, "w", encoding="utf-8") as f:
        f.writelines(lines)
    print(f"[report] 已写入 {path}")
```

小白重点：报告里要有**总分（Hit@K）+ 每题明细**。总分证明整体质量，明细方便定位是哪几题没召回、为什么。

---

### 1.7 一份合格测试报告长什么样

```markdown
# RAG 索引测试报告

- collection 片段数：14
- Hit@3：4/5 = 80%

## 问题：怎么开启 PHP 严格类型？
- 期望来源：php-basic.md
- 实际 Top-3 来源：['php-basic.md', 'composer.md', 'php-basic.md']
- 结果：✅ Hit

## 问题：Composer 和 npm 什么关系？
- 期望来源：composer.md
- 实际 Top-3 来源：['composer.md', 'php-basic.md', 'composer.md']
- 结果：✅ Hit

## 问题：...
- 结果：❌ Miss   ← 这条要在报告结尾分析原因
```

报告结尾建议加一段"结论 + 待改进"，比如：

```text
结论：14 个片段，5 题召回命中 4 题（80%）。
待改进：Q5 未命中，原因是相关内容跨了两个 chunk，边界信息丢失。
下一步：给该文档加 overlap=100 重新索引。
```

---

## 2. 源码阅读

本日无指定源码阅读，重点完成项目实战。

回看前五天的代码，把它们真正**拆成模块文件**（loader / chunker / embedder / store）。这个"重构成工程结构"的过程本身就是今天的重点练习。

---

## 3. 练习任务

### 练习 1：搭项目骨架

按 1.2 建好目录结构，把前几天的代码归位到对应模块文件。

目标：从"脚本"升级到"工程"。

---

### 练习 2：准备 10+ 片段的语料

在 `docs/` 放至少能切出 10+ chunk 的文档（可以用你前几周的学习笔记）。

目标：有真实、足量的索引素材。

---

### 练习 3：组装并运行 indexer

实现 `build_index()` 和 `index.py`，命令行一键跑通，观察每步 print 的数量。

目标：完整 pipeline 能一键运行。

---

### 练习 4：生成测试报告

用 5 个测试问题跑召回，生成 `report.md`，包含 Hit@3 总分和每题明细。

目标：交付一份能证明质量的报告。

---

### 练习 5：写项目 README

写清楚：项目做什么、怎么装依赖、怎么运行、当前 Hit@K 是多少、已知问题。

目标：项目达到"别人拿到能直接跑"的交付标准。

---

## 4. JS/Node.js 类比

| Python / RAG indexer | Node.js / JS 类比 | 说明 |
|---|---|---|
| indexer pipeline | ETL / 数据入库脚本 | 读→转→存三段式 |
| `src/` 分模块 | controller/service/model 分层 | 职责单一 |
| `argparse` | `commander` / `process.argv` | 命令行参数解析 |
| `build_index()` | `npm run build` 类的构建任务 | 一键产出成品 |
| `report.md` | 测试报告 / coverage 报告 | 证明质量的产物 |
| README | README | 交付说明 |

一句话类比：

> indexer ≈ 一个 ETL 脚本：Extract（读文档）→ Transform（切 chunk + 算向量）→ Load（写向量库），最后附一份"跑分报告"证明入库质量。

---

## 5. AI Review 提问

完成项目后，把目录结构、`indexer.py` 和 `report.md` 贴给 AI，然后问：

```text
我完成了一个 RAG indexer 项目：读文档 → 切 chunk → Embedding → 存向量库，
并生成了召回测试报告（Hit@3 = 80%）。这是我的目录结构、indexer 代码和报告。

请你按资深工程师标准帮我检查：

1. 我的 pipeline 分层和模块划分是否合理？
2. loader/chunker/embedder/store 之间的接口设计有没有问题？
3. 有没有漏掉的健壮性处理（空文件、编码、大文件、API 失败重试）？
4. 我的测试报告能否真正证明索引质量？还缺什么指标？
5. 如果要把这个 indexer 上生产，我还需要补哪些东西（增量索引、去重、日志）？

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [✅] 分模块的 `rag-indexer/` 项目结构
- [✅] 可一键运行的 `index.py`
- [✅] 10+ 片段成功索引进向量库
- [✅] `report.md` 测试报告（Hit@K + 每题明细 + 结论）
- [✅] 项目 README
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [✅] indexer 能一键运行，pipeline 每步有进度输出
- [✅] 至少 10 个片段成功索引
- [✅] loader 能处理空文件/非文本文件
- [✅] 能生成含 Hit@K 和每题明细的测试报告
- [✅] 报告里能指出未命中的原因和改进方向
- [✅] README 达到"别人拿到能跑"的标准

---

## 8. 今日自测题

### 8.1 indexer 负责 RAG 的哪一半？

参考答案：

> ✅ 负责"入库"半边：读文档 → 切 chunk → 算 Embedding → 存向量库。查询/召回（retriever）是另一半。

---

### 8.2 为什么要把代码拆成 loader/chunker/embedder/store 模块？

参考答案：

> ✅ 职责单一、方便复用和测试。就像后端分 controller/service/model，某一步要改（比如换 Embedding 模型），只动一个文件，不影响其他环节。

---

### 8.3 一份合格的测试报告至少要有什么？

参考答案：

> ✅ 总分（Hit@K）+ 每题明细（问题、期望来源、实际 Top-K、是否命中）+ 结论与待改进。总分证明整体质量，明细方便定位问题。

---

### 8.4 loader 为什么要过滤空文件和非文本文件？

参考答案：

> ✅ 真实文档目录会混入脏数据，一个坏文件（空的、二进制、错误编码）就可能让 embed 或 store 报错，整条流水线崩。防脏数据是生产 indexer 的必备处理。

---

### 8.5 命令行入口（argparse）对"交付"有什么意义？

参考答案：

> ✅ 让项目不改代码就能运行，别人拿到 `python index.py --docs xxx` 就能用。这是从"脚本"到"可交付工程"的关键一步。

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
我正在进行 Week 17 Day 06：RAG 索引项目 的学习。
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
