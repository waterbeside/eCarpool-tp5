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
    dump($value);
    Cache::set('carpool_configs',$value ,3600*24); 
    return $value ? json_decode($value,true) : $value;
  }

  

}
