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

---

## 8. 学习记录

| 记录项 | 内容 |
|--------|------|
| 今日最清楚的概念 |  |
| 今日最卡的概念 |  |
| JS/Node 类比是否帮助理解 |  |
| 实际耗时 |  |
| 明日要补的问题 |  |

---

## 9. AI Review 提示词

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
