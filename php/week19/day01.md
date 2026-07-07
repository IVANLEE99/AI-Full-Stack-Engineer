# Week 19 Day 01：SessionManager 设计

> 所属周：Week 19：Memory + Session  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/customer-agent`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解「LLM 是无状态的」，用一个 Map（字典）实现最简单的会话存储 SessionManager，能按 `sessionId` 存取一段多轮对话历史。

今天你要真正掌握这一句话：

> 大模型每次调用都是「一次性」的，它不会记得上一次你说过什么。所谓的「多轮对话记忆」，其实是我们后端自己把历史消息存起来，下次请求时再一起发给模型。SessionManager 就是这个「存历史」的仓库，最简单的实现就是一个 `Map<sessionId, messages[]>`。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先搞懂一个反直觉的事实：LLM 没有记忆
2. 理解一次 LLM 请求里到底发了什么（messages 数组）
3. 理解「会话 / Session」是什么、为什么需要它
4. 理解 `sessionId` 是什么、怎么生成
5. 理解「用 Map 存会话」这个数据结构
6. 用 Python 写一个最小的 SessionManager
7. 把 SessionManager 和 Web 后端里的 Session 做类比
8. 和 Node.js 的 `express-session` / `Map` 做类比
9. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 反直觉的事实：LLM 没有记忆

很多小白第一次用 API 调大模型时都会踩一个坑：

> 「我明明上一句告诉它我叫 Tom 了，为什么下一句问『我叫什么』它就不知道了？」

原因是：**大模型的每一次 API 调用都是完全独立的**。它就像一个「失忆天才」，每次醒来都很聪明，但完全不记得之前发生过什么。

我们先看一段会「失忆」的错误代码（伪代码，讲思路）：

```python
# 第 1 次请求
call_llm("我叫 Tom")          # 模型回复：你好 Tom

# 第 2 次请求
call_llm("我叫什么名字？")     # 模型回复：抱歉，我不知道你的名字
```

第 2 次请求里，模型根本收不到「我叫 Tom」这句话，所以它当然不知道。

小白重点：

> 「记忆」不是模型自带的功能，而是我们后端程序员**手动把历史拼进去**实现的。

---

### 1.2 一次 LLM 请求里到底发了什么

主流大模型（OpenAI / DeepSeek / 通义等）的对话接口，都要求你传一个 `messages` 数组。每条消息有两个关键字段：

- `role`：谁说的，常见值 `system` / `user` / `assistant`
- `content`：说了什么

```python
messages = [
    {"role": "system", "content": "你是一个客服助手"},
    {"role": "user", "content": "我叫 Tom"},
    {"role": "assistant", "content": "你好 Tom，有什么可以帮你？"},
    {"role": "user", "content": "我叫什么名字？"},
]
```

只要你把上面**完整的 4 条**都发过去，模型就能从第 2 条里读到「我叫 Tom」，于是能正确回答。

| role | 含义 | 类比 |
|---|---|---|
| `system` | 系统设定，给模型定角色/规则 | 剧本里的「人物设定」 |
| `user` | 用户说的话 | 你发的消息 |
| `assistant` | 模型之前的回复 | 对方发的消息 |

小白重点：

> 所谓「多轮对话」，本质就是：**把这次之前的所有 `user` / `assistant` 消息，连同这次的新消息，一起塞进 `messages` 数组发给模型。**

---

### 1.3 什么是「会话 / Session」

一个「会话（Session）」就是**一个用户和 AI 的一段连续对话**。

举例：

- 用户 A 打开客服窗口聊天 → 这是会话 1
- 用户 B 打开客服窗口聊天 → 这是会话 2
- 用户 A 关掉再重开一个新窗口 → 这可能是会话 3

每个会话都有自己独立的一份 `messages` 历史，互相之间不能串。你肯定不希望用户 A 的对话跑到用户 B 那里去。

所以我们需要一个「仓库」，能做到：

```text
给我一个 sessionId，我就能拿到这个会话对应的 messages 历史
```

这个仓库，就是今天要写的 **SessionManager**。

---

### 1.4 什么是 sessionId，怎么生成

`sessionId` 就是每个会话的「身份证号」，用来区分不同会话。它必须满足：

1. **唯一**：不同会话的 id 不能撞
2. **不可猜**：别人不能轻易猜到你的 id 去偷看你的对话

最简单可靠的做法是用 UUID：

```python
import uuid

session_id = str(uuid.uuid4())
print(session_id)
# 例如：3f2504e0-4f89-41d3-9a0c-0305e82c3301
```

| 生成方式 | 是否推荐 | 原因 |
|---|---|---|
| `uuid4()` | 推荐 | 唯一且随机，不可猜 |
| 自增数字 1,2,3 | 不推荐 | 容易被猜，容易撞 |
| 用户名当 id | 不推荐 | 同一用户开多个会话会串 |
| 时间戳 | 不推荐 | 高并发下会撞 |

小白重点：

> `sessionId` 类似 Web 里浏览器 Cookie 中的那个 `session_id`，服务器靠它认出「你是哪一次会话」。

---

### 1.5 用 Map（字典）存会话

现在把前面的东西串起来。我们需要一个数据结构：**key 是 sessionId，value 是这个会话的 messages 列表**。

在 Python 里，这个结构就是 `dict`（字典）：

```python
sessions = {
    "会话A的id": [
        {"role": "user", "content": "我叫 Tom"},
        {"role": "assistant", "content": "你好 Tom"},
    ],
    "会话B的id": [
        {"role": "user", "content": "订单怎么退款？"},
    ],
}
```

这就是 JS 里常说的 `Map<id, messages[]>`。

| 语言 | 这种结构叫什么 | 写法 |
|---|---|---|
| Python | `dict` | `{}` |
| JS | `Object` 或 `Map` | `{}` 或 `new Map()` |
| PHP | 关联数组 `array` | `["id" => [...]]` |

小白重点：

> 「用 Map 存会话」= 拿 sessionId 当钥匙，去开对应会话的历史抽屉。

---

### 1.6 动手写第一个 SessionManager

现在我们把它封装成一个类。一个 SessionManager 至少要能做 4 件事：

1. `create()`：开一个新会话，返回 sessionId
2. `append(sid, role, content)`：往某个会话追加一条消息
3. `get_messages(sid)`：取出某个会话的完整历史
4. `clear(sid)`：清空/删除某个会话

完整可运行代码（保存为 `session_manager.py`）：

```python
import uuid


class SessionManager:
    def __init__(self):
        # key: session_id, value: list[dict]
        self._sessions: dict[str, list[dict]] = {}

    def create(self) -> str:
        """新建一个会话，返回 session_id"""
        session_id = str(uuid.uuid4())
        self._sessions[session_id] = []
        return session_id

    def append(self, session_id: str, role: str, content: str) -> None:
        """往会话里追加一条消息"""
        if session_id not in self._sessions:
            raise KeyError(f"会话不存在: {session_id}")
        self._sessions[session_id].append({
            "role": role,
            "content": content,
        })

    def get_messages(self, session_id: str) -> list[dict]:
        """取出会话的完整消息历史"""
        return self._sessions.get(session_id, [])

    def clear(self, session_id: str) -> None:
        """清空一个会话的历史"""
        if session_id in self._sessions:
            self._sessions[session_id] = []


if __name__ == "__main__":
    sm = SessionManager()

    sid = sm.create()
    print("新会话 id:", sid)

    sm.append(sid, "user", "我叫 Tom")
    sm.append(sid, "assistant", "你好 Tom")
    sm.append(sid, "user", "我叫什么名字？")

    for msg in sm.get_messages(sid):
        print(f"[{msg['role']}] {msg['content']}")
```

运行：

```bash
python session_manager.py
```

你应该看到：

```text
新会话 id: 3f2504e0-4f89-41d3-9a0c-0305e82c3301
[user] 我叫 Tom
[assistant] 你好 Tom
[user] 我叫什么名字？
```

小白重点：

> 类里的 `self._sessions` 就是那个 Map。`create` 负责发身份证，`append` 负责往抽屉里塞消息，`get_messages` 负责把整个抽屉端出来。

---

### 1.7 把 SessionManager 接到「假 LLM」上跑通闭环

为了让你直观感受「有记忆」是怎么实现的，我们写一个假的 `fake_llm`，它只会把收到的 messages 打印出来，你就能亲眼看到「历史被一起发过去了」。

```python
def fake_llm(messages: list[dict]) -> str:
    """假的 LLM：不真调用，只演示收到了完整历史"""
    print("---- 模型这次收到的 messages ----")
    for m in messages:
        print(f"  [{m['role']}] {m['content']}")
    print("--------------------------------")
    # 假装模型能从历史里找到名字
    for m in messages:
        if "我叫" in m["content"]:
            name = m["content"].replace("我叫", "").strip()
            return f"你叫 {name}"
    return "我不知道你的名字"


def chat(sm: SessionManager, sid: str, user_text: str) -> str:
    # 1. 把用户这句存进历史
    sm.append(sid, "user", user_text)
    # 2. 取出完整历史，发给模型
    reply = fake_llm(sm.get_messages(sid))
    # 3. 把模型回复也存进历史
    sm.append(sid, "assistant", reply)
    return reply


if __name__ == "__main__":
    sm = SessionManager()
    sid = sm.create()

    print("AI:", chat(sm, sid, "我叫 Tom"))
    print("AI:", chat(sm, sid, "我叫什么名字？"))
```

运行后你会看到，第二轮时模型收到的 messages 里包含了「我叫 Tom」，所以它能答对。

这就是「记忆」的全部秘密：**存 → 取全部 → 一起发 → 再存**。

---

### 1.8 内存存储的局限（今天先知道，后面解决）

今天的 SessionManager 把数据存在 Python 进程的内存里（那个 `dict`）。这有几个问题，你先有个印象：

| 局限 | 说明 | 后面怎么解决 |
|---|---|---|
| 进程重启就丢 | 服务一重启，内存清空，历史全没了 | 存 Redis / 数据库 |
| 多台服务器不共享 | A 机器存的，B 机器读不到 | 存 Redis 等共享存储 |
| 会一直涨 | 会话越来越多，内存会爆 | 加过期时间 TTL / LRU 淘汰 |
| 历史无限长 | 一直 append，最终超出模型上下文 | Day02 的窗口截断 + Day03 摘要 |

今天用内存 Map 是为了**先把概念跑通**，别一上来就被 Redis 之类的东西劝退。

---

## 2. 源码阅读

- `ai-lab/customer-agent/session.ts`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. 会话是用什么数据结构存的（是不是一个 Map / 对象）
2. sessionId 是怎么生成的
3. 有没有 `append` / `getMessages` 之类的方法
4. 消息对象长什么样（有没有 role / content）
5. 有没有做过期清理

建议在笔记里写出这样一张对照表：

| session.ts 里的东西 | 作用 | 我的 Python 对应 |
|---|---|---|
| `Map<string, Message[]>` | 会话仓库 | `self._sessions: dict` |
| `createSession()` | 建会话 | `create()` |
| `appendMessage()` | 追加消息 | `append()` |
| `getMessages()` | 取历史 | `get_messages()` |

---

## 3. 练习任务

### 练习 1：跑通最小 SessionManager

把 1.6 的代码敲一遍（不要复制粘贴，手敲），运行成功，看懂每个方法。

目标：能说出 `create` / `append` / `get_messages` 各自做了什么。

---

### 练习 2：设计并实现 sessionId 规则

要求：

1. 用 `uuid4()` 生成 id
2. 在 `create()` 里防止极小概率的 id 撞车（撞了就重新生成）

参考实现：

```python
def create(self) -> str:
    while True:
        session_id = str(uuid.uuid4())
        if session_id not in self._sessions:
            self._sessions[session_id] = []
            return session_id
```

目标：理解为什么 id 要唯一、不可猜。

---

### 练习 3：跑通「有记忆」的假对话

把 1.7 的 `fake_llm` + `chat` 跑通，亲眼确认第二轮时历史被一起发了过去。

目标：能用一句话解释「LLM 记忆是怎么实现的」。

---

### 练习 4：多会话隔离测试

同一个 SessionManager 建两个会话，往里各存不同内容，验证它们**互不干扰**：

```python
sm = SessionManager()
a = sm.create()
b = sm.create()

sm.append(a, "user", "我是会话A")
sm.append(b, "user", "我是会话B")

assert sm.get_messages(a)[0]["content"] == "我是会话A"
assert sm.get_messages(b)[0]["content"] == "我是会话B"
print("隔离测试通过")
```

目标：理解 sessionId 如何做到会话隔离。

---

### 练习 5：列 SessionManager 与 Web Session 差异 5 条

| # | AI SessionManager | 传统 Web Session | 差异 |
|---|---|---|---|
| 1 | 存 messages 数组 | 存登录态/购物车等 | 存的东西不同 |
| 2 | 目的是给 LLM 拼上下文 | 目的是认用户身份 | 用途不同 |
| 3 | 一个 sessionId 一段对话 | 一个 sessionId 一个登录会话 | 粒度类似 |
| 4 | 内容会无限增长 | 内容相对固定 | AI 侧要额外做截断/摘要 |
| 5 | 常存 Redis | 也常存 Redis | 存储选型类似 |

---

## 4. JS/Node.js 类比

| Python / AI 后端 | Node.js 类比 | 说明 |
|---|---|---|
| `SessionManager` | `express-session` 的 store | 都是「按 id 存会话」 |
| `self._sessions: dict` | `new Map()` / `MemoryStore` | 内存里的会话仓库 |
| `sessionId` | `req.sessionID` | 会话身份标识 |
| `create()` | 首次请求时自动建 session | 新建会话 |
| `append()` | `req.session.messages.push()` | 往会话塞数据 |
| `get_messages()` | 读 `req.session.messages` | 取会话数据 |
| 内存存储会丢 | `MemoryStore` 生产不推荐 | 都建议换 Redis |

一句话类比：

> AI 里的 SessionManager，就像 Node 后端里的 `express-session` + `Map` store，只不过存的不是登录态，而是一整段对话历史。

---

## 5. AI Review 提问

完成练习后，把你的 SessionManager 代码贴给 AI，然后问：

```text
我正在学习 Week 19 Day 01：SessionManager 设计。

请你按资深后端工程师标准帮我检查：

1. 我用 dict/Map 存会话的设计合理吗？
2. sessionId 用 uuid4 是否足够？有没有安全隐患？
3. append / get_messages 的接口设计有没有问题？
4. 我用 express-session 做的类比准确吗？
5. 如果要上生产（多用户、多机器），我这个设计要改哪些地方？

请用中文输出：合理的地方、有问题的地方、修改建议、下一步练习。
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [✅] 能运行的 `session_manager.py`
- [✅] 带 sessionId 去重的 `create()`
- [✅] 跑通的「假 LLM 有记忆」demo
- [✅] 多会话隔离测试
- [✅] SessionManager 与 Web Session 差异 5 条
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [✅] 能解释「为什么 LLM 没有记忆」
- [✅] 能说出一次 LLM 请求里 messages 的结构
- [✅] 能解释什么是会话 / sessionId
- [✅] 能存会话（append 成功）
- [✅] 能取会话（get_messages 成功）
- [✅] 能做到多会话隔离
- [✅] 能用一句话解释「多轮记忆是后端拼出来的」
- [✅] 能用 express-session 做类比

---

## 8. 今日自测题

### 8.1 为什么 LLM 会「失忆」？

参考答案：

> ✅ 因为大模型每次 API 调用都是独立无状态的，它不保存上一次请求的内容。要有记忆，必须由后端自己把历史消息一起发过去。

---

### 8.2 一次对话请求里 messages 有哪几种 role？

参考答案：

> ✅ 常见三种：`system`（系统设定）、`user`（用户说的）、`assistant`（模型之前的回复）。

---

### 8.3 为什么 sessionId 要用 uuid4 而不是自增数字？

参考答案：

> ✅ 自增数字容易被猜到（别人 +1 就能访问别人的会话），也容易在并发下撞。uuid4 随机且唯一，更安全。

---

### 8.4 「用 Map 存会话」是什么意思？

参考答案：

> ✅ 用一个字典/Map，key 是 sessionId，value 是这个会话的 messages 列表。拿 id 就能取到对应的对话历史。

---

### 8.5 内存 Map 存会话有什么局限？

参考答案：

> ✅ 进程重启会丢、多台服务器不共享、内存会一直涨、历史会无限长。生产上要换 Redis 并做过期与截断。

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
我正在进行 Week 19 Day 01：SessionManager 设计 的学习。
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
