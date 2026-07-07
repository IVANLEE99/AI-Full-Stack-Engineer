# Week 16 Day 01：LangGraph 入门

> 所属周：Week 16：编排模式对比  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`pay-service + ai-lab`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

用 LangGraph 实现一个 3 步 Agent 工作流，理解 State（状态）、Node（节点）、Edge（边）三个核心概念。

今天你要真正掌握这一句话：

> LangGraph 把一个 AI 流程画成一张「有向图」：每个 Node 是一个处理步骤，Edge 决定下一步走哪里，而所有 Node 都读写同一份 State。这跟 PHP 支付里的 Node 链（一个 Context 顺着一串 Node 往下传）本质是同一件事——都是「流程编排」，只是一个用「图」描述，一个用「链」描述。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先搞清楚「什么是工作流编排」这个大概念
2. 理解「状态机 / 有向图」到底在说什么
3. 认识 LangGraph 的三大件：State、Node、Edge
4. 看懂一个最小的 LangGraph 图怎么跑起来
5. 亲手写一个 3 步 Agent 工作流（可运行）
6. 理解 State 是怎么在 Node 之间传递和累积的
7. 加一个条件边（conditional edge），体会「分支」
8. 用 Redux / Node.js middleware 做类比
9. 对照 PHP 支付 Node 链，先建立「两种编排是一回事」的直觉
10. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 先搞懂：什么是「工作流编排」

先别碰 LangGraph，先想一个生活例子。

你去餐厅点一份套餐，后厨的流程是：

```text
接单 → 备菜 → 炒菜 → 装盘 → 出餐
```

这一串「先做什么、后做什么、什么条件下跳过某一步」的安排，就叫**编排（Orchestration）**。

在后端世界里，一个复杂业务（比如「支付」「售后退款」「AI 回答问题」）从来不是一行代码搞定的，而是一连串步骤：

```text
校验参数 → 查库存 → 锁定订单 → 调用支付渠道 → 更新订单状态 → 发通知
```

**编排要解决的问题**就是：

- 这些步骤按什么顺序执行？
- 步骤之间怎么传数据？
- 某一步失败了怎么办？
- 什么情况下要分支（比如「VIP 用户走快速通道」）？

小白重点：

> 「编排」不是一个新框架，而是一种**组织多步骤流程**的思路。LangGraph 和 PHP 的 NodeExecutionEngine 都是编排工具，只是长得不一样。

---

### 1.2 两种描述流程的方式：链 vs 图

编排有两种常见的「画法」。

**画法一：链式（Chain / Pipeline）**

步骤一个接一个，像流水线：

```text
[Node A] → [Node B] → [Node C] → 结束
```

PHP 支付里的 Node 链就是这种。数据（Context）从头顺着传到尾。

**画法二：图式（Graph）**

步骤是图上的节点，节点之间用「边」连接，边上可以带条件：

```text
        ┌──────────┐
START → │  Node A  │
        └────┬─────┘
             │
        ┌────▼─────┐      条件为真
        │  Node B  │──────────────► Node C ──► END
        └────┬─────┘
             │ 条件为假
             ▼
          Node D ──► END
```

LangGraph 用的就是这种。

| 对比项 | 链式（PHP Node 链） | 图式（LangGraph） |
|---|---|---|
| 结构 | 一条直线 | 一张有向图 |
| 分支 | 靠 if/return 在节点内部实现 | 靠「条件边」显式表达 |
| 回头 / 循环 | 很难，通常不支持 | 天然支持（可以画一条边指回去） |
| 可视化 | 脑补顺序 | 能直接画成图 |
| 典型场景 | 支付、售后等固定流程 | AI Agent、需要反复决策的流程 |

小白重点：

> 链是「图」的一种特殊情况——一条没有分叉的直线。所以理解了图，就自然理解了链。

---

### 1.3 什么是 State（状态）

State 就是「这个流程从头到尾共享的一份数据」。

想象后厨有一块白板，上面写着：

```text
订单号：1001
菜品：宫保鸡丁
备菜完成：是
炒菜完成：否
```

每个厨师（Node）经过时，都能读白板、改白板。这块白板就是 State。

在 LangGraph 里，State 通常是一个字典（Python 的 dict / TypedDict）：

```python
from typing import TypedDict

class AgentState(TypedDict):
    question: str      # 用户的问题
    keywords: list     # 提取出的关键词
    answer: str        # 最终答案
```

小白重点：

> State 是所有 Node 的「共享内存」。Node 不直接互相调用，而是通过「读写同一份 State」来传递信息。这一点和 PHP Node 链里的 `$context` 一模一样。

对照 PHP 支付里的 Context：

```php
// PHP 里，Context 就是那份共享数据
$context = [
    'order_id' => 1001,
    'amount'   => 99.00,
    'paid'     => false,
];
```

`AgentState`（Python）≈ `$context`（PHP）≈ 后厨白板。

---

### 1.4 什么是 Node（节点）

Node 就是「一个处理步骤」，本质是一个函数。

它的工作模式永远是：

```text
输入：当前 State
输出：要更新到 State 的部分
```

一个最简单的 Node：

```python
def extract_keywords(state: AgentState) -> dict:
    # 读 state 里的 question
    question = state["question"]
    # 做一点处理
    keywords = question.split()
    # 返回要更新的字段（LangGraph 会自动合并进 State）
    return {"keywords": keywords}
```

小白重点（非常重要）：

> Node 函数**不需要**自己去改整个 State，只需要 `return` 一个「补丁」（要更新的字段），LangGraph 会自动把它合并到总 State 里。

这和 PHP Node 的写法对照：

```php
// PHP Node：直接改 context，然后交给下一个 node
class ExtractKeywordsNode
{
    public function handle(array $context, callable $next): array
    {
        $context['keywords'] = explode(' ', $context['question']);
        return $next($context); // 手动往下传
    }
}
```

| 对比项 | LangGraph Node（Python） | PHP Node |
|---|---|---|
| 本质 | 一个函数 | 一个类的 `handle` 方法 |
| 读数据 | 读参数 `state` | 读参数 `$context` |
| 写数据 | `return` 一个补丁 dict | 直接改 `$context` |
| 谁决定下一步 | 图的 Edge 决定 | 节点内部调 `$next()` |

---

### 1.5 什么是 Edge（边）

Edge 决定「这个 Node 执行完，接下来去哪个 Node」。

有两种边：

**1. 普通边（固定跳转）**

```python
# 执行完 A，一定去 B
graph.add_edge("node_a", "node_b")
```

**2. 条件边（按 State 决定去哪）**

```python
def route(state: AgentState) -> str:
    # 根据 state 返回下一个节点的名字
    if len(state["keywords"]) > 3:
        return "long_answer"
    else:
        return "short_answer"

graph.add_conditional_edges("extract_keywords", route)
```

小白重点：

> 条件边就是把 PHP 里写在节点内部的 `if (...) return ...;` 拿出来，变成图上一条**看得见的分支**。这是图式编排比链式编排最直观的优势。

还有两个特殊节点：

- `START`：图的入口
- `END`：图的出口

```python
from langgraph.graph import START, END

graph.add_edge(START, "extract_keywords")  # 从入口进第一个节点
graph.add_edge("build_answer", END)         # 最后一个节点连到出口
```

---

### 1.6 组装一个最小 LangGraph 图（3 步工作流）

现在把 State + Node + Edge 拼起来，做一个 3 步 Agent：

```text
START → 提取关键词 → 检索资料 → 生成答案 → END
```

先安装（在你的 `ai-lab` 环境里）：

```bash
pip install langgraph
```

完整可运行代码 `agent_demo.py`：

```python
from typing import TypedDict, List
from langgraph.graph import StateGraph, START, END


# 1) 定义 State：整个流程共享的数据
class AgentState(TypedDict):
    question: str        # 输入：用户问题
    keywords: List[str]  # 中间产物：关键词
    context: str         # 中间产物：检索到的资料
    answer: str          # 输出：最终答案


# 2) 定义 3 个 Node（每个都是一个函数）

def extract_keywords(state: AgentState) -> dict:
    print("步骤1：提取关键词")
    keywords = state["question"].replace("？", "").split()
    return {"keywords": keywords}


def retrieve_context(state: AgentState) -> dict:
    print("步骤2：检索资料")
    # 这里用假数据模拟检索
    fake_db = {
        "退款": "退款一般 3-7 个工作日到账。",
        "发货": "付款后 48 小时内发货。",
    }
    hit = ""
    for kw in state["keywords"]:
        if kw in fake_db:
            hit = fake_db[kw]
            break
    return {"context": hit or "未找到相关资料。"}


def build_answer(state: AgentState) -> dict:
    print("步骤3：生成答案")
    answer = f"根据资料：{state['context']}（关键词：{state['keywords']}）"
    return {"answer": answer}


# 3) 组装图
builder = StateGraph(AgentState)

builder.add_node("extract_keywords", extract_keywords)
builder.add_node("retrieve_context", retrieve_context)
builder.add_node("build_answer", build_answer)

builder.add_edge(START, "extract_keywords")
builder.add_edge("extract_keywords", "retrieve_context")
builder.add_edge("retrieve_context", "build_answer")
builder.add_edge("build_answer", END)

graph = builder.compile()


# 4) 运行
if __name__ == "__main__":
    result = graph.invoke({"question": "退款 需要 多久"})
    print("最终 State：", result)
    print("答案：", result["answer"])
```

运行：

```bash
python agent_demo.py
```

你应该看到类似输出：

```text
步骤1：提取关键词
步骤2：检索资料
步骤3：生成答案
最终 State： {'question': '退款 需要 多久', 'keywords': ['退款', '需要', '多久'], 'context': '退款一般 3-7 个工作日到账。', 'answer': '根据资料：退款一般 3-7 个工作日到账。（关键词：...）'}
答案： 根据资料：退款一般 3-7 个工作日到账。（关键词：['退款', '需要', '多久']）
```

小白重点：

> 注意：我们从没手动调用过 `retrieve_context(state)`。我们只是把节点和边「登记」到图里，`graph.invoke()` 会按图自动跑完所有步骤。这就是「编排」——你只描述流程结构，执行由引擎负责。

---

### 1.7 观察 State 是怎么「累积」的

上面的例子里，每个 Node 只返回自己那部分字段：

```python
return {"keywords": keywords}     # 只返回 keywords
return {"context": hit}           # 只返回 context
return {"answer": answer}         # 只返回 answer
```

但最终 State 里 `question / keywords / context / answer` 全都有。

这是因为 LangGraph 默认会**合并（merge）**每个 Node 的返回值到总 State：

```text
初始 State：{question}
经过 Node1： {question, keywords}
经过 Node2： {question, keywords, context}
经过 Node3： {question, keywords, context, answer}
```

小白重点：

> State 像滚雪球一样越滚越大，每个 Node 往上加自己的产物。这跟 PHP Node 链里 `$context['xxx'] = ...` 一路往里塞字段是完全一样的效果。

---

### 1.8 加一个条件边：体会「分支」

把流程改成：提取关键词后，如果关键词太少，就直接返回「问题太模糊」，否则继续检索。

```python
from typing import TypedDict, List
from langgraph.graph import StateGraph, START, END


class AgentState(TypedDict):
    question: str
    keywords: List[str]
    answer: str


def extract_keywords(state: AgentState) -> dict:
    keywords = state["question"].split()
    return {"keywords": keywords}


def ask_more(state: AgentState) -> dict:
    return {"answer": "你的问题太模糊了，请补充更多信息。"}


def build_answer(state: AgentState) -> dict:
    return {"answer": f"已收到关键词：{state['keywords']}"}


# 条件路由函数：返回下一个节点的名字
def route_by_keywords(state: AgentState) -> str:
    if len(state["keywords"]) < 2:
        return "ask_more"
    return "build_answer"


builder = StateGraph(AgentState)
builder.add_node("extract_keywords", extract_keywords)
builder.add_node("ask_more", ask_more)
builder.add_node("build_answer", build_answer)

builder.add_edge(START, "extract_keywords")

# 关键：条件边
builder.add_conditional_edges(
    "extract_keywords",
    route_by_keywords,
    {
        "ask_more": "ask_more",
        "build_answer": "build_answer",
    },
)

builder.add_edge("ask_more", END)
builder.add_edge("build_answer", END)

graph = builder.compile()

print(graph.invoke({"question": "退款"}))          # 只有 1 个词 → ask_more
print(graph.invoke({"question": "退款 要 多久"}))  # 3 个词 → build_answer
```

对照 PHP：这个 `route_by_keywords` 在链式写法里，其实就是藏在某个 Node 内部的一句 `if`：

```php
// PHP 链式里，分支逻辑通常写死在节点内部
public function handle(array $context, callable $next): array
{
    if (count($context['keywords']) < 2) {
        $context['answer'] = '问题太模糊';
        return $context; // 提前结束，不再往下
    }
    return $next($context);
}
```

| 对比项 | LangGraph 条件边 | PHP 节点内 if |
|---|---|---|
| 分支在哪 | 图上一条独立的边 | 藏在节点代码里 |
| 能否画出来 | 能，一眼看到分叉 | 不能，要读代码才知道 |
| 改流程 | 改图的连线 | 改节点内部逻辑 |

小白重点：

> 图式编排的核心价值：**把流程结构和业务逻辑分开**。流程长什么样看图就行，具体每步做什么看 Node 函数。

---

### 1.9 小结：LangGraph 三大件对照 PHP

| LangGraph（Python） | PHP Node 链 | 通俗理解 |
|---|---|---|
| State（TypedDict） | `$context`（array） | 全程共享的白板 |
| Node（函数） | Node 类的 `handle` | 一个处理步骤 |
| Edge（边） | `$next()` + 内部 if | 决定下一步去哪 |
| `StateGraph.compile()` | NodeExecutionEngine | 把步骤组装成可执行流程 |
| `graph.invoke(input)` | `engine->run($context)` | 启动整个流程 |

一句话总结今天：

> LangGraph 用「图 + 共享 State」来编排 AI 流程；PHP 用「链 + 共享 Context」来编排业务流程。名字不同，骨架一样。

---

## 2. 源码阅读

本日无指定源码阅读（这是概念入门日）。

但请你**预习** Day 02 要读的文件，先混个脸熟：

- `pay-service/common/services/pay/PayService.php`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

预习时只需要带着一个问题去扫一眼：

> 这个文件里，是不是也有一个「一份数据顺着多个步骤往下传」的结构？

明天我们会正式拆它。

---

## 3. 练习任务

### 练习 1：跑通 3 步工作流

把 1.6 的 `agent_demo.py` 完整敲一遍（不要复制粘贴，手敲能加深理解），运行成功，看到 3 个「步骤 X」依次打印。

目标：确认你的环境能跑 LangGraph，并理解「登记节点 → invoke → 自动执行」的流程。

---

### 练习 2：改 State，加一个字段

在 `AgentState` 里加一个字段 `lang`（语言），并加一个新 Node `detect_lang`，放在第一步：

```python
def detect_lang(state: AgentState) -> dict:
    # 简单判断：含中文就是 zh
    is_zh = any('一' <= c <= '鿿' for c in state["question"])
    return {"lang": "zh" if is_zh else "en"}
```

把它接到 `START` 后面、`extract_keywords` 前面。

目标：体会「加一个步骤 = 加一个 Node + 改两条边」。

---

### 练习 3：加条件边

把 1.8 的条件边例子跑通，分别输入 1 个词和 3 个词，观察走了不同分支。

目标：能说清楚「条件边和节点内 if 的区别」。

---

### 练习 4：画出你的图

用文字（ASCII）或纸笔，把练习 2 之后的工作流画成一张图，标出：

- START / END
- 每个 Node 的名字
- 每条边（哪个到哪个）

参考格式：

```text
START → detect_lang → extract_keywords → retrieve_context → build_answer → END
```

目标：养成「先画图再写代码」的编排思维。

---

### 练习 5：列 State 变化表

针对练习 1 的运行，填一张「State 演变表」：

| 阶段 | State 里有哪些字段 |
|---|---|
| 初始输入 | question |
| extract_keywords 后 | question, keywords |
| retrieve_context 后 | question, keywords, context |
| build_answer 后 | question, keywords, context, answer |

目标：亲眼确认 State 是「累积」的。

---

## 4. JS/Node.js 类比

LangGraph 的很多概念在前端 / Node 世界都有影子。

### 4.1 State ≈ Redux store

Redux 里有一个全局 store，reducer 接收 `(state, action)` 返回新 state：

```js
// Redux reducer
function reducer(state, action) {
  switch (action.type) {
    case "SET_KEYWORDS":
      return { ...state, keywords: action.payload };
    default:
      return state;
  }
}
```

LangGraph Node 做的事几乎一样：读旧 state，返回要更新的部分：

```python
def extract_keywords(state):
    return {"keywords": state["question"].split()}
```

| Redux | LangGraph |
|---|---|
| store | State |
| reducer | Node 函数 |
| `{...state, ...}` 合并 | 自动 merge 返回值 |
| action 触发 | Edge 触发下一个 Node |

### 4.2 Node 链 ≈ Express/Koa middleware

Node.js 的 Express 中间件也是「一份 req/res 顺着一串处理器往下传」：

```js
app.use((req, res, next) => {
  req.userId = 123; // 改共享数据
  next();            // 交给下一个
});
```

这和 PHP Node 链的 `handle($context, $next)` 是同一个模式（都是责任链）。

| Express middleware | PHP Node 链 | LangGraph |
|---|---|---|
| `req` | `$context` | State |
| `(req,res,next)` | `handle($ctx,$next)` | Node 函数 |
| `next()` | `$next($ctx)` | Edge |
| 链式，无内建分支图 | 链式 | 图式，有条件边 |

### 4.3 本周类比打卡

```text
本周概念：LangGraph 工作流编排
Node 等价：Express/Koa middleware（链）+ Redux（状态）
差异：LangGraph 是「图」，能画分支和循环；middleware 是「直链」
我能用自己的话解释吗：是 / 否
理解自评：1 / 2 / 3 / 4 / 5
```

---

## 5. AI Review 提问

完成练习后，把你的代码和理解贴给 AI，然后问：

```text
我正在学习 Week 16 Day 01：LangGraph 入门（State/Node/Edge）。

请你按资深工程师标准帮我检查：

1. 我对 State / Node / Edge 三个概念的理解是否正确？
2. 我的 3 步工作流代码结构是否合理？Node 单一职责做到了吗？
3. 我加的条件边写法对不对？路由函数返回值和 add_conditional_edges 的映射是否匹配？
4. 我用 Redux / Express middleware 做的类比有没有误导？
5. 对比 PHP 的 Node 链（Context 传递），我的理解准确吗？
6. 如果这是真实 AI 项目，我还需要注意哪些点（错误处理、超时、State 过大）？

请用中文输出：
- 我理解正确的地方
- 我理解错误或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [ ] 可运行的 `agent_demo.py`（3 步工作流）
- [ ] 加了 `detect_lang` 节点的改进版
- [ ] 条件边 demo（能走两个分支）
- [ ] 工作流的 ASCII 图
- [ ] State 演变表
- [ ] LangGraph 三大件 ↔ PHP Node 链 对照笔记
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说清楚「工作流编排」是什么
- [ ] 能区分「链式」和「图式」两种编排
- [ ] 能解释 State / Node / Edge 各是什么
- [ ] 3 步工作流能运行并打印 3 个步骤
- [ ] 能解释「Node 只返回补丁，State 自动合并」
- [ ] 能写出并跑通一个条件边
- [ ] 能用 Redux / middleware 做类比
- [ ] 能说出 LangGraph State ↔ PHP `$context` 的对应关系

---

## 8. 今日自测题

### 8.1 State、Node、Edge 分别是什么？

参考答案：

> ✅ State 是整个流程共享的一份数据（像后厨白板）；Node 是一个处理步骤（一个函数，读 State、返回要更新的字段）；Edge 是连接 Node 的边，决定执行完当前 Node 后去哪个 Node。

---

### 8.2 LangGraph Node 需要自己修改整个 State 吗？

参考答案：

> ✅ 不需要。Node 只需要 `return` 一个包含「要更新字段」的字典，LangGraph 会自动把它合并（merge）到总 State 里。

---

### 8.3 「条件边」和「在节点里写 if」有什么区别？

参考答案：

> ✅ 条件边把分支逻辑变成图上一条看得见的边，改流程只需改连线；节点内 if 把分支藏在代码里，要读代码才知道流程会分叉。图式编排的价值就是把「流程结构」和「业务逻辑」分开。

---

### 8.4 LangGraph 的 State 和 PHP Node 链的 `$context` 是什么关系？

参考答案：

> ✅ 本质相同，都是「整个流程共享、逐步累积的数据容器」。LangGraph 里 Node 返回补丁自动合并，PHP 里节点直接改 `$context` 往下传，效果一样。

---

### 8.5 链式编排是图式编排的特例吗？

参考答案：

> ✅ 是。链式就是一张「没有分叉、没有循环」的有向图——每个节点只有一条出边指向下一个节点。理解了图，链自然就懂了。

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
我正在进行 Week 16 Day 01：LangGraph 入门 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 16 README](./README.md)
