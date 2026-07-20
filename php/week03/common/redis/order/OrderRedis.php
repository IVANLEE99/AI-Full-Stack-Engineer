<?php

namespace common\redis\order;

class OrderRedis extends \common\BaseRedis
{
    /**
     * @inheritDoc
     */
    public function getConnection()
    {
        return \Yii::$app->redisBmMaster;
    }

    /**
     * 设置锁定订单状态（10分钟）
     */
    public function setOrderLocked($orderNo, $expire = 600)
    {
        $key = sprintf('bm:order:orderLocked:%s', $orderNo);
        return $this->getConnection()->setex($key, $expire, 1);
    }

    /**
     * 获取锁定订单状态
     */
    public function getOrderLocked($orderNo)
    {
        $key = sprintf('bm:order:orderLocked:%s', $orderNo);
        return $this->getConnection()->get($key);
    }

    /**
     * 轮训订单支付状态开始时间的key
     *
     * @param string $orderNo 订单号
     * @param int    $userId  用户ID
     *
     * @return string
     */
    private function getLoopOrderPayStatusKey($orderNo, $userId)
    {
        return sprintf(self::$key['order']['loop_pay_status'], $orderNo, $userId);
    }

    /**
     * 获取轮训订单支付状态开始时间
     *
     * @param string $orderNo 订单号
     * @param int    $userId  用户ID
     *
     * @return int
     */
    public function getLoopOrderPayStatusStartTime($orderNo, $userId)
    {
        return (int)$this->getConnection()->get($this->getLoopOrderPayStatusKey($orderNo, $userId));
    }

    /**
     * 设置轮训订单支付状态开始时间
     *
     * @param string $orderNo 订单号
     * @param int    $userId  用户ID
     *
     * @return int
     */
    public function setLoopOrderPayStatusStartTime($orderNo, $userId, $loopStartTime)
    {
        return $this->getConnection()->setex($this->getLoopOrderPayStatusKey($orderNo, $userId), 86400, $loopStartTime);
    }

    /**
     * 删除轮训订单支付状态开始时间
     *
     * @param string $orderNo 订单号
     * @param int    $userId  用户ID
     *
     * @return int
     */
    public function delLoopOrderPayStatusStartTime($orderNo, $userId)
    {
        return $this->getConnection()->del($this->getLoopOrderPayStatusKey($orderNo, $userId));
    }

    /**
     * 设置次日达待审核提醒状态（默认过期时间：5d）
     */
    public function setNextDayDeliveryPreviewNotice($orderNo, $expire = 86400 * 5)
    {
        $key = sprintf('bm:order:nextDayDeliveryPreviewNotice:%s', $orderNo);
        return $this->getConnection()->setex($key, $expire, 1);
    }

    /**
     * 获取次日达待审核提醒状态
     */
    public function getNextDayDeliveryPreviewNotice($orderNo)
    {
        $key = sprintf('bm:order:nextDayDeliveryPreviewNotice:%s', $orderNo);
        return $this->getConnection()->get($key);
    }

    /**
     * 设置次日达超时未推送oms提醒状态（默认过期时间：5d）
     */
    public function setNextDayDeliveryNotPushOmsNotice($orderNo, $expire = 86400 * 5)
    {
        $key = sprintf('bm:order:nextDayDeliveryNotPushOmsNotice:%s', $orderNo);
        return $this->getConnection()->setex($key, $expire, 1);
    }

    /**
     * 获取次日达超时未推送oms提醒状态
     */
    public function getNextDayDeliveryNotPushOmsNotice($orderNo)
    {
        $key = sprintf('bm:order:nextDayDeliveryNotPushOmsNotice:%s', $orderNo);
        return $this->getConnection()->get($key);
    }
}
