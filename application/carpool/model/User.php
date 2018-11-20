<?php
namespace app\carpool\model;

use think\Model;

class User extends Model
{
    // protected $insert = ['create_time'];

    /**
     * 创建时间
     * @return bool|string
     */
    /*protected function setCreateTimeAttr()
    {
        return date('Y-m-d H:i:s');
    }*/

   protected $table = 'user';
   protected $connection = 'database_carpool';
   protected $pk = 'uid';

   public $errorMsg = '';

   public function getDetail($account="",$uid=0){
     if(!$account && !$uid){
       return false;
     }
     $carpoolUser_jion =  [
       ['company c','u.company_id = c.company_id','left'],
     ];
     return  $this->alias('u')->join($carpoolUser_jion)->where(['loginname'=>$account])->find();
   }


   public function hashPassword($password){
      return md5($password);
   }

   public function createPasswordFromCode($code,$hash=1){
     $n = preg_match('/\d+/', $code ,$arr);
     $pw = $n ? @$arr[0] : $code;
     $pw_len = strlen($pw);
     if($pw_len < 6 ){
       for($i=0; $i < (6-$pw_len);$i++){
         $pw = "0".$pw;
       }
     }
     return $hash ? $this->hashPassword($pw) : $pw;
   }

   /**
    * 从temp拿到数
    * @var [type]
    */
   public function syncDataFromTemp($data){
     $inputUserData = [
       'nativename' => $data['name'],
       "loginname"=> $data['code'],
       "sex"=> $data['sex'],
       "modifty_time"=> $data['modifty_time'],
       "department_id"=> $data['department_id'],
       'company_id' => isset($data['department_city']) && mb_strtolower($data['department_city']) == "vietnam" ? 11 : 1,
       'is_active' => 1,
     ];

     //查找用户旧数据
     $oldData = $this->where("loginname",$data['code'])->find();
     if(!$oldData){ // 不存在用户则添加一个
       $pw = $this->createPasswordFromCode($data['code']);
       $inputUserData_default = [
         'indentifier' => uuid_create(),
         "name" => $data['name'],
         'deptid' => $data['code'],
         'route_short_name' => 'XY',
         'md5password' => $pw,
       ];
       $inputUserData = array_merge($inputUserData_default,$inputUserData);
       $returnId = $this->insertGetId($inputUserData);
       if($returnId){
         $inputUserData['uid'] = $returnId;
         $inputUserData['success'] = 1;
         $this->errorMsg = "user:添加成功。";
         return $inputUserData;
       }else{
         $this->errorMsg = "从临时库入库到正式库时，失败（旧）";
         return false;
       }
     }else{ //存在用户，则更新用户信息
       $inputUserData['uid'] = $oldData["uid"];
       if(strtotime($oldData['modifty_time']) >= strtotime($data['modifty_time'])){
         $this->errorMsg = "用户已存在，并且信息己最新，无须更新（旧）";
         $inputUserData['success'] = -2;
         return $inputUserData;
       }
       $res = $this->where("uid",$oldData["uid"])->update($inputUserData);
       if($res===false){
         $this->errorMsg = "用户已存在，但更新信息时，更新失败（旧）";
         return false;
       }else{
         $inputUserData['success'] = 2;
         $this->errorMsg = "user:更新成功。";
         return $inputUserData;
       }
     }
   }


}
