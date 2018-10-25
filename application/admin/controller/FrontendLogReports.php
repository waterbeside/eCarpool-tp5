<?php
namespace app\admin\controller;

use app\carpool\model\EventLog ;
use app\admin\controller\AdminBase;
use think\facade\Validate;
use think\facade\Config;
use think\Db;
use think\facade\Cache;
/**
 * 公司管理
 * Class Department
 * @package app\admin\controller
 */
class FrontendLogReports extends AdminBase
{

    /**
     * 部门管理
     * @param string $keyword
     * @param int    $page
     * @return mixed
     */
    public function index($filter=[])
    {

      $timeStr = isset($filter['time'])?$filter['time']:0;
      $period = $this->get_period(isset($filter['time'])?$filter['time']:0);
      $filter['time'] = date('Y-m-d',strtotime($period[0]))." ~ ".date('Y-m-d',strtotime($period[1])- 24*60*60);
      return $this->fetch('index',['filter'=>$filter]);
    }

    protected function get_period($timeStr){
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

    /**
     * [geActiveNum description]
     * @return [type] [description]
     */
    public function public_active_num($type="",$timeStr = 0){
      $typeArray = ['carpool','idle','tenement','activity','moment'];
      $period = $this->get_period($timeStr);
      $where_base =  " log_time >=  '".$period[0]."' AND log_time < '".$period[1]."' ";
      $where = "";
      switch ($type) {
        case 'carpool':
          $where = $where_base . " AND ( module_name = 'lovewall' OR  module_name = 'takecar' OR module_name = 'Posttrip') ";
          break;
        case 'idle':
          $where = $where_base . " AND ( module_name = 'content.idle') ";
          break;
        case 'tenement':
          $where = $where_base . " AND ( module_name = 'content.tenement') ";
          break;
        case 'activity':
          $where = $where_base . " AND ( module_name = 'content.activity') ";
          break;
        case 'moment':
          $where = $where_base . " AND ( module_name = 'content.moment') ";
          break;
        default:
          $where =  $where_base;
          break;
      }

      $sql                =  "SELECT count(*) as num FROM event_log WHERE  ".$where;

      $count_android      =  Db::connect('database_carpool')->query($sql." AND carpool_version like 'Android%'");
      $count_ios          =  Db::connect('database_carpool')->query($sql." AND carpool_version like 'iOS%'");
      $returnData         =  [
                              "a"=> isset($count_android[0]['num']) ? $count_android[0]['num'] : 0,
                              "i"=> isset($count_ios[0]['num']) ? $count_ios[0]['num'] : 0,
                            ];
      return $this->jsonReturn(0,$returnData);

    }



      /**
       * 取得周期数
       * @param  String $timeStr 时间范围
       * @param  String $type    [description]
       */
      public function public_cycle_datas($type = false, $timeStr=0, $cycle = false ){
        // $period = $this->get_period($timeStr);
        if(!isset($cycle) || !in_array($cycle,['year','month','week','day'])){
          $this->jsonReturn(-1,'error param');
        }
        if(!$type){
          $this->jsonReturn(-1,'error param');
        }

        $period = $this->get_period($timeStr);
        $now = date('Y-m-d H:i:s',time());

        $where_base =  " log_time >=  '".$period[0]."' AND log_time < '".$period[1]."' ";
        $where = "";
        switch ($type) {
          case 'carpool':
            $where = $where_base . " AND ( module_name = 'lovewall' OR  module_name = 'takecar' OR module_name = 'Posttrip') ";
            break;
          case 'content.idle':
            $where = $where_base . " AND ( module_name = 'content.idle') ";
            break;
          case 'content.tenement':
            $where = $where_base . " AND ( module_name = 'content.tenement') ";
            break;
          case 'content.activity':
            $where = $where_base . " AND ( module_name = 'content.activity') ";
            break;
          case 'content.moment':
            $where = $where_base . " AND ( module_name = 'content.moment') ";
            break;
          default:
            // code...
            break;
        }

        switch ($cycle) {
          case 'year':
            $time = "DATE_FORMAT(`log_time`,'%Y')";
            // $whereTime = " AND log_time < ".$now;
            break;
          case 'month':
            $time = "DATE_FORMAT(`log_time`,'%Y-%m')";
            $whereTime = " AND time < ".$now;
            break;
          case 'week':
            $time = "DATE_FORMAT(`log_time`,'%Y#%U')";
            // $whereTime = " AND time >= ".date("YmdHi",time() - 24*60*60*365*2)." AND time < ".$now;
            break;
          case 'day':
            $time = "DATE_FORMAT(log_time,'%Y-%m-%d')";
            // $whereTime = " AND time >= ".date("YmdHi",time() - 24*60*60*365*3)." AND time < ".$now;
            break;
          default:
            $time  = "YEAR(concat(log_time,'00'))";
            // $whereTime = " AND time < ".$now;
            break;
        }
        /*$cache_key = "carpool_reports_cycle_".$period."_".str_replace("&","and",$type);
        if(cache($cache_key)){
          $lists = cache($cache_key);
          $this->jsonReturn(0,['lists'=>$lists]);
        }*/


        $from = "SELECT  * FROM event_log as l  where  $where ";
        $from_2 = "SELECT
          count(*) as c , $time as t
          FROM ($from) as f
          GROUP BY t
          ORDER BY t DESC
          -- LIMIT 200;
        ";
        $sql = "SELECT *
            FROM ({$from_2}) as f2
            HAVING t IS NOT NULL
            ORDER BY t ASC
        ";


        $lists =  Db::connect('database_carpool')->query($sql);



        $this->jsonReturn(0,['lists'=>$lists]);
      }





}
