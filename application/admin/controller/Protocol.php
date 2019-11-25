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

class Protocol extends Base
{

    /**
     * [clothes 干衣机使用协议]
     * @return [type] [description]
     */
    public function clothes(){

        $SiteLogic = new SiteLogic();

        if(IS_POST){

            $title = I('post.title');
            $content = I('post.content');
            $arr = ['title'=>$title, 'content'=>$content];
            $SiteLogic->setShop('protocol',$arr);
            
            $this->ajaxReturn(['status' => 1, 'msg' => "设置成功~"]);    
        }

        $data = $SiteLogic->getShop('protocol');


        $this->assign('info',$data);
        return $this->fetch();
    }


    /**
     * [withdraw 干衣机使用协议]
     * @return [type] [description]
     */
    public function withdraw(){

        $SiteLogic = new SiteLogic();

        if(IS_POST){

            $title = I('post.title');
            $content = I('post.content');
            $arr = ['title'=>$title, 'content'=>$content];
            $SiteLogic->setShop('withdraw',$arr);
            
            $this->ajaxReturn(['status' => 1, 'msg' => "设置成功~"]);    
        }

        $data = $SiteLogic->getShop('withdraw');


        $this->assign('info',$data);
        return $this->fetch();
    }
    
}