# Week 22 Day 03：MCP 集成

> 所属周：Week 22：毕业项目：全栈实现  
> 阶段：第六阶段：毕业项目  
> 主仓库/项目：`graduation-project`  
> 类型：编码练习  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

给运营知识助手接入 MCP 工具（订单查询、商品查询），让大模型能"查真实数据"再回答，而不是瞎编。

今天你要真正掌握这一句话：

> MCP（Model Context Protocol）是"大模型调用外部工具的标准接口"；我们把"查订单""查商品"注册成 Tool，大模型判断需要数据时就发起 Tool 调用，PHP 执行真实查询后把结果喂回大模型——这就像给 AI 装了一双能查数据库的手。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 理解为什么需要 MCP / Tool（大模型不知道实时数据）
2. 理解 Tool 调用的完整回合流程
3. 设计"查订单""查商品"两个 Tool 的定义（名字、参数、描述）
4. 用 PHP 实现 Tool 的真实执行逻辑
5. 把 Tool 结果喂回大模型，拿到最终回答
6. 加上 Tool 失败的 fallback（降级）
7. 前端展示"正在查询…"中间态
8. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 为什么需要 MCP / Tool

大模型的知识是"训练时固定"的，它不知道：

- 用户订单号 `SN-8842` 现在是什么状态
- 商品 `SKU-201` 还有没有货

如果直接问，它会"一本正经地编"。解决办法：给它工具，让它去查真实数据。

```text
用户：我的订单 SN-8842 到哪了？
   │
大模型："我需要查订单" → 发起 Tool 调用 queryOrder(orderNo="SN-8842")
   │
PHP：执行真实查询 → { status: "运输中", eta: "明天" }
   │
大模型：拿到数据 → "您的订单 SN-8842 正在运输中，预计明天送达。"
```

小白重点：**MCP 是协议/规范，Tool 是具体的工具**。大模型不直接查数据库，它只会"说我要调哪个工具、传什么参数"，真正执行的是我们的 PHP 代码。

---

### 1.2 Tool 调用的完整回合

一次带 Tool 的对话，可能要和大模型来回两次：

```text
第 1 回合：
  PHP → 大模型：用户问题 + 可用工具列表
  大模型 → PHP：我要调 queryOrder，参数 {orderNo:"SN-8842"}
             （这一步大模型还没给最终答案）

PHP 执行 queryOrder → 得到订单数据

第 2 回合：
  PHP → 大模型：把 Tool 执行结果发回去
  大模型 → PHP：最终自然语言回答
```

小白重点：Tool 调用是"多轮"的。第一轮大模型只是"点单"（说要调什么），第二轮才根据数据给答案。PHP 在中间负责"上菜"。

---

### 1.3 设计 Tool 定义

Tool 定义告诉大模型"有哪些工具、怎么用"。核心三要素：名字、描述、参数。

```php
<?php
// Tool 定义（发给大模型时用）
$tools = [
    [
        "name" => "queryOrder",
        "description" => "根据订单号查询订单状态、物流信息。用户问订单相关问题时使用。",
        "parameters" => [
            "type" => "object",
            "properties" => [
                "orderNo" => [
                    "type" => "string",
                    "description" => "订单号，例如 SN-8842",
                ],
            ],
            "required" => ["orderNo"],
        ],
    ],
    [
        "name" => "queryProduct",
        "description" => "根据商品编号查询商品库存、价格。用户问商品相关问题时使用。",
        "parameters" => [
            "type" => "object",
            "properties" => [
                "sku" => [
                    "type" => "string",
                    "description" => "商品编号，例如 SKU-201",
                ],
            ],
            "required" => ["sku"],
        ],
    ],
];
```

小白重点：`description` 写得越清楚，大模型越知道"什么时候该调这个工具"。这是"提示词工程"的一部分，别偷懒。

对比 TypeScript 里定义函数签名：

```ts
// 大模型的 Tool 定义 ≈ 给函数写清楚的类型和注释
function queryOrder(orderNo: string): OrderInfo { /* ... */ }
```

| 对比项 | MCP Tool 定义 | TS 函数 |
|---|---|---|
| 名字 | `name` | 函数名 |
| 描述 | `description`（给 AI 看） | 注释/文档 |
| 参数类型 | JSON Schema | TS 类型 |
| 谁来"调用" | 大模型决定 | 程序员写代码调 |

---

### 1.4 用 PHP 实现 Tool 执行逻辑

Tool 定义只是"说明书"，真正干活的是 PHP 函数：

```php
<?php
declare(strict_types=1);

// 工具注册表：名字 => 执行函数
class ToolRegistry
{
    public function call(string $name, array $args): array
    {
        return match ($name) {
            "queryOrder"   => $this->queryOrder($args["orderNo"] ?? ""),
            "queryProduct" => $this->queryProduct($args["sku"] ?? ""),
            default => ["error" => "未知工具: {$name}"],
        };
    }

    private function queryOrder(string $orderNo): array
    {
        if ($orderNo === "") {
            return ["error" => "订单号不能为空"];
        }
        // 真实项目：查数据库 / 调订单服务
        // 这里用模拟数据
        $fake = [
            "SN-8842" => ["status" => "运输中", "eta" => "明天", "carrier" => "示例快递"],
            "SN-1001" => ["status" => "已签收", "eta" => "已完成", "carrier" => "示例快递"],
        ];
        return $fake[$orderNo] ?? ["error" => "订单不存在"];
    }

    private function queryProduct(string $sku): array
    {
        if ($sku === "") {
            return ["error" => "商品编号不能为空"];
        }
        $fake = [
            "SKU-201" => ["name" => "示例商品A", "stock" => 42, "price" => 99.0],
            "SKU-305" => ["name" => "示例商品B", "stock" => 0,  "price" => 199.0],
        ];
        return $fake[$sku] ?? ["error" => "商品不存在"];
    }
}
```

小白重点：用 `match` 做"工具路由"，把工具名映射到对应函数。真实项目里 `queryOrder` 内部会查数据库或调订单微服务，这里先用假数据跑通流程。

---

### 1.5 把 Tool 结果喂回大模型

完整的一次带 Tool 的对话代理逻辑（伪协议，重在理解流程）：

```php
<?php
declare(strict_types=1);

function chatWithTools(string $userMessage): array
{
    $registry = new ToolRegistry();

    // ==== 第 1 回合：带工具定义问大模型 ====
    $resp1 = callLlm([
        "messages" => [["role" => "user", "content" => $userMessage]],
        "tools" => getToolDefinitions(), // 见 1.3
    ]);

    // 大模型说"不用工具"，直接给答案
    if (empty($resp1["tool_calls"])) {
        return ["answer" => $resp1["content"], "sources" => []];
    }

    // ==== 大模型要调工具，PHP 执行 ====
    $toolResults = [];
    foreach ($resp1["tool_calls"] as $call) {
        $name = $call["name"];
        $args = $call["arguments"]; // 大模型给的参数
        $result = $registry->call($name, $args); // 真实执行
        $toolResults[] = [
            "tool" => $name,
            "args" => $args,
            "result" => $result,
        ];
    }

    // ==== 第 2 回合：把工具结果喂回去，拿最终回答 ====
    $resp2 = callLlm([
        "messages" => [
            ["role" => "user", "content" => $userMessage],
            ["role" => "assistant", "tool_calls" => $resp1["tool_calls"]],
            ["role" => "tool", "content" => json_encode($toolResults, JSON_UNESCAPED_UNICODE)],
        ],
    ]);

    return [
        "answer" => $resp2["content"],
        "sources" => [],
        "toolTrace" => $toolResults, // 方便调试/展示
    ];
}
```

小白重点：注意消息数组里多了 `role: "tool"`——这是把工具执行结果"塞回对话历史"，大模型才能基于真实数据回答。这就是"两回合"的代码体现。

---

### 1.6 Tool 失败的 fallback（降级）

工具会失败（订单服务挂了、超时）。不能让整个对话崩，要降级：

```php
<?php
private function queryOrder(string $orderNo): array
{
    try {
        $data = $this->orderService->find($orderNo); // 可能抛异常
        if ($data === null) {
            return ["error" => "订单不存在", "fallback" => "请核对订单号是否正确"];
        }
        return $data;
    } catch (\Throwable $e) {
        // 服务挂了：不抛给用户，返回可降级的提示
        error_log("queryOrder failed: " . $e->getMessage());
        return [
            "error" => "订单服务暂时不可用",
            "fallback" => "系统繁忙，请稍后再问，或联系人工客服。",
        ];
    }
}
```

大模型拿到 `fallback` 字段，会用友好的话告诉用户"暂时查不了"，而不是报错崩溃。

小白重点：**Tool 失败 fallback 是本周 php-pro 能力对齐点之一**。真实系统里"下游会挂"是常态，工具层必须优雅降级。

对比 Node 里的错误处理：

```js
async function queryOrder(orderNo) {
  try {
    return await orderService.find(orderNo);
  } catch (e) {
    return { error: "订单服务暂时不可用", fallback: "请稍后再试" };
  }
}
```

| 对比项 | PHP | Node |
|---|---|---|
| 捕获异常 | `try/catch (\Throwable $e)` | `try/catch (e)` |
| 记日志 | `error_log()` | `console.error()` |
| 降级返回 | 返回带 `fallback` 的数组 | 返回带 `fallback` 的对象 |

---

### 1.7 前端展示"正在查询…"中间态

Tool 调用会多花时间。前端应给用户反馈，而不是干等：

```js
// ChatView.vue 里，收到流式事件时判断类型
await streamChat(text, (event) => {
  const data = JSON.parse(event);
  if (data.type === "tool_start") {
    aiMsg.status = `正在查询${data.toolLabel}…`; // 如"正在查询订单…"
  } else if (data.type === "chunk") {
    aiMsg.status = "";
    aiMsg.content += data.text;
  }
});
```

模板里显示状态：

```vue
<div v-if="msg.status" class="tool-status">
  🔧 {{ msg.status }}
</div>
```

小白重点：好的对话产品会显示"正在查订单…""正在检索政策…"这类中间态，让用户知道 AI 在干活，而不是卡死了。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

如果本地有 MCP dev 工具或 Gateway 的工具配置，可对照阅读，重点找：

1. Tool 定义写在哪（名字、描述、参数 schema）
2. Tool 执行函数在哪、怎么被路由
3. 工具结果如何塞回对话
4. 失败降级怎么处理

建议在笔记里画出"两回合"时序图。

---

## 3. 练习任务

### 练习 1：写两个 Tool 定义

按 1.3 写出 `queryOrder` 和 `queryProduct` 的定义，重点打磨 `description`，让它清楚说明"什么时候该用"。

目标：理解 Tool 定义是"给 AI 看的说明书"。

---

### 练习 2：实现 ToolRegistry

按 1.4 写 `ToolRegistry` 类，用假数据实现两个查询。写个小脚本直接测：

```php
<?php
$registry = new ToolRegistry();
var_dump($registry->call("queryOrder", ["orderNo" => "SN-8842"]));
var_dump($registry->call("queryProduct", ["sku" => "SKU-305"]));
var_dump($registry->call("queryOrder", ["orderNo" => "SN-9999"])); // 不存在
```

目标：工具执行逻辑跑通，能正确处理"不存在"。

---

### 练习 3：串起两回合流程

按 1.5 写 `chatWithTools`，`callLlm` 先用模拟版（写死"大模型决定调 queryOrder"）：

```php
<?php
function callLlm(array $req): array
{
    // 模拟：如果用户问里有"订单"，就说要调 queryOrder
    $lastMsg = end($req["messages"])["content"] ?? "";
    if (str_contains($lastMsg, "订单") && !isset($req["messages"][2])) {
        return [
            "content" => "",
            "tool_calls" => [
                ["name" => "queryOrder", "arguments" => ["orderNo" => "SN-8842"]],
            ],
        ];
    }
    // 第二回合：基于工具结果给答案
    return ["content" => "您的订单 SN-8842 正在运输中，预计明天送达。", "tool_calls" => []];
}
```

目标：理解"点单 → 上菜 → 回答"的完整回合。

---

### 练习 4：加 fallback

按 1.6 给 `queryOrder` 加 try/catch，手动 `throw` 一个异常测试降级路径，确认返回的是友好提示而不是崩溃。

目标：掌握 Tool 失败降级。

---

### 练习 5：整理 Tool 调用时序笔记

在笔记里写清楚：哪几步是 PHP 干的、哪几步是大模型干的、`role: tool` 消息的作用。

目标：能独立讲清 MCP Tool 调用流程。

---

## 4. JS/Node.js 类比

| MCP / Tool 概念 | Node 类比 | 说明 |
|---|---|---|
| Tool 定义 | 带 JSDoc 的函数签名 | 告诉调用方怎么用 |
| Tool 执行函数 | 普通业务函数 | 真正查数据的地方 |
| 大模型发起 Tool 调用 | 事件触发回调 | "谁调"由 AI 决定，不是程序员 |
| `role: tool` 消息 | 回调结果传回 | 把执行结果喂回对话 |
| ToolRegistry `match` 路由 | `switch` / 路由表 | 名字映射到函数 |
| fallback 降级 | `try/catch` 返回默认值 | 下游挂了不崩 |
| 两回合对话 | async 多次 await | 需要来回交互 |

一句话类比：

> MCP Tool 就像给大模型一份"函数目录"，它决定调哪个、传什么参数，PHP 负责真正执行并把结果喂回去——调用方是 AI，执行者是你的 PHP。

---

## 5. AI Review 提问

完成练习后，把 `ToolRegistry` 和 `chatWithTools` 贴给 AI，然后问：

```text
我正在学习 Week 22 Day 03：给对话助手接入 MCP Tool（订单/商品查询）。

请你按资深 PHP 后端 + AI 工程师标准帮我检查：

1. 我的 Tool 定义（name/description/parameters）写得清楚吗？AI 能正确判断何时调用吗？
2. 两回合调用流程实现对不对？role:tool 消息用对了吗？
3. Tool 失败的 fallback 够健壮吗（超时、下游 5xx、参数非法）？
4. 有没有安全风险（比如大模型传了恶意参数，我直接查库）？
5. 真实项目里工具层还要加什么（限流、缓存、权限校验）？

请用中文输出：
- 我做对的地方
- 我的问题清单
- 修改建议
- 下一步练习
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [✅] `queryOrder` / `queryProduct` 两个 Tool 定义
- [✅] `ToolRegistry` 类（工具路由 + 执行）
- [✅] `chatWithTools` 两回合调用逻辑
- [✅] Tool 失败 fallback 实现
- [✅] 前端"正在查询…"中间态
- [✅] Tool 调用时序笔记
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说清为什么需要 MCP / Tool
- [ ] 能画出 Tool 调用的两回合流程
- [ ] 能写出清晰的 Tool 定义
- [ ] 能用 PHP 实现 Tool 执行并路由
- [ ] 能把 Tool 结果喂回大模型
- [ ] 能实现 Tool 失败 fallback
- [ ] 能说清"调用方是 AI、执行者是 PHP"的关系

---

## 8. 今日自测题

### 8.1 为什么大模型需要 Tool？

参考答案：

> ✅ 大模型的知识是训练时固定的，不知道实时数据（订单状态、库存）。给它 Tool，让它在需要时发起调用，由 PHP 查真实数据后喂回，避免它瞎编。

---

### 8.2 Tool 调用为什么是"两回合"？

参考答案：

> ✅ 第一回合大模型只"决定调哪个工具、传什么参数"（还没答案）；PHP 执行工具得到数据；第二回合把结果通过 `role:tool` 消息喂回，大模型才基于真实数据给最终回答。

---

### 8.3 Tool 定义里 `description` 为什么重要？

参考答案：

> ✅ 它是给大模型看的说明书，决定"AI 什么时候会调这个工具"。写得含糊，AI 就会该调不调或乱调。清晰的描述是提示词工程的一部分。

---

### 8.4 Tool 失败时应该怎么做？

参考答案：

> ✅ 用 try/catch 捕获异常，记录日志，返回带 `fallback` 字段的友好提示（如"服务暂时不可用，请稍后再试"），让大模型用友好的话回复用户，而不是让整个对话崩溃。

---

### 8.5 "调用方"和"执行者"分别是谁？

参考答案：

> ✅ 调用方是大模型（它决定调哪个工具、传什么参数）；执行者是我们的 PHP 代码（真正查数据库、调服务）。大模型不直接碰数据。

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
我正在进行 Week 22 Day 03：MCP 集成 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确（Tool 定义、两回合、fallback）
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险（安全、超时、权限）
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 22 README](./README.md)
