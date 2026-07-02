# 第一周详细学习内容:Go 语法入门

> 主题:Go 语法入门
> 目标:让你能看懂、跑通、敢改 Go 代码,并做出第一个 Todo API。
> 原则:本周不追求深,追求能独立写出并读懂基础 Go 代码。**80% 时间敲代码,20% 时间看文档。**

---

## 一、本周核心目标

| 目标 | 说明 |
|---|---|
| 环境跑通 | 装好 Go,理解 `go mod`,能编译运行 |
| 语法基础 | 掌握 `struct`、`interface`、`slice`、`map`、`error`、`defer` |
| 思维切换 | 从 JS/TS 的动态、异常思维,切换到 Go 的强类型、显式错误处理 |
| 项目产出 | 完成一个 Todo API 的基础 CRUD(内存版即可) |

---

## 二、前端视角的思维转换(最重要)

你是前端出身,先建立这几个对照,后面学得快:

| 你熟悉的 JS/TS | Go 的做法 | 关键差异 |
|---|---|---|
| `let/const`,类型可选 | 变量必须有明确类型 | Go 是强类型,编译期检查 |
| `try/catch` 抛异常 | 函数返回 `error`,手动判断 | Go 没有异常,错误是值 |
| `class` | `struct` + 方法 | Go 没有继承,用组合 |
| `interface`(结构约束) | `interface`(方法集合) | Go 接口是隐式实现 |
| `Array` | `slice` | slice 有长度和容量概念 |
| `Object`/`Map` | `map` | map 需要初始化才能写 |
| `async/await` | `goroutine`/`channel`(第7周) | 本周先不碰 |
| `undefined/null` | 零值(zero value) | 每种类型都有默认零值 |

记住一句话:**Go 里 `if err != nil` 会到处出现,这不是啰嗦,是设计。**

---

## 三、每天学习安排(7天)

### Day 1:环境搭建 + 第一个程序

**安装与配置**
```bash
# macOS 用 brew 装
brew install go

# 验证
go version

# 配置国内代理(下载依赖更快)
go env -w GOPROXY=https://goproxy.cn,direct
```

**创建第一个项目**
```bash
mkdir hello && cd hello
go mod init hello        # 生成 go.mod,类似 package.json
```

**写第一个程序** `main.go`
```go
package main

import "fmt"

func main() {
    fmt.Println("Hello, Go!")
}
```

**运行**
```bash
go run main.go     # 直接运行(类似 node main.js)
go build           # 编译成二进制文件
```

**Day 1 理解要点**
- `package main` + `func main()` 是可执行程序的入口
- `go.mod` 记录模块名和依赖,等价于 `package.json`
- `import` 的包如果没用会编译报错(Go 很严格)

---

### Day 2:变量、类型、函数

**变量声明**
```go
// 三种方式
var name string = "Go"   // 完整写法
var age = 18             // 类型推导
count := 10              // 短声明(最常用,只能在函数内)

// 零值:没赋值时的默认值
var s string   // ""
var n int      // 0
var b bool     // false
var p *int     // nil
```

**基础类型**
```go
int, int64, float64, string, bool, byte, rune
```

**函数(重点:多返回值)**
```go
// Go 函数可以返回多个值,这是错误处理的基础
func divide(a, b int) (int, error) {
    if b == 0 {
        return 0, fmt.Errorf("除数不能为0")
    }
    return a / b, nil
}

// 调用
result, err := divide(10, 2)
if err != nil {
    fmt.Println("出错:", err)
    return
}
fmt.Println("结果:", result)
```

**Day 2 理解要点**
- `:=` 只能在函数内用,`var` 哪里都能用
- 多返回值 `(int, error)` 是 Go 错误处理的核心模式
- 未使用的变量会编译报错(和未使用的 import 一样)

---

### Day 3:struct 与方法(替代 class)

```go
// 定义结构体(类似 TS 的 interface/type,但是实体)
type User struct {
    ID    int
    Name  string
    Email string
}

// 给 struct 定义方法
// (u User) 叫接收者,类似 this
func (u User) Greet() string {
    return "Hi, I'm " + u.Name
}

// 指针接收者:能修改原对象
func (u *User) Rename(newName string) {
    u.Name = newName
}

func main() {
    u := User{ID: 1, Name: "Alice", Email: "a@x.com"}
    fmt.Println(u.Greet())

    u.Rename("Bob")      // 用指针接收者修改
    fmt.Println(u.Name)  // Bob
}
```

**Day 3 理解要点**
- `struct` 是数据,方法是行为,分开写
- 值接收者 `(u User)` 拿到的是副本,改了不影响原对象
- 指针接收者 `(u *User)` 能改原对象,**要修改状态就用指针**
- Go 没有 `class`、没有继承,用 struct 组合

---

### Day 4:slice 与 map

**slice(动态数组)**
```go
// 创建
nums := []int{1, 2, 3}
nums = append(nums, 4)      // 追加,类似 push
fmt.Println(len(nums))      // 长度 4

// 遍历
for i, v := range nums {
    fmt.Println(i, v)       // 下标, 值
}

// 切片操作
sub := nums[1:3]            // [2, 3]
```

**map(键值对)**
```go
// 必须初始化才能写
m := make(map[string]int)
m["a"] = 1
m["b"] = 2

// 读取 + 判断是否存在
v, ok := m["a"]
if ok {
    fmt.Println("找到:", v)
}

// 删除
delete(m, "a")

// 遍历(注意:map 遍历顺序随机)
for k, v := range m {
    fmt.Println(k, v)
}
```

**Day 4 理解要点**
- slice 用 `append` 追加,用 `range` 遍历
- map 必须 `make` 初始化,否则写入会 panic
- `v, ok := m[key]` 的 `ok` 用来判断键是否存在(替代 JS 的 `in`)

---

### Day 5:interface 与 error

**interface(隐式实现)**
```go
// 定义接口:只声明方法
type Animal interface {
    Sound() string
}

// 定义类型
type Dog struct{}
func (d Dog) Sound() string { return "Woof" }

type Cat struct{}
func (c Cat) Sound() string { return "Meow" }

// Dog 和 Cat 自动"实现"了 Animal,不用显式声明
func describe(a Animal) {
    fmt.Println(a.Sound())
}

func main() {
    describe(Dog{})   // Woof
    describe(Cat{})   // Meow
}
```

**error 处理**
```go
import "errors"

// 自定义错误
var ErrNotFound = errors.New("未找到")

func findUser(id int) (*User, error) {
    if id <= 0 {
        return nil, ErrNotFound
    }
    return &User{ID: id, Name: "Alice"}, nil
}

// 使用
user, err := findUser(-1)
if err != nil {
    if errors.Is(err, ErrNotFound) {
        fmt.Println("用户不存在")
    }
    return
}
fmt.Println(user.Name)
```

**Day 5 理解要点**
- Go 接口是**隐式实现**:只要实现了接口的所有方法,就算实现了接口,不用 `implements`
- `error` 就是一个接口,错误是普通的返回值
- 用 `errors.Is` 判断错误类型

---

### Day 6:defer + 项目实战(Todo API)

**defer(延迟执行,常用于资源清理)**
```go
func readFile() {
    file := openFile()
    defer file.Close()   // 函数结束时自动执行,不管从哪里 return

    // ... 处理文件
}   // 到这里 file.Close() 才执行
```

**Day 6-7 实战:Todo API**

这是本周的产出。用标准库 `net/http` 做,先不引 Gin(第2周再学)。

```go
package main

import (
    "encoding/json"
    "fmt"
    "net/http"
    "sync"
)

// Todo 数据结构
type Todo struct {
    ID    int    `json:"id"`
    Title string `json:"title"`
    Done  bool   `json:"done"`
}

// 内存存储(加锁保证并发安全)
var (
    todos  = make(map[int]Todo)
    nextID = 1
    mu     sync.Mutex
)

// 创建 + 列表
func todosHandler(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "application/json")

    switch r.Method {
    case http.MethodGet:
        mu.Lock()
        list := make([]Todo, 0, len(todos))
        for _, t := range todos {
            list = append(list, t)
        }
        mu.Unlock()
        json.NewEncoder(w).Encode(list)

    case http.MethodPost:
        var t Todo
        if err := json.NewDecoder(r.Body).Decode(&t); err != nil {
            http.Error(w, "参数错误", http.StatusBadRequest)
            return
        }
        mu.Lock()
        t.ID = nextID
        nextID++
        todos[t.ID] = t
        mu.Unlock()
        json.NewEncoder(w).Encode(t)

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

**测试**
```bash
go run main.go

# 另开终端
curl -X POST localhost:8080/todos -d '{"title":"学Go","done":false}'
curl localhost:8080/todos
```

进阶(有余力再做):补上按 ID 查询、更新、删除,凑齐完整 CRUD。

---

### Day 7:复盘 + 提交

- 把 Todo API 补全成完整 CRUD(GET/POST/PUT/DELETE)
- `git init` 并提交本周代码
- 按下面的自检表检查掌握程度

---

## 四、本周验收清单

对照打卡,能独立做到才算过关:

- [ ] 能解释 `go run` 和 `go build` 的区别
- [ ] 能说清 `:=` 和 `var` 的使用场景
- [ ] 能手写一个带多返回值 `(结果, error)` 的函数
- [ ] 能定义 struct 并给它加值接收者/指针接收者方法
- [ ] 能说清值接收者和指针接收者的区别
- [ ] 会用 slice 的 `append` 和 map 的 `v, ok :=` 判断
- [ ] 能解释 Go 接口的"隐式实现"
- [ ] 能独立写出 `if err != nil` 错误处理
- [ ] Todo API 能跑通,curl 能创建和查询
- [ ] 能读懂 AI 生成的 Go 代码,并手动改一处

---

## 五、常见踩坑提醒

| 坑 | 说明 |
|---|---|
| 未使用变量/import 报错 | Go 强制,删掉或用 `_` 忽略 |
| map 没 `make` 就写入 | 直接 panic,必须先 `make` |
| 值接收者改不了原对象 | 要改状态用指针接收者 `(u *User)` |
| `:=` 用在函数外 | 不允许,函数外只能用 `var` |
| 忽略 err | 千万别写 `result, _ := fn()` 忽略错误,养成判断习惯 |

---

## 六、推荐资料(挑 1-2 个即可,别贪多)

- **Go 官方 Tour**: go.dev/tour — 交互式,最适合入门
- **Go by Example**: gobyexample.com — 按主题查语法,当字典用
- 《Go 语言圣经》前 1-5 章 — 想系统看时翻

---

## 七、本周节奏参考

| 天 | 主题 | 核心任务 |
|---|---|---|
| Day 1 | 环境 + Hello World | 装好 Go,跑通第一个程序 |
| Day 2 | 变量/类型/函数 | 掌握多返回值和错误返回 |
| Day 3 | struct + 方法 | 理解值/指针接收者 |
| Day 4 | slice + map | 掌握增删改查遍历 |
| Day 5 | interface + error | 理解隐式实现和错误处理 |
| Day 6 | defer + Todo API | 写出可运行的 Todo API |
| Day 7 | 复盘 + 提交 | 补全 CRUD,自检,Git 提交 |
