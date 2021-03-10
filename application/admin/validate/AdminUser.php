<?php

namespace app\admin\validate;

use think\Validate;
use app\common\model\AdminUser as AdminUserModel;

/**
 * 管理员验证器
 * Class AdminUser
 * @package app\admin\validate
 */
class AdminUser extends Validate
{
    protected $rule = [
        // 'username'         => 'require|unique:admin_user',
        'username'         => 'checkUsername',
        'password'         => 'confirm:confirm_password',
        'confirm_password' => 'confirm:password',
        'status'           => 'require',
        'group_id'         => 'require'
    ];

    protected $message = [
        'username.require'         => '请输入用户名',
        // 'username.checkUsername'         => '用户名有误',
        // 'username.unique'          => '用户名已存在',
        'password.confirm'         => '两次输入密码不一致',
        'confirm_password.confirm' => '两次输入密码不一致',
        'status.require'           => '请选择状态',
        'group_id.require'         => '请选择所属权限组'
    ];

    protected function checkUsername($value, $rule, $data = [])
    {
        
        if (empty($value) || trim($value) == '') {
            return '请输入用户名';
        }
        $map = [
            ['is_delete', '=', 0]
        ];
        if (isset($data['id'])) {
            $map[] = ['id', '<>', $data['id']];
        }
        $map[] = ['username', '=', $value];
        $adminUser = new AdminUserModel();
        $res = $adminUser->where($map)->find();
        if ($res) {
            return '用户名已存在';
        }

        return true;
    }
}
