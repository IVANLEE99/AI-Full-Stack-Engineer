# Week 20 Day 02：需求 Agent

> 所属周：Week 20：Multi-Agent 工作流  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/multi-agent`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

写出"需求 Agent"：把一句话口语需求，变成结构化需求（JSON）。用至少 3 个真实需求测试它。

今天你要真正掌握这一句话：

> 需求 Agent 就像产品经理：你丢给它一句模糊的口语（"我想做个登录功能"），它输出一份字段清晰的结构化需求（标题、角色、功能点、边界、约束）。关键是用 system prompt 把角色钉死，用"只返回 JSON"的约束把输出格式钉死。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾昨天的三 Agent 流水线，明确今天只做第一站
2. 理解需求 Agent 的职责：口语 → 结构化
3. 学会写一个"好的 system prompt"（角色 + 输出格式 + 约束）
4. 理解为什么要强制"只返回 JSON"
5. 用 Python 写出需求 Agent（先假模型，再接真模型）
6. 学会解析和校验模型返回的 JSON
7. 用 3 个真实需求测试
8. 处理"模型返回的不是合法 JSON"的情况
9. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 需求 Agent 要解决什么问题

用户给你的需求，往往是这样的口语：

```text
我想做一个用户可以上传头像的功能
```

这句话对人来说好懂，但对下游的"架构 Agent"来说太模糊了：

- 谁能上传？（角色）
- 支持什么格式？多大？（约束）
- 上传后存哪？（边界）
- 要不要审核？（功能点）

需求 Agent 的职责，就是把这句模糊的话，**翻译成结构清晰的需求**：

```json
{
  "title": "用户上传头像",
  "roles": ["登录用户"],
  "features": [
    "选择本地图片上传",
    "上传后生成缩略图",
    "替换旧头像"
  ],
  "constraints": ["仅支持 jpg/png", "单张不超过 5MB"],
  "non_goals": ["暂不支持头像审核", "暂不支持视频头像"]
}
```

小白重点：需求 Agent = **一个专职的产品经理**，它把"人话"翻译成"下游能用的结构化需求"。

---

### 1.2 system prompt：给 Agent 钉死角色

Agent 的"灵魂"是它的 **system prompt**（系统提示词）。它告诉模型："你是谁、你要干什么、你要怎么输出"。

一个好的需求 Agent system prompt 应该包含三部分：

```text
1. 角色：你是一名资深产品经理
2. 任务：把用户的一句话需求，拆解成结构化需求
3. 输出格式：只返回 JSON，字段固定为 title/roles/features/constraints/non_goals
```

完整示例：

```python
REQUIREMENT_SYSTEM_PROMPT = """
你是一名资深产品经理。用户会给你一句口语化的需求，
你需要把它拆解成结构化需求。

请只返回一个 JSON 对象，不要有任何多余文字，格式如下：
{
  "title": "需求标题（简短）",
  "roles": ["涉及的用户角色"],
  "features": ["功能点1", "功能点2"],
  "constraints": ["约束条件，如格式/大小/性能"],
  "non_goals": ["本次不做的事，明确边界"]
}

要求：
- 如果用户没说清楚，请用合理的默认假设补全，并放进对应字段
- features 至少 3 条
- non_goals 至少 1 条，用来明确边界
"""
```

小白重点：system prompt 里有三个"钉子"——**角色钉子、任务钉子、格式钉子**。三个都钉死，输出才稳定。

---

### 1.3 为什么要强制"只返回 JSON"

如果不约束，模型很可能返回这样：

```text
好的！根据你的需求，我帮你整理如下：

标题：用户上传头像
角色：登录用户
...
希望对你有帮助！
```

这种"带客套话的自然语言"对下游 Agent 是灾难——程序没法可靠地提取字段。

所以我们强制模型**只返回 JSON**：

| 输出方式 | 下游能否直接用 | 说明 |
|---|---|---|
| 带客套话的文字 | ❌ 难 | 要靠正则硬抠，容易出错 |
| 纯 JSON | ✅ 能 | `json.loads()` 一行解析 |

对比 JS/Node：这就像后端接口**永远返回 JSON**，而不是返回一段 HTML 夹带数据。前端才好解析。

```js
// 后端接口约定：永远返回 JSON
res.json({ title: "用户上传头像", roles: ["登录用户"] });
```

---

### 1.4 用 Python 写需求 Agent（先假模型）

和昨天一样，先用**假模型**跑通流程，理解数据怎么走，再接真模型。

新建 `requirement_agent.py`：

```python
# requirement_agent.py
import json

REQUIREMENT_SYSTEM_PROMPT = """
你是一名资深产品经理。把用户的一句话需求拆成结构化需求，
只返回 JSON：{title, roles[], features[], constraints[], non_goals[]}
"""


def fake_llm(system_prompt: str, user_input: str) -> str:
    """假模型：先返回一段固定的 JSON 字符串，模拟真实模型的输出"""
    return json.dumps({
        "title": "用户上传头像",
        "roles": ["登录用户"],
        "features": ["选择本地图片上传", "生成缩略图", "替换旧头像"],
        "constraints": ["仅支持 jpg/png", "单张不超过 5MB"],
        "non_goals": ["暂不支持头像审核"],
    }, ensure_ascii=False)


def requirement_agent(user_sentence: str) -> dict:
    """需求 Agent：一句话 → 结构化需求 dict"""
    raw = fake_llm(REQUIREMENT_SYSTEM_PROMPT, user_sentence)
    result = json.loads(raw)  # 把模型返回的 JSON 字符串解析成 dict
    return result


if __name__ == "__main__":
    req = requirement_agent("我想做一个用户可以上传头像的功能")
    print(json.dumps(req, ensure_ascii=False, indent=2))
```

运行：

```bash
python requirement_agent.py
```

小白重点：注意 `json.loads(raw)` 这一步——模型返回的是**字符串**，我们要把它解析成 Python 的 **dict** 才能用。这一步是所有"结构化输出 Agent"的必经步骤。

---

### 1.5 接真模型（以通用 Chat API 为例）

真实项目里，`fake_llm` 会换成真正的模型调用。这里给一个通用写法（用环境变量存密钥，脱敏）：

```python
# 真实模型调用（示意，脱敏）
import os
from openai import OpenAI  # 任意兼容 OpenAI 协议的客户端

client = OpenAI(
    api_key=os.environ["LLM_API_KEY"],       # 密钥放环境变量，别写死在代码里
    base_url=os.environ.get("LLM_BASE_URL"), # 可指向自建/代理网关
)


def real_llm(system_prompt: str, user_input: str) -> str:
    resp = client.chat.completions.create(
        model="your-model-name",
        messages=[
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_input},
        ],
        temperature=0.2,  # 需求分析要稳定，温度调低
    )
    return resp.choices[0].message.content
```

小白重点：

- 密钥**永远放环境变量**，不要硬编码进代码，更不要提交到 git。
- 需求分析这类"要稳定"的任务，`temperature` 调低（0~0.3），减少胡编。

对比 JS/Node：

```js
// Node 也是同样的模式：密钥放 process.env
const apiKey = process.env.LLM_API_KEY;
```

---

### 1.6 校验模型返回的 JSON（防止翻车）

真实模型有时会不听话，返回的不是合法 JSON（比如多了一句客套话、少了一个括号）。我们必须**兜底**。

```python
import json


def safe_parse_json(raw: str) -> dict:
    """安全解析：解析失败就抛出清晰的错误，而不是让程序莫名崩溃"""
    try:
        return json.loads(raw)
    except json.JSONDecodeError as e:
        raise ValueError(f"模型返回的不是合法 JSON：{raw[:200]}") from e


def requirement_agent(user_sentence: str) -> dict:
    raw = real_llm(REQUIREMENT_SYSTEM_PROMPT, user_sentence)
    data = safe_parse_json(raw)

    # 再校验必填字段，缺了就补默认值
    data.setdefault("title", "未命名需求")
    data.setdefault("roles", [])
    data.setdefault("features", [])
    data.setdefault("constraints", [])
    data.setdefault("non_goals", [])
    return data
```

| 风险 | 兜底做法 |
|---|---|
| 返回不是 JSON | `try/except` 捕获，抛清晰错误 |
| 缺字段 | `setdefault` 补默认值 |
| 字段类型不对 | （进阶）用 schema 校验，如 pydantic |

小白重点：**永远不要相信模型 100% 听话**。解析 + 校验 + 兜底，是 AI 后端的基本功。这跟后端"永远不信任前端传来的数据"是一个道理。

---

### 1.7 用 3 个真实需求测试

写好后，用 3 个不同的需求测试，看看输出是否合理：

```python
if __name__ == "__main__":
    cases = [
        "我想做一个手机号验证码登录",
        "用户能收藏喜欢的商品",
        "后台管理员可以导出订单表格",
    ]
    for c in cases:
        print("=" * 40)
        print("输入：", c)
        print(json.dumps(requirement_agent(c), ensure_ascii=False, indent=2))
```

测试时你要检查：

| 检查项 | 问自己 |
|---|---|
| title 是否准确 | 一眼能看出这是什么需求吗？ |
| features 是否完整 | 有没有漏掉明显的功能点？ |
| non_goals 是否合理 | 边界划清楚了吗？ |
| 输出是否稳定 | 同一个输入跑两次，结构一致吗？ |

小白重点：测多个 case，才能发现 prompt 的漏洞。**只测一个 case 的 Agent 是不可信的**。

---

## 2. 源码阅读

- `ai-lab/multi-agent/requirement_agent.py`（你今天写的）

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. `REQUIREMENT_SYSTEM_PROMPT` 里的"三个钉子"（角色/任务/格式）是否齐全
2. `json.loads` 在哪一步、为什么必须有
3. `safe_parse_json` 如何兜底
4. `setdefault` 补了哪些默认字段

建议你在笔记里写出类似表格：

| 代码环节 | 作用 |
|---|---|
| system prompt | 钉死角色和输出格式 |
| llm 调用 | 拿到模型返回（字符串） |
| json.loads | 字符串 → dict |
| safe_parse | 解析失败兜底 |
| setdefault | 缺字段补默认 |

---

## 3. 练习任务

### 练习 1：写出需求 Agent 的 system prompt

参照 1.2 节，自己写一版 `REQUIREMENT_SYSTEM_PROMPT`，必须包含角色、任务、输出格式三部分。

目标：能独立写出"钉死角色和格式"的系统提示词。

---

### 练习 2：跑通假模型版需求 Agent

照着 1.4 节把 `requirement_agent.py` 敲一遍，用假模型跑通。

目标：理解"模型返回字符串 → json.loads → dict"这条链路。

---

### 练习 3：加上 JSON 兜底

把 1.6 节的 `safe_parse_json` 和 `setdefault` 加进去。

然后**故意**把假模型的返回改成一段非法 JSON（比如 `"这不是JSON"`），运行，观察是否抛出了你写的清晰错误。

目标：理解为什么必须兜底，以及兜底怎么写。

---

### 练习 4：用 3 个需求测试

照着 1.7 节，用至少 3 个不同的真实需求测试你的 Agent，把输出记录到 `requirement_cases.md`。

目标：学会用多 case 检验 Agent 质量。

---

### 练习 5：对比"有约束"和"无约束"的 prompt

写两版 system prompt：
- A 版：明确要求"只返回 JSON"
- B 版：不写这句约束

分别测试，观察输出差异，记录到笔记。

目标：亲身体会"格式约束"对输出稳定性的影响。

---

## 4. JS/Node.js 类比

| 需求 Agent 概念 | Node.js 类比 | 说明 |
|---|---|---|
| 需求 Agent | 一个专职的转换 service | 输入口语 → 输出结构化 |
| system prompt | 函数的"契约/规格说明" | 定义输入输出行为 |
| 只返回 JSON | 接口永远返回 JSON | 便于下游解析 |
| json.loads | `JSON.parse` | 字符串 → 对象 |
| safe_parse | try/catch 包裹 JSON.parse | 防止解析崩溃 |
| setdefault 补字段 | 解构默认值 `{ x = [] }` | 缺字段兜底 |
| temperature 调低 | 配置项调稳 | 让输出更可控 |

小白重点：`json.loads` 之于 Python，等于 `JSON.parse` 之于 Node。两边都要用 try/catch（try/except）包住，因为**模型和用户一样，都可能给你脏数据**。

---

## 5. AI Review 提问

完成练习后，把你的 `requirement_agent.py` 和 3 个测试 case 贴给 AI，然后问：

```text
我正在学习 Week 20 Day 02：需求 Agent。

请你按资深后端工程师标准帮我检查：

1. 我的 system prompt 是否把角色、任务、输出格式都钉死了？
2. 我强制"只返回 JSON"的做法是否到位？
3. 我的 JSON 解析和兜底逻辑是否健壮？还有哪些异常没处理？
4. 我用"后端接口永远返回 JSON"来类比，准不准？
5. 如果是真实项目，需求 Agent 还需要注意什么（如敏感信息、超长输入）？

请用中文输出：
- 我做对的地方
- 我遗漏或有风险的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [✅] `REQUIREMENT_SYSTEM_PROMPT`（含三个钉子）
- [✅] `requirement_agent.py`（假模型版可运行）
- [✅] JSON 解析 + 兜底逻辑
- [✅] `requirement_cases.md`：3 个测试 case 及输出
- [✅] "有约束 vs 无约束" prompt 对比笔记
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [✅] 能说清需求 Agent 的职责（口语 → 结构化）
- [✅] 能写出含角色/任务/格式的 system prompt
- [✅] 能解释为什么要强制"只返回 JSON"
- [✅] 能用 `json.loads` 把返回解析成 dict
- [✅] 能写 JSON 解析兜底，防止程序崩溃
- [✅] 3 个测试 case 都能输出合理的结构化需求
- [✅] 能用 Node 的 JSON.parse / 接口约定做准确类比

---

## 8. 今日自测题

### 8.1 需求 Agent 的职责是什么？

参考答案：

> ✅ 把用户的一句话口语需求，翻译成字段清晰的结构化需求（title、roles、features、constraints、non_goals），供下游的架构 Agent 使用。它扮演产品经理的角色。

---

### 8.2 一个好的 system prompt 应该包含哪三部分？

参考答案：

```text
1. 角色钉子：你是谁（如"资深产品经理"）
2. 任务钉子：你要干什么（把口语拆成结构化需求）
3. 格式钉子：你要怎么输出（只返回 JSON，字段固定）
```

---

### 8.3 为什么要强制模型"只返回 JSON"？

参考答案：

> ✅ 因为下游 Agent 是程序，需要精准提取字段。纯 JSON 可以用 `json.loads` 一行解析；如果夹带客套话或自然语言，程序很难可靠提取，容易出错。

---

### 8.4 模型返回的不是合法 JSON，怎么办？

参考答案：

> ✅ 用 try/except 捕获 `json.JSONDecodeError`，抛出清晰的错误信息；再用 `setdefault` 给缺失字段补默认值。永远不要假设模型 100% 听话。

---

### 8.5 需求分析类任务，temperature 应该调高还是调低？为什么？

参考答案：

> ✅ 调低（0~0.3）。因为需求分析要求稳定、可复现，温度高会让模型更"发散/胡编"，不利于结构化输出的一致性。

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
我正在进行 Week 20 Day 02：需求 Agent 的学习。
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
