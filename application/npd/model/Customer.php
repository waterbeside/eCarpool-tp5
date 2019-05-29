<?php
namespace app\npd\model;

use think\Db;
use think\Model;
use my\RedisData;

class Customer extends Model
{
  protected $connection = 'database_npd';
  protected $table = 't_customer';
  protected $pk = 'id';
  
  /**
   * 取得列表，如果redis有
   * @param  integer $recache 是否不使用缓存
   */
  public function getList($recache = 0)
  {
    $cacheKey = 'NPD:customer:list';

    $redis = new RedisData();
    $lists = json_decode($redis->get($cacheKey),true);

    if(!$lists || $recache){
      $lists  = $this->where([['is_delete','=',0]])->order(['sort' => 'DESC', 'id' => 'ASC'])->select()->toArray();
      $redis->setex($cacheKey,3600*4,json_encode($lists));
    }
    return $lists;
  }


  public function deleteListCache()
  {
      $redis = new RedisData();
      $redis->delete("NPD:customer:list");
  }
}
