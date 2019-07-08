<?php
namespace app\api\controller\v1\publics;

use app\api\controller\ApiBase;
use app\carpool\service\Trips as TripsService;
use app\carpool\service\TripsReport as TripsReportService;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use my\RedisData;


use think\Db;

/**
 * 报表相关
 * Class Reports
 * @package app\api\controller
 */
class Reports extends ApiBase
{

  protected function initialize()
  {
    parent::initialize();
    // $this->checkPassport(1);
  }

  /**
   *  取得拼车合计数据
   */
  public function trips_summary()
  {
    $cacheKey = "carpool:reports:trips_summary";
    $redis = new RedisData();
    $returnData = $redis->cache($cacheKey);
    if (!$returnData) {
      $sql = "select sum(sumqty) as sumtrip,sum(1) as sumdriver from carpool.reputation";
      $res  =  Db::connect('database_carpool')->query($sql);
      if (!$res) {
        return $this->jsonReturn(20002, 'No data');
      }
      $returnData = $res[0];
      $redis->cache($cacheKey, $returnData, 3600 * 12);
    }
    $this->jsonReturn(0, $returnData, 'success');
  }

  /**
   * 取得每月数据
   */
  public function month_statis()
  {

    $filter['end']      = str_replace('.', '-', input('param.end'));
    $filter['start']    = str_replace('.', '-', input('param.start'));
    $filter['end']      = $filter['end'] ? $filter['end'] : date('Y-m');
    $filter['start']    = $filter['start'] ? $filter['start'] : date('Y-m',strtotime('-11 month',time()));
    $filter['show_type']    = input('param.show_type');
    $filter['department']    = input('param.department');
    $start =  date('Ym010000', strtotime($filter['start'] . '-01'));
    $end =  date('Ym010000', strtotime("+1 month", strtotime(str_replace('.', '-', $filter['end']) . '-01')));

    // $show_type = explode(',', $filter['show_type']);
    $department = explode(',', $filter['department']);

    if(!in_array($filter['show_type'],[1,2,3,4])){
      return $this->jsonReturn(992,[],'Error param');
    }


    $TripsReport = new TripsReportService();
    $TripsService = new TripsService();

    $monthArray = $TripsReport->getMonthArray($filter['start'], $filter['end'],'Y-m'); //取得所有月分作为x轴;
    $monthNum = count($monthArray);

    $listData = [];


    $listData = [];

    $redis = new RedisData();
    foreach ($monthArray as $key => $value) {
   
      foreach($department as $k => $did){
        $paramData = [
          'department'=> $did,
          'show_type'=> $filter['show_type'],
          'month'=> $value,
        ];
        $listData[$value][$did] =  $TripsReport->getMonthSum($paramData) ;
      }
      $listData[$value]['month'] = $value;
    }
    $returnData = [
      'months' => $monthArray,
      'list' => $listData,
    ];
    return $this->jsonReturn(0,$returnData,'successful');

  }
}
