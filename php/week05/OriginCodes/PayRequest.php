<?php

namespace fecshop\services\http;

use common\BaseApi;
use yii\httpclient\Client;

class PayRequest extends BaseApi
{
    /**
     * 获取api域名地址
     *
     * @return string
     */
    protected function getBaseUrl()
    {
        return 'http://pay.internal.bm.com/';  //内网
    }

    /**
     * 支付单下载链接
     *
     * @return string
     * @author 白杨
     * @Date   2021/5/21 下午3:30
     */
    public function getPaymentDownloadUrl()
    {
        return $this->getBaseUrl() . "payment-query/pay-page-list";
    }

    /**
     * 退款单列表下载链接
     *
     * @return string
     * @author 白杨
     * @Date   2021/5/21 下午3:31
     */
    public function getRefundDownloadUrl()
    {
        return $this->getBaseUrl() . "refund-query/refund-page-list";
    }


    /**
     * 访问内网分期确认授权和捕获信用卡交易
     *
     * @param array $params
     *
     * @return array|bool|mixed
     */
    public function affirmConfirm($params = [])
    {
        return $this->post('pay/affirm-confirm', $params);
    }

    /**
     * 测试分期付款成功后回调
     */
    public function callaBackAffirmConfirm($params = [])
    {
        return $this->post('pay/call-back-affirm-confirm', $params);
    }

    /**
     * airwallex异步通知地址
     */
    public function airwallexLookUp($params = [])
    {
        return $this->post('pay/airwallex-look-up', $params);
    }

    /**
     * airwallex异步通知地址
     */
    public function callBackAirwallexReturnUrl($params = [])
    {
        return $this->post('pay/call-back-airwallex-return-url', $params);
    }

    /**
     * airwallex轮询接口
     */
    public function airwallexQuery($params = [])
    {
        return $this->post('pay/airwallex-query', $params);
    }

    /**
     * 支付成功发送邮件
     */
    public function sendEmail($params = [])
    {
        return $this->post('pay/send-email', $params);
    }

    /**
     * braintree交易单创建
     *
     * @param $params
     *
     * @return array|bool|mixed
     * @author 白杨
     * @Date   2021/2/2 下午2:23
     */
    public function braintreeCreateTransaction($params)
    {
        return $this->post('pay/braintree-confirm', $params);
    }

    /**
     * paypal快捷支付
     *
     * @param $params
     *
     * @return array
     * @author liangchupeng
     * @since  2021.07.05
     */
    public function createPaypalQuickPayment($params)
    {
        return $this->post('pay/paypal-quick-payment', $params);
    }

    public function bankTransfer($params)
    {
        return $this->post('pay/bank-confirm', $params);
    }


    /**
     * 访问内网获取Braintree生成的客户端令牌
     *
     * @param array $params
     *
     * @return array|bool|mixed
     */
    public function generateClientToken($params = [])
    {
        return $this->post('braintree/client-token', $params);
    }


    /**
     * 访问内网创建交易 - Braintree
     *
     * @param array $params
     *
     * @return array|bool|mixed
     */
    public function createTransaction($params = [])
    {
        return $this->post('braintree/create-transaction', $params);
    }

    public function paymentMethods($params)
    {
        return $this->get('pay/payment-methods', $params);
    }

    public function paymentParams($params)
    {
        return $this->post('pay/payment-params', $params);
    }

    public function paySuccess($params)
    {
        return $this->get('pay/success', $params);
    }

    public function payFail($params)
    {
        return $this->get('pay/fail', $params);
    }

    /**
     * 线下支付确认收款
     */
    public function offlineConfirm($params, $header)
    {
        return $this->post('payment/offline-confirm', $params, $header, []);
    }

    /**
     * 支付冲销
     */
    public function paymentVoid($params, $header)
    {
        return $this->post('payment/void', $params, $header, []);
    }

    /**
     * 支付退款
     */
    public function paymentRefund($params, $header)
    {
        return $this->post('payment/refund', $params, $header, []);
    }

    /**
     * 支付关闭
     */
    public function paymentClose($params, $header)
    {
        return $this->post('payment/close', $params, $header, []);
    }

    /**
     * 提交结算
     */
    public function submittedForSettlement($params, $header)
    {
        return $this->post('payment/submit-for-settle', $params, $header, []);
    }

    /**
     * 风控通过
     */
    public function riskPass($params, $header)
    {
        return $this->post('payment/risk-pass', $params, $header, []);
    }

    /**
     * 待审核审核订单变成已支付
     */
    public function changeToPaid($params, $header)
    {
        return $this->post('payment/change-to-paid', $params, $header, []);
    }

    /**
     * 售后单确认退款
     */
    public function confirmRefund($params, $header)
    {
        return $this->post('refund/confirm-refund', $params, $header, []);
    }

    /**
     * 退款单执行退款
     */
    public function paymentApplyRefund($params, $header)
    {
        return $this->post('refund/payment-apply-refund', $params, $header, []);
    }

    /**
     * 售后单主动退款
     */
    public function artificialRefund($params, $header)
    {
        return $this->post('refund/artificial-refund', $params, $header, []);
    }

    /**
     * 获取支付订单列表
     *
     * @param $params
     * @param $header
     *
     * @return array|bool|mixed
     */
    public function payPageList($params, $header)
    {
        return $this->post('payment-query/pay-page-list', $params, $header, []);
    }

    /**
     * 获取支付单详情
     *
     * @param $params
     * @param $header
     *
     * @return array|bool|mixed
     */
    public function payDetail($params, $header)
    {
        return $this->post('payment-query/pay-detail', $params, $header, []);
    }

    /**
     * 获取退款订单列表
     *
     * @param $params
     * @param $header
     *
     * @return array|bool|mixed
     */
    public function refundPageList($params, $header)
    {
        return $this->post('refund-query/refund-page-list', $params, $header, []);
    }

    /**
     * 获取退款单详情
     *
     * @param $params
     * @param $header
     *
     * @return array|bool|mixed
     */
    public function refundDetail($params, $header)
    {
        return $this->post('refund-query/refund-detail', $params, $header, []);
    }

    /**
     * 获取退款creditNote下载文件的内容
     *
     * @param $params
     * @param $header
     *
     * @return array|bool|mixed
     */
    public function getCreditNoteContent($params, $header)
    {
        return $this->post('refund-query/get-credit-note-content', $params, $header, []);
    }

    public function getMethodIconList($params, $header = [])
    {
        return $this->get('pay/get-method-icon-list', $params, $header, []);
    }

    /**
     * 模拟支付
     *
     * @param       $params
     * @param array $header
     *
     * @return array|bool|mixed
     * @author 白杨
     * @Date   19/8/21 下午6:38
     */
    public function simulatePay($params, $header = [])
    {
        return $this->get('pay/simulate-pay', $params, $header, []);
    }

    public function closeTest($params, $header = [])
    {
        return $this->get('pay/close-test', $params, $header, []);
    }

    public function changeToOffline($params, $header = [])
    {
        return $this->post('refund/change-to-offline', $params, $header, []);
    }

    public function financeReview($params, $header = [])
    {
        return $this->post('refund/finance-review', $params, $header, []);
    }

    public function finalConfirm($params, $header = [])
    {
        return $this->post('refund/final-confirm', $params, $header, []);
    }

    public function rufundLogList($params, $header = [])
    {
        return $this->post('refund-query/refund-log-list', $params, $header, []);
    }

    /**
     * 内网请求
     *
     * @param $uri
     * @param $params
     * @param $options
     *
     * @return array|bool|mixed
     */
    public function postData($uri, $params, $options = [])
    {
        return $this->post($uri, $params, [], $options);
    }

    // 线下支付，提交凭证
    public function offlineSubmit($params, $header = [])
    {
        return $this->post('payment/offline-submit', $params, $header, []);
    }

    /**
     * 获取支付单的支付状态
     *
     * @param       $params
     * @param array $header
     *
     * @return array|bool|mixed
     * @since  2022.02.07
     * @author liangchupeng
     */
    public function getPayStatus($params, $header = [])
    {
        return $this->post('pay/get-pay-status', $params, $header);
    }

    /**
     * klarna下单支付
     *
     * @param       $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function createKlarnaTransaction($params, $header = [])
    {
        return $this->post('pay/create-klarna-transaction', $params, $header, []);
    }

    /**
     * klarna支付
     *
     * @param       $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function klarnaPay($params, $header = [])
    {
        return $this->post('pay/klarna-pay', $params, $header, []);
    }

    /**
     * 支付成功更新订单信息
     *
     * @param       $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function updateOrderSuccess($params, $header = [])
    {
        return $this->post('pay/update-order-success', $params, $header, [CURLOPT_TIMEOUT => 30]);
    }

    /**
     * Klarna更新会话
     *
     * @param       $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function updateKlarnaSession($params, $header = [])
    {
        return $this->post('pay/update-klarna-session', $params, $header, []);
    }

    /**
     * airwallex webhook事件回调
     *
     * @param       $params
     * @param array $headers
     *
     * @return array|bool|mixed
     */
    public function airwallexCallback($params, $headers = [])
    {
        return $this->post('airwallex/callback', $params, $headers, []);
    }

    public function worldpayWebhook($params, $headers = [])
    {
        return $this->post('payment/worldpay-webhook', $params, $headers, []);
    }

    public function worldpayConfirm($params, $headers = [])
    {
        return $this->post('payment/worldpay-confirm', $params, $headers, []);
    }

    /**
     * worldpay轮询接口
     */
    public function worldpayQuery($params = [])
    {
        return $this->post('payment/worldpay-query', $params);
    }
}
