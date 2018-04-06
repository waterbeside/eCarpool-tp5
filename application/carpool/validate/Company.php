<?php
namespace app\carpool\validate;

use think\Validate;

class Company extends Validate
{
    protected $rule = [
        'company_name'  => 'require',

    ];

    protected $message = [
        'company_name.require'  => '请输入公司名',
    ];
}
