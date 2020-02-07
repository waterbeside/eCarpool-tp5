<?php

namespace app\carpool\validate;

use think\Validate;

class Address extends Validate
{
    protected $rule = [
        'addressname'  => 'require',
        'longtitude'  => 'float',
        'latitude'  => 'float',
    ];

    protected $message = [
        'addressname.require'  => '起点名不能为空',
        'longtitude.float'     => '起点经度不能为空，且为数字',
        'latitude.float'     => '起点纬度不能为空，且为数字',
    ];
}
