<?php
namespace app\npd\validate;

use think\Validate;

class Nav extends Validate
{
    protected $rule = [
        'name'  => 'require',
        'name_en'  => 'require',
    ];

    protected $message = [
        'name.require'     => '请输入导航中文名',
        'name_en.require'     => '请输入导航英文名',
    ];


}
