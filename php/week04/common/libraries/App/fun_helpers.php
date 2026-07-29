<?php
/**
 * 站点应用相关函数，注意区别其他类助手：
 *      助手类 ：   同一类事物的抽象
 *      助手函数：  单一功能的函数
 *
 * 功能函数风格，自定义应用功能函数统一追加`g_`前缀(global)
 *
 */

use App\Utils\BaseFunction;
use App\Utils\ConfigHelper;
use App\Utils\LogHelper;

if (!function_exists('g_config')) {
    /**
     * 读取配置中心数据
     *
     * @param string $module
     * @param string $key
     * @param $default
     * @return array|false|mixed|null
     * @throws Exception
     */
    function g_config(string $module, string $key, $default = null)
    {
        return ConfigHelper::config($module, $key, $default);
    }
}

if (!function_exists('g_log_info')) {
    /**
     * 记录运行的日志
     *
     * @param string $filename
     * @param $message
     * @param array|string $context
     * @return void
     */
    function g_log_info(string $filename, $message, $context = []): void
    {
        LogHelper::info($filename, $message, $context);
    }
}

if (!function_exists('g_log_warning')) {
    /**
     * 记录警告的日志
     *
     * @param string $filename
     * @param $message
     * @param array|string $context
     * @return void
     */
    function g_log_warning(string $filename, $message, $context = []): void
    {
        LogHelper::warring($filename, $message, $context);
    }
}

if (!function_exists('g_log_error')) {
    /**
     * 记录错误的日志
     *
     * @param string $filename
     * @param $message
     * @param array|string $context
     * @return void
     */
    function g_log_error(string $filename, $message, $context = []): void
    {
        LogHelper::error($filename, $message, $context);
    }
}

if (!function_exists('debugData')) {
    /**
     * 用于内网页面调试的方法
     * @param $code
     * @param $data
     * @param $info
     *
     * @return void
     * @throws \yii\base\ExitException
     */
    function debugData($code, $data = null, $info = '')
    {
        $result = [
            'code' => $code,
            'data' => $data === null ? new \stdClass() : $data,
            'info' => $info,
        ];
        BaseFunction::instance()->writeJson($result);

        //响应日志必须在end之前
        \Yii::$app->end();
    }
}

if (!function_exists('storeDebugData')) {
    /**
     * 用于内网页面调试的方法
     * @param $code
     * @param $data
     * @param $info
     *
     * @return void
     * @throws \yii\base\ExitException
     */
    function storeDebugData(...$data)
    {
        $result = [
            'code' => 20260306,
            'data' => $data,
            'info' => "store debug",
        ];
        BaseFunction::instance()->writeJson($result);

        //响应日志必须在end之前
        \Yii::$app->end();
    }
}

/**
 * 外部参数调试
 */
if (!function_exists('isDebugStatus')) {
    function isDebugStatus()
    {
        return defined('IS_DEBUG_STATUS') && IS_DEBUG_STATUS == 1;
    }
}

/**
 * 设置调试标识
 */
if (!function_exists('setDebugStatus')) {
    function setDebugStatus($tagName, $value = 1)
    {
        return define($tagName, $value);
    }
}

/**
 * 获取调试标识
 */
if (!function_exists('getDebugStatus')) {
    function getDebugStatus($tagName)
    {
        return defined($tagName) ? constant($tagName) : '';
    }
}
