# Day01 导入实验库命令详解

> 所属：7 天掌握 MySQL  
> 对应：[day01.md](./day01.md) 第 6 节「导入实验库」  
> 相关 SQL：[labs/01_schema.sql](./labs/01_schema.sql) · [labs/02_seed.sql](./labs/02_seed.sql) · [mysql-command-not-found-macos.md](./mysql-command-not-found-macos.md)

---

## 完整命令

在 **`mysql/` 目录下**执行：

```bash
mysql -u root -p < labs/01_schema.sql
mysql -u root -p shop_lab < labs/02_seed.sql
```

若终端报 `command not found: mysql`，改用完整路径：

```bash
/usr/local/mysql/bin/mysql -u root -p < labs/01_schema.sql
/usr/local/mysql/bin/mysql -u root -p shop_lab < labs/02_seed.sql
```

详见 [mysql-command-not-found-macos.md](./mysql-command-not-found-macos.md)。

---

## 1. 这两条命令在干什么

| 顺序 | 命令 | 作用 |
|------|------|------|
| 1 | `mysql ... < labs/01_schema.sql` | **建库 + 建表**（表结构） |
| 2 | `mysql ... shop_lab < labs/02_seed.sql` | **往 shop_lab 里灌测试数据** |

一句话：**先搭架子，再填数据。**

---

## 2. 命令语法拆解

### 2.1 通用结构

```bash
mysql [连接参数] [数据库名] < SQL文件路径
```

| 部分 | 含义 |
|------|------|
| `mysql` | MySQL 命令行客户端 |
| `-u root` | 用 `root` 用户登录 |
| `-p` | 提示输入密码（`-p` 与密码之间不要有空格） |
| `< labs/01_schema.sql` | 把文件内容**重定向**给 mysql 执行 |
| `shop_lab` | 指定默认数据库（第二条才有） |

### 2.2 `<` 重定向是什么意思

```bash
mysql -u root -p < labs/01_schema.sql
```

等价于：

1. 打开 `labs/01_schema.sql` 文件
2. 把里面所有 SQL 语句交给 `mysql` 客户端
3. 由客户端发给 MySQL 服务端逐条执行

**不是**在 mysql 交互模式里输入 `source`，而是一次性从 shell 导入。

### 2.3 第一条为什么可以不写数据库名

`01_schema.sql` 文件**内部已经写了**：

```sql
CREATE DATABASE IF NOT EXISTS shop_lab ...;
USE shop_lab;
```

所以第一条命令不需要在命令行指定 `shop_lab`，SQL 文件自己会创建并切换库。

### 2.4 第二条为什么要写 `shop_lab`

`02_seed.sql` 开头是：

```sql
USE shop_lab;
INSERT INTO users ...
```

虽然文件里也有 `USE shop_lab`，命令行写上 `shop_lab` 可以：

- 明确指定默认库，避免连错库
- 与教程写法一致，语义更清晰

两种写法通常都能成功；教程采用「第二条显式指定库名」。

---

## 3. `01_schema.sql` 会创建什么

执行后会得到实验库 **`shop_lab`**，包含这些表：

| 表名 | 作用 |
|------|------|
| `users` | 用户 |
| `products` | 商品 |
| `orders` | 订单主表 |
| `order_items` | 订单商品明细 |
| `order_addresses` | 订单收货地址快照 |

文件还会：

- 设置字符集 `utf8mb4`
- 若表已存在则先 `DROP` 再重建（**会清空旧表结构**）
- 创建主键、唯一键、普通索引

---

## 4. `02_seed.sql` 会插入什么

在 `shop_lab` 里插入教程用的示例数据，例如：

- `users`：5 个用户（Tom、Alice、Bob…）
- `products`：5 个商品（键盘、鼠标、耳机…）
- `orders`：7 笔订单（不同状态）
- `order_items`：订单行项目
- `order_addresses`：每笔订单的地址快照

用于 Day01 及后续几天的 `SELECT` / `JOIN` / 聚合练习。

---

## 5. 执行步骤（推荐顺序）

```bash
# 1. 进入 mysql 教程目录
cd /path/to/AI-Full-Stack-Engineer/mysql

# 2. 导入表结构（会提示输入 root 密码）
mysql -u root -p < labs/01_schema.sql

# 3. 导入测试数据
mysql -u root -p shop_lab < labs/02_seed.sql
```

每次 `-p` 后回车，终端会提示 `Enter password:`，输入密码时**不会显示字符**，属正常现象。

---

## 6. 如何验证导入成功

进入 mysql 后执行（或在 shell 一行命令）：

```bash
mysql -u root -p -e "USE shop_lab; SHOW TABLES;"
```

交互模式：

```sql
USE shop_lab;
SHOW TABLES;
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM orders;
SELECT COUNT(*) FROM order_items;
```

期望大致结果：

| 检查项 | 期望 |
|--------|------|
| `SHOW TABLES` | 能看到 5 张表 |
| `users` 行数 | 5 |
| `orders` 行数 | 7 |
| `order_items` 行数 | 10 |

（以 [02_seed.sql](./labs/02_seed.sql) 实际插入为准。）

---

## 7. 与交互式 `source` 的对比

| 方式 | 命令示例 | 适用 |
|------|----------|------|
| Shell 重定向 | `mysql -u root -p < file.sql` | 脚本化、教程一键导入 |
| mysql 内 source | `mysql> source /绝对路径/labs/01_schema.sql` | 已在 mysql 里、路径要写对 |

教程用 `<` 是因为在 shell 里一条命令即可完成，适合 Day01 快速搭环境。

---

## 8. 常见问题

### 8.1 `command not found: mysql`

客户端未在 PATH 中。见 [mysql-command-not-found-macos.md](./mysql-command-not-found-macos.md)。

### 8.2 `ERROR 1045 Access denied`

用户名或密码错误。Navicat 能连时，可用相同密码；忘记密码见 [reset-official-mysql-root.md](./reset-official-mysql-root.md)。

### 8.3 `No such file or directory`

当前目录不对。必须在 **`mysql/`** 下执行，或使用绝对路径：

```bash
mysql -u root -p < /Users/你的用户名/.../mysql/labs/01_schema.sql
```

### 8.4 重复执行会怎样

- 再跑 `01_schema.sql`：会 `DROP` 旧表并重建，**结构重置**
- 再跑 `02_seed.sql`：可能主键/唯一键冲突（如 `Duplicate entry`）

练习环境可接受；若要干净重来，先执行 `01_schema.sql` 再执行 `02_seed.sql`。

### 8.5 在项目根目录执行失败

若在项目根执行：

```bash
mysql -u root -p < labs/01_schema.sql   # 错误：路径应是 mysql/labs/...
```

应：

```bash
cd mysql
mysql -u root -p < labs/01_schema.sql
```

---

## 9. 一句话速记

- 第一条：`< 01_schema.sql` → 创建 `shop_lab` 和所有表
- 第二条：`shop_lab < 02_seed.sql` → 往实验库插入测试数据
- `-u root -p` → root 登录并输入密码
- `<` → 把 SQL 文件内容交给 mysql 执行
- 在 `mysql/` 目录下跑，或写清文件绝对路径

---

## 相关文件

- [day01.md](./day01.md)
- [labs/01_schema.sql](./labs/01_schema.sql)
- [labs/02_seed.sql](./labs/02_seed.sql)
- [create-table-users-explained.md](./create-table-users-explained.md)
- [mysql-command-not-found-macos.md](./mysql-command-not-found-macos.md)
