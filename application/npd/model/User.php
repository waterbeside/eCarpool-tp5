<?php

namespace app\npd\model;

use think\Db;
use think\Model;

class User extends Model
{
    protected $connection = 'database_npd';
    protected $table = 't_user';
    protected $pk = 'id';

    protected $insert = ['create_time'];

    /**
     * 自动生成时间
     * @return bool|string
     */
    protected function setCreateTimeAttr()
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * 密码加密
     * @param {String} password password
     * @param {String} salt 盐
     * @param {String} type 1:第一层md5加密，0:第一层不加密
     */
    public function hashPassword($password, $salt, $type = 0)
    {
        $hash_1 = '';
        if ($type) {
            $hash_1 = md5($password);
        } else {
            $hash_1 = $password;
        }
        $hash_2 = $hash_1.$salt;
        return md5($hash_2);
    }

    /**
     * 创建加密密码和盐
     * @param {String} pass 原始密码
     * @param {Integer} type 1:第一层md5加密，0:第一层不加密
     */
    public function createPassword($pass, $type = 0)
    {
        $salt = getRandomString(6);
        $password = $this->hashPassword($pass, $salt, $type);
        return ['salt'=>$salt, 'password'=>$password ];
    }
}
