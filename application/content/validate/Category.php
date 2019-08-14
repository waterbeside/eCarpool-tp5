<?php

namespace app\content\validate;

use think\Validate;

class Category extends Validate
{
    protected $rule = [
        'name_zh'  => 'require',
    ];

    protected $message = [
        'name_zh.require'     => '请输入分类中文名',
    ];
}
