# Week 17 Day 02：Chunk 切分策略

> 所属周：Week 17：Embedding + Chunk  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/rag`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

实现按标题/段落切分（Chunk 切分策略）。

今天你要真正掌握这一句话：

> Chunk 切分就是把一篇长文档"切成一小段一小段"，每一段单独去做 Embedding 和检索。切分的好坏直接决定 RAG 能不能召回到"正好回答问题的那一段"，所以切分不是随便 `split()`，而是要尽量沿着标题、段落这种"语义边界"切，并让相邻块之间留一点重叠（overlap）。

昨天你学会了"文本 → 向量"。但真实文档动辄几万字，不能整篇丢给 Embedding。今天解决的问题是：**在做 Embedding 之前，怎么把长文档切成合适的小块。**

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解为什么必须切 Chunk（不切会怎样）
2. 理解"块大小"和"重叠"两个核心参数
3. 学会最简单的按字符/token 定长切分
4. 学会按段落切分（沿空行边界）
5. 学会按标题切分（沿 Markdown `#` 结构）
6. 理解递归切分（先大后小，兜底定长）
7. 给每个块补上 metadata（来源、标题、序号）
8. 对比几种策略的优劣
9. 阅读示例 `chunking.py` 源码结构
10. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 为什么必须切 Chunk

先问一个问题：既然能把文本变成向量，为什么不直接把整篇文档变成一个向量？

原因有三个：

1. **模型有输入上限**：Embedding 模型一次能接收的 token 数有限（常见 8192 token）。一篇长文档塞不进去。
2. **一个向量表达不了整篇文章**：把一万字压成一个 1536 维向量，等于把整本书的意思浓缩成一个"平均值"，细节全被抹平。用户问某个具体细节时，这个"平均向量"根本对不上。
3. **召回要精准**：RAG 的目标是"找到正好回答问题的那一段"，然后把这一段（而不是整篇）喂给大模型。段越小越聚焦，召回越准。

所以标准做法是：

```text
长文档 → 切成 N 个 Chunk → 每个 Chunk 单独 Embedding → 每个 Chunk 单独存储/检索
```

小白重点：

> Chunk 是 RAG 里检索和召回的"最小单位"。你检索到的不是"文档"，而是"文档里的某一块"。

---

### 1.2 两个核心参数：chunk_size 与 chunk_overlap

切分几乎所有策略都绕不开这两个参数：

| 参数 | 含义 | 类比 |
|---|---|---|
| `chunk_size` | 每块的最大长度（字符数或 token 数） | 每页纸能写多少字 |
| `chunk_overlap` | 相邻两块重叠的长度 | 每页开头重复上一页结尾几句话 |

为什么要重叠？看这个例子。假设一句话被切在了块的边界上：

```text
块 A: ......用户下单后，系统会先锁定库存，
块 B: 然后创建订单记录并扣减余额......
```

如果用户问"下单后系统做了什么"，正确答案横跨 A、B 两块。没有重叠时，检索可能只召回半句，答不全。加了重叠后：

```text
块 A: ......用户下单后，系统会先锁定库存，然后创建订单记录
块 B: 系统会先锁定库存，然后创建订单记录并扣减余额......
```

两块都包含了完整语义，召回哪一块都能答对。

经验值（起步阶段够用）：

```text
chunk_size    = 500 ~ 1000 token（中文可先按 300 ~ 500 字）
chunk_overlap = chunk_size 的 10% ~ 20%
```

---

### 1.3 策略一：按定长切分（最简单）

最朴素的做法：不管内容，每 N 个字符切一刀，带一点重叠。

```python
def chunk_by_length(text: str, chunk_size: int = 500, overlap: int = 100) -> list[str]:
    """按固定长度切分，相邻块重叠 overlap 个字符"""
    chunks = []
    start = 0
    while start < len(text):
        end = start + chunk_size
        chunk = text[start:end]
        chunks.append(chunk)
        # 下一块的起点 = 当前终点 - 重叠，保证相邻块有重叠
        start = end - overlap
    return chunks


text = "这是一篇很长的文档" * 200  # 造一段长文本
chunks = chunk_by_length(text, chunk_size=500, overlap=100)

print(f"共切出 {len(chunks)} 块")
print(f"第 1 块长度：{len(chunks[0])}")
```

对比 JS：

```js
function chunkByLength(text, chunkSize = 500, overlap = 100) {
  const chunks = [];
  let start = 0;
  while (start < text.length) {
    const end = start + chunkSize;
    chunks.push(text.slice(start, end));
    start = end - overlap;
  }
  return chunks;
}
```

小白重点：定长切分实现最简单，但它是"闭着眼睛切"，很容易把一句话、一个表格从中间劈开。它适合做**兜底**，不适合当主力。

---

### 1.4 策略二：按段落切分（沿空行边界）

真实文档里，段落之间通常有空行。段落本身就是天然的语义单位。我们优先沿段落切：

```python
def chunk_by_paragraph(text: str, max_size: int = 800) -> list[str]:
    """按段落（空行分隔）切分，累积到接近 max_size 时开一块新块"""
    # 用连续空行切成段落
    paragraphs = [p.strip() for p in text.split("\n\n") if p.strip()]

    chunks = []
    current = ""
    for para in paragraphs:
        # 如果加上这段还没超长，就并进当前块
        if len(current) + len(para) <= max_size:
            current += para + "\n\n"
        else:
            # 超长了，先把当前块收尾，再用这段开新块
            if current:
                chunks.append(current.strip())
            current = para + "\n\n"
    if current:
        chunks.append(current.strip())
    return chunks
```

这样切出来的块**不会把一个完整段落劈开**，语义更完整。

小白重点：段落切分的核心思路是"贪心合并"——一段一段往当前块里塞，塞不下了才另起一块。这样既尊重段落边界，又不会切出一堆太小的碎块。

---

### 1.5 策略三：按标题切分（沿文档结构）

技术文档、知识库文章通常有清晰的标题层级（Markdown 的 `#`、`##`、`###`）。标题下面的内容就是一个自然的知识单元。按标题切，能保证"一个块 = 一个小主题"。

```python
import re

def chunk_by_heading(markdown: str) -> list[dict]:
    """按 Markdown 标题切分，每块记录它所属的标题"""
    lines = markdown.split("\n")
    chunks = []
    current_title = "（无标题）"
    current_body = []

    for line in lines:
        # 匹配 # / ## / ### 开头的标题行
        if re.match(r"^#{1,6}\s", line):
            # 遇到新标题，先把上一段收尾
            if current_body:
                chunks.append({
                    "title": current_title,
                    "content": "\n".join(current_body).strip(),
                })
                current_body = []
            current_title = line.lstrip("#").strip()
        else:
            current_body.append(line)

    # 收尾最后一块
    if current_body:
        chunks.append({
            "title": current_title,
            "content": "\n".join(current_body).strip(),
        })
    return [c for c in chunks if c["content"]]


md = """
# 订单模块
订单模块负责创建和管理订单。

## 下单流程
用户下单后，系统先锁定库存，再创建订单。

## 退款流程
退款时会先校验订单状态，再原路退回。
"""

for c in chunk_by_heading(md):
    print(f"[{c['title']}] -> {c['content'][:20]}...")
```

输出类似：

```text
[订单模块] -> 订单模块负责创建和管理订单。...
[下单流程] -> 用户下单后，系统先锁定库存...
[退款流程] -> 退款时会先校验订单状态...
```

小白重点：按标题切分的最大好处是，**块自带"上下文标签"**。检索时你不仅知道内容，还知道它属于哪个小节，可以把标题也拼进 Embedding 文本，提升召回准确度。

---

### 1.6 策略四：递归切分（推荐的组合拳）

真实工程里最常用的是"递归切分"（LangChain 的 `RecursiveCharacterTextSplitter` 就是这个思路）：**优先用大的语义边界切，切完还太长就用更小的边界继续切，最后才用定长兜底。**

分隔符优先级（从粗到细）：

```text
["\n\n", "\n", "。", "，", " ", ""]
```

思路：

1. 先按 `\n\n`（段落）切
2. 某块还超长？对这块再按 `\n`（换行）切
3. 还超长？按 `。`（句号）切
4. 实在不行，按字符定长切

```python
def recursive_chunk(text: str, max_size: int = 500,
                    seps: list[str] = None) -> list[str]:
    """递归切分：优先用更粗的分隔符，超长再降级"""
    if seps is None:
        seps = ["\n\n", "\n", "。", "，", " ", ""]

    # 已经够短，直接返回
    if len(text) <= max_size:
        return [text]

    sep = seps[0]
    rest = seps[1:] if len(seps) > 1 else [""]

    # 按当前分隔符切
    parts = text.split(sep) if sep else list(text)

    chunks = []
    for part in parts:
        if len(part) <= max_size:
            chunks.append(part)
        else:
            # 这一块还太长，用更细的分隔符递归处理
            chunks.extend(recursive_chunk(part, max_size, rest))
    # 合并相邻小块（略，实际工程会再做一次贪心合并）
    return [c for c in chunks if c.strip()]
```

小白重点：递归切分兼顾了"尊重语义边界"和"控制块大小"。它是知识库场景的默认首选。你现在不必手写得多完美，理解这个"从粗到细降级"的思想就够了。

---

### 1.7 给每个 Chunk 补 metadata

只有纯文本还不够。检索到一块后，我们还想知道它从哪来。所以每块要带上 metadata：

```python
def build_chunks(doc_id: str, source: str, markdown: str) -> list[dict]:
    """切分并附加 metadata，产出可入库的结构"""
    raw = chunk_by_heading(markdown)
    result = []
    for i, c in enumerate(raw):
        result.append({
            "id": f"{doc_id}-{i}",           # 全局唯一 id
            "text": f"{c['title']}\n{c['content']}",  # 标题拼进正文，利于召回
            "metadata": {
                "doc_id": doc_id,            # 属于哪篇文档
                "source": source,            # 文件名/URL
                "title": c["title"],         # 所属标题
                "chunk_index": i,            # 第几块
            },
        })
    return result
```

这些 metadata 明天入库时会一起存进向量库，召回后可以用来展示"来源"、做过滤、做引用标注。

小白重点：

> RAG 的可信度，很大程度来自"能说清答案的出处"。metadata 就是出处的载体，切分阶段就要设计好。

---

### 1.8 几种策略对比

| 策略 | 优点 | 缺点 | 适用场景 |
|---|---|---|---|
| 定长切分 | 实现最简单、块大小均匀 | 会劈开句子/表格，语义碎 | 兜底、纯文本流 |
| 段落切分 | 尊重段落语义 | 段落长短不一，块大小不均 | 文章、博客 |
| 标题切分 | 块自带主题标签 | 依赖文档有标题结构 | 技术文档、知识库 |
| 递归切分 | 兼顾语义与大小 | 实现稍复杂 | 通用首选 |

一个实用建议：**标题切分 + 递归兜底**。先按标题切，某个标题下的内容太长，再对这块递归切。这是知识库场景的黄金组合。

---

## 2. 源码阅读

- `ai-lab/rag/chunking.py`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. 有没有 `chunk_size` / `chunk_overlap` 参数，默认值是多少
2. 用了哪种分隔符优先级（`separators` 列表）
3. 是否处理了 metadata（source / title / index）
4. 是否有"合并太小的块"这类后处理
5. 有没有针对中文的特殊处理（中文没有空格分词）

建议你在笔记里整理成表格：

| 源码要点 | 它的做法 | 我的理解 |
|---|---|---|
| 默认 chunk_size |  |  |
| 分隔符优先级 |  |  |
| metadata 字段 |  |  |
| 中文处理 |  |  |

---

## 3. 练习任务

### 练习 1：实现三种切分并对比块数

准备一段较长的 Markdown 文本（可用一篇你自己的笔记），分别用定长、段落、标题三种方式切，打印块数和平均块长：

```python
def report(name: str, chunks: list) -> None:
    texts = [c if isinstance(c, str) else c.get("content", "") for c in chunks]
    avg = sum(len(t) for t in texts) / max(len(texts), 1)
    print(f"{name}: 共 {len(texts)} 块，平均 {avg:.0f} 字")

report("定长", chunk_by_length(md_text, 500, 100))
report("段落", chunk_by_paragraph(md_text, 800))
report("标题", chunk_by_heading(md_text))
```

目标：直观感受不同策略切出来的块数和均匀程度差异。

---

### 练习 2：实现带重叠的段落切分

在 1.4 的段落切分基础上，加入 `overlap`：每块开头带上上一块结尾的若干字符。

目标：理解 overlap 在"块之间"的具体落地方式。

---

### 练习 3：给标题切分补 metadata 并落盘

用 1.7 的 `build_chunks`，把切分结果写成 `chunks.json`：

```python
import json

chunks = build_chunks("doc-001", "订单模块.md", md_text)
with open("chunks.json", "w", encoding="utf-8") as f:
    json.dump(chunks, f, ensure_ascii=False, indent=2)

print(f"已写出 {len(chunks)} 块到 chunks.json")
```

目标：产出一个明天能直接入库的 `chunks.json`。

---

### 练习 4：测试不同 chunk_size 的影响

同一篇文档，分别用 `chunk_size = 200 / 500 / 1000` 切，观察块数变化，并思考：块越小召回越精准但越可能丢上下文，块越大上下文越全但越可能召回到无关内容。把你的观察写进笔记。

目标：建立"块大小是个权衡"的直觉，为 Day 05 调优打基础。

---

## 4. JS/Node.js 类比

| Python / RAG | Node.js / JS 类比 | 说明 |
|---|---|---|
| `text.split("\n\n")` | `text.split("\n\n")` | 按段落切，几乎一样 |
| `chunk_by_length` | 手写滑动窗口 `slice` | 定长切分逻辑相同 |
| `RecursiveCharacterTextSplitter` | LangChain.js 同名类 | Node 生态有对应实现 |
| `chunk` 的 metadata | 给数组元素包一层对象 | `{ text, metadata }` |
| `re.match(r"^#{1,6}\s")` | `/^#{1,6}\s/.test(line)` | 正则匹配标题行 |

一句话类比：

> Chunk 切分本质就是"高级版的 `split()`"——普通 `split()` 只按一个分隔符切，而 RAG 的切分要按语义边界切、控制大小、加重叠、附 metadata。

---

## 5. AI Review 提问

完成练习后，把你的切分代码和 `chunks.json` 贴给 AI，然后问：

```text
我正在学习 RAG 的 Chunk 切分。这是我的切分代码和产出。

请你按资深工程师标准帮我检查：

1. 我的 chunk_size 和 overlap 设置是否合理？
2. 我的切分策略会不会把关键语义（表格、代码块、列表）劈开？
3. metadata 字段设计是否够用？后续做召回引用会不会缺信息？
4. 中文场景下我的切分有没有明显问题？
5. 如果要上生产，我还需要补哪些边界处理？

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [✅] 定长 / 段落 / 标题三种切分函数
- [✅] 带 overlap 的段落切分
- [✅] 带 metadata 的 `build_chunks`
- [✅] `chunks.json`（明天入库用）
- [✅] 不同 chunk_size 的对比观察笔记
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [✅] 能说清"为什么必须切 Chunk"
- [✅] 能解释 `chunk_size` 和 `chunk_overlap` 的作用
- [✅] 能实现按段落切分
- [✅] 能实现按标题切分
- [✅] 理解递归切分"从粗到细降级"的思想
- [✅] 能给每块附上 metadata
- [✅] 能产出可入库的 `chunks.json`
- [✅] 能说出三种策略各自的适用场景

---

## 8. 今日自测题

### 8.1 为什么不能把整篇文档做成一个向量？

参考答案：

> ✅ 三个原因：模型有输入 token 上限塞不下；一个向量会把整篇内容压成"平均值"抹平细节；RAG 要精准召回"正好回答问题的那一段"，块越聚焦召回越准。

---

### 8.2 `chunk_overlap` 是干什么用的？

参考答案：

> ✅ 让相邻两块有一段重叠内容。这样即使一句完整语义被切在块边界上，前后两块也都包含完整信息，避免召回到"半句话"。

---

### 8.3 按标题切分相比定长切分好在哪？

参考答案：

> ✅ 块沿标题这种语义边界切，不会把一个小主题劈开；而且块自带标题标签，可作为上下文拼进 Embedding 文本，召回更准。

---

### 8.4 递归切分的核心思想是什么？

参考答案：

> ✅ 从粗到细降级：先用最大的语义分隔符（段落）切，某块还超长就换更细的分隔符（换行、句号）继续切，最后才用定长兜底。兼顾语义完整和块大小可控。

---

### 8.5 metadata 里为什么要存 source 和 title？

参考答案：

> ✅ 为了召回后能说清答案的出处，做引用标注和来源过滤。RAG 的可信度很大程度来自"能追溯出处"，所以切分阶段就要把来源信息记录好。

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
我正在进行 Week 17 Day 02：Chunk 切分策略 的学习。
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
