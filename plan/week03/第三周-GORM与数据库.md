# 第三周详细学习内容:GORM 与数据库

> 主题:GORM / 数据库(第一个小项目正式启动)
> 目标:把上周内存版的 Todo / 用户数据落到真正的数据库,完成 Go + DB 联调。
> 原则:数据是后端的核心。**先用 Docker 起一个本地库,再一边写模型一边看表结构变化。**

---

## 一、本周核心目标

| 目标 | 说明 |
|---|---|
| 起本地数据库 | 用 Docker 起 MySQL / PostgreSQL,不污染本机 |
| GORM 模型定义 | 用 struct + tag 定义模型,`AutoMigrate` 自动建表 |
| 掌握 CRUD | 增删改查的 GORM 写法,替代手写 SQL |
| 关联与预加载 | 一对多关联、`Preload` 加载关联数据、软删除 |
| 项目产出 | 把 Todo / 商品数据真正存进数据库,接口读写数据库 |

---

## 二、前端视角的思维转换

你在前端可能只调过接口,没直接碰过数据库。先建立这几个认知:

| 你可能的印象 | 后端 / GORM 的实际做法 | 关键差异 |
|---|---|---|
| 数据存在浏览器 / 内存 | 数据存在数据库,重启不丢 | 持久化是后端的根本职责 |
| 手拼 SQL 字符串 | GORM 用链式方法生成 SQL | ORM 帮你避免拼字符串出错 |
| JSON 对象自由字段 | 表结构 / 模型字段固定 | 强类型,列是定义好的 |
| `id` 随便给 | 主键自增,由数据库管 | GORM 默认 `ID` 为主键 |
| 删了就没了 | 软删除:标记 `deleted_at` | 数据其实还在,只是查不到 |

记住一句话:**GORM 让你用写 struct 的方式操作数据库,SQL 它帮你生成,但你得懂它生成了什么。**

---

## 三、每天学习安排(7天)

### Day 1:用 Docker 起本地数据库

不用在本机装 MySQL,用 Docker 一条命令搞定,用完即弃。

**起 MySQL**
```bash
docker run -d \
  --name mysql-dev \
  -e MYSQL_ROOT_PASSWORD=root123 \
  -e MYSQL_DATABASE=todo_db \
  -p 3306:3306 \
  mysql:8.0

# 进容器验证
docker exec -it mysql-dev mysql -uroot -proot123 -e "show databases;"
```

**或者起 PostgreSQL(二选一即可)**
```bash
docker run -d \
  --name pg-dev \
  -e POSTGRES_PASSWORD=root123 \
  -e POSTGRES_DB=todo_db \
  -p 5432:5432 \
  postgres:16
```

**Day 1 理解要点**
- `-d` 后台运行,`-p 3306:3306` 把容器端口映射到本机
- `-e` 传环境变量,设置 root 密码和初始数据库
- 想清空重来:`docker rm -f mysql-dev` 删掉再重跑

---

### Day 2:连接数据库 + 第一个模型

```bash
go get gorm.io/gorm
go get gorm.io/driver/mysql   # 用 PG 则换成 gorm.io/driver/postgres
```

```go
package main

import (
    "gorm.io/driver/mysql"
    "gorm.io/gorm"
)

var DB *gorm.DB

func InitDB() {
    // DSN:用户名:密码@tcp(地址)/库名?参数
    dsn := "root:root123@tcp(127.0.0.1:3306)/todo_db?charset=utf8mb4&parseTime=True&loc=Local"
    db, err := gorm.Open(mysql.Open(dsn), &gorm.Config{})
    if err != nil {
        panic("连接数据库失败: " + err.Error())
    }
    DB = db
}

func main() {
    InitDB()
    // 连上后 DB 就能全局用了
}
```

**Day 2 理解要点**
- DSN 是连接字符串,`parseTime=True` 让时间字段能正确映射
- `gorm.Open` 返回 `*gorm.DB`,是后续所有操作的入口
- 生产环境 DSN 从环境变量读,别硬编码密码

---

### Day 3:模型定义 + AutoMigrate

```go
package main

import (
    "time"

    "gorm.io/gorm"
)

// GORM 模型:struct + tag
type Todo struct {
    ID        uint           `gorm:"primaryKey" json:"id"`
    Title     string         `gorm:"size:100;not null" json:"title"`
    Done      bool           `gorm:"default:false" json:"done"`
    UserID    uint           `json:"user_id"` // 外键:属于哪个用户
    CreatedAt time.Time      `json:"created_at"`
    UpdatedAt time.Time      `json:"updated_at"`
    DeletedAt gorm.DeletedAt `gorm:"index" json:"-"` // 软删除字段
}

func main() {
    InitDB()
    // 自动建表 / 改表结构,按 struct 生成
    DB.AutoMigrate(&Todo{})
}
```

**常用 GORM tag**
```
primaryKey       主键
size:100         字符串长度
not null         非空
default:false    默认值
uniqueIndex      唯一索引
index            普通索引
column:xxx       自定义列名
```

**Day 3 理解要点**
- `AutoMigrate` 会按 struct 建表 / 加列,但不会删列(安全)
- 字段名 `CreatedAt` / `UpdatedAt` 是 GORM 约定,会自动维护时间
- `gorm.DeletedAt` 类型的字段会开启软删除,`json:"-"` 让它不出现在响应里
- 可以内嵌 `gorm.Model` 一次性带上 ID/时间/软删除四个字段

---

### Day 4:CRUD 基础

```go
// 创建
todo := Todo{Title: "学 GORM", UserID: 1}
DB.Create(&todo)          // 创建后 todo.ID 会被自动回填
fmt.Println(todo.ID)

// 查询单条
var t Todo
DB.First(&t, 1)                       // 按主键查 id=1
DB.Where("title = ?", "学 GORM").First(&t) // 条件查询,用 ? 防注入

// 查询多条
var todos []Todo
DB.Where("user_id = ?", 1).Find(&todos)   // 查某用户的所有 todo
DB.Order("created_at desc").Limit(10).Find(&todos) // 排序 + 分页

// 更新
DB.Model(&t).Update("done", true)              // 更新单个字段
DB.Model(&t).Updates(Todo{Title: "改标题", Done: true}) // 更新多个字段

// 删除(软删除:实际是把 deleted_at 置为当前时间)
DB.Delete(&t)
DB.Delete(&Todo{}, 1) // 按 id 删
```

**Day 4 理解要点**
- 查询要传指针 `&t` / `&todos`,GORM 把结果填进去
- 条件用 `Where("col = ?", val)`,`?` 占位符能防 SQL 注入(千万别拼字符串)
- `First` 查不到会返回 `gorm.ErrRecordNotFound`,记得判断
- `Updates` 传 struct 时,零值字段(如 `false`、`0`)会被忽略,要更新零值用 `map` 或 `Select`

---

### Day 5:关联(一对多)+ 预加载 Preload

一个用户有多个 Todo,这是典型的一对多。

```go
type User struct {
    ID    uint   `gorm:"primaryKey" json:"id"`
    Name  string `json:"name"`
    Todos []Todo `json:"todos"` // 一对多:一个 User 有多个 Todo
}

type Todo struct {
    ID     uint   `gorm:"primaryKey" json:"id"`
    Title  string `json:"title"`
    UserID uint   `json:"user_id"` // 外键,GORM 按 <类型名>ID 约定识别
}

func main() {
    InitDB()
    DB.AutoMigrate(&User{}, &Todo{})

    // 查用户时,一起把他的 Todos 也查出来
    var user User
    DB.Preload("Todos").First(&user, 1)
    fmt.Println(user.Name, len(user.Todos))
}
```

**为什么要 Preload**
```go
// 不用 Preload:user.Todos 是空的,需要额外查询
DB.First(&user, 1)          // 只查 user 表
// 用了 Preload:一次把关联数据也查出来,避免 N+1 查询问题
DB.Preload("Todos").First(&user, 1)
```

**Day 5 理解要点**
- 一对多:父模型放 `[]子模型` 切片,子模型放 `父模型名+ID` 外键
- `Preload("Todos")` 主动加载关联数据,不写就不会自动加载(Go 不搞隐式懒加载)
- 不 Preload 又在循环里逐个查关联,就是经典的 N+1 查询,要避免

---

### Day 6-7:实战(Todo / 商品数据落库)

把上周的项目改成读写数据库。这里以商品为例,结构更完整。

```go
package main

import (
    "net/http"

    "github.com/gin-gonic/gin"
    "gorm.io/driver/mysql"
    "gorm.io/gorm"
)

var DB *gorm.DB

type Product struct {
    ID    uint    `gorm:"primaryKey" json:"id"`
    Name  string  `gorm:"size:100;not null" json:"name"`
    Price float64 `gorm:"not null" json:"price"`
    Stock int     `gorm:"default:0" json:"stock"`
    gorm.Model    `json:"-"` // 也可以只用几个字段,这里演示
}

func InitDB() {
    dsn := "root:root123@tcp(127.0.0.1:3306)/todo_db?charset=utf8mb4&parseTime=True&loc=Local"
    db, err := gorm.Open(mysql.Open(dsn), &gorm.Config{})
    if err != nil {
        panic(err)
    }
    db.AutoMigrate(&Product{})
    DB = db
}

type CreateProductReq struct {
    Name  string  `json:"name" binding:"required"`
    Price float64 `json:"price" binding:"required,gt=0"`
    Stock int     `json:"stock" binding:"gte=0"`
}

// 创建商品
func createProduct(c *gin.Context) {
    var req CreateProductReq
    if err := c.ShouldBindJSON(&req); err != nil {
        c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
        return
    }
    p := Product{Name: req.Name, Price: req.Price, Stock: req.Stock}
    if err := DB.Create(&p).Error; err != nil {
        c.JSON(http.StatusInternalServerError, gin.H{"error": "创建失败"})
        return
    }
    c.JSON(http.StatusOK, p)
}

// 列表
func listProducts(c *gin.Context) {
    var products []Product
    DB.Order("id desc").Find(&products)
    c.JSON(http.StatusOK, products)
}

// 查询单条
func getProduct(c *gin.Context) {
    id := c.Param("id")
    var p Product
    if err := DB.First(&p, id).Error; err != nil {
        c.JSON(http.StatusNotFound, gin.H{"error": "商品不存在"})
        return
    }
    c.JSON(http.StatusOK, p)
}

// 删除(软删除)
func deleteProduct(c *gin.Context) {
    id := c.Param("id")
    DB.Delete(&Product{}, id)
    c.JSON(http.StatusOK, gin.H{"message": "已删除"})
}

func main() {
    InitDB()
    r := gin.Default()
    api := r.Group("/api/v1")
    {
        api.POST("/products", createProduct)
        api.GET("/products", listProducts)
        api.GET("/products/:id", getProduct)
        api.DELETE("/products/:id", deleteProduct)
    }
    r.Run(":8080")
}
```

**测试**
```bash
# 创建
curl -X POST localhost:8080/api/v1/products \
  -d '{"name":"机械键盘","price":299.9,"stock":10}'

# 列表
curl localhost:8080/api/v1/products

# 查单条
curl localhost:8080/api/v1/products/1

# 删除后再查列表,验证软删除(查不到但表里还在)
curl -X DELETE localhost:8080/api/v1/products/1
```

进阶(有余力再做):加上分页(`Limit` + `Offset`)和按名称模糊搜索(`Where("name LIKE ?", "%"+kw+"%")`)。

---

## 四、本周验收清单

对照打卡,能独立做到才算过关:

- [ ] 能用 Docker 起一个本地 MySQL / PostgreSQL
- [ ] 能用 GORM 连上数据库并 `AutoMigrate` 建表
- [ ] 能用 struct + tag 定义模型,说清常用 tag 含义
- [ ] 能完成完整的 CRUD(Create/First/Find/Update/Delete)
- [ ] 知道用 `?` 占位符防 SQL 注入,不拼字符串
- [ ] 能定义一对多关联并用 `Preload` 加载
- [ ] 能解释什么是 N+1 查询以及怎么避免
- [ ] 理解软删除的原理(`deleted_at`)
- [ ] 接口能真正读写数据库,重启数据不丢
- [ ] 模型、表结构、接口三者对应清晰

---

## 五、常见踩坑提醒

| 坑 | 说明 |
|---|---|
| 忘了传指针 | `Find(&list)` 要传指针,否则查不进去 |
| `First` 查不到没判断 | 会返回 `ErrRecordNotFound`,要处理 |
| `Updates` 传 struct 忽略零值 | 更新 `false`/`0` 用 map 或 `Select` 指定字段 |
| 拼 SQL 字符串 | 一律用 `Where("x = ?", v)`,防注入 |
| 没写 Preload 却在循环里查 | 造成 N+1 查询,性能差 |
| `parseTime` 没开 | 时间字段映射报错,DSN 里加 `parseTime=True` |
| 直接删容器丢数据 | 学习期无所谓,真项目要挂数据卷持久化 |

---

## 六、推荐资料(挑 1-2 个即可)

- **GORM 官方文档中文版**: gorm.io/zh_CN/docs — 例子清晰,查方法用法
- **SQL 基础**: 廖雪峰 SQL 教程 — 补一下最基础的增删改查 SQL 概念
- Docker 官方 MySQL / Postgres 镜像页 — 参数和数据卷配置看这里

---

## 七、本周节奏参考

| 天 | 主题 | 核心任务 |
|---|---|---|
| Day 1 | Docker 起数据库 | 本地跑起 MySQL/PG |
| Day 2 | 连接 + 配置 | GORM 连上库 |
| Day 3 | 模型 + 迁移 | 定义 struct,AutoMigrate 建表 |
| Day 4 | CRUD | 掌握增删改查写法 |
| Day 5 | 关联 + Preload | 一对多和预加载 |
| Day 6 | 实战:数据落库 | 商品 CRUD 接口读写数据库 |
| Day 7 | 实战:完善 | 加分页/搜索,自检,提交 |
