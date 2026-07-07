# Week 19 Day 02：上下文窗口

> 所属周：Week 19：Memory + Session  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/customer-agent`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解「上下文窗口」和「token 预算」，实现「只保留最近 N 条消息」的窗口截断，防止历史越滚越长把模型撑爆。

今天你要真正掌握这一句话：

> 模型能一次读多少字是有硬上限的（上下文窗口），而多轮对话的历史会越来越长，迟早超限。最简单的解决办法是「滑动窗口」：每次只把最近 N 条（或不超过某个 token 预算的）消息发给模型，把太老的历史丢掉。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 理解什么是「上下文窗口（context window）」
2. 理解什么是 token，为什么按 token 算而不是按「字」算
3. 理解「历史无限增长」这个问题为什么严重
4. 学会粗略估算一段文本的 token 数
5. 理解「滑动窗口」策略：只留最近 N 条
6. 理解「按 token 预算」截断（比只数条数更靠谱）
7. 实现窗口截断代码
8. 理解 system 消息为什么要「钉住」不参与截断
9. 和 Node 的 `array.slice(-N)` 做类比
10. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 什么是上下文窗口

「上下文窗口（context window）」是指模型**一次请求最多能处理的 token 数量上限**，包括你发过去的所有 messages **加上**它要生成的回复。

打个比方：模型的「工作台」大小是固定的。你发的历史越多，占的桌面越大，留给它写回复的空间就越小；一旦超过桌面总面积，就会直接报错。

不同模型窗口大小不同（示意值）：

| 模型（示意） | 上下文窗口（token） | 大致相当于 |
|---|---|---|
| 早期小模型 | 4K (4096) | 几千字 |
| 中等模型 | 32K | 几万字 |
| 大窗口模型 | 128K | 十几万字 |
| 超大窗口 | 1M | 一整本书 |

小白重点：

> 窗口再大也是**有限**的，而对话历史是**无限增长**的。所以「管理历史长度」是每个做 AI 后端的人绕不开的活。

---

### 1.2 什么是 token，为什么不按「字」算

模型不是按「字」或「字符」计费和计长度的，而是按 **token**。token 是模型切分文本的最小单位，一个 token 可能是一个词、半个词、一个汉字、或一个标点。

粗略经验（记住这个量级就够用了）：

| 语言 | 粗略换算 |
|---|---|
| 英文 | 1 token ≈ 4 个字符 ≈ 0.75 个单词 |
| 中文 | 1 个汉字 ≈ 1~2 token |

小白重点：

> 精确算 token 要用官方的 tokenizer（比如 `tiktoken`）。但在学习阶段，用「粗略估算」先跑通逻辑就行，别一开始就陷进精确计算里。

---

### 1.3 历史无限增长的问题

回顾 Day01：我们每轮都 `append` 两条消息（user + assistant）。5 轮就是 10 条，50 轮就是 100 条。如果每条几百 token，很快就会：

1. **超出窗口** → 直接报错，对话中断
2. **变慢** → 发的 token 越多，模型处理越慢
3. **变贵** → 大多数 API 按 token 收费，历史越长越烧钱

所以我们必须在「发给模型之前」，把历史裁剪到一个合理长度。

---

### 1.4 粗略估算 token

先写一个简单的估算函数（不追求精确，够用即可）：

```python
def estimate_tokens(text: str) -> int:
    """粗略估算 token 数：中文按字数，英文按 1/4 字符数，取较大者"""
    chinese = sum(1 for ch in text if '一' <= ch <= '鿿')
    others = len(text) - chinese
    # 中文 1 字≈1.5token，英文约 4 字符=1token
    return int(chinese * 1.5 + others / 4) + 1


def count_messages_tokens(messages: list[dict]) -> int:
    """估算一组消息的总 token"""
    return sum(estimate_tokens(m["content"]) for m in messages)
```

想更精确时可以换成官方库（了解即可）：

```python
# pip install tiktoken
import tiktoken

enc = tiktoken.get_encoding("cl100k_base")
tokens = len(enc.encode("你好，世界"))
```

小白重点：

> 学习阶段用 `estimate_tokens` 就行。上线前再换成官方 tokenizer 做精确控制。

---

### 1.5 策略一：只保留最近 N 条（滑动窗口）

最简单的策略：不管 token，只保留**最近 N 条**消息，太老的直接丢。

```python
def window_by_count(messages: list[dict], n: int) -> list[dict]:
    """只保留最近 n 条消息"""
    return messages[-n:]
```

`messages[-n:]` 表示「取列表最后 n 个」。这就是 JS 里的 `messages.slice(-n)`。

例子：

```python
history = [
    {"role": "user", "content": "第1句"},
    {"role": "assistant", "content": "回1"},
    {"role": "user", "content": "第2句"},
    {"role": "assistant", "content": "回2"},
    {"role": "user", "content": "第3句"},
]

print(window_by_count(history, 3))
# 只剩最后 3 条：第2句的回复、第3句...
```

小白重点：

> 「只留最近 N 条」实现最简单，但缺点是**最早的信息会被丢掉**（比如用户一开始说的名字）。这就是为什么 Day03 要学「摘要压缩」来保住早期关键信息。

---

### 1.6 策略二：按 token 预算截断（更靠谱）

只数「条数」有个问题：有的消息很短，有的很长。更合理的是设一个 **token 预算**，从最新的往前加，加到快超预算就停。

```python
def window_by_tokens(messages: list[dict], max_tokens: int) -> list[dict]:
    """从最新往旧保留，总 token 不超过 max_tokens"""
    result = []
    total = 0
    # 从最后一条往前遍历
    for msg in reversed(messages):
        t = estimate_tokens(msg["content"])
        if total + t > max_tokens:
            break
        result.insert(0, msg)  # 插到最前，保持原顺序
        total += t
    return result
```

用法：

```python
kept = window_by_tokens(history, max_tokens=500)
```

| 策略 | 实现难度 | 优点 | 缺点 |
|---|---|---|---|
| 只留最近 N 条 | 最简单 | 一行代码 | 长短不均时不准 |
| 按 token 预算 | 中等 | 更贴近真实限制 | 要估算 token |
| 窗口 + 摘要 | 较难 | 保住早期关键信息 | Day03 才学 |

---

### 1.7 system 消息要「钉住」

有个关键坑：`system` 消息（角色设定，比如「你是一个客服」）**绝对不能被截断丢掉**。一旦丢了，模型就忘了自己是谁。

所以正确做法是：把 system 单独拎出来，永远保留，只对 user/assistant 历史做截断。

```python
def build_context(messages: list[dict], max_tokens: int) -> list[dict]:
    # 1. 分离 system 和其余历史
    systems = [m for m in messages if m["role"] == "system"]
    others = [m for m in messages if m["role"] != "system"]

    # 2. system 先占预算
    system_tokens = count_messages_tokens(systems)
    budget = max_tokens - system_tokens

    # 3. 剩余预算留给最近的历史
    kept = window_by_tokens(others, budget)

    # 4. system 永远放最前面
    return systems + kept
```

小白重点：

> 截断时永远「钉住 system，裁剪历史」。这是新手最容易忽略的一步。

---

### 1.8 把窗口逻辑接进 Day01 的 SessionManager

现在把窗口逻辑用到实际对话里：存历史时全存，但**发给模型前先过一遍 `build_context`**。

```python
def chat(sm, sid, user_text, max_tokens=1000):
    sm.append(sid, "user", user_text)

    full_history = sm.get_messages(sid)
    context = build_context(full_history, max_tokens)  # 只发裁剪后的

    print(f"完整历史 {len(full_history)} 条，实际发送 {len(context)} 条")
    reply = fake_llm(context)  # Day01 的假模型

    sm.append(sid, "assistant", reply)
    return reply
```

注意：**完整历史仍然全部存着**，只是「发给模型的」是裁剪版。这样将来需要时（比如做摘要）还能拿到完整历史。

---

## 2. 源码阅读

- `ai-lab/customer-agent/session.ts`（重点看截断/窗口相关部分）

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. 有没有 `maxTokens` / `maxMessages` 之类的配置
2. 是按「条数」还是按「token」截断
3. 有没有用 tokenizer（如 `tiktoken` / `gpt-tokenizer`）
4. system 消息是怎么处理的，有没有被保护
5. 是「存的时候截」还是「发的时候截」

对照表：

| session.ts 里的东西 | 作用 | 我的 Python 对应 |
|---|---|---|
| `slice(-N)` | 留最近 N 条 | `messages[-n:]` |
| `maxTokens` | token 预算 | `max_tokens` |
| `countTokens()` | 估算 token | `estimate_tokens()` |
| 单独拼 system | 保护角色设定 | `build_context` 里的分离 |

---

## 3. 练习任务

### 练习 1：实现按条数截断

写出并测试 `window_by_count`，验证 `messages[-3:]` 确实只留最后 3 条。

目标：理解 `[-n:]` 和 `slice(-n)` 的等价。

---

### 练习 2：实现 token 估算

写出 `estimate_tokens`，分别对一段中文、一段英文测试，观察数量级是否合理。

```python
print(estimate_tokens("你好世界"))       # 中文
print(estimate_tokens("hello world"))     # 英文
```

目标：能说出「中文 1 字约 1~2 token，英文 4 字符约 1 token」。

---

### 练习 3：实现按 token 预算截断

写出 `window_by_tokens`，构造一批长短不一的消息，验证总 token 不超预算。

目标：理解为什么按 token 比按条数更靠谱。

---

### 练习 4：保护 system 消息

写出 `build_context`，构造一组含 system 的消息，把 `max_tokens` 调得很小，验证：**其它历史被裁掉了，但 system 还在，且在最前面**。

目标：亲手验证「system 被钉住」。

---

### 练习 5：思考 N 怎么选

在笔记里回答：如果你的模型窗口是 32K token，你会给「历史」留多少 token 预算？为什么要留出余量给「模型回复」和「system」？

参考思路：

> 不能把 32K 全给历史。要减去 system（比如 500）、减去期望的回复长度（比如 2000）、再留安全余量（比如 10%）。剩下的才是历史预算。

---

## 4. JS/Node.js 类比

| Python / AI 后端 | Node.js 类比 | 说明 |
|---|---|---|
| `messages[-n:]` | `messages.slice(-n)` | 取最近 n 条 |
| `reversed(messages)` | `[...messages].reverse()` | 从新往旧遍历 |
| `estimate_tokens` | `gpt-tokenizer` 的 encode 长度 | 估算 token |
| `tiktoken` | `js-tiktoken` / `gpt-tokenizer` | 官方分词库 |
| `build_context` | 拼 prompt 前的裁剪函数 | 发送前处理 |

一句话类比：

> 窗口截断就是 `messages.slice(-N)` 的加强版：先钉住 system，再按 token 预算从最新往回留。

---

## 5. AI Review 提问

```text
我正在学习 Week 19 Day 02：上下文窗口与窗口截断。

请你按资深后端工程师标准帮我检查：

1. 我对「上下文窗口 / token 预算」的理解对吗？
2. 我的 estimate_tokens 粗估合理吗？什么时候必须换成官方 tokenizer？
3. 按 token 截断的 window_by_tokens 有没有边界 bug？
4. 我保护 system 消息的做法对吗？还有哪些消息该保护？
5. 生产环境里选 N / max_tokens 有什么经验值？

请用中文输出：合理的地方、有问题的地方、修改建议、下一步练习。
```

---

## 6. 今日产出

- [✅] `window_by_count`（按条数截断）
- [✅] `estimate_tokens`（token 估算）
- [✅] `window_by_tokens`（按预算截断）
- [✅] `build_context`（保护 system）
- [✅] 接进 SessionManager 的 chat 演示
- [✅] 「N 怎么选」的思考笔记
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [✅] 能解释什么是上下文窗口
- [✅] 能解释 token 和「字」的区别
- [✅] 能说出历史无限增长的三个危害
- [✅] 能实现按条数截断
- [✅] 能实现按 token 预算截断
- [✅] 窗口生效（发送条数少于完整历史）
- [✅] 能保护 system 消息不被裁掉
- [✅] 能用 `slice(-N)` 做类比

---

## 8. 今日自测题

### 8.1 什么是上下文窗口？

参考答案：

> ✅ 模型一次请求能处理的最大 token 数上限，包含输入的所有消息和它要生成的回复。超过就报错。

---

### 8.2 为什么按 token 算而不按字数算？

参考答案：

> ✅ 模型内部把文本切成 token 处理和计费。一个 token 可能是词、半个词、一个汉字或标点，和「字数」不是一一对应的。

---

### 8.3 「只留最近 N 条」有什么缺点？

参考答案：

> ✅ 会把最早的信息丢掉，比如用户开头说的名字/需求。所以需要配合摘要压缩来保住早期关键信息。

---

### 8.4 截断时为什么要保护 system 消息？

参考答案：

> ✅ system 是角色/规则设定，一旦被裁掉，模型就忘了自己是谁、该怎么答。所以要单独钉住，只裁剪 user/assistant 历史。

---

### 8.5 按 token 预算截断的思路是什么？

参考答案：

> ✅ 从最新的消息往旧遍历，累加 token，加到快超预算就停，保留下来的就是最近且不超预算的一段历史。

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
我正在进行 Week 19 Day 02：上下文窗口 的学习。
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
