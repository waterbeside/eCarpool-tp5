<?php
namespace app\npd\validate;

use think\Validate;

class Product extends Validate
{
    protected $rule = [
        'title'  => 'require',
        'title_en'  => 'require',
        'cid'  => 'min:0',
    ];

    protected $message = [
        'title.require'     => '请输入中文标题',
        'title_en.require'     => '请输入英文标题',
        'cid.min'     => '请选择分类',

    ];


}
