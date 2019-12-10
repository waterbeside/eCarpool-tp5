<?php

namespace app\carpool\validate;

use think\Validate;

class ShuttleTime extends Validate
{
    protected $rule = [
        'hours'  => 'require|integer',
        'minutes'  => 'require|integer',
    ];

    protected $message = [
        'hours.require'  => '小时不能为空',
        'minutes.require'     => '分钟数不能为空',
        'hours.integer'  => '小时数必须为数字',
        'minutes.integer'     => '分钟数必须为数字',
    ];
}
