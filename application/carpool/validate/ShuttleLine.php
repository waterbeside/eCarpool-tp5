<?php

namespace app\carpool\validate;

use think\Validate;

class ShuttleLine extends Validate
{
    protected $rule = [
        'start_name'  => 'require',
        'start_longitude'  => 'float',
        'start_latitude'  => 'float',
        'end_name'  => 'require',
        'end_longitude'  => 'float',
        'end_latitude'  => 'float',
    ];

    protected $message = [
        'start_name.require'  => '起点名不能为空',
        'start_longitude.float'     => '起点经度不能为空，且为数字',
        'start_latitude.float'     => '起点纬度不能为空，且为数字',
        'end_name.require'     => '终点名不能为空',
        'end_longitude.float'     => '终点经度不能为空，且为数字',
        'end_latitude.float'     => '终点纬度不能为空，且为数字',
    ];
}
