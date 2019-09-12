<?php

namespace app\npd\validate;

use think\Validate;

class Gallery extends Validate
{
    protected $rule = [
        'url'  => 'require',
    ];

    protected $message = [
        'url.require'     => '请上传图片',
    ];
}
