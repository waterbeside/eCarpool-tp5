<?php

namespace app\npd\validate;

use think\Validate;

class User extends Validate
{
    protected $rule = [
        'account'        => 'require|length:3,30|unique:npd/user',
        'password'         => 'require|confirm:confirm_password|min:6',
        'confirm_password' => 'confirm:password',
        // 'Department'       => 'require',
    ];
    // |unique:carpool/user
    protected $message = [
        'account.require'         => '请输入用户名',
        'account.length'          => '用户名不得少于3位',
        'account.unique'          => '用户名已存在',
        'password.confirm'          => '两次输入密码不一致',
        'password.require'          => '请填写密码',
        'password.min'              => '密码不得少于6位',
        'confirm_password.confirm'  => '两次输入密码不一致',
    ];

    // edit 验证场景定义
    public function sceneEdit()
    {
        return $this->only(['account']);
            // ->remove('account', 'unique');
    }

    // edit 验证场景定义
    public function sceneEdit_change_password()
    {
        return $this->only(['account', 'Department', 'password', 'confirm_password']);
            // ->append('password', 'length:6,18')
            // ->remove('account', 'unique');
    }
}
