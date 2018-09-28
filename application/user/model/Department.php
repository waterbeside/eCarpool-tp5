<?php
namespace app\user\model;

use think\Model;

class Department extends Model
{

  // 设置当前模型对应的完整数据表名称
  protected $table = 't_department';


    // 直接使用配置参数名
   protected $connection = 'database_carpool';
   protected $pk = 'id';



   public function create_department_by_str($department_str){
      $array = explode('/',$department_str);
      $lists = [];
      foreach ($array as $key => $value) {
        $data = [
          'name' => $value,
          'name_en' => $value,
          'pid' => 0,
          'company_id'=>1,
          'status'=>1,
          'path'=>'0'
        ];
        $data_p = [];
        if($key > 0){
          $data_p = $lists[$key-1];
          $data['pid']  = $data_p['id'];
          $data['path'] = $data_p['path'].','.$data_p['id'];
        }
        $check  = $this->where([['name','=',$value],["pid","=",$data['pid']]])->find();
        if(!$check){
          $newId =   $this->insertGetId($data);
          if(!$newId){ return false;}
          $data['id'] = $newId;
        }else{
          $data['id'] = $check['id'];
        }
        $lists[$key] = $data;
      }
      return true;
   }

   public function get_department_id_by_str($department_str){
      $array = explode('/',$department_str);
      $lists = [];
      foreach ($array as $key => $value) {
        $data = [
          'name' => $value,
          'name_en' => $value,
          'pid' => 0,
          'company_id'=>1,
          'status'=>1,
          'path'=>'0'
        ];
        $data_p = [];
        if($key > 0){
          $data_p = $lists[$key-1];
          $data['pid']  = $data_p['id'];
          $data['path'] = $data_p['path'].','.$data_p['id'];
        }
        $check  = $this->where([['name','=',$value],["pid","=",$data['pid']]])->find();
        if(!$check){
          $newId =   $this->insertGetId($data);
          if(!$newId){ return false;}
          $data['id'] = $newId;
        }else{
          $data['id'] = $check['id'];
        }
        $lists[$key] = $data;
      }
      return true;
   }

}