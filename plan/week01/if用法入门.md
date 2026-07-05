# if 用法入门

> `if` 是 Go 里最常用的流程控制语句之一。它用来根据条件决定“是否执行某段代码”。

---

## 1. if 是什么?

`if` 的作用很简单:

```text
如果条件成立,就执行这段代码。
如果条件不成立,就跳过。
```

最基本写法:

```go
if 条件 {
    // 条件为 true 时执行
}
```

例如:

```go
age := 20

if age >= 18 {
    fmt.Println("你已经成年")
}
```

如果 `age >= 18` 成立,就会输出:

```text
你已经成年
```

---

## 2. Go 的 if 和 JS 有什么不同?

这是前端转 Go 最需要注意的一点。

### JavaScript

```js
if (age >= 18) {
  console.log("成年")
}
```

### Go

```go
if age >= 18 {
    fmt.Println("成年")
}
```

Go 和 JS 的几个区别:

| 对比项 | JavaScript | Go |
|---|---|---|
| 条件外括号 | 通常写 `()` | **不写 `()`** |
| 大括号 | 可省略一行写法(不推荐) | **必须写 `{}`** |
| 条件类型 | JS 会做真假转换 | Go **必须是 bool** |

所以在 Go 里你要记住:

```text
if 后面不要小括号
大括号必须写
条件必须是布尔值 bool
```

---

## 3. 条件必须是 bool

Go 不像 JS 那样会把很多值自动当成真假。

### JavaScript 允许

```js
if (1) {
  console.log("true")
}

if ("hello") {
  console.log("true")
}
```

### Go 不允许

错误写法:

```go
if 1 {
    fmt.Println("true")
}
```

因为 Go 要求条件必须明确是 `true` 或 `false`。

正确写法:

```go
if 1 == 1 {
    fmt.Println("true")
}
```

或者:

```go
ok := true
if ok {
    fmt.Println("true")
}
```

---

## 4. if + else

如果条件不成立,执行另一段代码,就用 `else`。

```go
age := 16

if age >= 18 {
    fmt.Println("成年")
} else {
    fmt.Println("未成年")
}
```

输出:

```text
未成年
```

结构是:

```go
if 条件 {
    // true 执行
} else {
    // false 执行
}
```

---

## 5. if + else if + else

多个条件分支时可以继续往下判断:

```go
score := 85

if score >= 90 {
    fmt.Println("优秀")
} else if score >= 60 {
    fmt.Println("及格")
} else {
    fmt.Println("不及格")
}
```

输出:

```text
及格
```

执行规则:

```text
从上往下判断
第一个成立的分支会执行
执行完就结束,后面的不再看
```

所以顺序很重要。

---

## 6. 条件判断常用运算符

### 比较运算符

| 运算符 | 含义 |
|---|---|
| `==` | 等于 |
| `!=` | 不等于 |
| `>` | 大于 |
| `<` | 小于 |
| `>=` | 大于等于 |
| `<=` | 小于等于 |

示例:

```go
if age == 18 {
    fmt.Println("刚好 18")
}

if age != 18 {
    fmt.Println("不是 18")
}
```

---

### 逻辑运算符

| 运算符 | 含义 |
|---|---|
| `&&` | 并且 |
| `||` | 或者 |
| `!` | 取反 |

示例:

```go
age := 20
hasID := true

if age >= 18 && hasID {
    fmt.Println("允许进入")
}
```

```go
if age < 18 || !hasID {
    fmt.Println("不允许进入")
}
```

---

## 7. if 里的初始化语句(Go 很有特色)

Go 的 `if` 有一个很常见的写法:

```go
if 初始化语句; 条件 {
    // ...
}
```

这是 Go 很常见、很实用的语法。

例如:

```go
if n := 10; n > 5 {
    fmt.Println("n 大于 5")
}
```

这里的意思是:

1. 先执行 `n := 10`
2. 再判断 `n > 5`
3. 如果成立就执行代码块

注意:

```text
这个 n 只在 if / else 这个小作用域里有效
外面访问不到
```

---

## 8. 最经典的 Go 写法: if err != nil

这是 Go 最重要的 if 用法之一。

```go
result, err := divide(10, 2)
if err != nil {
    fmt.Println("出错:", err)
    return
}
fmt.Println(result)
```

意思是:

```text
如果 err 不为空,说明出错了
那就先处理错误,然后 return
```

这就是 Go 里最常见的错误处理模式。

你以后会看到很多这样的代码:

```go
if err != nil {
    return err
}
```

或者:

```go
if err != nil {
    fmt.Println(err)
    return
}
```

---

## 9. if err := ...; err != nil 写法

这是 Go 里最经典、最 idiomatic 的写法之一。

```go
if err := json.NewDecoder(r.Body).Decode(&todo); err != nil {
    http.Error(w, "参数错误", http.StatusBadRequest)
    return
}
```

拆开理解就是:

```go
err := json.NewDecoder(r.Body).Decode(&todo)
if err != nil {
    http.Error(w, "参数错误", http.StatusBadRequest)
    return
}
```

只是 Go 把它缩成了一行。

好处是:

- `err` 作用域更小
- 代码更紧凑
- 不会污染外层变量

所以在 Go 里非常常见。

---

## 10. return early: 先处理异常情况

Go 很推崇一种写法:

```text
先判断异常情况
出错就早点 return
正常逻辑放后面
```

例如:

```go
func divide(a, b int) (int, error) {
    if b == 0 {
        return 0, fmt.Errorf("除数不能为 0")
    }

    return a / b, nil
}
```

这比下面这种更常见:

```go
func divide(a, b int) (int, error) {
    if b != 0 {
        return a / b, nil
    } else {
        return 0, fmt.Errorf("除数不能为 0")
    }
}
```

因为 Go 更喜欢:

- 少嵌套
- 先处理错误
- 主路径更清晰

---

## 11. 嵌套 if

当然也可以写嵌套:

```go
age := 20
hasID := true

if age >= 18 {
    if hasID {
        fmt.Println("允许进入")
    }
}
```

但 Go 风格里一般不鼓励嵌套太深。

更常见会改成:

```go
if age < 18 {
    fmt.Println("未成年")
    return
}

if !hasID {
    fmt.Println("没有证件")
    return
}

fmt.Println("允许进入")
```

这种写法更清楚。

---

## 12. if 和短变量声明作用域

看这个例子:

```go
if x := 10; x > 5 {
    fmt.Println(x)
}

// 这里不能再用 x
```

为什么?

因为 `x` 只在 `if` 语句的作用域里存在。

同样:

```go
if err := doSomething(); err != nil {
    fmt.Println(err)
}

// 这里不能再用 err
```

这也是为什么这种写法更整洁,不会把变量带到外面。

---

## 13. if 判断字符串

```go
name := "Alice"

if name == "Alice" {
    fmt.Println("你好 Alice")
}
```

字符串比较就是直接用 `==` / `!=`。

Go 字符串比较是值比较,和 JS 里字符串比较类似。

---

## 14. if 判断 nil

Go 里会经常判断 `nil`。

例如:

```go
var p *int

if p == nil {
    fmt.Println("指针为空")
}
```

判断 error 也是同理:

```go
if err != nil {
    fmt.Println("出错了")
}
```

记住:

```text
error 本质上也经常通过 nil / 非 nil 来判断有没有错误
```

---

## 15. if 判断 map 中 key 是否存在

```go
m := map[string]int{
    "a": 1,
}

if v, ok := m["a"]; ok {
    fmt.Println("找到了:", v)
}
```

这里:

- `v` 是值
- `ok` 是这个 key 是否存在

如果 key 不存在:

```go
if _, ok := m["b"]; !ok {
    fmt.Println("没找到")
}
```

这也是 Go 非常常见的 if 写法。

---

## 16. if 判断类型断言结果

后面学 interface 时你会遇到这种写法:

```go
var x interface{} = "hello"

if s, ok := x.(string); ok {
    fmt.Println("字符串:", s)
}
```

意思是:

```text
如果 x 真的可以断言成 string
那就把结果放到 s 里继续用
```

这也是 Go 里典型的“if 初始化 + 条件判断”模式。

---

## 17. 在 HTTP 代码里 if 最常见的几个场景

### 1) 判断请求方法

```go
if r.Method != http.MethodPost {
    http.Error(w, "只支持 POST", http.StatusMethodNotAllowed)
    return
}
```

### 2) 判断 JSON 解析是否失败

```go
if err := json.NewDecoder(r.Body).Decode(&todo); err != nil {
    http.Error(w, "参数错误", http.StatusBadRequest)
    return
}
```

### 3) 判断资源是否存在

```go
todo, ok := todos[id]
if !ok {
    http.Error(w, "未找到", http.StatusNotFound)
    return
}
```

这些写法你以后会天天见。

---

## 18. 和 switch 怎么选?

如果只是二选一或少量条件,`if` 很自然:

```go
if age >= 18 {
    fmt.Println("成年")
} else {
    fmt.Println("未成年")
}
```

如果是多个并列条件,有时 `switch` 更清楚:

```go
switch score {
case 100:
    fmt.Println("满分")
case 60:
    fmt.Println("及格")
default:
    fmt.Println("其他分数")
}
```

简单说:

```text
少量分支 -> if
多个并列分支 -> 有时 switch 更清晰
```

---

## 19. 常见错误

### 错误 1: 写小括号

错误:

```go
if (age >= 18) {
    fmt.Println("成年")
}
```

虽然某些场景可能被接受? 但 Go 风格里**不要写**。

正确:

```go
if age >= 18 {
    fmt.Println("成年")
}
```

---

### 错误 2: 条件不是 bool

错误:

```go
if 1 {
    fmt.Println("true")
}
```

正确:

```go
if 1 == 1 {
    fmt.Println("true")
}
```

---

### 错误 3: 忘记大括号

错误:

```go
if age >= 18
    fmt.Println("成年")
```

Go 必须写 `{}`。

---

### 错误 4: 嵌套太深

错误思路:

```go
if a {
    if b {
        if c {
            // ...
        }
    }
}
```

更推荐:

```go
if !a {
    return
}
if !b {
    return
}
if !c {
    return
}
```

---

## 20. 和 JavaScript 对照

| JavaScript | Go |
|---|---|
| `if (cond) {}` | `if cond {}` |
| 可做 truthy / falsy 判断 | 条件必须是 bool |
| 常配合 throw | 常配合 `if err != nil` |
| 也能提前 return | Go 更强调 early return |

对前端出身的人,最重要的思维转换就是:

```text
JS 常依赖真假值转换
Go 要求条件明确为 bool
```

以及:

```text
Go 遇错先 if err != nil return
这是一种主流编码风格,不是啰嗦
```

---

## 21. 入门阶段最该掌握的 6 种 if 写法

### 1. 普通条件

```go
if age >= 18 {
    fmt.Println("成年")
}
```

### 2. if / else

```go
if age >= 18 {
    fmt.Println("成年")
} else {
    fmt.Println("未成年")
}
```

### 3. if / else if / else

```go
if score >= 90 {
    fmt.Println("优秀")
} else if score >= 60 {
    fmt.Println("及格")
} else {
    fmt.Println("不及格")
}
```

### 4. if err != nil

```go
if err != nil {
    return err
}
```

### 5. if 初始化; 条件

```go
if err := doSomething(); err != nil {
    return err
}
```

### 6. if v, ok := map[key]; ok

```go
if v, ok := m["a"]; ok {
    fmt.Println(v)
}
```

---

## 22. 记忆口诀

```text
if 后不加小括号
条件必须是 bool
大括号一定要写
else if 继续分支
错误处理看 err != nil
初始化语句放分号前
先处理异常,早点 return
少嵌套,主流程更清楚
```
