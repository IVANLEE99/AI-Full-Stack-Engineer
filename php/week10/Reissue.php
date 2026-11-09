<?php
/**
 * 售后补发的所有相关操作
 */

namespace common\services\processingType\concrete;

use App\Utils\BaseFunction;
use App\Utils\MyFunction;
use App\Utils\NodeExecutionEngine;
use common\api\AmsApi;
use common\api\GoodsInternal;
use common\api\PayInternal;
use common\enums\AfterSaleActionEnum;
use common\enums\AfterSaleProcessTypeEnum;
use common\enums\AfterSaleStatusEnum;
use common\enums\AuditTypeEnum;
use common\enums\ReissueTypeEnum;
use common\NodeFunction;
use common\repositorys\AfterSaleConsultLabelRepository;
use common\repositorys\AfterSaleGoodsRepository;
use common\repositorys\AfterSaleLogRepository;
use common\repositorys\AfterSaleRepository;
use common\services\AuditService;
use common\services\BasicDataService;
use common\services\common\MqService;
use common\services\contexts\apply\ManageApplyContext;
use common\services\contexts\AuditContext;
use common\services\contexts\SubmitAuditOrTemporaryDataContext;
use common\services\EstimateTaxService;
use common\services\nodes\apply\GetAuditDataNode;
use common\services\nodes\apply\GoodsInsertDataFormatNode;
use common\services\nodes\apply\InsertDataFormatNode;
use common\services\nodes\apply\SaveDataNode;
use common\services\nodes\GetAfterSaleDataNode;
use common\services\nodes\OrderRelatedDataNode;
use common\services\nodes\submitAuditOrTemporaryData\UpdateDataNode;
use common\services\OrderService;
use common\services\params\RefundCreateParams;

class Reissue extends \common\services\processingType\ProcessingType
{

    //处理方案的枚举值
    public $processingTypeValue = AfterSaleProcessTypeEnum::REISSUE;
    //处理方案的名称
    public $processingTypeName = '补发';

    public function apply()
    {
        $context   = new ManageApplyContext($this->params);
        $nodeChain = [
            // 订单相关的数据获取
            OrderRelatedDataNode::instance(),
            // 申请数据校验
            new NodeFunction($this, 'applyCheck'),
            // 数据组装前的一些处理
            new NodeFunction($this, 'applyContextValueSet'),
            // 售后商品相关组装
            GoodsInsertDataFormatNode::instance(),
            // 售后数据组装
            InsertDataFormatNode::instance(),
            // 数据保存
            SaveDataNode::instance(),
            // 补发的支付单相关处理,状态流转
            //new NodeFunction($this, 'reissuePaymentHandle'),
        ];
        $result    = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
        if ($result['code'] == 1) {
            return $this->returnSuccess($context->response);
        } else {
            return $this->returnError($result['code'], $result['info'], $context->response);
        }
    }

    public function temporaryData()
    {
        //1. 支付金额==0，可再次编辑，生成新的支付单。如果编辑后支付金额为0，则售后单状态为：待审核，否则为待支付
        //2. 支付金额 > 0 && 已支付，使用原有支付单，售后单状态为：待审核
        //实例化，初始化参数
        $context   = new SubmitAuditOrTemporaryDataContext($this->params);
        $nodeChain = [
            // 订单相关的数据获取
            OrderRelatedDataNode::instance(),
            // aftersaleNo售后单，售后商品的查询
            GetAfterSaleDataNode::instance(),
            // 申请数据校验
            new NodeFunction($this, 'applyCheck'),
            new NodeFunction($this, 'applyContextValueSet'),
            // 售后商品相关组装
            GoodsInsertDataFormatNode::instance(),
            // 售后插入数据组装
            InsertDataFormatNode::instance(),
            // 售后更新数据组装
            UpdateDataNode::instance(),
            // 补发的支付单相关处理,状态流转
            //new NodeFunction($this, 'reissuePaymentHandle'),
        ];
        $result    = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
        if ($result['code'] == 1) {
            return $this->returnSuccess($context->response);
        } else {
            return $this->returnError($result['code'], $result['info'], $context->response);
        }
    }

    //提交审核
    public function submitConfirm()
    {
        //实例化，初始化参数
        $context   = new SubmitAuditOrTemporaryDataContext($this->params);
        $nodeChain = [
            // 订单相关的数据获取
            OrderRelatedDataNode::instance(),
            // aftersaleNo售后单，售后商品的查询
            GetAfterSaleDataNode::instance(),
            // 申请数据校验
            new NodeFunction($this, 'applyCheck'),
            //上下文一些值的设置
            new NodeFunction($this, 'submitConfirmContextValueSet'),
            // 售后商品相关组装
            GoodsInsertDataFormatNode::instance(),
            // 售后插入数据组装
            InsertDataFormatNode::instance(),
            // 售后更新数据组装
            UpdateDataNode::instance(),
            // 补发的支付单相关处理,状态流转
            //new NodeFunction($this, 'reissuePaymentHandle'),
        ];
        $result    = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
        if ($result['code'] == 1) {
            return $this->returnSuccess($context->response);
        } else {
            return $this->returnError($result['code'], $result['info'], $context->response);
        }
    }

    public function auditPass()
    {
        //组织上下文数据
        $context               = new AuditContext($this->params);
        $context->handleAction = AuditTypeEnum::AUDIT_PASS;
        // 扭转为审核状态
        $context->status = 0;
        $nodeChain       = [
            // 获取需要审核的数据
            GetAuditDataNode::instance(),
            // 订单相关的数据获取
            OrderRelatedDataNode::instance(),
            // 校验
            new NodeFunction($this, 'auditCheckStatus'),
            // 审核通过
            new NodeFunction($this, 'auditPassHandle'),
        ];

        $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
        if (1 == $result['code']) {
            return $this->returnSuccess($context->response, 'success');
        } else {
            return $result;
        }

    }

    public function auditReject()
    {
        //组织上下文数据
        $context   = new AuditContext($this->params);
        $nodeChain = [
            // 获取需要审核的数据
            GetAuditDataNode::instance(),
            // 订单相关的数据获取
            OrderRelatedDataNode::instance(),
            // 校验
            new NodeFunction($this, 'auditCheckStatus'),
            // 审核拒绝
            new NodeFunction(new AuditService(), 'auditReject'),
        ];

        $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
        if (1 == $result['code']) {
            return $this->returnSuccess($context->response, 'success');
        } else {
            return $result;
        }
    }

    public function cancel()
    {
        //组织上下文数据
        $context   = new AuditContext($this->params);
        $nodeChain = [
            // 获取需要审核的数据
            GetAuditDataNode::instance(),
            // 校验状态
            new NodeFunction($this, 'cancelStatusCheck'),

            // 取消订单操作
            new NodeFunction($this, 'reissueCancelHandle'),
        ];

        $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
        if (1 == $result['code']) {
            return $this->returnSuccess($context->response, 'success');
        } else {
            return $result;
        }
    }

    public function paidNotify()
    {
        $params    = $this->params;
        $afterSale = $params['after_sale_data'];
        if ($afterSale['status'] != AfterSaleStatusEnum::WAITING_FOR_THE_PAYMENT) {
            return $this->returnError(4237998, '售后状态不是待支付');
        }

        $updateData = [
            'status'     => AfterSaleStatusEnum::THE_PAYMENT_PAID,
            'is_paid'    => 1,
            'pay_at'     => $params['pay_at'],
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
            9103,
            ['supplement' => sprintf("单号：%s,支付成功时间：%s", $params['payment_no'], date('Y-m-d H:i:s', $params['pay_at']))],
            $params['operator'] ?? []
        );
        $afterSale = array_merge($afterSale, $updateData);
        //已支付，创建补发订单
        $createReissueOrderResult = $this->createReissueOrder($afterSale);
        if (1 != $createReissueOrderResult['code']) {
            return $this->returnError(1938151, '创建补发订单异常：' . $createReissueOrderResult['info']);
        }
        $afterSale['mall_reissue_order_no'] = $createReissueOrderResult['data']['order_no'];
        //更新售后
        return $this->doneHandle($afterSale, $params['operator'] ?? [], '支付成功，生成商城补发订单号：' . $afterSale['mall_reissue_order_no']);
    }

    public function refundNotify()
    {
        $params    = $this->params;
        $afterSale = $params['after_sale_data'];
        if ($afterSale['status'] != AfterSaleStatusEnum::REFUNDING) {
            return $this->returnError(4237998, '售后状态不是退款中');
        }

        $updateData = [
            'status'      => AfterSaleStatusEnum::CANCEL,
            'refunded_at' => $params['refunded_at'],
            'updated_at'  => time(),
        ];
        $result     = AfterSaleRepository::instance()->updateData(['after_sale_no' => $params['after_sale_no']], $updateData);

        if (empty($result)) {
            return $this->returnError(4007004, '数据库操作失败，请重试');
        }
        //售后完成上报神策
        MqService::instance()->pushAfterSaleStatusChange($params['after_sale_no'], AfterSaleStatusEnum::DONE, $params['operator'] ?? [], AfterSaleActionEnum::ACTION_DONE, '补发售后完成');

        AfterSaleLogRepository::instance()->insertLog(
            $params['after_sale_no'],
            $afterSale['status'],
            $updateData['status'],
            9105,
            ['supplement' => !empty($params['refunded_at']) ? sprintf("退款成功时间：%s", date('Y-m-d H:i:s', $params['refunded_at'])) : ''],
            $params['operator'] ?? []
        );
        return $this->returnSuccess();

    }

    public function toOmsOrderNotify()
    {
        return $this->returnError(404, 'no action');
    }

    /**
     * ams系统，通知相关处理
     *
     * @return array
     */
    public function amsNotify()
    {
        $params             = $this->params;
        $params['operator'] = [
            'type' => 3,
            'id'   => 0,
            'name' => 'AMS系统通知'
        ];
        if (isset($params['reissue_status'])) {
            if ($params['reissue_status'] == 'pass') {
                return $this->amsPass($params);
            }

            if ($params['reissue_status'] == 'reject') {
                return $this->amsReject($params);
            }
        }
        return $this->returnError(4443239, '无相关操作');
    }

    /**
     * 申请，暂存，提交审核的各项检查
     *
     * @param ManageApplyContext $context
     *
     * @return void
     * @throws \Exception
     */
    public function applyCheck(ManageApplyContext $context)
    {
        $skuCodeNumList = [];
        if (!empty($context->modifyContent)) {
            //校验一下支付金额是否一致
            $payAmount = 0;
            foreach ($context->modifyContent['reissue_goods_list'] as $goods) {
                $payAmount                          = bcadd($payAmount, $goods['total_price'], 2);
                $payAmount                          = bcadd($payAmount, $goods['service_amount'], 2);
                $payAmount                          = bcadd($payAmount, $goods['shipping_fee'], 2);
                $skuCodeNumList[$goods['sku_code']] = $goods['qty'];
            }
            $payAmount = bcadd($payAmount, $context->modifyContent['rate_fee'], 2);
            $payAmount = bcsub($payAmount, $context->customerServiceAmount, 2);
            if ($context->applyPayAmount != $payAmount) {
                $errorMsg = sprintf('支付金额不一致，传值：%s，实时计算：%s', $context->applyPayAmount, $payAmount);
                g_log_warning('applyCheck', '售后提交支付金额不一致', [
                    'after_sale_no'           => $context->afterSaleNo,
                    'order_no'                => $context->orderNo,
                    'apply_goods_list'        => $context->applyGoodsListData,
                    'customer_service_amount' => $context->customerServiceAmount,
                    'modify_content'          => $context->modifyContent,
                    'error'                   => $errorMsg,
                ]);
                throw new \Exception($errorMsg, 31102);
            }
        }
        //判断金额是否一致
        if (!empty($context->afterSaleData) && $context->processingType == $context->afterSaleData['processing_type']) {
            $afterSaleConsultLabelList = AfterSaleConsultLabelRepository::instance(true)
                ->getReasonListByNames($context->afterSaleData['reason_name_3'], $context->afterSaleData['reason_name_4'], $context->afterSaleData['reason_name_5']);
            $reasonIdList              = [
                $afterSaleConsultLabelList[5]['id'] ?? 0,
                $afterSaleConsultLabelList[4]['id'] ?? 0,
                $afterSaleConsultLabelList[3]['id'] ?? 0,
            ];
        } else {
            $reasonIdList = [$context->params['reason3'] ?? 0, $context->params['reason2'] ?? 0, $context->params['reason1'] ?? 0];
        }
        $isSupportReissue = 0;
        foreach ($reasonIdList as $reasonId) {
            if (empty($reasonId)) {
                continue;
            }
            $consultLabel = AfterSaleConsultLabelRepository::instance()->getReasonById($reasonId);
            if (empty($consultLabel)) {
                continue;
            }
            //售后原因是否支持补发
            $isSupportReissue = $consultLabel['is_support_reissue'];
            break;
        }
        //售后原因支持补发，不需要判断是否已发货
        if (empty($isSupportReissue)) {
            // 售后商品的发货状态校验
            foreach ($context->orderGoodsData as $goodsDatum) {
                if (
                    in_array($goodsDatum['order_goods_id'], array_column($context->applyGoodsListData, 'order_goods_id'))
                    && $goodsDatum['dispatch_status'] == 0
                ) {
                    throw new \Exception(sprintf('商品: %s 未发货', $goodsDatum['sku_code']), 31103);
                }
            }
        }
        // 补发申请的时候没有退款金额
        if ($context->processingType == AfterSaleProcessTypeEnum::REISSUE) {
            $context->applyRefundAmount = 0;
        }
        $where       = [
            ['=', 'order_no', $context->orderData['order_no']],
            ['=', 'processing_type', AfterSaleProcessTypeEnum::REISSUE],
            ['not in', 'status', [AfterSaleStatusEnum::DONE, AfterSaleStatusEnum::CANCEL]]
        ];
        $afterSaleNo = $context->afterSaleNo ?? '';
        if (!empty($afterSaleNo)) {
            $where[] = ['!=', 'after_sale_no', $afterSaleNo];
        }
        // 验证是否有正在进行的补发单，有的话要做拦截
        $afterSaleListData = AfterSaleRepository::instance()->getListByConditions($where, ['after_sale_no']);
        if (!empty($afterSaleListData)) {
            $afterSaleNos       = array_column($afterSaleListData, 'after_sale_no');
            $afterSaleGoodsList = AfterSaleGoodsRepository::instance()->getListByConditions([
                ['in', 'after_sale_no', $afterSaleNos]
            ], ['order_goods_id']);
            $afterSaleGoodsids  = array_column($afterSaleGoodsList, 'order_goods_id');
            foreach ($context->applyGoodsListData as $applyGoodsDatum) {
                if (in_array($applyGoodsDatum['order_goods_id'], $afterSaleGoodsids)) {
                    throw new \Exception(sprintf('商品: %s 已有补发单正在进行，请勿重复操作', $applyGoodsDatum['sku_code']), 31104);
                }
            }
        }
        //【整套补发】次日达有效性校验，如果原单商品有次日达，补发商品也选了次日达，但实时检查补发的商品也已经没有次日达了，则报错
        if (ReissueTypeEnum::REISSUE_WHOLE == $context->modifyContent['reissue_type'] && !empty($context->modifyContent['order_goods_value_added_service_id'])) {
            $orderGoodsId = $context->applyGoodsListData[0]['order_goods_id'] ?? $context->afterSaleGoodsData[0]['order_goods_id']; //补发只能单个商品申请
            if (1 == $context->orderGoodsData[$orderGoodsId]['is_include_next_day']) { //原单商品是次日达
                $etaResult = GoodsInternal::instance()->getEtaInfo($skuCodeNumList, $context->orderData['site'], $context->orderData['language'],
                    $context->orderData['currency'], $context->orderData['platform'], '', $context->modifyContent['address_info']['country_code'],
                    $context->modifyContent['address_info']['zip'], 'realtime'
                );
                $skuCode = $context->orderGoodsData[$orderGoodsId]['sku_code'];
                if (1 == $etaResult['code'] && empty($etaResult['data'][$skuCode]['next_day_delivery_info'])) { //没有次日达信息，则说明次日达失效了
                    $context->response['is_remove_next_day'] = 1;
                    throw new \Exception('补发商品不支持原单增值服务', 31107);
                }
            }
        }
    }

    /**
     * 申请时的上下文的一些值的设置
     *
     * @param ManageApplyContext $context
     *
     * @return void
     * @throws \Exception
     */
    public function applyContextValueSet(ManageApplyContext $context)
    {
        if (empty($context->afterSaleNo)) {
            // 售后单号
            $context->afterSaleNo = BaseFunction::instance()->createNo('A', $context->orderData['user_id'] ?? 0);
        }

        //汇率
        $this->exchangeRateSet($context);

        // 状态初始化准备。
        if (isset($context->params['handle_action']) && $context->params['handle_action'] == AuditTypeEnum::TEMPORARY_DATA) {
            //申请的TemporaryData暂存数据也是申请，只不过把售后状态定死在待审核。
            $context->initStatus = AfterSaleStatusEnum::CONSULT;
        } else {
            $context->initStatus = AfterSaleStatusEnum::TO_AUDIT;
        }
    }

    /**
     * 确认内容提交时的上下文的一些值的设置
     *
     * @param ManageApplyContext $context
     *
     * @return void
     * @throws \Exception
     */
    public function submitConfirmContextValueSet(ManageApplyContext $context)
    {
        //汇率
        $this->exchangeRateSet($context);
        $context->initStatus = AfterSaleStatusEnum::TO_AUDIT;

        //判断补发单是否是已支付的，如果是已支付的，修改成：待审核，审核后推送给ams系统
        //if (!empty($context->afterSaleData['pay_at']) && $context->afterSaleData['pay_amount'] > 0) {
        //    $context->params['pay_payment_no'] = $context->afterSaleData['pay_payment_no'];
        //    $context->initStatus               = AfterSaleStatusEnum::TO_AUDIT;
        //}

    }

    /**
     * 补发的支付单相关处理,状态流转
     *
     * @param ManageApplyContext $context
     *
     * @return array|void
     */
    public function reissuePaymentHandle(ManageApplyContext $context)
    {
        if ($context->initStatus == AfterSaleStatusEnum::TO_AUDIT) {
            return;
        }

        //待确认内容状态，不处理。
        if ($context->afterSaleData['status'] == AfterSaleStatusEnum::CONSULT) {
            return;
        }
        $isPaid = 0;
        if (!empty($context->afterSaleData['pay_payment_no'])) {
            if (!empty($context->afterSaleData['pay_at']) || 0 == $context->afterSaleData['pay_amount']) {
                $isPaid         = 1;
                $changeTostatus = AfterSaleStatusEnum::TO_AUDIT;
                //已经支付过
                $supplement = '售后单已支付过，支付单号：' . $context->afterSaleData['pay_payment_no'];
            } else {
                $changeTostatus = AfterSaleStatusEnum::WAITING_FOR_THE_PAYMENT;
                $supplement     = '售后已生成过支付单，待支付:' . $context->afterSaleData['pay_payment_no'];
            }
        } else {
            if ($context->afterSaleData['pay_amount'] == 0) {
                //不用支付也要创建0元支付单
                $paymentResult = $this->createPayment($context);
                if (1 != $paymentResult['code']) {
                    throw new \Exception($paymentResult['info'], $paymentResult['code']);
                }
                $isPaid         = $paymentResult['data']['is_paid'];
                $changeTostatus = AfterSaleStatusEnum::TO_AUDIT;
                $supplement     = '售后支付金额为0,不需要支付';
            } else {
                $paymentResult = $this->createPayment($context);
                if ($paymentResult['code'] == 1 && !empty($paymentResult['data'])) {
                    $payment = $paymentResult['data'];
                    //校验补发支付金额
                    if (!isset($payment['pay_amount']) && $context->afterSaleData['pay_amount'] != $payment['pay_amount']) {
                        throw new \Exception('补发支付金额有误，请检查', 992893);
                    }
                    if (isset($payment['is_paid']) && $payment['is_paid'] == 1) {
                        $isPaid         = 1;
                        $changeTostatus = AfterSaleStatusEnum::TO_AUDIT;
                        $supplement     = sprintf('关联支付单号：%s 已支付', $payment['payment_no']);
                    } else {
                        $changeTostatus = AfterSaleStatusEnum::WAITING_FOR_THE_PAYMENT;
                        $supplement     = sprintf('关联支付单号：%s 未支付', $payment['payment_no']);
                    }
                } else {
                    throw new \Exception($paymentResult['info'], $paymentResult['code']);
                }
            }
        }
        if ($context->afterSaleData['status'] == $changeTostatus) {
            return;
        }
        $update = [
            'is_paid'    => $isPaid,
            'status'     => $changeTostatus,
            'updated_at' => time()
        ];
        if (!empty($context->payPaymentNo)) {
            $update['pay_payment_no'] = $context->payPaymentNo;
        }
        //开启事务
        $transaction = AfterSaleRepository::instance()->getConnection()->beginTransaction();
        try {
            AfterSaleRepository::instance()->updateData(['after_sale_no' => $context->afterSaleNo], $update);
            //插入日志
            AfterSaleLogRepository::instance()->insertLog(
                $context->afterSaleNo,
                $context->afterSaleData['status'],
                $changeTostatus,
                9101,
                ['supplement' => $supplement],
                $context->operator,
                $context->params
            );

            $transaction->commit();

        } catch (\Throwable $e) {
            $transaction->rollBack();
            g_log_error('reissue', '补发创建支付单异常',
                ['after_sale_no' => $context->afterSaleNo, 'error' => $e->getMessage(), 'error_trace' => $e->getTraceAsString()]);
            throw new \Exception($e->getMessage(), 1000049);
        }
    }

    //创建支付单
    private function createPayment(ManageApplyContext $context)
    {
        $result = PayInternal::instance()->paymentCreate(
            $context->afterSaleInsertData['site'],
            $context->afterSaleInsertData['currency'],
            $context->afterSaleInsertData['pay_amount'],
            $context->afterSaleNo,
            $context->afterSaleInsertData['order_no'],
            1
        );

        $context->payPaymentNo = $result['data']['payment_no'] ?? '';

        return $result;
    }

    /**
     * 创建补发支付单
     */
    private function createReissuePayment($afterSaleNo, $orderNo, $payAmount, $currency, $site)
    {
        return PayInternal::instance()->paymentCreate(
            $site,
            $currency,
            $payAmount,
            $afterSaleNo,
            $orderNo,
            1
        );
    }

    /**
     * 汇率的设置
     *
     * @param ManageApplyContext $context
     *
     * @return void
     * @throws \Exception
     */
    public function exchangeRateSet(ManageApplyContext $context)
    {
        $context->toUsdRate = BasicDataService::instance()->getExchangeRate($context->orderData['currency'], 'usd');
        $context->toCnyRate = BasicDataService::instance()->getExchangeRate($context->orderData['currency'], 'cny');
    }


    public function auditCheckStatus(AuditContext $context)
    {
        $afterSaleStatus = $context->afterSaleData['status'];
        // 状态不在可提交范围内
        if ($afterSaleStatus != AfterSaleStatusEnum::TO_AUDIT) {
            throw new \Exception('售后单状态不是待审核，请核实', 9004);
        }
    }

    public function auditPassHandle(AuditContext $context)
    {
        //请求供应链ams接口生成补发订单。
        $address = json_decode($context->afterSaleData['processing_content'], 1);
        $address = $address['address_info'] ?? [];
        if (empty($address)) {
            throw new \Exception('补发的地址信息缺失', 3299004);
        }

        $afterSaleConsultLabelList = AfterSaleConsultLabelRepository::instance(true)->getReasonListByNames($context->afterSaleData['reason_name_3'], $context->afterSaleData['reason_name_4'], $context->afterSaleData['reason_name_5']);
        $afterSaleConsultLabelTmp  = [
            'reason1_id'   => $afterSaleConsultLabelList[3]['id'] ?? 0,
            'reason1_name' => !empty($afterSaleConsultLabelList[3]['name']) ? $afterSaleConsultLabelList[3]['name'] : $context->afterSaleData['reason_name_3'],
            'reason2_id'   => $afterSaleConsultLabelList[4]['id'] ?? 0,
            'reason2_name' => !empty($afterSaleConsultLabelList[4]['name']) ? $afterSaleConsultLabelList[4]['name'] : $context->afterSaleData['reason_name_4'],
            'reason3_id'   => $afterSaleConsultLabelList[5]['id'] ?? 0,
            'reason3_name' => !empty($afterSaleConsultLabelList[5]['name']) ? $afterSaleConsultLabelList[5]['name'] : $context->afterSaleData['reason_name_5'],
        ];

        $paymentResult = PayInternal::instance()->getPayment($context->afterSaleData['pay_payment_no']);

        $fileUrlAnalysis                          = MyFunction::instance()->fileUrlAnalysis($context->afterSaleData['prove_url']);
        $context->afterSaleData['prove_url_list'] = array_column($fileUrlAnalysis, 'url');


        if (!empty($context->params['reissue_type'])) {
            foreach ($context->afterSaleGoodsData as &$afterSaleGoods) {
                $afterSaleGoods['reissue_type'] = $context->params['reissue_type'];
            }
        }
        $pushResult = AmsApi::instance()->pushReissueOrder($address,
            array_merge($context->afterSaleData, $afterSaleConsultLabelTmp),
            $context->afterSaleGoodsData,
            $paymentResult['data'] ?? [],
            $context->orderGoodsData,
            $context->valueAddedServiceList
        );
        if ($pushResult['code'] != 1) {
            throw new \Exception('供应链AMS补发单创建失败 ' . ($pushResult['info'] ?? ''), 3299005);
        }
        $reissueOrderId = $pushResult['data']['reissueOrderId'] ?? 0;
        $reissueOrderNo = $pushResult['data']['reissueOrderNo'] ?? '';
        if (empty($reissueOrderId) || empty($reissueOrderNo)) {
            throw new \Exception('供应链AMS补发单创建成功后未返回补发单号或补发单ID', 3299006);
        }
        // 判断是否要更新第一次审核人信息
        if (empty($context->afterSaleData['audit_at'])) {
            $updateData = [
                'audit_id'   => $context->operator['id'] ?? 0,
                'audit_user' => $context->operator['name'] ?? '',
                'audit_at'   => time(),
            ];
        }
        $updateData['reissue_order_no']    = $reissueOrderNo;
        $updateData['reissue_order_id']    = $reissueOrderId;
        $updateData['audit_last_at']       = time();
        $updateData['audit_operator_id']   = $context->operator['id'] ?? 0;
        $updateData['audit_operator_name'] = $context->operator['name'] ?? '';

        $updateData['status'] = AfterSaleStatusEnum::WAIT_FOR_REISSUE;

        $updateData['updated_at'] = time();

        //开启事务
        $transaction = AfterSaleRepository::instance()->getConnection()->beginTransaction();
        try {
            AfterSaleRepository::instance()->updateData(['after_sale_no' => $context->afterSaleNo], $updateData);
            if (!empty($context->params['reissue_type'])) {
                AfterSaleGoodsRepository::instance()->updateData(['after_sale_no' => $context->afterSaleNo], ['reissue_type' => $context->params['reissue_type'], 'updated_at' => time()]);
            }
            // 插入日志
            AfterSaleLogRepository::instance()->insertLog(
                $context->afterSaleNo,
                $context->afterSaleData['status'],
                $updateData['status'],
                9102,
                ['supplement' => $pushResult['data']['reissueOrderNo'] ?? ''],
                $context->operator,
                $context->params,
                '',
                $updateData
            );
            $transaction->commit();

        } catch (\Throwable $e) {
            $transaction->rollBack();
            //todo 调用取消amd补发单

            \Yii::error('auditPassHandle执行异常:' . $e->getMessage() . $e->getTraceAsString());
            throw new \Exception($e->getMessage(), 1930249);
        }
    }

    public function cancelStatusCheck(AuditContext $context)
    {
        if (!in_array($context->afterSaleData['status'], [
            AfterSaleStatusEnum::CUSTOMER_SERVICE_REVIEW,
            AfterSaleStatusEnum::TO_AUDIT,
            AfterSaleStatusEnum::WAITING_FOR_THE_PAYMENT,
            AfterSaleStatusEnum::CONTENT_CONFIRMED,
            AfterSaleStatusEnum::CONSULT
        ])) {
            throw new \Exception('该售后单状态，不允许操作取消售后，请检查', 2030049);
        }
    }

    public function reissueCancelHandle(AuditContext $context)
    {
        // 如果当前状态是待支付，还要取消支付单
        if ($context->afterSaleData['status'] == AfterSaleStatusEnum::WAITING_FOR_THE_PAYMENT) {
            $paymentCancleResult = PayInternal::instance()->paymentCancel($context->afterSaleData['pay_payment_no']);
            g_log_info('cancelHandlePaymentCancel', '补发单取消处理结果', [$paymentCancleResult, $context->afterSaleData]);
        }

        if ($context->afterSaleData['pay_amount'] == 0 || empty($context->afterSaleData['pay_at'])) {
            $changeTostatus = AfterSaleStatusEnum::CANCEL;
            $supplement     = '不需要退款';
        } else {
            $refundNo       = $this->createdRefund($context->afterSaleData, $context->operator, $context->afterSaleData['pay_amount'],
                $context->afterSaleData['pay_payment_no'], $context->afterSaleGoodsData);
            $changeTostatus = AfterSaleStatusEnum::REFUNDING;
            $supplement     = '需要退款,退款单号:' . $refundNo;
        }

        $updateData = [
            'status'            => $changeTostatus,
            'cancel_at'         => time(),
            'updated_at'        => time(),
            'cancel_reason_str' => $context->remark,
            'reject_note'       => $context->rejectRemark,
        ];
        //有退款单号，添加到更新数据里
        if (!empty($refundNo)) {
            $updateData['refund_no'] = $refundNo;
        }

        //不知道是拒绝理由，还是取消理由，代码先先留着
        if (is_array($context->rejectReasonList) && !empty($context->rejectReasonList[0]) && is_string($context->rejectReasonList[0])) {
            $updateData['reject_reason1'] = $context->rejectReasonList[0];
        }
        if (is_array($context->rejectReasonList) && !empty($context->rejectReasonList[1]) && is_string($context->rejectReasonList[1])) {
            $updateData['reject_reason2'] = $context->rejectReasonList[1];
        }
        //记录到日志的文案
        $logRemark  = sprintf('%s/%s %s', $updateData['reject_reason1'] ?? '', $updateData['reject_reason2'] ?? '', $updateData['reject_note']);
        $logRemark  = trim(trim($logRemark, '/'));
        $supplement .= ' ' . $logRemark;

        //开启事务
        $transaction = AfterSaleRepository::instance()->getConnection()->beginTransaction();
        try {
            AfterSaleRepository::instance()->updateData(['after_sale_no' => $context->afterSaleNo], $updateData);
            // 插入日志
            AfterSaleLogRepository::instance()->insertLog(
                $context->afterSaleNo,
                $context->afterSaleData['status'],
                $updateData['status'],
                9104,
                ['supplement' => $supplement],
                $context->operator,
                $context->params,
                '',
                $updateData
            );
            $transaction->commit();

        } catch (\Throwable $e) {
            $transaction->rollBack();
            \Yii::error('cancelOrderHandle执行异常:' . $e->getMessage() . $e->getTraceAsString());
            throw new \Exception($e->getMessage(), 1931049);
        }
    }

    /**
     * ams系统通过补发单
     *
     * 1.创建补单支付单
     * 2.如果需要付款，售后单状态变成待支付；如果无需付款，则生成补发订单，并完结售后单
     *
     * @return array
     */
    private function amsPass($params)
    {
        $afterSale = AfterSaleRepository::instance()->getAfterSaleByReissueNo($params['reissue_no']);
        if (empty($afterSale)) {
            return $this->returnError(1937147, 'after sale is empty');
        }
        if (AfterSaleStatusEnum::WAIT_FOR_REISSUE != $afterSale['status']) {
            return $this->returnError(1938149, '售后单处于非待补发订单生成状态，无法处理');
        }
        //创建补发支付单
        $paymentResult = $this->createReissuePayment($afterSale['after_sale_no'], $afterSale['order_no'], $afterSale['pay_amount'], $afterSale['currency'], $afterSale['site']);
        if (1 != $paymentResult['code']) {
            return $this->returnError(1938150, '创建补发单支付单异常：' . $paymentResult['info']);
        }
        $payment = $paymentResult['data'];
        if (1 == $payment['is_paid']) {
            $updateData = [
                'status'         => AfterSaleStatusEnum::THE_PAYMENT_PAID,
                'pay_at'         => $payment['pay_at'] ?? 0,
                'is_paid'        => 1,
                'pay_payment_no' => $payment['payment_no'],
                'updated_at'     => time(),
            ];
            AfterSaleRepository::instance()->updateData(['after_sale_no' => $afterSale['after_sale_no']], $updateData);
            // 插入日志
            AfterSaleLogRepository::instance()->insertLog(
                $afterSale['after_sale_no'],
                $afterSale['status'],
                $updateData['status'],
                0,
                [],
                $params['operator'],
                $params,
                sprintf('关联支付单号：%s 已支付', $payment['payment_no']),
                $updateData
            );
            $afterSale = array_merge($afterSale, $updateData);
            //已支付，创建补发订单
            $createReissueOrderResult = $this->createReissueOrder($afterSale);
            if (1 != $createReissueOrderResult['code']) {
                return $this->returnError(1938151, '创建补发订单异常：' . $createReissueOrderResult['info']);
            }
            $afterSale['mall_reissue_order_no'] = $createReissueOrderResult['data']['order_no'];
            //更新售后
            return $this->doneHandle($afterSale, $params['operator'], 'ams系统通过补发单完成，商城补发订单号：' . $afterSale['mall_reissue_order_no']);

        } else {
            //未支付，变成待支付状态
            $updateData = [
                'status'         => AfterSaleStatusEnum::WAITING_FOR_THE_PAYMENT,
                'pay_payment_no' => $payment['payment_no'],
                'updated_at'     => time(),
            ];
            AfterSaleRepository::instance()->updateData(['after_sale_no' => $afterSale['after_sale_no']], $updateData);
            // 插入日志
            AfterSaleLogRepository::instance()->insertLog(
                $afterSale['after_sale_no'],
                $afterSale['status'],
                $updateData['status'],
                0,
                [],
                $params['operator'],
                $params,
                sprintf('关联支付单号：%s 待支付', $payment['payment_no']),
                $updateData
            );
            return $this->returnSuccess();
        }
    }

    /**
     * 创建补发订单
     */
    private function createReissueOrder($afterSale)
    {
        $afterSaleGoodsList    = AfterSaleGoodsRepository::instance()->getListByAfterSaleNo($afterSale['after_sale_no']);
        $applyReissueOrderInfo = [
            'order_no'         => $afterSale['order_no'],
            'order_goods_list' => [],
        ];
        foreach ($afterSaleGoodsList as $goods) {
            $applyReissueOrderInfo['order_goods_list'][] = [
                'order_goods_id' => $goods['order_goods_id'],
                'sku_id'         => $goods['sku_id'],
                'sku_code'       => $goods['sku_code'],
                'qty'            => $goods['qty'],
            ];
        }
        //获取ams补发订单详情
        $reissueResult = AmsApi::instance()->getReissueOrderInfo($afterSale['reissue_order_id']);
        if (1 != $reissueResult['code']) {
            return $this->returnError(2937148, $reissueResult['info']);
        }
        //生成商城端的补发订单
        $processingContent                    = @json_decode($afterSale['processing_content'], true);
        $processingContent['delivery_method'] = $afterSale['reissue_delivery_method'];
        $createResult                         = OrderService::instance()->createReissueOrder($afterSale['after_sale_no'], $applyReissueOrderInfo, $processingContent, $afterSale['pay_payment_no'],
            $afterSale['pay_type'], $afterSale['customer_service_discount'], $reissueResult['data'], $afterSale['user_id'], $processingContent['address_info']['country_code'],
            $processingContent['address_info']['state_code'], $afterSale['site'], $afterSale['currency'], $afterSale['language'], $processingContent['address_info']['zip'], 'manage');
        if (1 != $createResult['code']) {
            return $this->returnError(2937149, $createResult['info']);
        }
        return $this->returnSuccess($createResult['data']);
    }

    /**
     * ams系统拒绝补发单
     *
     * @return array
     */
    private function amsReject($params)
    {
        $afterSale = AfterSaleRepository::instance()->getAfterSaleByReissueNo($params['reissue_no']);
        if (empty($afterSale)) {
            return $this->returnError(1937148, 'after sale is empty');
        }
        $updateData = [
            'status'     => AfterSaleStatusEnum::CUSTOMER_SERVICE_REVIEW,
            'updated_at' => time()
        ];
        //开启事务
        $transaction = AfterSaleRepository::instance()->getConnection()->beginTransaction();
        try {
            AfterSaleRepository::instance()->updateData(['after_sale_no' => $afterSale['after_sale_no']], $updateData);
            // 插入日志
            AfterSaleLogRepository::instance()->insertLog(
                $afterSale['after_sale_no'],
                $afterSale['status'],
                $updateData['status'],
                9107,
                ['reject_type_code' => $params['reject_type_code'] ?? '', 'reject_type_value' => $params['reject_type_value'] ?? '', 'reject_msg' => $params['reject_msg'] ?? ''],
                $params['operator'],
                $params,
                '',
                $updateData
            );
            $transaction->commit();
            return $this->returnSuccess();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            \Yii::error('amsReject执行异常:' . $e->getMessage() . $e->getTraceAsString());
            return $this->returnError(1937149, 'amsReject执行异常:' . $e->getMessage());
        }
    }


    /**
     * 设置下个售后状态
     *
     * @return void
     */
    public function setNextStatus(ManageApplyContext $context)
    {
        if (!empty($context->afterSaleData['pay_payment_no'])) {
            //补发驳回或审核拒绝的场景下，如果补发驳回或审核拒绝前无需支付，再次提交需要支付，则需要更新状态为待支付
            if (1 == $context->afterSaleData['is_paid'] && 0 == $context->afterSaleData['pay_amount'] && $context->applyPayAmount > 0) {
                $context->initStatus = AfterSaleStatusEnum::WAITING_FOR_THE_PAYMENT;
            }
        }
    }
}
