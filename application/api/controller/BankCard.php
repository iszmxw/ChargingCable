<?php
/**
 --------------------------------------------------
 空间类型   添加银行卡
 --------------------------------------------------
 Copyright(c) 2017 时代万网 www.agewnet.com
 --------------------------------------------------
 开发人员: 玩味  <729167563@qq.com>
 --------------------------------------------------

 */
namespace app\api\controller;

use Think\Controller;
use think\Page;
use think\Request;
use think\Db;
class BankCard extends Base{


    /**
     * [bank_list 获取银行卡列表]
     * @return [type] [description]
     */
    public function bank_list(){

        $bank_list =  Db::name('user_bank_card')->where('user_id', $this->user_id)->order('id desc')->select();

        foreach ($bank_list as $key => $value) {
            $bank_list[$key]['card_name'] = $this->substr_cut($value['card_name'],1,1);
            $bank_list[$key]['card_num'] = $this->substr_cut($value['card_num'],4,4);
        }
        return returnOk($bank_list);
    }


    /**
     * [add_bank 绑定银行卡]
     */
    public function add_bank(){

        $type = [
            1002 => '工商银行',1005 => '农业银行', 1026 => '中国银行', 1003 => '建设银行', 1001 => '招商银行', 1066 => '邮储银行', 1020 => '交通银行', 1004 => '浦发银行', 1006 => '民生银行', 1009 => '兴业银行', 1010 => '平安银行', 1021 => '中信银行', 1025 => '华夏银行', 1027 => '广发银行', 1022 => '光大银行', 4836 => '北京银行', 1056 => '宁波银行', 1024 => '上海银行'
        ];

        if(!IS_POST){
            return returnBad("请求方式错误",301);
        }

        $data = I('post.');
        $data['user_id'] = $this->user_id;
        $data['bank_name'] = $type[$data['type']];
        
        unset($data['openid']);

        if(!$data['card_name'])
        {
            return returnBad("请输入持卡人姓名",302);
        }

        if(!$data['card_num'])
        {
            return returnBad("请输入银行卡账号",303);
        }

        $strlen     = mb_strlen($data['card_num'], 'utf-8');
        if($strlen < 15 || !preg_match("/^\d*$/",$card_num) || $strlen > 19 )
        {
            return returnBad("请输入正确的银行卡账号",304);
        }

        if(!$data['type'])
        {
            return returnBad("请选择银行卡类型",305);
        }

        if(!$type[$data['type']])
        {
            return returnBad("银行卡类型错误",306);
        }

        $count = Db::name('user_bank_card')->where('user_id', $this->user_id)->where('card_num', $data['card_num'])->count();
        
        if($count && empty($data['id'])) 
        {
            return returnBad("请不要重复添加该银行卡",306);
        }

        $result = Db::name('user_bank_card')->update($data);

        $msg = $data['id'] ? '修改' : '添加' ;

        if($result)
        {
            return returnOk($msg.'成功');
        }

        return returnBad($msg."失败",307);
    }


    /**
     * [substr_cut 字符串中间 * 号代替]
     * @param  [type] $card_name [字符串]
     * @return [type]            [description]
     */
    public function substr_cut( $str, $start = 1, $end = 1 )
    {
        $strlen     = mb_strlen($str, 'utf-8');
        $firstStr   = mb_substr($str, 0, $start, 'utf-8');
        $lastStr    = mb_substr($str, -$start, $end, 'utf-8');
       
        if($strlen == 2) 
        {
            $str = $firstStr . str_repeat('*', mb_strlen($str, 'utf-8') - 1);
        }
        else if($strlen < 8) 
        {
            $str = $firstStr . str_repeat("*", 3) . $lastStr;
        }else 
        {
            $str = $firstStr .' '. str_repeat("*",4) .' '. str_repeat("*",4) .' '.' '. str_repeat("*",3) .' '. $lastStr;
        }
        return $str;
    }
}


