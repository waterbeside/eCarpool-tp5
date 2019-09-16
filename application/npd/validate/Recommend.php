<?php

namespace app\npd\validate;

use think\Validate;

class Recommend extends Validate
{
    protected $rule = [
        'image'  => 'require',
    ];

    protected $message = [
        'image.require'     => '请上传图片',
    ];
}
