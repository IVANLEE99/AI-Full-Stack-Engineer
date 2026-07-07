# Week 22 Day 02：PHP 薄 API 层

> 所属周：Week 22：毕业项目：全栈实现  
> 阶段：第六阶段：毕业项目  
> 主仓库/项目：`graduation-project`  
> 类型：源码阅读  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

用 PHP 写一个"薄 API 层"（对话代理接口）：前端把用户问题发给 PHP，PHP 不自己生成答案，而是转发给 LLM Gateway（大模型网关），再把结果（含流式）回传给前端。

今天你要真正掌握这一句话：

> 薄 API 层（BFF）的职责不是"干活"，而是"转发 + 拼装 + 鉴权 + 统一格式"；PHP 收到前端请求后，加上鉴权和上下文，转发给 LLM Gateway，把大模型的流式输出边收边转发给前端——它像一个"懂业务的中间人"，而不是真正生成答案的人。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 理解什么是"薄 API 层 / BFF"，它为什么存在
2. 理解对话代理接口的数据流（前端 → PHP → LLM Gateway → PHP → 前端）
3. 设计统一响应格式（成功/失败长一个样）
4. 写一个普通（非流式）的对话代理接口
5. 理解 SSE 流式，把接口改成流式转发
6. 前端对接：用 fetch 读流式响应
7. 加上错误处理和鉴权占位
8. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 什么是"薄 API 层"（BFF）

BFF = Backend For Frontend，专门服务前端的后端层。它"薄"在：**几乎不写业务逻辑，主要做转发和拼装**。

```text
前端（Vue3）
   │  发问题：{ message: "退货政策？" }
   ▼
PHP 薄 API 层（BFF）  ← 今天的主角
   │  1) 校验参数、鉴权
   │  2) 拼上下文、system prompt
   │  3) 转发给 LLM Gateway
   ▼
LLM Gateway（大模型网关，可能是 Node/Python 服务）
   │  真正调用大模型、跑 RAG、调 MCP 工具
   ▼
PHP 把结果（含引用来源）整理成统一格式 → 返回前端
```

为什么要多一层 PHP，而不让前端直接调大模型？

| 原因 | 说明 |
|---|---|
| 藏密钥 | 大模型 API Key 不能暴露给前端，必须放后端 |
| 鉴权 | 判断用户有没有权限用助手，PHP 层统一做 |
| 拼上下文 | 加 system prompt、历史消息、用户身份 |
| 统一格式 | 前端只认一种响应结构，后端屏蔽下游差异 |
| 限流/审计 | 记录谁问了什么，防滥用 |

小白重点：**"薄"不代表没用**。它是安全和统一的关键关卡，相当于餐厅的前台服务员——不做菜，但负责点单、传菜、结账。

---

### 1.2 对话代理接口的数据流

一次对话请求，走完整流程：

```text
1. 前端 POST /api/chat  body: { message, conversationId }
2. PHP 校验 message 非空、用户已登录
3. PHP 组装请求，转发给 LLM Gateway
4. Gateway 返回：{ answer, sources }
5. PHP 包成统一格式：{ code: 0, data: { answer, sources } }
6. 前端拿到，渲染气泡 + 引用来源
```

小白重点：注意 PHP 在第 3、5 步做的是"翻译"——把前端的请求翻译成 Gateway 能懂的，再把 Gateway 的回答翻译成前端能懂的。

---

### 1.3 设计统一响应格式

无论成功失败，接口都返回同一个结构，前端才好处理：

```php
<?php
// 成功
[
    "code" => 0,          // 0 表示成功，非 0 表示各种错误
    "message" => "ok",    // 给人看的提示
    "data" => [           // 真正的数据
        "answer" => "支持 7 天无理由退货。",
        "sources" => [ /* 引用来源 */ ],
    ],
];

// 失败
[
    "code" => 1001,
    "message" => "message 不能为空",
    "data" => null,
];
```

和 Node/Express 的响应约定对比：

```js
// Express 里常见的统一返回
res.json({ code: 0, message: "ok", data: {...} });
```

| 对比项 | PHP | Node/Express |
|---|---|---|
| 返回 JSON | `header + echo json_encode` | `res.json(...)` |
| 结构约定 | `{code, message, data}` | 同样 `{code, message, data}` |
| 差异 | 需手动设 Content-Type | Express 自动设 |

小白重点：统一格式最大的价值是"前端只写一套判断逻辑"——只看 `code` 是不是 0，是就取 `data`，不是就弹 `message`。

---

### 1.4 写一个普通（非流式）对话代理接口

先写最简单的版本，不流式，一次性返回。用原生 PHP 演示（框架里同理）：

```php
<?php
// api/chat.php —— 对话代理接口（非流式版）

declare(strict_types=1);

// 1) 声明返回 JSON
header("Content-Type: application/json; charset=utf-8");

// 2) 只允许 POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["code" => 1405, "message" => "只支持 POST", "data" => null]);
    exit;
}

// 3) 读取前端发来的 JSON body
$raw = file_get_contents("php://input");
$body = json_decode($raw, true) ?? [];
$message = trim($body["message"] ?? "");

// 4) 参数校验
if ($message === "") {
    echo json_encode(["code" => 1001, "message" => "message 不能为空", "data" => null]);
    exit;
}

// 5) 鉴权占位（真实项目从 token / session 取用户）
$userId = authGuard(); // 见 1.7
if ($userId === null) {
    echo json_encode(["code" => 1401, "message" => "未登录", "data" => null]);
    exit;
}

// 6) 转发给 LLM Gateway
try {
    $result = callLlmGateway($message, $userId);
    echo json_encode([
        "code" => 0,
        "message" => "ok",
        "data" => [
            "answer"  => $result["answer"],
            "sources" => $result["sources"] ?? [],
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    // 下游挂了，给前端一个友好错误（不泄露内部细节）
    echo json_encode(["code" => 1500, "message" => "服务暂时不可用", "data" => null]);
}

// ---- 下面是转发函数 ----

function callLlmGateway(string $message, int $userId): array
{
    $gatewayUrl = getenv("LLM_GATEWAY_URL") ?: "http://llm-gateway.internal/chat";
    $apiKey = getenv("LLM_GATEWAY_KEY") ?: "";

    $payload = json_encode([
        "message" => $message,
        "user_id" => $userId,
        "system"  => "你是运营知识助手，只回答政策/订单/商品相关问题。",
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($gatewayUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer {$apiKey}",
        ],
    ]);

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $httpCode >= 400) {
        throw new \RuntimeException("LLM Gateway 调用失败");
    }

    return json_decode($resp, true) ?? ["answer" => "", "sources" => []];
}
```

小白重点：`curl` 是 PHP 里发 HTTP 请求的标准方式，对标 Node 的 `fetch` / `axios`。这段的核心就是"收前端请求 → 转发给 Gateway → 包装返回"。

---

### 1.5 理解 SSE 流式

普通接口是"等全部生成完才返回"，用户要干等。SSE（Server-Sent Events）让后端"生成一点推一点"。

SSE 的响应格式很简单，每条消息以 `data: ` 开头，`\n\n` 结尾：

```text
data: 支持

data: 7 天

data: 无理由退货

data: [DONE]
```

响应头必须这样设：

```php
<?php
header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("X-Accel-Buffering: no"); // 关掉 nginx 缓冲，否则流不出去
```

小白重点：SSE 是"服务器单向推给浏览器"的技术，比 WebSocket 简单，最适合"AI 打字机效果"这种只需要下行推送的场景。

---

### 1.6 把接口改成流式转发

PHP 从 Gateway 边收边往前端吐。这是今天的核心：

```php
<?php
// api/chat_stream.php —— 对话代理接口（流式版）

declare(strict_types=1);

// 1) SSE 响应头
header("Content-Type: text/event-stream; charset=utf-8");
header("Cache-Control: no-cache");
header("X-Accel-Buffering: no");

// 2) 读参数
$body = json_decode(file_get_contents("php://input"), true) ?? [];
$message = trim($body["message"] ?? "");
if ($message === "") {
    sendSse(json_encode(["error" => "message 不能为空"]));
    sendSse("[DONE]");
    exit;
}

// 3) 关闭输出缓冲，保证每次 echo 立刻发出去
while (ob_get_level() > 0) {
    ob_end_flush();
}

// 4) 请求 Gateway 的流式接口，用 curl 的 WRITEFUNCTION 边收边转发
$gatewayUrl = getenv("LLM_GATEWAY_URL") ?: "http://llm-gateway.internal/chat/stream";
$apiKey = getenv("LLM_GATEWAY_KEY") ?: "";

$payload = json_encode([
    "message" => $message,
    "stream"  => true,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init($gatewayUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer {$apiKey}",
    ],
    // 关键：每收到一块数据就调用这个回调
    CURLOPT_WRITEFUNCTION => function ($ch, $chunk) {
        // 直接把 Gateway 的块转发给前端
        echo "data: " . $chunk . "\n\n";
        flush(); // 立刻发出去
        return strlen($chunk); // 必须返回收到的字节数
    },
]);

curl_exec($ch);
curl_close($ch);

// 5) 结束标记
sendSse("[DONE]");

// ---- 工具函数 ----
function sendSse(string $data): void
{
    echo "data: {$data}\n\n";
    flush();
}
```

小白重点：`CURLOPT_WRITEFUNCTION` 是关键——它让 PHP 在收到 Gateway 每一块数据时立刻转发，而不是等全部收完。`flush()` 强制把内容立即发给浏览器。

---

### 1.7 鉴权占位

薄 API 层必须挡住未登录用户。这里给一个简化版：

```php
<?php
// 从 Authorization 头取 token，校验后返回用户 id；失败返回 null
function authGuard(): ?int
{
    $headers = getallheaders();
    $auth = $headers["Authorization"] ?? "";

    if (!str_starts_with($auth, "Bearer ")) {
        return null;
    }

    $token = substr($auth, 7);

    // 真实项目：查 Redis / 解 JWT / 查 DB
    // 这里演示：假设 token 就是 "demo-token"
    if ($token === "demo-token") {
        return 1001; // 返回用户 id
    }
    return null;
}
```

对比 Node/Express 中间件：

```js
// Express 鉴权中间件
function authGuard(req, res, next) {
  const token = req.headers.authorization?.replace("Bearer ", "");
  if (token !== "demo-token") return res.status(401).json({ code: 1401 });
  req.userId = 1001;
  next();
}
```

| 对比项 | PHP | Express |
|---|---|---|
| 取请求头 | `getallheaders()` | `req.headers` |
| 中间件机制 | 框架里用过滤器/中间件 | `app.use(fn)` |
| 拦截 | `return null` 后主流程判断 | `return res.status(401)` |

---

### 1.8 前端对接流式接口

回到 Vue，把 Day01 的 `fakeReply` 换成真的流式请求：

```js
// src/api/chat.js
export async function streamChat(message, onChunk) {
  const res = await fetch("/api/chat_stream.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Authorization": "Bearer demo-token",
    },
    body: JSON.stringify({ message }),
  });

  const reader = res.body.getReader();
  const decoder = new TextDecoder();
  let buffer = "";

  while (true) {
    const { done, value } = await reader.read();
    if (done) break;

    buffer += decoder.decode(value, { stream: true });

    // SSE 以 \n\n 分隔每条消息
    const parts = buffer.split("\n\n");
    buffer = parts.pop(); // 最后一段可能不完整，留着

    for (const part of parts) {
      const line = part.replace(/^data: /, "").trim();
      if (line === "[DONE]") return;
      if (line) onChunk(line); // 把这一块交给回调
    }
  }
}
```

在 `ChatView.vue` 里用：

```js
import { streamChat } from "@/api/chat";

async function sendMessage() {
  const text = input.value.trim();
  if (!text || loading.value) return;

  messages.push({ role: "user", content: text, sources: [] });
  input.value = "";
  loading.value = true;

  const aiMsg = reactive({ role: "assistant", content: "", sources: [] });
  messages.push(aiMsg);

  try {
    await streamChat(text, (chunk) => {
      aiMsg.content += chunk; // 每块追加，界面自动刷新（打字机效果）
    });
  } catch (e) {
    aiMsg.content = "出错了，请稍后再试。";
  } finally {
    loading.value = false;
  }
}
```

小白重点：前后端"流式"要两边配合——PHP 用 `flush()` 边发，前端用 `reader.read()` 边收，缺一个就变回"等全部再显示"。

---

## 2. 源码阅读

- `graduation-project/api/`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读后端 API 时重点找这些内容：

1. 对话接口的路由定义（如 `POST /chat`、`POST /chat/stream`）
2. 统一响应格式的封装（是否有 `ApiResponse` / `Result` 类）
3. 调 LLM Gateway 的地方（curl / HTTP client 封装）
4. 鉴权是怎么做的（中间件 / 过滤器 / behaviors）
5. 流式接口有没有关缓冲、设 SSE 头

建议在笔记里写出这张表：

| 后端文件/概念 | 作用 | Node 类比 |
|---|---|---|
| 路由配置 | 定义接口地址 | `app.post("/chat")` |
| Controller | 处理请求 | 路由 handler |
| 统一响应类 | 包装 `{code,message,data}` | 响应中间件 |
| HTTP Client 封装 | 调 Gateway | `axios` 实例 |
| 鉴权中间件 | 挡未登录 | `app.use(authGuard)` |

---

## 3. 练习任务

### 练习 1：写非流式对话接口

按 1.4 写一个 `api/chat.php`，先把 `callLlmGateway` 换成返回假数据：

```php
<?php
function callLlmGateway(string $message, int $userId): array
{
    // 先假装 Gateway 返回
    return [
        "answer" => "你问的是「{$message}」，这是模拟回答。",
        "sources" => [
            ["title" => "示例政策", "snippet" => "引用片段…", "docId" => "P-01"],
        ],
    ];
}
```

用 `php -S localhost:8080` 起服务，用 curl 测：

```bash
curl -X POST http://localhost:8080/api/chat.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer demo-token" \
  -d '{"message":"退货政策？"}'
```

目标：拿到统一格式的 JSON 响应。

---

### 练习 2：验证统一响应格式

分别测试：空 message、未带 token、正常请求，确认三种情况返回的 `code` 不同、结构一致。

目标：理解统一响应格式的价值。

---

### 练习 3：写流式接口

按 1.6 写 `api/chat_stream.php`，`WRITEFUNCTION` 里先改成"每 300ms 吐一个字"的模拟版：

```php
<?php
$fakeAnswer = "支持7天无理由退货需商品完好";
foreach (mb_str_split($fakeAnswer) as $char) {
    echo "data: {$char}\n\n";
    flush();
    usleep(300000); // 0.3 秒
}
echo "data: [DONE]\n\n";
flush();
```

用 curl 测（能看到一个字一个字蹦出来）：

```bash
curl -N -X POST http://localhost:8080/api/chat_stream.php \
  -H "Content-Type: application/json" \
  -d '{"message":"退货政策？"}'
```

目标：理解 SSE 流式输出。`-N` 表示不缓冲。

---

### 练习 4：前端接流式

按 1.8 把 Vue 的 `sendMessage` 接上真流式接口，确认界面有打字机效果。

目标：打通前后端流式链路。

---

### 练习 5：整理薄 API 层职责清单

在笔记里写出薄 API 层"该做"和"不该做"的清单：

| 该做 | 不该做 |
|---|---|
| 参数校验、鉴权 | 生成 AI 答案（那是 Gateway 的活） |
| 拼上下文、system prompt | 直接连大模型（密钥应在 Gateway/后端） |
| 转发、统一格式 | 塞复杂业务逻辑 |
| 错误兜底、限流、审计 | 处理 UI 展示 |

目标：明确"薄"的边界。

---

## 4. JS/Node.js 类比

| PHP 薄 API 概念 | Node/Express 类比 | 说明 |
|---|---|---|
| `file_get_contents("php://input")` | `req.body`（配合 body-parser） | 读请求体 |
| `json_encode` / `json_decode` | `JSON.stringify` / `JSON.parse` | JSON 序列化 |
| `curl` | `fetch` / `axios` | 发 HTTP 请求 |
| `header("Content-Type: ...")` | `res.setHeader(...)` | 设响应头 |
| `flush()` | `res.write()` + `res.flush()` | 立即输出 |
| SSE `text/event-stream` | 同样 `text/event-stream` | 流式协议一致 |
| `CURLOPT_WRITEFUNCTION` | stream `pipe` / `on("data")` | 边收边转发 |
| `getallheaders()` | `req.headers` | 取请求头 |
| 统一响应 `{code,message,data}` | 同样约定 | 前端只认一种结构 |

一句话类比：

> PHP 的薄 API 层 = Node 里那个"只做转发和鉴权、不写业务"的 BFF 服务，curl 就是 PHP 的 fetch，SSE 协议两边完全一样。

---

## 5. AI Review 提问

完成练习后，把你的 `chat.php` 和 `chat_stream.php` 贴给 AI，然后问：

```text
我正在学习 Week 22 Day 02：用 PHP 写薄 API 层（对话代理接口），转发给 LLM Gateway。

请你按资深 PHP 后端 + 全栈工程师标准帮我检查：

1. 我的统一响应格式设计合理吗？
2. curl 转发和错误处理有没有漏洞（超时、下游 5xx）？
3. SSE 流式转发的实现对不对（缓冲、flush、header）？
4. 鉴权占位在真实项目要怎么加固？
5. 薄 API 层的职责边界我把握准了吗？有没有把不该做的塞进来？

请用中文输出：
- 我做对的地方
- 我的问题清单
- 修改建议
- 下一步练习
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [✅] `api/chat.php`：非流式对话代理接口
- [✅] 统一响应格式 `{code,message,data}`
- [✅] `api/chat_stream.php`：SSE 流式接口
- [✅] 鉴权占位函数
- [✅] 前端 `api/chat.js`：读流式响应
- [✅] 前端接上真接口，有打字机效果
- [✅] 薄 API 层职责清单笔记
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说清薄 API 层（BFF）的职责和边界
- [ ] 能画出对话代理的数据流
- [ ] 能设计并实现统一响应格式
- [ ] 能用 curl 转发请求给下游
- [ ] 能实现 SSE 流式接口（设头、关缓冲、flush）
- [ ] 前端能读流式响应并渲染打字机效果
- [ ] 能说清 curl/fetch、PHP/Express 的类比

---

## 8. 今日自测题

### 8.1 为什么不让前端直接调大模型，要多一层 PHP？

参考答案：

> ✅ 为了藏密钥（API Key 不能给前端）、统一鉴权、拼上下文、统一响应格式、做限流和审计。前端直连大模型会泄露密钥且无法管控。

---

### 8.2 统一响应格式为什么重要？

参考答案：

> ✅ 因为前端只需写一套判断逻辑：看 `code` 是否为 0，是就取 `data`，不是就提示 `message`。无论成功失败结构都一样，避免前端到处写特殊判断。

---

### 8.3 SSE 流式接口必须设哪些响应头？

参考答案：

> ✅ `Content-Type: text/event-stream`、`Cache-Control: no-cache`、`X-Accel-Buffering: no`（关掉 nginx 缓冲）。此外要关掉 PHP 输出缓冲并在每次输出后 `flush()`。

---

### 8.4 `CURLOPT_WRITEFUNCTION` 的作用是什么？

参考答案：

> ✅ 它是一个回调，curl 每收到一块下游数据就调用一次。我们在里面把这块数据立刻转发给前端并 `flush()`，实现"边收边转发"的流式效果，而不是等全部收完。

---

### 8.5 薄 API 层"不该做"哪些事？

参考答案：

> ✅ 不该生成 AI 答案（那是 Gateway/大模型的活）、不该直接持有大模型密钥去连模型（应放更内层）、不该塞复杂业务逻辑、不该处理 UI 展示。它只做转发、鉴权、拼装、统一格式、兜底。

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
我正在进行 Week 22 Day 02：PHP 薄 API 层 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确（BFF 职责、统一响应、SSE 流式）
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险（超时、缓冲、鉴权）
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 22 README](./README.md)
