<?php

namespace common\services;

use App\Utils\ConfigHelper;
use App\Utils\MyFunction;
use App\Utils\NodeExecutionEngine;
use App\Utils\RabbitMq;
use common\api\AmsApi;
use common\api\GoodsInternal;
use common\api\MarketInternal;
use common\api\OperateInternal;
use common\api\PayInternal;
use common\api\UserInternal;
use common\api\WeChatApi;
use common\api\OrderInternal;
use common\enums\AfterSaleActionEnum;
use common\enums\AfterSaleApplySourceEnum;
use common\enums\AfterSaleCompensationTypeEnum;
use common\enums\AfterSaleEmailEnum;
use common\enums\AfterSaleProcessTypeEnum;
use common\enums\AfterSaleStatusEnum;
use common\enums\AfterSaleTypeEnum;
use common\enums\AuditTypeEnum;
use common\enums\MemberConstants;
use common\enums\ModifyOrderTypeEnum;
use common\enums\PhaseEnum;
use common\enums\ReturnMethodEnum;
use common\profile\TrackingCompany;
use common\redis\AfterSaleDeliveryRedis;
use common\repositorys\AfterSaleConsultLabelRepository;
use common\repositorys\AfterSaleDeliveryRepository;
use common\repositorys\AfterSaleExchangeGoodsRepository;
use common\repositorys\AfterSaleGoodsRepository;
use common\repositorys\AfterSaleGoodsWarehouseSkuRepository;
use common\repositorys\AfterSaleLogisticsTrialCalculationRepository;
use common\repositorys\AfterSaleLogRepository;
use common\repositorys\AfterSaleRepository;
use common\repositorys\oms\ToOmsOrderRepository;
use common\services\audit\CancelOrderService;
use common\services\audit\RefundsService;
use common\services\check\AddressService;
use common\services\common\ExchangeCalculateService;
use common\services\common\MqService;
use common\services\contexts\AuditContext;
use common\services\contexts\apply\ClientApplyContext;
use common\services\contexts\apply\ManageApplyContext;
use common\services\contexts\ManageAuditContext;
use common\services\contexts\ReturnAddDeliveryContext;
use common\services\contexts\SubmitAuditOrTemporaryDataContext;
use common\services\email\EmailService;
use common\services\email\ModifyAddressEmail;
use common\services\email\ReturnRefundEmail;
use common\services\ga4\Ga4Service;
use common\services\nodes\AfterSaleGetNode;
use common\services\nodes\AfterSaleGoodsGetNode;
use common\services\nodes\apply\AfterSaleSaParamGetNode;
use common\services\nodes\apply\AutoSubmitAuditNode;
use common\services\nodes\apply\ClientGoodsInsertDataFormatNode;
use common\services\nodes\apply\ClientInsertDataFormatNode;
use common\services\nodes\apply\ClientPreDataFormatNode;
use common\services\nodes\apply\ClientReturnNode;
use common\services\nodes\apply\ConsultLabelGetNode;
use common\services\nodes\apply\GetAuditDataNode;
use common\services\nodes\apply\AfterSaleDataNode;
use common\services\nodes\apply\ClientApplyCheckNode;
use common\services\nodes\apply\SelfExchangeGoodsInsertDataFormatNode;
use common\services\nodes\audit\AuditCheckNode;
use common\services\nodes\audit\AuditHandleNode;
use common\services\nodes\apply\GoodsInsertDataFormatNode;
use common\services\nodes\apply\InsertDataFormatNode;
use common\services\nodes\apply\ToOmsOrderNode;
use common\services\nodes\apply\ManageApplyCheckNode;
use common\services\nodes\apply\PreDataFormatNode;
use common\services\nodes\apply\SaveDataNode;
use common\services\nodes\CreatePaymentNode;
use common\services\nodes\GetAfterSaleDataNode;
use common\services\nodes\OperatorHandleNode;
use common\services\nodes\OrderRelatedDataNode;
use common\services\nodes\PaymentGetNode;
use common\services\nodes\ReturnAddDeliveryNode;
use common\services\nodes\ReturnReceivedReturnNode;
use common\services\nodes\submitAuditOrTemporaryData\CancelInterceptNode;
use common\services\nodes\submitAuditOrTemporaryData\DataCheckNode;
use common\services\nodes\submitAuditOrTemporaryData\EmailSendNode;
use common\services\nodes\submitAuditOrTemporaryData\OffSetHandleNode;
use common\services\nodes\submitAuditOrTemporaryData\TemporaryInterceptHandleNode;
use common\services\nodes\submitAuditOrTemporaryData\ModifyOrderHandleNode;
use common\services\nodes\submitAuditOrTemporaryData\UpdateDataNode;
use common\services\nodes\submitAuditOrTemporaryData\ValueAddedServiceHandleNode;
use common\services\nodes\submitAuditOrTemporaryData\WarehouseSkuNode;
use common\services\nodes\ToOmsOrderGetDataNode;
use common\services\nodes\UpdateTaxDetailNode;
use common\services\oms\ToOmsOrderService;
use common\services\payment\RefundService;
use common\services\processingType\AfterSaleHandleService;
use common\services\processingType\concrete\ModifyReceiptMethodService;
use common\services\processingType\concrete\Protection;
use common\services\processingType\concrete\protection\CancelProtection;
use common\services\processingType\concrete\Reissue;
use common\services\processingType\concrete\transportinsurance\CancelTransportInsurance;
use common\services\processingType\ProcessingType;
use common\services\sensors\DisputeHappenedService;
use common\services\sensors\SensorsEvent;
use common\services\sensors\SensorsService;
use common\services\system\ConfigService;
use yii\db\ActiveRecord;
use yii\db\Exception;

class AfterSaleService extends \common\BaseService
{


    /**
     * 申请售后单限制
     *
     * @param       $orderNo
     * @param       $processingType
     * @param array $applyGoodsList
     *
     * @return array
     */
    public function checkRepeat($orderNo, $processingType, $applyGoodsList = [])
    {
        $afterSaleNo = '';
        // 1.同一处理方案，如果有未完结不能申请
        $isRepeatProcessing = false;
        // 2.修改信息和修改收货方式可申请的前提是，这个sku没有未完结的取消订单和未确认类型
        $isGoods = false;
        // 3 涉及到退款的（取消订单，换货，退货退款，其他退款），如果有未完结的不能申请
        $isRefund = false;

        //针对$applyGoodsList为空的时候，使用订单商品数据填充，后期补充。
        if (empty($applyGoodsList)) {
            $orderResult    = OrderInternal::instance()->getAllDataByOrderNo($orderNo, 'required');
            $orderGoodsList = $orderResult['data']['order_goods_list'] ?? [];
            if (!empty($orderGoodsList)) {
                $applyGoodsList = [];
                foreach ($orderGoodsList as $orderGoods) {
                    $applyGoodsList[] = [
                        'order_goods_id' => $orderGoods['order_goods_id'],
                        'qty'            => $orderGoods['qty'],
                    ];
                }
            }
        }

        // 读取相关的售后单
        $afterSaleList = AfterSaleRepository::instance()->getListByOrderNo($orderNo);
        if (empty($afterSaleList)) {
            return $this->returnSuccess();
        }
        $orderGoodsIds = [];
        // 商品的购买数量
        $orderGoodsQty = [];
        foreach ($applyGoodsList as $goods) {
            $orderGoodsIds[]                         = $goods['order_goods_id'];
            $orderGoodsQty[$goods['order_goods_id']] = !empty($goods['goods_qty']) ? $goods['goods_qty'] : 0;
        }
        $afterSaleGoodsList = AfterSaleGoodsRepository::instance()->getListByAfterSaleNos(array_column($afterSaleList, 'after_sale_no'));
        $arr                = [];
        foreach ($afterSaleGoodsList as $value) {
            if (!isset($arr[$value['after_sale_no']])) {
                $arr[$value['after_sale_no']] = [];
            }
            $arr[$value['after_sale_no']][] = $value;
            if (isset($orderGoodsQty[$value['order_goods_id']]) && $value['order_goods_qty'] > 0) {
                $orderGoodsQty[$value['order_goods_id']] = $value['order_goods_qty'];
            }
        }
        $afterSaleGoodsList = $arr;
        unset($arr);
        $exchangeGoodsQty = [];
        foreach ($afterSaleList as $afterSale) {
            // 判断取消，完结状态跳过
            if (in_array($afterSale['status'], [AfterSaleStatusEnum::CANCEL, AfterSaleStatusEnum::DONE])) {
                continue;
            }

            if ($afterSale['processing_type'] == AfterSaleProcessTypeEnum::EXCHANGE_GOODS) {
                $goodsList = $afterSaleGoodsList[$afterSale['after_sale_no']] ?? [];
                foreach ($goodsList as $goods) {
                    if (!isset($exchangeGoodsQty[$goods['order_goods_id']])) {
                        $exchangeGoodsQty[$goods['order_goods_id']] = [
                            'order_goods_id' => $goods['order_goods_id'],
                            'refund_qty'     => 0,
                            'after_sale_no'  => $afterSale['after_sale_no'],
                        ];
                    }
                    $exchangeGoodsQty[$goods['order_goods_id']]['refund_qty'] += $goods['refund_qty'] ?? 0;
                }
            }

            if ($afterSale['processing_type'] == $processingType) {
                $goodsList = $afterSaleGoodsList[$afterSale['after_sale_no']] ?? [];
                foreach ($goodsList as $goods) {
                    if (in_array($goods['order_goods_id'], $orderGoodsIds)) {
                        $afterSaleNo        = $afterSale['after_sale_no'];
                        $isRepeatProcessing = true;
                    }
                }
            }
            if (
                in_array($processingType, [AfterSaleProcessTypeEnum::MODIFY_ORDER_INFORMATION, AfterSaleProcessTypeEnum::RECEIPT_METHOD, AfterSaleProcessTypeEnum::UNCONFIRMED])
                && in_array($afterSale['processing_type'], [AfterSaleProcessTypeEnum::ONLY_REFUND, AfterSaleProcessTypeEnum::UNCONFIRMED])
            ) {
                $goodsList = $afterSaleGoodsList[$afterSale['after_sale_no']] ?? [];
                foreach ($goodsList as $goods) {
                    if (in_array($goods['order_goods_id'], $orderGoodsIds)) {
                        $afterSaleNo = $afterSale['after_sale_no'];
                        $isGoods     = true;
                    }
                }
            }
            if (in_array($processingType, [
                AfterSaleProcessTypeEnum::ONLY_REFUND,
                AfterSaleProcessTypeEnum::EXCHANGE_GOODS,
                AfterSaleProcessTypeEnum::RETURN_GOODS_REFUND,
                AfterSaleProcessTypeEnum::COMPENSATION,
                AfterSaleProcessTypeEnum::UNCONFIRMED,
            ])) {
                if (in_array($afterSale['processing_type'], [
                    AfterSaleProcessTypeEnum::ONLY_REFUND,
                    AfterSaleProcessTypeEnum::EXCHANGE_GOODS,
                    AfterSaleProcessTypeEnum::RETURN_GOODS_REFUND,
                    AfterSaleProcessTypeEnum::COMPENSATION,
                    AfterSaleProcessTypeEnum::UNCONFIRMED,
                ])) {
                    $goodsList = $afterSaleGoodsList[$afterSale['after_sale_no']] ?? [];
                    foreach ($goodsList as $goods) {
                        if (in_array($goods['order_goods_id'], $orderGoodsIds)) {
                            $afterSaleNo = $afterSale['after_sale_no'];
                            $isRefund    = true;
                        }
                    }

                }
            }
        }
        if ($isRefund) {
            return $this->returnError(42001, \Yii::t('error', 42001, ['after_sale_no' => $afterSaleNo]), ['after_sale_no' => $afterSaleNo]);
        }
        if ($isGoods) {
            return $this->returnError(42001, \Yii::t('error', 42001, ['after_sale_no' => $afterSaleNo]), ['after_sale_no' => $afterSaleNo]);
        }
        if ($isRepeatProcessing) {
            return $this->returnError(42001, \Yii::t('error', 42001, ['after_sale_no' => $afterSaleNo]), ['after_sale_no' => $afterSaleNo]);
        }
        // 判断是否换货售后单
        foreach ($exchangeGoodsQty as $orderGoodsId => $value) {
            if ($value['refund_qty'] <= 0) {
                continue;
            }
            if (isset($orderGoodsQty[$orderGoodsId]) && $orderGoodsQty[$orderGoodsId] > 0 && $value['refund_qty'] >= $orderGoodsQty[$orderGoodsId]) {
                return $this->returnError(42001, \Yii::t('error', 43001), ['after_sale_no' => $value['after_sale_no']]);
            }
        }
        return $this->returnSuccess();
    }

    /**
     * 管理后台申请售后。Premium 虚拟商品仅允许其他退款/退税费，免费权益服务拒绝。
     *
     * @param array $params 售后申请入参。processing_type / order_no / order_goods_id / goods_type
     *
     * @return array
     */
    public function manageApply($params)
    {
        $memberCheck = $this->validateMemberAfterSale($params);
        if ($memberCheck['code'] != 1) {
            return $memberCheck;
        }
        if (in_array($params['processing_type'], [AfterSaleProcessTypeEnum::MODIFY_ORDER_INFORMATION, AfterSaleProcessTypeEnum::REISSUE])) {
            $result = AddressService::instance()->applyAfterSaleCheckAddress($params);
            if ($result['code'] != 1) {
                return $result;
            }
        }
        if (in_array($params['processing_type'], \Yii::$app->params['processing_through_the_factory_class'])) {
            /**
             * 具体的售后处理类
             *
             * @var Reissue|ProcessingType|Protection|CancelProtection $handleClass
             */
            $handleClass = (new AfterSaleHandleService($params))->getConcreteClass($params['processing_type']);
            $result      = $handleClass->apply();
            g_log_info('manageApply', '申请售后日志', [$result, $params]);
            if (1 == $result['code']) {
                // 申请售后成功上报神策
                MqService::instance()->pushAfterSaleStatusChange($result['data']['after_sale_no'], $result['data']['after_sale_status'], $params['operator'] ?? [], AfterSaleActionEnum::ACTION_APPLY, '后台申请售后');
            }
        } else {
            //实例化，初始化参数
            $context   = new ManageApplyContext($params);
            $nodeChain = $this->getManageApplyNodeChain($context);
            $result    = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
            g_log_info('manageApply', '申请售后日志', [$result, $context->response, $params]);
            if (1 == $result['code']) {
                // 售后提交发送状态变化通知到MQ
                MqService::instance()->pushAfterSaleStatusChange($context->afterSaleNo, $context->afterSaleInsertData['status'], $context->operator, AfterSaleActionEnum::ACTION_APPLY, '后台申请售后');
                // 争议订单上报
                DisputeHappenedService::instance()->pushSensorsData($context->response['after_sale_no'], $context->orderAddressData, $params);
                // 生成售后单
                ReturnRefundEmail::instance()->applySendEmail($context->response['after_sale_no']);

                return $this->returnSuccess($context->response, 'success');
            }
            $result['data'] = array_merge($result['data'] ?? [], $context->response);
        }
        if (!empty($result['data']['after_sale_no']) && !empty($params['calculate_log_list'])) {
            //申请成功后，记录售后申请金额计算日志
            $operator = !empty($params['operator']) ? (is_string($params['operator']) ? json_decode($params['operator'], true) : $params['operator']) : [];
            AfterSaleLogRepository::instance()->insertByContent(
                $result['data']['after_sale_no'],
                -1, -1,
                ['售后金额计算日志' => $params['calculate_log_list']],
                $operator,
                $params
            );
        }
        return $result;
    }

    private function getManageApplyNodeChain(ManageApplyContext $context)
    {
        switch ($context->processingType) {
            // 留单补偿
            case AfterSaleProcessTypeEnum::COMPENSATION:
                $nodeChain = [
                    // 订单相关的数据获取
                    OrderRelatedDataNode::instance(),
                    // 订单数据，申请数据校验
                    ManageApplyCheckNode::instance(),
                    // 数据组装前的一些处理
                    PreDataFormatNode::instance(),
                    // 售后商品相关组装
                    GoodsInsertDataFormatNode::instance(),
                    // 售后数据组装
                    InsertDataFormatNode::instance(),
                    // 数据保存
                    SaveDataNode::instance(),
                ];
                break;
            // 补差价
            case AfterSaleProcessTypeEnum::OFFSET_ORDER:
                $nodeChain = [
                    // 订单相关的数据获取
                    OrderRelatedDataNode::instance(),
                    // 订单数据，申请数据校验
                    ManageApplyCheckNode::instance(),
                    // 数据组装前的一些处理
                    PreDataFormatNode::instance(),
                    // 售后商品相关组装
                    GoodsInsertDataFormatNode::instance(),
                    // 售后数据组装
                    InsertDataFormatNode::instance(),
                    // 数据保存
                    SaveDataNode::instance(),
                    UpdateTaxDetailNode::instance(),
                    // 创建支付单
                    CreatePaymentNode::instance()
                ];
                break;
            // 修改订单信息
            case AfterSaleProcessTypeEnum::MODIFY_ORDER_INFORMATION:
                $nodeChain = [
                    // 订单相关的数据获取
                    OrderRelatedDataNode::instance(),
                    // 订单数据，申请数据校验
                    ManageApplyCheckNode::instance(),
                    // 数据组装前的一些处理
                    PreDataFormatNode::instance(),
                    // 售后商品相关组装
                    GoodsInsertDataFormatNode::instance(),
                    // 售后数据组装
                    InsertDataFormatNode::instance(),
                    // 数据保存
                    SaveDataNode::instance(),
                    UpdateTaxDetailNode::instance(),
                    // 拦截商品，或给oms发送对应指令
                    ToOmsOrderNode::instance(),
                    // 自动提交审核
                    AutoSubmitAuditNode::instance(),
                ];
                break;
            // 换货
            case AfterSaleProcessTypeEnum::EXCHANGE_GOODS:
                $nodeChain = [
                    // 订单相关的数据获取
                    OrderRelatedDataNode::instance(),
                    // 订单的售后相关数据获取，组装
                    AfterSaleDataNode::instance(),
                    // 订单数据，申请数据校验
                    ManageApplyCheckNode::instance(),
                    // 数据组装前的一些处理
                    PreDataFormatNode::instance(),
                    // 售后商品相关组装
                    GoodsInsertDataFormatNode::instance(),
                    // 售后数据组装
                    InsertDataFormatNode::instance(),
                    // 数据保存
                    SaveDataNode::instance(),
                    // 拦截商品，或给oms发送对应指令
                    ToOmsOrderNode::instance(),
                    // 自动提交审核
                    AutoSubmitAuditNode::instance(),
                ];
                break;
            default:
                //默认的执行链
                $nodeChain = [
                    // 订单相关的数据获取
                    OrderRelatedDataNode::instance(),
                    // 订单的售后相关数据获取，组装
                    AfterSaleDataNode::instance(),
                    // 订单数据，申请数据校验
                    ManageApplyCheckNode::instance(),
                    // 数据组装前的一些处理
                    PreDataFormatNode::instance(),
                    // 售后商品相关组装
                    GoodsInsertDataFormatNode::instance(),
                    // 售后数据组装
                    InsertDataFormatNode::instance(),
                    // 数据保存
                    SaveDataNode::instance(),
                    UpdateTaxDetailNode::instance(),
                    // 数据保存后的一些处理
                    // 退货退款仓库sku
                    WarehouseSkuNode::instance(),
                    // 拦截商品，或给oms发送对应指令
                    ToOmsOrderNode::instance(),
                    // 自动提交审核
                    AutoSubmitAuditNode::instance(),
                    // ...
                ];
        }

        return $nodeChain;
    }

    /**
     * 用户端申请售后修改信息
     *
     * @param $params
     *
     * @return array
     */
    public function clientApply($params)
    {
        //实例化，初始化参数
        $context            = new ClientApplyContext($params);
        $context->eventName = SensorsEvent::EVENT_USER_APPLY_AFTER_SALE_SUCCESS_DETAILS;
        $nodeChain          = [
            // 订单相关的数据获取
            OrderRelatedDataNode::instance(),
            // 数据组装前的一些处理
            ClientPreDataFormatNode::instance(),
            // 订单数据，申请数据校验
            ClientApplyCheckNode::instance(),
            // 获取三级售后原因
            ConsultLabelGetNode::instance(),
            // 售后数据组装
            ClientInsertDataFormatNode::instance(),
            // 售后商品数据组装
            ClientGoodsInsertDataFormatNode::instance(),
            // 数据保存
            SaveDataNode::instance(),
            // 返回数据
            ClientReturnNode::instance(),
            // 售后事件上报参数
            AfterSaleSaParamGetNode::instance(),
            // 拦截商品，或给oms发送对应指令
            ToOmsOrderNode::instance(),
            // 自动提交审核
            AutoSubmitAuditNode::instance(),
        ];
        $result             = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
        if (1 == $result['code']) {
            // 售后提交发送状态变化通知到MQ
            MqService::instance()->pushAfterSaleStatusChange($context->afterSaleNo, $context->afterSaleInsertData['status'], $context->operator, AfterSaleActionEnum::ACTION_APPLY, '用户申请售后');
            // 申请售后成功上报神策
            // ApplyAfterSaleService::instance()->pushSensorsData(
            //     $context->response['after_sale_no'],
            //     $context->orderData,
            //     $context->orderGoodsData,
            //     $context->orderAddressData,
            //     $params,
            //     $context->orderGoodsOmsTrack
            // );
            return $this->returnSuccess($context->response, 'success');
        }
        return $result;
    }

    /**
     * 自主换货申请
     *
     * @param $params
     *
     * @return array
     */
    public function selfExchangeApply($params)
    {
        //实例化，初始化参数
        $params['processing_type'] = AfterSaleProcessTypeEnum::EXCHANGE_GOODS;
        $context                   = new ClientApplyContext($params);
        $context->eventName        = SensorsEvent::EVENT_USER_APPLY_AFTER_SALE_SUCCESS_DETAILS;
        $nodeChain                 = [
            // 订单相关的数据获取
            OrderRelatedDataNode::instance(),
            // 数据组装前的一些处理
            ClientPreDataFormatNode::instance(),
            // 订单数据，申请数据校验
            ClientApplyCheckNode::instance(),
            // 售后数据组装
            ClientInsertDataFormatNode::instance(),
            // 售后商品数据组装
            SelfExchangeGoodsInsertDataFormatNode::instance(),
            // 数据保存
            SaveDataNode::instance(),
            // 返回数据
            ClientReturnNode::instance(),
            // 拦截商品，或给oms发送对应指令
            ToOmsOrderNode::instance(),
        ];
        $result                    = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
        if (1 == $result['code']) {
            // 售后提交发送状态变化通知到MQ
            MqService::instance()->pushAfterSaleStatusChange($context->afterSaleNo, $context->afterSaleInsertData['status'], $context->operator, AfterSaleActionEnum::ACTION_APPLY, '自主换货售后申请');
            // 申请售后成功上报神策
            // ApplyAfterSaleService::instance()->pushSensorsData(
            //     $context->response['after_sale_no'],
            //     $context->orderData,
            //     $context->orderGoodsData,
            //     $context->orderAddressData,
            //     $params,
            //     $context->orderGoodsOmsTrack
            // );
            return $this->returnSuccess($context->response, 'success');
        }
        return $result;
    }

    public function getAfterSale($params)
    {
        $orderNo        = $params['order_no'] ?? '';
        $processingType = $params['processing_type'] ?? AfterSaleProcessTypeEnum::MODIFY_ORDER_INFORMATION;
        $where[]        = ['=', 'order_no', $orderNo];
        $where[]        = ['=', 'processing_type', $processingType];
        $where[]        = ['!=', 'status', AfterSaleStatusEnum::CANCEL];
        $afterSaleData  = AfterSaleRepository::instance()->getPageList($where, [], [['after_sale_id' => SORT_DESC]]);
        foreach ($afterSaleData as &$item) {
            if (!empty($item['processing_content'])) {
                $item['processing_content'] = json_decode($item['processing_content'], true);
            }
        }
        unset($item);
        return $this->returnSuccess(['list' => $afterSaleData]);
    }

    /**
     * 提交审核且暂存数据
     *
     * @param $params
     *
     * @return array
     */
    public function submitAuditOrTemporaryData($params)
    {
        if (
            isset($params['handle_action'])
            && $params['handle_action'] == AuditTypeEnum::SUBMIT_AUDIT
            && !empty($params['is_dispute'])
            && $params['is_dispute'] == 1
        ) {
            return $this->returnError(460002, 'It is dispute after sale order');
        }

        if (in_array($params['processing_type'], \Yii::$app->params['processing_through_the_factory_class'])) {
            /**
             * 具体的售后处理类
             *
             * @var ProcessingType|Protection|CancelProtection $handleClass
             */
            $handleClass = (new AfterSaleHandleService($params))->getConcreteClass($params['processing_type']);
            if ($params['handle_action'] == AuditTypeEnum::TEMPORARY_DATA) {
                $result = $handleClass->temporaryData();
            } else {
                $result = $handleClass->submitConfirm();
            }
        } else {
            //实例化，初始化参数
            $context   = new SubmitAuditOrTemporaryDataContext($params);
            $nodeChain = [
                // aftersaleNo关联售后单的查询
                GetAfterSaleDataNode::instance(),
                // 订单相关的数据获取
                OrderRelatedDataNode::instance(),
                // 订单的售后数据获取，组装
                AfterSaleDataNode::instance(),
                // 拦截工单数据获取
                ToOmsOrderGetDataNode::instance(),
                // 订单数据，申请数据校验
                DataCheckNode::instance(),
                // 数据组装前的一些处理
                \common\services\nodes\submitAuditOrTemporaryData\PreDataFormatNode::instance(),
                // 售后商品相关组装
                GoodsInsertDataFormatNode::instance(),
                // 售后插入数据组装
                InsertDataFormatNode::instance(),
                // 售后更新数据组装
                UpdateDataNode::instance(),
                //保存税费明细信息
                UpdateTaxDetailNode::instance(),
                // 退货退款仓库sku
                WarehouseSkuNode::instance(),
                // 留单补偿，补差价，提交审核的取消拦截处理
                CancelInterceptNode::instance(),
                // 补差单，创建支付单
                OffSetHandleNode::instance(),
                //新增增值服务-创建支付单，取消增值服务-白手套-创建退款单
                ValueAddedServiceHandleNode::instance(),
                // 修改订单信息、收货方式，提交审核的时候，查看是否发送对应指令
                ModifyOrderHandleNode::instance(),
                // 如果是暂存数据的场景下面，需要根据工单数据做统一判断
                TemporaryInterceptHandleNode::instance(),
                // 上面都走完了，走一下边缘业务，比如发送邮件
                EmailSendNode::instance(),
            ];
            $result    = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);

            if (
                !empty($context->afterSaleData)
                && $context->afterSaleData['processing_type'] == AfterSaleProcessTypeEnum::RETURN_GOODS_REFUND
                && $context->afterSaleData['status'] == AfterSaleStatusEnum::PENDING_REFUND_REVIEW
                && !empty($context->isApplyWarehouseSkuChange) //仓库sku信息发生了改变，主要是仓库sku数量和种类
            ) {
                g_log_info('amsReturnOrderUpdateSku', '更新ams退款单',
                    [
                        'processing_type'               => $context->afterSaleData['processing_type'],
                        'after_sale_status'             => $context->afterSaleData['status'],
                        'is_apply_warehouse_sku_change' => (int)$context->isApplyWarehouseSkuChange,
                    ]);
                RefundsService::instance()->amsReturnOrderUpdateSkuLog($context->afterSaleData, $context->operator);
            }

            if (1 == $result['code']) {
                $result = $this->returnSuccess($context->response, 'success');
            } else {
                $result = $this->returnError($result['code'], $result['info'], $context->response);
            }

            //自动提交审核，发现还未拦截成功，重新塞入mq,延迟处理
            if (!empty($context->params['is_auto_submit_audit']) && 79469812 == $result['code']) {
                AutoSubmitAuditNode::instance()->invokeNode($context);
            }
        }
        if (1 == $result['code'] && !empty($params['after_sale_no']) && !empty($params['calculate_log_list'])) {
            //申请成功后，记录售后申请金额计算日志
            $operator = !empty($params['operator']) ? (is_string($params['operator']) ? json_decode($params['operator'], true) : $params['operator']) : [];
            AfterSaleLogRepository::instance()->insertByContent(
                $params['after_sale_no'],
                -1, -1,
                ['售后金额计算日志' => $params['calculate_log_list']
                ],
                $operator,
                $params
            );
        }
        return $result;
    }

    /**
     * 指派处理人
     *
     * @param $params
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    public function designatedHandler($params)
    {
        $afterSaleNos = $params['after_sale_nos'];
        if (empty($afterSaleNos)) {
            return $this->returnError(40000, '售后单号为空');
        }
        if (!empty($params['operator']) && is_string($params['operator'])) {
            $params['operator'] = json_decode($params['operator'], 1);
        }
        $operatorId   = $params['operator_id'] ?? 0;
        $operatorName = $params['operator_name'] ?? '';
        if (empty($operatorId) || empty($operatorName)) {
            return $this->returnError(40001, '客服人员信息错误');
        }
        $where         = [
            ['in', 'after_sale_no', $afterSaleNos],
            ['=', 'status', AfterSaleStatusEnum::WAIT_FOR_DISTRIBUTION]
        ];
        $afterSaleList = AfterSaleRepository::instance()->getListByConditions($where, ['after_sale_id', 'after_sale_no', 'status']);
        if (empty($afterSaleList)) {
            return $this->returnError(40002, '符合条件的售后单为空');
        }

        $transaction = AfterSaleRepository::instance()->getConnection()->beginTransaction();
        try {
            //更新after_sale表
            $updateData = [
                'status'        => AfterSaleStatusEnum::PENDING,
                'operator_id'   => $operatorId,
                'operator_name' => $operatorName,
                'updated_at'    => time()
            ];
            AfterSaleRepository::instance()->updateData(['after_sale_id' => array_column($afterSaleList, 'after_sale_id')], $updateData);
            //插入日志
            AfterSaleLogRepository::instance()->insertLogsBatch(
                $afterSaleList,
                AfterSaleStatusEnum::PENDING,
                9001,
                ['operator_name' => $operatorName],
                $params['operator'],
                $params
            );

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            g_log_error('designatedHandler', 'error', [$params, $e->getTraceAsString()]);
            return $this->returnError(47001, $e->getMessage());
        }
        return $this->returnSuccess();
    }

    /**
     * 同步变更增值服务处理
     *
     * @param $afterSaleNo
     * @param $operator
     *
     * @return array
     * @throws Exception
     */
    public function valueAddedServiceHandle($afterSaleNo)
    {
        // 获取售后单信息
        $afterSaleData      = AfterSaleRepository::instance()->getAfterSaleByNo($afterSaleNo);
        $afterSaleGoodsData = AfterSaleGoodsRepository::instance()->getListByAfterSaleNo($afterSaleNo);
        // 解析信息
        $processingContent = json_decode($afterSaleData['processing_content'], true);
        $orderNo           = $afterSaleData['order_no'] ?? '';

        // 数据不存在报空
        if (empty($afterSaleData) || empty($afterSaleGoodsData)) {
            return $this->returnError(0, 'data null');
        }

        // 先执行取消服务
        $formatToOmsData = ToOmsOrderService::instance()->formatMidifyValueAddedServiceData($orderNo, $afterSaleNo, $processingContent['modify_content']);
        g_log_info('MidifyValueAddedServiceData', '同步变更增值服务处理结果', ['order_no' => $orderNo, 'after_sale_no' => $afterSaleNo, 'formatToOmsData' => $formatToOmsData]);
        if (!empty($formatToOmsData['cancel'])) {
            $cancelResult = ToOmsOrderService::instance()->pushOmsOrder($formatToOmsData['cancel']);
            if ($cancelResult['code'] != 1) {
                return $cancelResult;
            }
        }
        if (!empty($formatToOmsData['add'])) {
            $addreadResult = ToOmsOrderService::instance()->pushOmsOrder($formatToOmsData['add']);
            if ($addreadResult['code'] != 1) {
                return $addreadResult;
            }
        }

        return $this->returnSuccess(['cancel' => $cancelResult ?? null, 'add' => $addreadResult ?? null]);
    }

    /**
     * 取消运输险同步OMS处理
     *
     * @param string $afterSaleNo 售后单号
     *
     * @return array
     */
    public function cancelTransportInsuranceOmsHandle($afterSaleNo)
    {
        $afterSaleData      = AfterSaleRepository::instance()->getAfterSaleByNo($afterSaleNo);
        $afterSaleGoodsData = AfterSaleGoodsRepository::instance()->getListByAfterSaleNo($afterSaleNo);
        if (empty($afterSaleData) || empty($afterSaleGoodsData)) {
            return $this->returnError(3656083, 'After sale not found');
        }
        $orderNo         = $afterSaleData['order_no'];
        $formatToOmsData = ToOmsOrderService::instance()->formatCancelTransportInsuranceData($orderNo, $afterSaleNo, $afterSaleGoodsData);
        g_log_info('cancelTransportInsuranceOmsHandle', '同步取消运输险到OMS', [
            'order_no'        => $orderNo,
            'after_sale_no'   => $afterSaleNo,
            'formatToOmsData' => $formatToOmsData,
        ]);
        if (empty($formatToOmsData)) {
            return $this->returnSuccess();
        }
        return ToOmsOrderService::instance()->pushOmsOrder($formatToOmsData);
    }


    public function installServiceHandle($afterSaleNo)
    {
        // 获取售后单信息
        $afterSaleData      = AfterSaleRepository::instance()->getAfterSaleByNo($afterSaleNo);
        $afterSaleGoodsData = AfterSaleGoodsRepository::instance()->getListByAfterSaleNo($afterSaleNo);
        // 解析信息
        $processingContent = json_decode($afterSaleData['processing_content'], true);
        $orderNo           = $afterSaleData['order_no'] ?? '';

        // 数据不存在报空
        if (empty($afterSaleData) || empty($afterSaleGoodsData)) {
            return $this->returnError(22320, 'data null');
        }

        // 组装商品数据
        if (count($afterSaleGoodsData) > 1) {
            return $this->returnError(22321, 'data null');
        }
        $afterSaleGoods = $afterSaleGoodsData[0];
        $tmp            = [
            'order_goods_id' => $afterSaleGoods['order_goods_id'],
            'sku_id'         => $afterSaleGoods['sku_id'],
            'sku_code'       => $afterSaleGoods['sku_code'],
            'qty'            => $afterSaleGoods['qty'],
            'currency'       => $afterSaleData['currency'],
        ];
        $modifyContent  = $processingContent;
        if (!empty($modifyContent['old_service'])) {
            $modifyContent['old_service'] = array_merge($modifyContent['old_service'], $tmp);
        } else if (!empty($modifyContent['new_service'])) {
            $modifyContent['new_service'] = array_merge($modifyContent['new_service'], $tmp);
        }

        $formatToOmsData = ToOmsOrderService::instance()->formatModifyInstallServiceData($orderNo, $afterSaleNo, $modifyContent);
        g_log_info('MidifyValueAddedServiceData', '安装服务处理结果', $formatToOmsData);
        if (!empty($formatToOmsData['cancel'])) {
            $cancelResult = ToOmsOrderService::instance()->pushOmsOrder($formatToOmsData['cancel']);
            if ($cancelResult['code'] != 1) {
                return $cancelResult;
            }
        }
        if (!empty($formatToOmsData['add'])) {
            $addreadResult = ToOmsOrderService::instance()->pushOmsOrder($formatToOmsData['add']);
            if ($addreadResult['code'] != 1) {
                return $addreadResult;
            }
        }

        return $this->returnSuccess(['cancel' => $cancelResult ?? null, 'add' => $addreadResult ?? null]);
    }

    /**
     * 修改订单信息
     *
     * @param $afterSaleNo
     * @param $isCancle 1-取消 2-新增
     *
     * @return array|void
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     * @throws \yii\httpclient\Exception
     */
    public function modifyOrderInfo($afterSaleNo, $isOperate = 1)
    {
        // 获取售后单的数据
        $afterSaleData      = AfterSaleRepository::instance()->getAfterSaleByNo($afterSaleNo);
        $afterSaleGoodsData = AfterSaleGoodsRepository::instance()->getListByAfterSaleNo($afterSaleNo);
        $orderNo            = $afterSaleData['order_no'] ?? '';

        // 数据不存在报空
        if (empty($afterSaleData)) {
            return $this->returnError(0, 'data null');
        }

        // 拼装数据
        $goodsData = $this->getFormatInterceptOrderGoodsData($afterSaleGoodsData);

        // 解析处理方案的信息
        $processingContent = json_decode($afterSaleData['processing_content'], true);
        $modifyType        = $processingContent['code'] ?? '';

        // 把增值服务的信息拼接一下
        $serviceFlag = $processingContent['service_flag'] ?? [];
        if (!empty($serviceFlag)) {
            $serviceFlag['is_operate'] = $isOperate;
            // 取消的时候，使用old
            if ($isOperate == 1) {
                $serviceFlag['service_flag'] = $processingContent['old_service_flag'];
            }
            if ($isOperate == 2) {
                $serviceFlag['service_flag'] = $processingContent['new_service_flag'];
            }
        }

        switch ($modifyType) {
            // 修改为配送
            case ToOmsOrderRepository::MODIFY_ORDER_DELIVERY:
            case ToOmsOrderRepository::MODIFY_ORDER_ADDRESS_INFO:
                $addressInfo = $processingContent['address_info'] ?? [];
                if (empty($addressInfo)) {
                    throw new \yii\httpclient\Exception('address info null');
                }
                $modifyData['address_info']              = $addressInfo;
                $modifyData['service_flag']              = $serviceFlag;
                $modifyData['shipping_fee']              = $processingContent['shipping_fee_info']['new_shipping_fee'] ?? 0;
                $modifyData['usd_shipping_fee']          = (float)bcmul($modifyData['shipping_fee'], $afterSaleData['to_usd_rate'], 2);
                $modifyData['cny_shipping_fee']          = (float)bcmul($modifyData['shipping_fee'], $afterSaleData['to_cny_rate'], 2);
                $modifyData['shipping_fee_tax']          = $processingContent['shipping_fee_info']['new_shipping_fee_tax'] ?? 0;
                $modifyData['usd_shipping_fee_tax']      = (float)bcmul($modifyData['shipping_fee_tax'], $afterSaleData['to_usd_rate'], 2);
                $modifyData['cny_shipping_fee_tax']      = (float)bcmul($modifyData['shipping_fee_tax'], $afterSaleData['to_cny_rate'], 2);
                $modifyData['diff_shipping_fee']         = $processingContent['shipping_fee_info']['diff_shipping_fee'] ?? 0;
                $modifyData['usd_diff_shipping_fee']     = (float)bcmul($modifyData['diff_shipping_fee'], $afterSaleData['to_usd_rate'], 2);
                $modifyData['cny_diff_shipping_fee']     = (float)bcmul($modifyData['diff_shipping_fee'], $afterSaleData['to_cny_rate'], 2);
                $modifyData['diff_shipping_fee_tax']     = $processingContent['shipping_fee_info']['diff_shipping_fee_tax'] ?? 0;
                $modifyData['usd_diff_shipping_fee_tax'] = (float)bcmul($modifyData['diff_shipping_fee_tax'], $afterSaleData['to_usd_rate'], 2);
                $modifyData['cny_diff_shipping_fee_tax'] = (float)bcmul($modifyData['diff_shipping_fee_tax'], $afterSaleData['to_cny_rate'], 2);


                // 组装OMS的数据
                $commandType      = ToOmsOrderRepository::MODIFY_ORDER_TYPE;
                $toOmsOrderParams = ToOmsOrderService::instance()->formatInterceptData($orderNo, $afterSaleNo, $goodsData, $commandType, '', '', $modifyType, $modifyData);

                // 修改为配送
                return ToOmsOrderService::instance()->pushOmsOrder($toOmsOrderParams);

            // 指定发货时间
            case ToOmsOrderRepository::MODIFY_ORDER_ASSIGN_ISSUE_TIME:
                $assignIssueTime = $processingContent['assign_issue_time'];
                if (empty($assignIssueTime)) {
                    throw new \yii\httpclient\Exception('assign issue time null');
                }

                // 组装OMS的数据
                $modifyData       = ['assign_issue_time' => $assignIssueTime];
                $commandType      = ToOmsOrderRepository::MODIFY_ORDER_TYPE;
                $toOmsOrderParams = ToOmsOrderService::instance()->formatInterceptData($orderNo, $afterSaleNo, $goodsData, $commandType, '待确认', '', $modifyType, $modifyData);
                return ToOmsOrderService::instance()->pushOmsOrder($toOmsOrderParams);

            // 修改仓库code
            case ToOmsOrderRepository::MODIFY_ORDER_WAREHOUSE_CODE:
                $warehouseCode = $processingContent['warehouse_code'] ?? '';
                if (empty($warehouseCode)) {
                    throw new \yii\httpclient\Exception('warehouse code null');
                }

                // 组装OMS的数据
                $modifyData['warehouse_code']            = $warehouseCode;
                $modifyData['diff_shipping_fee_tax']     = $processingContent['shipping_fee_info']['diff_shipping_fee_tax'] ?? 0;
                $modifyData['usd_diff_shipping_fee_tax'] = (float)bcmul($modifyData['diff_shipping_fee_tax'], $afterSaleData['to_usd_rate'], 2);
                $modifyData['cny_diff_shipping_fee_tax'] = (float)bcmul($modifyData['diff_shipping_fee_tax'], $afterSaleData['to_cny_rate'], 2);
                $commandType                             = ToOmsOrderRepository::MODIFY_ORDER_TYPE;
                $toOmsOrderParams                        = ToOmsOrderService::instance()->formatInterceptData($orderNo, $afterSaleNo, $goodsData, $commandType, '', '', $modifyType, $modifyData);
                return ToOmsOrderService::instance()->pushOmsOrder($toOmsOrderParams);

            // 修改自提
            case ToOmsOrderRepository::MODIFY_ORDER_WAREHOUSE:
                $warehouseCode = $processingContent['warehouse_code'] ?? '';
                if (empty($warehouseCode)) {
                    throw new \yii\httpclient\Exception('warehouse code null');
                }

                // 组装OMS的数据
                $modifyData['warehouse_code']            = $warehouseCode;
                $modifyData['service_flag']              = $serviceFlag;
                $modifyData['shipping_fee']              = $processingContent['shipping_fee_info']['new_shipping_fee'] ?? 0;
                $modifyData['usd_shipping_fee']          = (float)bcmul($modifyData['shipping_fee'], $afterSaleData['to_usd_rate'], 2);
                $modifyData['cny_shipping_fee']          = (float)bcmul($modifyData['shipping_fee'], $afterSaleData['to_cny_rate'], 2);
                $modifyData['shipping_fee_tax']          = $processingContent['shipping_fee_info']['new_shipping_fee_tax'] ?? 0;
                $modifyData['usd_shipping_fee_tax']      = (float)bcmul($modifyData['shipping_fee_tax'], $afterSaleData['to_usd_rate'], 2);
                $modifyData['cny_shipping_fee_tax']      = (float)bcmul($modifyData['shipping_fee_tax'], $afterSaleData['to_cny_rate'], 2);
                $modifyData['diff_shipping_fee']         = $processingContent['shipping_fee_info']['diff_shipping_fee'] ?? 0;
                $modifyData['usd_diff_shipping_fee']     = (float)bcmul($modifyData['diff_shipping_fee'], $afterSaleData['to_usd_rate'], 2);
                $modifyData['cny_diff_shipping_fee']     = (float)bcmul($modifyData['diff_shipping_fee'], $afterSaleData['to_cny_rate'], 2);
                $modifyData['diff_shipping_fee_tax']     = $processingContent['shipping_fee_info']['diff_shipping_fee_tax'] ?? 0;
                $modifyData['usd_diff_shipping_fee_tax'] = (float)bcmul($modifyData['diff_shipping_fee_tax'], $afterSaleData['to_usd_rate'], 2);
                $modifyData['cny_diff_shipping_fee_tax'] = (float)bcmul($modifyData['diff_shipping_fee_tax'], $afterSaleData['to_cny_rate'], 2);
                $commandType                             = ToOmsOrderRepository::MODIFY_ORDER_TYPE;
                $toOmsOrderParams                        = ToOmsOrderService::instance()->formatInterceptData($orderNo, $afterSaleNo, $goodsData, $commandType, '', '', $modifyType, $modifyData);
                $result                                  = ToOmsOrderService::instance()->pushOmsOrder($toOmsOrderParams);

                // 修改为自提成功 修改商城的订单配送方式
                if (!empty($result['code']) && $result['code'] == 1) {
                    $warehouseList = AfterSaleRepository::instance()->getWarehouseList();
                    $warehouseList = array_column($warehouseList, null, 'warehouse_code');
                    $warehouseCode = $processingContent['warehouse_code'] ?? '';
                    $params        = [
                        'goods_list' => []
                    ];
                    foreach ($afterSaleGoodsData as $afterSaleGoods) {
                        $params['goods_list'][] = [
                            'order_no'               => $afterSaleData['order_no'],
                            'order_goods_id'         => $afterSaleGoods['order_goods_id'],
                            'delivery_method'        => 2,
                            'pick_up_warehouse_code' => $warehouseCode,
                            'pick_up_warehouse_name' => !empty($warehouseList[$warehouseCode]) ? $warehouseList[$warehouseCode]['warehouse_name'] : '',
                        ];
                    }
                    $params['goods_list'] = json_encode($params['goods_list'], JSON_UNESCAPED_UNICODE);
                    OrderInternal::instance()->afterSaleModifyOrder($params);
                }

                return $result;

            // 修改增值服务
            case ToOmsOrderRepository::MODIFY_ORDER_WHITE_GLOVES_FLAG:
                $modifyData['service_flag'] = $serviceFlag;
                $commandType                = ToOmsOrderRepository::MODIFY_ORDER_TYPE;
                $toOmsOrderParams           = ToOmsOrderService::instance()->formatInterceptData($orderNo, $afterSaleNo, $goodsData, $commandType, '', '', $modifyType, $modifyData);
                return ToOmsOrderService::instance()->pushOmsOrder($toOmsOrderParams);
                break;
        }

    }

    /**
     * 格式化调用OMS的商品数据
     *
     * @param $formatData
     * @param $type 1-是售后商品数据，2-工单数据（默认1）
     *
     * @return array
     */
    public function getFormatInterceptOrderGoodsData($formatData, int $type = 1): array
    {
        $goodsData = [];
        foreach ($formatData as $item) {
            $goodsData[] = [
                'sku_code'       => $item['sku_code'],
                'qty'            => $type == 2 ? $item['notice_qty'] : $item['apply_qty'],
                'order_goods_id' => $item['order_goods_id'],
            ];
        }
        return $goodsData;
    }

    /**
     * 审核通过
     *
     * @param $params
     *
     * @return array
     */
    public function auditPass($params)
    {
        $afterSale = AfterSaleRepository::instance()->getAfterSaleByNo($params['after_sale_no']);
        // 取消订单的退款走新逻辑
        if ($afterSale['processing_type'] == AfterSaleProcessTypeEnum::ONLY_REFUND) {
            return CancelOrderService::instance()->pass($afterSale, $params);
        }
        if (in_array($afterSale['processing_type'], \Yii::$app->params['processing_through_the_factory_class'])) {
            /**
             * 具体的售后处理类
             *
             * @var ProcessingType $handleClass
             */
            $handleClass = (new AfterSaleHandleService($params))->getConcreteClass($afterSale['processing_type']);
            return $handleClass->auditPass();
        } else {
            //组织上下文数据
            $context               = new AuditContext($params);
            $context->handleAction = AuditTypeEnum::AUDIT_PASS;
            // 扭转为审核状态
            $context->status = 0;
            $nodeChain       = [
                // 获取需要审核的数据
                GetAuditDataNode::instance(),
                PaymentGetNode::instance(),
                // 校验
                AuditCheckNode::instance(),
                // 流转状态为审核通过、售后已确认
                AuditHandleNode::instance(),
            ];

            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
            if (1 == $result['code']) {
                return $this->returnSuccess($context->response, 'success');
            } else {
                return $result;
            }
        }
    }

    /**
     * 审核拒绝
     *
     * @param $params
     *
     * @return array
     */
    public function auditReject($params)
    {
        $afterSale = AfterSaleRepository::instance()->getAfterSaleByNo($params['after_sale_no']);
        // 取消订单的退款走新逻辑
        if ($afterSale['processing_type'] == AfterSaleProcessTypeEnum::ONLY_REFUND) {
            return CancelOrderService::instance()->reject($afterSale, $params);
        }
        if (in_array($afterSale['processing_type'], \Yii::$app->params['processing_through_the_factory_class'])) {
            /**
             * 具体的售后处理类
             *
             * @var ProcessingType $handleClass
             */
            $handleClass = (new AfterSaleHandleService($params))->getConcreteClass($afterSale['processing_type']);
            return $handleClass->auditReject();
        } else {
            //组织上下文数据
            $context               = new AuditContext($params);
            $context->handleAction = AuditTypeEnum::AUDIT_REJECT;
            $nodeChain             = [
                // 获取需要审核的数据
                GetAuditDataNode::instance(),
                // 校验
                AuditCheckNode::instance(),
                // 扭转售后单状态
                AuditHandleNode::instance(),
            ];

            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
            if (1 == $result['code']) {
                return $this->returnSuccess($context->response, 'success');
            } else {
                return $result;
            }
        }
    }

    /**
     * 大额审核通过
     *
     * @param $params
     *
     * @return array
     */
    public function auditHighPass($params)
    {
        g_log_info('afterSaleAudit.log', '售后审核', $params);
        try {
            //组织上下文数据
            $context               = new AuditContext($params);
            $context->handleAction = AuditTypeEnum::AUDIT_HIGH_PASS;
            $nodeChain             = [
                // 获取需要审核的数据
                GetAuditDataNode::instance(),
                // 校验
                AuditCheckNode::instance(),
                // 扭转售后单状态
                AuditHandleNode::instance(),
            ];

            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
            if (1 == $result['code']) {
                return $this->returnSuccess($context->response, 'success');
            } else {
                return $result;
            }
        } catch (\Exception $e) {
            g_log_error('afterSaleAudit', '售后审核,异常', [$e->getMessage() . $e->getTraceAsString(), $params]);
            return $this->returnFormat(30008, null, $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 大额审核拒绝
     *
     * @param $params
     *
     * @return array
     */
    public function auditHighReject($params)
    {
        $afterSale = AfterSaleRepository::instance()->getAfterSaleByNo($params['after_sale_no']);
        // 取消订单的退款走新逻辑
        if ($afterSale['processing_type'] == AfterSaleProcessTypeEnum::ONLY_REFUND) {
            return CancelOrderService::instance()->largeAmountReject($afterSale, $params);
        }
        try {
            //组织上下文数据
            $context               = new AuditContext($params);
            $context->handleAction = AuditTypeEnum::AUDIT_HIGH_REJECT;
            $nodeChain             = [
                // 获取需要审核的数据
                GetAuditDataNode::instance(),
                // 校验
                AuditCheckNode::instance(),
                // 扭转售后单状态
                AuditHandleNode::instance(),
            ];

            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
            if (1 == $result['code']) {
                return $this->returnSuccess($context->response, 'success');
            } else {
                return $result;
            }
        } catch (\Exception $e) {
            return $this->returnFormat(30008, null, $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 关闭售后单
     *
     * @param $params
     *
     * @return array
     */
    public function cancelAfterSaleOrder($params)
    {
        $afterSale = AfterSaleRepository::instance()->getAfterSaleByNo($params['after_sale_no']);
        if (AfterSaleStatusEnum::CANCEL == $afterSale['status']) {
            return $this->returnError(5012001, 'After sales order has been cancelled');
        }
        $isIntercepting = AfterSaleAuditCheckService::instance()->checkIntercept($params['after_sale_no']);
        if ($isIntercepting) {
            return $this->returnError(5012034, 'Oms拦截订单结果未返回，请稍后重试！');
        }
        // 取消订单的退款走新逻辑
        if ($afterSale['processing_type'] == AfterSaleProcessTypeEnum::ONLY_REFUND) {
            return CancelOrderService::instance()->cancel($afterSale, $params);
        }
        if (in_array($afterSale['processing_type'], \Yii::$app->params['processing_through_the_factory_class'])) {
            /**
             * 具体的售后处理类
             *
             * @var ProcessingType $handleClass
             */
            $handleClass = (new AfterSaleHandleService($params))->getConcreteClass($afterSale['processing_type']);
            return $handleClass->cancel();
        } else {
            //组织上下文数据
            $context               = new AuditContext($params);
            $context->handleAction = AuditTypeEnum::CANCEL_AFTER_SALE_ORDER;
            $nodeChain             = [
                // 获取需要审核的数据
                GetAuditDataNode::instance(),
                // 校验
                AuditCheckNode::instance(),
                // 扭转售后单状态
                AuditHandleNode::instance(),
            ];

            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
            if (1 == $result['code']) {
                return $this->returnSuccess($context->response, 'success');
            } else {
                return $result;
            }
        }
    }

    /**
     * 拦截成功回调
     *
     * @param $businessNo
     * @param $commandCode
     * @param $resultType
     * @param $operator
     *
     * @return bool|void
     * @throws Exception
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     * @throws \yii\httpclient\Exception
     */
    public function interceptNotice($businessNo, $commandCode, $resultType, $operator, $noticeParams, $content)
    {
        $content = array_column($content, null, 'itemSkuCode');
        // 获取售后单信息
        $afterSaleData = AfterSaleRepository::instance()->getAfterSaleByNo($businessNo);
        // 获取处理方式
        switch ($afterSaleData['processing_type']) {
            case AfterSaleProcessTypeEnum::MODIFY_ORDER_INFORMATION:
            case AfterSaleProcessTypeEnum::RECEIPT_METHOD:

                $allCount = ToOmsOrderRepository::instance()->getCount([
                    ['=', 'business_no', $businessNo],
                    ['=', 'command_status', 0],
                    ['!=', 'command_code', $commandCode],
                    ['=', 'del_flag', 0],
                ]);

                // 还有工单未执行完，则暂时跳过
                if ($allCount > 0) {
                    return false;
                }

                // 核对一下当前状态是否正确
                $commandList = ToOmsOrderRepository::instance()->getListByConditions([
                    ['=', 'business_no', $businessNo],
                    ['=', 'command_code', $commandCode],
                    ['=', 'del_flag', 0],
                ]);
                foreach ($commandList as $item) {
                    // 表示当前拦截成功数量不足，扭转为待客服介入
                    if (!empty($content[$item['sku_code']]) && $item['qty'] != $content[$item['sku_code']]['qty']) {
                        $resultType = 3;
                        break;
                    }
                }

                // 如果工单成功，需要执行的操作
                $processingContent = @json_decode($afterSaleData['processing_content'], true);
                $code              = $processingContent['code'] ?? '';
                if ($resultType == 2) {
                    if (in_array($code, [ModifyOrderTypeEnum::MODIFY_ORDER_VALUE_ADDED_SERVICE, ModifyOrderTypeEnum::MODIFY_ORDER_WHITE_GLOVES_FLAG])) {
                        // 增值服务为待客服确认
                        $status = 1 == $afterSaleData['auto_complete'] ? AfterSaleStatusEnum::CONTENT_CONFIRMED : AfterSaleStatusEnum::CONSULT;
                    } else {
                        // 判断售后单是待审核状态下，修改地址信息保留状态
                        if ($code == ToOmsOrderRepository::MODIFY_ORDER_ADDRESS_INFO && $afterSaleData['status'] == AfterSaleStatusEnum::TO_AUDIT) {
                            $status = AfterSaleStatusEnum::TO_AUDIT;
                        } else {
                            // 属于修改订单信息的，但是不属于修改增值服务的，流程结束，保留现有售后单的状态，后续将在自动审核或人工审核通过后向OMS发起修改订单信息
                            $status = AfterSaleStatusEnum::CONTENT_CONFIRMED;
                        }
                    }

                } else {
                    // 失败为待介入
                    $status = AfterSaleStatusEnum::CUSTOMER_SERVICE_REVIEW;
                    // 同步OMS中不变更为待介入
                    if (in_array($afterSaleData['status'], [
                        AfterSaleStatusEnum::SYNC_OMS_ING,
                        AfterSaleStatusEnum::CANCEL,
                        AfterSaleStatusEnum::DONE
                    ])) {
                        $status = $afterSaleData['status'];
                    }
                    // 状态变更待客服介入状态发送群通知
                    WeChatApi::instance()->waitingCustomerServiceIntervene($afterSaleData, $operator, $params['message'] ?? '');
                }
                // 修改售后单状态
                AuditService::instance()->changeAfterSataus($afterSaleData['after_sale_no'], $afterSaleData['status'], $status, AfterSaleTypeEnum::MODIFY_ORDER_INFORMATION, $operator, 7000, ['supplement' => json_encode($noticeParams, 320)]);
                //如果是次日达且为自动完成售后，向oms取消增值服务
                if (1 == $afterSaleData['auto_complete'] && AfterSaleApplySourceEnum::APPLY_SOURCE_AUTO_CANCEL_NEXT_DAY == $afterSaleData['apply_source'] && ModifyOrderTypeEnum::MODIFY_ORDER_VALUE_ADDED_SERVICE == $code) {
                    $params = [
                        'after_sale_no' => $afterSaleData['after_sale_no'],
                        'operator'      => $operator,
                    ];
                    (new ModifyReceiptMethodService($params))->valueAddedServiceHandle();
                }
                break;
        }
    }

    /**
     * 取消拦截回调
     *
     * @param $businessNo
     * @param $commandCode
     * @param $resultType
     * @param $operator
     *
     * @return false|void
     * @throws Exception
     */
    public function cancelInterceptNotice($businessNo, $commandCode, $resultType, $operator, $noticeContent)
    {
        // 获取售后单信息
        $afterSaleData = AfterSaleRepository::instance()->getAfterSaleByNo($businessNo);
        // 取消拦截的工单成功为1
        if ($resultType == 1) {

            // 如果是补偿差价的取消拦截，则不流转售后单状态
            if ($afterSaleData['processing_type'] == AfterSaleProcessTypeEnum::OFFSET_ORDER) {
                return;
            }

            // 查看所有工单
            $allCount = ToOmsOrderRepository::instance()->getCount([
                ['=', 'business_no', $businessNo],
                ['=', 'command_status', 0],
                ['!=', 'command_code', $commandCode],
                ['=', 'del_flag', 0],
            ]);

            // 还有工单未执行完，则暂时跳过
            if ($allCount > 0) {
                return false;
            }
            // 如果是修改履约服务的取消拦截，判断一下
            $processingContent = @json_decode($afterSaleData['processing_content'], true) ?? [];
            if (!empty($processingContent['code']) && ModifyOrderTypeEnum::MODIFY_ORDER_VALUE_ADDED_SERVICE == $processingContent['code'] && AfterSaleApplySourceEnum::APPLY_SOURCE_AUTO_CANCEL_NEXT_DAY == $afterSaleData['apply_source']) {
                //修改次日达后，取消拦截的回调
                return (new ModifyReceiptMethodService(['after_sale_data' => $afterSaleData, 'operator' => $operator, 'notify_content' => $noticeContent]))->cancelInterceptNotify();
            }
            // 获取售后日志，查看取消拦截前售后状态为啥，确定流转完结态
            $afterSaleLog  = AfterSaleLogRepository::instance()->getOneLog([['=', 'after_sale_no', $businessNo]]);
            $currentStatus = $afterSaleLog['current_status'];
            $status        = AfterSaleStatusEnum::CANCEL;
            switch ($currentStatus) {
                case AfterSaleStatusEnum::REFUND_FAILD:
                case AfterSaleStatusEnum::REFUNDING:
                case AfterSaleStatusEnum::STATUS_REFUND:
                case AfterSaleStatusEnum::SYNC_OMS_ING:
                case AfterSaleStatusEnum::THE_PAYMENT_PAID:
                case AfterSaleStatusEnum::EXCHANGE_CONFIRMED:
                    $status = AfterSaleStatusEnum::DONE;
                    break;
            }

            if (!empty($processingContent['code']) && $processingContent['code'] == ModifyOrderTypeEnum::MODIFY_ORDER_VALUE_ADDED_SERVICE &&
                // 存在退款单号，或者支付成功时间，表明可以流转到已完结
                (!empty($afterSaleData['refund_no']) || !empty($afterSaleData['pay_at']))
            ) {
                $status = AfterSaleStatusEnum::DONE;
            }

            // 如果当前状态已经是终结，也跳出，已经做过修改
            if (in_array($afterSaleData['status'], [AfterSaleStatusEnum::CANCEL, AfterSaleStatusEnum::DONE])) {
                return;
            }

            // 修改售后单状态
            AuditService::instance()->changeAfterSataus($afterSaleData['after_sale_no'], $afterSaleData['status'], $status, AfterSaleTypeEnum::MODIFY_ORDER_INFORMATION, $operator, 7000, ['supplement' => json_encode($noticeContent, JSON_UNESCAPED_UNICODE)]);

            //取消售后上报神策
            $source = ($status == AfterSaleStatusEnum::CANCEL) ? 'OMS取消拦截成功回调售后取消' : 'OMS取消拦截成功回调售后完成';
            MqService::instance()->pushAfterSaleStatusChange($afterSaleData['after_sale_no'], $status, $operator,
                ($status == AfterSaleStatusEnum::CANCEL) ? AfterSaleActionEnum::ACTION_CANCEL : AfterSaleActionEnum::ACTION_DONE,
                $source);
            // 完结状态通知订单
            if ($status == AfterSaleStatusEnum::DONE) {
                OrderInternal::instance()->afterSaleDoneNotifyOrder($afterSaleData['order_no'] ?? '');
                $handler           = $operator['name'] ?? 'OMS取消拦截回调';
                $processingContent = @json_decode($afterSaleData['processing_content'], true);
                $allowCountryList  = ConfigHelper::getTransportInsuranceAllowCountryList();
                if (!empty($processingContent['code']) && ModifyOrderTypeEnum::MODIFY_ORDER_ADDRESS_INFO == $processingContent['code']
                    && in_array($processingContent['address_info']['country_code'], $allowCountryList)) {
                    //如果是修改地址，且在支持的国家，则修改运输险地址，否则取消运输险订单
                    $this->modifyTransportInsuranceOrderAddress($afterSaleData['after_sale_no'], $afterSaleData['order_no'], $processingContent['address_info'], $handler);
                } else {
                    //如果存在取消运输险的商品，则取消
                    $this->cancelTransportInsuranceOrderGoods($afterSaleData['after_sale_no'], $handler);
                }
                if ($afterSaleData['refund_amount'] > 0) {
                    // 售后成功邮件通知
                    EmailService::instance()->sendEmail($afterSaleData['after_sale_no'], AfterSaleEmailEnum::BUSINESS_CODE_AFTER_SALE_SUCCESS);
                }
            }

        } else {
            // 如果当前状态已经是待介入，也跳出，已经做过修改
            if ($afterSaleData['status'] == AfterSaleStatusEnum::CUSTOMER_SERVICE_REVIEW) {
                return;
            }
            // 失败为待介入
            $status = AfterSaleStatusEnum::CUSTOMER_SERVICE_REVIEW;
            // 同步OMS中不变更为待介入
            if (in_array($afterSaleData['status'], [
                AfterSaleStatusEnum::SYNC_OMS_ING,
                AfterSaleStatusEnum::CANCEL,
                AfterSaleStatusEnum::DONE
            ])) {
                $status = $afterSaleData['status'];
            }
            if ($afterSaleData['status'] == AfterSaleStatusEnum::CANCEL_INTERCEPT) {
                $status = AfterSaleStatusEnum::CANCEL;
            }
            // 修改售后单状态
            AuditService::instance()->changeAfterSataus($afterSaleData['after_sale_no'], $afterSaleData['status'], $status, AfterSaleTypeEnum::MODIFY_ORDER_INFORMATION, $operator, 7000, ['supplement' => json_encode($noticeContent, JSON_UNESCAPED_UNICODE)]);
            // 状态变更待客服介入状态发送群通知
            $message = '';
            if (is_array($noticeContent)) {
                $message = $noticeContent['message'] ?? '';
            }
            WeChatApi::instance()->waitingCustomerServiceIntervene($afterSaleData, $operator, $message);
        }
    }

    public function modifyOrderNotice($businessNo, $resultType, $commandCode, $operator, $noticeContent)
    {
        // 获取售后单信息
        $afterSaleData = AfterSaleRepository::instance()->getAfterSaleByNo($businessNo);
        // 解析处理方案的信息
        $processingContent = json_decode($afterSaleData['processing_content'], true);
        $modifyType        = $processingContent['code'] ?? '';

        // 修改安装服务通知 | 修改运输险
        if (in_array($modifyType, [ModifyOrderTypeEnum::MIDIFY_INSTALLATION, ModifyOrderTypeEnum::CANCEL_TRANSPORT_INSURANCE])) {
            $params = [
                'after_sale_data' => $afterSaleData,
                'operator'        => $operator,
                'command_type'    => ToOmsOrderRepository::MODIFY_ORDER_TYPE,
                'success'         => $resultType == 2,
            ];
            /**
             * 具体的售后处理类
             *
             * @var ProcessingType $handleClass
             */
            $handleClass = (new AfterSaleHandleService($params))->getConcreteClass(AfterSaleProcessTypeEnum::MODIFY_INSTALLATION_SERVICE);
            return $handleClass->toOmsOrderNotify();
        }

        // 工单成功
        if ($resultType == 2) {
            // 如果当前状态已经是取消拦截中，也跳出，已经做过修改
            if ($afterSaleData['status'] == AfterSaleStatusEnum::CANCEL_INTERCEPT) {
                return;
            }
            // 取出工单
            // A服务变更B服务的话，且当前指令为取消的话，暂时不执行下面的业务。否则如果执行了会多一次触发取消拦截的指令，导致售后单状态异常
            $commandInfo = ToOmsOrderRepository::instance()->getOneByConditions([['=', 'command_code', $commandCode]]);
            if ($processingContent['code'] == ModifyOrderTypeEnum::MODIFY_ORDER_VALUE_ADDED_SERVICE &&
                !empty($processingContent['modify_content'][0]['new_service_list']) &&
                !empty($processingContent['modify_content'][0]['old_service_list']) &&
                $commandInfo['service_operate'] == ToOmsOrderRepository::SERVICE_OPERATE_CANCEL
            ) {
                return;
            }
            //售后单状态变更已经会发送MQ消息消费了，这里注释掉
            /*if ($modifyType == ModifyOrderTypeEnum::MODIFY_ORDER_VALUE_ADDED_SERVICE) {
                // 修改成功重新申报
                EstimateTaxService::instance()->commitTransactionMqSend($afterSaleData['after_sale_no']);
            }*/
            $addressInfo = $processingContent['address_info'] ?? [];
            //修改为配送修改
            $afterSaleGoodsData = AfterSaleGoodsRepository::instance()->getListByAfterSaleNo($businessNo);
            if ($modifyType == ModifyOrderTypeEnum::MODIFY_ORDER_DELIVERY) {
                $params = [
                    'goods_list' => []
                ];
                foreach ($afterSaleGoodsData as $afterSaleGoods) {
                    $params['goods_list'][] = [
                        'order_no'               => $afterSaleData['order_no'],
                        'order_goods_id'         => $afterSaleGoods['order_goods_id'],
                        'delivery_method'        => 1,
                        'pick_up_warehouse_code' => '',
                        'pick_up_warehouse_name' => '',
                    ];
                }
                $params['goods_list']    = json_encode($params['goods_list'], 320);
                $params['address_info']  = json_encode($addressInfo);
                $params['after_sale_no'] = $afterSaleData['after_sale_no'];
                $params['order_no']      = $afterSaleData['order_no'];
                $params['modify_type']   = $modifyType;
                OrderInternal::instance()->afterSaleModifyOrder($params);
            }
            // 售后修改订单地址回调商城订单 is_update_order 状态为1的情况
            if ($modifyType == ModifyOrderTypeEnum::MODIFY_ORDER_ADDRESS_INFO) {
                if (!empty($processingContent['is_update_order']) && $processingContent['is_update_order'] == AfterSaleRepository::IS_UPDATE_ORDER_YES) {
                    $params                  = $addressInfo;
                    $params['order_no']      = $afterSaleData['order_no'];
                    $params['after_sale_no'] = $businessNo;
                    OrderInternal::instance()->afterSaleModifyOrderAddress($params);
                }
                // 修改地址发送邮件
                if (!empty($afterSaleData['email'])) {
                    ModifyAddressEmail::instance()->sendEmail($afterSaleData['after_sale_no'], $afterSaleData['email']);
                }
                //查订单信息，有保养合同的，发送更新protection信息
                $orderAllData = OrderInternal::instance()->getAllDataByOrderNo($afterSaleData['order_no'], null, 'required');
                if (isset($orderAllData['code']) && $orderAllData['code'] == 1 && !empty($orderAllData['data'])) {
                    if (!empty($orderAllData['data']['order_amount']['protection_plan_amount']) && $orderAllData['data']['order_amount']['protection_plan_amount'] > 0) {
                        $sendData = [
                            'after_sale_no'    => $afterSaleData['after_sale_no'],
                            'order_no'         => $afterSaleData['order_no'],
                            'shipping_address' => $addressInfo,
                        ];
                        $mqResult = RabbitMq::send(
                            \Yii::$app->params['extend_protection_plan_update']['exchange_name'],
                            \Yii::$app->params['extend_protection_plan_update']['route_key'],
                            $sendData
                        );
                        //插入日志
                        AfterSaleLogRepository::instance()->insertLog(
                            $afterSaleData['after_sale_no'],
                            $afterSaleData['status'],
                            $afterSaleData['status'],
                            9206,
                            ['supplement' => json_encode([$mqResult, $sendData], 320)],
                            $operator,
                            $noticeContent
                        );
                    }
                }
            }
            if ($afterSaleData['refund_amount'] > 0 && ($afterSaleData['refund_shipping_fee'] > 0 || $afterSaleData['refund_value_added_service_amount'] > 0) && !empty($processingContent['code']) &&
                in_array($processingContent['code'], [ModifyOrderTypeEnum::MODIFY_ORDER_WAREHOUSE,
                    ModifyOrderTypeEnum::MODIFY_ORDER_ADDRESS_INFO, ModifyOrderTypeEnum::MODIFY_ORDER_DELIVERY
                ])
            ) {
                //如果要退差，创建退款单
                $refundResult = RefundService::instance()->createdRefundByAfterSale($afterSaleData, $afterSaleData['refund_amount'], $afterSaleData['payment_no'], $operator, $afterSaleGoodsData);
                AfterSaleLogService::instance()->addLog($afterSaleData['after_sale_no'], $afterSaleData['status'],
                    1 == $refundResult['code'] ? AfterSaleStatusEnum::REFUNDING : $afterSaleData['status'],
                    sprintf('创建退款单%s：%s[%s]', 1 == $refundResult['code'] ? '成功' : '失败', $refundResult['data']['refund_no'] ?? '',
                        $refundResult['info']), [], ['create_refund_response' => $refundResult], $operator);
                if (1 != $refundResult['code']) {
                    // 创建退款单失败，流程中断
                    return;
                }
                //更新退款单号
                AfterSaleService::instance()->updateAfterSale($afterSaleData['after_sale_no'], AfterSaleStatusEnum::REFUNDING, null, null, null,
                    null, null, null, null, $refundResult['data']['refund_no']);
            } else {
                //调用取消拦截
                OmsService::instance()->cancelIntercept($afterSaleData['after_sale_no']);
                // 流转为取消拦截中
                AuditService::instance()->changeAfterSataus($afterSaleData['after_sale_no'], $afterSaleData['status'], AfterSaleStatusEnum::SYNC_OMS_ING,
                    AfterSaleTypeEnum::MODIFY_ORDER_INFORMATION, $operator, 7000, ['supplement' => json_encode($noticeContent, 320)]);
            }
            // 调用发送邮件的方法
            $afterSaleGoodData = AfterSaleGoodsRepository::instance()->getListByAfterSaleNo($afterSaleData['after_sale_no']);
            EmailService::instance()->modifySendEmail($afterSaleData['order_no'], array_column($afterSaleGoodData, 'order_goods_id'), $afterSaleData['processing_content']);
        } else {
            // 如果当前状态已经是待介入，也跳出，已经做过修改
            if ($afterSaleData['status'] == AfterSaleStatusEnum::CUSTOMER_SERVICE_REVIEW) {
                return;
            }
            $newStatus = AfterSaleStatusEnum::CUSTOMER_SERVICE_REVIEW;
            // 同步OMS中不变更为待介入
            if (in_array($afterSaleData['status'], [
                AfterSaleStatusEnum::SYNC_OMS_ING,
                AfterSaleStatusEnum::CANCEL,
                AfterSaleStatusEnum::DONE
            ])) {
                $newStatus = $afterSaleData['status'];
            }
            // 待介入
            AuditService::instance()->changeAfterSataus($afterSaleData['after_sale_no'], $afterSaleData['status'], AfterSaleStatusEnum::CUSTOMER_SERVICE_REVIEW, AfterSaleTypeEnum::MODIFY_ORDER_INFORMATION, $operator, 7000, ['supplement' => json_encode($noticeContent, JSON_UNESCAPED_UNICODE)]);
            // 状态变更待客服介入状态发送群通知
            $message = '';
            if (is_array($noticeContent)) {
                $message = $noticeContent['message'] ?? '';
            }
            WeChatApi::instance()->waitingCustomerServiceIntervene($afterSaleData, $operator, $message);
        }
    }

    /**
     * 确认取消订单
     *
     * @param $businessNo
     * @param $resultType
     * @param $operator
     *
     * @throws Exception
     */
    public function comfirmCancelOrder($businessNo, $resultType, $operator, $noticeContent)
    {
        // 获取售后单信息
        $afterSaleData = AfterSaleRepository::instance()->getAfterSaleByNo($businessNo);

        if ($afterSaleData['processing_type'] == AfterSaleProcessTypeEnum::ONLY_REFUND) {
            CancelOrderService::instance()->confirmCancelOrderNotice($resultType, $afterSaleData, $operator, $noticeContent);
            return true;
        }

        // 工单成功
        if ($resultType == 1) {
            //判断是否有安装服务，如果有则先发起取消安装服务
            if (in_array($afterSaleData['processing_type'], [AfterSaleProcessTypeEnum::EXCHANGE_GOODS, AfterSaleProcessTypeEnum::EXCHANGE_NONSALE_SKU]) && $afterSaleData['refund_installation_service_amount'] > 0) {
                $cancelResult = AfterSaleService::instance()->cancelInstallationServiceOrder($afterSaleData['after_sale_no'], $afterSaleData['order_no'], $afterSaleData['status'], $afterSaleData['processing_type'], $operator);
                if (1 != $cancelResult['code'] || $cancelResult['data']['require_cancel']) {
                    //发起取消失败或者需要取消，则直接返回，无需执行后续操作
                    return $cancelResult;
                }
            }
            //如果是1小时的整单取消，需要走下退款审核通过操作。 1小时的整单取消不走拦截
            if (
                $afterSaleData['processing_type'] == AfterSaleProcessTypeEnum::ONLY_REFUND
                && $afterSaleData['after_sale_phase'] == PhaseEnum::PHASE_1_HOUR
                && $afterSaleData['refund_of_entire_order'] == 1
            ) {
                $updateData = [];
                try {
                    $afterSaleGoodsData   = AfterSaleGoodsService::instance()->getListByAfterSaleNo($businessNo);
                    $refundNo             = AuditService::instance()->createdRefund($afterSaleData, $operator, 0, $afterSaleGoodsData);
                    $where                = ['after_sale_no' => $afterSaleData['after_sale_no']];
                    $updateData           = [
                        'refund_no'  => $refundNo,
                        'updated_at' => time(),
                    ];
                    $updateData['status'] = $afterSaleData['refund_amount'] > 0 ? AfterSaleStatusEnum::REFUNDING : AfterSaleStatusEnum::DONE;
                    // 修改售后单状态为
                    AfterSaleRepository::instance()->updateData($where, $updateData);
                    if (0 == $afterSaleData['refund_amount'] && AfterSaleStatusEnum::DONE == $updateData['status']) {
                        //0元单完成售后通知订单
                        OrderInternal::instance()->afterSaleDoneNotifyOrder($afterSaleData['order_no']);
                        //0元退款直接完结不走支付退款回调，完结时同步退还抵扣积分
                        PayService::instance()->notifyGrowthPointsOnAfterSaleDone($afterSaleData);
                    }
                    //售后日志
                    AfterSaleLogRepository::instance()->insertLog(
                        $afterSaleData['after_sale_no'],
                        $afterSaleData['status'],
                        $updateData['status'],
                        6025,
                        ['refund_no' => $refundNo],
                        $operator,
                        $noticeContent,
                        '',
                        $updateData
                    );
                } catch (\Exception $e) {
                    AfterSaleLogRepository::instance()->insertLog(
                        $afterSaleData['after_sale_no'],
                        $afterSaleData['status'],
                        $afterSaleData['status'],
                        6026,
                        ['info' => $e->getMessage(),],
                        $operator,
                        $noticeContent,
                        '',
                        $updateData
                    );
                }
            } else {
                // 确认取消流转为完结
                AuditService::instance()->changeAfterSataus($afterSaleData['after_sale_no'], $afterSaleData['status'], AfterSaleStatusEnum::DONE, AfterSaleTypeEnum::MODIFY_ORDER_INFORMATION, $operator, 7000, ['supplement' => json_encode($noticeContent, JSON_UNESCAPED_UNICODE)]);
                // 完结状态通知订单
                OrderInternal::instance()->afterSaleDoneNotifyOrder($afterSaleData['order_no'] ?? '');
                //如果存在取消运输险的商品，则取消
                $this->cancelTransportInsuranceOrderGoods($afterSaleData['after_sale_no'], $operator['name'] ?? 'OMS取消订单回调');
                // 售后完成上报神策
                MqService::instance()->pushAfterSaleStatusChange($afterSaleData['after_sale_no'], AfterSaleStatusEnum::DONE, $operator, AfterSaleActionEnum::ACTION_DONE, 'OMS取消订单成功回调');
            }
        } else {
            $newStatus = AfterSaleStatusEnum::CUSTOMER_SERVICE_REVIEW;
            // 同步OMS中不变更为待介入
            if (in_array($afterSaleData['status'], [
                AfterSaleStatusEnum::SYNC_OMS_ING,
                AfterSaleStatusEnum::CANCEL,
                AfterSaleStatusEnum::DONE
            ])) {
                $newStatus = $afterSaleData['status'];
            }
            // 待介入
            AuditService::instance()->changeAfterSataus($afterSaleData['after_sale_no'], $afterSaleData['status'], $newStatus, AfterSaleTypeEnum::MODIFY_ORDER_INFORMATION, $operator, 7000, ['supplement' => json_encode($noticeContent, JSON_UNESCAPED_UNICODE)]);
            // 状态变更待客服介入状态发送群通知
            $message = '';
            if (is_array($noticeContent)) {
                $message = $noticeContent['message'] ?? '';
            }
            WeChatApi::instance()->waitingCustomerServiceIntervene($afterSaleData, $operator, $message);
        }
    }


    public function returnAddDelivery($params)
    {
        try {
            //组织上下文数据
            $context = new ReturnAddDeliveryContext($params);

            $nodeChain = [
                OperatorHandleNode::instance(),
                AfterSaleGetNode::instance(),
                AfterSaleGoodsGetNode::instance(),
                ReturnAddDeliveryNode::instance(),
            ];

            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
            if (1 == $result['code']) {
                return $this->returnSuccess($context->response, 'success');
            } else {
                return $result;
            }
        } catch (\Exception $e) {
            return $this->returnFormat(30008, null, $e->getMessage() . $e->getTraceAsString());
        }
    }

    public function returnReceivedReturn($params)
    {
        try {
            //组织上下文数据
            $context = new ManageAuditContext($params);

            $nodeChain = [
                AfterSaleGetNode::instance(),
                AfterSaleGoodsGetNode::instance(),
                ReturnReceivedReturnNode::instance(),
            ];

            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
            if (1 == $result['code']) {
                return $this->returnSuccess($context->response, 'success');
            } else {
                return $result;
            }
        } catch (\Exception $e) {
            return $this->returnFormat(30008, null, $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 是否自动审核
     *
     * @param     $afterSaleNo
     * @param     $params
     *
     * @param int $toOmsCode
     *
     * @return array
     */
    public function checkSubmitAudit($afterSaleNo)
    {
        $afterSale = AfterSaleRepository::instance()->getAfterSaleByNo($afterSaleNo);
        try {
            if (empty($afterSale)) {
                throw new \Exception('After sale order not found', 460001);
            }
            if ($afterSale['is_dispute'] == AfterSaleRepository::IS_UPDATE_ORDER_YES) {
                throw new \Exception('It is dispute after sale order', 460002);
            }
            if (!in_array($afterSale['processing_type'], [
                AfterSaleProcessTypeEnum::RETURN_GOODS_REFUND,
                AfterSaleProcessTypeEnum::ONLY_REFUND,
                AfterSaleProcessTypeEnum::EXCHANGE_GOODS,
                AfterSaleProcessTypeEnum::MODIFY_ORDER_INFORMATION,
                AfterSaleProcessTypeEnum::RECEIPT_METHOD,
            ])) {
                throw new \Exception('It is not processing type in need', 460004);
            }
            //判断当前售后单是否存在发起拦截中或拦截失败，如果是则无法
            if (AfterSaleProcessTypeEnum::RETURN_GOODS_REFUND != $afterSale['processing_type']) {
                $interceptResult = ToOmsOrderService::instance()->getInterceptResultByAfterSaleNo($afterSaleNo);
                //存在拦截失败
                if (!$interceptResult) {
                    throw new \Exception('Exist intercept fail record', 460012);
                }
            }
            return $this->returnSuccess();

        } catch (\Throwable $e) {

            return $this->returnError($e->getCode(), $e->getMessage());
        }
    }

    public function addDelivery($params)
    {
        $proveUrl = '';
        if (!empty($params['prove_url'])) {
            $proveUrl = implode(',', $params['prove_url']);
        }
        $data = [
            'after_sale_no'    => $params['after_sale_no'],
            'tracking_number'  => $params['tracking_number'],
            'tracking_company' => $params['tracking_company'],
            'prove_url'        => $proveUrl,
            'created_at'       => time(),
            'updated_at'       => time(),
            'del_flag'         => 0,
        ];
        AfterSaleDeliveryRepository::instance()->insertData($data);
        $afterSaleData = AfterSaleRepository::instance()->getAfterSaleByNo($params['after_sale_no']);
        // 扭转状态
        AfterSaleRepository::instance()->updateData(['after_sale_no' => $params['after_sale_no']], ['status' => AfterSaleStatusEnum::WAITING_FOR_WAREHOUSE_RECEIVE_GOODS, 'updated_at' => time()]);
        // 插入日志
        AfterSaleLogRepository::instance()->insertLog(
            $params['after_sale_no'],
            $afterSaleData['status'],
            AfterSaleStatusEnum::WAITING_FOR_WAREHOUSE_RECEIVE_GOODS,
            '',
            [],
            ['id' => 0, 'type' => 1, 'name' => ''],
            $params,
            '',
            $data
        );
        // 确认收货写入redis 7天待提醒
        AfterSaleDeliveryRedis::instance()->setAfterSaleReturnTimeoutPromptQueue([
            'after_sale_no' => $params['after_sale_no'],
            'update_time'   => time(),
            'type'          => 'receipt_goods_more_than_7_days',
        ]);
        return $this->returnSuccess();
    }

    public function saveDelivery($params)
    {
        $operator = [];

        if (!empty($params['tracking_number'])) {
            $params['tracking_number'] = implode(',', $params['tracking_number']);
        }
        if (!empty($params['pick_up_address'])) {
            $params['pick_up_address'] = json_encode($params['pick_up_address']);
        }

        $afterSale = AfterSaleRepository::instance()->getAfterSaleByNo($params['after_sale_no']);
        if (empty($afterSale)) {
            return $this->returnError(4040101, 'The current after-sales status does not allow modification of logistics information.');
        }
        $afterSale = AfterSaleRepository::instance()->getAfterSaleByNo($params['after_sale_no']);
        if (!in_array($afterSale['status'], [AfterSaleStatusEnum::WAITING_FOR_DELIVERY_OF_GOODS, AfterSaleStatusEnum::WAITING_FOR_WAREHOUSE_RECEIVE_GOODS])) {
            return $this->returnError(4040102, 'The current after-sales status does not allow modification of logistics information.');
        }

        $afterSaleGoodsData = AfterSaleGoodsRepository::instance()->getListByAfterSaleNo($params['after_sale_no']);
        $skuCode            = array_column($afterSaleGoodsData, 'sku_code');

        $result = AmsApi::instance()->getWarehouseSkuBySkuCode($skuCode);

        $warehouseSkuList = [];
        if ($result['code'] == 1) {
            $warehouseSkuList = $result['data'];
        }

        // 获取客服选中的退货SKU
        $afterSaleGoodsWarehouseSkuList = AfterSaleGoodsWarehouseSkuRepository::instance()->getListByAfterSaleNo($params['after_sale_no']);
        if (!empty($afterSaleGoodsWarehouseSkuList)) {
            $afterSaleGoodsWarehouseSkuList = array_column($afterSaleGoodsWarehouseSkuList, null, 'warehouse_sku_code');
            foreach ($warehouseSkuList as $key => $value) {
                if (!empty($afterSaleGoodsWarehouseSkuList[$value['warehouse_sku_code']])) {
                    continue;
                }
                unset($warehouseSkuList[$key]);
            }
            $warehouseSkuList = array_values($warehouseSkuList);
        }

        $result = DeliveryService::instance()->saveAfterSaleDelivery(array_merge($params, ['sku_content' => @json_encode(['warehouse_sku_list' => $warehouseSkuList])]));

        $afterSaleData = AfterSaleRepository::instance()->getAfterSaleByNo($params['after_sale_no']);
        if ($result['code'] == 1) {
            $updateData = ['status' => AfterSaleStatusEnum::WAITING_FOR_WAREHOUSE_RECEIVE_GOODS, 'updated_at' => time()];
            // 扭转状态
            AfterSaleRepository::instance()->updateData(['after_sale_no' => $params['after_sale_no']], $updateData);
            // 插入日志
            AfterSaleLogRepository::instance()->insertLog(
                $params['after_sale_no'],
                $afterSaleData['status'],
                AfterSaleStatusEnum::WAITING_FOR_WAREHOUSE_RECEIVE_GOODS,
                '',
                [],
                ['id' => 0, 'type' => 1, 'name' => ''],
                $params,
                '',
                $updateData
            );
            // 填写成功回调ams物流接口
            RabbitMq::sendCommonMq('http://aftersale.internal.bm.com/internal/refund/ams-return-order-update', [
                'after_sale_no' => $params['after_sale_no'],
                'operator'      => ['id' => 0, 'type' => 1, 'name' => '用户填写物流']
            ], 1);
            // 确认收货写入redis 7天待提醒
            AfterSaleDeliveryRedis::instance()->setAfterSaleReturnTimeoutPromptQueue([
                'after_sale_no' => $params['after_sale_no'],
                'update_time'   => time(),
                'type'          => 'receipt_goods_more_than_7_days',
            ]);

        } else {
            // 插入日志
            AfterSaleLogRepository::instance()->insertLog(
                $params['after_sale_no'],
                $afterSaleData['status'],
                $afterSaleData['status'],
                '',
                [],
                ['id' => 0, 'type' => 1, 'name' => ''],
                $params,
                '保存物流信息失败：' . $result['info']
            );
        }

        return $this->returnSuccess();
    }

    /**
     * @param $afterSaleNo
     * @param $remark
     *
     * @return array
     */
    public function updateCustomerServiceRemark($afterSaleNo, $remark, $operator, $updateField = 'customer_service_remark')
    {
        $afterSale = AfterSaleRepository::instance()->getAfterSaleByNo($afterSaleNo);
        if (empty($afterSale)) {
            return $this->returnError(4001, '售后单不存在');
        }
        if ($updateField == 'receipt_remark') {
            $update = [
                'receipt_remark' => $remark,
            ];
        } else {
            $update = [
                'customer_service_remark' => $remark,
            ];
        }
        AfterSaleRepository::instance()->updateData(['after_sale_no' => $afterSaleNo], $update);
        // 插入日志
        AfterSaleLogRepository::instance()->insertLog(
            $afterSaleNo,
            $afterSale['status'],
            $afterSale['status'],
            $updateField == 'receipt_remark' ? 9003 : 9002,
            ['old_remark' => $afterSale[$updateField == 'receipt_remark' ? 'receipt_remark' : 'customer_service_remark'], 'remark' => $remark],
            $operator,
            [],
            '',
            $update
        );
        return $this->returnSuccess();
    }

    /**
     * 计算当前商品可退积分与需扣除奖励积分（根据当前的售后退款金额比例
     *
     * @param        $orderNo            [订单号]
     * @param        $orderGoodsData     [当前的订单商品列表]
     * @param        $applyGoodsListData [申请的售后商品列表]
     * @param        $refundAmountSplit  [当前售后操作摊完的金额]
     * @param string $afterSaleNo        [当前申请的售后单号，可为空]
     */
    public function calculateAfterSalePoints($orderNo, $orderGoodsData, $applyGoodsListData, $refundAmountSplit, string $afterSaleNo = '')
    {
        $returnData     = [];
        $applyGoodsList = array_column($applyGoodsListData, null, 'order_goods_id');

        // 取当前订单商品可退总积分与可扣总积分
        foreach ($orderGoodsData as $orderGoods) {
            $applyGoods = $applyGoodsList[$orderGoods['order_goods_id']] ?? [];
            if (empty($applyGoods)) {
                continue;
            }

            if ($applyGoods['qty'] == -1) {
                $applyGoods['qty'] = $orderGoods['qty'];
            }

            // 当商品未收货的时候，这个值为0
            $receivePoints = 0;
            if ($orderGoods['is_received'] == 1) {
                $receivePoints = $orderGoods['receive_points'];
            }

            $returnData[$orderGoods['order_goods_id']] = [
                'can_qty'           => $orderGoods['qty'],
                'apply_qty'         => $applyGoodsList[$orderGoods['order_goods_id']]['qty'],
                'can_points'        => $orderGoods['points'],
                'can_deduct_points' => $receivePoints,
                'apply_points'      => $orderGoods['points'],
            ];

        }

        // 取出所有的售后单
        $effectiveGoodsList = AfterSaleGoodsRepository::instance()->getEffectiveGoodsList(
            $orderNo,
            array_column($applyGoodsListData, 'order_goods_id'),
            $afterSaleNo
        );

        // 做扣减（金额单独累计，供积分「累计取整再求差」收口余数）
        // 纯积分0元售后单退款金额为0，按退还积分>0同样计入历史，避免重复退分；件数单独累计供数量比例收口
        $alreadyRefundedAmount = [];
        $alreadyRefundedQty    = [];
        if (!empty($effectiveGoodsList)) {
            // 做减法
            foreach ($effectiveGoodsList as $v) {
                if (($v['refund_amount'] > 0 || $v['refund_points'] > 0) && $v['refund_qty'] > 0) {
                    $returnData[$v['order_goods_id']]['can_qty']           = $returnData[$v['order_goods_id']]['can_qty'] - $v['refund_qty'];
                    $returnData[$v['order_goods_id']]['can_points']        = bcsub($returnData[$v['order_goods_id']]['can_points'], $v['refund_points']);
                    $returnData[$v['order_goods_id']]['can_deduct_points'] = bcsub($returnData[$v['order_goods_id']]['can_deduct_points'], $v['deduct_points']);
                    $alreadyRefundedAmount[$v['order_goods_id']]           = bcadd($alreadyRefundedAmount[$v['order_goods_id']] ?? '0', $v['refund_amount'], 2);
                    $alreadyRefundedQty[$v['order_goods_id']]              = ($alreadyRefundedQty[$v['order_goods_id']] ?? 0) + $v['refund_qty'];
                }
            }
        }

        // 计算可扣减比例
        foreach ($returnData as $orderGoodsId => $datum) {
            $refundAmount = $refundAmountSplit[$orderGoodsId] ?? 0;
            if ($datum['apply_qty'] > $datum['can_qty']) {
                $returnData[$orderGoodsId]['apply_qty'] = $datum['can_qty'];
            }
            $applyDeductPoints = 0;
            // 实付金额
            $payAmount = $orderGoodsData[$orderGoodsId]['pay_amount'];
            // 增值服务金额
            $serviceAmount = $orderGoodsData[$orderGoodsId]['value_added_service_amount'];
            // 只有大于0扣减操作需要做计算
            if ($datum['can_deduct_points'] > 0) {
                // 奖励分
                $receivePoints     = $orderGoodsData[$orderGoodsId]['receive_points'];
                $applyDeductPoints = $this->calculateCanDeductPoints($receivePoints, $payAmount, $serviceAmount, $orderGoodsData[$orderGoodsId]['rate_fee'] ?? 0, $refundAmount);
            }
            $applyPoints = 0;
            // 当可退的积分大于0，则进行计算
            if ($datum['can_points'] > 0) {
                if (bccomp((string)$payAmount, '0', 2) <= 0) {
                    // 纯积分行（实付0元）：金额比例公式失效，按申请件数比例退还
                    $applyPoints = AfterSalePointsCalculator::calculateCanPointsByQty(
                        $orderGoodsData[$orderGoodsId]['points'],
                        $datum['apply_qty'],
                        $orderGoodsData[$orderGoodsId]['qty'],
                        $alreadyRefundedQty[$orderGoodsId] ?? 0
                    );
                } else {
                    $applyPoints = $this->calculateCanPoints(
                        $orderGoodsData[$orderGoodsId]['points'],
                        $orderGoodsData[$orderGoodsId]['points_discount'],
                        $payAmount,
                        $serviceAmount,
                        $refundAmount,
                        $alreadyRefundedAmount[$orderGoodsId] ?? 0
                    );
                }
                $applyPoints = (int)min((int)$applyPoints, (int)$datum['can_points']);
            }
            $returnData[$orderGoodsId]['apply_deduct_points'] = $applyDeductPoints;
            $returnData[$orderGoodsId]['apply_points']        = $applyPoints;
        }
        return $returnData;
    }

    /**
     * 统一计算根据当前退款的金额，获取实际要扣的奖励积分
     * （申请退款金额/(支付金额-服务金额)）*积分数
     *
     * @param $receivePoints 收到积分
     * @param $payAmount     支付金额
     * @param $serviceAmount 服务金额
     * @param $serviceAmount 税费
     * @param $refundAmount  申请的退款金额
     *
     * @return string
     */
    public function calculateCanDeductPoints($receivePoints, $payAmount, $serviceAmount, $taxAmount, $refundAmount)
    {
        if ($payAmount == $refundAmount) {
            return $receivePoints;
        }
        return (int)bcmul(bcdiv($refundAmount, $payAmount, 2), $receivePoints);
    }

    /**
     * 可退多少分
     *
     * @param $points
     * @param $pointsDiscount
     * @param $payAmount
     * @param $serviceAmount
     * @param $refundAmount
     *
     * @return string
     */
    public function calculateCanPoints($points, $pointsDiscount, $payAmount, $serviceAmount, $refundAmount, $alreadyRefundedAmount = 0)
    {
        return AfterSalePointsCalculator::calculateCanPoints(
            $points,
            $pointsDiscount,
            $payAmount,
            $serviceAmount,
            $refundAmount,
            $alreadyRefundedAmount
        );
    }

    /**
     * 纯积分行（实付0元）按数量比例计算可退多少分
     *
     * @param int|string $points             商品行抵扣积分数
     * @param int|string $applyQty           本次申请件数
     * @param int|string $orderGoodsQty      商品行购买件数
     * @param int|string $alreadyRefundedQty 历史有效售后已退件数
     *
     * @return int
     */
    public function calculateCanPointsByQty($points, $applyQty, $orderGoodsQty, $alreadyRefundedQty = 0)
    {
        return AfterSalePointsCalculator::calculateCanPointsByQty(
            $points,
            $applyQty,
            $orderGoodsQty,
            $alreadyRefundedQty
        );
    }

    public function afterSaleNotifyOrderModifiyProtection($params, $handelResult)
    {
        g_log_info('afterSaleNotifyOrderModifiyProtection', '', [$params, $handelResult]);

        $requestData = $params['request_data'];
        if (empty($requestData['operator'])) {
            $requestData['operator'] = ['type' => '3', 'id' => 0, 'name' => 'Protection取消单通知'];
        }

        $afterSale          = AfterSaleRepository::instance()->getAfterSaleByNo($requestData['after_sale_no']);
        $afterSaleList      = AfterSaleRepository::instance()->getListByOrderNo($afterSale['order_no'], ['after_sale_no']);
        $afterSaleNos       = array_column($afterSaleList, 'after_sale_no');
        $afterSaleGoodsList = AfterSaleGoodsRepository::instance()->getListByAfterSaleNos($afterSaleNos, ['order_goods_id', 'sku_id', 'refund_protection_plan_apply_content', 'refund_protection_plan_cancel_content']);
        $handelGoodsList    = [];
        foreach ($afterSaleGoodsList as $afterSaleGoods) {
            $refundProtection = $afterSaleGoods['refund_protection_plan_apply_content'];
            if (empty($refundProtection)) {
                continue;
            }
            $protection = json_decode($refundProtection, 1);
            $qty        = $protection['qty'] ?? ($protection['can_qty'] ?? 0);
            if (empty($handelGoodsList[$afterSaleGoods['order_goods_id']])) {
                $handelGoodsList[$afterSaleGoods['order_goods_id']] = [
                    'order_goods_id' => $afterSaleGoods['order_goods_id'],
                    'sku_id'         => $afterSaleGoods['sku_id'],
                    'cancel_qty'     => $qty,
                ];
            } else {
                $handelGoodsList[$afterSaleGoods['order_goods_id']]['cancel_qty'] += $qty;
            }
        }
        $postOrderData = [
            'action'           => 'CancelProtection',
            'order_no'         => $afterSale['order_no'],
            'order_goods_list' => $handelGoodsList,
            'operator'         => $requestData['operator']
        ];
        $result        = OrderInternal::instance()->modifyOrderGoodsProtectionPlan($postOrderData);
        $logResult     = AfterSaleLogRepository::instance()->insertLog(
            $afterSale['after_sale_no'],
            $afterSale['status'],
            $afterSale['status'],
            9213,
            ['supplement' => json_encode(['notify_order' => [$result, $postOrderData], 'protection_cancel' => [$handelResult, $params]], 320)],
            $requestData['operator'] ?? []
        );
        return [$result, $logResult];
    }


    /**
     * 售后状态变化处理
     *
     * @param string $afterSaleNo MQ推送售后单号
     * @param int    $status      MQ推送的售后状态
     * @param string $action      售后动作，apply:提交，cancel：取消，done：完成
     * @param array  $operator    操作人
     *
     * @return array
     */
    public function statusChangeHandle($afterSaleNo, $status, $action, $operator)
    {
        $start     = $this->getMillisecond();
        $afterSale = AfterSaleRepository::instance()->getAfterSaleByNo($afterSaleNo);
        list($afterSale, $afterSaleGoods, $orderInfo, $orderAmount, $orderGoodsList, $orderAddress, $exchangeGoods,
            $afterShipList, $userInfo, $paymentInfo, $orderGoodsValueAddedServiceList, $orderGoodsProtectionPlanList,
            $exchangeGoodsValueAddedServiceList, $exchangeGoodsProtectionPlanList, $exchangeOrderInfo) = $this->getAfterSaleRelateData($afterSale);
        switch ($status) {
            case AfterSaleStatusEnum::DONE:
                EstimateTaxService::instance()->commitTransactionMqSend($afterSale['after_sale_no']);
                //次日达自动取消，发送邮件
                if (AfterSaleProcessTypeEnum::RECEIPT_METHOD == $afterSale['processing_type']) {
                    (new ModifyReceiptMethodService(['after_sale' => $afterSale, 'operator' => $operator]))->sendNextDayCancelEmail();
                }
                //判断是不是修改地址，要退优惠券
                $this->checkRollbackCouponUser($afterSale);
                //根据售后退款是否需要取消plus会员
                try {
                    $enqueueResult = (new \common\services\member\MemberAfterSaleService())->enqueueCancelPlusMember($afterSale, $afterSaleGoods, $orderGoodsList);
                    g_log_info('premium_plus_cancel', '取消plus会员', ['after_sale_no' => $afterSale['after_sale_no'], 'result' => $enqueueResult]);
                } catch (\Exception $e) {
                    g_log_error('premium_plus_cancel', 'after_sale_done_hook', [
                        'after_sale_no' => $afterSaleNo,
                        'error'         => $e->getMessage(),
                    ]);
                }
                try {
                    $benefitResult = (new \common\services\member\MemberAfterSaleService())->enqueueCancelRedeemedBenefitsIfNeeded(
                        $afterSale,
                        is_array($afterSaleGoods) ? $afterSaleGoods : [],
                        is_array($orderGoodsList) ? $orderGoodsList : [],
                        is_array($orderInfo) ? $orderInfo : [],
                        is_array($orderGoodsValueAddedServiceList) ? $orderGoodsValueAddedServiceList : []
                    );
                    g_log_info('premium_benefit_cancel', '取消核销服务', ['after_sale_no' => $afterSale['after_sale_no'], 'result' => $benefitResult]);
                } catch (\Exception $e) {
                    g_log_error('premium_benefit_cancel', 'after_sale_done_hook', [
                        'after_sale_no' => $afterSaleNo,
                        'error'         => $e->getMessage(),
                    ]);
                }
                try {
                    $countryCode  = is_array($orderAddress) ? (string)($orderAddress['country_code'] ?? '') : '';
                    $redeemResult = (new \common\services\member\MemberAfterSaleService())->enqueueRedeemBenefitsIfNeeded(
                        $afterSale,
                        is_array($orderInfo) ? $orderInfo : [],
                        $countryCode,
                        is_array($afterSaleGoods) ? $afterSaleGoods : [],
                        is_array($orderGoodsList) ? $orderGoodsList : [],
                        is_array($orderGoodsValueAddedServiceList) ? $orderGoodsValueAddedServiceList : []
                    );
                    g_log_info('premium_benefit_redeem', '核销服务', ['after_sale_no' => $afterSale['after_sale_no'], 'result' => $redeemResult]);
                } catch (\Exception $e) {
                    g_log_error('premium_benefit_redeem', 'after_sale_done_hook', [
                        'after_sale_no' => $afterSaleNo,
                        'error'         => $e->getMessage(),
                    ]);
                }

                break;
            case AfterSaleStatusEnum::CANCEL:
                // 好评返现（其他退款 + compensation_type=7）售后取消通知
                $this->notifyTargetReviewCashbackIfNeeded($afterSale, 2, '售后单已取消');
                break;
        }
        $end = $this->getMillisecond();
        g_log_info('statusChangeHandle.log', '售后相关数据收集成功,耗时:' . ($end - $start) . 'ms', []);

        //上报售后埋点
        $this->pushSensorEvent($action, $afterSale, $afterSaleGoods, $orderInfo, $orderAmount, $orderGoodsList, $orderAddress,
            $userInfo, $exchangeGoods, $afterShipList, $paymentInfo, $orderGoodsValueAddedServiceList, $orderGoodsProtectionPlanList,
            $exchangeGoodsValueAddedServiceList, $exchangeGoodsProtectionPlanList, $exchangeOrderInfo);
        return $this->returnSuccess();
    }

    /**
     * 售后完成，检查时候需要回退用户优惠券.
     *
     * @param $afterSale
     */
    public function checkRollbackCouponUser($afterSale)
    {
        if ($afterSale['processing_type'] == AfterSaleProcessTypeEnum::MODIFY_ORDER_INFORMATION || $afterSale['processing_type'] == AfterSaleProcessTypeEnum::RECEIPT_METHOD) {
            $processingContent = json_decode($afterSale['processing_content'], 1);
            if (!empty($processingContent['new_address_fee_info'])) {
                $rollbackCouponUser = $processingContent['new_address_fee_info']['rollback_coupon_user'] ?? 0;
                if (!empty($rollbackCouponUser)) {
                    $result = MarketInternal::instance()->rollbackOrderCouponUser($afterSale['order_no']);
                    g_log_info('statusChangeHandle', '修改地址售后完成退用户优惠券', ['order_no' => $afterSale['order_no'], 'result' => $result]);
                }
            }
        }
    }

    /**
     * 好评返现售后结果通知（其他退款 + compensation_type=7）
     *
     * @param array  $afterSale
     * @param int    $status     1-成功，2-失败
     * @param string $failReason 失败原因
     *
     * @return void
     */
    private function notifyTargetReviewCashbackIfNeeded($afterSale, $status, $failReason = '')
    {
        if (($afterSale['processing_type'] ?? 0) != AfterSaleProcessTypeEnum::COMPENSATION) {
            return;
        }
        $processingContent = json_decode($afterSale['processing_content'] ?? '', true);
        if (!is_array($processingContent)) {
            $processingContent = [];
        }
        if ((int)($processingContent['compensation_type'] ?? 0) !== AfterSaleCompensationTypeEnum::POSITIVE_REVIEW_CASHBACK) {
            return;
        }
        try {
            OperateInternal::instance()->notifyTargetReviewCashback(
                $afterSale['after_sale_no'] ?? '',
                (int)$status,
                (string)$failReason
            );
        } catch (\Exception $e) {
            g_log_error('notifyTargetReviewCashback', '好评返现售后结果通知失败', [
                'after_sale_no' => $afterSale['after_sale_no'] ?? '',
                'status'        => $status,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    /**
     * 获取换货单信息
     *
     */
    private function getExchangeOrderInfo($orderInfo, $orderAddress, $afterSaleNo, $processingType, $exchangeOrderNo)
    {
        $exchangeOrderInfo = [
            'order_info'               => null,
            'order_goods_list'         => null,
            'order_goods_protection'   => null,
            'value_added_service_list' => null,
        ];
        if (AfterSaleProcessTypeEnum::EXCHANGE_GOODS != $processingType) {
            return $exchangeOrderInfo;
        }
        if (!empty($exchangeOrderNo)) {
            $exchangeOrderResult = OrderInternal::instance()->getAllDataByOrderNo($exchangeOrderNo, 'required', 'required', null,
                null, null, null, null, null, 'required',
                null, 'required');
            if (empty($exchangeOrderResult['data'])) {
                return $exchangeOrderInfo;
            }
            return [
                'order_info'               => array_merge($exchangeOrderResult['data']['order'], $exchangeOrderResult['data']['order_amount']),
                'order_goods_list'         => $exchangeOrderResult['data']['order_goods_list'],
                'order_goods_protection'   => $exchangeOrderResult['data']['order_goods_protection'],
                'value_added_service_list' => $exchangeOrderResult['data']['value_added_service_list'],
            ];
        }
        $baseExchangeGoods = AfterSaleExchangeGoodsRepository::instance()->getListByNo($afterSaleNo, ['sku_id', 'qty']);
        $skuResult         = GoodsInternal::instance()->getSkuList(array_column($baseExchangeGoods, 'sku_id'),
            $orderInfo['site'], $orderInfo['language'], $orderInfo['currency'], $orderAddress['country_code'],
            $orderAddress['state_code'], $orderAddress['city'], $orderAddress['zip']);
        $skuList           = $skuResult['data'] ?? [];
        if (empty($skuList)) {
            return $exchangeOrderInfo;
        }
        $goodList          = [];
        $baseExchangeGoods = array_column($baseExchangeGoods, null, 'sku_id');
        foreach ($skuList as $sku) {
            $goodList[] = [
                'spu_category_name_first'  => $sku['spu_category_name_first'],
                'spu_category_name_second' => $sku['spu_category_name_second'],
                'spu_category_name_third'  => $sku['spu_category_name_third'],
                'spu_category_name_fourth' => $sku['spu_category_name_fourth'],
                'spu_title'                => $sku['spu_title'],
                'sku_id'                   => $sku['sku_id'],
                'sku_code'                 => $sku['sku_code'],
                'qty'                      => $baseExchangeGoods[$sku['sku_id']]['qty'] ?? 0,
                'goods_price'              => $sku['price'],
                'market_price'             => $sku['tag_price'],
                'estimated_arrival_at'     => $sku['delivery_time_str'],
                'sale_attr_group'          => $sku['sale_attr_text_group'],
            ];
        }
        $exchangeOrderInfo['order_goods_list'] = $goodList;
        return $exchangeOrderInfo;
    }


    /**
     * 通过售后单号获取上报信息并上报事件
     *
     * @param string $event  事件名
     * @param array  $params 参数集合
     *
     * @return array
     * @throws \Exception
     */
    public function pushAfterSaleEvent($event, $params)
    {
        $afterSaleNo = $params['after_sale_no'];
        $action      = null;
        switch ($event) {
            case SensorsEvent::EVENT_USER_APPLY_AFTER_SALE_SUCCESS_DETAILS:
                $action = AfterSaleActionEnum::ACTION_CLIENT_APPLY;
                break;
            case SensorsEvent::EVENT_REFUND_SUCCESS_DETAIL:
                $action = AfterSaleActionEnum::ACTION_REFUNDED;
                break;
            default:
                if (!empty($params['action'])) {
                    $action = $params['action'];
                }
                break;
        }
        $afterSale = AfterSaleRepository::instance()->getAfterSaleByNo($afterSaleNo);

        list($afterSale, $afterSaleGoods, $orderInfo, $orderAmount, $orderGoodsList, $orderAddress, $exchangeGoods,
            $afterShipList, $userInfo, $paymentInfo, $orderGoodsValueAddedServiceList, $orderGoodsProtectionPlanList,
            $exchangeGoodsValueAddedServiceList, $exchangeGoodsProtectionPlanList, $exchangeOrderInfo
            ) = $this->getAfterSaleRelateData($afterSale);
        //上报神策售后埋点
        $saResult = $this->pushSensorEvent($action, $afterSale, $afterSaleGoods, $orderInfo, $orderAmount, $orderGoodsList, $orderAddress,
            $userInfo, $exchangeGoods, $afterShipList, $paymentInfo, $params, $orderGoodsValueAddedServiceList, $orderGoodsProtectionPlanList,
            $exchangeGoodsValueAddedServiceList, $exchangeGoodsProtectionPlanList, $exchangeOrderInfo
        );
        if (AfterSaleActionEnum::ACTION_REFUNDED == $action) {
            //退款成功事件同时上报GA4
            Ga4Service::instance()->pushRefundEvent($afterSale, $afterSaleGoods, $orderInfo, $orderGoodsList);
        }
        return $saResult;
    }

    /**
     * 通过售后单号获取相关数据
     *
     * @param array $afterSale 售后单
     *
     * @return array
     * @throws \Exception
     */
    private function getAfterSaleRelateData($afterSale, $afterSaleGoods = [])
    {
        $afterSaleNo = $afterSale['after_sale_no'];
        if (empty($afterSaleGoods)) {
            $afterSaleGoods = AfterSaleGoodsRepository::instance()->getListByAfterSaleNo($afterSaleNo);
        }

        $orderResult = OrderInternal::instance()->getAllDataByOrderNo($afterSale['order_no'], 'required', 'required', 'required',
            null, null, null, null, null, 'required',
            null, 'required', 'required');

        $orderInfo      = $orderResult['data']['order'];
        $orderAmount    = $orderResult['data']['order_amount'];
        $orderGoodsList = $orderResult['data']['order_goods_list'];
        $orderAddress   = $orderResult['data']['order_address'];
        $afterShipList  = $orderResult['data']['aftership_list'];

        //用户信息
        $userInfo = UserInternal::instance()->getUserById($orderInfo['user_id']);

        //换货商品信息
        $exchangeOrderInfo = $this->getExchangeOrderInfo($orderInfo, $orderAddress, $afterSaleNo, $afterSale['processing_type'],
            $afterSale['exchange_order_no']); //成单后，商品详细信息

        //支付单信息
        $paymentResult = PayInternal::instance()->getPayment($orderInfo['payment_no']);
        $paymentInfo   = $paymentResult['data'] ?? [];

        return [$afterSale, $afterSaleGoods, $orderInfo, $orderAmount, $orderGoodsList, $orderAddress,
            $exchangeOrderInfo['order_goods_list'], $afterShipList, $userInfo, $paymentInfo, $orderResult['data']['value_added_service_list'],
            $orderResult['data']['order_goods_protection'], $exchangeOrderInfo['value_added_service_list'], $exchangeOrderInfo['order_goods_protection'],
            $exchangeOrderInfo['order_info']
        ];
    }

    /**
     * 上报神策事件
     *
     * @param string $action         售后动作，apply:提交，cancel：取消，done：完成
     * @param array  $afterSale      售后单
     * @param array  $afterSaleGoods 售后商品
     * @param array  $orderInfo      订单
     * @param array  $orderAmount    订单金额信息
     * @param array  $orderGoodsList 订单商品
     * @param        $orderAddress
     * @param array  $userInfo       用户信息
     * @param array  $exchangeGoods  换货信息
     * @param array  $afterShipList  物流信息
     * @param array  $paymentInfo    支付单信息
     * @param array  $params         其他额外的公共参数
     *
     * @return array
     */
    private function pushSensorEvent($action, $afterSale, $afterSaleGoods, $orderInfo, $orderAmount, $orderGoodsList, $orderAddress, $userInfo = [], $exchangeGoods = [],
                                     $afterShipList = [], $paymentInfo = [], $params = [], $orderGoodsValueAddedServiceList = [], $orderGoodsProtectionPlanList = [],
                                     $exchangeGoodsValueAddedServiceList = [], $exchangeGoodsProtectionPlanList = [], $exchangeOrderInfo = [])
    {
        //设置为中国时区
        date_default_timezone_set('PRC');
        $saInstance = SensorsService::instance();
        $saInstance->setParams($afterSale, $afterSaleGoods, array_merge($orderInfo, $orderAmount), $orderGoodsList, $orderAddress, $userInfo, $exchangeGoods,
            $afterShipList, $paymentInfo, $orderGoodsValueAddedServiceList, $orderGoodsProtectionPlanList, $exchangeGoodsValueAddedServiceList,
            $exchangeGoodsProtectionPlanList, $exchangeOrderInfo);
        //获取上报的公共售后信息
        $saBaseParams = $saInstance->getAfterSaleCommonParams(true, $params);
        g_log_info('pushSensorEvent', 'getAfterSaleCommonParams获取成功', $saBaseParams);
        $saInstance->setAfterSaleCommonParams($saBaseParams);
        //上报售后事件
        $processingContent = @json_decode($afterSale['processing_content'], true);
        $eventParamsList   = $saInstance->getSpecifySaEventParams($action, $afterSale['processing_type'], $processingContent, $afterSale['status']);

        g_log_info('pushSensorEvent', '售后事件组装成功', $eventParamsList);

        if (empty($eventParamsList)) {
            return $this->returnError(1994013, '无售后事件数据');
        }
        $saInstance->pushEvent($eventParamsList);
        return $this->returnSuccess();
    }

    /**
     * 获取订单的每个商品的可售后的金额，数量 order_goods_id为索引
     *
     * @param       $orderNo
     * @param array $orderGoodsList 售后商品列表
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    public function getCanApplyAfterSaleGoodsList($orderNo, $orderGoodsList)
    {
        $doneRecords = $this->getDoneRecordGroupByOrderGoodsId($orderNo, $orderGoodsList);

        //格式化
        $returnData = [];
        foreach ($orderGoodsList as $orderGoods) {
            if (empty($doneRecords[$orderGoods['order_goods_id']])) {
                $returnData[$orderGoods['order_goods_id']] = [
                    'order_goods_id' => $orderGoods['order_goods_id'],
                    'qty'            => $orderGoods['qty'],
                    'amount'         => $orderGoods['pay_amount'],
                    'goods_type'     => (int)($orderGoods['goods_type'] ?? 1),
                    'is_member_goods'=> (int)($orderGoods['is_member_goods'] ?? 0),
                    'allowed_solutions' => \common\services\member\MemberAfterSaleService::isPremiumMemberGoods($orderGoods)
                        ? ['other_refund', 'tax_refund']
                        : [],
                ];
            } else {
                $qty    = $orderGoods['qty'] - $doneRecords[$orderGoods['order_goods_id']]['qty'] ?? 0;
                $amount = bcsub($orderGoods['pay_amount'], $doneRecords[$orderGoods['order_goods_id']]['amount'] ?? 0, 2);
                if ($qty <= 0) {
                    $amount = 0;
                }
                $returnData[$orderGoods['order_goods_id']] = [
                    'order_goods_id' => $orderGoods['order_goods_id'],
                    'qty'            => max($qty, 0),
                    'amount'         => $amount,
                    'goods_type'     => (int)($orderGoods['goods_type'] ?? 1),
                    'is_member_goods'=> (int)($orderGoods['is_member_goods'] ?? 0),
                    'allowed_solutions' => \common\services\member\MemberAfterSaleService::isPremiumMemberGoods($orderGoods)
                        ? ['other_refund', 'tax_refund']
                        : [],
                ];
            }
        }
        return $returnData;
    }

    /**
     * 获取订单每个商品的已售后金额,数量
     *
     * @param $orderNo
     * @param $orderGoodsList
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    public function getDoneRecordGroupByOrderGoodsId($orderNo, $orderGoodsList)
    {
        $afterSaleList = AfterSaleRepository::instance()->getEffectiveByOrderNo($orderNo, ['after_sale_no', 'type', 'apply_amount', 'amount']);
        if (empty($afterSaleList)) {
            return [];
        }
        $orderGoodsList = array_column($orderGoodsList, null, 'order_goods_id');

        $aftersaleGoodsList = AfterSaleGoodsRepository::instance()->getListByAfterSaleNos(array_column($afterSaleList, 'after_sale_no'), ['after_sale_no', 'order_goods_id', 'qty', 'amount']);
        $doneRecords        = [];
        foreach ($afterSaleList as $aftersale) {
            //有整单退款的售后单，把所有商品的的支付金额当成已售后金额返回。
            if ($aftersale['type'] == AfterSaleRepository::TYPE_UNDELIVERED_REFUND) {
                foreach ($orderGoodsList as $orderGoodsId => $v) {
                    $doneRecords[$orderGoodsId] = [
                        'order_goods_id' => $orderGoodsId,
                        'qty'            => $orderGoodsList[$orderGoodsId]['qty'],
                        'amount'         => $orderGoodsList[$orderGoodsId]['pay_amount'],
                    ];
                }
                return $doneRecords;
            }
            //仅退款，退货退款的取售后商品上的amount
            if (in_array($aftersale['type'], [AfterSaleRepository::TYPE_ONLY_REFUND, AfterSaleRepository::TYPE_RETURN_GOODS_REFUND])) {
                foreach ($aftersaleGoodsList as $aftersaleGoods) {
                    if ($aftersaleGoods['after_sale_no'] == $aftersale['after_sale_no']) {
                        if (isset($doneRecords[$aftersaleGoods['order_goods_id']])) {
                            $doneRecords[$aftersaleGoods['order_goods_id']]['qty']    += $aftersaleGoods['qty'];
                            $doneRecords[$aftersaleGoods['order_goods_id']]['amount'] = bcadd($doneRecords[$aftersaleGoods['order_goods_id']]['amount'], $aftersaleGoods['amount'], 2);
                        } else {
                            $doneRecords[$aftersaleGoods['order_goods_id']] = [
                                'order_goods_id' => $aftersaleGoods['order_goods_id'],
                                'qty'            => $aftersaleGoods['qty'],
                                'amount'         => $aftersaleGoods['amount'],
                            ];
                        }
                    }
                }
            }
            // 换货的，整个商品的价值，作为售后金额
            if ($aftersale['type'] == AfterSaleRepository::TYPE_EXCHANGE_GOODS) {
                foreach ($aftersaleGoodsList as $aftersaleGoods) {
                    if ($aftersaleGoods['after_sale_no'] == $aftersale['after_sale_no']) {
                        $doneRecords[$aftersaleGoods['order_goods_id']] = [
                            'order_goods_id' => $aftersaleGoods['order_goods_id'],
                            'qty'            => $orderGoodsList[$aftersaleGoods['order_goods_id']]['qty'],
                            'amount'         => $orderGoodsList[$aftersaleGoods['order_goods_id']]['pay_amount'],
                        ];
                    }
                }
            }
        }

        //格式化
        $returnData = [];
        foreach ($orderGoodsList as $orderGoodsId => $v) {
            if (!empty($doneRecords[$orderGoodsId])) {
                $returnData[$orderGoodsId] = [
                    'order_goods_id' => $orderGoodsId,
                    'qty'            => $doneRecords[$orderGoodsId]['qty'],
                    'amount'         => $doneRecords[$orderGoodsId]['amount'],
                ];
            } else {
                $doneRecords[$orderGoodsId] = [
                    'order_goods_id' => $orderGoodsId,
                    'qty'            => $orderGoodsList[$orderGoodsId]['qty'],
                    'amount'         => $orderGoodsList[$orderGoodsId]['pay_amount'],
                ];
            }
        }

        return $returnData;
    }

    /**
     * 把客户端的申请数据结构，变成后端的申请数据结构
     *
     * @param $clientParams
     *
     * @return array
     */
    public function convertData($clientParams): array
    {
        $orderNo = $clientParams['order_no'] ?? '';
        // 取出商品的信息
        $orderAllData   = OrderInternal::instance()->getAllDataByOrderNo($orderNo, 'required');
        $orderData      = [];
        $orderGoodsList = [];
        if (isset($orderAllData['code']) && $orderAllData['code'] == 1 && !empty($orderAllData['data'])) {
            $allData        = $orderAllData['data'];
            $orderData      = $allData['order'];
            $orderGoodsList = $allData['order_goods_list'] ?? [];
            $orderGoodsList = array_column($orderGoodsList, null, 'order_goods_id');
        }

        $applyGoodsList = [];
        if (!empty($clientParams['order_goods_list'])) {
            $clientOrderGoodsList = is_string($clientParams['order_goods_list']) ? json_decode($clientParams['order_goods_list'], 1) : $clientParams['order_goods_list'];
            $clientOrderGoodsList = array_column($clientOrderGoodsList, null, 'order_goods_id');
            foreach ($orderGoodsList as $goodsDatum) {
                if (!empty($clientOrderGoodsList[$goodsDatum['order_goods_id']])) {
                    $applyGoodsList[] = [
                        'order_goods_id' => $goodsDatum['order_goods_id'],
                        'qty'            => !empty($clientOrderGoodsList[$goodsDatum['order_goods_id']]['qty']) ? $clientOrderGoodsList[$goodsDatum['order_goods_id']]['qty'] : $goodsDatum['qty'],
                        'sku_code'       => $goodsDatum['sku_code'],
                        'goods_qty'      => $goodsDatum['qty'],
                    ];
                }
            }
        } else {
            foreach ($orderGoodsList as $goodsDatum) {
                // 如果申请的商品ID不为空，则表示申请特定商品，如果ID跟当前商品不一致，则跳过
                if (!empty($clientParams['order_goods_id']) && $goodsDatum['order_goods_id'] != $clientParams['order_goods_id']) {
                    continue;
                }
                $applyGoodsList[] = [
                    'order_goods_id' => $goodsDatum['order_goods_id'],
                    'qty'            => !empty($clientParams['qty']) ? $clientParams['qty'] : $goodsDatum['qty'],
                    'sku_code'       => $goodsDatum['sku_code'],
                    'goods_qty'      => $goodsDatum['qty'],
                ];
            }
        }

        //由code换id，转入到主申请售后里
        $lableList = AfterSaleConsultLabelRepository::instance(true)->getReasonListByReasonCode($clientParams['reason1'] ?? '', $clientParams['reason2'] ?? '', $clientParams['reason3'] ?? '');

        $user   = UserInternal::instance()->getUserByToken($clientParams['token']);
        $params = [
            'flaw_type'                   => '',
            'customer_service_remark'     => '',
            'is_dispute'                  => 0,
            'is_recommend_exchange_email' => 1,
            'return_type'                 => 0,
            'reason1'                     => $lableList[3]['id'] ?? 0,
            'reason2'                     => $lableList[4]['id'] ?? 0,
            'reason3'                     => $lableList[5]['id'] ?? 0,
            'minimum_level_of_reason_id'  => $clientParams['minimum_level_of_reason_id'] ?? 0,
            'modify_content'              => '',
            'handle_action'               => AuditTypeEnum::CLIENT_APPLY,
            'add_source'                  => 1,
            'user_remark'                 => $clientParams['remark'] ?? '',
            'operator'                    => [
                'type' => 1,
                'id'   => $user['id'] ?? 0,
                'name' => '用户申请'
            ],
        ];
        //修改地址
        if ($clientParams['processing_type'] == AfterSaleProcessTypeEnum::MODIFY_ORDER_INFORMATION) {
            $params['modify_content'] = [
                'code'            => "ModifyAddressInfo",
                'is_update_order' => "1",
                'address_info'    => [
                    "first_name"   => $clientParams['first_name'] ?? '',
                    "last_name"    => $clientParams['last_name'] ?? '',
                    "company"      => $clientParams['company'] ?? '',
                    "email"        => $clientParams['email'] ?? '',
                    "phone"        => $clientParams['phone'] ?? '',
                    "fax"          => $clientParams['fax'] ?? '',
                    "country"      => $clientParams['country'] ?? '',
                    "country_code" => $clientParams['country_code'] ?? '',
                    "state"        => $clientParams['state'] ?? '',
                    "state_code"   => $clientParams['state_code'] ?? '',
                    "city"         => $clientParams['city'] ?? '',
                    "area"         => $clientParams['area'] ?? '',
                    "zip"          => $clientParams['zip'] ?? '',
                    "street1"      => $clientParams['street1'] ?? '',
                    "street2"      => $clientParams['street2'] ?? '',
                ]
            ];
        } else {
            // 如果是取消订单的话，走一下计算的方式，取出计算的金额
            //获取后端售后端计算数据
            $calculateParams = [
                'order_no'            => $orderNo,
                'order_data'          => $orderData,
                'order_goods_data'    => $orderGoodsList,
                'order_delivery_data' => [],

                'processing_type'  => $clientParams['processing_type'],
                'apply_goods_list' => json_encode($applyGoodsList),
                'after_sale_no'    => '',
            ];

            $calculateResult = AfterSaleQueryService::instance()->calculateAfterSale($calculateParams);
            if ($calculateResult['code'] == 1 && !empty($calculateResult['data'])) {
                $calculateData                              = $calculateResult['data'];
                $params['apply_refund_amount']              = $calculateData['apply_refund_amount'] ?? 0;
                $params['apply_goods_amount']               = $calculateData['apply_goods_amount'] ?? 0;
                $params['apply_value_added_service_amount'] = $calculateData['apply_value_added_service_amount'] ?? 0;
                $params['refund_protection_amount']         = $calculateData['apply_protection_plan_amount'] ?? 0;
                $params['apply_refund_rate_fee']            = $calculateData['apply_refund_rate_fee'] ?? 0;

                $list = $calculateData['list'] ?? [];
                foreach ($applyGoodsList as &$applyGoods) {
                    $applyGoods['goods_amount']               = $list[$applyGoods['order_goods_id']]['goods_amount'];
                    $applyGoods['protection_plan_amount']     = $list[$applyGoods['order_goods_id']]['protection_plan_amount'];
                    $applyGoods['value_added_service_amount'] = $list[$applyGoods['order_goods_id']]['value_added_service_amount'];
                    $applyGoods['warehouse_sku_list']         = [];
                }
                unset($applyGoods);
            }
        }

        // 前端的部分字段格式化成后端不可缺少的
        $params['apply_goods_list'] = json_encode($applyGoodsList, 320);
        return array_merge($clientParams, $params);
    }

    public function getDelivery($params): array
    {
        $id          = $params['id'] ?? 0;
        $afterSaleNo = $params['after_sale_no'] ?? '';
        $site        = $params['site'] ?? '';
        if (!empty($id)) {
            $afterSaleDelivery = AfterSaleDeliveryRepository::instance()->getInfoById($id);
        }

        // 获取售后
        $afterSale = AfterSaleRepository::instance()->getAfterSaleByNo($afterSaleNo);

        // 优先取$afterSaleDelivery
        if (empty($afterSaleDelivery['pick_up_address'])) {
            // 获取订单地址
            $orderResult  = OrderInternal::instance()->getAllDataByOrderNo($afterSale['order_no'], null, null, 'required');
            $orderAddress = $orderResult['data']['order_address'];
        } else {
            $orderAddress = json_decode($afterSaleDelivery['pick_up_address'], 1);
        }

        // 物流方式说明
        $returnMethodList      = ReturnMethodEnum::RETURN_METHOD;
        $data['return_method'] = $returnMethodList;

        $trackingCompanyList = TrackingCompany::$list;
        foreach ($trackingCompanyList as &$item) {
            $item['code'] = strtoupper($item['name']);
        }
        unset($item);
        $data['tracking_company_list'] = array_values($trackingCompanyList);
        // 默认显示收件人信息，可修改
        $data['pick_up_address'] = $orderAddress;
        // 退货地址（创建售后单时选择的仓库退货地址）
        $data['return_address'] = $this->getReturnAddress($afterSale['warehouse_type']);
        // 提示说明 --
        $trial = AfterSaleLogisticsTrialCalculationRepository::instance()->getLastCalculationFreight($afterSale['after_sale_no']);
        if (empty($trial)) {
            $data['estimated_shipping_cost'] = null;
        } else {
            $data['estimated_shipping_cost'] = [
                'amount'      => ConfigService::instance()->getAmountFormat($trial['amount'], $trial['currency_code'], $site),
                'description' => '(Will be deducted from the refund amount)',
            ];
        }

        $data['pick_up_time']     = '';
        $data['tracking_number']  = [];
        $data['tracking_company'] = '';
        $data['remark']           = '';
        $data['prove_url']        = [];
        $data['article_url']      = '/help-center/article-detail/27';

        if (!empty($afterSaleDelivery)) {
            $data['pick_up_time'] = $afterSaleDelivery['pick_up_time'];
            if (!empty($afterSaleDelivery['tracking_number'])) {
                $data['tracking_number']       = explode(',', $afterSaleDelivery['tracking_number']);
                $data['tracking_company']      = $afterSaleDelivery['tracking_company'] ?? '';
                $data['tracking_company_code'] = $afterSaleDelivery['tracking_company_code'] ?? '';
            }

            $data['remark'] = $afterSaleDelivery['remark'] ?? '';

            if (!empty($afterSaleDelivery['prove_url'])) {
                $data['prove_url'] = json_decode($afterSaleDelivery['prove_url'], 1);
            }

            foreach ($returnMethodList as &$item) {
                if ($afterSaleDelivery['return_method'] == $item['id']) {
                    $item['is_select'] = 1;
                } else {
                    $item['is_select'] = 0;
                }
            }
            $data['return_method'] = $returnMethodList;
        }

        return $this->returnSuccess($data);
    }

    private function getReturnAddress($warehouseType)
    {
        if (empty($warehouseType)) {
            return [];
        }
        // 获取仓库的信息
        $warehouseList = AfterSaleRepository::instance()->getWarehouseList();
        $warehouseList = array_column($warehouseList, null, 'id');
        $warehouseInfo = $warehouseList[$warehouseType] ?? [];
        $title         = \Yii::t('tag', 'Return Address');
        $returnAddress = [
            'title'          => $title,
            'contact_person' => $warehouseInfo['contact_person'],
            'phone'          => $warehouseInfo['phone'],
            'return_address' => $warehouseInfo['address'],
        ];
        return $returnAddress;
    }

    public function getTrackInfo($params): array
    {
        $trackingNumber = $params['tracking_number'] ?? '';
        if (empty($trackingNumber)) {
            return $this->returnError(44444, 'tracking number is null');
        }
        $afterSaleData      = AfterSaleRepository::instance()->getAfterSaleByNo($params['after_sale_no']);
        $afterSaleGoodsData = AfterSaleGoodsRepository::instance()->getListByAfterSaleNo($params['after_sale_no']);

        $redisData = AfterSaleDeliveryRedis::instance()->getAfterSaleDelivery($trackingNumber);

        $nodeList         = [];
        $viewDetails      = [];
        $contact          = [];
        $logisticsCompany = '';
        if (!empty($redisData)) {
            $data           = json_decode($redisData, 1);
            $trajectoryList = $data['trajectory_list'] ?? [];
            foreach ($trajectoryList as $list) {
                $nodeList[] = [
                    'content'    => $list['node_desc'],
                    'created_at' => $list['node_time_str'],
                ];
            }

            $logisticsCompany = $redisData['logistics_company'] ?? '';
            if (!empty($logisticsCompany)) {
                $viewDetails = [
                    'text' => \Yii::t('tag', 'View Details on') . $logisticsCompany,
                    'link' => TrackingCompany::$list[strtoupper($logisticsCompany)]['site'] ?? '',
                ];
                $contact     = [
                    'text'           => "Contact {$logisticsCompany}",
                    'exception_info' => [
                        'title'       => "Contact {$logisticsCompany} directly to get more details",
                        'description' => 'Call:' . TrackingCompany::$list[strtoupper($logisticsCompany)]['phone']['default'] ?? '',
                    ]
                ];
            }
        }


        return $this->returnSuccess([
            'after_sale_goods_info' => [
                'img'              => $afterSaleGoodsData[0]['img'],
                'tracking_company' => $logisticsCompany,
                'track_number'     => $trackingNumber,
            ],
            'return_address'        => $this->getReturnAddress($afterSaleData['warehouse_type']),
            'node_list'             => $nodeList,
            'view_details'          => $viewDetails,
            'contact'               => $contact,
        ]);
    }

    /**
     * 根据售后单获取售后单详情
     */
    public function getOneByAfterSaleNo($afterSaleNo)
    {
        return AfterSaleRepository::instance()->getOneByAfterSaleNo($afterSaleNo);
    }

    /**
     * 根据订单获取售后单详情
     */
    public function getOneByOrderNo($orderNo, $processingType = 0)
    {
        return AfterSaleRepository::instance()->getOneByOrderNo($orderNo, $processingType);
    }

    /**
     * 根据订单商品判断是否有正在售后的售后单
     *
     * @param int    $orderGoodsId       订单商品ID
     * @param array  $processingTypes    处理方案ID列表
     * @param string $excludeAfterSaleNo 过滤的售后单号 【如剔除当前自身在售后的单号】
     *
     * @return bool
     */
    public function isInAfterSaleByOrderGoodsId($orderGoodsId, $processingTypes = [], $excludeAfterSaleNo = '')
    {
        return AfterSaleRepository::instance()->isInAfterSaleByOrderGoodsId($orderGoodsId, $processingTypes, $excludeAfterSaleNo);
    }

    /**
     * 更新售后单
     *
     * @param string $afterSaleNo    售后单号
     * @param int    $status         售后单状态
     * @param int    $isPaid         是否支付
     * @param int    $paidAt         支付时间
     * @param string $payPaymentNo   支付单号
     * @param string $exchangeOrderNo
     * @param string $reissueOrderNo ams补发单号
     * @param string $reissueOrderId ams补发单ID
     *
     * @return array|ActiveRecord[]
     */
    public function updateAfterSale($afterSaleNo, $status = null, $payPaymentNo = null, $isPaid = null, $paidAt = null, $exchangeOrderNo = null, $reissueOrderNo = null, $reissueOrderId = null, $mallReissueOrderNo = null, $refundNo = null, $refundedAt = null)
    {
        $updateData = [
            'updated_at' => time(),
        ];
        if ($status !== null) {
            $updateData['status'] = $status;
        }
        if ($isPaid !== null) {
            $updateData['is_paid'] = $isPaid;
        }
        if ($paidAt !== null) {
            $updateData['paid_at'] = $paidAt;
        }
        if ($payPaymentNo !== null) {
            $updateData['pay_payment_no'] = $payPaymentNo;
        }
        if ($exchangeOrderNo !== null) {
            $updateData['exchange_order_no'] = $exchangeOrderNo;
        }
        if ($reissueOrderNo !== null) {
            $updateData['reissue_order_no'] = $reissueOrderNo;
        }
        if ($reissueOrderId !== null) {
            $updateData['reissue_order_id'] = $reissueOrderId;
        }
        if ($mallReissueOrderNo !== null) {
            $updateData['mall_reissue_order_no'] = $mallReissueOrderNo;
        }
        if ($refundNo !== null) {
            $updateData['refund_no'] = $refundNo;
        }
        if ($refundedAt !== null) {
            $updateData['refunded_at'] = $refundedAt;
        }
        $updateResult = AfterSaleRepository::instance()->updateData(['after_sale_no' => $afterSaleNo], $updateData);
        if (empty($updateResult)) {
            return $this->returnError(1000050, '更新售后单失败', ['update_data' => $updateData]);
        }
        return $this->returnSuccess(['update_data' => $updateData]);
    }

    /**
     * 安装服务取消结果通知
     *
     * @param string $afterSaleNo  售后单号
     * @param string $failedReason 失败原因
     * @param string $handler      操作人
     * @param int    $status       取消结果，1:取消成功，0:取消失败
     *
     * @return array
     */
    public function installServiceCancelNotify($afterSaleNo, $status, $failedReason, $handler)
    {
        $afterSale = $this->getOneByAfterSaleNo($afterSaleNo);
        if (empty($afterSale)) {
            return $this->returnError(1000051, '售后单不存在');
        }
        $params = [
            'after_sale_data' => $afterSale,
            'after_sale_no'   => $afterSaleNo,
            'success'         => $status,
            'failed_reason'   => $failedReason,
            'operator'        => ['type' => 3, 'name' => $handler],
        ];
        return (new AfterSaleHandleService($params))->getConcreteClass($afterSale['processing_type'])->installationServiceCancelNotify();
    }

    /**
     * 运输险取消结果通知
     *
     * @param string $orderNo 订单号
     *
     * @return array
     */
    public function transportInsuranceCancelNotify($orderNo, $status)
    {
        $params = [
            'order_no' => $orderNo,
            'status'   => $status,
            'operator' => ['type' => 3, 'id' => 0, 'name' => '运输险渠道回调'],
        ];
        return (new CancelTransportInsurance($params))->serviceCancelNotify();
    }

    /**
     * 发送给oms，修改订单信息
     *
     * @param $afterSale
     *
     * @return array|void
     * @throws \yii\db\Exception
     */
    public function sendToOmsModifyOrderInfo($afterSale)
    {
        // 组装toOmsOrder的参数 发送到oms
        $sendResult = AfterSaleService::instance()->installServiceHandle($afterSale['after_sale_no']);
        $sendResult = !empty($sendResult['data']['cancel']) ? true : false;

        // 更新收单数据
        $updateData = [
            'status'     => AfterSaleStatusEnum::SYNC_OMS_ING,
            'updated_at' => time()
        ];
        $result     = AfterSaleRepository::instance()->updateData(['after_sale_no' => $afterSale['after_sale_no']], $updateData);
        if (empty($result)) {
            return $this->returnError(4007004, '数据库操作失败，请重试');
        }
        AfterSaleLogRepository::instance()->insertLog(
            $afterSale['after_sale_no'],
            $afterSale['status'],
            $updateData['status'],
            9603,
            ['supplement' => sprintf("通知结果：%s，操作结果：%s", $sendResult, $result)],
            $params['operator'] ?? [],
            [],
            '',
            $updateData
        );
    }

    /**
     * 取消安装服务订单
     *
     * @return array
     */
    public function cancelInstallationServiceOrder($afterSaleNo, $orderNo, $afterSaleStatus, $processingType, $operator)
    {
        //检测是否需要取消安装服务
        $checkResult = $this->isRequireCancelInstallationService($afterSaleNo, $orderNo, $processingType);
        g_log_info('cancelInstallationServiceOrder', '是否需要取消安装服务检测结果', $checkResult);
        if (!$checkResult['data']['require_cancel']) {
            return $checkResult;
        }
        $cancelList = $checkResult['data']['cancel_list'];
        $result     = GoodsInternal::instance()->cancelInstallOrder($afterSaleNo, $cancelList, '售后审核通过取消安装服务', $operator);
        if (1 != $result['code']) {
            $error = '取消安装服务失败：' . $result['info'];
            AfterSaleService::instance()->updateAfterSale($afterSaleNo, $afterSaleStatus);
            AfterSaleLogService::instance()->addLog($afterSaleNo, $afterSaleStatus, $afterSaleStatus, $error, ['afterSaleNo' => $afterSaleNo, 'cancelList' => $cancelList], [], $operator);

            return $this->returnError(4007015, $error);
        }
        //调用接口成功后，internal会通知售后成功，调用售后的接口internal/after-sale/install-service-cancel-notify，就在这个接口更新状态了
        //AfterSaleService::instance()->updateAfterSale($afterSaleNo, AfterSaleStatusEnum::SERVICE_CANCELING);
        //AfterSaleLogService::instance()->addLog($afterSaleNo, $afterSaleStatus, AfterSaleStatusEnum::SERVICE_CANCELING, '已发起取消安装服务，处理中', [], ['status' => AfterSaleStatusEnum::SERVICE_CANCELING], $operator);
        return $this->returnSuccess([
            'require_cancel' => true,
        ]);
    }

    /**
     * 是否需要取消安装服务
     *
     * @param string $afterSaleNo    售后单号
     * @param string $orderNo        订单号
     * @param int    $processingType 处理方案
     *
     * @return  array
     */
    public function isRequireCancelInstallationService($afterSaleNo, $orderNo, $processingType)
    {
        if (!in_array($processingType, [AfterSaleProcessTypeEnum::MODIFY_INSTALLATION_SERVICE, AfterSaleProcessTypeEnum::EXCHANGE_GOODS,
            AfterSaleProcessTypeEnum::ONLY_REFUND, AfterSaleProcessTypeEnum::RETURN_GOODS_REFUND, AfterSaleProcessTypeEnum::EXCHANGE_NONSALE_SKU,
            AfterSaleProcessTypeEnum::EXCHANGE_NONSALE_SKU
        ])) {
            $processingTypeName = AfterSaleProcessTypeEnum::$list[$processingType] ?? '未知';
            return $this->returnSuccess(['require_cancel' => false, 'remark' => '当前处理方案[' . $processingTypeName . ']不需要取消安装服务']);
        }
        // 获取订单信信息
        $orderAllData = OrderInternal::instance()->getAllDataByOrderNo($orderNo, null, null, null, null, null, null, null, null, null, null, null, null, null, 'required');
        if (1 != $orderAllData['code']) {
            throw new \Exception($orderAllData['info'] ?? '获取订单数据失败', 400222);
        }
        $cancelInstallationList = [];
        if (!empty($orderAllData['data']['order_goods_installation'])) {
            foreach ($orderAllData['data']['order_goods_installation'] as $goodsInstallation) {
                if (!empty($goodsInstallation['installation_no'])) {
                    $cancelInstallationList[] = $goodsInstallation;
                }
            }
        }
        if (empty($cancelInstallationList)) {
            return $this->returnSuccess(['require_cancel' => false, 'remark' => '订单没有安装服务']);
        }
        $afterSaleGoodsData = AfterSaleGoodsService::instance()->getListByAfterSaleNo($afterSaleNo);
        //有安装服务，先发送取消安装服务
        $cancelList             = [];
        $cancelInstallationList = array_column($cancelInstallationList, null, 'order_goods_id');
        foreach ($afterSaleGoodsData as $goods) {
            $installationServiceInfo = $cancelInstallationList[$goods['order_goods_id']] ?? [];
            if (empty($installationServiceInfo)) {
                continue;
            }
            $cancelList[] = [
                'installation_no' => $installationServiceInfo['installation_no'],
                'sku_id'          => $goods['sku_id'],
                'sku_code'        => $goods['sku_code'],
                'qty'             => $goods['installation_service_qty'],
                'service_id'      => $installationServiceInfo['service_id'],
            ];
        }
        if (empty($cancelList)) {
            return $this->returnSuccess(['require_cancel' => false, 'remark' => '售后商品没有安装服务']);
        }
        return $this->returnSuccess([
            'require_cancel' => true,
            'cancel_list'    => $cancelList,
        ]);
    }

    /**
     * 安装服务单取消结果通知
     *
     * @param array  $afterSale    售后单信息
     * @param string $failedReason 失败原因
     * @param int    $notifyStatus 取消安装服务单结果，1：成功，0：失败
     * @param array  $operator     操作人信息
     *
     * @return array|ActiveRecord[]
     * @throws Exception
     */
    public function installationServiceCancelNotify($afterSale, $notifyStatus, $failedReason, $operator)
    {
        if ($notifyStatus) {
            $newStatus  = AfterSaleStatusEnum::SERVICE_CANCELED;
            $logContent = '安装服务单取消成功';
        } else {
            $newStatus  = AfterSaleStatusEnum::CUSTOMER_SERVICE_REVIEW;
            $logContent = '安装服务单取消失败:' . $failedReason;
        }
        $result     = AfterSaleService::instance()->updateAfterSale($afterSale['after_sale_no'], $newStatus);
        $updateData = [];
        if (1 != $result['code']) {
            AfterSaleLogService::instance()->addLog($afterSale['after_sale_no'], $afterSale['status'], $afterSale['status'], '更新售后单状态失败：' . $logContent, [], [], $operator);
            return $result;
        }
        if (!$notifyStatus) {
            AfterSaleLogService::instance()->addLog($afterSale['after_sale_no'], $afterSale['status'], $newStatus, $logContent, [], $updateData, $operator);
            return $result;
        }
        //取消成功创建退款单
        $afterSaleGoodsData = AfterSaleGoodsService::instance()->getListByAfterSaleNo($afterSale['after_sale_no']);
        $refundNo           = AuditService::instance()->createdRefund(array_merge($afterSale, $updateData), $operator, $afterSale['refund_amount'], $afterSaleGoodsData);
        if (empty($refundNo)) {
            return $result;
        }
        $updateResult = AfterSaleService::instance()->updateAfterSale($afterSale['after_sale_no'], AfterSaleStatusEnum::REFUNDING, null, null, null, null, null, null, null, $refundNo);
        if (1 != $updateResult['code']) {
            $logAfterSaleStatus = $newStatus;
            $updateLogContent   = $updateResult['info'];
        } else {
            $logAfterSaleStatus = AfterSaleStatusEnum::REFUNDING;
            $updateLogContent   = '创建退款单成功';
        }
        AfterSaleLogService::instance()->addLog($afterSale['after_sale_no'], $logAfterSaleStatus, AfterSaleStatusEnum::REFUNDING, $updateLogContent, [], ['status' => AfterSaleStatusEnum::REFUNDING, 'refund_no' => $refundNo], $operator);
        return $updateResult;

    }

    /**
     * 获取已取消或正在取消安装服务的总数（按order_goods_id分组）
     *
     * @param string $paymentNo          支付单号【同一个支付单】
     * @param array  $orderGoodsIds      订单商品ID列表
     * @param string $excludeAfterSaleNo 过滤售后单号
     *
     * @return array
     */
    public function getCancelInstallationCountGroup($orderGoodsIds, $paymentNo = '', $excludeAfterSaleNo = '')
    {
        return AfterSaleRepository::instance()->getCancelInstallationCountGroup($orderGoodsIds, $paymentNo, $excludeAfterSaleNo);
    }

    /**
     * 获取可退安装服务金额
     *
     * @param int   $processingType          处理方案
     * @param int   $applyQty                申请售后数量
     * @param array $orderGoods              订单商品信息
     * @param array $orderGoodsInstallation  安装服务信息
     * @param array $effectiveAfterSaleGoods 有效的售后商品信息
     *
     * @return float
     */
    public function getRefundableInstallationAmount($processingType, $applyQty, $orderGoods, $orderGoodsInstallation, $effectiveAfterSaleGoods)
    {
        //计算可退安装服务金额（指定的售后处理方案 & 商品安装服务可取消）
        if (in_array($processingType, [AfterSaleProcessTypeEnum::MODIFY_INSTALLATION_SERVICE, AfterSaleProcessTypeEnum::EXCHANGE_GOODS,
                AfterSaleProcessTypeEnum::ONLY_REFUND, AfterSaleProcessTypeEnum::RETURN_GOODS_REFUND, AfterSaleProcessTypeEnum::EXCHANGE_NONSALE_SKU,
                AfterSaleProcessTypeEnum::TAX_REBATE
            ]) &&
            !empty($orderGoodsInstallation && 1 == $orderGoodsInstallation['is_can_cancel'])) {
            //正在取消或者已取消的安装服务数量
            $cancelInstallationServiceQty = $effectiveAfterSaleGoods['cancel_installation_service_qty'] ?? 0;
            //剩余可取消件数
            $restCancelQty = (int)$orderGoods['qty'] - $cancelInstallationServiceQty;
            //实际要取消的件数
            $actualCancelQty              = min($restCancelQty, $applyQty);
            $refundableInstallationAmount = $orderGoods['qty'] != $actualCancelQty ? bcmul($orderGoods['installation_amount'] / $orderGoods['qty'], $actualCancelQty, 2) : $orderGoods['installation_amount'];
            //要收取的安装费：如果预约安装时间在24小时内，需要收费，那么可退金额就要减去这部分安装费
            $payInstallationAmount        = bcmul($orderGoodsInstallation['pay_single_installation_amount'], $applyQty, 2);
            $refundableInstallationAmount = max(bcsub($refundableInstallationAmount, $payInstallationAmount, 2), 0);
        } else {
            $refundableInstallationAmount = 0;
        }
        return $refundableInstallationAmount;
    }

    /**
     * 获取运费
     *
     * @param array  $applyGoodsList 申请售后商品信息
     * @param array  $orderGoodsData 订单商品信息
     * @param string $countryCode    国家码
     * @param string $stateCode      省份码
     * @param string $zip            邮编
     * @param string $currency       币种
     *
     * @return float|int
     * @throws \Exception
     */
    public function getShippingFee($applyGoodsList, $orderGoodsData, $countryCode, $stateCode, $zip, $currency)
    {
        $skuDeliveryList = [];
        foreach ($applyGoodsList as $orderGoodsId => $applyGoods) {
            $skuDeliveryList[] = [
                'qty'              => $applyGoods['qty'],
                'delivery_channel' => $orderGoodsData[$orderGoodsId]['delivery_channel'],
            ];
        }
        list($shippingFee, $skuDeliveryList) = ExchangeCalculateService::instance()->getShippingFee($skuDeliveryList, $countryCode, $stateCode, $zip, $currency);

        return $shippingFee;
    }

    /**
     * 获取是否需要取消运输险商品
     *
     * @param int          $processingType              处理方案
     * @param array|string $processingContent           处理内容
     * @param array        $orderGoods                  当前申请售后的订单商品
     * @param int          $cancelTransportInsuranceQty 已完成售后中当前商品已取消运输险数量
     *
     * @return int 1-需要，0-不需要
     */
    public function getCancelTransportInsuranceFlag($processingType, $processingContent = [], $orderGoods = [], $cancelTransportInsuranceQty = 0)
    {
        $processingType             = (int)$processingType;
        $processingContent          = $this->formatProcessingContent($processingContent);
        $modifyCode                 = $processingContent['code'] ?? '';
        $isCancelTransportInsurance = 0;

        if (in_array($processingType, [
            AfterSaleProcessTypeEnum::ONLY_REFUND,
            AfterSaleProcessTypeEnum::EXCHANGE_GOODS,
            AfterSaleProcessTypeEnum::EXCHANGE_NONSALE_SKU,
            AfterSaleProcessTypeEnum::RETURN_GOODS_REFUND,
        ], true)) {
            $isCancelTransportInsurance = 1;
        }

        if ($processingType == AfterSaleProcessTypeEnum::RECEIPT_METHOD) {
            $isCancelTransportInsurance = $modifyCode == ModifyOrderTypeEnum::MODIFY_ORDER_WAREHOUSE ? 1 : 0;
        }

        if ($processingType == AfterSaleProcessTypeEnum::MODIFY_TRANSPORT_INSURANCE) {
            $isCancelTransportInsurance = $modifyCode == ModifyOrderTypeEnum::CANCEL_TRANSPORT_INSURANCE ? 1 : 0;
        }

        if (
            $processingType == AfterSaleProcessTypeEnum::MODIFY_ORDER_INFORMATION
            && $modifyCode == ModifyOrderTypeEnum::MODIFY_ORDER_ADDRESS_INFO
        ) {
            $transportInsuranceSupportedCountryList = ConfigHelper::getTransportInsuranceAllowCountryList();
            if (in_array($processingContent['address_info']['country_code'], $transportInsuranceSupportedCountryList)) {
                $isCancelTransportInsurance = 0;
            } else {
                $isCancelTransportInsurance = 1;
            }
        }

        if (empty($isCancelTransportInsurance)) {
            return 0;
        }

        if (!empty($orderGoods)) {
            $orderGoodsQty = (int)($orderGoods['qty'] ?? 0);
            if ($orderGoodsQty > 0 && (int)$cancelTransportInsuranceQty >= $orderGoodsQty) {
                return 0;
            }
        }

        return 1;
    }

    private function formatProcessingContent($processingContent)
    {
        if (is_string($processingContent)) {
            $processingContent = json_decode($processingContent, true);
        }

        return is_array($processingContent) ? $processingContent : [];
    }

    private function isTruthyFlag($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value == 1;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'on'], true);
        }

        return !empty($value);
    }

    private function isFalsyFlag($value)
    {
        if (is_bool($value)) {
            return !$value;
        }
        if (is_numeric($value)) {
            return (int)$value == 0;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['0', 'false', 'no', 'n', 'off'], true);
        }

        return empty($value);
    }

    /**
     * 是否可以取消运输险
     *
     * 验证申请售后的商品是否满足取消运输险的条件：
     * 未取消的商品必须全部选择才能取消运输险
     *
     * @param string $orderNo         订单号
     * @param array  $orderGoodsData  订单商品数据，包含sku_code、qty、transportation_insurance_amount等字段
     * @param array  $applyGoodsList  申请售后的商品列表，包含sku_code、qty等字段
     * @param int    $autoComplete    是否为系统发起的自动完成售后，1：是，0：否
     * @param bool   $checkDispatched 是否检查已发货状态
     *
     * @return array 返回验证结果，成功返回success，失败返回error及错误信息
     */
    public function isCanCancelTransportInsurance($orderNo, $orderGoodsData, $applyGoodsList, $afterSaleNo, $autoComplete = 0, $checkDispatched = true)
    {
        //未取消的商品必须是全部已经选择上才能取消运输险
        //先获取全部有运输险的商品
        $includeTransportInsuranceGoodsList = [];
        $includeTransportInsuranceGoodsStr  = '';
        $isDispatched                       = false;
        foreach ($orderGoodsData as $goods) {
            if ($goods['origin_transportation_insurance_amount'] > 0) {
                $includeTransportInsuranceGoodsList[$goods['sku_code']] = $goods['qty'];
                $includeTransportInsuranceGoodsStr                      .= ($goods['sku_code'] . '数量' . $goods['qty'] . ' ');
                if ($goods['dispatch_status'] > 0) {
                    $isDispatched = true;
                }
            }
        }
        if (empty($includeTransportInsuranceGoodsList)) {
            return $this->returnError(4073923, '没有包含运输险的商品');
        }
        if ($checkDispatched && $isDispatched) {
            return $this->returnError(4073925, '已发货的商品不能取消运输险');
        }
        //已经存在退运输险费用的售后，则不可再退
        $transportInsuranceAfterSale = $this->getValidTransportInsurance($orderNo);
        if (!empty($transportInsuranceAfterSale)) {
            return $this->returnError(4073924, '已存在取消运输险的售后单：' . $transportInsuranceAfterSale['after_sale_no']);
        }
        if (!empty($autoComplete)) {
            //系统自动发起的售后单，不需要判断剩余可取消的商品数量
            return $this->returnSuccess();
        }
        //获取已取消运输险商品且已完成的售后单列表
        $afterSaleGoodsList = AfterSaleGoodsService::instance()->getCancelTransportInsuranceList($orderNo);
        $afterSaleQtyList   = [];
        if (!empty($afterSaleGoodsList)) {
            foreach ($afterSaleGoodsList as $goods) {
                if ($goods['after_sale_no'] == $afterSaleNo) {
                    continue;
                }
                if (!empty($afterSaleQtyList[$goods['sku_code']])) {
                    $afterSaleQtyList[$goods['sku_code']] += $goods['qty'];
                } else {
                    $afterSaleQtyList[$goods['sku_code']] = $goods['qty'];
                }
            }
        }
        //如果当前申请售后取消运输险的商品个数不等于除去已取消的数量，则不允许申请
        foreach ($applyGoodsList as $goods) {
            if (empty($includeTransportInsuranceGoodsList[$goods['sku_code']])) {
                continue;
            }
            $restQty = $includeTransportInsuranceGoodsList[$goods['sku_code']] - ($afterSaleQtyList[$goods['sku_code']] ?? 0);
            if ($goods['qty'] != $restQty) {
                return $this->returnError(4073926, $includeTransportInsuranceGoodsStr . '需要同时申请取消运输险');
            }
        }
        return $this->returnSuccess();
    }

    /**
     * 根据订单号获取一条未取消且处理方案为取消运输险的售后信息
     */
    public function getValidTransportInsurance($orderNo)
    {
        return AfterSaleRepository::instance()->getValidTransportInsurance($orderNo);
    }

    /**
     * 根据订单号获取已完成且处理方案为取消运输险的售后信息
     */
    public function getCancelledTransportInsurance($orderNo)
    {
        return AfterSaleRepository::instance()->getCancelledTransportInsurance($orderNo);
    }

    /**
     * 取消运输险订单商品
     *
     * @param string $afterSaleNo 售后单号
     * @param string $handler     操作人
     *
     * @return array 结果
     */
    public function cancelTransportInsuranceOrderGoods($afterSaleNo, $handler)
    {
        try {
            $afterSale = $this->getOneByAfterSaleNo($afterSaleNo);
            if (empty($afterSale)) {
                throw new \Exception('售后单不存在', 4073925);
            }
            if (AfterSaleStatusEnum::DONE != $afterSale['status']) {
                throw new \Exception('售后单未完成', 4073926);
            }
            $goodsList = AfterSaleGoodsService::instance()->getCancelTransportInsuranceGoodsList($afterSaleNo);
            if (empty($goodsList)) {
                g_log_warning('cancel_transport_insurance', '取消运输险订单商品',
                    ['after_sale_no' => $afterSaleNo, 'handler' => $handler, 'message' => '没有要取消运输险的商品']);
                return $this->returnSuccess(['message' => '没有要取消运输险的商品']);
            }
            $cancelGoodsList = [];
            foreach ($goodsList as $goods) {
                $cancelGoodsList[] = [
                    'sku_code'      => $goods['sku_code'],
                    'cancelled_qty' => (int)$goods['qty'],
                ];
            }
            $result        = OrderInternal::instance()->cancelTransportInsuranceOrderGoods($afterSale['order_no'], $cancelGoodsList, $handler);
            $cancelMessage = 1 == $result['code'] ? '售后商品取消运输险成功' : ('售后商品取消运输险失败：' . $result['info']);
            AfterSaleLogService::instance()->addLog($afterSale['after_sale_no'], $afterSale['status'], $afterSale['status'],
                $cancelMessage, ['order_no' => $afterSale['order_no'], 'goods_list' => $cancelGoodsList], [], ['id' => 0, 'name' => $handler, 'type' => 3]);
            if ($result['code'] != 1) {
                throw new \Exception($cancelMessage, 4073927);
            }
            $response = [];
            if (!empty($result['data']['require_cancel_transport_insurance'])) {
                //取消订单/修改地址/修改自提/换货/换货（非销售SKU）
                $reasonOptions = [
                    AfterSaleProcessTypeEnum::ONLY_REFUND              => \Yii::$app->params['cancelled_order_cancel_transportation_insurance'],
                    AfterSaleProcessTypeEnum::MODIFY_ORDER_INFORMATION => \Yii::$app->params['unsupported_address_cancel_transportation_insurance'],
                    AfterSaleProcessTypeEnum::RECEIPT_METHOD           => \Yii::$app->params['pickup_cancel_transportation_insurance'],
                    AfterSaleProcessTypeEnum::EXCHANGE_GOODS           => \Yii::$app->params['exchange_order_cancel_transportation_insurance'],
                    AfterSaleProcessTypeEnum::EXCHANGE_NONSALE_SKU     => \Yii::$app->params['exchange_order_cancel_transportation_insurance'],
                ];
                $reasonList    = $reasonOptions[$afterSale['processing_type']] ?? [];
                //需要取消运输险订单（如果运输险商品都取消了，则取消）
                $applyResult = (new CancelTransportInsurance([]))->applyCancelTransportInsurance($afterSale['order_no'], $reasonList, $handler);
                g_log_info('cancel_transport_insurance', '取消运输险订单',
                    ['after_sale_no' => $afterSaleNo, 'handler' => $handler, 'result' => $applyResult]);
                $message = '当前售后单完成后需要申请取消运输险，';
                if (1 == $applyResult['code']) {
                    $response['after_sale_no'] = $applyResult['data']['after_sale_no'];
                    $message                   .= ('申请成功：' . $applyResult['data']['after_sale_no']);
                } else {
                    $message           .= ('申请失败：' . $applyResult['info']);
                    $response['error'] = $message;
                }
                AfterSaleLogService::instance()->addLog($afterSale['after_sale_no'], $afterSale['status'], $afterSale['status'],
                    $message, [], [], ['id' => 0, 'name' => $handler, 'type' => 3]);
            }
            return $this->returnSuccess($response);

        } catch (\Throwable $e) {
            g_log_warning('cancel_transport_insurance', '取消运输险订单商品异常',
                ['after_sale_no' => $afterSaleNo, 'handler' => $handler, 'error' => $e->getMessage() . $e->getTraceAsString()]);
            return $this->returnError($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 修改运输险订单地址
     *
     * @param string $afterSaleNo 售后单号
     * @param string $orderNo     订单号
     * @param string $handler     操作人
     *
     * @return array 结果
     */
    public function modifyTransportInsuranceOrderAddress($afterSaleNo, $orderNo, $addressInfo, $handler)
    {
        try {
            //查看订单是否包含运输险
            $orderResult = OrderInternal::instance()->getAllDataByOrderNo($orderNo);
            $orderInfo   = $orderResult['data']['order'] ?? [];
            if (empty($orderInfo)) {
                throw new \Exception('未找到订单信息：' . $orderResult['info'], 4073146);
            }
            if (empty($orderInfo['transport_insurance'])) {
                g_log_warning('modifyTransportInsuranceOrderAddress', '修改运输险订单地址',
                    ['after_sale_no' => $afterSaleNo, 'handler' => $handler, 'message' => '该订单未包含运输险']);
                return $this->returnSuccess();
            }
            $result  = OrderInternal::instance()->modifyTransportInsuranceOrderAddress($orderNo, $addressInfo, $handler);
            $message = 1 == $result['code'] ? '修改运输险保单地址成功' : ('修改运输险保单地址失败：' . $result['info']);
            AfterSaleLogService::instance()->addLog($afterSaleNo, AfterSaleStatusEnum::DONE, AfterSaleStatusEnum::DONE,
                $message, [], [], ['id' => 0, 'name' => $handler, 'type' => 3]);
            if ($result['code'] != 1) {
                throw new \Exception($message, 4073928);
            }
            return $this->returnSuccess();

        } catch (\Throwable $e) {
            g_log_warning('modifyTransportInsuranceOrderAddress', '修改运输险保单地址异常',
                ['after_sale_no' => $afterSaleNo, 'handler' => $handler, 'error' => $e->getMessage() . $e->getTraceAsString()]);
            return $this->returnError($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 获取运输险金额明细（已支付，已退款，剩余可退款）
     *
     * @param string $orderNo                                   订单号
     * @param string $site                                      站点
     * @param string $currency                                  币种
     * @param array  $orderGoodsList                            订单商品列表，注意，每个商品都有tax_detail字段，包含了商品所有的税费明细数据
     * @param array  $applyGoodsList                            当前申请售后的商品 [['order_goods_id' => 1, 'qty' => 2]]
     * @param string $excludeAfterSaleNo                        要过滤的售后单
     * @param float  $customRefundTransportationInsuranceAmount 自定义申请退款的运输险费用
     * @param float  $customRefundTransportationInsuranceTax    自定义申请退款的运输险税费
     *
     * @return array 列表
     */
    public function getTransportInsuranceAmountDetail($orderNo, $site, $currency, $orderGoodsList, $applyGoodsList = [], $excludeAfterSaleNo = '',
                                                      $customRefundTransportationInsuranceAmount = 0, $customRefundTransportationInsuranceTax = 0)
    {
        //查询有退运输险金额的售后列表
        $refundTransportInsuranceList       = AfterSaleGoodsService::instance()->getRefundTransportInsuranceAmountGroupBySku($orderNo, [], $excludeAfterSaleNo);
        $includeTransportInsuranceGoodsList = [];
        $refundTransportInsuranceAmount     = 0;
        $refundTransportInsuranceTax        = 0;
        $insuranceAmountSplitBase           = [];
        $applyGoodsList                     = array_column($applyGoodsList, null, 'order_goods_id');
        foreach ($orderGoodsList as $goods) {
            if ($goods['origin_transportation_insurance_amount'] > 0) {
                //有效的运输险退款金额和税费
                $refundGoodsTransportInsuranceAmount = $refundTransportInsuranceList[$goods['sku_code']]['total_refund_transportation_insurance_amount'] ?? 0;
                $refundGoodsTransportInsuranceTax    = $refundTransportInsuranceList[$goods['sku_code']]['total_refund_transportation_insurance_tax'] ?? 0;
                //商品运输险先税费(us才退税)
                $goodsTransportInsuranceTax                                   = 'us' == $site ? ($goods['tax_detail']['transportation_insurance_tax'] ?? 0) : 0;
                $includeTransportInsuranceGoodsList[$goods['order_goods_id']] = [
                    'order_goods_id'                                => (int)$goods['order_goods_id'],
                    'sku_id'                                        => (int)$goods['sku_id'],
                    'sku_code'                                      => $goods['sku_code'],
                    'qty'                                           => (int)$goods['qty'],
                    'transportation_insurance_amount'               => (float)$goods['transportation_insurance_amount'],
                    'transportation_insurance_tax'                  => (float)$goodsTransportInsuranceTax,
                    'refund_transportation_insurance_amount'        => (float)(bcsub($goods['transportation_insurance_amount'], $refundGoodsTransportInsuranceAmount, 2)), //最大可退运输险
                    'refund_transportation_insurance_tax'           => (float)(bcsub($goodsTransportInsuranceTax, $refundGoodsTransportInsuranceTax, 2)),
                    'current_refundable_transport_insurance_amount' => 0,
                    'current_refundable_transport_insurance_tax'    => 0,
                ];
                if (!empty($applyGoodsList[$goods['order_goods_id']])) {
                    $insuranceAmountSplitBase[$goods['order_goods_id']] = $goods['transportation_insurance_amount'];
                    $refundTransportInsuranceAmount                     = (float)bcadd($refundTransportInsuranceAmount, $goods['transportation_insurance_amount'], 2);
                    $refundTransportInsuranceAmount                     = (float)bcsub($refundTransportInsuranceAmount, $refundGoodsTransportInsuranceAmount, 2);
                    $refundTransportInsuranceTax                        = (float)bcadd($refundTransportInsuranceTax, $goodsTransportInsuranceTax, 2);
                    $refundTransportInsuranceTax                        = (float)bcsub($refundTransportInsuranceTax, $refundGoodsTransportInsuranceTax, 2); //减去已退款运输险税费
                }
            }
        }
        $transportInsuranceAmountDetail = [
            'is_include_transport_insurance'    => count($includeTransportInsuranceGoodsList) > 0 ? 1 : 0,
            'transportation_insurance_amount'   => array_sum(array_column($includeTransportInsuranceGoodsList, 'transportation_insurance_amount')),
            'transportation_insurance_tax'      => array_sum(array_column($includeTransportInsuranceGoodsList, 'transportation_insurance_tax')),
            'refund_transport_insurance_amount' => (float)$refundTransportInsuranceAmount, //最大可退运输险金额
            'refund_transport_insurance_tax'    => (float)$refundTransportInsuranceTax,
            'goods_list'                        => $includeTransportInsuranceGoodsList,
        ];
        if (!empty($applyGoodsList) && 1 == $transportInsuranceAmountDetail['is_include_transport_insurance']) {
            //当前售后商品可以退的运输险金额和税费
            $currentRefundableTransportInsuranceAmount = 0;
            $currentRefundableTransportInsuranceTax    = 0;
            $transportInsuranceAmountGoodsList         = array_column($transportInsuranceAmountDetail['goods_list'], null, 'order_goods_id');
            foreach ($applyGoodsList as $applyGoods) {
                if (empty($transportInsuranceAmountGoodsList[$applyGoods['order_goods_id']])) {
                    continue;
                }
                //如果有传入自定义运输险退款金额，则使用传入的
                if ($customRefundTransportationInsuranceAmount > 0) {
                    $transportInsuranceAmountSplit = MyFunction::instance()->amountSplit($customRefundTransportationInsuranceAmount, $insuranceAmountSplitBase);
                    $transportInsuranceTaxSplit    = MyFunction::instance()->amountSplit($customRefundTransportationInsuranceTax, $insuranceAmountSplitBase);
                    //当前商品可退的运输险金额和税费（本行金额，禁止写入累计值）
                    $lineRefundableTransportInsuranceAmount = (float)($transportInsuranceAmountSplit[$applyGoods['order_goods_id']] ?? 0);
                    $lineRefundableTransportInsuranceTax    = (float)($transportInsuranceTaxSplit[$applyGoods['order_goods_id']] ?? 0);
                } else {
                    $lineRefundableTransportInsuranceAmount = (float)$transportInsuranceAmountGoodsList[$applyGoods['order_goods_id']]['refund_transportation_insurance_amount'];
                    $lineRefundableTransportInsuranceTax    = (float)$transportInsuranceAmountGoodsList[$applyGoods['order_goods_id']]['refund_transportation_insurance_tax'];
                }
                $currentRefundableTransportInsuranceAmount = (float)bcadd($currentRefundableTransportInsuranceAmount, $lineRefundableTransportInsuranceAmount, 2);
                $currentRefundableTransportInsuranceTax    = (float)bcadd($currentRefundableTransportInsuranceTax, $lineRefundableTransportInsuranceTax, 2);
                //当前申请售后商品应退的运输险金额和税费（按商品维度赋值，不能用累计总和）
                $transportInsuranceAmountDetail['goods_list'][$applyGoods['order_goods_id']]['current_refundable_transport_insurance_amount'] = $lineRefundableTransportInsuranceAmount;
                $transportInsuranceAmountDetail['goods_list'][$applyGoods['order_goods_id']]['current_refundable_transport_insurance_tax']    = $lineRefundableTransportInsuranceTax;
            }
            //当前售后单可退的运输险金额和税费
            $transportInsuranceAmountDetail['current_refundable_transport_insurance_amount']                    = (float)$currentRefundableTransportInsuranceAmount;
            $transportInsuranceAmountDetail['current_refundable_transport_insurance_tax']                       = (float)$currentRefundableTransportInsuranceTax;
            $transportInsuranceAmountDetail['current_refundable_transport_insurance_amount_include_tax']        = (float)bcadd($currentRefundableTransportInsuranceAmount, $currentRefundableTransportInsuranceTax, 2);
            $transportInsuranceAmountDetail['current_refundable_transport_insurance_amount_include_tax_symbol'] = MyFunction::instance()->formatPriceCurrencySymbol($currency, $transportInsuranceAmountDetail['current_refundable_transport_insurance_amount_include_tax'], $site);
        }
        $transportInsuranceAmountDetail['goods_list'] = array_values($transportInsuranceAmountDetail['goods_list']);
        return $transportInsuranceAmountDetail;
    }

    /**
     * Premium 售后方案校验：含 Plus 商品仅其他退款/退税费；免费权益服务不可退。
     *
     * @param array $params 售后入参
     *
     * @return array 校验失败返回错误结构，通过返回成功结构
     */
    public function checkMemberAfterSale(array $params)
    {
        return $this->validateMemberAfterSale($params);
    }

    /**
     * @param array $params
     *
     * @return array
     */
    protected function validateMemberAfterSale(array $params)
    {
        try {
            $processingType = (int)($params['processing_type'] ?? 0);
            $orderNo        = (string)($params['order_no'] ?? '');
            if (empty($orderNo) || empty($processingType)) {
                return $this->returnSuccess();
            }

            $applyGoodsList = $params['apply_goods_list'] ?? [];
            if (is_string($applyGoodsList)) {
                $applyGoodsList = json_decode($applyGoodsList, true) ?: [];
            }

            $orderGoodsList = [];
            $orderResult    = [];
            if (!empty($params['order_goods_list'])) {
                $orderGoodsList = is_string($params['order_goods_list'])
                    ? (json_decode($params['order_goods_list'], true) ?: [])
                    : $params['order_goods_list'];
            } elseif ($orderNo !== '') {
                $orderResult = OrderInternal::instance()->getAllDataByOrderNo($orderNo, 'required');
                if ((int)($orderResult['code'] ?? 0) === 1) {
                    $orderGoodsList = $orderResult['data']['order_goods_list'] ?? [];
                }
            }

            if (empty($applyGoodsList) && !empty($params['order_goods_id'])) {
                $applyGoodsList = [['order_goods_id' => (int)$params['order_goods_id'], 'qty' => 1]];
            }

            if (!\common\services\member\MemberAfterSaleService::applyListContainsPremium($applyGoodsList, $orderGoodsList)) {
                return $this->returnSuccess();
            }

            $mapped = \common\services\member\MemberAfterSaleService::mapProcessingTypeToSolution($processingType);
            if ($mapped === null) {
                $refundType = $params['refund_type'] ?? ($params['solution'] ?? '');
                if (in_array($refundType, ['other', 'other_refund', '4', 64, '64'], true)) {
                    $mapped = \common\services\member\MemberAfterSaleService::SOLUTION_OTHER_REFUND;
                }
                if (in_array($refundType, ['tax', 'tax_refund', 'tax_rebate', 73])) {
                    $mapped = \common\services\member\MemberAfterSaleService::SOLUTION_TAX_REFUND;
                }
                if (in_array($refundType, ['unconfirmed', 'not_confirm', 60])) {
                    $mapped = \common\services\member\MemberAfterSaleService::SOLUTION_UNCONFIRMED;
                }
            }
            if ($mapped === null) {
                $check = \common\services\member\MemberAfterSaleService::validateProcessingTypeForPremium($processingType);
                if (!$check['valid']) {
                    return $this->returnError($check['code'], $check['message']);
                }
                return $this->returnSuccess();
            }

            $orderGoodsMap = array_column($orderGoodsList, null, 'order_goods_id');
            $orderData     = [];
            if (!empty($orderResult['data']['order'])) {
                $orderData = $orderResult['data']['order'];
            }
            $isUse = (int)($orderData['is_use_benefit'] ?? ($params['is_use_benefit'] ?? 0));

            foreach ($applyGoodsList as $applyGoods) {
                $orderGoodsId = (int)($applyGoods['order_goods_id'] ?? 0);
                if ($orderGoodsId <= 0 || empty($orderGoodsMap[$orderGoodsId])) {
                    continue;
                }
                $orderGoods = $orderGoodsMap[$orderGoodsId];
                if (!\common\services\member\MemberAfterSaleService::isPremiumMemberGoods($orderGoods)) {
                    continue;
                }
                $isFree = \common\services\member\MemberAfterSaleService::isFreeBenefitService(
                    $isUse,
                    $orderGoods['origin_value_added_service_amount'] ?? 0,
                    $orderGoods['value_added_service_amount'] ?? 0
                ) || \common\services\member\MemberAfterSaleService::isFreeBenefitService(
                    $isUse,
                    $orderGoods['origin_transportation_insurance_amount'] ?? 0,
                    $orderGoods['transportation_insurance_amount'] ?? 0
                ) || \common\services\member\MemberAfterSaleService::isFreeBenefitService(
                    $isUse,
                    $orderGoods['origin_installation_amount'] ?? 0,
                    $orderGoods['installation_amount'] ?? 0
                );
                $goodsType = (int)($orderGoods['goods_type'] ?? MemberConstants::GOODS_TYPE_VIRTUAL);
                $check     = \common\services\member\MemberAfterSaleService::validateSolution($goodsType, $mapped, $isFree);
                if (!$check['valid']) {
                    return $this->returnError($check['code'], $check['message']);
                }
            }
        } catch (\Exception $e) {
            g_log_error('member_aftersale', 'validate_failed', [$e->getMessage()]);
        }

        return $this->returnSuccess();
    }
}
