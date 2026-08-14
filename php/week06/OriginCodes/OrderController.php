<?php

namespace AppOrderApi\controllers;

use App\Utils\BaseFunction;
use App\Utils\EnterpriseWechatMessage;
use App\Utils\MyFunction;
use AppOrderApi\forms\AddressForm;
use AppOrderApi\forms\AfterSaleForm;
use AppOrderApi\forms\OrderConfirmForm;
use AppOrderApi\forms\OrderForm;
use AppOrderApi\forms\PlaceOrderForm;
use AppOrderApi\forms\ValetOrderForm;
use AppPayApi\lib\Code;
use common\api\AfterSaleApi;
use common\redis\common\LockHandleRedis;
use common\redis\order\CartRedis;
use common\redis\order\OrderRedis;
use common\redis\order\RepeatRedis;
use common\redis\order\StockRedis;
use common\repositorys\ga\GaEventLogRepository;
use common\repositorys\order\AfterSaleRepository;
use common\repositorys\order\OrderLogRepository;
use common\repositorys\order\OrderRepository;
use common\repositorys\user\UserRepository;
use common\services\logistics\ShippingRuleService;
use common\services\order\address\CheckService;
use common\services\order\AfterSaleService;
use common\services\order\ConfirmOrderService;
use common\services\order\OrderElasticService;
use common\services\order\OrderGoodsAllService;
use common\services\order\OrderGoodsTrackService;
use common\services\order\OrderService;
use common\services\order\PlaceOrderService;
use common\services\order\service\OrderAddressService;
use common\services\order\service\OrderGoodsValueAddedServiceService;
use common\services\order\WarehouseService;
use common\services\pay\PayConfirmService;
use common\services\product\ProductTermsOfService;
use common\services\user\AddressService;
use common\services\user\UserService;
use Exception;
use Stripe\Order;
use Yii;

class OrderController extends BaseApiController
{
    /**
     * 给供应链更新订单包裹信息
     */
    public function actionUpdatePickUpInfo()
    {
        try {
            $params = $this->request->getRawBody();
            $params = json_decode($params, true);
            if (empty($params)) {
                $params = $this->request->post();
            }
            //锁判断
            $redisKey = $params['salesOrderNo'] ?? '';
            $lock     = LockHandleRedis::instance()->lock($redisKey, 3);
            if ($lock) {
                $this->endFail(810507, 'Frequent operation, please try again later!');
            }
            g_log_info('pickupinfo.log', '打包消息通知日志：', [$params]);
            $result = OrderService::instance()->updatePickUpInfo($params);
            if ($result === true) {
                $this->endSuccess();
            }
            LockHandleRedis::instance()->unlock($redisKey);

        } catch (\Exception $e) {
            Yii::error($e->getMessage() . $e->getTraceAsString(), 'actionUpdatePickUpInfo');
            return $this->endFail(20000, $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 订单商品校验
     *
     * @author 白杨
     * @Date   2021/4/15 上午10:23
     */
    public function actionGoodsListCheck()
    {
        try {
            $params = $this->request->post();
            //锁判断
            $redisKey = $params['eid'] ?? '';
            $lock     = LockHandleRedis::instance()->lock($redisKey, 3);
            if ($lock) {
                return $this->endFail(810507, 'Frequent operation, please try again later!');
            }
            //参数验证
            $form     = new OrderConfirmForm();
            $formBool = $form->orderGoodsListCheckValidate($params);
            if (!$formBool) {
                $msg = [
                    "#### 订单商品校验 校验失败",
                    $form->getSimpleFirstError(),
                    json_encode($params, 320)
                ];
                EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
                return $this->endValidate(800000, $form->getSimpleFirstError());
            }

            $result = OrderService::instance()->goodsListCheck($params);
            LockHandleRedis::instance()->unlock($redisKey);

            switch ($result['code']) {
                case 1:
                    return $this->endSuccess($result['data'], $result['info']);
                case 800305:
                    return $this->endSuccess($result['data'], $result['info'], '', 800305);
                default:
                    return $this->endFail($result['code'], $result['info'], '', '', $result['info']);
            }

        } catch (\Exception $e) {
            Yii::error($e->getMessage() . $e->getTraceAsString(), 'goodsListCheck');
            return $this->endFail(20000, $e->getMessage() . $e->getTraceAsString());
        }
    }


    /**
     * @deprecated
     * 后台导入补单
     */
    public function actionSyncInfo()
    {
        try {
            $params = $this->requestParams;
            $result = OrderService::instance()->orderDispatch($params);
            if (1 == $result['code']) {
                return $this->endSuccess($result['data'], $result['info']);
            } else {
                return $this->endFail($result['code'], $result['info']);
            }
        } catch (\Exception $e) {
            Yii::error($e->getTraceAsString(), 'PlaceOrder');
            return $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }


    /**
     * 代客下单
     *
     * @author 白杨
     * @Date   2021/1/28 下午7:43
     */
    public function actionValetorder()
    {
        $params     = $this->request->post();
        $returnData = [];
        try {
            //是否锁定
            if (!empty($params['order_no'])) {
                $isLocked = OrderRedis::instance()->getOrderLocked($params['order_no']);
                if (!empty($isLocked)) {
                    throw new \Exception('订单正在修改，请稍后再试', 9402823);
                }
            }
            //入参验证
            $form = new ValetOrderForm();
            if (!$form->valetOrderValidate($params)) {
                throw new \Exception($form->getSimpleFirstError(), !empty($form->error_code) ? $form->error_code : 800000);
            }
            $address = is_string($params['address']) ? json_decode($params['address'], 1) : $params['address'];
            //锁判断
            $redisKey = $address['email'] ?? '';
            $lock     = LockHandleRedis::instance()->lock($redisKey, 3);
            if ($lock) {
                throw new \Exception('Frequent operation, please try again later!', 810507);
            }
            //地址校验
            $form = new AddressForm();
            //todo 调试暂时注释
            if (!$form->addressValidate($address)) {
                throw new \Exception($form->getSimpleFirstError(), !empty($form->error_code) ? $form->error_code : 800000);
            }
            if (OrderRepository::STORE_ORDER == $params['order_sales_type']) {
                if (empty($params['affiliated_store'])) {
                    throw new \Exception('请选择所属门店', 9402821);
                }
                if (empty($params['affiliated_staff'])) {
                    throw new \Exception('请选择创建人', 9402822);
                }
            }
            //todo 调试暂时注释
            if (!empty($params['pf']) && $params['pf'] == 'manage') {
                $result = CheckService::instance()->checkOrderAddress($address);
                if ($result['code'] != 1) {
                    throw new \Exception($result['info'], $result['code']);
                }
                $countryCode = $address['country_code'] ?? '';
                $stateCode   = $address['state_code'] ?? '';
                $zip         = $address['zip'] ?? '';
                if ($countryCode == 'US' && !AddressService::instance()->checkUsZipCode($countryCode, $stateCode, $zip)) {
                    throw new \Exception('Wrong postal code', 800312);
                }
            }

            $result = PlaceOrderService::instance()->valetOrder($params);
            LockHandleRedis::instance()->unlock($redisKey);
            //如果是0元单，则预警提示
            if ('free_order' == $params['pay_type']) {
                $msg = [
                    '0元单下单提示',
                    '订单类型：0元单',
                    sprintf('下单时间：%s', date('Y-m-d H:i:s')),
                ];
                if (1 == $result['code']) {
                    $msg[] = sprintf('订单号：%s', $result['data']['order_no']);
                    $msg[] = sprintf('订单金额：%s', $result['data']['pay_amount']);
                    $msg[] = sprintf('下单SKU：%s', implode(',', $result['data']['sku_code_list']));
                    $msg[] = sprintf('收件人：%s', $result['data']['consignee']);
                }
                $msg[] = sprintf('操作结果：%s', 1 == $result['code'] ? '下单成功' : ('下单失败：' . $result['info']));
                $msg[] = sprintf('操作人：%s', $params['operator_name']);
                EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'free_order');
            }
            $returnData = $result['data'];
            if (1 != $result['code']) {
                throw new \Exception($result['info'], $result['code']);
            }
            g_log_info('valet_order.log', '代客下单结果', [$params, $result]);
            $this->endSuccess($returnData, $result['info']);

        } catch (\Exception $e) {
            g_log_error('valet_order.log', '代客下单异常', [$params, $e->getCode(), $e->getMessage(), $e->getTraceAsString()]);
            $this->endFail($e->getCode(), $e->getMessage(), '', '', $e->getMessage(), $returnData, true);
        }
    }

    /**
     * 代客下单金额计算
     *
     * @return void
     */
    public function actionValetOrderCalculate()
    {
        try {
            $params = $this->request->post();
            $result = OrderService::instance()->valetOrderCalculate($params);
            if ($result['code'] == 1) {
                $this->endSuccess($result['data']);
            } else {
                $this->endFail($result['code'], $result['info']);
            }
        } catch (\Exception $e) {
            Yii::error($e->getMessage() . $e->getTraceAsString(), 'ValetOrderCalculate');
            $this->endFail($e->getCode(), $e->getMessage(), '', '', $e->getMessage(), null, true);
        }
    }

    /**
     * 配送方式
     *
     * @author 白杨
     * @Date   2021/2/19 上午11:49
     */
    public function actionLocationTypeList()
    {
        return $this->endSuccess([
            'location_type_list' => Yii::$app->params['location_type'],
        ]);
    }

    /**
     * 取消订单
     *
     * @author 白杨
     * @Date   2021/2/19 上午11:55
     */
    public function actionOrderCancel()
    {
        try {
            $params = $this->request->post();
            //锁判断
            $redisKey = trim($params['order_no'] ?? '');
            $source   = $params['source'] ?? '';
            if (empty($redisKey)) {
                return $this->endFail(20000, 'order no error');
            }
            $lock = LockHandleRedis::instance()->lock($redisKey);

            if ($lock) {
                return $this->endFail(810507, 'Frequent operation, please try again later!');
            }

            // 是否要验证取消理由是否存在
            $closedReason = $source == 2 ? [] : ['closed_reason', 'required'];

            //参数校验
            $form = new OrderForm();
            if (!$form->orderCancelValidate($params, $closedReason)) {
                $msg = [
                    "#### 订单取消 校验失败",
                    $form->getSimpleFirstError(),
                    json_encode($params, 320)
                ];
                EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
                return $this->endValidate(800000, $form->getSimpleFirstError());
            }

            // 跟数据库的做映射
            if ($source == 1) {
                $params['source'] = OrderRepository::CLOSED_TYPE_USER_CANCEL;
            }
            if ($source == 2) {
                $params['source'] = OrderRepository::CLOSED_TYPE_ADMIN_CANCEL;
            }

            $result = OrderService::instance()->orderCancel($params);
            LockHandleRedis::instance()->unlock($redisKey);
            if (1 == $result['code']) {
                return $this->endSuccess($result['data'], $result['info']);
            } else {
                return $this->endFail($result['code'], $result['info'], '', '', $result['info']);
            }
        } catch (\Exception $e) {
            $msg = [
                "#### 取消订单 抛出异常",
                $e->getMessage() . substr($e->getTraceAsString(), 0, 100),
                json_encode($params, 320)
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
            Yii::error($e->getTraceAsString(), 'actionOrderCancel');
            return $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 支付状态下，取消订单，如果其他场景使用，请修改source
     *
     * @author 白杨
     * @Date   2021/3/26 下午6:59
     */
    public function actionOrderRefund()
    {
        try {
            $params                    = $this->request->post();
            $params['after_sale_type'] = AfterSaleRepository::TYPE_UNDELIVERED_REFUND;
            $params['source']          = 1;

            $afterSaleForm = new AfterSaleForm();
            if (!$afterSaleForm->canApplyValidate($params)) {
                $this->endFail($afterSaleForm->getSimpleFirstError());
            }

            //锁判断
            $redisKey = $params['order_no'] ?? '';
            $lock     = LockHandleRedis::instance()->lock($redisKey, 3);
            if ($lock) {
                $this->endFail(810507);
            }
            //格式化取消理由成售后理由
            if (!empty($params['closed_reason'])) {
                $params['reason'] = AfterSaleService::instance()->getAfterSaleReasonFromOrderRefund($params['closed_reason']);
            }
            $result = AfterSaleService::instance()->applyAfterSale($params);
            LockHandleRedis::instance()->unlock($redisKey);
            if (1 == $result['code']) {
                $this->endSuccess($result['data'], $result['info']);
            } else {
                $this->endFail($result['code'], $result['info']);
            }
        } catch (\Exception $e) {
            Yii::error($e->getTraceAsString(), 'ApplyAfterSale');
            $this->endFail($e->getCode(), $e->getTraceAsString());
        }
    }

    /**
     * 订单确认收货
     *
     * @author 白杨
     * @Date   2021/2/19 上午11:55
     */
    public function actionOrderReceive()
    {
        try {
            $params = $this->request->post();
            //锁判断
            $redisKey = $params['order_no'] ?? '';
            $lock     = LockHandleRedis::instance()->lock($redisKey);
            if ($lock) {
                return $this->endFail(810507, 'Frequent operation, please try again later!');
            }
            //参数验证
            $form = new OrderForm();
            if (!$form->orderReceivedValidate($params)) {
                $msg = [
                    "#### 订单确认收货 校验失败",
                    $form->getSimpleFirstError(),
                    json_encode($params, 320)
                ];
                EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
                return $this->endValidate(800000, $form->getSimpleFirstError());
            }
            $result = OrderService::instance()->orderReceive($params);
            LockHandleRedis::instance()->unlock($redisKey);
            if (1 == $result['code']) {
                return $this->endSuccess($result['data'], $result['info']);
            } else {
                return $this->endFail($result['code'], $result['info'], '', '', $result['info']);
            }
        } catch (\Exception $e) {
            $msg = [
                "#### 确认收货 抛出异常",
                $e->getMessage() . substr($e->getTraceAsString(), 0, 100),
                json_encode($params, 320)
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
            Yii::error($e->getMessage() . $e->getTraceAsString(), 'actionOrderReceive');
            return $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     *  管理后台-修改订单地址-修改用户订单地址
     *
     * @author 白杨
     * @Date   2021/2/25 下午8:39
     */
    public function actionModifyAddress()
    {
        $params = $this->request->post();
        $form   = new AddressForm();
        if (!$form->switchZipValidate(true)->addressValidate($params)) {
            $msg = [
                "#### 订单修改地址 校验失败",
                $form->getSimpleFirstError(),
                json_encode($params, 320)
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
            return $this->endValidate(!empty($form->error_code) ? $form->error_code : 800000, $form->getSimpleFirstError(), $form->getSimpleFirstError());
        }
        $order = OrderRepository::instance()->getOrderByOrderNo($params['order_no']);
        // 判断是否禁售
        $shippingRuleResult = ShippingRuleService::instance()->getRule($params['country_code'], $params['state_code'], $params['zip']);
        if ($shippingRuleResult->isBanned()) {
            return $this->endFail(70025, Yii::t('common/error', 70025));
        }
        if (OrderRepository::STATUS_PAYMENT_PROCESSING == $order['order_status']) {
            return $this->endFail(810511, Yii::t('error', 810511));
        }

        // 查找订单地址
        $orderAddress = OrderAddressService::instance()->getOrderAddressByOrderNo($params['order_no']);

        // 检查订单是否有 完成了 售后类型是多退少补的地址修改售后单
        $hasEverChangeAddress = AfterSaleApi::instance()->hasDonePayOrRefundDeliveryAddress($params['order_no']);
        if (empty($hasEverChangeAddress['hasDoneDeliveryAddress'])) {
            //历史售后单判断：售后单的处理方案（修改信息-修改地址、修改发货方式-自提改配送）+售后支付/退款的金额>0+售后单状态已完成
            //订单商品存在以上条件的售后单时，再次申请售后修改地址、自提改配送时，不再进行补差/退差计算

            // 国家不同则才要去判断商品价格是否发生变化
            $isPayAmountChange              = false;
            $newAddressOrderPayAmountChange = [];
            if ($orderAddress['country_code'] != $params['country_code']) {
                $newAddressOrderPayAmountChange = OrderService::instance()->newAddressOrderPayAmountChange($order, $params);
                if ($newAddressOrderPayAmountChange['newAddressOrderPayAmountChange']) {
                    $isPayAmountChange = true;
                }
            } else {
                //国家相同则判断运费、运费税是否发生变化
                // 判断地址是否发生运费的变化
                $isOrderShippingFeeChange = OrderService::instance()->OrderShippingFeeAndTaxChange($order, $params);
                if ($isOrderShippingFeeChange['orderShippingFeeAndTaxChange']) { // 运费、运费税发生改变
                    $isPayAmountChange = true;
                }
            }

            if ($isPayAmountChange) { // 商品支付金额发生变更
                $changeInfo                        = $isOrderShippingFeeChange ?? $newAddressOrderPayAmountChange;
                $changeInfo['show_edit_order_btn'] = 1;
                $changeInfo['notice_title']        = \Yii::t('tag', 'price_difference_notice_title');
                $changeInfo['notice_content']      = \Yii::t('tag', 'price_difference_notice_content');
                $changeInfo['button_text']         = \Yii::t('tag', 'contact_us_button_text');
                if ($order['order_status'] == OrderRepository::STATUS_UNPAID) {
                    // 订单未支付：无法修改成功，提示去编辑订单页面修改，点击【编辑订单】可直接跳转
                    return $this->endFail(111001, \Yii::t('error', 111001), '', '', '', $changeInfo, true);
                } else {
                    // 订单已支付：无法修改成功，提示去售后处理
                    return $this->endFail(111002, \Yii::t('error', 111002), '', '', '', $changeInfo, true);
                }
            }
        }

        //判断是否还在支付成功状态，如果是直接修改地址
        if (in_array($order['order_status'], [OrderRepository::STATUS_UNPAID, OrderRepository::STATUS_PAID_SUCCESS, OrderRepository::STATUS_PAID_AUDIT])) {
            try {
                //锁判断
                $redisKey = $params['order_no'] ?? '';
                $lock     = LockHandleRedis::instance()->lock($redisKey);
                if ($lock) {
                    return $this->endFail(810507, 'Frequent operation, please try again later!');
                }

                $result = OrderService::instance()->modifyAddress($params);
                LockHandleRedis::instance()->unlock($redisKey);
                if (1 == $result['code']) {
                    $this->endSuccess($result['data'], $result['info']);
                } else {
                    $this->endFail($result['code'], $result['info'], '', '', $result['info'], $result['data']);
                }
            } catch (\Exception $e) {
                $msg = [
                    "#### 修改修改订单地址 抛出异常",
                    $e->getMessage() . substr($e->getTraceAsString(), 0, 100000),
                    json_encode($params, 320)
                ];
                EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
                Yii::error($e->getTraceAsString(), 'actionModifyAddress');
                $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());
            }
        } else {
            //其它状态需要走售后
            // 指定申请售后类型
            $params['processing_type'] = 65;
            $result                    = AfterSaleApi::instance()->userModifyAddress($params);
            if (!empty($result['code']) && 1 == $result['code']) {
                $this->endSuccess($result['data'], $result['info']);
            } else {
                $code       = $result['code'] ?? 0;
                $returnData = $result['data'] ?? [];
                $returnInfo = $result['info'] ?? '';
                if (!empty($returnData['show_confirm_edit_btn'])) {
                    $orderGoodsValueAddedServiceList = OrderGoodsValueAddedServiceService::instance()->getNextDayDeliveryListByOrderNo($params['order_no']);
                    $serviceNames                    = '';
                    if (!empty($orderGoodsValueAddedServiceList)) {
                        $serviceNameList = array_column($orderGoodsValueAddedServiceList, 'service_name');
                        $serviceNames    = implode(',', $serviceNameList);
                    }
                    $code                              = 111004;
                    $returnData['show_edit_order_btn'] = 0;
                    $returnData['notice_title']        = \Yii::t('tag', 'delay_next_day_delivery_notice_title', ['next_day_delivery' => $serviceNames]);
                    $returnData['notice_content']      = \Yii::t('tag', 'delay_next_day_delivery_notice_content', ['next_day_delivery' => $serviceNames]);
                    $returnData['button_text']         = \Yii::t('tag', 'confirm_address_change_button_text');
                    $returnInfo                        = $returnData['notice_content'];
                }
                $this->endFail($code, $returnInfo, '', '', '', $returnData);
            }

        }
    }

    /**
     * 在线支付完成写入mq，一小时后状态扭转为待发货，同时通知erp
     */
    public function actionDelayPaidHandle()
    {
        try {
            $params = $this->request->post();
            g_log_info('delayPaidHandle.log', 'actionDelayPaidHandle接收推送', $params);
            //锁判断
            $redisKey = $params['order_no'] ?? '';
            $lock     = LockHandleRedis::instance()->lock($redisKey);
            if ($lock) {
                return $this->endFail(810507, 'Frequent operation, please try again later!');
            }
            //成功之后，修改订单状态，同时更新订单es
            $result = OrderService::instance()->delayUpdatePaidSuccess($params, 0, 'order-MQ回调');
            LockHandleRedis::instance()->unlock($redisKey);
            if (1 == $result['code']) {
                return $this->endSuccess($result['data'], $result['info']);
            } else {
                return $this->endFail($result['code'], $result['info'], '', '', $result['info']);
            }
        } catch (\Exception $e) {
            Yii::error($e->getTraceAsString(), 'PlaceOrder');
            return $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 修改账单地址
     */
    public function actionUpdateBillAddress()
    {
        try {

            $billAddressId = $this->requestParams['bill_address_id'] ?? 0;
            $token         = $this->requestParams['token'] ?? '';
            $pf            = $this->requestParams['pf'] ?? '';
            $userId        = BaseFunction::instance()->getUserId($token);
            if (empty($billAddressId)) {
                return $this->endFail(800601);
            }
            $redisKey = $userId . $billAddressId . ' update:bill:address';

            $lock = LockHandleRedis::instance()->lock($redisKey);
            if ($lock) {
                return $this->endFail(810507);
            }
            $result = OrderService::instance()->updateBillAddress($userId, $billAddressId, $this->requestParams);
            if (1 == $result['code']) {
                return $this->endSuccess($result['data'], $result['info']);
            } else {
                return $this->endFail($result['code'], $result['info'], '', '', $result['info']);
            }
        } catch (Exception $e) {

            return $this->endFail(9001, $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 给订单写入备注
     */
    public function actionAddOrderRemarks()
    {
        try {
            $params = $this->request->post();
            // 锁判断
            $redisKey = $params['order_no'] ?? '';
            $lock     = LockHandleRedis::instance()->lock($redisKey, 2);
            if ($lock) {
                return $this->endFail(810507, 'Frequent operation, please try again later!');
            }

            // 参数验证
            $form = new OrderForm();
            if (!$form->orderRemarksValidate($params)) {
                return $this->endValidate(800000, $form->getSimpleFirstError());
            }

            // 写入备注内容
            $result = OrderService::instance()->addRemarks($params);
            if (1 == $result['code']) {
                return $this->endSuccess($result['data'], $result['info']);
            } else {
                return $this->endFail($result['code'], $result['info'], '', '', $result['info']);
            }
        } catch (\Exception $e) {
            Yii::error($e->getMessage() . $e->getTraceAsString(), 'addOrderRemarks');
            return $this->endFail(20000, $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 新版订单确认页改版，订单确认页和收银台合并。
     *
     * @author 白杨
     * @Date   2021/4/19 上午10:07
     */
    public function actionTradeConfirm()
    {
        $this->endFail(293838202, 'forbidden');
//        try {
//            $params = $this->request->post();
//            //锁判断
//            $redisKey = $params['eid'] ?? '';
//            $redisKey = md5($redisKey . 'actionTradeConfirm');
//            $lock     = LockHandleRedis::instance()->lock($redisKey, 1);
//            if ($lock) {
//                $this->endFail(810507, 'Frequent operation, please try again later!');
//            }
//            Yii::info('tradeConfirm接口参数：' . json_encode($params), 'TradeConfirm');
//            MyFunction::instance()->setTimeZone($params['site'] ?? 'us');
//
//            // 只有类型为4的情况下，走一下这个身份获取
//            $source               = $params['source'] ?? 0;
//            $params['is_tourist'] = $params['is_tourist'] ?? UserRepository::IS_REGISTER;
//            if ($source == 4) {
//                $isTourist            = UserService::instance()->getLoginOrGuest($params);
//                $params['is_tourist'] = $isTourist;
//            }
//
//            //参数验证
//            if (empty($params['token'])) {
//                $params['is_tourist'] = 1;
//            }
//
//            $form = new OrderConfirmForm();
//            if (!$form->tradeConfirmValidate($params)) {
//                $msg = [
//                    "#### Trade Confirm 校验失败",
//                    $form->getSimpleFirstError(),
//                    json_encode($params, 320)
//                ];
//                EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
//                return $this->endValidate(800000, $form->getSimpleFirstError());
//            }
//            $params['version_int'] = $this->versionInt;
//            $result                = ConfirmOrderService::instance()->tradeConfirm($params);
//            LockHandleRedis::instance()->unlock($redisKey);
//            if (1 == $result['code']) {
//                return $this->endSuccess($result['data'], $result['info']);
//            } else {
//                return $this->endFail($result['code'], $result['info'], '', '', $result['info']);
//            }
//        } catch (\Exception $e) {
//            $msg = [
//                "#### Trade Confirm 抛出异常",
//                $e->getMessage() . substr($e->getTraceAsString(), 0, 100),
//                json_encode($params, 320)
//            ];
//            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
//            Yii::error($e->getMessage() . $e->getTraceAsString(), 'TradeConfirm');
//            return $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());
//        }
    }

    /**
     * 下单接口
     */
    public function actionTradePlace()
    {
        try {
            $params           = $this->request->post();
            $requestParamsLog = $params;
            unset($requestParamsLog['card_data_info']);
            Yii::info('TradePlace接口请求参数：' . json_encode($requestParamsLog));
            //锁判断
            $redisKey = $params['eid'] ?? '';
            $lock     = LockHandleRedis::instance()->paymentLocked($redisKey, 8);
            if ($lock) {
                return $this->endFail(810507, 'Frequent operation, please try again later!');
            }
            MyFunction::instance()->setTimeZone($params['site'] ?? 'us');
            //兼容处理前端传coupon_user_id=undefined
            if (isset($params['coupon_user_id']) && 'undefined' == $params['coupon_user_id']) {
                $params['coupon_user_id'] = 0;
            }
            if (empty($params['source'])) {
                $params['source'] = 2;
            }
            //参数验证
            $form = new PlaceOrderForm();
            if (!$form->TradePlaceValidate($params)) {
                $msg = [
                    "#### Trade Place 校验失败",
                    $form->getSimpleFirstError(),
                    json_encode($requestParamsLog, 320)
                ];
                EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
                return $this->endValidate(!empty($form->error_code) ? $form->error_code : 800000, $form->getSimpleFirstError(), $form->getSimpleFirstError());
            }

            $params['user-agent']   = $this->request->getUserAgent();
            $params['content-type'] = $this->request->getContentType();

            $result = PlaceOrderService::instance()->tradePlace($params);
            LockHandleRedis::instance()->paymentUnlocked($redisKey);
            Yii::info(sprintf('TradePlace接口请求结果：%s', json_encode($result)));
            if (1 == $result['code']) {
                //清除购物车数量缓存
                $userId = BaseFunction::instance()->getUserId($params['token']);
                CartRedis::instance()->delItemNums($params['site'], $params['eid'], $userId);

                return $this->endSuccess($result['data'], $result['info']);
            } else {
                $msg = [
                    "#### Trade Place 下单失败",
                    json_encode($result, 320),
                    json_encode($requestParamsLog, 320)
                ];
                EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
                return $this->endFail($result['code'], $result['info'], '', '', $result['info'], $result['data']);
            }
        } catch (\Exception $e) {
            $msg = [
                "#### Trade Place 抛出异常",
                $e->getMessage() . substr($e->getTraceAsString(), 0, 100),
                json_encode($requestParamsLog, 320)
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
            Yii::error('TradePlace发生异常: ' . $e->getMessage() . $e->getTraceAsString());
            return $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * ga数据上报
     *
     * @author 白杨
     * @Date   2021/5/7 上午10:25
     */
    public function actionGaReportedResult()
    {
        try {
            $params = $this->request->post();
            //锁判断
            $redisKey = $params['eid'] ?? '';
            $lock     = LockHandleRedis::instance()->lock($redisKey, 2);
            if ($lock) {
                return $this->endFail(810507, 'Frequent operation, please try again later!');
            }
            //参数验证

            if (empty($params['business_no']) || empty($params['event']) || empty($params['reported_data'])) {
                return $this->endValidate(800000, 'Missing parameters');
            }
            $count = GaEventLogRepository::instance()->getCount($params['business_no'], $params['event']);
            if ($count > 0) {
                return $this->endValidate(800000, 'Data has been reported');
            }
            $params['reported_data'] = !is_string($params['reported_data']) ? json_encode($params['reported_data'], 320) : $params['reported_data'];
            $result                  = GaEventLogRepository::instance()->insertData($params['business_no'], $params['event'], $params['reported_data']);
            if (empty($result)) {
                return $this->endFail(20000, 'Operation failed, please try again');
            }
            LockHandleRedis::instance()->unlock($redisKey);
            return $this->endSuccess([], 'success');

        } catch (\Exception $e) {
            $msg = [
                "#### Ga Reported 抛出异常",
                $e->getMessage() . substr($e->getTraceAsString(), 0, 100),
                json_encode($params, 320)
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
            Yii::error($e->getMessage() . $e->getTraceAsString(), 'GaReported');
            return $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 更新取货方式
     *
     * @author 白杨
     * @Date   2021/6/1 下午3:33
     */
    public function actionUpdateLocationType()
    {
        try {
            $params = $this->request->post();
            $form   = new OrderForm();
            if (!$form->updateLocationTypeValidate($params)) {
                return $this->endValidate(800000, $form->getSimpleFirstError());
            }

            //锁判断
            $redisKey = $params['eid'] ?? '';
            $lock     = LockHandleRedis::instance()->lock($redisKey, 2);
            if ($lock) {
                return $this->endFail(810507, 'Frequent operation, please try again later!');
            }
            $result = OrderService::instance()->updateLocationType($params);
            if (empty($result)) {
                return $this->endFail(20000, 'Operation failed, please try again');
            }
            LockHandleRedis::instance()->unlock($redisKey);
            return $this->endSuccess([], 'success');

        } catch (\Exception $e) {
            $msg = [
                "####UpdateLocationType 抛出异常",
                $e->getMessage() . substr($e->getTraceAsString(), 0, 100),
                json_encode($params, 320)
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
            return $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 获取订单支付状态
     *
     * @author liangchupeng
     * @since  2021.07.08
     */
    public function actionGetPayStatus()
    {
        try {
            $params = $this->request->post();
            $userId = BaseFunction::instance()->getUserId($params['token']);
            if (empty($userId)) {
                $this->endFail(401);
            }
            if (empty($params['order_no'])) {
                $this->endFail(800000);
            }
            $result = OrderService::instance()->getPayStatus((int)$userId, $params['order_no'], $params['payment_no'] ?? '');
            $this->endSuccess($result['data'], 'success');

        } catch (\Throwable $e) {
            $this->endFail($e->getCode(), $e->getMessage(), '', '', $e->getMessage(), null, true);
        }
    }

    /**
     * 更新订单支付单号
     *
     * @author liangchupeng
     * @since  2021.07.19
     *
     * @docs
     */
    public function actionUpdatePaymentNo()
    {
        try {
            $params = $this->request->post();
            Yii::info(sprintf('[UpdatePaymentNo]接收参数:%s', json_encode($params)));
            $userId = BaseFunction::instance()->getUserId($params['token']);
            if (empty($userId)) {
                return $this->endFail(401);
            }
            if (empty($params['order_no'])) {
                return $this->endFail(800000);
            }
            if (empty($params['payment_no'])) {
                return $this->endFail(800000);
            }
            $result = OrderService::instance()->updateOrder((int)$userId, $params['order_no'], ['payment_no' => (string)$params['payment_no']]);
            Yii::info(sprintf('[UpdatePaymentNo]更新%s， 订单号：%s，支付单号：%s', (!empty($result) ? '成功' : '失败'), $params['order_no'], $params['payment_no']));
            if (!empty($result)) {
                return $this->endSuccess(null, 'success');
            } else {
                return $this->endFail(20000);
            }

        } catch (\Throwable $e) {
            Yii::error(sprintf('[UpdatePaymentNo]更新失败， 订单号：%s，支付单号：%s, 错误信息：%s', $params['order_no'], $params['payment_no'], ($e->getMessage() . $e->getTraceAsString())));
            return $this->endFail($e->getCode(), $e->getMessage(), '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    public function actionPaypalQuickPayCreateOrder()
    {
        $this->endFail(810507, 'Frequent operation, please try again later!');
    }


    /**
     * 接收支付域支付状态变更通知
     *
     * @author 接收通知
     */
    public function actionOrderHandle()
    {
        try {
            $receivedData = $this->request->getRawBody();
            $receivedData = @json_decode($receivedData, true);
            g_log_info(__METHOD__ . '.log', '接收推送', $receivedData);
            //锁判断
            $redisKey = $receivedData['payment_no'] ?? '';
            $lock     = LockHandleRedis::instance()->lock($redisKey);
            if ($lock) {
                $this->endFail(810507, 'Frequent operation, please try again later!');
            }
            // 查看回调是否已经存在，存在跳过这个订单的循环
            $result = PayConfirmService::instance()->innerNotifyHandle($receivedData);
            if (1 == $result['code']) {
                $this->endSuccess($result['data'], $result['info']);
            } else {
                $this->endFail($result['code'], $result['info']);
            }
        } catch (\Exception $e) {
            \Yii::error($e->getTraceAsString(), __METHOD__);
            $this->endFail(210001, $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 后台管理系统审核订单地址
     */
    public function actionConfirmOrderAddressInfo()
    {
        try {
            $params = $this->request->post();
            //锁判断
            $redisKey = $params['order_no'] ?? '';
            $lock     = LockHandleRedis::instance()->lock($redisKey);
            if ($lock) {
                $this->endFail(810507, 'Frequent operation, please try again later!');
            }

            $result = OrderService::instance()->confirmOrderAddressInfo($params);
            if (empty($result)) {
                $this->endFail(20000, 'Operation failed, please try again');
            }
            LockHandleRedis::instance()->unlock($redisKey);
            $this->endSuccess([], 'success');
        } catch (\Exception $e) {
            $msg = [
                "#### 后台管理信息确认地址信息出现异常",
                $e->getMessage() . substr($e->getTraceAsString(), 0, 100),
                json_encode($params, 320)
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
            Yii::error($e->getTraceAsString(), 'actionConfirmOrderAddressInfo');
            $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 超卖订单可以售卖，修改超卖标志为未超卖，推送给oms发货
     *
     * @author 白杨
     * @Date   11/8/21 上午11:36
     */
    public function actionOversoldCanSell()
    {
        try {
            $params = $this->request->post();
            //锁判断
            $redisKey = $params['order_no'] ?? '';
            $lock     = LockHandleRedis::instance()->lock($redisKey);
            if ($lock) {
                $this->endFail(810507, 'Frequent operation, please try again later!');
            }

            $result = OrderService::instance()->oversoldCanSell($params);
            if (empty($result)) {
                $this->endFail(20000, 'Operation failed, please try again');
            }
            LockHandleRedis::instance()->unlock($redisKey);

            if (1 == $result['code']) {
                $this->endSuccess($result['data'], $result['info']);
            } else {
                $this->endFail($result['code'], $result['info']);
            }
        } catch (\Exception $e) {
            $msg = [
                "#### 后台管理信息确认地址信息出现异常",
                $e->getMessage() . substr($e->getTraceAsString(), 0, 100),
                json_encode($params, 320)
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
            Yii::error($e->getTraceAsString(), 'actionConfirmOrderAddressInfo');
            $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 获取交易快照
     *
     * @author liangchupeng
     * @since  2021.08.23
     */
    public function actionGetTransSnapshot()
    {
        try {
            $params = $this->request->post();
            $userId = (int)BaseFunction::instance()->getDecSignature($params['signature'] ?? '');
            if (empty($userId)) {
                $userId = BaseFunction::instance()->getUserId($params['token'] ?? '');
            }
            if (empty($userId)) {
                return $this->endFail(401);
            }
            if (empty($params['order_goods_id'])) {
                return $this->endFail(800000);
            }
            $snapshot = OrderService::instance()->getTransSnapshot((int)$userId, $params['order_goods_id'], $params['pf'] ?? 'pc');
            return $this->endSuccess(!empty($snapshot) ? $snapshot : null, 'success');

        } catch (\Throwable $e) {
            return $this->endFail($e->getCode(), $e->getMessage(), '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 获取订单商品跟踪信息
     *
     * @author liangchupeng
     * @since  2021.09.16
     */
    public function actionGetOrderGoodsTrackInfo()
    {
        try {
            $params = $this->request->post();
            $userId = (int)BaseFunction::instance()->getDecSignature($params['signature'] ?? '');
            if (empty($userId)) {
                $userId = BaseFunction::instance()->getUserId($params['token'] ?? '');
            }
            if (empty($userId)) {
                return $this->endFail(401);
            }
            if (empty($params['order_goods_id'])) {
                return $this->endFail(800000);
            }
            MyFunction::instance()->setTimeZone($params['site'] ?? 'us');
            $language  = BaseFunction::instance()->getFormatLanguage(strtolower($params['language'] ?? 'en'), $params['site'] ?? 'us');
            $trackInfo = OrderGoodsTrackService::instance()->getOrderGoodsTrackList((int)$params['order_goods_id'], (int)$userId, $params['pf'], $language);
            return $this->endSuccess(!empty($trackInfo) ? $trackInfo : null, 'success');

        } catch (\Throwable $e) {
            return $this->endFail($e->getCode(), $e->getMessage(), '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 获取订单+订单商品整体跟踪的信息
     *
     * @author liangchupeng
     * @since  2021.09.16
     */
    public function actionGetOrderDetailTrackInfo()
    {
        try {
            $params = $this->request->post();
            if (empty($params['order_no']) || empty($params['pf']) || empty($params['language'])) {
                return $this->endFail(800000, Code::Map(Code::PARAM_ERROR));
            }
            $userId = (int)BaseFunction::instance()->getDecSignature($params['signature'] ?? '');
            if (empty($userId)) {
                $userId = BaseFunction::instance()->getUserId($params['token'] ?? '');
            }
            if (empty($userId)) {
                return $this->endFail(401);
            }
            MyFunction::instance()->setTimeZone($params['site'] ?? 'us');
            $trackInfo = OrderGoodsTrackService::instance()->getOrderDetailTrackList((string)$params['order_no'], (int)$userId, $params['pf'], $params['language'] ??
                'en', $params['site'] ?? 'us');
            return $this->endSuccess(!empty($trackInfo) ? $trackInfo : null, 'success');

        } catch (\Throwable $e) {
            Yii::error('GetOrderDetailTrackInfo异常：' . json_encode($params) . ',错误：' . $e->getMessage() . $e->getTraceAsString());
            return $this->endFail($e->getCode(), $e->getMessage(), '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 订单详情页推荐商品信息（支付成功页推荐商品）
     *
     * @author liangchupeng
     * @since  2021.09.17
     */
    public function actionGetOrderRecommendGoods()
    {
        try {
            $params = $this->request->post();
            if (empty($params['order_no'])) {
                return $this->endFail(800000);
            }
            $result = OrderService::instance()->getOrderRecommendGoods($params['order_no'], $params['site'], $params['currency'], $params['language'], 8, $params['experiment_diverted_sensors'] ?? '', $params['pf'] ?? 'pc', $params['page_from'] ?? 'order');
            return $this->endSuccess(!empty($result) ? ['title' => Yii::t('track', 'recommend_for_you'), 'list' => $result] : null, 'success');

        } catch (\Throwable $e) {
            return $this->endFail($e->getCode(), $e->getMessage(), '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 获取订单详情的服务条款
     *
     * @author liangchupeng
     * @since  2021.09.17
     */
    public function actionGetOrderServiceGuarantee()
    {
        try {
            $params = $this->request->post();
            if (empty($params['order_no'])) {
                return $this->endFail(800000, Code::Map(Code::PARAM_ERROR));
            }
            $result = OrderService::instance()->getOrderServiceGuarantee($params['order_no'], $params['pf'] ?? 'pc');
            return $this->endSuccess(!empty($result) ? $result : null, 'success');

        } catch (\Throwable $e) {
            return $this->endFail($e->getCode(), $e->getMessage(), '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 获取统一的服务条款
     *
     * @author liangchupeng
     * @since  2021.10.12
     */
    public function actionGetServiceGuarantee()
    {
        try {
            $params = $this->request->post();
            if (empty($params['site']) || empty($params['language']) || empty($params['pf'])) {
                return $this->endFail(800000, 'Params Required');
            }
            $areaData = [
                'country_code' => $params['country_code'] ?? '',
                'state_code'   => $params['state_code'] ?? '',
                'city'         => $params['city'] ?? '',
                'sku_code'     => '',
                'zip'          => $params['zip'] ?? '',
            ];

            $result = ProductTermsOfService::instance()->getTermsInfo($params['site'], $params['language'], $params['pf'], $areaData);
            if (!empty($result['terms_of_service'])) {
                foreach ($result['terms_of_service'] as $key => $item) {
                    $result['terms_of_service'][$key]['icon_tips'] = Yii::$app->params['default_guarantee_service_icon'];
                }
            }

            $termsOfService = $result['terms_of_service'];
            foreach ($termsOfService as &$item) {
                if ($item['key'] == 'free_shipping') {
                    $item['sort']      = 3;
                    $item['icon_tips'] = 'https://img5.su-cdn.com/common/2022/06/30/2e195cd4b56bae19f2838bc9c1088d35.png';
                }
                if ($item['key'] == 'late_delivery_compensation') {
                    $item['sort']      = 4;
                    $item['icon_tips'] = 'https://img5.su-cdn.com/common/2022/06/23/6abf501259878eca64c044a0fbabf88c.png';
                }
                if ($item['key'] == 'no_hassle_returns') {
                    $item['sort']      = 2;
                    $item['icon_tips'] = 'https://img5.su-cdn.com/common/2022/06/23/cfa6d04cf355a227116a7b455c8b7d89.png';
                }
                if ($item['key'] == 'damage_compensation') {
                    $item['sort']      = 5;
                    $item['icon_tips'] = 'https://img5.su-cdn.com/common/2022/06/30/d5998c8888d31234fb68a52bd0565275.png';
                }
                if ($item['key'] == 'expect_customer_service') {
                    $item['sort'] = 6;
                }
            }
            $secureCheckout = [
                'key'       => 'secure_checkout',
                'title'     => \Yii::t('common/app', 'Secure Checkout'),
                'content'   => [
                    \Yii::t('common/app', 'We use the latest SSL security technology to encrypt all personal information.'),
                ],
                'icon'      => 'https://img5.su-cdn.com/common/2022/06/07/733bba28434ad43755b1d3b8e6f07e15.png',
                'type'      => 0,
                'icon_tips' => 'https://img5.su-cdn.com/common/2022/06/23/616ebbbb55ba9ac0a76ac974ef5530b5.png',
                'sort'      => 1,
            ];
            array_unshift($termsOfService, $secureCheckout);
            // 重新排个序
            $termsOfService             = MyFunction::instance()->arraySort($termsOfService, 'sort');
            $result['terms_of_service'] = $termsOfService;

            return $this->endSuccess(!empty($result) ? $result : null, 'success');

        } catch (\Throwable $e) {
            return $this->endFail($e->getCode(), $e->getMessage(), '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 发付请邮件
     *
     * @author liangchupeng
     * @since  2021.10.30
     */
    public function actionSendPayEmail()
    {
        $params = $this->request->post();
        try {
            Yii::info('SendPayEmail接收参数：' . json_encode($params));
            if (empty($params['order_no'])) {
                return $this->endFail(800000);
            }
            $result = OrderService::instance()->sendPayEmail($params['order_no']);
            Yii::info('SendPayEmail:' . $params['order_no'] . '结果：' . (int)$result);
            if ($result) {
                return $this->endSuccess(!empty($result) ? $result : null, 'success');
            } else {
                return $this->endFail(20000, 'fail');
            }

        } catch (\Throwable $e) {
            Yii::error('SendPayEmail' . ($params['order_no'] ?? '') . '异常：' . $e->getMessage() . $e->getTraceAsString());
            return $this->endFail($e->getCode(), $e->getMessage(), '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 添加自提地址
     *
     * @author 白杨
     * @Date   23/11/21 下午1:38
     */
    public function actionAddPickUpAddress()
    {
        $params = $this->requestParams;
        try {
            $form = new OrderForm();
            if (!$form->adddPickUpAddress($params)) {
                $msg = [
                    "#### Add Pick Up Address 校验失败",
                    $form->getSimpleFirstError(),
                    json_encode($params, 320)
                ];
                EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
                $this->endValidate(800000, $form->getSimpleFirstError());
            }
            $params['user_id'] = BaseFunction::instance()->getUserId($params['token']);
            $result            = OrderService::instance()->addPickUpAddress($params);

            if ($result['code'] == 1) {
                $this->endSuccess($result['data'], 'success');
            } else {
                $this->endFail($result['code'], $result['info']);
            }

        } catch (\Throwable $e) {
            Yii::error('AddPickUpAddress异常：' . $e->getMessage() . json_encode($params, 320) . $e->getTraceAsString());
            $this->endFail($e->getCode(), $e->getMessage(), '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 修改自提仓库
     */
    public function actionModifyWarehouse()
    {
        $params = $this->request->post();
        try {
            Yii::info('ModifyWarehouse接收参数：' . json_encode($params));
            $result = WarehouseService::instance()->modifyWarehouse($params);
            Yii::info('ModifyWarehouse结果：' . (int)$result);
            if ($result) {
                return $this->endSuccess();
            } else {
                return $this->endFail(\AppOrderApi\lib\Code::FAIL, 'fail');
            }

        } catch (\Throwable $e) {
            Yii::error('ModifyWarehouse异常：' . $e->getMessage() . json_encode($params, 320) . $e->getTraceAsString());
            $this->endFail($e->getCode(), $e->getMessage(), '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 录入/修改自提时间
     */
    public function actionModifyWarehouseTime()
    {
        $params = $this->request->post();
        try {
            Yii::info('ModifyWarehouseTime接收参数：' . json_encode($params));
            $result = WarehouseService::instance()->modifyWarehouseTime($params);
            Yii::info('ModifyWarehouseTime结果：' . (int)$result);
            if ($result) {
                return $this->endSuccess();
            } else {
                return $this->endFail(\AppOrderApi\lib\Code::FAIL, 'fail');
            }

        } catch (\Throwable $e) {
            Yii::error('ModifyWarehouseTime异常：' . $e->getMessage() . json_encode($params, 320) . $e->getTraceAsString());
            return $this->endFail($e->getCode(), $e->getMessage(), '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 修改自提联系方式
     */
    public function actionModifyPickAddress()
    {
        $params = $this->getParams();
        try {
            Yii::info('ModifyPickAddress接收参数：' . json_encode($params));
            $result = OrderService::instance()->modifyPickAddress($params);
            Yii::info('ModifyPickAddress结果：' . (int)$result);
            if ($result) {
                return $this->endSuccess();
            } else {
                return $this->endFail(\AppOrderApi\lib\Code::FAIL, 'fail');
            }

        } catch (\Throwable $e) {
            Yii::error('ModifyPickAddress异常：' . $e->getMessage() . json_encode($params, 320) . $e->getTraceAsString());
            return $this->endFail($e->getCode(), $e->getMessage(), '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }


    /**
     * 修改配送方式
     */
    public function actionModifyDelivery()
    {
        $params = $this->getParams();
        try {
            Yii::info('ModifyDelivery接收参数：' . json_encode($params));
            $result = OrderService::instance()->modifyDelivery($params);
            Yii::info('ModifyDelivery结果：' . (int)$result);
            if ($result) {
                return $this->endSuccess();
            } else {
                return $this->endFail(\AppOrderApi\lib\Code::FAIL, 'fail');
            }

        } catch (\Throwable $e) {
            Yii::error('ModifyDelivery异常：' . $e->getMessage() . json_encode($params, 320) . $e->getTraceAsString());
            return $this->endFail($e->getCode(), $e->getMessage(), '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 订单地址校验失败后，修改地址后，认为地址是ok的，推送给oms
     */
    public function actionAddressIsPass()
    {
        $params = $this->getParams();
        try {
            $result = OrderService::instance()->addressIsPass($params);
            if ($result['code'] == 1) {
                return $this->endSuccess();
            } else {
                return $this->endFail($result['code'], $result['info']);
            }

        } catch (\Throwable $e) {
            Yii::error('AddressIsPass异常：' . $e->getMessage() . json_encode($params, 320) . $e->getTraceAsString());
            return $this->endFail($e->getCode(), $e->getMessage(), '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 第三方订单金额捕获
     *
     * @author liangchupeng
     * @since  2022.05.17
     */
    public function actionCapture()
    {
        try {

            $params = $this->request->post();
            if (empty($params['order_no'])) {
                return $this->endFail(400231, '订单号为空');
            }
            $result = OrderService::instance()->capture($params['order_no'], $params['order_goods_id'] ?? 0, $params['admin_name']);

            if (1 == $result['code']) {
                return $this->endSuccess($result['data'], $result['info']);
            } else {
                return $this->endFail($result['code'], $result['info']);
            }

        } catch (\Exception $e) {

            Yii::error([$params, $e->getMessage(), $e->getTraceAsString()]);
            return $this->endFail($e->getCode(), $e->getMessage());
        }
    }

    // 设置获取订单超时的配置
    public function actionSetRepeat()
    {
        $params = $this->getParams();
        $days   = $params['days'] ?? 7;//默认七天
        if ($days <= 0) {
            return $this->endFail('40000', '低于 1 无法设置');
        }
        RepeatRedis::instance()->setDays($days);
        $this->endSuccess();
    }

    // 获取超时配置
    public function actionGetRepeat()
    {
        $days = RepeatRedis::instance()->getDays();
        $this->endSuccess(['days' => $days]);
    }

    public function actionCancelRepeat()
    {
        $params   = $this->getParams();
        $orderNos = $params['order_nos'];
        // 更新这批订单为取消标记
        foreach ($orderNos as $orderNo) {
            $orderInfo = OrderRepository::instance()->getOrderByOrderNo($orderNo);
            OrderRepository::instance()->update(['is_repeat' => 2], ['=', 'order_no', $orderNo]);
            // 打标走流程
            $insertLogData = [
                'order_no'            => $orderNo,
                'is_show_to_customer' => 0,
                'code'                => 9000026,
                'content'             => \Yii::t('common/order_track', 9000026, [], 'zh-CN'),
                'pre_status'          => $orderInfo['order_status'],
                'current_status'      => $orderInfo['order_status'],
                'operator_type'       => 3,
                'operator_id'         => $operator['id'] ?? 0,
                'operator_name'       => $operator['name'] ?? '系统',
                'created_at'          => time(),
                'updated_at'          => time()
            ];
            OrderLogRepository::instance()->insert($insertLogData);
            StockRedis::instance()->setOrderEsOrderNo([$orderNo]);
        }
        $this->endSuccess();
    }

    /**
     * 换货创建订单
     *
     * @return void
     */
    public function actionExchangePlace()
    {
        $params = $this->request->post();
        g_log_info('exchange_place.log', '换货下单参数', ['request' => $params]);
        try {
            //锁判断
            $redisKey = $params['after_sale_no'] ?? '';
            $lock     = LockHandleRedis::instance()->lock($redisKey, 1);
            if ($lock) {
                throw new \Exception('Frequent operation, please try again later!', 810507);
            }
            MyFunction::instance()->setTimeZone($params['site'] ?? 'us');
            //参数验证
            $form = new PlaceOrderForm();
            if (!$form->exchangePlaceValidate($params)) {
                $msg = [
                    "#### Exchange Place 校验失败",
                    $form->getSimpleFirstError(),
                    json_encode($params, 320)
                ];
                EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
                throw new \Exception($form->getSimpleFirstError(), 800000);
            }
            $result = PlaceOrderService::instance()->exchangePlace($params);
            g_log_info('exchange_place.log', '换货下单', ['request' => $params, 'response' => $result]);
            if (1 == $result['code']) {
                $this->endSuccess($result['data'], $result['info']);
            } else {
                $msg = [
                    "#### Exchange Place 下单失败",
                    json_encode($result, 320),
                    json_encode($params, 320)
                ];
                EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
                $this->endFail($result['code'], $result['info'], '', '', $result['info'], $result['data']);
            }
        } catch (\Exception $e) {
            g_log_error('exchange_place.log', '换货下单异常', ['request' => $params, 'response' => [$e->getCode(), $e->getMessage(), $e->getTraceAsString()]]);
            $msg = [
                "#### Exchange Place 抛出异常",
                $e->getMessage() . substr($e->getTraceAsString(), 0, 100),
                json_encode($params, 320)
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
            $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage());
        }
    }

    /**
     * 售后完成，把所有的完结售后单，通知订单
     *
     * @return void
     */
    public function actionAfterSaleDoneNotify()
    {
        $params = $this->request->post();
        try {
            //锁判断
            $redisKey = $params['order_no'] ?? '';
            $lock     = LockHandleRedis::instance()->lock($redisKey, 1);
            if ($lock) {
                $this->endFail(810507, 'Frequent operation, please try again later!');
            }
            MyFunction::instance()->setTimeZone($params['site'] ?? 'us');

            $result = OrderService::instance()->afterSaleDoneNotify($params);
            g_log_info('after_sale_done_notify.log', '售后完成通知订单', ['request' => $params, 'response' => $result]);
            if (1 == $result['code']) {
                $this->endSuccess($result['data'], $result['info']);
            } else {
                $this->endFail($result['code'], $result['info'], '', '', $result['info'], $result['data']);
            }
        } catch (\Exception $e) {
            g_log_error('after_sale_done_notify.log', '售后完成通知订单异常', ['request' => $params, 'response' => [$e->getCode(), $e->getMessage(), $e->getTraceAsString()]]);
            $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 是否为禁用的邮编
     *
     * @return void
     */
    public function actionIsZipForbidden()
    {
        try {
            $params = $this->request->post();
            //锁判断
            $countryCode = $params['country_code'] ?? '';
            $zip         = $params['zip'] ?? '';
            if (empty($countryCode) || empty($zip)) {
                $this->endFail(800000, 'Error Param');
            }
            $result = AddressService::instance()->isZipForbidden($countryCode, $zip);
            if (1 == $result['code']) {
                $this->endSuccess();
            } else {
                $this->endFail($result['code'], $result['info']);
            }
        } catch (\Exception $e) {
            $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage());
        }
    }

    /**
     * 订单数据同步到es和order_goods_all
     *
     * @return void
     */
    public function actionOrderDataSync()
    {
        try {
            $params  = $this->requestParams;
            $orderNo = $params['order_no'] ?? '';
            if (empty($orderNo)) {
                $this->endFail(800000, 'Error Param');
            }
            $esResult            = OrderElasticService::instance()->updateOrderNoByOrderNos([$orderNo]);
            $orderGoodsAllResult = OrderGoodsAllService::instance()->syncOrderGoodsAll([$orderNo]);
            $this->endSuccess([$esResult, $orderGoodsAllResult]);
        } catch (\Exception $e) {
            g_log_error('order_data_sync.log', '订单数据同步异常', [$e->getMessage(), $e->getTraceAsString()]);
            $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 订单异常检测（订单信息是否发生改变/重复订单）
     *
     * @doc https://yapi.popicorns.com/project/135/interface/api/40586
     *
     * @return void
     */
    public function actionOrderAnomalyDetect()
    {
        $params = $this->getParams();
        g_log_info('order_anomaly_detect.log', '订单异常检测参数', [$params]);
        try {
            $orderNo      = $params['order_no'] ?? '';
            $orderVersion = $params['order_version'] ?? '';
            $result       = OrderService::instance()->orderAnomalyDetect($params, $orderNo, $orderVersion);
            g_log_info('order_anomaly_detect.log', '订单异常检测结果', [$result]);
            if (1 == $result['code']) {
                $this->endSuccess($result['data'], $result['info']);
            } else {
                $this->endFail($result['code'], $result['info'], '', '', $result['info'], $result['data']);
            }
        } catch (\Throwable $e) {
            g_log_error('order_anomaly_detect.log', '订单异常检测异常', [$e->getMessage(), $e->getTraceAsString()]);
            $this->endFail(20000, 'Operation failed, please try again', '', '', $e->getMessage());
        }
    }

    /**
     * 补发订单下单
     *
     * @return void
     */
    public function actionReissuePlace()
    {
        $params = $this->request->post();
        try {
            //参数验证
            $form = new PlaceOrderForm();
            if (!$form->reissuePlaceValidate($params)) {
                throw new \Exception($form->getSimpleFirstError(), 800000);
            }
            //锁判断
            $redisKey = 'reissue_order_' . $params['after_sale_no'];
            $lock     = LockHandleRedis::instance()->lock($redisKey, 2);
            if ($lock) {
                throw new \Exception('Frequent operation, please try again later!', 810507);
            }
            MyFunction::instance()->setTimeZone($params['site'] ?? 'us');
            //补发订单下单
            $result = PlaceOrderService::instance()->reissuePlace($params['after_sale_no'], $params['apply_reissue_order_info'], $params['apply_reissue_content'], $params['reissue_payment_no'],
                $params['pay_type'], $params['customer_service_amount'] ?? 0, $params['ams_reissue_order_detail'], $params['user_id'],
                $params['site'], $params['country_code'] ?? '', $params['state_code'] ?? '', $params['currency'], $params['language'],
                $params['zip'], $params['pf']);
            g_log_info('reissue_place.log', '补发下单', ['request' => $params, 'response' => $result]);
            if (1 == $result['code']) {
                $this->endSuccess($result['data'], $result['info']);
            } else {
                $this->endFail($result['code'], $result['info'], '', '', $result['info'], $result['data']);
            }
        } catch (\Exception $e) {
            g_log_error('reissue_place.log', '补发下单异常',
                ['request' => $params, 'response' => [$e->getCode(), $e->getMessage(), $e->getTraceAsString()]]);
            $this->endFail($e->getCode() ?: 20000, $e->getMessage(), '', '', $e->getMessage());
        }
    }


    /**
     * 获取订单信息 （快牛用）
     *
     * @return void
     */
    public function actionGetOrderInfo()
    {
        $params  = $this->requestParams;
        $orderNo = $params['order_no'] ?? '';
        $email   = $params['email'] ?? '';
        $skuCode = $params['sku_code'] ?? '';
        $result  = \common\services\udesk\OrderService::instance()->getOrderByNo($orderNo, $email, $skuCode);
        if ($result['code'] != 1) {
            $this->endFail($result['code'], $result['info']);
        }
        $this->endSuccess($result['data']);
    }
}
