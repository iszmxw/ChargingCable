<?php
namespace app\api\logic;

class ChargeLogic
{
    public function getChargeCode($post)
    {
        $facility = M('lc_equipment_number')->where('number',$post['number'])->field('secret_key,number,password_number')->find();
        $str = md5(implode("",$facility));
        $str = substr($str,-3);
        $num = gmp_init($str,16);
        $first = $post['key'];//第一位
        $second = (($num>>9)&0x7)%5+1;//第二位
        $thirdly =  (($num>>6)&0x7)%5+1;//第三位
        $fourthly = (($num>>3)&0x7)%5+1;//第四位
        $fifth =  ($num & 0x7)%5+1;//第五位
        $code = $first.$second.$thirdly.$fourthly.$fifth;
        if($facility['password_number']==20){
            $password_number = 1;
        }else{
            $password_number = $facility['password_number']+1;
        }
        $password_number = sprintf("%02d",$password_number);
        if(substr_count($code,$first)==5){
            return $this->getChargeCode();
        }else{
            M('lc_equipment_number')->where('number',$post['number'])->setField('password_number',$password_number);
            return $code;
        }
    }
}