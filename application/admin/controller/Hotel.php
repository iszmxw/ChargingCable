<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 当燃
 * 拼团控制器
 * Date: 2016-06-09
 */

namespace app\admin\controller;

use app\common\model\Shopper;
use app\common\model\UserLabel;
use think\AjaxPage;
use think\Loader;
use think\Db;
use think\Page;
//分佣规则设置
class Hotel extends Base
{

    //酒店列表管理
    public function lists(){

        $timegap = urldecode(I('timegap'));
        $map = array();
        if ($timegap) {
            $gap = explode(',', $timegap);
            $begin = $gap[0];
            $end = $gap[1];
            $map['time'] = array('between', array(strtotime($begin), strtotime($end)));
            $this->assign('begin', $begin);
            $this->assign('end', $end);
        }
        $count = M('lc_hotel')->where($map)->count();
        $page = new Page($count,10);
        $lists = M('lc_hotel')->where($map)->order('time desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        if($lists){
            foreach($lists as $k=>$v){
                $lists[$k]['user_id'] = M("users")->where(["user_id"=>$v['user_id']])->getField("nickname");
            }
        }

        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    //录入酒店
    public function add_hotel(){
        if (IS_POST) {
            //酒店录入
            $post = I('post.');
            $name = $post['name'];//酒店名称
            if(empty($name)){
                $this->ajaxReturn(['status' => 0, 'msg' => "请填写酒店名称！！"]);
            }


            $province_id = $post['province_id'];//酒店位置
            if(empty($province_id)){
                $this->ajaxReturn(['status' => 0, 'msg' => "请选择酒店位置！！"]);
            }
            $user_id = $post['user_id'];//酒店管理员
            if(empty($user_id)){
                $this->ajaxReturn(['status' => 0, 'msg' => "请选择酒店管理员！！"]);
            }
            $lat = $post['lat'];//经度
            $lng = $post['lng'];//维度
            if(empty($lat) || empty($lng)){
                $this->ajaxReturn(['status' => 0, 'msg' => "请定位酒店经纬度！！"]);
            }

            if(!$post['thumb'])
            {
                $this->ajaxReturn(['status' => 0, 'msg' => "请上传酒店图片！！"]);
            }

            //判断酒店是否已经存在
            $hotel = M("lc_hotel")->where(['name'=>$name,'province_id'=>$province_id,'city_id'=>$post['city_id'],'area_id'=>$post['area_id']])->count();
            if($hotel >0 && empty($post['id'])){
                exit($this->error('酒店重复！！'));
            }
            $post['time'] = time();
            $post['admin'] =  M("admin")->where(['admin_id'=>session('admin_id')])->getField('user_name');
            if($province_id){
                $post['province_name'] =  M("region")->where(['id'=>$province_id])->getField('name');
            }
            if($post['city_id']){
                $post['city_name'] =  M("region")->where(['id'=>$post['city_id']])->getField('name');
            }
            if($post['area_id']){
                $post['area_name'] =  M("region")->where(['id'=>$post['area_id']])->getField('name');
            }
            if($post['id']){
                M("lc_hotel")->where(['id'=>$post['id']])->save($post);
                $this->ajaxReturn(['status' => 1, 'msg' => "操作成功", 'url' => U("Admin/Hotel/lists")]);
            }else{
                M("lc_hotel")->add($post);
                $this->ajaxReturn(['status' => 1, 'msg' => "操作成功", 'url' => U("Admin/Hotel/add_hotel")]);
            }


        }
        $id = I("get.id");
        if($id){
          $date =  M("lc_hotel")->where(['id'=>$id])->find();
        }
        $p = M('region')->where(array('parent_id'=>0,'level'=> 1))->select();
        $c = M('region')->where( array('level'=> 2))->select();
        $d = M('region')->where(array('level'=> 3))->select();
        // $user = M("users")->field("user_id,nickname")->select();
        $user= M("users")->where('user_id',$date['user_id'])->field("user_id,nickname")->find();
        $this->assign('date',$date);
        $this->assign('user',$user);
        $this->assign('province',$p);
        $this->assign('city',$c);
        $this->assign('district',$d);


        return $this->fetch();

    }

    //删除酒店列表
    public function deleteType()
    {
        $id = input('id/d');
        if(empty($id)){
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
        }
        $GoodsType = M("lc_hotel");
        $goods_type = $GoodsType->where(['id'=>$id])->delete();
        $this->ajaxReturn(['status' => 1, 'msg' => '删除成功']);
    }

    // 搜索酒店管理员
    public function type()
    {
        
        return $this->fetch();
    }

    public function ajaxindex(){
        $keywords = I('keywords');
        $page = I('page');
        $condition['level'] = 9;
        if($keywords)
        {
            $condition['nickname|mobile'] = ['like', "%$keywords%"];
        }
        
       
        $count = M('users')->where($condition)->count();
        $Page = new AjaxPage($count, 10);

        $userList =  M('users')->where($condition)->limit($Page->firstRow . ',' . $Page->listRows)->field('user_id,nickname,mobile_validated,mobile,head_pic')->select();
        $Page = new AjaxPage($count, 10);

        $show = $Page->show();
        
        $this->assign('userList', $userList);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }


    // 搜索酒店管理员
    public function location()
    {
        
        return $this->fetch();
    }
}