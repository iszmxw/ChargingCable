<?php
/**
 * ScanCommission
 * ============================================================================
 * 使用干衣机返润
 * ============================================================================
 * Author: 玩味
 * Date: 2019-8-22
 */
namespace app\common\model;

use think\Db;
use think\Model;
use think\Cache;

class ScanCommission extends Model
{	
	// 分润集合
	public static $share_log;
	// 销毁集合
	public static $destroy_log;

    //自定义初始化
    protected static function init()
    {
        self::$share_log = [];
		self::$destroy_log = [];
    }


    /**
     * [setScanCommission 使用设备，佣金设置]
     * @param [type] $id [支付订单ID]
     */
    public function setScanCommission( $id = 0 )
    {

        // 扫码支付订单
        $list = $list = Db::name('package_order')->where(['id' => $id, 'status'=>1])->find();
        
        $scantime = Cache::get('scantime'.$list['id']);
       
        if($scantime){
            $re_post = ['orderid'=> $list['id'], 'msg'=> '连续请求被拒绝'];
            $this->write_log( $re_post, 'commission_log.txt' ) ;return;
        }
        Cache::set('scantime'.$list['id'],time(),3);

        // 设备信息
        $equipment = Db::name('equipment')->where('id',$list['equipment_id'])->field('user_id,hotel_id,e_no')->find();
       
        $list['hotel_id'] = $equipment['hotel_id'];
        $list['e_no'] = $equipment['e_no'];

        if(empty($equipment) || empty($list))
        {
            $re_post = ['orderid'=> $list['id'], 'msg'=> '未找到支付信息'];
            $this->write_log( $re_post, 'commission_log.txt' ) ;return;
        }

        
        $father_list = Db::name('user_group')->where('user_id',$equipment['user_id'])->value('father_id');
        $father_list = explode(',', $father_list);
        // 所有父级会员
        $father_list = array_reverse($father_list);

        $map['user_id']  = ['in',$father_list];
        // 所有上级会员
        $agent_list = db::name('users')->where($map)->field('user_id,level')->select();

        //  市场人员集合
        $market_list = ['partner_user' => 0, 'direct_user' => 0, 'indirect_user' => 0];

        //  直推人员
        $market_list['direct_user'] = $agent_list[0]['user_id'];

        // 间推人员
        $market_list['indirect_user'] = $agent_list[1]['user_id'];

        // 合伙人员
        foreach ($agent_list as $key => $value)
        {
            if( $value['level_id'] == 3 || $value['level_id'] == 10)
            {
                $market_list['partner_user'] = $value['user_id'];
                break;
            }
        }
        
        /*
         * Investor:        使用干衣机，投资人可获得分润百分比
         * hotel:           使用干衣机，酒店负责人可获得分润百分比
         * technology:      使用干衣机，技术人可获得分润百分比
         * operate:         使用干衣机，运营人可获得分润百分比
         *
         * market:          使用干衣机，市场人员可获得分润百分比总和
         * market_partner:  使用干衣机，市场分润中合伙人可获得分润百分比
         * market_direct:   使用干衣机，市场分润中直接分享者可获得分润百分比
         * market_indirect: 使用干衣机，市场分润中间接分享者可获得分润百分比
         *
         * //partner_personnel:     合伙人user_id
         * technology_personnel:    技术人员user_id
         * operate_personnel:       运营人员user_id
        */
        $subcommission = M("lc_subcommission")->where(['id'=>1])->find();

        // 启动事务
        Db::startTrans();

        try{

            // 投资人员
            $invest_user = $equipment['user_id'];
            $this->invest_reward($invest_user, $list, $subcommission['Investor']);

            // 酒店负责人员
            $hotel_user = db::name('lc_hotel')->where('id',$equipment['hotel_id'])->value('user_id');
            $this->hotel_reward($hotel_user, $list, $subcommission['hotel']);

            // 技术人员
            $technology_user = explode(',',$subcommission['technology_personnel']); 
            $this->technology_reward($technology_user, $list, $subcommission['technology']);

            // 运营人员
            $operate_user = explode(',',$subcommission['operate_personnel']);
            $this->operator_reward($operate_user, $list, $subcommission['operate']);

            // 市场合伙人直推间推返润集合
            $rate = ['market'=>$subcommission['market'], 'market_partner'=>$subcommission['market_partner'], 'market_direct'=>$subcommission['market_direct'], 'market_indirect'=>$subcommission['market_indirect']];
            // 计算市场佣金
            $this->bazaar_reward($market_list, $list, $rate);
            // 记录总的
            $this->destroy_commission($list);

            // 提交事务
            Db::commit();

            return true;    

        }catch (\Exception $e){

            Db::rollback();

            $mes = $e->getMessage();

            $re_post = ['orderid'=> $list['id'], 'msg'=> $mes];
            $this->write_log( $re_post, 'sql_log.txt' ) ;return;
        }
        
    }



    /**
     * [invest_reward 计算投资人员返润]
     * @param  [type] $invest_user [投资人员]
     * @param  [type] $list        [支付订单]
     * @param  [type] $rate        [分润利率]
     * @return [type]              [description]
     */
    public function invest_reward($invest_user, $list, $rate)
    {

        $price = bcmul($list['price'], $rate / 100,2);

        $destroy = ['orderid'=> $list['id'], 'e_no'=>$list['e_no'], 'type'=>1, 'price'=>$price];

        if(empty($invest_user))
        {   
            $destroy['remark'] = '投资人员没有返润会员';
            $this->add_destroy( $destroy ) ;return;
        }

        if(empty($rate)) 
        {
            $destroy['remark'] = '没有设置投资人返润比例';
            $this->add_destroy( $destroy ) ;return;
        }

        if($price < 0.01) 
        {
            $destroy['remark'] = '投资人员返润金额小0.01';
            $this->add_destroy( $destroy ) ;return;
        }
        
        $data = ['user_id'=>$invest_user, 'price'=>$price, 'remark'=>'投资人员返润'];

        $this->add_commission($data, $list, 1);
        
    }



    /**
     * [hotel_reward 计算酒店负责人返润]
     * @param  [type] $hotel [酒店负责人员]
     * @param  [type] $list        [支付订单]
     * @param  [type] $rate        [分润利率]
     * @return [type]              [description]
     */
    public function hotel_reward($hotel_user, $list, $rate)
    {

        $price = bcmul($list['price'], $rate / 100,2);

        $destroy = ['orderid'=> $list['id'], 'e_no'=>$list['e_no'], 'type'=>2, 'price'=>$price];

        if(empty($hotel_user))
        {   
            $destroy['remark'] = '酒店负责人没有返润会员';
            $this->add_destroy( $destroy ) ;return;
        }

        if(empty($rate)) 
        {
            $destroy['remark'] = '没有设置酒店负责人返润比例';
            $this->add_destroy( $destroy ) ;return;
        }

        if($price < 0.01) 
        {
            $destroy['remark'] = '酒店负责人返润金额小0.01';
            $this->add_destroy( $destroy ) ;return;
        }
        
        $data = ['user_id'=>$hotel_user, 'price'=>$price, 'remark'=>'酒店负责人返润'];

        $this->add_commission($data, $list, 2);
        
    }



    /**
     * [technology_reward 计算技术人员返润]
     * @param  [type] $technology_user [技术人员]
     * @param  [type] $list            [支付订单]
     * @param  [type] $rate            [分润利率]
     * @return [type]                  [description]
     */
    public function technology_reward($technology_user = array(), $list, $rate)
    {

        $price = bcmul($list['price'], $rate / 100,2);

        $destroy = ['orderid'=> $list['id'], 'e_no'=>$list['e_no'], 'type'=>3, 'price'=>$price];

        if(empty($technology_user))
        {   
            $destroy['remark'] = '技术人员没有返润会员';
            $this->add_destroy( $destroy ) ;return;
        }

        if(empty($rate)) 
        {
            $destroy['remark'] = '没有设置技术人返润比例';
            $this->add_destroy( $destroy ) ;return;
        }

        if($price < 0.01) 
        {
            $destroy['remark'] = '技术人员返润金额小0.01';
            $this->add_destroy( $destroy ) ;return;
        }

        foreach ($technology_user as $key => $value) {
            $data = ['user_id'=>$value, 'price'=>$price, 'remark'=>'技术人员返润'];
            $this->add_commission($data, $list, 3);
        }
    }



    /**
     * [operator_reward 计算运营人员返润]
     * @param  [type] $operate_user [运营人员]
     * @param  [type] $list         [支付订单]
     * @param  [type] $rate         [分润利率]
     * @return [type]               [description]
     */
    public function operator_reward($operate_user, $list, $rate)
    {

        $price = bcmul($list['price'], $rate / 100,2);

        $destroy = ['orderid'=> $list['id'], 'e_no'=>$list['e_no'], 'type'=>4, 'price'=>$price];

        if(empty($operate_user))
        {   
            $destroy['remark'] = '运营人员没有返润会员';
            $this->add_destroy( $destroy ) ;return;
        }

        if(empty($rate)) 
        {
            $destroy['remark'] = '没有设置运营人员返润比例';
            $this->add_destroy( $destroy ) ;return;
        }

        if($price < 0.01) 
        {
            $destroy['remark'] = '运营人员返润金额小0.01';
            $this->add_destroy( $destroy ) ;return;
        }

        foreach ($operate_user as $key => $value) {
            $data = ['user_id'=>$value, 'price'=>$price, 'remark'=>'运营人员返润'];
            $this->add_commission($data, $list, 4);
        }

    }



    /**
     * [bazaar_reward 计算市场人员返润]
     * @param  [type] $market_list [合伙人、直推、间推，人员集合]
     * @param  [type] $list        [支付订单]
     * @param  [type] $rate        [分润利率]
     * @return [type]              [description]
     */
    public function bazaar_reward($market_list = array(), $list, $rate = array())
    {
        $totalprice = bcmul($list['price'], $rate['market'] / 100,2);

        $destroy = ['orderid'=> $list['id'], 'e_no'=>$list['e_no'], 'type'=>8, 'price'=>$totalprice];

        if(empty($market_list))
        {   
            $destroy['remark'] = '市场人员没有返润会员';
            $this->add_destroy( $destroy ) ;return;
        }
         
        if(empty($rate['market'])) 
        {
            $destroy['remark'] = '没有设置市场人员返润比例';
            $this->add_destroy( $destroy ) ;return;
        }


        foreach ($market_list as $key => $value) {
            
            switch ($key) {
                case 'partner_user':

                	$price = bcmul($totalprice, $rate['market_partner'] / 100,2);

	                $destroy = ['orderid'=> $list['id'], 'e_no'=>$list['e_no'], 'type'=>5, 'price'=>$price];

                    if(empty($value))
                    {   
                        $destroy['remark'] = '没有合伙人返润会员';
                        $this->add_destroy( $destroy ) ;continue;
                    }

                    if(empty($rate['market_partner']))
                    {   
                        $destroy['remark'] = '没有设置合伙人员返润比例';
                        $this->add_destroy( $destroy ) ;continue;
                    }
                    
                    if($price < 0.01) 
                    {
                        $destroy['remark'] = '合伙人返润金额小0.01';
                        $this->add_destroy( $destroy ) ;continue;
                    }

                    $data = ['user_id'=>$value, 'price'=>$price, 'remark'=>'合伙人员返润'];
                    $this->add_commission($data, $list, 5);

                    continue;
                
                case 'direct_user':

                	$price = bcmul($totalprice, $rate['market_direct'] / 100,2);

                	$destroy = ['orderid'=> $list['id'], 'e_no'=>$list['e_no'], 'type'=>6, 'price'=>$price];

                    if(empty($value))
                    {   
                        $destroy['remark'] = '没有直接分享者返润会员';
                        $this->add_destroy( $destroy ) ;continue;
                    }

                    if(empty($rate['market_direct']))
                    {   
                        $destroy['remark'] = '没有设置直接分享者返润比例';
                        $this->add_destroy( $destroy ) ;continue;
                    }
     
                    if($price < 0.01) 
                    {
                        $destroy['remark'] = '直接分享返润金额小0.01';
                        $this->add_destroy( $destroy ) ;continue;
                    }

                    $data = ['user_id'=>$value, 'price'=>$price, 'remark'=>'直接分享返润'];
                    $this->add_commission($data, $list, 6);

                    continue;

                case 'indirect_user':

                	$price = bcmul($totalprice, $rate['market_indirect'] / 100,2);

                	$destroy = ['orderid'=> $list['id'], 'e_no'=>$list['e_no'], 'type'=>7, 'price'=>$price];

                    if(empty($value))
                    {   
                        $destroy['remark'] = '没有间接分享会员';
                        $this->add_destroy( $destroy ) ;continue;
                    }

                    if(empty($rate['market_indirect']))
                    {   
                        $destroy['remark'] = '没有设置间接分享者返润比例'; 
                        $this->add_destroy( $destroy ) ;continue;
                    }

                    if($price < 0.01) 
                    {
                        $destroy['remark'] = '间接分享返润金额小0.01'; 
                        $this->add_destroy( $destroy ) ;continue;
                    }

                    $data = ['user_id'=>$value, 'price'=>$price, 'remark'=>'间接分享返润'];
                    $this->add_commission($data, $list, 7);

                    continue;
            }
        }

    }


    /**
     * [destroy_commission 一个订单记录一条总的记录]
     * @param  array  $list [订单]
     * @return [type]       [description]
     */
    public  function destroy_commission($list = [])
    {
  
	    $share_price = array_sum(array_map(create_function('$val', 'return $val["price"];'), self::$share_log));
    	
	    $destroy_price = array_sum(array_map(create_function('$val', 'return $val["price"];'), self::$destroy_log));

        $nickname = M('users')->where('user_id',$list['user_id'])->value('nickname');
     	$arr = [
    		'orderid'         => $list['id'],
            'pay_userid'      => $list['user_id'],
            'nickname'        => $nickname, // 冗余会员名称
            'ordersn'         => $list['ordersn'], // 冗余订单号
    		'equipment_id'    => $list['equipment_id'],
    		'price'           => $list['price'],
    		'share_price'     => $share_price,
    		'destroy_price'   => $destroy_price,
    		'share_log'       => serialize(self::$share_log),
    		'destroy_log'     => serialize(self::$destroy_log),
            'createtime'      => time()
    	];

    	$code = Db::name('can_general')->insert($arr);
        if(!$code)
        {
            $query = Db::name('can_general')->fetchSql(true)->find(1);
            $sql = 'SQL Query:'.$query;
            throw new \Exception($sql);
        }
    }



    /**
     * [add_commission 添加分佣记录]
     * @param array  $data      [user_id,price,remark]
     * @param [type] $list      [支付订单]
     */
    public function add_commission($data = array(), $list, $type)
    {

        $arr = [
            'user_id'       => $data['user_id'],        // 获取分润会员ID
            'orderid'       => $list['id'],        		// 订单ID
            'employ_id'     => $list['user_id'],        // 支付人ID
            'hotel_id'      => $list['hotel_id'],       // 酒店ID
            'e_no'          => $list['e_no'],           // 设备号
            'type'        	=> $type,         			// 返润类型
            'pack_id'       => $list['pack_id'],        // 套餐ID
            'pay_price'     => $list['price'],          // 支付佣金
            'price'         => $data['price'],          // 分佣佣金
            'remark'        => $data['remark'],         // 备注说明
            'createtime'    => time()                   // 时间戳
        ];
        

        $code = Db::name('can_commission')->insert($arr);
        if(!$code)
        {
            $query = Db::name('can_commission')->fetchSql(true)->find(1);
            $sql = 'SQL Query:'.$query;
            throw new \Exception($sql);
        }

        self::$share_log[] = ['user_id'=>$data['user_id'], 'e_no'=>$list['e_no'], 'type'=>$type, 'price'=>$data['price'], 'remark'=>$data['remark']];
		
        /** @param   int     $user_id        用户id
        * @param   int    $user_money     可用余额变动
        * @param   int     $pay_points     消费积分变动
        * @param   string  $desc    变动说明
        * @param   int    distribut_money 分佣金额
        * @param int $order_id 订单id
        * @param string $order_sn 订单sn*/

        accountLog($data['user_id'], $data['price'], 0, $data['remark'], $data['price'], $list['id'], $list['ordersn']);

    }



    /**
     * [add_destroy 记录销毁返润记录]
     * @param  [type] $data [未返润说明]
     * @return [type]       [description]
     */
    public function add_destroy($data)
    {
        
        $arr = [
        	'orderid' 		=> $data['orderid'],
        	'e_no' 	        => $data['e_no'],
        	'type' 			=> $data['type'],
        	'price' 		=> $data['price'],
        	'remark' 		=> $data['remark'],
        ];

        $code = Db::name('can_destroy')->insert($arr);
        if(!$code)
        {
            $query = Db::name('can_destroy')->fetchSql(true)->find(1);
            $sql = 'SQL Query:'.$query;
            throw new \Exception($sql);
        }

        self::$destroy_log[] = $arr;
    }


    /**
     * [write_log 记录未返润]
     * @param  [type] $data [未返润说明]
     * @param  [type] $txt  [文件名]
     * @return [type]       [description]
     */
    public function write_log($data, $txt)
    {
        
        $uploadUrl =  "./log/".date('Y').'/'.date('n').'/'.date('j').'/';

        if (!is_dir($uploadUrl)) {
            mkdir ($uploadUrl, 0777, true );
        }

        $myfile = fopen($uploadUrl.$txt, "a+") or die("Unable to open file!");
        $str = date('Y-m-d H:i:s') .' orderid:'. $data['orderid'].' msg:'.$data['msg'];

        fwrite($myfile, $str."\r\n");
        fclose($myfile);
    }



}
