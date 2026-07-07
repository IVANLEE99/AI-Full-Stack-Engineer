# Week 13 Day 03：Pydantic 请求验证

> 所属周：Week 13：FastAPI + LLM Gateway  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`ai-lab/llm-gateway`  
> 类型：编码练习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

给 /chat 加请求体校验。

今天你要真正掌握这一句话：

> Pydantic 用「类型注解 + 字段约束」来声明请求体长什么样；FastAPI 会在请求进入你的函数之前自动完成解析和校验，非法请求直接返回 422，根本到不了你的业务代码。这就像 Node 里的 Zod，也像 PHP 里的表单验证器，但它和类型系统、文档是一体的。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 理解昨天用 `payload: dict` 的问题
2. 理解 Pydantic 的 BaseModel
3. 定义 `Message` 和 `ChatRequest` 模型
4. 把 `/chat` 的参数换成 `ChatRequest`
5. 理解 FastAPI 自动校验 + 422 响应
6. 加字段约束（长度、取值范围、枚举）
7. 理解 `Field` 和默认值/可选字段
8. 用 curl 测合法与非法请求
9. 看自动生成的文档如何反映校验
10. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 昨天用 dict 收请求的问题

昨天 `/chat` 是这样收参数的：

```python
@app.post("/chat")
async def chat(payload: dict):
    model = payload["model"]        # 如果没传 model，直接 KeyError -> 500
    messages = payload["messages"]  # 如果 messages 是字符串也不报错
```

问题：

- 缺字段 → `KeyError` → 500，错误信息对调用方毫无帮助
- 字段类型错了（比如 `messages` 传成字符串）不会被拦
- 没有任何自动文档说明请求体长什么样

小白重点：「不校验的接口」在企业里等于「定时炸弹」。垃圾数据会一路流到调用大模型、写日志、扣费，出问题很难查。

---

### 1.2 Pydantic 的 BaseModel 是什么

Pydantic 是一个「用 Python 类型注解定义数据模型 + 自动校验」的库。FastAPI 内置依赖它。

最小例子：

```python
from pydantic import BaseModel


class User(BaseModel):
    name: str
    age: int


u = User(name="Tom", age=18)
print(u.name)  # Tom

# 类型不对会抛 ValidationError
User(name="Tom", age="not-a-number")  # 报错
```

小白重点：只要类型注解写了 `age: int`，Pydantic 就会在构造时校验。它还会尽量做安全转换（比如字符串 `"18"` → int `18`），但非法值会明确报错，而不是默默出 bug。

JS 里最接近的是 Zod：

```js
import { z } from "zod";
const User = z.object({ name: z.string(), age: z.number() });
User.parse({ name: "Tom", age: 18 });
```

PHP 里类似 Yii 的 `rules()` 或 Laravel 的 `FormRequest`。

---

### 1.3 定义 Message 和 ChatRequest 模型

新建 `app/schemas.py`：

```python
from pydantic import BaseModel


class Message(BaseModel):
    role: str
    content: str


class ChatRequest(BaseModel):
    model: str
    messages: list[Message]
```

小白重点：模型可以嵌套。`ChatRequest.messages` 是一个 `Message` 列表，Pydantic 会对里面每一条都校验。

---

### 1.4 把 /chat 参数换成 ChatRequest

改 `app/main.py`：

```python
from fastapi import FastAPI
from app.schemas import ChatRequest
from app.providers import call_openai, call_claude

app = FastAPI()

OPENAI_MODELS = {"gpt-4o-mini", "gpt-4o"}
CLAUDE_MODELS = {"claude-3-5-sonnet-latest", "claude-3-5-haiku-latest"}


@app.post("/chat")
async def chat(req: ChatRequest):
    # req.model / req.messages 已经是校验过的类型
    messages = [m.model_dump() for m in req.messages]

    if req.model in OPENAI_MODELS:
        result = call_openai(req.model, messages)
    elif req.model in CLAUDE_MODELS:
        result = call_claude(req.model, messages)
    else:
        return {"error": f"unknown model: {req.model}"}

    return {"model": req.model, "reply": result["reply"], "usage": result["usage"]}
```

小白重点：只要把参数类型标成 `req: ChatRequest`，FastAPI 就自动帮你：

1. 从请求体解析 JSON
2. 校验字段类型和结构
3. 校验失败自动返回 422（你的函数根本不会被调用）
4. 校验通过后，`req` 是一个带类型提示的对象，`req.model` 有自动补全

`m.model_dump()` 把 Pydantic 对象转回普通 dict，方便传给 SDK。

---

### 1.5 感受自动校验和 422

启动服务：

```bash
uvicorn app.main:app --reload
```

发一个缺字段的请求（没有 messages）：

```bash
curl -X POST http://127.0.0.1:8000/chat \
  -H "Content-Type: application/json" \
  -d '{"model":"gpt-4o-mini"}'
```

你会收到 422，响应体类似：

```json
{
  "detail": [
    {
      "type": "missing",
      "loc": ["body", "messages"],
      "msg": "Field required"
    }
  ]
}
```

小白重点：`loc` 精确告诉调用方「是 body 里的 messages 字段缺了」。这比昨天的 500 友好一百倍，而且这一切你一行校验代码都没写。

对比表：

| 场景 | 昨天 dict 版 | 今天 Pydantic 版 |
|---|---|---|
| 缺字段 | 500 KeyError | 422 + 精确定位 |
| 类型错 | 不拦，埋雷 | 422 |
| 文档 | 无 | 自动生成 |
| 业务代码 | 要写一堆 if 判断 | 拿到就是干净数据 |

---

### 1.6 加字段约束（长度、取值、枚举）

只声明类型还不够。比如 `messages` 不能为空，`role` 只能是三个值之一，`content` 不能太长。用 `Field` 和 `Literal`：

```python
from typing import Literal
from pydantic import BaseModel, Field


class Message(BaseModel):
    role: Literal["system", "user", "assistant"]
    content: str = Field(min_length=1, max_length=8000)


class ChatRequest(BaseModel):
    model: str = Field(min_length=1)
    messages: list[Message] = Field(min_length=1)   # 至少 1 条消息
    temperature: float = Field(default=1.0, ge=0.0, le=2.0)  # 可选，带范围
```

约束说明：

| 写法 | 含义 |
|---|---|
| `Literal["a","b"]` | 只能是枚举里的值 |
| `Field(min_length=1)` | 字符串/列表最少长度 |
| `Field(max_length=8000)` | 最大长度 |
| `Field(ge=0.0, le=2.0)` | 数值范围 0~2（ge=大于等于，le=小于等于） |
| `Field(default=1.0)` | 默认值（不传就用它，所以是可选字段） |

小白重点：`temperature` 有默认值，所以调用方可以不传；`role` 用 `Literal` 后，传 `"boss"` 这种非法角色会直接 422。

---

### 1.7 校验 model 是否受支持

上面的约束还不能拦「未知 model」。可以在业务里判断（昨天那样），也可以用 Pydantic 的字段校验器把它前置：

```python
from pydantic import BaseModel, field_validator

SUPPORTED_MODELS = {
    "gpt-4o-mini", "gpt-4o",
    "claude-3-5-sonnet-latest", "claude-3-5-haiku-latest",
}


class ChatRequest(BaseModel):
    model: str
    messages: list[Message] = Field(min_length=1)

    @field_validator("model")
    @classmethod
    def model_must_be_supported(cls, v: str) -> str:
        if v not in SUPPORTED_MODELS:
            raise ValueError(f"unsupported model: {v}")
        return v
```

小白重点：把「能不能处理这个 model」放进校验层，非法 model 在进入业务前就被 422 拦掉。不过要注意——这样返回的是校验错误 422，而「未知 model」语义上更像 400。选哪种取决于你的 API 设计，明天 Day 04 会统一错误格式时再权衡。

---

### 1.8 用 curl 测各种非法请求

逐一验证约束：

```bash
# messages 为空数组
curl -X POST http://127.0.0.1:8000/chat -H "Content-Type: application/json" \
  -d '{"model":"gpt-4o-mini","messages":[]}'

# role 非法
curl -X POST http://127.0.0.1:8000/chat -H "Content-Type: application/json" \
  -d '{"model":"gpt-4o-mini","messages":[{"role":"boss","content":"hi"}]}'

# content 为空
curl -X POST http://127.0.0.1:8000/chat -H "Content-Type: application/json" \
  -d '{"model":"gpt-4o-mini","messages":[{"role":"user","content":""}]}'

# temperature 超范围
curl -X POST http://127.0.0.1:8000/chat -H "Content-Type: application/json" \
  -d '{"model":"gpt-4o-mini","messages":[{"role":"user","content":"hi"}],"temperature":9}'
```

每个都应返回 422，并在 `detail[].loc` 里精确指出出错字段。

目标：亲眼确认每条约束都在生效。

---

### 1.9 校验如何反映到自动文档

打开：

```text
http://127.0.0.1:8000/docs
```

展开 `/chat`，你会看到请求体的 Schema：字段、类型、是否必填、枚举值、范围，全都自动列出来了。

小白重点：Pydantic 模型是「唯一事实来源」——校验规则、类型提示、API 文档三者永远一致，改一处全同步。这是它和 PHP 手写验证器最大的体验差异。

---

## 2. 源码阅读

- `ai-lab/llm-gateway/app/schemas.py`
- `ai-lab/llm-gateway/app/main.py`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. 有哪些 Pydantic 模型，字段和类型是什么
2. 用了哪些约束（`Field` / `Literal` / `field_validator`）
3. 哪些字段是必填、哪些有默认值
4. `/chat` 是怎么用模型对象（而不是 dict）的
5. Pydantic 对象怎么转回 dict 传给 SDK（`model_dump`）

建议在笔记里补全：

| 字段 | 类型 | 约束 | 必填? |
|---|---|---|---|
| `model` | str | 受支持列表 | 是 |
| `messages` | list[Message] | 至少 1 条 | 是 |
| `messages[].role` | str | system/user/assistant | 是 |
| `messages[].content` | str | 1~8000 字符 | 是 |
| `temperature` | float | 0~2，默认 1.0 | 否 |

---

## 3. 练习任务

### 练习 1：建 schemas.py

按 1.3 + 1.6 定义 `Message` 和 `ChatRequest`，加上 `role` 枚举、`content` 长度、`messages` 非空、`temperature` 范围约束。

目标：能独立用 Pydantic 声明一个带约束的嵌套模型。

---

### 练习 2：切换 /chat 到 ChatRequest

把昨天的 `payload: dict` 换成 `req: ChatRequest`，并用 `model_dump()` 转 dict 传给 provider。

目标：接口拿到的就是干净数据，业务里不再写字段校验。

---

### 练习 3：非法请求测试清单

按 1.8 跑完 4 个非法用例，把每个返回的 `detail[].loc` 记进笔记。

目标：确认每条约束生效，并看懂 422 的错误结构。

---

### 练习 4：加一个可选字段

给 `ChatRequest` 加一个可选的 `max_tokens: int = Field(default=1024, ge=1, le=4096)`，并在 provider 里用上它。

目标：理解「带默认值 = 可选字段」，以及数值范围约束。

---

### 练习 5：对比 Zod / PHP 验证器

写一段 Zod 或 Yii `rules()` 实现同样的校验，和 Pydantic 版并排放进笔记。

目标：把三套生态的「请求校验」概念打通。

---

## 4. JS/Node.js 类比

| Pydantic / FastAPI | Node.js 类比 | 说明 |
|---|---|---|
| `BaseModel` | Zod `z.object` | 声明数据结构 |
| 类型注解触发校验 | Zod schema | 类型即规则 |
| `Field(min_length=..)` | `z.string().min(..)` | 字段约束 |
| `Literal[...]` | `z.enum([...])` | 枚举 |
| 自动 422 | 校验中间件抛 400/422 | 请求进业务前拦截 |
| 校验错误 `detail[].loc` | Zod `error.issues[].path` | 精确定位出错字段 |
| 模型驱动自动文档 | 手动维护 OpenAPI | FastAPI 自动同步 |

---

## 5. AI Review 提问

完成练习后，把 `schemas.py` 和 `/chat` 代码贴给 AI，然后问：

```text
我正在学习 FastAPI Day 03：用 Pydantic 给 /chat 加请求体校验。

请你按资深后端工程师标准帮我检查：

1. 我的 Pydantic 模型字段和约束设计是否合理？
2. role 用 Literal、messages 用 min_length 这些约束够不够，还缺什么？
3. 未知 model 用 field_validator 拦是好做法吗，还是该在业务里返回 400？
4. 我把 Pydantic 对象 model_dump 成 dict 再传 SDK 的做法对吗？
5. 我用 Zod / PHP 验证器做的类比准确吗？

请用中文输出：做对的地方、做错的地方、修改建议、下一步练习。
```

---

## 6. 今日产出

- [ ] `app/schemas.py`，含 `Message` / `ChatRequest` 及约束
- [ ] `/chat` 改用 `ChatRequest` 参数
- [ ] 4 个非法请求的 422 测试记录
- [ ] 一个可选字段的实现（如 `max_tokens`）
- [ ] 字段/约束对照表
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 非法请求被拒绝（返回 422）
- [ ] 能定义嵌套 Pydantic 模型
- [ ] 能给字段加长度/范围/枚举约束
- [ ] 能解释 FastAPI 何时自动校验、失败返回什么
- [ ] 能看懂 422 响应里的 `detail[].loc`
- [ ] `/docs` 里能看到请求体 Schema
- [ ] 能说清 Pydantic 与 Zod 的类比

---

## 8. 今日自测题

### 8.1 昨天用 `payload: dict` 有什么问题？

参考答案：

> ✅ 缺字段会 KeyError 变 500，类型错不拦会埋雷，没有自动文档，业务里得手写一堆校验。数据不干净会一路流到调模型、扣费环节。

---

### 8.2 把参数标成 `req: ChatRequest` 后 FastAPI 做了什么？

参考答案：

> ✅ 自动解析 JSON、校验类型和结构、校验失败返回 422（业务函数不会被调用）、校验通过后给你一个带类型提示的对象。

---

### 8.3 校验失败返回什么状态码，错误信息里怎么定位字段？

参考答案：

> ✅ 返回 422，`detail` 是一个数组，每项的 `loc` 指出出错字段的位置（如 `["body","messages"]`），`msg` 说明原因。

---

### 8.4 怎么限制 role 只能是 system/user/assistant？

参考答案：

> ✅ 用 `Literal["system","user","assistant"]` 作为字段类型，传其它值会被 422 拦掉。

---

### 8.5 Pydantic 和 Zod 的类比？

参考答案：

```text
Pydantic BaseModel   ≈ Zod z.object
类型注解即校验规则     ≈ Zod schema
Field(min_length=..)  ≈ z.string().min(..)
Literal[...]          ≈ z.enum([...])
自动 422              ≈ 校验中间件抛错
```

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
我正在进行 Week 13 Day 03：Pydantic 请求验证 的学习。
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
