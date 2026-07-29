<?php

namespace common\models\order;

use Yii;

/**
 * This is the model class for table "order_goods".
 *
 * @property int    $order_goods_id
 * @property int    $order_goods_type               订单商品类型 1-普通商品 2-建议补发商品 3-补发商品
 * @property string $order_no                       订单编码
 * @property int    $user_id                        用户的id
 * @property string $pay_amount                     支付金额
 * @property string $usd_pay_amount                 支付金额（美元）
 * @property string $cny_pay_amount                 支付金额（人民币）
 * @property string $goods_price                    商品价格
 * @property string $usd_goods_price                商品价格（美元）
 * @property string $cny_goods_price                商品价格 （人民币）
 * @property string $market_price                   商品原价
 * @property string $usd_market_price               商品原价（美元）
 * @property string $cny_market_price               商品原价（人民币）
 * @property string $total_goods_price              商品总价
 * @property string $usd_total_goods_price          商品总价（美元）
 * @property string $cny_total_goods_price          商品总价（人民币）
 * @property string $goods_discount                 商品折扣价格：秒杀价 直接商品减价
 * @property string $usd_goods_discount             商品折扣价格（美元）
 * @property string $cny_goods_discount             商品折扣价格 (人民币)
 * @property string $sku_id                         sku_id
 * @property int    $qty                            购买数量，建议补发数量，补发数量
 * @property string $sku_code                       sku_code
 * @property int    $spu_id                         spu_id
 * @property string $spu_code                       spu_code
 * @property string $spu_title                      商品标题
 * @property int    $spu_title_resource_id          商品资源id
 * @property string $spu_description                商品描述
 * @property string $img                            商品图片
 * @property string $sale_attr_group                商品售卖属性
 * @property string $base_attr_group                商品基础属性
 * @property string $jump_url                       商品跳转url
 * @property string $spu_brand_name                 品牌名称
 * @property string $spu_category_name_first        一级分类名称
 * @property string $spu_category_name_second       二级分类名称
 * @property string $spu_category_name_third        三级分类名称
 * @property string $spu_category_name_fourth       二级分类名称
 * @property string $estimated_arrival_at           预计到达时间
 * @property int    $coupon_user_id                 优惠劵领取的id
 * @property string $coupon_discount                优惠券分摊金额
 * @property string $usd_coupon_discount            优惠券分摊金额（美元）
 * @property string $cny_coupon_discount            优惠券分摊金额（人民币）
 * @property int    $activity_type                  活动类型
 * @property int    $activity_id                    活动id
 * @property string $activity_title                 活动标题
 * @property string $activity_price                 活动金额
 * @property string $usd_activity_price             活动金额（美元）
 * @property string $cny_activity_price             活动金额（人民币）
 * @property string $activity_discount              活动的优惠金额
 * @property string $usd_activity_discount          活动的优惠金额（美元）
 * @property string $cny_activity_discount          活动的优惠金额（人民币）
 * @property string $shipping_method                货运方式
 * @property string $shipping_fee                   运费总额
 * @property string $usd_shipping_fee               运费总额（美元）
 * @property string $cny_shipping_fee               运费总额（人民币）
 * @property string $rate_fee                       税费
 * @property string $usd_rate_fee                   税费（美元）
 * @property string $cny_rate_fee                   税费（人民币）
 * @property string $customer_service_discount      客服的优惠金额
 * @property string $usd_customer_service_discount  客服的优惠金额（美元）
 * @property string $cny_customer_service_discount  客服的优惠金额（人民币）
 * @property string $payment_discount               支付优惠金额
 * @property string $usd_payment_discount           支付优惠金额(美元)
 * @property string $cny_payment_discount           支付优惠金额(人民币)
 * @property int    $is_paid                        是否已支付 0-未支付 1-已支付
 * @property int    $paid_at                        支付时间
 * @property int    $is_dispatched                  是否已发货 0-未发货 1-已发货
 * @property int    $dispatched_at                  订单发货时间
 * @property int    $is_received                    是否已收货 0-未收货 1-已收货
 * @property int    $received_at                    订单收货时间
 * @property int    $is_reviewed                    是否评论 1-已经评论，0-未评论
 * @property int    $reviewed_at                    订单评论时间
 * @property int    $is_refunded                    是否已退款 0-未退款 1-部分退款 2-全部退款
 * @property string $ec_order_no                    易仓销售单号
 * @property string $ec_warehouse_order_no          易仓仓配订单号
 * @property string $ec_warehouse_name              发货仓库名称
 * @property int    $oversold_num                   超卖数量
 * @property int    $created_at                     创建时间
 * @property int    $updated_at                     更新时间
 * @property int    $del_flag                       删除标志 0正常 1删除
 * @property int    $dispatch_status                发货状态 0 待发货 1 部分发货 2 全部发货
 * @property string $last_modify_time               最后更新时间
 * @property string $guarantee_service              当前商品享受的保障服务（json）
 * @property string $estimated_delivery_timestr     预计到达时间
 * @property int    $ec_status                      易仓订单商品状态
 * @property int    $ec_warehouse_type              易仓仓库类型，1海外仓 2广州仓
 * @property int    $delivery_track_type            订单跟踪线路类型，1：广州仓发货，2：外仓发货-缺货，3：海运在途，4：外仓发货-现货
 * @property string $cost_price                     成本价格（pms价格）
 * @property string $usd_cost_price                 成本价格（美元）
 * @property string $cny_cost_price                 成本价格（人民币）
 * @property int    $is_exchange                    0：未换货，1：换货中，2：换货成功（商品已取消）
 * @property int    $delivery_method_snapshoot      配送方式 1-物流 2-自提 快照数据，存入后不再修改
 * @property int    $delivery_method                配送方式 1-物流 2-自提
 * @property string $pick_up_warehouse_code         自提仓库code
 * @property string $pick_up_order_no               自提单号
 * @property int    $pick_up_time                   自提时间开始时间
 * @property string $pick_up_warehouse_name         自提的仓库名称（中文）
 * @property string $goods_label                    商品标签
 * @property string $market_label                   活动标签
 * @property int    $sku_type                       SKU类型，0：非正常商品，1：正常商品
 * @property string $pay_amount_actual              实际支付金额
 * @property string $usd_pay_amount_actual          实际支付金额（美元）
 * @property string $cny_pay_amount_actual          实际支付金额（人民币）
 * @property string $value_added_service_amount     增值服务费用
 * @property string $usd_value_added_service_amount 增值服务费用(美元)
 * @property string $cny_value_added_service_amount 增值服务费用(人民币)
 * @property int    $eta_total_max_stamp            最大到客时间
 * @property int    $eta_total_min_stamp            最小到客时间
 * @property string $goods_eta_info                 商品eta信息，json
 * @property int    $rule_type                      规则类型：0-普通商品 1- 库存售空或活动结束，商品自动下架; 2 -库存售空或活动结束，恢复零售价售卖
 * @property int    $delivery_channel               1、卡车、2快递-可跨仓，3、快递-不可跨仓
 * @property int    $delivery_qty                   发货数量
 * @property int    $is_suit                        是否是套装 0-否 1-是
 * @property int    $suit_sku_id                    套装sku_id
 * @property int    $points                         商品抵扣对应的积分
 * @property string $points_discount                积分抵扣金额
 * @property string $usd_points_discount            积分抵扣金额(美元)
 * @property string $cny_points_discount            积分抵扣金额(人民币)
 * @property int    $receive_points                 收货后领取的积分数
 * @property string $suit_discount                  套装折扣金额
 * @property string $usd_suit_discount              套装折扣金额(美元)
 * @property string $cny_suit_discount              套装折扣金额(人民币)
 * @property string $protection_plan_amount         保养服务费用
 * @property string $usd_protection_plan_amount     保养服务费用(美元)
 * @property string $cny_protection_plan_amount     保养服务费用(人民币)
 * @property string $tag_price                      商品划线价(单价)
 * @property string $usd_tag_price                  商品划线价(美元)
 * @property string $cny_tag_price                  商品划线价(人民币)
 * @property string $tax_code                       avaTax code
 * @property string $profit_check_ratio             利润核算系数
 * @property string $exchange_discount              换货优惠金额
 * @property string $usd_exchange_discount          换货优惠金额（美元）
 * @property string $cny_exchange_discount          换货优惠金额（人民币）
 * @property string $goods_warehouse_address        商品eta的仓库地址信息
 * @property int    $is_color_sku                   是否小样 1-是 0-否
 * @property int    $limited_time_reduction_id
 * @property string $promotion_ratio                推广占比
 * @property string $actual_cost_data               实际成本
 * @property int    $coupon_id                      优惠劵id
 * @property string $coupon_group_name              优惠券券组名称
 * @property int    $outbound_process               门店自提出库流程，1：普通出库，2：门店直出
 * @property string $refund_rate_fee                换货退税费
 * @property int    $sku_class                      sku类型，1：销售sku，2：仓库sku，3：零部件sku
 * @property string $store_manager_discount         店长优惠(当前币种)
 * @property string $usd_store_manager_discount     店长优惠（美元）
 * @property string $cny_store_manager_discount     店长优惠（人民币）
 * @property int    $is_include_next_day            是否为次日达服务，1：是，0：否
 */
class OrderGoods extends \common\BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'order_goods';
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
            [['order_goods_type', 'user_id', 'sku_id', 'qty', 'spu_id', 'spu_title_resource_id', 'coupon_user_id', 'activity_type', 'activity_id', 'is_paid', 'paid_at', 'is_dispatched', 'dispatched_at', 'is_received', 'received_at', 'is_reviewed', 'reviewed_at', 'is_refunded', 'oversold_num', 'created_at', 'updated_at', 'del_flag', 'dispatch_status', 'ec_status', 'ec_warehouse_type', 'delivery_track_type', 'is_exchange', 'delivery_method_snapshoot', 'delivery_method', 'pick_up_time', 'sku_type', 'eta_total_max_stamp', 'eta_total_min_stamp', 'rule_type', 'delivery_channel', 'delivery_qty', 'is_suit', 'suit_sku_id', 'points', 'receive_points', 'is_color_sku', 'limited_time_reduction_id', 'coupon_id', 'outbound_process', 'sku_class', 'is_include_next_day'], 'integer'],
            [['order_no', 'pay_amount', 'usd_pay_amount', 'cny_pay_amount', 'goods_price', 'usd_goods_price', 'cny_goods_price', 'market_price', 'usd_market_price', 'cny_market_price', 'total_goods_price', 'usd_total_goods_price', 'cny_total_goods_price', 'goods_discount', 'usd_goods_discount', 'cny_goods_discount', 'qty', 'spu_title', 'coupon_discount', 'usd_coupon_discount', 'cny_coupon_discount', 'activity_price', 'usd_activity_price', 'cny_activity_price', 'activity_discount', 'usd_activity_discount', 'cny_activity_discount', 'shipping_fee', 'usd_shipping_fee', 'cny_shipping_fee', 'rate_fee', 'usd_rate_fee', 'cny_rate_fee', 'customer_service_discount', 'usd_customer_service_discount', 'cny_customer_service_discount', 'payment_discount', 'usd_payment_discount', 'cny_payment_discount', 'cost_price', 'usd_cost_price', 'cny_cost_price', 'pay_amount_actual', 'usd_pay_amount_actual', 'cny_pay_amount_actual', 'value_added_service_amount', 'usd_value_added_service_amount', 'cny_value_added_service_amount', 'points_discount', 'usd_points_discount', 'cny_points_discount', 'suit_discount', 'usd_suit_discount', 'cny_suit_discount', 'protection_plan_amount', 'usd_protection_plan_amount', 'cny_protection_plan_amount', 'tag_price', 'usd_tag_price', 'cny_tag_price', 'profit_check_ratio', 'exchange_discount', 'usd_exchange_discount', 'cny_exchange_discount', 'promotion_ratio'
            ], 'required'
            ],
            [['pay_amount', 'usd_pay_amount', 'cny_pay_amount', 'goods_price', 'usd_goods_price', 'cny_goods_price', 'market_price', 'usd_market_price', 'cny_market_price', 'total_goods_price', 'usd_total_goods_price', 'cny_total_goods_price', 'goods_discount', 'usd_goods_discount', 'cny_goods_discount', 'coupon_discount', 'usd_coupon_discount', 'cny_coupon_discount', 'activity_price', 'usd_activity_price', 'cny_activity_price', 'activity_discount', 'usd_activity_discount', 'cny_activity_discount', 'shipping_fee', 'usd_shipping_fee', 'cny_shipping_fee', 'rate_fee', 'usd_rate_fee', 'cny_rate_fee', 'customer_service_discount', 'usd_customer_service_discount', 'cny_customer_service_discount', 'payment_discount', 'usd_payment_discount', 'cny_payment_discount', 'cost_price', 'usd_cost_price', 'cny_cost_price', 'pay_amount_actual', 'usd_pay_amount_actual', 'cny_pay_amount_actual', 'value_added_service_amount', 'usd_value_added_service_amount', 'cny_value_added_service_amount', 'points_discount', 'usd_points_discount', 'cny_points_discount', 'suit_discount', 'usd_suit_discount', 'cny_suit_discount', 'protection_plan_amount', 'usd_protection_plan_amount', 'cny_protection_plan_amount', 'tag_price', 'usd_tag_price', 'cny_tag_price', 'profit_check_ratio', 'exchange_discount', 'usd_exchange_discount', 'cny_exchange_discount', 'promotion_ratio', 'refund_rate_fee', 'store_manager_discount', 'usd_store_manager_discount', 'cny_store_manager_discount'
            ], 'number'
            ],
            [['spu_description', 'sale_attr_group', 'base_attr_group', 'guarantee_service', 'actual_cost_data'], 'string'],
            [['last_modify_time'], 'safe'],
            [['order_no', 'spu_code', 'ec_order_no', 'ec_warehouse_order_no', 'pick_up_order_no'], 'string', 'max' => 32],
            [['sku_code'], 'string', 'max' => 128],
            [['spu_title', 'img', 'coupon_group_name'], 'string', 'max' => 255],
            [['jump_url'], 'string', 'max' => 500],
            [['spu_brand_name', 'shipping_method', 'estimated_delivery_timestr'], 'string', 'max' => 20],
            [['spu_category_name_first', 'spu_category_name_second', 'spu_category_name_third', 'spu_category_name_fourth', 'ec_warehouse_name', 'pick_up_warehouse_code', 'pick_up_warehouse_name', 'tax_code'], 'string', 'max' => 50],
            [['estimated_arrival_at', 'activity_title'], 'string', 'max' => 200],
            [['goods_label', 'market_label', 'goods_warehouse_address'], 'string', 'max' => 1000],
            [['goods_eta_info'], 'string', 'max' => 5000],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'order_goods_id'                 => 'Order Goods ID',
            'order_goods_type'               => '订单商品类型 1-普通商品 2-建议补发商品 3-补发商品',
            'order_no'                       => '订单编码',
            'user_id'                        => '用户的id',
            'pay_amount'                     => '支付金额',
            'usd_pay_amount'                 => '支付金额（美元）',
            'cny_pay_amount'                 => '支付金额（人民币）',
            'goods_price'                    => '商品价格',
            'usd_goods_price'                => '商品价格（美元）',
            'cny_goods_price'                => '商品价格 （人民币）',
            'market_price'                   => '商品原价',
            'usd_market_price'               => '商品原价（美元）',
            'cny_market_price'               => '商品原价（人民币）',
            'total_goods_price'              => '商品总价',
            'usd_total_goods_price'          => '商品总价（美元）',
            'cny_total_goods_price'          => '商品总价（人民币）',
            'goods_discount'                 => '商品折扣价格：秒杀价 直接商品减价',
            'usd_goods_discount'             => '商品折扣价格（美元）',
            'cny_goods_discount'             => '商品折扣价格 (人民币)',
            'sku_id'                         => 'sku_id',
            'qty'                            => '购买数量，建议补发数量，补发数量',
            'sku_code'                       => 'sku_code',
            'spu_id'                         => 'spu_id',
            'spu_code'                       => 'spu_code',
            'spu_title'                      => '商品标题',
            'spu_title_resource_id'          => '商品资源id',
            'spu_description'                => '商品描述',
            'img'                            => '商品图片',
            'sale_attr_group'                => '商品售卖属性',
            'base_attr_group'                => '商品基础属性',
            'jump_url'                       => '商品跳转url',
            'spu_brand_name'                 => '品牌名称',
            'spu_category_name_first'        => '一级分类名称',
            'spu_category_name_second'       => '二级分类名称',
            'spu_category_name_third'        => '三级分类名称',
            'spu_category_name_fourth'       => '二级分类名称',
            'estimated_arrival_at'           => '预计到达时间',
            'coupon_user_id'                 => '优惠劵领取的id',
            'coupon_discount'                => '优惠券分摊金额',
            'usd_coupon_discount'            => '优惠券分摊金额（美元）',
            'cny_coupon_discount'            => '优惠券分摊金额（人民币）',
            'activity_type'                  => '活动类型',
            'activity_id'                    => '活动id',
            'activity_title'                 => '活动标题',
            'activity_price'                 => '活动金额',
            'usd_activity_price'             => '活动金额（美元）',
            'cny_activity_price'             => '活动金额（人民币）',
            'activity_discount'              => '活动的优惠金额',
            'usd_activity_discount'          => '活动的优惠金额（美元）',
            'cny_activity_discount'          => '活动的优惠金额（人民币）',
            'shipping_method'                => '货运方式',
            'shipping_fee'                   => '运费总额',
            'usd_shipping_fee'               => '运费总额（美元）',
            'cny_shipping_fee'               => '运费总额（人民币）',
            'rate_fee'                       => '税费',
            'usd_rate_fee'                   => '税费（美元）',
            'cny_rate_fee'                   => '税费（人民币）',
            'customer_service_discount'      => '客服的优惠金额',
            'usd_customer_service_discount'  => '客服的优惠金额（美元）',
            'cny_customer_service_discount'  => '客服的优惠金额（人民币）',
            'payment_discount'               => '支付优惠金额',
            'usd_payment_discount'           => '支付优惠金额(美元)',
            'cny_payment_discount'           => '支付优惠金额(人民币)',
            'is_paid'                        => '是否已支付 0-未支付 1-已支付',
            'paid_at'                        => '支付时间',
            'is_dispatched'                  => '是否已发货 0-未发货 1-已发货',
            'dispatched_at'                  => '订单发货时间',
            'is_received'                    => '是否已收货 0-未收货 1-已收货',
            'received_at'                    => '订单收货时间',
            'is_reviewed'                    => '是否评论 1-已经评论，0-未评论',
            'reviewed_at'                    => '订单评论时间',
            'is_refunded'                    => '是否已退款 0-未退款 1-部分退款 2-全部退款',
            'ec_order_no'                    => '易仓销售单号',
            'ec_warehouse_order_no'          => '易仓仓配订单号',
            'ec_warehouse_name'              => '发货仓库名称',
            'oversold_num'                   => '超卖数量',
            'created_at'                     => '创建时间',
            'updated_at'                     => '更新时间',
            'del_flag'                       => '删除标志 0正常 1删除',
            'dispatch_status'                => '发货状态 0 待发货 1 部分发货 2 全部发货',
            'last_modify_time'               => '最后更新时间',
            'guarantee_service'              => '当前商品享受的保障服务（json）',
            'estimated_delivery_timestr'     => '预计到达时间',
            'ec_status'                      => '易仓订单商品状态',
            'ec_warehouse_type'              => '易仓仓库类型，1海外仓 2广州仓',
            'delivery_track_type'            => '订单跟踪线路类型，1：广州仓发货，2：外仓发货-缺货，3：海运在途，4：外仓发货-现货',
            'cost_price'                     => '成本价格（pms价格）',
            'usd_cost_price'                 => '成本价格（美元）',
            'cny_cost_price'                 => '成本价格（人民币）',
            'is_exchange'                    => '0：未换货，1：换货中，2：换货成功（商品已取消）',
            'delivery_method_snapshoot'      => '配送方式 1-物流 2-自提 快照数据，存入后不再修改',
            'delivery_method'                => '配送方式 1-物流 2-自提',
            'pick_up_warehouse_code'         => '自提仓库code',
            'pick_up_order_no'               => '自提单号',
            'pick_up_time'                   => '自提时间开始时间',
            'pick_up_warehouse_name'         => '自提的仓库名称（中文）',
            'goods_label'                    => '商品标签',
            'market_label'                   => '活动标签',
            'sku_type'                       => 'SKU类型，0：非正常商品，1：正常商品',
            'pay_amount_actual'              => '实际支付金额',
            'usd_pay_amount_actual'          => '实际支付金额（美元）',
            'cny_pay_amount_actual'          => '实际支付金额（人民币）',
            'value_added_service_amount'     => '增值服务费用',
            'usd_value_added_service_amount' => '增值服务费用(美元)',
            'cny_value_added_service_amount' => '增值服务费用(人民币)',
            'eta_total_max_stamp'            => '最大到客时间',
            'eta_total_min_stamp'            => '最小到客时间',
            'goods_eta_info'                 => '商品eta信息，json',
            'rule_type'                      => '规则类型：0-普通商品 1- 库存售空或活动结束，商品自动下架; 2 -库存售空或活动结束，恢复零售价售卖',
            'delivery_channel'               => '1、卡车、2快递-可跨仓，3、快递-不可跨仓',
            'delivery_qty'                   => '发货数量',
            'is_suit'                        => '是否是套装 0-否 1-是',
            'suit_sku_id'                    => '套装sku_id',
            'points'                         => '商品抵扣对应的积分',
            'points_discount'                => '积分抵扣金额',
            'usd_points_discount'            => '积分抵扣金额(美元)',
            'cny_points_discount'            => '积分抵扣金额(人民币)',
            'receive_points'                 => '收货后领取的积分数',
            'suit_discount'                  => '套装折扣金额',
            'usd_suit_discount'              => '套装折扣金额(美元)',
            'cny_suit_discount'              => '套装折扣金额(人民币)',
            'protection_plan_amount'         => '保养服务费用',
            'usd_protection_plan_amount'     => '保养服务费用(美元)',
            'cny_protection_plan_amount'     => '保养服务费用(人民币)',
            'tag_price'                      => '商品划线价(单价)',
            'usd_tag_price'                  => '商品划线价(美元)',
            'cny_tag_price'                  => '商品划线价(人民币)',
            'tax_code'                       => 'avaTax code',
            'profit_check_ratio'             => '利润核算系数',
            'exchange_discount'              => '换货优惠金额',
            'usd_exchange_discount'          => '换货优惠金额（美元）',
            'cny_exchange_discount'          => '换货优惠金额（人民币）',
            'goods_warehouse_address'        => '商品eta的仓库地址信息',
            'is_color_sku'                   => '是否小样 1-是 0-否',
            'limited_time_reduction_id'      => 'Limited Time Reduction ID',
            'promotion_ratio'                => '推广占比',
            'actual_cost_data'               => '实际成本',
            'coupon_id'                      => '优惠劵id',
            'coupon_group_name'              => '优惠券券组名称',
            'outbound_process'               => '门店自提出库流程，1：普通出库，2：门店直出',
            'refund_rate_fee'                => '换货退税费',
            'sku_class'                      => 'sku类型，1：销售sku，2：仓库sku，3：零部件sku',
            'store_manager_discount'         => '店长优惠(当前币种)',
            'usd_store_manager_discount'     => '店长优惠（美元）',
            'cny_store_manager_discount'     => '店长优惠（人民币）',
            'is_include_next_day'            => '是否为次日达服务，1：是，0：否',
        ];
    }
}
