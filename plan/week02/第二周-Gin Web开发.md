# 第二周详细学习内容: Gin Web开发

> 主题: Gin Web开发
> 目标: 用 Gin 重写 Todo API,并加上用户注册 / 登录 + JWT 鉴权,跑通完整鉴权链路。
> 原则: 本周你已经会写标准库 http 了,现在换成生产级框架。**边学边把上周的 Todo 迁过来,不要空学 API。**

---

## 一、本周核心目标

| 目标 | 说明 |
|---|---|
| 掌握 Gin 路由 | 路由注册、路由分组、路径参数、查询参数 |
| 参数绑定与校验 | `c.ShouldBindJSON` + `validator` tag 自动校验请求体 |
| 理解中间件 | 中间件执行顺序、`c.Next()`、`c.Abort()`,能对比 Express/Koa |
| 项目产出 | Gin 版 Todo API + 用户注册 / 登录 + JWT 鉴权中间件 |

---

## 二、前端视角的思维转换

你写过 Express 或 Koa,Gin 的心智模型几乎一样,先建立对照:

| 你熟悉的 Express/Koa | Gin 的做法 | 关键差异 |
|---|---|---|
| `app.get('/x', fn)` | `r.GET("/x", fn)` | 几乎一样,方法名大写 |
| `(req, res, next)` | `func(c *gin.Context)` | 请求 / 响应都挂在一个 `c` 上 |
| `res.json({...})` | `c.JSON(200, gin.H{...})` | `gin.H` 就是 `map[string]any` |
| `req.body`(需 body-parser) | `c.ShouldBindJSON(&obj)` | 直接绑定到 struct,带校验 |
| `req.params.id` | `c.Param("id")` | 路径参数 |
| `req.query.page` | `c.Query("page")` | 查询参数 |
| `app.use(mw)` | `r.Use(mw)` | 中间件注册方式一致 |
| `next()` | `c.Next()` | 放行到下一个处理器 |
| 抛错跳过后续 | `c.Abort()` | 显式中断,不再往后走 |

记住一句话:**Gin 的 `*gin.Context` 就是 Express 的 `req` + `res` + `next` 合体,一个对象贯穿整个请求。**

---

## 三、每天学习安排(7天)

### Day 1: 装 Gin,跑通第一个路由

**初始化项目**
```bash
mkdir gin-todo && cd gin-todo
go mod init gin-todo
go get github.com/gin-gonic/gin
```

**第一个 Gin 服务** `main.go`
```go
package main

import "github.com/gin-gonic/gin"

func main() {
    r := gin.Default() // 带 Logger 和 Recovery 两个默认中间件

    r.GET("/ping", func(c *gin.Context) {
        c.JSON(200, gin.H{"message": "pong"})
    })

    r.Run(":8080") // 等价于 http.ListenAndServe(":8080", r)
}
```

**运行并测试**
```bash
go run main.go
curl localhost:8080/ping   # {"message":"pong"}
```

**Day 1 理解要点**
- `gin.Default()` 自带日志和 panic 恢复,`gin.New()` 则是空的
- `gin.H` 是 `map[string]any` 的简写,专门用来拼 JSON
- `r.Run(":8080")` 内部就是标准库的 `http.ListenAndServe`

---

### Day 2: 路由分组 + 路径 / 查询参数

```go
package main

import "github.com/gin-gonic/gin"

func main() {
    r := gin.Default()

    // 路由分组:统一前缀,便于版本管理(类似 express.Router())
    api := r.Group("/api/v1")
    {
        api.GET("/todos", listTodos)
        api.GET("/todos/:id", getTodo) // 路径参数
    }

    r.Run(":8080")
}

func listTodos(c *gin.Context) {
    // 查询参数:/todos?page=2,带默认值
    page := c.DefaultQuery("page", "1")
    c.JSON(200, gin.H{"page": page})
}

func getTodo(c *gin.Context) {
    id := c.Param("id") // 取 :id
    c.JSON(200, gin.H{"id": id})
}
```

**测试**
```bash
curl "localhost:8080/api/v1/todos?page=2"   # {"page":"2"}
curl localhost:8080/api/v1/todos/42         # {"id":"42"}
```

**Day 2 理解要点**
- `r.Group("/api/v1")` 做前缀分组,后面加中间件也是按组加
- `c.Param("id")` 取路径参数,`c.Query("k")` 取查询参数
- `c.DefaultQuery` 能给查询参数设默认值,省掉判空

---

### Day 3: 参数绑定与校验(ShouldBindJSON + validator)

这是 Gin 最省事的地方:把请求体直接绑到 struct,并用 tag 自动校验。

```go
package main

import (
    "net/http"

    "github.com/gin-gonic/gin"
)

// binding tag 是校验规则,required 必填,email 校验邮箱格式
type CreateTodoReq struct {
    Title    string `json:"title" binding:"required,min=1,max=100"`
    Priority int    `json:"priority" binding:"gte=0,lte=5"`
}

func createTodo(c *gin.Context) {
    var req CreateTodoReq
    // 绑定失败(比如 title 为空)会返回 error
    if err := c.ShouldBindJSON(&req); err != nil {
        c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
        return
    }
    c.JSON(http.StatusOK, gin.H{"title": req.Title, "priority": req.Priority})
}

func main() {
    r := gin.Default()
    r.POST("/todos", createTodo)
    r.Run(":8080")
}
```

**测试**
```bash
# 正常
curl -X POST localhost:8080/todos -d '{"title":"学Gin","priority":3}'
# title 缺失,会返回 400 和校验错误
curl -X POST localhost:8080/todos -d '{"priority":3}'
```

**常用 validator tag**
```
required        必填
min=1,max=100   字符串长度 / 数字范围
gte=0,lte=5     大于等于 / 小于等于
email           邮箱格式
oneof=a b c     枚举值
```

**Day 3 理解要点**
- `binding` tag 就是校验规则,绑定和校验一步完成
- `ShouldBindJSON` 失败只返回 error,不自动写响应(推荐)
- `MustBindWith` 系列会自动返回 400,但不够灵活,少用

---

### Day 4: 中间件与执行顺序(核心)

中间件是本周最需要理解的概念,尤其执行顺序。

```go
package main

import (
    "fmt"
    "time"

    "github.com/gin-gonic/gin"
)

// 自定义日志中间件:统计耗时
func Logger() gin.HandlerFunc {
    return func(c *gin.Context) {
        start := time.Now()
        fmt.Println("进入中间件 ->")

        c.Next() // 放行:执行后续中间件和最终 handler

        // c.Next() 返回后,这里的代码才执行(类似 Koa 洋葱模型)
        fmt.Printf("<- 离开中间件,耗时 %v\n", time.Since(start))
    }
}

// 鉴权中间件示例:不通过就 Abort
func Auth() gin.HandlerFunc {
    return func(c *gin.Context) {
        token := c.GetHeader("Authorization")
        if token == "" {
            c.JSON(401, gin.H{"error": "缺少 token"})
            c.Abort() // 中断,后续 handler 不再执行
            return
        }
        c.Next()
    }
}

func main() {
    r := gin.Default()
    r.Use(Logger()) // 全局中间件

    r.GET("/public", func(c *gin.Context) {
        c.JSON(200, gin.H{"msg": "公开接口"})
    })

    // 只给这个路由加鉴权
    r.GET("/private", Auth(), func(c *gin.Context) {
        c.JSON(200, gin.H{"msg": "受保护接口"})
    })

    r.Run(":8080")
}
```

**执行顺序(洋葱模型)**
```
请求进入
  -> Logger 前半段(c.Next() 之前)
    -> Auth 检查
      -> 最终 handler
    <- Auth 之后
  <- Logger 后半段(c.Next() 之后,打印耗时)
响应返回
```

**Day 4 理解要点**
- `c.Next()` 之前的代码"进入时"执行,之后的代码"返回时"执行(洋葱模型,和 Koa 一样)
- `c.Abort()` 中断链路,后面的 handler 不再执行,但当前中间件 `c.Next()` 后的代码仍会执行
- 中间件按注册顺序进入,按相反顺序返回

---

### Day 5: JWT 基础(签发与校验)

JWT 是无状态鉴权:登录时签发一个 token,后续请求带上,服务端校验签名。

```bash
go get github.com/golang-jwt/jwt/v5
```

```go
package auth

import (
    "time"

    "github.com/golang-jwt/jwt/v5"
)

// 生产环境从环境变量读,别硬编码
var jwtSecret = []byte("your-secret-key")

// 自定义 payload
type Claims struct {
    UserID uint `json:"user_id"`
    jwt.RegisteredClaims
}

// 签发 token
func GenerateToken(userID uint) (string, error) {
    claims := Claims{
        UserID: userID,
        RegisteredClaims: jwt.RegisteredClaims{
            ExpiresAt: jwt.NewNumericDate(time.Now().Add(24 * time.Hour)),
            IssuedAt:  jwt.NewNumericDate(time.Now()),
        },
    }
    token := jwt.NewWithClaims(jwt.SigningMethodHS256, claims)
    return token.SignedString(jwtSecret)
}

// 解析并校验 token
func ParseToken(tokenStr string) (*Claims, error) {
    token, err := jwt.ParseWithClaims(tokenStr, &Claims{},
        func(t *jwt.Token) (interface{}, error) {
            return jwtSecret, nil
        })
    if err != nil {
        return nil, err
    }
    if claims, ok := token.Claims.(*Claims); ok && token.Valid {
        return claims, nil
    }
    return nil, jwt.ErrTokenInvalid
}
```

**Day 5 理解要点**
- JWT 分三段:Header.Payload.Signature,签名保证内容没被篡改
- token 里别放敏感信息(密码等),payload 是可解码的
- `ExpiresAt` 设置过期时间,`jwtSecret` 一定要保密且从配置读取

---

### Day 6-7: 实战(Gin 版 Todo + 用户鉴权)

这是本周产出。把上周的 Todo 迁到 Gin,并加上注册 / 登录 + JWT 中间件。密码用 bcrypt 存哈希。

```bash
go get golang.org/x/crypto/bcrypt
```

**JWT 鉴权中间件** `middleware/jwt.go`
```go
package middleware

import (
    "strings"

    "gin-todo/auth"
    "github.com/gin-gonic/gin"
)

func JWTAuth() gin.HandlerFunc {
    return func(c *gin.Context) {
        // 从 Header 取 "Bearer xxx"
        header := c.GetHeader("Authorization")
        parts := strings.SplitN(header, " ", 2)
        if len(parts) != 2 || parts[0] != "Bearer" {
            c.JSON(401, gin.H{"error": "未登录"})
            c.Abort()
            return
        }

        claims, err := auth.ParseToken(parts[1])
        if err != nil {
            c.JSON(401, gin.H{"error": "token 无效或已过期"})
            c.Abort()
            return
        }

        // 把用户 ID 塞进 context,后续 handler 能取
        c.Set("userID", claims.UserID)
        c.Next()
    }
}
```

**主程序** `main.go`
```go
package main

import (
    "net/http"

    "gin-todo/auth"
    "gin-todo/middleware"

    "github.com/gin-gonic/gin"
    "golang.org/x/crypto/bcrypt"
)

// 简化:用内存存用户和 Todo(下周换成数据库)
type User struct {
    ID       uint
    Username string
    Password string // 存 bcrypt 哈希
}

var users = map[string]User{}
var userID uint = 0

type RegisterReq struct {
    Username string `json:"username" binding:"required"`
    Password string `json:"password" binding:"required,min=6"`
}

func register(c *gin.Context) {
    var req RegisterReq
    if err := c.ShouldBindJSON(&req); err != nil {
        c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
        return
    }
    if _, ok := users[req.Username]; ok {
        c.JSON(http.StatusConflict, gin.H{"error": "用户名已存在"})
        return
    }
    // 密码存哈希,绝不存明文
    hash, _ := bcrypt.GenerateFromPassword([]byte(req.Password), bcrypt.DefaultCost)
    userID++
    users[req.Username] = User{ID: userID, Username: req.Username, Password: string(hash)}
    c.JSON(http.StatusOK, gin.H{"message": "注册成功"})
}

func login(c *gin.Context) {
    var req RegisterReq
    if err := c.ShouldBindJSON(&req); err != nil {
        c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
        return
    }
    u, ok := users[req.Username]
    if !ok {
        c.JSON(http.StatusUnauthorized, gin.H{"error": "用户名或密码错误"})
        return
    }
    // 校验密码:比对哈希
    if err := bcrypt.CompareHashAndPassword([]byte(u.Password), []byte(req.Password)); err != nil {
        c.JSON(http.StatusUnauthorized, gin.H{"error": "用户名或密码错误"})
        return
    }
    token, _ := auth.GenerateToken(u.ID)
    c.JSON(http.StatusOK, gin.H{"token": token})
}

func main() {
    r := gin.Default()

    // 公开路由:注册 / 登录
    r.POST("/register", register)
    r.POST("/login", login)

    // 受保护路由:整组加 JWT 中间件
    authorized := r.Group("/api")
    authorized.Use(middleware.JWTAuth())
    {
        authorized.GET("/me", func(c *gin.Context) {
            uid, _ := c.Get("userID") // 从中间件里取
            c.JSON(200, gin.H{"userID": uid})
        })
    }

    r.Run(":8080")
}
```

**测试完整链路**
```bash
# 1. 注册
curl -X POST localhost:8080/register -d '{"username":"alice","password":"123456"}'

# 2. 登录,拿到 token
curl -X POST localhost:8080/login -d '{"username":"alice","password":"123456"}'
# {"token":"eyJhbG..."}

# 3. 带 token 访问受保护接口
curl localhost:8080/api/me -H "Authorization: Bearer eyJhbG..."
# {"userID":1}

# 4. 不带 token,应返回 401
curl localhost:8080/api/me
```

进阶(有余力再做):把 Todo 的 CRUD 也迁到 Gin,并让每个 Todo 关联 `userID`,只能看自己的。

---

## 四、本周验收清单

对照打卡,能独立做到才算过关:

- [ ] 能用 Gin 注册 GET/POST 路由并返回 JSON
- [ ] 会用路由分组 `r.Group` 管理前缀
- [ ] 能用 `c.ShouldBindJSON` + `binding` tag 完成绑定和校验
- [ ] 能取路径参数 `c.Param` 和查询参数 `c.Query`
- [ ] 能手写一个中间件,并解释 `c.Next()` / `c.Abort()` 的作用
- [ ] 能画出中间件的洋葱执行顺序
- [ ] 能签发和校验 JWT token
- [ ] 密码用 bcrypt 存哈希,不存明文
- [ ] 注册 / 登录 / 鉴权完整链路能跑通
- [ ] 能解释为什么 JWT 是"无状态"鉴权

---

## 五、常见踩坑提醒

| 坑 | 说明 |
|---|---|
| 忘记 `c.Abort()` | 中间件校验失败只 return 不 Abort,后续 handler 照样执行 |
| `ShouldBind` 与 `Bind` 混淆 | `Bind` 会自动写 400 响应,`ShouldBind` 只返回 error,推荐后者 |
| 绑定字段没导出 | struct 字段首字母必须大写,否则绑不进去 |
| JWT secret 硬编码 | 一定从环境变量 / 配置读,别提交到 git |
| 存明文密码 | 必须用 bcrypt 哈希,登录时用 `CompareHashAndPassword` 比对 |
| `c.JSON` 后没 return | 写完响应要 `return`,否则继续执行下面的代码 |

---

## 六、推荐资料(挑 1-2 个即可)

- **Gin 官方文档**: gin-gonic.com/docs — 例子全,查用法当字典
- **golang-jwt/jwt 仓库 README**: github.com/golang-jwt/jwt — JWT 用法看这个
- go-playground/validator 文档 — validator tag 大全,校验规则查这里

---

## 七、本周节奏参考

| 天 | 主题 | 核心任务 |
|---|---|---|
| Day 1 | 装 Gin + 第一个路由 | 跑通 `/ping` |
| Day 2 | 路由分组 + 参数 | 掌握 Param/Query/Group |
| Day 3 | 绑定与校验 | 会用 ShouldBindJSON + tag |
| Day 4 | 中间件 | 理解洋葱模型和 Next/Abort |
| Day 5 | JWT 基础 | 会签发和校验 token |
| Day 6 | 实战:注册/登录 | bcrypt + JWT 签发 |
| Day 7 | 实战:鉴权链路 | JWT 中间件保护路由,跑通全链路 |
