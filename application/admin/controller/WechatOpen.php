<?php
/**
 * 微信第三方开放平台
 */

namespace app\admin\controller;

use EasyWeChat\Foundation\Application;
use EasyWeChat\Server\Guard;
use think\Request;

class WechatOpen extends Base
{
    protected $openPlatform;

    public function __construct()
    {
        parent::__construct();
        $options            = [
            'debug'   => true,
            'app_id'  => 'wx6590d39e4f1bf4a0',
            'secret'  => 'd290f710854a122f7eebc11bb8bc2ec2',
            'token'   => 'iszmxw',
            'aes_key' => 'ckGPqhPfREgJZR6rC8rz3xqQcdmZRf8Xv9QMm5ym3Yf',
            'log'     => [
                'level'      => 'debug',
                'permission' => 0777,
                'file'       => '/runtime/log/easywechat.log',
            ],
        ];
        $app                = new Application($options);
        $this->openPlatform = $app->open_platform;
    }


    /**
     * 授权跳转链接
     * @param Request $request
     * @return \think\response\Redirect
     * @throws \EasyWeChat\Core\Exceptions\InvalidArgumentException
     * @author: iszmxw <mail@54zm.com>
     * @Date：2019/12/20 9:29
     */
    public function account_empower(Request $request)
    {
        $url      = "http://{$_SERVER['HTTP_HOST']}/index.php/Admin/WechatOpen/official_account";
        $response = $this->openPlatform->pre_auth->redirect($url);

        // 获取跳转的链接
        $url = $response->getTargetUrl();
        return $url;

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
//        $content = file_get_contents("php://input");
//        IszmxwLog('iszmxw.txt', $content);
        $openPlatform = $this->openPlatform;

        $openPlatform->server->serve();
        // 自定义处理
        $openPlatform->server->setMessageHandler(function ($message) {
            IszmxwLog('iszmxw.txt', json_encode('$message, true'));
        });
        $openPlatform->server->serve()->send();
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