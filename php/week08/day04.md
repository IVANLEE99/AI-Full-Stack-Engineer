# Week 08 Day 04：Docker 开发环境

> 所属周：Week 08：MQ + Webhook + Docker  
> 阶段：第二阶段：网关 + 微服务  
> 主仓库/项目：`pay-service + mall-gateway`  
> 类型：架构理解  
> 建议时长：约 3h  
> 学习方法：PHP 后端主线 + JS/Node.js 类比 + AI Review

---

## 今日目标

配置并使用 Docker 开发环境，能进入 PHP 容器、查看服务日志、理解 PHP 服务、RabbitMQ、Redis、MySQL 等组件为什么需要用容器统一运行。

今天你要真正掌握这一句话：

> Docker 的价值是统一开发环境：让 PHP、Nginx、MySQL、Redis、RabbitMQ 等依赖按同一套配置启动，减少“我本地可以、你本地不行”的问题。

---

## 0. 今日学习路线

建议按下面顺序学习：

1. 先理解为什么微服务/MQ/Webhook 学习需要 Docker 环境
2. 理解容器、镜像、服务、端口映射、挂载目录
3. 阅读本地环境说明文档或 `docker-compose.yml`
4. 启动 Docker 环境
5. 进入 PHP 容器
6. 在容器内执行 PHP/Composer/框架命令
7. 查看 PHP、Nginx、RabbitMQ、队列消费者日志
8. 对比容器 PHP 和本机 PHP 的差异
9. 整理 Docker 操作笔记并让 AI Review 检查

---

## 1. 学习内容

### 1.1 为什么需要 Docker？

当前学习已经涉及：

- PHP 服务
- BFF 网关
- 支付服务
- Webhook
- RabbitMQ
- Redis
- MySQL
- 队列消费者

如果全部手动安装在本机，容易出现：

| 问题 | 表现 |
|---|---|
| PHP 版本不一致 | 你是 8.3，别人是 8.1 |
| 扩展缺失 | 本地没有 redis/amqp 扩展 |
| 配置不同 | MySQL/RabbitMQ 地址不同 |
| 服务启动顺序复杂 | 先启动 DB、MQ，再启动 PHP |
| 排错困难 | 不知道日志在哪 |

Docker 可以把这些依赖统一声明和启动。

---

### 1.2 容器、镜像、服务怎么理解？

| 概念 | 含义 | 类比 |
|---|---|---|
| Image 镜像 | 程序运行模板 | 安装包/快照 |
| Container 容器 | 镜像跑起来的实例 | 正在运行的进程环境 |
| Service 服务 | docker-compose 里定义的一个组件 | php、mysql、rabbitmq |
| Volume 挂载 | 本地目录映射到容器 | 共享代码目录 |
| Port 端口 | 容器端口映射到本机 | 本机访问容器服务 |

例如：

```text
php 镜像 → php 容器 → 执行项目代码
rabbitmq 镜像 → rabbitmq 容器 → 提供 MQ 服务
```

---

### 1.3 `docker-compose.yml` 看什么？

读 Docker 配置时先找：

| 字段 | 你要理解什么 |
|---|---|
| `services` | 有哪些服务 |
| `image` / `build` | 服务从镜像启动还是自己构建 |
| `ports` | 本机端口如何访问容器 |
| `volumes` | 代码和数据目录如何挂载 |
| `environment` | 环境变量 |
| `depends_on` | 服务依赖顺序 |
| `command` | 容器启动命令 |

不要一开始就背 Docker 全部语法，先能看懂项目环境即可。

---

### 1.4 常用 Docker 命令

启动：

```bash
docker compose up -d
```

查看服务：

```bash
docker compose ps
```

进入 PHP 容器：

```bash
docker compose exec php bash
```

查看日志：

```bash
docker compose logs -f php
```

查看 RabbitMQ 日志：

```bash
docker compose logs -f rabbitmq
```

停止：

```bash
docker compose down
```

实际服务名以项目 `docker-compose.yml` 为准，可能叫 `php-fpm`、`app`、`workspace`。

---

### 1.5 在容器内做什么？

进入 PHP 容器后，你可以：

```bash
php -v
composer install
php yii migrate
php yii queue/listen
```

也可以检查扩展：

```bash
php -m
```

重点是：

```text
项目运行时使用的是容器里的 PHP，不一定是你 Mac/Windows 本机的 PHP。
```

所以排查 PHP 版本、扩展、配置时，要进入容器看。

---

### 1.6 如何查看日志？

常见日志来源：

| 日志 | 用途 |
|---|---|
| PHP 应用日志 | 看业务异常 |
| Nginx access/error log | 看 HTTP 请求和网关错误 |
| RabbitMQ 日志 | 看 MQ 连接/队列问题 |
| Consumer 日志 | 看消息消费失败 |
| MySQL 日志 | 看数据库问题 |

命令示例：

```bash
docker compose logs -f php
```

如果日志写到项目目录，也可以在容器或本机查看 `runtime/logs`、`storage/logs` 等目录，具体看框架。

---

### 1.7 容器和本地 PHP 的差异

| 对比项 | 本地 PHP | 容器 PHP |
|---|---|---|
| 版本 | 你本机安装的版本 | 镜像指定版本 |
| 扩展 | 本机扩展 | Dockerfile 安装的扩展 |
| 配置 | 本机 php.ini | 容器 php.ini |
| 路径 | 本机路径 | 容器内路径 |
| 网络 | localhost 是本机 | localhost 是容器自己 |

特别注意：容器内访问 MySQL/RabbitMQ，通常不是 `127.0.0.1`，而是 compose 服务名，如：

```text
mysql
rabbitmq
redis
```

---

### 1.8 Node.js 类比

Node 项目也常用 Docker：

```text
node app container
postgres container
redis container
worker container
```

PHP 项目类似，只是运行时可能是：

```text
nginx + php-fpm + mysql + redis + rabbitmq + queue worker
```

---

## 2. 源码阅读

本日无指定源码阅读，重点完成练习与复盘。

建议阅读：

- `docker-compose.yml`
- `Dockerfile`
- `.env.example`
- 本地环境说明文档
- 日志目录说明

记录：

| 服务 | 容器名/服务名 | 端口 | 用途 |
|---|---|---|---|
| PHP |  |  |  |
| RabbitMQ |  |  |  |
| Redis |  |  |  |
| MySQL |  |  |  |

---

## 3. 练习任务

### 练习 1：启动 Docker 环境

记录命令和结果：

```text
启动命令：
服务列表：
失败信息：
解决方法：
```

### 练习 2：进入 PHP 容器

记录：

```text
进入命令：
php -v 输出：
composer -V 输出：
php -m 是否有 redis/amqp：
```

### 练习 3：在容器内查看日志

至少查看：

- PHP 应用日志
- RabbitMQ 或 queue worker 日志

---

## 4. JS/Node.js 类比

- Docker ≈ 统一开发与部署环境
- compose service ≈ 一个服务进程配置
- PHP 容器 ≈ Node app 容器
- queue worker 容器 ≈ BullMQ worker 容器
- 容器网络 service name ≈ compose 内部 DNS

---

## 5. AI Review 提问

```text
我正在配置 Docker 开发环境。
我已经启动服务、进入 PHP 容器、查看 PHP 版本和日志，并整理了容器服务表。
请你检查：
1. 我对容器和本地环境差异的理解是否正确？
2. PHP 容器里还应该检查哪些扩展？
3. RabbitMQ/Redis/MySQL 的连接地址应该如何理解？
4. 查看日志时还应该关注哪些服务？
5. 真实项目 Docker 环境最常见的问题是什么？
```

---

## 6. 今日产出

- [ ] Docker 操作笔记
- [ ] 服务/端口/用途表
- [ ] PHP 容器检查记录
- [ ] 日志查看记录
- [ ] 容器与本地 PHP 差异笔记
- [ ] AI Review 记录

---

## 7. 今日完成标准

- [ ] 能启动 Docker 环境
- [ ] 能进入 PHP 容器
- [ ] 能查看 PHP 版本和扩展
- [ ] 能查看至少 2 类日志
- [ ] 能说明容器和本地 PHP 的差异
- [ ] 能解释容器内为什么用服务名访问 MySQL/RabbitMQ

### 7.1 你的环境现状（2026-11-03 实测）

**这六条目前一条都勾不了**，原因不在操作，而在环境还没到位：

| 检查项 | 实测结果 |
|---|---|
| `docker ps -a` 里的 PHP 容器 | 无 `app-php73` / `app-php82`；只有一个已退出 7 周的 `mm-redis` |
| `docker compose ls` | 空，没有已注册的 compose 项目 |
| `docker images` | 有 `redis:7` / `redis:7-alpine` / `nginx` / `postgres:15`，**没有 PHP 镜像** |
| `docker-compose-baomi.yml` | 本机没有该文件 |

对照 [php/03-mall-core-internal.md](../03-mall-core-internal.md#L283-L287) 的原文结论：

> 当前仓库未包含可直接使用的 `Dockerfile` 或 `docker-compose.yml`。……**不要在新手文档中承诺「一条 Docker 命令即可启动」。第一次启动前必须确认团队维护的环境仓库或 Compose 文件位置。**

所以第 1 条「能启动 Docker 环境」的**正确动作不是硬敲命令**，而是先拿到团队维护的 compose 文件位置。

### 7.2 已知的真实线索（来自个人环境配置，非本仓库）

团队 AI 工作空间的 `LOCAL_ENV.md` 记录了命名约定，可作为向团队索取配置文件时的对照：

```text
Compose 文件: docker-compose-baomi.yml
PHP7 容器名:  app-php73
PHP82 容器名: app-php82
进入 PHP7:    docker exec -it app-php73 bash
进入 PHP82:   docker exec -it app-php82 bash
```

> 这只是**命名约定**，不代表文件已在本机。注意 [php/03-mall-core-internal.md](../03-mall-core-internal.md#L285) 提到的是 **PHP 7.3 容器**——比你现在学的 Yii2 常见版本更老，接手前先确认目标服务实际跑哪个版本，别默认用较新的那个。

### 7.3 每条标准的可执行判定

拿到 compose 文件后，按下面逐条验证，**有输出才算勾**：

| 标准 | 判定命令 | 通过的样子 |
|---|---|---|
| 启动环境 | `docker compose -f docker-compose-baomi.yml up -d` 然后 `docker compose ps` | 各服务 `State` 为 `running`，不是 `restarting` |
| 进入 PHP 容器 | `docker exec -it app-php82 bash` | 提示符变成容器内的 `#` 或 `$` |
| PHP 版本与扩展 | 容器内 `php -v`、`php -m \| grep -E 'redis\|amqp\|pdo_mysql'` | 版本符合预期；`redis` / `amqp` 扩展**必须存在**（MQ 与缓存都依赖） |
| 两类日志 | `docker compose logs -f php` 与 `docker compose logs -f rabbitmq`；再看 `runtime/logs/` | 能同时看到应用日志与 MQ 日志，且时间戳在推进 |
| 容器 vs 本地 PHP 差异 | 本机 `php -v` 与容器内 `php -v` 各跑一次 | 两个版本号**不一样**，这就是差异的证据。本机实测 `PHP 8.5.8 (cli)`（Homebrew，2026-11-03）；若目标是 `app-php73`，差距是两个大版本 |
| 服务名访问 | 容器内 `getent hosts mysql rabbitmq redis` 或 `ping -c1 mysql` | 能解析出容器 IP；而 `127.0.0.1` 指容器自己，连不到别的服务 |

第 6 条的原理一句话：**compose 会给每个服务建内部 DNS，服务名就是主机名**。容器里的 `localhost` 是容器自身，所以连 MySQL 必须写服务名 `mysql`（或完整服务名，如 `baomi-mysql`，以 compose 文件为准），而不是 `127.0.0.1`。

### 7.4 环境没到位时，今天能做什么

不必空等。这三件事现在就能完成，且直接服务于本周 MQ 主线：

1. 用已有的 `redis:7-alpine` 跑通容器基本操作（`run` / `exec` / `logs` / `stop`），命令与 [redis 一天速成](../../redis/redis入门一天学完.md#2-安装与连接) 一致；
2. 用 `docker run -d -p 5672:5672 -p 15672:15672 rabbitmq:3-management` 起一个**独立** RabbitMQ，登录 `http://localhost:15672`（默认 `guest`/`guest`）看队列与 exchange——这能直接验证 [day01](./day01.md) 里的 exchange / route_key / queue 概念。注意本机**目前没有该镜像**，首次运行会先拉取；且 `guest` 用户默认只允许从 `localhost` 登录，在容器映射端口下通常可用，若报 `login refused` 就加 `-e RABBITMQ_DEFAULT_USER=admin -e RABBITMQ_DEFAULT_PASS=admin` 自建账号；
3. 把「容器 vs 本地 PHP 差异」写成文字：**运行时用的是容器里的 PHP，不是本机 PHP**，所以查版本、查扩展、查 `php.ini` 都要进容器。

> 但第 1 条「启动 Docker 环境」若指**项目 compose 环境**，在上述文件到手前仍不能勾。

---

## 8. 今日自测题

### 8.1 为什么学 MQ / Webhook / 微服务要用 Docker？

参考答案：

> ✅ 因为这些学习会同时用到 PHP、Nginx、MySQL、Redis、RabbitMQ、队列消费者等多个组件。用 Docker 可以按同一套配置统一启动，避免版本不一致、扩展缺失、配置不同、启动顺序复杂等“我本地可以、你本地不行”的问题。

---

### 8.2 镜像（Image）和容器（Container）有什么区别？

参考答案：

> ✅ 镜像是程序运行的模板，类似安装包或快照；容器是镜像跑起来的实例，是正在运行的进程环境。一个镜像可以启动多个容器。

---

### 8.3 进入 PHP 容器后，`php -v` 看到的是哪个 PHP？

参考答案：

> ✅ 是容器里镜像指定的 PHP，不一定是你 Mac/Windows 本机安装的 PHP。项目运行时用的就是容器里的 PHP，所以排查版本、扩展、配置时要进入容器看，可以用 `docker compose exec php bash` 进入。

---

### 8.4 容器内访问 MySQL / RabbitMQ，连接地址应该写什么？

参考答案：

> ✅ 通常不是 `127.0.0.1`，而是 docker-compose 里的服务名，例如 `mysql`、`rabbitmq`、`redis`。因为在容器里 `localhost` 指的是容器自己，compose 会为服务名提供内部 DNS 解析。

---

### 8.5 排查“队列消息没被消费”，应该看哪些日志？

参考答案：

> ✅ 至少看 RabbitMQ 日志（连接/队列问题）和消费者日志（消息消费失败），必要时再看 PHP 应用日志。可以用 `docker compose logs -f rabbitmq` 和 `docker compose logs -f php` 查看，日志也可能写在框架的 `runtime/logs` 或 `storage/logs` 目录。

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
我正在进行 Week 08 Day 04：Docker 开发环境 的学习。
请你扮演资深 PHP 后端工程师，帮我检查：
1. 今日理解是否正确
2. JS/Node 类比是否准确
3. 练习任务是否遗漏关键风险
4. 真实企业项目中还需要注意什么

请用中文输出：问题清单、修正建议、下一步练习。
```

---

## 返回本周

- [返回 Week 08 README](./README.md)
