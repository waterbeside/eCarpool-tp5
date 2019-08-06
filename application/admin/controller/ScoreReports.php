<?php
namespace app\admin\controller;

use app\carpool\model\User as CarpoolUserModel;
use app\score\model\Account as ScoreAccountModel;



use app\admin\controller\AdminBase;
use think\Db;


/**
 * 积分情况查询
 * Class Link
 * @package app\admin\controller
 */
class ScoreReports extends AdminBase
{
  protected $exclude_scoreAccounnt_id = ['25','96','776','9','12','2'];
  protected $exclude_carpoolAccounnt  = ['GET0285739','GET0296132','GET0294174','GET0296293','GET0295503','GET0296269'];

  /**
   * [index description]
   *
   */
  public function index($filter=[],$region_id=NULL)
  {
    $timeStr = isset($filter['time'])?$filter['time']:0;
    $period = $this->get_period(isset($filter['time'])?$filter['time']:0);
    $filter['time'] = date('Y-m-d',strtotime($period[0]))." ~ ".date('Y-m-d',strtotime($period[1])- 24*60*60);
    return $this->fetch('index',['filter'=>$filter,'region_id'=>$region_id]);
  }

  /**
   * 统计积分数
   */
  public function public_count($timeStr = NULL,$type = 'total' , $is_minus = 0, $uid = 0,$region_id = NULL)
  {
    $period = $this->get_period($timeStr);
    $join = '';
    $where_base = " t.is_delete = 0  AND t.time >=  '".$period[0]."' AND t.time < '".$period[1]."' ";

    // if (is_numeric($rule_number)) {
    //   $where_base .= "rule_number = $rule_number ";
    // }
    if($uid){
      $loginname = CarpoolUserModel::where('uid',$uid)->value('loginname');
      $account_id = ScoreAccountModel::where('carpool_account',$loginname)->value('id');
      $where_base .= " AND (t.account_id = $account_id OR t.carpool_account = $loginname ) ";
    }else{
      $exclude_scoreAccounnt_id_str = implode(",", $this->exclude_scoreAccounnt_id);
      $exclude_carpoolAccounnt_str = '"'.implode("\",\"", $this->exclude_carpoolAccounnt).'"';
      $where_base .= " AND (t.account_id NOT IN($exclude_scoreAccounnt_id_str) OR t.account_id IS NULL ) ";
      $where_base .= " AND (t.carpool_account NOT IN($exclude_carpoolAccounnt_str) OR t.carpool_account IS NULL ) ";
    }
    if($region_id > 0){
      $where_base .= ' AND'. $this->buildRegionMapSql($region_id);
      $join .= " LEFT JOIN carpool.t_department as d ON t.region_id = d.id ";
    }

    
    // $baseMap[] = $is_minus ? ['reason','<',0] : ['reason','>',0] ;
    // "-99"=>"管理员操作",
    // "-100"=>"拼车不合法",
    // "-200"=>"商品消费",
    // "-201"=>"积分抽奖",
    // "-202"=>"实物抽奖",
    // "-300"=>"系统操作",
    // "-301"=>"合并账号",
    // "1"=>"旧积分补入",
    // "99"=>"管理员操作",
    // "100"=>"拼车合法",
    // "101"=>"拼车不合法的强制放行操作",
    // "102"=>"拼车不合法的有限放行操作 (每月10次)",
    // "200"=>"取消商品兑换",
    // "201"=>"积分抽奖",
    // "300"=>"系统操作",
    // "301"=>"合并账号",
    if(is_numeric($type)){
      $where_base .= "reason  ";
    }else{
      $where_base .= $is_minus ? " AND reason < 0 " : " AND reason > 0 ";
      switch ($type) {
        case 'total':
          break;
        case 'carpool':
          $where_base .= $is_minus ? " AND reason = '-100' " : " AND reason >= 100 AND reason <= 102";
          break;
        case 'turnplate':
          $where_base .= $is_minus ? " AND reason = '-201' " : " AND reason = 201 ";
          break;
        case 'lottery':
          $where_base .= $is_minus ? " AND reason = '-202' " : " AND reason = 202";
          break;
        case 'goods':
          $where_base .= $is_minus ? " AND reason = '-200' " : " AND reason = 200 ";
          break;
        default:
          // code...
          break;
      }

    }

    $feilds = 't.id , t.region_id , t.account_id , t.carpool_account , t.time , t.reason , operand';
    $sql_base = "SELECT $feilds  FROM t_history as t $join where $where_base";
    $sql = "SELECT SUM(operand) as total FROM ( $sql_base ) as t";
    // dump($sql_base);exit;

// dump($sql);exit;
    $datas = Db::connect('database_score')->query($sql);
    $returnNum = $datas[0]['total'] ? $datas[0]['total'] : 0 ;
    return $this->jsonReturn(0,['total'=>$returnNum]);

  }



  /**
   * 返回格式化的时间范围数组
   * @param  String 由前端生成的时间范围字符串
   */
  protected function get_period($timeStr)
  {
    $returnData = [];
    //筛选时间
    if(!$timeStr || !is_array(explode(' ~ ',$timeStr))){
      $time_s = date("Y-m-d",strtotime('-1 week last sunday'));
      $time_e = date("Y-m-d",strtotime("$time_s +1 week"));
      $time_e_o = date("Y-m-d",strtotime($time_e)- 24*60*60);
      $timeStr = $time_s." ~ ".$time_e_o;
    }

    $time_arr = explode(' ~ ',$timeStr);
    $time_s = date('Y-m-d H:i:s',strtotime($time_arr[0]));
    $time_e = date('Y-m-d H:i:s',strtotime($time_arr[1]) + 24*60*60);
    $returnData = [$time_s,$time_e];
    return $returnData;
  }








}
