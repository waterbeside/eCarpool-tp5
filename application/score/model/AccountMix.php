<?php
namespace app\score\model;

use app\common\model\Configs;
use app\score\model\Account as AccountModel;
use app\carpool\model\User as CarpoolUserModel;
use app\user\model\Department as DepartmentModel;
use app\score\model\History as HistoryModel;
use think\Db;

class AccountMix extends AccountModel
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

    // 直接使用配置参数名
    protected $connection = 'database_score';
    protected $table = 't_account';
    protected $pk = 'id';
    // public $errorMsg = NULL;


    /**
     * 通过主键取得积分账号信息
     * @param  int $id
     */
    public function getDetailById($id)
    {
      $accountInfo = $this->getDetail(0,"",$id,1);
      return $accountInfo;

    }

    /**
     * 通过account取得积分账号信息
     * @param  int    $type 帐号名类型
     * @param  string $account 帐号名
     */
    public function getDetailByAccount($type,$account)
    {
      $accountInfo = $this->getDetail($type,$account,NULL,1);
      return $accountInfo;
    }


    /**
     * 通过account检查积分帐号是否存在
     * @param  int    $type 帐号名类型
     * @param  string $account 帐号名
     */
    public function getDetail($type,$account="",$account_id=NULL,$returnAll=1)
    {
      $accountInfo = NULL;
      if( $type=='0' || $type=="score" ){ //直接从积分帐号取
        if($account_id){
          $accountInfo = $this->where([['id','=',$account_id],['is_delete','<>', 1]])->find();
        }else{
          $accountInfo = $this->where([['account','=',$account],['is_delete','<>', 1]])->find();
        }
        if(!$accountInfo){
          return false;
        }
        if($accountInfo['carpool_account'] && $returnAll){
          $accountInfo['carpool'] = $this->getCarpoolAccount($accountInfo['carpool_account']);
        }
      }else if($type=='2'||$type=="carpool"){ //从拼车帐号取
        $accountInfo = $this->where([['carpool_account','=',$account],['is_delete','<>', 1]])->find();
        $accountInfo = $accountInfo ? $accountInfo->toArray() :[];
        if($returnAll){
          $accountInfo['carpool'] = $this->getCarpoolAccount($account);
        }
      }
      return $accountInfo;
    }

    /**
     * 取得拼车账号详情
     * @param  string $account carpool loginname
     */
    public function getCarpoolAccount($account)
    {
      $carpool_user_model = new CarpoolUserModel();
      return $carpool_user_model->getDetail($account);
    }



    /**
     * 取得carpool账号的department_id
     * @param  string $account carpool loginname
     */
    public function getCarpoolDepartmentID($account)
    {
      $department_id    = CarpoolUserModel::where([['loginname','=',$account]])->value('department_id');
      $department_id    = $department_id ? $department_id : 0;
      return $department_id;
    }


    /**
     * 更新积分
     * @param  array $params  $params = []
     */
    public function  updateScore($params)
    {
      $account_id   = isset($params['account_id']) ? $params['account_id'] : NULL;
      $account  = NULL;
      $accountField = 'carpool_account';
      $map = [];
      $map[] = ['is_delete','<>', 1];
      if(!$account_id){
        $account      = isset($params['account']) ? $params['account'] : NULL;
        $type         = isset($params['type']) ? $params['type'] : "carpool";
        if($type !="carpool"){
          $accountType = config("score.account_type");
          foreach ($accountType as $key => $value) {
            if( $value['name'] == $type ){
              $accountField = $value['field'];
              break;
            }
          }
        }
        $map[] = [$accountField,'=',$account];
      }else{
        $map[] = ['id','=', $account_id];

      }
      $data['operand']      = $params['operand'];
      $data['reason']       = $params['reason'];
      if(isset($params['region_id']) && $params['region_id']){
        $data['region_id']    = $params['region_id'];
      }
      if(!$account_id && !$account){
        $this->errorMsg = "lost account or account_id";
        return false;
      }

      Db::connect('database_score')->startTrans();
      try{
        //查找是否已开通拼车帐号，拼整理data
        $accountDetial = null ;
        $accountDetial = $this->where($map)->lock(true)->find();

        if($accountDetial && $accountDetial['id']){
          $data['account_id'] = $accountDetial['id'];
          $updateAccountMap = [
            'id'=>$accountDetial['id'],
            'update_time'=>$accountDetial['update_time']
          ];
          if( $data['reason'] > 0 ){
            $data['result']    = intval($accountDetial['balance']) +  $data['operand'];
            $upAccountStatus = $this->where($updateAccountMap)->setInc('balance', $data['operand']);
          }
          if( $data['reason'] < 0 ){
            $data['result']    = intval($accountDetial['balance']) -  $data['operand'];
            $upAccountStatus = $this->where($updateAccountMap)->setDec('balance', $data['operand']);
          }
          if(!$upAccountStatus){
            throw new \Exception("更新分数失败,1");
          }
        }else{
          if($account){
            $data[$accountField] = $account;
            $data['result'] = 0;
          }else{
            throw new \Exception("更新分数失败,2");
          }
        }
        if($accountField == 'carpool_account' && !isset($data['region_id'])){
          $account = $account ? $account : $accountDetial['carpool_account'] ;
          $data['region_id']  =   $this->getCarpoolDepartmentID($account);
        }
        $extra_info = isset($params['extra_info']) ? $params['extra_info'] : '';
        $extra_info = is_array($extra_info) ? json_encode($extra_info) :  $extra_info;
        $data['extra_info'] = empty($extra_info) ? '{}' : $extra_info;
        $data['is_delete'] = 0;
        $data['time'] =  date('Y-m-d H:i:s');
        $historyModel =   new HistoryModel;
        $upHistoryStatus = $historyModel->save($data);
        if(!$upHistoryStatus){
          throw new \Exception("更新分数失败,3");
        }
        // 提交事务
        Db::connect('database_score')->commit();
      } catch (\Exception $e) {
          // 回滚事务
          Db::connect('database_score')->rollback();
          $this->errorMsg = $e->getMessage();;

          // $this->log($logMsg,-1);
          return false;

      }
      // $this->log('改分成功，'.json_encode($this->request->post()),0);
      return true;

  }



     /**
      * 合并积分
      * tAccount  目标账号
      * $oAccount 被删账号
      */
     public function mergeScore($tAccount, $oAccount, $nowTime){
       Db::connect('database_score')->startTrans();
       try {
           $scoreAccount_t = Db::connect('database_score')->table('t_account')->where([['carpool_account','=',$tAccount],['is_delete','<>', 1]])->find();//取出目标员工号的积分账号信息
           $scoreAccount_o = Db::connect('database_score')->table('t_account')->where([['carpool_account','=',$oAccount],['is_delete','<>', 1]])->find();//取出手机号的积分账号信息
           $region_id_t  = $this->getCarpoolDepartmentID($tAccount);
           $region_id_o  = $this->getCarpoolDepartmentID($oAccount);

           if (!$scoreAccount_t) {
               throw new \Exception('10002');
           } else {
             $score_t = $scoreAccount_t['balance'];
             $score_o = $scoreAccount_o['balance'];
             $score_new = $score_t + $score_o; //合并积分
             $historyData = [
               "account_id" => $scoreAccount_t['id'],
               "operand" => $score_o == 0 ? 0 : abs($score_o),
               "reason" => $score_o >= 0 ? 301 : -301,
               "result" => $score_new,
               "extra_info" => '{}',
               "is_delete" => 0,
               "time" => date('Y-m-d H:i:s'),
               "region_id" => $region_id_t,
             ];
             Db::connect('database_score')->table('t_history')->insert($historyData); //插入因合并
             Db::connect('database_score')->table('t_account')->where([['carpool_account','=',$tAccount]])->setField('balance', $score_new); //员工账号加到手号账的积分
             $historyData_o = [
               "account_id" => $scoreAccount_o['id'],
               "operand" => $score_o == 0 ? 0 : abs($score_o),
               "reason" => -301,
               "result" => 0,
               "extra_info" => '{}',
               "is_delete" => 0,
               "time" => date('Y-m-d H:i:s'),
               "region_id" => $region_id_o,
             ];
             Db::connect('database_score')->table('t_history')->insert($historyData_o); //旧账号添加一条扣分的历史
             Db::connect('database_score')->table('t_account')->where([['carpool_account','=',$oAccount]])->update(['carpool_account'=>'delete_'.$oAccount.'_'.$nowTime,'is_delete'=> 1,'balance'=>0]); //删除手机账号的积分账号
               // 提交事务
             Db::connect('database_score')->commit();
           }
       } catch (\Exception $e) {
           // echo($e);
           Db::connect('database_score')->rollback();
           throw new \Exception($e->getMessage());
       }
     }

     /**
      * 注册除分账号
      * $loginname  capool账号的loginname
      */
     public function registerAccount($loginname){
       $scoreConfigs = (new Configs())->getConfigs("score");
       $url = config("secret.inner_api.score.register");
       $token =  $scoreConfigs['score_token'];
       $postData = [
         'account' =>$loginname,
         'token' => $token,
       ];
       $scoreAccountRes = $this->clientRequest($url,['json'=>$postData],'POST');
       if(!$scoreAccountRes){
         return false;
       }else{
         return $scoreAccountRes;
       }
     }


}
