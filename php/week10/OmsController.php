<?php


namespace AppConsole\controllers;


use common\api\OmsApi;
use common\api\OrderInternal;
use common\enums\AfterSaleProcessTypeEnum;
use common\models\oms\ToOmsOrder;
use common\repositorys\AfterSaleGoodsRepository;
use common\repositorys\AfterSaleRepository;
use common\repositorys\oms\ToOmsOrderRepository;
use common\services\oms\OmsDeliveryService;
use common\services\oms\ToOmsOrderService;
use common\services\OmsService;
use common\services\params\ToOmsOrderParams;
use PHPUnit\Util\Log\JSON;

class OmsController extends BaseController
{

    public $after_sale_no = '';
    public $command_code  = '';
    public $sku_code      = '';
    public $order_no      = '';
    public $qty           = 0;

    public function options($actionID)
    {
        return ['after_sale_no', 'sku_code', 'command_code', 'order_no', 'qty'];
    }

    /**
     * php yii oms/cancel-intercept --after_sale_no=A2212221832195892378
     */
    public function actionCancelIntercept()
    {
        echo '触发 推送OMS 取消拦截(售后单纬度)' . PHP_EOL;
        if (empty($this->after_sale_no)) {
            echo '售后单号为空' . PHP_EOL;
            return false;
        }
        $afterSale = AfterSaleRepository::instance()->getAfterSaleByNo($this->after_sale_no);
        if (empty($afterSale)) {
            echo '售后单不存在' . PHP_EOL;
            return false;
        }
        $orderNo = $afterSale['order_no'] ?? '';
        // 获取工单信息
        $toOmsOrderList = ToOmsOrderRepository::instance()->getList([
            ['=', 'business_no', $this->after_sale_no],
            ['=', 'command_type', ToOmsOrderRepository::INTERCEPT_ORDER_GOODS_TYPE],
            ['=', 'command_status', ToOmsOrderRepository::COMMAND_STATUS_SUCCESS],
        ]);
        $oldCommandCode = $toOmsOrderList[0]['command_code'] ?? '';

        // 拼装数据
        $toOmsOrderList = array_column($toOmsOrderList, null, 'sku_code');
        $goodsData      = OmsService::instance()->getFormatInterceptOrderGoodsData($toOmsOrderList, 2);

        foreach ($goodsData as $goods) {
            $toOmsOrderParams = ToOmsOrderService::instance()->formatInterceptData(
                $orderNo,
                $this->after_sale_no,
                [$goods],
                ToOmsOrderRepository::CANCEL_INTERCEPT_ORDER_TYPE,
                $aftersAleData['cancel_reason_str'] ?? '', $oldCommandCode);

            $result = ToOmsOrderService::instance()->pushOmsOrder($toOmsOrderParams);
            var_dump($result);
        }

        echo '结束' . PHP_EOL;
    }

    /**
     * 确认OMS取消
     * php yii oms/cancel --after_sale_no=A2212071618564076105
     */
    public function actionCancel()
    {
        $aftersAleData = AfterSaleRepository::instance()->getAfterSaleByNo($this->after_sale_no);
        if (empty($aftersAleData)) {
            echo '售后单为空' . PHP_EOL;
            return false;
        }

        $list = ToOmsOrder::find()
            ->andWhere(['=', 'business_no', $aftersAleData['after_sale_no']])
            ->andWhere(['=', 'command_type', 1])
            ->andWhere(['=', 'command_status', 1])
            ->asArray()
            ->all();


        foreach ($list as $item) {
            $interceptData = [];
            $insertData    = [];

            // 本次请求的指令码
            $commandCode  = ToOmsOrderService::instance()->getCommandCode();
            $interceptNow = [
                'itemSkuCode' => $item['sku_code'],
                'qty'         => $item['qty'],
            ];
            if (!empty($item['command_code'])) {
                $interceptNow['commandCode'] = $item['command_code'];
            }
            $interceptData[] = $interceptNow;

            // 工单数据拼装
            $insertData[] = [
                'order_no'        => $aftersAleData['order_no'],
                'business_no'     => $aftersAleData['after_sale_no'],
                'business_type'   => 2,
                'order_goods_id'  => $item['order_goods_id'],
                'sku_code'        => $item['sku_code'],
                'qty'             => $item['qty'],
                'command_code'    => $commandCode,
                'command_type'    => ToOmsOrderRepository::CONFIRM_CANCEL_ORDER_GOODS_TYPE,
                'command_status'  => 0,
                'service_operate' => 0,
                'created_at'      => time(),
                'updated_at'      => time(),
            ];

            // 如果修改订单的参数
            if (!empty($modifyData)) {
                $interceptData = ['skuModifyInfo' => $interceptData];
            }
            // 写入数据对象
            $interceptParams                 = new ToOmsOrderParams();
            $interceptParams->requestData    = $interceptData;
            $interceptParams->workData       = $insertData;
            $interceptParams->orderNo        = $aftersAleData['order_no'];
            $interceptParams->businessNo     = $aftersAleData['after_sale_no'];
            $interceptParams->oldCommandCode = $item['command_code'];
            $interceptParams->commandCode    = $commandCode;
            $interceptParams->commandType    = ToOmsOrderRepository::CONFIRM_CANCEL_ORDER_GOODS_TYPE;
            $interceptParams->isOperate      = 0;
            $result                          = ToOmsOrderService::instance()->pushOmsOrder($interceptParams);
            if (is_array($result)) {
                echo json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            } else if (is_string($result)) {
                echo $result . PHP_EOL;
            } else {
                echo json_encode([$result], JSON_UNESCAPED_UNICODE) . PHP_EOL;
            }
            echo '结束' . PHP_EOL;
        }
    }

    /**
     * 手动触发oms拦截
     * php yii oms/intercept --after_sale_no=A2211222325311558257
     */
    public function actionIntercept()
    {
        echo '触发 推送OMS 拦截(售后单纬度)' . PHP_EOL;
        if (empty($this->after_sale_no)) {
            echo '售后单号为空' . PHP_EOL;
            return false;
        }
        $result = OmsService::instance()->interceptOrder($this->after_sale_no);
        if (is_array($result)) {
            echo json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        } else if (is_string($result)) {
            echo $result . PHP_EOL;
        } else {
            echo json_encode([$result], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }
        echo '结束' . PHP_EOL;

    }


    /**
     * 手动触发oms拦截
     * php yii oms/intercept-sku --after_sale_no=A2212301552276850781 --sku_code=J04SCZ000013L
     */
    public function actionInterceptSku()
    {
        echo '触发 推送OMS 拦截(SKU纬度)' . PHP_EOL;
        if (empty($this->after_sale_no)) {
            echo '售后单号为空' . PHP_EOL;
            return false;
        }
        if (empty($this->sku_code)) {
            echo 'SKU为空' . PHP_EOL;
            return false;
        }
        // 拼装请求数据
        $afterSaleNo        = $this->after_sale_no;
        $skuCode            = $this->sku_code;
        $afterSaleGoodsData = AfterSaleGoodsRepository::instance()->getListByAfterSaleNo($afterSaleNo);

        foreach ($afterSaleGoodsData as $item) {
            if ($item['sku_code'] == $skuCode) {
                $aftersAleData = AfterSaleRepository::instance()->getAfterSaleByNo($afterSaleNo);
                $goodsData     = OmsService::instance()->getFormatInterceptOrderGoodsData([$item]);

                // 组装OMS的数据
                $processingType   = $aftersAleData['processing_type'] ?? AfterSaleProcessTypeEnum::UNCONFIRMED;
                $toOmsOrderParams = ToOmsOrderService::instance()->formatInterceptData($aftersAleData['order_no'], $aftersAleData['after_sale_no'], $goodsData, ToOmsOrderRepository::INTERCEPT_ORDER_GOODS_TYPE, AfterSaleProcessTypeEnum::$list[$processingType]);

                // 请求
                $result = ToOmsOrderService::instance()->pushOmsOrder($toOmsOrderParams);
                if (is_array($result)) {
                    echo json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
                } else if (is_string($result)) {
                    echo $result . PHP_EOL;
                } else {
                    echo json_encode([$result], JSON_UNESCAPED_UNICODE) . PHP_EOL;
                }
                echo '结束' . PHP_EOL;
                die();
            }
        }
    }

    /**
     * 订单纬度手动触发oms拦截
     * php yii oms/intercept-order-no --order_no=USA230207033814
     */
    public function actionInterceptOrderNo()
    {
        echo '触发 推送OMS 拦截(订单纬度)' . PHP_EOL;
        if (empty($this->order_no)) {
            echo '单号为空' . PHP_EOL;
            return false;
        }

        // 拼装请求数据
        $goodsList    = [];
        $orderAllData = OrderInternal::instance()->getAllDataByOrderNo($this->order_no, 'required');
        if (isset($orderAllData['code']) && $orderAllData['code'] == 1 && !empty($orderAllData['data'])) {
            $allData   = $orderAllData['data'];
            $goodsList = $allData['order_goods_list'] ?? [];
        }
        if (empty($goodsList)) {
            echo '商品数据查询失败' . PHP_EOL;
            return false;
        }

        // 组装OMS的数据
        $toOmsOrderParams = ToOmsOrderService::instance()->formatInterceptData($this->order_no, '', $goodsList, ToOmsOrderRepository::INTERCEPT_ORDER_GOODS_TYPE, '交易未完成，脚本拦截');

        // 请求
        $result = ToOmsOrderService::instance()->pushOmsOrder($toOmsOrderParams);
        if (is_array($result)) {
            echo json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        } else if (is_string($result)) {
            echo $result . PHP_EOL;
        } else {
            echo json_encode([$result], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }
        echo '结束' . PHP_EOL;
        die();
    }

    /**
     * 指定工单确认OMS取消
     * php yii oms/cancel-sku --command_code=
     */
    public function actionCancelSku()
    {
        if (empty($this->command_code)) {
            echo '指令码为空' . PHP_EOL;
            return false;
        }

        $list = ToOmsOrder::find()
            ->andWhere(['=', 'command_code', $this->command_code])
            ->asArray()
            ->all();


        foreach ($list as $item) {
            $interceptData = [];
            $insertData    = [];

            // 本次请求的指令码
            $commandCode  = ToOmsOrderService::instance()->getCommandCode();
            $interceptNow = [
                'itemSkuCode' => $item['sku_code'],
                'qty'         => $item['qty'],
            ];
            if (!empty($item['command_code'])) {
                $interceptNow['commandCode'] = $item['command_code'];
            }
            $interceptData[] = $interceptNow;

            // 工单数据拼装
            $insertData[] = [
                'order_no'        => $item['order_no'],
                'business_no'     => $item['business_no'],
                'business_type'   => 2,
                'order_goods_id'  => $item['order_goods_id'],
                'sku_code'        => $item['sku_code'],
                'qty'             => $item['qty'],
                'command_code'    => $commandCode,
                'command_type'    => ToOmsOrderRepository::CONFIRM_CANCEL_ORDER_GOODS_TYPE,
                'command_status'  => 0,
                'service_operate' => 0,
                'created_at'      => time(),
                'updated_at'      => time(),
            ];

            // 如果修改订单的参数
            if (!empty($modifyData)) {
                $interceptData = ['skuModifyInfo' => $interceptData];
            }
            // 写入数据对象
            $interceptParams                 = new ToOmsOrderParams();
            $interceptParams->requestData    = $interceptData;
            $interceptParams->workData       = $insertData;
            $interceptParams->orderNo        = $item['order_no'];
            $interceptParams->businessNo     = $item['business_no'];
            $interceptParams->oldCommandCode = $item['command_code'];
            $interceptParams->commandCode    = $commandCode;
            $interceptParams->commandType    = ToOmsOrderRepository::CONFIRM_CANCEL_ORDER_GOODS_TYPE;
            $interceptParams->isOperate      = 0;
            $result                          = ToOmsOrderService::instance()->pushOmsOrder($interceptParams);
            if (is_array($result)) {
                echo json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            } else if (is_string($result)) {
                echo $result . PHP_EOL;
            } else {
                echo json_encode([$result], JSON_UNESCAPED_UNICODE) . PHP_EOL;
            }
            echo '结束' . PHP_EOL;
        }
    }

    /**
     * php yii oms/test
     */
    public function actionTest()
    {
//        $afterSaleNo = 'A2212071618564076105';
//        ToOmsOrderService::instance()->interceptAfterSaleProcessing($afterSaleNo);
//        OmsService::instance()->comfirmCancelOrder($afterSaleNo);
//        $result = OmsApi::instance()->getWarehouseSkuBySkuCode(['J11ZYT000001']);
//        var_dump($result);
//        $result = OmsDeliveryService::instance()->pushOmsAfterSaleLogistics('A2211301505075901474','update');
//        var_dump($result);
//        $result = OmsDeliveryService::instance()->pushOmsAfterSaleLogistics('A2301101457216850519');
//        var_dump($result);
//        $result = OmsDeliveryService::instance()->cancelOmsAfterSaleLogistics('A2211301505075901474');
//        var_dump($result);
//        $result = OmsApi::instance()->getOmsAfterSaleLogistics('');
//        var_dump($result);
    }


    /**
     * 指定指令码，sku，数量进行取消拦截
     * php yii oms/cancel-intercept-by-command-and-sku-and-qty --command_code=ERPN240726140090 --sku_code=J04MCW000222 --qty=1
     *
     * @return false|void
     */
    public function actionCancelInterceptByCommandAndSkuAndQty()
    {
        var_dump($this->command_code, $this->sku_code, $this->qty);

        if (empty($this->command_code) || empty($this->sku_code)) {
            echo '参数为空' . PHP_EOL;
            return false;
        }

        $command = ToOmsOrder::find()
            ->andWhere(['=', 'command_code', $this->command_code])
            ->asArray()
            ->one();
        if (empty($command)) {
            echo '指令为空' . PHP_EOL;
            return false;
        }
        if ($command['sku_code'] != $this->sku_code) {
            echo 'sku_code有误' . PHP_EOL;
            return false;
        }


        // 本次请求的指令码
        $commandCode = ToOmsOrderService::instance()->getCommandCode();

        $qty          = $this->qty ?: $command['qty'];
        $interceptNow = [
            'itemSkuCode' => $this->sku_code,
            'qty'         => $qty,
        ];
        if (!empty($command['command_code'])) {
            $interceptNow['commandCode'] = $command['command_code'];
        }
        $interceptData[] = $interceptNow;

        // 工单数据拼装
        $insertData[] = [
            'order_no'        => $command['order_no'],
            'business_no'     => $command['business_no'],
            'business_type'   => 2,
            'order_goods_id'  => $command['order_goods_id'],
            'sku_code'        => $this->sku_code,
            'qty'             => $qty,
            'command_code'    => $commandCode,
            'command_type'    => ToOmsOrderRepository::CANCEL_INTERCEPT_ORDER_TYPE,
            'command_status'  => 0,
            'service_operate' => 0,
            'created_at'      => time(),
            'updated_at'      => time(),
        ];

        // 写入数据对象
        $interceptParams                 = new ToOmsOrderParams();
        $interceptParams->requestData    = $interceptData;
        $interceptParams->workData       = $insertData;
        $interceptParams->orderNo        = $command['order_no'];
        $interceptParams->businessNo     = $command['business_no'];
        $interceptParams->oldCommandCode = $command['command_code'];
        $interceptParams->commandCode    = $commandCode;
        $interceptParams->commandType    = ToOmsOrderRepository::CANCEL_INTERCEPT_ORDER_TYPE;
        $interceptParams->isOperate      = 0;
        $result                          = ToOmsOrderService::instance()->pushOmsOrder($interceptParams);
        if (is_array($result)) {
            echo json_encode($result, 320) . PHP_EOL;
        } else if (is_string($result)) {
            echo $result . PHP_EOL;
        } else {
            echo json_encode([$result], 320) . PHP_EOL;
        }
        echo '结束' . PHP_EOL;
    }
}
