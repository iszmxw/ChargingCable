<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * Author: 当燃
 * Date: 2015-09-09
 */

namespace app\admin\controller;

use app\admin\logic\OrderLogic;
use app\admin\logic\FinanceLogic;
use app\common\model\UserLabel;
use think\AjaxPage;
use think\console\command\make\Model;
use think\Page;
use think\Verify;
use think\Db;
use app\admin\logic\UsersLogic;
use app\common\logic\MessageTemplateLogic;
use app\common\logic\MessageFactory;
use app\common\model\Withdrawals;
use app\common\model\Users;
use think\Loader;
use think\Cache;

class User extends Base
{

    public function index()
    {
        return $this->fetch();
    }

    /**
     * 会员列表
     */
    public function ajaxindex()
    {
        // 搜索条件
        $condition = array();
        $nickname = I('nickname');
        $account = I('account');
        $account ? $condition['email|mobile'] = ['like', "%$account%"] : false;
        $nickname ? $condition['nickname'] = ['like', "%$nickname%"] : false;

/*      I('first_leader') && ($condition['first_leader'] = I('first_leader')); // 查看一级下线人有哪些
        I('second_leader') && ($condition['second_leader'] = I('second_leader')); // 查看二级下线人有哪些
        I('third_leader') && ($condition['third_leader'] = I('third_leader')); // 查看三级下线人有哪些*/
        $sort_order = I('order_by') . ' ' . I('sort');

        $usersModel = new Users();
        $count = $usersModel->where($condition)->count();
        $Page = new AjaxPage($count, 10);
        $userList = $usersModel->where($condition)->order($sort_order)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $user_id_arr = get_arr_column($userList, 'user_id');
       /* if (!empty($user_id_arr)) {
            $first_leader = DB::query("select first_leader,count(1) as count  from __PREFIX__users where first_leader in(" . implode(',', $user_id_arr) . ")  group by first_leader");
            $first_leader = convert_arr_key($first_leader, 'first_leader');

            $second_leader = DB::query("select second_leader,count(1) as count  from __PREFIX__users where second_leader in(" . implode(',', $user_id_arr) . ")  group by second_leader");
            $second_leader = convert_arr_key($second_leader, 'second_leader');

            $third_leader = DB::query("select third_leader,count(1) as count  from __PREFIX__users where third_leader in(" . implode(',', $user_id_arr) . ")  group by third_leader");
            $third_leader = convert_arr_key($third_leader, 'third_leader');
        }
        $this->assign('first_leader', $first_leader);
        $this->assign('second_leader', $second_leader);
        $this->assign('third_leader', $third_leader);*/
        $show = $Page->show();
        $this->assign('userList', $userList);
  /*      $this->assign('level', M('user_level')->getField('level_id,level_name'));*/
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    /**
     * 会员详细信息查看
     */
    public function detail()
    {
        $uid = I('get.id');
        $user = D('users')->where(array('user_id' => $uid))->find();
        if (!$user)
            exit($this->error('会员不存在'));
        if (IS_POST) {
            //  会员信息编辑
            $password = I('post.password');
            $password2 = I('post.password2');
            if ($password != '' && $password != $password2) {
                exit($this->error('两次输入密码不同'));
            }
            if ($password == '' && $password2 == '') {
                unset($_POST['password']);
            } else {
                $_POST['password'] = encrypt($_POST['password']);
            }

            if (!empty($_POST['email'])) {
                $email = trim($_POST['email']);
                $c = M('users')->where("user_id != $uid and email = '$email'")->count();
                $c && exit($this->error('邮箱不得和已有用户重复'));
            }

            if (!empty($_POST['mobile'])) {
                $mobile = trim($_POST['mobile']);
                $c = M('users')->where("user_id != $uid and mobile = '$mobile'")->count();
                $c && exit($this->error('手机号不得和已有用户重复'));
            }

            $userLevel = D('user_level')->where('level_id=' . $_POST['level'])->value('discount');
            $_POST['discount'] = $userLevel / 100;
            //如果设置是临时合伙人身份
            if($_POST['level'] == 10){
                //添加开始设置时间和到期时间
                //查找合伙人有效期时间
                $sub = db("lc_subcommission")->where(['id'=>1])->field("time_he")->find();
                $time = $sub['time_he'];
                $end = date('Y-m-d H:i:s',strtotime("+{$time}day"));
                $end = strtotime($end);
                $_POST['end_time'] = $end;
            }
            $row = M('users')->where(array('user_id' => $uid))->save($_POST);
            if ($row)
                exit($this->success('修改成功'));
            exit($this->error('未作内容修改或修改失败'));
        }

        $user['first_lower'] = M('users')->where("first_leader = {$user['user_id']}")->count();
        $user['second_lower'] = M('users')->where("second_leader = {$user['user_id']}")->count();
        $user['third_lower'] = M('users')->where("third_leader = {$user['user_id']}")->count();

        $this->assign('user', $user);
        return $this->fetch();
    }

    public function add_user()
    {
        if (IS_POST) {
            $data = I('post.');
            $user_obj = new UsersLogic();
            $res = $user_obj->addUser($data);
            if ($res['status'] == 1) {
                $this->success('添加成功', U('User/index'));
                exit;
            } else {
                $this->error('添加失败,' . $res['msg'], U('User/index'));
            }
        }
        return $this->fetch();
    }

    public function export_user()
    {
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">会员ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">会员昵称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">会员等级</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">手机号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">邮箱</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">注册时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">最后登陆</td>';
        /*$strTable .= '<td style="text-align:center;font-size:12px;" width="*">余额</td>';*/
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">积分</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">维护卡</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">累计消费</td>';
        $strTable .= '</tr>';
        $user_ids = I('user_ids');
        if ($user_ids) {
            $condition['user_id'] = ['in', $user_ids];
        } else {
            $mobile = I('mobile');
            $email = I('email');
            $mobile ? $condition['mobile'] = $mobile : false;
            $email ? $condition['email'] = $email : false;
        };
        $count = DB::name('users')->where($condition)->count();
        $p = ceil($count / 5000);
        for ($i = 0; $i < $p; $i++) {
            $start = $i * 5000;
            $end = ($i + 1) * 5000;
            $userList = M('users')->where($condition)->order('user_id')->limit($start,5000)->select();
            if (is_array($userList)) {
                foreach ($userList as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_id'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['nickname'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['level'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['mobile'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['email'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i', $val['reg_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i', $val['last_login']) . '</td>';
                    /*$strTable .= '<td style="text-align:left;font-size:12px;">' . $val['user_money'] . '</td>';*/
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['pay_points'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['pay_currency'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['total_amount'] . ' </td>';
                    $strTable .= '</tr>';
                }
                unset($userList);
            }
        }
        $strTable .= '</table>';
        downloadExcel($strTable, 'users_' . $i);
        exit();
    }

    /**
     * 用户收货地址查看
     */
    public function address()
    {
        $uid = I('get.id');
        $lists = D('user_address')->where(array('user_id' => $uid))->select();
        $regionList = get_region_list();
        $this->assign('regionList', $regionList);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    /**
     * 删除会员
     */
    public function delete()
    {
        $uid = I('get.id');

        //先删除ouath_users表的关联数据
        M('OuathUsers')->where(array('user_id' => $uid))->delete();
        $row = M('users')->where(array('user_id' => $uid))->delete();
        if ($row) {
            $this->success('成功删除会员');
        } else {
            $this->error('操作失败');
        }
    }

    /**
     * 删除会员
     */
    public function ajax_delete()
    {
        $uid = I('id');
        if ($uid) {
            $row = M('users')->where(array('user_id' => $uid))->delete();
            if ($row !== false) {
                //把关联的第三方账号删除
                M('OauthUsers')->where(array('user_id' => $uid))->delete();
                $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功', 'data' => ''));
            } else {
                $this->ajaxReturn(array('status' => 0, 'msg' => '删除失败', 'data' => ''));
            }
        } else {
            $this->ajaxReturn(array('status' => 0, 'msg' => '参数错误', 'data' => ''));
        }
    }

    /**
     * 账户资金记录
     */
    public function account_log()
    {
        $user_id = I('get.id');
        //获取类型
        $type = I('get.type');
        //获取记录总数
        $count = M('shou_log')->where(array('user_id' => $user_id))->count();
        $page = new Page($count);
        $lists = M('shou_log')->where(array('user_id' => $user_id))->order('time desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        //总订单金额
        $total_order_money = M('shou_log')->where(array('user_id' => $user_id))->sum('money');
        $this->assign('total_order_money', $total_order_money);
        //获得总分润金额
        $total_allf_money = M('shou_log')->where(array('user_id' => $user_id))->sum('allf_money');
        $this->assign('total_allf_money', $total_allf_money);
        //已提现金额
        $total_werdow = M("withdrawals")->where(array('user_id' => $user_id,'status'=>1))->sum('money');
        $this->assign('total_werdow', $total_werdow);
        //申请中提现金额
        $total_werdow_start = M("withdrawals")->where(array('user_id' => $user_id,'status'=>0))->sum('money');
        $this->assign('total_werdow_start', $total_werdow_start);

        //当前账户余额
        $money = M('users')->where(array('user_id' => $user_id))->value('user_money');
        $this->assign('money', $money);
        //提现中冻结金额
        $money_dj = M('users')->where(array('user_id' => $user_id))->value('frozen_money');
        $this->assign('money_dj', $money_dj);
        $this->assign('user_id', $user_id);
        $this->assign('page', $page->show());
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    /**
     * 账户资金调节
     */
    public function account_edit()
    {
        $user_id = I('user_id');
        if (!$user_id > 0) $this->ajaxReturn(['status' => 0, 'msg' => "参数有误"]);
        $user = M('users')->field('user_id,user_money,frozen_money,pay_points,is_lock,pay_currency')->where('user_id', $user_id)->find();
        if (IS_POST) {
          /*  $desc = I('post.desc');
            if (!$desc)
                $this->ajaxReturn(['status' => 0, 'msg' => "请填写操作说明"]);*/
            //加减用户资金
            //$m_op_type = I('post.money_act_type');
            $user_money = I('post.user_money/f');
           /* $user_money = $m_op_type ? $user_money : 0 - $user_money;
            if (($user['user_money'] + $user_money) < 0) {
                $this->ajaxReturn(['status' => 0, 'msg' => "用户剩余资金不足！！"]);
            }*/
           // if($user_money > 0){
                M('users')->where('user_id', $user_id)->update(['user_money' => $user_money]);
           // }
            //加减用户积分
            /*$p_op_type = I('post.point_act_type');
            $pay_points = I('post.pay_points/d');
            $pay_points = $p_op_type ? $pay_points : 0 - $pay_points;
            if (($pay_points + $user['pay_points']) < 0) {
                $this->ajaxReturn(['status' => 0, 'msg' => '用户剩余积分不足！！']);
            }*/
            //加减维护卡操作
           /* $currency_type = I('post.currency_type');//1加0减
            $pay_currency = I('post.pay_currency');//维护卡数量
            if($pay_currency != 0) {
                $admins = M("admin")->where(['admin_id'=>session('admin_id')])->getField('user_name');
                //增加
                if ($currency_type == 1) {
                    //查看维护卡总池数量
                    $arr = M("lc_subcommission")->where(['id' => 1])->field("all_pay_currency")->find();
                    if ($arr['all_pay_currency'] < $pay_currency) {
                        $this->ajaxReturn(['status' => 0, 'msg' => '维护卡总池数量不足！！']);
                    }
                    $res = M('users')->where('user_id', $user_id)->setInc('pay_currency', $pay_currency);
                    if ($res) {
                        //维护卡总池数量减
                        $arrs = M("lc_subcommission")->where(['id' => 1])->setDec("all_pay_currency", $pay_currency);
                        if ($arrs) {
                            //生成维护卡充值记录
                            $array = ['number' => $pay_currency, 'user_id' => $user_id, 'time' => time(), 'type' => 1, 'groups' => 1,'admin'=>$admins];//1;充值
                            M("lc_currency")->add($array);
                        }
                    }
                }
                if ($currency_type == 0) {
                    if ($user['pay_currency'] < $pay_currency) {
                        $this->ajaxReturn(['status' => 0, 'msg' => '用户剩余维护卡不足！！']);
                    }
                    $res = M('users')->where('user_id', $user_id)->setDec('pay_currency', $pay_currency);
                    if ($res) {
                        //总池加维护卡
                        $arrs = M("lc_subcommission")->where(['id' => 1])->setInc("all_pay_currency", $pay_currency);
                        if ($arrs) {
                            //生成维护回收记录

                            $array = ['number' => $pay_currency, 'user_id' => $user_id, 'time' => time(), 'type' => 0, 'groups' => 1,'admin'=>$admins];//0:回收
                            M("lc_currency")->add($array);
                        }
                    }

                }
            }*/
                //加减冻结资金
           // $f_op_type = I('post.frozen_act_type');
            $revision_frozen_money = I('post.frozen_money/f');
          //  if($revision_frozen_money > 0){
                M('users')->where('user_id', $user_id)->update(['frozen_money' => $revision_frozen_money]);
           // }
           /* if ($revision_frozen_money != 0) {    //有加减冻结资金的时候
                $frozen_money = $f_op_type ? $revision_frozen_money : 0 - $revision_frozen_money;
                $frozen_money = $user['frozen_money'] + $frozen_money;    //计算用户被冻结的资金
                if ($f_op_type == 1 && $revision_frozen_money > $user['user_money']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "用户剩余资金不足！！"]);
                }
                if ($f_op_type == 0 && $revision_frozen_money > $user['frozen_money']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "冻结的资金不足！！"]);
                }
                $user_money = $f_op_type ? 0 - $revision_frozen_money : $revision_frozen_money;    //计算用户剩余资金
                M('users')->where('user_id', $user_id)->update(['frozen_money' => $frozen_money]);
            }*/
           // if (accountLog($user_id, $user_money, 0, $desc, 0)) {
                $this->ajaxReturn(['status' => 1, 'msg' => "操作成功", 'url' => U("Admin/User/account_log", array('id' => $user_id))]);
          // } else {
          //      $this->ajaxReturn(['status' => -1, 'msg' => "操作失败"]);
         //   }
            exit;
        }
        $this->assign('user_id', $user_id);
        $this->assign('user', $user);
        return $this->fetch();
    }

    public function recharge()
    {
        $timegap = urldecode(I('timegap'));
        $nickname = I('nickname');
        $map = array();
        if ($timegap) {
            $gap = explode(',', $timegap);
            $begin = $gap[0];
            $end = $gap[1];
            $map['ctime'] = array('between', array(strtotime($begin), strtotime($end)));
            $this->assign('begin', $begin);
            $this->assign('end', $end);
        }
        if ($nickname) {
            $map['nickname'] = array('like', "%$nickname%");
            $this->assign('nickname', $nickname);
        }
        $count = M('recharge')->where($map)->count();
        $page = new Page($count);
        $lists = M('recharge')->where($map)->order('ctime desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    public function level()
    {
        $act = I('get.act', 'add');
        $this->assign('act', $act);
        $level_id = I('get.level_id');
        if ($level_id) {
            $level_info = D('user_level')->where('level_id=' . $level_id)->find();
            $this->assign('info', $level_info);
        }
        return $this->fetch();
    }

    public function levelList()
    {
        $Ad = M('user_level');
        $p = $this->request->param('p');
        $res = $Ad->order('level_id')->page($p . ',10')->select();
        if ($res) {
            foreach ($res as $val) {
                $list[] = $val;
            }
        }
        $this->assign('list', $list);
        $count = $Ad->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $this->assign('page', $show);
        return $this->fetch();
    }

    /**
     * 会员等级添加编辑删除
     */
    public function levelHandle()
    {
        $data = I('post.');
        $userLevelValidate = Loader::validate('UserLevel');
        $return = ['status' => 0, 'msg' => '参数错误', 'result' => ''];//初始化返回信息
        if ($data['act'] == 'add') {
            if (!$userLevelValidate->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '添加失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = D('user_level')->add($data);
                if ($r !== false) {
                    $return = ['status' => 1, 'msg' => '添加成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '添加失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'edit') {
            if (!$userLevelValidate->scene('edit')->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '编辑失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = D('user_level')->where('level_id=' . $data['level_id'])->save($data);
                if ($r !== false) {
                    $discount = $data['discount'] / 100;
                    D('users')->where(['level' => $data['level_id']])->save(['discount' => $discount]);
                    $return = ['status' => 1, 'msg' => '编辑成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '编辑失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'del') {
            $r = D('user_level')->where('level_id=' . $data['level_id'])->delete();
            if ($r !== false) {
                $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
            }
        }
        $this->ajaxReturn($return);
    }

    /**
     * 搜索用户名
     */
    public function search_user()
    {
        $search_key = trim(I('search_key'));
        if ($search_key == '') $this->ajaxReturn(['status' => -1, 'msg' => '请按要求输入！！']);
        $list = M('users')->where(['nickname' => ['like', "%$search_key%"]])->select();
        if ($list) {
            $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'result' => $list]);
        }
        $this->ajaxReturn(['status' => -1, 'msg' => '未查询到相应数据！！']);
    }

    public function agent_apply(){
        $Ad = M('user_agent');
        $p = $this->request->param('p');
        $res = Db::name('user_agent')->alias('a')->field('a.*,u.nickname,l.level_name')
            ->join('__USERS__ u', 'u.user_id = a.user_id', 'INNER')
            ->join('__USER_LEVEL__ l', 'l.level_id = a.level_id', 'INNER')
            ->order("a.id")->page($p . ',10')->select();
        if ($res) {
            foreach ($res as $val) {
                $list[] = $val;
            }
        }
        $this->assign('list', $list);
        $count = $Ad->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $this->assign('page', $show);
        return $this->fetch();
    }

    public function apply(){
        $apply_id = I('get.id');
        $user_agent = Db::name('user_agent')->where(['id'=>$apply_id])->find();
        if(!$user_agent['is_bond']){
            $this->error('请提醒用户缴纳保证金！！');
        }
        if(!$user_agent['is_fund']){
            $this->error('请提醒用户缴纳合作金！！');
        }
        M('user_agent')->where(array('id' => $apply_id))->save(['status'=>1]);

        $level_info = Db::name('user_level')->where(['level_id'=>$user_agent['level_id']])->find();
        // 客户没添加用户等级，上报没有累计消费的bug
        if($level_info){
            $update['level'] = $level_info['level_id'];
            $update['discount'] = $level_info['discount'] / 100;
        }
        Db::name('users')->where("user_id", $user_agent['user_id'])->save($update);

        //如果当前会员等级超过上级会员则清空上级
        $ue = M('users')->where(array('user_id' => $user_agent['user_id']))->find();
        if($ue["first_leader"]){
            $first_level = M('users')->where(array('user_id' => $ue["first_leader"]))->getField("level");
            if($user_agent['level_id']>$first_level && $user_agent['level_id']!=7){
                M('users')->where(array('user_id' => $user_agent['user_id']))->save(['first_leader'=>0, 'second_leader'=>0]);
                M('users')->where(array('user_id' => $ue['first_leader']))->setDec('underling_number');
                M('users')->where(array('user_id' => $ue['second_leader']))->setDec('underling_number');
            }
        }

        //奖励金发放
        $rebate_log = M('rebate_log')->where("status = 0 and order_id=0")->select();
        foreach ($rebate_log as $key => $val)
        {
            accountLog($val['user_id'], $val['money'], 0,"奖励金发放",$val['money']);
            $val['status'] = 3;
            $val['confirm_time'] = time();
            $val['remark'] = "奖励金发放";
            M("rebate_log")->where("id", $val['id'])->save($val);
        }

        $this->success('审核完成！！');
    }


    /**
     * 分销树状关系
     */
    public function ajax_distribut_tree()
    {
        $list = M('users')->where("first_leader = 1")->select();
        return $this->fetch();
    }

    /**
     *
     * @time 2016/08/31
     * @author dyr
     * 发送站内信
     */
    public function sendMessage()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $users = M('users')->field('user_id,nickname')->where(array('user_id' => array('IN', $user_id_array)))->select();
        }
        $this->assign('users', $users);
        return $this->fetch();
    }

    /**
     * 发送系统通知消息
     * @author yhj
     * @time  2018/07/10
     */
    public function doSendMessage()
    {
        $call_back = I('call_back');//回调方法
        $message_content = I('post.text', '');//内容
        $message_title = I('post.title', '');//标题
        $message_type = I('post.type', 0);//个体or全体
        $users = I('post.user/a');//个体id
        $message_val = ['name' => ''];
        $send_data = array(
            'message_title' => $message_title,
            'message_content' => $message_content,
            'message_type' => $message_type,
            'users' => $users,
            'type' => 0, //0系统消息
            'message_val' => $message_val,
            'category' => 0,
            'mmt_code' => 'message_notice'
        );

        $messageFactory = new MessageFactory();
        $messageLogic = $messageFactory->makeModule($send_data);
        $messageLogic->sendMessage();

        echo "<script>parent.{$call_back}(1);</script>";
        exit();
    }

    /**
     *
     * @time 2016/09/03
     * @author dyr
     * 发送邮件
     */
    public function sendMail()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $user_where = array(
                'user_id' => array('IN', $user_id_array),
                'email' => array('neq', '')
            );
            $users = M('users')->field('user_id,nickname,email')->where($user_where)->select();
        }
        $this->assign('smtp', tpCache('smtp'));
        $this->assign('users', $users);
        return $this->fetch();
    }

    /**
     * 发送邮箱
     * @author dyr
     * @time  2016/09/03
     */
    public function doSendMail()
    {
        $call_back = I('call_back');//回调方法
        $message = I('post.text');//内容
        $title = I('post.title');//标题
        $users = I('post.user/a');
        $email = I('post.email');
        if (!empty($users)) {
            $user_id_array = implode(',', $users);
            $users = M('users')->field('email')->where(array('user_id' => array('IN', $user_id_array)))->select();
            $to = array();
            foreach ($users as $user) {
                if (check_email($user['email'])) {
                    $to[] = $user['email'];
                }
            }
            $res = send_email($to, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
        if ($email) {
            $res = send_email($email, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
    }

    /**
     *  转账汇款记录
     */
    public function remittance()
    {
        $status = I('status', 1);
        $realname = I('realname');
        $bank_card = I('bank_card');
        $where['status'] = $status;
        $realname && $where['realname'] = array('like', '%' . $realname . '%');
        $bank_card && $where['bank_card'] = array('like', '%' . $bank_card . '%');

        $create_time = urldecode(I('create_time'));
        // echo urldecode($create_time);
        // echo $create_time;exit;
        // $create_time = str_replace('+', '', $create_time);

        $create_time = $create_time ? $create_time : date('Y-m-d H:i:s', strtotime('-1 year')) . ',' . date('Y-m-d H:i:s', strtotime('+1 day'));
        $create_time3 = explode(',', $create_time);
        $this->assign('start_time', $create_time3[0]);
        $this->assign('end_time', $create_time3[1]);
        if ($status == 2) {
            $time_name = 'pay_time';
            $export_time_name = '转账时间';
            $export_status = '已转账';
        } else {
            $time_name = 'check_time';
            $export_time_name = '审核时间';
            $export_status = '待转账';
        }
        $where[$time_name] = array(array('gt', strtotime($create_time3[0])), array('lt', strtotime($create_time3[1])));
        $withdrawalsModel = new Withdrawals();
        $count = $withdrawalsModel->where($where)->count();
        $Page = new page($count, C('PAGESIZE'));
        $list = $withdrawalsModel->where($where)->limit($Page->firstRow, $Page->listRows)->order("id desc")->select();
        if (I('export') == 1) {
            # code...导出记录
            $selected = I('selected');
            if (!empty($selected)) {
                $selected_arr = explode(',', $selected);
                $where['id'] = array('in', $selected_arr);
            }
            $list = $withdrawalsModel->where($where)->order("id desc")->select();
            $strTable = '<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">用户昵称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="100">银行机构名称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">账户号码</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">账户开户名</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请金额</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">状态</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">' . $export_time_name . '</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">备注</td>';
            $strTable .= '</tr>';
            if (is_array($list)) {
                foreach ($list as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['users']['nickname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['bank_name'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['bank_card'] . '</td>';
                    $strTable .= '<td style="vnd.ms-excel.numberformat:@">' . $val['realname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['money'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $export_status . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i:s', $val[$time_name]) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['remark'] . '</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .= '</table>';
            unset($remittanceList);
            downloadExcel($strTable, 'remittance');
            exit();
        }

        $show = $Page->show();
        $this->assign('show', $show);
        $this->assign('status', $status);
        $this->assign('Page', $Page);
        $this->assign('list', $list);
        return $this->fetch();
    }


    public function get_withdrawals_list($status = '')
    {
        $id = I('selected/a');
        $user_id = I('user_id/d');
        $realname = I('realname');
        $bank_card = I('bank_card');
        $create_time = urldecode(I('create_time'));
        $create_time = $create_time ? $create_time : date('Y-m-d H:i:s', strtotime('-1 year')) . ',' . date('Y-m-d H:i:s', strtotime('+1 day'));
        $create_time3 = explode(',', $create_time);
        $this->assign('start_time', $create_time3[0]);
        $this->assign('end_time', $create_time3[1]);
        $where['w.create_time'] = array(array('gt', strtotime($create_time3[0])), array('lt', strtotime($create_time3[1])));

        $status = empty($status) ? I('status') : $status;
        if ($status !== '') {
            $where['w.status'] = $status;
        } else {
            $where['w.status'] = ['lt', 2];
        }
        if ($id) {
            $where['w.id'] = ['in', $id];
        }
        $type = I('type');
        if($type !== ''){
            $where['w.type'] = ['eq', $type];
        }
        $user_id && $where['u.user_id'] = $user_id;
        $realname && $where['w.realname'] = array('like', '%' . $realname . '%');
        $bank_card && $where['w.bank_card'] = array('like', '%' . $bank_card . '%');
        $export = I('export');
        if ($export == 1) {
            $strTable = '<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">申请人</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="100">提现金额</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行名称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行账号</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">开户人姓名</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请时间</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">提现备注</td>';
            $strTable .= '</tr>';
            $remittanceList = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->select();
            if (is_array($remittanceList)) {
                foreach ($remittanceList as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['nickname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['money'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['bank_name'] . '</td>';
                    $strTable .= '<td style="vnd.ms-excel.numberformat:@">' . $val['bank_card'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['realname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i:s', $val['create_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['remark'] . '</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .= '</table>';
            unset($remittanceList);
            downloadExcel($strTable, 'remittance');
            exit();
        }
        $count = Db::name('withdrawals')->alias('w')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->count();
        $Page = new Page($count, 20);
        $list = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        //$this->assign('create_time',$create_time2);
        $show = $Page->show();
        /*$money = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->sum('money');
        $taxfee = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->sum('taxfee');*/
        $zong = Db::name('withdrawals')->alias('w')->field('sum(money) as money,sum(taxfee) as taxfee')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->find();

        $this->assign('show', $show);
        $this->assign('list', $list);
        $this->assign('type', $type);
        $this->assign('pager', $Page);
        $this->assign('zong', $zong);
        C('TOKEN_ON', false);
    }

    /**
     * 删除申请记录
     */
    public function delWithdrawals()
    {
        $id = I('del_id/d');
        $res = Db::name("withdrawals")->where(['id' => $id])->delete();
        if ($res !== false) {
            $return_arr = ['status' => 1, 'msg' => '操作成功', 'data' => '',];
        } else {
            $return_arr = ['status' => -1, 'msg' => '删除失败', 'data' => '',];
        }
        $this->ajaxReturn($return_arr);
    }

    /**
     * 修改编辑 申请提现
     */
    public function editWithdrawals()
    {
        $id = I('id');
        $withdrawals = Db::name("withdrawals")->find($id);
        $user = M('users')->where(['user_id' => $withdrawals['user_id']])->find();
        if ($user['nickname'])
            $withdrawals['user_name'] = $user['nickname'];
        elseif ($user['email'])
            $withdrawals['user_name'] = $user['email'];
        elseif ($user['mobile'])
            $withdrawals['user_name'] = $user['mobile'];
        $status = $withdrawals['status'];
        $withdrawals['status_code'] = C('WITHDRAW_STATUS')["$status"];
        $this->assign('user', $user);
        $this->assign('data', $withdrawals);
        return $this->fetch();
    }

    /**
     *  处理会员提现申请
     */
    public function withdrawals_update()
    {
        $id_arr = I('id/a');
        $data['status'] = $status = I('status');
        $data['remark'] = I('remark');
        $ids = implode(',', $id_arr);
        if ($status == 1) $data['check_time'] = time();
        if ($status != 1){
            $data['refuse_time'] = time();
            $withdrawals = Db::name('withdrawals')->whereIn('id', $ids)->select();
            foreach ($withdrawals as $k => $v){
                /*if($v['taxfee']>0){
                    accountLog($v['user_id'], $v['taxfee'], 0, "返回提现手续费");//手动转账
                }*/
                //accountLog($v['user_id'], $v['money'] + $v['taxfee'], 0, "提现拒绝返回余额");//手动转账，
                //提现驳回
                $frozen_money= M("users")->where(['user_id'=>$v['user_id']])->value("frozen_money");
                if($frozen_money>$v['money'] || $frozen_money==$v['money']) {
                    M("users")->where(['user_id' => $v['user_id']])->setInc('user_money', $v['money']);
                    M("users")->where(['user_id' => $v['user_id']])->setDec('frozen_money', $v['money']);
                }
            }
        }
        $r = Db::name('withdrawals')->whereIn('id', $ids)->update($data);

        if ($r !== false) {
            $this->ajaxReturn(array('status' => 1, 'msg' => "操作成功"), 'JSON');
        } else {
            $this->ajaxReturn(array('status' => 0, 'msg' => "操作失败"), 'JSON');
        }
    }

    // 用户申请提现
    public function transfer()
    {
        $id = I('selected/a');
        if (empty($id)) $this->error('请至少选择一条记录');
        $atype = I('atype');
        if (is_array($id)) {
            $withdrawals = M('withdrawals')->where('id in (' . implode(',', $id) . ')')->select();
        } else {
            $withdrawals = M('withdrawals')->where(array('id' => $id))->select();
        }
        $messageFactory = new \app\common\logic\MessageFactory();
        $messageLogic = $messageFactory->makeModule(['category' => 0]);

        $alipay['batch_num'] = 0;
        $alipay['batch_fee'] = 0;
        foreach ($withdrawals as $val) {
            $user = M('users')->where(array('user_id' => $val['user_id']))->find();
            //$oauthUsers = M("OauthUsers")->where(['user_id'=>$user['user_id'] , 'oauth_child'=>'mp'])->find();
            $oauthUsers = M("OauthUsers")->where(['user_id' => $user['user_id'], 'oauth' => 'weixin'])->find();
            //获取用户绑定openId
            $user['openid'] = $oauthUsers['openid'];
            if ($user['user_money'] < $val['money']) {
                $data = array('status' => -2, 'remark' => '账户余额不足');
                M('withdrawals')->where(array('id' => $val['id']))->save($data);
                $this->error('账户余额不足');
            } else {
                $rdata = array('type' => 1, 'money' => $val['money'], 'log_type_id' => $val['id'], 'user_id' => $val['user_id']);
                if ($atype == 'online') {
                    header("Content-type: text/html; charset=utf-8");
                    exit("请联系客服");
                } else {
                   /* accountLog($val['user_id'], ($val['money'] * -1), 0, "管理员处理用户提现申请");//手动转账，默认视为已通过线下转方式处理了该笔提现申请
                    $r = M('withdrawals')->where(array('id' => $val['id']))->save(array('status' => 2, 'pay_time' => time()));
                    expenseLog($rdata);//支出记录日志
                    // 提现通知
                    $messageLogic->withdrawalsNotice($val['id'], $val['user_id'], $val['money'] - $val['taxfee']);*/
                 /*  $object = $this->wxtixian($val['id']);
                   if($object['status']=='succen'){//提现成功
                       M("users")->where(['user_id' => $v['user_id']])->setDec('frozen_money', $v['money']);
                       $data = array('status' => 1, 'remark' => '提现成功','trade_no'=>$object['status']);
                       M('withdrawals')->where(array('id' => $val['id']))->save($data);
                       $this->error('提现成功！！');

                   }else{
                       $this->error($object['msg']);
                   }*/


                }
            }
        }
        if ($alipay['batch_num'] > 0) {
            //支付宝在线批量付款
            include_once PLUGIN_PATH . "payment/alipay/alipay.class.php";
            $alipay_obj = new \alipay();
            $alipay_obj->transfer($alipay);
        }
        $this->success("操作成功!", U('remittance'), 3);
    }



    //企业提现到微信零钱

    //封装提现方法
   public function wxtixian($id){
        $withdrawals = M("withdrawals")->where('id',$id)->find();
        $openid = M("users")->where('user_id',$withdrawals['user_id'])->value("openid");
       //判断用户是否为第一次提现
       $withdrawals_num = M("withdrawals")->where(['user_id'=>$withdrawals['user_id'],'status'=>['neq',-1]])->count();
       //查询提现条件
       $config = tpCache('cash');
       $free = 0;
       $max_money = $config['count_card'];//满足小于
       $count_cash = $config['count_cash'];//手续费比例
       if($withdrawals_num>0){
           //收取手续费

            if($withdrawals['money'] < $max_money){
               $free = ($count_cash*0.01) * $withdrawals['money'];
               $free = number_format($free,2);
                $withdrawals['money'] = $withdrawals['money'] - $free;
            }
       }

        $appid = 'wx9b04ac5aa5c4cc6a';//商户账号appid
        $mch_id = "1533376191";//商户号
        $arr = array();
        $arr['mch_appid'] = $appid;
        $arr['mchid'] = $mch_id;
        $arr['nonce_str'] = $this->genRandomString();//随机字符串，不长于32位
        $arr['partner_trade_no'] = 'HS' . date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8) . $withdrawals['user_id'] . rand(10000, 99000);//商户订单号
        $arr['openid'] = $openid;
        $arr['check_name'] = 'NO_CHECK';//是否验证用户真实姓名，这里不验证
        $arr['amount'] = $withdrawals['money'] *100;//付款金额，单位为分
        $desc = "商家提现";
        $arr['desc'] = $desc;//描述信息
        $arr['spbill_create_ip'] = '120.79.225.55';//获取服务器的ip
        //封装的关于签名的算法


        $arr['sign'] = $this->MakeSign($arr);//签名

        $var = $this->arrayToXml($arr);

        $xml = $this->curl_post_ssl('https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers', $var, 30, array(), 1);
        $rdata = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $return_code = (string)$rdata->return_code;
        $result_code = (string)$rdata->result_code;
        $return_code = trim(strtoupper($return_code));
        $result_code = trim(strtoupper($result_code));

        if ($return_code == 'SUCCESS' && $result_code == 'SUCCESS') {
            return array('status'=>'succen','trade_no'=> $arr['partner_trade_no'],'count_cash'=>$count_cash,'free'=>$free);
        } else {
            $returnmsg = (string)$rdata->return_msg;
            if($returnmsg=='NO_AUTH'){
                $returnmsg = '产品权限验证失败,请查看您当前是否具有该产品的权限(企业付款到零钱)';
            }
            return array('status'=>'error','msg'=>$returnmsg);

        }

    }

    function curl_post_ssl($url, $vars, $second = 30, $aHeader = array())
    {

        $ch = curl_init();//初始化curl

        curl_setopt($ch, CURLOPT_TIMEOUT, $second);//设置执行最长秒数
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_URL, $url);//抓取指定网页
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// 终止从服务端进行验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);//
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');//证书类型
        curl_setopt($ch, CURLOPT_SSLCERT, getcwd() . '/vendor/cert/apiclient_cert.pem');//证书位置
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');//CURLOPT_SSLKEY中规定的私钥的加密类型
        curl_setopt($ch, CURLOPT_SSLKEY,  getcwd() . '/vendor/cert/apiclient_key.pem');//证书位置
      //  curl_setopt($ch, CURLOPT_CAINFO, 'PEM');
       // curl_setopt($ch, CURLOPT_CAINFO, $isdir . 'rootca.pem');
        if (count($aHeader) >= 1) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);//设置头部
        }
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);//全部数据使用HTTP协议中的"POST"操作来发送

        $data = curl_exec($ch);//执行回话

        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
    }

    //遍历数组方法
    public function arrayToXml($data){
        $str='<xml>';
        foreach($data as $k=>$v) {
            $str.='<'.$k.'>'.$v.'</'.$k.'>';
        }
        $str.='</xml>';
        return $str;
    }

    public function genRandomString($len = 30){
        $chars = array(
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
            "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
            "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
            "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
            "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
            "3", "4", "5", "6", "7", "8", "9"
        );
        $charsLen = count($chars) - 1;
        // 将数组打乱
        shuffle($chars);
        $output = "";
        for ($i = 0; $i < $len; $i++) {
            $output .= $chars[mt_rand(0, $charsLen)];
        }
        return $output;
    }

    /**
     * 生成签名
     *  @return 签名
     */
    public function MakeSign( $params ){
        //签名步骤一：按字典序排序数组参数
        ksort($params);
        $string = $this->ToUrlParams($params);
        //签名步骤二：在string后加入KEY
        $string = $string . "&key="."15998c70d2d3ee19be34d53e0df87d9c";
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }


    /**
     * 签到列表
     * @date 2017/09/28
     */
    public function signList()
    {
        $this->get_sign_list();
        $this->assign('withdraw_status', C('WITHDRAW_STATUS'));
        return $this->fetch();
    }

    public function get_sign_list()
    {
    }


    /**
     * 会员签到 ajax
     * @date 2017/09/28
     */
    public function ajaxsignList()
    {
        // 搜索条件
        $condition = array();
        $mobile = I('mobile');
        $mobile && $where['w.mobile'] = array('like', '%' . $mobile . '%');
        $count = Db::name('user_sign')->alias('w')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->count();
        $Page = new Page($count, 20);
        $list = Db::name('user_sign')->alias('w')->field('w.*,u.nickname,u.mobile')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        //$this->assign('create_time',$create_time2);
        $show = $Page->show();
        $this->assign('show', $show);
        $this->assign('list', $list);
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    /**
     * 签到规则设置
     * @date 2017/09/28
     */
    public function signRule()
    {
        $config = tpCache('sign');
        $this->assign('config',$config);//当前配置项
        return $this->fetch();
    }

    /**
     * 会员标签列表
     */
    public function labels()
    {
        $p = input('p/d');
        $Label = new UserLabel();
        $label_list = $Label->order('label_order')->page($p, 10)->select();
        $this->assign('label_list', $label_list);
        $Page = new Page($Label->count(), 10);
        $this->assign('page', $Page);
        return $this->fetch();
    }

    /**
     * 添加、编辑页面
     */
    public function labelEdit()
    {
        $label_id = input('id/d');
        if ($label_id) {
            $Label = new UserLabel();
            $label = $Label->where('id', $label_id)->find();
            $this->assign('label', $label);
        }
        return $this->fetch();
    }

    /**
     * 会员标签添加编辑删除
     */
    public function label()
    {
        $label_info = input();
        $return = ['status' => 0, 'msg' => '参数错误', 'result' => ''];//初始化返回信息
        $userLabelValidate = Loader::validate('UserLabel');
        $UserLabel = new UserLabel();
        if (request()->isPost()) {
            if ($label_info['label_id']) {
                if (!$userLabelValidate->scene('edit')->batch()->check($label_info)) {
                    $return = ['status' => 0, 'msg' => '编辑失败', 'result' => $userLabelValidate->getError()];
                }else {
                    $UserLabel->where('id', $label_info['label_id'])->save($label_info);
                    $return = ['status' => 1, 'msg' => '编辑成功', 'result' => ''];
                }
            }else{
                if (!$userLabelValidate->batch()->check($label_info)) {
                    $return = ['status' => 0, 'msg' => '添加失败', 'result' => $userLabelValidate->getError()];
                } else {
                    $UserLabel->insert($label_info);
                    $return = ['status' => 1, 'msg' => '添加成功', 'result' => ''];
                }
            }
        }
        if (request()->isDelete()) {
            $UserLabel->where('id', $label_info['label_id'])->delete();
            $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
        }
        $this->ajaxReturn($return);
    }

    /**
     * 退款单列表
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function return_deposit(){
        $Ad = M('return_deposit');
        $p = $this->request->param('p');
        $res = Db::name('return_deposit')->alias('a')->field('a.*,u.nickname')
            ->join('__USERS__ u', 'u.user_id = a.user_id', 'INNER')
            ->order("a.id")->page($p . ',10')->select();
        if ($res) {
            foreach ($res as $val) {
                $list[] = $val;
            }
        }
        $this->assign('list', $list);
        $count = $Ad->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $this->assign('page', $show);
        return $this->fetch();
    }


    public function apply_deposit(){
        $apply_id = I('get.id');
        $return_deposit = Db::name('return_deposit')->where(['id'=>$apply_id])->find();
        $refund_money = $return_deposit["refund_money"];
        M('return_deposit')->where(array('id' => $apply_id))->save(['status'=>1,"checktime"=>time()]);
        Db::name('users')->where("user_id", $return_deposit['user_id'])->setDec('deposit', $refund_money);
        $this->success('审核完成！！');
    }


    /**
     * [withdrawals 提现列表]
     * @user 玩味
     * @time 09-12
     * @return [type] [description]
     */
    public function withdrawals()
    {
        $this->get_withdrawals_list();
        
        $this->assign('withdraw_status', C('WITHDRAW_STATUS'));
        return $this->fetch();
    }


    /**
     * [sublimt 审核提现]
     * @user 玩味
     * @time 09-12
     * @return [type] [description]
     */
    public function submit() 
    {
        
        $post = I('post.');
        $id = $post['id'];
        
        if(!is_array($id)){
            $id = [0=>$id];
        }else{
            $type = $post['type'];
            
            // if(!isset($type)) $this->ajaxReturn(['status' => 0, 'msg' => '批量打款需要筛选出打款类型', 'result' => '']);;
        }

        $log = M('withdrawals')->where('id','in',$id)->select();

        foreach ($log as $key => $value) {
            //判断冻结金额是否充足

            $dj_money = M("users")->where(['user_id'=>$value['user_id']])->getField("frozen_money");

            if($dj_money<$value['money']){
                $this->ajaxReturn(['status' => 0, 'msg' => '提现金额有误！！' ]);
            }

            $object = $this->wxtixian($value['id']);
            if ($object['status'] == 'succen') {//提现成功
                M("users")->where(['user_id'=>$value['user_id']])->setDec('frozen_money', $value['money']);
                $data = array('status' => 1, 'remark' => '提现成功', 'trade_no' => $object['status'],'count_cash'=>$object['count_cash'].'%','taxfee'=>$object['free']);
                M('withdrawals')->where(array('id' => $value['id']))->save($data);
                $this->ajaxReturn(array('status' => 0, 'msg' => "打款成功"), 'JSON');

            } else {
                $this->ajaxReturn(['status' => 0, 'msg' =>$object['msg'] ]);
                $this->error($object['msg']);
            }
        }






        // 启动事务
       /* Db::startTrans();

        try{

            foreach ($log as $key => $value) {
                
                if (empty($value)) 
                {
                    throw new \Exception('未找到提现记录~');
                }

                $withdrawals_submit = Cache::get('withdrawals_submit'.$value['pay_code']);
               
                if($withdrawals_submit)
                {
                    throw new \Exception('请求频繁~');
                }

                Cache::set('withdrawals_submit'.$value['pay_code'],time(),2);

                if(!empty($value['status']))
                {
                    throw new \Exception('已打款，无需重复打款~');
                }

                $Finance_object = new FinanceLogic();

                $result = $Finance_object->wechat($value);

                if($result['status'] == 0)
                {
                    
                    throw new \Exception($result['msg']);
                }

                M('withdrawals')->where('id',$value['id'])->update(['status'=>1]);
            }

            // 提交事务
            Db::commit();

            $this->ajaxReturn(['status' => 1, 'msg' => "打款成功~"]);    

        }catch (\Exception $e){

            Db::rollback();

            $mes = $e->getMessage();

            $this->ajaxReturn(['status' => 0, 'msg' => $mes ]);  
        }*/
        
    }
    /**
     * 将参数拼接为url: key=value&key=value
     * @param   $params
     * @return  string
     */
    public function ToUrlParams( $params ){
        $string = '';
        if( !empty($params) ){
            $array = array();
            foreach( $params as $key => $value ){
                $array[] = $key.'='.$value;
            }
            $string = implode("&",$array);
        }
        return $string;
    }

}