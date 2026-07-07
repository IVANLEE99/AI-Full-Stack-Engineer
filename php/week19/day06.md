# Week 19 Day 06：多轮 Demo 交付

> 所属周：Week 19：Memory + Session  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/customer-agent`  
> 类型：项目实战  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

把前五天的 SessionManager、上下文窗口、摘要压缩整合成一个**能对外演示**的多轮对话 Demo：跑 5 轮连贯对话，第 5 轮能引用第 1 轮的信息，并留下录屏/截图证据。

今天你要真正掌握这一句话：

> 「Demo 可演示」不等于「代码能跑」——它要能被别人在你不在场时看懂：有清晰的输入输出、有连贯的对话、有能证明记忆生效的那一刻（第 5 轮引用第 1 轮）。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 明确「可演示」的验收标准
2. 把三件套整合进一个 Agent 类
3. 设计一段有「记忆钩子」的演示脚本
4. 让 Demo 打印清晰的对话过程
5. 加一个「记忆命中」高亮，证明记忆生效
6. 跑通并录屏/截图
7. 写一段演示旁白（怎么讲）
8. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 什么叫「可演示」

一个合格 Demo 的验收标准：

| 标准 | 说明 |
|---|---|
| 能一键跑 | 一条命令就能启动 |
| 输入清晰 | 看得出用户说了什么 |
| 输出清晰 | 看得出 AI 回了什么 |
| 有记忆证据 | 第 5 轮明确用到第 1 轮信息 |
| 有旁白 | 你能一句句讲清在演示什么 |

小白重点：

> Demo 不是给你自己看的，是给别人看的。**别人看不懂的 Demo 等于没做。**

---

### 1.2 整合三件套：MemoryAgent

把前几天的能力封装成一个类：

```python
class MemoryAgent:
    def __init__(self, window=6, summarize_threshold=20):
        self.messages = []          # 完整历史
        self.summary = ""           # 已压缩摘要
        self.window = window
        self.threshold = summarize_threshold

    def _append(self, role, content):
        self.messages.append({"role": role, "content": content})

    def _maybe_summarize(self):
        # 历史太长时压缩（此处用假的摘要，真实项目调 LLM）
        if len(self.messages) > self.threshold:
            old = self.messages[:-self.window]
            self.summary = fake_summarize(self.summary, old)
            self.messages = self.messages[-self.window:]

    def _build_context(self, system):
        ctx = [{"role": "system", "content": system}]
        if self.summary:
            ctx.append({
                "role": "system",
                "content": f"[历史摘要] {self.summary}"
            })
        ctx += self.messages[-self.window:]
        return ctx

    def chat(self, user_input, system="你是客服助手"):
        self._append("user", user_input)
        self._maybe_summarize()
        context = self._build_context(system)
        reply = call_llm(context)     # 真实项目：调模型
        self._append("assistant", reply)
        return reply, context
```

一句话：

> MemoryAgent = SessionManager（存）+ 窗口（截）+ 摘要（压）三件套的组装体。

---

### 1.3 假的 LLM 与假摘要（离线可演示）

学习期没必要真调 API，用「假模型」也能演示记忆逻辑：

```python
USER_INFO = {}

def call_llm(context):
    # 假模型：从上下文里"记住"用户名和订单号，后面能复述
    text = " ".join(m["content"] for m in context)
    if "我叫" in text:
        name = text.split("我叫")[1][:3]
        USER_INFO["name"] = name
    if "订单" in text and "查" in text:
        return f"好的{USER_INFO.get('name','')}，正在为你查询订单状态。"
    if "刚才" in text or "我叫什么" in text:
        return f"你刚才说你叫 {USER_INFO.get('name','（未提供）')}。"
    return "收到，请问还有什么可以帮你？"

def fake_summarize(old_summary, messages):
    return old_summary + " " + f"（压缩了{len(messages)}条历史）"
```

小白重点：

> 演示的重点是「记忆链路」，不是模型多聪明。假模型只要能**证明上下文被正确带上**就够了。

---

### 1.4 设计带「记忆钩子」的演示脚本

关键：第 1 轮埋信息，第 5 轮取回。

```python
agent = MemoryAgent()

script = [
    "你好，我叫小明",              # 第1轮：埋下名字
    "我想咨询一下退货政策",         # 第2轮
    "大概几天能到账",              # 第3轮
    "另外帮我查一下订单",           # 第4轮
    "对了，我刚才说我叫什么来着？",   # 第5轮：取回第1轮信息
]

for i, msg in enumerate(script, 1):
    reply, ctx = agent.chat(msg)
    print(f"\n===== 第 {i} 轮 =====")
    print(f"用户: {msg}")
    print(f"AI  : {reply}")
```

第 5 轮 AI 应该回「你刚才说你叫 小明」——这就是记忆生效的证据。

---

### 1.5 高亮「记忆命中」瞬间

给演示加一个证据高亮，让观众一眼看到记忆生效：

```python
for i, msg in enumerate(script, 1):
    reply, ctx = agent.chat(msg)
    print(f"\n===== 第 {i} 轮 =====")
    print(f"用户: {msg}")
    print(f"AI  : {reply}")
    if i == 5 and "小明" in reply:
        print(">>> [记忆命中] 第5轮成功引用了第1轮的名字！<<<")
```

对比：没有记忆的模型会怎样？

| 场景 | 第 5 轮回答 |
|---|---|
| 无记忆（每次只发当前句） | 抱歉，我不知道你叫什么 |
| 有记忆（带上历史） | 你刚才说你叫小明 |

小白重点：

> 演示时一定要展示这个对比，观众才能理解「记忆」到底解决了什么。

---

### 1.6 留证据：录屏与截图

演示交付物：

```text
1. 终端跑一遍，录屏（10~30 秒）
2. 关键帧截图：第1轮埋信息 + 第5轮取回
3. 一段旁白文字（见 1.7）
4. 代码本身（demo.py）
```

命令示例：

```bash
python demo.py | tee demo_output.txt
```

`tee` 会把输出同时打印和存文件，方便截图和留档。

---

### 1.7 写演示旁白

一段能照着念的旁白：

```text
这是一个带记忆的客服 Agent Demo。
第1轮，用户说「我叫小明」——名字被存进会话历史。
第2到4轮，正常咨询退货、到账、订单。
第5轮，用户问「我刚才说我叫什么」——
注意看，AI 回答「你叫小明」，说明它带上了第1轮的历史。
如果去掉记忆模块，这里就会回答「不知道」。
这就是 Session + 上下文窗口 + 摘要三件套的效果。
```

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

可参考自己 Day01~05 的代码，检查整合后是否与设计文档一致。

---

## 3. 练习任务

### 练习 1：整合 MemoryAgent

按 1.2 把三件套封装成一个类，能 `chat()`。

目标：一个类跑通存、截、压。

---

### 练习 2：写演示脚本

按 1.4 设计 5 轮对话，第 1 轮埋信息、第 5 轮取回。

目标：脚本能证明记忆生效。

---

### 练习 3：加记忆命中高亮

按 1.5 在第 5 轮打印「记忆命中」提示，并做有/无记忆对比。

目标：让观众一眼看懂价值。

---

### 练习 4：录屏 + 截图

按 1.6 跑一遍并留证据（录屏或截图 + `demo_output.txt`）。

目标：产出可交付的演示材料。

---

### 练习 5：写旁白

按 1.7 写一段 60 秒能讲完的演示旁白。

目标：你能独立讲清这个 Demo。

---

## 4. JS/Node.js 类比

| Python / AI 后端 | Node.js 类比 | 说明 |
|---|---|---|
| MemoryAgent 整合 | 把中间件串成一条 pipeline | 各能力组装成完整流程 |
| 假的 call_llm | mock / stub | 用假实现聚焦主流程 |
| 演示脚本 | e2e 演示用例 | 模拟真实用户操作 |
| `tee` 留档 | 保存 e2e 运行日志 | 留证据 |
| 记忆命中高亮 | 断言 + 高亮日志 | 证明关键行为发生 |

一句话类比：

> 做 Demo 就像给 Node 项目写一个「跑给客户看的 e2e 场景」——mock 掉外部依赖，专心演示核心价值。

---

## 5. AI Review 提问

```text
我正在学习 Week 19 Day 06：多轮对话 Demo 交付。
这是我的 demo.py 和演示旁白：（粘贴）

请你按资深后端工程师标准帮我检查：

1. MemoryAgent 的三件套整合是否合理？
2. 演示脚本能否真正证明「记忆生效」？
3. 有/无记忆的对比是否清晰？
4. 作为可交付 Demo，还缺什么（README、一键运行）？
5. 从这个 Demo 到生产，最大的差距在哪？

请用中文输出：合理的地方、有问题的地方、修改建议、下一步。
```

---

## 6. 今日产出

- [✅] `MemoryAgent` 整合类
- [✅] 5 轮演示脚本（记忆钩子）
- [✅] 记忆命中高亮 + 有无记忆对比
- [✅] 录屏 / 截图 + `demo_output.txt`
- [✅] 60 秒演示旁白
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [✅] Demo 能一键跑通
- [✅] 5 轮对话连贯不断
- [✅] 第 5 轮成功引用第 1 轮信息
- [✅] 有记忆命中的可见证据
- [✅] 有有/无记忆的对比展示
- [✅] 留下了录屏或截图
- [✅] 能独立讲清这个 Demo

---

## 8. 今日自测题

### 8.1 「可演示」和「代码能跑」的区别？

参考答案：

> ✅ 能跑是自己知道结果；可演示是别人在你不在场时也能看懂输入、输出和价值。

---

### 8.2 MemoryAgent 整合了哪三件套？

参考答案：

> ✅ SessionManager（存历史）、上下文窗口（截断）、摘要压缩（压历史）。

---

### 8.3 演示脚本为什么要「第 1 轮埋、第 5 轮取」？

参考答案：

> ✅ 这样才能直观证明跨多轮的记忆确实生效，而不是只记住上一句。

---

### 8.4 为什么演示要展示「有无记忆对比」？

参考答案：

> ✅ 让观众理解记忆模块解决了什么问题——没有它，第 5 轮就答不上来。

---

### 8.5 学习期用假的 call_llm 演示合理吗？

参考答案：

> ✅ 合理。演示重点是记忆链路是否正确带上下文，不是模型多聪明；用 mock 更聚焦、可离线、可复现。

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
我正在进行 Week 19 Day 06：多轮 Demo 交付 的学习。
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
