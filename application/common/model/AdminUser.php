<?php
namespace app\common\model;

use think\Model;
use Firebase\JWT\JWT;

/**
 * 管理员模型
 * Class AdminUser
 * @package app\common\model
 */
class AdminUser extends Model
{
    protected $insert = ['create_time'];

    /**
     * 创建时间
     * @return bool|string
     */
    protected function setCreateTimeAttr()
    {
        return date('Y-m-d H:i:s');
    }

    /**
   * 登陆
   * @param type $identifier 用户ID,或者用户名
   * @param type $password 用户密码，不能为空
   * @return type 成功返回true，否则返回false
   */
  function loginUser($identifier, $password) {
      if (empty($identifier) || empty($password)) {
          return false;
      }
      //
      $key = config('secret.admin_setting')['jwt_key'];
      $exp = config('secret.admin_setting')['jwt_exp'];


      $user = $this->getLocalUser($identifier, $password);
      if(!$user){
        return false;
      }else{
        $token = array(
            "iss" => "carpool", //签发者
            // "aud" => "carpool", //指定接收方
            "iat" => time(), //签发时间
            "exp" => time()+$exp, //过期时间
            "nbf" => time(), //在此之前不被接受
            "username" => $user['username'],
            "uid" => $user['id'],
        );

        $jwt = JWT::encode($token, $key);

        /*session('admin_id', $user['id']);
        session('auth_name', $user['username']);*/
        //cookie('user_name', $user['loginname'],86400);

        $this->update(
            [
                'last_login_time' => date('Y-m-d H:i:s', time()),
                'last_login_ip'   => request()->ip(),
                'id'              => $user['id']
            ]
        );
        return ['code'=>0,'data'=>['jwt'=>$jwt,'user'=>$user]];


      }
  }

  /**
   * 登出
   * @return type 成功返回true，否则返回false
   */
    function logoutUser(){
      cookie('user_token', null);
      return true;
    }

    /**
     * 根据提示符(username)和未加密的密码(密码为空时不参与验证)获取本地用户信息
     * @param type $identifier 为数字时，表示uid，其他为用户名
     * @param type $password
     * @return 成功返回用户信息array()，否则返回布尔值false
     */
    function getLocalUser($identifier, $password = null) {
        if (empty($identifier)) {
            return false;
        }
        $map = array();
        if (is_int($identifier)) {
            $map['id'] = $identifier;
        } else {
            $map['username'] = $identifier;
        }

        $user = $this->where($map)->find();
        if (!$user) {
            return false;
        }
        if ($password) {
            //验证本地密码是否正确
            $hash = $user['password'];
            if(!password_verify($password, $hash)){
              return false;
            }
        }
        return $user;
    }


}
