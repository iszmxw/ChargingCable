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

use app\api\model\Goods;
use think\Db;

class ShopLogic {

    protected static $carousel = null;

    protected static $hot_goods = null;

    public function __construct()
    {
        //首页轮播图片
        if(self::$carousel == null){
            self::$carousel = M("ad")->order("orderby asc")->where('pid = 539')->field('ad_link,ad_code')->select();
            if(!empty(self::$carousel)){
                foreach (self::$carousel as $k => $v){
                    self::$carousel[$k]['ad_code'] = url_add_domain($v['ad_code']);
                }
            }else{
                self::$carousel = [];
            }
        }
        if(self::$hot_goods == null){
            self::$hot_goods =  Db::name('ad')->field('ad_id,ad_name,ad_link,ad_code')->where(['pid'=>538])->order("orderby asc")->limit(3)->select();
            if(!empty(self::$hot_goods)){
                foreach (self::$hot_goods as $k => $v){
                    self::$hot_goods[$k]['ad_code'] = url_add_domain($v['ad_code']);
                }
            }else{
                self::$hot_goods = [];
            }
        }
    }

    /*商城首页*/
    public function getIndex($post)
    {
        $where = [];
        if( isset($post['stair_id']) ) $where['id'] = (int)$post['stair_id'];
        $category = M("goods_category")->where($where)->where(['is_show'=>1,'level'=>1])->field('id,name')->order("sort_order asc")->select();
        //获取当前分类下的所有二级分类
        $sub_menu = [];
        foreach ($category as $key=>&$value){
            if($key==0){
                $sub_menu = M("goods_category")->where(['parent_id'=>$value['id'],'is_hot'=>1,'is_show'=>1])->field('id,name,image')->order("sort_order asc")->limit(0,7)->select();
                foreach ($sub_menu as &$v){
                    $v['image'] = url_add_domain($v['image']);
                }
            }
        }
        //获取当前二级分类下的所有商品
        if(isset($sub_menu[0]['id'])){
            $cat_id = $sub_menu[0]['id'];
            $cat_id && ($where['cat_id'] = $cat_id);
            if( isset($post['second_id']) ) $where['cat_id'] = (int)$post['second_id'];
            $goodsModel = new Goods();
            $page = isset($post['page']) ? $post['page'] : 1;
            $limit = isset($post['limit']) ? $post['limit'] : config('limit') ;
            if(($cat_id==2 && !$post['stair_id'] && !$post['stair_id']) || ($post['second_id']==2 && $post['stair_id']==1)){
                $wheres['is_recommend'] = 1;
                unset($where['id']);
                $goods_list = $goodsModel->goodsLists($page,$limit,$where,$wheres);


            }else{
                $where['is_recommend'] = 1;
                unset($where['id']);
                $goods_list = $goodsModel->goodsList($page,$limit,$where);
            }

        }else{
            $goods_list = [];
        }
        $one = [];
        $two = [];
        $where_s = ['is_recommend'=>1,'is_on_sale'=>1];
        if(empty($post['stair_id'])){
            //第一个顶级分类下的第二个下级分类
            if(isset($sub_menu[1]['id'])){
                $one['name']=$sub_menu[1]['name'];
                $where_s['cat_id'] = $sub_menu[1]['id'];
                $one_list = M('goods')->where($where_s)->field("goods_id,goods_name,shop_price,market_price,original_img,sales_sum,store_count")->select();
                foreach ($one_list as &$v){
                    $v['original_img'] = url_add_domain($v['original_img']);
                }
                $one['list'] = $one_list;
            }
            //第二个顶级分类下的第二个下级分类
            if(isset($sub_menu[2]['id'])){
                $two['name']=$sub_menu[2]['name'];
                $where_s['cat_id'] = $sub_menu[2]['id'];
                $two_list = M('goods')->where($where_s)->field("goods_id,goods_name,shop_price,market_price,original_img,sales_sum,store_count")->select();
                foreach ($two_list as &$v){
                    $v['original_img'] = url_add_domain($v['original_img']);
                }
                $two['list'] = $two_list;
            }
            $data = [
                'carousel'=>self::$carousel,
                'stair'=>$category,
                'second'=>$sub_menu,
                'one'=>$one,
                'two'=>$two,
                'goods_list' => $goods_list
            ];
        }else{
            $data = [
                'second'=>$sub_menu,
                'goods_list' => $goods_list
            ];
        }
        return $data;
    }
}