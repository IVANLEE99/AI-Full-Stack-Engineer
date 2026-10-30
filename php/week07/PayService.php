<?php
/*
 * @Author: zhengcongfeng
 * @Date: 2021-01-20 11:18:00
 * @LastEditTime: 2021-03-31 18:23:23
 * @LastEditors: Please set LastEditors
 * @Description: In User Settings Edit
 * @FilePath: \internal.com\common\services\pay\PayService.php
 */

namespace common\services\pay;

use App\Email\Email;
use App\Utils\BaseFunction;
use App\Utils\EnterpriseWechatMessage;
use App\Utils\MyFunction;
use App\Utils\NodeExecutionEngine;
use AppPayApi\controllers\api\PayInternal;
use AppPayApi\lib\Code;
use common\AbExperimentService;
use common\BaseService;
use common\enums\payment\CreditCardAbValue;
use common\enums\payment\PaymentErrorCodeMapping;
use common\redis\pay\PaymentRedis;
use common\repositorys\order\OrderRepository;
use common\repositorys\pay\PaymentAirwallexConfirmRepository;
use common\repositorys\pay\PaymentLogRepository;
use common\repositorys\pay\PaymentMethodRepository;
use common\repositorys\pay\PaymentMethodSettingRepository;
use common\repositorys\pay\PaymentRepository;
use common\repositorys\user\UserRepository;
use common\services\common\MqService;
use common\services\order\contexts\place\PlaceOrderContext;
use common\services\order\nodes\trade\PaymentMethodsGetNode;
use common\services\order\OrderService;
use common\services\order\PlaceOrderService;
use common\services\pay\contexts\pay\PayCreateContext;
use common\services\pay\contexts\pay\PaymentMethodsContext;
use common\services\pay\contexts\pay\PayParamsContext;
use common\services\pay\contexts\pay\PaySuccessContext;
use common\services\pay\nodes\common\GetOrderDataNode;
use common\services\pay\nodes\common\GetPaymentNode;
use common\services\pay\nodes\common\PaymentCheckNode;
use common\services\pay\nodes\common\PaymentSingleGetNode;
use common\services\pay\nodes\pay\BillAddressSaveNode;
use common\services\pay\nodes\pay\FreightOptionsHandleNode;
use common\services\pay\nodes\pay\PayCreateNode;
use common\services\pay\nodes\pay\ParamsByMethodNode;
use common\services\pay\nodes\pay\PaymentBusinessGetNode;
use common\services\pay\nodes\pay\paymentMethodsFormatNode;
use common\services\pay\nodes\pay\PaymentMethodsNode;
use common\services\pay\nodes\pay\PaymentSubjectTypeGetByOrderNoNode;
use common\services\pay\nodes\pay\PaySuccessNode;
use common\services\pay\nodes\pay\YouAlsoLikeNode;
use common\services\pay\nodes\paymentRequest\LoginJudgeNode;
use common\services\pay\nodes\paymentRequest\SuccessNode;
use common\services\site\BasicDataService;
use common\services\system\ConfigService;

class PayService extends BaseService
{
    /**
     * 支付列表
     *
     * @param $params
     *
     * @return array
     * @author 白杨
     * @Date   2021/2/1 上午11:22
     */
    public function paymentMethods($params)
    {
        g_log_info('paymentMethods.log', '支付列表', $params);
        try {
            //组织上下文数据
            $context = new PaymentMethodsContext();
            $this->contextInit($context, $params);
            $context->paymentNo          = $params['payment_no'];
            $context->userId             = $params['user_id'];
            $context->paymentMethod      = $params['payment_method'];
            $context->experimentDiverted = $params['experiment_diverted_sensors'] ?? '';
            $context->fromPage           = $params['from_page'] ?? 'NORMAL_PAY_PAGE';

            $nodeChain = [
                PaymentSingleGetNode::instance(),
                PaymentBusinessGetNode::instance(),
                PaymentCheckNode::instance(),
                PaymentMethodsGetNode::instance(),
                //LoginJudgeNode::instance(),
                PaymentMethodsNode::instance(),
                paymentMethodsFormatNode::instance(),
            ];


            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
            if (1 == $result['code']) {
                //个别支付需要用到地址信息
                $context->response['address'] = $this->getOrderAddress($context);

                g_log_info('paymentMethods.log', '支付列表,成功', [$result, $params]);
                return $this->returnSuccess($context->response, 'success');
            } else {
                g_log_error('paymentMethods.log', '支付列表,失败', [$result, $params]);
                return $result;
            }
        } catch (\Exception $e) {
            g_log_error('paymentMethods.log', '支付列表,异常', [$e->getMessage() . $e->getTraceAsString(), $params]);
            return $this->returnFormat($e->getCode(), null, $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 支付参数
     *
     * @param $params
     *
     * @return array
     * @author 白杨
     * @Date   2021/2/1 上午11:22
     */
    public function paymentParams($params)
    {
        g_log_info('paymentParams.log', '支付数据', $params);
        try {
            //组织上下文数据
            $context = new PayParamsContext();
            $this->contextInit($context, $params);
            $context->paymentNo     = $params['payment_no'];
            $context->paymentMethod = $params['payment_method'];
            $context->userId        = $params['user_id'];
            $context->cardDataInfo  = !empty($params['card_info']) ? $params['card_info'] : ($params['card_data_info'] ?? []);
            $context->cardInfo      = $context->cardDataInfo;
            $context->ipAddress     = $params['ip_address'] ?? '';
            $context->deviceId      = $params['device_id'] ?? '';

            $nodeChain = [
                PaymentSingleGetNode::instance(),
                PaymentBusinessGetNode::instance(),
                PaymentCheckNode::instance(),
                BillAddressSaveNode::instance(),
                ParamsByMethodNode::instance(),
            ];

            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
            if (1 == $result['code']) {
                g_log_info('paymentParams.log', '支付数据成功', [$result, $params]);
                return $this->returnSuccess($context->response, 'success');
            } else {
                g_log_error('paymentParams.log', '支付数据失败', [$result, $params]);
                return $result;
            }
        } catch (\Exception $e) {
            g_log_error('paymentParams.log', '支付数据,异常', [$e->getMessage() . $e->getTraceAsString(), $params]);
            return $this->returnFormat($e->getCode(), null, $e->getMessage() . $e->getTraceAsString());
        }
    }

    public function paymentRequestSuccess($params)
    {
        g_log_info('paymentParams.log', '支付数据', $params);
        try {
            //组织上下文数据
            $context = new \common\services\pay\contexts\paymentRequest\PaySuccessContext();
            $this->contextInit($context, $params);
            $context->paymentNo = $params['payment_no'];
            $context->userId    = $params['user_id'];
            $context->isTourist = $params['is_tourist'] ?? UserRepository::IS_REGISTER;//用户标示

            $nodeChain = [
                PaymentSingleGetNode::instance(),
                PaymentBusinessGetNode::instance(),
                SuccessNode::instance(),
            ];

            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
            if (1 == $result['code']) {
                g_log_info('paymentParams.log', '支付数据成功', [$result, $params]);
                return $this->returnSuccess($context->response, 'success');
            } else {
                g_log_error('paymentParams.log', '支付数据失败', [$result, $params]);
                return $result;
            }
        } catch (\Exception $e) {
            g_log_error('paymentParams.log', '支付数据,异常', [$e->getMessage() . $e->getTraceAsString(), $params]);
            return $this->returnFormat($e->getCode(), null, $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 支付成功
     *
     * @param $params
     *
     * @return array
     * @author 白杨
     * @Date   2021/2/1 上午11:22
     */
    public function paySuccess($params)
    {

        g_log_info('paySuccess.log', '支付成功', $params);
        try {
            //组织上下文数据
            $context = new PaySuccessContext();
            $this->contextInit($context, $params);
            $context->paymentNo = $params['payment_no'];
            $context->orderNo   = $params['order_no'];
            $context->userId    = $params['user_id'];
            $context->isTourist = $params['is_tourist'] ?? UserRepository::IS_REGISTER;//用户标示

            $nodeChain = [
                GetOrderDataNode::instance(),
                GetPaymentNode::instance(),
                FreightOptionsHandleNode::instance(),
                PaySuccessNode::instance(),
                YouAlsoLikeNode::instance(),
            ];

            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
            if (1 == $result['code']) {
                g_log_info('paySuccess.log', '支付成功', [$result, $params]);
                return $this->returnSuccess($context->response, 'success');
            } else {
                g_log_error('paySuccess.log', '支付成功,异常', [$result, $params]);
                return $result;
            }
        } catch (\Exception $e) {
            g_log_error('paySuccess.log', '支付成功,异常', [$e->getMessage() . $e->getTraceAsString(), $params]);
            return $this->returnFormat($e->getCode(), null, $e->getMessage() . $e->getTraceAsString());
        }
    }

    public function getBankAccountData(&$method, $currencyCode)
    {
        $bankCardAccount = $this->getBankCardAccount($currencyCode);

        $accountData            = [
            ['key' => \Yii::t('common/app', 'Account Name'), 'value' => $bankCardAccount['account_name'],],
            ['key' => \Yii::t('common/app', 'Account Number'), 'value' => $bankCardAccount['account_number'],],
            ['key' => \Yii::t('common/app', 'Bank Name'), 'value' => $bankCardAccount['bank_name']],
            ['key' => \Yii::t('common/app', 'Bank Address'), 'value' => $bankCardAccount['bank_address']],
            ['key' => \Yii::t('common/app', 'Swift Code'), 'value' => $bankCardAccount['swift_code']],
        ];
        $method['bank_account'] = $accountData;
        $method['bank_notice']  = [
            \Yii::t('common/app', 'Bank Account Customers choosing ...'),
            \Yii::t('common/app', 'Bank Account After the payment ...'),
        ];
    }

    /**
     * 线下付款账户
     *
     * @param $currencyCode
     *
     * @return array
     */
    public function getBankCardAccount($currencyCode)
    {
        $currencyCode        = strtolower($currencyCode);
        $bankCardAccountList = BasicDataService::BANK_CARD_ACCOUNT_LIST;
        $bankCardAccountList = array_column($bankCardAccountList, null, 'currency_code');
        $bankCardAccount     = $bankCardAccountList[$currencyCode] ?? [];
        if (empty($bankCardAccount)) {
            $bankCardAccount = $bankCardAccountList['usd'];
        }
        return $bankCardAccount;
    }

    /**
     * 支付失败
     *
     * @param $params
     *
     * @return array
     * @author 白杨
     * @Date   2021/2/1 上午11:22
     */
    public function payFail($params)
    {
        $payment = PaymentRepository::instance()->getByPaymentNo($params['payment_no']);
        $return  = [
            'title'         => \Yii::t('tag', 'Your order is not paid successfully'),
            'sub_title'     => \Yii::t('tag', 'Please repay'),
            'description'   => \Yii::t('tag', 'pay fail page our order：...', ['order_no' => $payment['business_no'] ?? '']),
            'business_type' => $payment['business_type'] ?? '',
            'business_no'   => $payment['business_no'] ?? '',
        ];
        return $this->returnSuccess($return);
    }

    /**
     * 创建支付单
     *
     * @param $params
     * @param $userId
     *
     * @return array
     * @author 白杨
     * @Date   2021/2/1 下午5:28
     */
    public function payCreate($params)
    {
        //实例化，初始化参数
        $context = new PayCreateContext();
        $this->contextInit($context, $params);
        $this->setPayCreateContext($context);

        $nodeChain = [
            //获取支付主体类型
            PaymentSubjectTypeGetByOrderNoNode::instance(),
            //创建支付单
            PayCreateNode::instance(),
        ];

        $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
        if (1 == $result['code']) {
            return $this->returnSuccess($context->response, 'success');
        } else {
            return $result;
        }
    }

    /**
     * 设置支付单创建参数
     *
     * @param PayCreateContext $context
     * @param string           $userId
     *
     * @author 白杨
     * @Date   2021/2/1 下午5:30
     */
    private function setPayCreateContext(PayCreateContext $context)
    {
        $context->site      = $context->params['site'] ?? '';
        $context->paymentNo = MyFunction::instance()->createUniqueNo('P', $context->site);
        $context->currency  = $context->params['currency'];
        $context->toUsdTate = ConfigService::instance()->getExchangeRate($context->currency, 'usd');
        $context->toCnyTate = ConfigService::instance()->getExchangeRate($context->currency, 'cny');

        $context->payAmount      = $context->params['pay_amount'];
        $context->amountsPayable = $context->params['amounts_payable'] ?? $context->payAmount;

        $context->businessType   = $context->params['business_type'];
        $context->businessNo     = $context->params['business_no'];
        $context->innerNotifyUrl = $context->params['inner_notify_url'];
        $context->localPaymentId = $context->params['local_payment_id'] ?? '';
        // 支付方式
        $context->paymentMethod = $context->params['payment_method'] ?? '';

        $context->expireAt   = $context->params['expire_at'] ?? 0;
        $context->payType    = $context->params['pay_type'] ?? 0;
        $context->orderNo    = $context->params['order_no'];
        $context->createFrom = (int)($context->params['create_from'] ?? 0);
    }

    /**
     * 下单时，更新支付单
     *
     * @param $params
     *
     * @return array
     * @author 白杨
     * @Date   2021/4/20 下午2:07
     */
    public function paymentUpdate($params)
    {
        g_log_info('paymentUpdate.log', '入参', $params);
        $update = [
            'currency'                => $params['currency'],
            'order_to_usd_rate'       => $params['order_to_usd_rate'],
            'order_to_cny_rate'       => $params['order_to_cny_rate'],
            'pay_amount'              => $params['pay_amount'],
            'usd_pay_amount'          => $params['usd_pay_amount'],
            'cny_pay_amount'          => $params['cny_pay_amount'],
            'payment_method'          => $params['payment_method'] ?? '',
            'payment_channel_company' => $params['payment_channel_company'] ?? '',
            'local_payment_id'        => $params['local_payment_id'] ?? '',
            // 应付金额
            'amounts_payable'         => $params['amounts_payable'],
            'usd_amounts_payable'     => $params['usd_amounts_payable'],
            'cny_amounts_payable'     => $params['cny_amounts_payable'],
            'out_id'                  => $params['out_id'] ?? '',
            'payment_subject_type'    => $params['payment_subject_type'] ?? PaymentRepository::SUBJECT_ONLINE,
            'updated_at'              => time(),
            'user_id'                 => $params['user_id'],
            'terminal_id'             => $params['terminal_id'] ?? 0,
        ];
        if (!empty($params['user_id'])) {
            $update['user_id'] = $params['user_id'];
        }

        if (isset($params['need_new_payment_no']) && $params['need_new_payment_no']) {
            $update['payment_no'] = MyFunction::instance()->createUniqueNo('P', $params['site']);
        }
        if (isset($params['payment_channel'])) {
            $update['payment_channel'] = $params['payment_channel'];
        }
        if (isset($params['payment_subject'])) {
            $update['payment_subject'] = $params['payment_subject'];
        }

        //校验数据是否一致
        $payment = PaymentRepository::instance()->getByPaymentNo($params['payment_no']);
        $isSame  = true;
        foreach ($update as $k => $v) {
            if (!in_array($k, ['updated_at']) && isset($payment[$k]) && $payment[$k] != $v) {
                $isSame = false;
                break;
            }
        }
        if ($isSame) {
            g_log_info('paymentUpdate.log', '数据一致，无变化', [$update, $payment, $params]);
            //这个提示语不能修改，前面有判断
            return $this->returnSuccess($payment, '数据一致，无变化');
        }

        //有关联单号的，支付金额为0，或负数时，变更成支付成功 -- 修改20240717，支付金额为<=0还是需要手动点一下付款走完剩余的流程
        //if (!empty($payment['related_payment_no']) && $update['pay_amount'] <= 0) {
        //    $update['status']  = PaymentRepository::STATUS_PAID;
        //    $update['is_paid'] = 1;
        //    $update['pay_at']  = time();
        //}

        $where = ['payment_no' => $params['payment_no'], 'business_no' => $params['business_no']];

        $result = PaymentRepository::instance()->update($update, $where);
        g_log_info('paymentUpdate.log', '结果', [$result, $params]);
        PaymentLogRepository::instance()->logInsert(
            0,
            $params['payment_no'],
            '', 777323, [],
            ['更新支付单', $result, $where, $update],
            ['type' => 1, 'id' => 0, 'name' => '更新支付单']
        );
        if (!empty($result)) {
            $payment = PaymentRepository::instance()->getByPaymentId($payment['payment_id']);
            return $this->returnSuccess($payment, 'success');
        } else {
            return $this->returnError(800102, 'Failed to update payment slip');
        }
    }

    /**
     * 支付单关闭
     *
     * @param $paymentNo
     *
     * @return array|int
     * @author 白杨
     * @Date   2021/2/1 下午5:40
     */
    public function paymentClosed($paymentNo, $remark = "", $delFlag = 0, $operator = [])
    {
        //校验参数
        if (empty($paymentNo)) {
            return $this->returnError(800000, 'Parameter error');
        }
        $payment = PaymentRepository::instance()->getByPaymentNo($paymentNo);

        $update = [
            'status'        => PaymentRepository::STATUS_CLOSED,
            'closed_at'     => time(),
            'closed_reason' => $remark,
            'del_flag'      => $delFlag,
            'updated_at'    => time(),
        ];
        $where  = ['payment_no' => $paymentNo];
        $result = PaymentRepository::instance()->update($update, $where);
        PaymentLogRepository::instance()->logInsert(
            1,
            $payment['payment_no'],
            '',
            PaymentLogRepository::CODE_CLOSE,
            '',
            $remark,
            $operator,
            $params['prove_url_list'] ?? ''
        );

        return $result;
    }

    /**
     * 支付成功发送邮件
     *
     * @param $params
     *
     * @return array
     * @author zhengcongfeng by 2021-03-26
     *
     */
    public function paymentSendEmail($params)
    {
        //校验参数
        if (empty($params['payment_no'])) {
            return $this->returnError(800000, 'Parameter error');
        }

        $payment = PaymentRepository::instance()->getByPaymentNo($params['payment_no']);
        if (empty($payment)) {
            return $this->returnError(700002, 'Missing payment slip information');
        }
        if ($payment['status'] == PaymentRepository::STATUS_CLOSED || $payment['business_type'] != PaymentRepository::BUSINESS_TYPE_ORDER) {
            return $this->returnError(700013, 'Payment order status error');
        }

        $order_no = $payment['business_no'];
        $order    = OrderRepository::instance()->getOrderByOrderNoAndUserId($order_no, $params['user_id']);
        if (empty($order)) {
            return $this->returnError(600002, 'Order data query failed');
        }
        return OrderService::instance()->orderEmailNotify($order_no, Email::BUSINESS_CODE_ORDER_PAYMENT);
    }

    /**
     * 获取分期付款状态
     *
     * @param $site         string 站点
     * @param $usdPayAmount float 美元支付金额
     *
     * @return int -1-不显示 0-失效，1-正常
     * @author 白杨
     * @Date   2021/3/23 下午3:17
     */
    public function getAffirmPayStatus($site, $currency, $usdPayAmount)
    {
        if ('us' !== strtolower($site)) {
            return -1;
        }
        if ('usd' !== strtolower($currency)) {
            return -1;
        }

        if ($usdPayAmount < 50 || $usdPayAmount > 17000) {
            return 0;
        }
        return 1;
    }

    /**
     * 获取klarna分期付款状态
     *
     * @param $site         string 站点
     * @param $payAmount    float 当前站点支付金额
     *
     * @return int -1-不显示 0-失效，1-正常
     * @throws \Exception
     */
    public function getKlarnaPayStatus($site, $currency, $payAmount, $orderLines, $klarnaCountryCode)
    {
        $currency = strtoupper($currency);
        if (empty($orderLines)) {
            return -1;
        }
        if (!in_array($klarnaCountryCode, ['US', 'GB', 'ES', 'DE', 'FR', 'BE', 'AT', 'NL', 'IE']) && $klarnaCountryCode != '') {
            return -1;
        }
        // 美国站只支持美元
        if (in_array($klarnaCountryCode, ['US']) && $currency != 'USD') {
            return 0;
        }
        // 欧洲只支持欧元
        if (in_array($klarnaCountryCode, ['ES', 'DE', 'FR', 'BE', 'AT', 'NL', 'IE']) && $currency != 'EUR') {
            return 0;
        }
        // 英国只支持英镑
        if (in_array($klarnaCountryCode, ['GB']) && $currency != 'GBP') {
            return 0;
        }
        //分期付款金额判断，支付金额限制 不满足条件塞到最后一个上
        switch ($site) {
            case 'us':
                $toRate      = ConfigService::instance()->getExchangeRate($currency, 'usd');
                $usPayAmount = bcmul($payAmount, $toRate, 2);
                $amount      = $this->getKlarnaAmount($site);
                if ($usPayAmount < $amount['min_amount'] || $usPayAmount > $amount['max_amount']) {
                    return 0;
                }
                break;
            case 'uk':
                $toRate      = ConfigService::instance()->getExchangeRate($currency, 'gbp');
                $ukPayAmount = bcmul($payAmount, $toRate, 2);
                $amount      = $this->getKlarnaAmount($site);
                if ($ukPayAmount < $amount['min_amount'] || $ukPayAmount > $amount['max_amount']) {
                    return 0;
                }
                break;
            case 'de':
                $toRate      = ConfigService::instance()->getExchangeRate($currency, 'eur');
                $dePayAmount = bcmul($payAmount, $toRate, 2);
                $amount      = $this->getKlarnaAmount($site);
                if ($dePayAmount < $amount['min_amount'] || $dePayAmount > $amount['max_amount']) {
                    return 0;
                }
                break;
            case 'fr':
                $toRate      = ConfigService::instance()->getExchangeRate($currency, 'eur');
                $frPayAmount = bcmul($payAmount, $toRate, 2);
                $amount      = $this->getKlarnaAmount($site);
                if ($frPayAmount < $amount['min_amount'] || $frPayAmount > $amount['max_amount']) {
                    return 0;
                }
                break;
            case 'es':
                $toRate      = ConfigService::instance()->getExchangeRate($currency, 'eur');
                $esPayAmount = bcmul($payAmount, $toRate, 2);
                $amount      = $this->getKlarnaAmount($site);
                if ($esPayAmount < $amount['min_amount'] || $esPayAmount > $amount['max_amount']) {
                    return 0;
                }
                break;
        }

        return 1;
    }

    public function getKlarnaAmount($site)
    {
        switch ($site) {
            case 'us':
                return [
                    'min_amount' => 10,
                    'max_amount' => 4000,
                    'currency'   => 'usd',
                ];
            case 'uk':
                return [
                    'min_amount' => 35,
                    'max_amount' => 2500,
                    'currency'   => 'gbp',
                ];
            case 'de':
                return [
                    'min_amount' => 0.1,
                    'max_amount' => 5000,
                    'currency'   => 'eur',
                ];
            case 'fr':
                return [
                    'min_amount' => 35,
                    'max_amount' => 1500,
                    'currency'   => 'eur',
                ];
            case 'es':
                return [
                    'min_amount' => 35,
                    'max_amount' => 1000,
                    'currency'   => 'eur',
                ];
        }
    }

    /**
     * 3，4期分期文案
     *
     * @param $site
     * @param $pf
     * @param $countryCode
     * @param $language
     * @param $currency
     * @param $payAmount
     *
     * @return string
     * @throws \Exception
     */
    public function getKlarnaAmountText($site, $pf, $countryCode, $language, $currency, $payAmount, $klarnaStrType = 0)
    {
        if (empty($language)) {
            $language = 'en';
        }
        $klarnaPayStatus = $this->getKlarnaPayStatus($site, $currency, $payAmount, [1], $countryCode);
        if ($klarnaPayStatus != 1) {
            return '';
        }

        $paymentMethod = PaymentMethodSettingRepository::instance(true)->getKlarnaPaymentMethod($site, $pf);
        if (empty($paymentMethod) || empty($paymentMethod['method_code'])) {
            return '';
        }
        if ($paymentMethod['method_code'] == PaymentMethodRepository::KLARNA_PAY_INSTALLMENT) {
            return '';
        }

        $methodMapping = [
            PaymentMethodRepository::KLARNA_TREE_INSTALLMENT => 3,
            PaymentMethodRepository::KLARNA_FOUR_INSTALLMENT => 4,
        ];
        $num           = $methodMapping[$paymentMethod['method_code']] ?? 0;
        if (empty($num)) {
            return '';
        }
        $amountFormat = ConfigService::instance()->amountFormat(bcdiv($payAmount, $num, 2), $currency, $site);
        $amountSymbol = $amountFormat['amount_symbol'];

        if ($klarnaStrType == 1) {
            $translateMapping = [
                'en' => 'Pay %s interest-free payments of %s with',
                'de' => 'Zahlen Sie %s zinsfreie Zahlungen von %s mit',
                'fr' => 'Payez %s paiements sans intérêt de %s avec',
                'es' => 'Pagar %s cuotas de %s sin intereses con',
                'nl' => 'Betaal %s renteloze betalingen van %s met',
            ];
        } else {
            $translateMapping = [
                'en' => 'Pay %s interest-free payments of %s with Klarna',
                'de' => 'Zahlen Sie %s zinsfreie Zahlungen von %s mit Klarna',
                'fr' => 'Payez %s paiements sans intérêt de %s avec Klarna',
                'es' => 'Pagar %s cuotas de %s sin intereses con Klarna',
                'nl' => 'Betaal %s renteloze betalingen van %s met Klarna',
            ];
        }


        if (empty($translateMapping[$language])) {
            return '';
        }
        return sprintf($translateMapping[$language], $num, $amountSymbol);
    }

    /**
     * 模拟支付
     *
     * @param $params
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     * @throws \yii\di\NotInstantiableException
     * @author 白杨
     * @Date   19/8/21 下午4:37
     */
    public function simulatePay($params)
    {
        $payment = PaymentRepository::instance()->getOneByOrdertNo($params['order_no']);
        if (empty($payment)) {
            return $this->returnError(700002, 'Missing payment slip information');
        }
        if ($payment['status'] != PaymentRepository::STATUS_UNPAID || $payment['business_type'] != PaymentRepository::BUSINESS_TYPE_ORDER) {
            return $this->returnError(700013, 'Payment order status error');
        }

        $order = OrderRepository::instance()->getOrderByOrderNo($params['order_no']);
        if (empty($order)) {
            return $this->returnError(600002, 'Order data query failed');
        }

        if (YII_DEBUG) { //测试环境
            $userIdWhiteList = [148, 31295, 322224];
        } else {
            $userIdWhiteList = [237453, 137337, 287441, 287440, 113157, 145739, 107941, 101938, 787875, 502760];
        }

        if (!in_array($order['user_id'], $userIdWhiteList)) {
            return $this->returnError(6000012, 'User is not in the whitelist');
        }

        $result = PayConfirmService::instance()->paidHandle($payment, $payment['payment_method'], '', '', $params['prove_url'] ?? '', time(), false, 1);

        return $result;
    }

    /**
     * 关闭测试单
     *
     * @param $params
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     * @author 白杨
     * @Date   19/8/21 下午7:35
     */
    public function closeTest($params)
    {
        if (empty($params['order_no']) || empty($params['payment_no'])) {
            return $this->returnError(7047801, '参数缺失，请重试');
        }
        $orderResult = OrderRepository::instance()->closeTestOrder($params['order_no']);
        if (empty($orderResult)) {
            return $this->returnError(7047802, '关闭订单失败，请重试');
        }
        $paymentResult = PaymentRepository::instance()->closeTestPayment($params['payment_no']);

        PaymentLogRepository::instance()->logInsert(
            PaymentLogRepository::STATUS_FAIL,
            $params['payment_no'],
            '',
            PaymentLogRepository::CODE_CLOSE_TEST,
            [],
            ['content' => '测试单，关闭测试支付单'],
            $params['operator'] ?? ''
        );
        if ($paymentResult && $orderResult) {
            return $this->returnSuccess([]);
        } else {
            return $this->returnError(7047803, '关闭失败，请重试');
        }
    }

    /**
     * 获取订单的地址，优先级：物流地址，账单地址，自提地址，发付请填写的地址
     *
     * @param $context
     *
     * @return array
     * @author 白杨
     * @Date   16/12/21 下午5:02
     */
    public function getOrderAddress($context)
    {
        $address = $context->orderAddressData ?? [];
        if (empty($address)) {
            $address = $context->billAddressData ?? [];
            if (empty($address)) {
                $address = $context->pickUpAddressData ?? [];
            }
        }
        //再兼容发付请一波
        if (empty($address) && isset($context->paymentRequestData) && !empty($context->paymentRequestData)) {
            $address = [
                'first_name'   => $context->paymentRequestData['first_name'],
                'last_name'    => $context->paymentRequestData['last_name'],
                'street1'      => 'unknown',
                'street2'      => 'unknown',
                'country_code' => !empty($context->paymentRequestData['country_code']) ? $context->paymentRequestData['country_code'] : ($context->params['country_code'] ?? ''),
                'state_code'   => !empty($context->paymentRequestData['state_code']) ? $context->paymentRequestData['state_code'] : ($context->params['state_code'] ?? ''),
                'city'         => !empty($context->paymentRequestData['city']) ? $context->paymentRequestData['city'] : ($context->params['city'] ?? ''),
                'zip'          => !empty($context->paymentRequestData['zip']) ? $context->paymentRequestData['zip'] : ($context->params['zip'] ?? ''),
                'phone'        => $context->paymentRequestData['phone'] ?? '',
            ];
        }
        return $address;
    }

    /**
     * 获取支付单的支付状态
     *
     * @param string $paymentNo 支付单号
     *
     * @return bool
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    public function getPayStatus($paymentNo)
    {
        $payment = PaymentRepository::instance()->getByPaymentNo($paymentNo);
        if (empty($payment)) {
            throw new \Exception('', 700002);
        }

        return (int)$payment['status'] != PaymentRepository::STATUS_UNPAID;
    }

    /**
     * 根据支付方式获取支付渠道公司
     *
     * @param string $paymentMethod 支付方法
     *
     * @return string
     *
     * @author liangchuepng
     *
     * @since  2022.01.25
     */
    public function getPayChannelByMethod($paymentMethod)
    {
        if (empty($paymentMethod)) {
            return '';
        }
        foreach (PaymentRepository::$payChannelMappings as $channel => $mapping) {
            if (in_array($paymentMethod, $mapping)) {
                return $channel;
            }
        }
        return '';
    }

    /**
     * 更新支付单表的支付单号
     *
     * @param int    $paymentId 支付单ID
     * @param string $paymentNo 支付单号
     * @param string $handler   操作人
     *
     * @return array
     */
    public function updatePaymentNo($paymentId, $paymentNo, $handler, $businessType)
    {
        //更新payment表支付单号
        $updatePaymentResult = PayInternal::instance()->updatePaymentNoByPaymentId($paymentId, $paymentNo, $handler);
        g_log_info('updateOrderSuccess.log', '更新payment表支付单号', [$paymentId, $paymentNo, $updatePaymentResult]);
        if (1 != $updatePaymentResult['code']) {
            return $this->returnError($updatePaymentResult['code'], $updatePaymentResult['info']);
        }
        //更新order表支付单号
        if (PaymentRepository::BUSINESS_TYPE_ORDER != $businessType) {
            //常规订单才需要更新支付单号
            return $this->returnSuccess();
        }
        $updateOrderResult = OrderService::instance()->updatePaymentNo($updatePaymentResult['data']['order_no'], $paymentNo);
        g_log_info('updateOrderSuccess.log', '更新order表支付单号', [$updatePaymentResult['data']['order_no'], $paymentNo, $updateOrderResult]);
        if (1 != $updateOrderResult['code']) {
            return $this->returnError($updateOrderResult['code'], $updateOrderResult['info']);
        }
        return $this->returnSuccess();
    }

    /**
     * 更新支付凭证
     *
     * @param int    $paymentId             支付单ID
     * @param string $paymentNo             支付单号
     * @param string $credentialId          支付凭证ID
     * @param string $transactionIdentifier 交易标识
     * @param string $handler               操作人
     *
     * @return array
     */
    public function updateCredential($paymentId, $paymentNo, $credentialId, $transactionIdentifier, $handler)
    {
        //更新支付凭证
        $updateResult = PayInternal::instance()->updateCredentialId($paymentId, $paymentNo, $credentialId, $transactionIdentifier, $handler);
        g_log_error('updateCredential.log', '更新支付凭证', [$paymentId, $paymentNo, $credentialId, $transactionIdentifier, $updateResult]);
        if (1 != $updateResult['code']) {
            return $this->returnError($updateResult['code'], $updateResult['info']);
        }
        return $this->returnSuccess();
    }

    /**
     * 根据支付参数获取支付单信息
     *
     * @param string $paymentNo     支付单号
     * @param string $paymentMethod 支付方式
     *
     * @return array
     */
    public function getPaymentByPaymentParams($paymentNo, $paymentMethod)
    {
        $result = PayInternal::instance()->getPaymentByPaymentParams($paymentNo, $paymentMethod);
        g_log_info('getPaymentByPaymentParams.log', '根据支付参数获取支付单信息', [$paymentNo, $paymentMethod, $result]);
        if (1 != $result['code']) {
            return [];
        }
        return $result['data']['payment'];
    }

    /**
     * 根据支付参数订单号获取支付单单号
     *
     * @param string $orderNo       订单号
     * @param string $paymentMethod 支付方式
     *
     * @return string
     */
    public function getPaymentNoByPaymentParams($orderNo, $paymentMethod)
    {
        $result = PayInternal::instance()->getPaymentNoByPaymentParams($orderNo, $paymentMethod);
        g_log_info('getPaymentNoByPaymentParams.log', '根据支付参数订单号获取支付单信息', [$orderNo, $paymentMethod, $result]);
        if (1 != $result['code']) {
            return '';
        }
        return $result['data']['payment_no'];
    }

    /**
     * 第三方支付成功更新系统的订单信息
     *
     * @param string $paymentNo    系统支付单号
     * @param int    $operatorType 1:用户， 2：系统
     *
     * @return array
     *
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     * @throws \yii\di\NotInstantiableException
     * @author liangchuepng
     *
     * @since  2022.04.21
     */
    public function updateOrderSuccess($paymentNo, $operatorType = 1, $utmParams = [], $requestParams = [], $fromInternal = false)
    {
        $saEvents      = [];
        $paymentMethod = $utmParams['payment_method'] ?? '';
        if (in_array($paymentMethod, [PaymentRepository::PAYMENT_METHOD_PAYPAL_PAYPAL, PaymentRepository::PAYMENT_METHOD_PAYPAL_CREDIT_CARD,
            PaymentRepository::PAYMENT_METHOD_PAYPAL_PAY_LATER
        ])) {
            //根据支付参数获取支付单信息
            $payment               = $this->getPaymentByPaymentParams($paymentNo, $paymentMethod);
            $payment['payment_no'] = $paymentNo;
        } else if (!empty($paymentNo)) {
            $payment = PaymentRepository::instance()->getByPaymentNo($paymentNo);
        } else if (!empty($utmParams['order_no'])) {
            $payment = PaymentRepository::instance()->getOneByOrdertNo($utmParams['order_no']);
        }
        //获取系统支付单的信息
        if (empty($payment)) {
            return $this->returnError(700001, 'The payment not found');
        }

        if (!defined('PAYMENT_PAY_AT') && !empty($payment['pay_at'])) {
            define('PAYMENT_PAY_AT', $payment['pay_at']);
            g_log_info('setPaymentPayAt.log', '支付成功更新系统的订单信息', [PAYMENT_PAY_AT, $payment['pay_at']]);
        }

        if (in_array($payment['status'], [PaymentRepository::STATUS_PAID, PaymentRepository::STATUS_PAID_AUDIT])) {
            return $this->returnSuccess(['success' => true], 'Success paid');
        }
        //如果有账单地址信息，则更新
        if (!empty($utmParams['bill_address'])) {
            $billAddressResult = BillAddressService::instance()->saveBillAddress($payment['business_no'], $payment['payment_no'], $utmParams['user_id'] ?? 0, $utmParams['bill_address']);
            g_log_info('UpdateOrderSuccessBillAddressResult.log', '', [$billAddressResult, $utmParams['bill_address']]);
        }
        if (empty($payment['payment_method']) && !empty($utmParams['payment_method'])) {
            //这里由客户端在支付成功后通知系统支付方式是什么
            $payment['payment_method'] = $utmParams['payment_method'];
        }
        $payment['payment_channel_company'] = PayService::instance()->getPayChannelByMethod($payment['payment_method']);

        // 支付方式改版新增的字段，如果存在这个字段，那么更新支付方式和第三方支付的id到表里
        if (!empty($utmParams['out_transaction_id'])) {
            PaymentRepository::instance()->updateByPaymentId($payment['payment_id'], $utmParams['out_transaction_id'], $utmParams['payment_method'],
                $payment['payment_channel_company']);
            $payment['out_id'] = $utmParams['out_transaction_id'];
        }
        //暂时支持AirWallex支付和普通订单
        if (
            !in_array($payment['payment_channel_company'], [PaymentRepository::PAY_BY_STRIPR, PaymentRepository::PAY_BY_AIRWALLEX, PaymentRepository::PAY_BY_WORLDPAY,
                PaymentRepository::PAY_BY_PAYPAL
            ]) && !in_array($payment['business_type'], [PaymentRepository::BUSINESS_TYPE_ORDER, PaymentRepository::BUSINESS_TYPE_FFQ])
            || $payment['status'] == PaymentRepository::STATUS_CLOSED
        ) {
            g_log_error('update_order_success_not_allowed.log', '不允许更新支付单',
                ['payment' => $payment, 'params' => [$paymentNo, $operatorType, $utmParams, $requestParams, $fromInternal]]);
            return $this->returnError(444444, 'This operation is not allowed');
        }

        //paypal直连先capture，状态变成completed，因为approved无法保证能够扣款成功
        if (in_array($payment['payment_method'], [PaymentRepository::PAYMENT_METHOD_PAYPAL_PAYPAL, PaymentRepository::PAYMENT_METHOD_PAYPAL_PAY_LATER])) {
            $captureResult = PayInternal::instance()->captureByPaymentId($payment['payment_id'], $paymentNo, $payment['pay_amount'], 'updateOrderSuccess');
            g_log_error('update_order_success_capture.log', 'paypal调用接口捕获日志', [$paymentNo, $payment['pay_amount'], $captureResult]);
            if (1 != $captureResult['code']) {
                return $this->returnError($captureResult['code'], $captureResult['info'], $captureResult['data'] ?? []);
            }
            if (!empty($captureResult['data']['__sa'])) {
                $saEvents = array_merge($saEvents, $captureResult['data']['__sa']);
            }
        }
        if (in_array($paymentMethod, [PaymentRepository::PAYMENT_METHOD_PAYPAL_PAYPAL, PaymentRepository::PAYMENT_METHOD_PAYPAL_CREDIT_CARD,
            PaymentRepository::PAYMENT_METHOD_PAYPAL_PAY_LATER
        ])) {
            //更新支付单号,特殊处理paypal公司支付，如果有payment_no传入，则以传入的为准，并更新到支付单表和订单表
            $updateNoResult = $this->updatePaymentNo($payment['payment_id'], $paymentNo, '更新订单成功_' . (int)$fromInternal, $payment['business_type']);
            if (1 != $updateNoResult['code']) {
                return $this->returnError($updateNoResult['code'], $updateNoResult['info']);
            }
        }
        if (PaymentRepository::PAY_BY_PAYPAL == $payment['payment_channel_company']) { //paypal公司支付需要通知对应支付参数记录为成功
            PayInternal::instance()->updatePaymentParameterSuccess($payment['payment_no'], $payment['payment_method'], 'updateOrderSuccess');
        }
        $pf = '';
        if (PaymentRepository::BUSINESS_TYPE_ORDER == $payment['business_type']) {
            //获取订单的用户信息
            $orderInfo = OrderRepository::instance()->getOrderByOrderNo($payment['business_no'], 'platform, user_id');
            $userInfo  = UserRepository::instance()->getUserById($orderInfo['user_id'], 'email');
            $pf        = $orderInfo['platform'] ?? '';
        }
        $operator = ['type' => 3, 'id' => 0, 'name' => '系统updateOrderSuccess操作'];
        if ($operatorType == 1) { //用户在客服端支付后发起的操作
            $operator = ['type' => 1, 'id' => $orderInfo['user_id'] ?? 0, 'name' => $userInfo['email'] ?? ''];
        }

        //获取第三方的支付单信息，worldpay支付查询会有延迟，会查询交易失败，导致流程无法往下走
        if ((float)$payment['pay_amount'] > 0) {
            if ($fromInternal && isset($utmParams['third_payment']) && !empty($utmParams['third_payment'])) { //如果从内网调用，以传过来支付结果为准
                $thirdPayment = $utmParams['third_payment'];
            } else {
                $thirdPayment = $this->getThirdPaymentInfo($payment['site'], $payment['out_id'], $payment['payment_channel_company'],
                    $payment['out_notify_content'], $payment['payment_no'], $payment['payment_id'], $payment['pay_at']);
                if ($thirdPayment['code'] != 1) {
                    return $thirdPayment;
                }
            }
        } else {//不用在线向第三方支付
            $thirdPayment = [];
        }

        //兼容之前的代码
        $thirdPayment['status'] = $thirdPayment['data']['status'] ?? '';
        //写入支付成功日志
        PaymentService::instance()->addPaymentLog(5, [
            'payment_no'    => $payment['payment_no'],
            'out_id'        => $payment['out_id'] ?? '',
            'operator_type' => $operator['type'],
            'operator_id'   => $operator['id'],
            'operator_name' => $operator['name'],
            'params'        => ['out_id' => $payment['out_id'], 'payment_channel_company' => $payment['payment_channel_company'], 'payment_no' => $payment['payment_no']],
            'status'        => 1,
        ], $thirdPayment['data'] ?? []);

        //更新交易单，通知内部系统
        $params['pf']                  = $pf;
        $params['site']                = $payment['site'];
        $params['token']               = '';
        $params['three_d_secure_info'] = '';
        $params['operator']            = $operator;
        $params['bill_address']        = $utmParams['bill_address'] ?? [];

        // 如果这个utm参数不为空的话，那么拼接utm进去
        if (!empty($utmParams)) {
            $params['utm_params'] = json_encode([
                'latest_utm_source'  => $utmParams['latest_utm_source'] ?? '',
                'latest_utm_medium'  => $utmParams['latest_utm_medium'] ?? '',
                'latest_utm_term'    => $utmParams['latest_utm_term'] ?? '',
                'latestutm_content'  => $utmParams['latestutm_content'] ?? '',
                'latestutm_campaign' => $utmParams['latestutm_campaign'] ?? '',
            ]);
        }

        $updateResult = PayConfirmService::instance()->paidHandle(array_merge($payment, $params), $payment['payment_method'], $payment['out_id'], $thirdPayment, [], time(), false);
        //失败发送
        if (1 != $updateResult['code']) {
            $wechatMsg = [
                '#### 支付成功同步更新订单信息失败：',
                '支付单号：' . $paymentNo,
                '回调返回：' . json_encode($updateResult, 320),
                '入参：' . json_encode(array_merge($payment, $params), 320),
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($wechatMsg, 'trade_core');
            g_log_error('sync_order_result.log', '更新订单失败', [$paymentNo, $updateResult]);

            return $this->returnError(700102, $updateResult['info']);
        }

        return $this->returnSuccess(['success' => true, '__sa' => $saEvents], 'success');
    }

    /**
     * 获取第三方的支付单信息
     *
     * @param string $site              站点
     * @param string $thirdPaymentId    第三方交易ID
     *
     * @param string $payChannelCompany 支付渠道公司
     * @param string $outNotifyContent  第三方支付信息
     * @param string $paymentNo         支付单号
     * @param int    $paymentId         支付单id
     *
     * @return array
     */
    public function getThirdPaymentInfo($site, $thirdPaymentId, $payChannelCompany, $outNotifyContent, $paymentNo, $paymentId, $payAt)
    {
        if ($payChannelCompany == PaymentRepository::PAY_BY_AIRWALLEX) {
            if (!defined('PAYMENT_PAY_AT') && !empty($payAt)) {
                define('PAYMENT_PAY_AT', $payAt);
                g_log_info('setPaymentPayAt.log', '支付成功更新系统的订单信息,getThirdPaymentInfo', [PAYMENT_PAY_AT, $payAt]);
            }
            $cardBrand = PaymentRepository::instance()->getAirWallexPaymentCardBrand($payChannelCompany, $outNotifyContent, $paymentNo);
            //TODO 切换到newpay
            $result = AirWallexService::getSiteInstance($site, $cardBrand)->getPayment($thirdPaymentId);
            if ($result['code'] == 1) {
                if (in_array($result['data']['status'], [AirWallexService::REQUIRES_CAPTURE, AirWallexService::SUCCEEDED])) {
                    return $this->returnSuccess($result['data']);
                } else {
                    return $this->returnError(4444493, 'The payment status is ' . $result['data']['status']);
                }
            } else {
                return $result;
            }
        }
        //迁移到下面的统一接口处理
        //if ($payChannelCompany == PaymentRepository::PAY_BY_STRIPR) {
        //    return $this->getStripePaymentIntent($thirdPaymentId, $site);
        //}

        if ($payChannelCompany == PaymentRepository::PAY_BY_WORLDPAY) {

            //TODO 切换到newpay
            for ($i = 1; $i <= 5; $i++) {
                $result = WorldpayService::instance()->searchPayment($paymentNo);
                if (1 == $result['code']) {
                    break;
                }
                sleep(3);
            }
            if ($result['code'] != 1) {
                return $this->returnError($result['code'], 'worldpay:' . $result['info']);
            }
            return $this->returnSuccess(['data' => $result['reply']['orderStatus']['payment'], 'status' => 'succeeded']);
        }

        if (in_array($payChannelCompany, [PaymentRepository::PAY_BY_PAYPAL, PaymentRepository::PAY_BY_AFTERPAY, PaymentRepository::PAY_BY_STRIPR])) {
            $result = PayInternal::instance()->getThirdPaymentDetail($paymentId, $paymentNo, $thirdPaymentId);
            if ($result['code'] != 1) {
                return $this->returnError($result['code'], $payChannelCompany . ':' . $result['info']);
            }
            if (1 != $result['data']['third_payment_is_paid']) {
                return $this->returnError($result['code'], 'The payment is not paid');
            }
            return $this->returnSuccess($result['data']['third_payment_detail']);
        }

        return $this->returnError(4444495, 'The payment channel company is error ' . $payChannelCompany);
    }

    public function getStripePaymentIntent($thirdPaymentId, $site)
    {
        //重试次数,
        $requestNum = 1;

        CodeHandle:
        $result = StripeService::getSiteInstance($site)->retrievePaymentIntent($thirdPaymentId);
        if ($result['code'] != 1 && empty($result['data'])) {
            return $result;
        }
        $paymentIntent = $result['data'];
        if (in_array(strtolower($paymentIntent['status']), ['processing', 'succeeded', 'requires_capture'])) {//支付成功
            return $this->returnSuccess($paymentIntent);
        } else if (strtolower($paymentIntent['status']) == 'canceled') {//已取消直接返回
            return $this->returnError(4444493, 'The payment intent status is ' . $paymentIntent['status']);
        } else {//其他状态，重复请求
            if ($requestNum <= 10) {
                sleep(1);
                $requestNum += 1;
                goto CodeHandle;
            }
            //报警
            $msg = [
                "#### stripe的PaymentIntent的状态有误，请排查",
                'stirpe:' . json_encode($paymentIntent, 320),
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'stripe');

            //todo 给个特殊的提示，给用户感知，后续有可能更新成已支付。
            return $this->returnError(4444497, 'The payment intent status is ' . $paymentIntent['status']);
        }
    }

    /**
     * 检查第三方支付的订单是否为已支付状态
     *
     * @param string $orderNo 订单号
     *
     * @return array
     *
     */
    public function checkThirdOrderIsPaid($orderNo)
    {

        $payment = PaymentRepository::instance()->getOneByOrdertNo($orderNo);

        try {
            if (empty($payment) || empty($payment['out_id'])) {
                throw new \Exception('系统第三方交易ID为空', 308001);
            }

            if ($payment['payment_channel_company'] != PaymentRepository::PAY_BY_BRAINTREE) {
                return $this->returnSuccess(null, 'success');
            }

            //暂时只处理bt交易的
            $result = BraintreeService::instance()->find($payment['payment_no'], $payment['out_id']);
            if ($result['code'] == 1) {
                if (!in_array(strtolower($result['data']['status']), ['settled', 'settling', 'submitted_for_settlement'])) {
                    throw new \Exception(sprintf('第三方订单处于未支付交易状态: %s', $result['data']['status']), 308002);
                }
            } else {
                throw new \Exception('获取不到braintree的交易数据', 308003);
            }

            return $this->returnSuccess(null, 'success');

        } catch (\Throwable $e) {

            g_log_error('checkThirdOrderIsPaid.log', $e->getMessage(), [$orderNo, $payment]);

            return $this->returnError($e->getCode(), $e->getMessage());
        }
    }

    /**
     * @param $site
     * @param $cardNumber
     *
     * @return array
     */
    public function airwallexLookUp($site, $cardNumber)
    {
        //look up 无需卡类型
        $airWallexServiceObj = AirWallexService::getSiteInstance($site);
        $result              = $airWallexServiceObj->lookUp($cardNumber);
        if ($result['code'] != $this->success_code) {
            return $this->returnError($result['code'], $result['info']);
        }
        return $this->returnSuccess(!empty($result['data']) ? $result['data'] : null);
    }

    /**
     * @param $params
     *
     * @return array
     */
    public function callBackAirwallexReturnUrl($params)
    {
        //从外部传递

        $paymentNo = $params['payment_no'] ?? '';
        if (!empty($paymentNo)) {
            $payment = PaymentRepository::instance()->getOneByPaymentNo($paymentNo);
            if (!defined('PAYMENT_PAY_AT') && !empty($payment['pay_at'])) {
                define('PAYMENT_PAY_AT', $payment['pay_at']);
                g_log_info('setPaymentPayAt.log', 'callBackAirwallexReturnUrl', [PAYMENT_PAY_AT, $payment['pay_at']]);
            }
        }

        $airWallexServiceObj = AirWallexService::getSiteInstance($params['site'], $params['card_brand'] ?? '');
        $result              = $airWallexServiceObj->confirmContinue($params);
        if ($result['code'] != $this->success_code) {
            if (!empty($result['data']['error_detail'])) {
                MqService::instance()->paymentFailNotify($paymentNo, $payment['payment_id'] ?? 0, $result['data']['error_detail'], 'confirm_continue', PaymentRepository::PAYMENT_METHOD_AIRWALLEX_CREDITCARD);
            }
            return $this->returnError($result['code'], $result['info']);
        }
        return $this->returnSuccess();
    }

    /**
     * @param $paymentNo
     *
     * @return array
     * pay_status = 1,成功，结束轮询，修改订单
     * 有error_message，直接弹出来，中断轮询
     * 无error_message，next_action = null,继续轮询
     * 无error_message，next_action !=null,组装form表单到下一步
     */
    public function airwallexQuery($paymentNo)
    {
        if (empty($paymentNo)) {
            return $this->returnSuccess([
                'next_action'   => null,
                'error_message' => \Yii::t('error', '800000'),
                'pay_status'    => 0,
            ]);
        }

        //中断时间
        $overTime = 900;
        if (YII_DEBUG) {
            $overTime = 60;
        }

        $paymentRedisObj     = PaymentRedis::instance();
        $startTimeRedisValue = $paymentRedisObj->getPayAirwallexQueryStartTime($paymentNo);
        if (!$startTimeRedisValue) {
            $paymentRedisObj->setPayAirwallexQueryStartTime($paymentNo, $overTime);
        }
        $queryAllTime = time() - $startTimeRedisValue;
        //每秒3次，轮询1个小时无结果，则中断轮询
        if ($startTimeRedisValue > 0 && $queryAllTime >= $overTime) {
            $paymentRedisObj->delPayAirwallexQueryStartTime($paymentNo);
            $errorMsg   = \Yii::t('error', '999008');
            $tips       = PayService::instance()->getPaymentErrorTips(PaymentRepository::PAY_BY_AIRWALLEX, '', true);
            $returnData = [
                'next_action'   => null,
                'error_message' => $errorMsg,
                'error_detail'  => PayService::instance()->formatPaymentErrorResponse($tips['error'], $tips['error_type'], PaymentRepository::PAYMENT_METHOD_AIRWALLEX_CREDITCARD),
                'pay_status'    => 0,
            ];
            return $this->returnSuccess($returnData);
        }

        $confirmDataList = PaymentAirwallexConfirmRepository::instance()
            ->getList([['=', 'payment_no', $paymentNo]],
                ['id', 'confirm_status', 'confirm_data', 'use_status', 'created_at', 'error_code', 'card_brand', 'provider_original_response_code']
            );
        //需要等待
        if (empty($confirmDataList)) {
            return $this->returnSuccess([
                'next_action'   => null,
                'error_message' => '',
                'pay_status'    => 0,
            ]);
        }

        //success与capture都为正常
        $statusSuccess      = 'SUCCEEDED';
        $statusCapture      = 'REQUIRES_CAPTURE';
        $confirmDataList    = MyFunction::instance()->arraySort($confirmDataList, 'id', 'SORT_DESC');
        $confirmDataListArr = array_column($confirmDataList, null, 'confirm_status');

        $successData = $confirmDataListArr[$statusSuccess] ?? [];
        $captureData = $confirmDataListArr[$statusCapture] ?? [];

        //如果能获取到成功的记录，则从成功记录拿到数据，否则从最新记录拿数据
        if (!empty($successData) || !empty($captureData)) {
            $confirmData = !empty($successData) ? $successData : $captureData;
        } else {
            //过滤使用的数据
            $filterConfirmDataList = [];
            foreach ($confirmDataList as $value) {
                if ($value['use_status'] != 1) {
                    $filterConfirmDataList[] = $value;
                }
            }
            $confirmData = current($filterConfirmDataList);
        }

        //无数据，等待
        if (empty($confirmData)) {
            return $this->returnSuccess([
                'next_action'   => null,
                'error_message' => '',
                'pay_status'    => 0,
            ]);
        }

        $id               = $confirmData['id'] ?? 0;
        $confirmStatus    = $confirmData['confirm_status'] ?? '';
        $errorCode        = $confirmData['error_code'] ?? '';
        $origResponseCode = $confirmData['provider_original_response_code'] ?? '';
        $cardBrand        = $confirmData['card_brand'] ?? '';
        $errorMessage     = $confirmData['message'] ?? '';

        //成功，表示完成confirm
        if (in_array(strtolower($confirmStatus), [strtolower($statusSuccess), strtolower($statusCapture)])) {
            $returnData = [
                'next_action'   => null,
                'error_message' => '',
                'pay_status'    => 1,
            ];
            //上报绑卡成功事件
            $getResult = PayInternal::instance()->getSaveCardInfoEventParams($paymentNo);
            if (1 == $getResult['code'] && !empty($getResult['data'])) {
                $returnData['__sa'][] = $getResult['data'];
            }
            return $this->returnSuccess($returnData);
        }
        $confirmDataArr = json_decode($confirmData['confirm_data'] ?? '', true);
        $nextAction     = !empty($confirmDataArr['next_action']) ? $confirmDataArr['next_action'] : null;

        //将指定数据修改为已使用
        if ($id > 0) {
            PaymentAirwallexConfirmRepository::instance()->updateAll(['use_status' => 1, 'use_time' => time()], ['id' => $id]);
        }
        $airwallexErrorMsg = $this->getAirWallexErrorInfo($cardBrand, $origResponseCode, $errorCode, $errorMessage);
        //未成功，则需要验证数据是否存在异常
        if (!empty($nextAction) && !is_array($nextAction)) {
            return $this->returnSuccess([
                'next_action'   => null,
                'error_message' => $airwallexErrorMsg,
                'pay_status'    => 0,
                'id'            => $id
            ]);
        }
        if (!empty($errorCode) || $errorCode > 0) {
            $tips        = PayService::instance()->getPaymentErrorTips(PaymentRepository::PAY_BY_AIRWALLEX, $errorCode, true);
            $returnData  = [
                'next_action'   => null,
                'error_message' => $airwallexErrorMsg,
                'error_detail'  => PayService::instance()->formatPaymentErrorResponse($tips['error'], $tips['error_type'], PaymentRepository::PAYMENT_METHOD_AIRWALLEX_CREDITCARD),
                'pay_status'    => 0,
                'id'            => $id
            ];
            $eventResult = PayInternal::instance()->getPaymentFailEventParams($paymentNo, PaymentRepository::PAYMENT_METHOD_AIRWALLEX_CREDITCARD, $airwallexErrorMsg);
            if (1 == $eventResult['code'] && !empty($eventResult['data']['__sa'])) {
                $returnData['__sa'] = $eventResult['data']['__sa'];
            }
            return $this->returnSuccess($returnData);
        }

        //需要执行下一步
        return $this->returnSuccess([
            'next_action'   => $nextAction,
            'error_message' => '',
            'id'            => $id,
            'pay_status'    => 0,
        ]);
    }

    /**
     * 空中云汇异常信息处理
     *
     * @param $cardBrand
     * @param $originResponseCode
     * @param $errorCode
     * @param $errorMessage
     *
     * @return mixed|string
     */
    public function getAirWallexErrorInfo($cardBrand, $originResponseCode, $errorCode, $errorMessage = '')
    {
        $errorInfo = (array)\Yii::t('common/app', 'airwallex_error');
        $cardBrand = in_array($cardBrand, ['mastercard', 'master']) ? 'mastercard' : $cardBrand;
        //读取原始响应码数据
        if (!empty($errorInfo[$cardBrand][$originResponseCode])) {
            return $errorInfo[$cardBrand][$originResponseCode];
        }
        //从错误码中查找映射数据
        if (!empty($errorInfo['common_error'][$errorCode])) {
            return $errorInfo['common_error'][$errorCode];
        }

        //从文案查找映射数据
        $errorMessage = strtolower($errorMessage);
        if (!empty($errorInfo['message_error'][$errorMessage])) {
            return $errorInfo['message_error'][$errorMessage];
        }

        return $errorInfo['common_error']['common_error'] ?? '';
    }

    /**
     * @param $paymentNo
     *
     * @return array
     * pay_status = 1,成功，结束轮询，修改订单
     * 有error_message，直接弹出来，中断轮询
     * 无error_message，next_action = null,继续轮询
     */
    public function worldpayQuery($paymentNo, $orderNo = '')
    {
        if (empty($paymentNo)) {
            return $this->returnSuccess([
                'next_action'   => null,
                'error_message' => \Yii::t('error', '800000'),
                'pay_status'    => 0,
            ]);
        }

        //中断时间
        $overTime = 900;
        if (YII_DEBUG) {
            $overTime = 60;
        }
        if (!empty($orderNo)) {
            //根据支付参数获取支付单，兼容新版
            $paymentNo = $this->getPaymentNoByPaymentParams($orderNo, PaymentRepository::PAYMENT_METHOD_WORLDPAY_CREDITCARD);
            $payment   = PaymentRepository::instance()->getByBusinessNo($orderNo, 'status');
        } else {
            $payment = PaymentRepository::instance()->getByPaymentNo($paymentNo, ['status']);
        }
        $paymentRedisObj     = PaymentRedis::instance();
        $startTimeRedisValue = $paymentRedisObj->getPayAirwallexQueryStartTime($paymentNo);
        if (!$startTimeRedisValue) {
            $paymentRedisObj->setPayAirwallexQueryStartTime($paymentNo, $overTime);
        }
        $queryAllTime = time() - $startTimeRedisValue;
        $tips         = PayService::instance()->getPaymentErrorTips(PaymentRepository::PAY_BY_WORLDPAY, '', true);
        //每秒3次，轮询1个小时无结果，则中断轮询
        $errorMsg = \Yii::t('error', '999008');
        if ($startTimeRedisValue > 0 && $queryAllTime >= $overTime) {
            $paymentRedisObj->delPayAirwallexQueryStartTime($paymentNo);
            $returnData = [
                'next_action'   => null,
                'error_message' => $errorMsg,
                'error_detail'  => PayService::instance()->formatPaymentErrorResponse($tips['error'], $tips['error_type'], PaymentRepository::PAYMENT_METHOD_WORLDPAY_CREDITCARD),
                'pay_status'    => 0,
            ];
            return $this->returnSuccess($returnData);
        }

        // 从redis取出失败
        $payStatusFromRedis = PaymentRedis::instance()->getWorldpayPayStatus($paymentNo);
        if (!empty($payStatusFromRedis) && $payStatusFromRedis == 'fail') {
            $returnData  = [
                'next_action'   => null,
                'error_message' => $errorMsg,
                'error_detail'  => PayService::instance()->formatPaymentErrorResponse($tips['error'], $tips['error_type'], PaymentRepository::PAYMENT_METHOD_WORLDPAY_CREDITCARD),
                'pay_status'    => 0,
            ];
            $eventResult = PayInternal::instance()->getPaymentFailEventParams($paymentNo, PaymentRepository::PAYMENT_METHOD_WORLDPAY_CREDITCARD, $errorMsg);
            if (!empty($eventResult['code']) && 1 == $eventResult['code'] && !empty($eventResult['data']['__sa'])) {
                $returnData['__sa'] = $eventResult['data']['__sa'];
            }
            return $this->returnSuccess($returnData);
        }

        if (in_array($payment['status'], [PaymentRepository::STATUS_PAID, PaymentRepository::STATUS_PAID_AUDIT])) {
            $returnData = [
                'next_action'   => null,
                'error_message' => '',
                'pay_status'    => 1,
            ];
            //上报绑卡成功事件
            $getResult = PayInternal::instance()->getSaveCardInfoEventParams($paymentNo);
            if (1 == $getResult['code'] && !empty($getResult['data'])) {
                $returnData['__sa'][] = $getResult['data'];
            }
            return $this->returnSuccess($returnData);
        } else {
            return $this->returnSuccess([
                'next_action'   => null,
                'error_message' => '',
                'pay_status'    => 0,
            ]);
        }
    }

    /**
     * 获取3ds结果
     *
     * @param string $orderNo   订单号
     * @param string $paymentNo 支付单号
     *
     * @return array
     */
    public function get3DSResult($orderNo, $paymentNo)
    {
        $result = PayInternal::instance()->get3DSResult($orderNo, $paymentNo);
        if (1 != $result['code']) {
            return $this->returnError($result['code'], $result['info']);
        }
        return $this->returnSuccess($result['data']);
    }

    /**
     * 获取第三方支付确认结果
     *
     * @param string $paymentNo     支付单号
     * @param string $paymentMethod 支付方式
     *
     * @return array
     */
    public function getThirdPaymentConfirm($paymentNo, $paymentMethod)
    {
        $result = PayInternal::instance()->getThirdPaymentConfirm($paymentNo, $paymentMethod);
        if (1 != $result['code']) {
            return $this->returnError($result['code'], $result['info']);
        }
        return $this->returnSuccess($result['data']);
    }

    /**
     * 格式化支付错误信息
     *
     * @param string $requestType  请求类型
     * @param string $responseCode 响应码
     * @param string $responseType 响应类型
     * @param string $responseDesc 响应类型
     * @param string $nextstep     下一步指引
     *
     * @return array
     */
    public function formatPaymentError($requestType, $responseCode, $responseType = '', $responseDesc = '', $nextstep = '')
    {
        return [
            'request_type'  => $requestType,
            'response_code' => $responseCode,
            'response_type' => $responseType,
            'response_desc' => $responseDesc,
            'nextstep'      => $nextstep,
        ];
    }

    /**
     * 获取支付失败的文案提示
     *
     * @param string $paymentCompany 支付公司
     * @param string $errorCode      错误码
     * @param bool   $isCreditCard   是否为信用卡支付
     *
     * @return array
     */
    public function getPaymentErrorTips($paymentCompany, $errorCode = '', $isCreditCard = false)
    {
        $errorMapping = PaymentErrorCodeMapping::$errorMapping[$paymentCompany] ?? [];
        $tips         = '';
        $errorType    = '';
        if (!empty($errorMapping)) {
            foreach ($errorMapping as $errorMap) {
                if (in_array($errorCode, $errorMap['codes'])) {
                    $tips      = $errorMap['error'];
                    $errorType = $errorMap['error_type'];
                    break;
                }
            }
        }
        if (empty($tips)) {
            //默认提示
            $tips      = PaymentErrorCodeMapping::PAY_DEFAULT_ERROR_TIPS;
            $errorType = PaymentErrorCodeMapping::DEFAULT_ERROR_TYPE;
            if ($isCreditCard) {
                //信用卡默认提示
                $tips      = PaymentErrorCodeMapping::CREDIT_CARD_DEFAULT_ERROR_TIPS;
                $errorType = PaymentErrorCodeMapping::CREDIT_CARD_DEFAULT_ERROR_TYPE;
            }
        }
        return [
            'error'      => \Yii::t('common/error', $tips),
            'error_type' => $errorType,
        ];
    }

    /**
     * 格式化支付错误信息响应
     *
     * @param string $paymentMethod 支付方式
     *
     * @return array
     */
    public function formatPaymentErrorResponse($error, $errorType = '', $paymentMethod = '')
    {
        $tip = '';
        if (in_array($paymentMethod, PaymentRepository::$creditCardList)) {
            $tip = \Yii::t('common/error', 'default_credit_card_tips');
        }
        return [
            'error'      => $error,
            'error_type' => $errorType,
            'tips'       => $tip,
        ];
    }

    /**
     * 通过业务单号获取支付单信息
     *
     * @param string $businessNo
     *
     * @return array|null
     */
    public function getPaymentOrderInfoByBusinessNo(string $businessNo, $fields = '*')
    {
        return PaymentRepository::instance()->getByBusinessNo($businessNo, $fields);
    }

    /**
     * 是否显示绑卡
     *
     * @param bool   $isTouristAccess 是否游客访问
     * @param bool   $isLogin         是否登录
     * @param bool   $userId          用户ID
     * @param string $site            站点
     *
     * @return int
     */
    public function isShowBindCard($isTouristAccess, $isLogin, $userId, $site)
    {
        $abCreditUsMore  = AbExperimentService::instance()->getAbValue('AB_CREDIT_US_1000MORE', 0); //6为useepay信用卡
        $abCreditUsBelow = AbExperimentService::instance()->getAbValue('AB_CREDIT_US_1000BELOW', 0);
        if ($isTouristAccess) {
            //游客访问，不用绑卡
            $isShowBindCard = 0;
        } else if (!$isLogin) {
            //未登录，不用绑卡
            $isShowBindCard = 0;
        } else if (CreditCardAbValue::USEEPAY_CREDIR_CARD == $abCreditUsMore || CreditCardAbValue::USEEPAY_CREDIR_CARD == $abCreditUsBelow) {
            //如果实验命中了Useepay信用卡，看用户是否已有其他信用卡绑过卡
            $cardInfoResult = PayInternal::instance()->getBindingCardInfo($userId, $site);
            if (!empty($cardInfoResult['data'])) {
                //有绑过卡
                $isShowBindCard = 1;
            } else {
                $isShowBindCard = 0;
            }
        } else {
            //其他情况，都显示绑卡
            $isShowBindCard = 1;
        }
        return $isShowBindCard;
    }
}
