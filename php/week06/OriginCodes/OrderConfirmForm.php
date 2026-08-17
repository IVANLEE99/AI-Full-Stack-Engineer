<?php
/**
 * 订单确认页表单
 *
 * @author  白杨
 * @Date    2021/3/13 上午11:14
 * @package AppOrderApi\forms
 */

namespace AppOrderApi\forms;


class OrderConfirmForm extends \common\BaseForm
{
    private $ruleArray = [];

    public function rules()
    {
        return array_merge($this->commonRules, $this->ruleArray);
    }

    public $eid;
    public $token;

    public $address_id;
    public $goods_list;
    public $coupon_user_id;
    public $commit;
    public $source;//进入来源：1-直接购买，2-购物车 3-个人中心
    public $order_no;
    public $payment_method;
    public $location_type;

    public function orderGoodsListCheckValidate($params)
    {
        $this->ruleArray = [
            [['eid', 'goods_list'], 'required'],
        ];

        $this->setAttributes($params);

        return $this->validate();
    }

    /**
     * 游客购买必须校验的参数
     *
     * @param $params
     *
     * @return bool
     */
    public function touristOrderGoodsListCheckValidate($params)
    {
        $this->ruleArray = [
            [['eid', 'goods_list'], 'required'],
        ];

        $this->setAttributes($params);

        return $this->validate();
    }

    /**
     * 订单确认页
     *
     * @param array $params
     *
     * @return bool
     */
    public function orderConfirmValidate($params = [])
    {
        $this->ruleArray = [
            [['eid', 'token', 'source'], 'required'],
            ['pf', 'in', 'range' => ['pc', 'm', 'android', 'ios']],
            ['address_id', 'integer'],
            ['source', 'in', 'range' => [1, 2, 3, 4]],
            ['location_type', 'integer', 'min' => 1, 'max' => 5],
            ['coupon_user_id', 'integer'],
            ['commit', 'string', 'length' => [0, 400]],
        ];

        $this->setAttributes($params);

        return $this->validate();
    }

    public function tradeConfirmValidate($params)
    {
        $this->ruleArray = [
            [['eid', 'source'], 'required'],
            ['pf', 'in', 'range' => ['pc', 'm', 'android', 'ios']],
            ['address_id', 'integer'],
            ['source', 'in', 'range' => [1, 2, 3, 4]],
            ['coupon_user_id', 'integer'],
            ['commit', 'string', 'length' => [0, 100]],
            ['payment_method', 'string', 'max' => 50],
            ['order_no', 'string', 'max' => 32],
        ];

        $this->setAttributes($params);

        return $this->validate();
    }

    public function touristTradeConfirmValidate($params)
    {
        $this->ruleArray = [
            [['eid', 'source'], 'required'],
            ['pf', 'in', 'range' => ['pc', 'm', 'android', 'ios']],
            ['address_id', 'integer'],
            ['source', 'in', 'range' => [1, 2, 3, 4]],
            ['coupon_user_id', 'integer'],
            ['commit', 'string', 'length' => [0, 100]],
            ['payment_method', 'string', 'max' => 50],
            ['order_no', 'string', 'max' => 32],
        ];

        $this->setAttributes($params);

        return $this->validate();
    }
}
