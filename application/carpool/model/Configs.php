<?php
namespace app\carpool\model;

use think\Model;
use think\facade\Cache;

class Configs extends Model
{

  // 设置当前模型对应的完整数据表名称
  protected $table = 't_configs';


  // 直接使用配置参数名
  protected $connection = 'database_carpool';
  protected $pk = 'id';


  public  function getConfig($name,$isJson = 0)
  {
    
    $value = Cache::get('carpool_configs');
    if($value){
      return $isJson ? json_decode($value,true) : $value;
    }
    $value = self::where("name",$name)->value('value');
    Cache::set('carpool_configs',$value ,3600*24); 
    return $value ? json_decode($value,true) : $value;
  }

  /**
   * 取得缓存列表
   *
   * @param integer $type 类型
   * @param integer $reche 是否重刷缓存
   * @return array 
   */
  public function getList($type=0,$reche = 0){
    $cacheKey = 'Carpool:config:list:type_'.$type;
    $list = Cache::get($cacheKey);
    if(!$list || $reche){
      $res = self::where("type",0)->select();
      if($res){
        $list = [];
        foreach($res as $k => $v){
          $decodeValue = json_decode($v['value']);
          if($decodeValue === null){
            $decodeValue = $v['value'];
          }
          $list[$v['name']] = $decodeValue;
        }
        if(!empty($list)){
          Cache::set($cacheKey,$list ,3600*6); 
        }
      }
    }
    return $list;


  }
  

}
