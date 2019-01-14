<?php
namespace app\common\model;

use think\Model;
use my\RedisData;

class Apps extends Model
{
  protected $redisObj = NULL;

  /**
   * 创建redis对像
   * @return redis
   */
  public function redis(){
    if(!$this->redisObj){
        $this->redisObj = new RedisData();
    }
    return $this->redisObj;
  }

  /**
   * 处理cache
   */
  public function itemCache($id,$value = false){
    $cacheKey = "carpool_management:apps:".$id;
    $redis = $this->redis();
    if($value === null){
      return $redis->delete($cacheKey);
    }else if($value){
      return $redis->set($cacheKey,$value);
    }else{
      $str =  $redis->get($cacheKey);
      $redData = $str ? json_decode($str,true) : false;
      return $redData;
    }
  }

}
