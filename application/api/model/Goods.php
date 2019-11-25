<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/19
 * Time: 9:29
 */
namespace app\api\model;

class Goods extends BaseModel
{
    public function goodsList($page,$limit,$where)
    {
        $where['is_on_sale'] = 1;
        $list = $this->where($where)->field("goods_id,goods_name,shop_price,market_price,original_img,sales_sum,store_count,free")->page($page,$limit)->select();
        foreach ($list as $value){
            $value['original_img'] = url_add_domain($value['original_img']);
        }
        $count = $this->where($where)->count('goods_id');
        $goods_list['list'] = [];
        if(!collection($list)->isEmpty()){
            $goods_list['list'] = collection($list)->toArray();
        }
        $goods_list['count'] = ($count-$page*$limit);
        return $goods_list;
    }

    public function goodsLists($page,$limit,$where,$wheres)
    {
        $where['is_on_sale'] = 1;
        $list = $this->where($where)->whereor($wheres)->field("goods_id,goods_name,shop_price,market_price,original_img,sales_sum,store_count,free")->group('goods_id')->page($page,$limit)->select();
        foreach ($list as $value){
            $value['original_img'] = url_add_domain($value['original_img']);
        }
        $count = $this->where($where)->whereor($wheres)->count('goods_id');
        $goods_list['list'] = [];
        if(!collection($list)->isEmpty()){
            $goods_list['list'] = collection($list)->toArray();
        }

        $goods_list['count'] = ($count-$page*$limit);
        return $goods_list;
    }
}