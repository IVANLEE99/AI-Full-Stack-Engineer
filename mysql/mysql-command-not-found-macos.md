# macOS：`mysql: command not found` 排查与修复

> 场景：终端输入 `mysql` 报 `zsh: command not found: mysql`，但 Navicat 可能仍能连上数据库  
> 适用：macOS + zsh  
> 相关：[reset-official-mysql-root.md](./reset-official-mysql-root.md) · [macos-homebrew-mysql.md](./macos-homebrew-mysql.md) · [tools/navicat-decrypt-usage.md](./tools/navicat-decrypt-usage.md)

---

## 0. 先读结论（30 秒）

| 现象 | 常见原因 |
|------|----------|
| `command not found: mysql` | **客户端已安装，但未加入 PATH** |
| Navicat 能连、终端不能 | Navicat 不依赖终端里的 `mysql` 命令 |
| 本机官方包常见路径 | `/usr/local/mysql/bin/mysql` |
| Homebrew 常见路径 | `/opt/homebrew/bin/mysql`（Apple Silicon） |

**一句话：** 不是数据库一定没装，而是 shell 找不到 `mysql` 可执行文件。

---

## 1. 报错示例

```bash
mysql
# zsh: command not found: mysql

mysql -u root -p
# zsh: command not found: mysql
```

---

## 2. 第一步：确认客户端装在哪

在终端依次执行：

```bash
# 是否在 PATH 里
which mysql

# 官方 MySQL 安装包（常见）
ls -la /usr/local/mysql/bin/mysql

# Homebrew（Apple Silicon 常见）
ls -la /opt/homebrew/bin/mysql

# Homebrew（Intel 常见）
ls -la /usr/local/bin/mysql
```

### 2.1 本机排查结果示例（官方包）

若看到：

```text
/usr/local/mysql/bin/mysql
```

说明客户端存在，只是没进 PATH。可验证版本：

```bash
/usr/local/mysql/bin/mysql --version
# 示例：Ver 8.0.27 for macos11 on x86_64
```

---

## 3. 临时使用（立刻可用）

不写配置文件，直接用完整路径：

```bash
/usr/local/mysql/bin/mysql -u root -p
```

Homebrew 安装时：

```bash
/opt/homebrew/bin/mysql -u root -p
```

---

## 4. 永久修复：加入 PATH（推荐）

### 4.1 官方 MySQL 安装包

```bash
echo 'export PATH="/usr/local/mysql/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc
```

验证：

```bash
mysql --version
which mysql
# 应输出：/usr/local/mysql/bin/mysql
```

### 4.2 Homebrew 默认 `mysql`

```bash
echo 'export PATH="$(brew --prefix)/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc
```

### 4.3 Homebrew `mysql@8.4`（keg-only）

若安装的是 `mysql@8.4`，可能不在默认 PATH：

```bash
echo 'export PATH="$(brew --prefix mysql@8.4)/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc
```

---

## 5. 连接数据库

PATH 修好后：

```bash
mysql -u root -p
```

按提示输入密码。

若密码在 Navicat 里已保存、终端不知道密码，可参考 [tools/navicat-decrypt-usage.md](./tools/navicat-decrypt-usage.md) 解密本地配置，或在 Navicat 查询窗口执行 `ALTER USER` 设一个新密码（见 [reset-official-mysql-root.md](./reset-official-mysql-root.md)）。

---

## 6. Navicat 能连、终端不能：对照检查

| 检查项 | Navicat | 终端 |
|--------|---------|------|
| 是否需要 `mysql` 命令 | 否 | 是 |
| 主机 | 看连接属性 | 默认 `127.0.0.1` 或 `localhost` |
| 端口 | 看连接属性 | 默认 `3306` |
| 用户名 | 看连接属性 | 如 `root` |
| 密码 | 已保存 | 需手动输入或从配置解密 |

**结论：** Navicat 连得上只说明 **MySQL 服务在跑**；`command not found` 只说明 **CLI 未配置 PATH**，两件事互不矛盾。

---

## 7. 若 PATH 修好后仍连不上

### 7.1 确认服务在运行

```bash
ps aux | grep mysqld
```

官方包常见进程：

```text
/usr/local/mysql/bin/mysqld --datadir=/usr/local/mysql/data
```

### 7.2 密码错误（1045）

```text
ERROR 1045 (28000): Access denied for user 'root'@'localhost' (using password: YES)
```

处理见 [reset-official-mysql-root.md](./reset-official-mysql-root.md)。

### 7.3 连错实例（官方 vs Homebrew vs Docker）

```bash
which -a mysql
mysql --version
docker ps --filter publish=3306
```

本机可能同时存在多套 MySQL，Navicat 连的端口/实例与终端默认不一致时会表现为「一个能连一个不能」。

---

## 8. 若确实没有 `mysql` 可执行文件

说明客户端未安装或已卸载，任选一种安装方式：

### 8.1 Homebrew（学教程推荐）

```bash
brew install mysql
brew services start mysql
mysql -u root
```

详见 [macos-homebrew-mysql.md](./macos-homebrew-mysql.md)。

### 8.2 官方安装包

从 [MySQL 官网](https://dev.mysql.com/downloads/mysql/) 下载 macOS 安装包，安装后把 `/usr/local/mysql/bin` 加入 PATH（见上文 4.1）。

---

## 9. 快速命令备忘

```bash
# 1. 找客户端
ls /usr/local/mysql/bin/mysql
ls /opt/homebrew/bin/mysql

# 2. 临时连接（官方包）
/usr/local/mysql/bin/mysql -u root -p

# 3. 永久加入 PATH（官方包）
echo 'export PATH="/usr/local/mysql/bin:$PATH"' >> ~/.zshrc && source ~/.zshrc

# 4. 验证
mysql --version
mysql -u root -p
```

---

## 10. 相关文件

- [reset-official-mysql-root.md](./reset-official-mysql-root.md) — 忘记 root 密码
- [macos-homebrew-mysql.md](./macos-homebrew-mysql.md) — Homebrew 安装与排错
- [tools/navicat-decrypt-usage.md](./tools/navicat-decrypt-usage.md) — Navicat 保存密码解密
- [README.md](./README.md) — 7 天 MySQL 教程总览
