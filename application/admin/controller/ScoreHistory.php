<?php
namespace app\admin\controller;

use app\score\model\History as HistoryModel;
use app\carpool\model\User as CarpoolUserModel;
use app\common\controller\AdminBase;
use think\Db;

/**
 * 积分历史
 * Class Link
 * @package app\admin\controller
 */
class ScoreHistory extends AdminBase
{


    /**
     * 积分帐号
     * @return mixed
     */
    public function index($type=1,$account="",$keyword="",$page = 1,$pagesize = 20)
    {

      // dump(config("score.reason"));exit;
      if(!$account){
        $this->jsonReturn(-1,[],'lost account');
      }
      $map = [];
      $map[] = ['is_delete','<>', 1];

      if( $type=='0' || $type=="score" ){
        dump($type);
        $map[] = ['indentifier','=', "$account"];
      }else if($type=='2'||$type=="carpool"){ //拼车帐号列表

        $map[] = ['carpool_account','=', "$account"];
      }
      if ($keyword) {
          $map[] = ['indentifier|carpool_account','like', "%{$keyword}%"];
      }


      $lists = HistoryModel::where($map)->order('time DESC')->paginate($pagesize, false,  ['query'=>request()->param()]);

      return $this->fetch('index', ['lists' => $lists, 'keyword' => $keyword,'pagesize'=>$pagesize,'type'=>$type]);

    }



}
