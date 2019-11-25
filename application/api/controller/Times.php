<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/26
 * Time: 8:54
 */

namespace app\api\controller;
use think\Db;

//定时任务
class Times{
    public function save_level()
    {
        //1,晋升合伙人身份
        //1.1 购买100台机器，20000维护卡
        $user = M("users")->where("pay_currency>19999 AND level<3")->field("user_id,pay_currency,e_repair_num,e_unbound_num,e_use_num,level")->select();
        if($user){
            foreach($user as $k=>$v){
              $num = $v['e_repair_num'] + $v['e_unbound_num'] + $v['e_use_num'];
              if($num>99){
                  M("users")->where(['user_id'=>$v['user_id']])->save(['level'=>3]);
              }
            }
        }
        //1.2团队购买500台机器，直推十个有效客户
        $users = M("users")->where('level<3')->field("user_id,pay_currency,e_repair_num,e_unbound_num,e_use_num,level")->select();
        if($users){
            foreach($users as $kd=>$vd){
                //十个有效客户
                $id = $vd['user_id'];
                $member = M("users")->where("first_leader='{$id}' AND (e_use_num>0 OR e_repair_num>0)")->count();
                if($member>9){
                    //查找团队是否有购买500台机器
                    $groupu = M("user_group")->where(['user_id'=>$id])->field("a_son_id")->find();
                    //查找所有孩子的购买机器数量
                    if($groupu['a_son_id']){
                      $arr = explode(",",$groupu['a_son_id']);
                      if($arr){
                          $num = 0;
                          foreach($arr as $ke=>$ve){
                              $us = M("users")->where(['user_id'=>$ve])->field("e_repair_num,e_unbound_num,e_use_num")->find();
                              $num = $us['e_repair_num'] + $us['e_unbound_num'] + $us['e_use_num'] + $num;
                          }
                          if($num > 499){
                              M("users")->where(['user_id'=>$id])->save(['level'=>3]);
                          }
                      }

                    }
                }
            }
        }
        //2.晋升店长身份，200维护卡
  /*      $user_shop = M("users")->where("level=1")->field("user_id,pay_currency,level")->select();
        if($user_shop){
            foreach($user_shop as $ka=>$va){
                if($va['pay_currency']>199){
                    M("users")->where(['user_id'=>$va['user_id']])->save(['level'=>2]);
                }
            }
        }*/
        //3.合伙人降级临时合伙人升降级处理----购买100台机器，20000维护卡
        $user_he = M("users")->where("level=3 OR level=10")->field("user_id,level,pay_currency,e_repair_num,e_unbound_num,e_use_num,end_time")->select();
        if($user_he){
            foreach($user_he as $kb=>$vb){
                $num = $vb['e_repair_num'] + $vb['e_unbound_num'] + $vb['e_use_num'];
                if($vb['level']==10){//临时合伙人身份
                  //判断临时身份是否过期
                    $time = time();
                    if($time > $vb['end_time']) {
                        if ($num < 99 || $vb['pay_currency'] < 20000) {
                            M("users")->where(['user_id' => $vb['user_id']])->save(['level' => 2]);
                        }
                    }
                }else{//真正的合伙人身份
                    if ($num < 99 || $vb['pay_currency'] < 20000) {
                        M("users")->where(['user_id' => $vb['user_id']])->save(['level' => 2]);
                    }
                }
            }
        }
        //4.店长降级处理-200维护卡
        $user_dc = M("users")->where("level=2")->field("user_id,level,pay_currency")->select();
        if($user_dc){
            foreach($user_dc as $kc=>$vc){
                if($vc['pay_currency']<200){
                    M("users")->where(['user_id' => $vc['user_id']])->save(['level' => 1]);
                }
            }
        }
        M('withdraw_cumulative')->where('1=1')->update(['count'=>0,'money'=>0]);
        
    }

    }