# Ollama 新手上手指南

> 适用读者：第一次在本机运行大模型、想先用命令行跑通，再接入 Node.js 或其他客户端的开发者。
>
> 核对日期：2026-11-23。本文的命令、模型元数据和接口响应形状，来自本机 **Ollama 0.40.17（macOS）** 与 **deepseek-r1:1.5b** 的实际检查；没有修改系统配置，也没有做性能基准测试。
>
> 版本差异提醒：不同 Ollama 版本的命令输出、默认上下文和可用参数可能不同；先以 `ollama --help` 和 `ollama show MODEL --help` 为准。

---

## 1. Ollama 是什么

Ollama 是一个本机大模型运行器：

```text
下载模型 → 本地加载 → 命令行对话
                    ↘ HTTP API（原生 /api/*）
                    ↘ OpenAI 兼容接口（/v1/*）
                    ↘ 其他客户端/应用
```

它的价值是让模型权重、推理和接口都在本机管理，不必先申请云端 API key。

本文只演示已下载到本机的模型。Ollama 也提供云端能力：连接本机 `11434` 端口，不自动保证每个模型都在本机推理。需要纯本地使用时，应核对所选模型；`OLLAMA_NO_CLOUD` 可限制 Ollama 的云端功能，但不等于禁止下载、更新等所有网络访问。

**它不是：**

- 模型训练或微调工具。`ollama create` 主要用来封装模型参数和模板，不是训练。
- 统一的模型能力保证。同一个模型在不同参数量、量化等级下能力不同。
- 默认带鉴权的公网服务。默认只监听本机回环地址。

新手先记住一句话：**先确认服务在跑，再确认模型已下载，最后确认客户端地址和模型名一致。**

---

## 2. 安装与启动

### 2.1 安装

从官方下载页选择对应系统：

<https://ollama.com/download>

- **macOS / Windows**：安装桌面应用后，通常会在后台启动本地服务。
- **Linux**：按官方文档安装，不要从第三方博客复制来历不明的安装脚本：<https://docs.ollama.com/linux>。

安装后先验证：

```bash
ollama --version
```

能输出版本号，说明命令可用。本文验证环境为 `0.40.17`。

### 2.2 确认服务在运行

Ollama 的 HTTP 服务默认地址是：

```text
http://127.0.0.1:11434
```

检查：

```bash
curl -sS http://127.0.0.1:11434/api/version
```

预期返回类似：

```json
{"version":"0.40.17"}
```

如果提示连接失败：

1. 先确认桌面应用已启动。
2. 仍不行再手动启动服务：

```bash
ollama serve
```

`serve` 会占用当前终端。如果提示端口已被占用，通常说明服务已经在运行，**不要再启动第二个实例**。

### 2.3 关于端口和监听地址

服务地址由 `OLLAMA_HOST` 控制，默认 `127.0.0.1:11434`。

新手不要为了提高“可访问性”把它改成 `0.0.0.0`。那会把无鉴权的模型服务暴露给局域网甚至公网。确实需要远程访问时，用防火墙、反向代理和认证层单独设计。

---

## 3. 下载并运行第一个模型

本文示例使用一个小体积推理模型：

```bash
ollama pull deepseek-r1:1.5b
ollama run deepseek-r1:1.5b
```

说明：

- `pull` 只负责下载/更新模型文件。
- `run` 会加载模型；如果本地没有，也会先下载。
- 模型文件较大，首次需要网络和磁盘空间。

本机检查到的规格：

| 项目 | 值 |
|---|---|
| 参数量 | 1.8B |
| 量化 | Q4_K_M |
| 体积 | 约 1.1 GB |
| 架构上下文上限 | 131072 |
| 能力标签 | tools、thinking、completion |

进入交互界面后，直接输入问题并回车，例如：

```text
用一句话解释什么是 HTTP 状态码。
```

退出交互：

```text
/bye
```

也可以按 `Ctrl+D`。不同版本提示可能略有差异。

### 3.1 推理模型会先“思考”

DeepSeek-R1 属于思考型模型。回答前可能先输出较长的分析过程，再给正文。

如果不想在终端显示思考内容：

```bash
ollama run deepseek-r1:1.5b --hidethinking
```

想看本轮耗时等统计：

```bash
ollama run deepseek-r1:1.5b --verbose
```

**重要现象（本机实测）**：把输出预算设得过小，模型可能把全部预算花在思考上，正文为空。一次请求的观察结果：

```json
{
  "done": true,
  "done_reason": "length",
  "content": "",
  "thinking_chars": 292
}
```

看到“空回答”时，先检查 `done_reason` 是否为 `length`，不要立刻判定模型坏了。增加输出预算后，本机再次请求得到：

```json
{
  "done_reason": "stop",
  "content": "\n\n你好"
}
```

---

## 4. 常用命令速查

| 命令 | 作用 | 新手注意 |
|---|---|---|
| `ollama list` | 已下载的模型 | 只说明“有文件”，不代表已加载 |
| `ollama ps` | 正在加载的模型 | 空闲超时后会被卸载；列表为空很正常 |
| `ollama show MODEL` | 架构、参数量、量化、能力标签 | 能力标签不等于稳定可用 |
| `ollama pull MODEL` | 下载/更新模型 | 标签要写完整，例如 `:1.5b` |
| `ollama run MODEL` | 加载并对话 | 首次运行可能较慢 |
| `ollama stop MODEL` | 从内存卸载 | **不删除模型文件** |
| `ollama rm MODEL` | 删除本地模型文件 | 不可恢复，需要重新下载 |
| `ollama cp 源 新名字` | 复制模型/建立别名 | 常用于派生不同参数版本 |
| `ollama create` | 根据 Modelfile 创建模型 | 可固化 `num_ctx` 等参数 |
| `ollama serve` | 手动启动 HTTP 服务 | 桌面应用通常已代管 |
| `ollama push` | 上传模型到注册表 | 新手一般用不到 |

`stop` 与 `rm` 的区别最常被混淆：

```text
stop：释放内存，文件还在，下次还能直接 run
rm  ：删除文件，下次必须重新 pull
```

查看模型详情：

```bash
ollama show deepseek-r1:1.5b
ollama show deepseek-r1:1.5b --parameters
ollama show deepseek-r1:1.5b --modelfile
```

---

## 5. 模型名、标签和体积怎么理解

模型名通常由“名称 + 标签”组成：

```text
deepseek-r1:1.5b
     名称      标签
```

同一个名称下可能有多个标签，对应不同参数量或量化版本。下载前确认标签，避免以为装了 7B 实际装的是 1.5B。

选择模型时先看三件事：

| 维度 | 影响 | 新手建议 |
|---|---|---|
| 参数量 | 能力、内存占用、速度 | 先用小模型跑通流程，再比较 |
| 量化等级 | 体积、内存、质量权衡 | Q4 常见；不要只看体积 |
| 上下文窗口 | 能放多少提示词和历史 | 先确认运行时实际加载值 |

### 5.1 “能力标签”不等于“能力保证”

`ollama show` 显示本机模型带 `tools` 标签。它表示模型元数据声明了工具调用能力，**不代表**：

- 每次都能正确选择工具。
- 参数一定符合你的 Schema。
- 能稳定完成多步 Agent 任务。

接入 Agent 前，必须用真实工具集单独测试。

---

## 6. 上下文窗口：模型上限 ≠ 实际加载值

这是新手最容易踩的坑。

本机同一个模型出现两个数字：

| 位置 | 数值 | 含义 |
|---|---:|---|
| `ollama show` 的 context length | 131072 | 模型架构/训练配置的上限 |
| `/api/ps` 的 context_length | 4096 | **本次运行实际加载**的窗口 |

`ollama serve --help` 还说明：`OLLAMA_CONTEXT_LENGTH` 的默认值会根据显存选择 4K/32K/256K 一档。因此不能把模型介绍页的大窗口当作“已经生效”。

### 6.1 查看实际加载值

```bash
ollama ps
curl -sS http://127.0.0.1:11434/api/ps
```

只有模型正在加载时，`/api/ps` 才会显示条目。看到 `context_length` 后，再判断你的提示词是否放得下。

### 6.2 临时指定原生 API 的窗口

原生 `/api/chat` 可以在 `options` 中指定：

```json
{
  "model": "deepseek-r1:1.5b",
  "messages": [{ "role": "user", "content": "你好" }],
  "stream": false,
  "options": { "num_ctx": 16384, "num_predict": 512 }
}
```

OpenAI 兼容接口对参数的支持范围有限；需要固定窗口时，优先用下面的 Modelfile 方式，并在客户端选择派生模型。

### 6.3 用 Modelfile 固化窗口

在新建的练习目录中创建名为 `Modelfile` 的文本文件：

```dockerfile
FROM deepseek-r1:1.5b
PARAMETER num_ctx 16384
```

执行：

```bash
ollama create deepseek-r1:1.5b-16k -f Modelfile
ollama show deepseek-r1:1.5b-16k --parameters
```

这里创建的是新别名，不覆盖原模型，也不涉及训练。之后客户端应把模型名改为 `deepseek-r1:1.5b-16k`。

更大的窗口会更耗内存、增加预填时间。不要一开始就设到模型上限。

---

## 7. 原生 HTTP API

所有接口都基于：

```text
http://127.0.0.1:11434
```

### 7.1 常用只读端点

| 端点 | 用途 |
|---|---|
| `GET /api/version` | 服务版本 |
| `GET /api/tags` | 已下载模型列表 |
| `GET /api/ps` | 正在加载的模型与运行窗口 |
| `POST /api/show` | 模型详情 |

```bash
curl -sS http://127.0.0.1:11434/api/tags
curl -sS http://127.0.0.1:11434/api/ps
```

### 7.2 非流式对话

```bash
curl -sS http://127.0.0.1:11434/api/chat \
  -H 'Content-Type: application/json' \
  -d '{
    "model": "deepseek-r1:1.5b",
    "messages": [{ "role": "user", "content": "用一句话解释 HTTP。" }],
    "stream": false,
    "options": { "num_predict": 256 }
  }'
```

本机实测的响应顶层字段包括：

```text
created_at、done、done_reason、eval_count、eval_duration、
load_duration、message、model、prompt_eval_count、
prompt_eval_duration、total_duration
```

`message` 里包含：

```json
{
  "role": "assistant",
  "content": "正文回答",
  "thinking": "思考过程"
}
```

**正文在 `message.content`，思考在 `message.thinking`。** 不要把两者拼在一起展示给最终用户。

### 7.3 流式对话默认是 NDJSON

不传 `stream:false` 时，`/api/chat` 默认流式返回。它不是 SSE，而是**一行一个 JSON 对象**（NDJSON）。

本机实测一次流式请求返回 23 行，每行都带 `message`，最后一行 `done: true`。

因此不能用 `JSON.parse(整个响应体)`，必须逐行解析。

---

## 8. OpenAI 兼容接口

部分客户端只支持 OpenAI 协议，可以接 Ollama 的兼容层：

| 配置 | 值 |
|---|---|
| Base URL | `http://127.0.0.1:11434/v1` |
| API Key | 本地服务不校验；客户端必填时填 `ollama` |
| Model | `deepseek-r1:1.5b` |

验证：

```bash
curl -sS http://127.0.0.1:11434/v1/models
```

本机检查返回的模型条目包含：

```json
{
  "id": "deepseek-r1:1.5b",
  "object": "model",
  "owned_by": "library"
}
```

对话请求：

```bash
curl -sS http://127.0.0.1:11434/v1/chat/completions \
  -H 'Content-Type: application/json' \
  -d '{
    "model": "deepseek-r1:1.5b",
    "messages": [{ "role": "user", "content": "用一句话解释 HTTP。" }],
    "max_tokens": 256
  }'
```

本机实测响应是标准 OpenAI 形状，并包含：

```text
choices[0].message.content   正文
choices[0].message.reasoning 思考内容（本次版本的实际字段）
usage.prompt_tokens / completion_tokens / total_tokens
```

注意：

- 思考字段名可能随版本或客户端变化，按实际响应检查。
- OpenAI 兼容不等于支持全部 OpenAI 参数、工具调用或结构化输出。
- `/v1` 是 Base URL；不要再手动拼 `/chat/completions` 给自动拼接的客户端。
- 本地不需要真实密钥，不代表远程服务也不需要鉴权。

---

## 9. 用 Node.js 调用

Node.js 18+ 自带 `fetch`，不需要额外依赖。

### 9.1 非流式

```js
const response = await fetch("http://127.0.0.1:11434/api/chat", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({
    model: "deepseek-r1:1.5b",
    messages: [{ role: "user", content: "用一句话解释 HTTP。" }],
    stream: false,
    options: { num_predict: 256 },
  }),
  signal: AbortSignal.timeout(120_000),
});

if (!response.ok) {
  throw new Error(`Ollama 请求失败：HTTP ${response.status}`);
}

const data = await response.json();
console.log(data.message.content);
```

### 9.2 流式并逐行解析 NDJSON

```js
const response = await fetch("http://127.0.0.1:11434/api/chat", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({
    model: "deepseek-r1:1.5b",
    messages: [{ role: "user", content: "用三句话解释事件循环。" }],
    options: { num_predict: 512 },
  }),
  signal: AbortSignal.timeout(120_000),
});

if (!response.ok || !response.body) {
  throw new Error(`Ollama 请求失败：HTTP ${response.status}`);
}

const decoder = new TextDecoder();
let buffer = "";
let done = false;

function consumeLine(line) {
  if (!line.trim()) return;
  const event = JSON.parse(line);
  if (event.error) throw new Error("Ollama 返回流式错误；不打印原始响应。");
  if (event.message?.content) process.stdout.write(event.message.content);
  if (event.done) {
    done = true;
    console.log("\n[done]", event.done_reason);
  }
}

for await (const chunk of response.body) {
  buffer += decoder.decode(chunk, { stream: true });
  const lines = buffer.split("\n");
  buffer = lines.pop() ?? "";
  for (const line of lines) consumeLine(line);
}

buffer += decoder.decode(); // 冲刷解码器，并处理没有尾随换行的最后一条事件
if (buffer.trim()) consumeLine(buffer);
if (!done) throw new Error("流提前结束，未收到 done 事件；回答可能不完整。");
```

实践要点：

- 保留不完整的最后一行到 `buffer`，下一块数据到达后再解析；流结束时还要冲刷解码器并处理尾行。
- HTTP 200 也可能包含流式 `error` 事件；没有收到 `done` 不能当作完整成功。
- `done_reason === "length"` 时，回答可能被截断或因思考耗尽预算。
- 出现异常先输出安全的错误类别，不要把完整请求体或用户原文写进公开日志。
- 生产代码还需要超时、取消、并发上限和重试策略。

### 9.3 调用 OpenAI 兼容层

```js
const response = await fetch("http://127.0.0.1:11434/v1/chat/completions", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
    Authorization: "Bearer ollama", // 本地占位值，不是真实密钥
  },
  body: JSON.stringify({
    model: "deepseek-r1:1.5b",
    messages: [{ role: "user", content: "用一句话解释 HTTP。" }],
    max_tokens: 256,
  }),
  signal: AbortSignal.timeout(120_000),
});

if (!response.ok) throw new Error(`HTTP ${response.status}`);
const data = await response.json();
console.log(data.choices?.[0]?.message?.content ?? "");
```

不要把这个占位密钥写成真实 token，也不要提交任何本机配置到仓库。

---

## 10. 接入其他客户端

大多数桌面客户端只需要两项信息：

```text
Ollama 地址：http://127.0.0.1:11434
模型名：deepseek-r1:1.5b
```

使用 OpenAI 兼容模式时：

```text
Base URL：http://127.0.0.1:11434/v1
Model：deepseek-r1:1.5b
API Key：ollama（仅占位）
```

已有两篇专题：

- [Magpie 接入本地 Ollama](magpie-ollama接入指南.md)
- [Claude Code 提示词过长排查](claude-code-ollama-prompt-too-long.md)

客户端若运行在 Docker 容器里，容器内的 `127.0.0.1` 指向容器自身，不是宿主机。需要按所用平台的宿主访问方式调整，不能照抄回环地址。

---

## 11. 安全与资源管理

本机默认监听回环地址，且没有内置鉴权。请至少做到：

- 不把 `11434` 端口映射到公网。
- 不随意设置 `OLLAMA_HOST=0.0.0.0`。
- 远程访问必须另加认证、TLS 和网络边界。
- 日志和示例中不出现真实密钥、用户原文或内部数据。
- 敏感资料先确认是否可以交给本地模型处理；本地推理不等于自动合规。

资源方面：

```bash
ollama ps           # 谁在占用内存
ollama stop MODEL   # 释放内存
ollama rm MODEL     # 删除磁盘文件
```

模型加载后默认会保留一段时间，空闲后卸载。首次加载通常比后续请求慢。

---

## 12. 常见问题排查

| 现象 | 常见原因 | 处理 |
|---|---|---|
| `connection refused` | 服务未启动 | 启动应用，或手动 `ollama serve` |
| `address already in use` | 服务已在运行 | 直接使用现有服务，不要重复启动 |
| `model not found` | 未下载或标签写错 | `ollama list` 核对，再 `pull` 正确标签 |
| 回答为空 | 输出预算耗尽在思考上 | 看 `done_reason`，提高 `num_predict` |
| 回答一直不结束 | 输出预算过大或模型异常 | 设置 `AbortSignal` 超时，先发短问题 |
| 首次响应很慢 | 模型加载、CPU 推理 | 保持模型加载；用小模型先验证流程 |
| 提示词超长 | 实际加载窗口太小 | 查 `/api/ps`，提高 `num_ctx` 或用 Modelfile 固定 |
| 客户端连不上 | 地址、端口或容器网络不对 | 核对 `OLLAMA_HOST` 与实际访问路径 |
| 工具调用不稳定 | 小模型能力限制 | 单独评测，不因聊天可用就上 Agent |
| 磁盘占用变大 | 多个模型/标签 | `ollama list` 后按需 `ollama rm` |

### 上下文超限的排查顺序

```text
1. 模型是否在加载？        ollama ps
2. 实际窗口是多少？        curl /api/ps
3. 请求是否超过窗口？      减少历史/资料，或提高 num_ctx
4. 客户端是否用了正确模型？ 核对模型 ID，不只看别名
5. 是否仍在报错？          保留脱敏错误与请求规模，再查协议兼容性
```

---

## 13. 能力边界

本机示例模型是 1.8B 小模型，适合：

- 学习本机模型运行、API 和流式解析。
- 简单问答、文本整理、脚本实验。
- 验证客户端接入链路。

不适合直接承诺：

- 复杂代码库改造和长链路 Agent。
- 高可靠工具调用与结构化输出。
- 与在线大模型同等级的知识和推理质量。

**“能跑通”与“能用于生产”是两件事。** 生产前需要评估准确率、延迟、内存、并发、隐私和失败恢复。

---

## 14. 上手验收清单

- [ ] `ollama --version` 有输出
- [ ] `curl http://127.0.0.1:11434/api/version` 返回 JSON
- [ ] `ollama list` 能看到目标模型
- [ ] `ollama run MODEL` 能进行一轮问答
- [ ] 知道 `/bye` 或 `Ctrl+D` 退出
- [ ] `ollama ps` 能看出模型是否加载
- [ ] `/api/ps` 的实际窗口与预期一致
- [ ] 能区分 `ollama stop` 和 `ollama rm`
- [ ] 知道原生 API 的正文在 `message.content`
- [ ] 知道流式响应是逐行 JSON
- [ ] 知道 OpenAI 兼容的 Base URL 到 `/v1` 为止
- [ ] 不把本地服务暴露到公网

---

## 15. 参考

- [Ollama 官方下载](https://ollama.com/download)
- [Ollama 文档首页](https://docs.ollama.com/)
- [Chat API](https://docs.ollama.com/api/chat)
- [List running models（/api/ps）](https://docs.ollama.com/api/ps)
- [Modelfile 参数](https://docs.ollama.com/modelfile)
- [Context length](https://docs.ollama.com/context-length)
- [OpenAI 兼容接口](https://docs.ollama.com/api/openai-compatibility)
- [Ollama Cloud 说明](https://docs.ollama.com/cloud)

> 外部文档在本次编写时因网络策略未能在线复核；命令和接口形状依据本机 0.40.17 的实际输出。遇到版本差异时，以你本机的 `--help` 和实际响应为准。

---

**一句话总结：** 先让 `ollama --version`、`/api/version`、`ollama list` 三件事都成立，再跑第一轮对话；之后所有“连不上、答不出、上下文不够”的问题，都按“服务 → 模型 → 实际窗口 → 客户端配置”的顺序排查。
