<?php


namespace common\services\order;

use App\Email\Email;
use App\Utils\BaseFunction;
use App\Utils\EncryptUtil;
use App\Utils\EnterpriseWechatMessage;
use App\Utils\MyFunction;
use App\Utils\NodeExecutionEngine;
use App\Utils\RabbitMq;
use App\Utils\UserSensitiveFormat;
use AppConsole\services\region\RegionZipService;
use AppOrderApi\forms\AddressForm;
use AppPayApi\lib\Code;
use common\api\AfterSaleApi;
use common\api\OmsApi;
use common\api\PayInternal;
use common\BaseContext;
use common\BaseService;
use common\enums\afterSale\AfterSaleProcessTypeEnum;
use common\enums\common\rate\EstimateTaxEnum;
use common\enums\order\ExchangeGoodsTypeEnum;
use common\enums\order\InvoiceFormatTypeEnum;
use common\enums\order\OfflineStore;
use common\enums\recommend\OrderProductRecommendEnums;
use common\enums\user\UserTypeEnum;
use common\models\order\OrderGoods;
use common\models\order\OrderLog;
use common\paramModule\coupon\CouponFilterParam;
use common\redis\app\VersionRedis;
use common\redis\common\LockHandleRedis;
use common\redis\order\DeliveryRedis;
use common\redis\order\OrderRedis;
use common\redis\order\StockRedis;
use common\redis\pay\PaymentRedis;
use common\repositorys\order\AfterSaleGoodsRepository;
use common\repositorys\order\AfterSaleRepository;
use common\repositorys\order\OrderAddressModifyLogRepository;
use common\repositorys\order\OrderAddressRepository;
use common\repositorys\order\OrderAmountRepository;
use common\repositorys\order\OrderDeliveryRepository;
use common\repositorys\order\OrderDisputeRepository;
use common\repositorys\order\OrderErpLogRepository;
use common\repositorys\order\OrderGoodsInstallationServiceRepository;
use common\repositorys\order\OrderGoodsProtectionPlanRepository;
use common\repositorys\order\OrderGoodsRepository;
use common\repositorys\order\OrderGoodsSuitRepository;
use common\repositorys\order\OrderGoodsValueAddedServiceRepository;
use common\repositorys\order\OrderLogRepository;
use common\repositorys\order\OrderPickUpAddressRepository;
use common\repositorys\order\OrderReissueDeliveryRepository;
use common\repositorys\order\OrderRemarksRepository;
use common\repositorys\order\OrderRepository;
use common\repositorys\order\TransactionSnapshotRepository;
use common\repositorys\pay\BillAddressRepository;
use common\repositorys\pay\PaymentRepository;
use common\repositorys\store\OfflineStoreRepository;
use common\repositorys\user\CountryAreaRepository;
use common\repositorys\user\UserRepository;
use common\services\activity\ActivityProductService;
use common\services\aftersale\AfterSaleFormatService;
use common\services\klaviyo\KlaviyoService;
use common\services\klaviyo\OrderProductRecommendService;
use common\services\logistics\nodes\ShippingFeeNode;
use common\services\logistics\ShippingRuleService;
use common\services\market\coupon\CouponUseService;
use common\services\order\address\CheckService;
use common\services\order\contexts\email\GetOrderEmailNotifyDataContext;
use common\services\order\contexts\notify\NotifyPaidContext;
use common\services\order\contexts\order\GoodsListCheckContext;
use common\services\order\contexts\order\ModifyAddressContext;
use common\services\order\contexts\order\OrderCancelContext;
use common\services\order\contexts\order\OrderDispatchContext;
use common\services\order\contexts\order\OrderPaidAuditContext;
use common\services\order\contexts\order\OrderReceiveContext;
use common\services\order\contexts\order\OrderRefundContext;
use common\services\order\contexts\order\OrderRemarksContext;
use common\services\order\contexts\order\OrderStatusNotifyContext;
use common\services\order\contexts\order\PushReceiveOrderContext;
use common\services\order\contexts\orderQuery\OrderDetailContext;
use common\services\order\contexts\place\PlaceOrderContext;
use common\services\order\contexts\place\ValetOrderContext;
use common\services\order\format\OrderInvoiceFormatService;
use common\services\order\nodes\common\ActivityNode;
use common\services\order\nodes\common\CheckStoreOrderNode;
use common\services\order\nodes\common\EtaPickUpDataGetNode;
use common\services\order\nodes\common\GoodsListCheckNode;
use common\services\order\nodes\common\GoodsListGetNode;
use common\services\order\nodes\common\GoodsParamsFormatNode;
use common\services\order\nodes\common\LimitedTimeReductionNode;
use common\services\order\nodes\common\OfflineStoreLimitedTimeReductionNode;
use common\services\order\nodes\common\OperatorHandleNode;
use common\services\order\nodes\common\OrderAddressGetNode;
use common\services\order\nodes\common\OrderAllAddressGetNode;
use common\services\order\nodes\common\OrderChangeSendRabbitMQNode;
use common\services\order\nodes\common\OrderGetNode;
use common\services\order\nodes\common\OrderGoodsGetNode;
use common\services\order\nodes\common\OrderGoodsValueAddedServiceGetNode;
use common\services\order\nodes\common\OrderPickAddressGetNode;
use common\services\order\nodes\common\PointsDiscountNode;
use common\services\order\nodes\common\RefundCreateNode;
use common\services\order\nodes\common\SuitDiscountNode;
use common\services\order\nodes\common\SuitPriceHandleNode;
use common\services\order\nodes\confirm\CustomerServiceHandleNode;
use common\services\order\nodes\confirm\StoreManagerDiscountNode;
use common\services\order\nodes\email\GetOrderEmailNotifyDataNode;
use common\services\order\nodes\exchange\CancelExchangeNode;
use common\services\order\nodes\installationService\InstallationServiceNode;
use common\services\order\nodes\order\DispatchV2HandleNode;
use common\services\order\nodes\order\ModifyAddressNode;
use common\services\order\nodes\order\OrderClosedNode;
use common\services\order\nodes\order\OrderGoodsCaptureAddNode;
use common\services\order\nodes\order\OrderPaidAuditHandleNode;
use common\services\order\nodes\order\OrderRefundCheckNode;
use common\services\order\nodes\order\ProtectionPlanNode;
use common\services\order\nodes\order\ReceiveHandleNode;
use common\services\order\nodes\order\SensorsDataNode;
use common\services\order\nodes\order\TaxHandleNode;
use common\services\order\nodes\place\CouponUseNode;
use common\services\order\nodes\place\DataFormatNode;
use common\services\order\nodes\place\FormatGoodsServiceEtaNode;
use common\services\order\nodes\place\OrderAddressAddNode;
use common\services\order\nodes\place\ValetOrderCalculateNode;
use common\services\order\nodes\valueAddedService\ValueAddedServiceCheckNode;
use common\services\order\nodes\valueAddedService\ValueAddedServiceNode;
use common\services\order\service\InvoiceService;
use common\services\order\service\OrderAddressService;
use common\services\order\service\OrderGoodsValueAddedServiceService;
use common\services\orderV4\OrderV4Service;
use common\services\pay\BraintreeService;
use common\services\pay\KlarnaService;
use common\services\pay\PaymentRequestQueryService;
use common\services\pay\PaymentService;
use common\services\pay\PayService;
use common\services\product\ProductRecommendService;
use common\services\product\ProductService;
use common\services\product\ProductTermsOfService;
use common\services\sensorsdata\SensorsDataBaseEvent;
use common\services\sensorsdata\SensorsDataService;
use common\services\site\BasicDataService;
use common\services\store\OfflineStoreService;
use common\services\system\CommonService;
use common\services\system\ConfigService;
use common\services\system\SiteService;
use common\services\tools\EncryptService;
use common\services\tools\PhoneNumberService;
use common\services\user\AddressService;
use common\services\user\JwtService;
use common\services\user\nodes\UserInfoGetNode;
use common\services\usps\UspsService;
use Yii;
use yii\db\Exception;
use yii\helpers\Console;

class OrderService extends BaseService
{

    /**
     * 商品校验
     *
     * @param $params
     *
     * @return array
     * @author 白杨
     * @Date   2021/3/9 上午11:07
     */
    public function goodsListCheck($params)
    {
        g_log_info('orderCancel.log', '商品校验', $params);
        try {
            //组织上下文数据
            $context = new GoodsListCheckContext();
            $this->contextInit($context, $params);

            $nodeChain = [
                GoodsParamsFormatNode::instance(),
                GoodsListGetNode::instance(),
                GoodsListCheckNode::instance(),
                // 显示活动，放在activityNode前
                LimitedTimeReductionNode::instance(),
                ActivityNode::instance(),
                //套装价格价格优化处理
                SuitPriceHandleNode::instance(),
                // 自提Eta数据
                EtaPickUpDataGetNode::instance(),
            ];

            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);

            // 把无法通过库存校验的skuid取出来
            if (!empty($context->checkGoods)) {
                $result['code'] = 800305;
            }
            switch ($result['code']) {
                case 1:
                    g_log_info('goodsListCheck.log', '商品校验,成功', [$result, $params]);
                    return $this->returnSuccess($context->response, 'success');
                case 800305:
                    g_log_error('goodsListCheck.log', '商品校验库存不足', [$result, $params]);
                    return $this->returnFormat($result['code'], $context->checkGoods, $result['code']);
                default:
                    g_log_error('goodsListCheck.log', '商品校验,失败', [$result, $params]);
                    return $result;
            }

        } catch (\Exception $e) {
            g_log_error('goodsListCheck.log', '商品校验,异常', [$e->getMessage() . $e->getTraceAsString(), $params]);
            return $this->returnFormat(800402, null, $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 未支付取消订单
     *
     * @param $orderNo
     * @param $closedReason
     * @param $source
     * @param $operator
     *
     * @return array
     * @author 白杨
     * @Date   2021/1/19 上午9:27
     */
    public function orderCancel($params)
    {
        g_log_info('orderCancel.log', '取消订单', $params);
        try {
            //组织上下文数据
            $context = new OrderCancelContext();
            $this->contextInit($context, $params);
            $context->orderNo      = $params['order_no'];
            $context->closedReason = $params['closed_reason'];
            $context->source       = $params['source'];
            $context->isSendMq     = true;

            $context->isLimitedTimeReductionReturnStock = true;


            //限制类型
            if (!in_array($context->source, [
                OrderRepository::CLOSED_TYPE_UNPAID_EXPIRE,
                OrderRepository::CLOSED_TYPE_USER_CANCEL,
                OrderRepository::CLOSED_TYPE_ADMIN_CANCEL,
                OrderRepository::CLOSED_TYPE_PAYMENT_VOID,
                OrderRepository::CLOSED_TYPE_AFTERSALE_REFUND,//商品维度，已全部退款的订单关单
                OrderRepository::CLOSED_TYPE_CHARGE_BACK, // charge back关闭订单
            ])) {
                return $this->returnError(800400);
            }

            if ($context->source == OrderRepository::CLOSED_TYPE_USER_CANCEL) {
                //用户取消必须有用户uid
                $context->userId = BaseFunction::instance()->getUserId($context->token);
                if (empty($context->userId)) {
                    return $this->returnError(401);
                }
            } else {
                //订单，订单商品数据有入参就不再次查询了
                if (isset($params['order']) && !empty($params['order'])) {
                    $context->orderData = $params['order'];
                }
                if (isset($params['order_goods']) && !empty($params['order_goods'])) {
                    $context->orderGoodsData = $params['order_goods'];
                }
            }

            $nodeChain = [
                OperatorHandleNode::instance(),
                OrderGetNode::instance(),
                OrderGoodsGetNode::instance(),
                OrderAddressGetNode::instance(),
                CancelExchangeNode::instance(),
                OrderClosedNode::instance(),
                OrderChangeSendRabbitMQNode::instance(),
            ];

            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
            if (1 == $result['code']) {
                g_log_info('orderCancel.log', '取消订单,成功', [$result, $params]);
                return $this->returnSuccess($context->response, 'success');
            } else {
                g_log_error('orderCancel.log', '取消订单,失败', [$result, $params]);
                return $result;
            }
        } catch (\Exception $e) {
            g_log_error('orderCancel.log', '取消订单,异常', [$e->getMessage() . $e->getTraceAsString(), $params]);
            return $this->returnFormat(800402, null, $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 已支付的退款，关单
     *
     * @param $params
     *
     * @return array
     * @author 白杨
     * @Date   2021/3/26 下午7:02
     */
    public function orderRefund($params)
    {
        g_log_info('orderRefund.log', '发货前整单退款，入参', $params);
        try {
            //组织上下文数据
            $context = new OrderRefundContext();
            $this->contextInit($context, $params);
            $context->orderNo      = $params['order_no'];
            $context->closedReason = ($params['closed_reason'] ?? '') . 'email:' . ($params['email'] ?? '');
            $context->source       = $params['source'] ?? 0;

            //限制类型
            if (!in_array($context->source, [
                OrderRepository::CLOSED_TYPE_USER_REFUND,
                OrderRepository::CLOSED_TYPE_ERP_UNABLE
            ])) {
                return $this->returnError(800400);
            }

            //用户退款，必须登录
            if ($context->source == OrderRepository::CLOSED_TYPE_USER_REFUND) {
                $context->userId = BaseFunction::instance()->getUserId($context->token);
                if (empty($context->userId)) {
                    return $this->returnError(401);
                }
            }

            // 只有用户退款的时候，返回库存
            if ($context->source == OrderRepository::CLOSED_TYPE_USER_REFUND) {
                $context->isReturnStock = true;
            }

            $nodeChain = [
                //操作人处理
                OperatorHandleNode::instance(),
                //商品数据
                OrderGetNode::instance(),
                //状态校验
                OrderRefundCheckNode::instance(),
                //订单商品，地址，
                OrderGoodsGetNode::instance(),
                OrderAddressGetNode::instance(),
                //创建退款单
                RefundCreateNode::instance(),
                //关闭订单
                OrderClosedNode::instance(),
                //上报
                SensorsDataNode::instance(),
                //发送mq
                OrderChangeSendRabbitMQNode::instance(),
            ];

            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
            if (1 == $result['code']) {
                g_log_info('orderRefund.log', '发货前整单退款,成功', [$result, $params]);
                return $this->returnSuccess($context->response, 'success');
            } else {
                g_log_error('orderRefund.log', '发货前整单退款,失败', [$result, $params]);
                return $result;
            }
        } catch (\Exception $e) {
            g_log_error('orderRefund.log', '发货前整单退款,异常', [$e->getMessage() . $e->getTraceAsString(), $params]);
            return $this->returnFormat(800402, null, $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 订单状态改变通知
     *
     * @param string $orderNo      订单号
     * @param int    $orderStatus  订单状态值
     * @param array  $operatorInfo 操作人信息['name' => 'xxx', 'id' => 111, 'type' => 1]
     *
     * @return array
     */
    public function orderStatusNotify($orderNo, $orderStatus, $operatorInfo = [])
    {
        //组织上下文数据
        $context = new OrderStatusNotifyContext();
        $this->contextInit($context, ['operator' => $operatorInfo]);
        $context->orderNo     = $orderNo;
        $context->orderStatus = $orderStatus;
        $context->isSendMq    = true;
        $context->operator    = $operatorInfo;

        $nodeChain = [
            //操作人处理
            OperatorHandleNode::instance(),
            //商品数据
            OrderGetNode::instance(),
            //状态校验
            OrderRefundCheckNode::instance(),
            //订单商品，地址，
            OrderGoodsGetNode::instance(),
            OrderAddressGetNode::instance(),
            //发送mq
            OrderChangeSendRabbitMQNode::instance(),
        ];
        $result    = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
        g_log_info('orderStatusNotify.log', '订单状态改变通知', ['request' => ['order_no' => $orderNo, 'order_status' => $orderStatus, 'operator' => $operatorInfo], 'response' => $result]);
        return $result;
    }

    /**
     *
     * @param $params
     *
     * @return array
     * @author 白杨
     * @Date   15/9/21 下午4:49
     */
    public function orderDispatchV2($params)
    {
        $fileName = 'orderDispatchNew.log';
        g_log_info($fileName, '订单发货', $params);

        $orderNo = $params['salesOrderNo'];
        $this->addOrderErpLog($orderNo, 'order_dispatch', $params, []);
        //补发单，转到另外一个方法处理
        if (strtoupper(substr($orderNo, 0, 2)) == 'BF') {
            $result = $this->reissueDispatch($params);
            $this->addOrderErpLog($orderNo, 'order_dispatch', [], $result);
            return $result;
        }

        try {
            //组织上下文数据
            $context = new OrderDispatchContext();
            $this->contextInit($context, $params);
            $context->orderNo      = $params['salesOrderNo'];
            $dispatchInfo          = $params['dispatchInfo'];
            $context->dispatchInfo = is_string($dispatchInfo) ? json_decode($dispatchInfo, 1) : $dispatchInfo;

            $nodeChain = [
                OperatorHandleNode::instance(),
                OrderGetNode::instance(),
                OrderGoodsGetNode::instance(),
                OrderAddressGetNode::instance(),
                OrderAllAddressGetNode::instance(),
                DispatchV2HandleNode::instance(),
                OrderGoodsCaptureAddNode::instance(),
                OrderChangeSendRabbitMQNode::instance(),
            ];

            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);

            $this->addOrderErpLog($orderNo, 'order_dispatch', [], $result);
            if (1 == $result['code']) {
                g_log_info($fileName, '订单发货,成功', [$result, $params]);
                return $this->returnSuccess($context->response, 'success');
            } else {
                g_log_error($fileName, '订单发货,失败', [$result, $params]);
                return $result;
            }
        } catch (\Exception $e) {
            $this->addOrderErpLog($orderNo, 'order_dispatch', [], ['code' => 404, 'info' => $e->getMessage(), 'data' => $e->getTraceAsString()]);
            g_log_error($fileName, '订单发货,异常', [$e->getMessage() . $e->getTraceAsString(), $params]);
            return $this->returnFormat(800406, null, $e->getMessage() . $e->getTraceAsString());
        }
    }

    public function reissueDispatch($params)
    {
        $reissueNo = $params['salesOrderNo'];
        $afterSale = AfterSaleRepository::instance()->getInfoByReissueNo($reissueNo);
        if (empty($afterSale)) {
            return $this->returnError(4288821, '没有对应到售后单');
        }

        $afterSaleGoodsList = AfterSaleGoodsRepository::instance()->getListByAfterSaleNo($afterSale['after_sale_no']);
        if (empty($afterSaleGoodsList)) {
            return $this->returnError(4288822, '没有对应到售后单商品');
        }

        $dispatchList = is_string($params['dispatchInfo']) ? json_decode($params['dispatchInfo'], 1) : $params['dispatchInfo'];
        $operator     = is_string($params['operator']) ? json_decode($params['operator'], 1) : $params['operator'];


        $orderNo = $afterSale['order_no'];

        $insertDeliveryData = [];
        foreach ($dispatchList as $dispatch) {
            //oms java系统使用的毫秒时间戳
            $dispatchedAt = floor(($dispatch['delivery_time'] ?? 0) / 1000);
            if (empty($dispatchedAt)) {
                // 企业微信报警
                $msg = [
                    '### 补发发货接口，没有发货时间字段',
                    json_encode($params, 320),
                ];
                EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'dispatch');
                throw new \Exception('delivery_time is empty', 810405);
            }

            //匹配skucode，如果跟售后商品匹配上了，就给对应的order_goods_id,如果没有匹配上，就是所有的售后商品的order_goods_id
            $orderGoodsIdList = [];
            foreach ($afterSaleGoodsList as $afterSaleGoods) {
                if ($afterSaleGoods['sku_code'] != $dispatch['sku_code']) {
                    $orderGoodsIdList[] = $afterSaleGoods['order_goods_id'];
                } else {
                    $orderGoodsIdList = [$afterSaleGoods['order_goods_id']];
                    break;
                }
            }
            foreach ($orderGoodsIdList as $orderGoodsId) {
                $insertDeliveryData[] = [
                    'order_no'                => $orderNo,
                    'order_goods_id'          => $orderGoodsId,
                    'tracking_number'         => $dispatch['transport_package'] ?? '',
                    'tracking_company'        => $dispatch['transport'] ?? '',
                    'qty'                     => $dispatch['qty'] ?? 0,//累计已发数量
                    'surplus_qty'             => $dispatch['surplus_qty'] ?? 0,//剩余未发数量
                    'this_time_qty'           => $dispatch['this_time_qty'] ?? 0,//本次已发
                    'dispatch_content'        => $dispatch['dispatch_content'] ?? '',
                    'operator_type'           => $operator['type'] ?? '4',
                    'operator_id'             => $operator['id'] ?? 0,
                    'operator_name'           => $operator['name'] ?? '补发发货',
                    'delivery_time'           => $dispatchedAt,
                    'ship_type'               => $dispatch['ship_type'] ?? 0,
                    'package_version'         => $dispatch['package_version'] ?? 0,
                    'install_desc_url'        => $dispatch['install_desc_url'] ?? '',
                    'sales_out_no'            => $dispatch['sales_out_no'] ?? '',
                    'delivery_warehouse_name' => $dispatch['delivery_warehouse_name'] ?? '',
                    'reissue_order_no'        => $reissueNo,
                    'dispatch_info'           => json_encode($dispatch, 320),
                    'created_at'              => time(),
                    'updated_at'              => time(),
                ];
            }
        }
        if (empty($insertDeliveryData)) {
            return $this->returnError(4288823, '插入数据为空');
        }

        $result = OrderReissueDeliveryRepository::instance()->insertAll($insertDeliveryData);
        if (empty($result)) {
            return $this->returnError(4288824, '数据插入失败，请重试');
        }

        return $this->returnSuccess();
    }

    /**
     * @param       $orderNo
     * @param       $actionType
     * @param array $request
     * @param array $response
     *
     * @return bool
     */
    public function addOrderErpLog($orderNo, $actionType, $request = [], $response = [])
    {
        $insert = [
            'order_no'    => $orderNo,
            'action_type' => $actionType,
            'request'     => @json_encode($request),
            'response'    => @json_encode($response),
            'created_at'  => time(),
            'updated_at'  => time(),
        ];

        return OrderErpLogRepository::instance()->insertModel($insert);
    }

    /**
     * 确认收货
     *
     * @param $params
     *
     * @return array
     * @author 白杨
     * @Date   2021/2/19 下午4:07
     */
    public function orderReceive($params)
    {
        g_log_info('orderReceive.log', '订单确认收货', $params);
        try {
            //组织上下文数据
            $context = new OrderReceiveContext();
            $this->contextInit($context, $params);
            $context->orderNo      = $params['order_no'];
            $context->orderGoodsId = $params['order_goods_id'] ?? 0;

            if (!empty($params['user_id'])) {
                $context->userId = $params['user_id'];
            } else {
                $context->userId = BaseFunction::instance()->getUserId($context->token);
            }
            if (empty($context->userId)) {
                return $this->returnError('401');
            }

            $nodeChain = [
                OperatorHandleNode::instance(),
                OrderGetNode::instance(),
                OrderGoodsGetNode::instance(),
                OrderAddressGetNode::instance(),
                OrderAllAddressGetNode::instance(),
                ReceiveHandleNode::instance(),
                OrderChangeSendRabbitMQNode::instance(),
            ];

            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
            if (1 == $result['code']) {
                g_log_info('orderReceive.log', '订单确认收货,成功', [$result, $params]);
                return $this->returnSuccess($context->response, 'success');
            } else {
                g_log_warning('orderReceive.log', '订单确认收货,失败', [$result, $params]);
                return $result;
            }
        } catch (\Exception $e) {
            g_log_error('orderReceive.log', '订单确认收货,异常', [$e->getMessage() . $e->getTraceAsString(), $params]);
            return $this->returnFormat(800403, null, $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 订单状态修改成支付待审核
     *
     * @param $params
     *
     * @return array
     * @author 白杨
     * @Date   2021/2/03 下午6:57
     */
    public function updateOrderPaidAudit($params)
    {
        g_log_info('orderReceive.log', '支付待审核', $params);
        try {
            //组织上下文数据
            $context = new OrderPaidAuditContext();
            $this->contextInit($context, $params);
            $context->orderNo     = $params['order_no'];
            $context->auditReason = $params['audit_reason'];

            $nodeChain = [
                OperatorHandleNode::instance(),
                OrderGetNode::instance(),
                OrderGoodsGetNode::instance(),
                OrderAddressGetNode::instance(),
                OrderPaidAuditHandleNode::instance(),
                OrderChangeSendRabbitMQNode::instance(),
            ];

            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
            if (1 == $result['code']) {
                g_log_info('orderReceive.log', '支付待审核,成功', [$result, $params]);
                return $this->returnSuccess($context->response, 'success');
            } else {
                g_log_error('orderReceive.log', '支付待审核,失败', [$result, $params]);
                return $result;
            }
        } catch (\Exception $e) {
            g_log_error('orderReceive.log', '支付待审核,异常', [$e->getMessage() . $e->getTraceAsString(), $params]);
            return $this->returnFormat(800403, null, $e->getMessage() . $e->getTraceAsString());
        }

    }

    /**
     * 修改订单地址
     *
     * @param $params
     *
     * @return array
     * @author 白杨
     * @Date   2021/2/25 下午8:38
     */
    public function modifyAddress($params)
    {
        g_log_info('modifyAddress.log', '修改订单地址', $params);
        try {
            //组织上下文数据
            $context = new ModifyAddressContext();
            $this->contextInit($context, $params);
            $context->orderNo = $params['order_no'];

            //是否需要检查区域能否支持当前服务 结算页进来不校验拦截
            $context->needCheckValueAddedArea = (!empty($params['__path']) && $params['__path'] == '/order/place') ? 0 : 1;
            //是否需要检测次日达弹窗提示 结算页进来不校验次日达信息弹窗
            $context->needCheckNextDayDelivery = (!empty($params['__path']) && $params['__path'] == '/order/place') ? 0 : (!empty($params['confirm_edit']) ? 0 : 1);

            // 管理后台修改地址强校验
            if ($context->pf == 'manage') {
                $result = CheckService::instance()->checkOrderAddress($params);
                if ($result['code'] != 1) {
                    return $this->returnError($result['code'], $result['info']);
                }
            }

            $nodeChain = [
                OperatorHandleNode::instance(),
                OrderGetNode::instance(),
                OrderGoodsGetNode::instance(),
                ValueAddedServiceCheckNode::instance(),
                OrderGoodsValueAddedServiceGetNode::instance(),
                OrderAddressGetNode::instance(),
                ModifyAddressNode::instance(),
            ];

            $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);

            // 订单地址日志
            AddressService::instance()->addAddressLogInRedis(2, $context->orderAddressData, $params, $result, ['operator_type' => $context->operator['type'], 'operator_id' => $context->operator['id']]);

            if (1 == $result['code']) {
                g_log_info('modifyAddress.log', '修改订单地址,成功', [$result, $params]);
                return $this->returnSuccess($context->response, 'success');
            } else {
                if ($result['code'] == 943801) {
                    return $this->returnError($result['code'], $result['info'], $context->errorWhiteGlovesData);
                } elseif ($result['code'] == 111003 || $result['code'] == 111004) {
                    return $this->returnError($result['code'], $result['info'], $context->delayDeliveryData);
                } else {
                    g_log_error('modifyAddress.log', '修改订单地址,失败', [$result, $params]);
                    return $result;
                }
            }
        } catch (\Exception $e) {
            g_log_error('modifyAddress.log', '修改订单地址,异常', [$e->getMessage() . $e->getTraceAsString(), $params]);

            $operator = ['operator_type' => 0, 'operator_id' => 0];
            if (!empty($context) && !empty($context->operator)) {
                $operator = ['operator_type' => $context->operator['type'], 'operator_id' => $context->operator['id']];
            }
            $bizAddress = [];
            if (!empty($context) && !empty($context->orderAddressData)) {
                $bizAddress           = $context->orderAddressData;
                $bizAddress['biz_id'] = $context->orderAddressData['order_address_id'];
            }
            AddressService::instance()->addAddressLogInRedis(2, $bizAddress, $params, ['code' => 404, 'info' => $e->getMessage()], $operator);

            return $this->returnFormat(800402, null, $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 订单相关邮件通知
     *
     * @param $orderNo
     * @param $businessCode string Email::BUSINESS_CODE_ORDER_NON_PAYMENT
     *
     * @author zhengcongfeng by 2021-03-09
     *
     */
    public function orderEmailNotify($orderNo, $businessCode)
    {
        try {
            //step 1 组装参数
            $context          = new GetOrderEmailNotifyDataContext();
            $context->orderNo = $orderNo;
            GetOrderEmailNotifyDataNode::instance()->invokeNode($context);
            if (empty($context->response)) {
                return $this->returnFormat(820000, null, 'Failed to send mail assembly parameters');
            }
            //step 2 获取接收邮箱规则
            if (!empty($context->response['order_address_data']['email'])) {
                $receiveEmail = $context->response['order_address_data']['email'];
            } else {
                $user         = UserRepository::instance()->getUserById($context->response['order_data']['user_id']);
                $receiveEmail = $user['email'];
            }
            // $language     = BaseFunction::instance()->getFormatLanguage(strtolower('it'));
            $language           = $context->response['language'];
            Yii::$app->language = $language;
            // $receiveEmail = 'fengcongzheng@163.com';

            //创建订单只发送给客服 zhengcongfeng by 2021-04-21
            if ($businessCode == Email::BUSINESS_CODE_ORDER_CREATE && !empty(getenv('EMAIL_BACKUP'))) {
                $receiveEmail = getenv('EMAIL_BACKUP');
            }

            //step 3 执行发送
            //send 方法为异步处理
            //sendHandle 方法为同步处理
            $result = Email::instance()->sendEmail($receiveEmail, $businessCode, $context->response['site'], $language, $context->response);
            if (empty($result)) {
                return $this->returnFormat(820001, null, 'Sending mail fail');
            }
            return $this->returnSuccess('success');
        } catch (\Throwable $e) {
            return $this->returnFormat(820002, null, 'Sending mail abnormal' . $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 在线支付完成写入mq，一小时后状态扭转为待发货
     *
     * @param $orderNo
     * @param $pushType 1 是原来的推送方式 2是换货的推送方式
     *
     * @return array
     */
    public function sendDelayDelivery($orderNo, $pushType = 1)
    {
        //延迟1小时通知erp，并扭转状态--测试环境延迟10秒，生产环境延迟3600秒
        if ($pushType == 2) {
            $delaySec = 1;
        } else {

            $delaySec = $this->getDelayHandleUpdateShipTime();
        }

        $orderInfo = [
            'order_no' => $orderNo
        ];

        // 推送mq
        $exchangeName = \Yii::$app->params["order_pay_success_delay_mq"]["exchange_name"];
        $routeKey     = \Yii::$app->params["order_pay_success_delay_mq"]["route_key"];

        $result = RabbitMq::send($exchangeName, $routeKey, @json_encode($orderInfo, JSON_UNESCAPED_UNICODE), $delaySec);
        g_log_info('sendDelayDelivery.log', "发送延迟队列{$orderNo}", ['order_no' => $orderNo, 'result' => $result, 'delaySec' => $delaySec]);
    }

    /**
     * 支付完成修改为待发货延期的时间
     *
     * @return int
     */
    public function getDelayHandleUpdateShipTime()
    {
        return Yii::$app->params['delay_handle_update_ship_time'];
    }

    /**
     * 延迟1小时后通知erp以及更新订单状态
     *
     * @param     $orderData
     *
     * @param int $retry
     *
     * @return array|void
     * @throws \Exception
     */
    public function delayUpdatePaidSuccess($orderData, $retry = 0, $handlerName = '')
    {
        $orderNo      = $orderData['order_no'];
        $lockRedisKey = 'delay-update-paid-success' . $orderNo;
//        $lock         = LockHandleRedis::instance()->lock($lockRedisKey, 10);
//        if ($lock) {
//            return $this->returnError(810507, 'Frequent operation, please try again later!');
//        }
        g_log_info('delayUpdatePaidSuccess.log', '支付成功通知OMS', [$orderNo, $handlerName]);
        try {
            //组织上下文数据
            $context = new NotifyPaidContext();
            $this->contextInit($context, []);
            $context->orderNo = $orderNo;

            OrderGetNode::instance()->invokeNode($context);
            if (empty($context->orderData) || $context->orderData['order_status'] != OrderRepository::STATUS_PAID_SUCCESS) {
                g_log_warning('delayUpdatePaidSuccess.log', '订单号不存在：', [$orderNo]);
                return $this->returnError(1001, 'Order status is not paid');
            }
            $isFailCheckAddress = $context->orderData['is_fail_check_address'];
            if (1 == $context->orderData['is_fail_check_address'] && empty($context->orderData['order_address_verify_result'])) {
                //说明没有进行地址校验，需要重新验证一下
                $verifyAddressResult = OrderChangeAsyncService::instance()->verifyOrderAddress($orderNo);
                if (1 == $verifyAddressResult['code']) {
                    $isFailCheckAddress = 0; //校验通过
                }
            }
            if (1 == $isFailCheckAddress) {
                g_log_warning('delayUpdatePaidSuccess.log', '地址校验不通过：' . $orderNo, [$orderNo, $context->orderData]);
                return $this->returnError(1001, 'Order address verification failed');
            }
            OrderGoodsGetNode::instance()->invokeNode($context);
            OrderAddressGetNode::instance()->invokeNode($context);
            OrderPickAddressGetNode::instance()->invokeNode($context);
            $orderAddress = !empty($context->orderAddressData) ? $context->orderAddressData : $context->orderPickAddressData;
            //TODO 推送前的校验 测试环境不校验
            //if (!YII_DEBUG) {
            $checkResult = $this->orderSyncCheck($context->orderData, $context->orderGoodsData, $orderAddress);
            if ($checkResult['code'] != 1) {
                return $this->returnError($checkResult['code'], $checkResult['info']);
            }
            //}

            //通知erp
            if (OrderRepository::ORDER_TYPE_REISSUE == $context->orderData['order_type'] ||
                (OrderRepository::ORDER_TYPE_EXCHANGE == $context->orderData['order_type'] && ExchangeGoodsTypeEnum::NON_SALE_SKU == $context->orderData['exchange_goods_type'])) {
                //补发订单 或者 换货（非销售sku）
                $orderReissueExtend = OrderReissueExtendService::instance()->getByOrderNo($orderNo);
                $payment            = PaymentRepository::instance()->getByPaymentNo($context->orderData['payment_no']);
                $result             = OmsApi::instance()->pushReissueOrder($context->orderData, $context->orderGoodsData, @json_decode($orderReissueExtend['reissue_detail'], true), $orderAddress, $payment, $retry);
            } else {
                $result = OmsApi::instance()->orderSync($context->orderData, $context->orderGoodsData, $context->orderAddressData, $retry, $context->orderPickAddressData);
            }
            if ($result['code'] != 1) {
                g_log_warning('delayUpdatePaidSuccess.log', 'order-delay-sync-error-' . $orderNo, [$orderNo, $result]);
                return $this->returnError(1001, "sync:{$result['info']}");
            }
            //修改订单状态
            $updateData   = [
                'order_status' => OrderRepository::STATUS_UNDELIVERY,
                'updated_at'   => time(),
            ];
            $updateWhere  = [
                'order_no'     => $orderNo,
                'order_status' => OrderRepository::STATUS_PAID_SUCCESS
            ];
            $updateResult = OrderRepository::instance()->update($updateData, $updateWhere);

            //订单日志
            if ($updateResult > 0) {
                $context->logCode     = 1008;
                $context->orderNo     = $orderNo;
                $context->preStatus   = OrderRepository::STATUS_PAID_SUCCESS;
                $context->orderStatus = OrderRepository::STATUS_UNDELIVERY;
                $context->operator    = [
                    'type' => 3,
                    'id'   => 0,
                    'name' => '系统'
                ];
                OrderLogRepository::instance()->insertLogByContext($context);

                // 直接申报实收
                $this->orderUpdateDeclaration($context->orderData);
            }

            //直接通知更新es
            StockRedis::instance()->setOrderEsOrderNo([$orderNo]);
            LockHandleRedis::instance()->unlock($lockRedisKey);

            OrderService::instance()->orderChangeSendRabbitMQ(array_merge($context->orderData, $updateData), $context->orderGoodsData);
        } catch (\Exception $e) {
            g_log_error('delayUpdatePaidSuccess.log', '延迟修改订单异常' . $orderNo, [$e->getMessage() . $e->getTraceAsString()]);
            return $this->returnFormat($e->getCode(), null, $e->getMessage() . $e->getTraceAsString());
        }

        return $this->returnSuccess([], '推送成功');
    }

    public function orderSyncCheck($order, $orderGoodsList, $orderAddress)
    {
        $errorMsg = '';
        //重复订单判断
        $checkRepeatResult = OrderRepeatService::instance()->handleRepeatOrder($order['order_no']);
        if (1 == $checkRepeatResult['code'] && !empty($checkRepeatResult['data']['is_repeat'])) {
            $errorMsg = '存在重复订单：' . ($checkRepeatResult['data']['repeat_order_nos'] ?? '');
            goto codeEnd;
        }
        //换货订单，要确保A的换货售后单已完结，B单才能发货
        if (isset($order['order_type']) && $order['order_type'] == OrderRepository::ORDER_TYPE_EXCHANGE) {
            if (empty($order['business_no'])) {
                $errorMsg = '换货订单的售后单号为空';
                goto codeEnd;
            }
            $afterSale = AfterSaleApi::instance()->getAfterSale($order['business_no']);
            if (empty($afterSale) || !isset($afterSale['status'])) {
                $errorMsg = '换货订单的售后单不存在';
                goto codeEnd;
            }
            if ($afterSale['status'] != 6) {
                $errorMsg = '换货订单的售后单还未完结';
                goto codeEnd;
            }
            $interceptResult = AfterSaleApi::instance()->getInterceptResultByAfterSaleNo($order['business_no']);
            g_log_info('exchange_after_sale.log', '', [$order['order_no'], $order['business_no'], $interceptResult, $afterSale]);
            if (!isset($interceptResult['code']) || $interceptResult['code'] != 1) {
                $errorMsg = ($interceptResult['info'] ?? '拦截失败');
                goto codeEnd;
            }
        }
        //当前订单有正在申请的售后，则不允许推送oms
        $afterSaleResult = AfterSaleApi::instance()->existAfterSaleInProcessing($order['order_no']);
        if (1 == $afterSaleResult['code'] && 1 == $afterSaleResult['data']['is_exist']) {
            $errorMsg = '当前订单的售后单还未完结:' . implode(',', $afterSaleResult['data']['after_sale_nos']);
            goto codeEnd;
        }
        $payment = PaymentRepository::instance()->getByPaymentNo($order['payment_no'], 'pay_amount,payment_channel_company');
        if (!empty($payment) && $payment['pay_amount'] > 0) {
            //订单校验支付金额
            $thirdPartInfomation = PayInternal::instance()->getPaymentDetailFromThirdPart($order['payment_no']);
            if (empty($thirdPartInfomation['data']) || $thirdPartInfomation['data']['is_paid'] != 1) {
                $errorMsg = '订单的支付金额未支付' . json_encode($thirdPartInfomation, 320);
                goto codeEnd;
            }

            //对paypal和stripe进行金额一致的校验
            if (in_array($payment['payment_channel_company'], [PaymentRepository::PAYMENT_METHOD_PAYPAL, PaymentRepository::PAY_BY_STRIPR,
                    PaymentRepository::PAY_BY_PINGPONG, PaymentRepository::PAY_BY_USEEPAY
                ])
                && 0 !== bccomp($thirdPartInfomation['data']['pay_amount'], $payment['pay_amount'], 2)) {
                $errorMsg = sprintf('系统和第三方支付金额不一致，系统：%s，%s：%s', $payment['pay_amount'], $payment['payment_channel_company'], $thirdPartInfomation['data']['pay_amount']);
                goto codeEnd;
            }
        }
        //订单商品金额
        $sumOrderGoodsPayAmount = array_sum(array_column($orderGoodsList, 'pay_amount'));
        if (bcsub($order['pay_amount'], $sumOrderGoodsPayAmount, 2) != '0.00') {
            $errorMsg = '订单商品总和不等于订单支付金额';
            goto codeEnd;
        }
        //普通订单&代客下单，校验订单地址是否为禁售
        if (in_array($order['order_type'], [OrderRepository::ORDER_TYPE_ORDINARY, OrderRepository::ORDER_TYPE_VALET])) {
            $isForbiddenShipping = ShippingRuleService::instance()->getRule($orderAddress['country_code'], $orderAddress['state_code'], $orderAddress['zip'], $order['currency'])->isBanned();
            if ($isForbiddenShipping) {
                $errorMsg = '当前订单地址被禁售';
                goto codeEnd;
            }
        }
        //订单商品校验，eta为空暂不推送，补充完整后再推
        //foreach ($orderGoodsList as $orderGoods) {
        //    if (empty($orderGoods['goods_eta_info'])) {
        //        $errorMsg = sprintf('订单商品[%s]eta信息为空', $orderGoods['sku_code']);
        //        goto codeEnd;
        //    }
        //}

        //普通订单、代客下单、换货订单校验 增值服务金额一致性和保险金额一致性
        if (in_array($order['order_type'], [OrderRepository::ORDER_TYPE_ORDINARY, OrderRepository::ORDER_TYPE_VALET, OrderRepository::ORDER_TYPE_EXCHANGE])) {
            //增值服务金额一致性校验
            $orderServiceAmount = $order['usd_value_added_service_amount'];
            $sumServiceAmount   = 0;
            $orderWhiteGloves   = OrderGoodsValueAddedServiceRepository::instance()->getByOrderNo($order['order_no']);
            if (!empty($orderWhiteGloves)) {
                $sumServiceAmount = array_sum(array_column($orderWhiteGloves, 'usd_service_amount'));
            }
            if (0 != bcsub($orderServiceAmount, $sumServiceAmount, 2)) {
                $errorMsg = '增值服务金额和记录不一致';
                goto codeEnd;
            }
            //保养服务金额一致性校验
            $orderProtectionPlanAmount = $order['usd_protection_plan_amount'];
            $sumPlanAmount             = 0;
            $protectionPlanList        = OrderGoodsProtectionPlanRepository::instance()->getByOrderNo($order['order_no']);
            if (!empty($protectionPlanList)) {
                $sumPlanAmount = array_sum(array_column($protectionPlanList, 'total_usd_service_amount'));
            }
            if (0 != bcsub($orderProtectionPlanAmount, $sumPlanAmount, 2)) {
                $errorMsg = '保养服务金额和记录不一致';
                goto codeEnd;
            }
        }
        //如果订单包含次日达，则检查次日达是否还有效
        $nextDayList = array_column($orderGoodsList, 'is_include_next_day');
        if (in_array(1, $nextDayList)) {
            $examinationResult = OrderGoodsNextDayExaminationService::instance()->nextDayDeliveryExaminationHandle($order, $orderGoodsList);
            g_log_info('orderSyncCheck', '检查次日达有效性检查', ['order_no' => $order['order_no'], 'result' => $examinationResult]);
        }
        codeEnd:
        if (!empty($errorMsg)) {//有错误信息的操作
            //有插入就不在插入mysql
            $count = OrderLogRepository::instance(true)->getCount([['=', 'order_no', $order['order_no']], ['=', 'code', 9320306]]);
            if ($count == 0) {
                //写入订单日志
                OrderLogRepository::instance()->insertLogV2(
                    $order['order_no'],
                    '推送oms订单失败，失败原因：' . $errorMsg,
                    $order['order_status'], $order['order_status'],
                    ['type' => '系统', 'id' => 0, 'name' => '推送oms程序'],
                    9320306
                );

                if ('换货订单的售后单还未完结' != $errorMsg) {
                    //企业微信报警
                    $wechatMsg = [
                        '推送oms订单失败',
                        '订单号：' . $order['order_no'],
                        '失败原因：' . $errorMsg
                    ];
                    EnterpriseWechatMessage::instance()->wechatMsgSend($wechatMsg, 'order_sync');
                }
            }
            return $this->returnError(9320306, $errorMsg);
        } else {
            return $this->returnSuccess([]);
        }

    }

    public function createdDeclare($params)
    {
        $mqData       = [
            'order_no' => $params['order_no'],//订单号
        ];
        $exchange     = \Yii::$app->params['tax_declaration_mq']['exchange_name'] ?? '';
        $routeKey     = \Yii::$app->params['tax_declaration_mq']['route_key'] ?? '';
        $delaySeconds = 1;
        $result       = RabbitMq::send($exchange, $routeKey, $mqData, $delaySeconds);

        g_log_info('trackingExceptionMqSend.log', '发送mq', [$exchange, $routeKey, $result]);
    }

    /**
     * 修改账单地址
     */
    public function updateBillAddress($userId, $billAddressId, $params)
    {
        // 获取State 州数据 兼容，再获取一下
        if (empty($params['state_id']) || empty($params['state']) || empty($params['state_code'])) {
            $state = CountryAreaRepository::instance()->checkAndReturnState($params['state'] ?? '', '', $params['country_code'] ?? '', $params['city'] ?? '');
            if (!empty($state)) {
                $params['state_id']   = $state['id'] ?? 0;
                $params['state']      = $state['name'] ?? '';
                $params['state_code'] = $state['code'] ?? '';
            }
        }

        $form = new AddressForm();
        if (!$form->addressValidate($params)) {
            return $this->returnError(800000, $form->getSimpleFirstError());
        }

        $update = [
            'email'               => $params['email'] ?? '',
            'company'             => $params['company'] ?? '',
            'first_name'          => $params['first_name'] ?? '',
            'last_name'           => $params['last_name'] ?? '',
            'phone'               => $params['phone'] ?? '',
            'phone_code'          => $params['phone_code'] ?? '',
            'international_phone' => PhoneNumberService::instance()->format($params['phone'], $params['country_code']),
            'fax'                 => $params['fax'] ?? '',
            'country_id'          => isset($params['country_id']) && !empty($params['country_id']) ? $params['country_id'] : 0,
            'country'             => $params['country'] ?? '',
            'country_code'        => $params['country_code'] ?? '',
            'state_id'            => isset($params['state_id']) && !empty($params['state_id']) ? $params['state_id'] : 0,
            'state'               => $params['state'] ?? '',
            'state_code'          => $params['state_code'] ?? '',
            'city'                => $params['city'] ?? '',
            'area'                => $params['area'] ?? '',
            'zip'                 => $params['zip'] ?? '',
            'street1'             => $params['street1'] ?? '',
            'street2'             => $params['street2'] ?? '',
            'updated_at'          => time(),
        ];

        $where = [
            'bill_address_id' => $billAddressId,
        ];

        $result = BillAddressRepository::instance()->updateById($billAddressId, $update);
        if (empty($result)) {
            return $this->returnError(9006, $form->getSimpleFirstError());
        }

        return $this->returnSuccess();
    }

    public function getLocationType()
    {
        $locationTypeOptions = \Yii::$app->params['location_type'];
        foreach ($locationTypeOptions as &$option) {
            $option['name'] = Yii::t('common/app', $option['name']);
        }
        unset($option);
        return $locationTypeOptions;
    }

    public function addRemarks($params)
    {
        try {
            //组织上下文数据
            $context          = new OrderRemarksContext();
            $context->orderNo = $params['order_no'];

            // 区分数据
            switch ($params['type']) {
                case 1:
                    $type = OrderRemarksRepository::REMARKS_TYPE_CLIENT;
                    break;
                case 2:
                    $type = OrderRemarksRepository::REMARKS_TYPE_SERVICE;
                    break;
                default:
                    throw new \Exception('Error writing order remarks', 800310);
            }

            // 组织数据
            $context->saveData = [
                'order_no'      => $params['order_no'],
                'operator_name' => $params['operator_name'],
                'remarks'       => $params['remarks'],
                'type'          => $type,
                'create_at'     => time(),
            ];
            OrderRemarksRepository::instance()->insert($context->saveData);
            return $this->returnSuccess([], 'success');

        } catch (\Exception $e) {
            g_log_error('addRemarks.log', '添加订单备注,异常', [$e->getMessage() . $e->getTraceAsString(), $params]);
            return $this->returnFormat(800402, null, $e->getMessage() . $e->getTraceAsString());
        }
        return $this->returnSuccess();
    }

    private function formatDayOrderStatistic($data)
    {
        if (empty($data['site']) || empty($data['site']['buckets'])) {
            return [];
        }

        $buckets = $data['site']['buckets'];

        $list = [];
        foreach ($buckets as $aggSite) {
            // 支付数据
            $paymentMethodList = $aggSite['payment_status']['buckets'] ?? [];
            if (!empty($paymentMethodList)) {
                $paymentMethodList = array_column($paymentMethodList, null, 'key');
            }
            $paidOrderPayment = $paymentMethodList[1] ?? [];
            // 日期
            $dayDate = $aggSite['day_date']['buckets'][0] ?? [];

            $array = [
                'day_date'            => $dayDate['key_as_string'] ?? '',
                'site'                => $aggSite['key'],
                'order_number'        => $aggSite['doc_count'],
                'usd_pay_amount'      => $aggSite['usd_pay_amount']['value'] ?? 0,
                'cny_pay_amount'      => $aggSite['cny_pay_amount']['value'] ?? 0,
                'paid_order_number'   => $paidOrderPayment['doc_count'] ?? 0,
                'paid_usd_pay_amount' => $paidOrderPayment['usd_pay_amount']['value'] ?? 0,
                'paid_cny_pay_amount' => $paidOrderPayment['cny_pay_amount']['value'] ?? 0,
            ];

            $array['usd_pay_amount']      = round($array['usd_pay_amount'], 2);
            $array['cny_pay_amount']      = round($array['cny_pay_amount'], 2);
            $array['paid_order_number']   = round($array['paid_order_number'], 2);
            $array['paid_usd_pay_amount'] = round($array['paid_usd_pay_amount'], 2);
            $array['paid_cny_pay_amount'] = round($array['paid_cny_pay_amount'], 2);

            $list[] = $array;
        }

        return $list;
    }

    /**
     * es搜索条件
     *
     * @param $search
     *
     * @return array
     */
    private function orderEsSearchWhere($search)
    {
        $where = [];

        if (!empty($search['created_start_time'])) {
            $where[] = ['>=', 'created_at', $search['created_start_time']];
        }
        if (!empty($search['created_end_time'])) {
            $where[] = ['<', 'created_at', $search['created_end_time']];
        }

        if (!empty($search['paid_start_time'])) {
            $where[] = ['>=', 'paid_at', $search['paid_start_time']];
        }
        if (!empty($search['paid_end_time'])) {
            $where[] = ['<', 'paid_at', $search['paid_end_time']];
        }

        if (!empty($search['site_code'])) {
            $where[] = ['=', 'site', $search['site_code']];
        }

        //来源
        if (isset($search['platform']) && $search['platform'] != '') {
            $where[] = ['=', 'platform', $search['platform']];
        }

        return $where;
    }

    /**
     * 获取订单PDF的页面文本(发票下载)
     *
     * @param $params
     *
     * @return array
     */
    public function getPdfView($params)
    {
        try {
            $formatType                        = $params['format_type'] ?? 1; //1.pdf, 2、Factur-X  3、UBL 2.1、4、CII
            $orderNo                           = isset($params['order_no']) ? $params['order_no'] : '';
            $ab                                = isset($params['ab']) ? $params['ab'] : '';
            $type                              = $params['type'] ?? 0; //类型：1，订单详情，2，订单发票
            $orderData                         = OrderRepository::instance()->getOrderByOrderNo($orderNo);
            $orderGoodsData                    = OrderGoodsRepository::instance()->getOrderGoodsByOrderNo($orderNo);
            $orderAmountData                   = OrderAmountRepository::instance()->getByOrderNo($orderNo);
            $shippingAddress                   = OrderAddressRepository::instance()->getAddressByOrderNo($orderNo);
            $billingAddress                    = BillAddressRepository::instance()->getBillAddressByPaymentNo($orderData['payment_no']);
            $orderGoodsValueAddedServiceList   = OrderGoodsValueAddedServiceRepository::instance()->getListByGoodsIds(array_column($orderGoodsData, 'order_goods_id'));
            $orderGoodsProtectionPlanList      = OrderGoodsProtectionPlanRepository::instance()->getListByGoodsIds(array_column($orderGoodsData, 'order_goods_id'));
            $orderGoodsInstallationServiceList = OrderGoodsInstallationServiceRepository::instance()->getListByGoodsIds(array_column($orderGoodsData, 'order_goods_id'));
            $orderGoodsList                    = OrderV4Service::instance()->goodsFormat(
                $orderGoodsData,
                $shippingAddress['country_code'] ?? '',
                $orderData['currency'],
                $orderData['site'],
                $orderGoodsValueAddedServiceList,
                $orderGoodsProtectionPlanList,
                $orderData['language'],
                '',
                'invoice',
                OrderRepository::ORDER_TYPE_REISSUE == $orderData['order_type'],
                $orderGoodsInstallationServiceList
            );
            // 每一项的商品增值服务加上运费
            $orderGoodsList = OrderV4Service::instance()->appendShippingFeeToValueService($orderGoodsList, $orderData);
            MyFunction::instance()->setTimeZone($params['site_code'] ?? '');
            // 设置语言包
            Yii::$app->language = BaseFunction::instance()->getFormatLanguage($orderData['language']);
            if ($ab == 1) {
                //老版发票下载
                $isInvoiceDownload = false;
            } else if ($ab == 2) {
                //新版发票下载
                $isInvoiceDownload = true;
            } else {
                $orderDisputeList = [];
                if ($orderData['is_dispute'] == 1) {
                    $orderDisputeList = OrderDisputeRepository::instance()->getListByOrderNo($orderData['order_no']);
                }
                // 是否发货30天之后
                $isInvoiceDownload = OrderInvoiceFormatService::instance()->getIsShowInvoiceDownload($orderData, $shippingAddress, $orderDisputeList);
                if ($isInvoiceDownload) {
                    $isInvoiceDownload = OrderInvoiceFormatService::instance()->getIsInvoiceDownload($orderGoodsData);
                }
            }
            // 国家编码
            $countryCode = $shippingAddress['country_code'] ?? '';
            if (empty($countryCode)) {
                $siteInfo    = ConfigService::instance()->getSiteByCode($orderData['site']);
                $countryCode = $siteInfo['country_code'];
            }
            $invoiceSummarize                = [];
            $userVatNumber                   = '';
            $isShowCashMethodInfo            = false;
            $shippingAddress['country_code'] = $countryCode;
            // 经营主体配置，包含税信息和公司信息
            $channelType               = ($orderData['order_sales_type'] == OrderRepository::STORE_ORDER) ? 2 : 1;
            $orderBusinessEntityConfig = OrderBusinessEntitySiteConfigService::instance()->getBusinessEntityConfig($orderData['site'], $shippingAddress['country_code'], $channelType);
            if ($isInvoiceDownload) {
                //获取支付单信息
                $payment = PayService::instance()->getPaymentOrderInfoByBusinessNo($orderData['order_no'], 'payment_no,payment_method');
                if (!empty($payment) && $channelType === 2 && $payment['payment_method'] == PaymentRepository::PAYMENT_METHOD_CASH_PAYMENT) {
                    // 针对门店订单，并且是现金收款的订单，需要展示现金相关的协议信息
                    $isShowCashMethodInfo = true;
                }
                $invoiceSummarize  = OrderInvoiceFormatService::instance()->getOrderInvoiceSummarizeByPdf($orderData, $orderGoodsData, $orderAmountData, $shippingAddress, $orderBusinessEntityConfig);
                $paramsBillAddress = [];
                // 后台下载根据输入地址为准
                if (!empty($params['source']) && $params['source'] == 1 && !empty($params['bill_address'])) {
                    $paramsBillAddress = @json_decode(str_replace('symbol_amp', '&', urldecode($params['bill_address'])), true) ?? [];
                }
                if (!empty($paramsBillAddress)) {
                    $billingAddress = $paramsBillAddress;
                }
                $paramsShippingAddress = [];
                if (!empty($params['source']) && $params['source'] == 1 && !empty($params['shipping_address'])) {
                    $paramsShippingAddress = @json_decode(str_replace('symbol_amp', '&', urldecode($params['shipping_address'])), true) ?? [];
                }
                if (!empty($paramsShippingAddress)) {
                    $shippingAddress = $paramsShippingAddress;
                }
                //$orderData['invoice_no'] = InvoiceService::instance()->generateInvoiceNo($orderNo);
            }
            if (empty($billingAddress)) {
                //如果没有账单地址，则使用收货地址
                $billingAddress = $shippingAddress;
            }
            if (1 == $type) {
                $amountSummarize = OrderV4Service::instance()->getAmountSummarize(
                    $orderData,
                    $orderAmountData,
                    $orderGoodsData,
                    $orderGoodsValueAddedServiceList,
                    $orderGoodsProtectionPlanList,
                    $orderGoodsInstallationServiceList
                );
            } else {
                $amountSummarize = OrderV4Service::instance()->getInvoiceAmountSummarize(
                    $orderData,
                    $orderAmountData,
                    $orderGoodsData,
                    $orderGoodsValueAddedServiceList,
                    $orderGoodsProtectionPlanList,
                    $orderGoodsInstallationServiceList
                );
            }
            $replaceData = [
                'order_no'                 => $orderNo,
                'invoice_no'               => $orderData['invoice_no'],
                'order_goods_list'         => $orderGoodsList,
                'order_summary'            => array_merge($amountSummarize, $invoiceSummarize),
                'print_at'                 => date('Y/m/d H:i', time()),
                'created_at_str'           => date('M d,Y', $orderData['created_at']),
                'shipping_address'         => $shippingAddress,
                'billing_address'          => $billingAddress,
                'vat_number'               => '',
                'user_vat_number'          => $userVatNumber,
                'paid_at_str'              => !empty($orderData['paid_at']) ? date('M d,Y', $orderData['paid_at']) : date('M d,Y', $orderData['created_at']),
                'is_download'              => $params['is_download'] ?? 0,
                'business_entity_config'   => $orderBusinessEntityConfig,
                'is_show_cash_method_info' => $isShowCashMethodInfo ? 1 : 0
            ];
            $viesHtml    = '';
            if (in_array($orderData['site'], ['us', 'uk', 'au'])) {
                //us/uk/au仅支持pdf下载
                if ($isInvoiceDownload) {
                    $viesHtml = Yii::$app->view->renderFile('@common/bmMail/pdf/pdf_view_days.php', ['replaceData' => $replaceData]);
                } else {
                    //老版发票下载
                    $viesHtml = Yii::$app->view->renderFile('@common/bmMail/pdf/pdf_view.php', ['replaceData' => $replaceData]);
                }
            } else {
                //de/fr/es 暂使用默认公司地址
                $companyAddress = [
                    'country_code' => 'FR',
                    'city'         => 'Pierrelaye',
                    'street1'      => '5 Chemin De La Basse Patelle',
                    'street2'      => 'ZA Porte Ouest, Route d\' Eragny',
                    'zip'          => '95480',
                ];
                switch ($formatType) {
                    case InvoiceFormatTypeEnum::PDF:
                        if ($isInvoiceDownload) {
                            $viesHtml = Yii::$app->view->renderFile('@common/bmMail/pdf/pdf_view_days.php', ['replaceData' => $replaceData]);
                        } else {
                            //老版发票下载
                            $viesHtml = Yii::$app->view->renderFile('@common/bmMail/pdf/pdf_view.php', ['replaceData' => $replaceData]);
                        }
                        break;
                    case InvoiceFormatTypeEnum::FACTUR_X:
                    case InvoiceFormatTypeEnum::UBL_2_1:
                    case InvoiceFormatTypeEnum::CII:
                        $templateList = [
                            InvoiceFormatTypeEnum::FACTUR_X => '@common/bmMail/xml/factur-x.xml',
                            InvoiceFormatTypeEnum::UBL_2_1  => '@common/bmMail/xml/ubl.xml',
                            InvoiceFormatTypeEnum::CII      => '@common/bmMail/xml/cii.xml',
                        ];
                        $viesHtml     = InvoiceDownloadService::instance()->generateXml($orderData, $orderGoodsData, $shippingAddress, $billingAddress, $orderBusinessEntityConfig, $companyAddress, $templateList[$formatType]);
                        break;
                }
            }
            return $this->returnSuccess(['html' => $viesHtml], 'success');
        } catch (\Exception $e) {
            g_log_error('getPdfView', 'pdf生成异常', [$params, $e->getMessage() . $e->getTraceAsString()]);
            return $this->returnFormat(800403, null, $e->getMessage() . $e->getTraceAsString());
        }
    }

    private function pdfOrderGoodsFormat($goodsDetail)
    {
        $goodsDetail['estimated_arrival_format'] = '';
        $goodsDetail['sale_attr_text']           = '';
        if (!empty($goodsDetail['estimated_arrival_at'])) {
            $goodsDetail['estimated_arrival_format'] = $goodsDetail['estimated_arrival_at'];
        }
        if (!empty($goodsDetail['sale_attr_group'])) {
            $sale_attr_group_arr = is_string($goodsDetail['sale_attr_group']) ? json_decode($goodsDetail['sale_attr_group'], 1) : $goodsDetail['sale_attr_group'];
            if (!empty($sale_attr_group_arr)) {
                foreach ($sale_attr_group_arr as $attr_key => $attr_value) {
                    if ($attr_key == 'option' && $attr_value == 'default') {
                        continue;
                    }
                    $goodsDetail['sale_attr_text'] .= $attr_key . ':' . $attr_value . ' ';
                }
            }
        }
        if (empty($goodsDetail['sale_attr_text'])) {
            $goodsDetail['sale_attr_text'] = '--';
        }
        return $goodsDetail;
    }

    /**
     * 更新取货方式
     *
     * @param $params
     *
     * @return int
     * @author 白杨
     * @Date   2021/6/1 下午3:27
     */
    public function updateLocationType($params)
    {
        $update = [
            'location_type' => $params['location_type']
        ];
        $where  = [
            'order_no' => $params['order_no']
        ];

        return OrderRepository::instance()->update($update, $where);
    }

    public function confirmOrderAddressInfo($params)
    {
        // 初始化更新条件
        $upTime = time();
        $where  = [
            'order_status' => OrderRepository::STATUS_PAID_AUDIT,
            'order_no'     => $params['order_no'],
        ];
        // 更新订单信息
        $result = OrderRepository::instance()
            ->update([
                'order_status' => OrderRepository::STATUS_PAID_SUCCESS,
                'updated_at'   => $upTime,
            ], $where);
        // 记录订单操作日志
        if (!empty($result)) {
            $context              = new OrderDetailContext();
            $context->orderNo     = $params['order_no'];
            $context->preStatus   = OrderRepository::STATUS_PAID_AUDIT;
            $context->orderStatus = OrderRepository::STATUS_PAID_SUCCESS;
            $context->logCode     = 1010;
            $context->operator    = ['type' => 2, 'id' => $params['admin_id'], 'name' => $params['admin_name']];
            $replace              = [
                'time' => is_int($upTime) ? date('Y-m-d H:i:s') : $upTime,
            ];
            OrderLogRepository::instance()->insertLogByContext($context, $replace);
        }
        return $result;
    }

    /**
     * 获取订单支付状态
     *
     * @param int    $userId    用户ID
     * @param string $orderNo   订单号
     * @param string $paymentNo 支付单号
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    public function getPayStatus($userId, $orderNo, $paymentNo = '')
    {
        $order = OrderRepository::instance()->getOrderByOrderNo($orderNo, 'payment_no,user_id');
        if (empty($order)) {
            throw new \Exception('', 600002);
        }
        //if ((int)$order['user_id'] != $userId) { //暂时不用判断
        //    throw new \Exception('', 20000);
        //}
        $payment = PaymentRepository::instance()->getByBusinessNo($orderNo, 'status,payment_method');
        if (empty($payment)) {
            throw new \Exception('', 700002);
        }
        if (in_array((int)$payment['status'], [PaymentRepository::STATUS_PAID, PaymentRepository::STATUS_PAID_AUDIT, PaymentRepository::STATUS_PROCESSING])) {
            return $this->returnSuccess([
                'pay_status'    => true,
                'error_message' => '',
                'error_detail'  => null,
                'next_action'   => null,
                'is_cancel'     => 0,
                'error_url'     => '',
                'cancel_url'    => '',
            ]);
        }
        //超时时间（因为用作前端轮询接口）
        $timeout       = YII_DEBUG ? 180 : 1800;
        $cacheKey      = !empty($paymentNo) ? $paymentNo : $payment['payment_no'];
        $loopStartTime = OrderRedis::instance()->getLoopOrderPayStatusStartTime($cacheKey, $userId);
        if (empty($loopStartTime)) {
            $loopStartTime = time();
            OrderRedis::instance()->setLoopOrderPayStatusStartTime($cacheKey, $userId, $loopStartTime);
        }
        $isTimeout = time() - $loopStartTime > $timeout;
        $errorUrl  = '';
        $cancelUrl = '';
        if (PaymentRepository::PAYMENT_METHOD_STRIPE_ACH == $payment['payment_method']) {
            $confirmResult = PayService::instance()->getThirdPaymentConfirm($order['payment_no'], $payment['payment_method']);
            $errorUrl      = $confirmResult['data']['error_url'] ?? '';
            $cancelUrl     = $confirmResult['data']['cancel_url'] ?? '';
            if (1 == $confirmResult['code'] && !$isTimeout) {
                if (!empty($confirmResult['data']['error_message']) || !empty($confirmResult['data']['next_action'])) {
                    //确认支付出现失败 || 确认支付需要用户进一步验证
                    return $this->returnSuccess([
                        'pay_status'    => false,
                        'error_message' => $confirmResult['data']['error_message'] ?? '',
                        'error_detail'  => $confirmResult['data']['error_detail'] ?? null,
                        'next_action'   => $confirmResult['data']['next_action'] ?? null,
                        'is_cancel'     => $confirmResult['data']['is_cancel'] ?? 0,
                        'error_url'     => $errorUrl,
                        'cancel_url'    => $cancelUrl,
                    ]);
                }
            }
        }
        if (in_array($payment['payment_method'], [PaymentRepository::PAYMENT_METHOD_PAYPAL_CREDIT_CARD,
            PaymentRepository::PAYMENT_METHOD_STRIPE_CREDITCARD,
            PaymentRepository::PAYMENT_METHOD_USEEPAY_CREDIT_CARD
        ])) {
            //信用卡需要判断第三方3DS是否验证通过
            $result    = PayService::instance()->get3DSResult($orderNo, $paymentNo);
            $errorUrl  = $result['data']['error_url'] ?? '';
            $cancelUrl = $result['data']['cancel_url'] ?? '';
            if (1 == $result['code']) {
                if (!$isTimeout) {
                    $result['data']['pay_status'] = false;
                    return $this->returnSuccess($result['data']);
                }
            } else {
                throw new \Exception($result['info'], $result['code']);
            }
        }
        if ($isTimeout) {
            //超时返回错误
            $paymentCompany = PayService::instance()->getPayChannelByMethod($payment['payment_method']);
            $tips           = PayService::instance()->getPaymentErrorTips($paymentCompany);
            OrderRedis::instance()->delLoopOrderPayStatusStartTime($orderNo, $userId);
            return $this->returnSuccess([
                'pay_status'    => false,
                'error_message' => Yii::t('common/error', 'payment_processing_timeout'),
                'error_detail'  => PayService::instance()->formatPaymentErrorResponse($tips['error'], $tips['error_type'], $payment['payment_method']),
                'next_action'   => null,
                'is_cancel'     => 0,
                'error_url'     => $errorUrl,
                'cancel_url'    => $cancelUrl,
            ]);
        }
        return $this->returnSuccess([
            'pay_status'    => false,
            'error_message' => '',
            'error_detail'  => null,
            'next_action'   => null,
            'is_cancel'     => 0,
            'error_url'     => '',
            'cancel_url'    => '',
        ]);
    }

    /**
     * 获取交易快照
     *
     * @param int    $userId       用户ID
     * @param string $orderGoodsId 订单商品唯一ID（order_goods表自增长id）
     *
     * @param string $pf           客户端来源
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    public function getTransSnapshot($userId, $orderGoodsId, $pf)
    {
        $result   = [];
        $snapshot = TransactionSnapshotRepository::instance()->getTransSnapshot($orderGoodsId);
        if (empty($snapshot)) {
            throw new \Exception(Code::Map(Code::SNAPSHOT_NOT_EXIST), 20000);
        }
        if ((int)$snapshot['user_id'] != $userId) {
            throw new \Exception(Code::Map(Code::ACCESS_FORBIDDEN), 401);
        }

        if (empty($snapshot['snapshot_content'])) {
            return $result;
        }
        $snapshotArr = json_decode($snapshot['snapshot_content'], true);
        if (!empty($snapshotArr)) {
            $result['product_info'] = [
                'product_img'      => !empty($snapshotArr['product_img']) ? $snapshotArr['product_img'] : null,
                'real_product_img' => !empty($snapshotArr['real_product_img']) ? $snapshotArr['real_product_img'] : null,
                'highlight_img'    => !empty($snapshotArr['highlight_img']) ? $snapshotArr['highlight_img'] : null,
                'dimension_img'    => !empty($snapshotArr['dimension_img']) ? $snapshotArr['dimension_img'] : null,
            ];
            $saleAttrs              = [];
            if (!empty($snapshotArr['sale_attr_group'])) {
                foreach ($snapshotArr['sale_attr_group'] as $item) {
                    $saleAttrs[] = [
                        'key'   => $item['pn_value'],
                        'value' => $item['pv_value'],
                    ];
                }
                $saleAttrs[] = [
                    'key'   => Yii::t('common/app', 'qty'),
                    'value' => $snapshotArr['qty'] ?? 0,
                ];
            }
            $result['snapshot'] = [
                'title'                      => Yii::t('common/app', 'transaction_snapshot'),
                'product_title'              => $snapshotArr['product_title'],
                'price_symbol'               => $snapshotArr['price_symbol'] ?? '',
                'product_detail_instruction' => [
                    'desc'                         => Yii::t('common/app', 'instruction'),
                    'latest_product_details_label' => Yii::t('common/app', 'view_latest_product'),
                    'latest_product_details_url'   => 'https://www.bm.com' . $snapshotArr['jump_url'],
                ],
                'selected'                   => [
                    'title'   => Yii::t('common/app', 'selected') . ':',
                    'content' => !empty($saleAttrs) ? $saleAttrs : null,
                ],
                'service_guarantee'          => [
                    'title'   => Yii::t('common/app', 'service_guarantee') . ':',
                    'content' => implode(',', array_column($snapshotArr['service_guarantee'], 'title')),
                ],
            ];
            if (isset($snapshotArr['value_added_service'])) {
                $result['value_added_service']['title']  = Yii::t('common/app', 'What We Offer');
                $result['value_added_service']['list'][] = [
                    'content' => $snapshotArr['value_added_service']['title'] . ' ' . $snapshotArr['value_added_service']['amount'],
                    'desc'    => $snapshotArr['value_added_service']['desc']
                ];
            }
            $result['product_static_info'] = [
                'sku_code'            => $snapshotArr['sku_code'],
                'product_overview'    => !empty($snapshotArr['product_overview']) ? $snapshotArr['product_overview'] : null,
                'weight_dimension'    => [
                    'dimension_img'  => !empty($snapshotArr['weight_dimension']['dimension_img']) ? $snapshotArr['weight_dimension']['dimension_img'] : null,
                    'dimension_attr' => !empty($snapshotArr['weight_dimension']['dimension_attr']) ? $snapshotArr['weight_dimension']['dimension_attr'] : null,
                ],
                'additional_document' => [
                    'files'     => $snapshotArr['additional_document']['files'] ?? null,
                    'video_url' => $snapshotArr['additional_document']['video_url'],
                ],
                'details'             => $snapshotArr['details'] ?? null,
            ];
        }
        return $result;
    }


    /**
     * 更新订单数据
     *
     * @param int    $userId     用户ID
     * @param string $orderNo    订单号
     *
     * @param array  $updateData 更新的数据
     *
     * @return bool
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    public function updateOrder($userId, $orderNo, $updateData)
    {
        $updateData['updated_at'] = time();

        return OrderRepository::instance()->update($updateData, ['user_id' => $userId, 'order_no' => $orderNo]);
    }

    /**
     * 推送已收货的信息到mq
     *
     * @param string $orderNo      订单状态
     * @param string $createdStart
     * @param string $createdEnd
     * @param string $receiveStart 收货开始时间
     * @param string $receiveEnd   收货结束时间
     *
     * @param int    $orderStatus  订单状态
     *
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     * @author liangchupeng
     */
    public function pushReceiveOrder($orderNo, $createdStart = '', $createdEnd = '', $receiveStart = '', $receiveEnd = '', $orderStatus = null)
    {
        $where = [
            ['in', 'order_type', [OrderRepository::ORDER_TYPE_ORDINARY, OrderRepository::ORDER_TYPE_VALET]],
            ['=', 'del_flag', 0],
        ];
        if (!empty($receiveStart)) {
            $where[] = ['>=', 'received_at', strtotime($receiveStart)];
        }
        if (!empty($receiveEnd)) {
            $where[] = ['<=', 'received_at', strtotime($receiveEnd . ' 23:59:59')];
        }
        if (!empty($createdStart)) {
            $where[] = ['>=', 'created_at', strtotime($createdStart)];
        }
        if (!empty($createdEnd)) {
            $where[] = ['<=', 'created_at', strtotime($createdEnd . ' 23:59:59')];
        }
        if (!empty($orderNo)) {
            $where[] = ['=', 'order_no', $orderNo];
        }
        if (null !== $orderStatus) {
            $where[] = ['=', 'order_status', (int)$orderStatus];
        }
        $page     = 1;
        $pageSize = 200;
        while (true) {
            $orderList = OrderRepository::instance()->getPageList($where, $page, $pageSize, ['order_id' => SORT_ASC]);
            if (empty($orderList)) {
                break;
            }
            foreach ($orderList as $order) {
                //组织上下文数据
                $context          = new PushReceiveOrderContext();
                $context->orderNo = $order['order_no'];
                $context->userId  = $order['user_id'];
                $nodeChain        = [
                    OrderGetNode::instance(),
                    OrderGoodsGetNode::instance(),
                    OrderAddressGetNode::instance(),
                    OrderChangeSendRabbitMQNode::instance(),
                ];
                $result           = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
                Yii::info('订单确认收货结果', json_encode([$result, $context->orderNo]));
                $context = null;
            }
            $page++;
            sleep(1);
        }
    }


    /**
     * 获取用户近期订单信息
     *
     * @param int $userId   用户ID
     * @param int $page     当前页数
     * @param int $pageSize 获取条数
     *
     * @return array|\yii\db\ActiveRecord[]
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     * @author liangchupeng
     */
    public function getRecentOrdersByUserId($userId, $page = 1, $pageSize = 5)
    {
        $where[]     = ['=', 'user_id', $userId];
        $where[]     = ['=', 'del_flag', 0];
        $result      = OrderElasticService::instance()->getOrderPageList($where, [], [], ['created_at DESC'], $page, $pageSize);
        $orderList   = $result['list'] ?? [];
        $orderResult = [];
        if (!empty($orderList)) {
            foreach ($orderList as $order) {
                $orderItem                    = [];
                $orderItem['created_at']      = date('Y-m-d H:i:s', $order['created_at']);
                $orderItem['order_id']        = $order['order_id'];
                $orderItem['order_no']        = $order['order_no'];
                $orderItem['currency_symbol'] = $order['currency_symbol'];
                $orderItem['currency']        = $order['currency'];
                $orderItem['site']            = $order['site'];
                $orderItem['payment_method']  = $order['payment_method_code'];
                //订单实际支付金额
                $payAmount                     = ConfigService::instance()->amountFormat($order['pay_amount'], $order['currency'], $order['site']);
                $orderItem['pay_amount']       = $payAmount['amount_symbol'];
                $orderItem['order_status_str'] = OrderRepository::STATUS_MAPPING[$order['order_status']];
                foreach ($order['order_goods'] as $key => $goods) {
                    $goodsItem = [];
                    //取3条商品信息即可
                    if ($key < 3) {
                        $goodsItem['sale_attr_group'] = json_decode($goods['sale_attr_group'], true);
                        $payAmount                    = ConfigService::instance()->amountFormat($goods['pay_amount'], $order['currency'], $order['site']);
                        $goodsItem['pay_amount']      = $payAmount['amount_symbol'];
                        $goodsItem['img']             = $goods['img'];
                        $goodsItem['jump_url']        = $goods['jump_url'];
                        $goodsItem['sku_id']          = $goods['sku_id'];
                        $goodsItem['spu_title']       = $goods['spu_title'];
                        $goodsItem['qty']             = $goods['qty'];
                        $goodsItem['order_goods_id']  = (int)$goods['order_goods_id'];
                        $orderItem['order_goods'][]   = $goodsItem;
                    } else {
                        break;
                    }
                }
                $orderResult[] = $orderItem;
            }
        }
        $orderList = null;
        return $orderResult;

    }

    /**
     *
     * @param $params
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     * @author 白杨
     * @Date   11/8/21 下午1:55
     */
    public function oversoldCanSell($params)
    {
        $orderNo   = $params['order_no'] ?? '';
        $orderData = OrderRepository::instance()->getOrderByOrderNo($orderNo);
        if (empty($orderData)) {
            return $this->returnError(882234, "订单数据为空");
        }

        if ($orderData['is_paid'] != 1) {
            return $this->returnError(882234, "超卖标志去除，只针对已支付订单");
        }

        if ($orderData['is_oversold'] == 0) {
            return $this->returnError(882234, "订单是未超卖订单，不需要修改");
        }

        $orderUpdate = [
            'is_oversold' => 0,
            'updated_at'  => time()
        ];
        $where       = [
            'order_no' => $orderNo
        ];

        $orderGoodsUpdate = [
            'oversold_num' => 0
        ];

        $transaction = OrderRepository::instance()->getConnection()->beginTransaction();
        //开启事务
        try {
            $result = OrderRepository::instance()->update($orderUpdate, $where);
            OrderGoodsRepository::instance()->update($orderGoodsUpdate, $where);

            // 记录订单操作日志
            if (!empty($result)) {
                $context              = new \stdClass();
                $context->orderNo     = $orderNo;
                $context->preStatus   = OrderRepository::STATUS_PAID_SUCCESS;
                $context->orderStatus = OrderRepository::STATUS_PAID_SUCCESS;
                $context->logCode     = 771011;
                $context->operator    = ['type' => 2, 'id' => $params['admin_id'], 'name' => $params['admin_name']];
                $replace              = [];
                OrderLogRepository::instance()->insertLogByContext($context, $replace);
            }
            $transaction->commit();

            // 推送mq
            $exchangeName = \Yii::$app->params["order_pay_success_delay_mq"]["exchange_name"];
            $routeKey     = \Yii::$app->params["order_pay_success_delay_mq"]["route_key"];

            $result = RabbitMq::send($exchangeName, $routeKey, @json_encode($orderData, JSON_UNESCAPED_UNICODE));
            g_log_error('sendDelivery.log', "发送队列{$orderNo}", ['order_no' => $orderNo, 'result' => $result]);

            return $this->returnSuccess([], '操作成功');
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->returnError(444444, "超卖标志去除异常：" . $e->getMessage());
        }

    }

    /**
     * 订单详情页推荐商品信息（支付成功页推荐商品）
     *
     * @param string $orderNo  订单号
     * @param string $site
     * @param string $currency
     * @param string $language
     * @param int    $quantity 获取数量
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     * @author liangchupeng
     * @since  2021.09.17
     */
    public function getOrderRecommendGoods($orderNo, $site, $currency, $language, $quantity = 8, $experimentDivertedSensors = '', $pf = 'pc', $pageFrom = 'order')
    {
        $orderGoods = OrderGoodsRepository::instance()->getOrderGoodsByOrderNo($orderNo, ['spu_id', 'sku_id', 'sku_class']);
        if (empty($orderGoods)) {
            return [];
        }
        //剔除定制商品的sku_id 完全是定制品的订单，不推荐商品
        $skuIds = [];
        foreach ($orderGoods as $orderGood) {
            if ($orderGood['sku_class'] != OrderGoodsRepository::SKU_CUSTOMIZED_CLASS) {
                array_push($skuIds, $orderGood['sku_id']);
            }
        }
        if (empty($skuIds)) {
            return [];
        }

        $productList = OrderProductRecommendService::instance()->setCommonParams([
            'business_code' => OrderProductRecommendEnums::SIMILAR_PRODUCTS,
            'sku_ids'       => $skuIds,
            'site'          => $site,
            'language'      => $language,
            'currency'      => $currency,
        ])->getRecommendList();

        if (!empty($productList)) {
            foreach ($productList as &$item) {
                $item['img_url'] = $item['sku_cover_img'] ?? $item['img_url'];
            }
            unset($item);
        }

        if (!empty($productList)) {
            $productList = ActivityProductService::instance()->formatProductListByVirtualDuration($productList, $site, $pf);
        }
        return $productList;
    }

    /**
     * 获取订单详情的服务条款
     *
     * @param string $orderNo 订单号
     * @param string $site
     * @param string $language
     * @param string $pf
     *
     * @return array
     * @author liangchupeng
     * @since  2021.09.22
     */
    public function getOrderServiceGuarantee($orderNo, $pf)
    {
        //获取订单信息
        $orderInfo = OrderRepository::instance()->getOrderByOrderNo($orderNo);

        $site     = $orderInfo['site'] ?? 'us';
        $language = $orderInfo['language'] ?? 'en';

        //获取订单地址信息
        $orderAddress = OrderAddressRepository::instance()->getAddressByOrderNo($orderNo);
        //获取订单商品
        $orderGoods = OrderGoodsRepository::instance()->getOrderGoodsByOrderNo($orderNo, 'sku_code');
        $areaData   = [
            'country_code' => $orderAddress['country_code'] ?? '',
            'state_code'   => $orderAddress['state_code'] ?? '',
            'city'         => $orderAddress['city'] ?? '',
            'zip'          => $orderAddress['zip'] ?? '',
            'sku_code'     => array_column($orderGoods, 'sku_code'),
        ];
        $result     = ProductTermsOfService::instance()->getTermsInfo($site, $language, $pf, $areaData);
        if (!empty($result['terms_of_service'])) {
            foreach ($result['terms_of_service'] as &$item) {
                switch ($item['key']) {
                    case 'secure_checkout':
                        $item['key']       = 'secure_checkout';
                        $item['title']     = \Yii::t('common/app', 'Secure Checkout');
                        $item['icon_tips'] = 'https://img5.su-cdn.com/common/2022/08/18/297dcf744a55bdb8f1d7317f3773dc9c.png';
                        break;
                    case 'expect_customer_service':
                        $item['icon_tips'] = 'https://img5.su-cdn.com/common/2022/08/18/ea0e78f580e2367ca7dd07df76280cd7.png';
                        break;
                    case 'free_shipping':
                        $item['icon_tips'] = 'https://img5.su-cdn.com/common/2022/08/18/7b72faf46c45c086261ea1140991ea68.png';
                        break;
                    case 'no_hassle_returns':
                        $item['icon_tips'] = 'https://img5.su-cdn.com/common/2022/08/18/b4ea8bbc101a4cc7b69519ee4f6e6b61.png';
                        break;
                    case 'late_delivery_compensation':
                        $item['icon_tips'] = 'https://img5.su-cdn.com/common/2022/08/18/3d2d0d6368b1f28d2aa187dda77ce27f.png';
                        break;
                    case 'damage_compensation':
                        $item['icon_tips'] = 'https://img5.su-cdn.com/common/2022/08/18/710ad355fca0a329e4c872006631e4da.png';
                        break;
                }
            }
        }
        return $result;
    }

    /**
     * 给oms的订单接口服务
     *
     * @param $params
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     * @author 白杨
     * @Date   14/10/21 上午10:51
     */
    public function getOrderListFromOms($params)
    {
        $orderNos = is_array($params['orderNos']) ? $params['orderNos'] : json_decode($params['orderNos'], 1);
        if (empty($orderNos)) {
            return $this->returnError(400004, '查询orderNos为空');
        }

        $orderList = OrderRepository::instance()->getOrderArrayByOrderNos($orderNos);
        $orderList = array_column($orderList, null, 'order_no');

        $orderAmountList = OrderAmountRepository::instance()->getByOrderNos($orderNos);

        $orderGoodsList = OrderGoodsRepository::instance()->getOrderGoodsByOrderNos($orderNos);

        $orderAddressList = OrderAddressRepository::instance()->getByOrderNos($orderNos);
        $orderAddressList = array_column($orderAddressList, null, 'order_no');

        $pickUpAddressList = OrderPickUpAddressRepository::instance()->getListByOrderNos($orderNos);
        $return            = [];
        foreach ($orderList as $order) {
            $orderAmount = $orderAmountList[$order['order_no']] ?? [];

            $orderGoods = [];
            foreach ($orderGoodsList as $v) {
                if ($v['order_no'] == $order['order_no']) {
                    $orderGoods[] = $v;
                }
            }

            $orderAddress = $orderAddressList[$order['order_no']] ?? [];
            if (empty($orderAmount) || empty($orderGoods)) {
                continue;
            }
            $pickUpAddress = $pickUpAddressList[$order['order_no']] ?? [];
            $return[]      = OmsApi::instance()->formatOrderDataToOms(array_merge($order, $orderAmount), $orderGoods, $orderAddress, $pickUpAddress);
        }
        return $this->returnSuccess($return);
    }

    /**
     * 更新订单商品为已评论的状态
     *
     * @param string $orderNo    订单号
     * @param string $skuId      sku id
     * @param string $skuCode    sku code
     * @param string $isReviewed 0 可评论 1已评论 2不可评论
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    public function updateOrderGoodsReviewed($orderNo = '', $skuId = '', $skuCode = '', $isReviewed = 0)
    {
        // sku id或者sku code必须有一个
        if (empty($skuId) && empty($skuCode)) {
            return $this->returnError(2001, 'sku_id or sku_code is null');
        }

        // 重组搜索的条件
        $where[] = ['=', 'order_no', $orderNo];
        $where[] = ['=', 'del_flag', 0];
        if ($skuId != '') {
            $where[] = ['=', 'sku_id', $skuId];
        } else if ($skuCode != '') {
            $where[] = ['=', 'sku_code', $skuCode];
        }
        // 检查是否有这个订单信息
        $orderCount = OrderGoodsRepository::instance()->getCount($where);

        // 如果存在这条订单信息，则走更新流程
        if ($orderCount > 0) {
            $updateWhere = [];
            // 重组搜索的条件
            if ($skuId != '') {
                $updateWhere = [
                    'order_no' => $orderNo,
                    'del_flag' => 0,
                    'sku_id'   => $skuId,
                ];
            } else if ($skuCode != '') {
                $updateWhere = [
                    'order_no' => $orderNo,
                    'del_flag' => 0,
                    'sku_code' => $skuCode,
                ];
            }
            // 更新订单表
            OrderRepository::instance()->update([
                'updated_at' => time()
            ], [
                'order_no' => $orderNo
            ]);
            // 更新订单商品表
            $updateResult = OrderGoodsRepository::instance()->update(
                [
                    'is_reviewed' => $isReviewed,
                    'reviewed_at' => time()
                ],
                $updateWhere
            );
            if (!empty($updateResult)) {
                StockRedis::instance()->setOrderEsOrderNo([$orderNo]);
                return $this->returnSuccess([], 'success');
            }
            return $this->returnError(2011, 'update fail');
        } else {
            return $this->returnError(2001, 'order_no is null');
        }
    }


    /**
     * 当前商品是否要评论
     *
     * @param        $isReviewded
     *
     * @return string
     */
    public function isReviewded($isReviewded, $isRefunded = 0)
    {
        if ($isRefunded == 2) {
            return OrderGoodsRepository::REVIEWED_STR[1];
        }

        switch ($isReviewded) {
            case 1:
            case 2:
                // 已评论的直接无需评论
                // 不可评论无需评论
                return OrderGoodsRepository::REVIEWED_STR[1];
                break;
            case 0:
                // 可以评论
                return OrderGoodsRepository::REVIEWED_STR[0];
                break;
            default:
                return OrderGoodsRepository::REVIEWED_STR[1];
                break;
        }
    }


    /**
     * 校验订单是否是已评论
     *
     * @param $orderNo
     * @param $skuCode
     *
     * @return array|\yii\db\ActiveRecord|null
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    public function checkIsReviewed($orderNo, $skuCode)
    {
        $orderGoods = OrderGoodsRepository::instance()->getByOrderNoSku($orderNo, $skuCode, ['is_reviewed']);
        return $orderGoods ?? [];
    }


    /**
     * 这个邮箱是否有购买过当前商品且已评论（最早的订单）
     * 未购买 NO_BUY
     * 已购买未评论 HAVE_BUY
     * 已购买已评论 HAVE_BUY_AND_REVIEWED
     *
     * @param        $email
     * @param        $skuId
     * @param string $skuCode
     * @param string $orderNo
     * @param string $site
     *
     * @return array|string[]
     * @throws Exception
     */
    public function getEmailHavaBuyGoods($email, $skuId, $skuCode = '', $orderNo = '', $site = '')
    {
        $noBuy              = 'NO_BUY';
        $haveBuy            = 'HAVE_BUY';
        $haveBuyAndReviewed = 'HAVE_BUY_AND_REVIEWED';
        // sku id或者sku code必须有一个
        if (empty($skuId) && empty($skuCode)) {
            return $this->returnError(2001, 'sku_id or sku_code is null');
        }
        // 重组搜索的条件
        $emailEncrypt = UserSensitiveFormat::instance()->emailEncrypt($email);
        $where[]      = ['or', sprintf('b.email in ("%s", "%s")', $emailEncrypt, $email), sprintf('p.email in ("%s", "%s")', $emailEncrypt, $email)];
        $where[]      = ['in', 'c.order_status', [6, 9]];

        if ($orderNo != '') {
            $where[] = ['=', 'a.order_no', $orderNo];
        }

        if ($site != '') {
            $where[] = ['=', 'c.site', $site];
        }

        if ($skuId != '') {
            $skuId   = is_array($skuId) ? $skuId : explode(',', $skuId);
            $where[] = ['in', 'a.sku_id', $skuId];
        } else if ($skuCode != '') {
            $skuCode = is_array($skuCode) ? $skuCode : explode(',', $skuCode);
            $where[] = ['in', 'a.sku_code', $skuCode];
        }

        $orderInfo = OrderGoodsRepository::instance()->getOrderInfoByYotpo($where);
        // 没有订单信息，返回空
        if (empty($orderInfo)) {
            return ['is_have' => $noBuy, 'order_no' => ''];
        }

        // 根据这个排序出来的订单信息，进行顺序检索出当前sku_id还未评论的商品给上级
        $isBuy = 'NO_BUY';
        foreach ($orderInfo as &$orderGoodsInfo) {
//            if (in_array($orderGoodsInfo['sku_id'], $skuId) && $orderGoodsInfo['is_reviewed'] == 2) {
//                continue;
//            }

            // 如果有已购买，但是未评论的商品，则直接跳出循环
            if (in_array($orderGoodsInfo['sku_id'], $skuId) && in_array($orderGoodsInfo['is_reviewed'], [0, 2])) { //$orderGoodsInfo['is_reviewed'] == 0
                $isBuy   = $haveBuy;
                $orderNo = $orderGoodsInfo['order_no'];
            }

            if (in_array($orderGoodsInfo['sku_id'], $skuId) && $orderGoodsInfo['is_reviewed'] == 1) {
                $isBuy   = $haveBuyAndReviewed;
                $orderNo = $orderGoodsInfo['order_no'];
            }

            // 如果有已购买，但是未评论的商品，则直接跳出循环
            if ($isBuy == $haveBuy) {
                break;
            }
        }

        return ['is_have' => $isBuy, 'order_no' => $orderNo];
    }


    /**
     * 获取用户最早一次购买的sku未评论的订单信息
     *
     * @param $email
     * @param $skuId
     * @param $skuCode
     * @param $orderNo
     * @param $site
     *
     * @return array|mixed
     * @throws Exception
     */
    public function getUserLastBuyForSku($email, $skuId, $skuCode, $orderNo, $site)
    {
        // sku id或者sku code必须有一个
        if (empty($skuId) && empty($skuCode)) {
            return $this->returnError(2001, 'sku_id or sku_code is null');
        }
        // 重组搜索的条件
        $emailEncrypt = UserSensitiveFormat::instance()->emailEncrypt($email);
        $where[]      = ['or', sprintf('b.email in ("%s", "%s")', $emailEncrypt, $email), sprintf('p.email in ("%s", "%s")', $emailEncrypt, $email)];
        $where[]      = ['in', 'c.order_status', [6, 9]];

        if ($orderNo != '') {
            $where[] = ['=', 'a.order_no', $orderNo];
        }

        if ($site != '') {
            $where[] = ['=', 'c.site', $site];
        }

        if ($skuId != '') {
            $skuId   = is_array($skuId) ? $skuId : explode(',', $skuId);
            $where[] = ['in', 'a.sku_id', $skuId];
        } else if ($skuCode != '') {
            $skuCode = is_array($skuCode) ? $skuCode : explode(',', $skuCode);
            $where[] = ['in', 'a.sku_code', $skuCode];
        }

        $orderInfo = OrderGoodsRepository::instance()->getOrderInfoByYotpo($where);
        return $orderInfo[0] ?? [];
    }


    /**
     * 根据是否已购买获取用户名称
     *
     * @param        $email
     * @param string $displayName
     * @param string $token
     * @param string $skuId
     * @param string $skuCode
     * @param string $orderNo
     *
     * @return mixed|string
     * @throws Exception
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    public function getUserNameForPurchase($email, $displayName = '', $token = '', $skuId = '', $skuCode = '', $orderNo = '', $site = '')
    {
        $userId    = BaseFunction::instance()->getUserId($token);
        $userInfo  = UserRepository::instance();
        $result    = $this->getEmailHavaBuyGoods($email, $skuId, $skuCode, $orderNo, $site);
        $buyStatus = $result['is_have'] ?? 'NO_BUY';
        $orderNo   = $result['order_no'] ?? '';
        if ($buyStatus == 'NO_BUY') {
            if ($userId) {
                //未购买,已登陆
                $userName = $userInfo->getUserByEmail($email)['nick_name'] ?? '';
            } else {
                //未购买,未登陆
                $userName = $displayName;
            }
        } else {
            $orderInfo = $this->getUserLastBuyForSku($email, $skuId, $skuCode, $orderNo, $site);
            $userName  = trim(($orderInfo['first_name'] ?? '') . ' ' . ($orderInfo['last_name'] ?? '')) ?: trim(($orderInfo['pick_first_name'] ?? '') . ' ' . ($orderInfo['pick_last_name'] ?? ''));
        }

        return $userName;
    }

    /**
     * 发付请邮件
     *
     * @param string $orderNo 订单号
     *
     * @return bool
     * @author liangchupeng
     * @since  2021.10.30
     */
    public function sendPayEmail($orderNo)
    {
        //获取订单信息
        $orderInfo = OrderRepository::instance()->getOrderByOrderNo($orderNo);
        if (empty($orderInfo)) {
            throw new \Exception('Order not found', 300001);
        }
        //获取订单地址
        $orderAddress = OrderAddressRepository::instance()->getAddressByOrderNo($orderNo);
        if (empty($orderAddress)) {
            throw new \Exception('Order address not found', 300002);
        }
        //上报klaviyo
        return (bool)KlaviyoService::instance()->reportSendPayEmail($orderInfo, $orderAddress);
    }

    /**
     * 获取支付链接
     *
     * @param string $orderNo
     * @param string $orderType
     * @param string $site
     * @param string $pf
     * @param int    $userId
     * @param string $eid
     * @param int    $exchangeGoodsType 换货订单商品类型，1：换货（销售sku），2：换货（非销售sku）
     * @param string $bmot
     * @param int    $userType          用户类型，1：注册用户，2：大客户，3：游客
     *
     * @return string
     */
    public function getPayUrl($orderNo, $orderType, $site = 'us', $pf = 'pc', $userId = 0, $eid = '', $paymentNo = '', $exchangeGoodsType = 0, $bmot = '', $userType = 0)
    {
        $domain    = \common\services\site\SiteService::instance()->getDomainBySiteAndPlatform($site, $pf);
        $signature = JwtService::instance()->createSignature('allow_user_id', $userId, time());
        if ($orderType == OrderRepository::ORDER_TYPE_EXCHANGE) {
            if (ExchangeGoodsTypeEnum::SALE_SKU == $exchangeGoodsType) {
                $url = AfterSaleFormatService::instance()->getExchangePlaceUrl($orderNo, $signature, '', $eid);
                return $domain . '/' . $url;
            } else {
                return PaymentRequestQueryService::instance()->getRequestUrl($site, $paymentNo);
            }
        }
        $url = sprintf('%s/order/place?source=3&order_no=%s&eid=%s&signature=%s', $domain, $orderNo, $eid, $signature);
        if (!empty($bmot)) {
            $url .= ('&bmot=' . $bmot);
        }
        if (UserTypeEnum::TYPE_TOURIST == $userType) {
            $url .= '&is_tourist=1';
        }
        return $url;
    }

    /**
     * 格式化带符号的金额
     *
     * @param float  $amount
     * @param string $symbol
     *
     * @return string
     */
    public function formatSymbolAmount($amount, $symbol)
    {
        return $symbol == '€' ? ($amount . '€') : ($symbol . $amount);
    }

    /**
     * @param $params
     *
     * @return bool
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    public function updatePickUpInfo($params)
    {
        // 首先获取当前的订单号的商品信息
        $orderNo   = $params['salesOrderNo'] ?? '';
        $goodsList = is_string($params['dispatchInfo']) ? json_decode($params['dispatchInfo'], 1) : $params['dispatchInfo'];

        // 过一遍通用的验证
        if (empty($orderNo)) {
            throw new \Exception('salesOrderNo is null', 800000);
        }
        if (empty($goodsList)) {
            throw new \Exception('dispatchInfo is null', 800000);
        }

        // 商品信息不存在
        $orderGoodsList = OrderGoodsRepository::instance()->getOrderGoodsByOrderNos($orderNo);
        if (empty($orderGoodsList)) {
            throw new \Exception('salesOrderNo as info is null', 800000);
        }
        // 处理一下数据的排序
        $goodsList      = array_column($goodsList, null, 'sku_code');
        $orderGoodsList = array_column($orderGoodsList, null, 'sku_code');

        foreach ($goodsList as $item) {
            // 处理一下全转大写，因为erp那边的数据问题
            $skuCode = strtoupper($item['sku_code']);
            // 看看存在不
            if ($orderGoodsList[$skuCode]['delivery_method'] == 2) {
                // 拿一下当前的信息，去更新
                $updateData = [
                    'pick_up_order_no' => $item['pick_up_order_no'],
                ];
                OrderGoodsRepository::instance()->update($updateData, ['order_goods_id' => $orderGoodsList[$skuCode]['order_goods_id']]);
            }
        }

        // 记录插入日志
        $operator = is_string($params['operator']) ? json_decode($params['operator'], 1) : $params['operator'];
        //兼容erp入参
        if (isset($operator['auditId']) && isset($operator['auditName'])) {
            $operator = [
                'type' => $operator['type'] ?? 4,
                'id'   => $operator['auditId'] ?? 0,
                'name' => $operator['auditName'] ?? '',
            ];
        }
        $context              = new \stdClass();
        $context->orderNo     = $orderNo;
        $context->preStatus   = OrderRepository::STATUS_UNDELIVERY;
        $context->orderStatus = OrderRepository::STATUS_UNDELIVERY;
        $context->logCode     = 9000011;
        $context->operator    = ['type' => 2, 'id' => $operator['id'], 'name' => $operator['name']];
        $replace              = ['supplement' => json_encode($params, 320)];
        OrderLogRepository::instance()->insertLogByContext($context, $replace);

        return true;
    }

    /**
     * @param $params
     *
     * @return array|int|void
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     * @throws \yii\httpclient\Exception
     */
    public function addPickUpAddress($params)
    {
        // 默认的是添加自提地址
        $userId     = $params['user_id'] ?? 0;
        $updateData = [
            'user_id'       => $params['user_id'],
            'first_name'    => $params['first_name'] ?? '',
            'last_name'     => $params['last_name'] ?? '',
            'email'         => $params['email'],
            'phone'         => $params['phone'],
            'is_subscriber' => !empty($params['is_subscriber']) ? $params['is_subscriber'] : 0,
            'country_code'  => $params['country_code'] ?? '',
            'state_code'    => $params['state_code'] ?? '',
            'city'          => $params['city'] ?? '',
            'zip'           => $params['zip'] ?? '',
            'order_no'      => $params['order_no'] ?? '',
        ];

        if (!empty($params['order_no'])) {
            $pickUpAddress = OrderPickUpAddressRepository::instance()->getByOrderNo($params['order_no']);
            if (!empty($pickUpAddress)) {
                $result = OrderPickUpAddressRepository::instance()->updateByOrderNo($params['order_no'], $updateData);
                if ($result) {
                    return $this->returnSuccess($result, '根据订单编号，更新自提地址成功');
                } else {
                    return $this->returnError(300001201, '根据订单编号，更新自提地址失败', $result);
                }
            }
        } else {
            $list = OrderPickUpAddressRepository::instance()->getListByUserId($userId, true);
            if (!empty($list)) {
                $pickUpAddress = $list[0] ?? [];
                $result        = OrderPickUpAddressRepository::instance()->updateById($pickUpAddress['id'], $updateData);
                if ($result) {
                    return $this->returnSuccess($result, '根据用户id，更新自提地址成功');
                } else {
                    return $this->returnError(300001202, '根据用户id，更新自提地址失败', $result);
                }
            }
        }

        if (isset($params['is_subscriber']) && $params['is_subscriber'] == 1) {
            AddressService::instance()->userSubscribe($params, $userId);
        }
        $updateData['created_at'] = time();
        $result                   = OrderPickUpAddressRepository::instance()->insert($updateData);
        if ($result) {
            return $this->returnSuccess($result, '插入自提地址成功');
        } else {
            return $this->returnError(300001202, '插入自提地址失败', $result);
        }
    }

    /**
     * @param $params
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    public function modifyPickAddress($params)
    {
        $data              = [
            'email'      => $params['email'] ?? '',
            'phone'      => $params['phone'] ?? '',
            'updated_at' => date('Y-m-d H:i:s', time()),
        ];
        $where['order_no'] = $params['order_no'];
        $result            = OrderPickUpAddressRepository::instance()->updateByOrderNo($params['order_no'], $data);
        if ($result) {
            return $this->returnSuccess();
        }
        return $this->returnError(980001, 'update error');
    }

    /**
     * @param $params
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    public function modifyDelivery($params)
    {
        $orderNo       = $params['order_no'] ?? '';
        $orderGoodsIds = $params['order_goods_ids'] ?? [];
        $orderInfo     = OrderRepository::instance()->getOrderByOrderNo($orderNo);

        // 校验订单状态
        if (!in_array($orderInfo['order_status'], [
            OrderRepository::STATUS_UNPAID,
            OrderRepository::STATUS_PAID_AUDIT,
            OrderRepository::STATUS_UNDELIVERY,
            OrderRepository::STATUS_DELIVERY_SECTION,
            OrderRepository::STATUS_PAID_SUCCESS,
            OrderRepository::STATUS_IN_STOCK,
        ])) {
            throw new Exception('Modification of shipping method is not allowed', 810501);
        }

        $orderGoodsInfo = OrderGoodsRepository::instance()->getOrderGoodsArrayByIds($orderGoodsIds);
        // 验证一下商品信息是否支持修改配送方式
        foreach ($orderGoodsInfo as $item) {
            // 只要有发货的商品，就弹窗提示
            if (in_array($item['dispatch_status'], [1, 2])) {
                throw new Exception("{$item['sku_code']}产品已发货，无法修改发货方式", 810501);
            }
        }

        $postData = [
            'order_no'     => $orderNo,
            'first_name'   => $params['first_name'],
            'last_name'    => $params['last_name'],
            'company'      => $params['company'] ?? '',
            'email'        => $params['email'],
            'phone'        => $params['phone'],
            'fax'          => $params['fax'] ?? '',
            'country'      => $params['country'],
            'country_code' => $params['country_code'],
            'state'        => $params['state'],
            'state_code'   => $params['state_code'],
            'city'         => $params['city'],
            'area'         => $params['area'] ?? '',
            'zip'          => $params['zip'] ?? '',
            'street1'      => $params['street1'] ?? '',
            'street2'      => $params['street2'] ?? '',
            'updated_at'   => time(),
        ];

        // 地址id存在，则更新地址信息
        if (empty($params['order_address_id'])) {
            $postData['created_at'] = time();
            // 写入订单地址表
            OrderAddressRepository::instance()->insert($postData);
        } else {
            OrderAddressRepository::instance()->updateById($params['order_address_id'], $postData);
        }

        // 更新订单商品配送方式
        $result = OrderGoodsRepository::instance()->update(
            [
                'delivery_method'        => 1,
                'pick_up_order_no'       => '',
                'pick_up_time'           => 0,
                'pick_up_warehouse_name' => '',
                'pick_up_warehouse_code' => ''
            ],
            ['in', 'order_goods_id', $orderGoodsIds]
        );

        // 为了保证下面的数据准确性，实时更新一次ES
        $esService = OrderElasticService::instance();
        $esService->updateOrderNoByOrderNos([$orderNo]);

        // 是否发送邮件
        if ($params['send_email'] == 1) {
            // 推送信息
            KlaviyoService::instance()->pushPickupEditInfo($orderNo, $orderGoodsIds, [], 'Delivery Update Node');
        }

        // 记录插入日志
        $operator = is_string($params['operator']) ? json_decode($params['operator'], 1) : $params['operator'];
        //兼容erp入参
        if (isset($operator['auditId']) && isset($operator['auditName'])) {
            $operator = [
                'type' => $operator['type'] ?? 4,
                'id'   => $operator['auditId'] ?? 0,
                'name' => $operator['auditName'] ?? '',
            ];
        }
        $context              = new \stdClass();
        $context->orderNo     = $orderNo;
        $context->preStatus   = $orderInfo['order_status'];
        $context->orderStatus = $orderInfo['order_status'];
        $context->logCode     = 9000012;
        $context->operator    = ['type' => 2, 'id' => $operator['id'], 'name' => $operator['name']];
        $replace              = ['supplement' => json_encode($params, 320)];
        OrderLogRepository::instance()->insertLogByContext($context, $replace);

        $orderGoodsInfo = OrderGoodsRepository::instance()->getOrderGoodsArrayByIds($orderGoodsIds, 'sku_code');
        $skuCode        = array_column($orderGoodsInfo, 'sku_code');
        $this->wxPushDelivery($orderNo, $skuCode, $params['city'], $params['street1'], $params['street2'], $operator['name']);

        if ($result) {
            return $this->returnSuccess();
        }
        return $this->returnError(980001, 'update error');
    }


    public function wxPushDelivery($orderNo, $skuCode, $city, $street1, $street2, $operatorName)
    {
        // 修改配送方式企业微信通知
        $msg = [
            "#### 修改配送方式通知",
            "订单号：{$orderNo}",
            "sku：" . implode(',', $skuCode),
            "修改后配送方式：物流配送",
            "收货地址：",
            "城市：{$city}",
            "城市/市：{$city}",
            "地址1：{$street1}",
            "地址2：{$street2}",
            "操作人：{$operatorName}",
        ];
        EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'delivery_update');
    }

    /**
     * 校验当前订单内的商品是否已经全部发货
     *
     * @param array $orderGoodsList 订单商品信息
     * @param array $dispatchInfo   oms推送过来的信息
     * @param int   $type           校验的方式 1-发货校验 2-换货校验
     *
     * @return bool
     */
    public function isDeliveryAll($orderGoodsList, $dispatchInfo = [], $type = 1, $deliveryVersion = 1)
    {
        // 根据本地商品数据来做校验
        $unDeliveryGoods = [];
        foreach ($orderGoodsList as $orderGoods) {
            // 如果商品已经被换货了，那么跳出这个循环
            if ($orderGoods['is_exchange'] == 2) {
                continue;
            }
            if ($deliveryVersion == 2) {
                // 新版接口兼容
                // 如果商品的发货状态不等于全部发货，那么写入一个sku做判断
                if ($orderGoods['dispatch_status'] != OrderGoodsRepository::DISPATCH_STATUS_DELIVERY) {
                    $unDeliveryGoods[] = strtoupper($orderGoods['sku_code']);
                }
                continue;
            }

            // 获取旧版接口数据
            if ($orderGoods['dispatch_status'] != OrderGoodsRepository::DISPATCH_STATUS_DELIVERY) {
                $unDeliveryGoods[] = $orderGoods['sku_id'];
            }
        }

        // 如果你是换货的话，那么只要验证这个就行了
        if ($type == 2 && !empty($unDeliveryGoods)) {
            // 换货但是没有全部发货
            return false;
        }

        // 正常都是全部发货
        if (empty($unDeliveryGoods)) {
            return true;
        }

        // 额外判断oms推送的信息
        $diffArray = [];
        if ($deliveryVersion == 2) {
            foreach ($dispatchInfo as $value) {
                if ($value['dispatch_status'] == 2) {
                    $diffArray[] = $value['sku_code'];
                }
            }
        } else {
            foreach ($dispatchInfo as $value) {
                if ($value['dispatch_status'] == 2) {
                    $diffArray[] = $value['sku'];
                }
            }
        }

        $diff = array_diff($unDeliveryGoods, $diffArray);
        if (empty($diff)) {
            //全部发货
            return true;
        } else {
            //部分发货
            return false;
        }
    }

    /**
     * 判断当前是否要处理OMS推送的发货信息
     */
    public function checkDeliveryGoodsStatus($dispatchInfo)
    {
        $result = false;
        foreach ($dispatchInfo as $value) {
            if (in_array($value['dispatch_status'], [1, 2])) {
                $result = true;
            }
        }
        return $result;
    }


    /**
     * 检查某段时间内未支付的订单是否已经支付（处理第三支付超时导致系统回写订单状态失败的情况） - 仅限使用braintree支付
     *
     * @param int    $createdStart 支付单创建开始时间
     *
     * @param string $orderNo      订单号
     *
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     * @author liangchupeng
     */
    public function checkOrderIsPaid($createdStart, $orderNo = null)
    {
        $where = [];
        if (!empty($createdStart)) {
            $where[] = ['>=', 'created_at', strtotime($createdStart)];
        }
        if (!empty($orderNo)) {
            $where[] = ['=', 'business_no', $orderNo];
        }
        $where[]  = ['=', 'is_test', 0];
        $where[]  = ['=', 'status', PaymentRepository::STATUS_UNPAID];
        $where[]  = ['in', 'payment_method', [PaymentRepository::PAYMENT_METHOD_PAYPAL, PaymentRepository::PAYMENT_METHOD_APPLE_PAY, PaymentRepository::PAYMENT_METHOD_CREDIT_CARD, PaymentRepository::PAYMENT_METHOD_GIROPAY, PaymentRepository::PAYMENT_METHOD_GOOGLE_PAY, PaymentRepository::PAYMENT_METHOD_SOFORT]];
        $page     = 1;
        $pageSize = 100;
        while (true) {
            $paymentList = PaymentRepository::instance()->getPageList($where, $page, $pageSize, ['payment_id' => SORT_ASC], ['payment_no', 'business_no']);
            if (empty($paymentList)) {
                break;
            }
            foreach ($paymentList as $payment) {
                $transaction = BraintreeService::instance()->getTransactionByPaymentNo($payment['payment_no']);
                if ($transaction === false) {
                    continue;
                }
                if (!in_array(strtolower($transaction->status), ['settled', 'settling', 'submitted_for_settlement'])) {
                    continue;
                }
                $msg = [
                    '#### 订单状态异常通知',
                    '订单号：' . $payment['business_no'],
                    '商城状态：未支付',
                    'BrainTree支付状态：' . $transaction->status,
                    'BrainTree交易ID：' . $transaction->id,
                ];
                EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
            }
            $page++;
            sleep(1);
        }
    }

    /**
     * 订单地址校验失败后，修改地址后，认为地址是ok的，推送给oms
     *
     * @param $params
     */
    public function addressIsPass($params)
    {
        $orderNo   = $params['order_no'];
        $orderInfo = OrderRepository::instance()->getOrderByOrderNo($orderNo);
        if ($orderInfo['is_fail_check_address'] != 1) {
            return $this->returnError(22700001, '该地址已检验通过');
        }


        $result = OrderRepository::instance()->update(['is_fail_check_address' => 0], ['order_no' => $orderNo]);
        if (empty($result)) {
            return $this->returnError(22700003, '更新失败，请重试');
        }

        $insertLogData = [
            'order_no'            => $orderNo,
            'is_show_to_customer' => 0,
            'code'                => 9000022,
            'content'             => \Yii::t('common/order_track', 9000022, '', 'zh-CN'),
            'pre_status'          => $orderInfo['order_status'],
            'current_status'      => $orderInfo['order_status'],
            'operator_type'       => 2,
            'operator_id'         => $params['admin_id'] ?? 0,
            'operator_name'       => $params['admin_name'] ?? '',
            'created_at'          => time(),
            'updated_at'          => time()
        ];

        OrderLogRepository::instance()->insert($insertLogData);

        //立即发送mq，推送给oms
        OrderService::instance()->sendDelayDelivery($orderNo, 2);
        //同步订单信息到es
        StockRedis::instance()->setOrderEsOrderNo([$orderNo]);
        return $this->returnSuccess([], '操作成功');
    }


    /**
     * 根据AB参数，判断是否要显示新版
     * true 显示 false 不显示
     *
     * @param string $pf       来源
     * @param string $abString 判断的AB结果
     * @param string $abStr    需要判断的AB实验
     * @param string $pfArr    需要判断的来源
     *
     * @return false|void
     */
    public function isNew($pf, $abString = '', $abStr = 'ab_m_checkout:1', $pfArr = ['m', 'android', 'ios'])
    {
        if (!in_array($pf, $pfArr)) {
            return false;
        }
        if (empty($abString)) {
            return false;
        }

        // 匹配AB结果
        $abMCheckoutBool = strpos($abString, $abStr);
        if ($abMCheckoutBool === false) {
            return false;
        }
        return true;
    }

    public function isAddressEditNew($pf, $abString, $abStr)
    {
        return $this->isNew($pf, $abString, $abStr, ['m', 'android', 'ios', 'pc']);
    }

    public function useCoupon($paymentInfo)
    {
        // 额外使用优惠券节点
        $context = new BaseContext();
        $this->contextInit($context, []);
        $context->orderNo = $paymentInfo['business_no'];

        OrderGetNode::instance()->invokeNode($context);

        $isNew = OrderService::instance()->isNew($context->orderData['platform'] ?? 'pc', $context->orderData['experiment_diverted'] ?? '', 'clear_shopping_cart:1', ['m', 'android', 'ios', 'pc']);

        // 只要不是新版，且优惠券ID为空，可以不走下面
        if ($isNew !== true || empty($context->orderData['coupon_user_id'])) {
            return;
        }

        OrderGetNode::instance()->invokeNode($context);
        OrderGoodsGetNode::instance()->invokeNode($context);

        $goodsList = [];
        foreach ($context->orderGoodsData as $goods) {
            $goodsList[] = [
                'sku_id'                 => $goods['sku_id'],
                'price'                  => $goods['goods_price'],
                'qty'                    => $goods['qty'],
                'spu_brand_id'           => $goods['spu_brand_id'] ?? 0,
                'spu_category_id_first'  => $goods['spu_category_id_first'] ?? 0,
                'spu_category_id_second' => $goods['spu_category_id_second'] ?? 0,
                'spu_category_id_third'  => $goods['spu_category_id_third'] ?? 0,
                'spu_category_id_fourth' => $goods['spu_category_id_fourth'] ?? 0,
                'activity_id'            => $goods['activity_id'],
                'activity_status'        => $goods['activity_status'] ?? 0,
                'selection'              => $goods['selection'] ?? [],
                'activity_type'          => !empty($goods['activity_type']) ? $goods['activity_type'] : 0,
                'exclusive_price'        => !empty($goods['exclusive_price']) ? $goods['exclusive_price'] : 0,
                'is_first_order_only'    => !empty($goods['is_first_order_only']) ? $goods['is_first_order_only'] : 0,
            ];
        }
        // 使用优惠券
        $result = CouponUseService::instance()->orderUseCoupon($context->orderData['coupon_user_id'], $context->orderData['user_id'], $goodsList, $context->orderData['site'], $context->orderData['order_no'], $context->orderData['currency']);

        if (1 !== $result['code']) {
            g_log_error('coupon-user-node-error.log', $result['info'], [$context, $result]);
            throw new \Exception($result['info'], 800306);
        }
    }

    /**
     * 第三方订单金额捕获(按订单商品维度捕获)
     *
     * @param $orderNo
     * @param $orderGoodsId
     *
     * @param $handlerName
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     * @since  2022.05.17
     * @author liangchupeng
     */
    public function capture($orderNo, $orderGoodsId, $handlerName)
    {
        $order          = OrderRepository::instance()->getOrderByOrderNo($orderNo, 'site');
        $orderGoodsList = OrderGoodsRepository::instance()->getByOrderNoAndOrderGoodsId($orderNo, $orderGoodsId, 'order_goods_id,pay_amount,pay_amount_actual,sku_code');
        if (empty($orderGoodsList)) {
            return $this->returnError(890001, 'Order goods not found');
        }
        $res = [];
        KlarnaService::setSite($order['site']);
        foreach ($orderGoodsList as $goods) {
            if ($goods['pay_amount_actual'] <= 0) {
                continue;
            }
            $result = KlarnaService::instance()->captureOrder($orderNo, $goods['sku_code'], $goods['pay_amount_actual'], $handlerName);
            if ($result['code'] != 1) {
                $res[] = 'sku:' . $goods['sku_code'] . ' fail:' . $result['info'];
            } else {
                $res[] = 'sku:' . $goods['sku_code'] . ' success';
            }
        }
        return $this->returnSuccess(null, json_encode($res, 320));
    }

    /**
     * 自动捕获订单（暂时只针对klarna，后续可扩展）
     *
     * 背景：若90天内，供应链未同步任何的物流单号，则在距离支付时间的第83天，进行系统自动提交结算
     *
     * @author liangchupeng
     *
     * @since  2022.05.18
     *
     */
    public function autoCapture()
    {
        //获取支付时间大于89天到90天的订单
        $where = [
            ['in', 'status', [PaymentRepository::STATUS_PAID, PaymentRepository::STATUS_PAID_AUDIT]],
            ['>', 'pay_at', time() - 90 * 86400 - 43200],
            ['<=', 'pay_at', time() - 83 * 86400],
            ['=', 'del_flag', 0],
            ['=', 'is_test', 0],
            ['=', 'business_type', 1],
            ['=', 'payment_method', PaymentRepository::PAYMENT_METHOD_KLARNA_PAY_LATER],
        ];
        $count = PaymentRepository::instance(true)->getCount($where);
        if ($count == 0) {
            return;
        }
        $pageSize  = 100;
        $totalPage = ceil($count / $pageSize);
        $page      = 1;
        while ($page <= $totalPage) {

            $paymentList = PaymentRepository::instance(true)->getPageList($where, $page, $pageSize, ['payment_id' => SORT_ASC], ['business_no']);
            if (empty($paymentList)) {
                break;
            }
            $page++;
            $orderNos = array_column($paymentList, 'business_no');
            //获取订单商品信息
            $condition      = [
                ['in', 'o.order_no', $orderNos],
            ];
            $orderGoodsList = OrderRepository::instance(true)->getOrderListByOrderNos($condition, 'o.site,og.order_no,og.sku_code,og.pay_amount_actual');

            if (empty($orderGoodsList)) {
                continue;
            }
            foreach ($orderGoodsList as $orderGoods) {
                if ($orderGoods['pay_amount_actual'] <= 0) {
                    continue;
                }
                KlarnaService::setSite($orderGoods['site']);
                KlarnaService::instance()->captureOrder($orderGoods['order_no'], $orderGoods['sku_code'], $orderGoods['pay_amount_actual'], '脚本自动捕获');
                var_dump($orderGoods['order_no'] . '_' . $orderGoods['sku_code']);
            }
        }
    }


    /**
     * 支付待审核订单（payment review）超24小时提醒(脚本专用)
     *
     * @param int $expiredHour 超时时间（小时）
     */
    public function reviewOrderNotify($expiredHour)
    {
        $startPaidAt = time() - $expiredHour * 3600;
        $endPaidAt   = time() - 30 * 86400;//一个月前开始
        $where       = [
            ['=', 'order_status', OrderRepository::STATUS_PAID_AUDIT],
            ['<=', 'paid_at', $startPaidAt],
            ['>=', 'paid_at', $endPaidAt],
            ['=', 'del_flag', 0],
        ];
        var_dump(sprintf('查询的支付时间段：%s 到 %s', date('Y-m-d H:i:s', $startPaidAt), date('Y-m-d H:i:s', $endPaidAt)));
        $count = OrderRepository::instance(true)->getCount($where);
        if ($count == 0) {
            var_dump('没有需要处理的数据');
            return;
        }
        $pageSize  = 50;
        $totalPage = ceil($count / $pageSize);
        $page      = 1;
        while ($page <= $totalPage) {
            $orderList = OrderRepository::instance(true)->getPageList($where, $page, $pageSize, ['order_id' => SORT_ASC], ['order_no', 'paid_at']);
            if (empty($orderList)) {
                var_dump('数据处理完毕，退出');
                break;
            }
            $page++;
            $orderNos = array_column($orderList, 'order_no');
            $orderStr = implode(',', $orderNos);
            var_dump($orderStr);
            //系统预警
            $msg = [
                '### 支付待审核订单超24小时提醒',
                '> 订单号：' . $orderStr,
                '> 备注：请及时确认是否存在异常',
                '> 预警时间：' . date('Y-m-d H:i:s'),
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'order_notify');
            sleep(1);
        }
    }

    /**
     * 含次日达服务订单处于支付待审核状态（payment review）提醒
     *
     * @param int $beforeMin X分钟前
     */
    public function nextDayReviewNotify($beforeMin)
    {
        $where     = [
            ['in', 'order_status', [OrderRepository::STATUS_PAID_AUDIT, OrderRepository::STATUS_PAID_SUCCESS]],
            ['<=', 'paid_at', time() - $beforeMin * 60],
            ['>=', 'paid_at', time() - 5 * 86400], //支付成功5天内
            ['=', 'del_flag', 0],
        ];
        $orderList = OrderRepository::instance(true)->getListByCondition($where, ['order_no', 'order_status']);
        if (empty($orderList)) {
            Console::output('暂无处理的数据');
            return;
        }
        $condition      = [
            ['=', 'is_include_next_day', 1],
            ['>', 'value_added_service_amount', 0],
            ['=', 'del_flag', 0],
            ['in', 'order_no', array_column($orderList, 'order_no')],
        ];
        $orderGoodsList = OrderGoodsRepository::instance(true)->getListByCondition($condition, ['order_no']);
        if (empty($orderGoodsList)) {
            Console::output('未包含次日达的订单商品');
            return;
        }
        $orderList = array_column($orderList, 'order_status', 'order_no');
        $orderNos  = array_unique(array_column($orderGoodsList, 'order_no'));
        $previewOrderNos = [];
        $paidOrderNos    = [];
        foreach ($orderNos as $orderNo) {
            if (OrderRepository::STATUS_PAID_AUDIT == $orderList[$orderNo]) {
                $previewFlag = OrderRedis::instance()->getNextDayDeliveryPreviewNotice($orderNo);
                if (empty($previewFlag)) {
                    $previewOrderNos[] = $orderNo;
                }
            } else {
                $pushFlag = OrderRedis::instance()->getNextDayDeliveryNotPushOmsNotice($orderNo);
                if (empty($pushFlag)) {
                    $paidOrderNos[] = $orderNo;
                }
            }
        }
        if (!empty($previewOrderNos)) {
            $previewOrderStr = implode(',', $previewOrderNos);
            $msg             = [
                '次日达订单支付待审核提醒',
                '> 订单号：' . $previewOrderStr,
                '> 备注：次日达订单时效性要求高，请及时处理',
                '> 预警时间：' . date('Y-m-d H:i:s'),
            ];
            //97a4743f - Rain
            $result = EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'next_day_preview_notify', 'markdown', ['97a4743f']);
            if (1 == $result['code']) {
                //发送标记
                foreach ($previewOrderNos as $orderNo) {
                    OrderRedis::instance()->setNextDayDeliveryPreviewNotice($orderNo);
                }
            }
            Console::output('次日达提醒订单：' . $previewOrderStr);
            Console::output('次日达订单支付待审核提醒结果：' . json_encode($result, 320));
        }
        if (!empty($paidOrderNos)) {
            $paidOrderNoStr = implode(',', $paidOrderNos);
            $msg            = [
                '次日达订单已支付未正常下发oms提醒',
                '> 订单号：' . $paidOrderNoStr,
                '> 备注：请及时处理',
                '> 预警时间：' . date('Y-m-d H:i:s'),
            ];
            //g2ef979g - ice
            $result = EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'next_day_paid_notify', 'markdown', ['g2ef979g']);
            if (1 == $result['code']) {
                foreach ($paidOrderNos as $orderNo) {
                    OrderRedis::instance()->setNextDayDeliveryNotPushOmsNotice($orderNo);
                }
            }
            Console::output('次日达提醒订单：' . $paidOrderNoStr);
            Console::output('次日达订单已支付未正常下发oms提醒：' . json_encode($result, 320));
        }
    }

    /**
     * 获取订单地址修改日志列表
     *
     * @param $orderNo
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     * @author liangchupeng
     * @since  2022.06.10
     */
    public function getOrderAddrModifyLogList($orderNo)
    {
        $list = OrderAddressModifyLogRepository::instance()->getLogList($orderNo, 'log_id,content,operator_type,operator_id,operator_name,created_at');
        if (empty($list)) {
            return [];
        }
        foreach ($list as $k => $item) {
            $list[$k]['operator_type_str'] = OrderAddressModifyLogRepository::OPERATOR_TYPE_MAPPING[$item['operator_type']] ?? '';
            $list[$k]['created_at']        = date('Y-m-d H:i:s', $item['created_at']);
        }

        return $list;
    }

    /**
     * 售后完成，把所有的完结售后单，通知订单
     *
     * @param $params
     *
     * @return array
     * @throws Exception
     */
    public function afterSaleDoneNotify($params)
    {
        $order = OrderRepository::instance()->getOrderByOrderNo($params['order_no']);
        if (empty($order)) {
            return $this->returnError(489032, '查询不到订单');
        }
        //if ($order['is_sales_delivery_order'] == 0) {
        //    return $this->returnError(489031, '非销售出库单不做处理');
        //}
        //售后详情，暂时还未使用
        $afterSaleList         = $params['after_sale_list'] ?? [];
        $zeroAmountOrderCancel = $params['zero_amount_order_cancel'] ?? 0; //0元单是否需要取消订单
        $applyGoodsSummary     = $params['apply_goods_summary'] ?? []; //当前订单商品售后汇总，退款金额&退款数量
        $exchangeSkuList       = [];
        if (is_array($afterSaleList)) {
            foreach ($afterSaleList as $afterSale) {
                // 为空跳过
                if (empty($afterSale['after_sale_goods_list'])) {
                    continue;
                }
                // 判断一下数据格式是否正确
                $afterSaleGoodsList = $afterSale['after_sale_goods_list'];
                if (is_string($afterSale['after_sale_goods_list'])) {
                    $afterSaleGoodsList = json_decode($afterSale['after_sale_goods_list'], true);
                }
                // 换货走这个
                if (in_array($afterSale['processing_type'], [AfterSaleProcessTypeEnum::EXCHANGE_GOODS, AfterSaleProcessTypeEnum::EXCHANGE_NONSALE_SKU])) {
                    foreach ($afterSaleGoodsList as $afterSaleGoods) {
                        $skuCode = strtolower($afterSaleGoods['sku_code']);
                        if (!isset($exchangeSkuList[$skuCode])) {
                            $exchangeSkuList[$skuCode] = (int)$afterSaleGoods['qty'];
                        } else {
                            $exchangeSkuList[$skuCode] += (int)$afterSaleGoods['qty'];
                        }
                    }
                }
                //else if (!empty($params['refund_amount']) && $params['refund_amount'] > 0) {
                //    //
                //    foreach ($afterSaleGoodsList as $afterSaleGoods) {
                //        $skuCode = strtolower($afterSaleGoods['sku_code']);
                //        if (!isset($exchangeSkuList[$skuCode])) {
                //            $refundSkuList[$skuCode] = $afterSaleGoods['refund_amount'];
                //        } else {
                //            $refundSkuList[$skuCode] = bcadd($afterSaleGoods['refund_amount'], $refundSkuList[$skuCode]);
                //        }
                //    }
                //}
            }
        }
        $orderGoodsList = OrderGoodsRepository::instance()->getListByNo($params['order_no']);
        //当前订单所有商品是否都已经换货了
        $allGoodExchanged = false;
        // 售后换货sku
        if (!empty($exchangeSkuList)) {
            $allGoodExchanged = true;
            foreach ($orderGoodsList as $orderGoods) {
                $skuCode = strtolower($orderGoods['sku_code']);
                if (!isset($exchangeSkuList[$skuCode]) || $exchangeSkuList[$skuCode] < $orderGoods['qty']) {
                    $allGoodExchanged = false;
                    break;
                }
            }
        }
        $applyGoodsSummary = array_column($applyGoodsSummary, null, 'sku_code');
        //订单商品是否全部取消
        $isAllGoodsCanceled = true;
        foreach ($orderGoodsList as $orderGoods) {
            $applyGoods = $applyGoodsSummary[$orderGoods['sku_code']] ?? [];
            if (empty($applyGoods)) {
                //如果当前商品没有售后记录，则使用订单商品是否取消
                $isAllGoodsCanceled &= (bool)$orderGoods['is_cancel'];
                continue;
            }
            $updateData = [];
            //当商品已退金额大于等于支付金额，则取消
            if (bcsub($applyGoods['refund_amount'], $orderGoods['pay_amount'], 2) >= 0) {
                $updateData['is_cancel']   = OrderGoodsRepository::IS_CANCELED;
                $updateData['is_refunded'] = OrderGoodsRepository::ALL_REFUNDED;
            } else {
                $updateData['is_refunded'] = OrderGoodsRepository::PARTIAL_REFUNDED;
                //商品未取消
                $isAllGoodsCanceled = false;
            }
            if (empty($updateData)) {
                continue;
            }
            $updateData['updated_at'] = time();
            OrderGoodsRepository::instance()->update($updateData, ['order_goods_id' => $orderGoods['order_goods_id']]);
        }
        //订单关单条件： (退款金额>=支付金额 && 已取消全部商品 && (支付金额>0 || (支付金额=0 && 0元单要取消))) || 商品全部已换货
        if (
            $order['order_status'] != OrderRepository::STATUS_CLOSED &&
            (
                ($order['pay_amount'] <= $params['refund_amount'] && $isAllGoodsCanceled && ($order['pay_amount'] > 0 || (0 == $order['pay_amount'] && 1 == $zeroAmountOrderCancel)))
                || $allGoodExchanged
            )
        ) {
            $orderUpdateData = [
                'order_status' => OrderRepository::STATUS_CLOSED,
                'updated_at'   => time()
            ];
            OrderRepository::instance()->update($orderUpdateData, ['order_no' => $params['order_no']]);
            $operatorName = $allGoodExchanged ? '售后换货关闭订单' : ($isAllGoodsCanceled ? '已取消全部商品关闭订单' : '无可退金额关闭订单');
            OrderLogRepository::instance()->insertLog($params['order_no'], 9320301, [], $order['order_status'], $orderUpdateData['order_status'], 3, 0, $operatorName);

            //已支付或支付待审核订单通过售后取消订单，则支付单也取消
            if (in_array($order['order_status'], [OrderRepository::STATUS_PAID_AUDIT, OrderRepository::STATUS_PAID_SUCCESS])) {
                //支付单数据操作
                PaymentService::instance()->paymentClosed([], '售后关闭支付单', 0, ['type' => 3, 'name' => '售后完成回调'], $order['payment_no']);
            }

            //订单取消状态通知
            $this->orderStatusNotify($params['order_no'], OrderRepository::STATUS_CLOSED, ['name' => $operatorName, 'id' => 0, 'type' => 3]);

            // 走返还优惠券
            $this->rollBackCouponByOrder($params['order_no']);

            // 订单已发货则不上报
            $isDispatched = $order['is_dispatched'] ?? 0;
            if ($isDispatched != 1) {
                // 走上报神策
                $paymentData    = PaymentRepository::instance()->getByPaymentNo($order['payment_no']);
                $orderAmount    = OrderAmountRepository::instance()->getByOrderNo($order['order_no']);
                $orderGoodsList = OrderGoodsRepository::instance()->getOrderGoodsByOrderNo($order['order_no']);
                $addSource      = $afterSaleList[0]['add_source'] ?? 1;
                $pf             = $addSource == 1 ? 'pc' : 'manage';
                SensorsDataService::instance()->cancleOrder(array_merge($order, $orderAmount), $paymentData, $orderGoodsList, $pf);
            }

            StockRedis::instance()->setOrderEsOrderNo([$params['order_no']]);
        }

        //有换货商品，更新订单商品大宽表
        if (!empty($exchangeSkuList)) {
            StockRedis::instance()->setOrderGoodsALlOrderNo([$params['order_no']]);
        }

        return $this->returnSuccess([]);
    }

    public function rollBackCouponByOrder($orderNo)
    {
        $order = OrderRepository::instance()->getOrderByOrderNo($orderNo);
        // 未使用优惠券，跳出
        if (empty($order['coupon_user_id'])) {
            return;
        }
        // 获取商品信息
        $orderGoodsList = OrderGoodsRepository::instance()->getListByNo($order['order_no']);
        foreach ($orderGoodsList as $orderGoods) {
            // 订单已发货，跳出
            if ($orderGoods['is_dispatched'] == 1) {
                return;
            }
        }
        // 回滚优惠券
        $result = CouponUseService::instance()->rollBackCouponByOrder($order['coupon_user_id']);
        g_log_info('rollbackCoupon.log', '关单回滚优惠券', ['coupon_user_id' => $order['coupon_user_id'], 'order_no' => $order['order_no'], $result]);
        return $result;
    }

    public function afterSaleModifyOrder($goodsList = '', $orderNo = '', $afterSaleNo = '', $addressInfo = [])
    {
        if (is_string($goodsList)) {
            $goodsList = json_decode($goodsList, true);
        }
        if (!is_array($goodsList)) {
            return $this->returnSuccess();
        }
        foreach ($goodsList as $goods) {
            if (empty($goods['order_goods_id']) || empty($goods['delivery_method'])) {
                continue;
            }
            $order = OrderRepository::instance(true)->getOrderByOrderNo($goods['order_no'], ['pay_amount', 'order_status']);
            if (empty($order)) {
                continue;
            }
            $update = [
                'delivery_method' => $goods['delivery_method']
            ];
            $where  = [
                'order_goods_id' => $goods['order_goods_id']
            ];
            OrderGoodsRepository::instance()->update($update, $where);
            OrderLogRepository::instance()->insertLog($goods['order_no'], 9320303, ['supplement' => json_encode($update, JSON_UNESCAPED_UNICODE)], $order['order_status'], $order['order_status'], 3, 0, '售后修改信息通知');
        }

        if (!empty($orderNo) && !empty($afterSaleNo) && !empty($addressInfo)) {
            if (is_string($addressInfo)) {
                $addressInfo = json_decode($addressInfo, true);
            }
            $this->afterSaleModifyOrderAddress($orderNo, $afterSaleNo, $addressInfo);
        }

        return $this->returnSuccess();
    }

    /**
     * 修改订单地址表
     *
     * @param       $orderNo
     * @param       $afterSaleNo
     * @param array $address
     *
     * @return array
     */
    public function afterSaleModifyOrderAddress($orderNo, $afterSaleNo, $address = [])
    {
        if (empty($orderNo)) {
            return $this->returnSuccess();
        }
        $order = OrderRepository::instance(true)->getOrderByOrderNo($orderNo, ['user_id', 'pay_amount', 'order_status']);
        if (empty($order)) {
            return $this->returnSuccess();
        }
        $update = [];
        if (!empty($address['first_name'])) {
            $update['first_name'] = $address['first_name'];
        }
        if (!empty($address['last_name'])) {
            $update['last_name'] = $address['last_name'];
        }
        if (!empty($address['country_code'])) {
            $update['country_code'] = $address['country_code'];
        }
        if (!empty($address['country'])) {
            $update['country'] = $address['country'];
        }
        if (!empty($address['country_id'])) {
            $update['country_id'] = $address['country_id'];
        }
        if (!empty($address['state_code'])) {
            $update['state_code'] = $address['state_code'];
        }
        if (!empty($address['state'])) {
            $update['state'] = $address['state'];
        }
        if (!empty($address['state_id'])) {
            $update['state_id'] = $address['state_id'];
        }
        if (!empty($address['city'])) {
            $update['city'] = $address['city'];
        }
        if (!empty($address['street1'])) {
            $update['street1'] = $address['street1'];
        }
        if (isset($address['street2'])) {
            $update['street2'] = $address['street2'];
        }
        if (!empty($address['email'])) {
            $update['email'] = $address['email'];
        }
        if (!empty($address['phone'])) {
            $update['phone'] = $address['phone'];
            if (!empty($update['country_code'])) {
                $update['international_phone'] = PhoneNumberService::instance()->format($update['phone'], $update['country_code']);
            }
        }
        if (!empty($address['zip'])) {
            $update['zip'] = $address['zip'];
        }
        if (isset($address['company'])) {
            $update['company'] = $address['company'];
        }
        if (empty($update)) {
            return $this->returnSuccess();
        }
        $orderAddress = OrderAddressRepository::instance()->getAddressByOrderNo($orderNo);
        if (empty($orderAddress)) {
            $orderAddress               = $update;
            $orderAddress['order_no']   = $orderNo;
            $orderAddress['user_id']    = $order['user_id'];
            $orderAddress['address_id'] = 0;
            $orderAddress['created_at'] = time();
            $orderAddress['updated_at'] = time();
            OrderAddressRepository::instance()->insert($orderAddress);
        } else {
            OrderAddressRepository::instance()->updateByOrderNo($orderNo, $update);
        }
        OrderLogRepository::instance()->insertLog($orderNo, 9320303, ['supplement' => json_encode($update, JSON_UNESCAPED_UNICODE)], $order['order_status'], $order['order_status'], 3, 0, '售后修改地址信息通知');
        OrderAddressModifyLogRepository::instance()->afterCreate($orderNo, $afterSaleNo, $orderAddress, $address, 3, 0, '售后修改地址信息通知');

        return $this->returnSuccess();
    }

    /**
     * 获取AB的实际值
     *
     * @param          $pf
     * @param string   $abString
     * @param string   $abStr
     * @param string[] $pfArr
     *
     * @return bool
     */
    public function getAbNum($pf, $abString = '', $abStr = 'ab_m_checkout', $pfArr = ['pc', 'm', 'android', 'ios'], $defaultValue = 1)
    {
        if (!in_array($pf, $pfArr)) {
            return $defaultValue;
        }
        if (empty($abString)) {
            return $defaultValue;
        }
        if (empty($abStr)) {
            return $defaultValue;
        }
        // 匹配AB，获取结果
        $abArr = explode(',', $abString);
        foreach ($abArr as $item) {
            $abMCheckoutBool = strpos($item, $abStr);
            if ($abMCheckoutBool !== false) {
                $nowAb = explode(':', $item);
                return $nowAb[1] ?? $defaultValue;
            }
        }
        // 默认返回实验结果
        return $defaultValue;
    }

    /**
     * 获取paypal支付方式实验结果
     *
     * @param string $pf                 来源端
     *
     * @param string $experimentDiverted 实验参数
     * @param string $site               站点
     *
     * @return int  0.不做ab  1.bt paypal   2.paypal直连
     */
    public function getPaypalAbResult($pf, $experimentDiverted, $site)
    {
        if (in_array($pf, ['android', 'ios'])) { //android 和 ios 不做ab
            return 0;
        }
        if (!in_array($site, array_column((new BasicDataService())->getSiteList(), 'site_code'))) {
            return 0;
        }
        $defaultNum = 1;
        $abName     = '';
        //新的实验名，根据需求不区分端
        if ('us' == $site) {
            $abName = 'ab_us_paypal_local';
        }
        if ('uk' == $site) {
            //$abName = 'ab_uk_paypal_local';
            $defaultNum = 2;
        }
        if (in_array($site, ['es', 'fr', 'de'])) {
            //$abName = 'ab_eu_paypal_local';
            $defaultNum = 2;
        }
        //$abName = 'pc' == $pf ? 'ab_paypal_pc' : 'ab_paypal_m';
        return $this->getAbNum($pf, $experimentDiverted, $abName, ['m', 'pc'], $defaultNum);
    }

    /**
     * 获取afterpay支付方式实验结果
     *
     * @param string $experimentDiverted 实验参数
     * @param string $pf                 来源端
     *
     * @return int    1.无afterpay   2.有afterpay    默认：1
     */
    public function getAfterpayAbResult($pf, $experimentDiverted, $site)
    {
        if (in_array($pf, ['android', 'ios'])) { //android 和 ios 不做ab
            return 1;
        }
        $abName = '';
        //新的实验名，根据需求不区分端
        if ('us' == $site) {
            $abName = 'pc' == $pf ? 'ab_us_afterpay_pc' : 'ab_us_afterpay_m';
        }
        if (empty($abName)) {
            return 1;
        }
        return $this->getAbNum($pf, $experimentDiverted, $abName, ['m', 'pc']);
    }

    /**
     * 获取stripe Affirm实验结果
     *
     * pc:  ab_us_stripe_affirm_pc
     * m:   ab_us_stripe_affirm_m
     * 旧版参数值：1
     * 新版参数值：2
     *
     * @param $site
     * @param $pf
     * @param $experimentDiverted
     *
     * @return bool|int
     */
    public function getStripeAffirmAbResult($pf, $experimentDiverted, $site)
    {
        $abName = '';
        if ('us' == $site) {
            if ('pc' == $pf) {
                return 2; //pc 全量新版
            }
            $abName = 'ab_us_stripe_affirm_m';
        }
        return $this->getAbNum($pf, $experimentDiverted, $abName, ['m', 'android', 'ios', 'pc']);
    }

    /**
     * 仅仅关闭订单的更新和插入日志
     *
     * @param $orderNo
     * @param $closedType
     * @param $closeReason
     *
     * @return array
     * @throws Exception
     */
    public function onlyOrderClose($orderNo, $closedType, $closeReason)
    {
        $order = OrderRepository::instance(true)->getOrderByOrderNo($orderNo, ['order_status']);
        if (!in_array($order['order_status'], [OrderRepository::STATUS_UNPAID])) {
            return $this->returnError(500329, 'The order status does not allow this operation');
        }
        $update = [
            'order_status'  => OrderRepository::STATUS_CLOSED,
            'is_closed'     => 1,
            'closed_at'     => time(),
            'closed_type'   => $closedType,
            'closed_reason' => $closeReason
        ];
        $where  = ['order_no' => $orderNo];

        $result = OrderRepository::instance()->update($update, $where);
        if (!empty($result)) {
            OrderLogRepository::instance()->insertLog($orderNo,
                9320302,
                ['supplement' => $closeReason],
                $order['order_status'],
                OrderRepository::STATUS_CLOSED,
                3,
                0,
                'onlyOrderClose'
            );
            return $this->returnSuccess($result, 'success');
        } else {
            return $this->returnError(500328, 'Please try again');
        }

    }

    public function valetOrderCalculate($params)
    {
        //组织上下文数据
        $context = new ValetOrderContext();
        PlaceOrderService::instance()->valetOrderContextInit($context, $params);
        $context->salesType                      = $params['order_sales_type'] ?? 1;
        $context->valetOrderCalculate            = true;
        $context->couponDisabledNoThrowException = 1;
        $context->filterNextDayService           = 0;
        // 商品请求eta数据
        $context->isRequestETA = true;
        $nodeChain             = [];
        if ($context->isUpdateOrder) {
            $nodeChain[] = OrderGetNode::instance();
            $nodeChain[] = UserInfoGetNode::instance();
        }
        $nodeChain = array_merge($nodeChain,
            [
                //检查是否为门店订单
                CheckStoreOrderNode::instance(),
                //商品参数组装
                GoodsParamsFormatNode::instance(),
                //获取批量商品数据
                GoodsListGetNode::instance(),
                //商品数据校验
                GoodsListCheckNode::instance(),
                //代客下单地址保存兼容
                OrderAddressAddNode::instance(),

                // 显示活动，放在activityNode前
                LimitedTimeReductionNode::instance(),

                //活动获取，校验商品限购节点
                ActivityNode::instance(),

                //门店限时活动
                OfflineStoreLimitedTimeReductionNode::instance(),

                //套装价格价格优化处理
                SuitPriceHandleNode::instance(),

                //套装折扣计算，必须放在活动后，涉及套装能秒杀
                SuitDiscountNode::instance(),
                //积分
                PointsDiscountNode::instance(),
                //优惠券使用节点 积分必须放在增值服务前。
                CouponUseNode::instance(),
                //客服优惠设置，分配到商品
                CustomerServiceHandleNode::instance(),
                //运费服务
                ShippingFeeNode::instance(),
                //增值服务
                ValueAddedServiceNode::instance(),
                //服务eta
                FormatGoodsServiceEtaNode::instance(),
                //安装服务的处理节点
                InstallationServiceNode::instance(),
                //保养服务
                ProtectionPlanNode::instance(),
                //店长优惠
                StoreManagerDiscountNode::instance(),
                //组装订单相关插入mysql的数据
                TaxHandleNode::instance(),
                //试算统计节点
                ValetOrderCalculateNode::instance(),
            ]
        );

        $result = NodeExecutionEngine::instance()->executeEngine($context, $nodeChain);
        if (1 == $result['code']) {
            return $this->returnSuccess($context->response, 'success');
        } else {
            return $result;
        }
    }


    /**
     * 更新订单金额的信息
     *
     * @param string $orderNo         订单号
     * @param float  $paymentDiscount 支付立减金额
     *
     * @return array
     */
    public function updateOrderAmount($orderNo, $paymentDiscount = 0)
    {
        $orderInfo = OrderRepository::instance()->getOrderByOrderNo($orderNo);
        if (empty($orderInfo)) {
            return $this->returnError(600002, 'Order data query failed');
        }
        $orderAmountInfo = OrderAmountRepository::instance()->getByOrderNo($orderNo);
        //支付立减金额相同，不用处理
        if ($orderAmountInfo['payment_discount'] == $paymentDiscount) {
            return $this->returnSuccess();
        }
        $orderGoodsList = OrderGoodsRepository::instance()->getOrderGoodsByOrderNo($orderNo);
        $suitDiscount   = array_sum(array_column($orderGoodsList, 'suit_discount'));
        $transaction    = Yii::$app->dbFecshop->beginTransaction();
        try {
            //计算订单支付金额（除支付立减外）
            $orderAmountInfo['suit_discount'] = $suitDiscount;
            $orderAmount                      = OrderExtendService::instance()->getPayDiscount($orderAmountInfo);
            //**********更新订单的支付金额**********
            $payAmount         = bcsub($orderAmount, $paymentDiscount, 2);
            $updateOrderResult = OrderRepository::instance()->updateAmount($orderNo, $payAmount, bcmul($payAmount, $orderInfo['to_usd_rate'], 2), bcmul($payAmount, $orderInfo['to_cny_rate'], 2));
            if (false === $updateOrderResult) {
                throw new \Exception('Update order payment amount fail', 800426);
            }
            //**********更新订单优惠金额表**********
            $updateAmountResult = OrderAmountRepository::instance()->updateAmount($orderNo, $paymentDiscount, bcmul($paymentDiscount, $orderInfo['to_usd_rate'], 2), bcmul($paymentDiscount, $orderInfo['to_cny_rate'], 2));
            if (false === $updateAmountResult) {
                throw new \Exception('Update order amount fail', 800427);
            }
            //**********更新订单商品的金额信息**********
            $orderGoodsAmountList = OrderExtendService::instance()->getOrderGoodsPayAmount($orderGoodsList);
            //分摊支付立减金额
            $orderGoodsPaymentDiscountList = MyFunction::instance()->amountSplit($paymentDiscount, $orderGoodsAmountList);
            foreach ($orderGoodsAmountList as $orderGoodsId => $goodsPayAmount) {
                $orderGoodsPaymentDiscount = $orderGoodsPaymentDiscountList[$orderGoodsId];
                // 更新订单商品的支付立减金额
                $goodsPayAmount = bcsub($goodsPayAmount, $paymentDiscount, 2);
                $updateResult   = OrderGoodsRepository::instance()->updateAmount($orderGoodsId, $goodsPayAmount, bcmul($goodsPayAmount, $orderInfo['to_usd_rate'], 2), bcmul($goodsPayAmount, $orderInfo['to_cny_rate'], 2), $orderGoodsPaymentDiscount, bcmul($orderGoodsPaymentDiscount, $orderInfo['to_usd_rate'], 2), bcmul($orderGoodsPaymentDiscount, $orderInfo['to_cny_rate'], 2));
                if (false === $updateResult) {
                    throw new \Exception('Update order goods payment discount fail', 800425);
                }
            }

            $transaction->commit();
            return $this->returnSuccess();

        } catch (\Throwable $e) {
            $transaction->rollBack();
            return $this->returnError($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 返回商品eta
     *
     * @param $etaInfoStr
     *
     * @return array
     */
    public function getOrderGoodsEta($etaInfoStr)
    {
        $data = [
            'max' => 0,
            'min' => 0,
        ];
        if (empty($etaInfoStr)) {
            return $data;
        }
        $etaInfo = json_decode($etaInfoStr, true);
        if (empty($etaInfo)) {
            return $data;
        }
        $data['min'] = $etaInfo['minEtaTime'];
        $data['max'] = $etaInfo['maxEtaTime'];

        return $data;
    }

    public function orderChangeSendRabbitMQ($order, $orderGoodsList)
    {
        //mq发送数据
        $sendData = [
            'order_no'            => $order['order_no'],
            'site'                => $order['site'],
            'language'            => $order['language'],
            'currency'            => $order['currency'],
            'order_type'          => $order['order_type'],
            'order_status'        => $order['order_status'],
            'info'                => $order,
            'create_order_source' => $order['create_order_source'] ?? '', '',
            'tax_declare_status'  => $order['tax_declare_status'],
        ];
        if (!empty($orderGoodsList)) {
            $sendData['goods_list'] = $orderGoodsList;
        }

        $exchange = \Yii::$app->params['order_change_mq']['exchange_name'] ?? '';
        $routeKey = \Yii::$app->params['order_change_mq']['route_key'] ?? '';
        if (!empty($exchange) && !empty($routeKey) && !empty($sendData)) {
            $result = RabbitMq::send($exchange, $routeKey, json_encode($sendData, 320));
        } else {
            $msg = sprintf('### 订单状态变化 查找不到rabbitmq的交换机名:%s routeKey:%s', $exchange, $routeKey);
            EnterpriseWechatMessage::instance()->wechatMsgSend([$msg, $sendData], 'order');
        }
        g_log_info('sendmq.log', '发送mq', [$exchange, $routeKey, $result]);
        return $result ?? [];
    }


    /**
     * 获取订单商品的优惠金额总和
     *
     *
     * @params int[] order_goods_ids 订单商品ID列表
     *
     */
    public function getOrderGoodsTotalDiscount($orderGoodsIds)
    {
        $orderGoodsList = OrderGoodsRepository::instance()->getListByOrderGoodsIds('', $orderGoodsIds);
        $totalDiscount  = 0;
        if (empty($orderGoodsList)) {
            return $this->returnSuccess(['total_discount' => $totalDiscount, 'discount_list' => []]);
        }
        $discountList = [];
        foreach ($orderGoodsList as $orderGoods) {
            $totalDiscount                                                  = bcadd($totalDiscount, $orderGoods['coupon_discount'], 2);
            $totalDiscount                                                  = bcadd($totalDiscount, $orderGoods['points_discount'], 2);
            $discountList[$orderGoods['order_goods_id']]['coupon_discount'] = $orderGoods['coupon_discount'];
            $discountList[$orderGoods['order_goods_id']]['points_discount'] = $orderGoods['points_discount'];
        }
        return $this->returnSuccess(['total_discount' => $totalDiscount, 'discount_list' => $discountList]);
    }


    public function emailSendRabbitMQ($orderNo, $businessCode, $skuId = '', $packageNumber = '', $trackingNumber = '')
    {
        //mq发送数据
        $sendData = [
            'order_no'        => $orderNo,
            'business_code'   => $businessCode,
            'sku_id'          => $skuId,
            'package_number'  => $packageNumber,
            'tracking_number' => $trackingNumber,
        ];
        $exchange = \Yii::$app->params['fulfillment_order_email_send_mq']['exchange_name'] ?? '';
        $routeKey = \Yii::$app->params['fulfillment_order_email_send_mq']['route_key'] ?? '';
        $result   = [];
        if (!empty($exchange) && !empty($routeKey) && !empty($sendData)) {
            $result = RabbitMq::send($exchange, $routeKey, json_encode($sendData, 320));
        } else {
            $msg = sprintf('### 发送邮件 查找不到rabbitmq的交换机名:%s routeKey:%s', $exchange, $routeKey);
            EnterpriseWechatMessage::instance()->wechatMsgSend([$msg, $sendData], 'order');
        }
        g_log_info('sendmq.log', '发送mq', [$sendData, $exchange, $routeKey, $result]);
        return $result ?? [];
    }

    /**
     * 订单商品妥投处理
     *
     * @params string $orderNo 订单号
     * @params array $deliveredGoodsList 妥投订单商品列表 [['order_goods_id' => 1, 'sku_code' => 'code123']]
     *
     */
    public function deliveredHandle($orderNo, $deliveredGoodsList, $params)
    {
        //订单商品妥投后，触发安装服务邮件预约
        if (OrderGoodsInstallationServiceRepository::instance()->isInstallationServiceOrder($orderNo)) {
            //安装服务发送邮件
            $orderGoodsIds               = array_column($deliveredGoodsList, 'order_goods_id');
            $orderGoodsList              = OrderGoodsRepository::instance()->getListByOrderGoodsIds($orderNo, $orderGoodsIds, ['order_goods_id', 'qty', 'sku_id']);
            $skuIds                      = array_column($orderGoodsList, 'sku_id');
            $updateOrderReceivedAtResult = InstallationService::instance()->updateOrderReceivedAt($orderNo, $skuIds, time());
            g_log_info('pay_success_create_installation.log', '订单商品妥投触发安装服务邮件预约', ['orderNo' => $orderNo, 'updateOrderReceivedAtResult' => $updateOrderReceivedAtResult]);
        }
        // 妥投通知 售后
        AfterSaleApi::instance()->orderGoodsReceivedHandle($params);
        //上报妥投事件（神策不稳定，上报容易超时，将上报动作放在所有业务处理完成之后）
        SensorsDataService::instance()->pushDelivered(SensorsDataBaseEvent::EVENT_ORDER_GOODS_RECEIVED_DETAIL, $orderNo, array_column($deliveredGoodsList, 'order_goods_id'));
    }

    /**
     * 订单待发货变成备货中的时间校验
     *
     * @param $orderNo
     *
     * @return array
     */
    public function orderInStockTimeCheck($orderNo)
    {
        $order = OrderRepository::instance()->getOrderByOrderNo($orderNo, ['order_status']);
        if ($order['order_status'] == OrderRepository::STATUS_UNDELIVERY) {
            $msg = [
                "#### 订单超过1.5小时，仍处于待发货状态，没有变更成备货中",
                sprintf('订单编号：%s', $orderNo),
            ];
            EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'tax_ship_from_address');
        }
        return $this->returnSuccess([]);
    }

    /**
     * 统计某段支付时间内sku的销售数量
     *
     * @param array  $skuIds     销售skuID列表
     * @param int    $startPayAt 开始支付时间戳
     * @param int    $endPayAt   结束支付时间戳
     * @param string $site       站点
     *
     * @return array
     *  [
     *              {
     *    sku_id: 10111
     *    qty:10,
     * }
     * ]
     */
    public function sumSkuSaleCount($skuIds, $startPayAt, $endPayAt, $site)
    {
        return OrderRepository::instance(true)->sumSkuSaleCount($skuIds, $startPayAt, $endPayAt, $site);
    }


    public function orderUpdateDeclaration($order)
    {
        if (empty($order)) {
            return $this->returnError(40027101, '订单信息为空');
        }
        // 订单所有商品都有履约发货仓库地址
        // 订单支付金额大于0
        // 为销售出库单
        // 且申报开关已经开启
        // 同时是美国站
        if ($order['pay_amount'] > 0
            && $order['tax_rate_type'] != 0  //保证试算过
            && MyFunction::instance()->taxDeclarationTurnOn()
            && strtolower($order['site']) == 'us'
        ) {
            // 修改为待申报
            OrderRepository::instance()->update(
                [
                    'tax_declare_status' => EstimateTaxEnum::TAX_TO_BE_DECLARED,
                    'updated_at'         => time(),
                ],
                [
                    'order_no' => $order['order_no'],
                ]
            );

            // 调用申报的接口
            EstimateTaxService::instance()->commitTransaction(['order_no' => $order['order_no']]);
        }
    }

    /**
     * 更新订单的支付单号
     *
     * @param string $orderNo   订单号
     * @param string $paymentNo 支付单号
     *
     * @return array
     */
    public function updatePaymentNo($orderNo, $paymentNo)
    {
        $orderInfo = OrderRepository::instance()->getOrderByOrderNo($orderNo, 'order_status');
        if (empty($orderInfo)) {
            return $this->returnError(600002, 'Order not found');
        }
        if (OrderRepository::STATUS_UNPAID != $orderInfo['order_status']) { //只有在待支付才能修改
            return $this->returnError(700013, 'Error Order status');
        }
        $updateData['payment_no'] = $paymentNo;
        $updateData['updated_at'] = time();
        $res                      = OrderRepository::instance()->update($updateData, ['order_no' => $orderNo]);
        g_log_info('updatePaymentNo.log', '更新订单的支付单号结果', [$updateData, ['order_no' => $orderNo], $res]);
        if (false !== $res) {
            //写入订单日志
            OrderLogRepository::instance()->insertLogV2($orderNo, json_encode(['订单：' . $orderNo . '的支付单号更新为：' . $paymentNo], 320), -1, -1, ['name' => '更新订单的支付单号']);
            return $this->returnSuccess();
        }
        return $this->returnError(700104, 'Failed to update order data');
    }

    /**
     * 更新订单为待支付的状态
     *
     * @param string $orderNo 订单号
     * @param string $remark  备注
     * @param string $handler 操作人
     *
     * @return array
     */
    public function updateOrderPending($orderNo, $remark, $handler)
    {
        $orderInfo = OrderRepository::instance()->getOrderByOrderNo($orderNo, 'order_status');
        if (empty($orderInfo)) {
            return $this->returnError(600002, 'Order not found');
        }
        $res = OrderRepository::instance()->updateOrderStatus($orderNo, OrderRepository::STATUS_UNPAID);
        if (!empty($res)) {
            //写入订单日志
            OrderLogRepository::instance()->insertLogV2($orderNo, json_encode(['订单：' . $orderNo . '状态更新为：待付款', '原因：' . $remark], 320),
                $orderInfo['order_status'], OrderRepository::STATUS_UNPAID, ['name' => $handler]);
            return $this->returnSuccess();
        }
        return $this->returnError(700235, 'Failed to update order pending');
    }

    /**
     * 新增订单日志
     *
     * @param string $orderNo           订单号
     * @param string $remark            备注
     * @param int    $originOrderStatus 原订单状态
     * @param string $handler           操作人
     *
     * @return array
     */
    public function addOrderLog($orderNo, $remark, $originOrderStatus, $handler)
    {
        $orderInfo = OrderRepository::instance()->getOrderByOrderNo($orderNo, 'order_status');
        if (empty($orderInfo)) {
            return $this->returnError(600002, 'Order not found');
        }
        $oldStatus = $originOrderStatus;
        if (-1 == $originOrderStatus) {
            $oldStatus = $orderInfo['order_status'];
        }
        //写入订单日志
        OrderLogRepository::instance()->insertLogV2($orderNo, $remark, $oldStatus, $orderInfo['order_status'], ['name' => $handler]);
        return $this->returnSuccess();
    }

    /**
     * 更新订单为支付成功的状态
     *
     * @param string $orderNo 订单号
     * @param string $remark  备注
     * @param string $handler 操作人
     * @param int    $paidAt  支付时间
     *
     * @return array
     */
    public function updateOrderPaid($orderNo, $remark, $handler, $paidAt = 0)
    {
        $orderInfo = OrderRepository::instance()->getOrderByOrderNo($orderNo, 'order_status');
        if (empty($orderInfo)) {
            return $this->returnError(600002, 'Order not found');
        }
        $res = OrderRepository::instance()->updateOrderPaid($orderNo, $paidAt);
        if (!empty($res)) {
            //写入订单日志
            OrderLogRepository::instance()->insertLogV2($orderNo, json_encode(['订单：' . $orderNo . '状态更新为：已支付', '原因：' . $remark], 320),
                $orderInfo['order_status'], OrderRepository::STATUS_PAID_SUCCESS, ['name' => $handler]);
            return $this->returnSuccess();
        }
        return $this->returnError(700225, 'Failed to update order paid');
    }

    /**
     * 批量更新订单为支付成功的状态
     *
     * @param array  $orderNos 订单号列表
     * @param string $remark   备注
     * @param string $handler  操作人
     *
     * @return array
     */
    public function batchUpdateOrderPaid($orderNos, $remark, $handler)
    {
        $res = OrderRepository::instance()->updateOrderPaid($orderNos);
        if ($res > 0) {
            $orderList = $this->getOrderListByOrderNos($orderNos);
            //写入订单日志
            $logList = [];
            foreach ($orderList as $order) {
                $logList[] = [
                    'order_no'            => $order['order_no'],
                    'is_show_to_customer' => 1,
                    'code'                => 0,
                    'content'             => json_encode(['订单：' . $order['order_no'] . '状态更新为：已支付', '原因：' . $remark], 320),
                    'pre_status'          => $order['order_status'],
                    'current_status'      => OrderRepository::STATUS_PAID_SUCCESS,
                    'operator_type'       => 1,
                    'operator_id'         => 0,
                    'operator_name'       => $handler,
                    'created_at'          => time(),
                    'updated_at'          => time()
                ];
            }
            OrderLogRepository::instance()->insertAll($logList);
            return $this->returnSuccess();
        }
        return $this->returnError(700225, 'Failed to batch update order paid');
    }

    /**
     * 批量更新订单为已取消
     *
     * @param array  $orderNos 订单号列表
     * @param string $remark   备注
     * @param string $handler  操作人
     *
     * @return array
     */
    public function batchUpdateOrderCancel($orderNos, $remark, $handler)
    {
        $res = OrderRepository::instance()->updateOrderCancel($orderNos);
        if ($res > 0) {
            $orderList = $this->getOrderListByOrderNos($orderNos);
            //写入订单日志
            $logList = [];
            foreach ($orderList as $order) {
                $logList[] = [
                    'order_no'            => $order['order_no'],
                    'is_show_to_customer' => 1,
                    'code'                => 0,
                    'content'             => json_encode(['订单：' . $order['order_no'] . '状态更新为：已取消', '原因：' . $remark], 320),
                    'pre_status'          => $order['order_status'],
                    'current_status'      => OrderRepository::STATUS_CLOSED,
                    'operator_type'       => 1,
                    'operator_id'         => 0,
                    'operator_name'       => $handler,
                    'created_at'          => time(),
                    'updated_at'          => time()
                ];
            }
            OrderLogRepository::instance()->insertAll($logList);
            return $this->returnSuccess();
        }
        return $this->returnError(700225, 'Failed to batch update order paid');
    }

    /**
     * 批量更新订单为支付成功的时间
     *
     * @param array  $orderNos 订单号列表
     * @param string $remark   备注
     * @param string $handler  操作人
     * @param int    $paidAt   支付时间
     *
     * @return array
     */
    public function batchUpdateOrderPaidAt($orderNos, $paidAt, $remark, $handler)
    {
        $res = OrderRepository::instance()->updateOrderPaidAt($orderNos, $paidAt);
        if ($res > 0) {
            $orderList = OrderRepository::instance()->getOrderByOrderNos($orderNos, ['order_status', 'order_no']);
            //写入订单日志
            $logList = [];
            foreach ($orderList as $order) {
                $logList[] = [
                    'order_no'            => $order['order_no'],
                    'is_show_to_customer' => 1,
                    'code'                => 0,
                    'content'             => json_encode(['订单：' . $order['order_no'] . '支付成功时间更新为：' . date('Y-m-d H:i:s', $paidAt), '原因：' . $remark], 320),
                    'pre_status'          => $order['order_status'],
                    'current_status'      => $order['order_status'],
                    'operator_type'       => 1,
                    'operator_id'         => 0,
                    'operator_name'       => $handler,
                    'created_at'          => time(),
                    'updated_at'          => time()
                ];
            }
            OrderLogRepository::instance()->insertAll($logList);
            return $this->returnSuccess();
        }
        return $this->returnError(700225, 'Failed to batch update order paid');
    }

    /**
     * 设置邮件发送时间
     *
     * @param $orderNo
     * @param $time
     *
     * @return void
     */
    public function setColorSkuEmail($orderNo, $time = 0)
    {
        $orderGoodsList = OrderGoodsRepository::instance()->getListByOrderNo($orderNo);
        $isSend         = false;
        foreach ($orderGoodsList as $goods) {
            if ($goods['is_color_sku'] == 1) {
                $isSend = true;
            }
        }
        // 存在的话，就发送
        if ($isSend) {
            // 默认是15天之后发送
            // 为空需要写入需要发送邮件的单号与分数
            $time = empty($time) ? time() : $time;
            $time = $time + 3600 * 24 * 15;
            DeliveryRedis::instance()->setDeliveryListTrackingMockUpNumber($orderNo, $time);
        }
    }

    /**
     * 根据优惠券组ID获取订单所属门店
     *
     * @param int $couponGroupId 优惠券组ID
     *
     * @return array
     */
    public function getAffiliatedStoreByCouponGroupId($couponGroupId)
    {
        if (empty($couponGroupId)) {
            return [];
        }
        return OfflineStoreService::instance()->getOneByCouponGroupId($couponGroupId);
    }

    /**
     * 根据UtmContent获取订单所属门店
     *
     * @param string $name
     *
     * @return array
     */
    public function getAffiliatedStoreByUtmContent($name)
    {
        return OfflineStoreService::instance()->getOneByName($name);
    }

    /**
     * 根据UtmContent获取订单所属门店店员信息
     *
     * @param string $name
     *
     * @return array{
     *      storeName: string,          // 门店名称
     *      storeId: int,               // 门店ID
     *      staffUserId: int,           // 员工用户关系表ID
     *      staffAdminUserId: int       // 员工对应admin_user_id
     *  }
     */
    public function userStaffOrder($userId): array
    {
        return OfflineStoreService::instance()->userStaffOrder($userId);
    }

    /**
     * 根据UtmContent获取订单所属门店
     *
     * @param string $name
     *
     * @return array
     */
    public function getStoreStaffByUtmContent($code)
    {
        return OfflineStoreService::instance()->getOneByName($code);
    }

    /**
     * 订单异常检测（订单信息是否发生改变/重复订单）
     *
     * @doc https://yapi.popicorns.com/project/135/interface/api/40586
     *
     * @param array  $params  所有参数集合，主要为了兼容之前的重复订单校验
     * @param string $orderNo 订单号
     *
     * @return array
     */
    public function orderAnomalyDetect($params, $orderNo, $orderVersion)
    {
        // 订单地址禁售检测
        if ($this->orderAddressForbiddenCheck($orderNo)) {
            return $this->returnSuccess([
                'exception_code'  => 1,
                'require_refresh' => 1,
                'details'         => [
                    'title' => 'Error',
                    'desc'  => Yii::t('common/error', 70025),
                ],
            ], Yii::t('common/error', 70025));
        }

        //订单信息一致性检测（是否发生了改变）
        $consistencyCheckResult = $this->orderConsistencyCheck($orderNo, $orderVersion);
        if (1 != $consistencyCheckResult['code']) {
            //检测到异常，返回异常信息，这里使用returnSuccess，前端根据data里面的内容做判断
            return $this->returnSuccess($consistencyCheckResult['data'], $consistencyCheckResult['info']);
        }

        //检查增值服务是否有效
        $valueAddedCheckResult = OrderGoodsValueAddedServiceService::instance()->checkInvalidValueAddedService($params);
        if (1 == $valueAddedCheckResult['code'] && !empty($valueAddedCheckResult['data']['exception_code'])) {
            //检测到异常，返回异常信息，这里使用returnSuccess，前端根据data里面的内容做判断
            return $this->returnSuccess($valueAddedCheckResult['data'], $valueAddedCheckResult['info']);
        }
        // 增值服务异常信息
        if (1 != $valueAddedCheckResult['code']) {
            //返回异常信息
            return $this->returnError($valueAddedCheckResult['code'], $valueAddedCheckResult['info']);
        }

        //重复订单检测
        $orderRepeatResult = OrderRepeatService::instance()->checkRepeatOrder($params);
        if (1 == $orderRepeatResult['code'] && !empty($orderRepeatResult['data']['exception_code'])) {
            //检测到异常，返回异常信息，这里使用returnSuccess，前端根据data里面的内容做判断
            return $this->returnSuccess($orderRepeatResult['data'], $orderRepeatResult['info']);
        }
        // 重复订单异常信息
        if (1 != $orderRepeatResult['code']) {
            //返回异常信息
            return $this->returnError($orderRepeatResult['code'], $orderRepeatResult['info']);
        }

        return $this->returnSuccess();
    }

    /**
     * 订单地址禁售检测
     *
     * @param string $orderNo
     *
     * @return bool
     */
    public function orderAddressForbiddenCheck(string $orderNo): bool
    {
        $orderAddress = OrderAddressService::instance()->getOrderAddressByOrderNo($orderNo);
        if (!empty($orderAddress)) {
            return ShippingRuleService::instance()->getRule(
                $orderAddress['country_code'],
                $orderAddress['state_code'],
                $orderAddress['zip']
            )->isBanned();
        }

        return true;
    }

    /**
     * 订单数据一致性校验
     *
     * @return array
     */
    public function orderConsistencyCheck($orderNo, $orderVersion)
    {
        if (!\common\services\config\ConfigService::instance()->getEnableOrderConsistencyCheck()) {
            return $this->returnSuccess();
        }
        if (empty($orderNo) || empty($orderVersion)) {
            return $this->returnSuccess();
        }
        $orderInfo = OrderRepository::instance()->getOrderByOrderNo($orderNo, 'order_status,order_version');
        //只检测未支付的订单
        if (empty($orderInfo) || OrderRepository::STATUS_UNPAID != $orderInfo['order_status']) {
            return $this->returnSuccess();
        }
        $newestOrderVersion = $this->getOrderVersion($orderNo, $orderInfo['order_version']);
        if ($newestOrderVersion == $orderVersion) {
            return $this->returnSuccess();
        }
        //订单数据发生了改变，预警
        $this->orderConsistencyChangeNotify($orderNo, $orderVersion, $newestOrderVersion, $orderInfo['order_version']);
        return $this->returnError(47013859, Yii::t('common/tag', 'order_had_been_updated_desc'), [
            'exception_code'  => 1,
            'require_refresh' => 1,
            'details'         => [
                'title' => Yii::t('common/tag', 'order_had_been_updated_title'),
                'desc'  => Yii::t('common/tag', 'order_had_been_updated_desc'),
            ],
        ]);
    }

    /**
     * 获取订单版本号（用于前端提交支付时候，订单数据一致性的校验）
     *
     * @return string
     */
    public function getOrderVersion($orderNo, $orderVersion)
    {
        return md5(sprintf('order_%s_%s', $orderNo, $orderVersion));
    }

    /**
     * 订单数据不一致预警
     */
    private function orderConsistencyChangeNotify($orderNo, $orderVersion, $lastOrderVersion, $originVersion)
    {
        $msg = [
            '#### 提交订单信息检测发生变化',
            '> 订单号：' . $orderNo,
            '> 参数版本：' . $orderVersion,
            '> 系统版本：' . $lastOrderVersion,
            '> 系统原值：' . $originVersion,
        ];
        EnterpriseWechatMessage::instance()->wechatMsgSend($msg, 'trade_core');
    }

    /**
     * 是否为新用户，在某个时间内有下单并支付成功的为老用户，否则为新用户（暂用于google purchase事件上报）
     *
     * @param int $userId      用户ID
     * @param int $startPaidAt 开始支付成功时间
     * @param int $endPaidAt   结束支付成功时间
     *
     * @return int  true: 新用户 false: 老用户
     */
    public function isNewCustomer($userId, $startPaidAt, $endPaidAt)
    {
        $paidAt = OrderRepository::instance()->getLastOrderPaidAt($userId, $startPaidAt, $endPaidAt);
        return empty($paidAt);
    }

    /**
     * 获取订单信息
     *
     * @param string $orderNo 订单号
     *
     * @return array
     */
    public function getOrderInfoByOrderNo($orderNo)
    {
        return OrderRepository::instance()->getOrderByOrderNo($orderNo);
    }

    /**
     * 获取订单信息
     *
     * @param array $orderNos 订单号列表
     *
     * @return array
     */
    public function getOrderListByOrderNos($orderNos, $fields = [])
    {
        return OrderRepository::instance()->getOrderByOrderNos($orderNos, $fields);
    }

    /**
     * 获取订单详情地址
     *
     * @param string $orderNo 订单号
     * @param string $site    站点
     * @param int    $userId  用户ID
     * @param bool   $isGuest 是否为游客
     * @param string $eid
     *
     * @return string
     */
    public function getOrderDetailUrl($orderNo, $site, $userId, $isGuest, $eid = '')
    {
        $bmUrl      = rtrim(Email::instance()->getUrlLink($site), '/');
        $orderDetailUrl = $bmUrl . '/tracking-order/detail?order_no=' . $orderNo . '&pos=TrackOrderButton&eid=' . $eid . '&signature=' . JwtService::instance()->createSignature('allow_user_id', $userId, time());
        if (!$isGuest) {
            $orderDetailUrl = $bmUrl . '/usercenter/orderdetail?order_no=' . $orderNo;
        }
        return $orderDetailUrl;
    }

    /**
     * 判断是否开启营销优惠
     *
     * @param array $orderInfo 订单信息
     *
     * @return bool
     */
    public function isEnableSalesDiscount($orderInfo)
    {
        if (!empty($orderInfo) && OrderRepository::DISABLE_SALES_DISCOUNT == $orderInfo['enable_sales_discount']) {
            return false;
        }
        return true;
    }

    /**
     * 计算订单的总价值（商品总价+增值服务+保养服务）
     *
     * @param float $totalGoodsPrice          商品总价
     * @param float $valueAddServicePrice     增值服务总价
     * @param float $protectionPlanPrice      保养服务总价
     * @param float $shippingFee              运费
     * @param float $installationServicePrice 安装服务费
     *
     * @return float
     */
    public function calOrderValue($totalGoodsPrice, $valueAddServicePrice, $protectionPlanPrice, $shippingFee = 0, $installationServicePrice = 0)
    {
        $totalPrice = 0;
        if (!empty($totalGoodsPrice)) {
            $totalPrice = bcadd($totalPrice, $totalGoodsPrice, 2);
        }
        if (!empty($valueAddServicePrice)) {
            $totalPrice = bcadd($totalPrice, $valueAddServicePrice, 2);
        }
        if (!empty($protectionPlanPrice)) {
            $totalPrice = bcadd($totalPrice, $protectionPlanPrice, 2);
        }
        if (!empty($shippingFee)) {
            $totalPrice = bcadd($totalPrice, $shippingFee, 2);
        }
        if (!empty($installationServicePrice)) {
            $totalPrice = bcadd($totalPrice, $installationServicePrice, 2);
        }

        return (float)$totalPrice;
    }


    /**
     * 创建补发订单号
     *
     * @param string $originalNo 原始订单号，订单号规则：原始订单号-BF01
     *
     * @return string
     */
    public function createReissueOrderNo($originalNo)
    {
        $count = OrderRepository::instance()->getCountByRelatedOrderNo($originalNo, OrderRepository::ORDER_TYPE_REISSUE);
        $seqNo = str_pad((string)($count + 1), 2, '0', STR_PAD_LEFT);
        return $originalNo . '-BF' . $seqNo;
    }

    /**
     * 创建bmot参数，用于记录门店信息
     *
     * @param int    $storeId   门店ID
     * @param string $storeName 门店名称
     * @param string $staffName 店员名称
     * @param int    $staffId   店员Id （admin_user的主键id）
     *
     * @return string
     */
    public function encBmot($storeId, $storeName, $staffName, $staffId)
    {
        if (empty($storeId) || empty($storeName) || empty($staffName) || empty($staffId)) {
            return '';
        }
        $data   = [
            'store_id'   => $storeId,
            'store_name' => $storeName,
            'staff_name' => $staffName,
            'staff_id'   => $staffId,
        ];
        $encStr = EncryptService::instance()->aesEncrypt(json_encode($data, 320));
        //encode返回前端，避免浏览器解析特殊字符异常
        return urlencode($encStr);
    }

    /**
     * 解密bmot参数
     *
     * @param string $bmot
     *
     * @return array
     */
    public function decBmot($bmot)
    {
        try {
            $content  = EncryptService::instance()->aesDecrypt($bmot);
            $bmotInfo = @json_decode($content, true);
            if (empty($bmotInfo)) {
                return [];
            }
            return $bmotInfo;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 通过关联单号获取已支付未关闭的记录数
     *
     * @param string $relatedOrderNo 关联单号
     *
     * @return int
     */
    public function getPaidCountByRelatedOrderNo($relatedOrderNo)
    {
        return OrderRepository::instance(true)->getPaidCountByRelatedOrderNo($relatedOrderNo);
    }

    /**
     * 判断订单运费是否发送变动
     *
     * @param string $orderNo
     * @param string $orderCurrency
     * @param string $countryCode
     * @param string $stateCode
     * @param string $zip
     *
     * @return bool
     */
    public function isOrderShippingFeeChange(string $orderNo, string $orderCurrency, string $countryCode, string $stateCode, string $zip): bool
    {
        $orderGoods          = OrderGoodsRepository::instance()->getOrderGoodsByOrderNo($orderNo, 'delivery_channel,qty,shipping_fee');
        $shippingFeeResult   = ShippingRuleService::instance()->getRule($countryCode, $stateCode, $zip, $orderCurrency);
        $shippingFeeDataMap  = $shippingFeeResult->getShippingFeeRules(true) ?: [];
        $orderShippingFee    = 0;
        $newOrderShippingFee = 0;
        foreach ($orderGoods as $goods) {
            $shippingFeeMethodType = $goods['delivery_channel'];
            $orderShippingFee      = bcadd($orderShippingFee, $goods['shipping_fee'], 2);
            if (!empty($shippingFeeDataMap[$shippingFeeMethodType])) {
                $newOrderShippingFee = bcadd(
                    $newOrderShippingFee,
                    bcmul($shippingFeeDataMap[$shippingFeeMethodType]['amount'], $goods['qty'], 2),
                    2
                );
            }
        }

        return $orderShippingFee != $newOrderShippingFee;
    }

    /**
     * 判断订单运费、运费税是否发生变化，并发回新旧地址运费及运费税.
     *
     * @param array $orderInfo
     * @param array $params
     *
     * @return array
     * @throws \Exception
     */
    public function OrderShippingFeeAndTaxChange(array $orderInfo, array $params): array
    {
        $orderNo                      = $orderInfo['order_no'];
        $orderGoods                   = OrderGoodsRepository::instance()->getOrderGoodsByOrderNo($orderNo);
        $orderGoodsProtectionPlanData = OrderGoodsProtectionPlanRepository::instance()->getByOrderNo($orderNo);
        $shippingFeeResult            = ShippingRuleService::instance()->getRule($params['country_code'], $params['state_code'], $params['zip'], $orderInfo['currency']);
        $shippingFeeDataMap           = $shippingFeeResult->getShippingFeeRules(true) ?: [];
        $orderShippingFee             = $orderShippingTax = $newOrderShippingFee = $newOrderShippingTax = 0.00;
        foreach ($orderGoods as $key => $goods) {
            $shippingFeeMethodType = $goods['delivery_channel'];
            $originalShippingFee   = $goods['shipping_fee'];
            $orderShippingFee      = bcadd($orderShippingFee, $goods['shipping_fee'], 2);
            $orderShippingTax      = bcadd($orderShippingTax, $goods['shipping_fee_tax'], 2);
            if (!empty($shippingFeeDataMap[$shippingFeeMethodType])) {
                $newShippingFee                   = bcmul($shippingFeeDataMap[$shippingFeeMethodType]['amount'], $goods['qty'], 2);
                $newOrderShippingFee              = bcadd($newOrderShippingFee, $newShippingFee, 2);
                $orderGoods[$key]['shipping_fee'] = $newShippingFee;//重置为新的运费，供后面查找运费税
            } else {
                $orderGoods[$key]['shipping_fee'] = 0.00;//重置为新的运费，供后面查找运费税
            }
            //重置支付金额，商品支付金额-原来的运费+现在的运费=现在新地址商品的支付金额
            $orderGoods[$key]['pay_amount'] = bcadd(bcsub($orderGoods[$key]['pay_amount'], $originalShippingFee, 2), $orderGoods[$key]['shipping_fee'], 2);
        }
        //获取新地址信息
        $newAddressData = $this->getNewAddressData($orderInfo, $params);
        //计算税费
        $taxData      = EstimateTaxService::instance()->getEstimateTax(
            '',//不要传订单号，传了会覆盖实收税费，因为这时候订单已经支付了，这里只是要获得用户更改地时税费的展示
            $orderGoods,
            $orderGoodsProtectionPlanData,
            $orderInfo['site'],
            $orderInfo['currency'],
            $newAddressData,
            $orderInfo['order_sales_type']
        );
        $goodsTaxList = $taxData['goods_list'] ?? [];
        $goodsTaxList = array_column($goodsTaxList, null, 'sku_code');
        if (!empty($goodsTaxList)) {
            $newOrderShippingTax = array_sum(array_column($goodsTaxList, 'shipping_tax'));
        }
        //若未发生变化，则仅计算前后运费差和运费税差，是否需补/退差价
        return [
            'orderShippingFeeAndTaxChange' => bcadd($newOrderShippingFee, $newOrderShippingTax, 2) != bcadd($orderShippingFee, $orderShippingTax, 2),
            'orderShippingFee'             => $orderShippingFee,
            'newOrderShippingFee'          => $newOrderShippingFee,
            'orderShippingTax'             => $orderShippingTax,
            'newOrderShippingTax'          => $newOrderShippingTax,
        ];
    }

    /**
     * 获取新地址订单商品的运费.
     *
     * @param array $orderInfo
     * @param array $params
     *
     * @return array|\yii\db\ActiveRecord[]
     */
    public function newAddressOrderGoodsShippingFee(array $orderInfo, array $params)
    {
        $orderNo            = $orderInfo['order_no'];
        $orderGoods         = OrderGoodsRepository::instance()->getOrderGoodsByOrderNo($orderNo);
        $shippingFeeResult  = ShippingRuleService::instance()->getRule($params['country_code'], $params['state_code'], $params['zip'], $orderInfo['currency']);
        $shippingFeeDataMap = $shippingFeeResult->getShippingFeeRules(true) ?: [];
        foreach ($orderGoods as $key => $goods) {
            $shippingFeeMethodType = $goods['delivery_channel'];
            if (!empty($shippingFeeDataMap[$shippingFeeMethodType])) {
                $newShippingFee                   = bcmul($shippingFeeDataMap[$shippingFeeMethodType]['amount'], $goods['qty'], 2);
                $orderGoods[$key]['shipping_fee'] = $newShippingFee;//重置为新的运费，供后面查找运费税
            }
        }

        return array_column($orderGoods, null, 'sku_id');
    }

    /**
     * 修改地址时返回新地址信息.
     *
     * @param $orderInfo
     * @param $params
     *
     * @return array
     */
    public function getNewAddressData($orderInfo, $params)
    {
        //替换中文符号
        $params['street1'] = MyFunction::instance()->replaceChinese($params['street1'], true);
        $params['street2'] = MyFunction::instance()->replaceChinese($params['street2'] ?? '', true);
        $newAddressData    = [
            'user_id'             => $orderInfo['user_id'],
            'first_name'          => $params['first_name'],
            'last_name'           => $params['last_name'],
            'company'             => $params['company'],
            'email'               => $params['email'],
            'phone'               => $params['phone'],
            'fax'                 => $params['fax'] ?? '',
            'country'             => $params['country'],
            'country_code'        => $params['country_code'],
            'state'               => $params['state'],
            'state_code'          => $params['state_code'],
            'state_id'            => $params['state_id'] ?? 0,
            'city'                => $params['city'],
            'area'                => $params['area'] ?? '',
            'zip'                 => $params['zip'],
            'street1'             => $params['street1'],
            'street2'             => $params['street2'],
            'phone_code'          => $params['phone_code'] ?? '',
            'international_phone' => PhoneNumberService::instance()->format($params['phone'], $params['country_code']),
        ];

        return $newAddressData;
    }

    /**
     * 新地址的订单应付金额对于原本订单实付金额 是否发生改变
     *
     * @param $orderInfo
     * @param $params
     */
    public function newAddressOrderPayAmountChange($orderInfo, $params)
    {
        $newCountryCode               = $params['country_code'];
        $orderNo                      = $orderInfo['order_no'];
        $orderAmount                  = OrderAmountRepository::instance()->getByOrderNo($orderNo);
        $orderGoodsOriginalData       = OrderGoodsRepository::instance()->getByOrderNoWithoutSuit($orderNo);
        $orderGoodsProtectionPlanData = OrderGoodsProtectionPlanRepository::instance()->getByOrderNo($orderNo);
        $orderGoodsSkuIds             = array_column($orderGoodsOriginalData, 'sku_id');
        $orderGoodsCouponUserIds      = array_column($orderGoodsOriginalData, 'coupon_user_id');
        $orderGoodsOriginalData       = array_column($orderGoodsOriginalData, null, 'sku_id');
        $orderGoodsSuitOriginalData   = OrderGoodsSuitRepository::instance()->getListByOrderNo($orderNo);
        $orderGoodsSuitSkuIds         = array_column($orderGoodsSuitOriginalData, 'sku_id');
        $orderGoodsSuitCouponUserIds  = array_column($orderGoodsSuitOriginalData, 'coupon_user_id');
        $orderGoodsSuitOriginalData   = array_column($orderGoodsSuitOriginalData, null, 'sku_id');
        $skuList                      = array_merge($orderGoodsSkuIds, $orderGoodsSuitSkuIds);
        $couponUserIds                = array_merge($orderGoodsCouponUserIds, $orderGoodsSuitCouponUserIds);
        $usedCouponUserId             = 0;
        foreach ($couponUserIds as $couponUserId) {
            //一个订单只能用一个优惠券
            if ($couponUserId > 0) {
                $usedCouponUserId = $couponUserId;
                break;
            }
        }

        //查找新国家地址商品价格
        $newGoodsList                = ProductService::instance()->getSkuInfosBySkuIdsIncludeSuitWithoutEta(
            $skuList,
            $orderInfo['site'],
            $orderInfo['currency'],
            $orderInfo['language'],
            ['format_activity' => false, 'stock_real_time_status' => 1],
            $newCountryCode
        );
        $originalCouponDiscountTotal = $orderAmount['coupon_discount'];//原地址商品优惠总价格
        $originalGoodsPriceTotal     = $orderAmount['goods_price'];//原地址商品总价格
        $newCouponDiscountTotal      = 0.00;//新地址商品优惠总价格
        $newGoodsPriceTotal          = 0.00;//新地址商品总价格

        //组装商品数据用来计算商品优惠券优惠金额
        $goodsList = [];
        foreach ($newGoodsList as $key => &$goods) {
            $orderGoods  = isset($orderGoodsOriginalData[$goods['sku_id']]) ? $orderGoodsOriginalData[$goods['sku_id']] : $orderGoodsSuitOriginalData[$goods['sku_id']];
            $goodsList[] = [
                'sku_id'                 => $goods['sku_id'],
                'price'                  => $goods['price'],
                'qty'                    => $orderGoods['qty'],
                'spu_brand_id'           => $goods['spu_brand_id'],
                'spu_category_id_first'  => $goods['spu_category_id_first'],
                'spu_category_id_second' => $goods['spu_category_id_second'],
                'spu_category_id_third'  => $goods['spu_category_id_third'],
                'spu_category_id_fourth' => $goods['spu_category_id_fourth'] ?? 0,
                'activity_id'            => $orderGoods['activity_id'] ?? 0,
                'activity_status'        => $goods['activity_status'] ?? 0,
                'activity_type'          => $orderGoods['activity_type'] ?? 0,
                'selection'              => $goods['selection'] ?? [],
                // 新人专享商品价格
                'exclusive_price'        => $orderGoods['activity_price'] ?? 0,
                'goods_price'            => $goods['price'] ?? 0,
                'is_first_order_only'    => !empty($goods['is_first_order_only']) ? $goods['is_first_order_only'] : 0,
                'cost_price'             => $goods['cost_price'] ?? 0,
            ];
            //累加新的商品金额
            $newGoodsPriceTotal        = bcadd($newGoodsPriceTotal, bcmul($goods['price'], $orderGoods['qty'], 2), 2);
            $newGoodsList[$key]['qty'] = $orderGoods['qty'] ?? 0;
            if (!empty($goods['is_suit']) && $goods['is_suit'] == 1) {
                foreach ($goods['suit_sku_list'] as $k => $suitSku) {
                    $goods['suit_sku_list'][$k]['qty'] = $suitSku['suit_included_qty'] * ($orderGoods['qty'] ?? 0);
                }
            }
            //计算出套装优惠
            if (!empty($goods['suit_sku_list'])) {
                $goods['suit_sku_list'] = SuitDiscountNode::instance()->formatGoodsList($goods['suit_sku_list'], $goods['price'], $goods['qty']);
            }
        }

        //使用旧的优惠券，看是否能分摊
        if ($usedCouponUserId) {
            $couponFilterParam          = new CouponFilterParam();
            $couponFilterParam->storeId = $orderInfo['affiliated_store_id'];
            $result                     = CouponUseService::instance()->calculationOrderUseCouponAmount($usedCouponUserId, $orderInfo['user_id'], $goodsList, $orderInfo['site'], $orderInfo['order_no'], $orderInfo['currency'], $couponFilterParam);
            if (1 == $result['code'] && !empty($result['data'])) {
                $skuCouponDiscountList  = $result['data']['sku_coupon_discount_list'] ?? [];
                $newCouponDiscountTotal = array_sum(array_column($skuCouponDiscountList, 'coupon_discount_amount'));
                foreach ($newGoodsList as &$goodsInfo) {
                    $goodsInfo['coupon_discount_amount'] = $skuCouponDiscountList[$goodsInfo['sku_id']]['coupon_discount_amount'] ?? 0;
                    //套装处理
                    if (!empty($goodsInfo['is_suit']) && $goodsInfo['is_suit'] == 1) {
                        $splitBase = [];
                        foreach ($goodsInfo['suit_sku_list'] as $key => $suitSku) {
                            $splitBase[$suitSku['sku_id']] = bcmul($suitSku['price'], $suitSku['suit_included_qty'], 2);
                        }

                        $suitAmountSplitList = MyFunction::instance()->amountSplit($goodsInfo['coupon_discount_amount'], $splitBase);
                        foreach ($goodsInfo['suit_sku_list'] as &$suitSku) {
                            $suitSku['coupon_discount_amount'] = $suitAmountSplitList[$suitSku['sku_id']] ?? 0.00;
                        }
                        unset($suitSku);
                    }
                }
            }
        }

        //新地址商品金额减去新地址优惠券优惠金额 是否等于 原地址商品金额减去原地址优惠券优惠金额
        $newGoodsPriceDiscountCoupon      = bcsub($newGoodsPriceTotal, $newCouponDiscountTotal, 2);
        $originalGoodsPriceDiscountCoupon = bcsub($originalGoodsPriceTotal, $originalCouponDiscountTotal, 2);

        $orderGoodsList      = $this->newAddressOrderGoodsShippingFee($orderInfo, $params);//获得了新地订单商品的运费
        $newAddressData      = $this->getNewAddressData($orderInfo, $params);
        $newOrderShippingFee = array_sum(array_column($orderGoodsList, 'shipping_fee'));
        $context             = new PlaceOrderContext();
        $context->goodsList  = $newGoodsList;
        $context->currency   = $orderInfo['currency'];
        $context->orderNo    = $orderInfo['order_no'];
        //组装新的orderGoods
        //处理套装数据，单品数据独立开。再单独处理两个数据
        $todoGoodsList = $suitGoodsList = [];
        foreach ($context->goodsList as $goodItem) {
            if (!empty($goodItem['suit_sku_list'])) {
                $suitGoodsList[] = $goodItem;
                $todoGoodsList   = array_merge($todoGoodsList, $goodItem['suit_sku_list']);
            } else {
                $todoGoodsList[] = $goodItem;
            }
        }

        //判断没有套装，按照原有的格式化商品，如果有按照新的逻辑走。
        $doneGoodsList = [];
        if (empty($suitGoodsList)) {
            foreach ($todoGoodsList as $goods) {
                $doneGoodsList[] = DataFormatNode::instance()->singleGoodsDataFormat($goods, $context);
            }
        } else {
            //$todoGoodsList合并相同skuid 到 $dongingGoodsList；
            $dongingGoodsList = DataFormatNode::instance()->mergeSameSkuHandle($todoGoodsList);

            //$dongingGoodsList格式化到$doneGoodsList。 里有格式化增值服务的逻辑
            foreach ($dongingGoodsList as $goods) {
                $doneGoodsList[] = DataFormatNode::instance()->singleGoodsDataFormat($goods, $context, (bool)$goods['is_suit']);
            }
        }
        $doneGoodsList = array_column($doneGoodsList, null, 'sku_id');

        foreach ($orderGoodsList as &$orderGoodsItem) {
            //组装商品各种价格信息，然后调用税费接口等到相关税费
            $todoGoods                           = $doneGoodsList[$orderGoodsItem['sku_id']] ?? [];
            $orderGoodsItem['goods_price']       = $todoGoods['goods_price'] ?? 0.00;
            $orderGoodsItem['total_goods_price'] = $todoGoods['total_goods_price'] ?? 0.00;
            $orderGoodsItem['coupon_discount']   = $todoGoods['coupon_discount_amount'] ?? 0;//用新的订单商品优惠券优惠金额
            $orderGoodsItem['suit_discount']     = $todoGoods['suit_discount'] ?? 0;//用新的订单商品套装优惠金额
            $totalGoodsDiscount                  = OrderExtendService::instance()->getTotalDiscount($orderGoodsItem);//订单商品所有的优惠
            //这里调用税费的接口传的pay_amount是商品金额减去各种优惠的金额 再加上 各种服务费的金额 最终得出的这个金额才来算税费的(因为getEstimateTax里面会减去各种服务费)
            $afterDiscountAmount = bcsub($orderGoodsItem['total_goods_price'], $totalGoodsDiscount, 2);

            $goodsPayAmount               = bcadd($afterDiscountAmount, $orderGoodsItem['value_added_service_amount'] ?? 0, 2);
            $goodsPayAmount               = bcadd($goodsPayAmount, $orderGoodsItem['protection_plan_amount'] ?? 0, 2);
            $goodsPayAmount               = bcadd($goodsPayAmount, $orderGoodsItem['shipping_fee'] ?? 0, 2);
            $goodsPayAmount               = bcadd($goodsPayAmount, $orderGoodsItem['installation_amount'] ?? 0, 2);
            $orderGoodsItem['rate_fee']   = 0.00;//税费重置为0
            $orderGoodsItem['pay_amount'] = $goodsPayAmount;
        }

        //计算税费
        $taxData          = EstimateTaxService::instance()->getEstimateTax(
            '',//不要传订单号，传了会覆盖实收税费，因为这时候订单已经支付了，这里只是要获得用户更改地时税费的展示
            $orderGoodsList,
            $orderGoodsProtectionPlanData,
            $orderInfo['site'],
            $orderInfo['currency'],
            $newAddressData,
            $orderInfo['order_sales_type']
        );
        $goodsTaxList     = $taxData['goods_list'] ?? [];
        $newOrderGoodsTax = $newOrderServiceTax = $newOrderExtendTax = $newOrderShippingTax = $newOrderGoodsRate = 0.00;
        if (!empty($goodsTaxList)) {
            $newOrderGoodsTax    = array_sum(array_column($goodsTaxList, 'goods_tax'));
            $newOrderServiceTax  = array_sum(array_column($goodsTaxList, 'service_tax'));
            $newOrderExtendTax   = array_sum(array_column($goodsTaxList, 'extend_tax'));
            $newOrderShippingTax = array_sum(array_column($goodsTaxList, 'shipping_tax'));
            $newOrderGoodsRate   = bcadd(bcadd(bcadd($newOrderGoodsTax, $newOrderServiceTax, 2), $newOrderExtendTax, 2), $newOrderShippingTax, 2);
        }

        $suitDiscount                   = array_sum(array_column($orderGoodsList, 'suit_discount'));
        $orderAmountInfo['goods_price'] = array_sum(array_column($orderGoodsList, 'total_goods_price'));;
        $orderAmountInfo['suit_discount']   = $suitDiscount;
        $orderAmountInfo['coupon_discount'] = $newCouponDiscountTotal;
        $orderAmountInfo['rate_fee']        = $newOrderGoodsRate;
        $orderAmountInfo['shipping_fee']    = $newOrderShippingFee;
        $newOrderPayAmount                  = OrderExtendService::instance()->getPayDiscount($orderAmountInfo);
        $originalOrderPayAmount             = $orderInfo['pay_amount'];

        return [
            'newAddressOrderPayAmountChange' => $newOrderPayAmount != $originalOrderPayAmount,
            'originalOrderPayAmount'         => $originalOrderPayAmount,
            'newOrderPayAmount'              => $newOrderPayAmount,
            'newOrderShippingFee'            => $newOrderShippingFee,
            'newOrderShippingTax'            => $newOrderShippingTax,
            'newOrderGoodsRate'              => $newOrderGoodsRate,
            'newOrderGoodsTax'               => $newOrderGoodsTax,
            'newCouponDiscountTotal'         => $newCouponDiscountTotal,
            'newGoodsPriceTotal'             => $newGoodsPriceTotal,
        ];
    }

    /**
     * 获取订单商品信息
     *
     * @param array $orderNos 订单号列表
     *
     * @return array
     */
    public function getOrderGoodsListByOrderNos($orderNos, $fields = [])
    {
        return OrderGoodsRepository::instance()->getOrderGoodsByOrderNos($orderNos, $fields);
    }

    /**
     * 发货处理（每5分钟执行，执行发货后的一些处理业务逻辑，可扩展）(脚本专用)
     *
     * @param int   $beforeMin   几分钟前，正常要和脚本执行频率要保持一致
     * @param array $deliveryIds 发货记录id列表
     */
    public function deliveryHandle($beforeMin, $deliveryIds = [])
    {
        $startAt = time() - $beforeMin * 60;
        $where   = [];
        if (!empty($deliveryIds)) {
            $where[] = ['in', 'delivery_id', $deliveryIds];
        } else {
            $where[] = ['>=', 'created_at', $startAt];
        }
        $where[]      = ['=', 'del_flag', 0];
        $deliveryList = OrderDeliveryRepository::instance(true)->getDeliveryByWhere($where);
        if (empty($deliveryList)) {
            return $this->returnSuccess([], '没有需要处理的发货单');
        }
        $orderNos       = array_column($deliveryList, 'order_no');
        $orderGoodsIds  = array_column($deliveryList, 'order_goods_id');
        $orderGoodsList = OrderGoodsRepository::instance(true)->getListByOrderGoodsIds(null, $orderGoodsIds, ['order_goods_id', 'sku_code']);
        $orderGoodsList = array_column($orderGoodsList, null, 'order_goods_id');
        //获取支付单列表
        $paymentList = PaymentRepository::instance(true)->getListByOrdertNos($orderNos, ['payment_channel_company', 'business_no']);
        $paymentList = array_column($paymentList, null, 'business_no');
        $handleList  = [];
        foreach ($deliveryList as $delivery) {
            $payment = $paymentList[$delivery['order_no']] ?? [];
            if (empty($payment)) {
                continue;
            }
            //暂时只处理pingpong支付的发货
            if (PaymentRepository::PAY_BY_PINGPONG != $payment['payment_channel_company']) {
                continue;
            }
            $orderGoods = $orderGoodsList[$delivery['order_goods_id']] ?? [];
            if (empty($orderGoods)) {
                continue;
            }
            //注意，这里是二维数组
            $dispatchList = [
                [
                    'tracking_number'  => $delivery['tracking_number'],
                    'tracking_company' => $delivery['tracking_company'],
                    'sku_code'         => $orderGoods['sku_code'],
                    'qty'              => (int)$delivery['this_time_qty'], //当次发货数量
                ],
            ];
            PayInternal::instance()->dispatchNotify($delivery['order_no'], $dispatchList);
            $handleList[] = [
                'order_no'      => $delivery['order_no'],
                'dispatch_list' => $dispatchList,
            ];
            sleep(1);
        }
        return $this->returnSuccess($handleList, '执行完成');
    }

    /**
     * 获取门店订单号
     * 一个店员可以在多家门店工作，有可能其中一个店铺做店长，一个店铺里面做店员
     * 情况1: 总部人员
     * 情况2:一个/多个店铺ID + 都是店员
     * 情况3:多个店铺Id + 一个是店员/一个是店长
     * 情况4:多个店铺ID + 都是店长
     * 情况5:指定店铺和店员查询
     *
     * @param int   $type 1：情况1、2:情况2、3:情况3、4:情况4、5:情况5
     * @param array $item 对应类型的值
     *                    {store_staff_id: 1, manager_store_ids: [],staff_store_ids:[]}
     * @param int   $storeId
     * @param int   $storeStaffId
     *
     * @return array
     */
    public function getStoreOrderNoList(int $type, array $item = [], int $storeId = 0, int $storeStaffId = 0): array
    {
        $data = OrderRepository::instance(true)->getStoreOrderNoList($type, $item, $storeId, $storeStaffId);
        if (!empty($data)) {
            return array_column($data, 'order_no');
        }

        return [];
    }
}
