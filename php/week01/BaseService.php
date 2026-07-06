<?php

namespace common;

use common\builder\BuilderManager;
use common\builder\common\CommonParamsBuilder;
use common\services\system\ConfigService;
use Yii;

/**
 * Class BaseService
 * 服务层基类，单例调用
 *
 * @package common
 */
class BaseService
{
    /**
     * @var int 返回成功码
     */
    protected $success_code = 1;

    /**
     * 从容器中获取单实例
     *
     * @return static
     */
    public static function instance()
    {
        $container = Yii::$container;
        if (!$container->hasSingleton(static::class)) {
            $container->setSingleton(static::class);
        }
        return $container->get(static::class);
    }

    /**
     * @param int   $code
     * @param mixed $data
     *
     * @return array
     */
    protected function retArray($code, $data = null, $info = '')
    {
        return ['code' => (int)$code, 'data' => $data, 'info' => $info];
    }

    protected function returnFormat($code, $data = null, $info = '')
    {
        return ['code' => (int)$code, 'data' => $data, 'info' => $info];
    }

    /**
     * 成功返回
     *
     * @param null $data
     *
     * @return array
     */
    protected function returnSuccess($data = null, $info = '')
    {
        return ['code' => $this->success_code, 'data' => $data, 'info' => $info];
    }

    protected function returnError($code = 0, $info = '', $data = null, $useOriginal = false)
    {
        return ['code' => $code, 'data' => $data, 'info' => $info, 'use_original' => $useOriginal];
    }

    /**
     * 上下文初始化公参参数
     *
     * @param $context
     * @param $params
     *
     * @author 白杨
     * @Date   2021/1/16 下午3:47
     */
    protected function contextInit($context, $params = [])
    {
        $eid             = $params['eid'] ?? '';
        $isLoginId       = $params['is_login_id'] ?? 0;
        $context->params = $params;

        $context->site      = strtolower($params['site'] ?? 'us');
        $context->language  = strtolower($params['language'] ?? '');
        $context->currency  = $params['currency'] ?? 'usd';
        $context->token     = $params['token'] ?? '';
        $context->signature = $params['signature'] ?? '';
        $context->eid       = $params['eid'] ?? '';
        $context->pf        = $params['pf'] ?? '';

        $context->countryCode = strtoupper($params['country_code'] ?? '');
        $context->stateCode   = $params['state_code'] ?? '';
        $context->zip         = $params['zip'] ?? '';

        $context->canPickUp       = $params['can_pick_up'] ?? 0;


        //经纬度
        $context->longitude = $params['longitude'] ?? 0;
        $context->latitude  = $params['latitude'] ?? 0;

        $context->distinctId = $params['distinct_id'] ?? $eid;
        $context->isLoginId  = (bool)$isLoginId;

        if (isset($context->currencySymbol)) {
            $currencySymbol          = ConfigService::instance()->getCurrencySymbol($params['currency'] ?? 'usd');
            $context->currencySymbol = $currencySymbol;
        }
    }

    public function setCommonParams($data)
    {
        self::getCommonParamsBuilder()->setSite($data['site'] ?? 'us');
        self::getCommonParamsBuilder()->setLanguage($data['language'] ?? 'en');
        self::getCommonParamsBuilder()->setCurrency($data['currency'] ?? 'usd');
        self::getCommonParamsBuilder()->setCountryCode($data['country_code'] ?? 'US');
        self::getCommonParamsBuilder()->setZip($data['zip'] ?? '');
    }

    /**
     * 获取公共参数对象
     *
     * @return CommonParamsBuilder
     */
    public static function getCommonParamsBuilder()
    {
        return BuilderManager::getBuilder('common\builder\common\CommonParamsBuilder');
    }


    /**
     * 获取毫秒时间戳
     *
     * @return float
     */
    protected function getMillisecond()
    {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
    }
}
