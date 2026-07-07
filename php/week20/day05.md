# Week 20 Day 05：阶段⑤总结

> 所属周：Week 20：Multi-Agent 工作流  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/multi-agent`  
> 类型：类比日  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

优化多 Agent 流水线的错误处理，让某一站失败时整条链不会"炸得莫名其妙"；同时对第五阶段做一次系统自评。

今天你要真正掌握这一句话：

> 多 Agent 流水线最怕"中间某一站悄悄坏了、后面全跟着错"。好的错误处理，是让每一站都能"要么给出合法输出，要么明确报出是哪一站、因为什么失败"，并且提供重试或降级的兜底。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾昨天的端到端 pipeline
2. 想清楚流水线会在哪些地方出错
3. 给单个 Agent 加"重试 + 兜底"
4. 给编排器加"定位是哪一站失败"
5. 加一个降级策略（失败不整条崩）
6. 跑几组会触发错误的输入，验证处理效果
7. 做第五阶段自评
8. 完成类比打卡
9. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 多 Agent 流水线会在哪些地方出错

流水线越长，出错点越多。常见的坑：

| 出错点 | 例子 | 后果 |
|---|---|---|
| 模型返回非 JSON | 返回了一段解释文字 | 解析崩溃 |
| 字段缺失 | 少了 features | 下游读到 None |
| 上下游字段名不一致 | 上游给 apis，下游读 endpoints | 内容对不上 |
| 模型超时/网络错误 | 请求挂了 | 整条链中断 |
| 某一站逻辑异常 | 除零、KeyError | 抛出裸异常 |

小白重点：这些坑在单 Agent 里也有，但在多 Agent 里会**被放大和传染**——第 2 站的错误，往往在第 3 站才爆出来，很难定位。

---

### 1.2 三层错误处理策略

我们分三层来加固：

```text
第 1 层：单 Agent 内 —— JSON 兜底 + 重试
第 2 层：编排器  —— 捕获异常，标注是哪一站失败
第 3 层：整体   —— 降级返回部分结果，而不是全崩
```

小白重点：这和 Node 后端的错误处理层次一样——函数内部 try/catch，中间件统一捕获，最后给前端一个友好的兜底响应。

---

### 1.3 第 1 层：给单 Agent 加重试和兜底

模型偶尔会返回非法 JSON，重试一次往往就好了。

```python
# safe_agent.py
import json


def call_with_retry(agent_fn, arg, retries: int = 2):
    """给任意 Agent 加重试。失败 retries 次后抛出带上下文的异常。"""
    last_err = None
    for attempt in range(1, retries + 1):
        try:
            return agent_fn(arg)
        except Exception as e:  # noqa: BLE001 学习示例，生产应细分异常
            last_err = e
            print(f"  第 {attempt} 次调用失败：{e}")
    raise RuntimeError(f"调用失败，已重试 {retries} 次") from last_err
```

小白重点：重试只对"偶发错误"（网络抖动、模型偶尔抽风）有用；对"稳定错误"（prompt 写错）无效——那种要改代码，不是靠重试。

---

### 1.4 给 JSON 解析加更稳的兜底

模型有时会在 JSON 前后加解释文字，比如 ```` ```json ... ``` ````。可以先"抠"出 JSON 再解析。

```python
import json
import re


def extract_json(raw: str) -> dict:
    """尽力从模型输出里抠出 JSON 对象"""
    # 去掉 ```json ``` 代码块围栏
    cleaned = re.sub(r"```(json)?", "", raw).strip("` \n")
    try:
        return json.loads(cleaned)
    except json.JSONDecodeError:
        # 兜底：抓第一个 { 到最后一个 }
        start = cleaned.find("{")
        end = cleaned.rfind("}")
        if start != -1 and end != -1:
            return json.loads(cleaned[start:end + 1])
        raise ValueError(f"无法解析 JSON：{raw[:200]}")
```

小白重点：这一步把"模型不听话加了废话"这种高频问题挡在门外。它是多 Agent 系统里最实用的一个小工具。

---

### 1.5 第 2 层：编排器定位是哪一站失败

昨天的 `run_chain` 一旦某站抛异常，你只看到一个裸报错，不知道是哪一站。给它加上"当前站名"的上下文。

```python
# orchestrator_safe.py
import json
from safe_agent import call_with_retry
from requirement_agent import requirement_agent
from architecture_agent import architecture_agent
from review_agent import review_agent


def run_chain_safe(user_sentence: str) -> dict:
    steps = [
        ("requirement", requirement_agent),
        ("architecture", architecture_agent),
        ("review", review_agent),
    ]

    result = {}
    current = user_sentence
    for name, agent in steps:
        print(f"→ 正在运行 {name} Agent ...")
        try:
            output = call_with_retry(agent, current, retries=2)
        except Exception as e:  # noqa: BLE001
            # 关键：明确标注是哪一站、因为什么失败
            raise RuntimeError(f"[{name}] 站失败：{e}") from e
        result[name] = output
        current = output
    return result
```

现在如果架构 Agent 崩了，你会看到：

```text
RuntimeError: [architecture] 站失败：无法解析 JSON：...
```

小白重点：`[{name}] 站失败` 这一句，把"哪一站坏了"直接写进错误信息，定位成本从几分钟降到几秒。

---

### 1.6 第 3 层：降级——不让整条链全崩

有时"Review 站挂了"不该让整个结果作废——需求和架构已经算出来了，可以先返回，Review 标记为失败。

```python
# orchestrator_graceful.py
import json
from safe_agent import call_with_retry
from requirement_agent import requirement_agent
from architecture_agent import architecture_agent
from review_agent import review_agent


def run_chain_graceful(user_sentence: str) -> dict:
    steps = [
        ("requirement", requirement_agent),
        ("architecture", architecture_agent),
        ("review", review_agent),
    ]

    result = {}
    current = user_sentence
    for name, agent in steps:
        try:
            output = call_with_retry(agent, current, retries=2)
            result[name] = output
            current = output
        except Exception as e:  # noqa: BLE001
            # 降级：记录失败，保留已完成的部分结果
            result[name] = {"_error": str(e), "_failed_step": name}
            print(f"⚠ {name} 站失败，已降级：{e}")
            break  # 后续站依赖它，无法继续
    return result
```

小白重点：降级的核心是"**部分成功也是成功**"。用户拿到需求+架构，也比拿到一个空白报错强。要不要继续，取决于后面的站是否依赖失败站的输出。

---

### 1.7 对比：三种编排器

| 版本 | 出错时的表现 | 适用场景 |
|---|---|---|
| `run_chain`（Day04） | 裸报错，不知哪站 | 学习、快速验证 |
| `run_chain_safe` | 明确报哪站失败 | 需要可维护性 |
| `run_chain_graceful` | 保留部分结果，降级 | 面向用户的产品 |

小白重点：这三版是"逐步加固"的过程，正是真实项目里错误处理的演进路线：先跑通 → 能定位 → 能兜底。

---

### 1.8 阶段⑤自评清单

第五阶段（RAG + 企业知识库 / AI Backend）到这里告一段落。对照下面做自评：

| 能力项 | 自评（会/半会/不会） |
|---|---|
| 说清单 Agent 与多 Agent 的区别 |  |
| 设计 Agent 的输入输出数据契约 |  |
| 写一个带 system prompt 的 Agent |  |
| 把多个 Agent 串成端到端 pipeline |  |
| 用编排器（steps 列表）管理流程 |  |
| 给流水线加重试/兜底/降级 |  |
| 定位是哪一站失败 |  |
| 用 Node 中间件链做类比 |  |

小白重点：自评不是打分，是找出"半会/不会"的项，作为明后天复盘的重点。

---

## 2. 源码阅读

- `ai-lab/multi-agent/safe_agent.py`（你今天写的）
- `ai-lab/multi-agent/orchestrator_safe.py`（你今天写的）
- `ai-lab/multi-agent/orchestrator_graceful.py`（你今天写的）

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. `call_with_retry` 什么情况下重试有用、什么情况没用
2. `extract_json` 是怎么处理模型加废话的
3. `[{name}] 站失败` 如何提升定位效率
4. 降级版为什么用 `break` 而不是 `continue`

建议你在笔记里写出类似表格：

| 错误处理手段 | 解决什么问题 |
|---|---|
| 重试 | 偶发错误 |
| JSON 抠取 | 模型加废话 |
| 站名标注 | 定位困难 |
| 降级 | 整条链全崩 |

---

## 3. 练习任务

### 练习 1：给单 Agent 加重试

照着 1.3 写 `call_with_retry`，故意让假模型前一次抛异常、第二次成功，验证重试生效。

目标：理解重试对偶发错误的作用。

---

### 练习 2：写 JSON 抠取工具

照着 1.4 写 `extract_json`，喂它一段 ```` ```json {...} ``` ````（带围栏）的字符串，验证能正确解析。

目标：掌握多 Agent 系统最实用的解析兜底工具。

---

### 练习 3：优化编排器（定位失败站）

照着 1.5 写 `run_chain_safe`，故意让架构 Agent 抛异常，确认错误信息里出现 `[architecture]`。

目标：学会把"哪一站坏了"写进错误信息。

---

### 练习 4：加降级策略

照着 1.6 写 `run_chain_graceful`，让 Review 站失败，确认仍能拿到需求和架构的部分结果。

目标：理解"部分成功也是成功"。

---

### 练习 5：阶段⑤自评 + 类比打卡

填写 1.8 的自评清单，并在笔记里用一句话总结"多 Agent 流水线像 Node 里的什么"。

目标：完成阶段复盘和类比打卡。

---

## 4. JS/Node.js 类比

| 错误处理概念 | Node.js 类比 | 说明 |
|---|---|---|
| 单 Agent try/catch | 函数内 try/catch | 局部兜底 |
| call_with_retry | axios-retry / p-retry | 偶发错误重试 |
| 编排器捕获+标注站名 | Express 错误中间件 | 统一捕获、带上下文 |
| 降级返回部分结果 | 部分渲染 / 兜底响应 | 不让整体白屏 |
| 三版编排器演进 | 从 demo 到生产的加固 | 先跑通再健壮 |

小白重点：Node 里 `app.use((err, req, res, next) => {...})` 是统一错误中间件；多 Agent 的编排器 `try/except` + 站名标注，就是同一个角色——**流程级的统一错误处理**。

---

## 5. AI Review 提问

完成练习后，把三版编排器贴给 AI，然后问：

```text
我正在学习 Week 20 Day 05：多 Agent 流水线的错误处理与阶段总结。

请你按资深后端架构师标准帮我检查：

1. 我的重试逻辑（call_with_retry）设计合理吗？哪些错误不该重试？
2. 我的 JSON 抠取兜底（extract_json）够不够稳？
3. 编排器里标注"哪一站失败"的做法对不对？
4. 我的降级策略（保留部分结果）合理吗？什么时候该 break、什么时候该继续？
5. 我用 Express 错误中间件来类比编排器错误处理，准不准？
6. 真实生产的多 Agent 系统，错误处理还差哪些（超时、限流、监控）？

请用中文输出：
- 我做对的地方
- 我遗漏或有风险的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [✅] `safe_agent.py`（重试工具）
- [✅] `extract_json`（JSON 抠取兜底）
- [✅] `orchestrator_safe.py`（定位失败站）
- [✅] `orchestrator_graceful.py`（降级版）
- [✅] 三版编排器对比笔记
- [✅] 阶段⑤自评清单（已填）
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [✅] 能列出多 Agent 流水线的主要出错点
- [✅] 能给单 Agent 加重试
- [✅] 能写 JSON 抠取兜底
- [✅] 能让编排器报出"哪一站失败"
- [✅] 能实现降级（保留部分结果）
- [✅] 能说清三版编排器的演进逻辑
- [✅] 完成阶段⑤自评与类比打卡

---

## 8. 今日自测题

### 8.1 多 Agent 流水线为什么比单 Agent 更难定位错误？

参考答案：

> ✅ 因为错误会在链上传染：第 2 站产出的坏数据，往往到第 3 站才爆出异常。不标注站名的话，只看到一个裸报错，很难知道根因在哪一站。

---

### 8.2 重试适合处理哪类错误？不适合哪类？

参考答案：

> ✅ 适合偶发错误（网络抖动、模型偶尔返回非法 JSON）。不适合稳定错误（prompt 写错、字段名不一致），那种重试多少次都一样，得改代码。

---

### 8.3 `extract_json` 解决了什么高频问题？

参考答案：

> ✅ 模型经常在 JSON 前后加解释文字或 ```json 代码块围栏，直接 json.loads 会崩。extract_json 先去掉围栏、再抠出第一个 { 到最后一个 }，大幅提升解析成功率。

---

### 8.4 降级版编排器里，为什么某站失败后用 `break`？

参考答案：

> ✅ 因为后面的站依赖失败站的输出（架构依赖需求，Review 依赖架构）。前面断了，后面没法继续，所以 break 并返回已完成的部分结果。如果各站相互独立，才可以 continue。

---

### 8.5 三版编排器体现了什么演进思路？

参考答案：

> ✅ 先跑通（run_chain）→ 能定位（run_chain_safe，标注站名）→ 能兜底（run_chain_graceful，降级）。这正是真实项目错误处理由简到强的加固路线。

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
我正在进行 Week 20 Day 05：阶段⑤总结 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 20 README](./README.md)
