# net/http 用法入门

> `net/http` 是 Go 标准库自带的 HTTP 包。它可以用来写 Web 服务、处理接口请求,也可以用来发起 HTTP 请求。

---

## 1. net/http 是什么?

`net/http` 是 Go 官方提供的 HTTP 标准库。

它可以做两类事情:

1. **写服务端**: 接收浏览器、Postman、curl、前端发来的请求
2. **写客户端**: 主动请求别人的接口

本周 Todo API 主要用的是第一种: **写 HTTP 服务端**。

---

## 2. 最小 HTTP 服务

```go
package main

import (
    "fmt"
    "net/http"
)

func main() {
    http.HandleFunc("/hello", func(w http.ResponseWriter, r *http.Request) {
        fmt.Fprintln(w, "Hello, Go HTTP!")
    })

    fmt.Println("服务启动在 :8080")
    http.ListenAndServe(":8080", nil)
}
```

运行:

```bash
go run main.go
```

访问:

```bash
curl http://localhost:8080/hello
```

返回:

```text
Hello, Go HTTP!
```

---

## 3. import "net/http" 是什么意思?

```go
import "net/http"
```

表示引入 Go 标准库里的 HTTP 包。

这个包里有很多和 Web 开发相关的能力,比如:

| 能力 | 说明 |
|---|---|
| `http.HandleFunc` | 注册路由处理函数 |
| `http.ListenAndServe` | 启动 HTTP 服务 |
| `http.ResponseWriter` | 给客户端写响应 |
| `*http.Request` | 读取客户端请求 |
| `http.MethodGet` | GET 方法常量 |
| `http.MethodPost` | POST 方法常量 |
| `http.Error` | 返回错误响应 |

---

## 4. http.HandleFunc:注册路由

```go
http.HandleFunc("/todos", todosHandler)
```

意思是:

```text
当用户访问 /todos 时,交给 todosHandler 函数处理。
```

比如:

```go
func todosHandler(w http.ResponseWriter, r *http.Request) {
    fmt.Fprintln(w, "todos page")
}
```

完整理解:

```text
路径 /todos -> 处理函数 todosHandler
```

这和前端路由有点像:

```text
/todos 页面 -> 对应某个组件或处理逻辑
```

但在后端里,它对应的是一个请求处理函数。

---

## 5. Handler 函数的两个参数

HTTP 处理函数一般长这样:

```go
func handler(w http.ResponseWriter, r *http.Request) {
    // 处理请求
}
```

这两个参数非常重要:

| 参数 | 作用 | 类比 |
|---|---|---|
| `w http.ResponseWriter` | 写响应给客户端 | response |
| `r *http.Request` | 读取客户端请求 | request |

你可以记成:

```text
r = request,读请求
w = response writer,写响应
```

---

## 6. http.ResponseWriter:写响应

```go
func helloHandler(w http.ResponseWriter, r *http.Request) {
    fmt.Fprintln(w, "hello")
}
```

`w` 是响应写入器。

你往 `w` 里写什么,客户端就收到什么。

常见写法:

```go
fmt.Fprintln(w, "hello")
```

或者返回 JSON:

```go
w.Header().Set("Content-Type", "application/json")
json.NewEncoder(w).Encode(data)
```

---

## 7. *http.Request:读请求

```go
func handler(w http.ResponseWriter, r *http.Request) {
    fmt.Println(r.Method)
    fmt.Println(r.URL.Path)
}
```

`r` 里包含客户端请求的信息。

常用字段:

| 字段 | 说明 |
|---|---|
| `r.Method` | 请求方法,如 GET / POST |
| `r.URL.Path` | 请求路径 |
| `r.Body` | 请求体,常用于读取 JSON |
| `r.URL.Query()` | 查询参数 |
| `r.Header` | 请求头 |

---

## 8. 根据请求方法区分功能

一个路径可以根据不同 HTTP 方法做不同事情。

例如 `/todos`:

```go
func todosHandler(w http.ResponseWriter, r *http.Request) {
    switch r.Method {
    case http.MethodGet:
        fmt.Fprintln(w, "查询 Todo 列表")
    case http.MethodPost:
        fmt.Fprintln(w, "创建 Todo")
    default:
        http.Error(w, "方法不支持", http.StatusMethodNotAllowed)
    }
}
```

对应关系:

| 请求 | 含义 |
|---|---|
| `GET /todos` | 查询列表 |
| `POST /todos` | 创建数据 |
| `PUT /todos/1` | 更新数据 |
| `DELETE /todos/1` | 删除数据 |

---

## 9. 常用 HTTP 方法常量

Go 标准库提供了方法常量:

```go
http.MethodGet
http.MethodPost
http.MethodPut
http.MethodDelete
```

推荐写法:

```go
case http.MethodGet:
```

不推荐写法:

```go
case "GET":
```

虽然两者都能运行,但用标准库常量更清晰、更不容易写错。

---

## 10. 返回 JSON

后端接口通常返回 JSON。

示例:

```go
type Todo struct {
    ID    int    `json:"id"`
    Title string `json:"title"`
    Done  bool   `json:"done"`
}

func todoHandler(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "application/json")

    todo := Todo{
        ID:    1,
        Title: "学 Go",
        Done:  false,
    }

    json.NewEncoder(w).Encode(todo)
}
```

访问后返回:

```json
{"id":1,"title":"学 Go","done":false}
```

关键点:

```go
w.Header().Set("Content-Type", "application/json")
```

告诉客户端返回的是 JSON。

```go
json.NewEncoder(w).Encode(todo)
```

把 Go 结构体编码成 JSON 并写入响应。

---

## 11. 读取 JSON 请求体

当前端或 curl 发来 JSON 时,Go 可以这样读取:

```go
func createTodoHandler(w http.ResponseWriter, r *http.Request) {
    var todo Todo

    if err := json.NewDecoder(r.Body).Decode(&todo); err != nil {
        http.Error(w, "参数错误", http.StatusBadRequest)
        return
    }

    json.NewEncoder(w).Encode(todo)
}
```

含义:

```go
var todo Todo
```

先准备一个变量接收数据。

```go
json.NewDecoder(r.Body).Decode(&todo)
```

把请求体里的 JSON 解析到 `todo` 变量里。

注意这里要传 `&todo`,因为 Decode 需要修改这个变量。

---

## 12. http.Error:返回错误

```go
http.Error(w, "参数错误", http.StatusBadRequest)
```

表示返回一个错误响应。

常见状态码:

| 状态码 | Go 常量 | 含义 |
|---|---|---|
| 200 | `http.StatusOK` | 成功 |
| 201 | `http.StatusCreated` | 创建成功 |
| 400 | `http.StatusBadRequest` | 请求参数错误 |
| 404 | `http.StatusNotFound` | 资源不存在 |
| 405 | `http.StatusMethodNotAllowed` | 方法不支持 |
| 500 | `http.StatusInternalServerError` | 服务器内部错误 |

示例:

```go
if r.Method != http.MethodPost {
    http.Error(w, "只支持 POST", http.StatusMethodNotAllowed)
    return
}
```

---

## 13. http.ListenAndServe:启动服务

```go
http.ListenAndServe(":8080", nil)
```

意思是:

```text
启动 HTTP 服务,监听 8080 端口。
```

更严谨的写法是处理错误:

```go
if err := http.ListenAndServe(":8080", nil); err != nil {
    fmt.Println("服务启动失败:", err)
}
```

因为端口可能被占用,或者服务启动失败。

---

## 14. nil 是什么意思?

```go
http.ListenAndServe(":8080", nil)
```

第二个参数传 `nil`,表示使用 Go 默认的路由器 `DefaultServeMux`。

你用:

```go
http.HandleFunc("/todos", todosHandler)
```

注册的路由,默认就是注册到这个默认路由器上。

所以入门阶段可以先固定这样写:

```go
http.HandleFunc("/path", handler)
http.ListenAndServe(":8080", nil)
```

---

## 15. 完整 Todo 示例

```go
package main

import (
    "encoding/json"
    "fmt"
    "net/http"
    "sync"
)

type Todo struct {
    ID    int    `json:"id"`
    Title string `json:"title"`
    Done  bool   `json:"done"`
}

var (
    todos  = make(map[int]Todo)
    nextID = 1
    mu     sync.Mutex
)

func todosHandler(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "application/json")

    switch r.Method {
    case http.MethodGet:
        mu.Lock()
        list := make([]Todo, 0, len(todos))
        for _, todo := range todos {
            list = append(list, todo)
        }
        mu.Unlock()

        json.NewEncoder(w).Encode(list)

    case http.MethodPost:
        var todo Todo
        if err := json.NewDecoder(r.Body).Decode(&todo); err != nil {
            http.Error(w, "参数错误", http.StatusBadRequest)
            return
        }

        mu.Lock()
        todo.ID = nextID
        nextID++
        todos[todo.ID] = todo
        mu.Unlock()

        json.NewEncoder(w).Encode(todo)

    default:
        http.Error(w, "方法不支持", http.StatusMethodNotAllowed)
    }
}

func main() {
    http.HandleFunc("/todos", todosHandler)
    fmt.Println("服务启动在 :8080")

    if err := http.ListenAndServe(":8080", nil); err != nil {
        fmt.Println("服务启动失败:", err)
    }
}
```

---

## 16. 用 curl 测试

启动服务:

```bash
go run main.go
```

创建 Todo:

```bash
curl -X POST http://localhost:8080/todos \
  -H "Content-Type: application/json" \
  -d '{"title":"学 Go net/http","done":false}'
```

查询 Todo:

```bash
curl http://localhost:8080/todos
```

---

## 17. 和前端思维对照

| 前端 / Node.js | Go net/http |
|---|---|
| Express / Koa | `net/http` 标准库 |
| `app.get('/todos')` | `http.HandleFunc("/todos", handler)` + 判断 `r.Method` |
| `req` | `r *http.Request` |
| `res` | `w http.ResponseWriter` |
| `res.json(data)` | `json.NewEncoder(w).Encode(data)` |
| `req.body` | `r.Body` |
| middleware | Gin 中更常见,标准库也能做但写法更原始 |

---

## 18. net/http 和 Gin 的关系

Gin 是基于 `net/http` 封装出来的 Web 框架。

你可以理解为:

```text
net/http = Go 官方原生 HTTP 能力
Gin = 更方便的 Web 框架,底层仍然离不开 net/http
```

标准库写法:

```go
http.HandleFunc("/ping", func(w http.ResponseWriter, r *http.Request) {
    fmt.Fprintln(w, "pong")
})
```

Gin 写法:

```go
r.GET("/ping", func(c *gin.Context) {
    c.JSON(200, gin.H{"message": "pong"})
})
```

Gin 更适合正式 Web API 开发,但先学 `net/http` 能帮助你理解 Go Web 的底层原理。

---

## 19. 入门阶段重点掌握

你现在学习 `net/http`,先掌握这些就够了:

- `http.HandleFunc` 注册路由
- `http.ListenAndServe` 启动服务
- `w http.ResponseWriter` 写响应
- `r *http.Request` 读请求
- `r.Method` 判断 GET / POST / PUT / DELETE
- `json.NewDecoder(r.Body).Decode(&data)` 读取 JSON
- `json.NewEncoder(w).Encode(data)` 返回 JSON
- `http.Error` 返回错误状态码

---

## 20. 记忆口诀

```text
HandleFunc 负责接路由
Request 负责读请求
ResponseWriter 负责写响应
ListenAndServe 负责启动服务
JSON Decoder 读请求体
JSON Encoder 写响应体
```
