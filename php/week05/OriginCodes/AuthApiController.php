<?php

namespace fecshop\app\frontapi\modules;

use fecshop\app\frontapi\filters\common\VerifySignatureFilter;
use fecshop\app\frontapi\filters\LoginAuthFilter;

class AuthApiController extends BaseApiController
{
    //不需要登陆认证的接口地址
    private $freeLoginAuthApiList = [
        'pay/pay/paypal-quick-payment', //paypal快捷支付
        'pay/pay/get-method-icon-list', //获取支付列表icon
        'pay/pay/call-back-affirm-confirm', //affirm的回调记录
        'pay/pay/simulate-pay', //模拟支付
        'order/order/get-service-guarantee', //服务保障
        'order/order/get-trans-snapshot',//订单快照
        'order/trade/confirm',//交易确认
        'order/after-exchange/confirm',
        'order/after-exchange/input',
        'order/after-exchange/exchange-info',
        'order/after-exchange/goods-select',
        'order/order/goods-list-check',//订单商品检查
        'pay/pay/methods', //获取支付列表
        'order/added-service/list', //获取增值服务列表
        'order/added-service/service-list', //获取增值服务列表
        'order/added-service/detail', //获取增值服务详情
        'market/coupon/recommend',//优惠券推荐
        'market/coupon/cart-sku-coupon-list',//购物车-多商品优惠券列表
        'market/coupon/popup-user-coupon-list',//弹窗-用户已领取的优惠券列表
        'market/coupon/get-item-coupon-list',//详情页优惠券列表
        'market/coupon/get-sku-best-coupon',//批量查询sku最优券
        'market/coupon/get-coupon-turntable-info',//新人优惠券转盘信息
        'market/coupon/collect-info',
        'market/coupon/get-new-coupon-suggest-popup',//获取新人券推荐弹窗
        'pay/pay/get-pay-status', //获取支付单状态
        'pay/pay/payment-params', //获取支付支付参数
        'pay/pay/braintree-create-transaction',//braintree创建交易单
        'pay/payment-request/success',//发付请支付成功
        'pay/pay/update-order-success',//空中云汇 支付成功，更新支付单
        'order/confirm/new-info',
        'order/order-v3/detail',
        'order/order/get-order-service-guarantee',
        'order/order/get-order-recommend-goods',
        // 售后优化
        'order/order/for-after-sale-to-user',
        'order/order/get-ship-and-bill-address',
        'order/after-sale/all-reason-to-user',
        'order/after-sale/apply-after-sale-page-solution',
        'order/after-sale/apply-amount-calculate',
        'order/after-sale/apply-self-exchange',
        'order/after-sale/apply-after-sale-v2',
        'order/order/order-address-detail',
        'order/order/modify-address',
        'order/order/update-bill-address',
        'pay/pay/airwallex-look-up',
        'pay/pay/airwallex-query',
        'pay/pay/call-back-airwallex-return-url',
        //收藏夹
        'user/favorate-list/create',
        'user/favorate-list/move-another-list',
        'user/favorate-list/rename',
        'user/favorate-list/del-list',
        'user/favorate-list/remove-all-sku',
        'user/favorate-list/get-list',
        'user/favorate-list/idea-list-detail',
        'user/favorate-list/sku-move-another-list',
        //收藏
        'user/favorate/sku-add',
        'user/favorate/sku-cancel',
        'user/favorate/sku-list',
        'user/favorate/sku-check',
        'user/favorate/all-sku',
        'user/favorate/idea-add',
        'user/favorate/batch-sku-add',
        'user/favorate/batch-idea-add',
        'user/favorate/all-idea',
        'user/favorate/idea-cancel',
        'user/favorate/product-list-detail',
        'user/favorate/product-list-filter-data',
        'pay/pay/worldpay-webhook',
        'pay/pay/worldpay-confirm',
        'pay/pay/stripe-confirm-callback',
        'pay/pay/useepay-confirm',
        // 新登录注册
        'user/code/login',
        'user/code/verify',
        // 换货页推荐链接
        'order/after-sale/get-replacement-product-list',
        'order/after-sale/get-recommend-replacement-product-list',
        'order/after-sale/get-self-exchange-goods-list',
        'order/after-sale/calculate-exchange-goods-amount',
        // 放权路由
        'order/order/get-invoice',
        'order/order/get-info-security',
        'order/delivery-or-pick-up/cart-list',
        // 退货退款
        'order/after-sale/get-return-sticker-data',
        'order/after-sale/download-pdf',
        'order/invite-comments/verify',
        'order/feedback/refer',
        'order/feedback/get-list',
        //获取优先
        'pay/pay/get-klarna-init-data',
        'pay/pay-credential/create',
        // 订阅劵信息
        'market/coupon/get-subscribe-coupon',
        'market/coupon/get-subscribe-coupon-setting',
        'market/coupon/report-preferences',
        //申请售后
        'order/after-sale/verify-apply',
        'order/added-service/get-task-rabbit-detail',
        'order/order/get-order-info',
        //门店
        'store/store/store-user-authorize',
        'store/store/store-promotion-authorize',
        'order/order/get-order-info',
        //impact不需要登录校验
        'order/impact/convert',
    ];

    //默认进行登陆认证
    private $loginAuth = true;

    public function beforeAction($action)
    {
        if (in_array(strtolower(\Yii::$app->request->getPathInfo()), $this->freeLoginAuthApiList)) {
            $this->loginAuth = false;
        }
        return parent::beforeAction($action);
    }

    /**
     * @return array
     * @property array $member
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        if ($this->loginAuth) {
            $behaviors['loginAuthFilter'] = [
                'class' => LoginAuthFilter::class,
            ];
        }

        $behaviors['VerifySignature'] = [
            'class' => VerifySignatureFilter::className(),
        ];

        return $behaviors;
    }

    /**
     * 获取用户的真实ip,仅适用于cgi运行方式，cli运行方式请不要使用
     *
     * @return string IP十五位字符，
     */
    protected function getRealIp()
    {
        $ip = getenv("HTTP_X_FORWARDED_FOR");
        if (empty($ip)) {
            $ip = getenv("REMOTE_ADDR");
        }

        $ip = explode(',', $ip);
        return trim($ip[0]) ?? '';
    }
}
