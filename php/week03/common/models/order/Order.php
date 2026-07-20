<?php

namespace common\models\order;

use Yii;

/**
 * This is the model class for table "order".
 *
 * @property int    $order_id
 * @property string $order_no              订单号
 * @property string $site                  当前站点
 * @property string $language              当前语言
 * @property string $currency              当前货币
 * @property string $currency_symbol       当前货币符号
 * @property string $to_usd_rate           美元汇率
 * @property string $to_cny_rate           人民币汇率
 * @property int    $order_status          订单状态:0-待支付，1-支付后待审核，2-待发货，3-配送中，4-已关闭，6-已完成，7-支付成功，8-部分发货，9-全部发货
 * @property string $order_status_str      订单状态描述
 * @property int    $order_type            订单类型 1-普通订单 2-代客下单 3-补发订单 4-换货订单
 * @property string $related_order_no      关联的订单号
 * @property string $payment_no            支付单号
 * @property int    $user_id               用户的id
 * @property int    $is_guest              是否游客 0-不是 1-是
 * @property string $eid                   设备标识
 * @property int    $goods_count           订单中产品的总个数，默认为0个
 * @property string $pay_amount            支付金额
 * @property string $usd_pay_amount        支付金额（美元）
 * @property string $cny_pay_amount        支付金额 （人民币）
 * @property int    $coupon_user_id        优惠劵领取的id
 * @property string $total_weight          总重量
 * @property int    $location_type         取货方式
 * @property int    $delivery_time_start   约定配送开始时间
 * @property int    $delivery_time_end     约定配送结束时间
 * @property string $shipping_method       货运方式
 * @property string $platform              来源：pc，m
 * @property string $remote_ip             ip地址
 * @property string $order_remark          订单的备注信息，由买家填写提交
 * @property string $audit_reason          支付待审核原因
 * @property int    $is_paid               是否已支付 0-未支付 1-已支付
 * @property int    $paid_at               支付时间
 * @property int    $is_dispatched         是否已发货 0-未发货 1-已发货
 * @property int    $dispatched_at         订单发货时间
 * @property int    $is_received           是否已收货 0-未收货 1-已收货
 * @property int    $received_at           订单收货时间
 * @property int    $is_reviewed           是否评论：0-未评论，1-已经评论
 * @property int    $reviewed_at           订单评论时间
 * @property int    $is_closed             是否已取消 0-未取消 1-已取消
 * @property int    $closed_at             取消时间
 * @property int    $closed_type           取消类型 1-超时关闭 2-用户关闭3-售前退款 4-erp无法履约 5-客服关闭未支付订单
 * @property string $closed_reason         订单关闭理由
 * @property int    $is_refunded           是否已退款 0-未退款 1-部分退款 2-全部退款
 * @property int    $is_show_to_customer   是否显示给顾客 1-显示 0-不显示
 * @property string $captcha_score         google的验证分数
 * @property int    $is_oversold           是否超卖 0-未超卖，1-超卖
 * @property int    $created_at            创建时间
 * @property int    $updated_at            更新时间
 * @property int    $del_flag              删除标志 0正常 1删除
 * @property string $last_modify_time      最后更新时间
 * @property int    $business_type         业务类型 0 是平台业务 1是分销业务
 * @property int    $is_test               测试标记 0-普通订单 1-测试订单
 * @property string $business_no           业务单号：换货-售后单号
 * @property int    $support_after_sale    是否支持售后，0：否，1：是
 * @property int    $order_sales_type      订单销售类型，1：普通订单-toc，2：普通订单-tob，3：清销订单
 * @property int    $enable_sales_discount 是否可用营销优惠，0：不可用，1：可用
 */
class Order extends \common\BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'order';
    }

    /**
     * @return \yii\db\Connection the database connection used by this AR class.
     */
    public static function getDb()
    {
        return Yii::$app->get('dbFecshop');
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['order_no', 'site', 'language', 'to_usd_rate', 'to_cny_rate', 'order_status', 'user_id', 'pay_amount', 'usd_pay_amount', 'cny_pay_amount'], 'required'],
            [['to_usd_rate', 'to_cny_rate', 'pay_amount', 'usd_pay_amount', 'cny_pay_amount', 'total_weight', 'captcha_score'], 'number'],
            [['order_status', 'order_type', 'user_id', 'is_guest', 'goods_count', 'coupon_user_id', 'location_type', 'delivery_time_start', 'delivery_time_end', 'is_paid', 'paid_at', 'is_dispatched', 'dispatched_at', 'is_received', 'received_at', 'is_reviewed', 'reviewed_at', 'is_closed', 'closed_at', 'closed_type', 'is_refunded', 'is_show_to_customer', 'is_oversold', 'created_at', 'updated_at', 'del_flag', 'business_type', 'is_test', 'support_after_sale', 'order_sales_type', 'enable_sales_discount'], 'integer'],
            [['last_modify_time'], 'safe'],
            [['order_no', 'related_order_no', 'payment_no', 'business_no'], 'string', 'max' => 32],
            [['site', 'language', 'currency', 'currency_symbol', 'platform'], 'string', 'max' => 10],
            [['order_status_str'], 'string', 'max' => 30],
            [['shipping_method'], 'string', 'max' => 20],
            [['remote_ip'], 'string', 'max' => 26],
            [['order_remark'], 'string', 'max' => 400],
            [['audit_reason'], 'string', 'max' => 100],
            [['closed_reason'], 'string', 'max' => 255],
            [['order_no'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'order_id'              => 'Order ID',
            'order_no'              => 'Order No',
            'site'                  => 'Site',
            'language'              => 'Language',
            'currency'              => 'Currency',
            'currency_symbol'       => 'Currency Symbol',
            'to_usd_rate'           => 'To Usd Rate',
            'to_cny_rate'           => 'To Cny Rate',
            'order_status'          => 'Order Status',
            'order_status_str'      => 'Order Status Str',
            'order_type'            => 'Order Type',
            'related_order_no'      => 'Related Order No',
            'payment_no'            => 'Payment No',
            'user_id'               => 'User ID',
            'is_guest'              => 'Is Guest',
            'goods_count'           => 'Goods Count',
            'pay_amount'            => 'Pay Amount',
            'usd_pay_amount'        => 'Usd Pay Amount',
            'cny_pay_amount'        => 'Cny Pay Amount',
            'coupon_user_id'        => 'Coupon User ID',
            'total_weight'          => 'Total Weight',
            'location_type'         => 'Location Type',
            'delivery_time_start'   => 'Delivery Time Start',
            'delivery_time_end'     => 'Delivery Time End',
            'shipping_method'       => 'Shipping Method',
            'platform'              => 'Platform',
            'remote_ip'             => 'Remote Ip',
            'order_remark'          => 'Order Remark',
            'audit_reason'          => 'Audit Reason',
            'is_paid'               => 'Is Paid',
            'paid_at'               => 'Paid At',
            'is_dispatched'         => 'Is Dispatched',
            'dispatched_at'         => 'Dispatched At',
            'is_received'           => 'Is Received',
            'received_at'           => 'Received At',
            'is_reviewed'           => 'Is Reviewed',
            'reviewed_at'           => 'Reviewed At',
            'is_closed'             => 'Is Closed',
            'closed_at'             => 'Closed At',
            'closed_type'           => 'Closed Type',
            'closed_reason'         => 'Closed Reason',
            'is_refunded'           => 'Is Refunded',
            'is_show_to_customer'   => 'Is Show To Customer',
            'captcha_score'         => 'Captcha Score',
            'is_oversold'           => 'Is Oversold',
            'created_at'            => 'Created At',
            'updated_at'            => 'Updated At',
            'del_flag'              => 'Del Flag',
            'last_modify_time'      => 'Last Modify Time',
            'business_type'         => 'Business Type',
            'is_test'               => 'Is Test',
            'business_no'           => 'Business No',
            'support_after_sale'    => 'Support After Sale',
            'order_sales_type'      => 'Order Sales Type',
            'enable_sales_discount' => 'Enable Sales Discount',
        ];
    }
}