<?php

namespace app\carpool\validate;

use think\Validate;

class User extends Validate
{
    protected $rule = [
        'loginname'        => 'require|length:3,30|unique:carpool/user',
        'password'         => 'require|confirm:confirm_password|min:6',
        'confirm_password' => 'confirm:password',
        'phone'            => 'unique:carpool/user',
        // 'Department'       => 'require',
    ];
    // |unique:carpool/user
    protected $message = [
        'loginname.require'         => '请输入用户名',
        'loginname.length'          => '用户名不得少于3位',
        'loginname.unique'          => '用户名已存在',
        'password.confirm'          => '两次输入密码不一致',
        'password.require'          => '请填写密码',
        'password.min'              => '密码不得少于6位',
        'confirm_password.confirm'  => '两次输入密码不一致',
        'phone.unique'              => '手机号已存在',
        'email.email'               => '邮箱格式错误',
        // 'Department.require'        => '部门不能为空',
    ];

    // edit 验证场景定义
    public function sceneEdit()
    {
        return $this->only(['Department']);
        // return $this->only(['loginname', 'Department'])
        //     ->remove('loginname', 'unique');
    }

    // edit 验证场景定义
    public function sceneEdit_change_password()
    {
        return $this->only(['Department', 'password', 'confirm_password']);
            // ->append('password', 'length:6,18')
            // ->remove('loginname', 'unique');
    }
}
