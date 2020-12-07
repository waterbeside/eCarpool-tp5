<?php

namespace app\npd\validate;

use think\Validate;

class Category extends Validate
{
    protected $rule = [
        'name'  => 'require',
        'site_id'  => 'require|min:1',
    ];

    protected $message = [
        'name.require'     => '请输入分类中文名',
        'site_id.min'     => '没有选择站点',
        'site_id.require'     => '没有选择站点',
    ];

    protected $scene = [
        'edit'  =>  ['name'],
    ];
}
