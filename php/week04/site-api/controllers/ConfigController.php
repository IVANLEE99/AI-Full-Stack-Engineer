<?php

namespace AppSiteApi\controllers;

use common\redis\common\LockHandleRedis;
use common\repositorys\config\ConfigRepository;
use common\services\config\ConfigService;

class ConfigController extends BaseApiController
{
    /**
     * 配置列表
     */
    public function actionGetConfigList()
    {
        $params   = $this->requestParams;
        $page     = $params['page'] ?? 1;
        $pageSize = 10;
        $result   = ConfigService::instance()->getConfigList($params['key'] ?? null, $params['description'] ?? '', $page, $pageSize);
        if ($result) {
            return $this->endSuccess($result, '获取成功');
        } else {
            return $this->endFail(30031, '获取失败');
        }
    }

    /**
     * 获取配置信息
     * http://site.internal.bm.com/config/get-config-data
     */
    public function actionGetConfigData()
    {
        $uniqKey = $this->requestParams['key'] ?? '';
        if (empty($uniqKey)) {
            return $this->endFail(1001, '参数异常');
        }
        $result = ConfigService::instance()->getConfigDataByUniqKey($uniqKey);
        return $this->endSuccess($result,'success');
    }
}
