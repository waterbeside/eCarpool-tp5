<?php

namespace app\npd\model;

use think\Db;
use think\Model;
use my\RedisData;

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

    /**
     * 取得账号详情
     *
     * @param integer $uid 用户id
     * @param integer $exp 缓存有效时间
     */
    public function findByUid($uid = "", $exp = 60 * 5)
    {
        if (!$uid) {
            return false;
        }
        $cacheKey = "npd_site:user:detail:uid_" . $uid;
        $redis = new RedisData();
        $cacheData = $redis->cache($cacheKey);
        if ($cacheData) {
            return $cacheData;
        }
        $res = $this->find($uid);

        if ($res) {
            $res = $res->toArray();
            $exp_offset = getRandValFromArray([1,2,3]);
            $exp +=  $exp_offset * 60;
            $cacheData = $redis->cache($cacheKey, $res, $exp);
        }
        return $res;
    }

    /**
     * 删除用户详情数据
     *
     * @param string|integer $account|uid
     * @return void
     */
    public function deleteDetailCache($account = "", $byID = false)
    {
        $cacheKey = $byID ? "npd_site:user:detail:uid_" . $account : "npd_site:user:detail:ac_" . strtolower($account);
        $redis = new RedisData();
        $redis->delete($cacheKey);
    }
}
