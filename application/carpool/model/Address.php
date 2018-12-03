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
     //先查找有没有对应的地址
     $findMap = [
       'addressname'=>$data['addressname'],
       'latitude'=>$data['latitude'],
       'longtitude'=>$data['longitude'],
     ];
     $res = $this
     ->field("address_type,addressname,longtitude as longitude,latitude,create_time,company_id,city")
     ->where($findMap)->find();

     if($res){
       $data = array_merge($data,$res->toArray());
       return $data;
     }
     //如果没有数据，则创建
     $inputData = [
       'address_type' => 1,
       'addressname' => $data['addressname'],
       'longtitude' => $data['longitude'],
       'latitude'   => $data['latitude'],
       'create_time'   => date("Y-m-d H:i:s"),
       'company_id'   => $data['company_id'],
       'city'       => isset($data['city']) && $data['city'] ? $data['city'] : "--",
     ];
     $createID = $this->insertGetId($inputData);
     if($createID){
       $data['addressid'] = intval($createID);
       $data = array_merge($data,$inputData);
     }else{
       $this->errorMsg = lang("Fail");
       return false;
     }
     unset($data['longtitude']);
     return $data;
   }


}
