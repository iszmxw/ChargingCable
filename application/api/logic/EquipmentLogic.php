<?php
/**
--------------------------------------------------
空间类型   商品模型
--------------------------------------------------
Copyright(c) 2017 时代万网 www.agewnet.com
--------------------------------------------------
开发人员: lichao  <729167563@qq.com>
--------------------------------------------------

 */
namespace app\api\logic;

use app\common\model\Equipment ;
use app\common\model\EquipmentNumber ;
use app\common\model\Users ;
use think\Log;
use think\Db;

class EquipmentLogic
{

    /**
     * 设备编号是否已使用
     * @param $e_no
     * @return bool
     */
    public function getEquipmentNo($e_no)
    {
        $EquipmentNumber = new EquipmentNumber();
        $res = $EquipmentNumber->get(['number'=>$e_no,'status'=>0]);
        return $res ? true : false;
    }

    /**
     * 判断支付密码是否正确
     * @param $paypwd
     * @return bool
     */
    public function isPaypwd($userid, $paypwd)
    {
        $users = new Users();
        $res = $users->get($userid);

        if (password_shal($paypwd) != $res->paypwd) {
            return TRUE;
        }else{
            return FALSE;
        }
    }

    /**
     * 设备激活搬定
     * @param $e_id
     * @param $userid
     * @param $data
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function addEquipment($e_id,$userid,$data)
    {
        $users = new Users();
        $Equipment = new Equipment();
        $EquipmentNumber = new EquipmentNumber();

        $add = [
            'hotel_id'      => $data['hotel_id'],
            'e_no'           => $data['e_no'],
            'e_hotel'       => $data['e_hotel'],
            'hotel_name'    => $data['hotel_name'],
            'investor'      => $data['investor'],
            'mobile'        => $data['mobile'],
            'edit_time'    => time(),
            'e_status'      => 1,
        ];

        if (!$e_id){
            $user = $Equipment->get(['user_id'=>$userid,'e_status'=>0]);
        }

        $add['id'] = $e_id ? $e_id : $user->id;

        $res = $Equipment->update($add);
        if ($res){
            //更新设备编号表状态
            $EquipmentNumber->where(['number'=>$res['e_no']])->update(['status'=>1]);
            //激活成功  增加正常设备+1   减少未绑定设备-1
            $user = $users->get($userid);
            $e_num = $user->e_unbound_num - 1;
            $e_use_num = $user->e_use_num + 1;
            $infos = [
                'user_id'         =>  $userid,
                'e_use_num'       =>  $e_use_num,
                'e_unbound_num'   =>  $e_num,
            ];
            $users->update($infos);
        }
        $ress = $res ? $res->toArray():[];

        return $ress;
    }

   

}