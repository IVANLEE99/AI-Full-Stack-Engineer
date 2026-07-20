<?php
/**
 *
 * @author  白杨
 * @Date    2021/1/16 上午9:33
 * @package common\repositorys\order
 */

namespace common\repositorys\order;


use App\Utils\BaseFunction;
use common\models\order\Order;
use common\models\order\OrderAddress;
use common\models\order\OrderAmount;
use common\models\order\OrderGoods;
use common\models\pay\Payment;
use common\redis\order\StockRedis;
use common\redis\user\UserRedis;
use common\repositorys\faq\FaqUserQuestionRepository;
use common\repositorys\pay\PaymentRepository;
use common\services\pay\PaymentService;
use yii\db\ActiveRecord;

class OrderRepository extends \common\BaseRepository
{
    //待付款
    const STATUS_UNPAID = '0';
    //支付后审核
    const STATUS_PAID_AUDIT = '1';
    //待发货
    const STATUS_UNDELIVERY = '2';
    //配送中（该状态已废弃，拆分成8，9）
    const STATUS_DISPATCHING = '3';
    //已完成
    const STATUS_COMPLETED = '6';
    //已关闭
    const STATUS_CLOSED = '4';
    //已支付【在线付款完成/线下付款审核通过】
    const STATUS_PAID_SUCCESS = '7';
    //部分发货
    const STATUS_DELIVERY_SECTION = '8';
    //全部发货
    const STATUS_DELIVERY_ALL = '9';
    //备货中
    const STATUS_IN_STOCK = '10';
    //支付处理中
    const STATUS_PAYMENT_PROCESSING = 11;

    //使用时，使用Yii::t('tag','Pending') 翻译
    const  STATUS_MAPPING = [
        self::STATUS_UNPAID             => 'Pending',
        self::STATUS_PAID_AUDIT         => 'Payment Review',
        self::STATUS_UNDELIVERY         => 'To be shipped',
        self::STATUS_DISPATCHING        => 'On delivery',
        self::STATUS_COMPLETED          => 'Order completed',
        self::STATUS_CLOSED             => 'Canceled',
        self::STATUS_PAID_SUCCESS       => 'Paid Success',
        self::STATUS_DELIVERY_SECTION   => 'Partially Shipped',
        self::STATUS_DELIVERY_ALL       => 'Shipped',
        self::STATUS_IN_STOCK           => 'Shipment Transfer',
        self::STATUS_PAYMENT_PROCESSING => 'Payment Processing',
    ];

    const ORDER_SUMMITTED          = 'order_summitted';
    const ORDER_PAYMENT_SUCCESSFUL = 'payment_successful';
    const ORDER_INSPECTION_PASSED  = 'inspection_passed';
    const ORDER_SHIPPING           = 'shipping';
    const ORDER_COMPLETED          = 'completed';
    const ORDER_CANCELED           = 'canceled';
    const ORDER_SHIPMENT_TRANSFER  = 'shipment_transfer';
    const ORDER_PROCESSING         = 'order_processing';

    //订单状态短映射关系
    public static $orderStatusMapping = [
        self::STATUS_UNPAID             => self::ORDER_SUMMITTED,
        self::STATUS_PAID_AUDIT         => self::ORDER_PAYMENT_SUCCESSFUL,
        self::STATUS_UNDELIVERY         => self::ORDER_INSPECTION_PASSED,
        self::STATUS_DISPATCHING        => self::ORDER_SHIPPING,
        self::STATUS_CLOSED             => self::ORDER_CANCELED,
        self::STATUS_COMPLETED          => self::ORDER_COMPLETED,
        self::STATUS_PAID_SUCCESS       => self::ORDER_PAYMENT_SUCCESSFUL,
        self::STATUS_DELIVERY_SECTION   => self::ORDER_SHIPPING,
        self::STATUS_DELIVERY_ALL       => self::ORDER_SHIPPING,
        self::STATUS_IN_STOCK           => self::ORDER_SHIPMENT_TRANSFER,
        self::STATUS_PAYMENT_PROCESSING => self::ORDER_PROCESSING,
    ];

    /**
     * 获取订单状态mapping，翻译后的，注意要在order-api使用。
     *
     * @author 白杨
     * @Date   2021/4/11 下午3:15
     */
    public function getStatusMpping($laguage = '')
    {
        $statusList = self::STATUS_MAPPING;
        foreach ($statusList as &$v) {
            $v = \Yii::t('tag', $v, [], $laguage);
        }
        unset($v);
        return $statusList;
    }

    //订单类型-普通订单
    const ORDER_TYPE_ORDINARY = 1;
    //订单类型-代客下单
    const ORDER_TYPE_VALET = 2;
    //订单类型-补发订单
    const ORDER_TYPE_REISSUE = 3;
    //换货订单
    const ORDER_TYPE_EXCHANGE = 4;

    const ORDER_TYPE_MAPPING = [
        self::ORDER_TYPE_ORDINARY => '普通订单',
        self::ORDER_TYPE_VALET    => '代客下单',
        self::ORDER_TYPE_REISSUE  => '补发订单',
        self::ORDER_TYPE_EXCHANGE => '换货订单',
    ];


    const ORDER_REFUNDED_MAPPING = [
        0 => '未退款',
        1 => '部分退款',
        2 => '全部退款'
    ];


    //支付状态-未支付
    const IS_PAID_UN_PAY = 0;
    //支付状态-已支付
    const IS_PAID_OVER = 1;
    //游客
    const IS_GUEST = 1;
    //注册用户
    const IS_REGISTER = 0;

    //关单类型-超时未支付，关闭订单
    const CLOSED_TYPE_UNPAID_EXPIRE = 1;
    //关单类型-用户取消
    const CLOSED_TYPE_USER_CANCEL = 2;
    //关单类型-售后退款
    const CLOSED_TYPE_AFTERSALE_REFUND = 3;
    //关单类型-erp无法履约
    const CLOSED_TYPE_ERP_UNABLE = 4;
    //关单类型-客服管理员关闭未支付订单
    const CLOSED_TYPE_ADMIN_CANCEL = 5;
    //关单类型-支付单冲销
    const CLOSED_TYPE_PAYMENT_VOID = 6;
    //关单类型-用户退款
    const CLOSED_TYPE_USER_REFUND = 7;
    //关单类型-支付单charge back 关闭订单
    const CLOSED_TYPE_CHARGE_BACK = 8;
    //关单类型-测试单关闭
    const CLOSED_TYPE_TEST = 9;
    //关单类型-换货关单
    const CLOSED_TYPE_EXCHANG = 10;
    //关单类型-退款关联关闭订单
    const CLOSED_TYPE_REFUND = 11;
    //关单类型-支付单关闭关联关闭订单
    const CLOSED_TYPE_BUSINESS_CLOSED = 12;
    //关单类型-废弃
    const CLOSED_TYPE_ABANDONED       = 13;
    const CLOSED_TYPE_EXCHANGE_CANCEL = 14;

    const CLOSED_TYPE_MAPPING = [
        0  => '未关闭',
        1  => '超时未支付，关闭订单',
        2  => '用户取消',
        3  => '售后退款',
        4  => 'erp无法履约',
        5  => '客服管理员关闭未支付订单',
        6  => '支付单冲销',
        7  => '用户退款',
        8  => 'charge back 关闭订单',
        9  => '测试单，关闭',
        10 => '换货成功，关闭订单',
        11 => '退款成功，关闭关联订单',
        12 => '关闭支付，关联关闭订单',
        13 => '废弃',
        14 => '换货取消，关闭订单',
    ];

    //延期转发货状态时间
    const DELAY_HANDLE_UPDATE_SHIP_TIME = 3600;

    //是否支持售后，0：否，1：是
    const SUPPORT_AFTER_SALE     = 1;
    const NOT_SUPPORT_AFTER_SALE = 0;

    //订单销售类型，1：TOC-线上订单（原普通订单-toc），2：TOB订单（原普通订单-tob），3：清销订单，4：TOC-线下订单（原门店订单）
    const NORMAL_ORDER_TOC = 1;
    const NORMAL_ORDER_TOB = 2;
    const CLEAR_OFF_ORDER  = 3;
    const STORE_ORDER      = 4;

    //是否可用营销优惠，0：不可用，1：可用
    const ENABLE_SALES_DISCOUNT  = 1;
    const DISABLE_SALES_DISCOUNT = 0;

    const ORDER_SALES_TYPE = [
        self::NORMAL_ORDER_TOC => 'TOC-线上订单',
        self::NORMAL_ORDER_TOB => 'TOB订单',
        self::CLEAR_OFF_ORDER  => '清销订单',
        self::STORE_ORDER      => 'TOC-线下订单',
    ];

    //销售出库单
    const SALES_DELIVERY_ORDER = 1;
    //非销售出库单
    const NOT_SALES_DELIVERY_ORDER = 0;


    const TAX_ONLINE        = 1;
    const TAX_OFFLINE       = 2;
    const TAX_OFFLINE_STATE = 3;

    const TAX_MAPPING = [
        self::TAX_ONLINE        => "在线API税率",
        self::TAX_OFFLINE       => "离线邮编税率",
        self::TAX_OFFLINE_STATE => "离线州税率",
    ];

    const  ENABLE_STORE_OFFERS  = 1; //使用门店优惠
    const  DISABLE_STORE_OFFERS = 2;  //不使用门店优惠

    /**
     * 数据连接
     *
     * @return \yii\db\Connection
     * @author 白杨
     * @Date   2021/2/22 下午8:33
     */
    public function getConnection()
    {
        return Order::getDb();
    }

    /**
     * 获取订单状态的各种语言的描述
     *
     * @param        $status
     * @param string $languageCode
     *
     * @return string
     * @author 白杨
     * @Date   2021/3/5 下午5:08
     */
    public function getOrderStatusStr($status, $languageCode = '')
    {
        if (empty($languageCode)) {
            $languageCode = \Yii::$app->language;
        }
        $languageCode = BaseFunction::instance()->getFormatLanguage($languageCode);

        $str = self::STATUS_MAPPING[$status] ?? '';
        if (empty($str)) {
            return '';
        }
        $orderStatusStr = \Yii::t('tag', $str, [], $languageCode ?: 'enus');
        return $orderStatusStr ?: '';
    }


    /**
     * 保存数据到mysql数据库
     *
     * @param $data
     *
     * @return string
     * @throws \yii\db\Exception
     * @author 白杨
     * @Date   2021/1/16 上午10:20
     */
    public function insert($data)
    {
        $this->getConnection()->createCommand()->insert(Order::tableName(), $data)->execute();
        return $this->getConnection()->getLastInsertID();
    }

    /**
     * 更新字段  BaiYang 2019-06-25 15:07
     *
     * @param $updateData
     * @param $where
     *
     * @return int
     */
    public function update($updateData, $where)
    {
        return Order::updateAll($updateData, $where);
    }

    /**
     * 更新订单的金额信息
     *
     * @param string $orderNo 订单号
     *
     * @return bool
     */
    public function updateAmount($orderNo, $payAmount = 0, $usdPayAmount = 0, $cnyPayAmount = 0)
    {
        $updateData = [
            'pay_amount'     => $payAmount,
            'usd_pay_amount' => $usdPayAmount,
            'cny_pay_amount' => $cnyPayAmount,
            'updated_at'     => time(),
        ];
        return $this->update($updateData, ['order_no' => $orderNo]);
    }

    /**
     * 更新订单的状态
     *
     * @param string $orderNo 订单号
     *
     * @return bool
     */
    public function updateOrderStatus($orderNo, $orderStatus)
    {
        $updateData = [
            'order_status' => $orderStatus,
            'updated_at'   => time(),
        ];
        if (self::STATUS_UNPAID == $orderStatus) {
            $updateData['is_paid'] = 0;
            $updateData['paid_at'] = 0;
        }
        return $this->update($updateData, ['order_no' => $orderNo]);
    }

    /**
     * 分页查询订单列表数据
     *
     * @param array $where
     *                      [
     *                      ["=","phone","13360502500"],
     *                      ["in","user_id",[1,2,3]],
     *                      [">=","created_at","2021-01-01 00:00:00"],
     *                      ['between', 'id', 1, 10]，
     *                      ['like', 'name', 'tester'],
     *                      ]
     * @param int $page
     * @param int $pageSize
     * @param array $orderBy
     *
     * @param array $fields
     *
     * @return array|\yii\db\ActiveRecord[]
     * @author 白杨
     * @Date   2021/1/23 下午5:38
     */
    public function getPageList($where = [], $page = 1, $pageSize = 10, $orderBy = [], $fields = [])
    {
        $order = Order::find();

        if (!empty($where)) {
            foreach ($where as $v) {
                $order->andWhere($v);
            }
        }

        if (!empty($fields)) {
            $order->select($fields);
        }

        if (!empty($orderBy)) {
            $order->orderBy($orderBy);
        } else {//默认order_id倒序
            $order->orderBy(['order_id' => SORT_DESC]);
        }

        return $order->offset(($page - 1) * $pageSize)->limit($pageSize)->asArray()->all($this->getDb());
    }


    /**
     * 获取满足条件的订单条数
     *
     * @param array $where
     *                      [
     *                      ["=","phone","13360502500"],
     *                      ["in","user_id",[1,2,3]],
     *                      [">=","created_at","2021-01-01 00:00:00"],
     *                      ['between', 'id', 1, 10]，
     *                      ['like', 'name', 'tester'],
     *                      ]
     *
     * @return int|string
     */
    public function getCount($where = [])
    {
        $order = Order::find();

        if (!empty($where)) {
            foreach ($where as $v) {
                $order->andWhere($v);
            }
        }

        return $order->count('*', $this->getDb());
    }

    /**
     * @param string $orderNo
     *
     * @return array|Order|null
     */
    public function getOrderObjByNo($orderNo)
    {
        return Order::find()->andWhere(['order_no' => $orderNo])->one();
    }

    public function getOrderByOrderNo($orderNo, $field = '*')
    {
        if (empty($orderNo)) {
            return [];
        }
        return Order::find()->select($field)->where(['order_no' => $orderNo])->asArray()->one($this->getDb());
    }

    /**
     * 根据订单号查询订单列表
     */
    public function getOrderByOrderNos($orderNo, $field = [])
    {
        return Order::find()->select($field)->where(['order_no' => $orderNo, 'del_flag' => 0])->asArray()->all($this->getDb());
    }

    public function getOrderByPaymentNo($paymentNo, $field = '*')
    {
        return Order::find()->select($field)->where(['payment_no' => $paymentNo])->asArray()->one();
    }

    public function getOrderByOrderNoAndUserId($orderNo, $userId = 0)
    {
        $where = ['order_no' => $orderNo];
        if (!empty($userId)) {
            $where['user_id'] = $userId;
        }
        return Order::find()->where($where)->asArray()->one();
    }

    public function getOrderByOrderNoAndUserIdDetail($orderNo, $userId = 0)
    {
        $where            = ['order_no' => $orderNo];
        $where['user_id'] = $userId;
        return Order::find()->where($where)->asArray()->one();
    }

    public function getOrderArrayByOrderNos($orderNos, $field = '*')
    {
        return Order::find()->select($field)->where(['in', 'order_no', $orderNos])->asArray()->all($this->getDb());
    }

    /**
     * @param $orderId
     *
     * @return array|Order|null
     */
    public function getOrderObjById($orderId)
    {
        return Order::find()->where(['order_id' => $orderId])->one();
    }

    /**
     * @param $userId
     *
     * @return array|Order|null
     */
    public function getOrderObjByUserId($userId)
    {
        return Order::find()->where(['user_id' => $userId])->one();
    }

    /**
     * @param        $userId
     * @param string $site
     *
     * @return array|\yii\db\ActiveRecord[]
     */
    public function getOrderByUserId($userId, $site = '')
    {
        $order = Order::find();
        if (!empty($site)) {
            $order->andWhere(['=', 'site', $site]);
        }
        $order->andWhere(['=', 'user_id', $userId]);
        return $order->asArray()->all();
    }

    /**
     * 从缓存里获取用户订单数，不管是否已支付。
     *
     * @param $userId
     *
     * @return bool|int|mixed|string|null
     */
    public function getCountFromCache($userId)
    {
        $orderCount = UserRedis::instance()->getUserOrderCount($userId);
        if (!empty($orderCount)) {
            return $orderCount;
        }
        $orderCount = Order::find()->andWhere(['=', 'user_id', $userId])->andWhere(['=', 'del_flag', 0])->count('*', $this->getDb());
        if ($orderCount > 0) {
            UserRedis::instance()->setUserOrderCount($userId, $orderCount);
        }
        return $orderCount;
    }

    /**
     * 支付待审核
     *
     * @param $orderNo
     * @param $auditReason
     *
     * @return int
     * @author 白杨
     * @Date   2021/2/3 下午2:30
     */
    public function updateOrderPaidAudit($orderNo, $auditReason)
    {
        $order = $this->getOrderByOrderNo($orderNo);
        if (empty($order)) {
            return false;
        }
        if (self::STATUS_UNPAID != $order['order_status']) {
            return false;
        }

        $language = $order['language'] ?? '';

        $update = [
            'order_status' => self::STATUS_PAID_AUDIT,
            'audit_reason' => $auditReason,
            'updated_at'   => time()
        ];
        $where  = [
            'order_no' => $orderNo
        ];

        return $this->update($update, $where);
    }


    /**
     * 订单状态分组
     *
     * @param int $userId
     * @param int $site
     * @param array $statusList
     *
     * @return mixed
     */
    public function getOrderStatusGroupCountByUserId($userId, $site = '', $statusList = [])
    {
        return Order::find()
            ->andWhere(['=', 'user_id', $userId])
            ->andWhere(['=', 'site', $site])
            ->andWhere(['in', 'order_status', $statusList])
            ->groupBy(['order_status'])
            ->select('count(order_status) as order_count,order_status')
            ->asArray()
            ->all();
    }

    /**
     * 用户订单分组
     *
     * @param array $userIds
     *
     * @return mixed
     */
    public function getOrderGroupCountByUserIds($userIds)
    {
        return Order::find()
            ->andWhere(['in', 'user_id', $userIds])
            ->groupBy(['user_id'])
            ->select('user_id,count(1) as order_count')
            ->asArray()
            ->all();
    }

    /**
     * @param int $userId
     *
     * @return int|string
     */
    public function getReturnOrderCountByUserId($userId)
    {
        return Order::find()
            ->andWhere(['=', 'user_id', $userId])
            ->andWhere(['in', 'is_refunded', [1, 2]])
            ->count();
    }

    /**
     * 根据用户id获取已支付的订单
     *
     * @param $userId
     *
     * @return array
     */
    public function getEffPaidOrderByUserId($userId)
    {
        return Order::find()
            ->andWhere(['=', 'user_id', $userId])
            ->andWhere(['=', 'is_paid', self::IS_PAID_OVER])
            ->limit(1)
            ->asArray()
            ->one();
    }

    /**
     * 统计订单优惠券使用情况
     *
     * @param $startTimestamp
     * @param $endTimestamp
     *
     * @return array|\yii\db\ActiveRecord[]
     * @author 白杨
     * @Date   2021/5/26 下午2:40
     */
    public function getCouponUseStatisticsData($startTimestamp, $endTimestamp, $isGuest = 0)
    {
        $list = Order::find()
            ->alias('a')
            ->join('left join', 'coupon_user b', 'a.coupon_user_id = b.id')
            ->join('left join', 'order_amount c', 'a.order_no = c.order_no')
            ->select(['coupon_code' => 'b.coupon_code', 'coupon_id' => 'b.coupon_id', 'coupon_name' => 'b.coupon_name', 'coupon_alias' => 'b.coupon_alias', 'site_code' => 'b.site_code', 'count' => 'count(1)', 'usd_pay_amount' => 'sum(a.usd_pay_amount)', 'usd_coupon_discount' => 'sum(c.usd_coupon_discount)'])
            ->andWhere(['between', 'a.paid_at', $startTimestamp, $endTimestamp])
            ->andWhere(['=', 'a.is_paid', self::IS_PAID_OVER])
            ->andWhere(['!=', 'a.coupon_user_id', '0'])
            ->andWhere(['=', 'a.is_guest', $isGuest])
            ->groupBy('b.coupon_code')
            ->asArray()
            ->all($this->getDb());
        return array_column($list, null, 'coupon_code');
    }

    /**
     * 根据参数获取不同维度的订单统计数据
     *
     * @param $where
     * @param $fieldData
     * @param $groupBy
     *
     * @return array|\yii\db\ActiveRecord[]
     * @author 德昌
     */
    public function getOrderStatisticsData($where, $fieldData, $groupBy)
    {
        $order = Order::find();
        foreach ($where as $v) {
            $order->andWhere($v);
        }
        return $order->select($fieldData)
            ->groupBy($groupBy)
            ->asArray()
            ->all($this->getDb());
    }

    /**
     * 单独统计优惠券订单的成本
     *
     * @param $where
     * @param $fieldData
     * @param $groupBy
     *
     * @return array|\yii\db\ActiveRecord[]
     * @author 德昌
     */
    public function getOrderStatisticsCouponData($startTimestamp, $endTimestamp, $groupBy)
    {
        return Order::find()
            ->alias('a')
            ->join('left join', 'order_amount c', 'a.order_no = c.order_no')
            ->select(['sum(usd_coupon_discount) as coupon_amount', 'site'])
            ->andWhere(['between', 'a.paid_at', $startTimestamp, $endTimestamp])
            ->andWhere(['=', 'a.is_paid', self::IS_PAID_OVER])
            ->groupBy($groupBy)
            ->asArray()
            ->all($this->getDb());
    }

    /**
     * 获取支付单已更新，但是订单号未更新的订单号列表
     *
     * @param $startTime
     *
     * @return array|\yii\db\ActiveRecord[]
     */
    public function getPayOrderlist($startTime)
    {
        return Order::find()
            ->alias('a')
            ->join('left join', 'payment c', 'a.order_no = c.business_no')
            ->select(['a.order_no', 'c.payment_no', 'c.pay_amount', 'c.currency', 'c.payment_method', 'c.business_type', 'c.business_no', 'c.pay_at', 'c.inner_notify_url', 'c.status'])
            ->andWhere(['<=', 'a.created_at', $startTime])
            ->andWhere(['=', 'c.status', 1])
            ->andWhere(['=', 'a.is_paid', 0])
            ->asArray()
            ->all();
    }

    /**
     * 根据支付单号，获取订单号
     *
     * @param $pamentNo
     *
     * @return false|int|string|null
     * @author 白杨
     * @Date   28/6/21 下午2:30
     */
    public function getOrderNoByPaymentNo($pamentNo)
    {
        $orderNo = Order::find()->select('order_no')->andWhere(['=', 'payment_no', $pamentNo])->scalar();
        return $orderNo ?: '';
    }

    /**
     * 获取paypal快捷支付下单总数，下单总金额
     *
     * @param int $startTime 订单创建开始时间
     * @param int $endTime 订单创建结束时间
     * @param string $groupBy 排序
     * @param string $indexBy 重新索引字段
     *
     * @return array|\yii\db\ActiveRecord[]
     */
    public function getPaypalQuickOrderData($startTime, $endTime, $groupBy = null, $indexBy = null)
    {
        return Order::find()
            ->alias('o')
            ->leftJoin('payment p', 'p.business_no=o.order_no')
            ->select(['sum(p.usd_pay_amount) as paypal_quick_order_amount', 'count(o.order_id) as paypal_quick_order_sum', 'o.site'])
            ->andWhere(['>=', 'o.created_at', $startTime])
            ->andWhere(['<', 'o.created_at', $endTime])
            ->andWhere(['=', 'o.del_flag', 0])
            ->andWhere(['=', 'p.pay_type', PaymentService::PAYPAL_QUICK_PAY])
            ->groupBy($groupBy)
            ->indexBy($indexBy)
            ->asArray()
            ->all($this->getDb());
    }

    /**
     * 获取支付立减下单总数，下单总金额
     *
     * @param int $startTime 订单创建开始时间
     * @param int $endTime 订单创建结束时间
     * @param string $groupBy 排序
     * @param string $indexBy 重新索引字段
     *
     * @return array|\yii\db\ActiveRecord[]
     */
    public function getPaymentDiscountOrderData($startTime, $endTime, $groupBy = null, $indexBy = null)
    {
        return OrderAmount::find()
            ->select(['sum(p.usd_pay_amount) as payment_discount_order_amount', 'count(o.order_id) as payment_discount_order_sum', 'o.site'])
            ->alias('oa')
            ->leftJoin('`order` o', 'o.order_no=oa.order_no')
            ->leftJoin('payment p', 'p.business_no=oa.order_no')
            ->andWhere(['>=', 'o.created_at', $startTime])
            ->andWhere(['<', 'o.created_at', $endTime])
            ->andWhere(['>', 'oa.payment_discount', 0])
            ->andWhere(['=', 'o.del_flag', 0])
            ->groupBy($groupBy)
            ->indexBy($indexBy)
            ->asArray()
            ->all($this->getDb());
    }

    /**
     * 根据站点、支付方式、下单日期分组获取下单总数
     *
     * @param int $startTime 订单创建开始时间
     * @param int $endTime 订单创建结束时间
     * @param string $groupBy 排序
     *
     * @return array|\yii\db\ActiveRecord[]
     */
    public function countOrderData($startTime, $endTime, $groupBy = 'payment_method,site,day')
    {
        return Order::find()
            ->alias('o')
            ->leftJoin('payment p', 'p.business_no=o.order_no')
            ->select(['count(o.order_id) AS order_sum', 'o.site AS site', 'p.payment_method AS payment_method', 'FROM_UNIXTIME(o.created_at,"%Y%m%d") AS day'])
            ->andWhere(['>=', 'o.created_at', $startTime])
            ->andWhere(['<', 'o.created_at', $endTime])
            ->andWhere(['=', 'o.del_flag', 0])
            ->groupBy($groupBy)
            ->asArray()
            ->all($this->getDb());
    }

    /**
     * 关闭测试支付单，业务，勿删除。
     *
     * @param $paymentNo
     *
     * @return int
     * @author 白杨
     * @Date   19/8/21 下午7:24
     */
    public function closeTestOrder($orderNo)
    {
        $update = [
            'order_status'  => OrderRepository::STATUS_CLOSED,
            'is_closed'     => 1,
            'closed_at'     => time(),
            'closed_type'   => OrderRepository::CLOSED_TYPE_TEST,
            'closed_reason' => OrderRepository::CLOSED_TYPE_MAPPING[OrderRepository::CLOSED_TYPE_TEST]
        ];

        $where = [
            'is_test'      => 1,
            'order_no'     => $orderNo,
            'order_status' => OrderRepository::STATUS_PAID_SUCCESS
        ];

        $result = $this->update($update, $where);
        if ($result) {
            StockRedis::instance()->setOrderEsOrderNo([$orderNo]);
        }
        return $result;
    }

    /**
     * 下单数据 分组统计
     *
     * @param array $params
     *
     * @return array
     * @author 白杨
     * @Date   29/11/21 下午5:51
     */
    public function getPlaceGroupList($params = [], $select = [], $groupBy = [])
    {
        $where = [
            ['<>', 'order_type', OrderRepository::ORDER_TYPE_EXCHANGE]
        ];

        if (empty($params['start_time'])) {
            $params['start_time'] = date('Y-m-d 00:00:00');
        } else {
            $params['start_time'] = date('Y-m-d 00:00:00', strtotime($params['start_time']));
        }
        $where[] = ['>=', 'created_at', strtotime($params['start_time'])];

        if (empty($params['end_time'])) {
            $params['end_time'] = date('Y-m-d H:i:s');
        } else {
            $params['end_time'] = date('Y-m-d 23:59:59', strtotime($params['end_time']));
        }
        $where[] = ['<=', 'created_at', strtotime($params['end_time'])];

        if (isset($params['platform']) && $params['platform'] != '') {
            $where[] = ['=', 'platform', $params['platform']];
        }

        if (isset($params['site_code']) && $params['site_code'] != '') {
            $where[] = ['=', 'site', $params['site_code']];
        }

        if (empty($select)) {
            $select = ['site', 'count(1) as num', 'sum(goods_count) as qty', 'sum(usd_pay_amount) as usd_pay_amount'];
        }
        if (empty($groupBy)) {
            $groupBy = ['site'];
        }

        $query = Order::find()->select($select);
        foreach ($where as $v) {
            $query->andWhere($v);
        }
        $list = $query->groupBy($groupBy)->asArray()->all($this->getDb());

        $return = [];
        foreach ($list as $v) {
            $keyStr = "";
            foreach ($groupBy as $group) {
                $keyStr .= $v[$group] ?? '';
            }
            $return[$keyStr] = $v;
        }
        return $return;
    }

    public function getAmountStatisticsByLeftJoinAmount($select, $where, $groupBy)
    {
        $query = Order::find()
            ->select($select)
            ->leftJoin('order_amount', 'order_amount.order_no = order.order_no');

        foreach ($where as $v) {
            $query->andWhere($v);
        }
        $list = $query->groupBy($groupBy)->asArray()->all($this->getDb());

        $return = [];
        foreach ($list as $v) {
            $keyStr = "";
            foreach ($groupBy as $group) {
                $keyStr .= $v[$group] ?? '';
            }
            $return[$keyStr] = $v;
        }
        return $return;
    }

    /**
     * 获取订单的税费统计
     *
     * @param array $select
     * @param array $where
     * @param array $groupBy
     */
    public function getTaxStatisticsByLeftJoinAmount($select, $where, $groupBy)
    {
        $query = Order::find()
            ->select($select)
            ->leftJoin('order_amount', 'order_amount.order_no = order.order_no')
            ->leftJoin('order_goods_tax_detail', 'order_goods_tax_detail.business_no = order.order_no');

        foreach ($where as $v) {
            $query->andWhere($v);
        }
        $list = $query->groupBy($groupBy)->asArray()->all($this->getDb());

        $return = [];
        foreach ($list as $v) {
            $keyStr = "";
            foreach ($groupBy as $group) {
                $keyStr .= $v[$group] ?? '';
            }
            $return[$keyStr] = $v;
        }
        return $return;
    }

    public function getPlatformSales()
    {
        $list = Order::find()
            ->alias('o')
            ->leftJoin('order_amount oa', 'oa.order_no=o.order_no')
            ->select(['o.platform as name',
                'count(o.order_id) as num',
                'sum(o.goods_count) as qty',
                'sum(o.usd_pay_amount) as usd_pay_amount',
                      'sum(oa.usd_rate_fee) as usd_rate_fee'
            ])
            ->andWhere(['=', 'o.is_paid', 1])
            ->andWhere(['>=', 'o.paid_at', strtotime(date('Y-m-d'))])
            ->andWhere(['>=', 'oa.updated_at', strtotime('-1 day')])
            ->groupBy('o.platform')
            ->asArray()
            ->all();
        return array_column($list, null, 'name');
    }

    /**
     * 获取重复用劵的订单数据
     *
     * @param $startTime
     * @param $endTime
     *
     * @return array|Order[]|\yii\db\ActiveRecord[]
     */
    public function getCouponRepetitionOrder($startTime, $endTime)
    {
        $orderList = Order::find()
            ->select(['order_no', 'coupon_user_id'])
            ->andWhere(['=', 'is_paid', 1])
            ->andWhere(['!=', 'coupon_user_id', 0])
            ->andWhere(['>=', 'paid_at', $startTime])
            ->andWhere(['<', 'paid_at', $endTime])
            ->asArray()
            ->all();

        $couponUserIds = array_values(array_filter(array_unique(array_column($orderList, 'coupon_user_id'))));
        if (empty($orderList)) {
            return [];
        }
        $orderGroupList = Order::find()
            ->select(['max(order_id) as order_id', 'coupon_user_id', 'count(1) as num'])
            ->andWhere(['=', 'is_paid', 1])
            ->andWhere(['in', 'coupon_user_id', $couponUserIds])
            ->groupBy('coupon_user_id')
            ->having(['>', 'num', 1])
            ->asArray()
            ->all();

        if (empty($orderGroupList)) {
            return [];
        }

        return Order::find()
            ->select(['order_no', 'coupon_user_id'])
            ->andWhere(['in', 'order_id', array_values(array_column($orderGroupList, 'order_id'))])
            ->andWhere(['=', 'is_paid', 1])
            ->asArray()
            ->all();

    }

    /**
     * 根据获取订单和订单商品列表
     *
     * @param array $where
     * @param string $fields
     *
     * @return array|\yii\db\ActiveRecord[]
     * @author 德昌
     */
    public function getOrderListByOrderNos($where = [], $fields = '')
    {
        $order = Order::find()->alias('o')->leftJoin(OrderGoods::tableName() . ' og', 'o.order_no=og.order_no');

        if (!empty($where)) {
            foreach ($where as $v) {
                $order->andWhere($v);
            }
        }

        if (!empty($fields)) {
            $order->select($fields);
        }
        return $order->asArray()->all($this->getDb());
    }

    public function getReplenishOrderList($where = [], $fields = [], $orderBy = [])
    {
        $order = Order::find();

        if (!empty($where)) {
            foreach ($where as $v) {
                $order->andWhere($v);
            }
        }

        if (!empty($fields)) {
            $order->select($fields);
        }

        if (!empty($orderBy)) {
            $order->orderBy($orderBy);
        }
        return $order->asArray()->all($this->getDb());
    }


    /**
     * 用户订单分组
     *
     * @param int $userIds
     *
     * @return mixed
     */
    public function getOrderGroupByUserIds($userIds)
    {
        return Order::find()
            ->andWhere(['in', 'user_id', $userIds])
            ->groupBy(['user_id', 'site'])
            ->select('user_id,site,order_no')
            ->asArray()
            ->all();
    }

    public function getLastOrderByUserId($userId, $fields = '*')
    {
        return Order::find()
            ->andWhere(['=', 'user_id', $userId])
            ->andWhere(['!=', 'order_type', OrderRepository::ORDER_TYPE_REISSUE])
            ->andWhere(['=', 'del_flag', 0])
            ->select($fields)
            ->orderBy(['order_id' => SORT_DESC])
            ->asArray()
            ->one($this->getDb());
    }

    public function getByRelatedOrderNoAndBusinessNo($relatedOrderNo, $businessNo)
    {
        return Order::find()
            ->andWhere(['=', 'related_order_no', $relatedOrderNo])
            ->andWhere(['=', 'business_no', $businessNo])
            ->andWhere(['=', 'del_flag', 0])
            ->one($this->getDb());
    }

    public function getByRelatedOrderNo($businessNo)
    {
        return Order::find()
            ->andWhere(['=', 'business_no', $businessNo])
            ->andWhere(['=', 'del_flag', 0])
            ->asArray()
            ->one($this->getDb());
    }

    public function getListByBusinessNo($businessNo, $column = ['*'])
    {
        return Order::find()
            ->select($column)
            ->andWhere(['!=', 'order_status', self::STATUS_CLOSED])
            ->andWhere(['=', 'business_no', $businessNo])
            ->andWhere(['=', 'del_flag', 0])
            ->all($this->getDb());
    }

    public function getList($orderNos)
    {
        $order = Order::find();
        $order->andWhere(['in', 'order_no', $orderNos]);
        return $order->asArray()->all($this->getDb());
    }


    /**
     * @param array $column
     * @param array $conditions
     *
     * @return array|\yii\db\ActiveRecord|null
     */
    public function getOneByCondition(array $column = [], array $conditions = [], $orderBy = [])
    {
        return Order::find()
            ->select($column)
            ->where($conditions)
            ->orderBy($orderBy)
            ->asArray()
            ->one($this->getDb());
    }

    public function getByCondition(array $conditions = [], array $column = [], $orderBy = [])
    {
        $order = Order::find();

        if (!empty($conditions)) {
            foreach ($conditions as $v) {
                $order->andWhere($v);
            }
        }

        if (!empty($column)) {
            $order->select($column);
        }

        if (!empty($orderBy)) {
            $order->orderBy($orderBy);
        }
        return $order->asArray()->one($this->getDb());
    }

    public function getListByCondition(array $conditions = [], array $column = [], $orderBy = [])
    {
        $order = Order::find();

        if (!empty($conditions)) {
            foreach ($conditions as $v) {
                $order->andWhere($v);
            }
        }

        if (!empty($column)) {
            $order->select($column);
        }

        if (!empty($orderBy)) {
            $order->orderBy($orderBy);
        }
        return $order->asArray()->all($this->getDb());
    }

    /**
     * 获取位置订单信息
     *
     * @param $startTime
     * @param $endTime
     * @param $orderTypes
     *
     * @return array
     */
    public function getUnpayOrderNosByTime($startTime, $endTime, $orderTypes = [1, 2, 3])
    {
        return Order::find()
            ->andWhere(['>', 'updated_at', $startTime])
            ->andWhere(['<=', 'updated_at', $endTime])
            ->andWhere(['=', 'is_paid', 0])
            ->andWhere(['=', 'order_status', 0])
            ->andWhere(['in', 'order_type', $orderTypes])
            ->asArray()
            ->all($this->getDb());
    }

    public function dataComparisonOfLastYear()
    {
        $thisYearStart = strtotime(date('Y-m-d 00:00:00'));
        $thisYearEnd   = strtotime(date('Y-m-d  23:59:59'));
        $thisYearData  = $this->getHourSaleData($thisYearStart, $thisYearEnd);

        $lastYearStart = strtotime(date("Y-m-d 00:00:00", strtotime("-1 year")));
        $lastYearEnd   = strtotime(date("Y-m-d 23:59:59", strtotime("-1 year")));
        $lastYearData  = $this->getHourSaleData($lastYearStart, $lastYearEnd);


        $hour     = [];
        $thisYear = [];
        $lastYear = [];
        for ($i = 0; $i < 24; $i++) {
            if ($i < 10) {
                $hourTmp = sprintf('0%s', $i);
            } else {
                $hourTmp = strval($i);
            }
            $thisYear[] = bcsub($thisYearData[$hourTmp]['usd_pay_amount'] ?? '0', $thisYearData[$hourTmp]['usd_rate_fee'] ?? '0', 2);
            $lastYear[] = bcsub($lastYearData[$hourTmp]['usd_pay_amount'] ?? '0', $lastYearData[$hourTmp]['usd_rate_fee'] ?? '0', 2);
            $hour[]     = sprintf('%s:00', $hourTmp);
        }

        $hour[]     = '24:00';
        $thisYear[] = '0';
        $lastYear[] = '0';


        /**
         * series: {
         * date: [
         * "07:00",
         * "08:00",
         * "09:00",
         * "10:00",
         * "11:00",
         * "12:00",
         * "13:00",
         * "14:00",
         * ],
         * timeSeries: [
         * {
         * name: "Precipitation(2021)",
         * data: [
         * 2.6, 5.9, 9.0, 26.4, 28.7, 70.7, 175.6, 182.2, 48.7, 18.8, 6.0,
         * 2.3,
         * ],
         * },
         * {
         * name: "Precipitation(2022)",
         * data: [
         * 3.9, 5.9, 11.1, 18.7, 48.3, 69.2, 231.6, 46.6, 55.4, 18.4, 10.3,
         * 0.7,
         * ],
         * },
         * ],
         * },
         */
        $returnData = [
            'date'       => $hour,
            'timeSeries' => [
                ['name' => 'Precipitation(2021)', 'data' => $lastYear],
                ['name' => 'Precipitation(2022)', 'data' => $thisYear],
            ]
        ];
        return ['series' => $returnData];
    }

    /**
     * 获取订单的小时销售金额，税费金额
     *
     * @param $start
     * @param $end
     *
     * @return array
     */
    public function getHourSaleData($start, $end)
    {
        $where  = [
            ['=', 'order.is_paid', 1],
            ['!=', 'order.order_type', OrderRepository::ORDER_TYPE_EXCHANGE], //排除换货单补差
            ['>=', 'order.paid_at', $start],
            //['>=', 'order_amount.updated_at', $start - 3600],
            //['<=', 'order_amount.updated_at', $end + 3600],
            ['<=', 'order.paid_at', $end]
        ];
        $select = ['from_unixtime(order.paid_at,"%H") as hour',
                   'count(order.order_id) as num',
                   'sum(order.goods_count) as qty',
                   'sum(order.usd_pay_amount) as usd_pay_amount',
                   'sum(order_amount.usd_rate_fee) as usd_rate_fee',
        ];

        $groupBy = 'hour';
        $query   = Order::find()->alias('order')
            ->leftJoin('order_amount', 'order_amount.order_no=order.order_no')
            ->select($select);
        foreach ($where as $v) {
            $query->andWhere($v);
        }
        $list = $query->groupBy($groupBy)
            ->asArray()
            ->all($this->getDb());
        return array_column($list, null, 'hour');
    }

    /**
     * 获取指定日期
     *
     * @param $day string '2022-11-13'
     *
     * @return array
     */
    public function getSaleData($start, $end)
    {
        $where  = [
            ['=', 'o.is_paid', 1],
            ['!=', 'o.order_type', OrderRepository::ORDER_TYPE_EXCHANGE], //排除换货单补差
            ['>=', 'o.paid_at', $start],
            ['>=', 'oa.updated_at', $start - 3600],
            ['<=', 'oa.updated_at', $end + 3600],
            ['<=', 'o.paid_at', $end]
        ];
        $select = ['count(o.order_id) as num',
                   'sum(o.goods_count) as qty',
                   'sum(o.usd_pay_amount) as usd_pay_amount',
                   'sum(oa.usd_rate_fee) as usd_rate_fee'
        ];
        $query  = order::find()
            ->alias('o')
            ->leftJoin('order_amount oa', 'oa.order_no=o.order_no')
            ->select($select);
        foreach ($where as $v) {
            $query->andWhere($v);
        }
        return $query->asArray()->one($this->getDb());
    }

    /**
     * 支付数据，分组统计
     *
     * @param array $params
     * @param string[] $groupBy
     *
     * @return array
     * @author 白杨
     * @Date   29/11/21 下午6:09
     */
    public function getPaidGroupList($params = [], $select = [], $groupBy = [])
    {
        $where = [
            ['=', 'order.is_paid', 1],
            ['!=', 'order.order_type', OrderRepository::ORDER_TYPE_EXCHANGE], //排除换货单补差
        ];
        if (empty($params['start_time'])) {
            $params['start_time'] = date('Y-m-d 00:00:00');
        } else {
            $params['start_time'] = date('Y-m-d 00:00:00', strtotime($params['start_time']));
        }
        $where[] = ['>=', 'order.paid_at', strtotime($params['start_time'])];
        $where[] = ['>=', 'order_amount.updated_at', strtotime($params['start_time']) - 86400 * 30];

        if (empty($params['end_time'])) {
            $params['end_time'] = date('Y-m-d H:i:s');
        } else {
            $params['end_time'] = date('Y-m-d 23:59:59', strtotime($params['end_time']));
        }
        $where[] = ['<=', 'order.paid_at', strtotime($params['end_time'])];
        $where[] = ['<=', 'order_amount.updated_at', strtotime($params['start_time']) + 86400 * 30];


        if (isset($params['platform']) && $params['platform'] != '') {
            $where[] = ['=', 'order.platform', $params['platform']];
        }

        if (isset($params['site_code']) && $params['site_code'] != '') {
            $where[] = ['=', 'order.site', $params['site_code']];
        }

        if (empty($select)) {
            $select = ['order.site',
                       'count(order.order_id) as num',
                       'sum(order.goods_count) as qty',
                       'sum(order.usd_pay_amount) as usd_pay_amount',
                       'sum(order_amount.usd_rate_fee) as usd_rate_fee'
            ];
        }
        if (empty($groupBy)) {
            $groupBy = ['site'];
        }

        $query = Order::find()
            ->leftJoin('order_amount', 'order_amount.order_no=order.order_no')
            ->select($select);
        foreach ($where as $v) {
            $query->andWhere($v);
        }
        $list = $query->groupBy($groupBy)->asArray()->all($this->getDb());

        $return = [];
        foreach ($list as $v) {
            $keyStr = "";
            foreach ($groupBy as $group) {
                if (strpos($group, '.')) {
                    $tmp   = explode('.', $group);
                    $group = $tmp[1];
                }
                $keyStr .= $v[$group] ?? '';
            }
            $return[$keyStr] = $v;
        }
        return $return;

    }

    public function getWhereList($where, $fields = [])
    {
        $order = Order::find();
        foreach ($where as $v) {
            $order->andWhere($v);
        }
        if (!empty($fields)) {
            $order->select($fields);
        }
        return $order->asArray()->all($this->getDb());
    }

    /**
     * 统计某段支付时间内sku的销售数量
     *
     * @param array $skuIds 销售skuID列表
     * @param int $startPayAt 开始支付时间戳
     * @param int $endPayAt 结束支付时间戳
     * @param bool $includeZeroOrder 是否包含0元单
     * @param bool $includeExchangeOrder 是否包含换货单
     *
     * @return array
     */
    public function sumSkuSaleCount($skuIds, $startPayAt, $endPayAt, $site, $includeZeroOrder = false, $includeExchangeOrder = false)
    {
        $model = Order::find()
            ->select('sum(og.qty) qty, og.sku_id')
            ->alias('o')
            ->leftJoin(OrderGoods::tableName() . ' og', 'o.order_no=og.order_no')
            ->andWhere(['in', 'og.sku_id', $skuIds])
            ->andWhere(['=', 'o.site', $site])
            ->andWhere(['>=', 'o.paid_at', $startPayAt])
            ->andWhere(['<=', 'o.paid_at', $endPayAt])
            ->groupBy('og.sku_id');
        if (!$includeZeroOrder) {
            $model->andWhere(['=', 'o.is_sales_delivery_order', self::SALES_DELIVERY_ORDER]);
        }
        if (!$includeExchangeOrder) {
            $model->andWhere(['!=', 'o.order_type', self::ORDER_TYPE_EXCHANGE]);
        }
        return $model->asArray()->all($this->getDb());
    }

    /**
     * 根据sql获取数据
     *
     * @param string $sql
     *
     * @return int
     * @throws \yii\db\Exception
     */
    public function getDataBySql(string $sql = '')
    {
        return $this->getConnection()->createCommand($sql)->queryOne();
    }

    /**
     * 更新订单表的支付单号
     *
     * @param string $paymentNo 支付单号
     * @param string $orderNo 订单号
     *
     * @return int
     */
    public function updatePaymentNo($orderNo, $paymentNo)
    {
        $updateData = [
            'payment_no' => $paymentNo,
            'updated_at' => time(),
        ];
        return $this->update($updateData, ['order_no' => $orderNo]);
    }

    /**
     * @param        $userId
     * @param string $pf
     *
     * @return array|\yii\db\ActiveRecord[]
     */
    public function getOrderByUserIdOrDesc($userId, $pf)
    {
        $order = Order::find();
        if (!empty($site)) {
            $order->andWhere(['in', 'platform', [$pf]]);
        }
        $order->andWhere(['=', 'user_id', $userId]);
        $order->andWhere(['=', 'is_paid', 1]);
        return $order->asArray()->orderBy('paid_at desc')->one();
    }

    public function getTradingStatus($email, $skuId, $createAt = 0)
    {
        if (empty($email) || empty($skuId)) {
            return FaqUserQuestionRepository::TRADING_STATUS_NO_ORDER;
        }

        if ($createAt > 0) {
            $order = Order::find()
                ->alias('o')
                ->select('o.order_no, oa.email, og.sku_id , o.order_status , o.is_dispatched')
                ->join('join', 'order_address oa', 'o.order_no=oa.order_no')
                ->join('join', 'order_goods og', 'o.order_no=og.order_no')
                ->andWhere(['=', 'oa.email', $email])
                ->andWhere(['=', 'og.sku_id', $skuId])
                ->andWhere(['=', 'o.is_paid', 1])
                ->andWhere(['<=', 'o.created_at', $createAt])
                ->orderBy(['o.paid_at' => SORT_DESC])
                ->asArray()
                ->one();
        } else {
            $order = Order::find()
                ->alias('o')
                ->select('o.order_no, oa.email, og.sku_id , o.order_status , o.is_dispatched')
                ->join('join', 'order_address oa', 'o.order_no=oa.order_no')
                ->join('join', 'order_goods og', 'o.order_no=og.order_no')
                ->andWhere(['=', 'oa.email', $email])
                ->andWhere(['=', 'og.sku_id', $skuId])
                ->andWhere(['=', 'o.is_paid', 1])
                ->orderBy(['o.paid_at' => SORT_DESC])
                ->asArray()
                ->one();
        }
        if (empty($order)) {
            return FaqUserQuestionRepository::TRADING_STATUS_NO_ORDER;
        }
        if ($order['is_dispatched'] != 1) {
            return FaqUserQuestionRepository::TRADING_STATUS_ORDER_NO_SEND;
        }
        if ($order['order_status'] != self::STATUS_COMPLETED) {
            return FaqUserQuestionRepository::TRADING_STATUS_ORDER_SEND;
        }
        if ($order['order_status'] == self::STATUS_COMPLETED) {
            return FaqUserQuestionRepository::TRADING_STATUS_ORDER_SEND_SIGN;
        }
    }

    /**
     * 获取某个用户过去一次支付的时间
     *
     * @param int $userId 用户ID
     *
     * @return int
     */
    public function getLastOrderPaidAt($userId, $startPaidAt, $endPaidAt)
    {
        return Order::find()
            ->andWhere(['=', 'user_id', $userId])
            ->andWhere(['>=', 'paid_at', $startPaidAt])
            ->andWhere(['<=', 'paid_at', $endPaidAt])
            ->andWhere(['=', 'del_flag', 0])
            ->max('paid_at', $this->getDb());
    }

    public function getListByMinIdAndMaxId($minId, $maxId = 0, $column = '*', $limit = 100)
    {
        $orderModel = Order::find();
        if ($maxId > 0) {
            $orderModel->andWhere(['<=', 'order_id', $maxId]);
        }
        $orderList = $orderModel->andWhere(['>', 'order_id', $minId])
            ->select($column)
            ->limit($limit)
            ->orderBy(['order_id' => SORT_ASC])
            ->asArray()
            ->all();
        return $orderList;
    }


    /**
     * 通过关联单号获取数量
     *
     * @param string $relatedOrderNo 关联单号
     * @param int $orderType 订单类型
     *
     * @return int
     */
    public function getCountByRelatedOrderNo($relatedOrderNo, $orderType)
    {
        return Order::find()
            ->andWhere(['=', 'related_order_no', $relatedOrderNo])
            ->andWhere(['=', 'order_type', $orderType])
            ->andWhere(['=', 'del_flag', 0])
            ->count('*', $this->getDb());
    }

    /**
     * 更新订单的为支付成功
     *
     * @param string|array $orderNos 订单号
     * @param int $paidAt 支付时间
     *
     * @return int
     */
    public function updateOrderPaid($orderNos, $paidAt = 0)
    {
        $updateData = [
            'order_status' => self::STATUS_PAID_SUCCESS,
            'is_paid'      => 1,
            'updated_at'   => time(),
        ];
        if ($paidAt > 0) {
            $updateData['paid_at'] = $paidAt;
        }
        return $this->update($updateData, ['order_no' => $orderNos]);
    }

    /**
     * 更新订单的为支付成功
     *
     * @param string|array $orderNos 订单号
     *
     * @return int
     */
    public function updateOrderCancel($orderNos)
    {
        $updateData = [
            'order_status' => self::STATUS_CLOSED,
            'is_paid'      => 1,
            'updated_at'   => time(),
        ];
        return $this->update($updateData, ['order_no' => $orderNos]);
    }

    /**
     * 更新订单的支付时间
     *
     * @param string|array $orderNos 订单号
     * @param int $paidAt 支付时间
     *
     * @return int
     */
    public function updateOrderPaidAt($orderNos, $paidAt)
    {
        $updateData = [
            'paid_at'    => $paidAt,
            'updated_at' => time(),
        ];
        return $this->update($updateData, ['order_no' => $orderNos]);
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
        return Order::find()
            ->andWhere(['=', 'related_order_no', $relatedOrderNo])
            ->andWhere(['=', 'is_paid', 1])
            ->andWhere(['!=', 'order_status', self::STATUS_CLOSED])
            ->andWhere(['=', 'del_flag', 0])
            ->count('*', $this->getDb());
    }

    /**
     * 获取用户购买次数
     *
     * @return array
     */
    public function getPurchaseCountGroupByUser($userIds = [], $orderTypeList = [], $page = 1, $pageSize = 20)
    {
        $model = Order::find()
            ->select(['count(*) as total', 'user_id'])
            ->andWhere(['=', 'is_paid', 1])
            ->andWhere(['!=', 'order_status', self::STATUS_CLOSED])
            ->groupBy('user_id')
            ->andWhere(['=', 'del_flag', 0])
            ->orderBy('user_id asc');
        if (!empty($orderTypeList)) {
            $model->andWhere(['in', 'order_type', $orderTypeList]);
        }
        if (!empty($userIds)) {
            $model->andWhere(['in', 'user_id', $userIds]);
        }
        if (!empty($page) && !empty($pageSize)) {
            $model->limit($pageSize)->offset(($page - 1) * $pageSize);
        }
        return $model->asArray()->all($this->getDb());
    }

    /**
     * 通过用户ID获取已支付订单列表
     *
     * @return array
     */
    public function getPaidListByUserId($userIds = [], $orderTypeList = [], $fields = [])
    {
        $model = Order::find()
            ->select($fields)
            ->andWhere(['=', 'is_paid', 1])
            ->andWhere(['!=', 'order_status', self::STATUS_CLOSED])
            ->andWhere(['=', 'del_flag', 0]);
        if (!empty($orderTypeList)) {
            $model->andWhere(['in', 'order_type', $orderTypeList]);
        }
        if (!empty($userIds)) {
            $model->andWhere(['in', 'user_id', $userIds]);
        }
        return $model->asArray()->all($this->getDb());
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
     * @param int $type 1：情况1、2:情况2、3:情况3、4:情况4、5:情况5
     * @param array $item 对应类型的值
     * {store_staff_id: 1, manager_store_ids: [],staff_store_ids:[]}
     * @param int $storeId
     * @param int $storeStaffId
     * @return array
     */
    public function getStoreOrderNoList(int $type = 5, array $item = [], int $storeId = 0, int $storeStaffId = 0): array
    {
        if (!in_array($type, [1, 2, 3, 4, 5])) {
            return [];
        }
        if ($type == 5 && $storeId <= 0 && $storeStaffId <= 0) {
            return [];
        }
        $query = Order::find()
            ->select('order_no')
            ->andWhere(['=', 'del_flag', 0]);
        switch ($type) {
            case 1: // 总部人员
                $query->andWhere(['>', 'affiliated_store_id', 0])
                    ->andWhere(['>', 'affiliated_staff_id', 0]);
                break;
            case 2: // 一个/多个店铺ID + 都是店员
                $query->andWhere(['in', 'affiliated_store_id', $item['staff_store_ids']])
                    ->andWhere(['=', 'affiliated_staff_id', $item['store_staff_id']]);
                break;
            case 3: // 多个店铺Id + 一个是店员/一个是店长
                // 条件1：作为店员的店铺，且是自己创建的订单
                $assistantCondition = [
                    'and',
                    ['in', 'affiliated_store_id', $item['staff_store_ids'] ?? [-1]],
                    ['=', 'affiliated_staff_id', $item['store_staff_id']]
                ];
                // 条件2：作为店长的店铺的所有订单（不限店员）
                $managerCondition = ['in', 'affiliated_store_id', $item['manager_store_ids'] ?? [-1]];
                // 使用OR条件合并
                $query->andWhere(['or', $assistantCondition, $managerCondition]);
                break;
            case 4: // 多个店铺ID + 都是店长
                $query->andWhere(['in', 'affiliated_store_id', $item['manager_store_ids']]);
                break;

        }
        // 额外指定的店铺和店员查询
        if ($storeId) {
            $query->andWhere(['=', 'affiliated_store_id', $storeId]);
        }
        if ($storeStaffId) {
            $query->andWhere(['=', 'affiliated_staff_id', $storeStaffId]);
        }

        return $query->asArray()->all($this->getDb());
    }

    /**
     * 获取门店订单号
     * 一个店员可以在多家门店工作，有可能其中一个店铺做店长，一个店铺里面做店员
     * 情况1: 总部人员
     * 情况2:一个/多个店铺ID + 都是店员
     * 情况3:多个店铺Id + 一个是店员/一个是店长
     * 情况4:多个店铺ID + 都是店长
     *
     * @param int $type
     * @param array $item
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public function storeOrderStatistics(int $type, array $item, $startTime, $endTime, int $storeId = 0, int $storeStaffId = 0, string $siteCode = ''): array
    {
        if (!in_array($type, [1, 2, 3, 4]) || ($type != 1 && empty($item))) {
            return [];
        }

        $select = [
            'site',
            'currency',
            'currency_symbol',
            'SUM(pay_amount) total_pay_amount',
            'SUM(usd_pay_amount) total_usd_pay_amount',
            'COUNT(order_no) total_order_count',
            'SUM(goods_count) total_goods_count',
            'count(user_id) total_user_count',
            'affiliated_store_id',
        ];
        $query = order::find()
            ->select($select)
            ->andWhere(['=', 'is_paid', 1])
            ->andWhere(['!=', 'order_type', self::ORDER_TYPE_REISSUE])
            ->andWhere(['>=', 'paid_at', $startTime])
            ->andWhere(['<=', 'paid_at', $endTime])
            ->groupBy('affiliated_store_id');
        switch ($type) {
            case 1: // 总部人员
                $query->andWhere(['>', 'affiliated_store_id', 0])
                    ->andWhere(['>', 'affiliated_staff_id', 0]);
                break;
            case 2: // 一个/多个店铺ID + 都是店员
                $query->andWhere(['in', 'affiliated_store_id', $item['staff_store_ids']])
                    ->andWhere(['=', 'affiliated_staff_id', $item['store_staff_id']]);
                break;
            case 3: // 多个店铺Id + 一个是店员/一个是店长
                // 条件1：作为店员的店铺，且是自己创建的订单
                $assistantCondition = [
                    'and',
                    ['in', 'affiliated_store_id', $item['staff_store_ids'] ?? [-1]],
                    ['=', 'affiliated_staff_id', $item['store_staff_id']]
                ];
                // 条件2：作为店长的店铺的所有订单（不限店员）
                $managerCondition = ['in', 'affiliated_store_id', $item['manager_store_ids'] ?? [-1]];
                // 使用OR条件合并
                $query->andWhere(['or', $assistantCondition, $managerCondition]);
                break;
            case 4: // 多个店铺ID + 都是店长
                $query->andWhere(['in', 'affiliated_store_id', $item['manager_store_ids']]);
                break;

        }
        // 额外指定的店铺和店员查询
        if ($storeId) {
            $query->andWhere(['=', 'affiliated_store_id', $storeId]);
        }
        if ($storeStaffId) {
            $query->andWhere(['=', 'affiliated_staff_id', $storeStaffId]);
        }
        if ($siteCode) {
            $query->andWhere(['=', 'site', $siteCode]);
        }
        return $query->asArray()->all($this->getDb());
    }

    public function storeOrderStatisticsForSite(int $type, array $item, $startTime, $endTime, int $storeId = 0, int $storeStaffId = 0, string $siteCode = ''): array
    {
        if (!in_array($type, [1, 2, 3, 4]) || ($type != 1 && empty($item))) {
            return [];
        }

        $select = [
            'site',
            'currency',
            'currency_symbol',
            'SUM(pay_amount) total_pay_amount',
            'SUM(usd_pay_amount) total_usd_pay_amount',
            'COUNT(order_no) total_order_count',
            'SUM(goods_count) total_goods_count',
            'count(user_id) total_user_count',
            'count(DISTINCT affiliated_store_id) total_store_count'
        ];
        $query = order::find()
            ->select($select)
            ->andWhere(['=', 'is_paid', 1])
            ->andWhere(['!=', 'order_type', self::ORDER_TYPE_REISSUE])
            ->andWhere(['>=', 'paid_at', $startTime])
            ->andWhere(['<=', 'paid_at', $endTime])
            ->groupBy('site');
        switch ($type) {
            case 1: // 总部人员
                $query->andWhere(['>', 'affiliated_store_id', 0])
                    ->andWhere(['>', 'affiliated_staff_id', 0]);
                break;
            case 2: // 一个/多个店铺ID + 都是店员
                $query->andWhere(['in', 'affiliated_store_id', $item['staff_store_ids']])
                    ->andWhere(['=', 'affiliated_staff_id', $item['store_staff_id']]);
                break;
            case 3: // 多个店铺Id + 一个是店员/一个是店长
                // 条件1：作为店员的店铺，且是自己创建的订单
                $assistantCondition = [
                    'and',
                    ['in', 'affiliated_store_id', $item['staff_store_ids'] ?? [-1]],
                    ['=', 'affiliated_staff_id', $item['store_staff_id']]
                ];
                // 条件2：作为店长的店铺的所有订单（不限店员）
                $managerCondition = ['in', 'affiliated_store_id', $item['manager_store_ids'] ?? [-1]];
                // 使用OR条件合并
                $query->andWhere(['or', $assistantCondition, $managerCondition]);
                break;
            case 4: // 多个店铺ID + 都是店长
                $query->andWhere(['in', 'affiliated_store_id', $item['manager_store_ids']]);
                break;

        }
        // 额外指定的店铺和店员查询
        if ($storeId) {
            $query->andWhere(['=', 'affiliated_store_id', $storeId]);
        }
        if ($storeStaffId) {
            $query->andWhere(['=', 'affiliated_staff_id', $storeStaffId]);
        }
        if ($siteCode) {
            $query->andWhere(['=', 'site', $siteCode]);
        }
        return $query->asArray()->all($this->getDb());
    }

    public function storeOrderStatisticsForStaff(int $type, array $item, $startTime, $endTime, int $storeId = 0, int $storeStaffId = 0, string $siteCode = ''): array
    {
        if (!in_array($type, [1, 2, 3, 4]) || ($type != 1 && empty($item))) {
            return [];
        }

        $select = [
            'site',
            'affiliated_store_id',
            'affiliated_staff_id',
            'currency',
            'currency_symbol',
            'SUM(pay_amount) total_pay_amount',
            'SUM(usd_pay_amount) total_usd_pay_amount',
            'COUNT(order_no) total_order_count',
            'SUM(goods_count) total_goods_count',
            'count(DISTINCT user_id) total_user_count',
            'count(DISTINCT affiliated_store_id) total_store_count'
        ];
        $query = order::find()
            ->select($select)
            ->andWhere(['=', 'is_paid', 1])
            ->andWhere(['!=', 'order_type', self::ORDER_TYPE_REISSUE])
            ->andWhere(['>=', 'paid_at', $startTime])
            ->andWhere(['<=', 'paid_at', $endTime])
            ->groupBy('affiliated_store_id,affiliated_staff_id');
        switch ($type) {
            case 1: // 总部人员
                $query->andWhere(['>', 'affiliated_store_id', 0])
                    ->andWhere(['>', 'affiliated_staff_id', 0]);
                break;
            case 2: // 一个/多个店铺ID + 都是店员
                $query->andWhere(['in', 'affiliated_store_id', $item['staff_store_ids']])
                    ->andWhere(['=', 'affiliated_staff_id', $item['store_staff_id']]);
                break;
            case 3: // 多个店铺Id + 一个是店员/一个是店长
                // 条件1：作为店员的店铺，且是自己创建的订单
                $assistantCondition = [
                    'and',
                    ['in', 'affiliated_store_id', $item['staff_store_ids'] ?? [-1]],
                    ['=', 'affiliated_staff_id', $item['store_staff_id']]
                ];
                // 条件2：作为店长的店铺的所有订单（不限店员）
                $managerCondition = ['in', 'affiliated_store_id', $item['manager_store_ids'] ?? [-1]];
                // 使用OR条件合并
                $query->andWhere(['or', $assistantCondition, $managerCondition]);
                break;
            case 4: // 多个店铺ID + 都是店长
                $query->andWhere(['in', 'affiliated_store_id', $item['manager_store_ids']]);
                break;

        }
        // 额外指定的店铺和店员查询
        if ($storeId) {
            $query->andWhere(['=', 'affiliated_store_id', $storeId]);
        }
        if ($storeStaffId) {
            $query->andWhere(['=', 'affiliated_staff_id', $storeStaffId]);
        }
        if ($siteCode) {
            $query->andWhere(['=', 'site', $siteCode]);
        }
        return $query->asArray()->all($this->getDb());
    }
}
