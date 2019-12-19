<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 当燃
 * 拼团控制器
 * Date: 2016-06-09
 */

namespace app\admin\controller;

use app\common\model\Shopper;
use app\admin\logic\SiteLogic;
use think\Loader;
use think\Db;
use think\Page;
use think\Request;
use think\Session;

//分佣规则设置
class Subcommission extends Base
{

    public function index()
    {
        $subcommission = M("lc_subcommission")->where(['id' => 1])->find();

        if (IS_POST) {
            $post          = I('post.');//得到数据
            $subcommission = M("lc_subcommission")->where(['id' => 1])->save($post);
            if ($subcommission) {
                $this->ajaxReturn(['status' => 1, 'msg' => "操作成功", 'url' => U("Admin/Subcommission/index")]);
            } else {
                $this->ajaxReturn(['status' => 1, 'msg' => "未做修改！！", 'url' => U("Admin/Subcommission/index")]);
            }


            $this->ajaxReturn(['status' => 1, 'msg' => "操作成功", 'url' => U("Admin/Subcommission/add_pay_currency")]);
        }
        $this->assign('subcommission', $subcommission);
        return $this->fetch();
    }

    //总池维护卡操作
    public function add_pay_currency()
    {
        $subcommission = M("lc_subcommission")->where(['id' => 1])->field("all_pay_currency")->find();

        if (IS_POST) {
            //加减维护卡操作
            $currency_type    = I('post.currency_type');//1加0减
            $all_pay_currency = I('post.all_pay_currency');//维护卡数量
            if ($all_pay_currency != 0) {
                //增加
                if ($currency_type == 1) {
                    //维护卡总池数量加
                    $res = M('lc_subcommission')->where(['id' => 1])->setInc('all_pay_currency', $all_pay_currency);
                    if ($res) {
                        //生成维护卡充值记录
                        //查找操作员
                        //查找操作员

                        $admins = M("admin")->where(['admin_id' => session('admin_id')])->getField('user_name');
                        $array  = ['number' => $all_pay_currency, 'time' => time(), 'type' => 1, 'admin' => $admins];//1;充值
                        M("lc_currency")->add($array);
                    }
                }
                //维护卡减
                if ($currency_type == 0) {
                    if ($all_pay_currency > $subcommission['all_pay_currency']) {
                        $this->ajaxReturn(['status' => 0, 'msg' => '总池剩余维护卡不足！！']);
                    }
                    $res = M('lc_subcommission')->where(['id' => 1])->setDec('all_pay_currency', $all_pay_currency);
                    if ($res) {
                        //生成维护卡充值记录
                        $admins = M("admin")->where(['admin_id' => session('admin_id')])->getField('user_name');
                        $array  = ['number' => $all_pay_currency, 'time' => time(), 'type' => 0, 'admin' => $admins];//1;充值
                        M("lc_currency")->add($array);
                    }
                }
                $this->ajaxReturn(['status' => 1, 'msg' => "操作成功", 'url' => U("Admin/Subcommission/add_pay_currency")]);
            }

        }
        $this->assign('subcommission', $subcommission);
        return $this->fetch();
    }

    //维护卡操作记录
    public function pay_currency_log()
    {
        $timegap = urldecode(I('timegap'));
        $map     = array();
        if ($timegap) {
            $gap         = explode(',', $timegap);
            $begin       = $gap[0];
            $end         = $gap[1];
            $map['time'] = array('between', array(strtotime($begin), strtotime($end)));
            $this->assign('begin', $begin);
            $this->assign('end', $end);
        }
        $count = M('lc_currency')->where($map)->count();
        $page  = new Page($count, 10);
        $lists = M('lc_currency')->where($map)->order('time desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();

    }

    //添加设备
    public function add_codelist()
    {
        //  require '../vendor/phpqrcode/phpqrcode.php';


        vendor('phpqrcode.phpqrcode');
        // EXIT;
        if (IS_POST) {
            $admins = M("admin")->where(['admin_id' => session('admin_id')])->getField('user_name');
            //批量生成批号lc_equipment_number表
            //设备密钥
            $secret_key = I('post.secret_key');
            if (empty($secret_key)) {
                $this->ajaxReturn(['status' => -1, 'msg' => '请输入设备密钥']);
            }
            //设备编号
            $number = I('post.number');
            if (empty($number)) {
                $this->ajaxReturn(['status' => -1, 'msg' => '请输入设备编号']);
            }
            $j_mobile = I('post.j_mobile');//酒店人员身份手机号
            //查询是否有这个身份
            $j_apply = M("lc_apply")->where(['mobile' => $j_mobile, 'type' => 3, 'status' => 2])->find();
            if (!$j_apply) {
                $this->ajaxReturn(['status' => -1, 'msg' => '酒店人员手机号码错误，未找到该酒店人员！！']);
            }


            $f_mobile = I('post.f_mobile');//分销商身份手机号
            $f_apply  = M("lc_apply")->where("mobile='{$f_mobile}' AND type>3 AND status=2")->find();
            if (!$j_apply) {
                $this->ajaxReturn(['status' => -1, 'msg' => '分销商人员手机号码错误，未找到该分销商！！']);
            }

            //套餐ID
            $pack_id = I('post.pack_id');
            if (empty($pack_id)) {
                $pack_id = M('package')->where(['default' => 1])->value('pid');
                if (empty($pack_id)) {
                    $this->ajaxReturn(['status' => -1, 'msg' => '套餐不存在，请先添加套餐']);
                }
            }
            //判断本次添加的设备是否已存在
            $id = M('lc_equipment_number')->where(['number' => $number])->value('id');
            if ($id) {
                $this->ajaxReturn(['status' => -1, 'msg' => '操作失败,设备已存在']);
            }
            $data   = [
                'secret_key'      => $secret_key,
                'number'          => $number,
                'time'            => time(),
                'admin'           => $admins,
                'pack_id'         => $pack_id,
                'password_number' => '01',
                'j_user_id'       => $j_apply['user_id'],
                'f_user_id'       => $f_apply['user_id'],
            ];
            $result = M("lc_equipment_number")->add($data);
            if ($result) {
                //生成设备二维码
                // require '/vendor/phpqrcode/phpqrcode.php';
                //http://jdx.thirmen.com/index.php/api/login/index?index=0&number=JDX19A000001
                $value                = 'http://' . $_SERVER['HTTP_HOST'] . '/index.php/api/login/index?index=0&number=' . $number; //二维码内容
                $errorCorrectionLevel = 'L';//容错级别
                $matrixPointSize      = 6;//生成图片大小
                $path                 = UPLOAD_PATH . date("Ymd", time()) . '/';
                if (!is_dir($path)) {
                    mkdir($path);
                }
                $Object    = new \QRcode();
                $rand      = substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8) . rand(9999, 99999) . $uid;
                $file_name = $path . $rand . 'code.jpg';
                $Object->png($value, $file_name, $errorCorrectionLevel, $matrixPointSize, 2);
                //$insert_id = db("admin_attachment")->insertGetId(array("path"=>$file_name));
                M("lc_equipment_number")->where(['id' => $result])->update(array('ewm' => $file_name));
                // $imgs = 'http://' . $_SERVER['HTTP_HOST'].'/public/'.$file_name;


                $this->ajaxReturn(['status' => 1, 'msg' => "操作成功", 'url' => U("Admin/Subcommission/add_codelist")]);
            } else {
                $this->ajaxReturn(['status' => -1, 'msg' => '操作失败,设备已存在']);
            }
        }
        $package = db::name('package')->select();
        $this->assign('package', $package);

        return $this->fetch();

    }

    // 设备编号列表
    public function code_list()
    {
        $admin_id = session('admin_id');
        $this->assign('admin_id', $admin_id);
        $timegap = urldecode(I('timegap'));
        $bank    = I('bank');

        $map = array();
        if ($timegap) {
            $gap         = explode(',', $timegap);
            $begin       = $gap[0];
            $end         = $gap[1];
            $map['time'] = array('between', array(strtotime($begin), strtotime($end)));
            $this->assign('begin', $begin);
            $this->assign('end', $end);
        }
        if ($bank) {
            $map['number|hotel_name'] = array('like', "%$bank%");
            $this->assign('bank', $bank);
        }
        $count = M('lc_equipment_number')->where($map)->count();
        $page  = new Page($count, 10);
        $lists = M('lc_equipment_number')->where($map)->order('time desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        if ($lists) {
            foreach ($lists as $k => $v) {
                $j_user                  = M("lc_apply")->where(['user_id' => $v['j_user_id']])->field("username,mobile")->find();
                $lists[$k]['j_username'] = $j_user['username'];
                $lists[$k]['j_mobile']   = $j_user['mobile'];

                $f_user                  = M("lc_apply")->where(['user_id' => $v['f_user_id']])->field("username,mobile")->find();
                $lists[$k]['f_username'] = $f_user['username'];
                $lists[$k]['f_mobile']   = $f_user['mobile'];
            }
        }


        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();

    }


    /**
     * 异步获取设备模式信息
     * @param Request $request
     * @return \think\response\View
     * @author: iszmxw <mail@54zm.com>
     * @Date：2019/11/19 14:11
     */
    public function get_device_mode()
    {
        $data      = I('post.');
        $number    = $data['number'];
        $mode_type = $data['mode_type'];
        return view('get_device_mode', ['number' => $number, 'mode_type' => $mode_type]);
    }


    // 免费扫码充电订单表
    public function power_order_free()
    {
        $timegap = urldecode(I('timegap'));
        $map     = array();
        if ($timegap) {
            $gap                = explode(',', $timegap);
            $begin              = $gap[0];
            $end                = $gap[1];
            $map['create_time'] = array('between', array(strtotime($begin), strtotime($end)));
            $this->assign('begin', $begin);
            $this->assign('end', $end);
            $map['pay_status'] = 2;//查找全部已经支付的订单
        } else {
            $map['pay_status'] = 2;//查找全部已经支付的订单
        }
        $count = M('power_order_free')->where($map)->count();
        $page  = new Page($count, 10);
        $lists = M('power_order_free')->where($map)->order('create_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        //循环查找用户头像，昵称
        foreach ($lists as $k => $v) {
            $users                 = M("users")->where(['openid' => $v['openid']])->field("head_pic")->find();
            $lists[$k]['head_pic'] = $users['head_pic'];
        }
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    /**
     * 切换设备收费模式
     * @param Request $request
     * @return array|\think\response\Json
     * @author: iszmxw <mail@54zm.com>
     * @Date：2019/11/15 18:00
     */
    public function device_mode()
    {
        $param    = I('post.');
        $admin_id = session('admin_id');
        // $param      = $request->param();
        $number     = $param['number'];
        $model_type = $param['model_type'];
        $reta       = $param['free_reta'];
        $where      = ['number' => $number];
        if ($admin_id != 1) {
            return json(['code' => 500, 'msg' => '操作失败，仅限超级管理员操作！']);
        }

        if ($model_type == 0) {
            $res = M('lc_equipment_number')->where($where)->update(['mode_type' => 0]);
            if ($res) {
                return json([
                    'code' => 200,
                    'msg'  => '操作成功'
                ]);
            }
        }

        if ($model_type == 1) {
            $res = M('lc_equipment_number')->where($where)->update(['mode_type' => 1]);
            if ($res) {
                return json([
                    'code' => 200,
                    'msg'  => '操作成功'
                ]);
            }
        }
        return json([
            'code' => 200,
            'msg'  => '未做任何修改操作'
        ]);

    }

    //导出设备号
    public function export_user()
    {
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">设备批号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">设备编码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">生成时间</td>';
        $strTable .= '</tr>';
        $timegap  = urldecode(I('timegap'));
        $bank     = I('bank');
        $map      = array();
        if ($timegap) {
            $gap         = explode(',', $timegap);
            $begin       = $gap[0];
            $end         = $gap[1];
            $map['time'] = array('between', array(strtotime($begin), strtotime($end)));
            $this->assign('begin', $begin);
            $this->assign('end', $end);
        }
        if ($bank) {
            $map['bank'] = array('like', "%$bank%");
            $this->assign('bank', $bank);
        }
        //  $map = json_encode($map);

        $count = M('lc_equipment_number')->where($map)->count();

        $p = ceil($count / 5000);
        for ($i = 0; $i < $p; $i++) {
            $start    = $i * 5000;
            $end      = ($i + 1) * 5000;
            $userList = M('lc_equipment_number')->where($map)->order('time')->limit($start, 5000)->select();
            if (is_array($userList)) {
                foreach ($userList as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['bank'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['number'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i', $val['time']) . '</td>';
                    $strTable .= '</tr>';
                }
                unset($userList);
            }
        }
        $strTable .= '</table>';
        downloadExcel($strTable, 'number_' . $i);
        exit();
    }


    /**
     * [package_price 套餐价格设置]
     * @return [type] [description]
     */
    public function package_price()
    {
        $Ad = M('user_level');
        $db = M('package');
        $p  = $this->request->param('p');

        $list = $db->order('pid asc')->page($p . ',10')->select();


        foreach ($list as $key => $val) {
            $list[$key]['date'] = M('package_price')->where('tid', $val['pid'])->select();
        }

        $this->assign('list', $list);
        $count = $Ad->count();
        $Page  = new Page($count, 10);
        $show  = $Page->show();
        $this->assign('page', $show);
        return $this->fetch();
    }


    /**
     * [add_package 添加/修改、套餐]
     */
    public function add_package()
    {

        if (IS_POST) {


            $package_name = I('post.package_name', '', 'trim');
            $default      = I('post.default', 0, 'intval');
            $date         = I('post.');
            $time_date    = $date['spec'];

            if (empty($time_date)) {
                $this->ajaxReturn(['status' => 0, 'msg' => "请添加时间段"]);
            }

            $last_time = array_column($time_date, 'time');
            array_multisort($last_time, SORT_ASC, $time_date);


            // 启动事务
            Db::startTrans();

            try {

                $packa_data = ['title' => $package_name, 'default' => $default];
                db::name('package')->insert($packa_data);
                $pid = Db::name('user')->getLastInsID();
                foreach ($time_date as $key => $value) {

                    $packa_price_data = [
                        'tid'         => $pid,
                        'name'        => $value['time'] . '小时',
                        'time'        => $value['time'],
                        'icon'        => $value['icon'],
                        'price'       => $value['price'],
                        'remark'      => $value['remark'],
                        'green_power' => $value['green_power']
                    ];

                    $result = db::name('package_price')->insert($packa_price_data);

                    if (empty($pid) || empty($result)) {
                        throw new \Exception('添加失败~');
                    }

                }

                if ($default == 1) {
                    $this->save_default($pid);
                }

                // 提交事务
                Db::commit();

                $this->ajaxReturn(['status' => 1, 'msg' => "添加成功~"]);

            } catch (\Exception $e) {

                Db::rollback();

                $mes = $e->getMessage();

                $this->ajaxReturn(['status' => 0, 'msg' => $mes]);
            }

        }

        return $this->fetch();
    }


    /**
     * [add_time 时间段]
     */
    public function add_time()
    {

        if (IS_POST) {

            $data = I('post.');

            $list = M('package_price')->where('tid', $data['pid'])->order('time asc')->select();

            // 启动事务
            Db::startTrans();

            try {

                $namekey   = 'package_name';
                $pricekey  = 'package_price';
                $thumbkey  = 'thumb';
                $remarkkey = 'package_remark';
                for ($i = 0; $i < 4; $i++) {
                    if ($i != 0) {

                        $namekey   = 'package_name' . $i;
                        $pricekey  = 'package_price' . $i;
                        $thumbkey  = 'thumb' . $i;
                        $remarkkey = 'package_remark' . $i;
                    }

                    if (empty($data[$namekey])) {
                        throw new \Exception("时间段不能为空");
                    }
                    if (empty($data[$pricekey])) {
                        throw new \Exception("价格不能为空" . $pricekey);
                    }
                    if (empty($data[$thumbkey])) {
                        throw new \Exception("图片不能为空");
                    }
                    if (empty($data[$remarkkey])) {
                        throw new \Exception("图片不能为空");
                    }

                    $packa_price_data = [
                        'tid'    => $data['pid'],
                        'name'   => $data[$namekey] . '分钟',
                        'time'   => $data[$namekey],
                        'icon'   => $data[$thumbkey],
                        'price'  => $data[$pricekey],
                        'remark' => $data[$remarkkey],
                    ];


                    if (empty($list)) {
                        db::name('package_price')->insert($packa_price_data);
                    } else {
                        db::name('package_price')->where('id', $list[$i]['id'])->update($packa_price_data);
                    }
                }
                // 提交事务
                Db::commit();

                $this->ajaxReturn(['status' => 1, 'msg' => "添加成功~"]);

            } catch (\Exception $e) {

                Db::rollback();

                $mes = $e->getMessage();

                $this->ajaxReturn(['status' => 0, 'msg' => $mes]);
            }
        }

        $pid  = I('pid', 0);
        $list = M('package_price')->where('tid', $pid)->order('time asc')->select();
        $this->assign('pid', $pid);
        $this->assign('list', $list);

        return $this->fetch();
    }


    /**
     * [save_default 修改默认套餐]
     * @return [type] [description]
     */
    public function save_default($pid = '')
    {
        $id  = I('post.pid', '');
        $pid = empty($pid) ? $id : $pid;

        db::name('package')->where('default', 1)->update(['default' => 0]);
        db::name('package')->where('pid', $pid)->update(['default' => 1]);

    }


    /**
     * [delete_package 删除套餐]
     * @return [type] [description]
     */
    public function delete_package()
    {

        $pid = I('post.pid');

        if (empty($pid)) {
            $this->ajaxReturn(['status' => 0, 'msg' => "参数错误"]);
        }

        // 启动事务
        Db::startTrans();

        try {

            $result = db::name('package')->where('pid', $pid)->delete();

            $count = db::name('package_price')->where('tid', $pid)->count();
            if ($count) {
                $res = db::name('package_price')->where('tid', $pid)->delete();
            } else {
                $res = 1;
            }

            if (empty($result) || empty($res)) {
                throw new \Exception('删除失败~' . $tid);
            }

            // 提交事务
            Db::commit();

            $this->ajaxReturn(['status' => 1, 'msg' => "删除成功~"]);

        } catch (\Exception $e) {

            Db::rollback();

            $mes = $e->getMessage();

            $this->ajaxReturn(['status' => 0, 'msg' => $mes]);
        }

    }

    //删除设备
    public function delete_shebei()
    {
        $id = I('post.id');

        if (empty($id)) {
            $this->ajaxReturn(['status' => 0, 'msg' => "参数错误"]);
        }
        $result = M('lc_equipment_number')->where(['id' => $id])->delete();
        if ($result) {
            $this->ajaxReturn(['status' => 1, 'msg' => "删除成功~"]);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => "删除失败"]);
        }


    }


    /**
     * [protocol 干衣机使用协议]
     * @return [type] [description]
     */
    public function protocol()
    {

        $SiteLogic = new SiteLogic();

        if (IS_POST) {

            $title   = I('post.title');
            $content = I('post.content');
            $arr     = ['title' => $title, 'content' => $content];
            $SiteLogic->setShop('protocol', $arr);

            $this->ajaxReturn(['status' => 1, 'msg' => "设置成功~"]);
        }

        $data = $SiteLogic->getShop('protocol');


        $this->assign('info', $data);
        return $this->fetch();
    }

    //扫码充电订单表
    public function power_order()
    {
        $timegap = urldecode(I('timegap'));
        $map     = array();
        if ($timegap) {
            $gap                = explode(',', $timegap);
            $begin              = $gap[0];
            $end                = $gap[1];
            $map['create_time'] = array('between', array(strtotime($begin), strtotime($end)));
            $this->assign('begin', $begin);
            $this->assign('end', $end);
            $map['pay_status'] = 2;//查找全部已经支付的订单
        } else {
            $map['pay_status'] = 2;//查找全部已经支付的订单
        }
        $count = M('power_order')->where($map)->count();
        $page  = new Page($count, 10);
        $lists = M('power_order')->where($map)->order('create_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        //循环查找用户头像，昵称
        foreach ($lists as $k => $v) {
            $users                 = M("users")->where(['user_id' => $v['user_id']])->field("nickname,head_pic")->find();
            $lists[$k]['nickname'] = $users['nickname'];
            $lists[$k]['head_pic'] = $users['head_pic'];
        }
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();

        return $this->fetch();
    }

    //申请列表
    public function apply_list()
    {

        $timegap = urldecode(I('timegap'));
        $bank    = I('bank');

        $map = array();
        if ($timegap) {
            $gap                = explode(',', $timegap);
            $begin              = $gap[0];
            $end                = $gap[1];
            $map['create_time'] = array('between', array(strtotime($begin), strtotime($end)));
            $this->assign('begin', $begin);
            $this->assign('end', $end);
        }
        if ($bank) {
            $map['bank'] = array('like', "%$bank%");
            $this->assign('bank', $bank);
        }
        $map['status'] = 1;
        $count         = M('lc_apply')->where($map)->count();
        $page          = new Page($count, 20);
        $lists         = M('lc_apply')->where($map)->order('create_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    //通过审核
    public function put_apply()
    {
        $id = I('get.id');
        if (!$id) {
            $this->error('网络错误！！');
        }
        $apply  = M("lc_apply")->where(['id' => $id])->find();
        $admins = M("admin")->where(['admin_id' => session('admin_id')])->getField('user_name');
        $agent  = Db::name('lc_apply')->where(['id' => $id])->update(['status' => 2, 'time' => time(), 'admin' => $admins]);
        //更改用户状态
        if ($agent) {
            Db::name('users')->where(['user_id' => $apply['user_id']])->update(['level' => $apply['type']]);
            $this->success('审核完成！！');
        } else {
            $this->error('网络错误！！');
        }

    }

    //删除申请
    public function delete_apply()
    {
        $id = I('post.id');

        if (empty($id)) {
            $this->ajaxReturn(['status' => 0, 'msg' => "参数错误"]);
        }
        $result = M('lc_apply')->where(['id' => $id])->delete();
        if ($result) {
            $this->ajaxReturn(['status' => 1, 'msg' => "删除成功~"]);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => "删除失败"]);
        }


    }

    //酒店列表
    public function hotel_list()
    {
        $timegap  = urldecode(I('timegap'));
        $key_word = trim(I('key_word'));

        $map = array();
        if ($key_word) {
            $map['username|mobile'] = array('like', "%$key_word%");
            $this->assign('key_word', $key_word);
        }
        if (I('id')) {
            $map['entry_uid'] = I('id');
        }
        //  var_dump($key_word);exit;
        $map['status'] = 2;
        $map['type']   = 3;
        $count         = M('lc_apply')->where($map)->count();
        $page          = new Page($count, 10);
        $lists         = M('lc_apply')->where($map)->order('create_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();
    }


    //所有设备列表
    public function hotel_member_list()
    {
        $user_id = I('get.id');
        //获取类型
        $type = I('get.type');
        //获取记录总数
        if ($type == 1) {
            $user_ids = 'j_user_id';
        } else {
            $user_ids = 'f_user_id';
        }
        $count = M('lc_equipment_number')->where(array("{$user_ids}" => $user_id))->count();
        $page  = new Page($count);
        $lists = M('lc_equipment_number')->where(array("{$user_ids}" => $user_id))->order('time desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        if ($lists) {
            foreach ($lists as $k => $v) {
                $lists[$k]['username'] = M("lc_apply")->where(['user_id' => $v['f_user_id']])->value("username");
            }
        }
        $this->assign('user_id', $user_id);
        $this->assign('page', $page->show());
        $this->assign('lists', $lists);
        return $this->fetch();

    }

    //修改酒店身份数据
    public function hotel_edit()
    {
        $id   = I('get.id');
        $list = M("lc_apply")->where(['id' => $id])->find();
        if (IS_POST) {
            $date = I('post.');
            //更改的总分成不能大于分销的分成
            $hotel_fc = M("lc_apply")->where(['user_id' => $list['entry_uid']])->value("one_level");
            if ($hotel_fc == 0) {
                $hotel_fc = M("lc_subcommission")->where(['id' => 1])->value("agent");
            }
            if ($date['one_level'] > $hotel_fc) {
                $this->ajaxReturn(['status' => 1, 'msg' => "修改失败，酒店分成不能大于代理总分成~" . $hotel_fc . '%']);
            }
            $result = M('lc_apply')->where(['id' => $date['id']])->save($date);
            $this->ajaxReturn(['status' => 1, 'msg' => "修改成功~"]);
        }
        $this->assign('list', $list);

        return $this->fetch();


    }

    //删除酒店
    public function hotel_del()
    {
        $data = I('post.');
        if ($data['id']) {
            //1.删除收益记录，删除绑定设备，删除添加记录，身份改为会员
            $user_id = M("lc_apply")->where(['id' => $data['id']])->value("user_id");
            // M("shou_log")->where(['user_id'=>$user_id])->delete();
            M("lc_equipment_number")->where(['j_user_id' => $user_id])->delete();
            $r     = M('lc_apply')->where(['id' => $data['id']])->delete();
            $level = M("users")->where(['user_id' => $user_id])->value("level");
            if ($level == 3) {
                M("users")->where(['user_id' => $user_id])->save(['level' => 2]);
            }
            if ($r) exit(json_encode(1));
        }
        if ($r) {
            $this->success("操作成功", U('Admin/Subcommission/hotel_list'));
        } else {
            $this->error("操作失败");
        }
    }

    //分销商类别
    public function distributor_list()
    {
        $key_word = trim(I('key_word'));

        $map = array();
        if ($key_word) {
            $map['username|mobile'] = array('like', "%$key_word%");
            $this->assign('key_word', $key_word);
        }
        if (I('id')) {
            $map['entry_uid'] = I('id');
        }
        $map['status'] = 2;
        $map['type']   = 4;
        $count         = M('lc_apply')->where($map)->count();
        $page          = new Page($count, 10);

        $lists = M('lc_apply')->where($map)->order('create_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    // 修改分销商
    public function distributor_edit()
    {
        $id   = I('get.id');
        $list = M("lc_apply")->where(['id' => $id])->find();
        if (IS_POST) {
            $date = I('post.');
            // 更改的总分成不能小于门店的分成
            $hotel_fc      = M("lc_apply")->where(['entry_uid' => $list['user_id'], 'type' => 3, 'one_level' => ['gt', 0]])->order("one_level asc")->value("one_level");
            $hotel_fc_free = M("lc_apply")->where(['entry_uid' => $list['user_id'], 'type' => 3, 'one_level_free' => ['gt', 0]])->order("one_level_free asc")->value("one_level_free");
            if ($date['one_level'] < $hotel_fc) {
                $this->ajaxReturn(['status' => 1, 'msg' => "修改失败，代理总分成不能小于门店默认模式最低分成~" . $hotel_fc . '%']);
            }
            if ($date['one_level_free'] < $hotel_fc_free) {
                $this->ajaxReturn(['status' => 1, 'msg' => "修改失败，代理总分成不能小于门店免费模式最低分成~" . $hotel_fc_free . '%']);
            }
            $result = M('lc_apply')->where(['id' => $date['id']])->save($date);
            $this->ajaxReturn(['status' => 1, 'msg' => "修改成功~"]);
        }
        $this->assign('list', $list);

        return $this->fetch();


    }

    //删除分销商
    public function distributor_del()
    {
        $data = I('post.');
        if ($data['id']) {
            //1.删除收益记录，删除绑定设备，删除添加记录，删除绑定的酒店,身份改为会员
            $user_id = M("lc_apply")->where(['id' => $data['id']])->value("user_id");
            //  M("shou_log")->where(['user_id'=>$user_id])->delete();
            M("lc_equipment_number")->where(['f_user_id' => $user_id])->delete();
            M('lc_apply')->where(['entry_uid' => $user_id, 'type' => 3])->delete();//删除分销商添加的所有酒店
            $r     = M('lc_apply')->where(['id' => $data['id']])->delete();
            $level = M("users")->where(['user_id' => $user_id])->value("level");
            if ($level == 4) {
                M("users")->where(['user_id' => $user_id])->save(['level' => 2]);
            }
            if ($r) exit(json_encode(1));
        }
        if ($r) {
            $this->success("操作成功", U('Admin/Subcommission/distributor_list'));
        } else {
            $this->error("操作失败");
        }

    }

    //代理商列表
    public function agent_list()
    {
        $key_word = trim(I('key_word'));

        $map = array();
        if ($key_word) {
            $map['username|mobile'] = array('like', "%$key_word%");
            $this->assign('key_word', $key_word);
        }
        $map['status'] = 2;
        $map['type']   = 5;
        $count         = M('lc_apply')->where($map)->count();
        $page          = new Page($count, 10);

        $lists = M('lc_apply')->where($map)->order('create_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        if ($lists) {
            $subcommission = M("lc_subcommission")->where(['id' => 1])->value("agent");
            $agent_free    = M("lc_subcommission")->where(['id' => 1])->value("agent_free");
            foreach ($lists as $k => $v) {
                if ($v['one_level'] == 0) {
                    $lists[$k]['one_level'] = $subcommission;
                }
                if ($v['one_level_free'] == 0) {
                    $lists[$k]['one_level_free'] = $agent_free;
                }
                if ($v['entry_uid']) {
                    $city = M("lc_apply")->where(['user_id' => $v['entry_uid'], 'type' => 5, 'status' => 2, 'is_on_sale' => 1])->getField('username');
                    if ($city) {
                        $lists[$k]['city'] = $city;
                    } else {
                        $lists[$k]['city'] = '无';
                    }
                } else {
                    $lists[$k]['city'] = '无';
                }
            }
        }
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();

    }

    //修改代理
    public function agent_edit()
    {
        $id    = I('get.id');
        $list  = M("lc_apply")->where(['id' => $id])->find();
        $apply = M("lc_apply")->where(['type' => 5, 'status' => 2, 'is_on_sale' => 1])->field('user_id,username')->select();
        if ($list['one_level'] == 0) {
            //公共可分润比例
            $subcommission     = M("lc_subcommission")->where(['id' => 1])->value("agent");
            $list['one_level'] = $subcommission;
        }
        if (IS_POST) {
            $date = I('post.');
            //更改的总分成不能小于酒店的分成
            $hotel_fc = M("lc_apply")->where(['entry_uid' => $list['user_id'], 'type' => 4, 'one_level' => ['gt', 0]])->order("one_level asc")->value("one_level");
            if ($date['one_level'] < $hotel_fc) {
                $this->ajaxReturn(['status' => 1, 'msg' => "修改失败，代理总分成不能小于分销商拥有最低分成~" . $hotel_fc . '%']);
            }

            if ($date['entry_uid']) {

                $date['entry_level'] = M("users")->where(['user_id' => $date['entry_uid']])->value('level');

            } else {
                $date['entry_uid']   = '';
                $date['entry_level'] = '';
            }
            $result = M('lc_apply')->where(['id' => $date['id']])->save($date);
            $this->ajaxReturn(['status' => 1, 'msg' => "修改成功~"]);
        }
        $this->assign('list', $list);
        $this->assign('apply', $apply);

        return $this->fetch();


    }

    //删除总代理
    public function agent_del()
    {
        $data = I('post.');
        if ($data['id']) {
            //1.删除收益记录，删除绑定设备，删除添加记录，删除绑定的酒店,删除名下分销商和分销商酒店 ,身份改为会员
            $user_id = M("lc_apply")->where(['id' => $data['id']])->value("user_id");
            M("shou_log")->where(['user_id' => $user_id])->delete();
            M("lc_equipment_number")->where(['f_user_id' => $user_id])->delete();
            //查找名下的分销商或酒店

            $f = M('lc_apply')->where(['entry_uid' => $user_id])->select();
            if ($f) {
                foreach ($f as $k => $v) {
                    //如果下级是分销商，删除分销商下面的酒店
                    if ($v['type'] == 4) {
                        M('lc_apply')->where(['entry_uid' => $v['user_id']])->delete();
                    }
                }
            }
            M('lc_apply')->where(['entry_uid' => $user_id])->delete();
            $r = M('lc_apply')->where(['id' => $data['id']])->delete();
            M("users")->where(['user_id' => $user_id])->save(['level' => 2]);
            if ($r) exit(json_encode(1));
        }
        if ($r) {
            $this->success("操作成功", U('Admin/Subcommission/agent_list'));
        } else {
            $this->error("操作失败");
        }

    }

    //扫码充电订单列表
    public function order_statistics()
    {
        //查找总订单金额
        $total_money = M('power_order')->where("pay_status=2 AND pay_price>0")->sum("pay_price");
        //免费充电订单金额
        $total_moneys = M('power_order_free')->where("pay_status=2 AND pay_price>0")->sum("pay_price");
        $total_money  = $total_money + $total_moneys;
        //分出去总金额
        $total_money_f  = M('shou_log')->sum("allf_money");
        $total_money_fs = M('shou_log')->where('type=4')->sum("allf_money");
        $total_money_f  = $total_money_f - $total_money_fs;


        $total_count  = M('power_order')->where("pay_status=2 AND pay_price>0")->count();
        $total_counts = M('power_order_free')->where("pay_status=2 AND pay_price>0")->count();
        $total_count  = $total_count + $total_counts;
        //总退款金额
        $today_money_tuik = M('power_order')->where("pay_status=2 AND pay_price>0 AND status=1")->sum("pay_price");

        //平台总收益
        $total_moneyab      = M('power_order')->where("pay_status=2 AND pay_price>0 AND status<1")->sum("pay_price");
        $total_moneyab      = $total_moneyab + $total_moneys;
        $total_money_system = $total_moneyab - $total_money_f;
        $this->assign('total_money', $total_money);
        $this->assign('total_money_f', $total_money_f);
        $this->assign('total_money_system', $total_money_system);
        $this->assign('total_count', $total_count);

        //php获取今日开始时间戳和结束时间戳
        $beginToday = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $endToday   = mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')) - 1;
        //查找今日总订单金额
        $today_money   = M('power_order')->where("pay_time>$beginToday AND pay_time<$endToday AND pay_status=2 AND pay_price>0")->sum("pay_price");
        $today_moneyy  = M('power_order_free')->where("pay_time>$beginToday AND pay_time<$endToday AND pay_status=2 AND pay_price>0")->sum("pay_price");
        $today_money   = $today_money + $today_moneyy;
        $today_moneys  = M('power_order')->where("pay_time>$beginToday AND pay_time<$endToday AND pay_status=2 AND pay_price>0 AND status<1")->sum("pay_price");
        $today_moneys  = $today_moneyy + $today_moneys;
        $today_moneyss = M('power_order')->where("pay_time>$beginToday AND pay_time<$endToday AND pay_status=2 AND pay_price>0 AND status=1")->sum("pay_price");
        //今日分出去金额
        $today_money_f  = M('shou_log')->where("time>$beginToday AND time<$endToday")->sum("allf_money");
        $today_money_fs = M('shou_log')->where("time>$beginToday AND time<$endToday AND type=4")->sum("allf_money");
        $today_money_f  = $today_money_f - $today_money_fs;
        //今日平台收益
        $today_money_system = $today_moneys - $today_money_f;

        /*  $today_count = M('power_order')->where("pay_time>$beginToday AND pay_time<$endToday AND pay_status=2 AND pay_price>0")->count();*/

        $this->assign('today_money', $today_money);
        $this->assign('today_moneyss', $today_moneyss);
        $this->assign('today_money_tuik', $today_money_tuik);
        $this->assign('today_money_f', $today_money_f);
        $this->assign('today_money_system', $today_money_system);
        /*    $this->assign('today_count', $today_count);*/


        //php获取昨日起始时间戳和结束时间戳
        $beginYesterday = mktime(0, 0, 0, date('m'), date('d') - 1, date('Y'));
        $endYesterday   = mktime(0, 0, 0, date('m'), date('d'), date('Y')) - 1;
        //查找昨日总订单金额
        $tow_money   = M('power_order')->where("pay_time>$beginYesterday AND pay_time<$endYesterday AND pay_status=2 AND pay_price>0")->sum("pay_price");
        $tow_moneyy  = M('power_order_free')->where("pay_time>$beginYesterday AND pay_time<$endYesterday AND pay_status=2 AND pay_price>0")->sum("pay_price");
        $tow_money   = $tow_money + $tow_moneyy;
        $tow_moneys  = M('power_order')->where("pay_time>$beginYesterday AND pay_time<$endYesterday AND pay_status=2 AND pay_price>0 AND status<1")->sum("pay_price");
        $tow_moneys  = $tow_moneys + $tow_moneyy;
        $tow_moneyss = M('power_order')->where("pay_time>$beginYesterday AND pay_time<$endYesterday AND pay_status=2 AND pay_price>0 AND status=1")->sum("pay_price");
        //昨日分出去金额
        $tow_money_f  = M('shou_log')->where("time>$beginYesterday AND time<$endYesterday")->sum("allf_money");
        $tow_money_fs = M('shou_log')->where("time>$beginYesterday AND time<$endYesterday AND type=4")->sum("allf_money");
        $tow_money_f  = $tow_money_f - $tow_money_fs;
        //昨日平台收益
        $tow_money_system = $tow_moneys - $tow_money_f;

        /* $tow_count = M('power_order')->where("pay_time>$beginYesterday AND pay_time<$endYesterday AND pay_status=2 AND pay_price>0")->count();*/

        $this->assign('tow_money', $tow_money);
        $this->assign('tow_moneyss', $tow_moneyss);
        $this->assign('tow_money_f', $tow_money_f);
        $this->assign('tow_money_system', $tow_money_system);
        /*    $this->assign('tow_count', $tow_count);*/

        //php获取上周起始时间戳和结束时间戳

        $beginLastweek = mktime(0, 0, 0, date('m'), date('d') - date('w') + 1 - 7, date('Y'));
        $endLastweek   = mktime(23, 59, 59, date('m'), date('d') - date('w') + 7 - 7, date('Y'));

        //查找上周总订单金额
        $week_money  = M('power_order')->where("pay_time>$beginLastweek AND pay_time<$endLastweek AND pay_status=2 AND pay_price>0")->sum("pay_price");
        $week_moneyy = M('power_order_free')->where("pay_time>$beginLastweek AND pay_time<$endLastweek AND pay_status=2 AND pay_price>0")->sum("pay_price");
        $week_money  = $week_moneyy + $week_money;

        $week_moneys  = M('power_order')->where("pay_time>$beginLastweek AND pay_time<$endLastweek AND pay_status=2 AND pay_price>0 AND status<1")->sum("pay_price");
        $week_moneys  = $week_moneys + $week_moneyy;
        $week_moneyss = M('power_order')->where("pay_time>$beginLastweek AND pay_time<$endLastweek AND pay_status=2 AND pay_price>0 AND status=1")->sum("pay_price");
        //上周分出去金额
        $week_money_f  = M('shou_log')->where("time>$beginLastweek AND time<$endLastweek")->sum("allf_money");
        $week_money_fs = M('shou_log')->where("time>$beginLastweek AND time<$endLastweek AND type=4")->sum("allf_money");
        $week_money_f  = $week_money_f - $week_money_fs;
        //上周平台收益
        $week_money_system = $week_moneys - $week_money_f;


        $week_count = M('power_order')->where("pay_time>$beginLastweek AND pay_time<$endLastweek AND pay_status=2 AND pay_price>0")->count();

        $this->assign('week_money', $week_money);
        $this->assign('week_moneyss', $week_moneyss);
        $this->assign('week_money_f', $week_money_f);
        $this->assign('week_money_system', $week_money_system);
        $this->assign('week_count', $week_count);

        //php获取本月起始时间戳和结束时间戳

        $beginThismonth = mktime(0, 0, 0, date('m'), 1, date('Y'));
        $endThismonth   = mktime(23, 59, 59, date('m'), date('t'), date('Y'));
        //查找本月总订单金额
        $smonth_money   = M('power_order')->where("pay_time>$beginThismonth AND pay_time<$endThismonth AND pay_status=2 AND pay_price>0")->sum("pay_price");
        $smonth_moneyy  = M('power_order_free')->where("pay_time>$beginThismonth AND pay_time<$endThismonth AND pay_status=2 AND pay_price>0")->sum("pay_price");
        $smonth_money   = $smonth_moneyy + $smonth_money;
        $smonth_moneys  = M('power_order')->where("pay_time>$beginThismonth AND pay_time<$endThismonth AND pay_status=2 AND pay_price>0 AND status<1")->sum("pay_price");
        $smonth_moneys  = $smonth_moneys + $smonth_moneyy;
        $smonth_moneyss = M('power_order')->where("pay_time>$beginThismonth AND pay_time<$endThismonth AND pay_status=2 AND pay_price>0 AND status=1")->sum("pay_price");
        //本月分出去金额
        $smonth_money_f  = M('shou_log')->where("time>$beginThismonth AND time<$endThismonth")->sum("allf_money");
        $smonth_money_fs = M('shou_log')->where("time>$beginThismonth AND time<$endThismonth AND type=4")->sum("allf_money");
        $smonth_money_f  = $smonth_money_f - $smonth_money_fs;
        //本月平台收益
        $smonth_money_system = $smonth_moneys - $smonth_money_f;
        $smonth_count        = M('power_order')->where("pay_time>$beginThismonth AND pay_time<$endThismonth AND pay_status=2 AND pay_price>0")->count();

        $this->assign('smonth_money', $smonth_money);
        $this->assign('smonth_moneyss', $smonth_moneyss);
        $this->assign('smonth_money_f', $smonth_money_f);
        $this->assign('smonth_money_system', $smonth_money_system);
        $this->assign('smonth_count', $smonth_count);


        // 上个月的起始时间:

        $lastbegin_time = strtotime(date('Y-m-01 00:00:00', strtotime('-1 month')));
        $lastend_time   = strtotime(date("Y-m-d 23:59:59", strtotime(-date('d') . 'day')));
        //查找上个月总订单金额
        $last_money   = M('power_order')->where("pay_time>$lastbegin_time AND pay_time<$lastend_time AND pay_status=2 AND pay_price>0")->sum("pay_price");
        $last_moneyy  = M('power_order_free')->where("pay_time>$lastbegin_time AND pay_time<$lastend_time AND pay_status=2 AND pay_price>0")->sum("pay_price");
        $last_money   = $last_moneyy + $last_money;
        $last_moneys  = M('power_order')->where("pay_time>$lastbegin_time AND pay_time<$lastend_time AND pay_status=2 AND pay_price>0 AND status<1")->sum("pay_price");
        $last_moneys  = $last_moneys + $last_moneyy;
        $last_moneyss = M('power_order')->where("pay_time>$lastbegin_time AND pay_time<$lastend_time AND pay_status=2 AND pay_price>0 AND status=1")->sum("pay_price");
        //上个月分出去金额
        $last_money_f  = M('shou_log')->where("time>$lastbegin_time AND time<$lastend_time")->sum("allf_money");
        $last_money_fs = M('shou_log')->where("time>$lastbegin_time AND time<$lastend_time AND type=4")->sum("allf_money");
        $last_money_f  = $last_money_f - $last_money_fs;
        //上个月平台收益
        $last_money_system = $last_moneys - $last_money_f;
        $last_count        = M('power_order')->where("pay_time>$lastbegin_time AND pay_time<$lastend_time AND pay_status=2 AND pay_price>0")->count();

        $this->assign('last_money', $last_money);
        $this->assign('last_moneyss', $last_moneyss);
        $this->assign('last_money_f', $last_money_f);
        $this->assign('last_money_system', $last_money_system);
        $this->assign('last_count', $last_count);
        return $this->fetch();


    }


    /**
     * 退款
     * @param float $totalFee 订单金额 单位元
     * @param float $refundFee 退款金额 单位元
     * @param string $refundNo 退款单号
     * @param string $wxOrderNo 微信订单号
     * @param string $orderNo 商户订单号
     * @return string
     */
    public function refundWx()
    {
        $id = I('post.id');
        if (empty($id)) {
            $this->ajaxReturn(['status' => 0, 'msg' => "参数错误~"]);
        }
        $order = M("power_order")->where(['id' => $id])->find();
        if ($order['status'] != 0 && $order['pay_status'] != 2) {
            $this->ajaxReturn(['status' => 0, 'msg' => "参数错误~"]);
        }
        if ($order['pay_price'] == 0) {
            $this->ajaxReturn(['status' => 0, 'msg' => "退款金额有误~"]);
        }
        $config          = array(
            'mch_id' => $this->mchid = '1533376191',
            'appid'  => $this->appid = 'wx9b04ac5aa5c4cc6a',
            'key'    => $this->apiKey = '15998c70d2d3ee19be34d53e0df87d9c',
        );
        $refundNo        = 'RFWX' . $uid . date('Ymd') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8) . rand(10000, 99000);
        $unified         = array(
            'appid'         => $config['appid'],
            'mch_id'        => $config['mch_id'],
            'nonce_str'     => self::createNonceStr(),
            'total_fee'     => intval($order['pay_price'] * 100),    //订单金额  单位 转为分
            'refund_fee'    => intval($order['pay_price'] * 100),    //退款金额 单位 转为分
            'sign_type'     => 'MD5',      //签名类型 支持HMAC-SHA256和MD5，默认为MD5
            //  'transaction_id'=>$wxOrderNo,        //微信订单号
            'out_trade_no'  => $order['order_sn'],    //商户订单号
            'out_refund_no' => $refundNo,    //商户退款单号
            'refund_desc'   => '设备问题',   //退款原因（选填）
        );
        $unified['sign'] = self::getSign($unified, $config['key']);
        $responseXml     = $this->curlPost('https://api.mch.weixin.qq.com/secapi/pay/refund', self::arrayToXml($unified));
        $unifiedOrder    = simplexml_load_string($responseXml, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($unifiedOrder === false) {
            $this->ajaxReturn(['status' => 0, 'msg' => "parse xml error~"]);
        }
        if ($unifiedOrder->return_code != 'SUCCESS') {
            $this->ajaxReturn(['status' => 0, 'msg' => $unifiedOrder->err_code_des]);
        }
        $admins = M("admin")->where(['admin_id' => session('admin_id')])->getField('user_name');
        M("power_order")->where(['id' => $id])->save(['status' => 1, 'admin' => $admins]);
        //判断有没有环保电量抵扣
        if ($order['user_power']) {
            M("users")->where(['user_id' => $order['user_id']])->setInc('green_power', $order['user_power']);
        }
        //退回订单分润
        $shou_log = M("shou_log")->where(['order_sn' => $order['order_sn']])->select();
        if ($shou_log) {
            foreach ($shou_log as $key => $val) {
                M("users")->where(['user_id' => $val['user_id']])->setDec('user_money', $val['allf_money']);
                //添加退款记录
                $arr = [];
                $arr = array('user_id' => $val['user_id'], 'money' => $val['money'], 'pay_user_id' => $val['pay_user_id'], 'time' => time(), 'type' => 4, 'allf_money' => $val['allf_money'], 'order_sn' => $val['order_sn'], 'subcommission' => $val['subcommission'], 'number' => $val['number']);
                M("shou_log")->insert($arr);
            }
        }
        //发送退款成功通知
        /* $message = new Adminmessage();
         $openid = M("users")->where(['user_id'=>$order['user_id']])->value("openid");
         if($openid && $order['formid'] && $order['room_id']) {
             $status = 1;
             $message->pay_room($order['formid'],$order['room_id'],$openid,$status,$order['money']);
         }*/

        $this->ajaxReturn(['status' => 1, 'msg' => $unifiedOrder->err_code_des]);
    }

    public static function curlGet($url = '', $options = array())
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        //https请求 不验证证书和host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    public function curlPost($url = '', $postData = '', $options = array())
    {
        if (is_array($postData)) {
            $postData = http_build_query($postData);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //设置cURL允许执行的最长秒数
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        //https请求 不验证证书和host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        //第一种方法，cert 与 key 分别属于两个.pem文件
        //默认格式为PEM，可以注释

        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
        curl_setopt($ch, CURLOPT_SSLCERT, getcwd() . '/vendor/cert/apiclient_cert.pem');

        //默认格式为PEM，可以注释
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
        curl_setopt($ch, CURLOPT_SSLKEY, getcwd() . '/vendor/cert/apiclient_key.pem');
        //第二种方式，两个文件合成一个.pem文件
//    curl_setopt($ch,CURLOPT_SSLCERT,getcwd().'/all.pem');
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    public static function createNonceStr($length = 16)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str   = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    public static function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
        }
        $xml .= "</xml>";
        return $xml;
    }

    public static function getSign($params, $key)
    {
        ksort($params, SORT_STRING);
        $unSignParaString = self::formatQueryParaMap($params, false);
        $signStr          = strtoupper(md5($unSignParaString . "&key=" . $key));
        return $signStr;
    }

    protected static function formatQueryParaMap($paraMap, $urlEncode = false)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if (null != $v && "null" != $v) {
                if ($urlEncode) {
                    $v = urlencode($v);
                }
                $buff .= $k . "=" . $v . "&";
            }
        }
        $reqPar = '';
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;
    }


}