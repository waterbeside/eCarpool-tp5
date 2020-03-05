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
     * @param string $cacheKey Cache key
     * @param string $rowKey Row key
     * @param mixed $value 插入期
     * @param integer $ex 过期时间
     * @param integer $exType 0:参数$ex为基于key的过期时间，其它:$ex为行的过期时间(当$exType>0时，$exType为key的过期时间，当为-1时，key不过期，当为-2时，不设置key的过期时间
     * @return void
     */
    public function hCache($cacheKey, $rowKey, $value = false, $ex = 0, $exType = -2)
    {
        $now = time();
        $rowExpKey = "_Expire:$rowKey";
        if ($value !== false) {
            $value = $this->formatValue($value);
            try {
                $this->multi();
                $this->hSet($cacheKey, $rowKey, $value);
                if ($ex > 0) {
                    if ($exType) {
                        $expTime = $now + $ex;
                        $this->hSet($cacheKey, $rowExpKey, $expTime);
                    } else {
                        $this->expire($cacheKey, $ex);
                    }
                }
                $this->exec();
            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
                return false;
            }
            return true;
        } else {
            try {
                $this->multi();
                $this->hGet($cacheKey, $rowKey);
                $this->hGet($cacheKey, $rowExpKey);
                $res = $this->exec();
            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
                return false;
            }
            $resValue = $res[0];
            $strExtime = $res[1];
            $redData = $this->formatRes($resValue);
            if (($strExtime || $ex > 0) && $strExtime < $now) {
                $this->hDel($cacheKey, $rowKey, $rowExpKey);
                return false;
            }
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

    /**
     * 通过lng和lat计算geo hash
     *
     * @param mixed $lng 当为数组时则以[lng,lat]格式传经纬度，否则放经度数据
     * @param mixed $lat 纬度
     * @return string 返回geohash
     */
    public function getGeohash($lng, $lat = null)
    {
        if (is_array($lng)) {
            $lnglat = $lng;
            $lng = $lnglat[0];
            $lat = $lnglat[1] ?? $lat;
        } else {
            $lnglat = [$lng, $lat];
        }
        if (empty($lng) || empty($lat)) {
            return false;
        }
        $time = time();
        $cacheKey = "temp:geo:getGeohash:$lng,$lat,$time";
        $this->geoadd($cacheKey, $lng, $lat, $time);
        $this->expire($cacheKey, 30);
        $res = $this->geohash($cacheKey, $time);
        return $res[0] ?? false;
    }

    /**
     * 通过经续度查询直线距离
     *
     * @param mixed $lng1 start_lng or [start_lng, start_lat]
     * @param mixed $lat1 start_lat or [end_lng, end_lat]
     * @param float $lng2 end_lng or null
     * @param float $lat2 end_lat or null
     * @return void
     */
    public function getDistance($lng1, $lat1, $lng2 = null, $lat2 = null, $u = 'm')
    {
        if (is_array($lng1) && is_array($lat1)) {
            if (count($lng1) < 2 || count($lat1) < 2) {
                return false;
            }
            $u = !empty($lng2) && is_string($lng2) ? $lng2 : $u;
            $start = $lng1;
            $end = $lat1;
            $lng1 = $start[0];
            $lat1 = $start[1];
            $lng2 = $end[0];
            $lat2 = $end[1];
        }
        $cacheKey = "temp:geo:getDistance:$lng1,$lat1,$lng2,$lat2";
        $this->geoadd($cacheKey, $lng1, $lat1, 'p1', $lng2, $lat2, 'p2');
        $this->expire($cacheKey, 30);
        $res = $this->geodist($cacheKey, 'p1', 'p2', $u);
        return $res;
    }

    /**
     * 从列表中找出指定距离范围内的数据
     *
     * @param array $list 列表数据
     * @param array $lnglat [lng,lat]格式传经纬度
     * @param array $lnglat [lng,lat]经纬度字段名
     * @return void
     */
    public function getRadius($list, $lnglat, $radius = 1000, $u = 'm', $fieldname = ['longitude', 'latitude'], $opt = ['WITHDIST', 'ASC'])
    {
        $time = microtime();
        $rd = $this->getRandomString(6, 0);
        $cacheKey = "temp:geo:getRadius:{$rd}_{$time}";
        foreach ($list as $key => $value) {
            $lngField = $fieldname[0] ?? 'longitude';
            $latField = $fieldname[1] ?? 'latitude';
            $lngValue = $value[$lngField] ?? 0;
            $latValue = $value[$latField] ?? 0;
            $this->geoadd($cacheKey, $lngValue, $latValue, $key);
        }
        $lng = $lnglat[0] ?? 0;
        $lat = $lnglat[1] ?? 0;
        $u = $u ?: 'm';
        $res = $this->georadius($cacheKey, $lng, $lat, $radius, $u, $opt);
        if ($res) {
            $newList = [];
            foreach ($res as $key => $value) {
                $oListKey = $value[0];
                if (isset($list[$oListKey])) {
                    $list[$oListKey]['_radius'] = $value;
                    $newList[] = $list[$oListKey];
                }
            }
            $list = $newList;
        }
        $this->expire($cacheKey, 30);
        // $this->del($cacheKey);
        return $list;

    }

    /**
     * 产生随机字符串
     * 产生一个指定长度的随机字符串,并返回给用户
     * @access public
     * @param int $len 产生字符串的位数
     * @param int $type 0: 生成数字或字母，1：只生成数字
     * @return string
     */
    public function getRandomString($len = 6, $type = 0)
    {
        $chars =  $type ? array(
            "0", "1", "2", "3", "4", "5", "6", "7", "8", "9"
        ) : array(
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
            "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
            "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
            "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
            "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
            "3", "4", "5", "6", "7", "8", "9"
        );
        $charsLen = count($chars) - 1;
        shuffle($chars);    // 将数组打乱
        $output = "";
        for ($i = 0; $i < $len; $i++) {
            $output .= $chars[mt_rand(0, $charsLen)];
        }
        return $output;
    }
}
