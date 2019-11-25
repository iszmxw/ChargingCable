<?php
/**
 --------------------------------------------------
 空间类型   设备扫码跳转
 --------------------------------------------------
 Copyright(c) 2017 时代万网 www.agewnet.com
 --------------------------------------------------
 开发人员: 玩味  <729167563@qq.com>
 --------------------------------------------------

 */
namespace app\api\controller;


use app\admin\logic\SiteLogic;

use Think\Controller;
use think\Page;
use think\Request;
use think\Db;
class Package extends BaseApplet{


    /**
     * [getList 获取设备套餐列表]
     * @return [type] [description]
     */
    public function getList()
    {

        $db = M('package_price');

        $e_no = I('e_no','','trim');

        $equipment = db::name('equipment')
                        ->alias('e')
                        ->join('lc_equipment_number n','n.number = e.e_no','LEFT')
                        ->where('e.e_no',$e_no)
                        ->field('e.id,e_no,n.pack_id')
                        ->find();

        if(empty($equipment))
        {
        	exit(json_encode(['code'=>302, 'msg'=>'没有找到设备信息']));
        } 

        if( $equipment['pack_id'] ) 
        {

            $package_data = $db->where('tid',$equipment['pack_id'])->select();
        } else {

            $pid = db::name('package')->where('default',1)->value('pid');
            $package_data = $db->where('tid',$pid)->select();
        }
        foreach ($package_data as $key => $value) {
        	$package_data[$key]['icon'] = 'https://'.$_SERVER['HTTP_HOST'].$value['icon'];
        }

        $siteObject = new SiteLogic();

        $protocol = $siteObject->getShop('protocol');

        exit(json_encode(['code'=>200,'equipment'=>$equipment, 'package_data'=>$package_data, 'protocol'=>$protocol]));

    }

    
    public function order_list() 
    {

        $where_arr = [
            'user_id' => $this->user_id,
            'status'=> 1
        ];

        $count = db::name('package_order')->where($where_arr)->count();

        $Page = new Page($count, 10);

        $order_list = db::name('package_order')->where($where_arr)->limit($Page->firstRow . ',' . $Page->listRows)->order("createtime DESC")->select();

        $current_time = time();

        foreach ($order_list as $key => $val) {
            $time = db::name('package_price')->where('id',$val['pack_id'])->value('time');
            $e_no = db::name('equipment')->where('id',$val['equipment_id'])->value('e_no');
            
            if($current_time > ($val['createtime'] + $time * 60)) 
            {
                $order_list[$key]['status_title'] = '已完成';
            }else{
                $order_list[$key]['status_title'] = '使用中';
            }   
            $order_list[$key]['e_no'] = $e_no;
            $order_list[$key]['time'] = $time;
            $order_list[$key]['createtime'] = date('Y-m-d H:i:s',$val['createtime']);
        }

        $order_info['list'] = $order_list;
        $order_info['count'] = $count;
        $order_info['code'] = 200;
        exit(json_encode($order_info));
    }

    public function hotel_list() 
    {
        $hotel_id = I('hotel_id/d',0);
        $where_arr = [
            'user_id' => $this->user_id,
            'status'=> 1
        ];
        
        $count = db::name('equipment')->where('hotel_id',$hotel_id)->count();
        
        $order_list = db::name('equipment')->where('hotel_id',$hotel_id)->field('id')->select();

        $current_time = time();
        $sum = 0;
        foreach ($order_list as $key => $val) {
            $package = db::name('package_order')->where('equipment_id',$val['id'])->field('pack_id,createtime')->select();
            
            foreach ($package as $ke => $value) {
                $time = db::name('package_price')->where('id',$value['pack_id'])->value('time');
               
                if( $current_time < ($time * 60 +$value['createtime']))
                {
                    $sum +=1;
                }
            }
        }
   
        $lng = I('lng',0);
        $lat = I('lat',0);

        $host = db::name('lc_hotel')->where('id',$hotel_id)->field('id,name,address,lng,lat,hours_time,mobile,thumb')->find();
        if(empty($host)){
            exit(json_encode(['code'=>301,'msg'=>'酒店不存在']));
        }
        $host['thumb'] = 'https://'.$_SERVER['HTTP_HOST'].$host['thumb'];
        //将角度转为狐度
        $radLat1 = deg2rad($lat);//deg2rad()函数将角度转换为弧度
        $radLat2 = deg2rad($host['lat']);
        $radLng1 = deg2rad($lng);
        $radLng2 = deg2rad($host['lng']);
        $a = $radLat1 - $radLat2;
        $b = $radLng1 - $radLng2;
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6378.137 * 1000;
        $sum = $count-$sum;
        if($sum < 0){
            $sum = 0;
        }
       
        $order_info['sum'] = $sum ;
        $order_info['count'] = $count;
        $order_info['distance'] = (int)$s/1000;
        $order_info['code'] = 200;
        $order_info['host'] =$host;
        
        exit(json_encode($order_info));
    }

}