<?php
namespace app\carpool\service;

use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\model\Grade as GradeModel;
use app\carpool\service\Trips as TripsService;
use think\Db;

class TripsList
{
  /**
   * 我的行程列表
   */
  public function myList($userData,$pagesize=20,$type = 0,$fullData = 0){

    $TripsService = new TripsService();
    $uid = $userData['uid'];
    $viewSql    = $TripsService->buildUnionSql($userData ,($type ? "(0,1,3,4)" : "(0,1,4)"),$type);
    $fields     = $TripsService->buildQueryFields('all');
    $join       = $TripsService->buildTripJoins();
    $orderby    = $type ? 't.time DESC, t.infoid DESC, t.love_wall_id DESC' : 't.time ASC, t.infoid ASC, t.love_wall_id ASC';

    if ($type==1) {
      $map = [
        ["t.time","<",date('YmdHi', strtotime('+15 minute'))],
        // ["t.go_time","<",strtotime('+15 minute')],
      ];
      $orderby = 't.time DESC, t.infoid DESC, t.love_wall_id DESC';
    } else {
        $map = [
          ["t.time",">",date('YmdHi', strtotime("-1 hour"))],
          // ["t.go_time",">",strtotime("-1 hour")],
        ];
        $orderby = 't.time ASC, t.infoid ASC, t.love_wall_id ASC';
        //如果$type !=1 则查缓存
    }

    $modelObj =  Db::connect('database_carpool')->table("($viewSql)" . ' t')->field($fields)->where($map)->join($join)->order($orderby);

    if ($pagesize > 0 || $type > 0) {
        $results =    $modelObj->paginate($pagesize, false, ['query'=>request()->param()])->toArray();
        if (!$results['data']) {
          return false;
        }

        $datas = $results['data'];
        $pageData = $this->getPageData($results);

    } else {
        $datas =    $modelObj->select();
        if (!$datas) {
          return false;
        }
        $total = count($datas);
        $pageData = [
          'total'=>$total,
          'pageSize'=>0,
          'lastPage'=>$total,
          'currentPage'=>1,
        ];
    }

    foreach ($datas as $key => $value) {
        $datas[$key] = $fullData ? $TripsService->formatResultValue($value) : $TripsService->unsetResultValue($TripsService->formatResultValue($value), "list");
        // $datas[$key] = $TripsService->formatResultValue($value,$merge_ids);
        $datas[$key]['show_owner']  = $value['trip_type'] ||  ($value['infoid']>0 && $uid == $value['passengerid']  &&  $value['carownid'] > 0)  ?  1 : 0;
        $datas[$key]['is_driver']   =  $uid == $value['carownid']  ?  1 : 0;
        $datas[$key]['status'] = intval($value['status']);
        $datas[$key]['took_count']  = $value['infoid'] > 0 ? ($datas[$key]['is_driver'] ? 1 : 0) : InfoModel::where([['love_wall_ID','=',$value['love_wall_ID']],['status','<>',2]])->count() ; //取已坐数
    }
    $returnData = [
      'lists'=>$datas,
      'page' =>$pageData
    ];
    return $returnData;

  }

  /**
   * 历史行程
   */
  public function history($userData,$pagesize =20){
    $uid = $userData['uid'];
    $TripsService = new TripsService();
    $viewSql    = $TripsService->buildUnionSql($userData ,"(0,1,2,3,4)", 1);

    $fields = $TripsService->buildQueryFields('all');
    $join   = $TripsService->buildTripJoins();
    $map    = [
      ["t.time","<",date('YmdHi', strtotime('-30 minute'))],
      // ["t.go_time","<",strtotime('+15 minute')],
    ];
    $orderby  = 't.time DESC, t.infoid DESC, t.love_wall_id DESC';
    $modelObj =  Db::connect('database_carpool')->table("($viewSql)" . ' t')->field($fields)->where($map)->join($join)->order($orderby);
    $results  =    $modelObj->paginate($pagesize, false, ['query'=>request()->param()])->toArray();
    if (!$results['data']) {
      return false;
    }
    
    $datas = $results['data'];
    $pageData = $this->getPageData($results);

    $GradeModel =  new GradeModel();
    foreach ($datas as $key => $value) {
        $app_id  = $value['map_type'] ? 2 : 1 ;
        $datas[$key] =  $TripsService->formatResultValue($value);
        // $datas[$key] = $TripsService->formatResultValue($value,$merge_ids);
        $datas[$key]['show_owner']    = $value['trip_type'] ||  ($value['infoid']>0 && $uid == $value['passengerid']  &&  $value['carownid'] > 0)  ?  1 : 0;
        $datas[$key]['is_driver']     =  $uid == $value['carownid']  ?  1 : 0;
        $datas[$key]['status']        = intval($value['status']);
        $datas[$key]['took_count']    = $value['infoid'] > 0 ? ($datas[$key]['is_driver'] ? 1 : 0) : InfoModel::where([['love_wall_ID','=',$value['love_wall_ID']],['status','<>',2]])->count() ; //取已坐数

        $grade_type = $datas[$key]['is_driver'] && $value['love_wall_ID'] > 0 ? 1 : 0;
        $ratedMap = [
          ['type','=',$grade_type],
          ['object_id','=',($grade_type ? $value['love_wall_ID'] : $value['infoid'])],
          ['uid','=',$uid]
        ];
        $isGrade = $GradeModel->isGrade('trips',$app_id,$datas[$key]['time']);
        $datas[$key]['already_rated'] =    !$isGrade  || $value['status']==2 || $GradeModel->where($ratedMap)->count() ? 1 : 0;
    }
    $returnData = [
      'lists'=>$datas,
      'page' =>$pageData
    ];
    return $returnData;
  }


  /**
   * 取得空座位列表
   */
  public function wall_list($map,$pagesize=20)
  {
    $TripsService = new TripsService();

    $fields = $TripsService->buildQueryFields('wall_list');

    $join = $TripsService->buildTripJoins("s,e,d,department");

    $results = WallModel::alias('t')->field($fields)->join($join)->where($map)->order(' time ASC, t.love_wall_ID ASC  ')
    ->paginate($pagesize, false, ['query'=>request()->param()])->toArray();
    if (!$results['data']) {
        return false;
    }
    $lists = [];
    foreach ($results['data'] as $key => $value) {
        $lists[$key] = $TripsService->unsetResultValue($TripsService->formatResultValue($value), "list");
        //取点赞数
        // $lists[$key]['like_count']    = WallLike::where('love_wall_ID', $value['id'])->count();
        //取已坐数
        $lists[$key]['took_count']    =  InfoModel::where([['love_wall_ID','=',$value['id']],["status","<>",2]])->count();
    }
    $returnData = [
      'lists'=>$lists,
      'page' => $this->getPageData($results),
    ];
    return $returnData;
  }  


  /**
   * 取得需求列表
   */
  public function info_list($map,$pagesize=20,$wid = 0,$orderby = 'time ASC, t.infoid ASC ')
  {
    $TripsService = new TripsService();

    $fields = $TripsService->buildQueryFields('info_list');
    $join = $TripsService->buildTripJoins("s,e,p,department");


    $modelObj =  InfoModel::alias('t')->field($fields)->join($join)->where($map)->order($orderby);
    // $sql = $modelObj->fetchSql()->select();
    if ($pagesize > 0 || $wid > 0) {
        $results =    $modelObj->paginate($pagesize, false, ['query'=>request()->param()])->toArray();

        if (!$results['data']) {
            return false;
        }
        $datas = $results['data'];
        $pageData = $this->getPageData($results);
    } else {
        $datas =    $modelObj->select();
        if (!$datas) {
            return false;
        }
        $total = count($datas);
        $pageData = [
        'total'=>$total,
        'pageSize'=>0,
        'lastPage'=>$total,
        'currentPage'=>1,
      ];
    }
    $lists = [];
    foreach ($datas as $key => $value) {
        $lists[$key] = $TripsService->unsetResultValue($TripsService->formatResultValue($value), "list");
    }
    $returnData = [
      'lists'=>$lists,
      'page' =>$pageData,
    ];
    
    return $returnData;
  }  

  /**
   * 取得分页数据
   */
  public function getPageData($results){
    return [
      'total'=>$results['total'],
      'pageSize'=>$results['per_page'],
      'lastPage'=>$results['last_page'],
      'currentPage'=>intval($results['current_page']),
    ];
  }

  




}