# PHP 后端高频内置函数速查表

> 配套:`week01/day05.md`、`week01/json_encode.md`
> 目标:整理 PHP 后端开发中最常用的内置函数,带示例和前端类比,方便随时查阅

---

## 0. 什么是内置函数

PHP 内置函数(built-in functions)是语言**自带、无需 `require` / `use` 就能直接调用**的函数,由 PHP 内核(C 实现)提供。

```php
echo strlen("hello");   // 5,直接用,不需要引入任何东西
```

前端类比:

```text
PHP 内置函数   ≈  JS 全局函数 / 内置对象方法(Math、JSON、Array.prototype 等)
```

一个关键差异:PHP 大量功能是**全局函数**(`count($arr)`),而 JS 更多是**对象方法**(`arr.length`)。

> 说明:`isset` / `empty` / `unset` / `echo` / `print` 严格来说是**语言结构**(language construct),不是函数,但用起来像函数。

---

## 1. 字符串

| 函数 | 作用 | 示例 | JS 类比 |
|---|---|---|---|
| `strlen` | 字符串字节长度 | `strlen("abc")` → 3 | `str.length`(注意中文差异) |
| `mb_strlen` | 按字符数算长度(多字节) | `mb_strlen("你好")` → 2 | `[...str].length` |
| `str_replace` | 替换子串 | `str_replace("a", "b", "aaa")` → "bbb" | `str.replaceAll()` |
| `explode` | 按分隔符拆成数组 | `explode(",", "a,b")` → `["a","b"]` | `str.split(",")` |
| `implode` | 数组拼成字符串 | `implode("-", ["a","b"])` → "a-b" | `arr.join("-")` |
| `trim` | 去掉两端空白 | `trim("  hi  ")` → "hi" | `str.trim()` |
| `strpos` | 查子串首次位置(找不到返回 `false`) | `strpos("abc","b")` → 1 | `str.indexOf()` |
| `str_contains` | 是否包含子串(PHP 8+) | `str_contains("abc","b")` → true | `str.includes()` |
| `str_starts_with` | 是否以某串开头(PHP 8+) | `str_starts_with("abc","a")` → true | `str.startsWith()` |
| `substr` | 截取子串 | `substr("abcde",1,2)` → "bc" | `str.slice()` |
| `strtolower` / `strtoupper` | 转小写 / 大写 | `strtoupper("ab")` → "AB" | `toUpperCase()` |
| `sprintf` | 格式化字符串(返回) | `sprintf("id=%d", 5)` → "id=5" | 模板字符串 |
| `number_format` | 数字千分位格式化 | `number_format(1234.5, 2)` → "1,234.50" | `toLocaleString()` |

> `strpos` 返回值要用 `=== false` 判断,因为位置可能是 `0`(第一个字符),用 `!` 会误判。

```php
if (strpos($str, "x") !== false) { /* 找到了 */ }
```

---

## 2. 数组

| 函数 | 作用 | 示例 | JS 类比 |
|---|---|---|---|
| `count` | 数组元素个数 | `count([1,2,3])` → 3 | `arr.length` |
| `array_map` | 对每个元素做映射 | `array_map(fn($x)=>$x*2, [1,2])` → `[2,4]` | `arr.map()` |
| `array_filter` | 过滤元素 | `array_filter([1,0,2])` → `[1,2]` | `arr.filter()` |
| `array_values` | 重排为连续索引(丢弃键) | `array_values([1=>"a"])` → `["a"]` | — |
| `array_keys` | 取所有键 | `array_keys(["a"=>1])` → `["a"]` | `Object.keys()` |
| `array_column` | 取二维数组某一列 | `array_column($rows, "id")` → `[1,2]` | `arr.map(r=>r.id)` |
| `in_array` | 值是否存在 | `in_array(2, [1,2])` → true | `arr.includes()` |
| `array_key_exists` | 键是否存在 | `array_key_exists("a", $arr)` | `"a" in obj` |
| `array_merge` | 合并数组 | `array_merge([1],[2])` → `[1,2]` | `[...a, ...b]` |
| `array_push` | 尾部追加 | `array_push($arr, 4)` | `arr.push()` |
| `array_slice` | 截取一段 | `array_slice([1,2,3],1)` → `[2,3]` | `arr.slice()` |
| `sort` / `usort` | 排序 / 自定义排序 | `usort($arr, fn($a,$b)=>$a-$b)` | `arr.sort()` |
| `array_reduce` | 归并累计 | `array_reduce([1,2,3], fn($c,$x)=>$c+$x, 0)` → 6 | `arr.reduce()` |

> 大坑:`array_filter` 会**保留原键**,过滤后键可能不连续,直接 `json_encode` 会变成 `{}`。返回列表接口时记得包一层 `array_values`。详见 `json_encode.md` 第 3 节。

```php
$list = array_values(array_filter($rows, fn($r) => $r["done"]));
```

---

## 3. JSON(详见 `json_encode.md`)

| 函数 | 作用 |
|---|---|
| `json_encode` | PHP 变量 → JSON 字符串 |
| `json_decode` | JSON 字符串 → PHP 变量(第二参 `true` 转成关联数组) |
| `json_last_error_msg` | 取最近一次 JSON 操作的错误描述 |

```php
$arr = json_decode('{"id":1}', true);  // ["id" => 1],加 true 得到数组而非对象
```

---

## 4. HTTP / 请求响应

| 函数 | 作用 | 示例 |
|---|---|---|
| `header` | 设置响应头(必须在输出前) | `header("Content-Type: application/json")` |
| `http_response_code` | 设置 / 获取 HTTP 状态码 | `http_response_code(404)` |
| `parse_url` | 解析 URL 各部分 | `parse_url($uri, PHP_URL_PATH)` |
| `parse_str` | 解析查询字符串到数组 | `parse_str("a=1&b=2", $out)` |
| `http_build_query` | 数组拼成查询字符串 | `http_build_query(["a"=>1])` → "a=1" |

常用超全局变量(不是函数,但天天用):

```php
$_GET        // query 参数
$_POST       // 表单 body
$_SERVER     // 服务器/请求信息,如 $_SERVER['REQUEST_METHOD']
$_REQUEST    // GET+POST+COOKIE 合集(不推荐)
```

---

## 5. 变量判断 / 类型

| 函数 | 作用 | 注意 |
|---|---|---|
| `isset` | 变量是否已设置且不为 null | 语言结构 |
| `empty` | 是否为"空"(0、""、null、[]、false 都算空) | 语言结构,易踩坑 |
| `is_array` / `is_string` / `is_int` / `is_null` / `is_bool` | 类型判断 | — |
| `gettype` | 返回类型名字符串 | 调试用 |
| `intval` / `floatval` / `strval` | 强制类型转换 | `intval("12abc")` → 12 |

> `isset` vs `empty` 区别:变量值是 `0` 或 `""` 时,`isset` 为 `true`(已设置),但 `empty` 为 `true`(算空)。判断"参数是否传了"用 `isset`,判断"是否有有效值"用 `empty`,别混用。

```php
$age = 0;
isset($age);   // true  —— 变量存在
empty($age);   // true  —— 但值算"空"
```

---

## 6. 时间日期

| 函数 | 作用 | 示例 |
|---|---|---|
| `time` | 当前 Unix 时间戳(秒) | `time()` → 1752345600 |
| `date` | 格式化时间戳 | `date("Y-m-d H:i:s")` → "2026-07-13 10:00:00" |
| `strtotime` | 字符串转时间戳 | `strtotime("2026-07-13")` |
| `microtime` | 带微秒的时间(测耗时) | `microtime(true)` |

> 复杂时间处理推荐用 `DateTime` 类或 `Carbon` 库,比裸函数更好用。

---

## 7. 文件

| 函数 | 作用 |
|---|---|
| `file_get_contents` | 读整个文件为字符串 |
| `file_put_contents` | 写字符串到文件 |
| `file_exists` | 文件是否存在 |
| `fopen` / `fwrite` / `fclose` | 流式读写大文件 |
| `__DIR__` / `__FILE__` | 当前目录 / 文件的魔术常量(day05 入口文件用过) |

```php
require __DIR__ . '/../vendor/autoload.php';  // day05 入口文件写法
```

---

## 8. 怎么查官方手册

不确定用法时查官方文档最快:

```text
https://www.php.net/manual/zh/function.函数名.php

例:
json_encode → https://www.php.net/manual/zh/function.json-encode.php
array_map   → https://www.php.net/manual/zh/function.array-map.php
```

> 注意:URL 里函数名的下划线 `_` 要换成中划线 `-`(`json_encode` → `json-encode`)。

---

## 9. 小结 / 检查清单

- [ ] 内置函数无需引入,直接调用;PHP 多为全局函数,JS 多为对象方法
- [ ] 字符串长度分 `strlen`(字节)和 `mb_strlen`(字符),中文场景用后者
- [ ] `strpos` 找不到返回 `false`,要用 `=== false` 判断
- [ ] `array_filter` 后键不连续,`json_encode` 前记得 `array_values`
- [ ] `isset` 判断"是否设置",`empty` 判断"是否为空",区分清楚
- [ ] `header()` 必须在任何输出之前调用
- [ ] 查文档:`php.net/manual/zh/function.函数名.php`,下划线换中划线

---

## 返回

- [返回 Week 01 Day 05](./day05.md)
- [json_encode 详解](./json_encode.md)
