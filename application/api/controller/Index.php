<?php
/**
 --------------------------------------------------
 空间类型   商品控制器
 --------------------------------------------------
 Copyright(c) 2017 时代万网 www.agewnet.com
 --------------------------------------------------
 开发人员: lichao  <729167563@qq.com>
 --------------------------------------------------

 */
namespace app\api\controller;

use app\api\logic\ShopLogic;

class Index extends Base{
    /* 
     * 获取主页数据
     *  */
    public function index()
    {
        $post = $this->check_post();
        $shop = new ShopLogic();
        $data = $shop->getIndex($post);
        if($data){
            return returnOk($data);
        }else {
            return returnBad('获取商品数据失败',302);
        }
    }

  //查找酒店下所有设备
    public function my_hotel_detail(){
        $page = I('post.page',1);
        $user_id = I('post.user_id');
        if(empty($this->user_id)){
            return returnBad('登录超时请重新登录',302);
        }
        if(empty($user_id)){
            return returnBad('参数缺失！！',302);
        }
        $list = M("lc_equipment_number")->where(['j_user_id'=>$user_id])->field("hotel_name,time,number")->limit(($page - 1) * 10, 10)->select();
        $count = M("lc_equipment_number")->where(['j_user_id'=>$user_id])->count();
        if($list){
            foreach($list as $k=>$v){
                $list[$k]['time'] = date('Y-m-d H:i:s',$v['time']);
            }
        }
        $data = array(
            'list'=>$list,
            'count'=>$count
        );
        return returnOk($data);
    }

    public function withdrawals(){
        if(empty($this->user_id)){
            return returnBad('登录超时请重新登录',302);
        }
        $user_id =$this->user_id;
        if(empty($this->user_id)){
            return returnBad('登录超时请重新登录',302);
        }
        if(empty($user_id)){
            return returnBad('参数缺失！！',302);
        }
        //状态：-1拒绝申请0申请中1付款成功
        $list = M("withdrawals")->where(['user_id'=>$user_id])->field("user_id,money,realname,status,create_time,bank_card,type")->order("create_time desc")->select();
        $count = M("withdrawals")->where(['user_id'=>$user_id])->count();
        if($list){
            foreach($list as $k=>$v){
                $list[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
                $list[$k]['head_pic'] = M("users")->where(['user_id'=>$v['user_id']])->value("head_pic");
            }
        }
        $data = array(
            'list'=>$list,
            'count'=>$count
        );
        return returnOk($data);

    }



}