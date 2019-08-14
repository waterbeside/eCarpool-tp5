<?php

namespace app\npd\validate;

use think\Validate;

class Category extends Validate
{
    protected $rule = [
        'name'  => 'require',
    ];

    protected $message = [
        'name.require'     => '请输入分类中文名',
    ];
}
