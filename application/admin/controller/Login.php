<?php
namespace app\admin\controller;

use think\facade\Config;
use app\common\controller\Base as BaseController;
use think\Db;
use think\facade\Session;
use think\facade\Cookie;
use app\common\model\AdminLog;

/**
 * 后台登录
 * Class Login
 * @package app\admin\controller
 */
class Login extends BaseController
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
            $AdminLog = new AdminLog;

            if ($validate_result !== true) {
                $this->jsonReturn(1,$validate_result);
            } else {
                $where['username'] = $data['username'];
                // $where['password'] = md5($data['password'] . Config::get('salt'));

                $Model_User = model('AdminUser');
                $res = $Model_User->loginUser($data['username'],$data['password']);
                if(is_array($res)){
                  $token = $res['data']['jwt'];
                  $user  = $res['data']['user'];
                  if($user['status']==1){
                    $exp = config('secret.admin_setting')['jwt_exp'];
                    Cookie::set('admin_token',$token,$exp);
                    Session::set('admin_id', $user['id']);
                    Session::set('admin_name', $user['username']);
                    // return json(array('code' => 1, 'msg' => '登录成功','data'=>['token'=>$token,'user'=>$user]));
                    $AdminLog->add('后台用户登入成功 username ='.$data['username'],0);
                    $this->jsonReturn(0,['token'=>$token,'user'=>$user],'登入成功');

                  }else{
                    $AdminLog->add('后台用户登入失败，帐号受限 username ='.$data['username'],-1);
                    $this->jsonReturn(1,'此帐号受限');
                  }

                }else{
                  $AdminLog->add('后台用户登入失败，用户名或密码错误 username ='.$data['username'],-1);
                  $this->jsonReturn(1,'用户名或密码错误');
                  // return json(array('code' => 0, 'msg' => '用户名或密码错误'));
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
        Cookie::delete('admin_token');
        $AdminLog = new AdminLog;
        // $AdminLog->add('后台用户登出成功',0);
        $this->success('退出成功', 'admin/login/index');
        // $this->jsonReturn(0,'退出成功');

    }


}
