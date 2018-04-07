<?php
namespace app\admin\controller;

use think\facade\Config;
use think\Controller;
use think\Db;
use think\facade\Session;

/**
 * 后台登录
 * Class Login
 * @package app\admin\controller
 */
class Login extends Controller
{
    /**
     * 后台登录
     * @return mixed
     */
    public function index()
    {
        return $this->fetch();
    }

    /**
     * 登录验证
     * @return string
     */
    public function login()
    {
        if ($this->request->isPost()) {
            $data            = $this->request->only(['username', 'password', 'verify']);
            $validate_result = $this->validate($data, 'Login');

            if ($validate_result !== true) {
                $this->error($validate_result);
            } else {
                $where['username'] = $data['username'];
                // $where['password'] = md5($data['password'] . Config::get('salt'));


                $admin_user = Db::name('admin_user')->field('id,username,status,password')->where($where)->find();

                if (!empty($admin_user)) {
                    $hash = $admin_user['password'];
                    if(!password_verify($data['password'], $hash)){
                      $this->error('用户名或密码错误');
                    }

                    if ($admin_user['status'] != 1) {
                        $this->error('当前用户已禁用');
                    } else {
                        Session::set('admin_id', $admin_user['id']);
                        Session::set('admin_name', $admin_user['username']);
                        Db::name('admin_user')->update(
                            [
                                'last_login_time' => date('Y-m-d H:i:s', time()),
                                'last_login_ip'   => $this->request->ip(),
                                'id'              => $admin_user['id']
                            ]
                        );
                        $this->success('登录成功', 'admin/index/index');
                    }
                } else {
                    $this->error('用户名或密码错误');
                }
            }
        }
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        Session::delete('admin_id');
        Session::delete('admin_name');
        $this->success('退出成功', 'admin/login/index');
    }


    public function test(){
      $hash = password_hash(123456, PASSWORD_DEFAULT);
      var_dump($hash);
      var_dump(password_verify(123456, $hash));
    }
}
