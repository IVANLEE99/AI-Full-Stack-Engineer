# Week 20 Day 01：三 Agent 架构

> 所属周：Week 20：Multi-Agent 工作流  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/multi-agent`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

画出"需求 → 架构 → Review"三 Agent 流水线，理解单 Agent 和多 Agent 的区别，定义每个 Agent 的输入输出格式。

今天你要真正掌握这一句话：

> 多 Agent 工作流就是把一个大任务拆成几个"专职角色"，每个 Agent 只干一件事，上一个 Agent 的输出当作下一个 Agent 的输入，像流水线一样把数据一站一站往下传；这就像 Node 里几个微服务串行调用，每个服务职责单一，通过消息（JSON）互相传数据。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先搞清楚什么是 Agent（智能体）
2. 理解单 Agent 的局限：一个 Prompt 干所有事会很乱
3. 理解多 Agent：拆角色、分职责
4. 认识我们本周的三个角色：需求 Agent、架构 Agent、Review Agent
5. 理解 Agent 之间怎么传消息（输入输出格式）
6. 画出三 Agent 流水线图
7. 用 Python 写一个"假的"三 Agent 骨架（不接真模型，先跑通流程）
8. 理解流水线编排（Pipeline / Orchestrator）是什么
9. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 什么是 Agent（智能体）

先别被"Agent"这个词吓到。

在我们本周的语境里，一个 **Agent** 就是：

> 一个"带了固定角色设定的 AI 调用单元"。你给它输入，它按照自己的角色（system prompt）处理，然后返回输出。

用最朴素的话说，一个 Agent = **一段角色说明（Prompt）+ 一次模型调用 + 结构化输出**。

举个生活里的例子：

- 你去餐厅点餐，服务员负责**记录你要什么**（需求 Agent）
- 后厨主厨负责**决定怎么做这道菜**（架构 Agent）
- 出餐前有人**检查这道菜有没有问题**（Review Agent）

每个人只干自己那一段，彼此配合，这就是多 Agent 协作。

小白重点：Agent 不是什么神秘的黑科技，本质就是"**一个函数**"，输入是文字，输出也是文字（通常是结构化的 JSON）。

```python
# 一个 Agent 最朴素的样子：就是一个函数
def some_agent(input_text: str) -> str:
    # 内部：拼一个 prompt，调一次模型，拿到结果
    result = "模型返回的内容"
    return result
```

对比 JS/Node：

```js
// Node 里也是一个函数
function someAgent(inputText) {
  const result = "模型返回的内容";
  return result;
}
```

| 对比项 | 说明 |
|---|---|
| Agent 本质 | 一个函数：输入文字 → 输出文字 |
| Agent 的"灵魂" | 它的角色设定（system prompt） |
| Agent 的输入 | 上一步的数据（通常是字符串或 JSON） |
| Agent 的输出 | 结构化结果（通常是 JSON） |

---

### 1.2 单 Agent 的局限

刚学 AI 应用时，大家都爱写一个"万能 Prompt"，想让一个 Agent 干所有事：

```text
你是一个全能助手。用户会给你一句话需求，
请你分析需求、设计架构、做代码 Review，然后输出完整报告。
```

这种做法在小任务上能用，但很快会出问题：

1. **职责混乱**：一个 Prompt 既要当产品经理，又要当架构师，还要当审查员，模型容易顾此失彼。
2. **难以调试**：输出错了，你不知道是"需求分析"错了，还是"架构设计"错了。
3. **难以复用**：想单独用"需求分析"的能力？没法拆出来。
4. **输出不稳定**：任务越多，模型越容易漏掉某一步。

这就像后端里一个 3000 行的"上帝函数"：什么都干，谁也不敢改。

```python
# 反例：一个 Agent 想干所有事（不推荐）
def god_agent(user_sentence: str) -> str:
    prompt = f"""
    你是全能助手，请对下面这句需求做：
    1. 需求分析
    2. 架构设计
    3. 代码 Review
    需求：{user_sentence}
    """
    # 一次调用干三件事，结果往往含糊、容易漏步骤
    return call_model(prompt)
```

小白重点：一个 Agent 干太多事，就像一个员工同时当 PM、架构师、审查员，累且容易出错。**拆开**才是正解。

---

### 1.3 多 Agent：拆角色、分职责

多 Agent 的核心思想就四个字：**分而治之**。

把大任务拆成几个小角色，每个角色只干一件事，然后按顺序串起来。

我们本周要做的就是这个经典的三 Agent 流水线：

```text
一句话需求
    │
    ▼
┌─────────────┐
│ 需求 Agent   │  把口语需求 → 结构化需求
│ (PM 角色)    │
└─────────────┘
    │ 结构化需求(JSON)
    ▼
┌─────────────┐
│ 架构 Agent   │  结构化需求 → 架构建议
│ (架构师角色)  │
└─────────────┘
    │ 架构建议(JSON)
    ▼
┌─────────────┐
│ Review Agent │  架构建议 → 风险与改进建议
│ (审查员角色)  │
└─────────────┘
    │
    ▼
最终 Review 报告
```

每个 Agent 的职责非常清晰：

| Agent | 角色类比 | 输入 | 输出 |
|---|---|---|---|
| 需求 Agent | 产品经理 PM | 一句话需求 | 结构化需求（功能点、角色、边界） |
| 架构 Agent | 架构师 | 结构化需求 | 架构建议（模块、API、数据流） |
| Review Agent | 技术评审 / Code Reviewer | 架构建议 | 风险点、改进建议、评分 |

小白重点：**每个 Agent 只关心自己的输入和输出**，它不需要知道整条流水线长什么样。这跟微服务的"单一职责"是一模一样的道理。

---

### 1.4 Agent 之间怎么传消息（输入输出格式）

Agent 之间要能配合，就必须**约定好数据格式**。

这就像后端两个服务之间要约定接口字段一样。我们统一用 **JSON** 作为 Agent 之间传递的"消息格式"。

需求 Agent 的输出（也就是架构 Agent 的输入），可以约定成这样：

```json
{
  "title": "用户登录功能",
  "roles": ["普通用户", "管理员"],
  "features": [
    "手机号 + 验证码登录",
    "登录失败限流",
    "登录成功返回 token"
  ],
  "non_goals": ["暂不支持第三方登录"],
  "constraints": ["响应时间 < 500ms"]
}
```

架构 Agent 的输出（也就是 Review Agent 的输入），可以约定成这样：

```json
{
  "modules": ["AuthController", "SmsService", "TokenService"],
  "apis": [
    {"method": "POST", "path": "/login/sms/send", "desc": "发送验证码"},
    {"method": "POST", "path": "/login/sms/verify", "desc": "校验并登录"}
  ],
  "data_flow": "用户 → Controller → SmsService → Redis → TokenService",
  "storage": ["Redis 存验证码", "MySQL 存用户"]
}
```

为什么一定要用结构化的 JSON，而不是一大段自然语言？

| 传递方式 | 优点 | 缺点 |
|---|---|---|
| 一大段自然语言 | 写起来简单 | 下一个 Agent 难以精准提取字段，容易理解偏差 |
| 结构化 JSON | 字段清晰、可校验、可程序化处理 | 需要约定格式、需要解析 |

小白重点：**Agent 之间用 JSON 传消息**，就像后端接口之间用 JSON 传数据。约定好字段，双方才能配合。

对比 JS/Node：

```js
// Node 服务之间也是传 JSON
const requirement = {
  title: "用户登录功能",
  features: ["手机号 + 验证码登录"],
};
// 序列化后通过 HTTP / 消息队列传给下一个服务
const body = JSON.stringify(requirement);
```

---

### 1.5 用 Python 写一个"假的"三 Agent 骨架

第一天我们**先不接真模型**，用假数据把整条流水线跑通。这样你能先看清"数据怎么流动"，明白了流程再去接模型就简单了。

新建 `pipeline_demo.py`：

```python
# pipeline_demo.py
# 目标：先不接真模型，用假数据跑通三 Agent 流水线

def requirement_agent(user_sentence: str) -> dict:
    """需求 Agent：一句话 → 结构化需求（这里先返回假数据）"""
    print(f"[需求Agent] 收到输入：{user_sentence}")
    return {
        "title": "用户登录功能",
        "roles": ["普通用户"],
        "features": ["手机号 + 验证码登录", "登录失败限流"],
        "non_goals": ["暂不支持第三方登录"],
    }


def architecture_agent(requirement: dict) -> dict:
    """架构 Agent：结构化需求 → 架构建议（这里先返回假数据）"""
    print(f"[架构Agent] 收到需求：{requirement['title']}")
    return {
        "modules": ["AuthController", "SmsService", "TokenService"],
        "apis": [
            {"method": "POST", "path": "/login/sms/send"},
            {"method": "POST", "path": "/login/sms/verify"},
        ],
        "data_flow": "用户 → Controller → SmsService → Redis → TokenService",
    }


def review_agent(architecture: dict) -> dict:
    """Review Agent：架构建议 → 风险与建议（这里先返回假数据）"""
    print(f"[ReviewAgent] 收到架构，模块数：{len(architecture['modules'])}")
    return {
        "risks": ["验证码未设置过期时间", "缺少限流细节"],
        "suggestions": ["Redis 验证码设置 5 分钟过期", "同一手机号 60s 限流一次"],
        "score": 75,
    }


def run_pipeline(user_sentence: str) -> dict:
    """流水线编排：把三个 Agent 串起来"""
    requirement = requirement_agent(user_sentence)
    architecture = architecture_agent(requirement)
    review = review_agent(architecture)
    return review


if __name__ == "__main__":
    result = run_pipeline("我想做一个手机号登录功能")
    print("\n最终 Review 报告：")
    print(result)
```

运行：

```bash
python pipeline_demo.py
```

你会看到类似输出：

```text
[需求Agent] 收到输入：我想做一个手机号登录功能
[架构Agent] 收到需求：用户登录功能
[ReviewAgent] 收到架构，模块数：3

最终 Review 报告：
{'risks': ['验证码未设置过期时间', '缺少限流细节'], 'suggestions': [...], 'score': 75}
```

小白重点：看到没？三个函数（Agent）被 `run_pipeline` 串起来，数据一站一站往下传。**这就是多 Agent 流水线的骨架**。今天你只要理解这个"数据流动"就成功了。

---

### 1.6 理解流水线编排（Orchestrator）

上面那个 `run_pipeline` 函数就是**编排器（Orchestrator）**。

编排器的职责：

1. 决定 Agent 的**执行顺序**
2. 把上一个 Agent 的输出**交给**下一个 Agent
3. （进阶）处理错误、重试、记录日志

```text
Orchestrator（编排器）
   │
   ├─ 调 需求Agent，拿到 requirement
   ├─ 调 架构Agent(requirement)，拿到 architecture
   └─ 调 ReviewAgent(architecture)，拿到 review
```

小白重点：Agent 是"干活的人"，Orchestrator 是"调度员"。调度员自己不干活，只负责按顺序喊人干活、传材料。

对比 JS/Node：

```js
// Node 里的编排：本质就是把几个 async 函数按顺序 await
async function runPipeline(sentence) {
  const requirement = await requirementAgent(sentence);
  const architecture = await architectureAgent(requirement);
  const review = await reviewAgent(architecture);
  return review;
}
```

| 概念 | 本周 Python 实现 | Node 类比 |
|---|---|---|
| Agent | 一个函数 | 一个 service 函数 |
| 消息 | dict / JSON | JSON body |
| Orchestrator | `run_pipeline` | 串行 await 的调度函数 |
| 流水线 | 需求→架构→Review | 微服务串行调用链 |

---

### 1.7 串行 vs 并行编排（了解即可）

我们本周用的是**串行**流水线：一个接一个，因为架构 Agent 必须等需求 Agent 出结果才能动。

但多 Agent 也可以**并行**：几个互不依赖的 Agent 同时干活。

```text
串行（本周用）：
需求Agent → 架构Agent → ReviewAgent

并行（了解）：
              ┌→ 安全审查Agent ┐
架构Agent →   ├→ 性能审查Agent ┤ → 汇总
              └→ 可读性Agent   ┘
```

| 编排方式 | 适用场景 | 类比 |
|---|---|---|
| 串行 | 后一步依赖前一步的结果 | 流水线、`await` 链 |
| 并行 | 几个任务互相独立 | `Promise.all` |

小白重点：先掌握串行。本周流水线里每一步都依赖上一步，所以必须串行。并行留到以后。

---

## 2. 源码阅读

- `ai-lab/multi-agent/pipeline_demo.py`（你今天自己写的）

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. 每个 Agent 函数的**输入类型**和**输出类型**
2. `run_pipeline` 里数据是如何**一站一站传递**的
3. 如果某个 Agent 返回的字段名对不上，下一步会怎样

建议你在笔记里写出类似表格：

| Agent | 输入 | 输出 | 对应角色 |
|---|---|---|---|
| requirement_agent | 一句话字符串 | 需求 dict | PM |
| architecture_agent | 需求 dict | 架构 dict | 架构师 |
| review_agent | 架构 dict | Review dict | 审查员 |

---

## 3. 练习任务

### 练习 1：画出三 Agent 流水线图

在纸上或用任意画图工具，画出：

```text
一句话需求 → [需求Agent] → 结构化需求 → [架构Agent] → 架构建议 → [ReviewAgent] → Review报告
```

要求在每个箭头上标注**传递的数据格式**（是字符串还是 JSON，有哪些字段）。

目标：能一眼看出"数据在流水线上怎么流动"。

---

### 练习 2：定义三个 Agent 的输入输出格式

新建 `contracts.md`，写下三个 Agent 之间的数据约定：

```text
需求Agent 输出（架构Agent 输入）：
{ title, roles[], features[], non_goals[], constraints[] }

架构Agent 输出（ReviewAgent 输入）：
{ modules[], apis[{method, path, desc}], data_flow, storage[] }

ReviewAgent 输出（最终报告）：
{ risks[], suggestions[], score }
```

目标：像定义接口一样，把 Agent 之间的"消息格式"固定下来。

---

### 练习 3：跑通假数据流水线

照着 1.5 节把 `pipeline_demo.py` 敲一遍并运行。

然后做一个小改动：给 `review_agent` 的返回值加一个字段 `"reviewer": "AI"`，重新运行，观察最终报告是否包含这个新字段。

目标：亲手体验"改一个 Agent 的输出，最终结果就变了"。

---

### 练习 4：给流水线加一行日志

在 `run_pipeline` 里，每调用完一个 Agent，就打印一行"当前进度"，例如：

```python
def run_pipeline(user_sentence: str) -> dict:
    requirement = requirement_agent(user_sentence)
    print("✅ 需求分析完成")
    architecture = architecture_agent(requirement)
    print("✅ 架构设计完成")
    review = review_agent(architecture)
    print("✅ Review 完成")
    return review
```

目标：理解 Orchestrator 除了传数据，还能**记录流程进度**。

---

### 练习 5：列出单 Agent vs 多 Agent 对比 5 条

| # | 维度 | 单 Agent | 多 Agent |
|---|---|---|---|
| 1 | 职责 | 一个 Prompt 干所有 | 每个 Agent 只干一件 |
| 2 | 调试 | 出错不知道哪一步 | 能定位到具体 Agent |
| 3 | 复用 | 难拆分 | 每个 Agent 可单独用 |
| 4 | 稳定性 | 任务多易漏步骤 | 每步聚焦更稳 |
| 5 | 维护 | 像上帝函数 | 像微服务 |

目标：能用自己的话讲清楚"为什么要拆多 Agent"。

---

## 4. JS/Node.js 类比

| 多 Agent 概念 | Node.js 类比 | 说明 |
|---|---|---|
| Agent | 一个单一职责的 service 函数 | 只干一件事 |
| 多 Agent 流水线 | 微服务串行调用链 | 一个接一个 |
| Agent 间消息 | 服务间传的 JSON body | 约定字段 |
| Orchestrator | 调度/编排函数（串行 await） | 只调度不干活 |
| 串行编排 | 顺序 `await` | 后一步依赖前一步 |
| 并行编排 | `Promise.all` | 多个独立任务同时跑 |
| 结构化输出 | 接口返回的 JSON schema | 便于下游解析 |

小白重点：如果你熟悉 Node 里"几个微服务串行调用、彼此传 JSON"，那多 Agent 流水线你**已经懂一半了**，剩下的只是把"服务"换成"带角色的 AI 调用"。

---

## 5. AI Review 提问

完成练习后，把你的流水线图和 `pipeline_demo.py` 贴给 AI，然后问：

```text
我正在学习 Week 20 Day 01：三 Agent 架构。

请你按资深后端工程师标准帮我检查：

1. 我对"单 Agent vs 多 Agent"的理解是否正确？
2. 我定义的三个 Agent 输入输出格式是否合理？字段有没有遗漏？
3. 我画的流水线数据流动对不对？
4. 我用 Node 微服务串行来类比多 Agent，准不准？
5. 如果这是真实项目，编排器还需要考虑哪些（错误、重试、日志）？

请用中文输出：
- 我理解正确的地方
- 我理解错误或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [✅] 三 Agent 流水线图（标注了数据格式）
- [✅] `contracts.md`：三个 Agent 的输入输出约定
- [✅] `pipeline_demo.py`：可运行的假数据流水线
- [✅] 给流水线加了进度日志
- [✅] 单 Agent vs 多 Agent 对比 5 条
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [✅] 能用一句话解释什么是 Agent
- [✅] 能说出单 Agent 的至少 3 个局限
- [✅] 能说清三个 Agent 分别是什么角色、输入输出是什么
- [✅] 能解释 Agent 之间为什么用 JSON 传消息
- [✅] 能跑通假数据的三 Agent 流水线
- [✅] 能解释 Orchestrator（编排器）的职责
- [✅] 能说清串行和并行编排的区别
- [✅] 能用 Node 微服务做出准确类比

---

## 8. 今日自测题

### 8.1 一个 Agent 的本质是什么？

参考答案：

> ✅ 本质就是一个函数：输入文字，按自己的角色设定（system prompt）处理，输出（通常是结构化 JSON）。角色设定是它的"灵魂"。

---

### 8.2 为什么不用一个"万能 Agent"干所有事？

参考答案：

> ✅ 因为职责混乱、难以调试（出错不知哪一步）、难以复用、任务多时容易漏步骤。拆成多个单一职责的 Agent 更清晰、更稳定，就像后端拆微服务而不是写上帝函数。

---

### 8.3 三个 Agent 分别对应什么角色？

参考答案：

```text
需求 Agent ≈ 产品经理（一句话 → 结构化需求）
架构 Agent ≈ 架构师（需求 → 架构建议）
Review Agent ≈ 技术评审（架构 → 风险与建议）
```

---

### 8.4 Agent 之间为什么用 JSON 传消息？

参考答案：

> ✅ 因为 JSON 是结构化的，字段清晰、可校验、下游 Agent 能精准提取需要的字段。如果用一大段自然语言，下一个 Agent 容易理解偏差、难以程序化处理。

---

### 8.5 Orchestrator（编排器）和 Agent 有什么区别？

参考答案：

> ✅ Agent 是"干活的人"，负责处理自己那一段任务；Orchestrator 是"调度员"，自己不干具体活，只负责决定执行顺序、把上一步输出传给下一步、以及处理错误/日志。

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
我正在进行 Week 20 Day 01：三 Agent 架构 的学习。
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
