<?php
/**
 *
 * @author  白杨
 * @Date    2021/1/20 上午11:52
 * @package fecshop\app\frontapi\modules\Order\controllers
 */

namespace fecshop\app\frontapi\modules\Order\controllers;

use fecshop\app\frontapi\modules\AuthApiController;
use fecshop\services\http\NewUserRequest;
use fecshop\services\http\AfterSaleRequest;
use fecshop\services\http\OrderRequest;
use Yii;

class OrderController extends AuthApiController
{
    /**
     * 其他商品校验
     *
     * @author 白杨
     * @Date   2021/3/9 上午11:40
     */
    public function actionGoodsListCheck()
    {
        $params = Yii::$app->request->post();
        $result = OrderRequest::instance()->goodsListCheck($params);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data'], $result['info']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 购物车商品校验
     */
    public function actionCartGoodsCheck()
    {
        $params = Yii::$app->request->post();
        $result = OrderRequest::instance()->cartGoodsCheck($params);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data'], $result['info']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 订单确认页
     *
     * @author 白杨
     * @Date   2021/3/9 上午11:38
     */
    public function actionConfirm()
    {
        return $this->endFail(404, 'Abandoned');
        $params = Yii::$app->request->post();
        $result = OrderRequest::instance()->orderConfirm($params);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 下单
     *
     * @author 白杨
     * @Date   2021/3/9 上午11:38
     */
    public function actionPlace()
    {
        return $this->endFail(404, 'Abandoned');
        $params = Yii::$app->request->post();
        $result = OrderRequest::instance()->orderPlace($params);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 订单列表
     *
     * @author 白杨
     * @Date   2021/3/9 上午11:38
     */
    public function actionList()
    {
        $params = $this->getParams();
        $result = OrderRequest::instance()->orderList($params);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 订单详情
     *
     * @author 白杨
     * @Date   2021/3/9 上午11:38
     */
    public function actionDetail()
    {
        $params = $this->getParams();
        $result = OrderRequest::instance()->orderDetail($params);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 取消订单-整单取消
     */
    public function actionCancel()
    {
        $params = $this->getParams();
        $header = $this->getHeaders();
        $result = OrderRequest::instance()->cancelOrder($params, $header);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 订单收货--整单收货
     */
    public function actionOrderReceive()
    {
        $params = $this->getParams();
        $header = $this->getHeaders();
        $result = OrderRequest::instance()->orderReceive($params, $header);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 修改订单用户收货地址
     */
    public function actionModifyAddress()
    {
        $params = $this->getParams();
        $header = $this->getHeaders();
        $result = OrderRequest::instance()->modifyAddress($params, $header);
        if (1 == $result['code']) {
            $this->endSuccess($result['data']);
        } else {
            $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 退款
     *
     * @author 白杨
     * @Date   2021/3/26 下午8:27
     */
    public function actionOrderRefund()
    {
        $params = $this->getParams();
        $result = OrderRequest::instance()->orderRefund($params);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 修改账单地址
     */
    public function actionUpdateBillAddress()
    {
        $header       = $this->getHeaders();
        $params       = $this->getParams();
        $orderRequest = new OrderRequest();
        $result       = $orderRequest->updateBillAddress($params, $header);
        if ($result['code'] != 1) {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
        return $this->endSuccess($result['data']);
    }

    /**
     * 获取订单地址
     */
    public function actionOrderBillAddressDetail()
    {
        $header       = $this->getHeaders();
        $params       = $this->getParams();
        $orderRequest = new OrderRequest();
        $result       = $orderRequest->orderBillAddressDetail($params, $header);
        if ($result['code'] != 1) {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
        return $this->endSuccess($result['data']);
    }

    /**
     * 获取订单地址
     */
    public function actionOrderAddressDetail()
    {
        $header       = $this->getHeaders();
        $params       = $this->getParams();
        $orderRequest = new OrderRequest();
        $result       = $orderRequest->orderAddressDetail($params, $header);
        if ($result['code'] != 1) {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
        return $this->endSuccess($result['data']);
    }


    /**
     * 获取订单地址
     */
    public function actionOldOrderUrl()
    {
        $header       = $this->getHeaders();
        $params       = $this->getParams();
        $orderRequest = new OrderRequest();
        $result       = $orderRequest->oldOrderUrl($params, $header);
        if ($result['code'] != 1) {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
        return $this->endSuccess($result['data']);
    }

    public function actionGaReportedResult()
    {
        $header       = $this->getHeaders();
        $params       = $this->getParams();
        $orderRequest = new OrderRequest();
        $result       = $orderRequest->gaReportedResult($params, $header);
        if ($result['code'] != 1) {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
        return $this->endSuccess($result['data']);
    }

    /**
     * 修改取货方式
     *
     * @author 白杨
     * @Date   2021/6/1 下午3:52
     */
    public function actionUpdateLocationType()
    {
        $header       = $this->getHeaders();
        $params       = $this->getParams();
        $orderRequest = new OrderRequest();
        $result       = $orderRequest->updateLocationType($params, $header);
        if ($result['code'] != 1) {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
        return $this->endSuccess($result['data']);
    }

    /**
     * 获取订单支付状态
     *
     * @author liangchupeng
     * @since  2021.07.08
     */
    public function actionGetPayStatus()
    {
        $params = Yii::$app->request->post();
        $result = OrderRequest::instance()->getPayStatus($params);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data'], $result['info']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 获取交易快照
     *
     * @author liangchupeng
     * @since  2021.08.23
     */
    public function actionGetTransSnapshot()
    {
        $params = Yii::$app->request->post();
        $result = OrderRequest::instance()->getTransSnapshot($params);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data'], $result['info']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 获取订单商品跟踪信息
     *
     * @author liangchupeng
     * @since  2021.09.16
     */
    public function actionGetOrderGoodsTrackInfo()
    {
        $params = Yii::$app->request->post();
        $result = OrderRequest::instance()->getOrderGoodsTrackInfo($params);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data'], $result['info']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 获取订单和订单商品整体的跟踪信息
     *
     * @author liangchupeng
     * @since  2021.09.17
     */
    public function actionGetOrderDetailTrackInfo()
    {
        $params = Yii::$app->request->post();
        $result = OrderRequest::instance()->getOrderDetailTrackInfo($params);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data'], $result['info']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 获取订单详情的推荐商品
     *
     * @author liangchupeng
     * @since  2021.09.17
     */
    public function actionGetOrderRecommendGoods()
    {
        $params = Yii::$app->request->post();
        $result = OrderRequest::instance()->getOrderRecommendGoods($params);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data'], $result['info']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 获取订单详情的服务条款
     *
     * @author liangchupeng
     * @since  2021.09.17
     */
    public function actionGetOrderServiceGuarantee()
    {
        $params = Yii::$app->request->post();
        $result = OrderRequest::instance()->getOrderServiceGuarantee($params);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data'], $result['info']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 统一获取服务条款
     *
     * @author liangchupeng
     * @since  2021.10.13
     */
    public function actionGetServiceGuarantee()
    {
        $params = Yii::$app->request->post();
        $result = OrderRequest::instance()->getServiceGuarantee($params);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data'], $result['info']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 添加自提信息
     *
     * @throws \yii\base\ExitException
     * @author 白杨
     * @Date   23/11/21 下午1:36
     */
    public function actionAddPickUpAddress()
    {
        $header = $this->getHeaders();
        $params = $this->getParams();
        $type   = $params['type'] ?? 1;
        if ($type == 2) {
            $result = NewUserRequest::instance()->subscribe($params, $header);
        } else {
            $result = OrderRequest::instance()->addPickUpAddress($params, $header);
        }
        if (1 == $result['code']) {
            $data = empty($result['data']) ? null : $result['data'];
            $this->endSuccess($data, $result['info']);
        } else {
            $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 获取订单商品aftership信息
     *
     * @author liangchupeng
     * @since  2022.01.14
     */
    public function actionGetOrderGoodsAftershipInfo()
    {
        $params = Yii::$app->request->post();
        $result = OrderRequest::instance()->getOrderGoodsAftershipInfo($params, $this->getHeaders());
        if (1 == $result['code']) {
            return $this->endSuccess($result['data'], $result['info']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data']);
        }
    }


    public function actionForAfterSaleToUser()
    {
        $params = $this->getParams();
        $result = OrderRequest::instance()->forAfterSaleToUser($params, $this->getHeaders());
        if (1 == $result['code']) {
            $this->endSuccess($result['data'], $result['info']);
        } else {
            $this->endFail($result['code'], $result['info'], $result['data']);
        }
    }

    public function actionGetShipAndBillAddress()
    {
        $params = Yii::$app->request->get();
        $result = OrderRequest::instance()->getShipAndBillAddress($params, $this->getHeaders());
        if (1 == $result['code']) {
            $this->endSuccess($result['data'], $result['info']);
        } else {
            $this->endFail($result['code'], $result['info'], $result['data']);
        }
    }

    public function actionRepeat()
    {
        $params = $this->getParams();
        $result = OrderRequest::instance()->repeat($params, $this->getHeaders());
        if (1 == $result['code']) {
            $data = empty($result['data']) ? null : $result['data'];
            $this->endSuccess($data, $result['info']);
        } else {
            $this->endFail($result['code'], $result['info'], $result['data']);
        }
    }

    public function actionGetInvoice()
    {
        $params = $this->getParams();
        $result = OrderRequest::instance()->getInvoice($params, $this->getHeaders());
        if (1 == $result['code']) {
            $data = empty($result['data']) ? null : $result['data'];
            $this->endSuccess($data, $result['info']);
        } else {
            $this->endFail($result['code'], $result['info'], $result['data']);
        }
    }

    public function actionGetList()
    {
        $params = $this->getParams();
        $result = OrderRequest::instance()->getOrderList($params, $this->getHeaders());
        if (1 == $result['code']) {
            $data = empty($result['data']) ? null : $result['data'];
            $this->endSuccess($data, $result['info']);
        } else {
            $this->endFail($result['code'], $result['info'], $result['data']);
        }
    }

    public function actionGetOrderBanner()
    {
        $params = $this->getParams();
        $result = OrderRequest::instance()->getOrderBanner($params, $this->getHeaders());
        if (1 == $result['code']) {
            $data = empty($result['data']) ? null : $result['data'];
            $this->endSuccess($data, $result['info']);
        } else {
            $this->endFail($result['code'], $result['info'], $result['data']);
        }
    }

    public function actionGetInfoSecurity()
    {
        $params = $this->getParams();
        $result = OrderRequest::instance()->getInfoSecurity($params, $this->getHeaders());
        if (1 == $result['code']) {
            $data = empty($result['data']) ? null : $result['data'];
            $this->endSuccess($data, $result['info']);
        } else {
            $this->endFail($result['code'], $result['info'], $result['data']);
        }
    }

    public function actionGetOrderGoodsCardList()
    {
        $params = $this->getParams();
        $result = OrderRequest::instance()->getOrderGoodsCardList($params, $this->getHeaders());
        if (1 == $result['code']) {
            $data = empty($result['data']) ? [] : $result['data'];
            $this->endSuccess($data, $result['info']);
        } else {
            $this->endFail($result['code'], $result['info'], $result['data']);
        }
    }

    public function actionGetThisItemHelp()
    {
        $params = $this->getParams();
        $result = OrderRequest::instance()->getThisItemHelp($params, $this->getHeaders());
        if (1 == $result['code']) {
            $data = empty($result['data']) ? null : $result['data'];
            $this->endSuccess($data, $result['info']);
        } else {
            $this->endFail($result['code'], $result['info'], $result['data']);
        }
    }

    /**
     * 订单异常检测（订单信息是否发生改变/重复订单）
     *
     * @return void
     */
    public function actionOrderAnomalyDetect()
    {
        $params = $this->getParams();
        $result = OrderRequest::instance()->orderAnomalyDetect($params, $this->getHeaders());
        if (1 == $result['code']) {
            $data = empty($result['data']) ? null : $result['data'];
            $this->endSuccess($data, $result['info']);
        } else {
            $this->endFail($result['code'], $result['info'], $result['data']);
        }
    }


    /**
     * 获取订单信息（快牛用的）
     * @return null
     * @throws \yii\base\ExitException
     */
    public function actionGetOrderInfo()
    {
        $params = $this->getParams();
        $result = OrderRequest::instance()->getOrderInfo($params, $this->getHeaders());
        if ($result['code'] != 1) {
            return $this->endFail($result['code'], $result['info'], $result['data']);
        }
        $data = empty($result['data']) ? null : $result['data'];
        return $this->endSuccess($data, $result['info']);
    }

    /**
     * 门店下单-信息确认
     *
     * @return null
     * @throws \yii\base\ExitException
     */
    public function actionStoreTradeConfirm()
    {
        $params               = Yii::$app->request->post();
        $params['ip_address'] = $this->JPGetRealIp();
        $result               = OrderRequest::instance()->orderStoreTradeConfirm($params);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 门店下单
     *
     * @return null
     * @throws \yii\base\ExitException
     */
    public function actionStoreTradePlace()
    {
        $params               = Yii::$app->request->post();
        $params['ip_address'] = $this->JPGetRealIp();
        $result               = OrderRequest::instance()->orderStoreTradePlace($params);
        if (1 == $result['code']) {
            return $this->endSuccess($result['data']);
        } else {
            return $this->endFail($result['code'], $result['info'], $result['data'], $result['error'] ?? '');
        }
    }

    /**
     * 获取用户的真实ip,仅适用于cgi运行方式，cli运行方式请不要使用
     *
     * @return string IP十五位字符，
     */
    private function JPGetRealIp()
    {
        $ip = getenv("HTTP_X_FORWARDED_FOR");
        if (empty($ip)) {
            $ip = getenv("REMOTE_ADDR");
        }

        $ip = explode(',', $ip);
        return trim($ip[0]) ?? '';
    }
}
