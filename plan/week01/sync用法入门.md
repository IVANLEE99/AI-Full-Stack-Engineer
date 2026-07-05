# sync 用法入门

> `sync` 是 Go 标准库自带的**并发同步包**。它提供了互斥锁、等待组等常用同步工具,让你在多个 `goroutine` 同时运行时,能安全地访问共享数据。

---

## 1. sync 是什么?

Go 天生支持并发,主要靠两个东西:

```text
goroutine: 轻量级线程,可以同时跑多段代码
channel:   通信管道,goroutine 之间传数据
```

但有时候多个 `goroutine` 会同时修改同一份数据,这就需要**同步工具**。

`sync` 包就是 Go 提供的一组基础同步工具。

---

## 2. 为什么需要 sync?

看一段会出问题的代码:

```go
package main

import (
    "fmt"
    "sync"
)

var counter int

func main() {
    var wg sync.WaitGroup

    for i := 0; i < 1000; i++ {
        wg.Add(1)
        go func() {
            counter++
            wg.Done()
        }()
    }

    wg.Wait()
    fmt.Println(counter)
}
```

你期望输出 `1000`。

但实际可能输出 `900`、`980` 等等每次都不一样。

为什么?

```text
counter++ 不是一步操作,多个 goroutine 同时操作会互相覆盖。
```

这叫**数据竞争 data race**。

解决方法之一:用 `sync.Mutex` 加锁。

---

## 3. sync.Mutex: 互斥锁

`sync.Mutex` 是最常用的同步工具。

它的 API 很简单:

```go
mu.Lock()   // 加锁
mu.Unlock() // 解锁
```

改进上面的例子:

```go
package main

import (
    "fmt"
    "sync"
)

var (
    counter int
    mu      sync.Mutex
)

func main() {
    var wg sync.WaitGroup

    for i := 0; i < 1000; i++ {
        wg.Add(1)
        go func() {
            mu.Lock()
            counter++
            mu.Unlock()
            wg.Done()
        }()
    }

    wg.Wait()
    fmt.Println(counter)
}
```

现在稳定输出 `1000`。

---

## 4. 为什么要加锁?

`counter++` 看起来只有一步,实际上大致是:

```text
1. 读 counter 当前值
2. 加 1
3. 写回 counter
```

如果不加锁,可能发生:

```text
goroutine A 读到 0
goroutine B 读到 0
goroutine A 写回 1
goroutine B 写回 1   <- 应该是 2
```

加锁后:

```text
goroutine A 加锁 -> 读 0 -> 写 1 -> 解锁
goroutine B 加锁(等 A 解锁) -> 读 1 -> 写 2 -> 解锁
```

所以加锁能保证:

```text
同一时刻只有一个 goroutine 能操作这段代码。
```

---

## 5. 为什么 Go 的 map 要加锁?

Go 的 `map` 不是并发安全的。

意思是多个 `goroutine` 同时读写同一个 map,会出错或 panic。

所以在 Tanbo API 里你会看到:

```go
var (
    todos  = make(map[int]Todo)
    nextID = 1
    mu     sync.Mutex
)
```

访问 `todos` 时要锁保护:

```go
mu.Lock()
todos[t.ID] = t
mu.Unlock()
```

原因就是:

```text
多个请求可能同时来,各自跑在一个 goroutine 里,
同时操作 map 就会出问题。
```

---

## 6. 用 defer 释放锁

也更常见的写法是:

```go
mu.Lock()
defer mu.Unlock()

todos[t.ID] = t
```

这样无论后面代码怎么 return,锁都会被释放。

更安全,不容易忘记 Unlock。

---

## 7. sync.RWMutex: 读写锁

`sync.Mutex` 是互斥锁,无论读还是写都加锁。

如果读多写少,可以用 `sync.RWMutex`:

```go
var mu sync.RWMutex

mu.RLock()   // 读锁
mu.RUnlock()

mu.Lock()    // 写锁
mu.Unlock()  // 写锁
```

读写锁的特点:

```text
多个 goroutine 可以同时读
但只要有一个 goroutine 在写,其他都不能读也不能写
```

适合**读多写少**的场景。

入门阶段如果不确定,先用 `sync.Mutex` 就够了。

---

## 8. sync.WaitGroup: 等待一组 goroutine 完成

`WaitGroup` 用来等一组 goroutine 全部跑完。

它的 3 个方法:

```go
wg.Add(1)    // 计数器 +1,表示要等一个 goroutine
wg.Done()    // 计数器 -1,表示一个 goroutine 跑完
wg.Wait()    // 阻塞,直到计数器归 0
```

示例:

```go
package main

import (
    "fmt"
    "sync"
)

func main() {
    var wg sync.WaitGroup

    for i := 1; i <= 3; i++ {
        wg.Add(1)
        go func(id int) {
            defer wg.Done()
            fmt.Println("goroutine", id, "完成")
        }(i)
    }

    wg.Wait()
    fmt.Println("所有 goroutine 跑完")
}
```

关键点:

```text
启动 goroutine 前: wg.Add(1)
goroutine 内部结尾: wg.Done() (常用 defer wg.Done())
主流程结尾: wg.Wait()
```

---

## 9. 为什么要用 WaitGroup?

`main` 函数一旦结束,所有 goroutine 也跟着结束。

例如:

```go
func main() {
    go func() {
        fmt.Println("我是 goroutine")
    }()
    // 啥也不等
}
```

可能根本来不及打印就退出了。

解决方法用 `WaitGroup`,等所有 goroutine 跑完再退出 main。

---

## 10. Add / Done / Wait 之间的关系

可以这样理解:

```text
wg.Add(1)  -> 计数器变成 5
wg.Done()  -> 计数器变成 4, 3, 2, 1
wg.Wait()  -> 阻塞,直到计数器变成 0
```

所以规律是:

```text
启动 goroutine 前 Add
goroutine 结束时 Done
主流程里 Wait
```

---

## 11. defer wg.Done() 的常见写法

很多人习惯写成:

```go
go func() {
    defer wg.Done()
    // ... 业务逻辑
}()
```

好处:

```text
不管 goroutine 内部怎么 return,或者 panic,
都尽量保证计数器会被 -1,不会一直卡住 Wait。
```

---

## 12. sync.Once: 只执行一次

`sync.Once` 用来保证某段代码只执行一次。

常用于单例、全局初始化。

```go
package main

import (
    "fmt"
    "sync"
)

var (
    once sync.Once
)

func initConfig() {
    once.Do(func() {
        fmt.Println("初始化配置")
    })
}

func main() {
    for i := 0; i < 5; i++ {
        initConfig()
    }
}
```

输出:

```text
初始化配置
```

`once.Do` 只会真正执行一次,后面调用都跳过。

---

## 13. sync.Cond: 条件变量

入门阶段不常用,先了解概念。

`sync.Cond` 用在“等某个条件成立再继续”的场景。

入门学 Web 后端基本用不到,先不纠结。

---

## 14. sync.Mutex 常见坑

### 坑 1: 忘了 Unlock

错误:

```go
mu.Lock()
todos[t.ID] = t
// 忘了 Unlock
```

会导致其他 goroutine 一直等,程序卡死。

推荐用:

```go
mu.Lock()
defer mu.Unlock()
todos[t.ID] = t
```

---

### 坑 2: Lock 两次

```go
mu.Lock()
mu.Lock() // 死锁
```

同一个 goroutine 内 `Mutex` 不能重复 Lock,会死锁。

---

### 坑 3: 没初始化

`sync.Mutex` 不需要手动初始化,直接用就行:

```go
var mu sync.Mutex // OK
```

但要注意不要把 Mutex 当成值拷贝:

```go
mu2 := mu // 危险,锁状态可能被复制
```

通常 Mutex 用指针或包级变量,不要随便复制。

---

### 坑 4: 含锁结构体别用值接收者

```go
type Counter struct {
    mu sync.Mutex
    n  int
}

func (c Counter) Inc() { // 错误,会把锁复制
    c.mu.Lock()
    c.n++
    c.mu.Unlock()
}
```

应该用指针接收者:

```go
func (c *Counter) Inc() {
    c.mu.Lock()
    defer c.mu.Unlock()
    c.n++
}
```

这也呼应你前面学的“指针接收者选取规则”。

---

## 15. 用 race 检测器检查数据竞争

Go 自带数据竞争检测工具:

```bash
go run -race main.go
```

如果代码里有数据竞争,会打印警告。

开发阶段推荐常用 `-race` 跑一下,能发现很多并发问题。

---

## 16. sync 和 channel 的选择

Go 推崇一个理念:

```text
不要通过共享内存来通信,而要通过通信来共享数据。
```

意思是:

- 优先用 channel
- 必要时用 sync

但实际项目里:

```text
处理共享状态、保护 map -> Mutex
goroutine 之间传数据 -> channel
等待 goroutine 完成 -> WaitGroup
```

入门阶段 Mutex 和 WaitGroup 用得最多。

---

## 17. 在 Todo API 里怎么理解?

你会看到:

```go
var (
    todos  = make(map[int]Todo)
    nextID = 1
    mu     sync.Mutex
)
```

每次写操作:

```go
mu.Lock()
t.ID = nextID
nextID++
todos[t.ID] = t
mu.Unlock()
```

每次读操作:

```go
mu.Lock()
list := make([]Todo, 0, len(todos))
for _, t := range todos {
    list = append(list, t)
}
mu.Unlock()
```

这里锁保护的是 `todos` 这个共享 map。

因为可能有多个 HTTP 请求同时来操作它。

---

## 18. 和 JavaScript 对比

| JavaScript | Go |
|---|---|
| 单线程 + Promise | 多个 goroutine 并发 |
| 不需要锁 | 共享数据要加锁 |
| async/await | `go func()` + `WaitGroup` |
| 没有内置锁概念 | `sync.Mutex` |

JavaScript 是单线程 + 事件循环,一般不用考虑锁。
Go 是多 goroutine,共享数据一定要上锁。

---

## 19. 入门阶段重点掌握

- `sync.Mutex`:`Lock()` / `Unlock()`,保护共享数据
- `sync.WaitGroup`:`Add(1)` / `Done()` / `Wait()`,等 goroutine 完成
- 用 `defer mu.Unlock()` 防止忘记释放
- `go run -race` 检测数据竞争
- 含锁的结构体用指针接收者

---

## 20. 记忆口诀

```text
多个 goroutine 改同一份数据: 加锁
加锁就 Unlock,用 defer 更稳
启动 goroutine 前 Add
goroutine 内部 defer Done
主流程最后 Wait
读多写少用 RWMutex
一次性初始化用 Once
想查竞态跑 -race
```
