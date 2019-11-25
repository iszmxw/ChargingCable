<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/20
 * Time: 10:58
 */

namespace app\api\controller;


use app\api\logic\PayLogic;
use app\api\logic\ChargeLogic;
use think\Db;
use think\Loader;

class Wxpay extends Base
{


    /**
     * 扫码充电支付
     */
    public function chargepay()
    {
        $chargeLogic = new ChargeLogic();
        if ($this->user_id == 0) {
            return returnBad('登录超时请重新登录', 302);
        }
        $uid = $this->user_id;//user_id
        $t_id = input("id/d", 0); //  套餐id
        $time = input('time');  // 充电时间（分钟）
        $price = input('price');       // 充电价格
        $invoice_desc = input('green_power');       // 赠送环保电量
        $key_one = input("key/d"); //  密码key（密码第一位）
        $green_power = input("green_power");//  用户剩余环保电量
        $number = input("number");//设备编号
        $money = input("money");//需支付金额
        if ($time == '' || $time == false || $time == '' || $time == 'undefault') {
            return returnBad("请选择充电套餐", 306);
        }
        //判断前端价格和后端价格是否一致
        //1.后端价格。查找用户当前环保电量。
        $user_power = M('users')->where(['user_id'=>$uid])->field("green_power")->find();
        $power = $user_power['green_power'];//用户当前拥有环保电量
        //可抵扣环保电量总数
        if($power > 0) {
            if ($power > $time || $power == $time) {
                $nums = floor($time / 60);
                $times = $nums * 60;//可抵扣环保电量数
            } elseif($power > 59 && $power < $time) {
                $nums = floor($power / 60);
                $times = $nums * 60;//可抵扣环保电量数
            }else{
                $times = 0;//可抵扣环保电量数
            }
        }else{
              $times = 0;//可抵扣环保电量数
        }
        //计算价格
        $show_time = ($time - $times) / $time;
        $moneys = $show_time * $price;
        //保留两位小数
        $moneys = round($moneys,2);
        if($money != $moneys){
            return returnBad("金额有误！！", 306);
        }
        //创建唯一订单号
        $order_sn = 'WX' .$uid. date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8) . rand(10000, 99000);
        //生成密码
        $chargeLogic = new ChargeLogic();
        $post = array();
        $post['key'] = $key_one;
        $post['number'] = $number;
        $code = $chargeLogic->getChargeCode($post);
        if(!$code){
            return returnBad('网络错误，获取密码失败，请重新扫码获取',306);
        }
        //添加订单记录
        if ($moneys > 0) {
            $pay_status = 1;
            $pay_time = '';
        }else{
            $pay_status = 2;
            $pay_time = time();
        }
        $array = array(
            'order_sn'=>$order_sn,
            'user_id'=>$uid,
            't_id' => $t_id,
            'time' => $time,
            'price'=>$price,
            'green_power'=>$invoice_desc,
            'user_power'=>$times,
            'key'=>$key_one,
            'number'=>$number,
            'pay_price'=>$moneys,
            'create_time'=>time(),
            'password'=>$code,
            'pay_status'=>$pay_status,
            'pay_time'=>$pay_time
        );
        $order = M("power_order")->add($array);
       if($order) {
           if ($moneys > 0) {
               $notify_url = url_add_domain('/index.php/Api/Weixin/power_notify');
               //订单支付
               $pay = new PayLogic($this->user['openid'],$order_sn, $moneys * 100,$body="下单消费",$notify_url=$notify_url,$xcappid = '');
               $parameters = $pay->weixinapp();

               return returnOk($parameters);
           } else {
               //2.减去自身抵扣的环保电量
               M("users")->where(['user_id'=>$uid])->setDec('green_power',$times);
               //3.加上赠送的环保电量
               M("users")->where(['user_id'=>$uid])->setInc('green_power',$invoice_desc);
               return returnOk($array);
           }
       }else{
           return returnBad("网络错误！！", 306);
       }

    }
}