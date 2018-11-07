<?php
namespace app\user\model;
use my\RedisData;
// use app\carpool\model\User as OldUserModel;
use app\user\model\UserTest as OldUserModel ;
use app\user\model\User as UserModel;
use app\user\model\UserProfile;
use app\user\model\Department;
use think\Model;
use think\Db;

class UserTemp extends Model
{


  protected $table = 't_user_temp';
  protected $connection = 'database_carpool';
  protected $pk = 'id';

  public $errorMsg = '';



  public function updateStatusAndTime($id,$status=0){
    $updateUserTempData = [
      "status" => $status,
      "sync_time" => date("Y-m-d H:i:s"),
    ];
    $this->where("id",$id)->update($updateUserTempData);
  }

  /**
   * 从HR接口拉取数据
   * @param  string $date 更新日期
   */
  public function pullListFromHr($date = ''){
    $date = $date ? $date : date("Y-m-d");
    $redis = new RedisData();
    $isloadingKey = "carpool:user:sync_hr:isloading";
    $lastDateKey = "carpool:user:sync_hr:lastDate";
    $cacheKey = "carpool:user:sync_hr:$date";
    $isLoading = $redis->get($isloadingKey);
    if($isLoading){
      $this->errorMsg ='后台正在执行同步，请稍候再试';
      return false;
    }
    $lastDate = $redis->get($lastDateKey);
    if($lastDate && strtotime($date)<strtotime($lastDate)){
      $this->errorMsg ='最新同步过的日期大于您设定的日期，无须再同步。';
      return false;
    }
    if($isLoading){
      $this->errorMsg ='后台正在执行同步，请稍候再试';
      return false;
    }
    $cacheData = json_decode($redis->get($cacheKey),true);
    if($cacheData){
      $this->errorMsg ='该日期数据已经同步过';
      return false;
    }
    $redis->setex($isloadingKey,60*10,1);
    $redis->set($lastDateKey,$date);
    $dataArray = $this->clientRequest(config('secret.HR_api.getUserlist'), ['modiftytime' => $date]);
    dump($dataArray);
    if(!$dataArray){
      $this->errorMsg ='无新数据';
      $isLoading = $redis->delete($isloadingKey);
      return false;
    }
    $redis->set($cacheKey,json_encode($dataArray));

    $returnData = [];
    foreach ($dataArray as $key => $value) {
      $data = $this->addFromHr($value);
      if(!$data){
        continue;
      }
      $returnData[$key] = $data;
    }
    $isLoading = $redis->delete($isloadingKey);
    return $returnData;
  }

  /**
   * * 从HR接口拉取单一用户数据
   * @param  string $code 用户工号
   */
  public function pullUserFromHr($code=''){
    if(!$code){
      $this->errorMsg = "empty code";
      return false;
    }
    $dataArray = $this->clientRequest(config('secret.HR_api.getUser'), ['employeecode' => $code]);
    if(!$dataArray){
      $this->errorMsg ='无该员工';
      return 10003;
    }
    $resData = $dataArray[0];
    // dump($resData);exit;
    $data = $this->addFromHr($resData);
    return $data;
  }



  /**
   * 请求数据
   * @param  string $url  请求地址
   * @param  array  $data 请求参数
   * @param  string $type 请求类型
   */
  protected function clientRequest($url,$data=[],$type='POST'){
    try {
      $client = new \GuzzleHttp\Client();
      $response = $client->request($type, $url, ['form_params' => $data]);
      $body = $response->getBody();
      $remainingBytes = $body->getContents();
      $bodyObj = new \SimpleXMLElement($remainingBytes);
      unset($body);
      unset($remainingBytes);
      $bodyString = json_decode(json_encode($bodyObj),true)[0] ;
      unset($bodyObj);
      $dataArray = json_decode($bodyString,true);
      unset($bodyString);
      return $dataArray;
    } catch (Exception $e) {
      $this->errorMsg ='拉取失败';
      return false;
    }
  }

  protected function addFromHr($resData){
    $oldDataMap = [
      ['code',"=",$resData['Code']],
      ["modifty_time",">=",date("Y-m-d H:i:s",strtotime($resData['ModiftyTime']))],
    ];
    $oldData = $this->where($oldDataMap)->find();
    if($oldData){
      $this->errorMsg ='无新数据';
      return false;
    }
    $data = [
      "code" => $resData['Code'],
      "name" => $resData['EmployeeName'],
      "modifty_time" => $resData['ModiftyTime'],
      "department" => $resData['OrgFullName'],
      "sex" => $resData['Sex'],
    ];
    $returnId = $this->insertGetId($data);
    if($returnId){
      $data['id'] = $returnId;
      $data['success'] = 1;
    }else{
      $data['id'] = 0;
      $data['success'] = 0;
    }
    return $data;
  }

  /**
   * 推用户数据到主库比较并更新
   * @param  string $code [description]
   * @param  [type] $data [description]
   * @return [type]       [description]
   */
  public function toPrimary($code='',$data=NULL){
    if(!$data){
      $dataWhere = [
        ['code','=',$code],
        ['status','<',1],
        ['status','>',-2],
      ];
      $data = $this->where($dataWhere)->order('modifty_time desc')->find();
    }
    if(!$data){
      $this->errorMsg = "No data";
      return false;
    }

    //创建及取得部门信息
    $DepartmentModel = new Department();
    $department_data = $DepartmentModel->create_department_by_str($data['department']);
    $data['department_id'] = $department_data['id'];

    $OldUserModel = new OldUserModel();
    $res_old = $OldUserModel->syncDataFromTemp($data); // 入到旧表中

    if($res_old){
      $this->updateStatusAndTime($data["id"],$res_old['success']);
      $returnData = [
        'uid' => $res_old['uid'],
        'code'=> $data['code'],
        // 'uids' => [$res_old['uid'],$res_new['uid']],
      ];
    }else{
      $this->updateStatusAndTime($data["id"],-1);
      $this->errorMsg = $OldUserModel->errorMsg;
      return false;
    }


    /*
    $res_new = [];
    $this->startTrans();
    try{
      $UserModel = new UserModel();
      $res_new = $UserModel->syncDataFromTemp($data); // 入到新user主表中
      // if(!$res_new){
      //   throw new \Exception($UserModel->errorMsg);
      // }
      if($res_new && $res_new['success'] > 0  ){
        $UserProfile = new UserProfile();
        $res_profile = $UserProfile->syncUpdate($res_new); // 入到uerProfile表中
      }
      // if(!$res_profile){
      //   throw new \Exception($UserProfile->errorMsg);
      // }
      $this->commit();
    } catch (\Exception $e) {
      // dump($e->getMessage());
      // $this->errorMsg = $e->getMessage();
      $this->rollback();
    }


    $this->errorMsg = $OldUserModel->errorMsg."; ".$UserModel->errorMsg."; ".(isset($UserProfile) ? $UserProfile->errorMsg : "")."; ";
    if( !$res_old && !$res_new   ){
      $this->updateStatusAndTime($data["id"],-1);
      $this->errorMsg = $OldUserModel->errorMsg."; ".$UserModel->errorMsg."; ";
      return false;
    }

    if( $res_old['success'] == 1 || $res_new['success'] == 1  ){
      $this->updateStatusAndTime($data["id"],1);
    }else if($res_old['success'] == 2 || $res_new['success'] == 2){
      $this->updateStatusAndTime($data["id"],2);
    }else if($res_old['success'] == -2 || $res_new['success'] == -2){
      $this->updateStatusAndTime($data["id"],-2);
    }
    $returnData = [
      'uid' => $res_old['uid'],
      'uids' => [$res_old['uid'],$res_new['uid']],
    ];*/
    return $returnData;

  }

}
