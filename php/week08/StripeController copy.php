<?php

namespace fecshop\app\frontapi\modules\Outer\controllers;

use fecshop\app\frontapi\modules\BaseController;
use fecshop\services\http\NewPayRequest;

class StripeController extends BaseController
{
    /**
     * webhook回调
     *
     * @author liangchupeng
     *
     */
    public function actionWebhook()
    {
        $params  = \Yii::$app->request->post();
        $headers = $this->getAllHeaders();
        $result  = NewPayRequest::instance()->stripeWebhook($params, $headers);
        $this->endJson($result['code'], null, $result['info']);
    }
}

