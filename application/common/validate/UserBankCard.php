<?php
namespace app\common\validate;
use think\Validate;

class UserBankCard extends Validate
{
    // 验证规则
    protected $rule = [
        ['bank_name', 'require'],
        ['card_name','require'],
        ['card_num','require|number'],
    ];
    //错误信息
    protected $message  = [
        'bank_name.require'    => '银行卡名称必填',
        'card_name.require'     => '持卡人姓名必填',
        'card_num.number'        => '银行卡必须为数字',
        'card_num.require'     => '银行卡账号必填',
    ];
}