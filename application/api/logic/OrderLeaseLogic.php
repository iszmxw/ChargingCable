<?php
namespace app\api\logic;

class OrderLeaseLogic
{
    //添加订单
    public function addOrder($id,$user_id,$post)
    {
        $package = M('package_price')->where('id',$id)->field('time,price,green_power')->find();
        $order_sn = 'c'.date('Ymdhis') . time() . mt_rand(1000, 9999);
        $data = [
            'order_sn'=>$order_sn,
            'user_id'=>$user_id,
            'number'=>$post['number'],
            'use_time'=>$package['time'],
            'price_id'=>$id,
            'green_power'=>$package['green_power'],
            'pay_type'=>'微信支付',
            'price'=>$package['price'],
            'order_amount'=>$package['price'],
            'total_amount'=>$package['price'],
            'add_time'=>time(),
        ];
        $result = M('order_lease')->add($data);
        if($result){
            return ['status'=>1,'order_sn'=>$order_sn,'order_amount'=>$package['price']];
        }else{
            return ['status'=>-1];
        }
    }
}