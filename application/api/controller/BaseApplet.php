<?php

namespace app\api\controller;

use app\api\logic\UserLogic;
use think\Controller;
use think\Cookie;
use think\Session;
use think\response\Json;

class BaseApplet extends Controller {

    public $user_id = ''; //用户id
    public $user = '';  //用户信息
    public $token = '';  //用户token
    public $tpshop_config = array();

    public function _initialize()
    {
        parent::_initialize();
        $this->checkSign(); //验证签名

        //设置跨域
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods:POST,GET");
        header("Access-Control-Allow-Headers:x-requested-with,content-type,openid");
        // header("Content-type:text/json;charset=utf-8");
        $this->user = Cookie::get('user');
        $this->user_id = Cookie::get('user_id');
        $this->openid = I("openid");
        if ((!$this->user || !$this->user_id) && $this->openid) {
            $userLogic = new UserLogic();
            $this->user = $userLogic->getuser($this->openid);
            $this->user_id = $this->user['user_id'];
            Cookie::set('user', $this->user);
            Cookie::set('user_id', $this->user_id);
            Session::set('user', $this->user);
            Session::set('user_id', $this->user_id);
        }
        
        if (Self::checkAction() === true) {
            return true;//不需要检验token的接口
        } else {
            if (empty($this->openid)) {
                exit(json_encode(['code' => 401, 'status' => 0, 'msg' => '请先登录', 'return_url' => 'http://'.$_SERVER['SERVER_NAME'].'/api/login/index']));
            }
            
            $userinfo = M('users')->where(['openid'=>$this->openid])->find();

            if(empty($userinfo)){
                exit(json_encode(['code' => 401, 'status' => 0, 'msg' => '请求openid有误！', 'return_url' => 'http://'.$_SERVER['SERVER_NAME'].'/api/login/index']));
            }else{
                $this->user=$userinfo;
                $this->user_id = $userinfo['user_id'];
            }
        }
    }

    /**
     * 验证签名
     * @throws CommException
     */
    public function checkSign()
    {
        $sign = I("sign");
        $time = I("time");
        $openid = I("openid");
        //file_put_contents("1.txt",$sign);
        return true;
        $mysign = md5($openid . $time.C('secure.sign_salt'));
        if (empty($sign) || empty($time)) {
            exit(json_encode(['code' => 302, 'msg' => '请求参数缺失']));
        }
        if ($sign != $mysign) {
            exit(json_encode(['code' => 301, 'msg' => '签名验证失败']));
        }
       
        if ($time + 2150000 - time() < 0) {
            exit(json_encode(['code' => 301, 'msg' => '调用错误']));
        }
    }

    /**
     * 检验接口方法是否需要openid
     * @return bool
     */
    public static function checkAction()
    {
        $action_name = ACTION_NAME;
        $config = ['login'];
        if (in_array($action_name, $config)) {
            return true;
        } else {
            return false;
        }
    }

}