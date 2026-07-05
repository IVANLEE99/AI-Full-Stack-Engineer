# fmt 用法入门

> `fmt` 是 Go 标准库里的**格式化输入输出包**。你最早写 `Hello, World!` 用的 `fmt.Println` 就是它,几乎每个 Go 程序都会用到。

---

## 1. fmt 是什么?

`fmt` 名字来自 C 语言的 `printf` / `scanf` 家族。

它主要做三件事:

| 方向 | 说明 | 常用函数 |
|---|---|---|
| 输出到终端 | 打印日志、调试 | `fmt.Println` / `fmt.Printf` |
| 拼接字符串 | 生成字符串 | `fmt.Sprintf` / `fmt.Errorf` |
| 从输入读取 | 扫描 stdin | `fmt.Scan` / `fmt.Scanf`(Web 后端很少用) |

初学期用得最多的是第一类和第二类。

---

## 2. import "fmt"

```go
import "fmt"
```

`fmt` 是标准库,不用安装,直接 import 就能用。

---

## 3. Println: 打印一行 + 换行

```go
fmt.Println("Hello, Go!")
```

输出:

```text
Hello, Go!
```

支持多个参数,中间自动加空格:

```go
fmt.Println("name:", "Alice", "age:", 18)
```

输出:

```text
name: Alice age: 18
```

末尾自带换行。

---

## 4. Print: 打印不换行

```go
fmt.Print("Hello, ")
fmt.Print("Go!")
```

输出:

```text
Hello, Go!
```

`fmt.Print` 不会自动换行,也不会自动加空格。

---

## 5. Printf: 格式化打印

`fmt.Printf` 里 `f` 表示 format。

需要在字符串里用**占位符**:

```go
fmt.Printf("name: %s, age: %d\n", "Alice", 18)
```

输出:

```text
name: Alice, age: 18
```

常用占位符:

| 占位符 | 含义 | 示例 |
|---|---|---|
| `%v` | 默认格式(最通用) | 任意值 |
| `%s` | 字符串 | `"Alice"` |
| `%d` | 整数 | `18` |
| `%f` | 浮点数 | `3.14` |
| `%t` | bool | `true` |
| `%+v` | 带字段名的 struct | `{Name:Alice}` |
| `%#v` | Go 语法表示 | `User{Name:"Alice"}` |
| `%T` | 类型 | `User` |
| `%%` | 字面 `%` | `%` |

入门阶段最常用的就是:

```text
%s 字符串
%d 整数
%v 万能
```

---

## 6. %v 和 %+v 的区别

```go
type User struct {
    Name string
    Age  int
}

u := User{Name: "Alice", Age: 18}

fmt.Printf("%v\n",  u)
fmt.Printf("%+v\n", u)
fmt.Printf("%#v\n", u)
```

输出:

```text
{Alice 18}
{Name:Alice Age:18}
main.User{Name:"Alice", Age:18}
```

记法:

```text
%v      值
%+v     带字段名
%#v     带类型和字段名
```

调试 struct 时 `%+v` 最常用。

---

## 7. Sprintf: 字符串格式化,不打印

`fmt.Sprintf` 和 `Printf` 用法一样,但**返回字符串**,不输出到终端。

```go
s := fmt.Sprintf("name: %s, age: %d", "Alice", 18)
fmt.Println(s)
```

输出:

```text
name: Alice, age: 18
```

使用场景:

```text
你想把一段文字保存为字符串,以后再决定打印或返回。
```

---

## 8. Sprintln / Sprint

```go
fmt.Sprintln("Hello", "Go")
fmt.Sprint("Hello", "Go")
```

两者也是返回字符串,不打印。

`Sprintln` 自动加空格和末尾换行。
`Sprint` 既不自动加空格,也不换行。

---

## 9. Println vs Printf vs Sprintf 速记

| 函数 | 输出到哪里 | 支持占位符 |
|---|---|---|
| `Print` | 终端,不换行 | 不支持 |
| `Println` | 终端,换行 | 不支持 |
| `Printf` | 终端,支持占位符 | 支持 |
| `Sprint` | 返回字符串 | 不支持 |
| `Sprintln` | 返回字符串 | 不支持 |
| `Sprintf` | 返回字符串 | 支持 |

也可以这样记:

```text
有 f = 支持占位符
有 ln = 自动换行
S 开头 = 返回字符串,不打印
```

---

## 10. Errorf: 生成错误

`fmt.Errorf` 用来生成 `error`:

```go
func divide(a, b int) (int, error) {
    if b == 0 {
        return 0, fmt.Errorf("除数不能为 0, 当前 b=%d", b)
    }
    return a / b, nil
}
```

```go
_, err := divide(10, 0)
fmt.Println(err)
```

输出:

```text
除数不能为 0, 当前 b=0
```

它和 `errors.New` 的区别:

```go
errors.New("除数不能为 0")              // 固定字符串
fmt.Errorf("除数为 0, b=%d", b)        // 可以格式化
```

需要拼接变量进错误消息时,用 `fmt.Errorf`。

---

## 11. Fprintln / Fprintf: 写入指定 writer

`F` 开头表示写入指定的 `io.Writer`,比如 HTTP 响应、文件。

```go
fmt.Fprintln(os.Stdout, "Hello from Fprintln")
```

在 `net/http` 里你其实见过类似写法:

```go
fmt.Fprintln(w, "Hello, Go HTTP!")
```

这里 `w http.ResponseWriter` 就是一个 writer。

`Fprintln(w, ...)` 把内容写进响应体,客户端就收到了。

---

## 12. Scan: 从标准输入读取(入门少见)

Web 后端很少用,但早期练习题可能会见到:

```go
var name string
fmt.Print("请输入名字: ")
fmt.Scan(&name)
fmt.Println("你好,", name)
```

记得传指针 `&name`,因为 Scan 要把输入写回变量。

Web 开发里一般用 `r.Body` 读 JSON,所以 `Scan` 用得不多。

---

## 13. 占位符实战示例

```go
package main

import "fmt"

type User struct {
    Name string
    Age  int
}

func main() {
    u := User{Name: "Alice", Age: 18}

    fmt.Printf("字符串: %s\n",  "Alice")
    fmt.Printf("整数:   %d\n",  18)
    fmt.Printf("浮点:   %f\n",  3.14)
    fmt.Printf("布尔:   %t\n",  true)
    fmt.Printf("默认:   %v\n",  u)
    fmt.Printf("带名:   %+v\n", u)
    fmt.Printf("语法:   %#v\n", u)
    fmt.Printf("类型:   %T\n",  u)
}
```

输出:

```text
字符串: Alice
整数:   18
浮点:   3.141500
布尔:   true
默认:   {Alice 18}
带名:   {Name:Alice Age:18}
语法:   main.User{Name:"Alice", Age:18}
类型:   main.User
```

---

## 14. 控制宽度等进阶用法(了解即可)

```go
fmt.Printf("%5d\n",  42)   // 占 5 个字符宽
fmt.Printf("%-5d|\n", 42)  // 左对齐
fmt.Printf("%.2f\n", 3.14159) // 小数保留 2 位
```

输出:

```text
   42
42   
3.14
```

入门阶段不用记,知道有这功能就行。

---

## 15. 在 Todo API 里怎么用?

### 打印启动日志

```go
fmt.Println("服务启动在 :8080")
```

---

### 打印错误

```go
if err := http.ListenAndServe(":8080", nil); err != nil {
    fmt.Println("服务启动失败:", err)
}
```

---

### 生成错误

```go
return fmt.Errorf("用户不存在, id=%d", id)
```

---

### 写入 HTTP 响应(标准库写法)

```go
fmt.Fprintln(w, "Hello, Go HTTP!")
```

返回 JSON 时更推荐用:

```go
json.NewEncoder(w).Encode(todo)
```

---

## 16. 和 JavaScript 对比

| JavaScript | Go |
|---|---|
| `console.log(...)` | `fmt.Println(...)` |
| `console.log(name, age)` | `fmt.Println("name:", name, "age:", age)` |
| 模板字符串 `` `Hi ${name}` `` | `fmt.Sprintf("Hi %s", name)` |
| `throw new Error("xx")` | `fmt.Errorf("xx")` |
| `process.stdout.write(x)` | `fmt.Fprint(os.Stdout, x)` |

最大区别:

```text
JS 用模板字符串,Go 用占位符 + Printf/Sprintf
```

---

## 17. 常见坑

### 坑 1: 用 Println 时多写了占位符

错误:

```go
fmt.Println("name: %s", "Alice")
```

会直接把 `%s` 也当成字符串输出。

正确:

```go
fmt.Printf("name: %s\n", "Alice")
```

或者:

```go
fmt.Println("name:", "Alice")
```

---

### 坑 2: Printf 忘了 \n

```go
fmt.Printf("hello")
fmt.Printf("world")
```

输出:

```text
helloworld
```

`Printf` 不像 `Println` 会自动换行,需要自己写 `\n`。

---

### 坑 3: Errorf 和 errors.New 混用

```go
errors.New("用户不存在, id=%d", id) // 报错,errors.New 不支持占位符
```

需要拼接变量就用:

```go
fmt.Errorf("用户不存在, id=%d", id)
```

---

## 18. 入门阶段重点掌握

- `fmt.Println`:打印 + 换行,最常用
- `fmt.Printf`:格式化打印
- `fmt.Sprintf`:拼接字符串
- `fmt.Errorf`:生成带格式的 error
- 占位符:`%s` `%d` `%v` `%+v` `%T`
- `Fprintln(w, ...)`:往 HTTP 响应里写

---

## 19. 记忆口诀

```text
Println 打印并换行
Print 不换行
Printf 支持占位符
S 开头返回字符串
F 开头写入指定 writer
Errorf 生成错误
%s 字符串 %d 整数 %v 万能
%+v 调试 struct 最方便
```
