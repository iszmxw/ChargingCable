<?php
/**
--------------------------------------------------
空间类型   使用支付控制器
--------------------------------------------------
Copyright(c) 2017 时代万网 www.agewnet.com
--------------------------------------------------
开发人员: 玩味  <729167563@qq.com>
--------------------------------------------------
 */
namespace app\api\controller;

use app\common\model\ScanCommission;
use app\api\logic\PayLogic;
use think\Controller;
use think\Log;
use think\Db;

class Pay extends Controller{


    //支付回调
    public function notify(){

        $postXml = $GLOBALS["HTTP_RAW_POST_DATA"]; //接收微信参数
       
        if (empty($postXml)) {
            return FAIL;
        }
        $attr =xmlToArray($postXml);
       
        $sign1=$attr['sign'];    //签名
        unset($attr['sign']);
        $model=new PayLogic($attr["openid"],$attr["out_trade_no"],$attr["total_fee"]);
        $sign=$model->getSign($attr);     //生成签名
        $order = Db::name('package_order')->where('ordersn',$attr['out_trade_no'])->find();
       
        if(!$order)
        {
            file_put_contents('./pay/pay.txt', json_encode($attr));return;
            return FAIL;
        }
        
        if($sign1 == $sign){ //验签通过
           
            if($attr['return_code'] == 'SUCCESS' && $attr['result_code'] == 'SUCCESS'){ //支付成功
                
                if ((string)($order['price'] * 100) != (string)$attr['total_fee']) 
                {
                    file_put_contents('./pay/pay.txt', json_encode($attr));return FAIL;
                }
                
                Db::name('package_order')->where('id',$order['id'])->update(['status'=>1, 'paytype' => 1]);
                $ScanObject = new ScanCommission();
                $ScanObject->setScanCommission($order['id']);
            }
            $return_xml='<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            return $return_xml;
        }
        return FAIL;
    }

}