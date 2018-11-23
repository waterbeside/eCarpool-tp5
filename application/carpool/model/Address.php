<?php
namespace app\carpool\model;

use think\facade\Cache;
use think\Model;

class Address extends Model
{

  // 设置当前模型对应的完整数据表名称
  protected $table = 'address';

    // 直接使用配置参数名
   protected $connection = 'database_carpool';

   protected $pk = 'addressid';

   protected $insert = [];
   protected $update = [];

   public $errorMsg = "";

   public function addFromTrips($data){
     if(empty($data['longitude']) || empty($data['latitude']) || empty($data['addressname'])){
       $this->errorMsg = lang("Can not be empty");
       return false;
     }
     //如果id为空，通过经纬度查找id.无则创建一个并返回id;
     $data['longtitude'] = $data['longitude'];
     $data['city'] = isset($data['city']) && $data['city'] ? $data['city'] : "-" ;
     $data['address_type'] = 1;
     $createID = $this->insertGetId($startDatas);
     if($createID){
       $createAddress[0] = $startDatas;
       $createAddress[0]['addressid'] = $createID;
       $datas['addressid'] = $createID;
     }else{
       $this->errorMsg = lang("Fail");
       return false;
     }

     unset($data['longtitude']);
     return $data;
   }


}
