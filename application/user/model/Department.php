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



   /**
    * [create_department_by_str 根据部门路径字符串添加部门到数据库，并返回完整id层级数径]
    */
   public function create_department_by_str($department_str,$company_id = 1){
      if(!$department_str){
       return 0;
      }
      //验证该部门是否已存在，是则直接返回；
      $department_str_path = str_replace('/',',',$department_str);
      $checkPathName = $this->where([['fullname','=',$department_str_path]])->find();
      if($checkPathName){
       return $checkPathName;
      }

      $array = explode('/',$department_str);


      // dump($department_str_path);exit;
      $lists = [];
      $ids = [];
      foreach ($array as $key => $value) {
        $data = [
          'name' => $value,
          // 'name_en' => $value,
          'pid' => 0,
          'company_id'=>$company_id,
          'status'=>1,
          'path'=>'0',
          'fullname'=> $value,
        ];
        $data_p = [];
        if($key > 0){
          $data_p = $lists[$key-1];
          $data['pid']  = $data_p['id'];
          $data['path'] = $data_p['path'].','.$data_p['id'];
          $data['fullname'] = $data_p['fullname'].','.$value;
        }
        $check  = $this->where([['name','=',$value],["pid","=",$data['pid']]])->find();
        if(!$check){
          $newId =   $this->insertGetId($data);
          if(!$newId){ return false;}
          $data['id'] = $newId;
        }else{
          $data['id'] = $check['id'];
        }
        $ids[] = $data['id'];
        $lists[$key] = $data;
      }
      return end($lists);
   }


   public function formatFullName($fullNameStr){
     if(!$fullNameStr){
       return false;
     }
     if(is_numeric($fullNameStr)){
       $fullName = $this->where("id",$fullNameStr)->cache(320)->value("fullname");
     }else if(is_string($fullNameStr)){
       $fullName = $fullNameStr;
     }
     if(!isset($fullName) || !$fullName){
       return false;
     }
     $path_list = explode(',',$fullName);
     $departmentName_per = isset($path_list[3]) ?  $path_list[3] : "";
     $departmentName = isset($path_list[4]) ?  $path_list[4] : "";

     $returnData = [
       'branch' => $departmentName_per ,
       'department' => $departmentName,
       // 'formatName' => $departmentName,
       'fullName' => $fullName,
     ];

     if(preg_match('/[\x{4e00}-\x{9fa5}]/u', $departmentName)>0){
       $returnData['formatName'] = $departmentName;
       return $returnData;
     }

     $departmentName_epl_1 = explode(" and ",$departmentName);

     $newName = "";
     foreach ($departmentName_epl_1 as $letters) {
       $departmentName_epl = explode(" ",$letters);
       $newName .= $newName ? ' and ' : '';
       if(count($departmentName_epl)>1 && strlen($departmentName) > 16 ){
         $shortName = "";
         foreach ($departmentName_epl as $key => $value) {
           if(strpos($value,'(') === false && strlen($value) > 2 ){
             $shortName .= strtoupper($value{0});
           }else{
             $shortName .= " ".$value;
           }
         }
         $newName .= $shortName;
       }else{
         $newName .= $letters;
       }

     }
     $returnData['formatName'] = $newName;

     return $returnData;



   }


}
