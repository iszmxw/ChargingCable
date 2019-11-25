<?php

/**
 * 基础设置缓存
 * ============================================================================
 * 版权所有 2015-2027 深圳时代万网网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.agewnet.net/
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: 玩味
 * Date: 2019-08-24
 */

namespace app\admin\logic;

use think\Model;
use think\Cache;
use think\Db;

class SiteLogic extends Model
{


    /**
     * [getSet 获取后台基础设置]
     * @param  [type] $key [设置的键名]
     * @return [type]      [array]
     */
    public function getShop($key)
    {
        $data = Cache::get('site');

        if(empty($data)){
            $data = M('site')->value('data');
            $data = unserialize($data);

            $this->setShop($key,$data[$key]);
        }else{

            $data = unserialize($data);
        }
        
        return $data[$key];
    }



    /**
     * [setShop 基础设置]
     * @param [type] $key [要设置的键名]
     * @param array  $val [要设置的数据]
     */
    public function setShop($key,$val = array())
    {
        $list = M('site')->find();

        if($list)
        {
            $data = unserialize($list['data']);

        } else {
            $data = [];
        }
        $data[$key] = $val;

        $str = serialize($data);
        if($list)
        {
            M('site')->where('id',$list['id'])->update(['data'=>$str]);
        }else{
            M('site')->insert(['data'=>$str]);
        }

        Cache::set('site',$str);
        return ;
    }
}