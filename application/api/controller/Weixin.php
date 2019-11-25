<?php
/**
--------------------------------------------------
空间类型   购买支付控制器
--------------------------------------------------
Copyright(c) 2017 时代万网 www.agewnet.com
--------------------------------------------------
开发人员: lichao  <729167563@qq.com>
--------------------------------------------------
 */
namespace app\api\controller;

use app\api\logic\PayLogic;
use app\api\model\Users;
use app\api\model\UserGroup;
use think\Controller;
use think\Log;
use think\Db;

class Weixin extends Controller{

    //根据code获取小程序openid
    public static function get_openid($code){

        $appid = C('APPID_AP');
        $secret = C('SECRET_AP');
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid=$appid&secret=$secret&js_code=$code&grant_type=authorization_code";
        $data = file_get_contents($url);
        $data = json_decode($data,true);

        if($data['openid']){

            $userinfo = M('users')->where((['openid'=>$data['openid']]))->find();
            
            if(!$userinfo){

                $res = M('users')->insert([
                    'head_pic'          => I('avatarUrl',''),
                    'nickname'          => I('nickName',''),
                    //'token'             => Token::makeUserToken(),
                    //'token_express'     => time()+config('secure.express'),
                    'openid'            => $data['openid'],
                    'oauth_child'       =>'mp',
                    'user_no'           => getUserNo(),
                    'reg_time'          => time(),
                ]);
            }

            echo json_encode(['code'=>200,'openid'=>$data['openid']]);
            
        }else{
            echo json_encode(['code'=>301,'msg'=>'请求openid失败']);
        }
    }

    // 网页授权登录获取 OpendId11
    public  function get_user_data($code)
    {

        $code = $_GET['code'];
        //通过code获得openid
        if (!isset($code)){
            //触发微信返回code码
            //$baseUrl = urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
            $baseUrl = urlencode($this->get_url());
            $url = $this->__CreateOauthUrlForCode($baseUrl); // 获取 code地址
            Header("Location: $url"); // 跳转到微信授权页面 需要用户确认登录的页面
            exit();
        } else {
            //上面获取到code后这里跳转回来
            $data = $this->getOpenidFromMp($code);//获取网页授权access_token和用户openid
            $data2 = $this->GetUserInfo($data['access_token'],$data['openid']);//获取微信用户信息
            
            $data2['access_token'] = $data['access_token'];//获取微信用户信息
            $data2['token_express'] = time()+7*24*3600;//获取微信用户信息
            return $data2;
        }
    }

    // 网页授权登录获取 OpendId11
    public  function GetOpenid($code,$pid)
    {
        $pid = $_GET['pid'];
        $code = $_GET['code'];
        if( false /*session('?openid')*/)
        {
            //绑定上下级关系
            if(!empty($pid))
            {
                //要绑定的父亲的信息
                $father = M('users')
                    ->where('user_id='.$pid)
                    ->field('user_id,first_leader,second_leader,third_leader,openid')
                    ->find();
                //有孩子的用户不能绑定上级，以免关系混乱
                $w = true;
                $u_now = db('users')
                    ->where('openid',session('openid'))
                    ->field('user_id,first_leader,second_leader,third_leader,openid')
                    ->find();

                if($u_now)
                {
                    //查询当前用户是否有下级
                    $is_has_child =M('users')->where('first_leader='.$u_now['user_id'])->field('user_id')->find();
                    if($is_has_child || $u_now['first_leader'])  $w = false;
                } else{
                    $w = false;
                }
                if($father && $pid!=$u_now['user_id'] && $w)
                {
                    $data['first_leader']   = $pid; //第一个上级
                    $data['second_leader']  = $father['first_leader'];  //第二个上级
                    $data['third_leader']   = $father['second_leader']; //第三个上级
                    //更新用户组
                    db('users')->where('openid',session('openid'))->save($data);
                }
                $res = db('users')->where(['openid'=>session('openid')])->find();
                //用户分销关系分组
                if( !empty($father) && $res)
                {
                    //查询分组表是否已存在记录
                    $userGroup = db('user_group')->where('user_id',$father['user_id'])->find();
                    if ($w){ //有上级与有下级的用户不能绑定，以免关系混乱
                        if($userGroup)
                        {
                            $a_son_id = explode(',',$userGroup['a_son_id']);
                            //排除子级重复添加与自己添加自己为下级
                            if ((!in_array($res['user_id'],$a_son_id)) && ($res['user_id'] != $res['first_leader']))
                            {
                                $user_group_data['f_son_id'] =  $userGroup['f_son_id'] . ',' . $res['user_id'];
                                $user_group_data['a_son_id'] =  $userGroup['a_son_id'] . ',' . $res['user_id'];
                                //存在就更新用户的子级
                                $ug_res =  db('user_group')->where('user_id',$res['first_leader'])->save($user_group_data);
                            }
                        } else {
                            //排除自己添加自己为下级
                            if ($father['user_id'] != $res['user_id']){
                                $user_group_data['user_id'] = $father['user_id'];
                                $user_group_data['f_son_id'] = $res['user_id'];
                                $user_group_data['a_son_id'] = $res['user_id'];
                                //不存在则添加
                                $ug_res = db('user_group')->insertGetId($user_group_data);
                            }
                        }
                    }else{
                        $ug_res = false;
                    }

                    //更新上级的所有儿子
                    if($ug_res != false)
                    {
                        $all_f = db('user_group')->select();
                        if($all_f)
                        {
                            $father_id = '';
                            foreach ($all_f as $fv)
                            {
                                $all_son_arr = explode(",",$fv['a_son_id']);
                                //更新当前绑定下级用户的所有上级的所有儿子
                                if(in_array($father['user_id'],$all_son_arr))
                                {
                                    $a_son_id = $fv['a_son_id'].','.$res['user_id'];
                                    db('user_group')->where(['user_id'=>$fv['user_id']])->setField('a_son_id',$a_son_id);
                                    $father_id .= ','.$fv['user_id'];
                                   file_put_contents('father.txt',json_encode([$father_id]));
                                }
                            }
                            Log::write('父级1：'.$father_id);
                            //如果是添加用户分销组，找到当前绑定下级用户的上级更新
                            if(!empty($father_id))
                            {
                                $father_id = ltrim($father_id,',');
                                db('user_group')
                                    ->where(['user_id'=>$father['user_id']])
                                    ->setField('father_id',$father_id);
                            }
                        }
                    }
                }
            }
            return session('openid');
        }
        //通过code获得openid
        if (!isset($code))
        {

            //触发微信返回code码
            //$baseUrl = urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
            $baseUrl = urlencode($this->get_url());
            $url = $this->__CreateOauthUrlForCode($baseUrl); // 获取 code地址
            
            Header('Location: '.$url.'?pid='.$pid); // 跳转到微信授权页面 需要用户确认登录的页面
            exit();
        } else {
            //上面获取到code后这里跳转回来
            $data = $this->getOpenidFromMp($code);//获取网页授权access_token和用户openid
            $green_power = M("lc_subcommission")->where(['id'=>1])->value("power");
            $data2 = $this->GetUserInfo($data['access_token'],$data['openid']);//获取微信用户信息

            $data['nickname']       = empty($data2['nickname']) ? '微信用户' : trim($data2['nickname']);
            $data['province']       = trim($data2['province']);
            $data['city']           = trim($data2['city']);
            $data['sex']            = $data2['sex'];
            $data['head_pic']       = $data2['headimgurl'];
            $data['subscribe']      = $data2['subscribe'];
            $data['oauth_child']    = 'mp';
            $data['token_express']  = time()+7*24*3600;
            $data['green_power']    =$green_power;
            $data2['token']         = $data['access_token'];
            $data['token']          = $data['access_token'];
            $data['user_no']        = getUserNo();
            $data2['head_pic']      = $data2['headimgurl']; //更新头像
            $data2['update_time']   = time(); //更新时间
            $data2['token_express'] = time()+7*24*3600; //token有效期
            if(!$_SESSION) session_start();
            session('token',$data['access_token']);
            session('openid',$data['openid']);
            $data['oauth'] = 'weixin';
            if(isset($data2['unionid'])) $data['unionid'] = $data2['unionid'];
            //绑定上下级关系
            if(!empty($pid))
            {
                $father = M('users')
                    ->where('user_id='.$pid)
                    ->field('user_id,first_leader,second_leader,third_leader,openid')
                    ->find();
                //有孩子的用户不能绑定上级，以免关系混乱
                $w = true;
                $u_now = db('users')
                    ->where('openid',$data['openid'])
                    ->field('user_id,first_leader,second_leader,third_leader,openid')
                    ->find();

                if($u_now)
                {
                    $is_has_child =M('users')
                        ->where('first_leader='.$u_now['user_id'])
                        ->field('user_id')
                        ->find();
                    if($is_has_child ||$u_now['first_leader'])  $w = false;
                }
                //$data['grade'] =0;
                //$data2['grade'] =0;
                if($father && $father['openid']!=$data['openid'] && $w)
                {
                    $data2['first_leader']  = $pid;
                    $data['first_leader']   = $pid;
                    $data2['second_leader'] = $father['first_leader'];
                    $data['second_leader']  = $father['first_leader'];
                    $data2['third_leader']  = $father['second_leader'];
                    $data['third_leader']   = $father['second_leader'];
                    //添加用户组
                    //$data['grade'] = $father['grade'] + 1;
                    //$data2['grade'] = $father['grade'] + 1;
                }
            }

            //注册或更新用户信息，绑定上下级关系
            $userinfo = db('users')->where(['openid'=>$data['openid']])->find();

            if( $userinfo )
            {
               db('users')->where(['user_id'=>$userinfo['user_id']])->save($data2);
                $users_id = $userinfo['user_id'];
            } else {
                $data['reg_time'] = time();
                $users_id = db('users')->insertGetId($data);
                //查看当前是否有发放注册赠送优惠券
                /*$timesv = time();
                $coupon = db("coupon")->where(['type'=>4,'status'=>1,'use_start_time'=>['lt',$timesv],'use_end_time'=>['gt',$timesv]])->select();
                if($coupon){
                    foreach($coupon as $sk=>$sv){
                        if($sv['createnum'] ==0 || ($sv['createnum'] - $sv['send_num'])>0) {
                            $as = [];
                            $as['cid'] = $sv['id'];
                            $as['type'] = 2;//注册
                            $as['uid'] = $users_id;
                            $as['send_time'] = $timesv;
                            db('coupon_list')->insert($as);
                        }
                    }
                }*/

            }
            $res = db('users')->where(['user_id'=>$users_id])->find();
            //用户分销关系分组
            if( !empty($father) )
            {
                $userGroup = db('user_group')->where('user_id',$father['user_id'])->find();

                if ($w){ //有上级与有下级的用户不能绑定，以免关系混乱
                    if($userGroup)
                    {
                        $a_son_id = explode(',',$userGroup['a_son_id']);
                        //判断当前要更新的用户下级是否已经存在与自己添加自己为下级
                        if ((!in_array($res['user_id'],$a_son_id)) && ($res['user_id'] != $userGroup['user_id']))
                        {
                            //更新当前用户的所有孩子
                            $user_group_data['f_son_id'] =  $userGroup['f_son_id'] . ',' . $res['user_id'];
                            $user_group_data['a_son_id'] =  $userGroup['a_son_id'] . ',' . $res['user_id'];
                            //绑定
                            $ug_res =db('user_group')->where('user_id',$userGroup['user_id'])->save($user_group_data);
                        }
                    } else {
                        //判断当前要添加的用户下级是否是自己
                        if ($father['user_id'] != $res['user_id'])
                        {
                            $user_group_data['user_id'] = $father['user_id'];
                            $user_group_data['f_son_id'] = $res['user_id'];
                            $user_group_data['a_son_id'] = $res['user_id'];
                            $ug_res = db('user_group')->insert($user_group_data);
                        }
                    }
                }else{
                    $ug_res = false;
                }
                //更新上级的所有儿子
                if( $ug_res != false )
                {
                    $all_f = db('user_group')->select();
                    
                    if($all_f)
                    {
                        $father_id = '';
                        foreach ($all_f as $fv)
                        {
                            $all_son_arr = explode(",",$fv['a_son_id']);
                            //更新当前绑定下级用户的所有上级的所有儿子
                            if(in_array($father['user_id'],$all_son_arr))
                            {
                                $a_son_id=$fv['a_son_id'].','.$res['user_id'];
                                db('user_group')->where(['user_id'=>$fv['user_id']])->save(['a_son_id'=>$a_son_id]);
                                $father_id .= ','.$fv['user_id'];
                            }
                        }

                        //如果是添加用户分销组，找到当前绑定下级用户的上级更新
                        if(!empty($father_id))
                        {
                            $father_id = ltrim($father_id,',');
                            db('user_group')
                                ->where(['user_id'=>$father['user_id']])
                                ->save(['father_id'=>$father_id]);
                        }
                    }
                }
            }

            //判断用户是否关注过微信公众号

            return $data['openid'];
        }
    }



    //支付回调
    public function notify(){
        $postXml = $GLOBALS["HTTP_RAW_POST_DATA"]; //接收微信参数

        if (empty($postXml)) {
            return ;
        }
        $attr =xmlToArray($postXml);
        //M('order')->where(array('order_id' => $attr['out_trade_no']))->save(array('state'=>2, 'type' => 2));
        $sign1=$attr['sign'];    //签名
        unset($attr['sign']);
        $model=new PayLogic($attr["openid"],$attr["out_trade_no"],$attr["total_fee"]);
        $sign=$model->getSign($attr);     //生成签名
        if($sign1 == $sign){ //验签通过
            if($attr['return_code'] == 'SUCCESS' && $attr['result_code'] == 'SUCCESS'){ //支付成功
                $order_sn=$attr['out_trade_no']; //订单号
                if (strlen($order_sn) > 18) {
                    $order_sn = substr($order_sn, 0, 18);
                }

                //用户在线充值
                if (stripos($order_sn, 'recharge') !== false) {

                    $order_amount = M('recharge')->where(['order_sn' => $order_sn, 'pay_status' => 0])->value('account');
                } else {

                    $order_amount = M('order')->where(['order_sn' => "$order_sn"])->value('order_amount');
                }

                if ((string)($order_amount * 100) != (string)$attr['total_fee']) {
                    return false; //验证失败
                }
                update_pay_status($order_sn, array('transaction_id' => $attr["transaction_id"])); // 修改订单支付状态

                //查看当前是否有优惠券
                $order = M("order")->where(['order_sn'=>$order_sn])->find();
                if($order['coupon_id']>0){
                    //修改优惠券状态
                    $coupon_list = M("coupon_list")->where(['cid'=>$order['coupon_id'],'uid'=>$order['user_id']])->save(['status'=>1,'use_time'=>time(),'order_id'=>$order['order_id']]);
                if($coupon_list){
                    //优惠券使用数量加1
                    M("coupon")->where(['id'=>$order['coupon_id']])->setInc("use_num");
                }
                }

            }
            $return_xml='<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            return $return_xml;
        }
    }


    //充电支付回调
    public function power_notify(){
        $postXml = $GLOBALS["HTTP_RAW_POST_DATA"]; //接收微信参数

        if (empty($postXml)) {
            return ;
        }
        $attr =xmlToArray($postXml);
        $sign1=$attr['sign'];    //签名
        unset($attr['sign']);
        $model=new PayLogic($attr["openid"],$attr["out_trade_no"],$attr["total_fee"]);
        $sign=$model->getSign($attr);     //生成签名
        $order_sn=$attr['out_trade_no']; //订单号
        //判断这条订单是否已经更改过状态
        $order = M("power_order")->where(['order_sn'=>$order_sn])->find();
        if($order['pay_status']==2){//已支付过
            $return_xml='<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            return $return_xml;
        }else {

            if ($sign1 == $sign) { //验签通过
                if ($attr['return_code'] == 'SUCCESS' && $attr['result_code'] == 'SUCCESS') { //支付成功
                    //1.更改订单状态
                    M("power_order")->where(['order_sn'=>$order_sn])->update(['pay_status'=>2,'pay_time'=>time()]);
                    //2.减去自身抵扣的环保电量
                    M("users")->where(['user_id'=>$order['user_id']])->setDec('green_power',$order['user_power']);
                    //3.加上赠送的环保电量
                    M("users")->where(['user_id'=>$order['user_id']])->setInc('green_power',$order['green_power']);
                 /*******************************************分润处理开始*********************************************************/
                    //酒店分润处理查找分润
                    //查找设备绑定的酒店和分销商
                    $money = $order['pay_price'];//订单金额
                    $number = $order['number'];//设备编号
                    $lc_equipment_number = M("lc_equipment_number")->where(['number'=>$order['number']])->field("j_user_id,f_user_id")->find();
                    //查找酒店的分润比例
                    $hotel = M('lc_apply')->where(['user_id'=>$lc_equipment_number['j_user_id']])->value("one_level");
                    //公共可分润比例
                    $subcommission = M("lc_subcommission")->where(['id'=>1])->value("agent");
                    $subcommission = intval($subcommission);
                    $hotel = intval($hotel);
                    if($hotel > 0){
                        $hotel_bl = number_format($hotel/100,2);//酒店可得分润比例
                        $j_money = number_format($money * $hotel_bl,2);//酒店可获得分润金额
                        if($j_money>0){
                            M("users")->where(['user_id'=>$lc_equipment_number['j_user_id']])->setInc('user_money',$j_money);//往酒店人员零钱添加分润金额
                            //添加分润记录
                            //添加分润记录(收入记录)
                            $arrays = ['user_id'=>$lc_equipment_number['j_user_id'],'money'=>$money,'allf_money'=>$j_money,'pay_user_id'=>$order['user_id'],'time'=>time(),'type'=>1,'order_sn'=>$order_sn,'subcommission'=>$hotel,'number'=>$number];
                            M("shou_log")->add($arrays);
                        }
                    }

                    //分销商比例分成
                    //1.查找身份
                    $f_level = M("users")->where(['user_id'=>$lc_equipment_number['f_user_id']])->field('level')->find();
                    $f_level['agent_f'] = M("lc_apply")->where(['user_id'=>$lc_equipment_number['f_user_id']])->value("one_level");
                    if($f_level['level']==5){//是总代理
                            //2,查看是否用的是公共分润比例
                        $f_bili = intval($f_level['agent_f']);
                        if($f_bili > 0){//是,得到最终比例（总-酒店=代理）
                           $result_bili = $f_bili -  $hotel;
                        }else{//否
                            $result_bili = $subcommission - $hotel;
                        }
                        if($result_bili > 0){
                            $total_bl = number_format($result_bili/100,2);//总代理可得分润比例
                            $z_money = number_format($money * $total_bl,2);//总代理可获得分润金额
                            if($z_money>0){
                                M("users")->where(['user_id'=>$lc_equipment_number['f_user_id']])->setInc('user_money',$z_money);//往总代理零钱添加分润金额
                                //添加分润记录(收入记录)
                                $arrayss = ['user_id'=>$lc_equipment_number['f_user_id'],'money'=>$money,'allf_money'=>$z_money,'pay_user_id'=>$order['user_id'],'time'=>time(),'type'=>3,'order_sn'=>$order_sn,'subcommission'=>$result_bili,'number'=>$number];
                                M("shou_log")->add($arrayss);

                            }
                        }
                    }else{//不是总代理身份
                        //查找分销商分成比例
                        $f_fcbili = M('lc_apply')->where(['user_id'=>$lc_equipment_number['f_user_id']])->value("one_level");
                        //可分成 = 自己所有-分配给酒店
                        $f_fcbilis = intval($f_fcbili) - $hotel;
                        if($f_fcbilis > 0){
                            $f_fcbiliv = number_format($f_fcbilis/100,2);//分销商可得分润比例
                            $f_money = number_format($money * $f_fcbiliv,2);//分销商可获得分润金额
                            if($f_money>0){
                                M("users")->where(['user_id'=>$lc_equipment_number['f_user_id']])->setInc('user_money',$f_money);//往分代人员零钱添加分润金额
                                //添加分润记录
                                //添加分润记录(收入记录)
                                $arraysss = ['user_id'=>$lc_equipment_number['f_user_id'],'money'=>$money,'allf_money'=>$f_money,'pay_user_id'=>$order['user_id'],'time'=>time(),'type'=>2,'order_sn'=>$order_sn,'subcommission'=>$f_fcbilis,'number'=>$number];
                                M("shou_log")->add($arraysss);
                            }
                        }

                        //查找上级总代理
                        $entry_uid = M('lc_apply')->where(['user_id'=>$lc_equipment_number['f_user_id']])->value("entry_uid");
                        if($entry_uid){
                            $one_level = M("lc_apply")->where(['user_id'=>$entry_uid])->value('one_level');
                            $f_level['agent_f'] = $one_level;
                            $f_bili = intval($f_level['agent_f']);
                            if($f_bili > 0){//是,得到最终比例（总-分销商=自己）
                                $result_bili = $f_bili - $f_fcbili;
                            }else{//否
                                $result_bili = $subcommission - $f_fcbili;
                            }

                            if($result_bili > 0){
                                $total_bl = number_format($result_bili/100,2);//总代理可得分润比例
                                $z_money = number_format($money * $total_bl,2);//总代理可获得分润金额
                                if($z_money>0){
                                    M("users")->where(['user_id'=>$entry_uid])->setInc('user_money',$z_money);//往总代理零钱添加分润金额
                                    //添加分润记录(收入记录)
                                    $arrayssss = ['user_id'=>$entry_uid,'money'=>$money,'allf_money'=>$z_money,'pay_user_id'=>$order['user_id'],'time'=>time(),'type'=>3,'order_sn'=>$order_sn,'subcommission'=>$result_bili,'number'=>$number];
                                    M("shou_log")->add($arrayssss);

                                }
                            }

                        }

                    }

                    /*******************************************分润处理结束*********************************************************/
                }
                $return_xml = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
                return $return_xml;
            }
        }
    }

    /**********写入日志方法***********/
    /**
     * 日志记录
     * @param  $path     string   日志文件目录
     * @param  $file     string   日志文件名，不包含后缀
     * @param  $content  string   记录内容
     * @param  @author yangzl
     * @return void
     **/
    public function writeLogs($path,$file,$content,$more=true){
        $newpath = '';
        if (!file_exists($path)) {
            mkdir ($path);
            @chmod ($path, 0777 );
        }
        if($more){
            $newpath .= $path.$file.@date('Y-m-d').".log";
        }else{
            $newpath .= $path.$file.".log";
        }
        $content .="\r\n"."----------------------------------------------------------------------------------------------------------------"."\r\n";
        $this->write_file($newpath,$content,"a+");
    }

    /**
     * 写内容
     * @param  $filename   string   日志文件名
     * @param  $data       string   记录内容
     * @param  $method
     * @author yanzl
     **/
    private function write_file($filename,$data,$method="rb+",$iflock=1){
        @touch($filename);
        $handle=@fopen($filename,$method);
        if($iflock){
            @flock($handle,LOCK_EX);
        }
        @fputs($handle,$data);
        if($method=="rb+") @ftruncate($handle,strlen($data));
        @fclose($handle);
        @chmod($filename,0777);
        if( is_writable($filename) ){
            return 1;
        }else{
            return 0;
        }
    }

    /**
     * 获取当前的url 地址
     * @return type
     */
    private function get_url() {
        $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'http://' : 'http://';
        $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : $path_info);
        
        return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').$relate_url;
    }

    /**
     *
     * 通过code从工作平台获取openid机器access_token
     * @param string $code 微信跳转回来带上的code
     *
     * @return openid
     */
    public function GetOpenidFromMp($code)
    {
        //通过code获取网页授权access_token 和 openid 。网页授权access_token是一次性的，而基础支持的access_token的是有时间限制的：7200s。
        //1、微信网页授权是通过OAuth2.0机制实现的，在用户授权给公众号后，公众号可以获取到一个网页授权特有的接口调用凭证（网页授权access_token），通过网页授权access_token可以进行授权后接口调用，如获取用户基本信息；
        //2、其他微信接口，需要通过基础支持中的“获取access_token”接口来获取到的普通access_token调用。
        $url = $this->__CreateOauthUrlForOpenid($code);
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $res = curl_exec($ch);//运行curl，结果以jason形式返回
        $data = json_decode($res,true);
        curl_close($ch);
        return $data;
    }


    /**
     *
     * 通过access_token openid 从工作平台获取UserInfo
     * @return openid
     */
    public function GetUserInfo($access_token,$openid)
    {
        // 获取用户 信息
        $url = $this->__CreateOauthUrlForUserinfo($access_token,$openid);
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $res = curl_exec($ch);//运行curl，结果以jason形式返回
        $data = json_decode($res,true);
        curl_close($ch);
        //获取用户是否关注了微信公众号， 再来判断是否提示用户 关注
        if(!isset($data['unionid'])){
            $access_token2 = $this->get_access_token();//获取基础支持的access_token
            
  
            $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$access_token2&openid=$openid";

            $subscribe_info = httpRequest($url,'GET');
           
            $subscribe_info = json_decode($subscribe_info,true);

            $data['subscribe'] = $subscribe_info['subscribe'];
        }
        
        /*if($data['subscribe'] != 1)
        {
            $uri='http://'.$_SERVER['HTTP_HOST'].'/dist/index.html#/invite2'; //跳转扫码干衣机页面
            Header("location:".$uri);
            die;
        }*/
        
        return $data;
    }


    public function get_access_token(){
        $appid = config('appid');
        $secret = config('secret');
        //判断是否过了缓存期
        $expire_time = $this->weixin_config['web_expires'];
        if($expire_time > time()){
            return $this->weixin_config['web_access_token'];
        }
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$secret}";
        $return = httpRequest($url,'GET');
        $return = json_decode($return,1);
        $web_expires = time() + 7140; // 提前60秒过期
        M('wx_user')->where(array('id'=>$this->weixin_config['id']))->save(array('web_access_token'=>$return['access_token'],'web_expires'=>$web_expires));
        
        return $return['access_token'];
    }

    /**
     *
     * 构造获取code的url连接
     * @param string $redirectUrl 微信服务器回跳的url，需要url编码
     *
     * @return 返回构造好的url
     */
    private function __CreateOauthUrlForCode($redirectUrl)
    {
        $urlObj["appid"] = config('APPID');
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
//        $urlObj["scope"] = "snsapi_base";
        $urlObj["scope"] = "snsapi_userinfo";
        $urlObj["state"] = "STATE"."#wechat_redirect";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
    }

    /**
     *
     * 构造获取open和access_toke的url地址
     * @param string $code，微信跳转带回的code
     *
     * @return 请求的url
     */
    private function __CreateOauthUrlForOpenid($code)
    {
        $urlObj["appid"] = config('appid');
        $urlObj["secret"] = config('secret');
        $urlObj["code"] = $code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
    }

    /**
     *
     * 构造获取拉取用户信息(需scope为 snsapi_userinfo)的url地址
     * @return 请求的url
     */
    private function __CreateOauthUrlForUserinfo($access_token,$openid)
    {
        $urlObj["access_token"] = $access_token;
        $urlObj["openid"] = $openid;
        $urlObj["lang"] = 'zh_CN';
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/userinfo?".$bizString;
    }

    public function ToUrlParams($urlObj){
        $buff = "";
        foreach ($urlObj as $k => $v)
        {
            if($k != "sign"){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;

    }

    public function httpGet($url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);
        curl_close($curl);

        return $res;
    }





}