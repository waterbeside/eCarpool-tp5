<?php

namespace app\carpool\validate;

use think\Validate;

class Company extends Validate
{
    protected $rule = [
        'company_name'  => 'require|unique:carpool/Company',
    ];

    protected $message = [
        'company_name.unique'  => '公司名已存在',
        'company_name.require'     => '请输入公司名',
    ];

    protected $scene = [
        'edit'  =>  ['company_name' => 'require'],
    ];
}
