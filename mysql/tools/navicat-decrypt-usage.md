# Navicat 保存密码解密工具使用说明

> 适用：本机 Navicat 已保存 MySQL 连接密码，想在命令行或其他工具里复用同一密码  
> 工具路径：[navicat-decrypt.php](./navicat-decrypt.php) · [NavicatPassword.php](./NavicatPassword.php)  
> 相关：[reset-official-mysql-root.md](../reset-official-mysql-root.md)

---

## 0. 重要说明

| 项 | 说明 |
|----|------|
| 用途 | 解密**你自己电脑**上 Navicat 本地保存的连接密码 |
| 支持算法 | Navicat **11**（Blowfish）、**12+**（AES-128-CBC） |
| 不支持 | Navicat 15/16/17 若更换加密方式，本工具可能失效 |
| 安全 | **不要把明文密码提交到 Git**；解密结果仅本地使用 |

MySQL 服务端**无法查看**明文密码；本工具读的是 Navicat **客户端配置文件**里的密文。

---

## 1. 工具文件

```text
mysql/tools/
├── NavicatPassword.php    # 加解密核心类
├── navicat-decrypt.php      # 命令行入口
└── navicat-decrypt-usage.md # 本文档
```

---

## 2. 环境要求

- 已安装 **PHP**（建议 7.4+）
- PHP 扩展 **openssl** 已启用

检查：

```bash
php -v
php -m | grep openssl
```

若 `openssl` 无输出，需先安装/启用 OpenSSL 扩展。

---

## 3. 从 Navicat 找到密文（macOS）

### 3.1 确认已勾选「保存密码」

1. 打开 Navicat
2. 右键连接 → **编辑连接**
3. 确认勾选了 **保存密码**（Save password）

未勾选则配置里可能没有 `Password` 字段。

### 3.2 常见配置文件位置

Navicat 连接信息通常在本机目录（按你安装的 Navicat 版本/Product 略有不同）：

```text
~/Library/Application Support/PremiumSoft CyberTech/Navicat CC/
```

可进一步查找：

```text
~/Library/Application Support/PremiumSoft CyberTech/Navicat CC/Navicat Premium/
~/Library/Application Support/PremiumSoft CyberTech/Navicat CC/Common/
```

部分版本使用：

- `.ncx` 连接导出文件
- `plist` / `json` / **SQLite** 数据库

### 3.3 在配置里找 Password 字段

用文本编辑器或 SQLite 工具打开连接配置，搜索：

- `Password`
- `Pwd`
- `password`

密文特征：

- **大写十六进制**字符串
- 长度多为 16 的倍数，例如：`4F3A2B1C9D8E7F60514253647586970`（示例，请换成本机配置里的密文）

复制时：

- 不要带空格、换行
- 不要带引号（脚本会自动处理空格）

### 3.4 用 SQLite 查看（部分 Navicat 版本）

若连接存在 SQLite 库里，可用：

```bash
# 示例：先找到 .db / .sqlite 文件，再查看表结构
sqlite3 "路径/connections.db" ".tables"
sqlite3 "路径/connections.db" "SELECT * FROM connection LIMIT 1;"
```

表名、字段名因版本而异，以你本机实际文件为准。

---

## 4. 解密命令

在项目根目录执行：

```bash
php mysql/tools/navicat-decrypt.php '你的密文' 12
```

### 4.1 参数说明

| 参数 | 必填 | 说明 |
|------|------|------|
| 第 1 个：密文 | 是 | 从 Navicat 配置复制的十六进制字符串 |
| 第 2 个：版本 | 否 | `11` 或 `12`，默认 `12` |

### 4.2 示例

```bash
# Navicat 12+（AES），最常见
php mysql/tools/navicat-decrypt.php '4F3A2B1C9D8E7F60514253647586970' 12

# Navicat 11（Blowfish）
php mysql/tools/navicat-decrypt.php '你的密文' 11
```

### 4.3 成功输出

```text
版本: 12
密码: your_plain_password
```

### 4.4 自动回退版本

脚本逻辑：

1. 先用你指定的版本（默认 12）解密
2. 失败则自动尝试另一个版本（12 ↔ 11）
3. 都失败则报错退出

因此不确定版本时，可直接：

```bash
php mysql/tools/navicat-decrypt.php '你的密文'
```

---

## 5. 解密后如何使用

### 5.1 命令行连接 MySQL

```bash
mysql -u root -p
# 输入解密得到的密码
```

若使用官方安装路径：

```bash
/usr/local/mysql/bin/mysql -u root -p
```

### 5.2 写入本地 `.env`（应用开发）

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASSWORD=解密得到的密码
```

**务必**把 `.env` 加入 `.gitignore`，不要提交仓库。

### 5.3 在 Navicat 里核对

解密结果应与 Navicat 能成功连接时使用的密码一致。若不一致，见下方排错。

---

## 6. 在 PHP 代码里调用（可选）

```php
<?php

require __DIR__ . '/NavicatPassword.php';

use FatSmallTools\NavicatPassword;

$encrypted = '4F3A2B1C9D8E7F60514253647586970'; // 替换为 Navicat 配置中的密文
$tool = new NavicatPassword(12);
$password = $tool->decrypt($encrypted);

echo $password;
```

加密（一般不需要，仅调试）：

```php
$tool = new NavicatPassword(12);
$cipher = $tool->encrypt('my_password');
echo $cipher;
```

---

## 7. 常见问题

### 7.1 解密失败 / 输出为空

| 可能原因 | 处理 |
|----------|------|
| 密文复制不完整 | 重新复制完整十六进制串 |
| 版本不对 | 分别试 `11` 和 `12` |
| Navicat 版本太新 | 15+ 可能已换算法，考虑重置 MySQL 密码 |
| 连接未保存密码 | 在 Navicat 编辑连接勾选保存密码后重试 |
| OpenSSL 未启用 | `php -m \| grep openssl` 检查 |

### 7.2 解密结果是乱码

- 密文可能不是密码字段（误复制了别的配置）
- 尝试从另一个配置文件/导出文件里找 `Password`

### 7.3 找不到配置文件

1. 在 Finder 中 **前往 → 前往文件夹**，粘贴：

   ```text
   ~/Library/Application Support/PremiumSoft CyberTech/
   ```

2. 或在终端搜索（可能较慢）：

   ```bash
   mdfind -name 'Navicat' 2>/dev/null | head -20
   ```

### 7.4 解密成功但 `mysql -u root -p` 仍失败

可能原因：

- Navicat 连的是 **另一套 MySQL 实例**（如 Docker / Homebrew / 官方包端口不同）
- 用户名不是 `root`（看 Navicat 连接里的用户名）
- 主机不是本机（远程库密码与本地 root 无关）

在 Navicat 连接属性里核对：**主机、端口、用户名**。

---

## 8. 解密失败时的替代方案（推荐）

若工具无法解密，或你只想固定一个已知密码：

在 Navicat 已能连上的前提下，打开查询窗口执行：

```sql
ALTER USER 'root'@'localhost' IDENTIFIED BY '你的新密码';
FLUSH PRIVILEGES;
```

然后在 Navicat 连接里更新为同一密码。完整步骤见 [reset-official-mysql-root.md](../reset-official-mysql-root.md)。

---

## 9. 安全提醒

1. 解密结果仅用于本机开发，不要发到聊天、截图、文档仓库
2. 不要把含真实密码的 `.env`、导出连接文件提交 Git
3. 本工具仅解密本地 Navicat 配置，不访问网络
4. 生产环境凭据应通过团队规定的密钥管理方式获取，不要用此工具处理他人机器

---

## 10. 快速命令备忘

```bash
# 1. 检查 PHP
php -v && php -m | grep openssl

# 2. 解密（把 YOUR_CIPHER 换成配置里的密文）
php mysql/tools/navicat-decrypt.php 'YOUR_CIPHER' 12

# 3. 用解密密码登录
mysql -u root -p
```

---

## 11. 相关文件

- [navicat-decrypt.php](./navicat-decrypt.php)
- [NavicatPassword.php](./NavicatPassword.php)
- [reset-official-mysql-root.md](../reset-official-mysql-root.md)
- [macos-homebrew-mysql.md](../macos-homebrew-mysql.md)
- [README.md](../README.md)
