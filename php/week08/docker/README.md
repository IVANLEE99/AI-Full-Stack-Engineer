# day04 学习环境（可运行的替代方案）

> 用途：项目自带的 `docker-compose-baomi.yml` 不在本机，这套配置让你**现在就能**跑通 day04 六条完成标准，并直接服务于本周 MQ 主线。
> 位置：`php/week08/docker/`
> 验证日期：2026-11-03

---

## 1. 它是什么

一个 4 服务的最小学习环境：**PHP 8.2 + MySQL 8.0 + Redis 7 + RabbitMQ 3**。

| 服务名 | 容器名 | 本机端口 → 容器端口 | 用途 |
|---|---|---|---|
| `php` | `learn-php` | — | 跑 PHP 代码，挂载 `php/week08/` 到 `/app` |
| `mysql` | `learn-mysql` | `3307` → `3306` | 避免和你本机 3306 冲突 |
| `redis` | `learn-redis` | `6380` → `6379` | 避免和已有 redis 冲突 |
| `rabbitmq` | `learn-rabbitmq` | `5673` → `5672`（AMQP）<br>`15673` → `15672`（管理台） | 队列实验 |

> 端口刻意错开，是为了**不干扰你已有的环境**。连接时务必用左边那个本机端口。

---

## 2. 常用命令

```bash
cd php/week08/docker

docker compose up -d              # 启动（首次会构建 PHP 镜像）
docker compose ps                 # 看状态
docker compose logs -f rabbitmq   # 看某个服务日志
docker compose exec php bash      # 进 PHP 容器
docker compose down               # 停止（保留 MySQL 数据卷）
docker compose down -v            # 停止并清空 MySQL 数据
```

进容器后离开：`exit` 或 `Ctrl+D`。

---

## 3. 六条完成标准的验证命令

### 3.1 启动环境

```bash
docker compose up -d
docker compose ps
```

通过标准：4 个服务都是 `Up`，且 `PORTS` 列有映射。

确认 MySQL 真的能服务（它启动比 Redis 慢）：

```bash
docker exec learn-mysql mysqladmin ping -uroot -proot123
# 期望输出：mysqld is alive
```

### 3.2 进入 PHP 容器

```bash
docker compose exec php bash
pwd          # /app
ls           # 能看到 day01.md、RabbitMq.php 等 —— 这就是挂载
```

### 3.3 查看版本与扩展

```bash
docker compose exec php php -v
docker compose exec php php -m | grep -E '^(redis|amqp|pdo_mysql)$'
```

**实测对照（2026-11-03）**：

| | 版本 | redis | amqp | pdo_mysql |
|---|---|---|---|---|
| 容器 `learn-php` | **8.2.35** | ✅ | ✅ | ✅ |
| 本机 Homebrew | **8.5.8** | ❌ | ❌ | ✅ |

**这就是「容器 PHP ≠ 本机 PHP」的硬证据**：本机缺 `redis` 与 `amqp`，直接在本机跑本周代码会失败。

### 3.4 查看两类日志

**第一类：服务自身日志**

```bash
docker compose logs --tail=5 redis
docker compose logs --tail=8 rabbitmq
```

**第二类：应用日志（容器写、本机读）**

```bash
# 容器内写
docker compose exec php bash -c 'echo "[$(date "+%F %T")] INFO test" >> /app/docker/logs/app.log'

# 本机读同一个文件
cat php/week08/docker/logs/app.log
```

因为 `volumes: - ..:/app`，两边看到的是**同一个文件**，不是复制。

### 3.5 说明容器与本机 PHP 的差异

按实测数据讲这五项：

| 对比项 | 本机 | 容器 | 后果 |
|---|---|---|---|
| 版本 | 8.5.8 | 8.2.35 | 语法/弃用告警可能不同 |
| redis 扩展 | ❌ | ✅ | 本机跑不了缓存代码 |
| amqp 扩展 | ❌ | ✅ | **本机跑不了本周 MQ 代码** |
| `localhost` | Mac 自己 | 容器自己 | 连接串写 `127.0.0.1` 必失败 |
| 路径 | `/Users/...` | `/app` | 配置里的绝对路径会失效 |

### 3.6 解释为什么用服务名

**亲手做这个对比**：

```bash
# ❌ 用 127.0.0.1 —— 失败
docker compose exec php php -r '$r=new Redis(); $r->connect("127.0.0.1",6379,1);'
# Fatal error: Connection refused

# ✅ 用服务名 —— 成功
docker compose exec php php -r '$r=new Redis(); $r->connect("redis",6379,2); echo $r->ping();'
# 1
```

再看 DNS 是怎么解析的：

```bash
docker compose exec php getent hosts redis mysql rabbitmq
# 172.18.0.2  redis
# 172.18.0.4  mysql
# 172.18.0.3  rabbitmq

docker compose exec php hostname -i
# 172.18.0.5   ← 自己，和上面三个都不同
```

**原理一句话**：容器里的 `127.0.0.1` 永远指容器自己；compose 为每个服务在内部网络登记了 DNS，**服务名就是主机名**。

---

## 4. 三个服务一起连通（一条命令验证）

```bash
docker compose exec php php -r '
$r=new Redis(); $r->connect("redis",6379,2);
echo "Redis: ".$r->ping()."\n";

$pdo=new PDO("mysql:host=mysql;port=3306;dbname=learn","root","root123");
echo "MySQL: ".$pdo->query("SELECT VERSION()")->fetchColumn()."\n";

$c=new AMQPConnection();
$c->setHost("rabbitmq"); $c->setPort(5672);
$c->setLogin("admin"); $c->setPassword("admin123");
$c->connect();
echo "RabbitMQ: ".($c->isConnected() ? "连接成功" : "失败")."\n";
'
```

实测输出：

```text
Redis: 1
MySQL: 8.0.46
RabbitMQ: 连接成功
```

---

## 5. 一个容易踩的坑：`AMQPStreamConnection` 找不到

`RabbitMq.php` 用的是 `PhpAmqpLib\Connection\AMQPStreamConnection`，那是 **php-amqplib 库**的类，需要 composer 安装：

```bash
docker compose exec php composer require php-amqplib/php-amqplib
```

而 Dockerfile 里装的 `amqp` 是 **PHP 扩展**，提供的是另一套 API（`AMQPConnection`、`AMQPExchange`）。两者不是同一个东西：

| | 来源 | 类名 |
|---|---|---|
| `amqp` 扩展 | `pecl install amqp` | `AMQPConnection` |
| php-amqplib | composer 包 | `PhpAmqpLib\Connection\AMQPStreamConnection` |

本周读的 `RabbitMq.php` 用的是**后者**。验证扩展是否装好，用 `php -m | grep amqp`；验证库是否装好，用 `composer show php-amqplib/php-amqplib`。

---

## 6. RabbitMQ 管理台

浏览器打开 <http://localhost:15673>，账号 `admin` / `admin123`。

能直接看到 [day01](./day01.md) 讲的三个东西：

- **Exchanges** 标签页 → 交换机列表
- **Queues** 标签页 → 队列与消息堆积
- **Connections** 标签页 → 谁连着

---

## 7. 与项目真实环境的区别（必须知道）

这套环境是**学习用**，不是项目环境：

| 项 | 学习环境 | 项目环境 |
|---|---|---|
| compose 文件 | `docker-compose.yml`（本目录） | `docker-compose-baomi.yml`（需向团队获取） |
| PHP 版本 | 8.2 | 团队模板提到 **7.3**，需确认 |
| 容器名 | `learn-php` | `app-php73` / `app-php82` |
| 代码挂载 | `php/week08/` | 各业务仓库 |

所以：

- 六条标准可以用这套环境**真实完成**；
- 但「启动项目环境」这一条，仍要等团队给 compose 文件；
- 不要在文档或配置里把学习环境的端口当成项目端口。

---

## 8. 清理

```bash
cd php/week08/docker
docker compose down          # 停容器，保留数据
docker compose down -v       # 连 MySQL 数据一起删
docker rmi learn-env-php     # 删构建出来的镜像（可选）
```

日志文件 `php/week08/docker/logs/` 会留在本机，可手动删。