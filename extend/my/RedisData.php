<?php

namespace my;

use app\common\model\Configs;
use \Redis;

/**
 * Redis数据
 ***/
class RedisData extends Redis
{
    protected $redisConfig = null;

    public function __construct()
    {
        $ConfigsModel = new Configs();
        $configs = $ConfigsModel->getConfigs();
        $this->connect($configs['redis_host'], $configs['redis_port']);
        // $this->connect("127.0.0.1", $configs['redis_port']);
        if ($configs['redis_auth']) {
            $this->auth($configs['redis_auth']);
        }
        $this->redisConfig = $configs;
    }


    /**
     * 通用的缓存方法
     *
     * @param string $cacheKey 缓存key
     * @param * $value 要缓存的值
     * @param integer $ex  有效时长 (秒) 当大于0时才设置有效时
     * @return array
     */
    public function cache($cacheKey, $value = false, $ex = 0)
    {
        if ($value === null) {
            return $this->del($cacheKey);
        } elseif ($value !== false) {
            $value = $this->formatValue($value);
            if ($ex > 0) {
                return $this->setex($cacheKey, $ex, $value);
            } else {
                return $this->set($cacheKey, $value);
            }
        } else {
            $str =  $this->get($cacheKey);
            $redData = $this->formatRes($str);
            return $redData;
        }
    }


    /**
     * hGet、hSet合并使用
     *
     * @param string $cacheKey
     * @param [type] $field
     * @param boolean $value
     * @param integer $ex
     * @return void
     */
    public function hCache($cacheKey, $field, $value = false, $ex = 0)
    {
        if ($value !== false) {
            $value = $this->formatValue($value);
            $this->hSet($cacheKey, $field, $value);
            if ($ex > 0) {
                $this->expire($cacheKey, $ex);
            }
            return true;
        } else {
            $str =  $this->hGet($cacheKey, $field);
            $redData = $this->formatRes($str);
            return $redData;
        }
    }


    public function formatValue($value)
    {
        $value = is_string($value) ? $value : json_encode($value);
        return $value;
    }

    public function formatRes($res)
    {
        $redData = $res !== false ? json_decode($res, true) : false;
        return $redData;
    }
}
