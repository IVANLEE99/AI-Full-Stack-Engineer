# 第十七周详细学习内容:SSE 与流式接口

> 主题:SSE 与流式接口
> 目标:让你能用 Go 调用 LLM 的流式 API,并把 token 实时转发给前端,做出打字机效果。
> 原则:先把"数据一段段流出来"这件事跑通,再谈优化。**80% 时间调通链路,20% 时间处理边界。**

---

## 一、本周核心目标

| 目标 | 说明 |
|---|---|
| 理解 SSE | 搞懂 Server-Sent Events 协议格式和它与 WebSocket 的区别 |
| 会用 Flusher | 掌握 `http.Flusher`,让数据不被缓冲、立即下发 |
| 调 LLM 流式 API | 用 `http.Client` 请求 OpenAI 兼容的 `stream: true` 接口 |
| 流式转发 | 把 LLM 返回的流边读边转成 SSE 推给前端 |
| 项目产出 | 完成 `/chat/stream` 接口,前端 `EventSource` 能看到打字机效果 |

---

## 二、本周关键认知

你在前端一定见过 ChatGPT 那种"一个字一个字蹦出来"的效果。它的本质不是前端动画,而是**后端把数据分很多小块,持续往同一个 HTTP 连接里写**。本周就是从后端把这套链路打通。

- **SSE(Server-Sent Events)** 是一个单向长连接:服务端持续往客户端推数据,客户端只收不发。它就是普通的 HTTP 响应,只是 `Content-Type: text/event-stream`,而且**不结束**,一直往里写。
- 前端对接 SSE 用浏览器原生的 `EventSource`,比 WebSocket 简单得多,自带断线重连。
- LLM 的流式返回本身也是 SSE 格式(OpenAI 兼容接口 `stream: true` 时,返回一串 `data: {...}` 行)。所以你的后端角色是:**一个 SSE 客户端(对 LLM) + 一个 SSE 服务端(对前端)**,中间做转发。
- Go 里默认响应会被缓冲。要立即下发,必须拿到 `http.Flusher` 并在每次写完后 `Flush()`。这是本周最容易踩的坑。

前端对照:

| 前端概念 | 本周对应 |
|---|---|
| `fetch` 拿完整 JSON | Go 里一次性读完 body(非流式) |
| `EventSource` 收 SSE | Go 里做 SSE 服务端往它推 |
| `ReadableStream` 流式读 | Go 里用 `bufio.Scanner` 逐行读 LLM 响应 |
| axios 超时 | Go 里用 `context.WithTimeout` + `http.Client.Timeout` |

---

## 三、每天学习安排(7天)

### Day 1:看懂 SSE 协议格式

SSE 的报文格式极简单。每条消息由若干行组成,以一个空行结束:

```
data: 这是第一条消息

data: 第二条消息的第一行
data: 第二条消息的第二行

event: done
data: [DONE]

```

规则:
- `data:` 开头是数据,一条消息可以有多行 `data:`。
- 空行表示"一条消息结束"。
- `event:` 可选,给消息起个类型名,前端可以按类型监听。
- `id:` 可选,断线重连时浏览器会带上 `Last-Event-ID`。

**用 Go 起一个最简单的 SSE 服务端体验一下** `main.go`

```go
package main

import (
    "fmt"
    "net/http"
    "time"
)

func sseHandler(w http.ResponseWriter, r *http.Request) {
    // 关键三个响应头
    w.Header().Set("Content-Type", "text/event-stream")
    w.Header().Set("Cache-Control", "no-cache")
    w.Header().Set("Connection", "keep-alive")

    // 拿到 Flusher,不能刷新就说明这个环境不支持流式
    flusher, ok := w.(http.Flusher)
    if !ok {
        http.Error(w, "不支持流式响应", http.StatusInternalServerError)
        return
    }

    for i := 1; i <= 5; i++ {
        // 注意结尾的两个 \n:一个结束 data 行,一个空行结束消息
        fmt.Fprintf(w, "data: 第 %d 条消息\n\n", i)
        flusher.Flush() // 立即下发,不刷就会被缓冲
        time.Sleep(time.Second)
    }
    fmt.Fprint(w, "data: [DONE]\n\n")
    flusher.Flush()
}

func main() {
    http.HandleFunc("/sse", sseHandler)
    fmt.Println("SSE 服务启动在 :8080")
    http.ListenAndServe(":8080", nil)
}
```

**测试**
```bash
go run main.go
# 另开终端,-N 关闭 curl 缓冲,能看到每秒蹦一条
curl -N localhost:8080/sse
```

**Day 1 理解要点**
- SSE 消息用空行分隔,`data:` 后面是内容
- 三个响应头缺一不可,尤其 `text/event-stream`
- 不 `Flush()` 数据会被攒着一次性发出,打字机效果就没了

---

### Day 2:http.Client 与流式读取

调用 LLM 前,先掌握怎么用 `http.Client` 发请求、并**边收边读**(而不是等全部返回)。

```go
package main

import (
    "bufio"
    "fmt"
    "net/http"
    "strings"
    "time"
)

func main() {
    client := &http.Client{
        Timeout: 60 * time.Second, // 整个请求的总超时
    }

    req, _ := http.NewRequest("GET", "http://localhost:8080/sse", nil)
    resp, err := client.Do(req)
    if err != nil {
        fmt.Println("请求失败:", err)
        return
    }
    defer resp.Body.Close()

    // 逐行读取响应体,不等它全部返回
    scanner := bufio.NewScanner(resp.Body)
    for scanner.Scan() {
        line := scanner.Text()
        if strings.HasPrefix(line, "data: ") {
            data := strings.TrimPrefix(line, "data: ")
            fmt.Println("收到:", data)
            if data == "[DONE]" {
                break
            }
        }
    }
}
```

**Day 2 理解要点**
- `bufio.NewScanner(resp.Body)` 逐行读,是流式消费的关键,不用 `io.ReadAll`(那会等全部返回)
- `http.Client.Timeout` 是整个请求周期的总超时,流式接口要设得大一些或用 context 控制
- 读完一定 `defer resp.Body.Close()`,否则连接泄漏

---

### Day 3:调用 LLM 的流式 API

现在对接真正的 LLM。这里用 OpenAI 兼容接口,GLM、DeepSeek、Kimi 等都支持这套格式,只是换 `BaseURL` 和 `Model`。

先准备环境变量:

```bash
export LLM_API_KEY="你的key"
export LLM_BASE_URL="https://api.deepseek.com/v1"   # DeepSeek 示例
# GLM: https://open.bigmodel.cn/api/paas/v4
```

`llm.go`

```go
package main

import (
    "bufio"
    "bytes"
    "encoding/json"
    "fmt"
    "net/http"
    "os"
    "strings"
)

// 请求体结构
type ChatRequest struct {
    Model    string    `json:"model"`
    Messages []Message `json:"messages"`
    Stream   bool      `json:"stream"`
}

type Message struct {
    Role    string `json:"role"`
    Content string `json:"content"`
}

// 流式返回的每个 chunk 结构(只取用得到的字段)
type StreamChunk struct {
    Choices []struct {
        Delta struct {
            Content string `json:"content"`
        } `json:"delta"`
    } `json:"choices"`
}

func main() {
    reqBody := ChatRequest{
        Model:  "deepseek-chat",
        Stream: true,
        Messages: []Message{
            {Role: "user", Content: "用一句话介绍 Go 语言"},
        },
    }
    body, _ := json.Marshal(reqBody)

    url := os.Getenv("LLM_BASE_URL") + "/chat/completions"
    req, _ := http.NewRequest("POST", url, bytes.NewReader(body))
    req.Header.Set("Content-Type", "application/json")
    req.Header.Set("Authorization", "Bearer "+os.Getenv("LLM_API_KEY"))

    resp, err := http.DefaultClient.Do(req)
    if err != nil {
        fmt.Println("请求失败:", err)
        return
    }
    defer resp.Body.Close()

    scanner := bufio.NewScanner(resp.Body)
    for scanner.Scan() {
        line := scanner.Text()
        if !strings.HasPrefix(line, "data: ") {
            continue
        }
        data := strings.TrimPrefix(line, "data: ")
        if data == "[DONE]" {
            break
        }
        var chunk StreamChunk
        if err := json.Unmarshal([]byte(data), &chunk); err != nil {
            continue // 有些行是空的或心跳,跳过
        }
        if len(chunk.Choices) > 0 {
            fmt.Print(chunk.Choices[0].Delta.Content) // 打印增量,不换行
        }
    }
    fmt.Println()
}
```

**Day 3 理解要点**
- LLM 流式响应本身就是 SSE 格式:一堆 `data: {json}` 行,以 `data: [DONE]` 结束
- 内容在 `choices[0].delta.content` 里,是**增量**,要自己拼起来
- 结构体只声明你要用的字段,多余字段 `json.Unmarshal` 会自动忽略

---

### Day 4:把 LLM 流转发给前端(核心)

把 Day 3 的"读 LLM 流"和 Day 1 的"往前端写 SSE"接起来。这是本周的核心逻辑。

```go
package main

import (
    "bufio"
    "bytes"
    "encoding/json"
    "fmt"
    "net/http"
    "os"
    "strings"
)

func chatStreamHandler(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "text/event-stream")
    w.Header().Set("Cache-Control", "no-cache")
    w.Header().Set("Connection", "keep-alive")
    // 允许前端跨域连接(开发期)
    w.Header().Set("Access-Control-Allow-Origin", "*")

    flusher, ok := w.(http.Flusher)
    if !ok {
        http.Error(w, "不支持流式", http.StatusInternalServerError)
        return
    }

    // 1. 构造对 LLM 的流式请求
    reqBody := ChatRequest{
        Model:  "deepseek-chat",
        Stream: true,
        Messages: []Message{
            {Role: "user", Content: r.URL.Query().Get("q")},
        },
    }
    body, _ := json.Marshal(reqBody)

    url := os.Getenv("LLM_BASE_URL") + "/chat/completions"
    req, _ := http.NewRequest("POST", url, bytes.NewReader(body))
    req.Header.Set("Content-Type", "application/json")
    req.Header.Set("Authorization", "Bearer "+os.Getenv("LLM_API_KEY"))

    resp, err := http.DefaultClient.Do(req)
    if err != nil {
        fmt.Fprintf(w, "event: error\ndata: 调用模型失败\n\n")
        flusher.Flush()
        return
    }
    defer resp.Body.Close()

    // 2. 边读 LLM 流,边转成 SSE 写给前端
    scanner := bufio.NewScanner(resp.Body)
    for scanner.Scan() {
        line := scanner.Text()
        if !strings.HasPrefix(line, "data: ") {
            continue
        }
        data := strings.TrimPrefix(line, "data: ")
        if data == "[DONE]" {
            break
        }
        var chunk StreamChunk
        if err := json.Unmarshal([]byte(data), &chunk); err != nil {
            continue
        }
        if len(chunk.Choices) == 0 {
            continue
        }
        token := chunk.Choices[0].Delta.Content
        if token == "" {
            continue
        }
        // 转发给前端。注意:token 里可能有换行,要转义避免破坏 SSE 格式
        safe := strings.ReplaceAll(token, "\n", "\\n")
        fmt.Fprintf(w, "data: %s\n\n", safe)
        flusher.Flush() // 每个 token 都立即下发
    }

    // 3. 通知前端结束
    fmt.Fprint(w, "event: done\ndata: [DONE]\n\n")
    flusher.Flush()
}

func main() {
    http.HandleFunc("/chat/stream", chatStreamHandler)
    fmt.Println("启动在 :8080,试试 /chat/stream?q=你好")
    http.ListenAndServe(":8080", nil)
}
```

**Day 4 理解要点**
- 后端同时扮演两个角色:LLM 的 SSE 客户端 + 前端的 SSE 服务端
- token 里可能含换行,直接写会破坏 SSE 的"空行分隔"规则,要转义(实战里更推荐把整段 JSON 编码后再发)
- 每收到一个 token 就 `Flush()` 一次,这是打字机效果的来源

---

### Day 5:前端 EventSource 对接

后端跑通后,前端用原生 `EventSource` 就能接。写个最小 HTML 验证。

```html
<!DOCTYPE html>
<html>
<body>
  <div id="output" style="white-space: pre-wrap;"></div>
  <script>
    const q = encodeURIComponent("用三句话介绍 Go");
    const es = new EventSource(`http://localhost:8080/chat/stream?q=${q}`);
    const out = document.getElementById("output");

    // 默认消息(没有 event 名的)
    es.onmessage = (e) => {
      // 后端把换行转义成了 \n,这里还原
      out.textContent += e.data.replaceAll("\\n", "\n");
    };

    // 监听自定义的 done 事件
    es.addEventListener("done", () => {
      console.log("结束");
      es.close(); // 必须手动关,否则浏览器会自动重连
    });

    es.onerror = (e) => {
      console.error("连接出错", e);
      es.close();
    };
  </script>
</body>
</html>
```

**Day 5 理解要点**
- `EventSource` 只支持 GET,复杂参数(比如多轮对话历史)要走 POST 时,得改用 `fetch` + `ReadableStream` 手动解析
- `onmessage` 收无名事件,`addEventListener('done', ...)` 收 `event: done` 的消息
- 结束后必须 `es.close()`,否则 EventSource 会自动重连,反复触发请求

---

### Day 6:超时、断连与 context 控制

生产接口必须处理两件事:用户关掉页面(断连)、LLM 迟迟不返回(超时)。用 `context` 统一控制。

```go
package main

import (
    "bufio"
    "bytes"
    "context"
    "encoding/json"
    "fmt"
    "net/http"
    "os"
    "strings"
    "time"
)

func chatStreamHandler(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "text/event-stream")
    w.Header().Set("Cache-Control", "no-cache")

    flusher, _ := w.(http.Flusher)

    // r.Context() 在客户端断开时会自动 Done,再叠加一个总超时
    ctx, cancel := context.WithTimeout(r.Context(), 90*time.Second)
    defer cancel()

    reqBody := ChatRequest{
        Model:    "deepseek-chat",
        Stream:   true,
        Messages: []Message{{Role: "user", Content: r.URL.Query().Get("q")}},
    }
    body, _ := json.Marshal(reqBody)

    url := os.Getenv("LLM_BASE_URL") + "/chat/completions"
    // 把 ctx 绑到请求上:ctx 取消,底层连接会被中断
    req, _ := http.NewRequestWithContext(ctx, "POST", url, bytes.NewReader(body))
    req.Header.Set("Content-Type", "application/json")
    req.Header.Set("Authorization", "Bearer "+os.Getenv("LLM_API_KEY"))

    resp, err := http.DefaultClient.Do(req)
    if err != nil {
        fmt.Fprint(w, "event: error\ndata: 上游请求失败\n\n")
        flusher.Flush()
        return
    }
    defer resp.Body.Close()

    scanner := bufio.NewScanner(resp.Body)
    for scanner.Scan() {
        // 每次循环先看客户端是否已断开或超时
        select {
        case <-ctx.Done():
            fmt.Println("连接中断,停止转发:", ctx.Err())
            return
        default:
        }

        line := scanner.Text()
        if !strings.HasPrefix(line, "data: ") {
            continue
        }
        data := strings.TrimPrefix(line, "data: ")
        if data == "[DONE]" {
            break
        }
        var chunk StreamChunk
        if json.Unmarshal([]byte(data), &chunk) != nil || len(chunk.Choices) == 0 {
            continue
        }
        token := chunk.Choices[0].Delta.Content
        if token != "" {
            fmt.Fprintf(w, "data: %s\n\n", strings.ReplaceAll(token, "\n", "\\n"))
            flusher.Flush()
        }
    }
    fmt.Fprint(w, "event: done\ndata: [DONE]\n\n")
    flusher.Flush()
}
```

**Day 6 理解要点**
- `r.Context()` 会在客户端断开连接时自动取消,不用自己监听 socket
- `http.NewRequestWithContext` 把 ctx 传给上游请求,取消时会一并中断对 LLM 的调用,省钱
- 循环里用 `select { case <-ctx.Done() }` 做非阻塞检查,及时退出

---

### Day 6-7 实战:用 Gin 封装完整 /chat/stream

前面用标准库讲清了原理。实战用第 8 周学过的 Gin,代码更整洁。Gin 提供了 `c.Stream`。

```go
package main

import (
    "bufio"
    "bytes"
    "encoding/json"
    "net/http"
    "os"
    "strings"

    "github.com/gin-gonic/gin"
)

type ChatRequest struct {
    Model    string    `json:"model"`
    Messages []Message `json:"messages"`
    Stream   bool      `json:"stream"`
}
type Message struct {
    Role    string `json:"role"`
    Content string `json:"content"`
}
type StreamChunk struct {
    Choices []struct {
        Delta struct {
            Content string `json:"content"`
        } `json:"delta"`
    } `json:"choices"`
}

func handleStream(c *gin.Context) {
    question := c.Query("q")

    c.Header("Content-Type", "text/event-stream")
    c.Header("Cache-Control", "no-cache")
    c.Header("Access-Control-Allow-Origin", "*")

    reqBody, _ := json.Marshal(ChatRequest{
        Model:    "deepseek-chat",
        Stream:   true,
        Messages: []Message{{Role: "user", Content: question}},
    })

    url := os.Getenv("LLM_BASE_URL") + "/chat/completions"
    req, _ := http.NewRequestWithContext(c.Request.Context(), "POST", url, bytes.NewReader(reqBody))
    req.Header.Set("Content-Type", "application/json")
    req.Header.Set("Authorization", "Bearer "+os.Getenv("LLM_API_KEY"))

    resp, err := http.DefaultClient.Do(req)
    if err != nil {
        c.SSEvent("error", "调用模型失败")
        return
    }
    defer resp.Body.Close()

    scanner := bufio.NewScanner(resp.Body)
    // c.Stream 返回 false 时(客户端断开)会停止循环
    c.Stream(func(w io.Writer) bool {
        if !scanner.Scan() {
            return false // 读完了,结束
        }
        line := scanner.Text()
        if !strings.HasPrefix(line, "data: ") {
            return true
        }
        data := strings.TrimPrefix(line, "data: ")
        if data == "[DONE]" {
            c.SSEvent("done", "[DONE]")
            return false
        }
        var chunk StreamChunk
        if json.Unmarshal([]byte(data), &chunk) == nil && len(chunk.Choices) > 0 {
            if token := chunk.Choices[0].Delta.Content; token != "" {
                // SSEvent 会自动处理格式和 Flush
                c.SSEvent("message", token)
            }
        }
        return true
    })
}

func main() {
    r := gin.Default()
    r.GET("/chat/stream", handleStream)
    r.Run(":8080")
}
```

> 注意:上面用到 `io.Writer`,记得 `import "io"`。`c.SSEvent` 会自动帮你 flush,比手写 `Fprintf + Flush` 省事。

本周产出:一个能跑的 `/chat/stream` 接口 + 一个 HTML 页面,打开就能看到 AI 逐字回答。

---

### Day 7:复盘 + 提交

- 把标准库版和 Gin 版都跑通,对比两者写法
- 测三种情况:正常问答、中途关页面(看后端日志是否停止)、故意断网(看错误处理)
- `git` 提交本周代码,写清楚 commit message

---

## 四、本周验收清单

- [ ] 能说清 SSE 的报文格式(`data:`、空行、`event:`)
- [ ] 能解释为什么必须 `Flush()`,不刷会怎样
- [ ] 能用 `http.Client` + `bufio.Scanner` 流式读取响应
- [ ] 能调通 LLM 的 `stream: true` 接口并拼出完整回答
- [ ] 能把 LLM 流转成 SSE 转发给前端
- [ ] `/chat/stream` 接口跑通,前端 `EventSource` 有打字机效果
- [ ] 能用 `context` 处理客户端断连和超时
- [ ] 关掉前端页面时,后端能感知并停止调用 LLM
- [ ] 能说清 `EventSource` 只支持 GET 的限制
- [ ] 能读懂 Gin 的 `c.Stream` + `c.SSEvent` 写法

---

## 五、常见踩坑提醒

| 坑 | 说明 |
|---|---|
| 忘了 `Flush()` | 数据被缓冲,前端等全部生成完才一次性收到,没有流式效果 |
| 少了 `text/event-stream` 头 | 浏览器不当作 SSE 处理,`EventSource` 报错 |
| token 里的换行没转义 | 破坏 SSE 的空行分隔,消息错乱。用 `SSEvent` 或整体 JSON 编码 |
| 用 `io.ReadAll` 读上游 | 等 LLM 全部生成完才返回,失去流式意义 |
| 前端不 `es.close()` | done 后 EventSource 自动重连,反复请求 LLM,烧钱 |
| 没绑 context 到上游 | 用户关页面了,后端还在傻傻调 LLM,浪费 token |
| nginx/代理缓冲 | 生产环境代理默认缓冲响应,需配 `proxy_buffering off` |

---

## 六、推荐资料

- **MDN - Using server-sent events**:SSE 前端对接权威文档
- **OpenAI API - Streaming**:平台文档里的 stream 说明,各兼容厂商格式一致
- **Gin 文档 - Server-Sent Events 示例**:看 `c.Stream` 官方用法
- DeepSeek / GLM 开放平台文档:确认你用的模型名和 BaseURL

---

## 七、本周节奏参考

| 天 | 主题 | 核心任务 |
|---|---|---|
| Day 1 | SSE 协议 | 起一个纯 Go 的 SSE demo,curl 看到逐条推送 |
| Day 2 | 流式读取 | 用 `bufio.Scanner` 边收边读 |
| Day 3 | 调 LLM 流 | 调通 `stream: true`,拼出完整回答 |
| Day 4 | 流式转发 | 把 LLM 流转成 SSE 推给前端 |
| Day 5 | 前端对接 | `EventSource` 页面看到打字机效果 |
| Day 6 | 超时断连 | 用 context 处理断连和超时 |
| Day 7 | Gin 封装 + 提交 | `c.Stream` 重写,测边界,Git 提交 |
