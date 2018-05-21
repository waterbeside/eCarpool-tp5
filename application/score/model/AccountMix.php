<?php
namespace app\score\model;

use think\Model;
use app\carpool\model\User as CarpoolUserModel;

class AccountMix extends Model
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


    /**
     * 通过主键取得积分账号信息
     * @param  int $id
     */
    public function getDetailById($id){
      $accountInfo = $this->getDetail(0,"",$id,1);
      return $accountInfo;

    }

    /**
     * 通过account取得积分账号信息
     * @param  int    $type 帐号名类型
     * @param  string $account 帐号名
     */
    public function getDetailByAccount($type,$account){
      $accountInfo = $this->getDetail($type,$account,NULL,1);
      return $accountInfo;
    }


    /**
     * 通过account检查积分帐号是否存在
     * @param  int    $type 帐号名类型
     * @param  string $account 帐号名
     */
    public function getDetail($type,$account="",$account_id=NULL,$returnAll=1){
      $accountInfo = NULL;
      if( $type=='0' || $type=="score" ){ //直接从积分帐号取
        if($account_id){
          $accountInfo = $this->where(['id'=>$account_id,['is_delete','<>', 1]])->find();
        }else{
          $accountInfo = $this->where(['account'=>$account,['is_delete','<>', 1]])->find();
        }
        if(!$accountInfo){
          return false;
        }
        if($accountInfo['carpool_account'] && $returnAll){
          $accountInfo['carpool'] = $this->getCarpoolAccount($accountInfo['carpool_account']);
        }
      }else if($type=='2'||$type=="carpool"){ //从拼车帐号取
        $accountInfo = $this->where(['carpool_account'=>$account,['is_delete','<>', 1]])->find();
        if($returnAll){
          $accountInfo['carpool'] = $this->getCarpoolAccount($account);
        }
      }
      return $accountInfo;

    }

    //取得拼车帐号
    public function getCarpoolAccount($account){
      $carpool_user_model = new CarpoolUserModel();
      return $carpool_user_model->getDetail($account);
    }



}
