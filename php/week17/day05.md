# Week 17 Day 05：优化与类比日

> 所属周：Week 17：Embedding + Chunk  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/rag`  
> 类型：类比日  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

调 Chunk 策略，完成打卡。

今天你要真正掌握这一句话：

> 优化 RAG 召回，本质是"控制变量做实验"：一次只改一个东西（Chunk 大小 / overlap / 切分策略 / Top-K），用同一批测试问题跑 Hit@K，看指标是升是降。凭感觉调参是玄学，用指标对比才是工程。

昨天你测出了召回质量和失败 case。今天不是学新概念，而是**动手把昨天的分数调上去**，并把整周的类比串成一条线。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 复盘昨天的召回失败 case
2. 建立"控制变量"的优化方法论
3. 实验一：调 Chunk 大小
4. 实验二：调 overlap
5. 实验三：换切分策略（固定 vs 按标题/段落）
6. 实验四：调 Top-K
7. 用一张实验对比表记录所有结果
8. 完成本周类比打卡
9. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 优化的第一原则：控制变量

RAG 有很多可调的旋钮：Chunk 大小、overlap、切分策略、Top-K、Embedding 模型... 如果一次改好几个，指标变了你也不知道是哪个起的作用。

```text
❌ 错误做法：一次把 chunk 从 500 改到 800，overlap 从 0 改到 100，K 从 3 改到 5
   → 指标涨了，但你不知道是谁的功劳，也没法复现

✅ 正确做法：固定其他一切，只改 chunk 大小，跑一遍指标；再只改 overlap，再跑一遍
   → 每次实验都能归因
```

小白重点：优化 = 科学实验。**每次只动一个变量**，记录 before/after 指标。这是今天最重要的方法论。

---

### 1.2 搭一个可复用的实验函数

为了反复做实验，把"切分 → 入库 → 测召回"打包成一个函数，参数就是你要调的旋钮：

```python
def run_experiment(name, raw_docs, test_cases,
                   chunk_size=500, overlap=0, strategy="fixed", top_k=3):
    # 1. 按参数切分
    chunks = []
    for doc in raw_docs:
        if strategy == "fixed":
            chunks += split_fixed(doc["text"], chunk_size, overlap)
        else:
            chunks += split_by_heading(doc["text"])

    # 2. 入一个独立 collection（用实验名区分，避免互相污染）
    store = VectorStore(name=f"exp_{name}")
    store.reset()
    store.add(
        ids=[f"c{i}" for i in range(len(chunks))],
        embeddings=embed(chunks),
        documents=chunks,
        metadatas=[{"exp": name} for _ in chunks],
    )

    # 3. 测召回
    print(f"\n=== 实验 {name}: size={chunk_size} overlap={overlap} "
          f"strategy={strategy} K={top_k} chunks={len(chunks)} ===")
    evaluate(store, test_cases, top_k=top_k)
    return store
```

小白重点：每个实验用**独立的 collection**（`exp_A`、`exp_B`），互不干扰，才能公平对比。

---

### 1.3 实验一：调 Chunk 大小

保持其他参数不变，只改 `chunk_size`：

```python
run_experiment("A_size300", raw_docs, test_cases, chunk_size=300)
run_experiment("A_size500", raw_docs, test_cases, chunk_size=500)
run_experiment("A_size800", raw_docs, test_cases, chunk_size=800)
```

一般规律（不是绝对，以你的数据为准）：

| Chunk 大小 | 倾向 | 风险 |
|---|---|---|
| 偏小（~300） | 主题聚焦、召回精准 | 上下文可能被切碎、不完整 |
| 中等（~500） | 平衡点，常见推荐 | —— |
| 偏大（~800+） | 上下文完整 | 主题混杂，召回被稀释 |

小白重点：如果昨天失败原因是"召回了主题混杂的 chunk"，试着把 chunk 调小；如果是"召回的片段太零碎、意思不完整"，试着调大。

---

### 1.4 实验二：调 overlap

overlap（重叠）是相邻 chunk 共享的一段文字，防止关键句正好被切在边界上。

```python
run_experiment("B_ov0",   raw_docs, test_cases, chunk_size=500, overlap=0)
run_experiment("B_ov100", raw_docs, test_cases, chunk_size=500, overlap=100)
```

```text
无 overlap：
[.....句子A][句子B.....]   ← "句子A句子B"这个跨边界的语义丢了

有 overlap：
[.....句子A句子B]
        [句子A句子B.....]  ← 边界信息在两个 chunk 里都保留
```

小白重点：overlap 能救回"被切在边界的答案"，但会增加 chunk 数量和存储/费用。常用 10%~20% 的 chunk 大小。

---

### 1.5 实验三：换切分策略

对比"固定长度切分" vs "按标题/段落切分"（Day02 的成果）：

```python
run_experiment("C_fixed",   raw_docs, test_cases, strategy="fixed")
run_experiment("C_heading", raw_docs, test_cases, strategy="heading")
```

结构化文档（有清晰标题的手册、文档）通常"按标题切"更好，因为每个 chunk 主题干净。纯长文本（没有标题）则只能固定长度切。

| 策略 | 适合的文档 | 优点 |
|---|---|---|
| 固定长度 | 无结构长文本 | 简单、通用 |
| 按标题/段落 | 结构化文档 | 主题完整、召回准 |

---

### 1.6 实验四：调 Top-K

最后调 K，看能不能用最小的 K 拿到满意的 Hit：

```python
for k in [1, 3, 5]:
    print(f"\n--- top_k = {k} ---")
    evaluate(best_store, test_cases, top_k=k)
```

如果 K=1 就已经 Hit 很高，说明你的排序很准，是好事；如果非要 K=5 才达标，说明正确答案常排在后面，Chunk/模型还有优化空间。

---

### 1.7 实验对比表（今天的核心产出）

把所有实验结果填进一张表，一眼看出哪个组合最好：

| 实验 | chunk_size | overlap | strategy | K | chunks 数 | Hit@K | MRR |
|---|---|---|---|---|---|---|---|
| baseline | 500 | 0 | fixed | 3 | 12 | 0.60 | 0.50 |
| A_size300 | 300 | 0 | fixed | 3 | ? | ? | ? |
| A_size800 | 800 | 0 | fixed | 3 | ? | ? | ? |
| B_ov100 | 500 | 100 | fixed | 3 | ? | ? | ? |
| C_heading | - | - | heading | 3 | ? | ? | ? |
| **best** | ? | ? | ? | ? | ? | **↑** | **↑** |

小白重点：这张表就是你今天的"实验记录本"。最后一行圈出最优组合，并写一句"为什么它最好"。

---

### 1.8 本周类比串讲（类比日重点）

今天是类比日，把整周概念用一条 JS/后端类比串起来，帮你彻底记牢：

| 本周概念 | 一句话类比 |
|---|---|
| Embedding | 把文本变成"语义指纹"（一串数字），像给字符串算了个带含义的 hash |
| 向量维度 | 指纹的长度，固定不变，像定长数组 |
| 余弦相似度 | 比两个指纹"方向"像不像，像字符串相似度打分 |
| Chunk 切分 | `text.split()`，但按语义切，不是瞎切 |
| overlap | 切片时留搭接，像滑动窗口有重叠 |
| 向量库 | 专门存指纹、并支持"最相似查询"的数据库 |
| 召回 Top-K | `results.slice(0, k)`，按相似度排序取前 K |
| Hit@K / MRR | 给搜索功能写的单元测试断言与通过率 |
| RAG | 语义搜索（召回）+ 大模型生成，先查资料再回答 |

一句话总览：

> RAG 索引 = 把文档切成 chunk → 每块算语义指纹（Embedding）→ 存进向量库；查询 = 把问题也算指纹 → 找最像的 Top-K 块。今天做的优化，就是调"怎么切"和"取几块"，让找得更准。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

可回看 Day02 的 `chunking.py`，思考：如果要支持"按 chunk_size 参数动态切分"，代码要怎么改？把改造思路记进笔记。

---

## 3. 练习任务

### 练习 1：搭实验函数

实现 1.2 的 `run_experiment()`，确保能通过参数控制 chunk_size / overlap / strategy / top_k。

目标：拥有一个可复用的实验工具。

---

### 练习 2：跑 Chunk 大小实验

按 1.3 跑 300 / 500 / 800 三组，记录各自的 Hit@K 和 MRR。

目标：观察 chunk 大小对召回的影响。

---

### 练习 3：跑 overlap 与策略实验

按 1.4、1.5 各跑一组对比，记录结果。

目标：验证 overlap 和切分策略是否改善了昨天的失败 case。

---

### 练习 4：填实验对比表

把所有实验填进 1.7 的表格，圈出最优组合，写一句归因。

目标：用数据（而非感觉）选出最佳参数。

---

### 练习 5：完成本周类比打卡

对照 1.8 的类比表，用自己的话把 9 个概念各写一句类比（不许照抄），发到你的学习打卡渠道。

目标：确认整周概念真正内化。

---

## 4. JS/Node.js 类比

| Python / RAG 优化 | Node.js / JS 类比 | 说明 |
|---|---|---|
| 控制变量实验 | A/B 测试 / benchmark 对比 | 一次只改一个变量 |
| `run_experiment()` | 参数化测试 `test.each()` | 同一逻辑跑多组参数 |
| chunk_size 调参 | 调分页 pageSize | 影响每次处理的粒度 |
| overlap | 滑动窗口重叠 | 防止边界信息丢失 |
| 实验对比表 | benchmark 结果表 | 对比不同配置性能 |
| best 组合 | 选定最优配置 | 用数据决策 |

一句话类比：

> 今天做的事 ≈ 给搜索功能做性能调优的 A/B 测试。你在跑一组 benchmark，改一个参数、量一次指标，最后选出跑分最高的配置。

---

## 5. AI Review 提问

完成实验后，把你的实验对比表贴给 AI，然后问：

```text
我在优化 RAG 召回，做了 chunk 大小、overlap、切分策略、Top-K 四组对照实验。
这是我的实验对比表（含 Hit@K 和 MRR）。

请你按资深工程师标准帮我检查：

1. 我的实验设计是否做到了"控制变量"？有没有混淆变量？
2. 从数据看，哪个参数对召回影响最大？
3. 我选的 best 组合合理吗？还有没有没试过的方向？
4. 我的类比（Embedding/Chunk/召回 ≈ JS 概念）准确吗？
5. 真实企业 RAG 里，除了这几个旋钮，还有哪些提升召回的手段？

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [✅] 可复用的 `run_experiment()` 函数
- [✅] Chunk 大小 / overlap / 策略 / Top-K 四组实验结果
- [✅] 完整的实验对比表 + 最优组合
- [✅] 昨天失败 case 是否被改善的记录
- [✅] 本周 9 个概念的类比打卡
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [✅] 能解释"控制变量"优化法
- [✅] 能用同一批测试问题做对照实验
- [✅] 能说出 chunk 大小、overlap、策略各自的影响
- [✅] 能填出实验对比表并选出最优组合
- [✅] 召回指标相比 baseline 有改善（或能解释为何没改善）
- [✅] 能用自己的话完成本周类比打卡

---

## 8. 今日自测题

### 8.1 优化 RAG 召回为什么要"控制变量"？

参考答案：

> ✅ 一次只改一个参数，指标变化才能归因到那个参数。一次改多个，指标变了也不知道是谁起的作用，无法复现和优化。

---

### 8.2 chunk 太大和太小分别有什么问题？

参考答案：

> ✅ 太大：一个 chunk 包含多个主题，召回被稀释、噪声多。太小：上下文被切碎、语义不完整。通常取一个平衡点（如 ~500），并用测试数据验证。

---

### 8.3 overlap 的作用是什么？

参考答案：

> ✅ 让相邻 chunk 共享一段文字，防止关键句正好被切在边界导致语义丢失。代价是 chunk 数量和存储/费用增加，常用 10%~20% 的 chunk 大小。

---

### 8.4 什么文档适合"按标题切"？

参考答案：

> ✅ 有清晰标题结构的文档（手册、技术文档）。按标题切能让每个 chunk 主题干净、召回更准。纯无结构长文本则只能用固定长度切分。

---

### 8.5 K=1 就 Hit 很高说明什么？

参考答案：

> ✅ 说明排序很准，正确答案常排第一，是好现象。反之如果非要 K=5 才达标，说明正确答案常排在后面，Chunk 或模型还有优化空间。

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
我正在进行 Week 17 Day 05：优化与类比日 的学习。
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
