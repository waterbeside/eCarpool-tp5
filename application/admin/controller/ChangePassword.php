<?php

namespace app\admin\controller;

use app\admin\controller\AdminBase;
use think\facade\Config;
use think\Db;
use think\facade\Session;

/**
 * 修改密码
 * Class ChangePassword
 * @package app\admin\controller
 */
class ChangePassword extends AdminBase
{
    /**
     * 修改密码
     * @return mixed
     */
    public function index()
    {
        return $this->fetch('system/change_password');
    }

    /**
     * 更新密码
     */
    public function updatePassword()
    {
        if ($this->request->isPost()) {
            $admin_id    = $this->userBaseInfo['uid'];
            // $admin_id    = Session::get('admin_id');
            $data   = $this->request->param();

            if (!$data['password'] == $data['confirm_password']) {
                $this->jsonReturn(-1, '两次密码输入不一致');
            }

            $result = Db::name('admin_user')->find($admin_id);

            $hash = $result['password'];

            if (!password_verify($data['old_password'], $hash)) {
                $this->jsonReturn(-1, '密码错误');
            }


            // $new_password = md5($data['password'] . Config::get('salt'));
            $new_password  = password_hash($data['password'], PASSWORD_BCRYPT);
            $res          = Db::name('admin_user')->where(['id' => $admin_id])->setField('password', $new_password);

            if ($res !== false) {
                $this->log('修改密码成功，id=' . $admin_id, 0);
                $this->success('修改成功');
            } else {
                $this->log('修改密码失败，id=' . $admin_id, -1);
                $this->jsonReturn(-1, '修改失败');
            }
        }
    }
}
