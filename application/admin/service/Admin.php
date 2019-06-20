<?php
namespace app\admin\service;

use think\Db;
use Firebase\JWT\JWT;
use app\common\service\Service;
use app\common\model\AdminUser;
use app\common\model\AdminLog;
use think\facade\Cookie;
use think\facade\Session;

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
   * 登陆
   * @param [string,integer] $identifier 用户ID,或者用户名
   * @param string $password 用户密码，不能为空
   * @return array 成功返回用户登录成功的相关信息数据，否则返回false
   */
  function loginUser($identifier, $password)
  {
    if (empty($identifier) || empty($password)) {
      return false;
    }
    //
    $key = config('secret.admin_setting')['jwt_key'];
    $exp = config('secret.admin_setting')['jwt_exp'];

    $AdminUser = new AdminUser();
    $user = $AdminUser->getLocalUser($identifier, $password);
    if (!$user) {
      return false;
    } else {
      $token = array(
        "iss" => "carpool", //签发者
        // "aud" => "carpool", //指定接收方
        "iat" => time(), //签发时间
        "exp" => time() + $exp, //过期时间
        "nbf" => time(), //在此之前不被接受
        "username" => $user['username'],
        "uid" => $user['id'],
      );

      $jwt = JWT::encode($token, $key);
      $AdminUser->where([['id', '=', $user['id']]])->update(
        [
          'last_login_time' => date('Y-m-d H:i:s', time()),
          'last_login_ip'   => request()->ip(),
        ]
      );
      return ['code' => 0, 'data' => ['jwt' => $jwt, 'user' => $user]];
    }
  }

  /**
   * 验证jwt
   * 
   */
  public function checkToken()
  {
    $Authorization = request()->header('Authorization');
    $temp_array    = explode('Bearer ', $Authorization);
    $Authorization = count($temp_array) > 1 ? $temp_array[1] : '';
    $Authorization = $Authorization ? $Authorization : cookie('admin_token');
    $Authorization = $Authorization ? $Authorization : input('request.admin_token');

    if (!$Authorization) {
      return $this->error(10004, '您尚未登入', ['url' => 'admin/login/index']);
    } else {
      $code = 10004;
      try{
        $jwtDecode = JWT::decode($Authorization, config('secret.admin_setting')['jwt_key'], array('HS256'));
        $code = 0;
      } catch(\Firebase\JWT\SignatureInvalidException $e) {  //签名不正确
        $msg =  $e->getMessage();
      }catch(\Firebase\JWT\BeforeValidException $e) {  // 签名在某个时间点之后才能用
        $msg =  $e->getMessage();
      }catch(\Firebase\JWT\ExpiredException $e) {  // token过期
        $msg =  $e->getMessage();
       }catch(\Exception $e) {  //其他错误
        $msg =  $e->getMessage();
      }catch(\DomainException $e) {  //其他错误
        $msg =  $e->getMessage();
      }
      if($code > 0){
        return $this->error(10004, $msg);
      }
      return array(
        'username' => $jwtDecode->username,
        'uid' => $jwtDecode->uid,
      );

    }
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
}
