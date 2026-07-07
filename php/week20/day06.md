# Week 20 Day 06：Multi-Agent Demo

> 所属周：Week 20：Multi-Agent 工作流  
> 阶段：第五阶段：RAG + 企业知识库  
> 主仓库/项目：`ai-lab/multi-agent`  
> 类型：项目实战  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

把这一周的需求 Agent、架构 Agent、Review Agent 和错误处理全部整合，做一个**能跑的 Multi-Agent Demo**：输入一句话需求，输出一份完整的 Review 报告。

今天你要真正掌握这一句话：

> 一个真正能用的 Multi-Agent Demo，不只是"把三个 Agent 串起来"，而是要有统一入口、清晰输出、错误兜底、以及可复现的样例输入输出——这样别人拿到你的 Demo，一跑就懂。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 回顾这一周做过的三个 Agent 和编排器
2. 设计 Demo 的统一入口和输出格式
3. 整合三个 Agent + 错误处理
4. 写一个 CLI 入口（命令行输入需求）
5. 把最终报告格式化成人类可读的文本
6. 准备 2-3 组样例输入输出
7. 跑通并截图/保存输出
8. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 Demo 的整体结构

一周下来，我们已经有这些零件：

```text
requirement_agent.py   —— 一句话 → 结构化需求
architecture_agent.py  —— 需求 → 架构建议
review_agent.py        —— 架构 → 风险与建议
safe_agent.py          —— 重试/兜底工具
orchestrator_*.py      —— 编排器
```

今天把它们组装成一个入口：

```text
demo.py  ← 用户只跑这一个文件
```

小白重点：Demo 的价值在于"一个入口跑通全部"。零件再多，用户也只想执行一条命令。

---

### 1.2 设计统一输出格式

三个 Agent 各自输出一个 dict，Demo 要把它们汇总成一份报告：

```python
{
    "input": "我想做一个宠物领养平台",
    "requirement": { ... },   # 需求 Agent 输出
    "architecture": { ... },  # 架构 Agent 输出
    "review": { ... },        # Review Agent 输出
    "status": "success"       # 或 partial / failed
}
```

小白重点：加一个 `status` 字段，让调用方一眼知道是全成功、部分成功还是失败——这是产品级 API 的习惯。

---

### 1.3 整合成 Demo 主逻辑

复用 Day05 的降级编排器，包一层报告组装。

```python
# demo.py
import json
from orchestrator_graceful import run_chain_graceful


def run_demo(user_sentence: str) -> dict:
    """Multi-Agent Demo 主入口：一句话 → 完整报告"""
    chain = run_chain_graceful(user_sentence)

    # 判断整体状态
    failed = any(
        isinstance(v, dict) and "_error" in v
        for v in chain.values()
    )
    status = "partial" if failed else "success"
    if "requirement" in chain and "_error" in chain.get("requirement", {}):
        status = "failed"  # 第一站就挂，等于整体失败

    return {
        "input": user_sentence,
        "requirement": chain.get("requirement"),
        "architecture": chain.get("architecture"),
        "review": chain.get("review"),
        "status": status,
    }
```

小白重点：Demo 主逻辑只做两件事——调用编排器、组装报告。真正的活儿都在各个 Agent 里，这体现了"编排层薄、执行层厚"的分层思想。

---

### 1.4 把报告格式化成人类可读

dict 直接打印很难看。写一个格式化函数，输出漂亮的文本报告。

```python
# report.py


def format_report(result: dict) -> str:
    lines = []
    lines.append("=" * 50)
    lines.append(f"需求输入：{result['input']}")
    lines.append(f"处理状态：{result['status']}")
    lines.append("=" * 50)

    req = result.get("requirement") or {}
    lines.append("\n【一、结构化需求】")
    lines.append(f"  项目：{req.get('project_name', '-')}")
    for f in req.get("features", []):
        lines.append(f"  - 功能：{f}")

    arch = result.get("architecture") or {}
    lines.append("\n【二、架构建议】")
    for api in arch.get("apis", []):
        lines.append(f"  - 接口：{api}")

    review = result.get("review") or {}
    lines.append("\n【三、Review 报告】")
    for risk in review.get("risks", []):
        lines.append(f"  - 风险：{risk}")
    for sug in review.get("suggestions", []):
        lines.append(f"  - 建议：{sug}")

    lines.append("\n" + "=" * 50)
    return "\n".join(lines)
```

小白重点：`.get(key, default)` 到处用，是因为降级时某些字段可能不存在，用 get 才不会崩。这是处理"可能缺字段"数据的标准姿势。

---

### 1.5 写 CLI 入口

让用户在命令行直接输入需求。

```python
# main.py
import sys
from demo import run_demo
from report import format_report


def main():
    if len(sys.argv) > 1:
        sentence = " ".join(sys.argv[1:])
    else:
        sentence = input("请输入一句话需求：").strip()

    if not sentence:
        print("需求不能为空")
        return

    print("\n正在运行 Multi-Agent 流水线，请稍候 ...\n")
    result = run_demo(sentence)
    print(format_report(result))


if __name__ == "__main__":
    main()
```

运行方式：

```bash
python main.py 我想做一个宠物领养平台
```

或者：

```bash
python main.py
# 然后按提示输入
```

小白重点：`if __name__ == "__main__":` 是 Python 的"程序入口"写法，类似 Node 里判断 `require.main === module`。它让文件既能被直接运行、又能被别的文件 import。

---

### 1.6 准备样例输入输出

好 Demo 一定要带"样例"，别人不用动脑就能验证。准备 2-3 组：

**样例 1 输入：**

```text
我想做一个宠物领养平台
```

**样例 1 输出（节选）：**

```text
==================================================
需求输入：我想做一个宠物领养平台
处理状态：success
==================================================

【一、结构化需求】
  项目：宠物领养平台
  - 功能：宠物信息发布
  - 功能：领养申请
  - 功能：审核流程

【二、架构建议】
  - 接口：POST /api/pets
  - 接口：POST /api/adoptions

【三、Review 报告】
  - 风险：缺少领养方资质审核
  - 建议：增加实名认证和黑名单机制
==================================================
```

小白重点：把样例存成文件（如 `examples/pet.txt`），是让 Demo"可复现"的关键。面试或演示时，直接跑样例，稳。

---

### 1.7 完整目录结构

整理后的 Demo 项目：

```text
ai-lab/multi-agent/
├── requirement_agent.py   # 需求 Agent
├── architecture_agent.py  # 架构 Agent
├── review_agent.py        # Review Agent
├── safe_agent.py          # 重试/兜底工具
├── orchestrator_graceful.py  # 降级编排器
├── demo.py                # Demo 主逻辑
├── report.py              # 报告格式化
├── main.py                # CLI 入口
└── examples/              # 样例输入输出
    ├── pet.txt
    └── mall.txt
```

小白重点：这个目录结构清晰体现了分层——Agent 层、编排层、展示层、入口层各司其职。就像 Node 项目分 controller / service / util。

---

## 2. 源码阅读

- `ai-lab/multi-agent/demo.py`（你今天写的）
- `ai-lab/multi-agent/main.py`（你今天写的）
- `ai-lab/multi-agent/report.py`（你今天写的）

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. `run_demo` 是如何判断 status 的
2. `format_report` 为什么大量用 `.get(..., default)`
3. CLI 入口如何同时支持命令行参数和交互输入
4. 各文件之间的依赖方向（入口 → 展示 → 编排 → Agent）

建议你在笔记里写出类似表格：

| 文件 | 层次 | 职责 |
|---|---|---|
| main.py | 入口层 | 接收用户输入 |
| report.py | 展示层 | 格式化报告 |
| demo.py | 编排层 | 组装流水线结果 |
| *_agent.py | 执行层 | 具体 Agent 逻辑 |

---

## 3. 练习任务

### 练习 1：整合 Demo 主逻辑

照着 1.3 写 `demo.py`，确保能返回带 `status` 的完整报告 dict。

目标：把一周的零件组装成一个入口。

---

### 练习 2：写报告格式化

照着 1.4 写 `format_report`，跑一个需求，看输出是否清晰易读。

目标：把机器可读的 dict 变成人类可读的报告。

---

### 练习 3：写 CLI 入口

照着 1.5 写 `main.py`，用 `python main.py 你的需求` 跑通。

目标：让 Demo 有一个真正的运行入口。

---

### 练习 4：准备样例

准备 2-3 组样例输入，存到 `examples/` 下，每组带输入和期望输出。

目标：让 Demo 可复现、可演示。

---

### 练习 5：跑一个会触发降级的输入

故意让某一站失败（比如手动改坏 Review Agent），确认 Demo 输出 `status: partial` 而不是整个崩溃。

目标：验证错误处理在 Demo 里真的生效。

---

## 4. JS/Node.js 类比

| Demo 概念 | Node.js 类比 | 说明 |
|---|---|---|
| main.py 入口 | `index.js` / `cli.js` | 程序启动入口 |
| `if __name__ == "__main__"` | `require.main === module` | 判断是否被直接运行 |
| demo.py 组装结果 | controller 汇总 service 结果 | 编排层 |
| format_report | 视图层 / 模板渲染 | 把数据变展示 |
| status 字段 | HTTP 响应里的 code/status | 让调用方判断成败 |
| examples/ 样例 | `__fixtures__` / 示例请求 | 可复现的测试数据 |

小白重点：这个 Demo 的分层，几乎和一个小型 Node 后端一模一样——入口收请求、controller 编排、service 干活、view 展示。多 Agent 系统本质上就是"用 Agent 当 service 的后端"。

---

## 5. AI Review 提问

完成 Demo 后，把 `demo.py`、`main.py`、样例输出贴给 AI，然后问：

```text
我正在学习 Week 20 Day 06：整合一个能跑的 Multi-Agent Demo。

请你按资深后端工程师标准帮我检查：

1. 我的 Demo 分层（入口/编排/执行/展示）合理吗？
2. status 字段的判定逻辑（success/partial/failed）对不对？
3. format_report 用 .get 兜底缺字段，够稳吗？
4. 我的样例输入输出，能让别人一跑就懂吗？还差什么？
5. 我用"小型 Node 后端"来类比这个 Demo 的分层，准不准？
6. 如果要把这个 Demo 变成真实产品，还差哪些（并发、日志、配置、测试）？

请用中文输出：
- 我做对的地方
- 我遗漏或有风险的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [✅] `demo.py`（Demo 主逻辑）
- [✅] `report.py`（报告格式化）
- [✅] `main.py`（CLI 入口）
- [✅] `examples/` 下 2-3 组样例
- [✅] 完整目录结构整理
- [✅] 一次降级场景的验证记录
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [✅] 能用一条命令跑通整个 Demo
- [✅] 输入一句话需求，输出完整 Review 报告
- [✅] 报告人类可读、分三段（需求/架构/Review）
- [✅] 有 status 字段区分成败
- [✅] 准备好可复现的样例输入输出
- [✅] 降级场景下 Demo 不整体崩溃
- [✅] 能说清 Demo 的分层与 Node 后端的类比

---

## 8. 今日自测题

### 8.1 一个"能用的 Demo"和"几个 Agent 堆在一起"差别在哪？

参考答案：

> ✅ 能用的 Demo 有统一入口（一条命令跑通）、清晰输出（人类可读报告）、错误兜底（降级不崩）、可复现样例（别人一跑就懂）。只是把 Agent 堆一起，缺入口、缺展示、缺兜底，别人拿到跑不起来。

---

### 8.2 `status` 字段为什么重要？

参考答案：

> ✅ 它让调用方一眼知道是全成功、部分成功还是失败，不用去逐字段判断。这是产品级 API 的习惯，类似 HTTP 响应里的状态码。

---

### 8.3 `format_report` 里为什么到处用 `.get(key, default)`？

参考答案：

> ✅ 因为降级时某些字段（如 review）可能不存在或是错误对象。直接用 `dict[key]` 会 KeyError，用 `.get` 带默认值才不会崩，这是处理"可能缺字段"数据的标准做法。

---

### 8.4 `if __name__ == "__main__":` 的作用是什么？

参考答案：

> ✅ 它让文件既能被直接运行（作为入口），又能被别的文件 import 而不触发主逻辑。类似 Node 里 `require.main === module` 的判断。

---

### 8.5 这个 Demo 的分层和 Node 后端有什么对应？

参考答案：

> ✅ main.py=入口(收请求)、demo.py=controller(编排)、*_agent.py=service(干活)、report.py=view(展示)。多 Agent 系统本质就是"用 Agent 当 service 的后端"。

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
我正在进行 Week 20 Day 06：Multi-Agent Demo 的学习。
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
