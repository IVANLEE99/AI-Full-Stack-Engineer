<?php

namespace common\factory\payment;

use App\Utils\MyFunction;
use common\repositorys\pay\PaymentMethodSubjectRepository;
use common\repositorys\pay\PaymentRepository;
use common\services\pay\PaymentMethodService;
use common\services\pay\PaymentSubjectService;
use common\services\pay\PayService;
use common\services\payment\AffirmService;
use common\services\payment\AfterPayService;
use common\services\payment\AirWallexService;
use common\services\payment\BankTransferService;
use common\services\payment\BraintreeService;
use common\services\payment\KlarnaService;
use common\services\payment\OtherPayService;
use common\services\payment\PaypalService;
use common\services\payment\PingPongService;
use common\services\payment\StripeService;
use common\services\payment\UseepayService;
use common\services\payment\WorldpayService;
use common\services\payment\ZiniaService;

class PaymentFactory
{
    /**
     * 根据支付公司实例化
     *
     * @param string $paymentCompany     支付公司
     * @param string $site               站点
     * @param array  $cardInfo           卡信息
     * @param string $paymentMethod      支付方式
     * @param string $paymentSubject     支付主体名称
     * @param int    $paymentSubjectType 支付主体类型 1：线上账号主体，2：线下账号主体
     *
     * @return PaymentInterface
     */
    public static function instance($paymentCompany, $site = '', $cardInfo = [], $paymentSubject = '', $paymentMethod = '', $paymentSubjectType = PaymentMethodSubjectRepository::TYPE_ONLINE)
    {
        $config = [];
        if (PaymentRepository::PAY_BY_OTHER != $paymentCompany) {
            //获取支付账号秘钥配置
            $config = PayService::instance()->getPaymentChannelConfig($paymentCompany, $site, $paymentSubject, $paymentMethod, $paymentSubjectType);
            if (null === $config) {
                return null;
            }
        }
        switch ($paymentCompany) {
            case PaymentRepository::PAY_BY_BRAINTREE:
                return BraintreeService::instance();

            case PaymentRepository::PAY_BY_KLARNA:
                return KlarnaService::getSiteInstance($site, $config);

            case PaymentRepository::PAY_BY_AFFIRM:
                return AffirmService::instance();

            case PaymentRepository::PAY_BY_AIRWALLEX:
                return AirWallexService::getSiteInstance($site, $cardInfo, $config);

            case PaymentRepository::PAY_BY_STRIPR:
                return StripeService::getSiteInstance($site, $config);

            case PaymentRepository::PAY_BY_WORLDPAY:
                return WorldpayService::getSiteInstance($site, $config);

            case PaymentRepository::PAY_BY_BANK_TRANSFER:
                return BankTransferService::instance();

            case PaymentRepository::PAY_BY_PAYPAL: //paypal直连
                return PaypalService::getSiteInstance($site, $config);

            case PaymentRepository::PAY_BY_AFTERPAY: //afterpay
                return AfterPayService::getSiteInstance($site, $config);
            case PaymentRepository::PAY_BY_OTHER: //其他支付
                return OtherPayService::instance();
            case PaymentRepository::PAY_BY_PINGPONG: //pingpong
                return PingPongService::getSiteInstance($site, $config);
            case PaymentRepository::PAY_BY_USEEPAY:
                return UseepayService::getSiteInstance($config);
            case PaymentRepository::PAY_BY_ZINIA:
                return ZiniaService::getSiteInstance($site, $config);
            default:
                return null;
        }
    }
}
