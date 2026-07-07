# Week 13 Day 04：错误处理与 API Key

> 所属周：Week 13：FastAPI + LLM Gateway  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`ai-lab/llm-gateway`  
> 类型：架构理解  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

加鉴权与统一错误格式。

今天你要真正掌握这一句话：

> 一个能交付的网关，必须做两件事：一是「只有带对 API Key 的请求才放行」，二是「不管哪里出错，返回给调用方的错误格式都长一样」。前者用依赖注入做鉴权，后者用异常处理器统一收口——就像 Express 的鉴权中间件 + 全局错误中间件。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 理解为什么网关必须鉴权
2. 理解 API Key 鉴权的最简形态
3. 用 FastAPI 依赖注入做 Key 校验
4. 理解 HTTPException 与状态码
5. 设计统一错误响应格式
6. 用异常处理器统一收口（校验错误、业务错误、未知错误）
7. 把上游模型调用的错误也包进统一格式
8. 用环境变量管理密钥（不写死在代码里）
9. curl 测无 Key / 错 Key / 对 Key
10. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 为什么网关必须鉴权

`llm-gateway` 背后是要花钱的大模型 API。如果谁都能调你的 `/chat`：

- 别人白嫖你的额度，账单爆炸
- 无法追踪是谁在调、调了多少
- 一旦被刷，你连关谁都不知道

小白重点：鉴权不是「锦上添花」，是网关的底线。今天做的是最简单的一种——静态 API Key，够学习和内部使用，但生产环境要配额度、轮换、按调用方发不同 Key。

> 安全提示：本文只做「校验请求头里的 Key 是否等于服务端配置的 Key」。这是演示级方案。真实生产要用更强的方案（每个调用方独立 Key、可吊销、限流、审计日志）。

---

### 1.2 API Key 鉴权的最简形态

约定：调用方在请求头里带上 Key，服务端比对。

```text
Authorization: Bearer sk-my-secret-key
```

或用自定义头：

```text
X-API-Key: sk-my-secret-key
```

小白重点：`Authorization: Bearer xxx` 是行业惯例（和 OpenAI、大多数 API 一致），推荐用这个。

---

### 1.3 用依赖注入做 Key 校验

FastAPI 的「依赖注入」(Depends) 就像 Express 的中间件：在进入业务前先跑一段逻辑。新建 `app/auth.py`：

```python
import os
from fastapi import Header, HTTPException, status

# 服务端配置的 Key，从环境变量读，不写死
GATEWAY_API_KEY = os.getenv("GATEWAY_API_KEY", "")


def verify_api_key(authorization: str = Header(default="")) -> None:
    # 期望格式：Authorization: Bearer <key>
    prefix = "Bearer "
    if not authorization.startswith(prefix):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="missing or malformed Authorization header",
        )
    token = authorization[len(prefix):]
    if not GATEWAY_API_KEY or token != GATEWAY_API_KEY:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="invalid API key",
        )
```

在 `/chat` 上挂这个依赖：

```python
from fastapi import Depends
from app.auth import verify_api_key


@app.post("/chat", dependencies=[Depends(verify_api_key)])
async def chat(req: ChatRequest):
    ...
```

小白重点：

- `Header(default="")` 让 FastAPI 自动从请求头取 `Authorization`
- `dependencies=[Depends(verify_api_key)]` 表示「进 chat 前先跑鉴权」，不通过就抛 401，业务不执行
- `/health` 不挂鉴权，保持公开（探活接口通常不鉴权）

JS 类比：

```js
app.post("/chat", verifyApiKey, chatHandler);  // verifyApiKey 是中间件
```

---

### 1.4 HTTPException 与状态码

FastAPI 用 `HTTPException` 抛业务错误，你指定状态码和信息，框架负责转成 HTTP 响应。

常用状态码：

| 码 | 含义 | 何时用 |
|---|---|---|
| 400 | 请求本身有问题 | 未知 model、参数语义错 |
| 401 | 未认证 | 没带 Key / Key 错 |
| 403 | 已认证但无权限 | Key 对但没开通该模型 |
| 422 | 请求体校验失败 | Pydantic 自动返回 |
| 429 | 请求太频繁 | 限流 |
| 500 | 服务端内部错误 | 没接住的异常 |
| 502/503 | 上游出错/不可用 | 调大模型失败 |

小白重点：状态码是给「机器」看的第一信号。调用方靠它决定「要不要重试」「是不是我参数错了」。别所有错误都甩 500。

---

### 1.5 设计统一错误响应格式

问题：现在错误格式五花八门——Pydantic 是 `{"detail": [...]}`，HTTPException 是 `{"detail": "..."}`，未捕获异常是一堆栈。调用方没法统一处理。

定一个统一结构：

```json
{
  "error": {
    "type": "invalid_api_key",
    "message": "invalid API key",
    "status": 401
  }
}
```

小白重点：统一错误格式是「API 契约」的一部分。调用方只要认 `error.type` 和 `error.status`，不用管错误来自校验、鉴权还是上游。

---

### 1.6 用异常处理器统一收口

FastAPI 允许注册「异常处理器」，把不同来源的错误都转成统一格式。在 `app/main.py`：

```python
from fastapi import FastAPI, Request, HTTPException
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse

app = FastAPI()


def error_body(status_code: int, err_type: str, message: str) -> dict:
    return {"error": {"type": err_type, "message": message, "status": status_code}}


# 1) 请求体校验错误（Pydantic 触发的 422）
@app.exception_handler(RequestValidationError)
async def on_validation_error(request: Request, exc: RequestValidationError):
    return JSONResponse(
        status_code=422,
        content=error_body(422, "validation_error", "request body is invalid"),
    )


# 2) 业务主动抛的 HTTPException
@app.exception_handler(HTTPException)
async def on_http_error(request: Request, exc: HTTPException):
    type_map = {401: "invalid_api_key", 400: "bad_request", 403: "forbidden"}
    err_type = type_map.get(exc.status_code, "http_error")
    return JSONResponse(
        status_code=exc.status_code,
        content=error_body(exc.status_code, err_type, str(exc.detail)),
    )


# 3) 兜底：任何没接住的异常
@app.exception_handler(Exception)
async def on_unhandled_error(request: Request, exc: Exception):
    # 生产环境这里要记日志，但不要把内部堆栈返回给调用方
    return JSONResponse(
        status_code=500,
        content=error_body(500, "internal_error", "internal server error"),
    )
```

小白重点：

- 三个处理器覆盖三类错误：校验、业务、未知
- 兜底处理器很关键——它保证「无论多离谱的 bug，调用方拿到的还是干净的 JSON」，而不是一堆内部堆栈（堆栈泄露是安全问题）
- 内部细节记进日志，对外只给概括信息

JS 类比：Express 的错误中间件 `app.use((err, req, res, next) => {...})`。

---

### 1.7 把上游模型调用错误包进统一格式

调 OpenAI/Claude 可能失败（超时、余额不足、限流）。在 provider 层把它转成 HTTPException：

```python
from fastapi import HTTPException


def call_openai(model, messages):
    try:
        # resp = client.chat.completions.create(...)
        ...
    except Exception as e:
        # 上游失败 -> 502 Bad Gateway，语义是「我这个网关的上游挂了」
        raise HTTPException(status_code=502, detail=f"upstream error: {e}")
```

小白重点：上游（大模型）出错用 502 最贴切——它表达的是「网关本身没问题，是它依赖的服务挂了」。这样调用方知道「不是我参数错，可以重试」。

---

### 1.8 用环境变量管理密钥

绝不要把 Key 写死在代码里（会被提交进 git，泄露）。用环境变量 + `.env` 文件：

`.env`（加进 `.gitignore`，绝不提交）：

```text
GATEWAY_API_KEY=sk-gateway-demo-123
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
```

用 `python-dotenv` 加载：

```bash
pip install python-dotenv
```

```python
from dotenv import load_dotenv
load_dotenv()  # 在读 os.getenv 之前调用
```

小白重点：

- `.env` 必须进 `.gitignore`
- 提供一个 `.env.example`（只有键名、没有真实值）给别人参考
- 三个 Key 各司其职：`GATEWAY_API_KEY` 是调用方进你网关的钥匙；`OPENAI_API_KEY`/`ANTHROPIC_API_KEY` 是你网关调上游的钥匙

---

### 1.9 curl 测三种情况

先设好环境变量并启动：

```bash
export GATEWAY_API_KEY=sk-gateway-demo-123
uvicorn app.main:app --reload
```

不带 Key（预期 401）：

```bash
curl -i -X POST http://127.0.0.1:8000/chat \
  -H "Content-Type: application/json" \
  -d '{"model":"gpt-4o-mini","messages":[{"role":"user","content":"hi"}]}'
```

带错 Key（预期 401）：

```bash
curl -i -X POST http://127.0.0.1:8000/chat \
  -H "Authorization: Bearer wrong-key" \
  -H "Content-Type: application/json" \
  -d '{"model":"gpt-4o-mini","messages":[{"role":"user","content":"hi"}]}'
```

带对 Key（预期正常或 502，取决于上游是否真连）：

```bash
curl -i -X POST http://127.0.0.1:8000/chat \
  -H "Authorization: Bearer sk-gateway-demo-123" \
  -H "Content-Type: application/json" \
  -d '{"model":"gpt-4o-mini","messages":[{"role":"user","content":"hi"}]}'
```

目标：确认 401 的响应体是统一的 `{"error":{...}}` 格式，且不含内部堆栈。

---

## 2. 源码阅读

- `ai-lab/llm-gateway/app/auth.py`
- `ai-lab/llm-gateway/app/main.py`（异常处理器部分）

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. 鉴权依赖长什么样，从哪个头取 Key
2. 哪些接口挂了鉴权、哪些没挂
3. 注册了哪几个异常处理器，各管什么
4. 统一错误格式的结构是什么
5. 密钥从哪里读（是否有写死的风险）

建议在笔记里补全：

| 错误来源 | 状态码 | error.type | 处理器 |
|---|---|---|---|
| 没带/错 Key | 401 | invalid_api_key | HTTPException handler |
| 请求体校验失败 | 422 | validation_error | RequestValidationError handler |
| 未知 model | 400 | bad_request | HTTPException handler |
| 上游模型失败 | 502 | http_error | HTTPException handler |
| 没接住的异常 | 500 | internal_error | Exception handler |

---

## 3. 练习任务

### 练习 1：写 auth.py

按 1.3 实现 `verify_api_key` 依赖，从 `Authorization: Bearer` 取 Key，与环境变量比对，不通过抛 401。

目标：能用 Depends 做鉴权。

---

### 练习 2：挂鉴权

给 `/chat` 挂 `dependencies=[Depends(verify_api_key)]`，`/health` 保持公开。

目标：理解哪些接口该鉴权、哪些不该。

---

### 练习 3：三个异常处理器

按 1.6 注册校验错误、HTTPException、兜底异常三个处理器，统一成 `{"error":{...}}` 格式。

目标：无论错误来自哪里，响应格式一致。

---

### 练习 4：环境变量管理密钥

建 `.env` + `.env.example`，把 `.env` 加进 `.gitignore`，用 `load_dotenv()` 加载。

目标：密钥不落进代码/git。

---

### 练习 5：错误码对照表

按 2 的表格，把你项目里每种错误的状态码和 type 填全，并各写一条 curl 复现。

目标：把「错误分类 → 状态码 → 统一格式」串成体系。

---

## 4. JS/Node.js 类比

| FastAPI | Node.js 类比 | 说明 |
|---|---|---|
| `Depends(verify_api_key)` | 鉴权中间件 | 进业务前校验 |
| `HTTPException` | `res.status(4xx).json()` | 主动抛业务错误 |
| `@app.exception_handler` | 错误中间件 `app.use((err..))` | 统一收口 |
| `RequestValidationError` | Zod 校验失败 | 422 来源 |
| `os.getenv` + dotenv | `process.env` + dotenv | 环境变量 |
| 502 上游错误 | 代理层 502 | 表达上游挂了 |

---

## 5. AI Review 提问

完成练习后，把 `auth.py` 和异常处理器代码贴给 AI，然后问：

```text
我正在学习 FastAPI Day 04：给 llm-gateway 加 API Key 鉴权和统一错误格式。

请你按资深后端工程师标准帮我检查：

1. 我的 API Key 鉴权方式（Bearer + 环境变量比对）有什么安全隐患？
2. 我注册的三个异常处理器覆盖全了吗，有没有漏掉的错误来源？
3. 各种错误的状态码用得对吗（401/400/422/502/500）？
4. 我的统一错误格式在生产上够用吗，还该加什么字段（如 request_id）？
5. 我用 Express 中间件做的类比准确吗？

请用中文输出：做对的地方、安全隐患、修改建议、下一步练习。
```

---

## 6. 今日产出

- [ ] `app/auth.py`（Bearer Key 校验依赖）
- [ ] `/chat` 挂鉴权、`/health` 公开
- [ ] 三个异常处理器 + 统一错误格式
- [ ] `.env` / `.env.example` / `.gitignore`
- [ ] 错误码对照表 + 每种错误的 curl 复现
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 错误格式统一（都是 `{"error":{...}}`）
- [ ] 无 Key / 错 Key 返回 401
- [ ] 对 Key 能进入业务
- [ ] 未捕获异常返回 500 而非堆栈
- [ ] 能说清 401/400/422/502/500 各自何时用
- [ ] 密钥从环境变量读，不在代码里
- [ ] 能解释 Depends 与 Express 中间件的类比

---

## 8. 今日自测题

### 8.1 网关为什么必须鉴权？

参考答案：

> ✅ 背后是花钱的大模型 API，不鉴权会被白嫖、无法追踪调用方、被刷了也没法止损。鉴权是网关底线。

---

### 8.2 FastAPI 里怎么做鉴权，和 Express 什么关系？

参考答案：

> ✅ 用依赖注入 `Depends(verify_api_key)`，在进业务前校验请求头里的 Key，不通过抛 401。等价于 Express 的鉴权中间件。

---

### 8.3 为什么要统一错误格式？

参考答案：

> ✅ 校验、鉴权、上游、未知错误来源不同、格式各异，调用方无法统一处理。统一成 `{"error":{type,message,status}}` 后，调用方只认这一套结构。

---

### 8.4 上游模型调用失败该返回什么状态码？

参考答案：

> ✅ 502 Bad Gateway。它表达「网关本身没问题，是依赖的上游挂了」，调用方据此可选择重试。

---

### 8.5 为什么兜底异常处理器不能返回堆栈？

参考答案：

> ✅ 内部堆栈会泄露代码结构、路径、依赖等信息，是安全隐患。对外只给概括信息（如 internal server error），细节记进日志。

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
我正在进行 Week 13 Day 04：错误处理与 API Key 的学习。
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
