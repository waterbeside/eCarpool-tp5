<?php
namespace app\user\model;

use think\Model;
use my\RedisData;
use think\Db;

class Department extends Model
{

  // 设置当前模型对应的完整数据表名称
  protected $table = 't_department';


    // 直接使用配置参数名
   protected $connection = 'database_carpool';
   protected $pk = 'id';


   protected $redisObj ;


   /**
    * create_department_by_str 根据部门路径字符串添加部门到数据库，并返回最后部门id]
    * @param  string  $department_str 部门全称
    * @param  integer  $company_id 公司id
    */
   public function create_department_by_str($department_str,$company_id = 1){
      if(!$department_str){
       return 0;
      }
      $rule_number_list = config('score.rule_number');
      //验证该部门是否已存在，是则直接返回；
      $department_str_path = str_replace('/',',',$department_str);
      $checkPathName = $this->where([['fullname','=',$department_str_path]])->find();
      if($checkPathName){
       return $checkPathName;
      }

      $array = explode('/',$department_str);


      $lists = [];
      $ids = [];
      $integral_number = 0;
      foreach ($array as $key => $value) {
        $data = [
          'name' => $value,
          // 'name_en' => $value,
          'pid' => 0,
          'company_id'=>$company_id,
          'status'=>1,
          'path'=>'0',
          'fullname'=> $value,
          // 'integral_number'=> $integral_number,
          'deep'=> $key,
        ];
        $data_p = [];
        if($key > 0){
          $data_p = $lists[$key-1];
          $data['pid']  = $data_p['id'];
          $data['path'] = $data_p['path'].','.$data_p['id'];
          $data['fullname'] = $data_p['fullname'].','.$value;
          // if($key === 1){
          //   foreach ($rule_number_list as $k => $v) {
          //     if(isset($v['region']) && $v['region'] && $v['region'] === $value){
          //       $integral_number = $k;
          //       break;
          //     }
          //   }
          // }
          // $data['integral_number'] = $integral_number;
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

   /**
    * 格式化部门
    * @param  [type]  $fullNameStr [description]
    * @param  integer $type        0：返回数组， 1：返回分厂加公司名(string)，2：返回分厂加公司名缩写(string), 3: 返回分厂
    */
   public function formatFullName($fullNameStr,$type=0){
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
     $region = isset($path_list[1]) ?  $path_list[1] : "";

     $returnData = [
       'region' => $region ,
       'branch' => $departmentName_per ,
       'department' => $departmentName,
       'format_name' => $departmentName_per.','.$departmentName,
       // 'fullname' => $fullName,
     ];
     if($type == 1){
       return $returnData['format_name'];
     }

     if(preg_match('/[\x{4e00}-\x{9fa5}]/u', $departmentName)>0){
       $returnData['short_name'] = $departmentName;
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
     $returnData['short_name'] = $newName;

     if($type == 2){
       return $returnData['branch'].','.$returnData['short_name'];
     }
     if($type == 3){
       return $returnData['branch'];
     }
     return $returnData;

   }


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
   public function itemCache($id,$value = false,$ex = 3600*24,$key='department'){
     $cacheKey = "carpool:".$key.":".$id;
     // if($keyFix){
     //   $cacheKey = $cacheKey.":".$keyFix;
     // }
     $redis = $this->redis();
     if($value === null){
       return $redis->delete($cacheKey);
     }else if($value){
       if(is_array($value)){
         $value = json_encode($value);
       }
       if($ex > 0){
         return $redis->setex($cacheKey,$ex,$value);
       }else{
         return $redis->set($cacheKey,$value);
       }
     }else{
       $str =  $redis->get($cacheKey);
       $redData = $str ? json_decode($str,true) : false;
       return $redData;
     }
   }

   public function itemChildrenCache($id,$value = false,$ex = 3600*24){
     return $this->itemCache($id, $value , $ex,'departmentChildrens');
   }


   /**
    * 取单条数据
    */
   public function getItem($id,$cache_time = 3600*24){
      $data =  $this->itemCache($id);
      $department = $this->itemCache($id);
      if(!$department || !$cache_time){
        $department =  $this->find($id);
        if(!$department){
          return false;
        }
        $department = $department->toArray();
        $this->itemCache($id,$department,$cache_time);
      }
      $department['department_format'] = $this->formatFullName($department['fullname']);
      return $department;
   }

   /**
    * 取子部门id
    * @param  integer  $pid  父ID
    * @param  integer $type 0时，从path字段截取，1时历遍取.
    * @return string        "id,id,id"
    */
   public function getChildrenIds($pid,$cache_time = 3600*24*2){
     $data =  $this->itemChildrenCache($pid);
     $ids = $this->itemChildrenCache($pid);
     if(!$ids || !$cache_time){
       $map = [
          ['','exp', Db::raw("FIND_IN_SET($pid,path)")]
       ];
       $ids =  $this->where($map)->order('id asc')->column('id');
       if(!$ids){
         return false;
       }
       $this->itemChildrenCache($pid,$ids,$cache_time);
     }
     return $ids;
   }
   
   public function excludeChildrens($ids){
     $idsArray = explode(",",$ids);
     $idsArray = array_values(array_unique($idsArray));
     $childrenList = [];
     foreach ($idsArray as $key => $value) {
       $childrens = $this->getChildrenIds($value);
       $childrens = is_array($childrens) && $childrens ? $childrens : [];
       if(in_array($value,$childrenList)){
         unset($idsArray[$key]);
       }
       $childrenList =  $childrens ? array_merge($childrenList,$childrens) : $childrenList;

     }
     foreach ($idsArray as $key => $value) {
       if(in_array($value,$childrenList)){
         unset($idsArray[$key]);
       }
     }
     return $idsArray;
   }

   /**  
    * 通过id 列表查找部门数据
    */
   public function getDeptDataIdList($ids,$field = null){
      $deptsArray = explode(',',$ids);
      $deptsData = [];
      foreach ($deptsArray as $key => $value) {
        $deptsItemData = $this->getItem($value);
        // field('id , path, fullname , name')->find($value);
        if($field){
          $fieldArray = explode(',',$field);
          $deptsItemData_n = [];
          foreach($fieldArray as  $f){
             $deptsItemData_n[$f] = isset($deptsItemData[$f]) ? $deptsItemData[$f] : null;
          }
          $deptsItemData  = $deptsItemData_n;
        }
        $deptsData[$value] = $deptsItemData ? $deptsItemData : [];
      }
      return $deptsData;
   }

   public function getDeptDataList($ids,$field = null){
    $deptsArray = explode(',',$ids);
    $deptsData = [];
    foreach ($deptsArray as $key => $value) {
      $deptsItemData = $this->getItem($value);
      // field('id , path, fullname , name')->find($value);
      if($field){
        $fieldArray = explode(',',$field);
        $deptsItemData_n = [];
        foreach($fieldArray as  $f){
           $deptsItemData_n[$f] = isset($deptsItemData[$f]) ? $deptsItemData[$f] : null;
        }
        $deptsItemData  = $deptsItemData_n;
      }
      $deptsData[] = $deptsItemData ? $deptsItemData : [];
    }
    return $deptsData;
 }



}
