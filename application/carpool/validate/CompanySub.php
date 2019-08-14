<?php

namespace app\carpool\validate;

use think\Validate;

class CompanySub extends Validate
{
    protected $rule = [
        'sub_company_name'  => 'require|unique:carpool/CompanySub',
    ];

    protected $message = [
        'sub_company_name.unique'          => '分厂名已存在',
        'sub_company_name.require'  => '请输入分厂名',
    ];

    // edit 验证场景定义
    /*public function sceneADD()
    {
        return $this->only(['sub_company_name'])
        ->append('sub_company_name', 'unique:carpool/CompanySub')
    }*/

    protected $scene = [
        'edit'  =>  ['sub_company_name' => 'require'],
    ];
}
