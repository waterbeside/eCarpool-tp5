<?php

namespace app\admin\controller;

use think\facade\Config;
// use app\common\controller\Base as BaseController;
use app\admin\controller\AdminBase;

use think\Db;
use think\facade\Session;
use think\facade\Cookie;
use app\common\model\AdminLog;
use app\carpool\model\User as CarpoolUser;
use app\admin\service\Admin as AdminService;

/**
 * 后台登录
 * Class Login
 * @package app\admin\controller
 */
class Login extends AdminBase
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
            $AdminLog = new AdminLog();
            $AdminService = new AdminService();


            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            }

            // $where['password'] = md5($data['password'] . Config::get('salt'));
            $res = $AdminService->loginUser($data['username'], $data['password']);
            if (!is_array($res)) {
                $AdminLog->add('后台用户登入失败，用户名或密码错误 username =' . $data['username'], -1);
                $this->jsonReturn(-1, lang('User name or password error'));
            }

            $token = $res['data']['jwt'];
            $user  = $res['data']['user'];

            if ($user['status'] != 1) {
                $AdminLog->add('后台用户登入失败，帐号受限 username =' . $data['username'], -1);
                $this->jsonReturn(-1, lang('User name or password error'));
            }

            //验证用户是否离职
            $carpool_uid = $user['carpool_uid'];
            if ($carpool_uid > 0 && $user['carpool_account']) {
                $CarpoolUser = new CarpoolUser();
                $checkActiveRes = $CarpoolUser->checkDimission($user['carpool_account']);
                if (!$checkActiveRes) {
                    if ($CarpoolUser->errorCode == 10003) {
                        $AdminLog->add('后台用户登入失败，用户关联的capool账号已离职 username =' . $data['username'], -1);
                        $errorMsg = lang('This account employee has left');
                    } else {
                        $AdminLog->add($CarpoolUser->errorMsg . ' username =' . $data['username'], -1);
                        $errorMsg = lang('Login failed');
                    }
                    $this->jsonReturn(-1, [], $errorMsg, ['error' => $CarpoolUser->errorMsg]);
                }
            }

            $AdminService->setLoginData($res['data'], 0); //设置session cookie等
            $AdminService->remUser($user['id'], $res['data']); //记录在线用户到缓存
            $AdminLog->add('后台用户登入成功 username =' . $data['username'], 0);
            $this->jsonReturn(0, ['token' => $token, 'user' => $user], lang('Sign in suceesfully'));
        }
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        $AdminService = new AdminService();
        $AdminService->logout();
        $AdminLog = new AdminLog;
        // $AdminLog->add('后台用户登出成功',0);
        $this->success(lang('Sign out suceesfully'), 'admin/login/index');
        // $this->jsonReturn(0,'退出成功');
    }
}
