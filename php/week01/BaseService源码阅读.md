# mall-core/common/BaseService.php 源码阅读

## 1. 文件定位

`BaseService.php` 是服务层基类，位于 `common` 命名空间下，主要用于给业务 Service 提供统一的基础能力。

它的核心作用包括：

- 通过 Yii 容器实现服务类单例调用
- 统一成功 / 失败返回结构
- 初始化上下文公共参数
- 设置公共参数构建器
- 提供毫秒级时间戳工具方法

---

## 2. 类结构

```php
namespace common;

use common\builder\BuilderManager;
use common\builder\common\CommonParamsBuilder;
use common\services\system\ConfigService;
use Yii;
```

该类依赖了以下对象：

| 依赖 | 作用 |
| --- | --- |
| `Yii` | 使用 Yii 的依赖注入容器获取单例对象 |
| `BuilderManager` | 获取公共参数构建器对象 |
| `CommonParamsBuilder` | 公共参数构建器类型声明 |
| `ConfigService` | 获取货币符号等系统配置 |

---

## 3. 成员属性

```php
protected $success_code = 1;
```

`$success_code` 表示服务层统一的成功状态码，默认值为 `1`。

在 `returnSuccess()` 方法中会使用该值作为成功返回的 `code`。

---

## 4. instance()：从 Yii 容器中获取单例

```php
public static function instance()
{
    $container = Yii::$container;
    if (!$container->hasSingleton(static::class)) {
        $container->setSingleton(static::class);
    }
    return $container->get(static::class);
}
```

### 作用

`instance()` 用于获取当前 Service 类的单例实例。

### 执行流程

1. 获取 Yii 容器对象：

   ```php
   $container = Yii::$container;
   ```

2. 判断当前类是否已经注册为单例：

   ```php
   if (!$container->hasSingleton(static::class))
   ```

3. 如果没有注册，则注册当前类为单例：

   ```php
   $container->setSingleton(static::class);
   ```

4. 从容器中取出当前类实例：

   ```php
   return $container->get(static::class);
   ```

### 关键点

这里使用的是 `static::class`，不是 `self::class`。

这表示子类调用 `instance()` 时，拿到的是子类自己的实例，而不是 `BaseService` 的实例。

例如：

```php
UserService::instance();
```

实际注册和获取的是：

```php
UserService::class
```

这体现了 PHP 的后期静态绑定特性。

---

## 5. retArray()：统一数组返回

```php
protected function retArray($code, $data = null, $info = '')
{
    return ['code' => (int)$code, 'data' => $data, 'info' => $info];
}
```

### 作用

用于返回统一格式的数组结构。

返回字段：

| 字段 | 含义 |
| --- | --- |
| `code` | 状态码，强制转换为整型 |
| `data` | 返回数据 |
| `info` | 提示信息 |

---

## 6. returnFormat()：统一格式返回

```php
protected function returnFormat($code, $data = null, $info = '')
{
    return ['code' => (int)$code, 'data' => $data, 'info' => $info];
}
```

### 作用

`returnFormat()` 和 `retArray()` 的功能基本一致，都是返回统一格式数组。

返回结构：

```php
[
    'code' => 状态码,
    'data' => 数据,
    'info' => 提示信息,
]
```

---

## 7. returnSuccess()：成功返回

```php
protected function returnSuccess($data = null, $info = '')
{
    return ['code' => $this->success_code, 'data' => $data, 'info' => $info];
}
```

### 作用

用于返回服务层成功结果。

默认成功状态码来自类属性：

```php
protected $success_code = 1;
```

### 示例返回

```php
[
    'code' => 1,
    'data' => $data,
    'info' => $info,
]
```

---

## 8. returnError()：失败返回

```php
protected function returnError($code = 0, $info = '', $data = null, $useOriginal = false)
{
    return ['code' => $code, 'data' => $data, 'info' => $info, 'use_original' => $useOriginal];
}
```

### 作用

用于返回服务层失败结果。

### 参数说明

| 参数 | 默认值 | 含义 |
| --- | --- | --- |
| `$code` | `0` | 错误码 |
| `$info` | `''` | 错误提示信息 |
| `$data` | `null` | 附加数据 |
| `$useOriginal` | `false` | 是否使用原始错误信息 |

### 示例返回

```php
[
    'code' => 0,
    'data' => null,
    'info' => '错误信息',
    'use_original' => false,
]
```

---

## 9. contextInit()：初始化上下文公共参数

```php
protected function contextInit($context, $params = [])
```

### 作用

`contextInit()` 用于把请求中的公共参数写入 `$context` 对象，方便后续业务逻辑统一读取。

### 初始化原始参数

```php
$eid             = $params['eid'] ?? '';
$isLoginId       = $params['is_login_id'] ?? 0;
$context->params = $params;
```

含义：

- `$eid`：用户或设备标识
- `$isLoginId`：是否为登录用户 ID
- `$context->params`：保存完整参数

---

### 初始化站点、语言、币种等参数

```php
$context->site      = strtolower($params['site'] ?? 'us');
$context->language  = strtolower($params['language'] ?? '');
$context->currency  = $params['currency'] ?? 'usd';
$context->token     = $params['token'] ?? '';
$context->signature = $params['signature'] ?? '';
$context->eid       = $params['eid'] ?? '';
$context->pf        = $params['pf'] ?? '';
```

字段说明：

| 字段 | 默认值 | 说明 |
| --- | --- | --- |
| `site` | `us` | 站点，统一转小写 |
| `language` | `''` | 语言，统一转小写 |
| `currency` | `usd` | 币种 |
| `token` | `''` | 用户 token |
| `signature` | `''` | 签名 |
| `eid` | `''` | 用户或设备标识 |
| `pf` | `''` | 平台来源 |

---

### 初始化地区参数

```php
$context->countryCode = strtoupper($params['country_code'] ?? '');
$context->stateCode   = $params['state_code'] ?? '';
$context->zip         = $params['zip'] ?? '';
```

字段说明：

| 字段 | 说明 |
| --- | --- |
| `countryCode` | 国家码，统一转大写 |
| `stateCode` | 州 / 省编码 |
| `zip` | 邮编 |

---

### 初始化提货能力

```php
$context->canPickUp = $params['can_pick_up'] ?? 0;
```

`canPickUp` 表示当前请求或用户是否支持自提。

---

### 初始化经纬度

```php
$context->longitude = $params['longitude'] ?? 0;
$context->latitude  = $params['latitude'] ?? 0;
```

用于保存地理位置信息。

---

### 初始化用户标识

```php
$context->distinctId = $params['distinct_id'] ?? $eid;
$context->isLoginId  = (bool)$isLoginId;
```

说明：

- `distinctId`：优先使用 `distinct_id`，没有则使用 `$eid`
- `isLoginId`：强制转为布尔值，表示当前 ID 是否为登录 ID

---

### 初始化货币符号

```php
if (isset($context->currencySymbol)) {
    $currencySymbol          = ConfigService::instance()->getCurrencySymbol($params['currency'] ?? 'usd');
    $context->currencySymbol = $currencySymbol;
}
```

这里先判断 `$context` 对象中是否存在 `currencySymbol` 属性。

如果存在，则通过 `ConfigService` 根据币种获取对应货币符号，并写回上下文。

例如：

| currency | currencySymbol |
| --- | --- |
| `usd` | `$` |
| `eur` | `€` |

---

## 10. setCommonParams()：设置公共参数构建器

```php
public function setCommonParams($data)
{
    self::getCommonParamsBuilder()->setSite($data['site'] ?? 'us');
    self::getCommonParamsBuilder()->setLanguage($data['language'] ?? 'en');
    self::getCommonParamsBuilder()->setCurrency($data['currency'] ?? 'usd');
    self::getCommonParamsBuilder()->setCountryCode($data['country_code'] ?? 'US');
    self::getCommonParamsBuilder()->setZip($data['zip'] ?? '');
}
```

### 作用

把传入数据设置到公共参数构建器中。

### 设置内容

| 参数 | 默认值 | 说明 |
| --- | --- | --- |
| `site` | `us` | 站点 |
| `language` | `en` | 语言 |
| `currency` | `usd` | 币种 |
| `country_code` | `US` | 国家码 |
| `zip` | `''` | 邮编 |

### 设计特点

该方法没有直接保存参数，而是通过 `CommonParamsBuilder` 统一构建公共参数对象。

---

## 11. getCommonParamsBuilder()：获取公共参数构建器

```php
public static function getCommonParamsBuilder()
{
    return BuilderManager::getBuilder('common\builder\common\CommonParamsBuilder');
}
```

### 作用

通过 `BuilderManager` 获取 `CommonParamsBuilder` 构建器实例。

### 返回值

```php
CommonParamsBuilder
```

### 关键点

这里传入的是完整类路径字符串：

```php
'common\builder\common\CommonParamsBuilder'
```

说明项目中通过 `BuilderManager` 统一管理 Builder 对象。

---

## 12. getMillisecond()：获取毫秒时间戳

```php
protected function getMillisecond()
{
    list($t1, $t2) = explode(' ', microtime());
    return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
}
```

### 作用

返回当前时间的毫秒级时间戳。

### 执行流程

1. `microtime()` 返回当前 Unix 时间戳和微秒数：

   ```php
   "0.123456 1710000000"
   ```

2. 使用 `explode(' ', microtime())` 拆分为两部分：

   ```php
   $t1 = "0.123456";
   $t2 = "1710000000";
   ```

3. 两者相加得到秒级浮点时间：

   ```php
   floatval($t1) + floatval($t2)
   ```

4. 乘以 `1000` 转为毫秒。

5. 使用 `sprintf('%.0f', ...)` 去掉小数部分。

6. 最终转换为 `float` 返回。

---

## 13. 整体设计总结

`BaseService` 是一个典型的服务层基类，主要解决服务类中的通用问题。

### 1. 单例调用

通过 Yii 容器统一管理 Service 实例：

```php
ServiceClass::instance()
```

避免业务代码中频繁 `new` Service 对象。

---

### 2. 返回格式统一

所有服务方法可以使用统一结构返回结果：

```php
[
    'code' => 状态码,
    'data' => 数据,
    'info' => 提示信息,
]
```

这样 Controller 或调用方可以按照固定格式处理结果。

---

### 3. 公共参数统一初始化

`contextInit()` 把请求公共参数集中写入上下文对象，避免每个业务方法重复解析：

- 站点
- 语言
- 币种
- token
- 国家 / 州 / 邮编
- 经纬度
- 用户标识
- 货币符号

---

### 4. Builder 模式管理公共参数

`setCommonParams()` 和 `getCommonParamsBuilder()` 配合使用，通过构建器统一维护公共参数。

---

### 5. 工具方法复用

`getMillisecond()` 提供毫秒时间戳，方便服务层进行耗时统计、日志记录或时间比较。

---

## 14. 一句话总结

`BaseService` 是服务层的基础父类，它通过 Yii 容器实现单例服务调用，并封装了统一返回格式、上下文公共参数初始化、公共参数构建器和毫秒时间戳等通用能力。
