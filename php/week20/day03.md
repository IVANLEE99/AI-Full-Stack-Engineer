# Week 20 Day 03：架构 Agent

> 所属周：Week 20：Multi-Agent 工作流  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/multi-agent`  
> 类型：编码练习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

写出"架构 Agent"：接收上游的结构化需求，输出架构建议（API 列表、数据表、数据流）。

今天你要真正掌握这一句话：

> 架构 Agent 就像技术架构师：它的输入不是人话，而是需求 Agent 输出的**结构化需求 JSON**；它的输出也是结构化的架构方案 JSON（endpoints、tables、data_flow、tech_notes）。上一个 Agent 的输出，正是这一个 Agent 的输入——这就是"消息传递"。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾昨天的需求 Agent，明确它的输出长什么样
2. 理解架构 Agent 的输入 = 需求 Agent 的输出（Agent 间消息传递）
3. 设计架构 Agent 的输出结构（API/表/数据流）
4. 写架构 Agent 的 system prompt
5. 用 Python 写架构 Agent（假模型 → 真模型）
6. 把需求 Agent 和架构 Agent **串起来**跑一次
7. 检查串联时的数据契约是否对齐
8. 处理架构 Agent 的异常兜底
9. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 架构 Agent 要解决什么问题

昨天的需求 Agent 输出了这样的结构化需求：

```json
{
  "title": "用户上传头像",
  "roles": ["登录用户"],
  "features": ["选择本地图片上传", "生成缩略图", "替换旧头像"],
  "constraints": ["仅支持 jpg/png", "单张不超过 5MB"],
  "non_goals": ["暂不支持头像审核"]
}
```

架构 Agent 的职责，是把这份需求变成**能指导开发的架构方案**：

```json
{
  "endpoints": [
    {"method": "POST", "path": "/api/avatar/upload", "desc": "上传头像"},
    {"method": "GET", "path": "/api/avatar/{userId}", "desc": "获取头像"}
  ],
  "tables": [
    {"name": "user_avatar", "fields": ["id", "user_id", "url", "thumb_url", "created_at"]}
  ],
  "data_flow": [
    "用户选图 → 前端校验大小/格式",
    "POST 上传 → 后端存对象存储 → 生成缩略图",
    "写入 user_avatar 表 → 返回 url"
  ],
  "tech_notes": ["图片存对象存储而非数据库", "缩略图异步生成可用队列"]
}
```

小白重点：架构 Agent = **技术架构师**。它不关心"人话"，只吃"结构化需求"，吐"结构化架构"。

---

### 1.2 核心：上游输出 = 下游输入（消息传递）

多 Agent 协作最核心的一点：**前一个 Agent 的输出，就是后一个 Agent 的输入**。

```text
用户口语
   │
   ▼
[需求 Agent] ──输出结构化需求 JSON──▶ [架构 Agent] ──输出架构方案 JSON──▶ ...
```

在代码里，就是把上一步的返回值，喂给下一步：

```python
requirement = requirement_agent("我想做一个用户可以上传头像的功能")
architecture = architecture_agent(requirement)  # 需求的输出，直接当架构的输入
```

这就是 **Agent 间的消息传递**。它像流水线上的工件：上一台机器加工完，直接传给下一台。

对比 JS/Node：这就像 Promise 链或函数组合，前一步的结果传给后一步。

```js
const requirement = await requirementAgent(sentence);
const architecture = await architectureAgent(requirement); // 结果串下去
```

小白重点：Agent 之间靠**结构化数据（dict/JSON）**通信，而不是靠人话。数据契约对齐，流水线才不会断。

---

### 1.3 设计架构 Agent 的输出结构

先想清楚"架构方案需要哪些字段"，再写 prompt。一个够用的结构：

| 字段 | 含义 | 例子 |
|---|---|---|
| `endpoints` | API 接口列表 | POST /api/avatar/upload |
| `tables` | 数据表设计 | user_avatar(id, user_id, url...) |
| `data_flow` | 数据流/时序 | 选图→上传→存储→写库 |
| `tech_notes` | 技术选型/注意点 | 图片存对象存储 |

小白重点：**先定字段，再写 prompt**。字段是数据契约，下游 Review Agent 就靠这些字段干活。

---

### 1.4 写架构 Agent 的 system prompt

```python
ARCHITECTURE_SYSTEM_PROMPT = """
你是一名资深后端架构师。用户会给你一份结构化需求（JSON），
你需要设计出可落地的后端架构方案。

请只返回一个 JSON 对象，不要有任何多余文字，格式如下：
{
  "endpoints": [{"method": "POST", "path": "/api/xxx", "desc": "说明"}],
  "tables": [{"name": "表名", "fields": ["字段1", "字段2"]}],
  "data_flow": ["数据流步骤1", "数据流步骤2"],
  "tech_notes": ["技术注意点1", "技术注意点2"]
}

要求：
- endpoints 至少覆盖需求里的每个核心 feature
- tables 要包含主键和必要字段
- data_flow 按时间顺序描述请求如何流转
- tech_notes 给出关键技术选型理由
"""
```

小白重点：注意 prompt 里明确说"用户会给你一份结构化需求（JSON）"——这告诉模型，它的**输入是上游的输出**，而不是人话。

---

### 1.5 用 Python 写架构 Agent

新建 `architecture_agent.py`：

```python
# architecture_agent.py
import json

ARCHITECTURE_SYSTEM_PROMPT = """
你是一名资深后端架构师。根据结构化需求设计架构方案，
只返回 JSON：{endpoints[], tables[], data_flow[], tech_notes[]}
"""


def fake_llm(system_prompt: str, user_input: str) -> str:
    """假模型：返回一段固定的架构方案 JSON"""
    return json.dumps({
        "endpoints": [
            {"method": "POST", "path": "/api/avatar/upload", "desc": "上传头像"},
            {"method": "GET", "path": "/api/avatar/{userId}", "desc": "获取头像"},
        ],
        "tables": [
            {"name": "user_avatar",
             "fields": ["id", "user_id", "url", "thumb_url", "created_at"]},
        ],
        "data_flow": [
            "前端校验格式/大小",
            "POST 上传到对象存储",
            "生成缩略图并写入 user_avatar",
        ],
        "tech_notes": ["图片存对象存储", "缩略图异步生成"],
    }, ensure_ascii=False)


def safe_parse_json(raw: str) -> dict:
    try:
        return json.loads(raw)
    except json.JSONDecodeError as e:
        raise ValueError(f"架构 Agent 返回非法 JSON：{raw[:200]}") from e


def architecture_agent(requirement: dict) -> dict:
    """架构 Agent：结构化需求 dict → 架构方案 dict"""
    # 把上游需求 dict 转成字符串，作为本 Agent 的输入
    user_input = json.dumps(requirement, ensure_ascii=False)
    raw = fake_llm(ARCHITECTURE_SYSTEM_PROMPT, user_input)
    data = safe_parse_json(raw)
    data.setdefault("endpoints", [])
    data.setdefault("tables", [])
    data.setdefault("data_flow", [])
    data.setdefault("tech_notes", [])
    return data
```

小白重点：`architecture_agent` 的参数是 **dict**（上游需求），第一步先把它 `json.dumps` 成字符串再喂给模型。这一步就是"把上游工件放上流水线传送带"。

---

### 1.6 把需求 Agent 和架构 Agent 串起来

现在把昨天的需求 Agent 和今天的架构 Agent 连成一条小流水线。

新建 `pipeline_2step.py`：

```python
# pipeline_2step.py
import json
from requirement_agent import requirement_agent
from architecture_agent import architecture_agent


def run_pipeline(user_sentence: str) -> dict:
    # 第 1 站：口语 → 结构化需求
    requirement = requirement_agent(user_sentence)
    print("【需求 Agent 输出】")
    print(json.dumps(requirement, ensure_ascii=False, indent=2))

    # 第 2 站：结构化需求 → 架构方案（上游输出直接当输入）
    architecture = architecture_agent(requirement)
    print("\n【架构 Agent 输出】")
    print(json.dumps(architecture, ensure_ascii=False, indent=2))

    return {"requirement": requirement, "architecture": architecture}


if __name__ == "__main__":
    run_pipeline("我想做一个用户可以上传头像的功能")
```

运行：

```bash
python pipeline_2step.py
```

你会看到两个 Agent 依次输出。这就是**流水线编排**的雏形：需求 → 架构。

小白重点：`architecture_agent(requirement)` 这一行，就是两个 Agent 之间的"接口"。上游的 `requirement` 直接流进下游，中间没有人参与。

---

### 1.7 数据契约对齐（串联最容易踩的坑）

串联时最常见的问题：**上游给的字段，下游用不上，或者字段名对不上**。

比如需求 Agent 输出的键叫 `features`，架构 Agent 却去读 `feature_list`——数据就断了。

对齐检查清单：

| 检查项 | 说明 |
|---|---|
| 字段名一致 | 上游叫 `features`，下游就读 `features` |
| 类型一致 | 上游是数组，下游别当字符串用 |
| 缺字段兜底 | 上游可能漏字段，下游用 `.get()` 兜底 |
| 空值处理 | 上游给空数组，下游别崩 |

安全读取上游字段的写法：

```python
features = requirement.get("features", [])  # 缺了就用空列表，不会 KeyError
```

小白重点：多 Agent 系统里，**每一处"上游输出→下游输入"都是一个数据契约**。契约对齐，流水线才稳。这跟微服务之间约定接口字段是一模一样的道理。

---

## 2. 源码阅读

- `ai-lab/multi-agent/architecture_agent.py`（你今天写的）
- `ai-lab/multi-agent/pipeline_2step.py`（你今天写的）

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. `architecture_agent` 的参数类型是不是 dict（上游输出）
2. 哪一步把上游 dict 转成了字符串喂给模型
3. `run_pipeline` 里，上游输出是怎么流进下游的
4. 用 `.get()` 还是 `[]` 读上游字段（哪个更安全）

建议你在笔记里写出类似表格：

| 代码环节 | 作用 |
|---|---|
| architecture_agent(requirement) | Agent 间消息传递 |
| json.dumps(requirement) | 上游 dict → 字符串输入 |
| safe_parse_json | 下游返回兜底 |
| requirement.get("features", []) | 安全读上游字段 |

---

## 3. 练习任务

### 练习 1：设计架构输出结构

参照 1.3 节，自己列出架构 Agent 的输出字段表（至少 4 个字段），写进笔记。

目标：学会"先定字段（数据契约），再写 prompt"。

---

### 练习 2：写架构 Agent 的 system prompt

参照 1.4 节，写一版 `ARCHITECTURE_SYSTEM_PROMPT`，明确说明"输入是结构化需求 JSON"。

目标：让模型知道自己的输入来自上游 Agent。

---

### 练习 3：跑通假模型版架构 Agent

照着 1.5 节把 `architecture_agent.py` 敲一遍，用假模型跑通。

目标：理解 dict 输入 → json.dumps → 模型 → 解析回 dict 的链路。

---

### 练习 4：串联需求 Agent 和架构 Agent

照着 1.6 节写 `pipeline_2step.py`，把两个 Agent 串起来跑一次。

目标：亲手实现"上游输出 = 下游输入"的消息传递。

---

### 练习 5：故意制造契约不对齐，再修好

把架构 Agent 里读上游字段的地方，从 `requirement.get("features", [])` 改成 `requirement["feature_list"]`（一个不存在的键），运行看报错。

然后改回来，理解为什么用 `.get()` 更安全。

目标：亲身体会数据契约对齐的重要性。

---

## 4. JS/Node.js 类比

| 架构 Agent 概念 | Node.js 类比 | 说明 |
|---|---|---|
| 架构 Agent | 一个处理 service | 输入结构化需求，输出架构 |
| 上游输出=下游输入 | Promise 链 / 函数组合 | 结果一路往下传 |
| Agent 间消息传递 | 微服务间接口调用 | 靠结构化数据通信 |
| 数据契约 | 接口字段约定 | 字段名/类型要对齐 |
| `.get("x", [])` | 解构默认值 `{ x = [] }` | 缺字段兜底 |
| pipeline 编排 | 中间件链 / pipe() | 一步接一步 |

小白重点：`architecture_agent(requirement_agent(sentence))` 这种"套娃"调用，本质就是 Node 里的函数组合 `f(g(x))`，或者 `.then().then()` 链。区别只是每一步内部调了大模型。

---

## 5. AI Review 提问

完成练习后，把你的 `architecture_agent.py` 和 `pipeline_2step.py` 贴给 AI，然后问：

```text
我正在学习 Week 20 Day 03：架构 Agent。

请你按资深后端架构师标准帮我检查：

1. 我设计的架构输出字段（endpoints/tables/data_flow/tech_notes）是否合理、够用？
2. 我把"上游需求输出"当"下游架构输入"的串联方式对不对？
3. 我读取上游字段的方式是否安全（.get vs 直接下标）？
4. 我用"Promise 链/微服务接口"来类比 Agent 间消息传递，准不准？
5. 真实项目里，架构 Agent 还需要考虑什么（如需求不完整、字段缺失）？

请用中文输出：
- 我做对的地方
- 我遗漏或有风险的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [✅] 架构输出字段设计表
- [✅] `ARCHITECTURE_SYSTEM_PROMPT`
- [✅] `architecture_agent.py`（假模型版可运行）
- [✅] `pipeline_2step.py`（需求 + 架构串联可运行）
- [✅] 数据契约对齐笔记（含故意报错的实验）
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [✅] 能说清架构 Agent 的输入是"结构化需求"而非人话
- [✅] 能设计出合理的架构输出结构
- [✅] 能写出说明输入来自上游的 system prompt
- [✅] 能把需求 Agent 和架构 Agent 串成流水线
- [✅] 能解释"上游输出 = 下游输入"的消息传递
- [✅] 能用 `.get()` 安全读取上游字段
- [✅] 能用 Node 的函数组合/微服务接口做准确类比

---

## 8. 今日自测题

### 8.1 架构 Agent 的输入是什么？

参考答案：

> ✅ 是上游需求 Agent 输出的**结构化需求 JSON（dict）**，不是用户的人话。架构 Agent 只吃结构化数据。

---

### 8.2 什么是 Agent 间的"消息传递"？

参考答案：

> ✅ 前一个 Agent 的输出，直接作为后一个 Agent 的输入。在代码里就是 `architecture_agent(requirement)`——把 requirement 传下去。Agent 之间靠结构化数据通信。

---

### 8.3 为什么读上游字段建议用 `.get("features", [])` 而不是 `["features"]`？

参考答案：

> ✅ 因为上游可能漏字段。用 `["features"]` 时若键不存在会抛 KeyError 让程序崩溃；用 `.get("features", [])` 时缺字段会返回默认空列表，程序更健壮。

---

### 8.4 什么是"数据契约对齐"？为什么重要？

参考答案：

> ✅ 指上下游 Agent 约定好字段名、类型、结构并保持一致。字段名对不上（如 features vs feature_list）流水线就断了。它和微服务之间约定接口字段是同一个道理。

---

### 8.5 用 Node 的什么概念类比"两个 Agent 串联"最贴切？

参考答案：

> ✅ Promise 链（`.then().then()`）或函数组合 `f(g(x))`。前一步的结果一路往下传，只是每一步内部调了大模型。

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
我正在进行 Week 20 Day 03：架构 Agent 的学习。
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
