<?php

namespace common\enums;

use yii2mod\enum\helpers\BaseEnum;

class AfterSaleStatusEnum extends BaseEnum
{
    /**
     * 售后状态：
     * 40-待分配 41-待处理
     * 7-待确认售后内容（原协商中）
     * 0-退款待审核 1-退款审核通过(去掉退货退款-待用户发货)
     * 30-大额退款待审核  31-退款中 32-退款失败 34-退款成功  35-待客服介入
     * 1-退货退款_待用户发货(原1-退货退款-待用户发货) 2-待仓库收货  3-仓库确认售后
     * 8-换货_待用户录入 9-换货_待客服审核换货 10-补差待支付（原待支付） 11-换货_待用户确认 12-售后完成(废弃，同6) 18-换货已确认 19-自主换货待确认
     * 50-取消拦截中 55-供应链同步中（取消履约&取消拦截操作中）
     * 70-售后已确认 75-供应修改中 73-供应已修改
     * 4-已取消-终结 6-已完结-终结
     * 后续添加状态的原则：0-10基础状态 10-20换货状态 20-30退货退款状态 30-40退款状态 50-60第三方同步状态 70-80修改订单信息状态
     */
    /**
     * 简易状态扭转流程
     * 用户申请指定状态 ---> 40 ---分配处理人---> 41 ---暂存数据---> 7
     */
    // 待分配
    const WAIT_FOR_DISTRIBUTION = 40;
    // 待处理
    const PENDING = 41;
    //7-待确认售后内容（原协商中）
    const CONSULT = 7;
    //0-待审核
    const TO_AUDIT = 0;
    //21-审核通过
    const AUDIT_PASS = 26;
    //24-审核拒绝
    const AUDIT_REJECT = 24;
    //大额退款待审核
    const HIGH_VALUE_TO_AUDIT = 30;
    //31-退款中
    const REFUNDING = 31;
    //32-退款失败
    const REFUND_FAILD = 32;
    //33-退款成功
    const STATUS_REFUND = 33;
    //34-退款成功
    const WAIT_FOR_REFUND = 34;
    //35-待客服介入
    const CUSTOMER_SERVICE_REVIEW = 35;
    //1-退货退款_待用户发货(原1-退货退款-待用户发货) 填写物流信息
    const WAITING_FOR_DELIVERY_OF_GOODS = 1;
    //2-待仓库收货
    const WAITING_FOR_WAREHOUSE_RECEIVE_GOODS = 2;
    //3-仓库确认售后
    const WAREHOUSE_STAFF_COMFIRMED_RETURN_GOODS = 3;
    //8-换货_待用户录入
    const EXCHANGE_WAITING_FOR_USER_INPUT = 8;
    //9-换货_待客服审核换货
    const EXCHANGE_TO_AUDIT = 9;
    //10-补差待支付（原待支付)
    const WAITING_FOR_THE_PAYMENT = 10;
    //16 支付成功
    const THE_PAYMENT_PAID = 16;
    //11-换货_待用户确认
    const EXCHANGE_WAITING_FOR_USER_CONFIRM = 11;
    //换货已确认
    const EXCHANGE_CONFIRMED = 18;
    //自主换货待确认
    const SELF_EXCHANGE_CONFIRMED = 19;
    //取消拦截中
    const CANCEL_INTERCEPT = 50;
    //同步oms中
    const SYNC_OMS_ING = 55;
    //修改信息-修改内容已确认
    const CONTENT_CONFIRMED = 70;
    //修改信息-oms修改中
    const CONTENT_OMS_EDITING = 75;
    // oms已修改完成
    const CONTENT_OMS_EDITED = 73;
    //已取消-完结
    const CANCEL = 4;
    //已完成-完结
    const DONE = 6;

    //补发订单信息新增
    //待生成补发订单
    const WAIT_FOR_REISSUE = 91;

    //保养服务信息新增
    //已创建保养订单
    const PROTECTION_ORDER_CREATED = 76;
    // 取消保养订单/合同中
    const PROTECTION_ORDER_CANCELING = 72;
    /**
     * 退件审核状态
     */
    const REFUND_RETURN_REVIEW = 22;
    // 待退款审核
    const PENDING_REFUND_REVIEW = 25;
    // 退款拒绝，待修改
    const REFUND_REFUSED_PENDING_MODIFICATION = 28;

    // todo 安装服务售后状态
    // 服务下单中
    const INSTALLATION_ORDER_CREATING = 77;
    const INSTALLATION_ORDER_CREATED  = 78;
    // 待确认取消
    const SERVICE_CANCELING = 79;
    const SERVICE_CANCELED  = 80;
    const INSTALLATION_ORDER_CANCEL_FAIL = 81;


    public static $list = [
        self::WAIT_FOR_DISTRIBUTION                  => '待分配处理人',
        self::PENDING                                => '待处理',
        self::CONSULT                                => '等待确认售后内容',
        self::TO_AUDIT                               => '待审核',
        self::AUDIT_PASS                             => '审核通过',
        self::AUDIT_REJECT                           => '审核拒绝',
        self::HIGH_VALUE_TO_AUDIT                    => '大额退款待审核',
        self::REFUNDING                              => '退款中',
        self::REFUND_FAILD                           => '退款失败',
        self::STATUS_REFUND                          => '退款成功',
        self::CUSTOMER_SERVICE_REVIEW                => '等待客服介入',
        self::REFUND_RETURN_REVIEW                   => '待退件审核',
        self::PENDING_REFUND_REVIEW                  => '待退款审核',
        self::REFUND_REFUSED_PENDING_MODIFICATION    => '退款拒绝，待修改',
        self::WAITING_FOR_DELIVERY_OF_GOODS          => '等待填写退货物流信息',
        self::WAITING_FOR_WAREHOUSE_RECEIVE_GOODS    => '物流填写完毕，等待仓库收货',
        self::WAREHOUSE_STAFF_COMFIRMED_RETURN_GOODS => '仓库人员已确认收货',
        self::EXCHANGE_WAITING_FOR_USER_INPUT        => '待用户录入换货商品',
        self::EXCHANGE_TO_AUDIT                      => '待客服审核换货',
        self::WAITING_FOR_THE_PAYMENT                => '待支付',
        self::THE_PAYMENT_PAID                       => '支付成功',
        self::EXCHANGE_WAITING_FOR_USER_CONFIRM      => '待用户确认换货商品',
        self::EXCHANGE_CONFIRMED                     => '换货信息已确认',
        self::SELF_EXCHANGE_CONFIRMED                => '自主换货待确认',
        self::CANCEL_INTERCEPT                       => '取消拦截中',
        self::SYNC_OMS_ING                           => '同步OMS中',
        self::CONTENT_CONFIRMED                      => '内容已确认',
        self::CONTENT_OMS_EDITING                    => '内容同步OMS中',
        self::CONTENT_OMS_EDITED                     => 'OMS已修改',
        self::CANCEL                                 => '已取消',
        self::DONE                                   => '已完成',
        self::WAIT_FOR_REISSUE                       => '待生成补发订单',
        self::PROTECTION_ORDER_CREATED               => '已创建保养订单',
        self::PROTECTION_ORDER_CANCELING             => '取消保养订单/合同中',
        self::WAIT_FOR_REFUND                        => '待退款',
        self::INSTALLATION_ORDER_CREATING            => '服务下单中',
        self::INSTALLATION_ORDER_CREATED             => '安装服务下单完成',
        self::SERVICE_CANCELING           => '待确认取消',
        self::SERVICE_CANCELED            => '服务已取消',
        self::INSTALLATION_ORDER_CANCEL_FAIL         => '取消安装服务单失败',
    ];

    public static $enList = [
        self::WAIT_FOR_DISTRIBUTION                  => 'After-sales Info Confirming',
        self::PENDING                                => 'After-sales Info Confirming',
        self::CONSULT                                => 'After-sales Info Confirming',
        self::TO_AUDIT                               => 'Refund Applied',
        self::HIGH_VALUE_TO_AUDIT                    => 'Refund Applied',
        self::REFUNDING                              => 'In Refunding',
        self::REFUND_FAILD                           => 'Refunding',
        self::STATUS_REFUND                          => 'Refunded Successfully',
        self::CUSTOMER_SERVICE_REVIEW                => 'Awaiting customer service to review',
        self::REFUND_RETURN_REVIEW                   => 'Pending return review',
        self::PENDING_REFUND_REVIEW                  => 'Pending refund review',
        self::REFUND_REFUSED_PENDING_MODIFICATION    => 'Refund refused, pending modification',
        self::WAITING_FOR_DELIVERY_OF_GOODS          => 'Return Item',
        self::WAITING_FOR_WAREHOUSE_RECEIVE_GOODS    => 'Awaiting received',
        self::WAREHOUSE_STAFF_COMFIRMED_RETURN_GOODS => 'Waiting for entry',
        self::EXCHANGE_WAITING_FOR_USER_INPUT        => 'Waiting for entry',
        self::EXCHANGE_TO_AUDIT                      => 'Confirm Exchanged',
        self::WAITING_FOR_THE_PAYMENT                => 'Awaiting payment',
        self::THE_PAYMENT_PAID                       => 'Payment successful',
        self::EXCHANGE_WAITING_FOR_USER_CONFIRM      => 'Confirm exchange',
        self::EXCHANGE_CONFIRMED                     => 'After-sales Info Confirming',
        self::SELF_EXCHANGE_CONFIRMED                => 'After-sales Info Confirming',
        self::CANCEL_INTERCEPT                       => 'After-sales Info Confirming',
        self::SYNC_OMS_ING                           => 'After-sales Info Confirming',
        self::CONTENT_CONFIRMED                      => 'After-sales Info Confirming',
        self::CONTENT_OMS_EDITING                    => 'After-sales Info Confirming',
        self::CANCEL                                 => 'Canceled',
        self::DONE                                   => 'Completed',
        self::WAIT_FOR_REISSUE                       => 'Wait for Reissue',
        self::PROTECTION_ORDER_CREATED               => 'Protection Order Created',
        self::PROTECTION_ORDER_CANCELING             => 'Protection Order Cancellation',
        self::WAIT_FOR_REFUND                        => 'Waiting for Refund',
        self::INSTALLATION_ORDER_CREATING            => 'Service order in progress',
        self::SERVICE_CANCELING           => 'To be confirmed for cancellation',
        self::SERVICE_CANCELED            => 'Service cancelled',
        self::INSTALLATION_ORDER_CANCEL_FAIL         => 'Failed to cancel installation',
    ];

    /**
     * 所有售后类型对应的售后状态
     *
     * @return array
     */
    public static function getAllTypeStatusMapping()
    {
        return [
            AfterSaleTypeEnum::UNCONFIRMED              => array_keys(self::$list),
            //全部上线后，迭代删除其选项
            AfterSaleTypeEnum::UNDELIVERED_REFUND       => [
                self::WAIT_FOR_DISTRIBUTION,
                self::PENDING,
                self::CONSULT,
                self::TO_AUDIT,
                self::HIGH_VALUE_TO_AUDIT,
                self::REFUNDING,
                self::REFUND_FAILD,
                self::CUSTOMER_SERVICE_REVIEW,
                self::CANCEL_INTERCEPT,
                self::SYNC_OMS_ING,
                self::CANCEL,
                self::DONE,
            ],
            AfterSaleTypeEnum::ONLY_REFUND              => [
                self::WAIT_FOR_DISTRIBUTION,
                self::PENDING,
                self::CONSULT,
                self::TO_AUDIT,
                self::HIGH_VALUE_TO_AUDIT,
                self::REFUNDING,
                self::REFUND_FAILD,
                self::CUSTOMER_SERVICE_REVIEW,
                self::CANCEL_INTERCEPT,
                self::SYNC_OMS_ING,
                self::CANCEL,
                self::DONE,
            ],
            AfterSaleTypeEnum::RETURN_GOODS_REFUND      => [
                self::WAIT_FOR_DISTRIBUTION,
                self::PENDING,
                self::CONSULT,
                self::REFUND_RETURN_REVIEW,
                self::PENDING_REFUND_REVIEW,
                self::REFUND_REFUSED_PENDING_MODIFICATION,
                self::WAITING_FOR_DELIVERY_OF_GOODS,
                self::WAITING_FOR_WAREHOUSE_RECEIVE_GOODS,
                self::WAREHOUSE_STAFF_COMFIRMED_RETURN_GOODS,
                self::CUSTOMER_SERVICE_REVIEW,
                self::TO_AUDIT,
                self::HIGH_VALUE_TO_AUDIT,
                self::REFUNDING,
                self::REFUND_FAILD,
                self::CUSTOMER_SERVICE_REVIEW,
                self::CANCEL,
                self::DONE,
            ],
            AfterSaleTypeEnum::EXCHANGE_GOODS           => [
                self::WAIT_FOR_DISTRIBUTION,
                self::PENDING,
                self::CONSULT,
                self::EXCHANGE_WAITING_FOR_USER_INPUT,
                self::EXCHANGE_TO_AUDIT,
                self::EXCHANGE_WAITING_FOR_USER_CONFIRM,
                self::EXCHANGE_CONFIRMED,
                self::WAITING_FOR_THE_PAYMENT,
                self::CANCEL_INTERCEPT,
                self::SYNC_OMS_ING,
                self::CANCEL,
                self::DONE,
            ],
            AfterSaleTypeEnum::MODIFY_ORDER_INFORMATION => [
                self::WAIT_FOR_DISTRIBUTION,
                self::PENDING,
                self::CONSULT,
                self::CONTENT_CONFIRMED,
                self::WAITING_FOR_THE_PAYMENT,
                self::CONTENT_OMS_EDITING,
                self::CANCEL_INTERCEPT,
                self::SYNC_OMS_ING,
                self::CANCEL,
                self::DONE,
            ]
        ];
    }

    /**
     * 获取指定售后类型的状态列表
     *
     * @param $type
     *
     * @return array|string[]
     */
    public static function getTypeStatusMappingByType($type)
    {
        $nameMapping = self::$list;
        $allList     = self::getAllTypeStatusMapping();
        $list        = $allList[$type] ?? [];
        if (empty($list)) {
            return $nameMapping;
        }
        $returnData = [];
        foreach ($list as $v) {
            $returnData[$v] = $nameMapping[$v] ?? 'null';
        }
        return $returnData;
    }
}
