# Magpie 接入本地 Ollama：deepseek-r1:1.5b

> 目标：让 Magpie 使用本机 Ollama 已下载的 `deepseek-r1:1.5b`，不经过云端模型服务。
>
> 核对日期：2026-11-23。本文核对过本机 Ollama 服务、模型列表和 OpenAI 兼容端点；**没有执行模型推理，也没有实际操作 Magpie 界面**。
>
> “Magpie”存在多个同名产品。本文以 [yetone/magpie](https://github.com/yetone/magpie)（本地 AI 网关）为主；如果你的 Magpie 是其他项目，请先确认设置页是否支持 Ollama 或 OpenAI-compatible。

---

## 1. 前提：Ollama 正在运行，模型已下载

先在本机终端确认：

```bash
ollama --version
ollama list
```

应能看到：

```text
deepseek-r1:1.5b
```

再确认 HTTP 服务可访问：

```bash
curl -sS http://127.0.0.1:11434/api/version
curl -sS http://127.0.0.1:11434/v1/models
```

本次核对结果：

- Ollama 版本：`0.40.17`
- 服务地址：`http://127.0.0.1:11434`
- `/v1/models` 返回模型 `deepseek-r1:1.5b`

不需要一直停留在 `ollama run deepseek-r1:1.5b` 的交互终端里。客户端连接的是 Ollama 后台服务；只要服务在运行即可。

---

## 2. 推荐方式：Magpie 原生 Ollama 预设

如果使用 yetone/magpie：

1. 打开 **Providers**。
2. 点击 **＋ Add provider → On this machine → Ollama**。
3. 按下表填写：

| 字段 | 填写值 |
|---|---|
| Server address | `http://127.0.0.1:11434` |
| API key | 留空 |

注意：这里填的是 **Ollama 根地址**，不要加 `/v1`，也不要填完整聊天接口路径。

4. 在模型列表中选择 `deepseek-r1:1.5b`。
5. 保存后，Magpie 中通常显示为：

```text
ollama/deepseek-r1:1.5b
```

6. 如果要给 Agent 使用，到 **Agents** 页面选择该模型，并新开一个会话使配置生效。

### 建议同时检查上下文窗口

Magpie 官方本地模型指南建议把 **Advanced → Context window** 设置为模型实际加载的上下文大小。接入成功不代表小模型能稳定完成长上下文或 Agent 任务。

---

## 3. 备选方式：OpenAI-compatible 自定义供应商

如果当前 Magpie 版本没有原生 Ollama 入口，可使用 OpenAI 兼容接口：

| 字段 | 填写值 |
|---|---|
| Provider type | OpenAI Compatible / Custom OpenAI |
| Base URL | `http://127.0.0.1:11434/v1` |
| API Key | 留空；客户端强制要求时填 `ollama` |
| Model | `deepseek-r1:1.5b` |

关键区别：

- ✅ **Base URL**：`http://127.0.0.1:11434/v1`
- ❌ 不是完整请求地址：`http://127.0.0.1:11434/v1/chat/completions`

多数客户端会自动拼接 `/chat/completions`。重复拼接会得到错误的 URL。

本地 Ollama 不校验这个 API key；填 `ollama` 只是满足某些客户端“必填”的表单校验，不是真实凭证。

---

## 4. 其他程序通过 Magpie 网关调用

如果你不是“让 Magpie 使用 Ollama”，而是“让别的客户端通过 Magpie 网关使用这个模型”，则配置对象要换成 Magpie 的网关：

| 字段 | 填写值 |
|---|---|
| Base URL | `http://127.0.0.1:3425/v1` |
| Model | `ollama/deepseek-r1:1.5b` |
| API Key | 本机回环连接可用占位值 `magpie`，以 Magpie 实际设置为准 |

不要混淆两个端口：

| 端口 | 作用 |
|---|---|
| `11434` | Ollama 自己的服务端口 |
| `3425` | Magpie 默认网关端口，通常另需在 Magpie 中开启 |

---

## 5. 验证接入

### 5.1 先验证 Ollama 本身

```bash
curl -sS http://127.0.0.1:11434/v1/models
```

返回列表中包含：

```json
{ "id": "deepseek-r1:1.5b", "object": "model" }
```

### 5.2 再在 Magpie 中发一条短消息

建议输入一个简单、可判断的问题，例如：

```text
用一句话解释什么是 HTTP 状态码。
```

能返回文本即说明基本链路可用。

注意：

- 首次加载模型可能较慢，1.5B 模型在本机也可能需要数秒预热。
- `deepseek-r1` 是推理型模型，回答前可能出现较长的思考过程；这不等同于卡死。
- 若 Magpie 显示空回答，先检查它是否把“思考内容”和“最终回答”分开渲染。

---

## 6. 常见问题

| 现象 | 常见原因 | 处理 |
|---|---|---|
| `connection refused` | Ollama 服务未启动 | 启动 Ollama 应用或服务后重试 |
| 模型不存在 / 404 | Magpie 中模型名写错 | 使用 `deepseek-r1:1.5b`，不要加 `ollama:` 或路径 |
| 404 或路径重复 | Base URL 与客户端自动拼接冲突 | OpenAI 兼容填到 `/v1` 为止 |
| Docker 中连不上 `127.0.0.1` | 容器内回环地址不是宿主机 | 使用 Magpie 提供的宿主访问地址；macOS/Windows Docker Desktop 常见为 `host.docker.internal`，以实际环境为准 |
| 回答很慢 | 首次加载、CPU 推理或上下文过长 | 先发短问题；检查上下文窗口设置 |
| 回答质量差 | 1.8B 小模型能力有限 | 用于接入验证和简单文本任务，不直接当作生产质量结论 |
| 工具/Agent 不稳定 | 小模型的工具调用可靠性有限 | 单独做工具调用测试，不因聊天可用就认定 Agent 可用 |

---

## 7. 安全与边界

- `127.0.0.1` 只适合 Magpie 与 Ollama 在同一台机器、同一网络命名空间。
- 不要把 `11434` 直接暴露到公网。Ollama 默认没有面向公网的鉴权。
- 本机接入不需要真实密钥；不要为了“像云端配置”而填入真实 API key。
- 模型会在本机处理输入；如果输入包含敏感资料，仍需按本地数据安全要求管理。
- 如果 Magpie 运行在容器中，容器访问宿主机服务需要额外地址映射，不能照抄回环地址。

---

## 8. 能力边界（不要过度推断）

- 本机 Ollama 的模型元数据把 `deepseek-r1:1.5b` 标记为具备 `tools` 能力，但**能力标签不等于可靠的工具调用**。
- `deepseek-r1:1.5b` 是约 1.8B 参数的小模型。普通问答、接入验证和教学演示可用；复杂 Agent、长上下文和多步工具编排需要单独评测。
- 本文只核对了模型列表和兼容端点，**没有实际运行推理**，因此不声称生成质量、工具调用或 Magpie 侧显示已经验证。
- 如果 Magpie 是另一个同名产品，以上菜单名称和字段可能不同；请以该项目官方文档准。

---

## 9. 最小检查清单

- [ ] `ollama list` 能看到 `deepseek-r1:1.5b`
- [ ] `curl http://127.0.0.1:11434/v1/models` 能返回该模型
- [ ] Magpie 原生 Ollama：地址填 `http://127.0.0.1:11434`，不加 `/v1`
- [ ] OpenAI 兼容：地址填 `http://127.0.0.1:11434/v1`
- [ ] 模型名统一使用 `deepseek-r1:1.5b`
- [ ] 在 Magpie 中发一条短消息并得到文本回答
- [ ] 需要 Agent 时，单独验证工具调用，不把聊天成功当成 Agent 成功

---

## 参考

- [Magpie 官方本地 Ollama 接入指南](https://usemagpie.ai/docs/local-models)
- [Magpie 提供商与网关说明](https://github.com/yetone/magpie/blob/main/docs/reference.md#providers-and-the-gateway)
- [Ollama OpenAI 兼容接口](https://docs.ollama.com/api/openai-compatibility)
- [Ollama API 地址说明](https://docs.ollama.com/api/introduction)
- 同名产品（划词阅读工具）：[demo007x/Magpie](https://github.com/demo007x/Magpie)

---

**一句话总结：** Magpie 走原生 Ollama 预设时填 `http://127.0.0.1:11434`；走 OpenAI 兼容时填 `http://127.0.0.1:11434/v1`；模型统一选 `deepseek-r1:1.5b`，本地接入不需要真实 API key。
