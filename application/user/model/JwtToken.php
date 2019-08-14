<?php

namespace app\user\model;

use my\RedisData;
use app\common\model\BaseModel;
use Firebase\JWT\JWT;
use think\facade\Log;

// use think\Model;

class JwtToken extends BaseModel
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 't_jwt_token';


    // 直接使用配置参数名
    protected $connection = 'database_carpool';
    protected $pk = 'id';

    protected $activeTokenCacheKey = 'carpool:user:token_dict';

    public $invalid_type = [
        '-99' => '系统控制',
        '-4' => '修改了资料',
        '-3' => '登录时间过长',
        '0' =>  '未失效',
        '1' => '正常登出',
        '2' => '单点登录',
    ];

    /**
     * 取得失效类型的文字描述
     */
    public function parseInvalidType($num)
    {
        return isset($this->invalid_type[$num]) ? $this->invalid_type[$num] : $num;
    }

    /**
     * 取得token详情
     *
     * @param integer $uid uid
     * @param integer $recache 是否重写缓存
     */
    public function getDetailByUid($uid, $client = ['iOS', 'Android', '1', '2'])
    {
        $map = [
            ['uid', '=', $uid],
            ['is_delete', '=', 0],
        ];
        if (!empty($client) && is_array($client)) {
            $map[] = ['client', 'in', $client];
        }
        $res = $this->where($map)->find();
        return $res;
    }

    /**
     * 取得有效的在线token
     *
     * @param integer $uid
     * @return void
     */
    public function getActiveToken($uid, $recache = 0)
    {
        $cacheKey = $this->activeTokenCacheKey;
        $redis = new RedisData();
        $cacheData = $redis->hGet($cacheKey, $uid);
        if ($cacheData && !$recache) {
            return $cacheData;
        }
        $res = $this->getDetailByUid($uid, ['iOS', 'Android', '1', '2']);
        if (!$res) {
            return false;
        }
        $jwt = $res['token'];
        $redis->hSet($cacheKey, $uid, $jwt);
        return $jwt;
    }

    /**
     * 取得token在线信息
     *
     * @param string $token
     * @return array
     */
    public function getByToken($token)
    {
        $res = $this->where('token', $token)->find();
        if (!$res) {
            return false;
        }
        return $res->toArray();
    }

    /**
     * 检查用户是否有效在线
     *
     * @param integer $uid
     * @param string $token 要用来验证的token
     */
    public function checkActive($uid, $token)
    {
        $tokenActive = $this->getActiveToken($uid);
        if ($tokenActive != $token) {
            $data = $this->getByToken($token);
            if ($data) {
                $this->errorData = [
                    'uid' => $data['uid'],
                    'platform' => $data['client'] == 'iOS' ? 1 : $data['client'] == 'Android' ? 2 : 0,
                    'iss' => $data['iss'],
                    'token' => $data['token'],
                    'iat' => $data['iat'],
                    'exp' => $data['exp'],
                    'create_type' => $data['create_type'],
                    'create_time' => $data['create_time'],
                    'invalid_type' => $data['invalid_type'],
                    'invalid_time' => $data['invalid_time'],
                ];
            }
            return false;
        } else {
            return true;
        }
    }

    /**
     * 计算单点登入次数
     *
     * @param integer $uid
     * @return integer
     */
    public function countByUid($uid)
    {
        return $this->where('uid', $uid)->count();
    }


    /**
     * 插入当前jwt到数据库
     *
     * @param string $token 要插入的token
     * @param array $jwtData
     */
    public function addToken($token, $jwtData = null)
    {

        if (!is_object($jwtData) || empty($jwtData)) {
            $jwtData = JWT::decode($token, config('secret.front_setting')['jwt_key'], array('HS256'));
        }
        if (!$jwtData) {
            return false;
        }
        $client = strtolower($jwtData->client);
        $data = [
            'uid' => $jwtData->uid,
            'client' => $client === 'ios' ? 'iOS' : $client === 'android' ? 'Android' : $jwtData->client,
            'iss' => $jwtData->iss,
            'token' => $token,
            'iat' => $jwtData->iat,
            'exp' => $jwtData->exp,
            'create_type' => -98,
            'create_time' => time(),
            'invalid_type' => 0,
            'invalid_time' => 0,
            'is_delete' => 0,
        ];
        return $this->insertGetId($data);
    }


    /**
     * 废除token
     *
     * @param array $map 筛选字段
     * @param integer $type jwt作废原因 (-99:系统控制 /  -4:修改了资料 / -3:登录时间过长 /  1:正常登出 /  2:单点登录 )
     */
    public function invalidateByMap($map, $type = -99)
    {
        if (!$map) {
            return false;
        }
        $updata = [
            'invalid_type' => $type,
            'invalid_time' => time(),
            'is_delete' => 1,
        ];
        $data = $this->where($map)->find();
        if (!$data) {
            return true;
        }
        $res = $this->where($map)->update($updata);
        if ($res !== false) {
            $redis = new RedisData();
            $cacheKey = $this->activeTokenCacheKey;
            $redis->hDel($cacheKey, $data['uid']);
        }
        return $res;
    }


    /**
     * 废除token
     *
     * @param string $token jwt
     * @param integer $type jwt作废原因 (-99:系统控制 /  -4:修改了资料 / -3:登录时间过长 /  1:正常登出 /  2:单点登录 )
     */
    public function invalidate($token, $type = -99)
    {
        if (!$token) {
            return false;
        }
        $map = [
            ['token', '=', $token]
        ];
        return $this->invalidateByMap($map, $type);
    }

    /**
     * 废除token
     *
     * @param integer $uid uid
     * @param integer $type jwt作废原因 (-99:系统控制 /  -4:修改了资料 / -3:登录时间过长 /  1:正常登出 /  2:单点登录 )
     */
    public function invalidateByUid($uid, $type = -99, $client = ['iOS', 'Android', '1', '2'])
    {
        if (!$uid) {
            return false;
        }
        $map = [
            ['uid', '=', $uid],
            ['is_delete', '=', 0],
        ];
        if (!empty($client) && is_array($client)) {
            $map[] = ['client', 'in', $client];
        }
        return $this->invalidateByMap($map, $type);
    }
}
