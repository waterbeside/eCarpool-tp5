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
    protected static $instance;

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


    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static;
        }
        return static::$instance;
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

    /**
     * set前数据处理
     *
     * @param mixed $value
     * @return string
     */
    public function formatValue($value)
    {
        $value = is_string($value) ? $value : json_encode($value);
        return $value;
    }

    /**
     * get后数据处理
     *
     * @param mixed $value
     * @return mixed
     */
    public function formatRes($res)
    {
        if ($res === false) {
            return false;
        }
        try {
            $redData = json_decode($res, true);
        } catch (\Exception $e) {
            $redData = $res;
        }
        return $redData;
    }
    

    /**
     * 加锁
     *
     * @param string $lockKey cache key
     * @param integer $ex key超时时间
     * @param integer $runCount 取锁失败后的取锁循环次数，每次间隔$sleep微秒时间
     * @param integer $sleep  间格时间单位微秒
     * @return boolean
     */
    public function lock($lockKey, $ex = 10, $runCount = 50, $sleep = 20*1000)
    {
        if ($runCount < 1) {
            return false;
        }
        $lock = false;
        $cacheKey = "lock:{$lockKey}";
        // 获取锁
        $now = time();
        $expires = $now + $ex + 1;
        $lock = $this->set($cacheKey, $expires, ['nx', 'ex' => $ex]);
        if (!$lock) {
            // 如果取不到锁
            $lockData = $this->get($cacheKey);
            if ($now > $lockData) { // 检查锁过期没
                $this->unlock($lockKey);
                $lock = $this->lock($lockKey, $ex, $runCount);
            }
        }
        if (!$lock) {
            //休眠10毫秒
            usleep($sleep);
            $runCount = $runCount - 1 ?? 0;
            $lock = $this->lock($lockKey, $ex, $runCount);
        }
        //返回锁
        return $lock;

    }

    /**
     * 释放锁。
     */
    public function unlock($lockKey)
    {
        $cacheKey = "lock:{$lockKey}";
        return $this->del($cacheKey);
    }
}
