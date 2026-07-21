# 重置本机官方 MySQL root 密码（macOS）

> 适用：本机通过 **Oracle/MySQL 官方安装包** 安装的实例（路径形如 `/usr/local/mysql`）  
> 场景：`mysql -u root` 报 `ERROR 1045 (28000): Access denied ... (using password: NO)`，且密码已遗忘  
> 相关：[macos-homebrew-mysql.md](./macos-homebrew-mysql.md) · [README.md](./README.md)

---

## 0. 重要说明

| 项 | 说明 |
|----|------|
| 操作性质 | **改认证数据**，需短暂停服 |
| 适用 | 本机开发库、可接受停服、已备份或无重要数据 |
| 不适用 | 未备份的重要数据、多人共用且无变更窗口的环境 |
| 文档是否自动执行 | **否**。本文只提供命令；执行前请自行确认 |

### 0.1 你这台机器上的典型路径（按实际排查结果）

| 项 | 路径 / 值 |
|----|-----------|
| 官方 basedir | `/usr/local/mysql` |
| 官方 datadir | `/usr/local/mysql/data` |
| 官方 mysqld | `/usr/local/mysql/bin/mysqld` |
| 官方客户端 | `/usr/local/mysql/bin/mysql` |
| LaunchDaemon | `/Library/LaunchDaemons/com.oracle.oss.mysql.mysqld.plist` |
| 版本示例 | MySQL Community Server **9.5.0**（以 `mysqld --version` 为准） |
| Homebrew 客户端 | `/opt/homebrew/bin/mysql`（可能与官方实例共用连接入口，易混淆） |
| Homebrew 数据 | `/opt/homebrew/var/mysql`（**另一套实例**，重置官方 root **不会**改这套） |

### 0.2 为何会 1045？

常见组合：

1. 官方 `mysqld` 已在跑，且 root **有密码**
2. 你执行 `mysql -u root`（`using password: NO`）→ 被拒绝
3. 若同时装了 Homebrew MySQL，它可能因 **3306 被官方占用** 而启动失败，但 `brew services` 仍显示异常/反复拉起

重置前建议先分清：**你到底要登录哪一套**。

```bash
ps aux | grep '[m]ysqld'
# 官方示例：
# _mysql ... /usr/local/mysql/bin/mysqld --datadir=/usr/local/mysql/data ...
```

---

## 1. 前置检查

```bash
# 谁在跑
ps aux | grep '[m]ysqld'

# 官方客户端版本
/usr/local/mysql/bin/mysql --version

# 尝试密码登录（若突然想起来，可跳过全文重置）
mysql -u root -p
# 或
/usr/local/mysql/bin/mysql -u root -p
```

还能登录则 **不要** 做 skip-grant-tables，直接改密码即可：

```sql
ALTER USER 'root'@'localhost' IDENTIFIED BY '你的新密码';
FLUSH PRIVILEGES;
```

---

## 2. 备份数据目录（强烈建议）

```bash
# 备份目录（按日期）
BACKUP_DIR=~/mysql-backup-$(date +%Y%m%d)
mkdir -p "$BACKUP_DIR"

# 更稳：先停服（见第 3 节）后再拷；若先拷一份也可以
sudo ditto /usr/local/mysql/data "$BACKUP_DIR/mysql-data"
```

确认磁盘空间足够（`data` 目录可能很大）。

---

## 3. 停掉所有 MySQL

避免官方与 Homebrew 互相抢端口、临时实例起不来。

```bash
# 3.1 停 Homebrew（若已安装）
brew services stop mysql 2>/dev/null || true
brew services stop mysql@8.4 2>/dev/null || true

# 3.2 停官方 LaunchDaemon
sudo launchctl unload -w /Library/LaunchDaemons/com.oracle.oss.mysql.mysqld.plist

# 3.3 确认无 mysqld
ps aux | grep '[m]ysqld'
```

若仍有进程：

```bash
# 将 <PID> 换成上面看到的 mysqld PID
sudo kill <PID>
sleep 2
# 仍不退出：
sudo kill -9 <PID>
ps aux | grep '[m]ysqld'   # 应无 mysqld
```

可选：停服后再备份一次 `data`（一致性更好）。

---

## 4. 以跳过权限表方式启动（临时、仅本机）

**安全要点：**

- 必须加 `--skip-networking`（禁止 TCP，降低被局域网误连风险）
- 用独立 socket / pid / 错误日志，避免和正常实例文件搅在一起
- **用完必须关掉**，不可长期运行

```bash
sudo /usr/local/mysql/bin/mysqld \
  --user=_mysql \
  --basedir=/usr/local/mysql \
  --datadir=/usr/local/mysql/data \
  --skip-grant-tables \
  --skip-networking \
  --pid-file=/tmp/mysqld-skip-grant.pid \
  --socket=/tmp/mysql-skip-grant.sock \
  --log-error=/tmp/mysqld-skip-grant.err &
```

等待 2–3 秒后检查：

```bash
ps aux | grep '[m]ysqld'
tail -n 50 /tmp/mysqld-skip-grant.err
ls -la /tmp/mysql-skip-grant.sock
```

若启动失败：先读 `/tmp/mysqld-skip-grant.err`，确认无其它 mysqld、datadir 权限是否异常。

---

## 5. 无密码进入并重设 root

```bash
/usr/local/mysql/bin/mysql -u root --socket=/tmp/mysql-skip-grant.sock
```

在 `mysql>` 提示符下执行（**把密码换成你自己的**）：

```sql
FLUSH PRIVILEGES;

ALTER USER 'root'@'localhost' IDENTIFIED BY '你的新密码';

-- 若报认证插件相关错误，可改用：
-- ALTER USER 'root'@'localhost' IDENTIFIED WITH caching_sha2_password BY '你的新密码';

-- 若存在 127.0.0.1 账号且也要统一密码：
-- ALTER USER 'root'@'127.0.0.1' IDENTIFIED BY '你的新密码';

SELECT user, host, plugin FROM mysql.user WHERE user = 'root';

EXIT;
```

### 5.1 仅 MySQL 5.7 老语法备忘（本机若是 8/9 一般用上面 `ALTER USER`）

```sql
FLUSH PRIVILEGES;
SET PASSWORD FOR 'root'@'localhost' = PASSWORD('你的新密码');
-- 或
UPDATE mysql.user SET authentication_string = PASSWORD('你的新密码')
WHERE User = 'root' AND Host = 'localhost';
FLUSH PRIVILEGES;
```

9.x 请优先 `ALTER USER`，不要依赖已移除/废弃的 `PASSWORD()` 写法。

---

## 6. 关闭临时实例，恢复正常服务

```bash
# 6.1 结束 skip-grant 实例
sudo kill "$(cat /tmp/mysqld-skip-grant.pid)"
sleep 2
ps aux | grep '[m]ysqld'   # 应没有 mysqld

# 若 pid 文件不存在但进程还在：
# sudo kill <PID>

# 6.2 重新加载官方服务
sudo launchctl load -w /Library/LaunchDaemons/com.oracle.oss.mysql.mysqld.plist
sleep 3
ps aux | grep '[m]ysqld'
```

应再次看到 `/usr/local/mysql/bin/mysqld` 在运行。

---

## 7. 验证登录

```bash
mysql -u root -p
# 输入新密码后：
SELECT VERSION(), @@datadir, USER(), CURRENT_USER();
```

期望：

| 检查 | 期望 |
|------|------|
| 能登录 | 不再 1045 |
| `@@datadir` | `/usr/local/mysql/data/`（或等价路径） |
| 版本 | 与官方包一致（如 9.5.x） |

### 7.1 导入本教程实验库

```bash
cd /path/to/AI-Full-Stack-Engineer

mysql -u root -p < mysql/labs/01_schema.sql
mysql -u root -p shop_lab < mysql/labs/02_seed.sql
mysql -u root -p -e "USE shop_lab; SHOW TABLES; SELECT COUNT(*) FROM users;"
```

---

## 8. 故障排查

| 现象 | 处理 |
|------|------|
| 第 4 步临时实例起不来 | 读 `/tmp/mysqld-skip-grant.err`；确认已杀光其它 mysqld；检查 `/usr/local/mysql/data` 权限属主是否为 `_mysql` |
| `ALTER USER` 失败 | 先 `FLUSH PRIVILEGES;`；再执行；检查是否连到 skip-grant 的 socket |
| 正常启动后仍 1045 | 确认改的是 `'root'@'localhost'`；检查是否还有 `'root'@'127.0.0.1'`；确认没有连错实例 |
| `brew services` 仍 error | 官方占 3306 时 Homebrew 起不来属预期；`brew services stop mysql` 即可，或见共存方案 |
| 想改用 Homebrew 为主 | 重置/备份后停官方服务，再 `brew services start mysql`，见 [macos-homebrew-mysql.md](./macos-homebrew-mysql.md) |

### 8.1 两套 MySQL 共存时的习惯

```bash
# 看进程判断「当前权威实例」
ps aux | grep '[m]ysqld'

# 显式指定客户端（减少 PATH 混淆）
/usr/local/mysql/bin/mysql -u root -p
/opt/homebrew/bin/mysql -u root -h 127.0.0.1 -P 3307   # 若 Homebrew 改过端口
```

---

## 9. 安全收尾清单

- [ ] 临时 `--skip-grant-tables` 进程已结束  
- [ ] 官方 LaunchDaemon 已正常 load，业务用正常鉴权登录  
- [ ] 新密码只存在本地密码管理器，**未**写入 Git 仓库  
- [ ] 学习用密码勿与生产复用  
- [ ] 若不再需要 Homebrew 实例，保持 `brew services stop`，避免日志刷屏  

---

## 10. 可选：重置成功后改用 Homebrew

若目标是「只保留 Homebrew 一套」：

1. 确认官方库无要迁数据，或已 `mysqldump` 导出  
2. `sudo launchctl unload -w /Library/LaunchDaemons/com.oracle.oss.mysql.mysqld.plist`  
3. `brew services start mysql`  
4. `mysql -u root`（Homebrew 新装常见无密码，以 caveats 为准）  
5. 再导入 `mysql/labs/*.sql`  

详见：[macos-homebrew-mysql.md](./macos-homebrew-mysql.md)

---

## 11. 完整命令速抄（熟练后）

```bash
# 备份
sudo ditto /usr/local/mysql/data ~/mysql-backup-$(date +%Y%m%d)/mysql-data

# 全停
brew services stop mysql 2>/dev/null || true
sudo launchctl unload -w /Library/LaunchDaemons/com.oracle.oss.mysql.mysqld.plist
ps aux | grep '[m]ysqld'

# 临时启动
sudo /usr/local/mysql/bin/mysqld \
  --user=_mysql \
  --basedir=/usr/local/mysql \
  --datadir=/usr/local/mysql/data \
  --skip-grant-tables \
  --skip-networking \
  --pid-file=/tmp/mysqld-skip-grant.pid \
  --socket=/tmp/mysql-skip-grant.sock \
  --log-error=/tmp/mysqld-skip-grant.err &

# 改密
/usr/local/mysql/bin/mysql -u root --socket=/tmp/mysql-skip-grant.sock
# FLUSH PRIVILEGES;
# ALTER USER 'root'@'localhost' IDENTIFIED BY '你的新密码';
# EXIT;

# 恢复
sudo kill "$(cat /tmp/mysqld-skip-grant.pid)"
sudo launchctl load -w /Library/LaunchDaemons/com.oracle.oss.mysql.mysqld.plist
mysql -u root -p -e "SELECT VERSION(), @@datadir;"
```

---

## 12. 相关文档

- [macos-homebrew-mysql.md](./macos-homebrew-mysql.md) — Homebrew 安装、端口冲突、排错  
- [README.md](./README.md) — 7 天教程与 labs 导入  
- [day01.md](./day01.md) — 环境与 CRUD  

**以你本机 `ps`、`@@datadir`、错误日志为准；路径因安装方式可能略有差异。**
