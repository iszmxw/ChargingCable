<?php
/**
 --------------------------------------------------
 空间类型   余额提现
 --------------------------------------------------
 Copyright(c) 2017 时代万网 www.agewnet.com
 --------------------------------------------------
 开发人员: 玩味  <729167563@qq.com>
 --------------------------------------------------

 */
namespace app\api\controller;

use app\admin\logic\SiteLogic;
use Think\Controller;
use think\Request;
use think\Cache;
use think\Db;
class Withdraw extends Base{


    /**
     * [index 提现首页]
     * @return [type] [description]
     */
    public function index()
    {

        // 提现设置
        $config = tpCache('cash');

        // 提现规则
        $siteObject = new SiteLogic();
        $protocol = $siteObject->getShop('withdraw');
        
        return returnOk(['config'=>$config, 'protocol'=>$protocol,'user_money'=>$this->user['user_money']]);

    }


    /**
     * [withdraw_subimt 提交提现申请]
     * @return [type] [description]
     */
    public function withdraw_subimt()
    {
        $user = $this->user;

        $scantime = Cache::get('withdraw_subimt'.$user['user_id']);

        if($scantime){
            return returnBad("请不要重复提交~", 301);
        }
        Cache::set('withdraw_subimt'.$user['user_id'],time(),3);

        // 提现设置
        $config = tpCache('cash');
        // 提现金额
        $money = I('money');

        // 提现类型
        $type = input('type/d');

        // 银行卡ID
        $bank_id = input('bank_id/d');

        // 今日提现记录
        $cumulative = Db::name('withdraw_cumulative')->where('user_id',$user['user_id'])->find();
        
        // 提现关闭提现
        if($config['cash_open'] == 0) 
        {
            return returnBad("系统暂时不支持提现~", 302);
        }

        // 0微信1银行卡
        if(!in_array($type, [0,1])) 
        {
            return returnBad("暂不支持该提现类型~", 303);
        }

        // 系统只支持微信提现
        if($config['cash_open'] == 2 && $type != 0) 
        {
            return returnBad("系统暂时只支持微信提现~", 304);
        }

        $bank = [];
        // 提现只支持银行卡提现
        if($config['cash_open'] == 3 && $type != 1) 
        {
            return returnBad("系统暂时只支持银行卡提现~", 305);
        }

        if($type == 1) 
        {
            $bank = M('user_bank_card')->where(['id'=>$bank_id, 'user_id'=>$user['user_id']])->find();
            if(empty($bank))
            {
                return returnBad("没找到该银行卡~", 315);
            }
        }

        if($money < 1) 
        {
            return returnBad("提现金额过小~", 307);
        }

        if(ceil($money) != $money) 
        {
            return returnBad("提现不能有小数点~", 308);
        }

        if($money > $user['user_money']) 
        {
            return returnBad("余额不足~", 309);
        }

        if($config['min_cash'] && $money < $config['min_cash']) 
        {
            return returnBad("最低提现金额 ".$config['min_cash'].'元~', 310);
        }
        
        if($config['max_cash'] && $money > $config['max_cash']) 
        {
            return returnBad("最大提现金额 ".$config['max_cash'].'元~', 311);
        }

        if($config['max_cash'] && $money > $config['max_cash']) 
        {
            return returnBad("最大提现金额 ".$config['max_cash'].'元~', 312);
        }

        $total_money = M('withdraw_cumulative')->sum('money');
        if($config['count_cash'] && ($total_money + $money) >= $config['count_cash']) 
        {
            return returnBad("今日系统可以提现金额 ".$config['count_cash']-$total_money.'元~', 313);
        }

        if($config['cash_times'] && $cumulative['count'] >= $config['cash_times']) 
        {
            return returnBad("今日暂无可提现数次~", 314);
        }

        $taxfee = 0;
        if($config['service_ratio'] && $money < $config['cash_card']) 
        {
            $taxfee = bcmul($money,$config['service_ratio']/100,2);

            if($config['min_service_money'] && $taxfee < $config['min_service_money']){
                $taxfee = $config['min_service_money'];
            }

            if($config['max_service_money'] && $taxfee > $config['max_service_money']){
                $taxfee = $config['max_service_money'];
            }
        }

        $pay_code = 'TX'.date('ymdHis') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);

        $data = [
            'user_id'   => $user['user_id'],
            'type'      => $type,
            'money'     => $money,
            'taxfee'    => $taxfee,
            'status'    => 0,
            'pay_code'  => $pay_code,
            'realname'  => $bank['card_name'],
            'bank_name' => $bank['bank_name'],
            'bank_card' => $bank['card_num'],
            'bank_type' => $bank['type'],
            'create_time' => time(),
        ];

        $id = Db::name('withdrawals')->insertGetId($data);
        
        accountLog($user['user_id'], -$money, 0, '申请提现余额', 0, $id, $pay_code);

        return returnOK("申请成功，待审核通过~");
    }

}