<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: IT宇宙人      
 * 
 * Date: 2016-03-09
 */

namespace app\admin\controller;
use think\Page;
use app\admin\logic\GoodsLogic;
use think\Db;

class Distribut extends Base {
    
    /*
     * 初始化操作
     */
    public function _initialize() {
       parent::_initialize();
    }    
    
    /**
     * 分销树状关系
     */
    public function tree(){                
        $where = 'is_distribut = 0 and first_leader = 0';
        if($this->request->param('user_id'))
            $where = "user_id = '{$this->request->param('user_id')}'";
        
        $list = M('users')->where($where)->select();        
        $this->assign('list',$list);
        return $this->fetch();
    }
 
    /**
     * 分销商列表
     */
    public function distributor_list(){
    	$condition['is_distribut']  = 0;
    	$nickname = trim(I('nickname'));
    	$user_id = trim(I('user_id'));
    	if(!empty($nickname)){
    		$condition['nickname'] = array('like',"%$nickname%");
    	}
        if(!empty($user_id)){
            $condition['user_id'] = array('like',"%$user_id%");
        }
    	$count = M('users')->where($condition)->count();
    	$Page = new Page($count,10);
    	$show = $Page->show();
    	$user_list = M('users')->where($condition)->order('distribut_money DESC')->limit($Page->firstRow.','.$Page->listRows)->select();
    	foreach ($user_list as $k=>$val){
    		$user_list[$k]['fisrt_leader'] = M('users')->where(array('first_leader'=>$val['user_id']))->count();
    		$user_list[$k]['second_leader'] = M('users')->where(array('second_leader'=>$val['user_id']))->count();
    		$user_list[$k]['third_leader'] = M('users')->where(array('third_leader'=>$val['user_id']))->count();
    		$user_list[$k]['lower_sum'] = $user_list[$k]['fisrt_leader'] +$user_list[$k]['second_leader'] + $user_list[$k]['third_leader'];
    	}
    	$this->assign('page',$show);
    	$this->assign('pager',$Page);
    	$this->assign('user_list',$user_list);
    	return $this->fetch();
    }
    
    /**
     * 分销设置
     */
    public function set(){                       
        header("Location:".U('Admin/System/index',array('inc_type'=>'distribut')));
        exit;
    }
    
    public function goods_list(){
    	$GoodsLogic = new GoodsLogic();
    	$brandList = $GoodsLogic->getSortBrands();
    	$categoryList = $GoodsLogic->getSortCategory();
    	$this->assign('categoryList',$categoryList);
    	$this->assign('brandList',$brandList);
    	$where = ' commission > 0 ';
    	$cat_id = I('cat_id/d');
        $bind = array();
    	if($cat_id > 0)
    	{
    		$grandson_ids = getCatGrandson($cat_id);
    		$where .= " and cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
    	}
    	$key_word = I('key_word') ? trim(I('key_word')) : '';
    	if($key_word)
    	{
    		$where = "$where and (goods_name like :key_word1 or goods_sn like :key_word2)" ;
            $bind['key_word1'] = "%$key_word%";
            $bind['key_word2'] = "%$key_word%";
    	}
        $brand_id = I('brand_id');
        if($brand_id){
            $where = "$where and brand_id = :brand_id";
            $bind['brand_id'] = $brand_id;
        }
    	$model = M('Goods');
    	$count = $model->where($where)->bind($bind)->count();
    	$Page  = new Page($count,10);
    	$show = $Page->show();
    	$goodsList = $model->where($where)->bind($bind)->order('sales_sum desc')->limit($Page->firstRow.','.$Page->listRows)->select();
        $catList = D('goods_category')->select();
        $catList = convert_arr_key($catList, 'id');
        $this->assign('catList',$catList);
        $this->assign('pager',$Page);
    	$this->assign('goodsList',$goodsList);
    	$this->assign('page',$show);
    	return $this->fetch();
    }
 

    
    /**
     * 分成记录
     */
    public function rebate_log()
    { 


        $model = M("shou_log");
        $type = I('type');
        $user_id = I('user_id/d');
        $order_sn = I('order_sn');
        $time = I('time');
        $time = $time  ? $time  : date('Y-m-d',strtotime('-1 year')).' - '.date('Y-m-d',strtotime('+1 day'));

        $time2 = explode(' - ',$time);
        $where = " time >= '".strtotime($time2[0])."' and time <= '".strtotime($time2[1])."' ";
        if($type === '0' || $type > 0)
            $where .= " and type = $type ";
        $user_id && $where .= " and user_id = $user_id ";
        $order_sn && $where .= " and order_sn like '%{$order_sn}%' ";

        $count = $model->where($where)->count();
        $Page  = new Page($count,16);
        $list = $model->where($where)->order("id desc")->limit($Page->firstRow.','.$Page->listRows)->select();
        if(!empty($list)){
            $get_user_id = get_arr_column($list, 'user_id'); // 获佣用户
            $pay_user_id = get_arr_column($list, 'pay_user_id'); //购买用户
            $user_id_arr = array_merge($get_user_id,$pay_user_id);
            $user_arr = M('users')->where("user_id in (".  implode(',', $user_id_arr).")")->getField('user_id,mobile,nickname,email');
            $this->assign('user_arr',$user_arr);
        }
        $this->assign('time',$time);
        $show  = $Page->show();
        $this->assign('show',$show);
        $this->assign('list',$list);
        C('TOKEN_ON',false);
        return $this->fetch();
    }
    
    /**
     * 获取某个人下级元素
     */    
    public  function ajax_lower()
    {
        $id = $this->request->param('id');
        $list = M('users')->where("first_leader =".$id)->select();
        $this->assign('list',$list);
        return $this->fetch();
    }
    
    /**
     * 修改编辑 分成 
     */
    public  function editRebate(){        
        $id = I('id');
        $rebate_log = DB::name('rebate_log')->where('id',$id)->find();
        if (IS_POST) {
            $data = I('post.');
            // 如果是确定分成 将金额打入分佣用户余额
            if ($data['status'] == 3 && $rebate_log['status'] != 3) {
                accountLog($data['user_id'], $rebate_log['money'], 0, "订单:{$rebate_log['order_sn']}分佣", $rebate_log['money']);
            }
            DB::name('rebate_log')->update($data);
            $this->success("操作成功!!!", U('Admin/Distribut/rebate_log'));
            exit;
        }                      
       
       $user = M('users')->where("user_id = {$rebate_log[user_id]}")->find();       
            
       if($user['nickname'])        
           $rebate_log['user_name'] = $user['nickname'];
       elseif($user['email'])        
           $rebate_log['user_name'] = $user['email'];
       elseif($user['mobile'])        
           $rebate_log['user_name'] = $user['mobile'];            
       
       $this->assign('user',$user);
       $this->assign('rebate_log',$rebate_log);
       return $this->fetch();
    }


    public function reward_month(){
        $users = Db::name("users")->where(["level"=>7, "first_leader"=>["neq",""]])->getField("user_id,first_leader,nickname");
        $where["status"]=3;
        $firstday=mktime(0,0,0,date('m'),1,date('Y'));
        $lastday=mktime(23,59,59,date('m'),date('t'),date('Y'));
        $where["confirm_time"]=[">=",$firstday];
        $where["confirm_time"]=[$where["confirm_time"],["<=",$lastday]];
        $count = Db::name("rebate_log")->where($where)->where(["type"=>3])->count();
        if($count){
            $this->error("本月已经完成上月分成");
        }

        $firstday = strtotime(date('Y-m-01 00:00:00', strtotime('-1 month')));
        $lastday = strtotime(date('Y-m-t 23:59:59', strtotime('-1 month')));
        $where["confirm_time"]=[">=",$firstday];
        $where["confirm_time"]=[$where["confirm_time"],["<=",$lastday]];
        $where["type"]=1;
        foreach ($users as $k => $v){
            $where["user_id"]=$k;
            $money = Db::name("rebate_log")->where($where)->sum("money");//获取上月总佣金
            if($money>1){
                $income = $money * 0.03;
                $data = array(
                    'user_id' =>$v['first_leader'],
                    'buy_user_id'=>$v['user_id'],
                    'nickname'=>$v['nickname'],
                    'goods_price' => $money,
                    'money' => $income,
                    'level' => 2,
                    'create_time' => time(),
                    'confirm_time' => time(),
                    'status' => 3,
                    'type' => 3,
                    'detail' => "战略合作伙伴专享上月业绩奖励",
                );
                M('rebate_log')->add($data);
                /* 插入帐户变动记录 */
                $account_log = array(
                    'user_id'       => $v['first_leader'],
                    'user_money'    => $income,
                    'change_time'   => time(),
                    'desc'   => "战略合作伙伴专享上月业绩奖励",
                );
                M('account_log')->add($account_log);
                Db::name('users')->where(["user_id"=> $v['first_leader']])->save(["distribut_money"=>['exp','distribut_money+'.$income]]);
            }
        }
        $this->success("操作成功!!!", U('Admin/Distribut/rebate_log'));
    }
            


    /**
     * 使用干衣机分润
     */
    public function employ_log()
    { 


        $model = M("can_general");
       
        $user_id = I('user_id/d');
        $order_sn = I('order_sn');
       
        $start_time = I('start_time');
        $end_time = I('end_time');

        if($start_time){
            $time =$start_time.' * '. $end_time;
        }else{
            $time = date('Y-m-d',strtotime('-1 year')).' * '.date('Y-m-d',strtotime('+1 day'));
        }

        $time2 = explode(' * ',$time);
        if($time2)
        {
	        $where = " o.createtime >= '".strtotime($time2[0])."' and o.createtime <= '".strtotime($time2[1])."' ";
        }
      
        $user_id && $where .= " and o.user_id = $user_id ";
        $order_sn && $where .= " and o.ordersn like '{$order_sn}%' ";

        // $count = $model->where($where)->count();
        $count = M('package_order')->alias('o')->join('sd_can_general l','o.id = l.orderid','left')->where($where)->count();
        $Page  = new Page($count,16);

        // $list = $model->where($where)->order("createtime desc")->limit($Page->firstRow.','.$Page->listRows)->select();
        $list = M('package_order')->alias('o')->join('sd_can_general l','o.id = l.orderid','left')->where($where)->field('o.*,l.share_price,l.share_log,l.destroy_log')->select();
        
        foreach ($list as $key => $val) {
            // $list[$key]['status'] = M('package_order')->where('id',$val['orderid'])->value('status');
            $list[$key]['e_no'] = M('equipment')->where('id',$val['equipment_id'])->value('e_no');
        }

        $price_data = M('package_order')->alias('o')->join('sd_can_general l','o.id = l.orderid','left')->where($where)->field('SUM(l.price) as price,SUM(l.share_price) as share_price,SUM(l.destroy_price) as destroy_price')->find();

        //$price_data = $model->where($where)->field('SUM(price) as price,SUM(share_price) as share_price,SUM(destroy_price) as destroy_price')->find();
       
        $this->assign('time2',$time2);
        $this->assign('count',$count);
        $this->assign('price_data',$price_data);
        $show  = $Page->show();
        $this->assign('show',$show);
        $this->assign('list',$list);
        $this->assign('order_sn',$order_sn);
        $this->assign('user_id',$user_id);
        C('TOKEN_ON',false);
        return $this->fetch();
    }

    // 搜索酒店管理员
    public function type()
    {
        $orderid = I('id');
        $share_log = M('can_general')->where('orderid',$orderid)->value('share_log');
        $share_log = unserialize($share_log);
        // 1投资人2酒店3技术4运营5合伙人6直推7间推
        $type = ['投资人分润','酒店分润','技术分润','运营分润','合伙人分润','直推分润','间推分润'];
        foreach ($share_log as $key => $val) {
            $member = Db::name('users')->where('user_id',$val['user_id'])->field('nickname,head_pic')->find();
            $share_log[$key]['head_pic'] = $member['head_pic'];
            $share_log[$key]['nickname'] = $member['nickname'];
            $share_log[$key]['type'] = $type[$val['type']-1];
        }

        $this->assign('userList',$share_log);
        return $this->fetch();
    }

    // 搜索酒店管理员
    public function destroytype()
    {
        $orderid = I('id');
        $share_log = M('can_general')->where('orderid',$orderid)->value('destroy_log');
        $share_log = unserialize($share_log);
        // 1投资人2酒店3技术4运营5合伙人6直推7间推
        $type = ['投资人分润','酒店分润','技术分润','运营分润','合伙人分润','直推分润','间推分润','市场分润'];
        foreach ($share_log as $key => $val) {
            $share_log[$key]['type'] = $type[$val['type']-1];
        }

        $this->assign('userList',$share_log);
        return $this->fetch();
    }


    /**
     * [employ_analysis 使用分润列表]
     * @return [type] [description]
     */
    public function employ_list()  
    {
        

        // $type = ['投资人分润','酒店分润','技术分润','运营分润','合伙人分润','直推分润','间推分润'];

        $hotel = Db::name('lc_hotel')->column('id,name','id');

        $count = Db::name('can_commission')->where($where)->group($group)->count();

        $Page = new Page($count,20);

        $start_time = I('start_time');
        $end_time = I('end_time');


        if($start_time){
            $time =$start_time.' * '. $end_time;
        }else{
            $time = date('Y-m-d',strtotime('-1 year')).' * '.date('Y-m-d',strtotime('+1 day'));
        }

        $time2 = explode(' * ',$time);

        $where = " createtime >= '".strtotime($time2[0])."' and createtime <= '".strtotime($time2[1])."' ";

        $type = I('type');
        if($type) 
        {
            $where .= " and type = {$type}";
        }

        $user_id = I('user_id');
        if($user_id) 
        {
            $where .= " and user_id = {$user_id}";
        }
        $order_list = Db::name('can_commission')->where($where)->select();

        foreach ($order_list as $key => &$val) {
            
            $val['employ_user'] = M('users')->where('user_id',$val['employ_id'])->field('nickname,mobile,head_pic')->find();

            $val['user'] = M('users')->where('user_id',$val['user_id'])->field('nickname,mobile,head_pic')->find();
     
            $order_list[$key]['e_no']  = $val['e_no'];
      
            $order_list[$key]['hotel_name'] = $hotel[$val['hotel_id']];

            $val['ordersn'] = M('package_order')->where('id',$val['orderid'])->value('ordersn');
        }

        unset($val);
        
        $this->assign('time2',$time2);
        $this->assign('order_list',$order_list);
        $this->assign('count',$count);
        $this->assign('page',$Page);
        $this->assign('price',$price);
        $this->assign('type',$type);
        $this->assign('user_id',$user_id);

        return $this->fetch();
    }

}