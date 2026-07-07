# Week 19 Day 03：摘要压缩

> 所属周：Week 19：Memory + Session  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/customer-agent`  
> 类型：编码练习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解「摘要压缩」：当历史太长时，用 LLM 把老对话总结成一段 summary，用短短一段话代替一大堆旧消息，既省 token 又保住关键信息。

今天你要真正掌握这一句话：

> 只做窗口截断会把早期信息丢光。更聪明的做法是：把「太老的历史」交给 LLM 总结成一小段 summary，塞进 system 里；这样即使原始消息被删，用户开头说的名字、需求、约定还在，token 却大幅下降。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾 Day02：窗口截断会丢掉早期信息
2. 理解「摘要压缩」要解决什么问题
3. 理解触发时机：什么时候该压缩
4. 理解压缩的三段结构：summary + 最近若干条
5. 写一个「假摘要」函数先跑通流程
6. 换成「真 LLM 摘要」的 prompt 写法
7. 把摘要塞回 system，形成新的上下文
8. 测试长对话，验证信息没丢
9. 和 Node 的类比
10. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 为什么需要摘要压缩

Day02 的窗口截断有个硬伤：**丢掉的信息就永远没了**。

看这个场景：

```text
第 1 轮：用户说「我叫张三，想退一个订单，订单号 A1001」
第 2~20 轮：一堆细节来回
第 21 轮：用户问「我刚说的订单号是多少来着？」
```

如果用「只留最近 5 条」，第 1 轮早被丢了，模型根本不知道订单号。

**摘要压缩**的思路：在丢弃老消息之前，先让 LLM 把它们总结成一段话：

```text
[历史摘要] 用户张三要退订单 A1001，已确认收货但商品有质量问题，客服已同意退款流程...
```

这段摘要塞进 system，几十条老消息就浓缩成几行字。

---

### 1.2 压缩后的三段结构

压缩后的上下文长这样：

```text
1. system：原始角色设定（你是客服...）
2. system：[历史摘要] 用户张三要退订单 A1001...   ← 新增的摘要
3. user/assistant：最近 N 条原始消息（不压缩，保持细节）
```

小白重点：

> 「最近的」保留原文（细节重要），「很老的」压成摘要（只留要点）。这就是「近处高清、远处模糊」的记忆策略。

对比三种策略：

| 策略 | 早期信息 | token | 复杂度 |
|---|---|---|---|
| 全量历史 | 全保留 | 爆炸 | 低 |
| 只窗口截断 | 丢光 | 可控 | 低 |
| 窗口 + 摘要 | 要点保留 | 可控 | 中 |

---

### 1.3 什么时候触发压缩

不是每轮都压缩（那样太费钱），而是**达到阈值才压**。常见触发条件：

```python
def need_compress(messages, max_messages=20, max_tokens=3000):
    if len(messages) > max_messages:
        return True
    if count_messages_tokens(messages) > max_tokens:
        return True
    return False
```

小白重点：

> 触发条件设「宽松」一点，别太频繁。压缩本身要调一次 LLM，也是有成本的。

---

### 1.4 先用「假摘要」跑通流程

和 Day01 一样，先不接真 LLM，用假函数把流程跑通：

```python
def fake_summarize(messages: list[dict]) -> str:
    """假摘要：把消息内容拼起来截断，仅用于跑通流程"""
    text = " / ".join(m["content"] for m in messages)
    return f"[历史摘要] {text[:50]}..."
```

先验证「结构」对不对，再换真模型。

---

### 1.5 真 LLM 摘要的 prompt 怎么写

真正压缩时，专门发一个「请总结」的请求给 LLM：

```python
def llm_summarize(messages: list[dict]) -> str:
    """用 LLM 总结历史（此处 call_llm 是你封装的调用函数）"""
    history_text = "\n".join(
        f'{m["role"]}: {m["content"]}' for m in messages
    )
    prompt = [
        {"role": "system", "content":
            "你是对话摘要助手。请把下面的对话浓缩成简短摘要，"
            "必须保留：用户身份、关键需求、订单号/编号、已达成的结论。"
            "不要编造，不要超过 100 字。"},
        {"role": "user", "content": f"请总结以下对话：\n{history_text}"},
    ]
    return call_llm(prompt)  # 返回摘要文本
```

小白重点（摘要 prompt 三要素）：

> 1. 明确「必须保留什么」（身份、编号、结论）  
> 2. 明确「不要编造」（防止 LLM 瞎补）  
> 3. 明确「长度上限」（否则摘要也可能很长）

---

### 1.6 完整的压缩函数

把上面拼起来：压缩「老的一半」，保留「近的一半」。

```python
def compress(messages: list[dict], keep_recent: int = 6) -> list[dict]:
    systems = [m for m in messages if m["role"] == "system"]
    others = [m for m in messages if m["role"] != "system"]

    if len(others) <= keep_recent:
        return messages  # 不够长，不压

    old = others[:-keep_recent]      # 要压缩的老消息
    recent = others[-keep_recent:]   # 保留的近消息

    summary = llm_summarize(old)     # 老消息 → 一段摘要
    summary_msg = {"role": "system", "content": summary}

    return systems + [summary_msg] + recent
```

流程图（文字版）：

```text
[system][老1][老2]...[老N][近1]...[近6]
                 │
                 ▼  llm_summarize(老1..老N)
[system][摘要][近1]...[近6]
```

---

### 1.7 接进对话循环

在对话循环里，每轮结束后检查是否要压缩：

```python
def chat(sm, sid, user_text):
    sm.append(sid, "user", user_text)
    context = sm.get_messages(sid)
    reply = call_llm(context)
    sm.append(sid, "assistant", reply)

    # 关键：达到阈值就压缩并回写
    history = sm.get_messages(sid)
    if need_compress(history):
        compressed = compress(history)
        sm.replace(sid, compressed)   # 用压缩后的替换原历史
        print(f"已压缩：{len(history)} → {len(compressed)} 条")

    return reply
```

这里需要给 SessionManager 加一个 `replace` 方法：

```python
def replace(self, session_id: str, messages: list[dict]) -> None:
    self._store[session_id] = messages
```

---

### 1.8 摘要会丢信息吗

会。摘要本质是**有损压缩**，一定会丢细节。所以要注意：

| 风险 | 说明 | 缓解 |
|---|---|---|
| 丢关键字段 | 订单号被省略 | prompt 明确要求保留 |
| 编造信息 | LLM 幻觉补细节 | prompt 强调「不要编造」 |
| 摘要过时 | 后面又聊了新内容 | 定期重新摘要 |
| 摘要太长 | 没起到压缩作用 | 限制字数 |

小白重点：

> 摘要是「用信息损失换 token 空间」。关键字段要在 prompt 里点名保护，重要业务数据（订单号、金额）最好另外存结构化字段，别只靠摘要记。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

如果你的项目里有 `summarize` / `compress` / `condense` 相关函数，可以对照看：

1. 触发压缩的阈值是多少
2. 保留最近几条
3. 摘要 prompt 里点名保护了哪些字段
4. 摘要存哪里（system？单独字段？）

---

## 3. 练习任务

### 练习 1：假摘要跑通

写出 `fake_summarize` 和 `compress`，用假摘要把一段 20 条的历史压缩，打印压缩前后条数。

目标：先跑通结构，不接真模型。

---

### 练习 2：写摘要 prompt

写出 `llm_summarize` 的 prompt（可以先 print 出来不真调）。检查是否包含「保留什么、不编造、长度上限」三要素。

目标：会写摘要 prompt。

---

### 练习 3：触发条件

写出 `need_compress`，构造 25 条消息，验证触发；构造 5 条，验证不触发。

目标：理解阈值控制。

---

### 练习 4：测试长对话保信息

构造一个「第1轮说订单号 A1001，中间聊 20 轮，第22轮问订单号」的场景，验证压缩后摘要里**还留着 A1001**。

目标：亲眼确认摘要保住了关键信息。

---

### 练习 5：对比实验

同一段长对话，分别用「只窗口截断」和「窗口+摘要」处理，比较：早期信息是否还在、token 各是多少。写进笔记。

目标：用数据说明摘要的价值。

---

## 4. JS/Node.js 类比

| Python / AI 后端 | Node.js 类比 | 说明 |
|---|---|---|
| `compress()` | 消息数组的 reduce/总结函数 | 把老消息折叠 |
| `llm_summarize()` | 再调一次 LLM API | 摘要也是一次请求 |
| `others[:-keep_recent]` | `arr.slice(0, -keep)` | 取老的部分 |
| `others[-keep_recent:]` | `arr.slice(-keep)` | 取近的部分 |
| 摘要塞进 system | 拼 prompt 时前置一段 context | 前置背景 |

一句话类比：

> 摘要压缩就像 git 的 squash：把一堆旧 commit（旧消息）压成一条带说明的 commit（摘要），保留近期的明细。

---

## 5. AI Review 提问

```text
我正在学习 Week 19 Day 03：摘要压缩。

请你按资深后端工程师标准帮我检查：

1. 我的压缩触发时机（阈值）合理吗？
2. 我的摘要 prompt 能有效防止丢关键字段和编造吗？
3. compress 的「保留最近 N 条 + 摘要老消息」结构对吗？
4. 哪些业务数据不该只靠摘要记，应该单独结构化存储？
5. 摘要压缩在生产里还有什么坑（成本、延迟、累积误差）？

请用中文输出：合理的地方、有问题的地方、修改建议、下一步练习。
```

---

## 6. 今日产出

- [✅] `fake_summarize` + `compress`（跑通结构）
- [✅] `llm_summarize` 的摘要 prompt
- [✅] `need_compress` 触发逻辑
- [✅] 长对话保信息测试
- [✅] 「窗口 vs 窗口+摘要」对比实验
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [✅] 能解释摘要压缩解决什么问题
- [✅] 能说出压缩后的三段结构
- [✅] 能写出触发压缩的阈值逻辑
- [✅] 能写出保护关键字段的摘要 prompt
- [✅] 超长历史可以被压缩（条数明显下降）
- [✅] 压缩后关键信息（订单号）仍在
- [✅] 能说出摘要的信息损失风险

---

## 8. 今日自测题

### 8.1 摘要压缩比窗口截断好在哪？

参考答案：

> ✅ 窗口截断会把早期信息彻底丢掉，摘要压缩会先把老消息总结成一段话保住要点，再删原文，兼顾省 token 和保信息。

---

### 8.2 压缩后的上下文由哪几部分组成？

参考答案：

> ✅ 原始 system 设定 + 一段历史摘要（塞进 system）+ 最近 N 条未压缩的原始消息。

---

### 8.3 摘要 prompt 应该包含哪三要素？

参考答案：

> ✅ 保留什么（身份、编号、结论）、不要编造、长度上限。

---

### 8.4 摘要有什么风险？

参考答案：

> ✅ 有损压缩会丢细节，还可能编造（幻觉），摘要可能过时或太长。关键业务数据应另外结构化存储。

---

### 8.5 为什么不每轮都压缩？

参考答案：

> ✅ 压缩要额外调一次 LLM，有成本和延迟。所以设阈值，达到才压。

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
我正在进行 Week 19 Day 03：摘要压缩 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 19 README](./README.md)
