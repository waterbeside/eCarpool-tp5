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
                $this->jsonReturn(-1,$validate_result);
            }

            $where['username'] = $data['username'];
            // $where['password'] = md5($data['password'] . Config::get('salt'));

            $Model_User = model('AdminUser');
            $res = $Model_User->loginUser($data['username'],$data['password']);
            if(!is_array($res)){
              $AdminLog->add('后台用户登入失败，用户名或密码错误 username ='.$data['username'],-1);
              $this->jsonReturn(-1,'用户名或密码错误');
              // return json(array('code' => 0, 'msg' => '用户名或密码错误'));
            }


            $token = $res['data']['jwt'];
            $user  = $res['data']['user'];


            $carpool_uid = $user['carpool_uid'];
            if($user['status']!=1){
              $AdminLog->add('后台用户登入失败，帐号受限 username ='.$data['username'],-1);
              $this->jsonReturn(-1,'此帐号受限');
            }



            if($carpool_uid > 0 && $user['carpool_account']){

              //从Hr接口验证是否离职
              try {
                $checkActiveUrl  = config('others.local_hr_sync_api.single');
                $params = [
                  'code'=>$user['carpool_account'],
                  'is_sync'=>0,
                ];
                $checkActiveRes = $this->clientRequest($checkActiveUrl,$params,'GET');

                  // $res = json_decode($content, true);
              } catch (Exception $e) {
                  // $this->errorMsg ='拉取失败';
                  $this->error("登入失败");
              }
              if(!$checkActiveRes || $checkActiveRes['code'] !== 0 ){
                $AdminLog->add('后台用户登入失败，用户关联的capool账号已离职 username ='.$data['username'],-1);
                $this->jsonReturn(-1,'此帐号员工已离职');
              }
            }



            $exp = config('secret.admin_setting.jwt_exp');
            Cookie::set('admin_token',$token,$exp);
            Session::set('admin_id', $user['id']);
            Session::set('admin_name', $user['username']);
            // return json(array('code' => 1, 'msg' => '登录成功','data'=>['token'=>$token,'user'=>$user]));
            $AdminLog->add('后台用户登入成功 username ='.$data['username'],0);
            $this->jsonReturn(0,['token'=>$token,'user'=>$user],'登入成功');





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
