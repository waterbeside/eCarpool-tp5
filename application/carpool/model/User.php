<?php
namespace app\carpool\model;

use app\common\model\Configs;
use app\common\model\BaseModel;
// use think\Model;

class User extends BaseModel
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

   // public $errorMsg = '';

   public function getDetail($account="",$uid=0){
     if(!$account && !$uid){
       return false;
     }
     $carpoolUser_jion =  [
       ['company c','u.company_id = c.company_id','left'],
     ];
     return  $this->alias('u')->join($carpoolUser_jion)->where(['loginname'=>$account])->find();
   }


   public function hashPassword($password,$salt = false){
     if(is_string($salt)){
       return md5(md5($password).$salt);
     }else{
       return md5($password);
     }
   }


   public function createHashPassword($password){
     $salt = getRandomString(6);
     return [
       "hash" => md5(md5($password).$salt),
       "salt" => $salt,
     ];
   }



   /**
    * 取工号数字部分并作为密码
    * @param  [type]  $code [description]
    * @param  integer $hash [description]
    * @return [type]        [description]
    */
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
    * 通过账号密码，取得用户信息
    * @param  string $loginname 用户名
    * @param  string $password  密码
    * @return array||false
    */
   public function checkedPassword($loginname,$password){
     $userData = $this->where([['loginname','=',$loginname],['is_delete','<>',1]])->find();
     if(!$userData){
       $this->errorCode = 10002;
       $this->errorMsg = lang('User does not exist');
       return false;
     }

     if(!$userData['is_active']){
       $this->errorCode = 10003;
       $this->errorMsg = lang('The user is banned');
       return false;
     }
     if(!$userData['md5password']){ // 当md5password字段为空时，使用hr初始密码验证
       $checkPassword = $this->checkInitPwd($loginname,$password);
       if($checkPassword){
         return $userData;
       }else{
         $this->errorCode = 10001;
         // $this->errorMsg = lang('User name or password error');
         return false;
       }
     }

     if(isset($userData['salt']) && $userData['salt']){
       if(strtolower($userData['md5password']) != strtolower($this->hashPassword($password,$userData['salt']))){
         $this->errorCode = 10001;
         $this->errorMsg = lang('User name or password error');
         return false;
       }
     }else if(strtolower($userData['md5password']) != strtolower($this->hashPassword($password))){
       $this->errorCode = 10001;
       $this->errorMsg = lang('User name or password error');
       return false;
     }
     return $userData;

   }


   public function checkInitPwd($loginname,$password){
     $scoreConfigs = (new Configs())->getConfigs("score");
     $url = config("secret.HR_api.checkPwd");
     $token =  $scoreConfigs['score_token'];
     $postData = [
       'code' =>$loginname,
       'pwd' => $password,
     ];
     $scoreAccountRes = $this->clientRequest($url,['form_params'=>$postData],'POST','xml');

     if(!$scoreAccountRes){
       return false;
     }else{
       $bodyObj = new \SimpleXMLElement($scoreAccountRes);
       $bodyString = json_decode(json_encode($bodyObj),true)[0] ;
       if($bodyString!=="OK"){
         $this->errorMsg = $bodyString;
         return false;
       }else{
         return  true;
       }
     }
   }


   /**
    * 从temp拿到数
    * @var [type]
    */
   public function syncDataFromTemp($data){
     $inputUserData = [
       "loginname"=> $data['code'],
       "sex"=> $data['sex'],
       "modifty_time"=> $data['modifty_time'],
       "department_id"=> $data['department_id'],
       'company_id' => isset($data['department_city']) && mb_strtolower($data['department_city']) == "vietnam" ? 11 : 1,
     ];

     if($data['name']){
       $inputUserData['nativename'] = $data['name'];
     }

     if($data['email']){
       $inputUserData['mail'] = $data['email'];
     }

     if(isset($data['department_format']) && $data['department_format']){
       $inputUserData['Department'] = $data['department_format'];
     }
     if(isset($data['department_branch']) && $data['department_branch']){
       $inputUserData['companyname'] = $data['department_branch'];
     }

     //查找用户旧数据
     $oldData = $this->where("loginname",$data['code'])->find();
     if(!$oldData){ // 不存在用户则添加一个
       $pw = $this->createPasswordFromCode($data['code']);
       $inputUserData_default = [
         'indentifier' => uuid_create(),
         "name" => $data['name'],
         'deptid' => $data['code'],
         'route_short_name' => 'XY',
         'md5password' => $pw, //日后将会取消初始密码的写入
         'is_active' => 1,
         // 'is_delete' => 0,
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
