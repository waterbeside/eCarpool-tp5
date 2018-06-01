<?php
namespace app\common\model;

use think\Model;

class Configs extends Model
{


  public  function getConfig($name)
  {
      $value = self::where("name",$name)->value('value');
      return $value;
  }

  public  function getConfigs($group=null)
  {
    $where = [];
    if($group){
      $where[]=["group","=",$group];
    }
    $res = self::where($where)->select();
    if(!$res){
      return false;
    }
    $configs = [];
    foreach ($res as $key => $value) {
      $configs[$value['name']] = $value['value'];
    }
    return $configs;
  }

}
