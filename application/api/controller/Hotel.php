<?php
/**
 --------------------------------------------------
 空间类型   附近酒店列表
 --------------------------------------------------
 Copyright(c) 2017 时代万网 www.agewnet.com
 --------------------------------------------------
 开发人员: 玩味  <729167563@qq.com>
 --------------------------------------------------

 */
namespace app\api\controller;

use Think\Controller;
use think\Page;
use think\Request;
use think\Db;
class Hotel extends BaseApplet{


    /**
     * [lists 附近酒店列表]
     * @param  [type]  $lng      [经度]
     * @param  [type]  $lat      [维度]
     * @param  [type]  $keywords [搜索条件]
     * @return [type]            [description]
     */
    public function lists()
    {

        $lat = I('lat',0); // 经度 
        // $lat = 22.72174;
        $lng = I('lng',0) ; // 维度
        // $lng = 114.06031;

        if(!$lat || !$lng) return returnBad("请打开手机的GPS定位",302);

        $keywords = I('keywords','','trim') ; // 搜索条件
        if($keywords) 
        {
            $str = " and ( name like '{$keywords}%' or address like '{$keywords}%' )";
        }

        // 不走索引
        /*$sql = "explain SELECT *,ROUND(6378.138*2*ASIN(SQRT(POW(SIN(($lat*PI()/180-lat*PI()/180)/2),2)+COS($lat*PI()/180)*COS(lat*PI()/180)*POW(SIN(($lng*PI()/180-lng*PI()/180)/2),2)))*1000) AS location FROM sd_lc_hotel  having  location <= 2000" .$str;
        $lists = Db::query($sql);

        $count=count($lists);          
        $Page = new Page($count, 10);
        
        $start=$Page->firstRow*$Page->listRows;  
     
        $list=array_slice($lists,$start,$Page->listRows);    */


         //使用此函数计算得到结果后，带入sql查询。
        $squares = $this->returnSquarePoint($lng, $lat);
        
        $sqsl = "SELECT count(id) as total FROM `sd_lc_hotel` where lat<>0 and lat>{$squares['right-bottom']['lat']} and lat<{$squares['left-top']['lat']} and lng>{$squares['left-top']['lng']} and lng<{$squares['right-bottom']['lng']} ".$str;
        $count = Db::query($sqsl);
        
        $Page = new Page($count[0]['total'], 15);
        
        $info_sql = "SELECT id,name,address,lng,lat,thumb FROM `sd_lc_hotel` where lat<>0 and lat>{$squares['right-bottom']['lat']} and lat<{$squares['left-top']['lat']} and lng>{$squares['left-top']['lng']} and lng<{$squares['right-bottom']['lng']} ".$str.' order by id desc limit '.$Page->firstRow . ',' . $Page->listRows;
        

        $list = Db::query($info_sql);
        foreach ($list as $key => $value) {
            $list[$key]['latitude'] = $value['lat'];
            $list[$key]['longitude'] = $value['lng'];
            $list[$key]['thumb'] = 'https://'.$_SERVER['HTTP_HOST'].$value['thumb'];
            
            $list[$key]['location'] = $this->getDistance($lng, $lat, $value['lng'], $value['lat']);
        }

        return returnOk(['list'=>$list, 'count'=>$count[0]['total'],'psize'=>15]);

    }

    /**
     * [计算四个点的距离]
     * @param  [type]  $lng      [维度]
     * @param  [type]  $lat      [经度]
     * @param  integer $distance [距离2公里内]
     * @return [type]            [description]
     */
    public function returnSquarePoint($lng, $lat,$distance = 2)
    {

        $dlng =  2 * asin(sin($distance / (2 * 6371)) / cos(deg2rad($lat)));
        $dlng = rad2deg($dlng);
         
        $dlat = $distance/6371;
        $dlat = rad2deg($dlat);
         
        return [
            'left-top'      => ['lat'=> $lat + $dlat, 'lng'=> $lng - $dlng],
            'right-top'     => ['lat'=> $lat + $dlat, 'lng'=> $lng + $dlng],
            'left-bottom'   => ['lat'=> $lat - $dlat, 'lng'=> $lng - $dlng],
            'right-bottom'  => ['lat'=> $lat - $dlat, 'lng'=> $lng + $dlng]
        ];
     }
    

    /** 
     * [根据两点间的经纬度计算距离]
     * @param $lng1
     * @param $lat1
     * @param $lng2
     * @param $lat2
     * @return int
     */
    public static function getDistance($lng1, $lat1, $lng2, $lat2)
    {
        //将角度转为狐度
        $radLat1 = deg2rad($lat1);//deg2rad()函数将角度转换为弧度
        $radLat2 = deg2rad($lat2);
        $radLng1 = deg2rad($lng1);
        $radLng2 = deg2rad($lng2);
        $a = $radLat1 - $radLat2;
        $b = $radLng1 - $radLng2;
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6378.137 * 1000;
        return (int)$s/1000;
    }

}