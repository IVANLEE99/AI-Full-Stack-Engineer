<?php

namespace fecshop\app\frontapi\modules\Pay\controllers;

use fecshop\app\frontapi\modules\AuthApiController;
use fecshop\services\http\NewPayRequest;
use fecshop\services\http\PayRequest;
use \Yii;

/**
 *
 * @author 白杨
 * @Date   2021/1/29 上午11:20
 */
class PayController extends AuthApiController
{
    /**
     * 支付方式
     *
     * @author 白杨
     * @Date   2021/1/30 下午3:50
     */
    public function actionMethods()
    {
        $params = Yii::$app->request->get();
        $result = NewPayRequest::instance()->checkStandPaymentMethods($params);
        if (1 === $result['code']) {
            return $this->endSuccess($result['data']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 选中支付方式，组装参数
     *
     * @author 白杨
     * @Date   2021/1/30 下午3:51
     */
    public function actionPaymentParams()
    {
        $params               = Yii::$app->request->post();
        $params['ip_address'] = $this->JPGetRealIp();
        $result               = NewPayRequest::instance()->checkStandPaymentParams($params);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * affirm支付确认
     *
     * @author 白杨
     * @Date   2021/1/30 下午3:51
     */
    public function actionAffirmConfirm()
    {
        try {
            $params            = Yii::$app->request->post();
            $params['log_str'] = uniqid('affirm_confirm_');
            $result            = PayRequest::instance()->affirmConfirm($params);
            \Yii::info([$result, $params], $params['log_str']);
            if (1 === $result['code']) {
                return $this->endSuccess($result['data']);
            } else {
                return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            return $this->endFail(1004, 'exception');
        }
    }

    public function actionCallBackAffirmConfirm()
    {
        try {
            $params = Yii::$app->request->post();
            $result = PayRequest::instance()->callaBackAffirmConfirm($params);
            if (1 === $result['code']) {
                return $this->endSuccess($result['data']);
            } else {
                return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            return $this->endFail(1004, 'exception');
        }
    }

    /**
     * 支付成功发送邮件
     *
     * @author zhengcongfeng by 2021-03-26
     */
    public function actionSendEmail()
    {
        try {
            $params = Yii::$app->request->post();
            $result = PayRequest::instance()->sendEmail($params);
            if (1 === $result['code']) {
                return $this->endSuccess($result['data']);
            } else {
                return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            return $this->endFail(1004, 'exception');
        }
    }

    /**
     * braintree交易单创建
     *
     * @author 白杨
     * @Date   2021/2/2 下午2:23
     */
    public function actionBraintreeCreateTransaction()
    {
        try {
            $params            = Yii::$app->request->post();
            $params['log_str'] = uniqid('braintree_create_transaction_');
            $result            = PayRequest::instance()->braintreeCreateTransaction($params);
            \Yii::info([$result, $params], $params['log_str']);
            if (1 === $result['code']) {
                return $this->endSuccess($result['data']);
            } else {
                return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            return $this->endFail(1004, 'exception');
        }
    }

    /**
     * paypal快捷支付接口
     *
     * @author  liangchupeng
     * @since   2021.07.05
     */
    public function actionPaypalQuickPayment()
    {
        try {
            $params            = Yii::$app->request->post();
            $params['log_str'] = uniqid('paypal_quick_payment');
            $result            = PayRequest::instance()->createPaypalQuickPayment($params);
            \Yii::info([$result, $params], $params['log_str']);
            if (1 === $result['code']) {
                return $this->endSuccess($result['data'], null, $result['request_id'] ?? '');
            } else {
                return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '', $result['request_id'] ?? '');
            }
        } catch (\Throwable $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            return $this->endFail(1004, 'exception');
        }
    }

    /**
     * 线下转账请求改接口
     *
     * @author 白杨
     * @Date   2021/2/3 下午2:52
     */
    public function actionBankTransfer()
    {
        try {
            $params            = Yii::$app->request->post();
            $params['log_str'] = uniqid('bank_transfer_');
            $result            = PayRequest::instance()->bankTransfer($params);
            \Yii::info([$result, $params], $params['log_str']);
            if (1 === $result['code']) {
                return $this->endSuccess($result['data']);
            } else {
                return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            return $this->endFail(1004, 'exception');
        }
    }

    /**
     * 支付成功接口
     *
     * @author 白杨
     * @Date   2021/2/1 下午2:00
     */
    public function actionSuccess()
    {
        try {
            $params            = Yii::$app->request->get();
            $params['log_str'] = uniqid('pay_success_');
            $result            = PayRequest::instance()->paySuccess($params);
            \Yii::info([$result, $params], $params['log_str']);
            if (1 === $result['code']) {
                return $this->endSuccess($result['data']);
            } else {
                return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error('AffirmConfirm:' . $e->getMessage() . $e->getTraceAsString());
            return $this->endFail(1004, 'exception');
        }
    }

    /**
     * 支付失败接口
     *
     * @author 白杨
     * @Date   2021/2/1 下午2:00
     */
    public function actionFail()
    {
        try {
            $params = Yii::$app->request->get();
            $result = PayRequest::instance()->payFail($params);
            if (1 === $result['code']) {
                return $this->endSuccess($result['data']);
            } else {
                return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error('AffirmConfirm:' . $e->getMessage() . $e->getTraceAsString());
            return $this->endFail(1004, 'exception');
        }
    }

    /**
     * 支付失败接口
     *
     * @author 白杨
     * @Date   2021/2/1 下午2:00
     */
    public function actionGetMethodIconList()
    {
        try {
            $params = Yii::$app->request->get();
            $result = PayRequest::instance()->getMethodIconList($params);
            if (1 === $result['code']) {
                return $this->endSuccess($result['data']);
            } else {
                return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error('AffirmConfirm:' . $e->getMessage() . $e->getTraceAsString());
            return $this->endFail(1004, 'exception');
        }
    }

    /**
     * 模拟支付
     *
     * @throws \yii\base\ExitException
     * @author 白杨
     * @Date   19/8/21 下午6:39
     */
    public function actionSimulatePay()
    {
        try {
            $params = Yii::$app->request->get();
            $result = PayRequest::instance()->simulatePay($params);
            if (1 === $result['code']) {
                return $this->endSuccess($result['data']);
            } else {
                return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error('AffirmConfirm:' . $e->getMessage() . $e->getTraceAsString());
            return $this->endFail(1004, 'exception');
        }
    }

    /**
     * 获取支付单的支付状态
     *
     * @author liangchupeng
     * @since  2022.02.07
     */
    public function actionGetPayStatus()
    {
        $params = Yii::$app->request->post();
        $result = PayRequest::instance()->getPayStatus($params);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data'], $result['info']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * klarna一键下单支付
     */
    public function actionCreateKlarnaTransaction()
    {
        try {
            $result = PayRequest::instance()->createKlarnaTransaction($this->getParams(), $this->getHeaders());
            if (1 === $result['code']) {
                return $this->endSuccess($result['data']);
            } else {
                return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Throwable $e) {
            Yii::error('CreateKlarnaTransaction:' . $e->getMessage() . $e->getTraceAsString());
            return $this->endFail(1004, 'exception');
        }
    }

    /**
     * klarna支付
     */
    public function actionKlarnaPay()
    {
        try {
            //$result = PayRequest::instance()->klarnaPay($this->getParams(), $this->getHeaders());
            //切换到newpay
            $result = NewPayRequest::instance()->klarnaPay($this->getParams(), $this->getHeaders());
            if (1 === $result['code']) {
                return $this->endSuccess($result['data']);
            } else {
                return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Throwable $e) {
            Yii::error('klarnaPay:' . $e->getMessage() . $e->getTraceAsString());
            return $this->endFail(1004, 'exception');
        }
    }

    /**
     * Klarna更新会话
     */
    public function actionUpdateKlarnaSession()
    {
        try {
            $result = PayRequest::instance()->updateKlarnaSession($this->getParams(), $this->getHeaders());
            if (1 === $result['code']) {
                return $this->endSuccess($result['data']);
            } else {
                return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Throwable $e) {
            Yii::error('updateKlarnaSession:' . $e->getMessage() . $e->getTraceAsString());
            return $this->endFail(1004, 'exception');
        }
    }

    /**
     * 支付成功更新订单信息
     */
    public function actionUpdateOrderSuccess()
    {
        try {
            $result = NewPayRequest::instance()->updatePaymentSuccess($this->getParams(), $this->getHeaders());
            if (1 === $result['code']) {
                return $this->endSuccess($result['data']);
            } else {
                return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Throwable $e) {
            Yii::error('actionUpdateOrderSuccess:' . $e->getMessage() . $e->getTraceAsString());
            return $this->endFail(1004, 'exception');
        }
    }

    /**
     * air wallex 异步通知地址
     *
     * @throws \yii\base\ExitException
     */
    public function actionAirwallexLookUp()
    {
        try {
            $params = Yii::$app->request->post();
            //$result = PayRequest::instance()->airwallexLookUp($params);
            //切换到newpay处理
            $result = NewPayRequest::instance()->airwallexLookUp($params);
            if (1 === $result['code']) {
                return $this->endSuccess($result['data']);
            } else {
                return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            return $this->endFail(1004, 'exception');
        }
    }

    /**
     * air wallex 异步通知地址
     *
     * @throws \yii\base\ExitException
     */
    public function actionCallBackAirwallexReturnUrl()
    {
        try {
            $postData            = Yii::$app->request->post();
            $paramsData          = array_merge($postData, Yii::$app->request->get());
            $rawBody             = $this->request->getRawBody();
            $rawBody             = !empty($rawBody) ? @json_decode($rawBody, true) : [];
            $rawBody             = is_array($rawBody) ? $rawBody : [];
            $params              = array_merge($rawBody, $paramsData);
            $params['post_data'] = $postData;
            //切换到newpay处理
            NewPayRequest::instance()->airwallexConfirmCallback($params);
            //$result              = PayRequest::instance()->callBackAirwallexReturnUrl($params);
            //if (1 === $result['code']) {
            //    return $this->endSuccess($result['data']);
            //} else {
            //    return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            //}
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
//            return $this->endFail(1004, 'exception');
        }
        return Yii::$app->view->renderFile('@frontapi/web/html/loading.html');
    }

    /**
     * air wallex 轮询接口
     *
     * @throws \yii\base\ExitException
     */
    public function actionAirwallexQuery()
    {
        try {
            $params = Yii::$app->request->post();
            //$result = PayRequest::instance()->airwallexQuery($params);
            //切换到newpay处理
            $result = NewPayRequest::instance()->airwallexQuery($params);
            if (1 === $result['code']) {
                return $this->endSuccess($result['data']);
            } else {
                return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            return $this->endFail(1004, 'exception');
        }
    }

    public function actionWorldpayConfirm()
    {
        try {
            $params = $this->request->post();
            Yii::info(['WorldpayConfirm', $params]);
            if (empty($params)) {
                $this->endFail(4444, 'body is empty');
            }
            $result = NewPayRequest::instance()->worldpayConfirm($params);

        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
        }

        return Yii::$app->view->renderFile('@frontapi/web/html/loading.html');
    }

    public function actionWorldpayWebhook()
    {
        try {
            $body = $this->request->getRawBody();
            Yii::info(['WorldpayWebhook', $body]);
            if (empty($body)) {
                $this->endFail(4444, 'body is empty');
            }
            $headers = $this->getAllHeaders();
            $params  = [];
            parse_str($body, $params);
            //其他事件统一接入newpay处理
            $result = NewPayRequest::instance()->worldpayWebhook($params, $headers);
            Yii::info([$result, $params]);
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
        }

        //worldpay 要求webhook，返回[ok]
        echo '[ok]';
        Yii::$app->end();
    }

    /**
     * worldpay 轮询接口
     *
     * @throws \yii\base\ExitException
     */
    public function actionWorldpayQuery()
    {
        try {
            $params = Yii::$app->request->post();
            $result = NewPayRequest::instance()->worldpayQuery($params);
            if (1 === $result['code']) {
                return $this->endSuccess($result['data']);
            } else {
                return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            return $this->endFail(1004, 'exception');
        }
    }

    /**
     * 获取基础支付方式列表(仅获取支付方式的基础信息)
     */
    public function actionGetBasePaymentMethodList()
    {
        try {
            $params = Yii::$app->request->post();
            $result = NewPayRequest::instance()->getBasePaymentMethodList($params);
            if (1 === $result['code']) {
                $this->endSuccess($result['data']);
            } else {
                $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            $this->endFail(1004, 'exception');
        }
    }

    /**
     * 获取带有支付参数的支付方式列表
     */
    public function actionGetPaymentMethodList()
    {
        try {
            $params = Yii::$app->request->post();
            $result = NewPayRequest::instance()->getPaymentMethodList($params);
            if (1 === $result['code']) {
                $this->endSuccess($result['data']);
            } else {
                $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            $this->endFail(1004, 'exception');
        }
    }

    /**
     * 支付接口
     */
    public function actionPay()
    {
        try {
            $params               = Yii::$app->request->post();
            $header               = $this->getHeaders();
            $params['log_str']    = uniqid('pay_pay_');
            $params['ip_address'] = $this->JPGetRealIp();
            $result               = NewPayRequest::instance()->pay($params, $header);
            if (1 === $result['code']) {
                $this->endSuccess($result['data']);
            } else {
                $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            $this->endFail(1004, 'exception');
        }
    }

    /**
     * 获取用户的真实ip,仅适用于cgi运行方式，cli运行方式请不要使用
     *
     * @return string IP十五位字符，
     */
    private function JPGetRealIp()
    {
        $ip = getenv("HTTP_X_FORWARDED_FOR");
        if (empty($ip)) {
            $ip = getenv("REMOTE_ADDR");
        }

        $ip = explode(',', $ip);
        return trim($ip[0]) ?? '';
    }

    /**
     * 去支付接口（支付前数据处理）
     */
    public function actionStartPayment()
    {
        try {
            $params = Yii::$app->request->post();
            $header = $this->getHeaders();
            $result = NewPayRequest::instance()->startPayment($params, $header);
            if (1 === $result['code']) {
                $this->endSuccess($result['data']);
            } else {
                $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            $this->endFail(1004, 'exception');
        }
    }

    /**
     * 3DS授权认证处理
     */
    public function actionAuthorize()
    {
        try {
            $params = Yii::$app->request->post();
            $header = $this->getHeaders();
            $result = NewPayRequest::instance()->authorize($params, $header);
            if (1 === $result['code']) {
                $this->endSuccess($result['data']);
            } else {
                $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            $this->endFail(1004, 'exception');
        }
    }

    /**
     * 支付日志记录
     */
    public function actionPaymentLogging()
    {
        try {
            $params = Yii::$app->request->post();
            $header = $this->getHeaders();
            $result = NewPayRequest::instance()->paymentLogging($params, $header);
            if (1 === $result['code']) {
                $this->endSuccess($result['data']);
            } else {
                $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            $this->endFail(1004, 'exception');
        }
    }

    /**
     * 支付失败上报
     */
    public function actionPaymentFailReport()
    {
        try {
            $params = Yii::$app->request->post();
            $header = $this->getHeaders();
            $result = NewPayRequest::instance()->paymentFailReport($params, $header);
            if (1 === $result['code']) {
                $this->endSuccess($result['data']);
            } else {
                $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            $this->endFail(1004, 'exception');
        }
    }

    /**
     * 支付事件日志记录
     */
    public function actionPaymentEventLogging()
    {
        try {
            $params = Yii::$app->request->post();
            $header = $this->getHeaders();
            $result = NewPayRequest::instance()->paymentEventLogging($params, $header);
            if (1 === $result['code']) {
                $this->endSuccess($result['data']);
            } else {
                $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            $this->endFail(1004, 'exception');
        }
    }

    /**
     * 组装支付失败上报参数
     */
    public function actionGetPaymentFailEventParams()
    {
        try {
            $params = Yii::$app->request->post();
            $header = $this->getHeaders();
            $result = NewPayRequest::instance()->getPaymentFailEventParams($params, $header);
            if (1 === $result['code']) {
                $this->endSuccess($result['data']);
            } else {
                $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            $this->endFail(1004, 'exception');
        }
    }

    /**
     * 获取klarna初始化数据
     *
     * @return void
     * @throws \yii\base\ExitException
     */
    public function actionGetKlarnaInitData()
    {
        try {
            $params = Yii::$app->request->get();
            $header = $this->getHeaders();
            $result = NewPayRequest::instance()->getKlarnaInitData($params, $header);
            if (1 === $result['code']) {
                $this->endSuccess($result['data']);
            } else {
                $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            $this->endFail(1004, 'exception');
        }
    }

    /**
     * stripe 3DS确认完成回调
     *
     * @throws \yii\base\ExitException
     */
    public function actionStripeConfirmCallback()
    {
        $params = $this->request->get();
        try {
            Yii::info(['StripeConfirmCallback', $params]);
            if (empty($params)) {
                $this->endFail(4444, 'body is empty');
            }
            $result = NewPayRequest::instance()->stripeConfirmCallback($params, $this->getHeaders());

        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
        }
        return Yii::$app->view->renderFile('@frontapi/web/html/loading.html');
    }

    /**
     * 支付确认接口
     */
    public function actionConfirm()
    {
        try {
            $params               = Yii::$app->request->post();
            $header               = $this->getHeaders();
            $params['log_str']    = uniqid('pay_confirm_');
            $params['ip_address'] = $this->JPGetRealIp();
            $result               = NewPayRequest::instance()->confirm($params, $header);
            if (1 === $result['code']) {
                $this->endSuccess($result['data']);
            } else {
                $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
            }
        } catch (\Exception $e) {
            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            $this->endFail(1004, 'exception');
        }
    }

    /**
     * Useepay 3DS完成跳转地址（中间页，loading）
     *
     */
    public function actionUseepayConfirm()
    {
        try {
            $params               = Yii::$app->request->get();
            $header               = $this->getHeaders();
            $params['ip_address'] = $this->JPGetRealIp();
            $result               = NewPayRequest::instance()->useepayConfirmCallback($params, $header);
        } catch (\Exception $e) {
        }
        return Yii::$app->view->renderFile('@frontapi/web/html/loading.html');
    }
}
