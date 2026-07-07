# Week 13 Day 02：多模型 /chat 接口

> 所属周：Week 13：FastAPI + LLM Gateway  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`ai-lab/llm-gateway`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

接入 OpenAI 与 Claude。

今天你要真正掌握这一句话：

> 不同大模型的 API 参数和返回结构不一样，但都能抽象成「传入一段对话消息 → 返回一段回复文本」；Gateway 的核心就是用一个统一的 `/chat` 接口 + 一个 `model` 参数，把请求「路由」到对应供应商，再把各家不同的返回值「归一化」成同一种格式。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 理解「聊天补全」（chat completion）的通用模型
2. 理解 messages 数组和 role（system/user/assistant）
3. 看懂 OpenAI 请求/响应长什么样
4. 看懂 Claude（Anthropic）请求/响应长什么样
5. 对比两家差异，找出「共同点」
6. 设计统一的 `/chat` 请求和响应格式
7. 用「适配器」思路给每家写一个 provider 函数
8. 用 `model` 参数做路由分发
9. 用 curl 测两个模型
10. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 理解「聊天补全」的通用模型

现代大模型对话 API 基本都是一个套路：你把「一段对话历史」发过去，模型「续写」出下一句（assistant 的回复）。

对话历史是一个消息数组，每条消息有一个 `role`：

```json
[
  {"role": "system", "content": "你是一个乐于助人的助手"},
  {"role": "user", "content": "你好"},
  {"role": "assistant", "content": "你好，有什么可以帮你的？"},
  {"role": "user", "content": "帮我写一句诗"}
]
```

三种常见角色：

| role | 含义 | 类比 |
|---|---|---|
| `system` | 设定助手的身份/规则 | 全局配置/人设 |
| `user` | 用户说的话 | 请求方 |
| `assistant` | 模型之前的回复 | 上下文记忆 |

小白重点：模型本身「没有记忆」。所谓「多轮对话」，其实是每次请求都把之前的全部消息再发一遍。这一点和 HTTP 无状态很像——状态得你自己带上。

---

### 1.2 OpenAI 的请求与响应长什么样

OpenAI Chat Completions 请求（示意）：

```json
POST https://api.openai.com/v1/chat/completions
Authorization: Bearer <OPENAI_API_KEY>
Content-Type: application/json

{
  "model": "gpt-4o-mini",
  "messages": [
    {"role": "user", "content": "你好"}
  ]
}
```

响应（示意，简化）：

```json
{
  "id": "chatcmpl-xxx",
  "choices": [
    {
      "index": 0,
      "message": {"role": "assistant", "content": "你好！"}
    }
  ],
  "usage": {"prompt_tokens": 8, "completion_tokens": 3, "total_tokens": 11}
}
```

关键：回复文本在 `choices[0].message.content`。

---

### 1.3 Claude（Anthropic）的请求与响应长什么样

Claude Messages API 请求（示意）：

```json
POST https://api.anthropic.com/v1/messages
x-api-key: <ANTHROPIC_API_KEY>
anthropic-version: 2023-06-01
Content-Type: application/json

{
  "model": "claude-3-5-sonnet-latest",
  "max_tokens": 1024,
  "messages": [
    {"role": "user", "content": "你好"}
  ]
}
```

响应（示意，简化）：

```json
{
  "id": "msg_xxx",
  "content": [
    {"type": "text", "text": "你好！"}
  ],
  "usage": {"input_tokens": 8, "output_tokens": 3}
}
```

关键：回复文本在 `content[0].text`。

---

### 1.4 对比两家，找共同点

| 对比项 | OpenAI | Claude (Anthropic) |
|---|---|---|
| 鉴权头 | `Authorization: Bearer xxx` | `x-api-key: xxx` |
| 额外必填头 | 无 | `anthropic-version` |
| `max_tokens` | 可选 | **必填** |
| system 提示 | 放在 messages 里 | 单独的 `system` 字段 |
| 回复位置 | `choices[0].message.content` | `content[0].text` |
| token 字段 | `prompt/completion_tokens` | `input/output_tokens` |

小白重点：虽然细节不同，但「传 messages、拿回复文本」这个骨架是共通的。这正是我们能做统一 Gateway 的前提。

这和 PHP 里做支付网关一模一样：微信和支付宝签名方式、字段名都不同，但「发起支付、拿到支付结果」的骨架一致，于是能抽象成统一接口。

---

### 1.5 设计统一的 `/chat` 请求 / 响应格式

我们自己定义 Gateway 的对外契约，不暴露各家差异。

统一请求：

```json
POST /chat
{
  "model": "gpt-4o-mini",
  "messages": [
    {"role": "user", "content": "你好"}
  ]
}
```

统一响应：

```json
{
  "model": "gpt-4o-mini",
  "reply": "你好！",
  "usage": {"input_tokens": 8, "output_tokens": 3}
}
```

小白重点：不管底层是 OpenAI 还是 Claude，业务方拿到的永远是 `reply` 这一个字段。这就是「归一化」的价值——供应商换了，业务代码不用改。

---

### 1.6 用「适配器」思路给每家写 provider

新建 `app/providers.py`。这里用真实 SDK（`openai`、`anthropic`）演示。先装依赖：

```bash
pip install openai anthropic
pip freeze > requirements.txt
```

`app/providers.py`：

```python
import os
from openai import OpenAI
from anthropic import Anthropic


def call_openai(model: str, messages: list[dict]) -> dict:
    client = OpenAI(api_key=os.environ["OPENAI_API_KEY"])
    resp = client.chat.completions.create(
        model=model,
        messages=messages,
    )
    return {
        "reply": resp.choices[0].message.content,
        "usage": {
            "input_tokens": resp.usage.prompt_tokens,
            "output_tokens": resp.usage.completion_tokens,
        },
    }


def call_claude(model: str, messages: list[dict]) -> dict:
    client = Anthropic(api_key=os.environ["ANTHROPIC_API_KEY"])
    resp = client.messages.create(
        model=model,
        max_tokens=1024,
        messages=messages,
    )
    return {
        "reply": resp.content[0].text,
        "usage": {
            "input_tokens": resp.usage.input_tokens,
            "output_tokens": resp.usage.output_tokens,
        },
    }
```

小白重点：每个 provider 函数「输入统一（model + messages）、输出统一（reply + usage）」，把各家 SDK 的差异都关在函数内部。这就是适配器模式。

Node 里等价的思路：

```js
async function callOpenAI(model, messages) { /* ... */ return { reply, usage }; }
async function callClaude(model, messages) { /* ... */ return { reply, usage }; }
```

---

### 1.7 用 `model` 参数做路由分发

在 `app/main.py` 里加 `/chat`：

```python
import os
from fastapi import FastAPI, Request
from app.providers import call_openai, call_claude

app = FastAPI()

# 简单的模型 -> 供应商映射
OPENAI_MODELS = {"gpt-4o-mini", "gpt-4o"}
CLAUDE_MODELS = {"claude-3-5-sonnet-latest", "claude-3-5-haiku-latest"}


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/chat")
async def chat(payload: dict):
    model = payload["model"]
    messages = payload["messages"]

    if model in OPENAI_MODELS:
        result = call_openai(model, messages)
    elif model in CLAUDE_MODELS:
        result = call_claude(model, messages)
    else:
        return {"error": f"unknown model: {model}"}

    return {
        "model": model,
        "reply": result["reply"],
        "usage": result["usage"],
    }
```

小白重点：这里先用 `payload: dict` 收请求，能跑就行。明天 Day 03 会用 Pydantic 把它换成有校验的 `ChatRequest`，比现在这种「直接下标取值、非法请求会 500」健壮得多。

设置密钥（先临时用环境变量，明天讲更规范的做法）：

```bash
export OPENAI_API_KEY="sk-..."
export ANTHROPIC_API_KEY="sk-ant-..."
```

小白重点：密钥绝不能写死在代码里，也不能提交到 git。`.env` 已经在昨天的 `.gitignore` 里。

---

### 1.8 用 curl 测两个模型

启动服务：

```bash
uvicorn app.main:app --reload
```

测 OpenAI：

```bash
curl -X POST http://127.0.0.1:8000/chat \
  -H "Content-Type: application/json" \
  -d '{"model":"gpt-4o-mini","messages":[{"role":"user","content":"你好"}]}'
```

测 Claude：

```bash
curl -X POST http://127.0.0.1:8000/chat \
  -H "Content-Type: application/json" \
  -d '{"model":"claude-3-5-sonnet-latest","messages":[{"role":"user","content":"你好"}]}'
```

两次都应返回结构一致的：

```json
{"model":"...","reply":"...","usage":{"input_tokens":..,"output_tokens":..}}
```

小白重点：没有真实密钥也没关系——重点是理解「同一个 `/chat`，靠 `model` 分流到不同供应商，返回统一格式」。你可以先把 provider 函数换成写死返回假数据（mock），把整条链路跑通。

Mock 版 provider（无需真实密钥即可测试）：

```python
def call_openai(model, messages):
    last = messages[-1]["content"]
    return {"reply": f"[openai mock] 收到：{last}",
            "usage": {"input_tokens": 0, "output_tokens": 0}}
```

---

## 2. 源码阅读

- `ai-lab/llm-gateway/app/providers.py`
- `ai-lab/llm-gateway/app/main.py`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. 每个 provider 函数的「输入」和「输出」是否统一
2. 各家 SDK 的差异是不是都被关在函数内部
3. `/chat` 里靠什么字段决定调哪家（路由分发）
4. 归一化后对外返回的字段有哪些
5. 未知 model 时怎么处理

建议在笔记里补全这张适配表：

| 项目 | OpenAI 内部 | Claude 内部 | 对外统一 |
|---|---|---|---|
| 回复文本 | `choices[0].message.content` | `content[0].text` | `reply` |
| 输入 token | `usage.prompt_tokens` | `usage.input_tokens` | `usage.input_tokens` |
| 输出 token | `usage.completion_tokens` | `usage.output_tokens` | `usage.output_tokens` |

---

## 3. 练习任务

### 练习 1：画出两家 API 的对照表

不看答案，凭 1.2 ~ 1.4 自己默写 OpenAI 和 Claude 在「鉴权头、system、max_tokens、回复位置」四点的差异。

目标：能脱口说出两家最关键的区别。

---

### 练习 2：实现 mock 版 `/chat`

先不接真实 SDK，用 1.8 的 mock provider 把 `/chat` 跑通，curl 测两个 model 都能返回统一格式。

目标：整条「统一入口 → model 路由 → 统一输出」链路先通。

---

### 练习 3：接入真实 SDK（有密钥则做）

按 1.6 换成真实 `call_openai` / `call_claude`，配好环境变量，curl 测真实回复。

目标：拿到真实模型回复，观察 `usage` 字段。

---

### 练习 4：加一个未知 model 的用例

```bash
curl -X POST http://127.0.0.1:8000/chat \
  -H "Content-Type: application/json" \
  -d '{"model":"foo-bar","messages":[{"role":"user","content":"hi"}]}'
```

观察返回。

目标：理解未知 model 时的分支处理，思考「这个错误格式够好吗」（Day 04 会统一）。

---

### 练习 5：加第三家（伪）供应商

假想再接一家 `deepseek` 模型，只写一个 mock provider 并加进路由映射。

目标：体会「加供应商 = 加一个函数 + 加一条映射」，业务对外接口不变，这就是 Gateway 的扩展性。

---

## 4. JS/Node.js 类比

| Gateway / Python | Node.js 类比 | 说明 |
|---|---|---|
| provider 函数 | adapter 模块 | 封装单个供应商差异 |
| `model` 路由分发 | strategy 选择 | 按参数选实现 |
| 统一 `reply` 字段 | DTO / 归一化响应 | 屏蔽底层差异 |
| OpenAI/Anthropic SDK | `openai` / `@anthropic-ai/sdk` (npm) | 各家官方 SDK |
| 环境变量存密钥 | `process.env.OPENAI_API_KEY` | 不写死密钥 |
| 多供应商抽象 | payment gateway 模式 | 统一入口 + 多实现 |

---

## 5. AI Review 提问

完成练习后，把 `providers.py` 和 `/chat` 代码贴给 AI，然后问：

```text
我正在学习 FastAPI Day 02：用统一 /chat 接口接入 OpenAI 和 Claude。

请你按资深后端工程师标准帮我检查：

1. 我的适配器（provider）抽象是否合理，各家差异有没有真正被隔离？
2. 用 model 参数做路由分发的写法有没有更好的方式？
3. 统一响应格式设计得好吗，缺不缺关键字段（比如错误、finish_reason）？
4. 我把密钥放环境变量对吗，还有哪些安全隐患？
5. 我用支付网关/adapter 做的类比准确吗？

请用中文输出：做对的地方、做错的地方、修改建议、下一步练习。
```

---

## 6. 今日产出

- [ ] `app/providers.py`，含 `call_openai` / `call_claude`
- [ ] `/chat` 接口，支持 `model` 参数路由
- [ ] mock 版跑通的 curl 记录
- [ ] OpenAI vs Claude 差异对照表
- [ ] 统一请求/响应格式说明
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能切换 2 模型
- [ ] 能说清 messages + role 的通用模型
- [ ] 能说出 OpenAI 与 Claude 至少 3 个差异
- [ ] 能解释 provider 适配器为什么这么设计
- [ ] `/chat` 能根据 `model` 路由到不同供应商
- [ ] 对外返回统一的 `reply` 字段
- [ ] 密钥没有写死在代码里

---

## 8. 今日自测题

### 8.1 大模型对话 API 的通用骨架是什么？

参考答案：

> ✅ 传入一个带 role 的 messages 数组（对话历史），模型返回下一条 assistant 回复。模型本身无记忆，多轮对话靠每次把历史消息一起发过去实现。

---

### 8.2 OpenAI 和 Claude 最关键的几个差异？

参考答案：

> ✅ 鉴权头不同（`Authorization: Bearer` vs `x-api-key`）；Claude 的 `max_tokens` 必填且需要 `anthropic-version` 头；system 提示在 OpenAI 放 messages、Claude 是单独字段；回复文本位置不同（`choices[0].message.content` vs `content[0].text`）。

---

### 8.3 为什么要把每家封装成 provider 函数？

参考答案：

> ✅ 用适配器模式把各家 SDK 的差异关在函数内部，让它们的输入输出统一。这样 `/chat` 只管路由，加/换供应商时业务对外接口不用改。

---

### 8.4 Gateway 的「归一化」指什么？

参考答案：

> ✅ 把各家不同的返回结构，转换成 Gateway 自己定义的统一格式（比如统一用 `reply` 字段）。业务方永远拿到同一种格式，不感知底层供应商。

---

### 8.5 密钥应该怎么存？

参考答案：

> ✅ 放环境变量（或 `.env` + 密钥管理服务），绝不写死在代码里，也不提交到 git。`.env` 要加进 `.gitignore`。

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
我正在进行 Week 13 Day 02：多模型 /chat 接口 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 13 README](./README.md)
