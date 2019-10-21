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
     * 根据提示符(username)和未加密的密码(密码为空时不参与验证)获取本地用户信息
     * @param type $identifier 为数字时，表示uid，其他为用户名
     * @param type $password
     * @return 成功返回用户信息array()，否则返回布尔值false
     */
    public function getLocalUser($identifier, $password = null)
    {
        if (empty($identifier)) {
            return false;
        }
        $map = array();
        if (is_int($identifier)) {
            $map[] = ['id', '=', $identifier];
        } else {
            $map[] = ['username', '=', $identifier];
        }

        $user = $this->where($map)->find();
        if (!$user) {
            return false;
        }
        if ($password) {
            //验证本地密码是否正确
            $hash = $user['password'];
            if (!password_verify($password, $hash)) {
                return false;
            }
        }
        return $user;
    }
}
