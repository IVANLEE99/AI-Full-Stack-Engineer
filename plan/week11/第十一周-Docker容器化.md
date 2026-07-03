# 第十一周详细学习内容: Docker容器化

> 主题: Docker容器化
> 目标: 让你的 Go 项目从“本地能跑”升级到“任何机器都能一致运行”,并把 Go 服务、MySQL、Redis 编排成一套可一键启动的开发环境。
> 原则: 本周不是学 Docker 全家桶,而是把它当成交付工具来用。**重点不是背命令,而是把你的项目真正装进容器里跑起来。**

---

## 一、本周核心目标

| 目标 | 说明 |
|---|---|
| 掌握 Docker 基础心智 | 理解镜像、容器、端口映射、卷、网络 |
| 写出 Go 项目的 Dockerfile | 会做多阶段构建,减小镜像体积 |
| 学会 docker-compose 编排 | 一次启动 Go + MySQL + Redis |
| 完成交付验证 | 新机器或新环境能用一条命令跑起项目 |

---

## 二、本周关键认知

前端常见的问题是“我本地能跑,你那边不行”。Docker 解决的是**环境一致性**。

| 你熟悉的前端场景 | Docker 里的对应概念 | 关键差异 |
|---|---|---|
| `package.json` + `npm install` | Dockerfile + `docker build` | 一个描述依赖,一个描述完整运行环境 |
| 本地 `npm run dev` | `docker run image` | 本地直接跑 vs 在隔离环境里跑 |
| `.env.local` | `environment` / `env_file` | 容器内也要注入环境变量 |
| 本地 MySQL / Redis 手动启动 | `docker-compose up` | 多个服务一起编排启动 |
| “你也装一下这个版本” | 镜像里固定版本 | Docker 的价值就是不用再手工对齐环境 |

记住一句话: **Docker 不是拿来“炫技部署”的,而是拿来把你的项目变成一个别人也能稳定运行的交付物。**

---

## 三、每天学习安排(7天)

### Day 1: Docker 基础概念 + 跑第一个容器

先理解 4 个词:
- `image`: 镜像,像“模板”
- `container`: 容器,镜像运行后的实例
- `build`: 根据 Dockerfile 构建镜像
- `run`: 基于镜像启动容器

**安装后先跑 hello-world**
```bash
docker --version
docker run hello-world
```

**再跑一个 Nginx 看端口映射**
```bash
docker run -d -p 8088:80 --name my-nginx nginx
```

浏览器打开 `http://localhost:8088`

**Day 1 理解要点**
- `-d` 表示后台运行
- `-p 8088:80` 表示“宿主机端口:容器端口”
- 镜像是静态模板,容器是运行中的实例

---

### Day 2: 给 Go 服务写第一个 Dockerfile

今天目标:把你的 Go 项目装进镜像。

**最基础 Dockerfile**
```dockerfile
FROM golang:1.24

WORKDIR /app

COPY go.mod go.sum ./
RUN go mod download

COPY . .
RUN go build -o server ./cmd/server

EXPOSE 8080

CMD ["./server"]
```

**构建镜像**
```bash
docker build -t booking-system:v1 .
```

**启动容器**
```bash
docker run -p 8080:8080 booking-system:v1
```

**Day 2 理解要点**
- `WORKDIR` 相当于容器里的当前目录
- `COPY go.mod go.sum ./` 这一步先拷依赖文件,是为了复用缓存
- `EXPOSE 8080` 是声明用途,真正开放端口还是靠 `-p`

---

### Day 3: 多阶段构建(重点)

直接用 `golang:1.24` 跑出来的镜像通常比较大。生产环境常用多阶段构建。

**推荐 Dockerfile**
```dockerfile
# ===== 构建阶段 =====
FROM golang:1.24 AS builder

WORKDIR /app

COPY go.mod go.sum ./
RUN go mod download

COPY . .
RUN CGO_ENABLED=0 GOOS=linux GOARCH=amd64 go build -o server ./cmd/server

# ===== 运行阶段 =====
FROM alpine:3.20

WORKDIR /app

COPY --from=builder /app/server ./server

EXPOSE 8080

CMD ["./server"]
```

**为什么更好**
- 第一阶段只负责编译
- 第二阶段只保留二进制文件
- 镜像更小,更适合部署

**Day 3 理解要点**
- 多阶段构建是 Go 项目 Docker 化的常规做法
- `CGO_ENABLED=0` 常用于构建静态二进制,减少运行时依赖
- 运行阶段不需要 Go 编译器,只需要可执行文件

---

### Day 4: MySQL / Redis 单独跑起来

在把它们编排到 compose 之前,先单独跑一次。

**MySQL**
```bash
docker run -d \
  --name my-mysql \
  -e MYSQL_ROOT_PASSWORD=123456 \
  -e MYSQL_DATABASE=app_db \
  -p 3306:3306 \
  mysql:8.4
```

**Redis**
```bash
docker run -d \
  --name my-redis \
  -p 6379:6379 \
  redis:7
```

**查看容器状态**
```bash
docker ps
```

**查看日志**
```bash
docker logs my-mysql
docker logs my-redis
```

**Day 4 理解要点**
- 本地原来手工启动的服务,现在都能变成容器服务
- `docker logs` 是你排错的第一入口
- MySQL 首次启动时间可能比较长,别刚起就以为挂了

---

### Day 5: 用 docker-compose 编排完整环境

这一天最重要。你不想每次都手动起 3 个容器。

**`docker-compose.yml` 示例**
```yaml
version: '3.9'

services:
  app:
    build: .
    container_name: booking-app
    ports:
      - "8080:8080"
    depends_on:
      - mysql
      - redis
    environment:
      DB_HOST: mysql
      DB_PORT: 3306
      DB_USER: root
      DB_PASSWORD: 123456
      DB_NAME: app_db
      REDIS_ADDR: redis:6379

  mysql:
    image: mysql:8.4
    container_name: booking-mysql
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: 123456
      MYSQL_DATABASE: app_db
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql

  redis:
    image: redis:7
    container_name: booking-redis
    restart: always
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data

volumes:
  mysql_data:
  redis_data:
```

**启动**
```bash
docker compose up --build
```

**后台启动**
```bash
docker compose up -d --build
```

**停止并删除容器**
```bash
docker compose down
```

**Day 5 理解要点**
- `depends_on` 只保证启动顺序,不保证服务一定“可用”
- compose 里服务名(`mysql`, `redis`)就是容器内互相访问时的主机名
- volume 用来保留数据库数据,不然删容器数据就没了

---

### Day 6: 项目接入环境变量 + 本地联调

你现在要让项目配置真正从环境变量读取,不要再写死地址。

**Go 配置结构示例**
```go
package config

import "os"

type Config struct {
    DBHost    string
    DBPort    string
    DBUser    string
    DBPass    string
    DBName    string
    RedisAddr string
}

func Load() Config {
    return Config{
        DBHost:    getEnv("DB_HOST", "127.0.0.1"),
        DBPort:    getEnv("DB_PORT", "3306"),
        DBUser:    getEnv("DB_USER", "root"),
        DBPass:    getEnv("DB_PASSWORD", "123456"),
        DBName:    getEnv("DB_NAME", "app_db"),
        RedisAddr: getEnv("REDIS_ADDR", "127.0.0.1:6379"),
    }
}

func getEnv(key, fallback string) string {
    if v := os.Getenv(key); v != "" {
        return v
    }
    return fallback
}
```

**连接串示例**
```go
cfg := config.Load()

dsn := cfg.DBUser + ":" + cfg.DBPass + "@tcp(" + cfg.DBHost + ":" + cfg.DBPort + ")/" + cfg.DBName + "?charset=utf8mb4&parseTime=True&loc=Local"
```

**Day 6 理解要点**
- 本地运行和容器运行可以共用一套配置结构
- 本地默认值 + 容器环境变量覆盖,这是最常见做法
- 只要你把配置层做好,后面部署就简单很多

---

### Day 7: 交付验证 + 镜像优化

最后一天,你要像交付给别人一样验证:

**验证清单**
1. 删除本地手工启动的 MySQL / Redis
2. 只靠 `docker compose up -d --build` 启动
3. 接口能正常访问
4. 数据能写入 MySQL
5. 缓存能写入 Redis
6. 容器重启后数据库数据还在

**查看 compose 环境状态**
```bash
docker compose ps
docker compose logs app
docker compose logs mysql
docker compose logs redis
```

**镜像优化思路**
- 用多阶段构建
- `.dockerignore` 过滤无关文件
- 尽量只复制必要代码

**`.dockerignore` 示例**
```text
.git
.idea
.vscode
node_modules
*.log
.env
coverage.out
```

**Day 7 理解要点**
- 能在自己电脑跑不算交付,能在“空环境”一把拉起才算交付
- Docker 化的本质是让“我的环境问题”变成“镜像定义问题”

---

## 四、本周验收清单

- [ ] 能解释 image、container、build、run 的区别
- [ ] 能写一个可运行的 Go 项目 Dockerfile
- [ ] 能解释为什么 Go 项目推荐用多阶段构建
- [ ] 能单独拉起 MySQL 和 Redis 容器
- [ ] 能写 `docker-compose.yml` 编排 Go + MySQL + Redis
- [ ] 项目配置支持从环境变量读取
- [ ] 能用 `docker compose up -d --build` 一键启动整套环境
- [ ] 能用 `docker logs` / `docker compose logs` 排查问题
- [ ] 数据库 volume 生效,重启容器后数据还在
- [ ] 能清楚说出“为什么容器化能提升交付稳定性”

---

## 五、常见踩坑提醒

| 坑 | 说明 |
|---|---|
| `depends_on` 当成健康检查 | 它只保证启动顺序,不保证 MySQL 已准备好接请求 |
| 连接数据库还写 `localhost` | 容器内访问别的服务要写服务名,比如 `mysql` |
| 没配 volume | 容器删掉后数据库数据也没了 |
| 忘了 `.dockerignore` | 会把一堆无关文件打进镜像,拖慢构建 |
| 宿主机端口冲突 | 本机已有 MySQL / Redis / 8080 服务时会启动失败 |
| Dockerfile 先 `COPY . .` 再下载依赖 | 会让缓存利用率很差,每次都重新下载依赖 |

---

## 六、推荐资料(挑 1-2 个即可)

- Docker 官方 Get Started
- Docker Compose 官方文档
- Go 官方关于构建和交付的文档

---

## 七、本周节奏参考

| 天 | 主题 | 核心任务 |
|---|---|---|
| Day 1 | Docker 基础 | 跑 hello-world 和 Nginx |
| Day 2 | Dockerfile 入门 | 把 Go 项目打进镜像 |
| Day 3 | 多阶段构建 | 减小镜像体积 |
| Day 4 | 单服务容器 | 分别跑 MySQL 和 Redis |
| Day 5 | Compose 编排 | 一次起 Go + MySQL + Redis |
| Day 6 | 配置联调 | 用环境变量接通项目配置 |
| Day 7 | 交付验证 | 一键启动、排错、持久化检查 |
