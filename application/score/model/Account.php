<?php
namespace app\score\model;

use think\Db;
use app\common\model\Configs;
use app\common\model\BaseModel;
// use think\Model;

class Account extends BaseModel
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

   protected $pk = 'id';


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
    * 合并积分
    * tAccount  目标账号
    * $oAccount 被删账号
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
