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
     * 授权跳转链接
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function account_empower(Request $request)
    {
        $user_id = $request->get('user_id');

        $config        = $this->openPlatform->getConfig();
        $appid         = $config['app_id'];
        $pre_auth_code = $this->openPlatform->createPreAuthorizationCode()['pre_auth_code'];

        $callback = config('app.url') . '/wechat/official_account/callback?user_id=' . $user_id;

        if (isMobile()) {
            // 移动端授权链接
            $url = "https://mp.weixin.qq.com/safe/bindcomponent?action=bindcomponent&auth_type=1&no_scan=1&component_appid={$appid}&pre_auth_code={$pre_auth_code}&redirect_uri={$callback}#wechat_redirect";
        } else {
            // pc端授权链接
            $url = "https://mp.weixin.qq.com/cgi-bin/componentloginpage?component_appid={$appid}&pre_auth_code={$pre_auth_code}&redirect_uri={$callback}";
        }
        return redirect($url);
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
//                    IszmxwLog('iszmxw', json_encode($event));
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