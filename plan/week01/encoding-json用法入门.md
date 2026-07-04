# encoding/json 用法入门

> `encoding/json` 是 Go 标准库自带的 JSON 编码/解码包。它负责在 **Go 数据结构** 和 **JSON 字符串** 之间相互转换。

---

## 1. encoding/json 是什么?

在 Web API 开发里,前后端通常用 JSON 传数据。

例如前端传给后端:

```json
{"title":"学 Go","done":false}
```

Go 后端需要把这段 JSON 解析成 Go 结构体。

后端返回给前端时,也需要把 Go 结构体转成 JSON。

`encoding/json` 就是用来做这两件事的:

| 方向 | Go 术语 | 常用函数 |
|---|---|---|
| JSON -> Go | 解码 / 反序列化 | `json.Unmarshal` / `json.NewDecoder(...).Decode(...)` |
| Go -> JSON | 编码 / 序列化 | `json.Marshal` / `json.NewEncoder(...).Encode(...)` |

---

## 2. 为什么叫 encoding/json?

```go
import "encoding/json"
```

意思是引入 Go 标准库 `encoding` 目录下的 `json` 包。

你可以简单理解为:

```text
encoding/json = Go 官方 JSON 工具包
```

它不需要安装第三方依赖,直接 import 就能用。

---

## 3. JSON 和 Go struct 的关系

Go 里通常用 `struct` 表示 JSON 数据结构。

```go
type Todo struct {
    ID    int    `json:"id"`
    Title string `json:"title"`
    Done  bool   `json:"done"`
}
```

对应 JSON:

```json
{
  "id": 1,
  "title": "学 Go",
  "done": false
}
```

---

## 4. json tag 是什么?

这一段:

```go
ID    int    `json:"id"`
Title string `json:"title"`
Done  bool   `json:"done"`
```

里面的:

```go
`json:"id"`
```

叫 **结构体标签**，也常叫 **json tag**。

它告诉 Go:

```text
Go 字段 ID 转成 JSON 时,字段名叫 id
Go 字段 Title 转成 JSON 时,字段名叫 title
Go 字段 Done 转成 JSON 时,字段名叫 done
```

为什么需要它?

因为 Go 结构体导出字段必须大写开头:

```go
ID
Title
Done
```

但 JSON 通常喜欢小写字段名:

```json
{"id":1,"title":"学 Go","done":false}
```

所以用 json tag 做映射。

---

## 5. 字段为什么要大写?

Go 的规则是:

```text
结构体字段大写开头 = 可以被其他包访问
结构体字段小写开头 = 只能在当前包内部访问
```

`encoding/json` 是另一个包。

所以如果你写:

```go
type Todo struct {
    id    int
    title string
    done  bool
}
```

这些字段是小写,`encoding/json` 访问不到,转 JSON 时会被忽略。

正确写法:

```go
type Todo struct {
    ID    int    `json:"id"`
    Title string `json:"title"`
    Done  bool   `json:"done"`
}
```

记住:

```text
Go struct 字段要给 JSON 用,字段名必须大写开头。
JSON 字段名想小写,用 json tag 控制。
```

---

## 6. Go -> JSON: json.Marshal

`json.Marshal` 可以把 Go 数据转成 JSON 字节切片。

```go
package main

import (
    "encoding/json"
    "fmt"
)

type Todo struct {
    ID    int    `json:"id"`
    Title string `json:"title"`
    Done  bool   `json:"done"`
}

func main() {
    todo := Todo{
        ID:    1,
        Title: "学 Go",
        Done:  false,
    }

    data, err := json.Marshal(todo)
    if err != nil {
        fmt.Println("JSON 编码失败:", err)
        return
    }

    fmt.Println(string(data))
}
```

输出:

```json
{"id":1,"title":"学 Go","done":false}
```

注意:

```go
json.Marshal(todo)
```

返回的是 `[]byte`,所以打印时通常转成 string:

```go
fmt.Println(string(data))
```

---

## 7. JSON -> Go: json.Unmarshal

`json.Unmarshal` 可以把 JSON 字节切片解析到 Go 变量里。

```go
package main

import (
    "encoding/json"
    "fmt"
)

type Todo struct {
    ID    int    `json:"id"`
    Title string `json:"title"`
    Done  bool   `json:"done"`
}

func main() {
    data := []byte(`{"id":1,"title":"学 Go","done":false}`)

    var todo Todo
    err := json.Unmarshal(data, &todo)
    if err != nil {
        fmt.Println("JSON 解析失败:", err)
        return
    }

    fmt.Println(todo.ID)
    fmt.Println(todo.Title)
    fmt.Println(todo.Done)
}
```

重点是:

```go
json.Unmarshal(data, &todo)
```

这里必须传 `&todo`。

因为 `Unmarshal` 要把解析结果写进 `todo` 变量里,所以需要它的地址。

---

## 8. 为什么 Unmarshal 要传指针?

错误写法:

```go
json.Unmarshal(data, todo)
```

正确写法:

```go
json.Unmarshal(data, &todo)
```

原因:

```text
如果不传地址,函数拿到的是副本,改不到原变量。
传 &todo,函数才能把 JSON 结果写回 todo。
```

这和你前面学的指针接收者思想是一致的:

```text
想修改原对象 -> 传指针
```

---

## 9. Web API 中更常用: NewEncoder / NewDecoder

在 HTTP 服务里,我们通常不会手动处理 `[]byte`。

而是直接从请求体读 JSON,直接往响应里写 JSON。

这时更常用:

```go
json.NewDecoder(r.Body).Decode(&data)
json.NewEncoder(w).Encode(data)
```

---

## 10. 读取请求 JSON: json.NewDecoder

在 `net/http` 里,请求体是:

```go
r.Body
```

可以这样解析:

```go
func createTodo(w http.ResponseWriter, r *http.Request) {
    var todo Todo

    if err := json.NewDecoder(r.Body).Decode(&todo); err != nil {
        http.Error(w, "参数错误", http.StatusBadRequest)
        return
    }

    fmt.Println(todo.Title)
}
```

含义:

```text
从请求体 r.Body 里读取 JSON,
解析后写入 todo 变量。
```

---

## 11. 返回 JSON 响应: json.NewEncoder

```go
func getTodo(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "application/json")

    todo := Todo{
        ID:    1,
        Title: "学 Go",
        Done:  false,
    }

    json.NewEncoder(w).Encode(todo)
}
```

含义:

```text
把 todo 转成 JSON,
然后写入响应 w。
```

这一句很常见:

```go
w.Header().Set("Content-Type", "application/json")
```

它告诉客户端:

```text
我返回的是 JSON。
```

---

## 12. Marshal / Unmarshal 和 Encoder / Decoder 的区别

| 用法 | 适合场景 | 输入/输出 |
|---|---|---|
| `json.Marshal` | Go 数据转 JSON 字节 | Go -> `[]byte` |
| `json.Unmarshal` | JSON 字节转 Go 数据 | `[]byte` -> Go |
| `json.NewEncoder(w).Encode(data)` | HTTP 响应 JSON | Go -> `ResponseWriter` |
| `json.NewDecoder(r.Body).Decode(&data)` | HTTP 请求体 JSON | `Request.Body` -> Go |

简单记:

```text
普通变量互转: Marshal / Unmarshal
HTTP 请求响应: Encoder / Decoder
```

---

## 13. json tag 常见写法

### 改字段名

```go
type User struct {
    UserName string `json:"user_name"`
}
```

JSON:

```json
{"user_name":"Alice"}
```

---

### 忽略字段

```go
type User struct {
    Name     string `json:"name"`
    Password string `json:"-"`
}
```

`Password` 不会出现在 JSON 里。

---

### 空值时省略 omitempty

```go
type User struct {
    Name  string `json:"name"`
    Email string `json:"email,omitempty"`
}
```

如果 `Email` 是空字符串,转 JSON 时会省略这个字段。

例如:

```go
User{Name: "Alice"}
```

输出:

```json
{"name":"Alice"}
```

---

## 14. Go 零值和 JSON

如果字段没有赋值,Go 会用零值。

| Go 类型 | 零值 | JSON 表现 |
|---|---|---|
| `string` | `""` | `""` |
| `int` | `0` | `0` |
| `bool` | `false` | `false` |
| `slice` | `nil` | `null` |
| `map` | `nil` | `null` |
| `*T` 指针 | `nil` | `null` |

示例:

```go
todo := Todo{}
data, _ := json.Marshal(todo)
fmt.Println(string(data))
```

输出:

```json
{"id":0,"title":"","done":false}
```

---

## 15. JSON 数组和 Go slice

JSON 数组对应 Go 的 slice。

```json
[
  {"id":1,"title":"学 Go","done":false},
  {"id":2,"title":"写 Todo API","done":true}
]
```

对应 Go:

```go
var todos []Todo
```

解析:

```go
err := json.Unmarshal(data, &todos)
```

返回:

```go
json.NewEncoder(w).Encode(todos)
```

---

## 16. JSON 对象和 Go map

如果 JSON 结构不固定,可以用 map。

```go
var data map[string]interface{}
```

示例:

```go
raw := []byte(`{"name":"Alice","age":18}`)

var m map[string]interface{}
if err := json.Unmarshal(raw, &m); err != nil {
    fmt.Println(err)
    return
}

fmt.Println(m["name"])
fmt.Println(m["age"])
```

但入门阶段,如果结构明确,优先用 struct。

因为 struct 更清晰、更安全。

---

## 17. interface{} 是什么?

你可能会看到:

```go
map[string]interface{}
```

`interface{}` 可以理解为“任意类型”。

因为 JSON 里的值可能是:

- string
- number
- bool
- object
- array
- null

所以不确定类型时,Go 会用 `interface{}` 承接。

但入门阶段建议:

```text
能用 struct 就用 struct,少用 map[string]interface{}。
```

---

## 18. JSON 字段缺失会怎样?

如果 JSON 里少了某个字段,Go 会使用对应类型的零值。

```json
{"title":"学 Go"}
```

解析到:

```go
type Todo struct {
    ID    int    `json:"id"`
    Title string `json:"title"`
    Done  bool   `json:"done"`
}
```

结果是:

```go
Todo{
    ID: 0,
    Title: "学 Go",
    Done: false,
}
```

这就是为什么后端通常还要做参数校验。

---

## 19. JSON 类型不匹配会怎样?

如果 JSON 类型和 Go 字段类型不匹配,会报错。

例如:

```json
{"id":"abc","title":"学 Go","done":false}
```

但 Go 里:

```go
ID int `json:"id"`
```

这时解析会失败,因为字符串 `"abc"` 不能放进 `int`。

所以要处理错误:

```go
if err := json.NewDecoder(r.Body).Decode(&todo); err != nil {
    http.Error(w, "参数错误", http.StatusBadRequest)
    return
}
```

---

## 20. 完整 HTTP 示例

```go
package main

import (
    "encoding/json"
    "fmt"
    "net/http"
)

type Todo struct {
    ID    int    `json:"id"`
    Title string `json:"title"`
    Done  bool   `json:"done"`
}

func todosHandler(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "application/json")

    switch r.Method {
    case http.MethodGet:
        todos := []Todo{
            {ID: 1, Title: "学 Go", Done: false},
            {ID: 2, Title: "写 Todo API", Done: true},
        }
        json.NewEncoder(w).Encode(todos)

    case http.MethodPost:
        var todo Todo
        if err := json.NewDecoder(r.Body).Decode(&todo); err != nil {
            http.Error(w, "参数错误", http.StatusBadRequest)
            return
        }

        todo.ID = 100
        json.NewEncoder(w).Encode(todo)

    default:
        http.Error(w, "方法不支持", http.StatusMethodNotAllowed)
    }
}

func main() {
    http.HandleFunc("/todos", todosHandler)
    fmt.Println("服务启动在 :8080")
    http.ListenAndServe(":8080", nil)
}
```

测试:

```bash
curl http://localhost:8080/todos
```

```bash
curl -X POST http://localhost:8080/todos \
  -H "Content-Type: application/json" \
  -d '{"title":"学 encoding/json","done":false}'
```

---

## 21. 和 JavaScript 的对照

| JavaScript | Go |
|---|---|
| `JSON.stringify(obj)` | `json.Marshal(obj)` |
| `JSON.parse(str)` | `json.Unmarshal([]byte(str), &obj)` |
| `res.json(data)` | `json.NewEncoder(w).Encode(data)` |
| `req.body` | `json.NewDecoder(r.Body).Decode(&data)` |
| 对象字段可随意访问 | struct 字段要大写才能被 JSON 包访问 |
| 字段名通常天然小写 | 用 json tag 控制字段名 |

---

## 22. 常见错误

### 错误 1: struct 字段小写

错误:

```go
type Todo struct {
    id    int    `json:"id"`
    title string `json:"title"`
}
```

这样 JSON 包访问不到字段。

正确:

```go
type Todo struct {
    ID    int    `json:"id"`
    Title string `json:"title"`
}
```

---

### 错误 2: Unmarshal / Decode 没传指针

错误:

```go
json.Unmarshal(data, todo)
json.NewDecoder(r.Body).Decode(todo)
```

正确:

```go
json.Unmarshal(data, &todo)
json.NewDecoder(r.Body).Decode(&todo)
```

---

### 错误 3: 忘记处理 err

错误:

```go
json.NewDecoder(r.Body).Decode(&todo)
```

正确:

```go
if err := json.NewDecoder(r.Body).Decode(&todo); err != nil {
    http.Error(w, "参数错误", http.StatusBadRequest)
    return
}
```

---

### 错误 4: 忘记设置 Content-Type

建议返回 JSON 前加上:

```go
w.Header().Set("Content-Type", "application/json")
```

---

## 23. 入门阶段重点掌握

现在你先掌握这几个就够了:

```go
// Go -> JSON 字节
json.Marshal(data)

// JSON 字节 -> Go
json.Unmarshal(raw, &data)

// HTTP 请求体 JSON -> Go
json.NewDecoder(r.Body).Decode(&data)

// Go -> HTTP JSON 响应
json.NewEncoder(w).Encode(data)
```

还有两句重点:

```text
struct 字段要大写,json tag 控制 JSON 字段名。
Decode / Unmarshal 要传指针,因为它要修改变量。
```

---

## 24. 记忆口诀

```text
Marshal: Go 变 JSON
Unmarshal: JSON 变 Go
Encoder: 写 JSON 响应
Decoder: 读 JSON 请求
字段大写给包访问
json tag 控制字段名
想写入变量就传指针
```
