<?php
/**
--------------------------------------------------
空间类型   客服控制器
--------------------------------------------------
Copyright(c) 2017 时代万网 www.agewnet.com
--------------------------------------------------
开发人员: lichao  <729167563@qq.com>
--------------------------------------------------
 */
namespace app\api\controller;

use app\api\controller\Weixin;
use app\api\controller\WxTools;
use think\Controller;
use think\Log;
use think\Db;

class WxMessage extends Controller{

    //获取access_token
    private static function get_access_token()
    {
        $wechat = new Weixin();
        $access_token = $wechat->get_access_token();

        return $access_token;
    }


    //客服回复用户信息
    public static function reply_customer($open_id, $content)
    {
        $wx_tools = new WxTools();
        $data = '{"touser":"' . $open_id . '","msgtype":"text","text":{"content":"' . $content . '"}}';
        $access_token = self::get_access_token();
        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . $access_token;
        $result = $wx_tools->postCurl($url, $data);
        
        return json_decode($result, true);

    }

    //获取所有客服账号
    public static function get_customer_account_list()
    {
        $wx_tools = new WxTools();
        $access_token = self::get_access_token();
        $url = "https://api.weixin.qq.com/cgi-bin/customservice/getkflist?access_token=" . $access_token;
        $result = $wx_tools->getCurl($url);

        return json_decode($result, true);
    }

    //邀请微信号到客服
    public static function invite_customer_account($kf_account, $invite_wx)
    {
        $wx_tools = new WxTools();
        $access_token = self::get_access_token();

        $data = '{"kf_account":"' . $kf_account . '","invite_wx":"' . $invite_wx . '"}';
        $url = "https://api.weixin.qq.com/customservice/kfaccount/inviteworker?access_token=" . $access_token;
        $result = $wx_tools->postCurl($url, $data);
        return json_decode($result, true);
    }


    //添加客服账号
    public static function add_customer_account($kf_account, $nickname, $password)
    {
        $wx_tools = new WxTools();
        $access_token = self::get_access_token();

        $data = '{"kf_account":"' . $kf_account . '","nickname":"' . $nickname . '","text":"' . $password . '"}';
        $url = "https://api.weixin.qq.com/customservice/kfaccount/add?access_token=" . $access_token;
        $result = $wx_tools->postCurl($url, $data);
        return json_decode($result, true);
    }

    //设置微信头像
    public static function upload_head_img($kf_account, $file)
    {
        $wx_tools = new WxTools();
        $access_token = self::get_access_token();

        $url = 'https://api.weixin.qq.com/customservice/kfaccount/uploadheadimg?access_token=' . $access_token . '&kf_account=' . $kf_account;
        $tmp_name = $file['tmp_name'];
        $type = $file['type'];
        $path = $file['name'];

        $result = $wx_tools->curl_post_file($url, $tmp_name, $type, $path);
        return $result;
    }


    //修改客服账号
    public static function modify_customer_account($kf_account, $nickname, $password)
    {
        $wx_tools = new WxTools();
        $access_token = self::get_access_token();

        $data = '{"kf_account":"' . $kf_account . '","nickname":"' . $nickname . '","text":"' . $password . '"}';
        $url = "https://api.weixin.qq.com/customservice/kfaccount/update?access_token=" . $access_token;
        $result = $wx_tools->postCurl($url, $data);
        return json_decode($result, true);
    }

    //删除客服帐号
    public static function remove_customer_account($kf_account)
    {
        $wx_tools = new WxTools();
        $access_token = self::get_access_token();

        $data = '{"kf_account":"' . $kf_account . '"}';
        $url = "https://api.weixin.qq.com/customservice/kfaccount/del?access_token=" . $access_token;
        $result = $wx_tools->postCurl($url, $data);
        return json_decode($result, true);
    }

    //获取用户与客服之间的聊天记录

    public static function get_customer_service_chat_record($starttime, $endtime, $msgid, $number)
    {
        $wx_tools = new WxTools();
        $access_token = self::get_access_token();

        $data = '{"starttime":"' . $starttime . '","endtime":"' . $endtime . '","msgid":"' . $msgid . '","number":"' . $number . '"}';
        $url = "https://api.weixin.qq.com/customservice/msgrecord/getmsglist?access_token=" . $access_token;
        $result = $wx_tools->postCurl($url, $data);
        return json_decode($result, true);
    }
}