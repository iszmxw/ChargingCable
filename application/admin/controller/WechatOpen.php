<?php
/**
 * 微信第三方开放平台
 */

namespace app\admin\controller;

use app\common\logic\AdminLogic;
use app\common\logic\ModuleLogic;
use think\Page;
use think\Request;
use think\Verify;
use think\Loader;
use think\Db;

class WechatOpen extends Base
{
    /**
     * 授权事件接收URL
     * @author: iszmxw <mail@54zm.com>
     * @Date：2019/12/19 15:23
     */
    public function auth()
    {
        dump(1);
    }

    /**
     * 消息与事件接收URL
     * @author: iszmxw <mail@54zm.com>
     * @Date：2019/12/19 15:36
     */
    public function message_callback(Request $request)
    {
        $appid = $request->param('appid');
        dump($appid);
    }
}