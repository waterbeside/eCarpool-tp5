<?php
namespace app\admin\controller;

use app\carpool\model\User as CarpoolUserModel;
use app\carpool\model\Company as CompanyModel;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\common\controller\AdminBase;
use think\Db;

// 1、上周总拼车人数（用户数量）
// 2、上周总拼车次数
// 3、上周减少碳排放
// 4、高明、新疆、泰州等等各地拼车次数
// 5、上周司机、乘客排行榜

/**
 * 拼车情况查询
 * Class Link
 * @package app\admin\controller
 */
class CarpoolReports extends AdminBase
{
  /**
   * [index description]
   *
   */
  public function index($filter=[])
  {
    $map = [];

    $map[] = ['status','<>', 2];

      // echo date("Y-m-d",strtotime('-1 week last monday'))." 00:00:00";
      // echo date("Y-m-d",strtotime('last sunday'))." 23:59:59";



    $timeStr = isset($filter['time'])?$filter['time']:0;
    $period = $this->get_period(isset($filter['time'])?$filter['time']:0);
    $filter['time'] = date('Y-m-d',strtotime($period[0]))." ~ ".date('Y-m-d',strtotime($period[1])- 24*60*60);
    // $datas = array(
    //   "driver_count"=> $this->public_driver_count($timeStr),
    //   "passenger_count"=> $this->public_passenger_count($timeStr),
    //   "user_count" => $this->public_user_count($timeStr),
    // );
    //
    // $datas['carbon'] = $datas['passenger_count']*7.6*2.3/10;
    // dump($datas);

    // $cacheExpiration = strtotime($value) >= strtotime(date('Y-m',strtotime("now"))) ? 900 : 3600*24*60 ;
    // Yii::app()->cache->set($cacheDatasKey, $listItem ,$cacheExpiration);

    return $this->fetch('index',['filter'=>$filter]);


  }

  /**
   * 计算司机数
   */
  public function public_driver_count($timeStr = 0){
    $period = $this->get_period($timeStr);

    $where_base =  " i.status IN(1,3)  AND carownid IS NOT NULL AND carownid <> '' AND time >=  ".$period[0]." AND time < ".$period[1]." ";


    //从info表取得非空座位的乘搭的司机数
    $from['count_c'] = "SELECT carownid FROM info as i  where  $where_base AND love_wall_ID is Null   GROUP BY carownid  , time";
    $sql['count_c']  = "SELECT  count(*) as c  FROM  (".$from['count_c']." ) as p_info ";
    $datas['count_c'] = Db::connect('database_carpool')->query($sql['count_c']);


    //从love_wall表取得非空座位的乘搭的司机数
    $from['count_c1'] = "SELECT love_wall_ID , (select count(infoid) from info as i where i.love_wall_ID = t.love_wall_ID AND i.status <> 2 ) as pa_num FROM love_wall as t  where  t.status <> 2  AND t.time >=  ".$period[0]." AND t.time < ".$period[1]."   ";
    $sql['count_c1']  = "SELECT  count(*) as c   FROM (".$from['count_c1']." ) as ta   WHERE pa_num > 0   ";
    $datas['count_c1'] = Db::connect('database_carpool')->query($sql['count_c1']);
    return $this->jsonReturn(0,['total'=>$datas['count_c'][0]['c']+$datas['count_c1'][0]['c']]);

  }

  /**
   * 计算乘客数
   */
  public function public_passenger_count($timeStr){
    $period = $this->get_period($timeStr);
    $where_base =  " i.status IN(1,3)  AND carownid IS NOT NULL AND carownid <> '' AND time >=  ".$period[0]." AND time < ".$period[1]." ";
    //取得该月乘客人次
    // $from['count_p'] = "SELECT love_wall_ID FROM info as i  where  $where_base  GROUP BY carownid, passengerid, love_wall_ID, time";
    // $from = "SELECT * FROM info as i  where  i.status <> 2  AND time >=  ".$period[0]." AND time < ".$period[1]." ";
    $from['count_p'] = "SELECT distinct startpid,endpid,time,carownid,passengerid FROM info as i  where  $where_base ";
    $sql['count_p']  = "SELECT  count(*) as c
      FROM
       (".$from['count_p']." ) as p_info
    ";
    $datas['count_p'] =  Db::connect('database_carpool')->query($sql['count_p']);
    return $this->jsonReturn(0,['total'=>$datas['count_p'][0]['c']]);


  }




  /**
   * 计算用户数
   */
  public function public_user_count($timeStr){
    $period = $this->get_period($timeStr);
    $where_base =  " i.status IN(1,3)  AND carownid IS NOT NULL AND carownid <> '' AND time >=  ".$period[0]." AND time < ".$period[1]." ";

    $from_01 = "SELECT distinct startpid,endpid,time,carownid,passengerid FROM info as i  where  $where_base ";
    $from_passenger  = "SELECT  passengerid
      FROM
       (".$from_01." ) as i
      GROUP BY
        passengerid
    ";
    $from_driver  = "SELECT  carownid
      FROM
       (".$from_01." ) as i
      GROUP BY
        carownid
    ";
    $sql_driver = "SELECT count(carownid) as c FROM (".$from_driver." ) as ci ";
    $sql_passenger = "SELECT count(passengerid) as c FROM (".$from_passenger." ) as ci where passengerid not in (".$from_driver.")";
    $res_driver =  Db::connect('database_carpool')->query($sql_driver);
    $res_passenger =  Db::connect('database_carpool')->query($sql_passenger);
    return $this->jsonReturn(0,['total'=>$res_passenger[0]['c'] + $res_driver[0]['c']]);


  }


  /**
   * 各分厂拼车情况
   */
  public function public_subcompany_count($timeStr){
    $period = $this->get_period($timeStr);
    $where_base =  " i.status IN(1,3)  AND carownid IS NOT NULL AND carownid <> '' AND time >=  ".$period[0]." AND time < ".$period[1]." ";



  }


  /**
   * 乘客|司机排行
   */
  public function public_ranking($timeStr = 0,$type){
    $period = $this->get_period($timeStr);
    $where_base =  " i.status IN(1,3)  AND carownid IS NOT NULL AND carownid <> '' AND time >=  ".$period[0]." AND time < ".$period[1]." ";
    $from_01 = "SELECT distinct startpid,endpid,time,carownid,passengerid FROM info as i  where  $where_base ";
    if(!in_array($type,['driver','passenger'])){
      return $this->jsonReturn(-1,'type error');
    }
    if($type=="driver"){
      $fieldname = 'carownid';
    }else if($type=="passenger"){
      $fieldname = 'passengerid';
    }
    $sql_passenger  = "SELECT  {$fieldname} , count({$fieldname}) as c , u.name, u.loginname, u.Department , u.sex, u.phone, c.company_name
      FROM
       (".$from_01." ) as i
      LEFT JOIN user as u ON u.uid = {$fieldname}
      LEFT JOIN company as c ON u.company_id = c.company_id
      GROUP BY
        {$fieldname}
      ORDER BY
        c DESC
    ";
    $res_passenger =  Db::connect('database_carpool')->query($sql_passenger);
    $this->jsonReturn(0,['lists'=>$res_passenger]);
  }





  /**
   * 取得周期数
   * @param  String $timeStr 时间范围
   * @param  String $type    [description]
   */
  public function public_cycle_datas($timeStr=0, $type = false){
    $period = $this->get_period($timeStr);
    if(!isset($type) || !in_array($type,['year','month','week','day'])){
      $this->jsonReturn(-1,'error param');
    }
    switch ($type) {
      case 'year':
        $time = "DATE_FORMAT(concat(`time`,'00'),'%Y')";
        break;
      case 'month':
        $time = "DATE_FORMAT(concat(`time`,'00'),'%Y-%m')";
        break;
      case 'week':
        $time = "DATE_FORMAT(concat(`time`,'00'),'%Y#%U')";
        break;
      case 'day':
        $time = "DATE_FORMAT(concat(`time`,'00'),'%Y-%m-%d')";
        break;
      default:
        $time  = "YEAR(concat(`time`,'00'))";
        break;
    }
    $where_base =  " i.status IN(1,3)  AND carownid IS NOT NULL AND carownid <> '' AND time >=  ".$period[0]." AND time < ".$period[1]." ";
    $from = "SELECT distinct startpid,endpid,time,carownid,passengerid FROM info as i  where  $where_base ";
    $sql = "SELECT
      count(*) as c , $time as t
      FROM ($from) as f
      GROUP BY t
    ";
    // dump($sql);exit;
    $lists =  Db::connect('database_carpool')->query($sql);
    $this->jsonReturn(0,['lists'=>$lists]);

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
    $time_s = date('YmdHi',strtotime($time_arr[0]));
    $time_e = date('YmdHi',strtotime($time_arr[1]) + 24*60*60);
    $returnData = [$time_s,$time_e];
    return $returnData;
  }








}
