<?php
/**
 * --------------------------------------------------
 * 空间类型   商品模型
 * --------------------------------------------------
 * Copyright(c) 2017 时代万网 www.agewnet.com
 * --------------------------------------------------
 * 开发人员: lichao  <729167563@qq.com>
 * --------------------------------------------------
 */
namespace app\api\logic;

use app\admin\controller\User;
use app\common\model\Users;
use think\Db;

class UserLogic
{
    /**
     * 判断用户是否认证
     * @param $userid
     * @return bool
     * @throws \think\exception\DbException
     */
    public function is_realname($userid)
    {
        $userModel = new Users();
        if ($userModel->get($userid)->is_realname == 1){
            return FALSE;
        }else{
            return TRUE;
        }
    }

    /**
     * 判断当前设备数量是否大于需转让的设备
     * @param $userid
     * @param $enum
     * @return bool
     * @throws \think\exception\DbException
     */
    public function getEunboundNum($userid, $enum, $id_no)
    {
        $userModel = new Users();
        $data = $userModel->get($userid);
        $data_no = $userModel->get(['user_no'=>$id_no]);
        //dump($data_no);die;
        $unbound_num = bcsub($data->e_unbound_num,$enum);//相减
        

        if ($unbound_num < 0) 
        {
            return FALSE;
        }else{
            $unbound_num_no = bcadd($data_no->e_unbound_num,$enum);//相加
            $arr = [$unbound_num_no,$unbound_num];
            return $arr;
        }
    }

    /*个人中心首页*/
    public function center($post)
    {
        $openid = $post['openid'];
        $field = "id,username,portrait,jifen,balance,type";
        $user = M("user")->where(['openid' => $openid])->field($field)->find();
        $time = time();
        $where['user_id'] = $user['id'];
        $where['use_time'] = array('lt', $time);
        $where['send_time'] = array('gt', $time);
        $user['coupon'] = M("coupon_list")->alias("c")
            ->join('zk_coupon as p on c.cid = p.id')
            ->where($where)
            ->count();
        return $user;
    }


    /*我的团队*/
    public function mytrun($post)
    {
        $uid = $post['id'];
        $page = !empty($post['page']) ? $post['page'] : 1;
        $umodel = M("user");
        $filed = "id,username,add_time,province,city";
        $count = $umodel->where(['fid' => $uid])->count();
        $user = $umodel->where(['fid' => $uid])->field($filed)->limit(($page - 1) * 10, 10)->select();
        return $data = array(
            'count' => $count,
            'user_list' => $user,
        );
    }

    /*我的积分*/
    public function mypoints($post)
    {
        $uid = $post['id'];
        $page = !empty($post['page']) ? $post['page'] : 1;
        $model = M("points");
        $count = "0.00";
        $filed = "points,time,type";
        $points = $model->where(['uid' => $uid])->field($filed)->limit(($page - 1) * 10, 10)->select();
        foreach ($points as $k => $v) {
            if ($v['type'] == 1) {
                $points[$k]['points'] = '+' . $v['points'];
            }
            if ($v['type'] == 2) {
                $points[$k]['points'] = '-' . $v['points'];
            }
            $count = $count + $points[$k]['points'];
        }
        unset($k, $v);
        return $data = array(
            'count' => $count,
            'user_list' => $points,
        );
    }

    /*我的佣金记录*/
    public function mycommission($post)
    {
        $uid = $post['id'];
        $page = !empty($post['page']) ? $post['page'] : 1;
        $model = M("commission");
        $filed = "money,time,type";
        $where['uid'] = $uid;
        $type = $post['type'];
        if ($type == 2) {
            $where['type'] = 2;
        }
        $count = "0.00";
        $commission = $model->where($where)->field($filed)->limit(($page - 1) * 10, 10)->select();
        foreach ($commission as $k => $v) {
            if ($v['type'] == 1) {
                $commission[$k]['money'] = '+' . $v['money'];
            }
            if ($v['type'] == 2) {
                $commission[$k]['money'] = '-' . $v['money'];
            }
            $count = $count + $commission[$k]['money'];
        }
        unset($k, $v);
        return $data = array(
            'count' => $count,
            'user_list' => $commission,
        );
    }


    /*佣金提现获取数据*/
    public function commission_new($post)
    {
        $uid = $post['id'];
        $commission = M("setting")->where(array('k' => 'commission'))->field('v')->find();
        $balance = M("user")->where(['id' => $uid])->field('balance')->find();
        return $data = array(
            'commission' => $commission['v'],
            'balance' => $balance['balance']
        );
    }

    /*佣金提现提交*/
    public function commission_sub($post)
    {
        $uid = $post['id'];
        $number = $post['number'];
        $truename = $post['truename'];
        $money = $post['money'];
        $model = M("getcash");
        if (empty($truename)) {
            ajaxReturn(['code' => '302', 'msg' => '姓名不能为空', 'data' => $truename]);
            exit();
        }
        if (!empty($number)) {
            $re = $this->check_bankCard($number);
            if (!$re) {
                ajaxReturn(['code' => '302', 'msg' => '银行卡号无效', 'data' => $number]);
                exit();
            }
        } else {
            ajaxReturn(['code' => '302', 'msg' => '银行卡号不能为空', 'data' => $number]);
            exit();
        }
        $arr = array(
            'truename' => $truename,//姓名
            'addtime' => time(),            //时间
            'uid' => $uid,                //用户id
            'money' => $money,            //提现金额
            'number' => $number            //银行卡号
        );
        $log = $model->where(['uid' => $uid, 'status' => 0])->find();
        if ($log) {
            ajaxReturn(['code' => '302', 'msg' => '已存在一笔提现未处理', 'data' => $log['id']]);
            exit();
        }
        $data = $model->add($arr);
        return $data;
    }


    /*我的会员订单*/
    public function myuser_order($post)
    {
        $uid = $post['id'];
        $page = !empty($post['page']) ? $post['page'] : 1;
        $commission = M('commission')->where(['uid' => $uid, 'type' => 1])->select();
        $count = 0.00;
        foreach ($commission as $k => $v) {
            $count = $count + $commission[$k]['money'];                                     //获得佣金
        }
        unset($k, $v);
        $order_money = 0;
        $order_num = 0;
        $order_nopay = 0;
        $order_yespay = 0;
        $order_refund = 0;
        $where['u.fid'] = $uid;
        $where = "u.fid = $uid";
        if (!empty($post['state'])) {
            $state = $post['state'];
            $where .= " and o.`state`=$state";
        } else {
            $where .= " and (o.`state`=1 or o.`state`=2 or o.`state`=4)";
        }

        $field = "o.`add_time`,o.`state`,o.`allmoney`,u.`username`,u.`portrait`";
        $order = M("order")->alias("o")
            ->join("zk_user as u on u.`id` = o.`user_id`")
            ->where($where)
            ->order("o.`add_time` desc")
            ->field($field)
            ->limit(($page - 1) * 10, 10)
            ->select();
        $order_num = M("order")->alias("o")//所有订单数量
        ->join("zk_user as u on u.`id` = o.`user_id`")
            ->where($where)
            ->count();
        $ratearr = M("rate")->where(['rate_id' => 1])->find();
        $rate = $ratearr['rate'];
        foreach ($order as $k => $v) {
            $num = $rate / 100 * $v['allmoney'];
            $order[$k]['rate'] = sprintf("%.2f", substr(sprintf("%.3f", $num), 0, -2));
            if ($v['state'] == 1) {
                $order_nopay++;                                                        //未支付订单数量
            } elseif ($v['state'] == 2) {
                $order_yespay++;                                                        //已支付订单数量
            } elseif ($v['state'] == 4) {
                $order_refund++;                                                        //确认收货订单数量
            }
            $order_money = $order_money + $v['allmoney'];                                   //订单总额
        }
        unset($k, $v);
        return $data = array(
            'money' => $count,
            'order_num' => $order_num,
            'order_nopay' => $order_nopay,
            'order_yespay' => $order_yespay,
            'order_refund' => $order_refund,
            'order_list' => !empty($order) ? $order : []
        );
    }

    /*验证银行卡号是否有效*/

    public function check_bankCard($card_number)
    {
        $arr_no = str_split($card_number);
        $last_n = $arr_no[count($arr_no) - 1];
        krsort($arr_no);
        $i = 1;
        $total = 0;
        foreach ($arr_no as $n) {
            if ($i % 2 == 0) {
                $ix = $n * 2;
                if ($ix >= 10) {
                    $nx = 1 + ($ix % 10);
                    $total += $nx;
                } else {
                    $total += $ix;
                }
            } else {
                $total += $n;
            }
            $i++;
        }
        $total -= $last_n;
        $x = 10 - ($total % 10);
        if ($x == $last_n) {
            return true;
        } else {
            return false;
        }
    }


    /*我的收藏列表*/
    public function my_collection($post)
    {
        $uid = $post['id'];
        $page = !empty($post['page']) ? $post['page'] : 1;
        $field = "g.id as goods_id,g.goods,g.price,g.shop_price,g.pic,g.number,c.id";
        $collection = M("collection")->alias("c")//所有订单数量
        ->join("zk_goods as g on g.`id` = c.`goods_id`")
            ->where(['c.uid' => $uid])
            ->field($field)
            ->limit(($page - 1) * 10, 10)
            ->select();
        foreach ($collection as $k => $v) {
            if ($v['pic']) {
                $collection[$k]['pic'] = 'http://' . $_SERVER['HTTP_HOST'] . __ROOT__ . $v['pic'];
            }
        }
        unset($k, $v);
        return $data = array(
            'list' => !empty($collection) ? $collection : []
        );
    }


    /*我的收藏-取消收藏*/
    public function no_collection($post)
    {
        $id = $post['id'];
        $del = M("collection")->where(['id' => $id])->delete();
        if ($del) {
            return 2;
        } else {
            return 1;
        }

    }

    /*获取用户地址*/
    public function getAddress($post)
    {
        $uid = $post['id'];
        $field = "id,consignee,tel,type,province,city,county,address";
        $address = M("address")->where(['user_id' => $uid])->field($field)->order("add_time desc")->select();
        foreach ($address as $k => $v) {
            $address[$k]['addre'] = $v['province'] . '' . $v['city'] . '' . $v['county'] . '' . $v[' '];
            unset($address[$k]['province'], $address[$k]['city'], $address[$k]['county'], $address[$k]['address']);
            $mobfore = substr($v['tel'], -4);
            $address[$k]['tel'] = substr($v['tel'], 0, 3) . '****' . substr($v['tel'], 7, strlen($v['tel']));
        }
        unset($v, $k);
        return $data = array(
            'list' => !empty($address) ? $address : []
        );
    }

    /*删除地址-设为默认地址*/
    public function del_addre($post)
    {
        $id = $post['id'];
        $uid = $post['uid'];
        $type = !empty($post['type']) ? $post['type'] : 1;
        if ($type == 1) {//删除
            $del = M('address')->where(['id' => $id])->delete();
            return $del;
        } else {//设为默认地址
            M('address')->where(['user_id' => $uid])->save(['type' => 0]);
            $data = M("address")->where(['id' => $id, 'user_id' => $uid])->save(['type' => 1]);
            return $data;
        }

    }

    /*获取省-市-区*/
    public function age_province($post)
    {
        $city = $post['province'];
        $county = $post['city'];
        if (!$city && !$county) {
            return M("area")->field("province as name")->group("province")->select();
        } elseif ($city && !$county) {
            return M("area")->where(['province' => $city])->field("city as name")->group("city")->select();
        } elseif (!$city && $county) {
            return M("area")->where(['city' => $county])->field("county as name")->group("county")->select();
        }
    }

    /*收货地址提交*/
    public function sub_address($post)
    {
        $add['user_id'] = $uid = $post['id'];
        $add['consignee'] = $post['consignee'];
        $add['city'] = $post['city'];
        $add['address'] = $post['address'];
        $add['province'] = $post['province'];
        $add['county'] = $post['county'];
        $add['tel'] = $post['tel'];
        $add['add_time'] = time();
        $add['addrcity'] = $add['province'] . $add['city'] . $add['county'] . $add['address'];
        if ($post['type'] == 1) {
            $addre = M("address")->where(['user_id' => $uid, 'type' => 1])->find();
            if (!$addre) {
                $add['type'] = 1;
            } else {
                $add['type'] = 0;
            }
            $adds = M("address")->add($add);
        } else {
            $adds = M("address")->where(array('id' => $post['address_id']))->save($add);
        }

        return $adds;
    }


    /*编辑收货地址获取原始信息*/
    public function edit_address($post)
    {
        $id = $post['id'];
        $field = "user_id,consignee,province,city,county,tel,address";
        $address = M("address")->where(['id' => $id, 'user_id' => $post['uid']])->field($field)->find();
        if (!$address) {
            ajaxReturn(['code' => '302', 'msg' => '地址不存在']);
            exit();
        } else {
            return $address;
        }

    }


    /*编辑设置个人信息*/
    public function edit_info($post)
    {
        $uid = $post['id'];
        $user = M("user")->where(['id' => $uid])->field("id,phone,truename,mycity")->find();
        return $user;
    }

    /*设置个人信息提交*/
    public function sub_info($post)
    {
        $uid = $post['id'];
        $fid = $post['fid'];
        $user = M('user')->where(['id'=>$uid])->field('fid,fname,type')->find();
        if($user && $user['type']<2){
            if($fid){
                $save['type'] =2;
                $save['up_time'] =time();
                $fuser = M('user')->where(['id'=>$fid])->field('id,username,fid,fname,type')->find();
                if($fuser && intval($fuser['type'])>2){
                    $save['fid'] = $fuser['id'];
                    $save['fname'] = $fuser['username'];
                    if($fuser['fid']){
                        $fuser2 = M('user')->where(['id'=>$fuser['fid']])->field('id,username,fid,fname,type')->find();
                        if($fuser2 && intval($fuser2['type'])==4){
                            $save['fid2'] = $fuser2['id'];
                            $save['fname2'] = $fuser2['username'];
                        }
                    }
                }
            }
        }
        if($post['phone']){
            $save['phone'] = $post['phone'];
        }
        if($post['truename']){
            $save['truename'] = $post['truename'];
        }
        if($post['mycity']){
            $save['mycity'] = $post['mycity'];
        }
        if($post['password']){
            $save['password'] = md5(trim($post['password']));
        }
        $user = M("user")->where(['id' => $uid])->save($save);
        return $user;

    }

    /*我的优惠券列表*/
    public function mycoupon($post)
    {
        $uid = $post['id'];
        $type = !empty($post['type']) ? $post['type'] : 1;
        $page = !empty($post['page']) ? $post['page'] : 1;
        $time = time();
        $field = "l.cid,l.user_id,c.name,c.money,c.type,c.condition,c.use_start_time,c.use_end_time";
        if ($type == 1) {//未使用
            $where['l.user_id'] = $uid;
            $where['c.use_start_time'] = array('lt', $time);
            $where['c.use_end_time'] = array('gt', $time);
            $where['l.status'] = 0;
        } elseif ($type == 2) {//已使用
            $where['l.user_id'] = $uid;
            $where['l.status'] = 1;
        } elseif ($type == 3) {//已过期
            $where['l.user_id'] = $uid;
            $where['l.status'] = array('neq', 1);
            $where['c.use_end_time'] = array('lt', $time);
        }
        $user = M("coupon_list")->alias("l")
            ->join("zk_coupon as c on c.id=l.cid")
            ->where($where)
            ->field($field)
            ->limit(($page - 1) * 10, 10)
            ->select();
        return $data = array('coupon_list' => $user);
    }

    public function getuser($openid)
    {
        $user = Db::name('users')->where(['openid' => $openid])->find();
        return $user;
    }
}