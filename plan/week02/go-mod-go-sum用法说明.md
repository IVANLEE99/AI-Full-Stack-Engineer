# go.mod 和 go.sum 是干什么的?

> 适合前端转 Go 时理解:可以把 `go.mod` 类比成 `package.json`,把 `go.sum` 类比成 `package-lock.json` / `pnpm-lock.yaml`。

---

## 一句话总结

```text
go.mod 管:我这个项目依赖谁
go.sum 管:这些依赖是不是原来的那一份
```

也可以这样记:

```text
go.mod ≈ package.json
go.sum ≈ package-lock.json / pnpm-lock.yaml / yarn.lock
```

---

## 1. go.mod 是什么?

`go.mod` 是 Go 项目的**模块说明文件**。

它主要记录三类信息:

1. 当前项目的模块名
2. 当前项目使用的 Go 版本
3. 当前项目依赖了哪些第三方包

例如:

```go
module gin-todo

go 1.25.5

require (
    github.com/gin-gonic/gin v1.12.0
)
```

含义是:

```text
这个项目模块名叫 gin-todo
使用 Go 1.25.5
依赖 Gin v1.12.0
```

---

## 2. module 是什么?

```go
module gin-todo
```

这表示当前项目的模块名。

入门阶段你可以先简单理解为:

```text
module 就是这个 Go 项目的名字
```

如果是本地练习项目,可以写:

```go
module gin-todo
```

如果是正式开源项目,通常会写成仓库地址:

```go
module github.com/yourname/gin-todo
```

类似前端 `package.json` 里的:

```json
{
  "name": "gin-todo"
}
```

---

## 3. go 版本是什么意思?

```go
go 1.25.5
```

表示这个项目使用的 Go 语言版本。

它的作用类似告诉 Go 工具链:

```text
请按这个 Go 版本的规则来处理项目
```

---

## 4. require 是什么?

```go
require github.com/gin-gonic/gin v1.12.0
```

表示当前项目依赖了 Gin 这个第三方库。

你在代码里写了:

```go
import "github.com/gin-gonic/gin"
```

Go 就需要知道这个包从哪里下载、用哪个版本。

这些信息就记录在 `go.mod` 里。

---

## 5. indirect 是什么意思?

你可能会在 `go.mod` 里看到:

```go
require (
    github.com/gin-gonic/gin v1.12.0 // indirect
    github.com/go-playground/validator/v10 v10.30.1 // indirect
)
```

`// indirect` 表示**间接依赖**。

意思是:

```text
不是你代码直接 import 的包,
而是你依赖的包又依赖了它。
```

例如:

```text
你的项目 -> 依赖 Gin
Gin -> 又依赖 validator、json、sse 等包
```

这些 Gin 间接用到的包,就可能出现在 `go.mod` 或 `go.sum` 中。

---

## 6. go.sum 是什么?

`go.sum` 是 Go 的**依赖校验文件**。

它记录的是依赖包的版本和校验值(hash)。

例如:

```go
github.com/gin-gonic/gin v1.12.0 h1:xxxxxx
github.com/gin-gonic/gin v1.12.0/go.mod h1:yyyyyy
```

你不用手写这个文件。

Go 会自动维护它。

---

## 7. go.sum 有什么用?

`go.sum` 的核心作用是:**确认你下载到的依赖包没有被篡改**。

它解决的问题是:

```text
我今天下载的 Gin v1.12.0
和你明天下载的 Gin v1.12.0
是不是同一份内容?
```

如果校验值对不上,Go 会报错,避免你使用到被污染或被篡改的依赖。

---

## 8. go.mod 和 go.sum 的关系

可以这样理解:

| 文件 | 负责什么 | 前端类比 |
|---|---|---|
| `go.mod` | 声明项目依赖谁 | `package.json` |
| `go.sum` | 校验依赖内容是否一致 | `package-lock.json` / `pnpm-lock.yaml` |

更具体一点:

```text
go.mod:我要用 Gin v1.12.0
go.sum:我确认这个 Gin v1.12.0 的内容 hash 是 xxx
```

---

## 9. 常用命令

### 初始化 Go 项目

```bash
go mod init gin-todo
```

会生成 `go.mod`。

---

### 安装依赖

```bash
go get github.com/gin-gonic/gin
```

会更新:

```text
go.mod
go.sum
```

---

### 整理依赖

```bash
go mod tidy
```

它会做两件事:

1. 删除代码里已经不用的依赖
2. 补上代码里用到了但 `go.mod` 里缺少的依赖

这是 Go 项目里非常常用的命令。

---

### 下载依赖

```bash
go mod download
```

根据 `go.mod` 和 `go.sum` 下载依赖。

不过平时你直接运行:

```bash
go run main.go
```

Go 也会自动下载缺失依赖。

---

## 10. 要不要手动改 go.mod 和 go.sum?

### go.mod

可以偶尔看,但入门阶段不建议手动乱改。

常见操作是用命令自动更新:

```bash
go get 包名
go mod tidy
```

### go.sum

基本不要手动改。

它是 Go 自动生成和维护的校验文件。

如果 `go.sum` 出问题,通常先尝试:

```bash
go mod tidy
```

---

## 11. 要不要提交到 Git?

要。

`go.mod` 和 `go.sum` 都应该提交。

原因是别人拉你的项目后,可以根据这两个文件下载一致的依赖版本。

```text
提交 go.mod:别人知道项目依赖什么
提交 go.sum:别人能校验依赖是否一致
```

---

## 12. 和 npm 的完整类比

| 前端 npm | Go |
|---|---|
| `npm init` | `go mod init` |
| `package.json` | `go.mod` |
| `package-lock.json` | `go.sum` |
| `npm install express` | `go get github.com/gin-gonic/gin` |
| `node_modules` | Go module cache |
| `npm install` | `go mod download` / `go run` 自动下载 |

注意:Go 项目目录里一般不会出现类似 `node_modules` 的依赖目录。

Go 会把下载的依赖放到全局缓存里。

---

## 13. 在 gin-todo 项目里怎么理解?

你的 Gin 项目里有:

```text
plan/week02/gin-todo/go.mod
plan/week02/gin-todo/go.sum
```

说明这个目录已经是一个独立的 Go module。

当你执行:

```bash
go get github.com/gin-gonic/gin
```

Go 会把 Gin 以及 Gin 需要的依赖记录下来。

所以你会看到很多包,比如:

```text
github.com/gin-gonic/gin
github.com/go-playground/validator/v10
github.com/bytedance/sonic
golang.org/x/net
```

这很正常。

因为你安装一个 Gin,Go 需要同时下载 Gin 背后的依赖树。

---

## 14. 常见问题

### Q1: 为什么我只安装了 Gin,go.mod / go.sum 里出现一堆包?

因为 Gin 自己也依赖很多包。

这和前端安装一个库后,`package-lock.json` 里出现很多依赖是一样的。

---

### Q2: go.sum 很长正常吗?

正常。

`go.sum` 记录的是依赖校验值,通常会比 `go.mod` 长很多。

不用手动读完它。

---

### Q3: 可以删除 go.sum 吗?

不建议。

如果删了,Go 可能会重新生成,但团队协作时应该保留它。

---

### Q4: go.mod 里的 indirect 依赖可以删吗?

不要手动删。

如果你觉得依赖乱了,执行:

```bash
go mod tidy
```

让 Go 自动整理。

---

### Q5: `go get` 超时怎么办?

如果看到类似错误:

```bash
go: module github.com/gin-gonic/gin: Get "https://proxy.golang.org/github.com/gin-gonic/gin/@v/list": dial tcp ... i/o timeout
```

说明默认代理访问超时。

可以设置国内代理:

```bash
go env -w GOPROXY=https://goproxy.cn,direct
```

然后重新执行:

```bash
go get github.com/gin-gonic/gin
```

查看当前代理:

```bash
go env GOPROXY
```

---

## 15. 入门阶段建议

你现在只需要记住这几个操作:

```bash
# 初始化项目
go mod init 项目名

# 安装依赖
go get 包名

# 整理依赖
go mod tidy

# 运行项目
go run main.go
```

以及这句话:

```text
go.mod 和 go.sum 都不要删,都要提交。
```
