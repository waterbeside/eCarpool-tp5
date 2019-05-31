<?php
namespace app\carpool\service;

use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\service\Trips as TripsService;
use my\RedisData;

class TripsDetail
{
  /**
   * 取得行情明细
   */
  public function detail($from,$id, $pb){

    $data = null;
    $TripsService = new TripsService();

    // 查缓存
    $redis = new RedisData();
    if($from == 'wall'){
      $cacheKey = "carpool:trips:wall_detail:{$id}";
    }else{
      $cacheKey = "carpool:trips:info_detail:{$id}";
    }

    $cacheExp = 30;
    $cacheData = $redis->get($cacheKey);
    if($cacheData){
      if($cacheData == "-1"){
        return false;
      }
      $data = $cacheData;
    }

    if(!$data || !is_array($data)){
      $TripsService = new TripsService();
      $fields     = $TripsService->buildQueryFields($from.'_detail');
      if($from == 'wall'){
        $join = $TripsService->buildTripJoins("s,e,d,department");
        $data = WallModel::alias('t')->field($fields)->join($join)->where("t.love_wall_ID", $id)->find();
      }
      if($from == 'info'){
        $join = $TripsService->buildTripJoins();
        $data = InfoModel::alias('t')->field($fields)->join($join)->where("t.infoid", $id)->find();
      }
      if (!$data) {
          $redis->setex($cacheKey, $cacheExp, -1);
          return false;
      }
      
      if($from == 'wall'){
        $countBaseMap = ['love_wall_ID','=',$data['love_wall_ID']];
        $data['took_count']       = InfoModel::where([$countBaseMap,["status","in",[0,1,3,4]]])->count(); //取已坐数
        $data['took_count_all']   = InfoModel::where([$countBaseMap,['status','<>',2]])->count() ; //取已坐数
      }
      $redis->setex($cacheKey, $cacheExp, json_encode($data));
    }

    $data = $TripsService->unsetResultValue($TripsService->formatResultValue($data), ($pb ? "detail_pb" : "detail"));

    return $data;
  }

  /**
   * 取得
   */


}