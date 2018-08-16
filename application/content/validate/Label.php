<?php
namespace app\content\validate;

use think\Validate;

class Label extends Validate
{
    protected $rule = [
        'name_zh'  => 'require',
    ];

    protected $message = [
        'name_zh.require'     => '请输入标签中文名',
    ];


}
