<?php

namespace app\score\validate;

use think\Validate;

class Prize extends Validate
{
    protected $rule = [
        'name'  => 'require',
        'price'  => 'require|float',
        'amount'  => 'require|float',
        'total_count'  => 'require|float',

    ];

    protected $message = [
        'name.require'  => '奖品名不能为空',
        'price.require'     => '兑抽奖所需积分不能为空',
        'price.float'     => '抽奖所需积分必须为数字',
        'total_count.require'     => '开奖阈值不能为空',
        'total_count.float'     => '开奖阈值必须为数字',
        'amount.require'     => '采购价不能为空',
        'amount.float'     => '价格必须为数字',

    ];
}
