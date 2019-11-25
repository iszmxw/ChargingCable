<?php
/**
 * 充电套餐控制器
 */
namespace app\api\controller;
use app\api\logic\ChargeLogic;
use think\Loader;
use app\api\logic\OrderLeaseLogic;
use app\api\logic\PayLogic;

class Charge extends Base
{
    public $startDate = '00:00';
    public $endDate = '09:00';

    //套餐列表
    public function chargeList()
    {
        $number = input('number/s');
        $equipment = M('lc_equipment_number')->where(['number'=>$number,'status'=>1])->find();
        if(empty($equipment)) return returnBad('设备未启用');
        //查找是否有酒店
        if($equipment['j_user_id']){
            //查找酒店下的充电价格
            $apply = M("lc_apply")->where(array('user_id'=>$equipment['j_user_id']))->field("one_hour,three_hour,ten_hour")->find();
        }


        $pid = M('package')->where('pid',$equipment['pack_id'])->value('pid');
        $list = M('package_price')->where('tid',$pid)->field('id,time,price,green_power')->order('id asc')->select();
        foreach($list as $k=>&$value){
            if($k==0 && $apply['one_hour']){
                $list[$k]['price'] = $apply['one_hour'];
            }elseif($k==1 &&$apply['three_hour']){
                $list[$k]['price'] = $apply['three_hour'];
            }elseif($k==2 &&$apply['ten_hour']){
                $list[$k]['price'] = $apply['ten_hour'];
            }
            $value['key'] = ++$k;
        }
        $user_info = M('users')->where(['user_id'=>$this->user_id])->field('green_power,mobile')->find();
        $is_register = 1;
        if(empty($user_info['mobile'])) $is_register = 0;
        $data = [
            'number'=>$number,
            'list'=>$list,
            'green_power'=>$user_info['green_power'],
            'is_register'=>$is_register
        ];
        return returnOk($data);
    }

    //获取充电宝密码
    public function getChargeCode()
    {
        $post = $this->check_post();

        $chargeLogic = new ChargeLogic();
       //判断时间是否已过期
        $order_sn = $post['order_sn'];
        $order = M("power_order")->where(['order_sn'=>$order_sn])->field("pay_time,time,key,order_sn,t_id,password")->order("pay_time desc")->find();
        if($order) {
            $time = $order['pay_time'] + ($order['time'] * 60);
            if ($time < time()){
                return returnBad('订单时间已过期',302);
            }
        }

        $code = $chargeLogic->getChargeCode($post);

        $data = [
            'code'=>$code
        ];
        if($data){
            return returnOk($data);
        }else {
            return returnBad('获取密码失败，请重新扫码获取',302);
        }
    }
    //支付完成获取
    public function get_password(){
        $number = input('number');
        $openid = input('openid/s');
        $user = M("users")->where(['openid'=>$openid])->field("user_id")->find();
        $uid = $user['user_id'];
        $order = M("power_order")->where(['user_id'=>$uid,'number'=>$number,'pay_status'=>2])->order("pay_time DESC")->limit(1)->select();
         return returnOk($order[0]);

    }

    //个人中心-环保电量
    public function getGreenPower()
    {
        $openid = input('openid/s');
        $upper_limit = M('config')->where('name','green_power')->value('value');
        $green_power = M('users')->where('openid',$openid)->value('green_power');
        $data = ['upper_limit'=>(int)$upper_limit,'green_power'=>$green_power];
        return returnOk($data);
    }
    

    //联系客服
    public function contactCustomerService()
    {
        $date = date('H:i');
        $cruTime = strtotime($date);
        $startDate = strtotime($this->startDate);
        $endDate = strtotime($this->endDate);
        if($cruTime>$startDate && $cruTime<$endDate){
            $number = M('config')->where('name','customer_service_wechat')->value('value');
            $data = ['title'=>'请添加客服微信'];
        }else{
            $number = M('config')->where('name','customer_service_mobile')->value('value');
            $data = ['title'=>'客服电话'];
        }
        $data['number'] = $number;
        return returnOk($data);
    }
}