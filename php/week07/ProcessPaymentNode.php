<?php


namespace common\services\pay\nodes\pay;

use common\api\OrderInternal;
use common\factory\payment\PaymentFactory;
use common\services\pay\contexts\pay\ProcessPaymentContext;

class ProcessPaymentNode extends \common\BaseNode
{

    /**
     * 触发支付
     *
     * @param ProcessPaymentContext $context
     */
    public function invokeNode($context)
    {
        if (empty($context->paymentData)) {
            throw new \Exception('Payment not found', 720006);
        }
        $transactionId = $context->paymentData['out_id'] ?? '';
        //处理支付
        $orderResult = OrderInternal::instance()->getOrder($context->paymentData['business_no']);
        if (1 != $orderResult['code']) {
            throw new \Exception('Order not found', 46681145);
        }
        $shippingAddress = !empty($orderResult['data']['order_address']) ? $orderResult['data']['order_address'] : ($orderResult['data']['pickup_address'] ?? []);
        //触发终端支付
        $result          = PaymentFactory::instance($context->paymentCompany, $context->paymentData['site'], [], $context->paymentSubject)
            ->startReaderPayment($context->paymentData['payment_id'], $context->paymentNo, $context->paymentData['currency'], $context->paymentData['pay_amount'],
                $context->paymentMethod, $context->terminalId, $shippingAddress, $transactionId, $context->paymentData['business_no']);
        if (1 != $result['code']) {
            throw new \Exception($result['info'], $result['code']);
        }
    }

    /**
     * @inheritDoc
     */
    public function rollbackNode($context)
    {
    }
}
