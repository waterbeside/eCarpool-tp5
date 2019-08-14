<?php

namespace app\carpool\validate;

use think\Validate;

class Department extends Validate
{
    protected $rule = [
        'department_name'  => 'require',
        'company_id'  => 'require',
    ];

    protected $message = [
        'department_name.require'  => '请输入部门名',
        'company_id.require'  => '请输选择公司',
    ];
}
