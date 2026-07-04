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

**补充:Go 和 JavaScript 的方法调用有什么区别?**

前端转 Go 的时候,这里特别容易混。你可以直接记住一句话:

> **Go 的方法本质上是"函数 + 接收者",JavaScript 的方法本质上是"对象属性 + this 绑定"。**

#### 1. 接收者 vs this

**Go:方法有显式接收者**
```go
type User struct {
    Name string
}

func (u User) GetName() string {
    return u.Name
}

func (u *User) SetName(name string) {
    u.Name = name
}
```
- `(u User)`、`(u *User)` 叫**接收者**
- Go 在定义方法时就明确这个方法属于谁
- 值接收者和指针接收者行为不同

**JavaScript:方法依赖 this**
```js
class User {
    constructor(name) {
        this.name = name
    }

    getName() {
        return this.name
    }

    setName(name) {
        this.name = name
    }
}
```
- JS 方法里常用 `this`
- `this` 指向谁,取决于**调用方式**,不是定义位置

#### 2. Go 要区分值接收者和指针接收者,JS 默认就能改对象

**Go**
```go
type User struct {
    Name string
}

func (u User) ChangeNameFail(name string) {
    u.Name = name  // 改的是副本
}

func (u *User) ChangeNameOK(name string) {
    u.Name = name  // 改的是原对象
}
```

```go
u := User{Name: "Alice"}
u.ChangeNameFail("Bob")
fmt.Println(u.Name) // Alice

u.ChangeNameOK("Bob")
fmt.Println(u.Name) // Bob
```

- 值接收者拿到的是副本
- 指针接收者才能真正修改原对象

**JavaScript**
```js
class User {
    changeName(name) {
        this.name = name
    }
}
```
- JS 对象本身就是引用语义的常见使用方式
- 所以方法里改 `this.name`,通常就是直接改原对象

#### 3. Go 的方法定义在结构体外面,JS 的方法通常写在类里面

**Go**
```go
type User struct {
    Name string
}

func (u User) Greet() string {
    return "Hi, " + u.Name
}
```

**JavaScript**
```js
class User {
    constructor(name) {
        this.name = name
    }

    greet() {
        return `Hi, ${this.name}`
    }
}
```

- Go 的 `struct` 和方法是分开定义的
- JS 的 `class` 通常把属性和方法写在一起

#### 4. JavaScript 会有 this 丢失问题,Go 没这个坑

**JavaScript**
```js
const u = new User("Alice")
const greet = u.greet
// greet() 这里可能报错,因为 this 丢了
```

要么手动绑定:
```js
const greet = u.greet.bind(u)
```

**Go**
```go
u := User{Name: "Alice"}
greet := u.Greet
greet() // 可以正常调用
```

- Go 不存在 JS 那种经典的 `this` 丢失问题
- 因为接收者在方法调用这件事上更稳定、更明确

#### 5. nil 接收者 vs null/undefined

**Go**
```go
func (u *User) SafeName() string {
    if u == nil {
        return "unknown"
    }
    return u.Name
}
```

```go
var u *User
fmt.Println(u.SafeName()) // unknown
```

- Go 允许你给 `nil` 指针调用方法
- 但前提是方法内部自己处理 `nil`

**JavaScript**
```js
let u = null
u.getName() // 直接报错
```

- JS 里 `null` / `undefined` 调方法会直接出错

#### 6. 最后用一张表记住

| 对比项 | Go | JavaScript |
|---|---|---|
| 方法依赖什么 | 接收者 | `this` |
| 绑定方式 | 定义时明确 | 调用时决定 |
| 修改原对象 | 通常用指针接收者 | 直接改 `this` |
| 方法定义位置 | struct 外 | class 内 |
| 是否有 this 丢失问题 | 没有 | 有 |
| nil/null 调用方法 | 可设计为安全 | 通常直接报错 |

**记忆口诀**
- Go: **方法 = 函数 + 接收者**
- JS: **方法 = 对象属性 + this**

如果你是前端出身,最值得先建立的直觉就是:
- 在 Go 里,**想修改原对象,优先想到指针接收者**
- 在 JS 里,**想避免 this 出问题,就注意调用方式或用箭头函数 / bind**

#### 7. 值接收者和指针接收者到底怎么选?

这是 Go 初学者最常问的问题。你不用一开始背太多规则,先记住这个最实用版本:

> **不需要改数据时,优先考虑值接收者;需要改数据时,用指针接收者。**

然后再补 3 条判断规则。

**规则 1: 需要修改结构体内容 -> 用指针接收者**
```go
type User struct {
    Name string
}

func (u *User) Rename(name string) {
    u.Name = name
}
```

因为你要改的是原对象,不是副本。

**规则 2: 结构体比较大 -> 通常用指针接收者**
```go
type BigData struct {
    A [1024]int
}

func (b *BigData) Process() {
    // 避免每次调用都复制一大份数据
}
```

因为值接收者会拷贝一份结构体,数据很大时会有额外开销。

**规则 3: 如果一个类型有方法用了指针接收者,通常整个类型的方法都保持一致**
```go
type Counter struct {
    N int
}

func (c *Counter) Inc() {
    c.N++
}

func (c *Counter) Value() int {
    return c.N
}
```

虽然 `Value()` 看起来不修改数据,理论上可以写成值接收者,但实际开发里常常仍然统一写成指针接收者。

这样做的好处:
- 风格一致
- 避免混淆
- 方便后续扩展

#### 8. 一个简单判断表

| 场景 | 推荐 |
|---|---|
| 方法需要修改对象状态 | 指针接收者 |
| 结构体很大,不想拷贝 | 指针接收者 |
| 结构体里有 `sync.Mutex` 这类不能随便复制的字段 | 指针接收者 |
| 只是读数据,结构体很小,语义上像副本 | 值接收者 |
| 同一个类型已经大多用指针接收者 | 继续统一用指针接收者 |

#### 9. 特别提醒: 含锁的结构体不要用值接收者

比如:
```go
type Counter struct {
    mu sync.Mutex
    n  int
}
```

这种类型如果你用值接收者,会把锁也一起复制,这是很危险的。

所以这类结构体的方法一般都写成:
```go
func (c *Counter) Inc() {
    c.mu.Lock()
    defer c.mu.Unlock()
    c.n++
}
```

#### 10. 入门阶段的实用建议

如果你现在还拿不准,可以先这样用:
- **结构体方法默认优先考虑指针接收者**
- 只有在你明确知道"这个方法不改数据 + 结构体很小 + 值语义更合理"时,再用值接收者

这样对初学者最稳,也更接近很多真实项目里的写法。

---

### Day 6:defer + 项目实战(Todo API)

**defer(延迟执行,常用于资源清理)**

#### 1. 基本概念
`defer` 用于延迟执行函数调用,延迟的函数会在外层函数返回之前执行,无论函数正常返回还是 panic。

```go
func deferExample() {
    fmt.Println("开始执行")
    defer fmt.Println("defer 语句执行")  // 最后执行
    fmt.Println("函数执行完毕")
}
// 输出顺序: 开始执行 -> 函数执行完毕 -> defer 语句执行
```

#### 2. 执行顺序(后进先出 LIFO)
多个 defer 语句按照**后进先出**的顺序执行:

```go
defer fmt.Println("第一个 defer")
defer fmt.Println("第二个 defer")
defer fmt.Println("第三个 defer")
// 执行顺序: 第三个 -> 第二个 -> 第一个
```

#### 3. 常见应用场景

**文件操作(资源清理)**
```go
func readFile() {
    file, err := os.Open("file.txt")
    if err != nil {
        return
    }
    defer file.Close()   // 确保文件一定会关闭

    // ... 处理文件
}
```

**互斥锁(确保释放)**
```go
func updateData() {
    mu.Lock()
    defer mu.Unlock()   // 确保锁一定会释放

    // ... 修改数据
}
```

**错误恢复(panic/recover)**
```go
func safeCall() {
    defer func() {
        if r := recover(); r != nil {
            fmt.Println("从 panic 中恢复:", r)
        }
    }()

    // ... 可能 panic 的代码
}
```

**性能测量**
```go
func measureTime() {
    start := time.Now()
    defer func() {
        fmt.Printf("执行时间: %v\n", time.Since(start))
    }()

    // ... 业务逻辑
}
```

#### 4. 变量求值时机(重要)

```go
// 普通 defer: 参数在 defer 语句处立即求值
x := 1
defer fmt.Println("x =", x)  // 输出 1,不是 2
x = 2

// 匿名函数 defer: 变量在函数返回时求值
y := 1
defer func() {
    fmt.Println("y =", y)  // 输出 2
}()
y = 2
```

#### 5. 注意事项
- defer 会增加少量性能开销,在性能敏感的循环中要谨慎使用
- defer 的参数在 defer 语句处立即求值
- 匿名函数中的变量会在函数返回时求值
- defer 常用于确保资源一定被释放,防止资源泄漏

**Day 6-7 实战:Todo API**

这是本周的产出。用标准库 `net/http` 做,先不引 Gin(第2周再学)。

#### 先看完整代码
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

#### 逐段解释

**1. Todo 结构体**
```go
type Todo struct {
    ID    int    `json:"id"`
    Title string `json:"title"`
    Done  bool   `json:"done"`
}
```
- 这是 Todo 数据的结构定义
- `ID` 是唯一标识
- `Title` 是待办内容
- `Done` 表示是否完成
- `` `json:"id"` `` 这种写法叫 **JSON 标签**,表示结构体转成 JSON 时字段名叫什么

例如这个结构体会被编码成:
```json
{"id":1,"title":"学Go","done":false}
```

**2. 内存存储**
```go
var (
    todos  = make(map[int]Todo)
    nextID = 1
    mu     sync.Mutex
)
```
- `todos` 是一个 `map[int]Todo`,键是 ID,值是 Todo
- `nextID` 用来模拟自增主键,每创建一个 Todo 就加 1
- `mu` 是互斥锁,因为 Go 的 map 不能被多个请求同时安全读写,否则可能报错

**3. 处理函数入口**
```go
func todosHandler(w http.ResponseWriter, r *http.Request) {
```
- `w` 用来写响应给客户端
- `r` 表示客户端发过来的请求
- 这个函数负责处理 `/todos` 路由的所有请求

**4. 设置响应格式**
```go
w.Header().Set("Content-Type", "application/json")
```
- 告诉客户端:我返回的是 JSON 数据
- 前后端联调时这是很常见的一步

**5. 用请求方法区分功能**
```go
switch r.Method {
```
这里根据 HTTP 方法走不同逻辑:
- `GET /todos` -> 查询所有 Todo
- `POST /todos` -> 创建一个 Todo
- 其他方法 -> 返回不支持

**6. GET:查询列表**
```go
case http.MethodGet:
    mu.Lock()
    list := make([]Todo, 0, len(todos))
    for _, t := range todos {
        list = append(list, t)
    }
    mu.Unlock()
    json.NewEncoder(w).Encode(list)
```
这一段做了 4 件事:

- `mu.Lock()`：加锁,防止别的请求同时修改 map
- `make([]Todo, 0, len(todos))`：创建一个切片,准备装所有 Todo
- `for _, t := range todos`：遍历 map,把每个 Todo 放进切片
- `json.NewEncoder(w).Encode(list)`：把切片转成 JSON 返回给客户端

为什么不直接返回 map?
- 因为接口通常更希望返回数组列表
- 切片也更符合前端接收列表数据的习惯

**7. POST:创建 Todo**
```go
case http.MethodPost:
    var t Todo
    if err := json.NewDecoder(r.Body).Decode(&t); err != nil {
        http.Error(w, "参数错误", http.StatusBadRequest)
        return
    }
```
这一步是把客户端传来的 JSON 请求体解析到 `t` 里。

例如前端传:
```json
{"title":"学Go","done":false}
```
解析后就会变成一个 Go 结构体。

如果 JSON 格式错误,就会进入:
```go
http.Error(w, "参数错误", http.StatusBadRequest)
return
```
- 返回 400 状态码
- 提前结束函数

接着是保存数据:
```go
mu.Lock()
t.ID = nextID
nextID++
todos[t.ID] = t
mu.Unlock()
json.NewEncoder(w).Encode(t)
```
意思是:
- 加锁
- 给新 Todo 分配 ID
- `nextID` 自增,留给下一个 Todo 用
- 把 Todo 放进 map
- 解锁
- 把新创建的数据原样返回给客户端

**8. default:不支持的方法**
```go
default:
    http.Error(w, "方法不支持", http.StatusMethodNotAllowed)
```
如果用户发的是 `PUT`、`DELETE` 等当前还没实现的方法,就返回 405。

**9. main 函数:启动 HTTP 服务**
```go
func main() {
    http.HandleFunc("/todos", todosHandler)
    fmt.Println("服务启动在 :8080")
    http.ListenAndServe(":8080", nil)
}
```
- `http.HandleFunc("/todos", todosHandler)`：把 `/todos` 路径绑定到这个处理函数
- `fmt.Println("服务启动在 :8080")`：打印启动日志
- `http.ListenAndServe(":8080", nil)`：启动 Web 服务,监听本机 8080 端口

启动后你就可以访问:
- `GET http://localhost:8080/todos`
- `POST http://localhost:8080/todos`

#### 这段代码你要重点学会什么
- `struct` 如何表示接口返回的数据结构
- `map` 如何临时充当内存数据库
- `sync.Mutex` 为什么能保护共享数据
- `http.HandleFunc` 如何注册路由
- `r.Method` 如何区分不同请求
- `json.NewDecoder` / `json.NewEncoder` 如何处理 JSON
- `if err != nil` 如何做 Go 风格错误处理

#### 这段代码的局限
这个版本是入门版,有几个明显限制:
- 数据只存在内存里,程序一重启就丢了
- 只支持列表和创建,还不支持按 ID 查询、更新、删除
- `mu.Lock()` / `mu.Unlock()` 可以进一步改成 `defer mu.Unlock()` 让代码更安全
- `http.ListenAndServe` 的错误没有处理,更严谨的写法应该判断返回值

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
