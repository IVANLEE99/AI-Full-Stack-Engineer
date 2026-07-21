# macOS Homebrew 安装与使用 MySQL（详细说明）

> 适用：macOS + [Homebrew](https://brew.sh)  
> 用途：完成本仓库 [7 天掌握 MySQL](./README.md) 教程的本地环境  
> 文档基准时间：2026-07（包版本以你本机 `brew info` 为准）  
> 相关：`mysql/README.md` 第 4 节「环境准备」的展开版

---

## 0. 先读结论（30 秒）

| 问题 | 建议 |
|------|------|
| 最短命令能跑起来吗？ | 能：`brew install mysql && brew services start mysql && mysql -u root` |
| 学本教程选哪个版本？ | **优先 `mysql@8.4`（LTS 思路）**；默认 `mysql` 当前是 **9.x**，语法大多兼容，但和线上 8.x 更接近时选 8.4 |
| 新装 root 有密码吗？ | Homebrew 公式说明：**默认无 root 密码**，先用 `mysql -u root`（不要加 `-p`） |
| 和 Docker 怎么选？ | 本机长期练 SQL → Homebrew；要隔离/多版本/与 CI 一致 → Docker |
| Apple Silicon 路径 | 常见前缀 `/opt/homebrew` |
| Intel Mac 路径 | 常见前缀 `/usr/local` |

下文所有路径以 **Apple Silicon（`/opt/homebrew`）** 为主；Intel 请把前缀换成 `/usr/local`。

---

## 1. 什么是 Homebrew？它如何装 MySQL？

**Homebrew** 是 macOS（也支持 Linux）上的包管理器。安装 MySQL 时它会：

1. 下载 **formula**（配方）定义的源码或 bottle（预编译包）
2. 安装到 Cellar，并（默认 formula）symlink 到 `$(brew --prefix)/bin`
3. 初始化数据目录（常见：`$(brew --prefix)/var/mysql`）
4. 提供 `brew services` 用 launchd 托管 `mysqld` 后台运行

你得到的是：

| 组件 | 作用 | 常见位置（Apple Silicon） |
|------|------|---------------------------|
| `mysql` | 客户端 CLI | `/opt/homebrew/bin/mysql` |
| `mysqld` | 服务端进程 | `/opt/homebrew/opt/mysql/bin/mysqld` |
| 数据目录 datadir | 库表文件 | `/opt/homebrew/var/mysql` |
| 日志等 | 错误日志等 | 同 datadir 或 formula caveats 说明 |
| 配置 | 可选自定义 | `$(brew --prefix)/etc/my.cnf` 等 |

**不要**与官方 DMG、MAMP、Docker 里的 MySQL **同时抢 3306 端口**，否则启动失败或连错实例。

---

## 2. 前置条件

### 2.1 安装 Homebrew（若尚未安装）

官方一键脚本（以 [brew.sh](https://brew.sh) 当前说明为准）：

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

安装结束后，**Apple Silicon** 通常需要把 brew 加入 PATH（安装脚本会提示，示例）：

```bash
echo 'eval "$(/opt/homebrew/bin/brew shellenv)"' >> ~/.zprofile
eval "$(/opt/homebrew/bin/brew shellenv)"
```

验证：

```bash
brew --version
brew doctor   # 有警告先读懂；不一定全是错误
```

### 2.2 命令行工具

编译依赖有时需要 Xcode Command Line Tools：

```bash
xcode-select --install
```

### 2.3 冲突软件检查

Homebrew 的 `mysql` 与下列 formula **冲突**（会装同一批二进制名）：

- `mariadb`
- `percona-server`

检查：

```bash
brew list --formula | grep -E 'mysql|mariadb|percona' || true
lsof -iTCP:3306 -sTCP:LISTEN || true
```

若 3306 已被占用，先停掉占用方，或改 MySQL 端口（见第 9 节）。

### 2.4 查看当前可装版本（推荐每次安装前执行）

```bash
brew update
brew info mysql
brew info mysql@8.4
```

**本机实测参考（2026-07，会过时）：**

| Formula | 版本示例 | 说明 |
|---------|----------|------|
| `mysql`（别名 `mysql@9.7`） | 9.7.x | 默认最新主线，symlink 到 PATH |
| `mysql@8.4` | 8.4.x | **keg-only**（不自动进 PATH），更贴近许多生产 8.x |

> 教程与 [mysql-pro-agent](./mysql-pro-agent.md) 按 **MySQL 8.0+/8.4 LTS** 叙述。学习优先 8.4；若你接受 9.x 语法差异风险，用默认 `mysql` 更省事。

---

## 3. 方案 A：安装默认 `mysql`（当前多为 9.x）

### 3.1 安装

```bash
brew update
brew install mysql
```

安装结束请阅读终端里的 **Caveats**（注意事项），通常包括：

- 其他位置的 `/etc/my.cnf` 可能干扰启动
- 默认 **无 root 密码**
- 默认主要允许本机连接
- 如何 `brew services start mysql`
- 从很老的版本跨大版本升级的路径提示

### 3.2 启动（推荐：services）

```bash
# 现在启动，并在登录后自动拉起
brew services start mysql

# 查看状态
brew services list | grep mysql
```

状态含义直觉：

| Status | 含义 |
|--------|------|
| `started` | 服务在跑 |
| `none` / 未列出 | 未用 brew services 托管或未装 |
| `error` | 启动失败，看错误日志（第 10 节） |

### 3.3 不通过 services、前台/手动启动（可选）

```bash
# Apple Silicon 示例路径（以 brew info 输出为准）
$(brew --prefix mysql)/bin/mysqld_safe --datadir="$(brew --prefix)/var/mysql"
```

一般学习场景 **优先 `brew services`**。

### 3.4 首次连接

Homebrew 新装常见写法：

```bash
# 无密码时不要加 -p，否则会提示输入密码（直接回车有时也能进，但易混淆）
mysql -u root

# 验证
SELECT VERSION();
STATUS;
SHOW DATABASES;
exit
```

若你曾设置过密码：

```bash
mysql -u root -p
```

### 3.5（建议）基础加固：`mysql_secure_installation`

仅在你打算在本机长期当「准开发库」时执行：

```bash
mysql_secure_installation
```

向导会问：是否设 root 密码、是否移除匿名用户、是否禁止 root 远程登录、是否删 test 库等。  
**纯本地刷题** 可稍后做；设完密码后客户端改为 `mysql -u root -p`。

---

## 4. 方案 B：安装 `mysql@8.4`（推荐跟本教程）

### 4.1 为何 keg-only？

`mysql@8.4` 是 **alternate version**：Homebrew **不会**默认把它链到 `/opt/homebrew/bin`，以免和默认 `mysql`（9.x）抢命令名。

### 4.2 安装与启动

```bash
brew update
brew install mysql@8.4
brew services start mysql@8.4
brew services list | grep mysql
```

### 4.3 把 8.4 客户端放进 PATH（zsh 示例）

临时（当前终端有效）：

```bash
export PATH="$(brew --prefix mysql@8.4)/bin:$PATH"
mysql -u root
SELECT VERSION();   -- 应类似 8.4.x
```

永久（写入 `~/.zshrc`）：

```bash
echo 'export PATH="$(brew --prefix mysql@8.4)/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc
which mysql
mysql --version
```

也可用 brew 提示的 `brew link` 方式（若提示允许且你确认不与 9.x 冲突）：

```bash
# 谨慎：可能与已安装的 mysql 9 冲突
brew link mysql@8.4 --force --overwrite
```

更稳妥的做法是：**只装一个主版本**，或 8.4 仅靠 PATH 前缀切换，不 force link。

### 4.4 同时装了 9 与 8.4 时

- 两个 services 名称不同：`mysql` vs `mysql@8.4`
- **不能**同时都监听 3306
- 停掉一个再开另一个：

```bash
brew services stop mysql
brew services start mysql@8.4
```

或反过来。用 `lsof -iTCP:3306 -sTCP:LISTEN` 确认只有一个监听者。

---

## 5. 日常运维命令速查

```bash
# 启动 / 停止 / 重启
brew services start mysql          # 或 mysql@8.4
brew services stop mysql
brew services restart mysql

# 服务列表
brew services list

# 客户端
mysql -u root
mysql -u root -p
mysql -u root -h 127.0.0.1 -P 3306

# 版本与路径
mysql --version
brew --prefix mysql
brew --prefix mysql@8.4
ls "$(brew --prefix)/var/mysql"

# 升级 formula（先停服务、读 caveats、备份）
brew services stop mysql
brew upgrade mysql
brew services start mysql
```

### 5.1 跨大版本升级注意（来自 formula caveats 摘要）

从 **MySQL &lt; 8.4** 直接升到 **MySQL &gt; 9.0** 时，官方 caveats 要求 **先经过 8.4 跑一轮**：

```text
brew services stop mysql
brew install mysql@8.4
brew services start mysql@8.4
# …确认可连接、必要时跑升级程序 …
brew services stop mysql@8.4
brew services start mysql   # 新主版本
```

本机若是 **全新安装**，无历史 datadir，可忽略「升级路径」，但仍建议：**先定版本再灌 labs 数据**，减少来回迁。

---

## 6. 导入本教程实验库（shop_lab）

在仓库根目录或 `mysql/` 目录执行（路径按你当前位置改）：

```bash
# 假设当前在仓库根：AI-Full-Stack-Engineer
cd /path/to/AI-Full-Stack-Engineer

# 无密码
mysql -u root < mysql/labs/01_schema.sql
mysql -u root shop_lab < mysql/labs/02_seed.sql

# 有密码
mysql -u root -p < mysql/labs/01_schema.sql
mysql -u root -p shop_lab < mysql/labs/02_seed.sql
```

验证：

```sql
mysql -u root -e "USE shop_lab; SHOW TABLES; SELECT COUNT(*) AS users FROM users; SELECT COUNT(*) AS orders FROM orders;"
```

期望：存在 `users`、`products`、`orders`、`order_items`、`order_addresses` 等表，且 seed 后有行数。

练习脚本（可选打开对照）：

```bash
mysql -u root shop_lab < mysql/labs/03_practice.sql
# 该文件含注释与参考答案，更适合在客户端里分段执行
```

---

## 7. 配置文件（可选）

Homebrew MySQL 常通过以下位置生效（以 `brew info` / 实际文件为准）：

| 文件 | 用途 |
|------|------|
| `$(brew --prefix)/etc/my.cnf` | 全局自定义 |
| `$(brew --prefix)/etc/my.cnf.d/*.cnf` | 片段配置（若存在） |
| 数据目录内自动文件 | 实例运行时文件 |

最小示例（**改完需 restart**）：

```ini
# $(brew --prefix)/etc/my.cnf
[mysqld]
port=3306
bind-address=127.0.0.1
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci

# 学习用可略放宽，生产勿照抄
# max_connections=200

[client]
default-character-set=utf8mb4
```

```bash
brew services restart mysql   # 或 mysql@8.4
```

**警告：** 系统里若存在 `/etc/my.cnf` 或 `/etc/mysql/my.cnf`（其他安装器留下），可能干扰 Homebrew 实例，表现为「装了却启不来 / 连错配置」。冲突时先排查这些文件。

---

## 8. 字符集与连接参数（学习向）

本教程 labs 使用 **utf8mb4**。建议确认：

```sql
SHOW VARIABLES LIKE 'character_set%';
SHOW VARIABLES LIKE 'collation%';
```

客户端连接指定：

```bash
mysql -u root --default-character-set=utf8mb4
```

---

## 9. 改端口 / 多实例（进阶）

当 3306 被占用又不想杀进程时：

```ini
# my.cnf
[mysqld]
port=3307
[client]
port=3307
```

连接：

```bash
mysql -u root -P 3307 -h 127.0.0.1
```

GUI（TablePlus / DBeaver）里 Host=`127.0.0.1`、Port=`3307`、User=`root`。

---

## 10. 故障排查清单

### 10.1 `Can't connect to local MySQL server through socket`

常见原因：服务没起来、socket 路径不一致、连错实例。

```bash
brew services list | grep mysql
brew services restart mysql
ls "$(brew --prefix)/var/mysql"/*.sock 2>/dev/null || true
mysql -u root -h 127.0.0.1 -P 3306
```

用 TCP（`127.0.0.1`）可绕过默认 socket 路径问题，便于判断是「服务没起」还是「socket 路径不对」。

### 10.2 服务 `error` / 立刻退出

```bash
# 查看 launchd / brew 服务日志（路径因系统而异，可先）
brew services info mysql
# 到 datadir 找 *.err
ls -lt "$(brew --prefix)/var/mysql" | head
# 用 tail 看最近错误
tail -n 80 "$(brew --prefix)/var/mysql"/$(hostname).err 2>/dev/null \
  || tail -n 80 "$(brew --prefix)/var/mysql"/*.err 2>/dev/null
```

高频原因：

- 3306 被占用
- datadir 权限被改坏
- 残留的 `/etc/my.cnf` 配置不兼容
- 磁盘满
- 不完整升级后的数据目录

### 10.3 `Access denied for user 'root'@'localhost'`

- 密码已设置却没加 `-p`
- 或曾经装过旧 MySQL / **官方 DMG 与 Homebrew 并存**，连到了带密码的那一套
- Homebrew 因 3306 被占用未启动，客户端实际打到官方实例

处理方向：

1. `ps aux | grep '[m]ysqld'` 确认当前权威实例与 datadir  
2. 有密码：`mysql -u root -p`  
3. **官方包密码遗忘**：按 [reset-official-mysql-root.md](./reset-official-mysql-root.md) 重置（先备份 datadir）  
4. 仅想用 Homebrew：先停官方服务再 `brew services start mysql`（见上文端口冲突）

### 10.4 `command not found: mysql`

```bash
which brew
echo "$PATH"
# 默认 mysql
ls "$(brew --prefix)/bin/mysql"
# 8.4 keg-only
ls "$(brew --prefix mysql@8.4)/bin/mysql"
export PATH="$(brew --prefix mysql@8.4)/bin:$PATH"   # 若用 8.4
```

### 10.5 与 MariaDB / Docker 混淆

```bash
which -a mysql
mysql --version
docker ps --filter publish=3306
```

版本字符串含 `MariaDB` 或连到 Docker 映射端口时，说明 **不是** 你以为的那个 Homebrew MySQL。

### 10.6 安装失败 / bottle 拉取失败

```bash
brew update
brew doctor
brew install mysql -v    # 详细日志
```

网络问题需自备镜像或代理；属环境问题，与 SQL 教程无关。

---

## 11. 停止、卸载与清理

### 11.1 仅停止

```bash
brew services stop mysql
# 或
brew services stop mysql@8.4
```

### 11.2 卸载 formula（保留或删除数据请自觉）

```bash
brew services stop mysql
brew uninstall mysql

# 8.4
brew services stop mysql@8.4
brew uninstall mysql@8.4
```

**数据目录默认不会总被自动删光。** 若确定不要本地库：

```bash
# 危险：删除后数据不可恢复
# 先确认路径
echo "$(brew --prefix)/var/mysql"
# 再手动 rm -rf（务必三思）
```

### 11.3 卸干净后的检查

```bash
brew list | grep mysql || true
lsof -iTCP:3306 -sTCP:LISTEN || true
```

---

## 12. Homebrew vs Docker（怎么选）

| 维度 | Homebrew | Docker（`mysql:8.4` 镜像） |
|------|----------|----------------------------|
| 安装体验 | 与 macOS 集成好，`mysql` CLI 直接用 | 需 Docker Desktop / Colima 等 |
| 版本切换 | formula / keg-only | 换镜像 tag |
| 隔离性 | 中（共享本机端口与文件） | 强 |
| 性能 | 原生进程，通常更轻 | 桌面虚拟化有开销 |
| 数据 | `var/mysql` | volume / named volume |
| CI 一致性 | 一般 | 更好 |
| 本教程 | README 默认示例之一 | README 备选示例 |

Docker 最小对照（来自教程 README）：

```bash
docker run --name mysql8 \
  -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=shop_lab \
  -p 3306:3306 -d mysql:8.4

docker exec -i mysql8 mysql -uroot -proot < mysql/labs/01_schema.sql
# seed 时注意 01_schema 已 CREATE DATABASE，按需调整
```

**不要** Homebrew 与 Docker 同时占 3306。

---

## 13. 与 GUI 客户端连接

| 工具 | Host | Port | User | Password | 备注 |
|------|------|------|------|----------|------|
| TablePlus / DBeaver / Workbench | `127.0.0.1` | `3306` | `root` | 空或你设的密码 | 优先 TCP，少踩 socket 坑 |
| VS Code 插件 | 同上 | 同上 | 同上 | 同上 | 选 MySQL 8/9 协议 |

建议 Host 填 **`127.0.0.1`** 而不是 `localhost`（后者在部分客户端会走 socket）。

---

## 14. 推荐验收清单（装完打勾）

- [ ] `brew services list` 中目标 mysql 为 `started`
- [ ] `mysql -u root`（或 `-p`）能进客户端
- [ ] `SELECT VERSION();` 为大版本 8.4.x 或 9.x（符合你的选择）
- [ ] `SHOW VARIABLES LIKE 'character_set_server';` 含 `utf8mb4`（或连接级可指定）
- [ ] 已导入 `labs/01_schema.sql` + `02_seed.sql`
- [ ] `USE shop_lab; SHOW TABLES;` 看到 5 张业务表
- [ ] 已知如何 `stop` / `restart`
- [ ] 已知 3306 冲突时如何排查

---

## 15. 完整最短路径抄写版

### 15.1 默认 mysql（快）

```bash
brew update
brew install mysql
brew services start mysql
mysql -u root -e "SELECT VERSION();"
cd /path/to/AI-Full-Stack-Engineer
mysql -u root < mysql/labs/01_schema.sql
mysql -u root shop_lab < mysql/labs/02_seed.sql
mysql -u root shop_lab -e "SHOW TABLES;"
```

### 15.2 mysql@8.4（贴合教程）

```bash
brew update
brew install mysql@8.4
brew services start mysql@8.4
export PATH="$(brew --prefix mysql@8.4)/bin:$PATH"
mysql -u root -e "SELECT VERSION();"
cd /path/to/AI-Full-Stack-Engineer
mysql -u root < mysql/labs/01_schema.sql
mysql -u root shop_lab < mysql/labs/02_seed.sql
```

### 15.3 README 里的三行在完整流程中的位置

```bash
brew install mysql          # 下载安装服务端+客户端
brew services start mysql   # launchd 托管后台 mysqld
mysql -u root -p            # 连接；新装常无密码，可先去掉 -p
```

| 命令 | 做什么 | 不做会怎样 |
|------|--------|------------|
| `brew install mysql` | 安装二进制与初始化 datadir | 系统无 `mysqld`/`mysql` |
| `brew services start mysql` | 启动并（通常）注册登录自启 | 客户端报无法连接 |
| `mysql -u root -p` | 打开交互客户端 | 无法执行 SQL / 导入 labs |

---

## 16. 安全提醒（本地学习）

- 默认仅本机连接仍 **不要** 把 root 无密码实例暴露到公网
- 勿在教程库练习 `DROP DATABASE mysql;` 等系统库破坏操作
- 生产变更、删库、提权不在本文范围；对齐 [mysql-pro-agent](./mysql-pro-agent.md) 安全规则
- 公司笔记本若有统一数据策略，先遵守公司规范再装本地库

---

## 17. 相关链接

- 本教程总览：[README.md](./README.md)
- Day01 环境与 CRUD：[day01.md](./day01.md)
- 实验脚本：[labs/](./labs/)
- Homebrew：[https://brew.sh](https://brew.sh)
- MySQL 官方文档：[https://dev.mysql.com/doc/](https://dev.mysql.com/doc/)
- 查看本机公式说明：`brew info mysql` / `brew info mysql@8.4`

---

## 18. 文档维护

若 `brew info mysql` 的默认大版本变化，请更新：

1. 本文第 0、2.4 节版本表  
2. [README.md](./README.md) 环境准备中的版本提示  
3. 你自己的「选 8.4 还是 9」决定  

**以本机 `brew info` 与 `SELECT VERSION()` 为准，不以本文缓存版本号为权威。**
