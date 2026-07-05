# switch 用法入门

> `switch` 是 Go 里非常常用的流程控制语句。它适合处理**多个并列分支判断**，写出来通常比一长串 `if / else if / else` 更清晰。

---

## 1. switch 是什么?

`switch` 的作用是:

```text
根据不同条件,执行不同分支。
```

最常见写法:

```go
switch 表达式 {
case 值1:
    // 表达式 == 值1 时执行
case 值2:
    // 表达式 == 值2 时执行
default:
    // 都不匹配时执行
}
```

例如:

```go
day := 2

switch day {
case 1:
    fmt.Println("周一")
case 2:
    fmt.Println("周二")
default:
    fmt.Println("其他")
}
```

输出:

```text
周二
```

---

## 2. switch 和 if 的关系

很多 `switch` 都可以用 `if / else if / else` 改写。

例如:

```go
score := 90

if score == 100 {
    fmt.Println("满分")
} else if score == 90 {
    fmt.Println("90 分")
} else {
    fmt.Println("其他")
}
```

可以写成:

```go
switch score {
case 100:
    fmt.Println("满分")
case 90:
    fmt.Println("90 分")
default:
    fmt.Println("其他")
}
```

所以你可以这样理解:

```text
少量条件、复杂逻辑 -> 常用 if
多个并列值判断 -> 常用 switch
```

---

## 3. Go 的 switch 比 JS 更省事

### JavaScript

```js
switch (day) {
  case 1:
    console.log("周一")
    break
  case 2:
    console.log("周二")
    break
  default:
    console.log("其他")
}
```

### Go

```go
switch day {
case 1:
    fmt.Println("周一")
case 2:
    fmt.Println("周二")
default:
    fmt.Println("其他")
}
```

最大的区别:

| 对比项 | JavaScript | Go |
|---|---|---|
| 需要 `break` | 通常需要 | **默认自动 break** |
| 小括号 | 常写 `switch(x)` | **不写也行** |
| case 可写表达式 | 有限支持 | 支持更灵活 |

Go 默认**命中一个分支就结束**，不用像 JS 那样老写 `break`。

这是 Go 的 `switch` 很舒服的地方。

---

## 4. 最基础的值匹配 switch

```go
status := "success"

switch status {
case "success":
    fmt.Println("成功")
case "fail":
    fmt.Println("失败")
default:
    fmt.Println("未知状态")
}
```

这里就是拿 `status` 和每个 `case` 做比较。

如果匹配成功,执行对应分支。

---

## 5. default 分支

`default` 表示:

```text
上面所有 case 都不匹配时,执行这里。
```

例如:

```go
level := "vip"

switch level {
case "admin":
    fmt.Println("管理员")
case "user":
    fmt.Println("普通用户")
default:
    fmt.Println("未知身份")
}
```

输出:

```text
未知身份
```

`default` 不是必须写,但很多时候建议保留。

---

## 6. 一个 case 匹配多个值

Go 支持一个 `case` 写多个值,用逗号分开:

```go
score := 90

switch score {
case 90, 91, 92:
    fmt.Println("优秀")
case 60, 61, 62:
    fmt.Println("及格")
default:
    fmt.Println("其他")
}
```

这比写很多 `if` 更清晰。

---

## 7. switch 不一定只判断“等于”

Go 的 `switch` 很灵活,可以不写表达式。

例如:

```go
score := 85

switch {
case score >= 90:
    fmt.Println("优秀")
case score >= 60:
    fmt.Println("及格")
default:
    fmt.Println("不及格")
}
```

这其实很像:

```go
if score >= 90 {
    ...
} else if score >= 60 {
    ...
} else {
    ...
}
```

也就是说:

```text
switch 后面不写表达式时,
每个 case 后面写的是一个布尔条件。
```

这个写法在 Go 里也很常见。

---

## 8. switch true 的理解

为什么上面那个 `switch` 能运行?

因为它本质上等价于:

```go
switch true {
case score >= 90:
    fmt.Println("优秀")
case score >= 60:
    fmt.Println("及格")
default:
    fmt.Println("不及格")
}
```

Go 会从上到下找第一个值为 `true` 的 case。

所以这种写法非常适合:

- 范围判断
- 多个条件判断
- 替代很长的 `if / else if`

---

## 9. case 的匹配顺序很重要

看这个例子:

```go
score := 95

switch {
case score >= 60:
    fmt.Println("及格")
case score >= 90:
    fmt.Println("优秀")
}
```

输出会是:

```text
及格
```

为什么不是“优秀”? 

因为 `switch` 是**从上往下匹配**,命中第一个就结束。

所以正确顺序应该是:

```go
switch {
case score >= 90:
    fmt.Println("优秀")
case score >= 60:
    fmt.Println("及格")
default:
    fmt.Println("不及格")
}
```

规则和 `if / else if` 一样:

```text
把更严格、更特殊的条件写在前面。
```

---

## 10. switch 里的初始化语句

和 `if` 一样,`switch` 也可以带初始化语句:

```go
switch n := 10; {
case n > 5:
    fmt.Println("n > 5")
default:
    fmt.Println("其他")
}
```

也可以这样:

```go
switch day := 2; day {
case 1:
    fmt.Println("周一")
case 2:
    fmt.Println("周二")
}
```

注意:

```text
这里声明的变量 day / n 只在 switch 这个作用域里有效。
```

---

## 11. switch 和类型判断

后面学 `interface` 时你会遇到 **type switch**。

例如:

```go
func printType(x interface{}) {
    switch v := x.(type) {
    case int:
        fmt.Println("int:", v)
    case string:
        fmt.Println("string:", v)
    default:
        fmt.Println("unknown")
    }
}
```

这表示:

```text
根据 interface{} 里面装的真实类型,
走不同分支。
```

这是 Go 很有特色的一种 `switch` 用法。

入门阶段先知道有这个东西就行。

---

## 12. Go 的 switch 默认自动 break

这个点一定要和 JS 区分开。

### Go

```go
n := 2

switch n {
case 1:
    fmt.Println("one")
case 2:
    fmt.Println("two")
case 3:
    fmt.Println("three")
}
```

输出:

```text
two
```

不会继续执行 `case 3`。

因为 Go 的 `switch` 默认就是:

```text
命中一个 case -> 执行 -> 结束
```

---

## 13. fallthrough: 强制继续执行下一个 case

如果你真的想让它继续执行下一个 case,Go 提供 `fallthrough`:

```go
n := 1

switch n {
case 1:
    fmt.Println("one")
    fallthrough
case 2:
    fmt.Println("two")
default:
    fmt.Println("other")
}
```

输出:

```text
one
two
```

注意:

```text
fallthrough 不会重新判断下一个 case 条件,
而是直接进入下一个分支执行。
```

所以它要谨慎用。

Go 项目里其实不常滥用 `fallthrough`。

---

## 14. break 在 switch 里怎么用?

虽然 Go 默认会结束,但你仍然可以显式写 `break`。

不过大多数时候没必要。

例如:

```go
switch n {
case 1:
    fmt.Println("one")
    break
}
```

这通常是多余的。

---

## 15. switch 和 if 到底怎么选?

### 更适合 if 的场景

- 条件比较复杂
- 需要处理错误 `if err != nil`
- 需要 early return
- 逻辑不是并列分支

例如:

```go
if err != nil {
    return err
}
```

### 更适合 switch 的场景

- 多个并列值判断
- 多个范围判断
- 根据状态码、方法、类型走分支
- 想让代码更整齐

例如:

```go
switch r.Method {
case http.MethodGet:
    ...
case http.MethodPost:
    ...
default:
    ...
}
```

---

## 16. 在 HTTP 代码里 switch 很常见

这是你最常见的场景之一:

```go
func todosHandler(w http.ResponseWriter, r *http.Request) {
    switch r.Method {
    case http.MethodGet:
        fmt.Println("查询列表")
    case http.MethodPost:
        fmt.Println("创建 Todo")
    default:
        http.Error(w, "方法不支持", http.StatusMethodNotAllowed)
    }
}
```

意思是:

| 请求方法 | 分支 |
|---|---|
| `GET` | 查询 |
| `POST` | 创建 |
| 其他 | 返回 405 |

这是 Go Web 代码里非常标准的写法。

---

## 17. switch 和枚举/状态值搭配很好用

虽然 Go 没有像 TS 那样的 enum 语法,但常常用常量配合 `switch`:

```go
const (
    StatusPending = "pending"
    StatusDone    = "done"
    StatusFailed  = "failed"
)

status := StatusDone

switch status {
case StatusPending:
    fmt.Println("处理中")
case StatusDone:
    fmt.Println("已完成")
case StatusFailed:
    fmt.Println("失败")
default:
    fmt.Println("未知状态")
}
```

---

## 18. 常见错误

### 错误 1: 以为要写 break

从 JS 转过来的人最容易多写 `break`。

虽然不一定报错,但很多时候没有必要。

Go 默认自动结束,不用老想着补 `break`。

---

### 错误 2: case 顺序写反

错误:

```go
switch {
case score >= 60:
    fmt.Println("及格")
case score >= 90:
    fmt.Println("优秀")
}
```

`95` 会先命中 `>= 60`。

所以范围判断时要把严格条件放前面。

---

### 错误 3: fallthrough 用错

很多人会以为 `fallthrough` 会继续判断下一个 case。

其实不是。

它是:

```text
直接执行下一个 case 的代码,不再判断条件。
```

所以不能乱用。

---

### 错误 4: 忘了 default

不是所有情况都必须有 `default`,但很多业务逻辑里最好有。

尤其是:

- 状态机
- HTTP 方法判断
- 类型判断
- 枚举值判断

这样更稳。

---

## 19. 和 JavaScript 对照

| JavaScript | Go |
|---|---|
| `switch(x) { case 1: ... break }` | `switch x { case 1: ... }` |
| 默认会贯穿,要手动 `break` | 默认不会贯穿 |
| `fallthrough` 逻辑常见但危险 | 需要显式 `fallthrough` |
| 条件判断多用 `if` 或 `switch` | Go 里 `switch {}` 很适合范围判断 |

前端转 Go 最重要的转换是:

```text
Go 的 switch 默认自动结束
不要带着 JS 的 break 焦虑
```

---

## 20. 入门阶段最该掌握的 5 种 switch 写法

### 1. 值匹配

```go
switch day {
case 1:
    fmt.Println("周一")
case 2:
    fmt.Println("周二")
default:
    fmt.Println("其他")
}
```

### 2. 一个 case 多个值

```go
switch score {
case 90, 91, 92:
    fmt.Println("优秀")
}
```

### 3. 条件 switch

```go
switch {
case score >= 90:
    fmt.Println("优秀")
case score >= 60:
    fmt.Println("及格")
default:
    fmt.Println("不及格")
}
```

### 4. 判断 HTTP 方法

```go
switch r.Method {
case http.MethodGet:
    // 查询
case http.MethodPost:
    // 创建
default:
    http.Error(w, "方法不支持", http.StatusMethodNotAllowed)
}
```

### 5. type switch

```go
switch v := x.(type) {
case string:
    fmt.Println(v)
case int:
    fmt.Println(v)
}
```

---

## 21. 记忆口诀

```text
多个并列分支用 switch
默认命中一个就结束
Go 不需要老写 break
多个值可写一个 case
范围判断可用 switch {}
严格条件写前面
fallthrough 会直接进下一个分支
HTTP 方法分发很适合 switch
```
