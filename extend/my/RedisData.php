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
   * @param integer $ex  有效时长 (秒)
   * @return array 
   */
  public function cache($cacheKey, $value = false, $ex = 0)
  {
    if ($value === null) {
      return $this->delete($cacheKey);
    } else if ($value) {
      $value = json_encode($value);
      if ($ex > 0) {
        return $this->setex($cacheKey, $ex, $value);
      } else {
        return $this->set($cacheKey, $value);
      }
    } else {
      $str =  $this->get($cacheKey);
      $redData = $str ? json_decode($str, true) : false;
      return $redData;
    }
  }
}
