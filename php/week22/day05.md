# Week 22 Day 05：联调与类比日

> 所属周：Week 22：毕业项目：全栈实现  
> 阶段：第六阶段：毕业项目  
> 主仓库/项目：`graduation-project`  
> 类型：类比日  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

把前四天的成果（Vue3 对话 UI + PHP 薄 API + MCP 工具 + RAG 政策问答）**接到一起联调**，让"问政策 → 查订单 → 追问"这条完整链路能在浏览器里真实跑通，并完成本周类比打卡。

今天你要真正掌握这一句话：

> 联调不是"再写新功能"，而是"把各段接口按同一份契约对齐，沿着请求路径逐段排查，让数据从前端一路流到 LLM/工具/知识库再流回来"——90% 的联调 bug 都出在字段名、格式、跨域、流式解析这四类对不齐上。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 画出完整数据流：前端 → PHP API → LLM/MCP/RAG → 回流
2. 搭起本地联调环境（前端 dev server + PHP 服务）
3. 打通第一条链路：普通对话（前端 → API → LLM → 前端）
4. 打通流式：SSE 从 API 一路推到前端渲染
5. 打通 Tool：问订单能查到真实数据
6. 打通 RAG：问政策能带引用来源
7. 按"排查四件套"定位并修 bug
8. 完成本周类比打卡，写笔记自测

---

## 1. 学习内容

### 1.1 先画出完整数据流

联调前必须心里有这张图，否则出 bug 不知道去哪查：

```text
[Vue3 前端]
   │ POST /api/chat/completions  { message, conversation_id }
   ▼
[PHP 薄 API 层]  ← Day 02
   │ 判断：问政策 or 查订单？
   ├──(政策)→ RAG 检索片段 → 拼提示词 ─┐   ← Day 04
   ├──(订单)→ 声明 Tool 给模型 ────────┤   ← Day 03
   │                                    ▼
   │                             [LLM Gateway]
   │                                    │ 需要调工具？
   │                            ┌───────┴───────┐
   │                          是│               │否
   │                            ▼               │
   │                     [MCP: 查订单/商品]      │
   │                            │               │
   │                            └───→ 结果喂回 ←─┘
   │ SSE 流式回传 answer + sources
   ▼
[Vue3 前端] 逐字渲染 + 展示引用来源卡片
```

小白重点：联调时"顺着箭头走"。前端收不到数据？从右往左一段段查：前端解析对不对 → API 有没有推 → LLM 返回没 → Tool/RAG 有没有拿到数据。

---

### 1.2 搭本地联调环境

两个服务同时起：

```bash
# 终端 1：PHP API（Day 02）
php -S localhost:8000 -t api/web

# 终端 2：前端 dev server（Day 01）
cd frontend
npm run dev   # 通常起在 localhost:5173
```

前端和 API 不同端口，会遇到**跨域（CORS）**问题。PHP 侧加响应头：

```php
<?php
// api 入口或中间件里
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// 预检请求（浏览器 POST 前会先发 OPTIONS）直接返回 200
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}
```

小白重点：跨域是联调第一个坑。浏览器控制台报 `CORS policy` 就是它。注意 SSE + 自定义头时预检 `OPTIONS` 必须正确响应。

对比 Node 里用 `cors` 中间件：

```js
// Express
app.use(cors({ origin: "http://localhost:5173" }));
```

PHP 里手动设响应头，本质一样。

---

### 1.3 打通第一条链路：普通对话

先不管流式、不管工具，跑通最简单的"发一句 → 回一句"：

```js
// 前端最小验证（先用非流式，确认链路通）
async function testChat() {
  const res = await fetch("http://localhost:8000/api/chat/test", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ message: "你好" }),
  });
  const data = await res.json();
  console.log("收到：", data); // { code, message, data:{ answer } }
}
```

联调检查点：

| 检查项 | 出错现象 | 排查 |
|---|---|---|
| 请求发出 | Network 里没有请求 | 前端 URL/方法写错 |
| 跨域 | CORS 报错 | 补响应头（1.2） |
| 404 | 接口找不到 | 路由没配对 |
| 500 | 服务端错误 | 看 PHP 日志 |
| 字段对不上 | `data.answer` 是 undefined | 前后端字段名不一致 |

小白重点：**先跑通最简单的一条**，再叠加流式/工具/RAG。别一上来全接，出错无从查起。

---

### 1.4 打通流式（SSE）

普通对话通了，换成 Day 01/02 的 SSE 流式：

```js
// 前端：用 fetch + ReadableStream 读 SSE
async function streamChat(message, onChunk) {
  const res = await fetch("http://localhost:8000/api/chat/completions", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ message }),
  });
  const reader = res.body.getReader();
  const decoder = new TextDecoder();
  let buffer = "";

  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    buffer += decoder.decode(value, { stream: true });
    // SSE 以 \n\n 分隔事件
    const parts = buffer.split("\n\n");
    buffer = parts.pop(); // 留下不完整的
    for (const part of parts) {
      const line = part.replace(/^data: /, "");
      if (line === "[DONE]") return;
      const chunk = JSON.parse(line);
      onChunk(chunk); // { delta: "文", sources? }
    }
  }
}
```

流式联调常见坑：

| 坑 | 现象 | 解决 |
|---|---|---|
| 缓冲没关 | 前端一次性收到全部，没有"逐字" | PHP 侧 `ob_flush(); flush();` |
| 分隔符 | JSON 解析报错 | 按 `\n\n` 切，处理半个事件 |
| 结束标记 | 前端一直转圈 | 约定 `[DONE]` 并处理 |
| 编码 | 中文乱码 | 用 `TextDecoder` + `{stream:true}` |

小白重点：SSE 联调最容易卡在"缓冲"和"分隔"。记住 PHP 每推一段要 `flush()`，前端要处理"半个事件"的 buffer。

---

### 1.5 打通 Tool（查订单）

问"我的订单 SN-8842 到哪了"，验证 Day 03 的 Tool 链路：

```text
前端发问 → API 声明 order_query 工具给模型 → 模型返回 tool_calls
→ API 执行 MCP 查订单 → 结果喂回模型 → 模型生成最终回答 → 流式回前端
```

联调检查点：

```php
<?php
// 在 API 里加临时日志，确认每一步
error_log("1. 收到问题: " . $message);
error_log("2. 模型是否要调工具: " . json_encode($resp["tool_calls"] ?? "否"));
error_log("3. MCP 查询结果: " . json_encode($toolResult));
error_log("4. 最终回答: " . $finalAnswer);
```

小白重点：Tool 联调看这四条日志——哪条断了，问题就在哪。常见：模型没识别出要调工具（提示词/工具描述写得不清楚）、MCP 查询失败（订单号格式）。

---

### 1.6 打通 RAG（问政策）

问"退货政策是几天"，验证 Day 04 的 RAG 链路，重点看**引用来源有没有回到前端并展示**：

```js
// 前端：收到带 sources 的 chunk 时存起来
function onChunk(chunk) {
  if (chunk.delta) currentMsg.content += chunk.delta;
  if (chunk.sources) currentMsg.sources = chunk.sources; // 引用来源
}
```

联调检查点：

- API 检索到片段了吗？（打印 retrieved）
- sources 字段拼进 SSE 了吗？
- 前端存到消息对象了吗？
- 来源卡片渲染出来了吗？

小白重点：RAG 联调最容易漏"引用来源没传到前端"。answer 有了但 sources 空，多半是 SSE 里没带 sources 字段，或前端没接。

---

### 1.7 排查四件套

联调 90% 的 bug 属于这四类，按这个顺序查最快：

| # | 类别 | 典型现象 | 排查方法 |
|---|---|---|---|
| 1 | 字段名不一致 | 数据是 undefined | 对比前后端字段名（`answer` vs `content`） |
| 2 | 格式不对 | JSON 解析报错 | 看 Network 原始响应 |
| 3 | 跨域/网络 | CORS / 404 / 超时 | 看 Network 状态码、响应头 |
| 4 | 流式解析 | 不逐字 / 卡死 / 乱码 | 看 SSE 分隔、flush、编码 |

小白重点：**先看浏览器 Network 面板的原始请求/响应**，再看 PHP 日志。别凭猜改代码。

一个高频真实 bug：

```text
现象：前端消息一直是空的
排查：Network 看到 API 返回 { "reply": "..." }
     但前端读的是 data.answer
根因：字段名不一致（reply vs answer）
修复：统一契约——两边都用 answer
```

---

### 1.8 联调的正确心态

小白重点（这段很重要）：

- 联调不是写新代码，是**让已有的接口对齐**。忍住重写的冲动。
- 一次只改一处，改完立刻验证，别批量改。
- 加临时日志比盯代码猜快 10 倍，查完记得删。
- 遇到卡住超过 30 分钟没进展，回到 1.1 的数据流图，重新定位"数据流到哪一段断了"。
- 契约（字段名/格式）是联调的地基。前四天如果都按统一 `{code, message, data:{answer, sources}}` 做，今天会很顺。

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

联调时重点"读自己前四天的代码"，对照检查契约是否一致：

1. Day 01 前端读的字段名 vs Day 02 API 返回的字段名
2. Day 02 SSE 格式 vs Day 01 前端解析格式
3. Day 03 Tool 返回结构 vs 喂回模型的格式
4. Day 04 sources 结构 vs 前端展示读的字段

建议在笔记里做一张"契约对齐表"，逐行核对前后端字段。

---

## 3. 练习任务

### 练习 1：搭起联调环境

同时起 PHP API 和前端 dev server，配好 CORS，前端能访问到 API（哪怕先返回假数据）。

目标：两个服务能通信，没有 CORS 报错。

---

### 练习 2：打通普通对话

按 1.3 跑通"发一句 → 回一句"，在 Network 面板确认请求发出、响应正确、字段对上。

目标：最简单的一条链路先通。

---

### 练习 3：打通流式

按 1.4 换成 SSE，确认前端能逐字渲染。故意制造一个"缓冲没关"的 bug，观察现象，再修好。

目标：理解 SSE 联调的缓冲和分隔坑。

---

### 练习 4：打通 Tool 和 RAG

分别问"查订单 SN-8842""退货政策几天"，按 1.5/1.6 加日志确认每一步，直到订单能查到、政策带引用。

目标：三条链路（对话/Tool/RAG）全通。

---

### 练习 5：修一个真实 bug + 完成类比打卡

故意把前端某个字段名改错，用"排查四件套"定位并修复，记录排查过程。然后填本周类比打卡模板。

目标：掌握系统化排查方法，完成打卡。

---

## 4. JS/Node.js 类比

| 联调概念 | Node 类比 | 说明 |
|---|---|---|
| 前端 dev server | `vite` / `webpack-dev-server` | 本地开发服务器 |
| CORS 响应头 | `cors` 中间件 | 允许跨域 |
| SSE 流式读取 | `res.body.getReader()` | 读流 |
| 临时日志排查 | `console.log` 打点 | 定位数据流断点 |
| Network 面板 | 抓包/日志 | 看真实请求响应 |
| 契约对齐 | TS interface 前后端共享 | 字段名统一 |

一句话类比：

> 前后端联调就像"接自来水管"——每段管子（接口）接头（字段/格式）要对齐，漏水（bug）就顺着管路一段段查，而不是把整套管子重装。

---

## 5. AI Review 提问

把你的联调记录、遇到的 bug 和修复过程贴给 AI，然后问：

```text
我正在做 Week 22 Day 05：前后端联调（Vue3 + PHP API + MCP + RAG）。

请你按资深全栈工程师标准帮我检查：

1. 我的数据流理解对不对？排查顺序合理吗？
2. CORS / SSE 流式的处理有没有隐患？
3. 我修 bug 的方法系统吗？还是在瞎改？
4. 三条链路（对话/Tool/RAG）的契约是否真的对齐了？
5. 真实项目联调还有哪些常见坑我没遇到（超时、并发、鉴权）？

请用中文输出：
- 我做对的地方
- 潜在问题清单
- 排查建议
- 下一步（为 Day 06 MVP 演示做准备）
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [✅] 完整数据流图（1.1）
- [✅] 可运行的联调环境（前端 + API）
- [✅] 普通对话链路跑通
- [✅] SSE 流式跑通
- [✅] Tool（查订单）链路跑通
- [✅] RAG（问政策带引用）链路跑通
- [✅] 一份"排查四件套"bug 记录
- [✅] 本周类比打卡
- [✅] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能画出前端→API→LLM/MCP/RAG 的完整数据流
- [ ] 联调环境能跑，无 CORS 报错
- [ ] 普通对话链路通
- [ ] SSE 流式能逐字渲染
- [ ] 问订单能查到真实数据
- [ ] 问政策能带引用来源
- [ ] 能用"排查四件套"定位并修复至少一个 bug
- [ ] 核心对话流程端到端跑通

---

## 8. 今日自测题

### 8.1 联调 bug 大多出在哪四类？

参考答案：

> ✅ 字段名不一致、数据格式不对、跨域/网络、流式解析。按这个顺序查最快，先看 Network 原始请求响应，再看服务端日志。

---

### 8.2 出 CORS 报错怎么办？

参考答案：

> ✅ 前端和 API 不同端口会跨域。PHP 侧加 `Access-Control-Allow-Origin` 等响应头，并对浏览器的预检 `OPTIONS` 请求直接返回 200。类似 Node 的 cors 中间件。

---

### 8.3 SSE 流式前端不逐字、一次性全出，为什么？

参考答案：

> ✅ 多半是服务端输出缓冲没关。PHP 每推一段要 `ob_flush(); flush();`。另外前端要按 `\n\n` 切事件并处理"半个事件"的 buffer。

---

### 8.4 联调时为什么要先跑通最简单的一条链路？

参考答案：

> ✅ 一上来把流式+Tool+RAG 全接，出错无从定位。先跑通"发一句回一句"，再逐步叠加流式、工具、RAG，每加一层就验证，问题能立刻锁定在新加的那层。

---

### 8.5 前端消息一直是空的，怎么查？

参考答案：

> ✅ 看 Network 面板 API 的原始响应，对比前端读的字段名。常见是字段名不一致（如后端返 `reply`，前端读 `answer`）。修法是统一契约，两边用同一个字段名。

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
我正在进行 Week 22 Day 05：联调与类比日 的学习。
请你扮演资深全栈工程师，帮我检查：
1. 今日理解是否正确（数据流、排查四件套、契约对齐）
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险（CORS、SSE 缓冲、字段对齐）
4. 真实企业项目联调还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 22 README](./README.md)
