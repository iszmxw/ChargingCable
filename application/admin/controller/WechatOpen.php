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
            'app_id'  => config('WechatOpen.AppId'),
            'secret'  => config('WeChatOpen.AppSecret'),
            'token'   => config('WeChatOpen.Token'),
            'aes_key' => config('WeChatOpen.Aes_Key')
        ];
        $app                = new Application($config);
        $this->openPlatform = $app->open_platform;
    }

    /**
     * 授权跳转链接
     * @param Request $request
     * @return \think\response\Redirect
     * @author: iszmxw <mail@54zm.com>
     * @Date：2019/12/19 17:09
     */
    public function account_empower(Request $request)
    {
//        $appid         = config('WechatOpen.AppId');
        $pre_auth_code = $this->openPlatform->pre_auth->getCode();

//        dump($appid);
        dump($pre_auth_code);
        die();

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

                case 'unauthorized':
                    // ...
                case 'updateauthorized':
                    // ...
                case 'component_verify_ticket':
                    // ...
            }
        });
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
        /**
         * 全网发布
         */
        if ($appid == 'wx570bc396a51b8ff8') {
            IszmxwLog('iszmxw.txt', $appid);
            return $this->releaseToNetWork($appid);
        }
        dump($appid);
    }

    /**
     * 处理全网发布相关逻辑
     * @param $authorizer_appid
     * @return mixed
     * @throws \EasyWeChat\Server\BadRequestException
     * @author: iszmxw <mail@54zm.com>
     * @Date：2019/12/19 17:59
     */
    private function releaseToNetWork($authorizer_appid)
    {
        $open_platform = $this->openPlatform;
        $message       = $open_platform->server->getMessage();
        //返回API文本消息
        if ($message['MsgType'] == 'text' && strpos($message['Content'], "QUERY_AUTH_CODE:") !== false) {
            $auth_code                = str_replace("QUERY_AUTH_CODE:", "", $message['Content']);
            $authorization            = $open_platform->handleAuthorize($auth_code);
            $authorizer_refresh_token = $authorization['authorization_info']['authorizer_refresh_token'];
            if ($authorizer_refresh_token) {
                $official_account_client = $open_platform->officialAccount($authorizer_appid, $authorizer_refresh_token);
                $content                 = $auth_code . '_from_api';
                $official_account_client['customer_service']->send([
                    'touser'  => $message['FromUserName'],
                    'msgtype' => 'text',
                    'text'    => [
                        'content' => $content
                    ]
                ]);
                return $official_account_client->server->serve();
            }
            //返回普通文本消息
        } elseif ($message['MsgType'] == 'text' && $message['Content'] == 'TESTCOMPONENT_MSG_TYPE_TEXT') {
            $official_account_client = $open_platform->officialAccount($authorizer_appid);
            $official_account_client->server->push(function ($message) {
                return $message['Content'] . "_callback";
            });
            return $official_account_client->server->serve();
            //发送事件消息
        } elseif ($message['MsgType'] == 'event') {
            $official_account_client = $open_platform->officialAccount($authorizer_appid);
            $official_account_client->server->push(function ($message) {
                return $message['Event'] . 'from_callback';
            });
            return $official_account_client->server->serve();
        }
    }
}