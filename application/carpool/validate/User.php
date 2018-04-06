<?php
namespace app\admin\validate;

use think\Validate;

class User extends Validate
{
    protected $rule = [
        'loginname'         => 'require|unique:carpool/user',
        'password'         => 'confirm:confirm_password',
        'confirm_password' => 'confirm:password',
        'phone'            => 'unique:carpool/user',
        'Department'       => 'require',
        'is_active'        => 'require',
    ];

    protected $message = [
        'loginname.require'         => '请输入用户名',
        'loginname.unique'          => '用户名已存在',
        'password.confirm'         => '两次输入密码不一致',
        'confirm_password.confirm' => '两次输入密码不一致',

        'phone.unique'          => '手机号已存在',

        'email.email'              => '邮箱格式错误',
        'is_active.require'        => '请选择状态'
    ];
}
