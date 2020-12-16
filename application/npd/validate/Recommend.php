<?php

namespace app\npd\validate;

use think\Validate;

class Recommend extends Validate
{
    protected $rule = [
        'image'  => 'require',
        'site_id'  => 'require|min:1',
    ];

    protected $message = [
        'image.require'     => '请上传图片',
        'site_id.min'     => '没有选择站点',
        'site_id.require'  => '没有选择站点',
    ];

    protected $scene = [
        'edit'  =>  ['image'],
    ];
}
