<?php

/**
 * 退货退款的所有相关操作
 */

namespace common\services\processingType\concrete;

use common\enums\AfterSaleProcessTypeEnum;
use common\enums\AfterSaleStatusEnum;
use common\services\AfterSaleGoodsService;
use common\services\AfterSaleLogService;
use common\services\AfterSaleService;
use common\services\AuditService;

class ReturnGoodsRefund extends \common\services\processingType\ProcessingType
{

    //处理方案的枚举值
    public $processingTypeValue = AfterSaleProcessTypeEnum::RETURN_GOODS_REFUND;
    //处理方案的名称
    public $processingTypeName = '退货退款';

    /**
     * 售后申请
     */
    public function apply()
    {
        return $this->returnError(404, 'no action');
    }

    /**
     * 暂存数据
     */
    public function temporaryData()
    {
        return $this->returnError(404, 'no action');
    }

    /**
     * 提交审核
     */
    public function submitConfirm()
    {
        return $this->returnError(404, 'no action');
    }

    /**
     * 取消售后
     */
    public function cancel()
    {
        return $this->returnError(404, 'no action');
    }

    /**
     * 支付成功通知
     */
    public function paidNotify()
    {
        return $this->returnError(404, 'no action');
    }

    /**
     * 退款成功通知
     */
    public function refundNotify()
    {
        return $this->returnError(404, 'no action');

    }

    /**
     * 售后审核通过
     *
     * @return array
     */
    public function auditPass()
    {
        return $this->returnError(404, 'no action');
    }

    /**
     * 售后审核拒绝
     *
     * @return array
     */
    public function auditReject()
    {
        return $this->returnError(404, 'no action');
    }

    public function toOmsOrderNotify()
    {
        return $this->returnError(404, 'no action');
    }

    /**
     * 安装服务单取消结果通知
     */
    public function installationServiceCancelNotify()
    {
        return AfterSaleService::instance()->installationServiceCancelNotify($this->params['after_sale_data'], $this->params['success'], $this->params['failed_reason'], $this->params['operator'] ?? []);
    }
}
