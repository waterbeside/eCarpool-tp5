<?php
namespace app\admin\controller;

use app\score\model\Account as ScoreAccountModel;
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
    public function index($type='0',$keyword="",$page = 1,$pagesize = 20)
    {
      // $type = strval($type);
      if( $type=='0'|| $type=="score"){  //积分帐号列表
        $map = [];
        $map[] = ['is_delete','<>', 1];
        if ($keyword) {
            $map[] = ['account|carpool_account','like', "%{$keyword}%"];
        }

        $lists = ScoreAccountModel::where($map)->order('id DESC')->paginate($pagesize, false,  ['query'=>request()->param()]);

        return $this->fetch('index', ['lists' => $lists, 'keyword' => $keyword,'pagesize'=>$pagesize,'type'=>$type]);

      }elseif($type=='1'||$type=="phone"){//电话

      }elseif($type=='2'||$type=="carpool"){ //拼车帐号列表

        $map = [];
        if ($keyword) {
            $map[] = ['loginname|phone|Department|name|companyname','like', "%{$keyword}%"];
        }
        $join = [
          ['company c','u.company_id = c.company_id','left'],
        ];
        $lists = CarpoolUserModel::alias('u')->join($join)->where($map)->order('uid DESC')->paginate($pagesize, false,  ['query'=>request()->param()]);
      }

      return $this->fetch('index_carpool', ['lists' => $lists, 'keyword' => $keyword,'pagesize'=>$pagesize,'type'=>$type]);
    }

    /**
     * 取得积分帐号信息
     */
    public function public_get_account($type=1){
      /*account = 0,
      phone = 1,
      carpool = 2,
      weixin = 3,
      qq = 4,*/
      if($type=='2'||$type=="carpool"){
        if(!$acc){
          $this->jsonReturn(-1,[],'lost account');
        }
        $map = [];
        $map[] = ['is_delete','<>', 1];
        $fieldName = "carpool_account";
        $map[$fieldName] = $acc;
        $map["is_delete"]= 0;
        $data = ScoreAccountModel::where($map)->field("id,account,platform,register_date,indentifier,balance")->find();
        $this->jsonReturn(0,$data,'success');
      }

    }

    //取得积分
    public function public_get_balance($type=1){
      if($type=='2'||$type=="carpool"){
        $acc  = $this->request->get('acc');
        if(!$acc){
          $this->jsonReturn(-1,[],'lost account');
        }
        $map = [];
        $map[] = ['is_delete','<>', 1];
        $fieldName = "carpool_account";
        $map[$fieldName] = $acc;
        $map["is_delete"]= 0;
        $data = ScoreAccountModel::where($map)->value("balance");
        $this->jsonReturn(0,$data,'success');
      }
    }

}
