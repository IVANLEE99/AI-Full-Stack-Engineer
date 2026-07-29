<?php

namespace common\models\order;

use Yii;

/**
 * This is the model class for table "order_address".
 *
 * @property int $order_address_id
 * @property string $order_no 订单号
 * @property int $user_id 用户的id
 * @property int $address_id 用户的地址id
 * @property string $email 客户邮箱
 * @property string $company 公司
 * @property string $first_name 名字1
 * @property string $last_name 名字2
 * @property string $phone 电话
 * @property string $fax 传真
 * @property int $country_id 表country_area.id 国家id
 * @property string $country_code 国家码 country的iso2_code
 * @property string $country 国家
 * @property int $state_id 表country_area.id 一级行政单位id'
 * @property string $state_code 州/省码 region的code
 * @property string $state 州/省
 * @property string $city 城市/市
 * @property string $area 乡/镇
 * @property string $zip 邮编
 * @property string $street1 街道1
 * @property string $street2 街道2
 * @property int $created_at 创建时间
 * @property int $updated_at 更新时间
 * @property int $del_flag 删除标志 0正常 1删除
 * @property string $phone_code 国家电话区号
 * @property string $international_phone 国际格式电话号码
 */
class OrderAddress extends \common\BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'order_address';
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
            [['order_no'], 'required'],
            [['user_id', 'address_id', 'country_id', 'state_id', 'created_at', 'updated_at', 'del_flag'], 'integer'],
            [['order_no', 'phone'], 'string', 'max' => 32],
            [['email'], 'string', 'max' => 90],
            [['company'], 'string', 'max' => 255],
            [['first_name', 'last_name', 'fax', 'country', 'state_code', 'state', 'city', 'area'], 'string', 'max' => 50],
            [['country_code'], 'string', 'max' => 2],
            [['zip'], 'string', 'max' => 20],
            [['street1', 'street2'], 'string', 'max' => 200],
            [['phone_code'], 'string', 'max' => 16],
            [['international_phone'], 'string', 'max' => 64],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'order_address_id' => 'Order Address ID',
            'order_no' => 'Order No',
            'user_id' => 'User ID',
            'address_id' => 'Address ID',
            'email' => 'Email',
            'company' => 'Company',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'phone' => 'Phone',
            'fax' => 'Fax',
            'country_id' => 'Country ID',
            'country_code' => 'Country Code',
            'country' => 'Country',
            'state_id' => 'State ID',
            'state_code' => 'State Code',
            'state' => 'State',
            'city' => 'City',
            'area' => 'Area',
            'zip' => 'Zip',
            'street1' => 'Street1',
            'street2' => 'Street2',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'del_flag' => 'Del Flag',
            'phone_code' => 'Phone Code',
            'international_phone' => 'International Phone',
        ];
    }
}
