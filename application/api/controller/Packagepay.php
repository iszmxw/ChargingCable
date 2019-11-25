<?php
/**
 * Pacagepay
 * ============================================================================
 * 使用干衣机支付
 * ============================================================================
 * Author: 玩味
 * Date: 2019-8-27
 */

namespace app\api\controller;

use app\api\logic\PayLogic;
use app\api\logic\PayModel;
use think\Db;
use think\Cache;

class Packagepay extends BaseApplet
{

    /**
     * [createOrder 创建支付记录]
     * @return [type] [description]
     */
    public function createOrder()
    {   

        $user = $this->user;

        $scantime = Cache::get('packpay'.$user['user_id']);
       
        if($scantime)
        {
            echo json_encode(['code'=>306,'msg'=>'请求频繁']);exit;
        }

        Cache::set('packpay'.$user['user_id'],time(),4);

        // 先删除之前待支付的
        Db::name('package_order')->where(['user_id'=>$user['user_id'], 'status'=>0])->delete();

        $pack_id = I('package_id',0,'intval');
        $equipment_id = I('equipment_id',0,'intval');

        $equipment = Db::name('equipment')->where(['id'=>$equipment_id])->find();


        if(!$equipment) 
        {
            echo json_encode(['code'=>306,'msg'=>'设备异常']);exit;
        }
       
        if($equipment['e_status'] == 0)
        {
            echo json_encode(['code'=>307,'msg'=>'设备尚未激活']);exit;
        } 

        if($equipment['e_status'] == 2)
        {
            echo json_encode(['code'=>308,'msg'=>'设备维护中']);exit;
        } 

        if(!$pack_id)
        {
            echo json_encode(['code'=>305,'msg'=>'请选择需要使用的套餐']);exit;
        } 
        $equipment_number_id = Db::name('lc_equipment_number')->where(['number'=>$equipment['e_no']])->value('pack_id');
        
        if($equipment_number_id)
        {
            $package = db::name('package_price')->where(['id'=>$pack_id, 'tid'=>$equipment_number_id])->find();
        } else {
            $pid = db::name('package')->where('default',1)->value('pid');

            $package = db::name('package_price')->where(['id'=>$pack_id, 'tid'=>$pid])->find();
        }
       
        if(!$package)
        {
            echo json_encode(['code'=>309,'msg'=>'选择的时间段异常']);exit;
        } 
        
        $data['ordersn']    = 'SH'.date('ymdHis') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $data['user_id']    = $user['user_id'];
        $data['nickname']   = $user['nickname'];
        $data['price']      = $package['price'];
        $data['status']     = 0;
        $data['pack_id']    = $pack_id;
        $data['createtime'] = time();
        $data['equipment_id'] = $equipment_id;

        
        $orderid = Db::name('package_order')->insertGetId($data);

        if($orderid)
        {
            $order = Db::name('package_order')->where("id", $orderid)->find();

            if (is_array($order) && $order['status'] == 0) 
            {
                $body = '使用设备'.$equipment['title'].'('.$package['name'].')';
                //微信JS支付
                $notify_url = url_add_domain('/index.php/Api/Pay/notify');

                $pay = new PayLogic($user['openid'],$order['ordersn'],$order['price']*100,$body,$notify_url,'wx71092b65dead0d5e');
                $parameters=$pay->weixinapp();
                if($parameters['return_code'] == 'FAIL' || $parameters['result_code'] == 'FAIL'){
                    echo json_encode(['code'=>309,'msg'=>'支付失败']);exit;
                }
              
                echo json_encode(['code'=>200,'parameters'=>$parameters, 'package'=>$package]);exit;
            } else {
                echo json_encode(['code'=>307,'msg'=>'已完成支付']);exit;
            }

        } else {
            echo json_encode(['code'=>308,'msg'=>'网络异常']);exit;
        }

    }

}