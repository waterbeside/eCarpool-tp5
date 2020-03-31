<?php

namespace app\admin\service;

use think\Db;
use Firebase\JWT\JWT;
use app\common\service\Service;
use app\common\model\AdminUser;
use think\facade\Cookie;
use think\facade\Session;
use my\RedisData;

class Admin extends Service
{

    /**
     * 登录成功后设置cookie session等信息
     */
    public function setLoginData($data, $exp = null)
    {
        $token = $data['jwt'];
        $user = $data['user'];
        $exp = $exp === 0 ? config('secret.admin_setting.jwt_exp') : $exp;
        Cookie::set('admin_token', $token, $exp);
        Session::set('admin_id', $user['id']);
        Session::set('admin_name', $user['username']);
    }

    /**
     * 登出
     *
     * @return void
     */
    public function logout()
    {
        Session::delete('admin_id');
        Session::delete('admin_name');
        Cookie::delete('admin_token');
    }

    /**
     * 登陆
     * @param [string,integer] $identifier 用户ID,或者用户名
     * @param string $password 用户密码，不能为空
     * @return array 成功返回用户登录成功的相关信息数据，否则返回false
     */
    public function loginUser($identifier, $password)
    {
        if (empty($identifier) || empty($password)) {
            return false;
        }
        //

        $AdminUser = new AdminUser();
        $user = $AdminUser->getLocalUser($identifier, $password);
        if (!$user) {
            return false;
        } else {
            $jwt = $this->createToken($user);
            $ip = request()->ip();
            $AdminUser->where([['id', '=', $user['id']]])->update(
                [
                    'last_login_time' => date('Y-m-d H:i:s', time()),
                    'last_login_ip'   => $ip,
                ]
            );
            return ['code' => 0, 'data' => ['jwt' => $jwt, 'user' => $user, 'ip' => $ip]];
        }
    }

    /**
     * 创建token
     *
     * @param array $userData 用户数据
     * @param integer $exp     JWT超时，当不设时，从配置里拿
     * @return string 返回生成JWT
     */
    public function createToken($userData, $exp = null)
    {
        $key = config('secret.admin_setting.jwt_key');
        $exp = !$exp ? config('secret.admin_setting.jwt_exp') : $exp;
        $token = array(
            "iss" => "carpool", //签发者
            // "aud" => "carpool", //指定接收方
            "iat" => time(), //签发时间
            "exp" => time() + $exp, //过期时间
            "nbf" => time(), //在此之前不被接受
            "username" => $userData['username'],
            "uid" => $userData['id'],
        );
        $jwt = JWT::encode($token, $key);
        return $jwt;
    }

    /**
     * 从请求取得JWT
     *
     * @return string
     */
    public function getToken()
    {
        $Authorization = request()->header('Authorization');
        $temp_array    = explode('Bearer ', $Authorization);
        $Authorization = count($temp_array) > 1 ? $temp_array[1] : '';
        $Authorization = $Authorization ? $Authorization : cookie('admin_token');
        $Authorization = $Authorization ? $Authorization : input('request.admin_token');
        return $Authorization;
    }

    /**
     * 验证jwt
     *
     */
    public function checkToken($Authorization = null)
    {

        $Authorization =  $Authorization ? $Authorization : $this->getToken();

        if (!$Authorization) {
            return $this->error(10004, '您尚未登入', ['url' => 'admin/login/index']);
        } else {
            $code = 10004;
            try {
                $jwtDecode = JWT::decode($Authorization, config('secret.admin_setting')['jwt_key'], array('HS256'));
                $code = 0;
            } catch (\Firebase\JWT\SignatureInvalidException $e) {  //签名不正确
                $msg =  $e->getMessage();
            } catch (\Firebase\JWT\BeforeValidException $e) {  // 签名在某个时间点之后才能用
                $msg =  $e->getMessage();
            } catch (\Firebase\JWT\ExpiredException $e) {  // token过期
                $msg =  $e->getMessage();
            } catch (\Exception $e) {  //其他错误
                $msg =  $e->getMessage();
            } catch (\DomainException $e) {  //其他错误
                $msg =  $e->getMessage();
            }
            if ($code > 0) {
                return $this->error(10004, $msg);
            }
            $returnData  = (array) $jwtDecode;
            return $returnData;
        }
    }

    /**
     * 记住用的登录情况到缓存
     *
     * @param integer $uid 用户id
     * @param array $data 要缓存的用户登入信息
     * @param integer $exp 缓存过期时间
     * @return void
     */
    public function remUser($uid, $data, $exp = 0, $refresh = 0)
    {
        $exp = $exp ? $exp : config('secret.admin_setting.online_exp');
        $cackeKey = "carpool_admin:online_admin:$uid";
        $sid_name = config('session.name');
        $sess_id = Cookie::get($sid_name);
        $setData = [
            'sess_id' => $refresh && isset($data['sess_id']) ? $data['sess_id'] : $sess_id,
            'jwt' => $data['jwt'],
            'user' => $data['user'],
            'ip' => $data['ip'],
            'login_time' => $refresh && isset($data['login_time']) ?   $data['login_time'] : time(),
            'refresh_time' => time(),
        ];
        $redis = RedisData::getInstance();
        $redis->cache($cackeKey, $setData, $exp);
    }

    /**
     * 取得admin_id
     *
     * @return void
     */
    public function getAdminID()
    {
        $admin_id =  Session::has('admin_id') ? Session::get('admin_id') : null;
        if (!$admin_id) {
            $jwtDecode = $this->checkToken();
            if (!$jwtDecode) {
                return $this->error(10004, $this->errorMsg);
            }
            $admin_id = $jwtDecode['uid'];
        }
        return $admin_id ? $admin_id : $this->error(10004, lang('You are not logged in'));
    }

    /**
     * 取得admin的用户信息
     *
     * @return void
     */
    public function getAdminData()
    {
        $data = $this->checkRemUser();
        return $data && isset($data['user']) ?  $data['user'] : false;
    }

    /**
     * 验证用户是否有效在线
     *
     * @param integer $uid 用户id
     * @param array $data 要验证的信息: ['sess_id', 'jwt']
     * @return void
     */
    public function checkRemUser($uid = null, $data = null)
    {
        $uid  = $this->getAdminID();
        if (!$uid) {
            return $this->error(10004, lang('You are not logged in'));
        }
        $cackeKey  = "carpool_admin:online_admin:$uid";
        $redis = RedisData::getInstance();
        $cacheData = $redis->cache($cackeKey);
        if (!$cacheData) {
            $this->logout();
            return $this->error(10004, lang('You haven`t operated for a long time, please log in again'));
        }
        $sid_name = config('session.name');
        $sess_id = Cookie::get($sid_name);
        $jwt = $this->getToken();
        if ($sess_id != $cacheData['sess_id'] &&  $jwt != $cacheData['jwt']) {
            return $this->error(10009, lang('You log in elsewhere, if...'));
        }
        return $cacheData;
    }

    /**
     * 重签用户登入通行证
     *
     * @param integer $uid    用户uid
     * @param boolean $type   0:优先从redis取数据，1:从数据库取数据
     * @return void
     */
    public function reSignPassport($uid, $type = 0)
    {
        if (is_array($uid)) {
            $data = $uid;
            $uid = $data['user']['id'];
        } else {
            $data = $this->checkRemUser($uid);
        }
        if (!$data) {
            $errorCode = $this->errorCode > 0 ? $this->errorCode : -1;
            $errorMsg = $this->errorMsg  ? $this->errorMsg : "Lost admin online data";
            return $this->error($errorCode, $errorMsg);
        }
        $now = time();
        $refreshTime = isset($data['refresh_time']) ? $data['refresh_time'] : 0;
        $exp = config('secret.admin_setting.online_exp') ? config('secret.admin_setting.online_exp') : 2 * 3600;
        $exp_x = $exp  > 60 * 30 ? $exp - (60 * 20) : $exp * 0.9;
        if ($now - $refreshTime < $exp_x) {
            // return $data;
        }
        if ($type) {
            $data['user'] = AdminUser::find($uid)->toArray();
        }
        if (!$data['user']) {
            return $this->error(-1, "User does not exist");
        }
        $data['jwt'] = $this->createToken($data['user']);
        $this->setLoginData($data);
        return $this->remUser($uid, $data, 0, 1);
    }
}
