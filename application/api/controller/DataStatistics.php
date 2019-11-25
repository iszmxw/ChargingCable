<?php
/**
 --------------------------------------------------
 空间类型   分润数据统计
 --------------------------------------------------
 Copyright(c) 2017 时代万网 www.agewnet.com
 --------------------------------------------------
 开发人员: 玩味  <729167563@qq.com>
 --------------------------------------------------

 */
namespace app\api\controller;


use app\admin\logic\SiteLogic;

use Think\Controller;
use think\Page;
use think\Db;
class DataStatistics extends Base{


    /**
     * [action 会员分润统计]
     * @return [type] [description]
     */
    public function action(){

        $user = $this->user;
       
        $a_son_id = Db::name('user_group')->where('user_id',$user['user_id'])->value('a_son_id');

        // 团队总金额
        $team_money = Db::name('users')->where('user_id','in',$a_son_id)->sum('distribut_money');

        $team_money = $team_money ? $team_money : '0.00' ;

        $where['deleted'] = 0;
        $where['order_status'] = 4;
        $where['user_id'] = ['in',$a_son_id];
        // 订单完成数量
        $team_finish_total = Db::name('order')->where($where)->count();
        

        $where['order_status'] = ['in',[1,2]];
       
        // 订单进行中数量
        $team_underway_total = Db::name('order')->where($where)->count();
       
        return returnOk(['distribut_money'=>$user['distribut_money'], 'team_money'=>$team_money, 'team_finish_total'=>$team_finish_total, 'team_underway_total'=>$team_underway_total]);

    }

}