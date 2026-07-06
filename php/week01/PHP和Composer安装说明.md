# PHP 与 Composer 安装说明（macOS）

> 适用对象：PHP 初学者，macOS 用户。  
> 当前状态示例：PHP 已安装，但 Composer 未安装。

---

## 1. 先检查当前环境

打开终端，分别执行：

```bash
php -v
```

如果看到类似：

```text
PHP 8.4.14 (cli) ...
```

说明 PHP 已经安装成功。

再执行：

```bash
composer -V
```

如果看到：

```text
zsh: command not found: composer
```

说明 Composer 还没有安装，或者安装了但没有加入 PATH。

---

## 2. PHP 和 Composer 是什么关系？

可以用 Node.js 来类比：

| PHP 生态 | Node.js 生态 | 说明 |
|---|---|---|
| PHP | Node.js | 运行代码的解释器 / runtime |
| Composer | npm / pnpm / yarn | 包管理器 |
| `composer.json` | `package.json` | 项目依赖配置文件 |
| `vendor/` | `node_modules/` | 第三方依赖目录 |
| `composer install` | `npm install` | 安装项目依赖 |
| `vendor/autoload.php` | Node 模块解析机制 | Composer 自动加载入口 |

你现在的情况是：

```text
PHP runtime 已经有了
Composer 包管理器还缺
```

---

## 3. 推荐安装方式：使用 Homebrew 安装 Composer

### 3.1 检查是否已经安装 Homebrew

执行：

```bash
brew -v
```

如果看到类似：

```text
Homebrew 4.x.x
```

说明已经有 Homebrew，可以直接进入下一步。

如果看到：

```text
zsh: command not found: brew
```

说明还没有安装 Homebrew。

---

### 3.2 安装 Homebrew

如果你的电脑没有 Homebrew，执行：

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

安装完成后，终端通常会提示你把 Homebrew 加入 PATH。

Apple Silicon Mac，也就是 M1 / M2 / M3 / M4 芯片，通常执行：

```bash
echo 'eval "$(/opt/homebrew/bin/brew shellenv)"' >> ~/.zprofile
eval "$(/opt/homebrew/bin/brew shellenv)"
```

Intel Mac 通常执行：

```bash
echo 'eval "$(/usr/local/bin/brew shellenv)"' >> ~/.zprofile
eval "$(/usr/local/bin/brew shellenv)"
```

然后重新检查：

```bash
brew -v
```

如果能看到 Homebrew 版本，说明 Homebrew 安装成功。

---

### 3.3 使用 Homebrew 安装 Composer

执行：

```bash
brew install composer
```

安装完成后检查：

```bash
composer -V
```

如果看到类似：

```text
Composer version 2.x.x ...
```

说明 Composer 安装成功。

---

## 4. 备用安装方式：使用 Composer 官方安装脚本

如果你不想用 Homebrew，也可以使用 Composer 官方安装脚本。

### 4.1 下载安装器

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
```

### 4.2 安装到全局路径

Apple Silicon Mac 推荐安装到：

```bash
php composer-setup.php --install-dir=/opt/homebrew/bin --filename=composer
```

Intel Mac 或传统路径可以安装到：

```bash
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
```

如果提示权限不足，可以加 `sudo`：

```bash
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
```

### 4.3 删除安装器

```bash
php -r "unlink('composer-setup.php');"
```

### 4.4 检查 Composer

```bash
composer -V
```

如果看到 Composer 版本号，说明安装成功。

---

## 5. 安装后检查路径

安装完成后建议执行：

```bash
which php
php -v
which composer
composer -V
```

你应该看到：

```text
PHP 8.x.x
Composer version 2.x.x
```

还可以执行：

```bash
composer diagnose
```

它会检查 Composer 环境是否正常。

---

## 6. 最小 Composer 验证 Demo

安装 Composer 后，可以创建一个测试目录验证是否正常。

### 6.1 创建测试目录

```bash
mkdir composer-test
cd composer-test
```

### 6.2 快速生成 `composer.json`

```bash
composer init --name=test/demo --description="composer test" --author="test <test@example.com>" --require=monolog/monolog:^3.0 --no-interaction
```

### 6.3 安装依赖

```bash
composer install
```

执行后目录里应该出现：

```text
composer.json
composer.lock
vendor/
```

如果出现 `vendor/` 目录，说明 Composer 可以正常安装依赖。

---

## 7. Composer Autoload 最小 Demo

这个 demo 用来验证 `vendor/autoload.php` 和 PSR-4 自动加载。

### 7.1 创建项目目录

```bash
mkdir php-autoload-demo
cd php-autoload-demo
```

### 7.2 创建 `composer.json`

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
```

### 7.3 创建目录

```bash
mkdir -p src/Services
```

### 7.4 创建类文件

创建文件：

```text
src/Services/UserService.php
```

内容：

```php
<?php

declare(strict_types=1);

namespace App\Services;

class UserService
{
    public function getName(): string
    {
        return "Tom";
    }
}
```

### 7.5 创建入口文件

创建文件：

```text
index.php
```

内容：

```php
<?php

declare(strict_types=1);

require __DIR__ . "/vendor/autoload.php";

use App\Services\UserService;

$userService = new UserService();

echo $userService->getName() . PHP_EOL;
```

### 7.6 生成自动加载文件

```bash
composer dump-autoload
```

### 7.7 运行

```bash
php index.php
```

期望输出：

```text
Tom
```

如果能输出 `Tom`，说明 Composer autoload 已经跑通。

---

## 8. 常见问题

### 8.1 `composer: command not found`

说明 Composer 没安装，或者不在 PATH。

优先执行：

```bash
brew install composer
```

然后重新打开终端，再执行：

```bash
composer -V
```

---

### 8.2 `brew: command not found`

说明 Homebrew 没安装。

先安装 Homebrew：

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

然后按终端提示配置 PATH。

---

### 8.3 `Permission denied`

如果用官方安装脚本安装到 `/usr/local/bin` 时提示权限不足，可以使用：

```bash
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
```

但如果使用 Homebrew，一般不需要 `sudo`。

---

### 8.4 修改了 `composer.json` 的 autoload 后不生效

修改 `autoload.psr-4` 后，需要执行：

```bash
composer dump-autoload
```

它会重新生成自动加载映射。

---

## 9. 今日完成标准

- [ ] `php -v` 能看到 PHP 版本
- [ ] `composer -V` 能看到 Composer 版本
- [ ] 能解释 `PHP ≈ Node.js runtime`
- [ ] 能解释 `Composer ≈ npm`
- [ ] 能解释 `composer.json ≈ package.json`
- [ ] 能解释 `vendor/ ≈ node_modules/`
- [ ] 能运行 `composer install`
- [ ] 能跑通 Composer autoload demo
- [ ] 能解释 `vendor/autoload.php` 的作用
- [ ] 能解释 `App\\Services\\UserService` 如何映射到 `src/Services/UserService.php`

---

## 10. 一句话总结

> PHP 是运行 PHP 代码的解释器，Composer 是 PHP 的包管理器和自动加载工具；安装好 Composer 后，才能真正开始学习 `composer.json`、`vendor/autoload.php` 和 PSR-4 自动加载。
