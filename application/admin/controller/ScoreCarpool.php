<?php
namespace app\admin\controller;

use app\score\model\Account as ScoreAccountModel;
use app\carpool\model\User as CarpoolUserModel;
use app\carpool\model\WaitingProcess;
use app\carpool\model\Info as InfoModel;
use app\common\controller\AdminBase;
use think\Db;

/**
 * 无效拼车处理
 * Class Link
 * @package app\admin\controller
 */
class ScoreCarpool extends AdminBase
{

  /**
   * 失败拼车管理
   * @param string $keyword
   * @param int    $page
   * @return mixed
   */
  public function fails($keyword = '', $page = 1,$pagesize = 30)
  {
      $isvalid   = input('param.isvalid/s',"");

      $map = [];
      if ($keyword) {
          $map[] = ['t.infoid|t.code|t.result','like', "%{$keyword}%"];
      }
      if($isvalid!==""){
        $map[] = ['t.isvalid','=',$isvalid];

      }

      $subJoin = [
        ['user d','i.carownid = d.uid','left'],
        ['user p','i.passengerid = p.uid','left'],
        ['address s','i.startpid = s.addressid','left'],
        ['address e','i.endpid = e.addressid','left'],
      ];
      $subField = "infoid,i.time as info_time ,i.status as info_status, startpid ,endpid,carownid,passengerid,
                    d.loginname as d_loginname , d.name as d_name , d.phone as d_phone,
                    p.loginname as p_loginname , p.name as p_name , p.phone as p_phone,
                    s.addressname as s_addressname , s.latitude as s_latitude , s.longtitude as s_longtitude ,
                    e.addressname as e_addressname ,  e.latitude as e_latitude , e.longtitude as e_longtitude
                    ";
      $subSql = InfoModel::alias('i')
        ->field($subField)
        ->join($subJoin)
        ->buildSql();


        $lists = WaitingProcess::alias('t')->join([$subSql=> 'ii'], 't.infoid = ii.infoid','left')->where($map)->order('wpid DESC')->paginate($pagesize, false,  ['query'=>request()->param()]);
      // $lists = WaitingProcess::alias('w')->join([$subSql=> 'ii'], '.w.infoid = ii.infoid','left')->where($map)->order('wpid DESC')->fetchSql()->select();

      return $this->fetch('fails', ['lists' => $lists, 'keyword' => $keyword,'pagesize'=>$pagesize,'isvalid'=>$isvalid]);
  }


  public function fail_operate(){
    

  }


}
