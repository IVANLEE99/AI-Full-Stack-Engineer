# Week 16 Day 06：编排模式交付

> 所属周：Week 16：编排模式对比  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`pay-service + ai-lab`  
> 类型：项目实战  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

把 Day01 的 LangGraph Demo 从「能跑」升级到「能交付」：加错误处理、加条件分支、加日志，配上一份别人照着就能跑起来的 README。

今天你要真正掌握这一句话：

> 「能跑的代码」和「能交付的代码」差的不是功能，而是：别人能不能照着文档一次跑通、出错时能不能看懂、流程能不能被讲清楚。交付 = 可运行 + 可复现 + 可理解。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 明确「交付标准」到底包含哪些东西
2. 回顾 Day01 的 3 步 Demo，找出它「不够交付」的地方
3. 给每个 Node 加错误处理（别一崩全崩）
4. 加一个条件边，让流程有真正的分支
5. 加结构化日志，让流程可观测
6. 写一份合格的 README（依赖、安装、运行、预期输出）
7. 自己按 README 从零跑一遍，验证可复现
8. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 什么叫「可交付」

先明确标准。一个可交付的编排 Demo 至少满足：

| 标准 | 说明 | 反例 |
|------|------|------|
| 可运行 | clone 下来能跑 | 少装了依赖就崩 |
| 可复现 | 别人照 README 一次跑通 | 只有你机器能跑 |
| 可理解 | 有注释、有流程图 | 一堆没注释的函数 |
| 可观测 | 出错能看懂日志 | 崩了只有一行报错 |
| 有边界 | 处理异常输入 | 输入一变就崩 |

小白重点：

> 交付不是「写更多功能」，而是「让别人不用问你就能用」。写文档、加日志、处理错误，都是为了这个。

---

### 1.2 回顾 Day01 Demo 的不足

Day01 的 3 步 Demo（伪代码结构）：

```python
def understand(state): ...   # 步骤1：理解问题
def search(state): ...       # 步骤2：检索信息
def answer(state): ...       # 步骤3：生成回答
```

它「能跑」，但离交付还差：

1. 没有错误处理——某个 Node 抛异常，整个图崩掉。
2. 没有分支——不管什么问题都走同样三步（真实场景应「不需要检索的直接回答」）。
3. 没有日志——不知道每步 State 变成了什么。
4. 没有文档——别人不知道怎么装、怎么跑。

今天逐个补上。

---

### 1.3 升级点一：给 Node 加错误处理

核心思路：**Node 内部 try/except，把错误写进 State，而不是直接抛出**。这样流程能继续走到「兜底回答」，而不是整个崩掉。

```python
def search(state: dict) -> dict:
    """步骤2：检索信息。出错时不崩，把错误记进 state。"""
    try:
        question = state["question"]
        # 这里假装调用检索服务
        docs = fake_search(question)
        state["docs"] = docs
        state["search_ok"] = True
    except Exception as e:
        state["docs"] = []
        state["search_ok"] = False
        state["error"] = f"检索失败: {e}"
    return state
```

对比 PHP Node 链里的做法——通常也是把错误写进 Context，让后续节点判断：

```php
// 伪代码：PHP Node 内部
public function handle(Context $ctx): void
{
    try {
        $ctx->docs = $this->search->run($ctx->question);
        $ctx->searchOk = true;
    } catch (\Throwable $e) {
        $ctx->docs = [];
        $ctx->searchOk = false;
        $ctx->error = '检索失败: ' . $e->getMessage();
    }
}
```

小白重点：

> 两边思路一模一样——**错误不往外抛，而是写进共享状态**，让流程有机会走到兜底逻辑。这是编排里非常重要的一个模式。

---

### 1.4 升级点二：加条件边（真正的分支）

让流程根据「问题是否需要检索」走不同路径。

```python
def route_after_understand(state: dict) -> str:
    """条件边：决定 understand 之后走哪条路。"""
    if state.get("need_search"):
        return "search"      # 需要检索 → 去检索
    else:
        return "answer"      # 简单问题 → 直接回答
```

图的连接（伪代码）：

```python
graph.add_node("understand", understand)
graph.add_node("search", search)
graph.add_node("answer", answer)

graph.set_entry_point("understand")

# 关键：条件边
graph.add_conditional_edges(
    "understand",
    route_after_understand,
    {"search": "search", "answer": "answer"},
)

graph.add_edge("search", "answer")
graph.set_finish_point("answer")
```

流程图：

```text
        understand
          │
   need_search?
     │        │
    是        否
     ▼        │
  search      │
     │        │
     ▼        ▼
        answer
          │
          ▼
        结束
```

对比：PHP 链式做同样的分支，只能在节点内写 if——没有「条件边」这种一等公民。这正是 Day03/Day05 反复强调的核心差异。

---

### 1.5 升级点三：加结构化日志

让每一步都能被观测。

```python
import logging

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("agent")

def understand(state: dict) -> dict:
    logger.info("进入 understand，输入问题: %s", state.get("question"))
    state["need_search"] = "价格" in state["question"]
    logger.info("understand 完成，need_search=%s", state["need_search"])
    return state
```

小白重点：

> 日志要记「进入了哪个节点、State 变成了什么」。出问题时，你能顺着日志一眼看出流程卡在哪一步、State 哪里不对。这在 PHP 里对应给每个 Node 打日志。

---

### 1.6 升级点四：写一份合格的 README

README 是交付的门面。至少包含：

```markdown
# LangGraph 3 步 Agent Demo

## 功能
一个 3 步 AI 工作流：理解问题 → （按需）检索 → 生成回答。
演示 State / Node / 条件边 / 错误处理 / 日志。

## 依赖
- Python 3.10+
- langgraph（版本见 requirements.txt）

## 安装
```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
```

## 运行
```bash
python main.py
```

## 预期输出
```text
INFO:agent:进入 understand，输入问题: 这个套餐多少钱
INFO:agent:understand 完成，need_search=True
...
最终回答: ...
```

## 流程图
（贴上 1.4 的流程图）
```

小白重点：

> README 的黄金标准是：**一个从没见过这个项目的人，照着从上到下做，能一次跑通**。写完后一定要自己按它从零跑一遍验证。

---

### 1.7 依赖锁定：可复现的关键

`requirements.txt` 里写**具体版本**，不要用 `langgraph`（不锁版本），要用 `langgraph==x.y.z`。

```text
# 反例（别人装到的版本可能和你不一样，可能跑不起来）
langgraph

# 正例（锁定版本，保证可复现）
langgraph==0.2.0
```

对比 PHP：`composer.json` 里用 `"package": "1.2.3"` 或 `composer.lock` 锁版本，思路完全一致。

小白重点：

> 「在我机器上能跑」最常见的原因就是版本没锁。锁版本 = 可复现的第一步。

---

## 2. 源码阅读

本日无指定源码阅读，重点完善 Demo 与文档。

参考对象仍是本周两条 PHP 链，看看正式项目里节点是怎么打日志、怎么处理错误的：

- `pay-service/common/services/pay/PayService.php`
- `aftersale-service/common/services/nodes/`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

---

## 3. 练习任务

### 练习 1：给 3 个 Node 都加错误处理

把 Day01 的 understand / search / answer 三个 Node 都改成「try/except + 错误写进 state」的写法。

验收：故意让 search 抛异常，流程仍能走到 answer 并给出兜底回答，不整体崩溃。

---

### 练习 2：加一个条件边

实现 1.4 的 `route_after_understand`，让「不需要检索的问题」跳过 search 直接回答。

验收：输入「你好」走 understand → answer；输入「这个套餐多少钱」走 understand → search → answer。

---

### 练习 3：加结构化日志

给每个 Node 加进入/退出日志。运行后，光看日志就能复述整个流程走了哪几步、State 怎么变。

---

### 练习 4：写并验证 README

按 1.6 写一份 README，然后**换一个干净目录/虚拟环境，完全照 README 从零跑一遍**。

验收：中途没有任何一步需要你「凭记忆补操作」。凡是需要补的，都说明 README 漏了，补进去。

---

### 练习 5：锁定依赖

生成 `requirements.txt` 并锁定版本：

```bash
pip freeze > requirements.txt
```

检查里面的 langgraph 是否带了 `==版本号`。

---

## 4. JS/Node.js 类比

| 交付要素 | Python/LangGraph | PHP | JS/Node 类比 |
|----------|-----------------|-----|-------------|
| 依赖清单 | requirements.txt | composer.json | package.json |
| 依赖锁定 | pip freeze | composer.lock | package-lock.json |
| 运行入口 | main.py | index.php | index.js |
| 错误处理 | try/except | try/catch | try/catch |
| 日志 | logging | Monolog | winston / console |

一句话：

> 「可交付」这套要求跟语言无关。不管 Python、PHP 还是 Node，交付都是同一套：锁依赖、写入口、处理错误、打日志、写 README。

---

## 5. AI Review 提问

贴出你升级后的 Demo 和 README，提问：

```text
我正在学习 PHP Week16 Day06：编排模式交付。

我把一个 LangGraph 3 步 Agent Demo 从「能跑」升级到「可交付」：
- 给每个 Node 加了 try/except，错误写进 state 不整体崩
- 加了一个条件边，简单问题跳过检索
- 加了结构化日志
- 写了 README 并锁定了依赖版本

请你按资深工程师标准帮我检查：

1. 我的错误处理方式对吗？有没有该处理却漏掉的错误？
2. 条件边的实现和流程图是否一致、是否清晰？
3. 我的日志够不够定位问题？记的信息合不合理？
4. 我的 README 是否真的能让别人一次跑通？漏了什么？
5. 依赖锁定做得对吗？还有什么可复现性的坑？

请用中文输出：我对的地方、我错或不完整的地方、修改建议、下一步练习。
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [ ] 升级后的 LangGraph Demo（含错误处理 + 条件边 + 日志）
- [ ] 一份合格的 README（依赖/安装/运行/预期输出/流程图）
- [ ] 锁定版本的 requirements.txt
- [ ] 「从零跑通」的验证记录
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 三个 Node 都有错误处理，单点失败不导致整体崩溃
- [ ] 有至少一个条件边，流程能真正分支
- [ ] 有结构化日志，能顺着日志复述流程
- [ ] README 完整，且自己在干净环境按它一次跑通
- [ ] requirements.txt 锁定了版本

---

## 8. 今日自测题

### 8.1 「能跑」和「能交付」差在哪？

参考答案：

> ✅ 差的不是功能，而是可复现（别人照文档能跑通）、可理解（有注释和流程图）、可观测（出错能看懂日志）、有边界（异常输入不崩）。交付 = 可运行 + 可复现 + 可理解。

---

### 8.2 Node 出错时，为什么推荐「把错误写进 State」而不是直接抛出？

参考答案：

> ✅ 直接抛出会让整个流程崩掉；写进 State 则让流程能继续走到兜底逻辑（比如返回「暂时无法回答」）。这样单个节点失败不会拖垮整个编排，健壮性更好。

---

### 8.3 为什么依赖要锁定版本？

参考答案：

> ✅ 不锁版本，别人安装到的可能是不同版本，导致「在我机器上能跑，在你机器上崩」。锁版本（requirements.txt 带 ==、composer.lock、package-lock.json）是可复现的基础。

---

### 8.4 判断 README 合不合格的黄金标准是什么？

参考答案：

> ✅ 一个从没见过项目的人，照着 README 从上到下做，能一次跑通、中途不需要凭记忆补任何操作。写完必须自己在干净环境验证一遍。

---

### 8.5 日志应该记什么才有用？

参考答案：

> ✅ 记「进入了哪个节点、关键 State 变成了什么、走了哪条分支」。目标是出问题时能顺着日志一眼看出流程卡在哪、State 哪里不对，而不是只有一行没头没尾的报错。

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
我正在进行 Week 16 Day 06：编排模式交付 的学习。
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
