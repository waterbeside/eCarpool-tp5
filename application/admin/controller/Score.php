<?php
namespace app\admin\controller;

use app\score\model\History as HistoryModel;
use app\score\model\AccountMix as AccountMixModel;
use app\score\model\Account as ScoreAccountModel;

use app\common\controller\AdminBase;
use think\Db;

/**
 * 积分操作
 * Class Link
 * @package app\admin\controller
 */
class Score extends AdminBase
{

  /**
   * 变更积分
   * @return mixed
   */
  public function change()
  {
    if ($this->request->isPost()) {
      $datas         = $this->request->post('');

      if(!$datas['operand'] || !$datas['reason']){
        $this->jsonReturn(-1,'参数错误');
      }else{
        $datas['operand'] = abs(intval($datas['operand']));
      }
      //根据accountType查出account字段
      if($this->updateScore($datas)){
        $this->jsonReturn(0,'改分成功');
      }else{
        $this->jsonReturn(-1,'改分失败');
      };

    }else{
      $type         = input('param.type/s');
      $account      = input('param.account/s');
      $account_id   = input('param.account_id/d',0);
      $accountModel = new AccountMixModel();
      if( ($type=='0' || $type=="score") && $account_id ){ //直接从积分帐号取
        $accountInfo = $accountModel->getDetailById($account_id);
      }else{
        $accountInfo = $accountModel->getDetailByAccount($type,$account);
      }

      $reasons = config('score.reason');
      $reasons_operable = config('score.reason_operable');
      $reasonsArray=[];
      foreach ($reasons as $key => $value) {
        if(in_array($key,$reasons_operable)){
          $reasonsArray[] = ['code'=>$key,'title'=>$value];
        }
      }
      
      $returnData = [
        'accountInfo'=>$accountInfo,
        'reasons'=>$reasons,
        'reasonsArray'=>$reasonsArray,
        'type'=>$type,
        'account'=>$account,
        'account_id'=>$account_id,
      ];
      return $this->fetch('change',$returnData);
    }

  }


  public function updateScore($datas){

    $type         = $datas['type'];
    $account_id   = $datas['account_id'];
    $account      = $datas['account'];
    $isadd        = $datas['isadd'];

    $data['operand']      = $datas['operand'];
    $data['reason']       = $datas['reason'];

    $accountType = config("score.accountType");
    $accountField = '';
    foreach ($accountType as $key => $value) {

      if( (is_numeric($type) && $key ==  $type ) || $value['name'] == $type ){
        $accountField = $value['field'];
        break;
      }
    }

    if(!$accountField && !$account_id){
      $this->jsonReturn(-1,'参数错误');
      return false;
    }

    try{
      //查找是否已开通拼车帐号，拼整理data
      $accountDetial = null ;
      if( ($type=='0' || $type=="score")  &&  $account_id ){ //直接从积分帐号取
        $accountDetial = ScoreAccountModel::where(['id'=>$account_id,['is_delete','<>', 1]])->lock(true)->find();
      }elseif($accountField){
        $accountDetial = ScoreAccountModel::where([$accountField=>$account,['is_delete','<>', 1]])->lock(true)->find();
      }
      if($accountDetial && $accountDetial['id']){
        $data['account_id'] = $accountDetial['id'];
        $updateAccountMap = [
          'id'=>$accountDetial['id'],
          'update_time'=>$accountDetial['update_time']
        ];
        if($isadd && $data['reason'] > 0 ){
          $data['result']    = intval($accountDetial['balance']) +  $data['operand'];
          $upAccountStatus = ScoreAccountModel::where($updateAccountMap)->setInc('balance', $data['operand']);
        }
        if(!$isadd && $data['reason'] < 0 ){
          $data['result']    = intval($accountDetial['balance']) -  $data['operand'];
          $upAccountStatus = ScoreAccountModel::where($updateAccountMap)->setDec('balance', $data['operand']);
        }
        if(!$upAccountStatus){
          throw new \Exception("更新分数失败");
        }
      }else{
        $data[$accountField] = $account;
        $data['result'] = 0;
      }
      $data['extra_info'] = '{}';
      $data['is_delete'] = 0;
      $data['time'] =  date('Y-m-d H:i:s');
      $historyModel =   new HistoryModel;
      $upHistoryStatus = $historyModel->save($data);
      if(!$upHistoryStatus){
        throw new \Exception("更新分数失败");
      }
      // 提交事务
      Db::commit();
    } catch (\Exception $e) {
        // 回滚事务
        Db::rollback();
        $logMsg = '改分失败，请稍候再试'.json_encode($this->request->post());
        $this->log($logMsg,-1);
        return false;

    }
    $this->log('改分成功，'.json_encode($this->request->post()),0);
    return true;

  }


}
