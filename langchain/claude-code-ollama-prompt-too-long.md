# Claude Code 接入本地 Ollama：Prompt is too long 排查与修复

> 适用场景：Claude Code → Magpie → Ollama → `deepseek-r1:1.5b`。
>
> 核对日期：2026-11-23。本文记录本机配置和 HTTP 元数据的检查结果，给出修复建议；没有实际修改配置、创建新模型或执行修复后的推理验证。
>
> Magpie 接入步骤先看 [Magpie / Ollama 接入指南](magpie-ollama接入指南.md)。

## 1. 现象：清空会话后，“你好”也超长

终端显示使用 DeepSeek-R1 / Ollama。执行 `/clear`，然后输入：

```text
你好
```

仍然报错：

```text
Prompt is too long · this conversation is a single exchange and cannot be compacted — the request size comes mostly from system prompt, tool definitions, or attachments.
```

这不是在说“你好”两个字太长，而是整次模型请求过大。

Claude Code 的请求可能包括：

```text
系统提示 + 项目说明 + 工具定义 + Skills/MCP 等上下文 + 聊天消息
```

`/clear` 会清空聊天历史，但不会移除所有固定系统提示和工具定义。只有一轮对话时，也没有足够的聊天历史可供压缩。

**因此，反复执行 `/clear` 或 `/compact` 不是这个场景的根本修复。**

## 2. 本次实际检查结果

| 检查对象 | 本次观察值 | 含义 |
|---|---:|---|
| Ollama 已加载模型的 `context_length` | **4096** | 当前运行实例实际加载的上下文窗口 |
| Magpie 模型元数据的 `context_window` / `max_input_tokens` | **128000** | 网关向客户端宣告的输入能力 |
| Magpie 模型元数据的 `max_output_tokens` | **32768** | 网关宣告的最大输出能力 |
| Claude Code 的 `CLAUDE_CODE_MAX_OUTPUT_TOKENS` | **32768** | 当前客户端输出预算配置 |
| 原模型保存的参数 | 没有 `num_ctx` | 未在该模型的保存参数里固定运行窗口 |

这里存在一个已确认的不一致：

```text
Magpie 宣告 128000
        ↓
Ollama 实际加载 4096
```

**模型理论支持的窗口、网关宣告的窗口、运行时实际加载的窗口，不是同一个概念。**

输出预算也需要与可用窗口协调；不能用提高 `max_tokens` 代替扩大实际输入窗口。

这些事实足以解释此类上下文超限问题，但本次没有截获原失败请求，也没有测量它精确的输入 token 数，不能据此排除其他兼容性问题。

### 2.1 当时的模型别名映射

用户级 Claude Code 配置把三个默认别名都映射到同一个模型：

```json
{
  "ANTHROPIC_DEFAULT_HAIKU_MODEL": "ollama/deepseek-r1:1.5b",
  "ANTHROPIC_DEFAULT_SONNET_MODEL": "ollama/deepseek-r1:1.5b",
  "ANTHROPIC_DEFAULT_OPUS_MODEL": "ollama/deepseek-r1:1.5b"
}
```

这意味着：

- 切换到 sonnet 或 opus 别名，仍然使用这个本地 R1 模型。
- 依赖这些默认别名的辅助模型、子 Agent 等调用，也可能遇到同样的上下文问题。
- 界面上的别名不等于实际发送给网关的模型已经改变。

排错时应核对实际模型 ID，而不是只看别名。

## 3. 先检查自己的环境

以下命令都是元数据检查，不要求发送生成请求。

### 3.1 已安装与已加载模型

```bash
ollama list
ollama ps
```

`ollama list` 说明模型已安装；`ollama ps` 说明哪些模型正在加载。两者不能互相替代。

检查实际加载窗口：

```bash
curl -sS http://127.0.0.1:11434/api/ps
```

关注对应模型的：

```json
{
  "name": "deepseek-r1:1.5b",
  "context_length": 4096
}
```

模型没有加载时，列表可能为空；这不是模型不存在的证据。

### 3.2 查看原模型保存的参数

```bash
ollama show deepseek-r1:1.5b --parameters
```

如果没有 `num_ctx`，不要把模型介绍页上的大窗口当成本次运行已经加载的窗口。

### 3.3 查看 Magpie 宣告的窗口

```bash
curl -sS http://127.0.0.1:3425/v1/models
```

只查找 ID 为 `ollama/deepseek-r1:1.5b` 的条目，不要把其他模型的能力拿来套用。

有些部署需要认证；按自己的 Magpie 配置操作，不公开凭据或完整配置文件。

## 4. 推荐修复：固定一个 64K 上下文的本地别名

建议先用 `65536` 作为排查起点，不直接把所有配置标成 128K。

**更大的上下文会增加内存占用和提示词预填时间。** 64K 不是对所有机器的性能保证；需要结合自己的硬件和实际请求占用判断。

### 4.1 创建 Modelfile

在一个新的练习目录中创建名为 Modelfile 的文本文件，写入：

```dockerfile
FROM deepseek-r1:1.5b
PARAMETER num_ctx 65536
```

然后在该目录执行：

```bash
ollama create deepseek-r1:1.5b-64k -f Modelfile
```

这里创建的是新模型别名及其参数配置：

- 复用已安装的 R1 权重。
- 不进行训练或微调。
- 不覆盖原来的 `deepseek-r1:1.5b`。
- 新别名是 `deepseek-r1:1.5b-64k`。

创建后可检查：

```bash
ollama show deepseek-r1:1.5b-64k --parameters
```

预期保存参数中包含 `num_ctx 65536`。

### 4.2 在 Magpie 中刷新并选择新别名

在 Ollama 提供商的模型列表中刷新模型，选择：

```text
ollama/deepseek-r1:1.5b-64k
```

将该模型的 **Advanced → Context window** 设置为：

```text
65536
```

确保网关宣告值和目标运行窗口一致。只改 Magpie 的能力标签，不代表 Ollama 已实际按这个窗口加载。

## 5. 协调 Claude Code 的模型映射和输出预算

修改的是[用户级配置](https://code.claude.com/docs/en/settings#settings-files)中已有的 env 对象，不是本课程的环境变量模板。

下面只展示需要合并修改的字段：

```json
{
  "env": {
    "ANTHROPIC_DEFAULT_HAIKU_MODEL": "ollama/deepseek-r1:1.5b-64k",
    "ANTHROPIC_DEFAULT_SONNET_MODEL": "ollama/deepseek-r1:1.5b-64k",
    "ANTHROPIC_DEFAULT_OPUS_MODEL": "ollama/deepseek-r1:1.5b-64k",
    "CLAUDE_CODE_MAX_OUTPUT_TOKENS": "2048"
  }
}
```

注意：

1. **不要用这个片段覆盖整个配置文件。** 保留已有权限、插件、Hooks、网关地址和其他 env 字段。
2. `ANTHROPIC_BASE_URL` 仍指向 Magpie 网关，不要误改为 Ollama 的 OpenAI 端点。
3. 这次用较小输出预算做接线验证，不代表 2048 tokens 足以完成所有推理或编码任务。
4. 新别名必须先在 Magpie 中可见，模型 ID 要与网关返回值一致。
5. 不需要填写、公开或复制真实 API key 到说明文档。

## 6. 重启会话并验证实际效果

完成配置后，重启对应的 Claude Code 会话，再发送一条短消息。

随后检查：

```bash
ollama ps
curl -sS http://127.0.0.1:11434/api/ps
```

确认：

- 实际加载的是新别名，而不是旧模型。
- 对应运行实例的 `context_length` 不再是 4096，并与目标配置一致。
- Magpie 宣告的模型窗口也与之协调。

不能仅凭 Modelfile 写了 65536 或 Magpie 界面显示 64K，就宣称运行窗口已经生效。

在你的 Claude Code 会话中，还可以运行：

```text
/context
```

查看系统提示、工具、项目说明和会话历史的占用。该命令的具体显示以你安装的版本为准；本次未在故障终端中执行它。

## 7. 如果仍然提示过长

按以下顺序继续检查：

| 检查项 | 判断方式 | 后续动作 |
|---|---|---|
| 仍加载旧模型 | 实际模型 ID 与新别名不同 | 检查映射、Magpie 模型列表和会话是否重启 |
| 实际窗口仍小 | `/api/ps` 不符合保存参数 | 检查运行时参数覆盖与服务加载行为 |
| 固定提示仍超过窗口 | 查看 `/context` | 只启用当前任务需要的 MCP、插件和上下文 |
| 输出/推理预算不协调 | 检查实际请求与配置 | 使用服务支持的预算设置，不盲目提高输出上限 |
| 窗口匹配后仍失败 | 保留脱敏错误类别和请求规模 | 再检查网关协议转换与模型兼容性 |
| 能聊天但 Agent 不稳定 | 工具选择、参数或多步任务失败 | 单独评测，不把聊天成功当成编码 Agent 成功 |

**不要通过关闭权限检查或沙箱解决上下文问题。** 这不是权限错误。

## 8. 验收清单与能力边界

- [ ] 新模型别名保存了目标 `num_ctx`。
- [ ] Magpie 已识别新模型，宣告窗口与目标一致。
- [ ] Claude Code 实际使用新模型 ID。
- [ ] 客户端输出预算不再沿用原来的 32768 做最小接线测试。
- [ ] `/api/ps` 显示运行窗口已经生效。
- [ ] 清空后发送短消息不再因请求规模超限而失败。
- [ ] 另行验证工具调用和编码任务，不把成功接入当成可靠 Agent 能力。

`deepseek-r1:1.5b` 适合本地接线验证和简单任务实验。窗口扩大只改变可处理上下文的配置，不会把小模型变成能力更强的模型。

### 本文已做与未做

**已检查：** Ollama 已加载模型、运行窗口、保存参数，Magpie 模型能力元数据，以及 Claude Code 的非敏感模型映射和输出预算字段。

**未执行：** 新别名创建、配置改写、模型推理、修复后的故障会话验证。

本文也没有检查原失败请求的完整内容或精确 token 数；上述数值是本次观察快照，读者应检查自己的环境。

## 参考

- [Ollama OpenAI 兼容接口](https://docs.ollama.com/api/openai-compatibility)
- [Ollama Modelfile 参数说明](https://docs.ollama.com/modelfile)
- [Magpie 本地模型指南](https://usemagpie.ai/docs/local-models)
- [Claude Code 设置说明](https://code.claude.com/docs/en/settings)

**一句话总结：** 先让 Ollama 实际加载窗口与 Magpie 宣告窗口一致，再协调客户端预算；`/clear` 不能消除 Claude Code 固定的系统提示和工具负载。
