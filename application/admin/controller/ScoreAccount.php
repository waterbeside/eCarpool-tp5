<?php
namespace app\admin\controller;

use app\score\model\Account as ScoreAccountModel;
use app\score\model\AccountMix as AccountMixModel;
use app\carpool\model\User as CarpoolUserModel;
use app\common\controller\AdminBase;
use think\Db;

/**
 * 积分帐号
 * Class Link
 * @package app\admin\controller
 */
class ScoreAccount extends AdminBase
{

    /**
     * 积分帐号
     * @return mixed
     */
    public function index($type='2',$keyword="",$page = 1,$pagesize = 20)
    {
      // $type = strval($type);
      if( $type=='0'|| $type=="score"){  //积分帐号列表
        $map = [];
        $map[] = ['is_delete','<>', 1];
        if ($keyword) {
            $map[] = ['account|carpool_account','like', "%{$keyword}%"];
        }

        $lists = ScoreAccountModel::where($map)->order('id DESC')->paginate($pagesize, false,  ['query'=>request()->param()]);
        foreach ($lists as $key => $value) {
          $carpool = CarpoolUserModel::where(['loginname'=>$value['carpool_account']])->find();
          $lists[$key]['carpoolUserInfo'] = $carpool ? $carpool : ['name'=>'-','phone'=>'-'];
        }

        return $this->fetch('index', ['lists' => $lists, 'keyword' => $keyword,'pagesize'=>$pagesize,'type'=>$type]);

      }elseif($type=='1'||$type=="phone"){//电话

      }elseif($type=='2'||$type=="carpool"){ //拼车帐号列表

        $map = [];
        if ($keyword) {
            $map[] = ['uid|loginname|phone|Department|name|companyname','like', "%{$keyword}%"];
        }
        $join = [
          ['company c','u.company_id = c.company_id','left'],
        ];
        $lists = CarpoolUserModel::alias('u')->join($join)->where($map)->order('uid DESC')->paginate($pagesize, false,  ['query'=>request()->param()]);
        foreach ($lists as $key => $value) {
          $lists[$key]['accountInfo'] = ScoreAccountModel::where(['carpool_account'=>$value['loginname']])->find();
        }
      }

      return $this->fetch('index_carpool', ['lists' => $lists, 'keyword' => $keyword,'pagesize'=>$pagesize,'type'=>$type]);
    }

    /**
     * 取得积分帐号信息
     */
    public function detail($type=2,$account=NULL,$account_id=NULL){

      $accountInfo = null;
      $userInfo = null;
      $accountModel = new AccountMixModel();
      if( ($type=='0' || $type=="score") && $account_id ){ //直接从积分帐号取
        $accountInfo = $accountModel->getDetailById($account_id);
      }else{
        $accountInfo = $accountModel->getDetailByAccount($type,$account);
      }

      if($accountInfo && $accountInfo['carpool']){
        $userInfo = $accountInfo['carpool'];
      }
      if($userInfo){
        $userInfo->avatar = $userInfo->imgpath ? config('app.avatarBasePath').$userInfo->imgpath : config('app.avatarBasePath')."im/default.png";
      }
      if(!isset($accountInfo['id'])){
        $accountInfo = NULL ;
      }

      $returnData = [
        'accountInfo'=>$accountInfo,
        'userInfo'=>$userInfo
      ];
      return $this->fetch('detail', $returnData);

    }

    /**
     * 取得积分帐号信息
     */
    public function public_get_account($type=2){
      /*account = 0,
      phone = 1,
      carpool = 2,
      weixin = 3,
      qq = 4,*/
      $account  = $this->request->get('account');
      if($type=='2'||$type=="carpool"){
        if(!$account){
          $this->jsonReturn(-1,[],'lost account');
        }
        $map = [];
        $map[] = ['is_delete','<>', 1];
        $fieldName = "carpool_account";
        $map[$fieldName] = $account;
        $map["is_delete"]= 0;
        $data = ScoreAccountModel::where($map)->field("id,account,platform,register_date,indentifier,balance")->find();
        $this->jsonReturn(0,$data,'success');
      }
    }

    //取得积分
    public function public_get_balance($type=2){
      if($type=='2'||$type=="carpool"){
        $account  = $this->request->get('account');
        if(!$account){
          $this->jsonReturn(-1,[],'lost account');
        }
        $map = [];
        $map[] = ['is_delete','<>', 1];
        $fieldName = "carpool_account";
        $map[$fieldName] = $account;
        $map["is_delete"]= 0;
        $data = ScoreAccountModel::where($map)->value("balance");
        $this->jsonReturn(0,$data,'success');
      }
    }

}
