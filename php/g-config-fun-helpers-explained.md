# `g_config()` 源码阅读笔记 + `function_exists` / `defined` / `constant`

> 对应：[week04/day01.md](./week04/day01.md)  
> 源码：`week04/common/libraries/App/fun_helpers.php` · `week04/common/libraries/App/Utils/ConfigHelper.php`

---

## 1. 阅读 `g_config()` 时先找（清单答案）

| # | 问题 | 答案 |
|---|---|---|
| 1 | 函数名是不是 `g_config` | 是 |
| 2 | 参数有几个 | **3 个**：`$module`、`$key`、`$default` |
| 3 | 默认值参数叫什么 | `$default`，默认 `null` |
| 4 | 调用了哪个类或方法 | `ConfigHelper::config($module, $key, $default)` |
| 5 | 找不到配置时返回什么 | 返回 **`$default`** |
| 6 | 是否使用缓存 | **有**：`static $configs`，同进程按 module 缓存 ini 解析结果 |
| 7 | 是否有类型转换 | **入口不做转换**；ini 用 `INI_SCANNER_RAW`，业务侧自行 `intval` / `(string)` 等 |

---

## 2. 入口：`fun_helpers.php`

```php
if (!function_exists('g_config')) {
    /**
     * 读取配置中心数据
     *
     * @param string $module
     * @param string $key
     * @param $default
     * @return array|false|mixed|null
     */
    function g_config(string $module, string $key, $default = null)
    {
        return ConfigHelper::config($module, $key, $default);
    }
}
```

理解：

- `g_config` 是**薄封装**（助手函数），真正逻辑在 `ConfigHelper`
- `g_` 前缀表示 global 助手函数（文件头注释）
- `function_exists` 防止重复定义

---

## 3. 真正逻辑：`ConfigHelper::config()`

```php
public static function config(string $module, string $key, $default = null)
{
    if (!static::isValidModule($module)) {
        if (SiteHelper::isProd()) {
            return $default;                    // 生产：兜底
        } else {
            throw new \Exception("module '{$module}' not found"); // 非生产：尽早暴露错误
        }
    }

    static $configs;
    if (!isset($configs[$module])) {
        $config_dir = empty(getenv('NACOS_CONFIG_DIR'))
            ? '/data/www/nacos-config'
            : getenv('NACOS_CONFIG_DIR');
        $filename = $config_dir . DIRECTORY_SEPARATOR . $module . '.ini';
        if (file_exists($filename)) {
            $configs[$module] = parse_ini_file($filename, true, INI_SCANNER_RAW);
        } else {
            $configs[$module] = [];
        }
    }

    if (empty($key)) {
        return $configs[$module] ?? null;       // key 空：返回整模块
    }
    return $configs[$module][$key] ?? $default; // 找不到 key：返回默认值
}
```

### 流程简图

```text
g_config(module, key, default)
        ↓
ConfigHelper::config(...)
        ↓
module 合法？ ──否──▶ 生产: default / 非生产: throw
        ↓ 是
static $configs 有缓存？ ──否──▶ 读 {module}.ini → parse_ini_file → 写入缓存
        ↓ 是
key 为空？ ──是──▶ 返回整个 module 配置数组
        ↓ 否
返回 configs[module][key] ?? default
```

---

## 4. 阅读记录表（可直接抄到 day01）

| 问题 | 记录 |
|---|---|
| `g_config()` 在哪个文件 | `common/libraries/App/fun_helpers.php` |
| 参数列表 | `$module`, `$key`, `$default = null` |
| 默认值如何处理 | `?? $default`；无效 module 生产也返回 `$default` |
| 配置来源 | `{NACOS_CONFIG_DIR}/{module}.ini` |
| 是否使用缓存 | 有，`static $configs` |
| 最终返回值 | 配置值 / `$default` / 整模块数组 |

### ConfigHelper

| 问题 | 记录 |
|---|---|
| module 常量 | `mall_common` `content` `goods` `market` `order` `operate` `pay` `site` `user` `aftersale` |
| key 常量 | 多为方法内字符串，不是统一常量表 |
| 常用模块 | `site` / `pay` / `order` / `goods` / `mall_common` |
| 类型转换 | `config()` 不做；调用方常见 `intval()`、`(string)`、`trim()` |

---

## 5. 调用示例

```php
// 开关：找不到则 false
$enable = g_config('pay', 'enable_stripe', false);

// 文案：找不到则默认商城名
$name = g_config('site', 'name', '默认商城');

// 用模块常量，少写错字符串
$value = g_config(ConfigHelper::$SITE, 'IS_URL_REDIRECT', 1);

// 业务侧自己转类型
$id = intval(g_config(ConfigHelper::$SITE, 'RETURN_EXCHANGE_POLICY_ARTICLE_ID_US', 61));
```

---

## 6. `function_exists` / `defined` / `constant`

同一文件里的三类 PHP 内置函数。

### 6.1 `function_exists('函数名')`

判断函数是否已定义。

```php
if (!function_exists('g_config')) {
    function g_config(...) { ... }
}
```

用途：文件可能被多次加载时，避免 “Cannot redeclare function”。

同类写法贯穿本文件：`g_log_info`、`debugData`、`isDebugStatus` 等。

---

### 6.2 `defined('常量名')`

判断常量是否已定义。

```php
function isDebugStatus()
{
    return defined('IS_DEBUG_STATUS') && IS_DEBUG_STATUS == 1;
}
```

```php
function getDebugStatus($tagName)
{
    return defined($tagName) ? constant($tagName) : '';
}
```

用途：常量不存在时先判断，避免直接读未定义常量报错。

---

### 6.3 `constant('常量名')`

用**字符串**动态取常量值。

```php
$tagName = 'IS_DEBUG_STATUS';
$value = constant($tagName);   // 等价于 IS_DEBUG_STATUS，但名字可变
```

| 写法 | 适用 |
|---|---|
| `IS_DEBUG_STATUS` | 常量名写死 |
| `constant($tagName)` | 常量名在变量里 |

配套定义常量：

```php
function setDebugStatus($tagName, $value = 1)
{
    return define($tagName, $value);
}
```

---

### 6.4 三者对照

| 函数 | 查什么 | 本文件用途 |
|---|---|---|
| `function_exists` | 函数有没有 | 安全定义 `g_config` 等 |
| `defined` | 常量有没有 | 读调试开关前判断 |
| `constant` | 按名字取值 | 动态读 `$tagName` |

---

## 7. 和 Node.js 类比

| PHP | Node.js 粗类比 |
|---|---|
| `g_config('pay', 'enable', false)` | `process.env.PAY_ENABLE ?? 'false'`，但可按模块分组、可动态来自配置中心文件 |
| `function_exists` | 检查某函数是否可调用（少见；更像防重复注册） |
| `defined` + `constant` | 类似检查全局常量/配置键是否存在再取值 |

差异：`process.env` 多在进程启动时固定；`g_config` 读的是业务 ini，同请求内有静态缓存，配置文件可被运维/配置中心更新后由进程重新加载策略决定是否刷新（本实现是**请求内**缓存，不是跨请求 Redis）。

---

## 8. 一句话速记

- `g_config` = 薄封装 → `ConfigHelper::config`
- 三参数：`module` + `key` + `default`
- 找不到 → 返回 `$default`
- 有 `static $configs` 进程内缓存
- 入口不做类型转换
- `function_exists` 防重复定义函数
- `defined` + `constant` 安全、动态读写常量

---

## 相关文件

- [week04/day01.md](./week04/day01.md)
- [week04/common/libraries/App/fun_helpers.php](./week04/common/libraries/App/fun_helpers.php)
- [week04/common/libraries/App/Utils/ConfigHelper.php](./week04/common/libraries/App/Utils/ConfigHelper.php)
- [php-pro.md](./php-pro.md)
