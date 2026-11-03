<?php

/**
 * stripe回调处理
 */

namespace AppPayApi\controllers\outer;

use App\Utils\EnterpriseWechatMessage;
use App\Utils\MyFunction;
use common\BaseWebController;
use common\redis\pay\PaymentRedis;
use common\repositorys\pay\PaymentMethodParameterRepository;
use common\repositorys\pay\PaymentProcessLogRepository;
use common\repositorys\pay\PaymentRepository;
use common\services\common\MqService;
use common\services\pay\PaymentWebhookService;
use common\services\pay\PayService;
use yii\db\Exception;

class StripeController extends BaseWebController
{
    private $logFile = 'outer_stripe';

    /**
     * 接收stripe webhook事件回调
     */
    public function actionWebhook()
    {
        $params = \Yii::$app->request->post();
        try {
            $result = PaymentWebhookService::instance()->paymentWebhookHandle(PaymentRepository::PAY_BY_STRIPR,
                $params['type'], $params);
            if (1 == $result['code']) {
                g_log_info($this->logFile, 'stripe webhook事件回调结果', [$params, $result]);
                return $this->endSuccess($result['data']);
            } else {
                g_log_warning($this->logFile, 'stripe webhook事件回调处理失败', [$params, $result]);
            }
            return $this->endFail($result['code'], $result['info']);

        } catch (\Throwable $e) {

            g_log_error($this->logFile, 'stripe webhook事件回调异常', [$params, $e->getMessage(), $e->getTraceAsString()]);
            return $this->endFail($e->getCode(), $e->getMessage());
        }
    }

    /**
     * stripe 3ds验证完成回调
     */
    public function actionConfirmCallback()
    {
        $params = \Yii::$app->request->post();
        g_log_info($this->logFile, 'stripe 3ds验证完成回调参数', [$params]);
        try {
            if (empty($params['payment_intent']) || empty($params['payment_intent_client_secret'])) {
                throw new \Exception('参数错误', 5038012);
            }
            //通过支付参数获取支付单详情
            $paymentParameter = PaymentMethodParameterRepository::instance()->getByTransactionId($params['payment_intent'], 'order_no,payment_no,payment_id,payment_method');
            if (empty($paymentParameter)) {
                throw new Exception('Payment parameter not found', 9367926);
            }
            $paymentNo = $paymentParameter['payment_no'];
            $result    = PayService::instance()->confirmPaymentByCompanyCallback($paymentNo, $paymentParameter['payment_method'], [
                'process_type' => PaymentProcessLogRepository::TYPE_VALIDATE_3DS
            ]);
            //写入redis，等待前端轮询
            PaymentRedis::instance()->setPayStatus($paymentNo, json_encode($result['data']));
            g_log_info($this->logFile, 'stripe 3ds验证完成回调', ['request' => $params, 'response' => $result]);
            if (1 != $result['code']) {
                throw new Exception($result['info'], $result['code']);
            }
            $this->endSuccess($result['data']);

        } catch (\Throwable $e) {

            g_log_warning($this->logFile, 'stripe 3ds验证完成回调异常', ['request' => $params, 'response' => [$e->getCode(), $e->getMessage(), $e->getTraceAsString()]]);
            $this->endFail($e->getCode(), $e->getMessage());
        }
    }

}
