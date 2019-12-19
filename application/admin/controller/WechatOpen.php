<?php
/**
 * 微信第三方开放平台
 */

namespace app\admin\controller;

use EasyWeChat\Foundation\Application;
use think\Request;

class WechatOpen extends Base
{
    protected $openPlatform;

    public function __construct()
    {
        parent::__construct();
        $config             = [
            'app_id'  => config('product.WeChatOpen.AppId'),
            'secret'  => config('product.WeChatOpen.AppSecret'),
            'token'   => config('product.WeChatOpen.Token'),
            'aes_key' => config('product.WeChatOpen.Aes_Key')
        ];
        $app                = new Application($config);
        $this->openPlatform = $app->open_platform;
    }

    /**
     * 授权事件接收URL
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \EasyWeChat\Core\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Server\BadRequestException
     * @author: iszmxw <mail@54zm.com>
     * @Date：2019/12/19 16:55
     */
    public function auth(Request $request)
    {
        $openPlatform = $this->openPlatform;
        // 处理授权取消事件
        // 自定义处理
        $openPlatform->server->setMessageHandler(function ($event) {
            IszmxwLog('iszmxw', json_encode($event));
            // 事件类型常量定义在 \EasyWeChat\OpenPlatform\Guard 类里
            switch ($event->InfoType) {
                case 'authorized':
                    // ...
                    IszmxwLog('iszmxw', json_encode($event));
                case 'unauthorized':
                    // ...
                case 'updateauthorized':
                    // ...
                case 'component_verify_ticket':
                    // ...
            }
        });
//        $server->server->push(function ($message) {
//            $appid = $message['AuthorizerAppid'];
//            // 软删除当前公众号数据,以及公众号相关的任务
//            $official_id = OfficialAccount::getValue(['appid' => $appid], 'id');
//            OfficialAccount::EditData(['appid' => $appid], ['status' => 0]);
//            OfficialAccount::selected_delete(['appid' => $appid]);
//            PlanTask::selected_delete(['official_id' => $official_id]);
//        }, Guard::EVENT_UNAUTHORIZED);
        return $openPlatform->server->serve();
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