# Week 13 Day 01：FastAPI 入门

> 所属周：Week 13：FastAPI + LLM Gateway  
> 阶段：第四阶段：AI Backend  
> 主仓库/项目：`ai-lab/llm-gateway`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

搭建项目并实现 `/health`。

今天你要真正掌握这一句话：

> FastAPI 是一个基于 Python 类型注解 + ASGI 的 Web 框架，你用 `@app.get("/xxx")` 装饰一个普通函数，它就变成了一个 HTTP 接口；这就像 Express 里 `app.get("/xxx", handler)`，但 FastAPI 靠函数的类型注解自动完成参数解析、校验和文档生成。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先搞清楚这一阶段的目标：我们要做一个 LLM Gateway（大模型统一网关）
2. 确认 Python 环境是否可用
3. 理解什么是虚拟环境（venv），为什么要用它
4. 安装 FastAPI 和 uvicorn
5. 写出第一个 FastAPI 应用
6. 用 uvicorn 把它跑起来
7. 理解装饰器路由 `@app.get`
8. 实现 `/health` 健康检查接口
9. 打开自动文档 `/docs`
10. 建立项目目录骨架
11. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 先理解这一周要做什么：LLM Gateway

前三个阶段你在用 PHP（Yii2/TP）做业务后端。第四阶段进入 AI Backend，主线语言换成 **Python**，框架换成 **FastAPI**。

本周项目 `ai-lab/llm-gateway` 是一个「大模型统一网关」。它要做的事很简单：

```text
客户端  --->  你的 Gateway  --->  OpenAI / Claude 等大模型
         (统一的 /chat 接口)      (各家 API 不一样)
```

为什么需要 Gateway？因为每家大模型的 API 参数、鉴权、返回格式都不一样。你在业务里到处直接调 OpenAI/Claude，会很乱。用一个 Gateway 把它们「包」起来，业务只认你自己的 `/chat` 接口。

小白重点：这和你在 PHP 里写一个 `PaymentGateway` 去统一微信支付、支付宝是一个思路，只是这次统一的对象是「大模型供应商」。

```text
PHP 世界：PaymentGateway  -> 微信 / 支付宝 / 银联
本周世界：LLMGateway      -> OpenAI / Claude / ...
```

---

### 1.2 确认 Python 是否可用

先在终端执行：

```bash
python3 --version
```

你应该看到类似：

```text
Python 3.11.x
```

要求 Python 3.9 以上，推荐 3.10/3.11。再确认 pip：

```bash
python3 -m pip --version
```

能输出版本就说明 Python 基础环境 OK。

| 对比项 | Python | Node.js |
|---|---|---|
| 运行时 | `python3` | `node` |
| 包管理器 | `pip` | `npm` |
| 版本查看 | `python3 --version` | `node -v` |
| 执行脚本 | `python3 app.py` | `node app.js` |

---

### 1.3 理解虚拟环境（venv）

Python 有个坑：默认所有包都装到「全局」，不同项目容易互相打架。解决办法是给每个项目建一个独立的「虚拟环境」。

小白重点：

```text
venv ≈ 每个项目独立的 node_modules
```

Node 天生每个项目一个 `node_modules/`，互不干扰；Python 需要你手动建一个 venv 来达到同样的隔离效果。

先给项目建目录并进入：

```bash
mkdir -p ai-lab/llm-gateway
cd ai-lab/llm-gateway
```

创建虚拟环境：

```bash
python3 -m venv .venv
```

激活它（macOS / Linux）：

```bash
source .venv/bin/activate
```

Windows PowerShell：

```powershell
.venv\Scripts\Activate.ps1
```

激活成功后，终端提示符前面会多一个 `(.venv)`。以后 `pip install` 装的东西只会进这个环境。

退出虚拟环境用：

```bash
deactivate
```

---

### 1.4 安装 FastAPI 和 uvicorn

在激活了 venv 的终端里执行：

```bash
pip install "fastapi[standard]" uvicorn
```

说明：

- `fastapi`：Web 框架本体。
- `uvicorn`：ASGI 服务器，负责真正监听端口、跑你的应用。

小白重点：FastAPI 只是「写路由的框架」，它自己不会监听端口。真正把服务跑起来、接收 HTTP 请求的是 uvicorn。

```text
FastAPI ≈ Express 里你写的路由和逻辑
uvicorn ≈ 真正 listen(3000) 的那个 server
```

在 PHP 里也类似：你写的是 Yii2/TP 的 Controller，真正监听端口的是 `php-fpm + nginx` 或 `php artisan serve`。

装完后把依赖记录下来：

```bash
pip freeze > requirements.txt
```

这相当于 npm 的 `package.json` 里的 `dependencies`。

| Python | Node.js | PHP |
|---|---|---|
| `pip install` | `npm install` | `composer require` |
| `requirements.txt` | `package.json` | `composer.json` |
| `.venv/` | `node_modules/` | `vendor/` |

---

### 1.5 写出第一个 FastAPI 应用

新建文件 `app/main.py`：

```bash
mkdir -p app
```

`app/main.py` 内容：

```python
from fastapi import FastAPI

app = FastAPI()


@app.get("/")
def read_root():
    return {"message": "Hello LLM Gateway"}
```

逐行看：

- `from fastapi import FastAPI`：导入框架，类似 JS 的 `import express from "express"`。
- `app = FastAPI()`：创建应用实例，类似 `const app = express()`。
- `@app.get("/")`：这是一个「装饰器」，把下面的函数注册成 `GET /` 接口。
- 返回一个 dict，FastAPI 会自动把它变成 JSON 响应。

Express 对照：

```js
import express from "express";

const app = express();

app.get("/", (req, res) => {
  res.json({ message: "Hello LLM Gateway" });
});
```

小白重点：FastAPI 里你直接 `return dict`，框架自动帮你 `JSON.stringify` 并设置 `Content-Type: application/json`；Express 里你要显式 `res.json(...)`。

---

### 1.6 用 uvicorn 把服务跑起来

在项目根目录（`ai-lab/llm-gateway`）执行：

```bash
uvicorn app.main:app --reload
```

拆解这条命令：

- `app.main`：指 `app/main.py` 这个模块。
- `:app`：指模块里那个 `app = FastAPI()` 变量。
- `--reload`：改代码自动重启，等价于 Node 的 `nodemon`。

你会看到类似输出：

```text
Uvicorn running on http://127.0.0.1:8000 (Press CTRL+C to quit)
```

浏览器打开 `http://127.0.0.1:8000`，应该看到：

```json
{"message":"Hello LLM Gateway"}
```

小白重点：默认端口是 `8000`（Express 常用 3000，PHP 内置服务器常用 8000/8080），可以用 `--port 9000` 改。

---

### 1.7 理解装饰器路由 `@app.get`

`@app.get("/")` 里的 `@` 是 Python 的「装饰器」语法。你现在只要理解成一句话：

> 装饰器就是「给下面这个函数贴个标签」，FastAPI 看到标签就把这个函数登记成对应的 HTTP 接口。

常见方法对照：

```python
@app.get("/items")      # 查询
@app.post("/items")     # 新建
@app.put("/items/{id}") # 全量更新
@app.delete("/items/{id}") # 删除
```

Express 对照：

```js
app.get("/items", ...);
app.post("/items", ...);
app.put("/items/:id", ...);
app.delete("/items/:id", ...);
```

| 对比项 | FastAPI | Express |
|---|---|---|
| 注册 GET | `@app.get("/x")` | `app.get("/x", fn)` |
| 路径参数 | `/items/{id}` | `/items/:id` |
| 返回 JSON | `return {...}` | `res.json({...})` |
| 自动文档 | 内置 `/docs` | 需自己接 swagger |

---

### 1.8 实现 `/health` 健康检查接口

健康检查接口是几乎所有后端服务的标配，用来让监控系统、负载均衡、K8s 探针判断「服务还活着吗」。

在 `app/main.py` 里加上：

```python
from fastapi import FastAPI

app = FastAPI()


@app.get("/")
def read_root():
    return {"message": "Hello LLM Gateway"}


@app.get("/health")
def health():
    return {"status": "ok"}
```

保存后（有 `--reload` 会自动重启），访问：

```bash
curl http://127.0.0.1:8000/health
```

应该返回：

```json
{"status":"ok"}
```

小白重点：健康检查要「轻」——不要在里面查数据库、调外部 API，否则外部一挂，你的健康检查也跟着超时，监控会误判。它只回答一件事：「进程本身还能响应请求吗」。

---

### 1.9 打开自动文档 `/docs`

FastAPI 最爽的地方：自动生成交互式 API 文档。启动服务后打开：

```text
http://127.0.0.1:8000/docs
```

你会看到一个可以直接点「Try it out」发请求的页面（Swagger UI）。还有一个：

```text
http://127.0.0.1:8000/redoc
```

小白重点：这份文档是根据你的路由和类型注解「自动生成」的，不用你手写。PHP 里你要接 Swagger 注解才能有类似效果，FastAPI 是天生自带。

---

### 1.10 建立项目目录骨架

今天的最后，把项目结构立起来，方便后面几天往里填：

```text
ai-lab/llm-gateway/
├── .venv/              # 虚拟环境（不提交到 git）
├── app/
│   ├── __init__.py     # 让 app 成为一个 Python 包
│   └── main.py         # FastAPI 应用入口
├── requirements.txt    # 依赖清单
└── .gitignore
```

创建空的 `app/__init__.py`：

```bash
touch app/__init__.py
```

`.gitignore` 内容：

```text
.venv/
__pycache__/
*.pyc
.env
```

小白重点：`__init__.py` 的作用是告诉 Python「这个文件夹是一个包」，这样你才能写 `from app.main import app`。它类似于目录级别的模块声明，没有它跨目录 import 容易失败。

---

## 2. 源码阅读

- `ai-lab/llm-gateway/app/main.py`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. `app = FastAPI()` 在哪里创建
2. 一共注册了几个路由
3. 每个路由用的是 `@app.get` 还是 `@app.post`
4. 每个路由函数返回的是什么结构
5. 有没有 `/health` 接口

建议在笔记里画一张「路由表」：

| 路径 | 方法 | 作用 | 返回 |
|---|---|---|---|
| `/` | GET | 首页/欢迎 | `{"message": ...}` |
| `/health` | GET | 健康检查 | `{"status": "ok"}` |

---

## 3. 练习任务

### 练习 1：跑通 Hello World

按 1.5 ~ 1.6 建好 `app/main.py` 并用 uvicorn 启动，浏览器访问首页看到 JSON。

目标：确认「写函数 → 装饰器 → uvicorn 启动」这条链路是通的。

---

### 练习 2：实现并测试 `/health`

按 1.8 加上 `/health`，用 curl 验证：

```bash
curl -i http://127.0.0.1:8000/health
```

`-i` 会连响应头一起打印。观察状态码是否为 `200 OK`。

目标：`/health` 返回 `{"status":"ok"}` 且状态码 200。

---

### 练习 3：加一个带路径参数的接口

在 `main.py` 里加：

```python
@app.get("/ping/{name}")
def ping(name: str):
    return {"pong": name}
```

测试：

```bash
curl http://127.0.0.1:8000/ping/tom
```

应返回：

```json
{"pong":"tom"}
```

目标：理解 `{name}` 路径参数如何自动传进函数参数，并注意 `name: str` 这个类型注解。

---

### 练习 4：对比 Express 写一遍

用 Node/Express（或伪代码）写出等价的 `/health` 和 `/ping/:name`，逐行和 FastAPI 对照。

Express 示例：

```js
import express from "express";
const app = express();

app.get("/health", (req, res) => res.json({ status: "ok" }));
app.get("/ping/:name", (req, res) => res.json({ pong: req.params.name }));

app.listen(8000, () => console.log("listening on 8000"));
```

目标：能说清楚「FastAPI 不需要手写 `res.json`，也不需要单独写 `listen`（交给 uvicorn）」。

---

### 练习 5：打开 `/docs` 试一次

启动服务，打开 `/docs`，找到 `/health` 和 `/ping/{name}`，点 Try it out 发一次请求。

目标：体会自动文档，理解它是根据代码生成的。

---

## 4. JS/Node.js 类比

| FastAPI / Python | Node.js / Express 类比 | 说明 |
|---|---|---|
| `FastAPI()` | `express()` | 创建应用实例 |
| `@app.get("/x")` | `app.get("/x", fn)` | 注册路由 |
| `uvicorn` | `node` + `nodemon` | ASGI 服务器 / 热重载 |
| `return {...}` | `res.json({...})` | 返回 JSON |
| `/items/{id}` | `/items/:id` | 路径参数写法 |
| `pip install` | `npm install` | 安装依赖 |
| `requirements.txt` | `package.json` | 依赖清单 |
| `.venv/` | `node_modules/` | 依赖隔离目录 |
| `/docs`（内置） | swagger-ui-express（需接） | 自动 API 文档 |

---

## 5. AI Review 提问

完成练习后，把你的 `main.py`、目录结构和启动命令贴给 AI，然后问：

```text
我正在学习 FastAPI Day 01：搭建项目并实现 /health。

请你按资深后端工程师标准帮我检查：

1. 我的 FastAPI 项目结构合理吗？
2. venv 和 requirements.txt 的用法对吗？
3. /health 健康检查的实现有没有问题（比如是否应该查依赖）？
4. 我用 Express/Node 做的类比有没有误导？
5. 如果这是企业级 AI 网关项目，我还缺哪些工程规范？

请用中文输出：
- 我做对的地方
- 我做错或不完整的地方
- 修改建议
- 下一步练习任务
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [ ] 建好 `ai-lab/llm-gateway` 项目目录 + venv
- [ ] `app/main.py`，含 `/` 和 `/health`
- [ ] `requirements.txt`
- [ ] `/ping/{name}` 路径参数练习
- [ ] Express 对照代码
- [ ] 打开过 `/docs` 并发过一次请求
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] `/health` 可访问
- [ ] 能用 uvicorn 启动服务
- [ ] 能解释 FastAPI 和 uvicorn 各自的职责
- [ ] 能解释 venv 的作用以及和 node_modules 的类比
- [ ] 能说出 `@app.get` 装饰器的作用
- [ ] 能写出一个带路径参数的接口
- [ ] 能打开 `/docs` 并发一次请求
- [ ] 能说清 FastAPI 返回 dict 会自动变 JSON

---

## 8. 今日自测题

### 8.1 FastAPI 和 uvicorn 是什么关系？

参考答案：

> ✅ FastAPI 是写路由和业务逻辑的框架，它本身不监听端口；uvicorn 是 ASGI 服务器，负责真正监听端口、接收 HTTP 请求并把请求交给 FastAPI 处理。类比 Node：FastAPI 像你写的 Express 路由，uvicorn 像真正 `listen()` 的那层。

---

### 8.2 为什么要用 venv？

参考答案：

> ✅ 为了让每个项目的依赖互相隔离，避免不同项目的包版本互相打架。它相当于给 Python 项目做一个独立的 `node_modules`。

---

### 8.3 `@app.get("/health")` 做了什么？

参考答案：

> ✅ 它是一个装饰器，把下面的函数注册成 `GET /health` 接口。请求这个路径时，FastAPI 就会调用这个函数，并把它的返回值序列化成 JSON 响应。

---

### 8.4 健康检查接口里适合查数据库吗？

参考答案：

> ✅ 一般不适合（至少不要放在最基础的存活探针里）。健康检查要轻量，只回答「进程本身还能响应吗」。如果里面查数据库/调外部 API，外部一挂健康检查就超时，会导致监控误判、服务被误重启。深度检查可以单独做一个 `/health/ready` 接口。

---

### 8.5 FastAPI 的 `/docs` 是怎么来的？

参考答案：

> ✅ 它是 FastAPI 根据你的路由定义和类型注解自动生成的交互式文档（Swagger UI），不需要手写。这是 FastAPI 相比手动接 Swagger 的一大便利。

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
我正在进行 Week 13 Day 01：FastAPI 入门 的学习。
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
