<?php

namespace fecshop\services\http;

use common\BaseApi;

class OrderRequest extends BaseApi
{
    /**
     * 获取api域名地址
     *
     * @return string
     */
    protected function getBaseUrl()
    {
        return 'http://order.internal.bm.com/';
    }

    public function getOrderListDownloadUrl()
    {
        return $this->getBaseUrl() . "order-query/export-order-list";
    }

    public function getBackAddressListDownloadUrl()
    {
        return $this->getBaseUrl() . "risk/back-address-list";
    }

    public function getOrderGoodsListDownloadUrl()
    {
        return $this->getBaseUrl() . "order-query/order-goods-list";
    }

    public function getAfterSaleDownloadUrl()
    {
        return $this->getBaseUrl() . "after-sale-query/page-list";
    }

    /**
     * 售后原因下载地址
     */
    public function getAfterSaleReasonDownloadUrl()
    {
        return $this->getBaseUrl() . "after-sale-query/export-all-reason-list";
    }


    public function test()
    {
        $data   = [];// 参数
        $header = [];// 请求头
        // 请求配置
        $options = [
            CURLOPT_TIMEOUT => 10,// 超时时间
        ];

        return $this->post('site/index', $data, $header, $options);
    }

    /**
     * @param array $params
     *
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function getAfterSaleOrderPageList($params = [], $header = [])
    {
        return $this->post('after-sale-query/page-list', $params, $header);
    }

    /**
     * 购物车列表
     *
     * @param $params
     *
     * @return array|bool|mixed
     * @author 白杨
     * @Date   2021/1/20 上午11:08
     */
    public function cartList($params, $header = [])
    {
        $params['ip'] = $this->JPGetRealIp();
        return $this->get('cart/cart-list', $params, $header);
    }

    public function getYouMayAlsoNeedGoods($params, $header = [])
    {
        $params['ip'] = $this->JPGetRealIp();
        return $this->get('cart-new/may-also-need-goods', $params, $header);
    }

    /**
     * 购物车添加
     *
     * @param $params
     *
     * @return array|bool|mixed
     * @author 白杨
     * @Date   2021/1/20 上午11:08
     */
    public function cartAdd($params, $header = [])
    {
        $params['ip'] = $this->JPGetRealIp();
        return $this->post('cart/add', $params, $header);
    }

    /**
     * 批量加购
     *
     * @param       $params
     * @param array $header
     *
     * @return array|bool|mixed
     * @author 白杨
     * @Date   23/6/21 下午4:29
     */
    public function cartBatchAdd($params, $header = [])
    {
        return $this->post('cart/batch-add', $params, $header);
    }

    /**
     * 购物车单个商品修改
     *
     * @param $params
     *
     * @return array|bool|mixed
     * @author 白杨
     * @Date   2021/1/20 上午11:08
     */
    public function cartChange($params, $header = [])
    {
        return $this->post('cart/change', $params, $header);
    }

    public function getServiceCartGuarantee($params, $header = [])
    {
        return $this->post('cart-new/get-service-cart-guarantee', $params, $header);
    }

    public function getInfoSecurity($params, $header = [])
    {
        return $this->post('order-v4/get-info-security', $params, $header);
    }

    /**
     * 全选，取消选中
     *
     * @param $params
     *
     * @return array|bool|mixed
     * @author 白杨
     * @Date   2021/1/20 上午11:16
     */
    public function cartSelectedall($params, $header = [])
    {
        $params['ip'] = $this->JPGetRealIp();
        return $this->post('cart/selectedall', $params, $header);
    }

    /**
     * 购物车批量删除
     *
     * @param $params
     *
     * @return array|bool|mixed
     * @author 白杨
     * @Date   2021/3/1 下午8:54
     */
    public function cartBatchDelete($params, $header = [])
    {
        return $this->post('cart/batch-delete', $params, $header);
    }

    public function cartGetItemNums($params, $header = [])
    {
        $params['ip'] = $this->JPGetRealIp();
        return $this->get('cart/get-item-nums', $params, $header);
    }

    public function getCartSkuId($params, $header = [])
    {
        return $this->get('cart/get-cart-sku-id', $params, $header);
    }

    //下面两个方法合并成一个。前端调用哪个待确认
    public function goodsListCheck($params, $header = [])
    {
        return $this->post('order/goods-list-check', $params, $header);
    }

    public function cartGoodsCheck($params)
    {
        return $this->post('order/goods-list-check', $params);
    }

    public function orderConfirm($params, $header = [])
    {
        return $this->post('order/confirmorder', $params, $header);
    }

    public function getPayStatus($params, $header = [])
    {
        return $this->post('order/get-pay-status', $params, $header);
    }

    /**
     * 获取交易快照
     *
     * @param       $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function getTransSnapshot($params, $header = [])
    {
        return $this->post('order/get-trans-snapshot', $params, $header);
    }

    /**
     * 获取订单商品跟踪信息
     *
     * @param       $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function getOrderGoodsTrackInfo($params, $header = [])
    {
        return $this->post('order/get-order-goods-track-info', $params, $header);
    }

    /**
     * 获取oms侧订单商品跟踪信息
     *
     * @param       $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function getOrderGoodsAllTrackInfo($params, $header = [])
    {
        return $this->post('order-query/get-order-goods-all-track-info', $params, $header);
    }

    /**
     * 获取aftership信息
     *
     * @param       $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function getOrderGoodsAftershipInfo($params, $header = [])
    {
        return $this->post('order-query/get-order-goods-aftership-info', $params, $header);
    }

    /**
     * 获取订单跟踪信息
     *
     * @param       $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function getOrderTrackInfo($params, $header = [])
    {
        return $this->post('order-query/get-order-track-info', $params, $header);
    }

    /**
     * 获取订单和订单商品整体的跟踪信息
     *
     * @param       $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function getOrderDetailTrackInfo($params, $header = [])
    {
        return $this->post('order/get-order-detail-track-info', $params, $header);
    }

    /**
     * 获取订单和订单商品整体的跟踪信息
     *
     * @param       $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function getOrderRecommendGoods($params, $header = [])
    {
        return $this->post('order/get-order-recommend-goods', $params, $header);
    }

    /**
     * 获取订单详情的服务条款
     *
     * @param       $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function getOrderServiceGuarantee($params, $header = [])
    {
        return $this->post('order/get-order-service-guarantee', $params, $header);
    }

    /**
     * 统一获取服务条款
     *
     * @param       $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function getServiceGuarantee($params, $header = [])
    {
        return $this->post('order/get-service-guarantee', $params, $header);
    }

    public function oversoldCanSell($params, $header = [])
    {
        return $this->post('order/oversold-can-sell', $params, $header);
    }

    /**
     * 交易确认页
     *
     * @param $params
     *
     * @return array|bool|mixed
     * @author 白杨
     * @Date   2021/4/19 下午4:42
     */
    public function confirmNewInfo($params, $header = [])
    {
        $params['ip'] = $this->JPGetRealIp();
        return $this->post('confirm/new-info', $params, $header);
    }

    public function changeValueAdded($params, $header = [])
    {
        return $this->post('confirm/change-value-added', $params, $header);
    }

    public function tradeConfirm($params, $header = [])
    {
        $params['ip'] = $this->JPGetRealIp();
        return $this->post('order/trade-confirm', $params, $header);
    }

    /**
     * 交易下单，更新订单接口
     *
     * @param $params
     *
     * @return array|bool|mixed
     * @author 白杨
     * @Date   2021/4/19 下午4:48
     */
    public function tradePlace($params, $header = [])
    {
        return $this->post('order/trade-place', $params, $header);
    }

    /**
     * 审核售后订单
     */
    public function afterSaleAudit($params = [], $header = [])
    {
        return $this->post('after-sale/after-sale-audit', $params, $header);
    }

    /**
     * 客服售后订单物流信息
     */
    public function serviceAddAfterSaleDelivery($params, $header = [])
    {
        return $this->post('after-sale/add-after-sale-delivery', $params, $header, []);
    }

    // 售后详情
    public function afterSaleInfo($params = [], $header = [])
    {
        return $this->post('after-sale-query/info', $params, $header);
    }

    /**
     * 申请售后订单
     */
    public function afterSaleApply($params = [], $header = [])
    {
        return $this->post('after-sale/apply-after-sale', $params, $header, []);
    }

    /**
     * 申请售后订单
     */
    public function getExchangeInfo($params = [], $header = [])
    {
        return $this->post('after-exchange/exchange-info', $params, $header, []);
    }

    // 售后详情
    public function afterSaleCancel($params)
    {
        return $this->post('after-sale/after-sale-audit', $params);
    }

    //用户添加售后物流信息
    public function addAfterSaleDelivery($params)
    {
        return $this->post('after-sale/user-add-delivery', $params);
    }

    /**
     * 售后补发订单列表
     *
     * @param array $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function reissueList($params = [], $header = [])
    {
        return $this->get('order-query/order-reissue-list', $params, $header, []);
    }

    /**
     * 售后补发订单详情
     *
     * @param array $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function reissueDetail($params = [], $header = [])
    {
        return $this->get('order-query/order-reissue-detail', $params, $header, []);
    }

    /**
     * 申请售后补发订单
     *
     * @param array $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function reissue($params = [], $header = [])
    {
        return $this->post('order/reissue-order', $params, $header, []);
    }

    /**
     * 申请售后的订单商品详情页
     *
     * @param array $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function afterSaleOrderGoodsInfo($params = [], $header = [])
    {
        return $this->post('after-sale-query/order-goods-info', $params, $header, []);
    }

    public function orderPlace($params, $header = [])
    {
        return $this->post('order/placeorder', $params, $header);
    }

    /**
     * 门店下单-信息确认
     *
     * @param $params
     * @param $header
     * @return array|bool|mixed
     */
    public function orderStoreTradeConfirm($params, $header = [])
    {
        return $this->post('storeapi/order/trade-confirm', $params, $header);
    }

    /**
     * 门店下单
     *
     * @param $params
     * @param $header
     * @return array|bool|mixed
     */
    public function orderStoreTradePlace($params, $header = [])
    {
        return $this->post('storeapi/order/trade-place', $params, $header);
    }

    public function orderList($params)
    {
        return $this->get('order-query/order-list', $params, [], []);
    }

    public function orderGoodsList($params)
    {
        return $this->get('order-query/order-goods-list', $params, [], []);
    }

    public function exportOrderList($params)
    {
        return $this->get('order-query/export-order-list', $params, [], []);
    }

    public function orderDetail($params)
    {
        return $this->get('order-query/order-detail', $params, [], []);
    }

    /**
     * 代客下单
     *
     * @param $params
     * @param $header
     *
     * @return array|bool|mixed
     */
    public function syncInfo($params, $header)
    {
        return $this->post('order/sync-info', $params, $header, []);
    }

    /**
     * 代客下单
     *
     * @param $params
     * @param $header
     *
     * @return array|bool|mixed
     */
    public function valetOrder($params, $header)
    {
        return $this->post('order/valetorder', $params, $header, []);
    }

    /**
     * 订单配置信息
     *
     * @param $params
     * @param $header
     *
     * @return array|bool|mixed
     */
    public function orderLocationTypeList($params, $header)
    {
        return $this->post('order/location-type-list', $params, $header, []);
    }

    /**
     * 取消订单
     *
     * @param $params
     * @param $header
     *
     * @return array|bool|mixed
     */
    public function cancelOrder($params, $header)
    {
        return $this->post('order/order-cancel', $params, $header, []);
    }

    /**
     * 订单收货--整单收货
     */
    public function orderReceive($params, $header)
    {
        return $this->post('order/order-receive', $params, $header, []);
    }

    /**
     * 验证是否可以申请售后
     */
    public function verifyApply($params, $header)
    {
        return $this->post('after-sale/can-apply', $params, $header, []);
    }

    /**
     * 修改订单收货地址
     */
    public function modifyAddress($params, $header = [])
    {
        return $this->post('order/modify-address', $params, $header, []);
    }

    /**
     * 修改订单自提联系方式
     */
    public function modifyPickAddress($params, $header = [])
    {
        return $this->post('order/modify-pick-address', $params, $header, []);
    }

    /**
     * 修改订单自提仓库
     */
    public function modifyWarehouse($params, $header = [])
    {
        return $this->post('order/modify-warehouse', $params, $header, []);
    }

    /**
     * 修改订单配送方式
     */
    public function modifyDelivery($params, $header = [])
    {
        return $this->post('order/modify-delivery', $params, $header, []);
    }

    /**
     * 修改订单自提仓库
     */
    public function modifyWarehouseTime($params, $header = [])
    {
        return $this->post('order/modify-warehouse-time', $params, $header, []);
    }

    /**
     * 全部仓库地址
     */
    public function warehouseAll($params, $header)
    {
        return $this->post('after-sale-query/warehouse-all-list', $params, $header, []);
    }

    /**
     * 退款
     *
     * @param $params
     *
     * @return array|bool|mixed
     * @author 白杨
     * @Date   2021/3/26 下午8:27
     */
    public function orderRefund($params)
    {
        return $this->post('order/order-refund', $params);
    }

    /**
     * 验证是否可以申请售后
     */
    public function checkApplyAfterSale($params, $header)
    {
        return $this->post('after-sale/can-apply', $params, $header, []);
    }

    /**
     * 申请售后
     */
    public function applyAfterSale($params, $header)
    {
        return $this->post('after-sale/apply-after-sale', $params, $header, []);
    }

    /**
     * 修改站单地址
     */
    public function updateBillAddress($params, $header)
    {
        return $this->post('order/update-bill-address', $params, $header, []);
    }

    /**
     * 订单地址详情
     */
    public function orderAddressDetail($params, $header)
    {
        return $this->post('order-query/order-address', $params, $header, []);
    }

    /**
     * 账单地址
     */
    public function orderBillAddressDetail($params, $header)
    {
        return $this->post('order-query/bill-address', $params, $header, []);
    }

    public function oldOrderUrl($params, $header)
    {
        return $this->post('order-query/old-order-init', $params, $header, []);
    }

    /**
     * 给订单添加备注
     *
     * @param $params
     * @param $header
     *
     * @return array|bool|mixed
     */
    public function addOrderRemarks($params, $header)
    {
        return $this->post('order/add-order-remarks', $params, $header, []);
    }

    public function gaReportedResult($params, $header)
    {
        return $this->post('order/ga-reported-result', $params, $header, []);
    }

    /**
     * 订单统计
     */
    public function statisticsDay($params, $header)
    {
        return $this->post('order-query/statistic', $params, $header, []);
    }

    /**
     * 获取游客地址列表
     */
    public function touristAddressList($params)
    {
        return $this->get('tourist/address-list', $params);
    }

    /**
     * 新增游客地址列表
     */
    public function touristAddAddress($params, $header)
    {
        return $this->get('tourist/add-address', $params, $header, []);
    }

    /**
     * 游客商品校验
     */
    public function touristGoodsListCheck($params, $header)
    {
        return $this->post('order/goods-list-check', $params, $header, []);
    }

    /**
     * 游客商品校验
     */
    public function touristTradeConfirm($params, $header)
    {
        $params['ip'] = $this->JPGetRealIp();
        return $this->post('tourist/trade-confirm', $params, $header, []);
    }

    /**
     * 游客购买支付完成页
     *
     * @param $params
     *
     * @return array|bool|mixed
     */
    public function touristPaySuccess($params)
    {
        return $this->get('tourist/success', $params);
    }

    /**
     * 获取PDF
     *
     * @param $params
     *
     * @return array|bool|mixed
     */
    public function touristPdf($params)
    {
        return $this->get('tourist/get-pdf-view', $params);
    }

    /**
     * 修改取货方式
     */
    public function updateLocationType($params, $header)
    {
        return $this->post('order/update-location-type', $params, $header, []);
    }

    /**
     * 全部售后原因列表
     */
    public function allReasonList($params, $header)
    {
        return $this->post('after-sale-query/all-reason-list', $params, $header, []);
    }

    public function reasonList($params, $header)
    {
        return $this->post('after-sale-query/reason-list', $params, $header, []);
    }

    public function addReason($params, $header)
    {
        return $this->post('after-sale/add-reason', $params, $header, []);
    }

    public function delReason($params, $header)
    {
        return $this->post('after-sale/del-reason', $params, $header, []);
    }

    public function editReason($params, $header)
    {
        return $this->post('after-sale/edit-reason', $params, $header, []);
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


    /**
     * @param string $ip IP十五位字符
     *
     * @return bool ，是否为内部ip
     */
    private function JPCheckIP($ip)
    {
        if (preg_match("/^(192\.168|127\.0\.0)\./", $ip) && getenv("HTTP_X_FORWARDED_FOR")) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 获取PDF页面
     *
     * @param array $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function dovnloadPdf($params = [], $header = [])
    {
        return $this->get('order-query/get-order-view', $params, $header, []);
    }

    public function confirmOrderAddressInfo($params = [], $header = [])
    {
        return $this->post('order/confirm-order-address-info', $params, $header, []);
    }

    /**
     * 售后处理方案列表
     *
     * @param array $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function getProcessMethodList($params = [], $header = [])
    {
        return $this->get('after-sale/get-process-method-list', $params, $header, []);
    }

    /**
     * 确认换货页面数据接口
     *
     * @param array $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function afterSaleExchangeConfirm($params = [], $header = [])
    {
        return $this->get('after-exchange/confirm', $params, $header, []);
    }

    /**
     * 确认换货
     *
     * @param array $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function afterSaleExchangeTradeConfirm($params = [], $header = [])
    {
        return $this->post('after-exchange/trade-confirm', $params, $header, []);
    }

    public function afterSaleExchangeUserInput($params = [], $header = [])
    {
        return $this->post('after-exchange/input', $params, $header, []);
    }

    public function afterSaleExchangeUserInputCalculate($params = [], $header = [])
    {
        return $this->post('after-exchange/calculate', $params, $header, []);
    }

    public function afterSaleExchangeGoodsSelect($params = [], $header = [])
    {
        return $this->post('after-exchange/goods-select', $params, $header, []);
    }

    public function afterSaleExchangeEnterExchangeGoods($params = [], $header = [])
    {
        return $this->post('after-exchange/enter-exchange-goods', $params, $header, []);
    }

    /**
     * 更新售后处理方案状态
     *
     * @param array $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function setProcessMethodStatus($params = [], $header = [])
    {
        return $this->post('after-sale/set-process-method-status', $params, $header, []);
    }

    /**
     * 删除售后处理方案
     *
     * @param array $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function deleteProcessMethod($params = [], $header = [])
    {
        return $this->post('after-sale/delete-process-method', $params, $header, []);
    }

    /**
     * 新增或更新售后处理方案
     *
     * @param array $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function addUpdateProcessMethod($params = [], $header = [])
    {
        return $this->post('after-sale/add-update-process-method', $params, $header, []);
    }

    /**
     * 数据看板
     *
     * @param array $params
     * @param array $header
     *
     * @return array|bool|mixed
     * @author 白杨
     * @Date   14/9/21 下午4:04
     */
    public function getBossBoard($params = [], $header = [])
    {
        return $this->get('/statistics/boss-board', $params, $header, []);
    }

    public function getAftersaleCycle($params = [], $header = [])
    {
        return $this->post('statistics/aftersale-cycle', $params, $header, []);
    }

    public function getAftersaleCycleDownload()
    {
        return $this->getBaseUrl() . 'statistics/aftersale-cycle';
    }

    public function getAftersaleProcess($params = [], $header = [])
    {
        return $this->post('statistics/aftersale-process', $params, $header, []);
    }

    public function getAftersaleProcessDownload()
    {
        return $this->getBaseUrl() . 'statistics/aftersale-process';
    }

    public function getAftersaleReason($params = [], $header = [])
    {
        return $this->post('statistics/aftersale-reason', $params, $header, []);
    }

    public function getAftersaleReasonDownload()
    {
        return $this->getBaseUrl() . 'statistics/aftersale-reason';
    }

    /**
     * 根据邮箱和订单号查询订单信息是否存在
     *
     * @param array $params
     * @param array $header
     *
     * @return array|bool|mixed
     * @since  2021.10.18
     * @author liangchupeng
     *
     */
    public function queryOrderExists($params = [], $header = [])
    {
        return $this->post('order-query/query-order-exists', $params, $header, []);
    }


    /**
     * 搜索商品
     *
     * @param array $params
     * @param array $header
     *
     * @author liangchupeng
     *
     */
    public function searchProduct($params = [], $header = [])
    {
        return $this->post('after-sale-query/search-product', $params, $header, []);
    }

    /**
     * 发付请邮件
     *
     * @param array $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function sendPayEmail($params = [], $header = [])
    {
        return $this->post('order/send-pay-email', $params, $header);
    }

    /**
     * 取消换货
     *
     * @param array $params
     * @param array $header
     *
     * @author liangchupeng
     *
     */
    public function cancelExchange($params = [], $header = [])
    {
        return $this->post('after-sale/cancel-exchange', $params, $header, []);
    }

    /**
     * 取消换货
     *
     * @param array $params
     * @param array $header
     *
     * @author liangchupeng
     *
     */
    public function enterExchange($params = [], $header = [])
    {
        return $this->post('after-sale/enter-exchange', $params, $header, []);
    }

    /**
     * 客服录入换货
     *
     * @param array $params
     * @param array $header
     *
     * @author liangchupeng
     *
     */
    public function enterExchangeGoods($params = [], $header = [])
    {
        return $this->post('after-sale/enter-exchange-goods', $params, $header, []);
    }

    /**
     * 客服审核换货
     *
     * @param array $params
     * @param array $header
     *
     * @author liangchupeng
     *
     */
    public function afterSaleAuditExchange($params = [], $header = [])
    {
        return $this->post('after-sale/after-sale-audit-exchange', $params, $header, []);
    }

    /**
     * 是否可以申请换货
     *
     * @param array $params
     * @param array $header
     *
     * @author liangchupeng
     *
     */
    public function isCanApplyExchange($params = [], $header = [])
    {
        return $this->post('after-sale-query/is-can-apply-exchange', $params, $header, []);
    }

    /**
     * 添加售后备注
     *
     * @param array $params
     * @param array $header
     *
     * @return array|bool|mixed
     * @author 白杨
     * @Date   12/11/21 下午3:03
     */
    public function addAfterSaleRemark($params = [], $header = [])
    {
        return $this->post('after-sale/add-after-sale-remark', $params, $header, []);
    }

    /**
     * 添加自提地址
     *
     * @param array $params
     * @param array $header
     *
     * @return array|bool|mixed
     * @author 白杨
     * @Date   23/11/21 上午11:45
     */
    public function addPickUpAddress($params = [], $header = [])
    {
        return $this->post('order/add-pick-up-address', $params, $header, []);
    }

    /**
     * 是否可以申请换货
     *
     * @param array $params
     * @param array $header
     *
     * @author liangchupeng
     *
     */
    public function getWarehouseList($params = [], $header = [])
    {
        return $this->post('warehouse/list', $params, $header, []);
    }


    public function addressIsPass($params = [], $header = [])
    {
        return $this->post('order/address-is-pass', $params, $header, []);
    }


    public function actionSetRepeat($params)
    {
        return $this->get('order/set-repeat', $params, [], []);
    }

    public function actionCancelRepeat($params)
    {
        return $this->get('order/cancel-repeat', $params, [], []);
    }


    public function actionGetRepeat($params)
    {
        return $this->get('order/get-repeat', $params, [], []);
    }

    public function getConsultLabelPageList($params)
    {
        return $this->get('consult-label/page-list', $params, [], []);
    }

    public function getConsultLabelDetail($params)
    {
        return $this->get('consult-label/detail', $params, [], []);
    }

    public function getUdeskSessionLabel($params)
    {
        return $this->get('consult-label/get-udesk-session-label', $params, [], []);
    }

    public function getConsultLabelCascader($params)
    {
        return $this->get('consult-label/get-cascader', $params, [], []);
    }


    public function consultLabelEdit($params)
    {
        return $this->post('consult-label/edit', $params, [], []);
    }

    public function forAfterSaleToUser($params, $header)
    {
        return $this->post('order-query/for-after-sale-to-user', $params, $header, []);
    }

    public function allReasonToUser($params, $header)
    {
        return $this->get('after-sale-v2/all-reason-to-user', $params, $header, []);
    }

    public function applyAfterSalePageSolution($params, $header)
    {
        return $this->post('after-sale-v2/apply-page-solution', $params, $header, []);
    }

    public function applyAmountCalculate($params, $header)
    {
        return $this->post('after-sale-v2/apply-amount-calculate', $params, $header, []);
    }

    public function applyAfterSaleV2($params, $header)
    {
        return $this->post('after-sale-v2/user-apply', $params, $header, []);
    }

    public function refundWaitingForReviewHandle($params, $header)
    {
        return $this->post('after-sale-v2/refund-waiting-for-review-handle', $params, $header, []);
    }

    public function allReasonToManage($params, $header)
    {
        return $this->post('after-sale-v2/all-reason-to-manage', $params, $header, []);
    }

    public function getShipAndBillAddress($params, $header)
    {
        return $this->get('order-query/get-ship-and-bill-address', $params, $header, []);
    }

    /**
     * 售后使用订单数据接口
     * start
     */
    /**
     * @param $params [order_no]
     * @param $header
     *
     * @return array|bool|mixed
     */
    public function getBasicOrder($params, $header)
    {
        return $this->get('order-query/get-basic-order', $params, $header, []);
    }

    public function getOrderAddress($params, $header)
    {
        return $this->get('order-query/get-order-address', $params, $header, []);
    }

    public function getOrderAddressPickUp($params, $header)
    {
        return $this->get('order-query/get-order-address-pick-up', $params, $header, []);
    }

    public function getOrderGoodsList($params, $header)
    {
        return $this->get('order-query/get-order-goods-list', $params, $header, []);
    }

    public function getSkuInfosBySkuIds($params, $header = [])
    {
        return $this->get('order-query/get-sku-infos-by-sku-ids', $params, $header, []);
    }

    public function repeat($params, $header = [])
    {
        return $this->get('order-repeat/repeat', $params, $header, []);
    }

    /**
     * 订单异常检测（订单信息是否发生改变/重复订单）
     *
     * @return array
     */
    public function orderAnomalyDetect($params, $header = [])
    {
        return $this->post('order/order-anomaly-detect', $params, $header, []);
    }

    /**
     * end
     */

    /**
     * 创建神策被动上传功能
     */
    public function createEvent($params, $header = [])
    {
        return $this->post('mq-consume/create-event', $params, $header, []);
    }

    public function getTrackingExceptionDownloadUrl()
    {
        return $this->getBaseUrl() . 'tracking-exception/page-list';
    }

    public function confirmSaveValueAddedService($params, $header)
    {
        return $this->post('cart-new/confirm-save-value-added-service', $params, $header, []);
    }

    // 购物车修改保养/增值服务
    public function settleChooseService($params, $header = [])
    {
        return $this->post('cart-new/settle-choose-service', json_encode($params), $header, []);
    }

    /**
     * 购物车中增加、修改、删除安装服务
     *
     * @param       $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function changeInstallationService($params, $header = [])
    {
        return $this->post('cart-new/change-installation-service', json_encode($params), $header, []);
    }

    public function getProtectionListByOrderGoodsIds($params, $header)
    {
        return $this->get('protection-plan/get-list-by-order-goods-ids', $params, $header, []);
    }

    public function getProtectionListByOrderNo($params, $header)
    {
        return $this->get('protection-plan/get-list-by-order-no', $params, $header, []);
    }

    public function getOrderPlanRefund($params, $header)
    {
        return $this->post('protection-plan/get-order-plan-refund', $params, $header, []);
    }

    public function getInvoice($params, $header)
    {
        return $this->post('order-v4/get-invoice', $params, $header, []);
    }

    public function getOrderList($params, $header)
    {
        return $this->post('order-v4/order-list', $params, $header, []);
    }

    public function getOrderBanner($params, $header)
    {
        return $this->post('order-v4/get-order-banner', $params, $header, []);
    }

    public function getOrderGoodsCardList($params, $header)
    {
        return $this->post('order-v4/get-order-goods-card-list', $params, $header, []);
    }

    public function getThisItemHelp($params, $header)
    {
        return $this->post('order-v4/get-this-item-help', $params, $header, []);
    }

    public function getOrderTrackList($params, $header)
    {
        return $this->get('order-query/get-order-track-list', $params, $header, []);
    }

    /**
     * 加购色样弹窗-购物车接口
     *
     * @param $params
     * @param $header
     *
     * @return array|bool|mixed
     */
    public function getColorSkuCartList($params, $header = [])
    {
        return $this->get('delivery-or-pick-up/get-cart-color-sku-list', $params, $header, []);
    }

    public function orderDataSync($params, $header = [])
    {
        return $this->post('order/order-data-sync', $params, $header, []);
    }


    public function getOrderInfo($params, $header = [])
    {
        return $this->post('order/get-order-info', $params, $header, []);
    }

    /**
     * 报价单详情
     *
     * @param array $params
     * @param $header
     * @return array|bool|mixed
     */
    public function quotationOrderInfo(array $params, $header = [])
    {
        $params['ip'] = $this->JPGetRealIp();
        return $this->get('storeapi/quotation-order/info', $params, $header);
    }

    /**
     * 报价单商品变更
     *
     * @param array $params
     * @param $header
     * @return array|bool|mixed
     */
    public function quotationOrderGoodsChange(array $params, $header = [])
    {
        $params['ip'] = $this->JPGetRealIp();
        return $this->get('storeapi/quotation-order/goods-change', $params, $header);
    }

    public function quotationInfo($params, $header = [])
    {
        $params['ip'] = $this->JPGetRealIp();
        return $this->get('storeapi/quotation/info', $params, $header);
    }

    public function quotationChange($params, $header = [])
    {
        $params['ip'] = $this->JPGetRealIp();
        return $this->post('storeapi/quotation/change', $params, $header);
    }

    /**
     * impact 转化
     * @param       $params
     * @param array $header
     *
     * @return array|bool|mixed
     */
    public function impactConvert($params, $header = [])
    {
        return $this->post('impact/convert', $params, $header);
    }
}
