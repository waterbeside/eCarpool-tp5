<?php
namespace app\admin\controller;

use app\score\model\History as HistoryModel;
use app\score\model\Account as ScoreAccountModel;
use app\carpool\model\User as CarpoolUserModel;
use app\admin\controller\AdminBase;
use think\Db;

/**
 * 积分历史
 * Class Link
 * @package app\admin\controller
 */
class ScoreHistory extends AdminBase
{


    /**
     * 积分历史（所有用户）
     * @return mixed
     */
    public function index($filter=NULL,$page = 1,$pagesize = 20,$rule_number=NULL)
    {
        $map = [];
        $map[] = ['is_delete','<>', 1];
        //地区区分
        if (is_numeric($rule_number)) {
          $map[] = ['rule_number','=', $rule_number];
        }
        // dump($filter);
        if($filter['reason']){
          $map[] = ['reason','=', $filter['reason']];
        }
        if($filter['time']){
          $time_arr = explode(' ~ ',$filter['time']);
          if(is_array($time_arr)){
            $time_s = date('Y-m-d H:i:s',strtotime($time_arr[0]));
            $time_e = date('Y-m-d H:i:s',strtotime($time_arr[1]) + 24*60*60);
            $map[] = ['time', 'between time', [$time_s, $time_e]];
          }
        }

        $lists = HistoryModel::where($map)->order('time DESC, id DESC')->paginate($pagesize, false,  ['query'=>request()->param()]);
        foreach ($lists as $key => $value) {
          if($value['account_id']){
            $userInfo = ScoreAccountModel::where(['id'=>$value['account_id']])->find();
            $lists[$key]['account'] = $userInfo['carpool_account'] ? $userInfo['carpool_account'] : ($userInfo['phone'] ? $userInfo['carpool_account'] : $userInfo['identifier']);
          }else if($value['carpool_account']){
            $lists[$key]['account'] = $value['carpool_account'];
          }else{
            $lists[$key]['account'] = '-';

          }
        }

        $reasons = config('score.reason');

        $returnData = [
          'rule_number' => $rule_number,
          'lists' => $lists,
          'filter' => $filter,
          'pagesize'=>$pagesize,
          'reasons'=>$reasons
        ];
        return $this->fetch('index', $returnData);

    }

    /**
     * 积分历史（指定用户）
     * @return mixed
     */
    public function lists($type=1,$account=NULL,$account_id=NULL,$filter=NULL,$page = 1,$pagesize = 20,$rule_number=NULL)
    {
      $map = [];
      $map[] = ['is_delete','<>', 1];
      if (is_numeric($rule_number)) {
          $map[] = ['rule_number','=', $rule_number];
      }
      // dump($filter);
      if($filter['reason']){
        $map[] = ['reason','=', $filter['reason']];
      }
      if($filter['time']){
        $time_arr = explode(' ~ ',$filter['time']);
        if(is_array($time_arr)){
          $time_s = date('Y-m-d H:i:s',strtotime($time_arr[0]));
          $time_e = date('Y-m-d H:i:s',strtotime($time_arr[1]) + 24*60*60);
          $map[] = ['time', 'between time', [$time_s, $time_e]];
        }
      }

      if( $type=='0' || $type=="score" ){ //直接从积分帐号取
        if(!$account&&!$account_id){ //当account为空时，读取全部
          $this->error("Lost account or account_id");
        }
        if($account_id){
          $accountInfo = ScoreAccountModel::where(['id'=>$account_id])->find();
        }else{
          $accountInfo = ScoreAccountModel::where(['account'=>$account])->find();
        }

        if(!$accountInfo){
          $this->error("账号不存在");
        }
        if($accountInfo['carpool_account']){
          $userInfo = CarpoolUserModel::where(['loginname'=>$account])->find();
        }
        $map[] = ['account_id','=', $accountInfo['id']];
      }else if($type=='2'||$type=="carpool"){ //从拼车帐号取
        if(!$account){ //当account为空时，读取全部
          $this->error("Lost account");
        }
        $accountInfo = ScoreAccountModel::where(['carpool_account'=>$account])->find();
        $userInfo = CarpoolUserModel::where(['loginname'=>$account])->find();
        if($accountInfo){
          $map[] = ['account_id','=', $accountInfo['id']];
        }else{
          $map[] = ['carpool_account','=', $account];
        }
      }

      $lists = HistoryModel::where($map)->order('time DESC, id DESC')->paginate($pagesize, false,  ['query'=>request()->param()]);

      $auth['admin/ScoreHistory/edit'] = $this->checkActionAuth('admin/ScoreHistory/edit');
      $auth['admin/ScoreHistory/delete'] = $this->checkActionAuth('admin/ScoreHistory/delete');
      $reasons = config('score.reason');

      $returnData = [
        'rule_number' => $rule_number,
        'lists' => $lists,
        'filter' => $filter,
        'pagesize'=>$pagesize,
        'type'=>$type,
        'auth'=>$auth,
        'account'=>$account,
        'account_id'=>$account_id,
        'reasons'=>$reasons
      ];
      return $this->fetch('lists', $returnData);

    }






}
