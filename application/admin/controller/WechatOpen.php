<?php
/**
 * 微信第三方开放平台
 */

namespace app\admin\controller;

use App\Library\Upload;
use App\Models\OfficialAccount;
use Doctrine\Common\Cache\RedisCache;
use EasyWeChat\Foundation\Application;
use think\Request;

class WechatOpen extends Base
{
    protected $openPlatform;

    public function __construct()
    {
        parent::__construct();
        // 使用自己写的缓存方案替代
        $cacheDriver = new RedisCache();
        $redis       = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $cacheDriver->setRedis($redis);
        $config             = config('WechatOpen');// 获取配置参数
        $options            = [
            'open_platform' => [
                'app_id'  => $config['AppId'],
                'secret'  => $config['AppSecret'],
                'token'   => $config['Token'],
                'aes_key' => $config['Aes_Key'],
                'log'     => [
                    'level'      => 'error',
                    'permission' => 0777,
                    'file'       => 'runtime/log/easywechat.log',
                ],
                'cache'   => $cacheDriver
            ]
        ];
        $app                = new Application($options);
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
        $serve        = $openPlatform->server;
        // 自定义处理
        $serve->setMessageHandler(function ($event) {
            // 事件类型常量定义在 \EasyWeChat\OpenPlatform\Guard 类里
            switch ($event->InfoType) {
                case 'authorized':
                    IszmxwLog('iszmxw.txt', 'authorized');
                    break;
                case 'unauthorized':
                    IszmxwLog('iszmxw.txt', 'unauthorized');
                    break;
                case 'updateauthorized':
                    IszmxwLog('iszmxw.txt', 'updateauthorized');
                    break;
                case 'component_verify_ticket':
                    IszmxwLog('iszmxw.txt', 'component_verify_ticket');
                    break;
                default:
                    break;
            }
        });
        $serve->serve()->send(); // Laravel 里请使用：return $response;
        die;
    }

    /**
     * 授权跳转
     * @author: iszmxw <mail@54zm.com>
     * @Date：2019/12/21 16:19
     */
    public function authorization_jump()
    {
        return view('authorization_jump');
    }

    /**
     * 公众号授权跳转链接
     * @param Request $request
     * @return \think\response\Redirect
     * @throws \EasyWeChat\Core\Exceptions\InvalidArgumentException
     * @author: iszmxw <mail@54zm.com>
     * @Date：2019/12/20 9:29
     */
    public function account_empower(Request $request)
    {
        $callback      = "http://{$_SERVER['HTTP_HOST']}/index.php/Admin/WechatOpen/official_account_callback";
        $appid         = config('WechatOpen.AppId');
        $pre_auth_code = $this->openPlatform->pre_auth->getCode();
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
     * 公众号授权回调地址
     * @param Request $request
     * @return \think\response\View
     * @author: iszmxw <mail@54zm.com>
     * @Date：2019/12/21 16:11
     */
    public function official_account_callback(Request $request)
    {
        $get          = $request->param();
        $openPlatform = $this->openPlatform;
        // 第二次回调会带一个授权code
        if (isset($get['auth_code'])) {
            $auth_info  = $openPlatform->getAuthorizationInfo($get['auth_code']); // 获取授权信息
            $appid      = $auth_info['authorization_info']['authorizer_appid'];   // 获取此次授权公众号的appid
            $info       = $openPlatform->getAuthorizerInfo($appid);               // 获取授权公众号的信息
            $qrcode_url = $info['authorizer_info']['qrcode_url'];                 // 获取公众号的二维码，并且存到服务器
            $img        = download($qrcode_url, "./uploads/wechat/{$appid}", date('YmdHis') . ".jpg");
            if ($img['error'] == 0) {
                $qrcode_path = $img['save_path'];
            } else {
                $qrcode_path = '';
            }
            // 处理空图像
            $head_img = empty($info['authorizer_info']['head_img']) ? '' : $info['authorizer_info']['head_img'];
            $data     = [
                'user_id'           => 0,
                'appid'             => $appid,
                'refresh_token'     => $info['authorization_info']['authorizer_refresh_token'],
                'name'              => $info['authorizer_info']['nick_name'],
                'head_img'          => $head_img,
                'service_type_info' => $info['authorizer_info']['service_type_info']['id'],
                'verify_type_info'  => $info['authorizer_info']['verify_type_info']['id'],
                'public_name'       => $info['authorizer_info']['user_name'],
                'alias'             => $info['authorizer_info']['alias'],
                'qrcode_url'        => $qrcode_url,
                'qrcode_path'       => $qrcode_path,
                'authorized'        => 1,
                'status'            => 0,
            ];
            // 查询该appid是否存在系统中
            $where   = ['appid' => $appid];
            $isExist = M('lc_official_account')->where($where)->find();
            if ($isExist) {
                unset($data['status']);
                unset($data['user_id']);
                $data['updated_time'] = time(); // 更新时间
                if (empty($isExist['deleted_time'])) {
                    $re = M('lc_official_account')->where($where)->update($data);
                    if ($re) {
                        return view('message', ['message' => '重新授权到平台，公众号信息已经更新']);
                    } else {
                        return view('message', ['message' => '操作失败，请稍后再试']);
                    }
                } else {
                    // 恢复系统中软删除的数据
                    $data['deleted_time'] = null;
                    M('lc_official_account')->where($where)->update($data);
                    return view('message', ['message' => '欢迎您回来，授权成功啦！']);
                }
            } else {
                $data['created_time'] = time(); // 创建时间
                $data['updated_time'] = time(); // 更新时间
                $re                   = M('lc_official_account')->add($data);
                if ($re) {
                    return view('message', ['message' => '授权成功！' . $re]);
                } else {
                    return view('message', ['message' => '授权失败！']);
                }
            }
        } else {
            return view('message', ['message' => '授权失败！']);
        }
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
     * 公众号列表
     * @author: iszmxw <mail@54zm.com>
     * @Date：2019/12/21 17:46
     */
    public function official_list()
    {
        // 初始化搜索条件，默认只显示没有软删除的数据
        $where = ['deleted_time' => null];
        $list  = M('lc_official_account')->where($where)->select();
        return view('official_list', ['list' => $list]);
    }


    /**
     * 授权公众号修改操作
     * @param Request $request
     * @return \think\response\Json
     * @author: iszmxw <mail@54zm.com>
     * @Date：2019/12/23 14:22
     */
    public function official_edit(Request $request)
    {
        $param = $request->param();
        $where = ['id' => $param['id']];
        // 软删除操作
        if ('delete' === $param['act']) {
            $data = ['deleted_time' => time()];
            M('lc_official_account')->where($where)->save($data);
            return json(['code' => 200, 'message' => '操作成功', 'data' => []]);
        }
    }
}