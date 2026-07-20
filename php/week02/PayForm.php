<?php

namespace AppPayApi\forms;

use common\BaseForm;

class PayForm extends BaseForm
{
    private $ruleArray = [];

    public function rules()
    {
        return $this->ruleArray;
    }

    public $amount;
    public $base_amount;
    public $currency;
    public $order_to_base_rate;
    public $payment_method;
    public $payment_methods;
    public $business_type;
    public $business_no;
    public $inner_notify_url;
    public $title;
    public $desc;
    public $return_url;
    public $comment;
    public $payment_no;
    public $refund_no;
    public $nonce;
    public $checkout_token;
    public $device_data;

    /**
     * @param array $params
     *
     * @return bool
     */
    public function payCreateValidate($params = [])
    {
        $this->ruleArray = [
            [['amount'], 'required','message'=>'Price cannot be empty'],
            ['base_amount', 'required'],
            ['currency', 'required'],
            ['order_to_base_rate', 'required'],
            ['business_type', 'required'],
            ['business_no', 'required'],
            ['inner_notify_url', 'required'],
            ['title', 'required'],
            ['desc', 'required'],
            ['return_url', 'required'],
            ['comment', 'required'],
        ];


        $this->setAttributes($params);
        return $this->validate();
    }

    public function paymentMethodsValidate($params = []) {
        $this->ruleArray = [
            ['payment_no', 'required','message'=>'700001'],
        ];

        $this->setAttributes($params);
        return $this->validate();
    }

    public function paymentParamsValidate($params = []) {
        $this->ruleArray = [
            ['payment_no', 'required', 'message' => '700001'],
            ['payment_method', 'required', 'message' => '700007'],
        ];

        $this->setAttributes($params);
        return $this->validate();
    }

    public function affirmConfirmValidate($params = []) {
        $this->ruleArray = [
            ['payment_no', 'required','message'=>'700001'],
            ['checkout_token', 'required','message'=>'700008'],
        ];

        $this->setAttributes($params);
        return $this->validate();
    }

    public function braintreeConfirmValidate($params = [])
    {
        $this->ruleArray = [
            ['payment_no', 'required', 'message' => '700001'],
            ['payment_method', 'required', 'message' => '700007'],
            ['nonce', 'required', 'message' => '700009'],
            ['device_data', 'required', 'message' => '700010'],
        ];
        $this->setAttributes($params);
        return $this->validate();
    }

    public function bankConfirmValidate($params = []) {
        $this->ruleArray = [
            ['payment_no', 'required','message'=>'700001'],
            ['payment_method', 'required','message'=>'700007'],
        ];

        $this->setAttributes($params);
        return $this->validate();
    }

    public function  payStatusValidate($params = []) {
        $this->ruleArray = [
            //['payment_no', 'required','message'=>'700001'],
        ];

        $this->setAttributes($params);
        return $this->validate();
    }

}
