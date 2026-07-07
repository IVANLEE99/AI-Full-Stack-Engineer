# Week 20 Day 04：Review Agent 串联

> 所属周：Week 20：Multi-Agent 工作流  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/multi-agent`  
> 类型：架构理解  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

写出"Review Agent"，并把三个 Agent 串成完整流水线：输入架构方案，输出风险清单与改进建议，最终形成端到端 pipeline。

今天你要真正掌握这一句话：

> Review Agent 是流水线的最后一站，像 Code Review 的资深工程师：它吃"架构方案 JSON"，吐"风险 + 建议 JSON"。把需求 → 架构 → Review 三站接起来，就是一条能"从一句话跑到 Review 报告"的端到端多 Agent 流水线。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾前两天：需求 Agent、架构 Agent 各自的输入输出
2. 理解 Review Agent 的职责（找风险、给建议）
3. 设计 Review Agent 的输出结构
4. 写 Review Agent 的 system prompt
5. 用 Python 写 Review Agent
6. 把三个 Agent 串成端到端 pipeline
7. 加一个统一的 pipeline 编排器（可复用）
8. 端到端跑一次，检查每一站的衔接
9. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 Review Agent 要解决什么问题

架构 Agent 给出了方案，但方案可能有坑：没考虑并发、少了鉴权、表设计不合理……

Review Agent 的职责，就是**站在评审者视角挑毛病、给建议**：

```json
{
  "risks": [
    {"level": "high", "point": "上传接口未做鉴权，任何人都能覆盖他人头像"},
    {"level": "medium", "point": "缩略图同步生成会拖慢响应"}
  ],
  "suggestions": [
    "上传接口加登录态校验和 user_id 归属校验",
    "缩略图改为异步队列生成"
  ],
  "score": 70
}
```

小白重点：Review Agent = **Code Review 里的资深工程师**。它的输入是架构方案，输出是"风险 + 建议 + 打分"。

---

### 1.2 三 Agent 的输入输出总览

先把三站的数据契约理清楚：

| Agent | 输入 | 输出 |
|---|---|---|
| 需求 Agent | 用户口语（字符串） | 结构化需求 JSON |
| 架构 Agent | 结构化需求 JSON | 架构方案 JSON |
| Review Agent | 架构方案 JSON | 风险+建议 JSON |

连起来看：

```text
一句话 ──▶ [需求] ──需求JSON──▶ [架构] ──架构JSON──▶ [Review] ──Review报告──▶ 结果
```

小白重点：每一个箭头都是一次"上游输出 = 下游输入"的消息传递。整条链就是一条流水线。

---

### 1.3 设计 Review Agent 的输出结构

| 字段 | 含义 | 例子 |
|---|---|---|
| `risks` | 风险清单（带等级） | high: 未鉴权 |
| `suggestions` | 改进建议 | 加登录校验 |
| `score` | 架构质量打分(0-100) | 70 |

小白重点：风险最好带 `level`（high/medium/low），下游或人看报告时能一眼抓重点。

---

### 1.4 写 Review Agent 的 system prompt

```python
REVIEW_SYSTEM_PROMPT = """
你是一名资深后端评审专家（Reviewer）。用户会给你一份架构方案（JSON），
你需要审查其中的风险并给出改进建议。

请只返回一个 JSON 对象，格式如下：
{
  "risks": [{"level": "high|medium|low", "point": "风险描述"}],
  "suggestions": ["建议1", "建议2"],
  "score": 0到100的整数
}

审查重点：
- 安全：鉴权、越权、输入校验
- 性能：同步阻塞、N+1、缺缓存
- 数据：表设计、索引、一致性
- 可维护性：接口是否清晰、职责是否单一
"""
```

小白重点：这版 prompt 明确说"输入是架构方案 JSON"，并给了审查维度清单，让模型评审有章可循。

---

### 1.5 用 Python 写 Review Agent

新建 `review_agent.py`：

```python
# review_agent.py
import json

REVIEW_SYSTEM_PROMPT = """
你是一名资深后端评审专家。根据架构方案 JSON 输出风险与建议，
只返回 JSON：{risks[], suggestions[], score}
"""


def fake_llm(system_prompt: str, user_input: str) -> str:
    return json.dumps({
        "risks": [
            {"level": "high", "point": "上传接口未鉴权，可越权覆盖他人头像"},
            {"level": "medium", "point": "缩略图同步生成会拖慢响应"},
        ],
        "suggestions": [
            "上传接口加登录态校验与 user_id 归属校验",
            "缩略图改为异步队列生成",
        ],
        "score": 70,
    }, ensure_ascii=False)


def safe_parse_json(raw: str) -> dict:
    try:
        return json.loads(raw)
    except json.JSONDecodeError as e:
        raise ValueError(f"Review Agent 返回非法 JSON：{raw[:200]}") from e


def review_agent(architecture: dict) -> dict:
    """Review Agent：架构方案 dict → 风险+建议 dict"""
    user_input = json.dumps(architecture, ensure_ascii=False)
    raw = fake_llm(REVIEW_SYSTEM_PROMPT, user_input)
    data = safe_parse_json(raw)
    data.setdefault("risks", [])
    data.setdefault("suggestions", [])
    data.setdefault("score", 0)
    return data
```

小白重点：注意三个 Agent 的结构几乎一模一样——**输入 dict → json.dumps → 模型 → 解析 → 兜底**。这是多 Agent 系统里可复用的"单 Agent 模板"。

---

### 1.6 把三个 Agent 串成端到端 pipeline

新建 `pipeline_3step.py`：

```python
# pipeline_3step.py
import json
from requirement_agent import requirement_agent
from architecture_agent import architecture_agent
from review_agent import review_agent


def run_pipeline(user_sentence: str) -> dict:
    # 第 1 站：口语 → 需求
    requirement = requirement_agent(user_sentence)
    # 第 2 站：需求 → 架构
    architecture = architecture_agent(requirement)
    # 第 3 站：架构 → Review
    review = review_agent(architecture)

    result = {
        "requirement": requirement,
        "architecture": architecture,
        "review": review,
    }
    return result


if __name__ == "__main__":
    out = run_pipeline("我想做一个用户可以上传头像的功能")
    print(json.dumps(out, ensure_ascii=False, indent=2))
```

运行：

```bash
python pipeline_3step.py
```

你会看到从一句话，一路跑出需求、架构、Review 三份结果。这就是**端到端多 Agent 流水线**。

小白重点：这三行

```python
requirement = requirement_agent(user_sentence)
architecture = architecture_agent(requirement)
review = review_agent(architecture)
```

就是整个 Week 20 的核心。每一行的输出，都是下一行的输入。

---

### 1.7 抽出一个可复用的编排器

上面每加一个 Agent 就要多写一行，也不好统一处理日志和异常。可以把"一串 Agent"抽象成一个列表，用编排器统一跑。

新建 `orchestrator.py`：

```python
# orchestrator.py
import json
from requirement_agent import requirement_agent
from architecture_agent import architecture_agent
from review_agent import review_agent


def run_chain(user_sentence: str) -> dict:
    """按顺序跑一串 Agent，每一步的输出喂给下一步"""
    steps = [
        ("requirement", requirement_agent),
        ("architecture", architecture_agent),
        ("review", review_agent),
    ]

    result = {}
    current = user_sentence  # 第一站的输入是人话
    for name, agent in steps:
        print(f"→ 正在运行 {name} Agent ...")
        output = agent(current)
        result[name] = output
        current = output  # 关键：本站输出变成下一站输入
    return result


if __name__ == "__main__":
    out = run_chain("我想做一个用户可以上传头像的功能")
    print(json.dumps(out, ensure_ascii=False, indent=2))
```

小白重点：`current = output` 这一行，就是"流水线传送带"。循环每转一圈，工件就往前流一站。加/减 Agent 只需改 `steps` 列表——这就是**编排（orchestration）**的价值：把流程和实现解耦。

对比 Node：这就像 `reduce` 把一串中间件依次套起来。

```js
const steps = [requirementAgent, architectureAgent, reviewAgent];
let current = sentence;
for (const agent of steps) {
  current = await agent(current);
}
```

---

### 1.8 端到端衔接检查

三站串起来后，重点检查每个"接缝"：

| 接缝 | 上游给什么 | 下游要什么 | 对齐？ |
|---|---|---|---|
| 需求→架构 | features 数组 | 读 features | ✅ |
| 架构→Review | endpoints/tables | 读 endpoints/tables | ✅ |

任何一处字段名不一致，报告就会出现"看起来跑通了，但内容对不上"的假象。

小白重点：端到端跑通 ≠ 内容正确。要顺着数据流，检查每一站真的用到了上游的关键字段。

---

## 2. 源码阅读

- `ai-lab/multi-agent/review_agent.py`（你今天写的）
- `ai-lab/multi-agent/pipeline_3step.py`（你今天写的）
- `ai-lab/multi-agent/orchestrator.py`（你今天写的）

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. 三个 Agent 的函数结构是不是同一套模板
2. `run_chain` 里 `current = output` 起什么作用
3. `steps` 列表增删一项，会不会影响其它代码
4. 每一站的输入类型和上一站输出类型是否匹配

建议你在笔记里写出类似表格：

| 代码环节 | 作用 |
|---|---|
| steps 列表 | 声明流水线的 Agent 顺序 |
| current = output | 上游输出→下游输入 |
| for name, agent in steps | 逐站编排 |
| setdefault | 每站输出兜底 |

---

## 3. 练习任务

### 练习 1：设计 Review 输出结构

参照 1.3 节，列出 Review Agent 的输出字段（含 risks 的 level），写进笔记。

目标：学会带等级的风险结构设计。

---

### 练习 2：写 Review Agent

照着 1.5 节写 `review_agent.py`，用假模型跑通。

目标：复用"单 Agent 模板"，再写一个 Agent。

---

### 练习 3：串三个 Agent

照着 1.6 节写 `pipeline_3step.py`，端到端跑一次，观察三份输出。

目标：亲手搭出完整的需求→架构→Review 流水线。

---

### 练习 4：抽象编排器

照着 1.7 节写 `orchestrator.py`，用 `steps` 列表 + 循环跑同一条链。

目标：理解"编排"把流程和实现解耦的价值。

---

### 练习 5：给流水线加一个假的第四站

在 `steps` 里再加一个 `("summary", summary_agent)`，`summary_agent` 只需返回 `{"note": "已完成评审"}`。跑一次看能不能无缝接上。

目标：体会编排器"加 Agent 只改列表"的扩展性。

---

## 4. JS/Node.js 类比

| 多 Agent 概念 | Node.js 类比 | 说明 |
|---|---|---|
| Review Agent | Code Review 服务 | 审查并给建议 |
| 三 Agent 串联 | Promise 链 / async 顺序 await | 一步接一步 |
| orchestrator | reduce / 中间件链 | 用列表编排流程 |
| current = output | 累加器往下传 | 上游结果传给下游 |
| steps 列表 | 中间件数组 | 声明式的流程 |
| 端到端 pipeline | Express 请求处理链 | req 流过多个 handler |

小白重点：Node 的 Express 中间件 `app.use(a).use(b).use(c)`，请求依次流过 a、b、c；多 Agent 流水线里，数据依次流过需求、架构、Review。思路完全一致。

---

## 5. AI Review 提问

完成练习后，把三个 Agent 和 `orchestrator.py` 贴给 AI，然后问：

```text
我正在学习 Week 20 Day 04：Review Agent 串联。

请你按资深后端架构师标准帮我检查：

1. 我的 Review Agent 输出结构（risks/suggestions/score）是否合理？
2. 我把三个 Agent 串成端到端 pipeline 的方式对不对？
3. 我抽的 orchestrator（steps 列表 + current=output）是不是好的编排设计？
4. 我用 Express 中间件链/reduce 来类比编排，准不准？
5. 真实项目里，端到端流水线还要考虑什么（某一站失败、耗时、日志）？

请用中文输出：
- 我做对的地方
- 我遗漏或有风险的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [✅] Review 输出字段设计表
- [✅] `review_agent.py`（假模型版可运行）
- [✅] `pipeline_3step.py`（三 Agent 端到端可运行）
- [✅] `orchestrator.py`（列表编排版可运行）
- [✅] 端到端衔接检查笔记
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [✅] 能说清 Review Agent 的输入输出
- [✅] 能设计带等级的风险输出结构
- [✅] 能写出第三个 Agent（复用单 Agent 模板）
- [✅] 能把三个 Agent 串成端到端 pipeline
- [✅] 能用 `steps` 列表 + 循环实现编排器
- [✅] 能解释 `current = output` 的作用
- [✅] 能用 Express 中间件链做准确类比

---

## 8. 今日自测题

### 8.1 Review Agent 的输入和输出分别是什么？

参考答案：

> ✅ 输入是架构 Agent 输出的架构方案 JSON；输出是风险清单、改进建议和打分（risks/suggestions/score）。

---

### 8.2 三个 Agent 是怎么串起来的？

参考答案：

> ✅ 需求 Agent 的输出喂给架构 Agent，架构 Agent 的输出喂给 Review Agent。每一步的输出都是下一步的输入，形成端到端流水线。

---

### 8.3 orchestrator 里 `current = output` 有什么作用？

参考答案：

> ✅ 它把当前 Agent 的输出保存下来，作为下一个 Agent 的输入。它就是流水线的"传送带"，让工件一站站往前流。

---

### 8.4 用 `steps` 列表编排相比手写三行调用，好在哪？

参考答案：

> ✅ 流程和实现解耦。增删 Agent 只需改列表，不用动主逻辑；还能在循环里统一加日志、计时、异常处理。这就是"编排"的价值。

---

### 8.5 端到端跑通了，是不是就说明结果一定对？

参考答案：

> ✅ 不一定。跑通只说明没报错。还要顺着数据流检查每一站真的用到了上游的关键字段（数据契约对齐），否则会出现"跑通但内容对不上"的假象。

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
我正在进行 Week 20 Day 04：Review Agent 串联 的学习。
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
