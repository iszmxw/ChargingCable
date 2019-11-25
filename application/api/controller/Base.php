<?php

namespace app\api\controller;

use app\api\logic\UserLogic;
use think\Controller;
use think\Cookie;
use think\response\Json;

class Base extends Controller {

    public $user_id = '';
    public $user = '';
    public $tpshop_config = array();
    /**
     * 检验签名是否正确
     * @param string $Sign
     * @return bool
     */
    public function _initialize()
    {
        parent::_initialize();
        //设置跨域
        header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, authKey, sessionId");
       $openid = I("openid");
      /* if(empty($openid)){
           $openid = 'ooha95jKBseQAdSU08xWQ0ACtYG0';
       }*/
        //$openid = I("openid",0);
        //$return_url = 'http://'.$_SERVER['SERVER_NAME'].'/api/user/get_user_info'; //微信公众号授权接口
        $return_url = 'http://'.$_SERVER['HTTP_HOST'].'/index.php/api/Login/index'; //微信公众号授权接口
        $user = db('users')->where(['openid'=>$openid])->find();

        
        //校验权限，需要登录
        if($user){
            $this->user = $user;
          /*  if($user['user_id'] == 138){
                $user['user_id'] = 13;
            }
            if($user['user_id'] == 6){
                $user['user_id'] = 27;
            }*/
            $this->user_id = $user['user_id'];

        }else{
            returnBads('请授权公众号登录', 401, ['return_url'=>$return_url]);
        }
    }
    

    /**
     * 验证签名
     * @throws CommException
     */
    public function checkSign(){
        $sign = request()->header('sign');
        $time = request()->header('time');
        //file_put_contents("1.txt",$sign);

        $mysign = md5(md5($time.C('secure.sign_salt')));
        if(empty($sign) || empty($time)){
            exit(json_encode(['code'=>302,'msg'=>'请求参数缺失']));
        }
        if($sign != $mysign){
            exit(json_encode(['code'=>301,'msg'=>'调用错误']));
        }
        if($time + 2150000 - time() < 0){
            exit(json_encode(['code'=>301,'msg'=>'调用错误']));
        }
    }

    /**
     * 短信随机数字码
     * @param int $len
     * @return bool|string
     */
    public function randSmsCode($len = 6)
    {
        $chars = str_repeat('123456789', 3);
        // 位数过长重复字符串一定次数
        $chars = str_repeat($chars, $len);
        $chars = str_shuffle($chars);
        $str = substr($chars, 0, $len);
        return $str;
    }

    /*判断post
     *参数
     */
    public function check_post(){
        $post = I('post.');
        if(empty($post)){
           returnBad('请求参数缺失',302);
        }
        return $post;
    }

    public function return_ajax($data){
        if($data){
            returnOk($data);
        }else {
            returnBad('获取数据失败',302);
        } 
    }

}