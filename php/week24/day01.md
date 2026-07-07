# Week 24 Day 01：Docker Compose — 编排前端 + PHP + 向量库 + LLM Gateway

> 所属周：Week 24：毕业项目：部署 + 复盘  
> 阶段：第六阶段：毕业项目  
> 主仓库/项目：`graduation-project`  
> 类型：概念入门  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

理解 Docker 和 Docker Compose，并能用一个 `docker-compose.yml` 把毕业项目的多个服务（前端 + PHP 后端 + 向量库 + LLM Gateway）一键编排起来。

今天你要真正掌握这一句话：

> Docker 把一个服务连同它的运行环境打包成"镜像"，运行起来就是"容器"；Docker Compose 用一个 `docker-compose.yml` 文件描述**多个容器**怎么协同（网络、端口、依赖、环境变量），一条 `docker compose up` 就能把整套系统拉起来。这就像 Node 里用 `npm start` 起一个进程，但 Compose 是"一条命令起一整套进程"。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先搞清楚"为什么需要 Docker"这个问题
2. 理解镜像（image）和容器（container）的区别
3. 理解一个服务怎么用 Dockerfile 打包成镜像
4. 理解为什么单个容器不够，需要 Compose 编排多个
5. 逐段读懂 `docker-compose.yml` 的结构
6. 理解服务之间怎么通过"服务名"互相访问（网络）
7. 理解环境变量、数据卷、端口映射
8. 亲手写一个包含 4 个服务的 `docker-compose.yml`
9. 跑通 `docker compose up`，看服务是否都起来
10. 写今日笔记和自测

---

## 1. 学习内容

### 1.1 先确认 Docker 是否可用

先在终端执行：

```bash
docker -v
```

你应该看到类似：

```text
Docker version 27.x.x, build ...
```

再执行：

```bash
docker compose version
```

你应该看到类似：

```text
Docker Compose version v2.x.x
```

如果这两个命令都能输出版本，说明今天的基础环境 OK。

小白重点：注意新版是 `docker compose`（中间有空格，是 Docker 的子命令），老版本是 `docker-compose`（带横线，是独立工具）。本教程统一用新版 `docker compose`。

如果还没装 Docker，可以去官网装 **Docker Desktop**（Mac/Windows）或在 Linux 上装 Docker Engine。装完记得启动 Docker Desktop，让后台的 Docker 引擎跑起来。

---

### 1.2 为什么需要 Docker？先理解"在我电脑上是好的"

你可能遇到过这种情况：

```text
你：我本地跑得好好的呀！
同事：我这里一启动就报错……
```

原因通常是：两个人的 PHP 版本、扩展、数据库版本、系统环境都不一样。

Docker 解决的就是这个问题。它把一个服务需要的**所有东西**（操作系统层、PHP 运行时、扩展、你的代码、配置）打包成一个"镜像"。谁拉到这个镜像，跑出来的环境都一模一样。

用一句话记住：

> Docker = 把"能跑起来的整套环境"装进一个盒子，换台机器也能原样跑。

Node 类比：

| 场景 | 传统方式的痛点 | Docker 的做法 |
|---|---|---|
| Node 项目 | 需要对方装对 Node 版本、npm、系统依赖 | 镜像里已经装好 Node 和依赖 |
| PHP 项目 | 需要对方装对 PHP 版本、扩展、Composer | 镜像里已经装好 PHP 和扩展 |
| 数据库 | 需要手动装 MySQL/Redis | 直接用官方镜像启动 |

---

### 1.3 理解镜像（image）和容器（container）

这两个概念小白最容易混。用做菜类比：

| 概念 | 类比 | 说明 |
|---|---|---|
| 镜像 image | 菜谱 / 冷冻速食包 | 静态的、只读的模板 |
| 容器 container | 按菜谱做出来的那盘菜 | 由镜像"运行"起来的实例 |

关键点：

- 一个镜像可以启动出**多个**容器（同一份菜谱可以做很多盘）。
- 容器是"活的"，有自己的运行状态；镜像是"死的"，只是模板。

常用命令：

```bash
docker images          # 看本地有哪些镜像
docker ps              # 看正在运行的容器
docker ps -a           # 看所有容器（包括停掉的）
```

Node 类比：

```text
镜像 image  ≈  一个打包好的 npm package（含运行时）
容器 container ≈ node app.js 跑起来的那个进程
```

---

### 1.4 用 Dockerfile 把 PHP 服务打包成镜像

`Dockerfile` 是"打包镜像的说明书"。它一步步告诉 Docker：基于什么系统、装什么、拷什么代码、怎么启动。

一个给 PHP 后端用的 `Dockerfile` 示例：

```dockerfile
# 1. 基础镜像：官方 PHP 8.3 + FPM
FROM php:8.3-fpm

# 2. 安装系统依赖和 PHP 扩展
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# 3. 安装 Composer（从官方 composer 镜像里拷可执行文件）
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 4. 设置工作目录
WORKDIR /var/www/html

# 5. 先拷依赖清单，装依赖（利用缓存，代码变了也不用重装依赖）
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist

# 6. 再拷全部代码
COPY . .

# 7. 声明容器对外暴露的端口
EXPOSE 9000

# 8. 容器启动时执行的命令
CMD ["php-fpm"]
```

每一行的含义：

| 指令 | 作用 | Node/npm 类比 |
|---|---|---|
| `FROM` | 选一个基础镜像 | 类似选 `node:20` 作为基础 |
| `RUN` | 在构建镜像时执行命令 | 类似 Dockerfile 里 `npm install` 系统依赖 |
| `COPY` | 把宿主机文件拷进镜像 | 拷 `package.json` / 源码 |
| `WORKDIR` | 设定工作目录 | 类似 `cd /app` |
| `EXPOSE` | 声明端口（文档作用） | 类似声明服务监听 3000 |
| `CMD` | 容器启动命令 | 类似 `npm start` / `node app.js` |

小白重点：第 5 步"先拷 composer.json 再拷代码"是一个重要技巧。因为 Docker 是分层缓存的，只要 `composer.json` 没变，第 5 层就复用缓存，不用每次改代码都重新装依赖，构建会快很多。这和 Node 里"先 COPY package.json 再 npm install"是同一个套路。

---

### 1.5 为什么一个容器不够？需要编排多个服务

毕业项目不是一个孤零零的 PHP。它至少有这几块：

```text
前端（浏览器访问的页面）
   ↓ 调接口
PHP 后端（业务逻辑、API）
   ↓ 查向量
向量库（存文档 embedding，做相似检索）
   ↓ 调大模型
LLM Gateway（统一代理各家大模型 API）
```

每一块都是一个独立的服务，最好各跑各的容器。这样：

- 前端挂了不影响后端。
- 向量库可以单独升级、单独扩容。
- 每个服务的环境互不污染。

问题来了：4 个容器，难道我要开 4 个终端，手动敲 4 条 `docker run`，还要自己配网络让它们互相能找到？太麻烦了。

这就是 **Docker Compose** 出场的地方。

---

### 1.6 Docker Compose 是什么？

Docker Compose 用**一个 YAML 文件**描述整套系统里所有服务，然后一条命令全部启动。

核心心智模型：

```text
docker run 单个容器  ≈  node 单个脚本
docker compose 一整套 ≈ 一个能同时管理多个进程的编排器
```

对比：

| 你想做的事 | 不用 Compose | 用 Compose |
|---|---|---|
| 启动 4 个服务 | 敲 4 条 `docker run`，还要配网络 | `docker compose up` |
| 关闭全部 | 一个个 `docker stop` | `docker compose down` |
| 改配置 | 记不清当时敲的参数 | 改 `docker-compose.yml` |
| 交给同事 | 口头描述一堆命令 | 发一个 yml 文件 |

一句话：

> Compose 把"一堆 docker run 命令 + 网络配置"变成了一个可版本化、可分享的配置文件。

---

### 1.7 逐段读懂 docker-compose.yml 的结构

先看一个最小例子（只有 PHP + 数据库）：

```yaml
services:
  app:
    build: .
    ports:
      - "8080:9000"
    environment:
      APP_ENV: production
    depends_on:
      - db

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: app
    volumes:
      - db_data:/var/lib/mysql

volumes:
  db_data:
```

拆开看：

| 字段 | 含义 |
|---|---|
| `services` | 所有服务的清单，每个服务是一个容器 |
| `app` / `db` | 服务名（也是它在内网里的"主机名"） |
| `build: .` | 用当前目录的 Dockerfile 构建镜像 |
| `image: mysql:8.0` | 直接用现成镜像，不自己构建 |
| `ports` | 端口映射，格式 `宿主机端口:容器端口` |
| `environment` | 传给容器的环境变量 |
| `depends_on` | 启动顺序依赖（db 先起，app 后起） |
| `volumes` | 数据卷，用来持久化数据 |

小白重点：`ports` 的 `"8080:9000"` 读法是"把宿主机的 8080 转发到容器里的 9000"。你在浏览器访问 `localhost:8080`，实际到的是容器内的 9000。

---

### 1.8 服务之间怎么互相访问？（Compose 网络）

这是 Compose 最"神奇"也最关键的一点：

> 在同一个 `docker-compose.yml` 里的服务，会被放进同一个虚拟网络。它们可以直接用**服务名**当主机名互相访问。

举例：PHP 里连数据库，主机名不写 `127.0.0.1`，而是写服务名 `db`：

```php
<?php

// 注意：host 是服务名 db，不是 localhost
$dsn = "mysql:host=db;port=3306;dbname=app";
$pdo = new PDO($dsn, "root", "secret");
```

同理，PHP 调 LLM Gateway，就用 `http://llm-gateway:8000`；调向量库，就用 `http://vector-db:6333`。

对比一下小白常犯的错：

| 写法 | 结果 |
|---|---|
| `host=localhost` | ❌ 在容器内，localhost 指容器自己，连不到 db 容器 |
| `host=127.0.0.1` | ❌ 同上，指向容器自身 |
| `host=db` | ✅ Compose 自动解析成 db 容器的内网 IP |

Node 类比：在 Docker Compose 里，服务名就像内网 DNS 名，等价于你在 Node 微服务里通过服务发现拿到的地址，只是 Compose 帮你自动做好了。

---

### 1.9 环境变量、数据卷、端口的正确用法

#### 1.9.1 环境变量：不要把密码写死在 yml 里

推荐把敏感配置放进 `.env` 文件，Compose 会自动读取：

`.env` 文件（不提交到 Git）：

```text
DB_ROOT_PASSWORD=change-me-in-prod
DB_NAME=app
LLM_API_KEY=sk-xxxxxx
```

`docker-compose.yml` 里引用：

```yaml
services:
  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_NAME}
```

`${DB_ROOT_PASSWORD}` 会被 `.env` 里的值替换。

小白重点：`.env` 一定要写进 `.gitignore`，绝不能把真实密钥推到 GitHub。可以额外提供一个 `.env.example` 做模板（值用占位符）。

#### 1.9.2 数据卷：让数据"活得比容器久"

容器是"用完即弃"的。容器删了，里面的数据也没了。数据库数据必须放进 volume：

```yaml
services:
  db:
    image: mysql:8.0
    volumes:
      - db_data:/var/lib/mysql   # 命名卷，数据留在宿主机

volumes:
  db_data:
```

还有一种"绑定挂载"，把宿主机目录直接映射进容器，开发时常用来同步代码：

```yaml
services:
  app:
    build: .
    volumes:
      - ./src:/var/www/html/src   # 改本地代码，容器里立刻生效
```

| 卷类型 | 写法 | 用途 |
|---|---|---|
| 命名卷 | `db_data:/var/lib/mysql` | 持久化数据库数据 |
| 绑定挂载 | `./src:/var/www/html/src` | 开发时热同步代码 |

#### 1.9.3 端口：只暴露需要对外的服务

前端和后端需要浏览器访问，要映射端口。数据库、向量库这类内部服务，能不暴露就不暴露，只让内网访问，更安全。

---

### 1.10 完整示例：4 个服务的 docker-compose.yml

下面是毕业项目的完整编排（前端 + PHP + 向量库 + LLM Gateway）。全部用代号和占位符，脱敏。

```yaml
# docker-compose.yml —— 毕业项目 graduation-project 编排
services:
  # 1) 前端：静态页面 / SPA，用 Nginx 托管
  frontend:
    image: nginx:1.27-alpine
    ports:
      - "8080:80"                       # 浏览器访问 http://localhost:8080
    volumes:
      - ./frontend/dist:/usr/share/nginx/html:ro
    depends_on:
      - backend

  # 2) PHP 后端：业务 API
  backend:
    build:
      context: ./backend
      dockerfile: Dockerfile
    environment:
      APP_ENV: production
      DB_HOST: db                       # 用服务名访问数据库
      DB_NAME: ${DB_NAME}
      DB_USER: ${DB_USER}
      DB_PASSWORD: ${DB_PASSWORD}
      VECTOR_DB_URL: http://vector-db:6333
      LLM_GATEWAY_URL: http://llm-gateway:8000
    depends_on:
      - db
      - vector-db
      - llm-gateway
    # 后端一般不直接暴露给公网，通过前端/网关转发
    expose:
      - "9000"

  # 3) 数据库：存业务数据
  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_NAME}
      MYSQL_USER: ${DB_USER}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql
    # 不映射到宿主机端口，只在内网用

  # 4) 向量库：存文档 embedding，做相似检索
  vector-db:
    image: qdrant/qdrant:latest
    volumes:
      - vector_data:/qdrant/storage
    # 内网端口 6333，供 backend 调用

  # 5) LLM Gateway：统一代理各家大模型 API
  llm-gateway:
    image: ghcr.io/example/llm-gateway:latest   # 代号占位镜像
    environment:
      LLM_API_KEY: ${LLM_API_KEY}
      LLM_PROVIDER: example-provider
    # 内网端口 8000，供 backend 调用

volumes:
  db_data:
  vector_data:
```

对应的 `.env.example`（提交这个模板，不提交真实 .env）：

```text
# 数据库
DB_ROOT_PASSWORD=change-me
DB_NAME=graduation
DB_USER=app_user
DB_PASSWORD=change-me-too

# 大模型
LLM_API_KEY=sk-your-key-here
```

启动整套系统：

```bash
docker compose up -d
```

`-d` 表示后台运行（detached）。查看状态：

```bash
docker compose ps
```

看某个服务的日志：

```bash
docker compose logs -f backend
```

小白重点：读这个文件时，抓住"数据流向"。浏览器 → frontend(8080) → backend(9000) → 分别去 db / vector-db / llm-gateway。服务之间全用服务名互相找。

---

### 1.11 Compose 常用命令速查

| 命令 | 作用 | Node/npm 类比 |
|---|---|---|
| `docker compose up -d` | 后台启动全部服务 | 类似同时 `npm start` 多个进程 |
| `docker compose down` | 停止并删除全部容器 | 停掉全部进程 |
| `docker compose ps` | 查看服务状态 | `pm2 list` |
| `docker compose logs -f 服务名` | 看某服务日志 | `pm2 logs` |
| `docker compose build` | 重新构建镜像 | `npm run build` |
| `docker compose restart 服务名` | 重启某服务 | `pm2 restart` |
| `docker compose exec backend bash` | 进入容器内部执行命令 | `docker exec` / ssh 进去 |
| `docker compose down -v` | 停服务并删数据卷 | ⚠️ 会删数据，谨慎 |

⚠️ 安全提醒：`docker compose down -v` 会连数据卷一起删除，数据库数据会丢。只在确认要清空数据时用。

---

## 2. 源码阅读

- `graduation-project/docker-compose.yml`
- `graduation-project/backend/Dockerfile`

> 说明：路径均为公开代号 + 相对路径。学习时按你的本地仓库映射查找对应文件。

阅读时重点找这些内容：

1. `services` 下一共定义了几个服务？各是干什么的？
2. 哪些服务是 `build`（自己构建），哪些是 `image`（用现成镜像）？
3. 哪些服务映射了 `ports`（对外暴露），哪些只在内网？
4. 服务之间通过什么互相访问？（找服务名当主机名的地方）
5. 敏感信息（密码、API Key）是写死的，还是走 `${}` + `.env`？
6. 哪些数据用了 `volumes` 持久化？

建议你在笔记里画出这样一张"服务拓扑图 + 表格"：

| 服务名 | 来源 | 对外端口 | 依赖谁 | 作用 |
|---|---|---|---|---|
| frontend | image nginx | 8080 | backend | 托管前端页面 |
| backend | build Dockerfile | 无（仅内网） | db/vector-db/llm-gateway | 业务 API |
| db | image mysql | 无 | 无 | 业务数据 |
| vector-db | image qdrant | 无 | 无 | 向量检索 |
| llm-gateway | image 代号镜像 | 无 | 无 | 代理大模型 |

---

## 3. 练习任务

### 练习 1：跑通一个最小 Compose（PHP + DB）

新建目录：

```bash
mkdir compose-demo
cd compose-demo
```

创建 `docker-compose.yml`：

```yaml
services:
  app:
    image: php:8.3-cli
    working_dir: /app
    volumes:
      - ./:/app
    command: php -S 0.0.0.0:9000 index.php
    ports:
      - "9000:9000"
    depends_on:
      - db

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: demo
    volumes:
      - db_data:/var/lib/mysql

volumes:
  db_data:
```

创建 `index.php`：

```php
<?php

declare(strict_types=1);

header("Content-Type: application/json");

echo json_encode([
    "status" => "ok",
    "message" => "Hello from PHP container",
]);
```

启动：

```bash
docker compose up -d
```

浏览器访问 `http://localhost:9000`，应看到：

```json
{"status":"ok","message":"Hello from PHP container"}
```

看日志确认两个服务都起来：

```bash
docker compose ps
docker compose logs db
```

目标：跑通最基础的多服务编排。

---

### 练习 2：让 PHP 连上数据库容器

修改 `index.php`，用服务名 `db` 连接：

```php
<?php

declare(strict_types=1);

header("Content-Type: application/json");

try {
    // 注意 host 用服务名 db
    $pdo = new PDO(
        "mysql:host=db;port=3306;dbname=demo",
        "root",
        "secret"
    );
    $version = $pdo->query("SELECT VERSION()")->fetchColumn();

    echo json_encode([
        "status" => "ok",
        "db_version" => $version,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
    ]);
}
```

重启并访问：

```bash
docker compose restart app
```

目标：亲手验证"服务之间用服务名互相访问"。

小白提示：MySQL 容器首次启动需要几秒初始化，太快访问可能连不上，稍等再刷新。

---

### 练习 3：把敏感配置抽到 .env

新建 `.env`：

```text
DB_ROOT_PASSWORD=secret
DB_NAME=demo
```

修改 `docker-compose.yml` 的 db 部分：

```yaml
  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_NAME}
    volumes:
      - db_data:/var/lib/mysql
```

再创建 `.gitignore`：

```text
.env
```

目标：理解"配置和代码分离"，密码不进代码库。

---

### 练习 4：写出毕业项目的 4 服务编排

参考 1.10，为你的毕业项目 `graduation-project` 写一份完整的 `docker-compose.yml`，包含：

- frontend（前端页面）
- backend（PHP 后端，用 Dockerfile 构建）
- vector-db（向量库）
- llm-gateway（大模型代理）

并配套写：

- `backend/Dockerfile`
- `.env.example`

目标：产出可以直接放进毕业项目的编排文件。

---

### 练习 5：Compose 命令映射表

把下面命令补全"作用"和"Node 类比"：

| 命令 | 作用 | Node 类比 |
|---|---|---|
| `docker compose up -d` |  |  |
| `docker compose down` |  |  |
| `docker compose logs -f backend` |  |  |
| `docker compose exec backend bash` |  |  |
| `docker compose ps` |  |  |

参考答案见 1.11。

---

## 4. JS/Node.js 类比

| Docker / Compose | Node.js / npm 类比 | 说明 |
|---|---|---|
| 镜像 image | 打包好的运行时 + 代码 | 静态模板 |
| 容器 container | `node app.js` 起的进程 | 运行实例 |
| `Dockerfile` | 构建脚本（含 `npm install`） | 打镜像的说明书 |
| `docker run` | `node app.js` | 起单个进程 |
| `docker-compose.yml` | 一个能同时管理多进程的配置 | 多服务编排 |
| `docker compose up` | 一条命令起整套系统 | 一键启动 |
| 服务名当主机名 | 微服务里的服务发现地址 | 内网 DNS |
| `.env` + `${VAR}` | `dotenv` + `process.env` | 配置注入 |
| volume 数据卷 | 挂载持久化目录 | 数据不随进程消失 |
| `docker compose logs` | `pm2 logs` | 看日志 |

注意：PHP 常见部署是 Nginx + PHP-FPM 两个进程配合（Nginx 处理 HTTP，PHP-FPM 跑 PHP），而 Node 通常一个进程包办。所以 PHP 的 Compose 里经常能看到 `web`(nginx) 和 `app`(php-fpm) 两个服务，这点要能说清楚。

---

## 5. AI Review 提问

完成练习后，把你的 `docker-compose.yml` 和 `Dockerfile` 贴给 AI，然后问：

```text
我正在学习 PHP Week 24 Day 01：用 Docker Compose 编排毕业项目（前端 + PHP + 向量库 + LLM Gateway）。

请你按资深 DevOps/PHP 工程师标准帮我检查：

1. 我的服务拆分是否合理？
2. 服务之间用服务名互相访问的写法对不对？
3. 端口暴露是否安全？哪些服务不该对公网开放？
4. 敏感信息（密码、API Key）是否正确地走了 .env？
5. 数据卷配置能否保证数据库数据不丢？
6. Dockerfile 的分层缓存是否合理？

请用中文输出：
- 我配置正确的地方
- 我配置错误或有风险的地方
- 修改建议
- 生产环境上线前还要补什么
```

---

## 6. 今日产出

今天结束前，你应该产出这些内容：

- [ ] 跑通的最小 Compose（PHP + DB）
- [ ] PHP 通过服务名连上数据库容器的验证
- [ ] 抽到 `.env` 的配置示例
- [ ] 毕业项目 4 服务 `docker-compose.yml`
- [ ] `backend/Dockerfile`
- [ ] `.env.example` 模板
- [ ] 服务拓扑图 / 表格
- [ ] 今日 AI Review 记录

---

## 7. 今日完成标准

- [ ] 能说清楚镜像和容器的区别
- [ ] 能读懂一个 `Dockerfile` 每一行的作用
- [ ] 能解释为什么要用 Compose 编排多服务
- [ ] 能说出 `docker-compose.yml` 里 `services`/`ports`/`volumes`/`environment`/`depends_on` 的作用
- [ ] 能解释服务之间为什么用服务名而不是 localhost 互相访问
- [ ] 能把敏感配置正确抽到 `.env`
- [ ] 能跑通一个多服务的 `docker compose up`
- [ ] 能画出毕业项目的服务拓扑图

---

## 8. 今日自测题

### 8.1 镜像和容器有什么区别？

参考答案：

> ✅ 镜像是静态的、只读的模板（像菜谱）；容器是镜像运行起来的实例（像做出来的菜）。一个镜像可以启动多个容器。

---

### 8.2 为什么容器里连数据库不能用 localhost？

参考答案：

> ✅ 在容器内，`localhost`/`127.0.0.1` 指的是容器自己，而数据库在另一个容器里。Compose 会把同一编排里的服务放进同一网络，用服务名（如 `db`）当主机名即可互相访问。

---

### 8.3 `docker-compose.yml` 里 `depends_on` 是干什么的？

参考答案：

> ✅ 声明服务的启动顺序依赖，比如 backend 依赖 db，Compose 会先启动 db 再启动 backend。注意它只保证"启动顺序"，不保证"db 已就绪能连"，生产中往往还要加健康检查或重试。

---

### 8.4 数据卷（volume）解决了什么问题？

参考答案：

> ✅ 容器删除后里面的数据会丢失。把数据库目录挂到命名卷上，数据就保存在宿主机，即使容器重建，数据仍在。

---

### 8.5 敏感信息应该怎么放进 Compose？

参考答案：

> ✅ 不要写死在 `docker-compose.yml` 里。放进 `.env` 文件，用 `${VAR}` 引用，并把 `.env` 加入 `.gitignore`，另外提交一个值为占位符的 `.env.example` 作为模板。

---

## 9. 学习记录

| 记录项 | 内容 |
|--------|------|
| 今日最清楚的概念 |  |
| 今日最卡的概念 |  |
| JS/Node 类比是否帮助理解 |  |
| 实际耗时 |  |
| 明日要补的问题 |  |

---

## 10. AI Review 提示词

```text
我正在进行 Week 24 Day 01：Docker Compose 的学习。
请你扮演资深 PHP 后端 / DevOps 工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 24 README](./README.md)
