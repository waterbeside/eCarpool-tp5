<?php
namespace app\api\controller\v1;

use think\facade\Env;
use app\api\controller\ApiBase;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\model\Address;
use app\carpool\model\WallLike;
use app\carpool\model\user as UserModel;
use app\user\model\Department as DepartmentModel;
use app\common\model\PushMessage;
use app\carpool\model\UserPosition as UserPositionModel;
use app\carpool\model\Grade as GradeModel;
use my\RedisData;

use think\Db;

/**
 * 行程相关
 * Class Trips
 * @package app\api\controller
 */
class Trips extends ApiBase
{
    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * 我的行程
     */
    public function index($pagesize=20, $type=0, $fullData = 0)
    {
        $page = input('param.page',1);
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $extra_info = json_decode($userData['extra_info'], true);
        $merge_ids = isset($extra_info['merge_id']) && is_array($extra_info['merge_id']) && $type ==1  ? $extra_info['merge_id'] : [];

        $redis = new RedisData();
        $InfoModel = new InfoModel();
        $viewSql  =  $InfoModel->buildUnionSql($uid, $merge_ids ,($type ? "(0,1,3,4)" : "(0,1,4)"));
        $fields = 't.infoid , t.love_wall_ID , t.time, t.trip_type , t.time, t.status, t.passengerid, t.carownid , t.seat_count,  t.subtime, t.map_type ';
        $fields .= ','.$this->buildUserFields('d');
        $fields .= ', dd.fullname as d_full_department';
        $fields .= ','.$this->buildUserFields('p');
        $fields .= ', pd.fullname as p_full_department';
        $fields .=  $this->buildAddressFields();
        $join = $this->buildTripJoins();



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
            $cacheKey = "carpool:trips:my:u{$uid}:pz{$pagesize}_p{$page}_fd{$fullData}";
            $cacheExp = 20;
            $cacheData = $redis->get($cacheKey);
            if($cacheData){
              if($cacheData == "-1"){
                return $this->jsonReturn(20002, lang('No data'));
              }
              $returnData = json_decode($cacheData,true);
              return $this->jsonReturn(0, $returnData, "success");
            }
        }


        $modelObj =  Db::connect('database_carpool')->table("($viewSql)" . ' t')->field($fields)->where($map)->join($join)->order($orderby);
        if ($pagesize > 0 || $type > 0) {
            $results =    $modelObj->paginate($pagesize, false, ['query'=>request()->param()])->toArray();
            if (!$results['data']) {
                return $this->jsonReturn(20002, lang('No data'));
            }

            $datas = $results['data'];
            $pageData = [
              'total'=>$results['total'],
              'pageSize'=>$results['per_page'],
              'lastPage'=>$results['last_page'],
              'currentPage'=>intval($results['current_page']),
            ];
        } else {
            $datas =    $modelObj->select();
            if (!$datas) {
                if(!$type){
                  $redis->setex($cacheKey, $cacheExp, -1);
                }
                return $this->jsonReturn(20002, lang('No data'));
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
            $datas[$key] = $fullData ? $this->formatResultValue($value) : $this->unsetResultValue($this->formatResultValue($value), "list");
            // $datas[$key] = $this->formatResultValue($value,$merge_ids);
            $datas[$key]['show_owner']  = $value['trip_type'] ||  ($value['infoid']>0 && $uid == $value['passengerid']  &&  $value['carownid'] > 0)  ?  1 : 0;
            $datas[$key]['is_driver']   =  $uid == $value['carownid']  ?  1 : 0;
            $datas[$key]['status'] = intval($value['status']);
            $datas[$key]['took_count']  = $value['infoid'] > 0 ? ($datas[$key]['is_driver'] ? 1 : 0) : InfoModel::where([['love_wall_ID','=',$value['love_wall_ID']],['status','<>',2]])->count() ; //取已坐数
        }
        $returnData = [
          'lists'=>$datas,
          'page' =>$pageData
        ];
        if(!$type){
          $redis->setex($cacheKey, $cacheExp, json_encode($returnData));
        }

        $this->jsonReturn(0, $returnData, "success");
    }


    /**
     * 历史行程
     */
    public function history($pagesize =20)
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $page = input('param.page',1);


        // 查缓存
        $redis = new RedisData();
        $cacheKey = "carpool:trips:history:u{$uid}:pz{$pagesize}_p{$page}";
        $cacheExp = 20;
        $cacheData = $redis->get($cacheKey);
        if($cacheData){
          if($cacheData == "-1"){
            return $this->jsonReturn(20002, lang('No data'));
          }
          $returnData = json_decode($cacheData,true);
          return $this->jsonReturn(0, $returnData, "success");
        }

        $extra_info = json_decode($userData['extra_info'], true);
        $merge_ids = isset($extra_info['merge_id']) && is_array($extra_info['merge_id'])  ? $extra_info['merge_id'] : [];

        $InfoModel = new InfoModel();
        $viewSql  =  $InfoModel->buildUnionSql($uid, $merge_ids ,"(0,1,2,3,4)");
        $fields = 't.infoid , t.love_wall_ID , t.time, t.trip_type , t.time, t.status, t.passengerid, t.carownid , t.seat_count,  t.subtime, t.map_type ';
        $fields .= ','.$this->buildUserFields('d');
        $fields .= ', dd.fullname as d_full_department';
        $fields .= ','.$this->buildUserFields('p');
        $fields .= ', pd.fullname as p_full_department';
        $fields .=  $this->buildAddressFields();
        $join = $this->buildTripJoins();
        $map = [
          ["t.time","<",date('YmdHi', strtotime('-30 minute'))],
          // ["t.go_time","<",strtotime('+15 minute')],
        ];
        $orderby = 't.time DESC, t.infoid DESC, t.love_wall_id DESC';
        $modelObj =  Db::connect('database_carpool')->table("($viewSql)" . ' t')->field($fields)->where($map)->join($join)->order($orderby);
        $results =    $modelObj->paginate($pagesize, false, ['query'=>request()->param()])->toArray();
        if (!$results['data']) {
            $redis->setex($cacheKey, $cacheExp, -1);
            return $this->jsonReturn(20002, lang('No data'));
        }

        $datas = $results['data'];
        $pageData = [
          'total'=>$results['total'],
          'pageSize'=>$results['per_page'],
          'lastPage'=>$results['last_page'],
          'currentPage'=>intval($results['current_page']),
        ];

        $GradeModel =  new GradeModel();

        foreach ($datas as $key => $value) {
            $app_id  = $value['map_type'] ? 2 : 1 ;
            $datas[$key] =  $this->formatResultValue($value);
            // $datas[$key] = $this->formatResultValue($value,$merge_ids);
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
        $redis->setex($cacheKey, $cacheExp, json_encode($returnData));
        $this->jsonReturn(0, $returnData, "success");
        // $this->unsetResultValue($this->index($pagesize, 1, 1));
    }

    /**
     * 墙上空座位
     */
    public function wall_list($pagesize=20, $keyword="", $city=null, $map_type=NULL)
    {
        $userData = $this->getUserData(1);
        $company_id = $userData['company_id'];
        $time_e = strtotime("+20 day");
        $time_s = strtotime("-1 hour");
        $map = [
          ['d.company_id','=',$company_id],
          // ['love_wall_ID','>',0],
          ['t.status','<',2],
          // ['go_time','between time',[date('YmdHi',$time_s),date('YmdHi',$time_e)]],
          ['t.time','<',(date('YmdHi', $time_e))],
          ['t.time','>',(date('YmdHi', $time_s))],
        ];
        if ($keyword) {
            $map[] = ['d.name|s.addressname|e.addressname|t.startname|t.endname','like',"%{$keyword}%"];
        }
        
        if ($city) {
            $map[] = ['s.city','=',$city];
        }

        if (is_numeric($map_type)) {
            $map[] = ['t.map_type','=',$map_type];
        }
        $fields = 't.time, t.status, t.seat_count';
        $fields .= ', t.love_wall_ID as id, t.subtime , t.carownid as driver_id  ';
        $fields .= ', dd.fullname as d_full_department  ';

        $fields .= ','.$this->buildUserFields('d');
        $fields .=  $this->buildAddressFields();
        $join = $this->buildTripJoins("s,e,d,department");


        $results = WallModel::alias('t')->field($fields)->join($join)->where($map)->order(' time ASC, t.love_wall_ID ASC  ')
        ->paginate($pagesize, false, ['query'=>request()->param()])->toArray();
        if (!$results['data']) {
            return $this->jsonReturn(20002, lang('No data'));
        }
        $lists = [];
        foreach ($results['data'] as $key => $value) {
            $lists[$key] = $this->unsetResultValue($this->formatResultValue($value), "list");

            //取点赞数
            // $lists[$key]['like_count']    = WallLike::where('love_wall_ID', $value['id'])->count();
            //取已坐数
            $lists[$key]['took_count']    =  InfoModel::where([['love_wall_ID','=',$value['id']],["status","<>",2]])->count();
        }
        $returnData = [
          'lists'=>$lists,
          'page' =>[
            'total'=>$results['total'],
            'pageSize'=>$results['per_page'],
            'lastPage'=>$results['last_page'],
            'currentPage'=>intval($results['current_page']),
          ]
        ];
        $this->jsonReturn(0, $returnData, "success");
    }


    /**
     * 空座位详情页
     * @param  integer $id 空座位id
     */
    public function wall_detail($id, $returnType=1, $pb = 0)
    {
        if (!$pb) {
            $this->checkPassport(1);
            $uid = $this->userBaseInfo['uid'];
        }
        if (!$id || !is_numeric($id)) {
            $this->jsonReturn(992, [], 'lost id');
            // return $this->error('lost id');
        }
        $data = null;

        // 查缓存
        $redis = new RedisData();
        $cacheKey = "carpool:trips:wall_detail:{$id}";
        $cacheExp = 30;
        $cacheData = $redis->get($cacheKey);
        if($cacheData){
          if($cacheData == "-1"){
            return $returnType ? $this->jsonReturn(20002, lang('No data')) : [];
          }
          $data = $cacheData;
        }


        if(!$data || !is_array($data)){
          $fields = 't.time, t.status,  t.seat_count, t.map_type, t.im_tid, t.im_chat_tid';
          $fields .= ', t.love_wall_ID as id ,t.love_wall_ID , t.subtime , t.carownid as driver_id  ';
          $fields .= ', dd.fullname as d_full_department  ';
          $fields .= ','.$this->buildUserFields('d');
          $fields .=  $this->buildAddressFields();
          $join = $this->buildTripJoins("s,e,d,department");
          $data = WallModel::alias('t')->field($fields)->join($join)->where("t.love_wall_ID", $id)->find();
          if (!$data) {
              $redis->setex($cacheKey, $cacheExp, -1);
              return $returnType ? $this->jsonReturn(20002, lang('No data')) : [];
          }
          $app_id  = $data['map_type'] ? 2 : 1 ;
          $data = $this->unsetResultValue($this->formatResultValue($data), ($pb ? "detail_pb" : "detail"));

          $countBaseMap = ['love_wall_ID','=',$data['love_wall_ID']];
          $data['took_count']       = InfoModel::where([$countBaseMap,["status","in",[0,1,3,4]]])->count(); //取已坐数
          $data['took_count_all']   = InfoModel::where([$countBaseMap,['status','<>',2]])->count() ; //取已坐数
          $redis->setex($cacheKey, $cacheExp, json_encode($data));

        }



        if (!$pb) {

            $data['uid']        = $uid;
            $GradeModel         = new GradeModel();
            $grade_allow = $GradeModel->isGrade('trips',$app_id,$data['time']);


            if($data['d_uid'] == $uid){
              $data['take_status']      = null;
              $data['hasTake']          = 0;
              $data['hasTake_finish']   = 0;
              $ratedMap = [
                ['type','=',1],
                ['object_id','=', $id],
                ['uid','=',$uid]
              ];
              $checkHasGrade = GradeModel::where($ratedMap)->count();
              $data['already_rated'] =  !$grade_allow || $checkHasGrade ? 1 : 0;
            }else{
              $infoData                 = InfoModel::where([$countBaseMap,['passengerid','=',$uid]])->order("subtime DESC , infoid DESC")->find();
              $data['take_status']      = $infoData['status']; //查看是否已搭过此车主的车
              $data['hasTake']          = $data['take_status'] !== null && in_array($data['take_status'], [0,1,4]) ? 1 : InfoModel::where([$countBaseMap,["status","in",[0,1,4]],['passengerid','=',$uid]])->count(); //查看是否已搭过此车主的车
              $data['hasTake_finish']   = $data['take_status'] == 3 ? 1 : InfoModel::where([$countBaseMap,['status','=',3],['passengerid','=',$uid]])->count();  //查看是否已搭过此行程评分
              if($infoData){
                $ratedMap = [
                  ['type','=',0],
                  ['object_id','=', $infoData['infoid']],
                  ['uid','=',$uid]
                ];
                $checkHasGrade = GradeModel::where($ratedMap)->count();
                $data['already_rated'] = !$grade_allow || $checkHasGrade ? 1 : 0;
              }else{
                $data['already_rated'] = 1 ;
              }
            }
            $data['take_status']      = intval($data['take_status']);
        }
        // return $this->success('加载成功','',$data);
        return $returnType ?   $this->jsonReturn(0, $data, 'success') : $data;
    }

    /**
     * 约车需求
     */
    public function info_list($keyword="", $status = 0, $pagesize=50, $wid = 0, $returnType = 1, $orderby = '',$city=NULL, $map_type=null)
    {
        $userData = $this->getUserData(1);
        $company_id = $userData['company_id'];


        $time_e = strtotime("+20 day");
        $time_s = strtotime("-1 hour");

        if ($wid>0) {
            $map = [
              ['love_wall_ID','=',$wid]
            ];
        } else {
            $map = [
              ['p.company_id','=',$company_id],
              // ['love_wall_ID','>',0],
              // ['go_time','between time',[date('YmdHi',$time_s),date('YmdHi',$time_e)]],
              ['t.time','<',(date('YmdHi', $time_e))],
              ['t.time','>',(date('YmdHi', $time_s))],
            ];
        }


        $map[] = $this->buildStatusMap($status);
        // dump($map);exit;

        if ($keyword) {
            $map[] = ['s.addressname|p.name|e.addressname|t.startname|t.endname','like',"%{$keyword}%"];
        }
        if ($city) {
            $map[] = ['s.city','=',$city];
        }
        if (is_numeric($map_type)) {
            $map[] = ['t.map_type','=',$map_type];
        }

        $fields = 't.time, t.status ';
        // $fields = ', FROM_UNIXTIME(t.go_time,'%Y-%M-%D %h:%i:%s') as format_time';
        $fields .= ',t.infoid as id, t.love_wall_ID , t.subtime , t.carownid as driver_id , t.passengerid as passenger_id  ';
        $fields .= ', pd.fullname as p_full_department  ';

        // $fields .= ','.$this->buildUserFields('d');
        $fields .= ','.$this->buildUserFields('p');
        $fields .=  $this->buildAddressFields();
        $join = $this->buildTripJoins("s,e,p,department");

        $orderby = $orderby ? $orderby : 'time ASC, t.infoid ASC ';

        $modelObj =  InfoModel::alias('t')->field($fields)->join($join)->where($map)->order($orderby);
        // $sql = $modelObj->fetchSql()->select();
        if ($pagesize > 0 || $wid > 0) {
            $results =    $modelObj->paginate($pagesize, false, ['query'=>request()->param()])->toArray();

            if (!$results['data']) {
                return $this->jsonReturn(20002, lang('No data'));
            }
            $datas = $results['data'];
            $pageData = [
              'total'=>$results['total'],
              'pageSize'=>$results['per_page'],
              'lastPage'=>$results['last_page'],
              'currentPage'=>intval($results['current_page']),
            ];
        } else {
            $datas =    $modelObj->select();
            if (!$datas) {
                return $this->jsonReturn(20002, lang('No data'));
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
            $lists[$key] = $this->unsetResultValue($this->formatResultValue($value), "list");
        }
        $returnData = [
          'lists'=>$lists,
          'page' =>$pageData,
        ];
        return $returnType ? $this->jsonReturn(0, $returnData, "success") : $returnData;
    }


    /**
     * 行程详情页
     * @param  integer $id 行程id
     */
    public function info_detail($id, $returnType=1, $pb = 0)
    {
        if (!$pb) {
            $this->checkPassport(1);
            $uid = $this->userBaseInfo['uid'];
        }
        if (!$id || !is_numeric($id)) {
            $this->jsonReturn(992, [], 'lost id');
            // return $this->error('lost id');
        }
        $data = null;
        // 查缓存
        $redis = new RedisData();
        $cacheKey = "carpool:trips:info_detail:{$id}";
        $cacheExp = 30;
        $cacheData = $redis->get($cacheKey);
        if($cacheData){
          if($cacheData == "-1"){
            return $returnType ? $this->jsonReturn(20002, lang('No data')) : [];
          }
          $data = $cacheData;
        }
        if(!$data || !is_array($data)){
          $fields = 't.time, t.status, t.map_type';
          $fields .= ',t.infoid as id, t.infoid , t.love_wall_ID , t.subtime , t.carownid as driver_id, t.passengerid as passenger_id  ';
          $fields .= ','.$this->buildUserFields('d');
          $fields .= ', dd.fullname as d_full_department  ';
          $fields .= ','.$this->buildUserFields('p');
          $fields .= ', pd.fullname as p_full_department  ';
          $fields .=  $this->buildAddressFields();
          $join = $this->buildTripJoins();
          $data = InfoModel::alias('t')->field($fields)->join($join)->where("t.infoid", $id)->find();
          if (!$data) {
              $redis->setex($cacheKey, $cacheExp, -1);
              return $returnType ? $this->jsonReturn(20002, lang('No data')) : [];
          }
          $app_id  = $data['map_type'] ? 2 : 1 ;
          $data = $this->unsetResultValue($this->formatResultValue($data), ($pb ? "detail_pb" : "detail"));
          $redis->setex($cacheKey, $cacheExp, json_encode($data));
        }

        if (!$pb) {
            $data['uid']          = $uid;
            $GradeModel         = new GradeModel();
            $grade_allow = $GradeModel->isGrade('trips',$app_id,$data['time']);
            $ratedMap = [
              ['type','=',0],
              ['object_id','=', $id],
              ['uid','=',$uid]
            ];

            $data['already_rated'] =  !$data['d_uid'] || !$grade_allow ||  GradeModel::where($ratedMap)->count() ? 1 : 0;
        }

        // return $this->success('加载成功','',$data);
        return $returnType ?   $this->jsonReturn(0, $data, 'success') : $data;
    }






    /**
     * 发布行程
     * @param string $from  行程类型 wall|info
     */
    public function add($from)
    {
        if (!in_array($from, ['wall','info'])) {
            $this->jsonReturn(992, [], lang('Parameter error'));
        }
        $userData = $this->getUserData(1);
        $uid = $userData['uid']; //取得用户id

        $time                 = input('post.time');
        $map_type             = input('post.map_type');
        // $datas['startpid']    = input('post.startpid');
        // $datas['endpid']      = input('post.endpid');
        $datas['start']       = input('post.start');
        $datas['start']       = is_array($datas['start']) ? $datas['start'] : json_decode($datas['start'],true);
        $datas['end']         = input('post.end');
        $datas['end']         = is_array($datas['end']) ? $datas['end'] : json_decode($datas['end'],true);
        $datas['seat_count']  = input('post.seat_count');
        $datas['distance']    = input('post.distance', 0);
        $datas['time']        = is_numeric($time) ? date('YmdHi', $time) : date('YmdHi', strtotime($time.":00"));

        // $this->jsonReturn(-1,$datas);
        // dump($datas);exit;

        if (empty($time)) {
            $this->jsonReturn(-1, [], lang('Please select date and time'));
        }
        if ($from=="wall" && empty($datas['seat_count'])) {
            $this->jsonReturn(-1, [], lang('The number of empty seats cannot be empty'));
        }

        $AddressModel = new Address();
        $InfoModel    = new InfoModel();
        $WallModel    = new WallModel();

        //计算前后范围内有没有重复行程
        if ($InfoModel->checkRepetition($time, $uid, 60*5)) {
            $this->jsonReturn(-1, [], $InfoModel->errorMsg);
        }
        if ($WallModel->checkRepetition($time, $uid, 60*5)) {
            $this->jsonReturn(-1, [], $WallModel->errorMsg);
        }


        $createAddress = array();
        //处理起点
        if ((!isset($datas['start']['addressid']) || !(is_numeric($datas['start']['addressid']) && $datas['start']['addressid'] >0)) && !$map_type) {
            $startDatas = $datas['start'];
            $startDatas['company_id'] = $userData['company_id'];
            $startDatas['create_uid'] = $userData['uid'];
            $startRes = $AddressModel->addFromTrips($startDatas);
            if (!$startRes) {
                $this->jsonReturn(-1, [], lang("The point of departure must not be empty"));
            }
            $datas['start']['addressid'] = $startRes['addressid'];
            $createAddress[0] = $startRes;
        }

        //处理终点
        if ((!isset($datas['end']['addressid']) || !(is_numeric($datas['end']['addressid']) && $datas['end']['addressid'] >0)) && !$map_type) {
            $endDatas = $datas['end'];
            $endDatas['company_id'] = $userData['company_id'];
            $endDatas['create_uid'] = $userData['uid'];
            $endRes = $AddressModel->addFromTrips($endDatas);
            if (!$endRes) {
                $this->jsonReturn(-1, [], lang("The destination cannot be empty"));
            }
            $datas['end']['addressid'] = $endRes['addressid'];
            $createAddress[1] = $endRes;
        }

        //检查出发时间是否已经过了
        if (date('YmdHi', time()) > $datas['time']) {
            $this->jsonReturn(-1, [], lang("The departure time has passed. Please select the time again"));
            // $this->error("出发时间已经过了<br /> 请重选时间");
        }


        //检查时间是否上下班时间
        $hourMin = date('Hi', strtotime($datas['time']));
        $datas['type']   = 2;
        if ($hourMin > 400 && $hourMin < 1000) {
            $datas['type'] =  0 ;
        } elseif ($hourMin > 1600 && $hourMin < 2200) {
            $datas['type'] =  1 ;
        }

        $inputData = [
          'status'    => 0,
          'subtime'   => date('YmdHi'),
          'time'      => $datas['time'],
          'type'      => $datas['type'],
          'map_type'  => $map_type,
          'distance'  => $datas['distance'],
          'startpid'  => $map_type ? (isset($datas['start']['gid']) &&  $datas['start']['gid'] ? -1 : 0) : $datas['start']['addressid'] ,
          'endpid'  => $map_type ? (isset($datas['end']['gid']) &&  $datas['end']['gid'] ? -1 : 0) : $datas['end']['addressid'] ,
          'startname'  => $datas['start']['addressname'] ,
          'start_latlng'  => Db::raw("geomfromtext('point(".$datas['start']['longitude']." ".$datas['start']['latitude'].")')"),
          'endname'  => $datas['end']['addressname'] ,
          'end_latlng'  => Db::raw("geomfromtext('point(".$datas['end']['longitude']." ".$datas['end']['latitude'].")')"),

        ];
        if ($map_type) {
            if (isset($datas['start']['gid']) &&  $datas['start']['gid']) {
                $inputData['start_gid'] = $datas['start']['gid'];
            }
            if (isset($datas['end']['gid']) &&  $datas['end']['gid']) {
                $inputData['end_gid'] = $datas['start']['gid'];
            }
        }

        if ($from == "wall") {
            $inputData['carownid'] = $uid;
            $inputData['seat_count']  = $datas['seat_count'];
            $result = $WallModel->insertGetId($inputData);
        } elseif ($from == "info") {
            $inputData['passengerid'] = $uid;
            $inputData['carownid']    = -1;
            $result = $InfoModel->insertGetId($inputData);
        }

        // var_dump($model->attributes['infoid']);
        if ($result) {
            $this->jsonReturn(0, ['createAddress'=>$createAddress,'id'=>$result], 'success');
        } else {
            $this->jsonReturn(-1, lang('Fail'));
        }
    }

    /**
     * 乘客列表
     * @param  integer          $id 空座位id
     * @param  integer|string   $status 状态筛选
     */
    public function passengers($id, $status = "neq|2")
    {
        $res =  $this->info_list("", $status, 0, $id, 0, 'status ASC, time ASC');
        if ($res) {
            foreach ($res['lists'] as $key => $value) {
                $res['lists'][$key] = $this->unsetResultValue($value, ['love_wall_ID']);
            }
            if (isset($res['page'])) {
                unset($res['page']);
            }
        }
        $this->jsonReturn(0, $res, 'success');
    }

    /**
     * 改变更新字段
     * @param string $from  行程类型 wall|info
     */
    public function change($from="", $id, $type="",$step=0)
    {
        $this->checkPassport(1);
        $type = mb_strtolower($type);
        $from = mb_strtolower($from);

        if (!in_array($type, ['cancel','finish','riding','hitchhiking','pickup','get_on','startaddress','endaddress']) || !$id) {
            return $this->jsonReturn(992, [], lang('Parameter error'));
        }
        if ($from=="wall") {
            $Model    = new WallModel();
        } elseif ($from=="info") {
            $Model    = new InfoModel();
        } else {
            return $this->jsonReturn(992, [], lang('Parameter error'));
        }

        $userData = $this->getUserData(1);
        $uid = $userData['uid']; //取得用户id
        $fields = "*,x(start_latlng) as start_lng , y(start_latlng) as start_lat,x(end_latlng) as end_lng,y(end_latlng) as end_lat";
        $datas = $Model->field($fields)->get($id);

        if (!$datas) {
            return $this->jsonReturn(20002, lang('No data'));
        }
        $map_type = $datas['map_type'];
        $appid = $map_type ? 2 : 1;

        $isDriver    = $datas->carownid == $uid ? true : false; //是否司机操作
        $driver_id   = $datas->carownid ; //司机id;

        $AddressModel = new Address();
        $InfoModel    = new InfoModel();
        $WallModel    = new WallModel();

        /*********** 完成或取消或上车 ***********/
        if (in_array($type, ["cancel","finish","get_on"])) {
            //检查是否已取消或完成
            if (in_array($datas->status, [2,3])) {
                return $this->jsonReturn(-1, [], lang('The trip has been completed or cancelled. Operation is not allowed'));
            }
            //检查时间
            if ($type == "finish" && strtotime($datas->time.'00')  > time()) {
                return $this->jsonReturn(-1, [], lang('The trip not started, unable to operate'));
            }
            //检查是否允许操作
            if ($from=="info" && !$isDriver && $datas->passengerid != $uid) {
                return $this->jsonReturn(-1, [], lang('No permission'));
            }
            // 断定是否自己上自己车
            if ($type == "get_on" && $isDriver) {
                return $this->jsonReturn(-1, [], lang('You can`t take your own'));
            }

            //如果是乘客从空座位操作, 则查出infoid，递归到from = info操作。
            if ($from=="wall" && !$isDriver) {
                $infoDatas = InfoModel::where([["love_wall_ID",'=',$id],['passengerid',"=",$uid],['status','in',[0,1,4]]])->order("status")->find();
                if (!$infoDatas) {
                    $checkCount = InfoModel::where([["love_wall_ID",'=',$id],['passengerid',"=",$uid]])->order("status")->count();
                    if (!$checkCount) {
                        return $this->jsonReturn(-1, [], lang('No permission'));
                    } else {
                        return $this->jsonReturn(-1, [], lang('The trip has been completed or cancelled. Operation is not allowed'));
                    }
                }
                return $this->change("info", $infoDatas['infoid'], $type);
                exit;
            }


            //处理要更新的数据
            if ($type == "cancel") { //如果是取消
              $datas->cancel_user_id = $uid;
                $datas->cancel_time    = date('YmdHis', time());
                $datas->status         = 2;
                if ($from == "info" && $isDriver) {
                    $datas->status          = 0;
                    $datas->love_wall_ID    = null;
                    $datas->carownid        = null;
                }
            } elseif ($type == "finish") { //如果是完结
                $datas->cancel_time    = date('YmdHis', time());
                $datas->status         = 3;
            } elseif ($type == "get_on") {
                $datas->geton_time    = date('Y-m-d H:i:s');
                $datas->status         = 4;
            }

            //保存改变的状态
            $res = $datas->save();

            if (!$res) {
                return $this->jsonReturn(-1, [], lang('Fail'));
            }


            //如果是取消操作，则推送消息(设置要推的消息);
            if ($type == "cancel") {
                if ($isDriver) { // 如果是司机，则推给乘客
                    $push_msg = lang("The driver {:name} cancelled the trip", ["name"=>$userData['name']]) ;
                    $passengerids = $from == "wall" ?  InfoModel::where([["love_wall_ID",'=',$id],["status","in",[0,1,4]]])->column('passengerid') : $datas->passengerid ;
                    $sendTarget = $passengerids ;
                } else { //如果乘客取消，则推送给司机
                    $push_msg = lang("The passenger {:name} cancelled the trip", ["name"=>$userData['name']]) ;
                    $sendTarget = $driver_id;
                    // 顺便检查词机空座位状态并更正 -----2019-04-25
                    if($from == "info"){
                        $took_count = InfoModel::where([["love_wall_ID",'=',$id],["status","in",[0,1,3,4]]])->count();
                        if($took_count === 0){ //如果发现没有有效乘客，则更改空座位状态为0;
                            WallModel::where(["love_wall_ID",'=',$datas->love_wall_ID])->update(["status"=>0]);
                        }
                    }
                    
                }
            } elseif ($type == 'get_on') {
                $push_msg = lang("The passenger {:name} has got on your car", ["name"=>$userData['name']]) ;
                $sendTarget = $driver_id;
            }

            if ($from=="wall" && $isDriver) { //如果是司机操作空座位，则同时对乘客行程进行操作。
                $upInfoData = $type == "finish" ? ["status"=>3] : ["status"=>0,"love_wall_ID"=>null,"carownid"=>-1] ;
                InfoModel::where([["love_wall_ID",'=',$id],["status","in",[0,1,4]]])->update($upInfoData);
            }

            //如果是取消或上车操作，执行推送消息
            if (($type == "cancel" || $type == "get_on") && isset($push_msg) && isset($sendTarget) && !empty($sendTarget)) {
                $this->pushMsg($sendTarget, $push_msg, $appid);
            }
            $extra = $this->errorMsg ? ['pushMsg'=>$this->errorMsg] : [1];
            return $this->jsonReturn(0, [], "success", $extra);
        }

        /*********** riding 搭车  ***********/
        if ($type == "riding" || $type == "hitchhiking") {
            if ($from !="wall") {
                return $this->jsonReturn(992, [], lang('Parameter error'));
            }

            if ($datas->status == 2) {
                return $this->jsonReturn(-1, [], lang('Failed, the owner has cancelled the trip'));
                // return $this->error('或车主或已取消空座位，<br />请选择其它司机。');
            }
            if ($datas->status == 3) {
                return $this->jsonReturn(-1, [], lang('Failed, the trip has ended'));
            }
            // 断定是否自己搭自己
            if ($isDriver) {
                return $this->jsonReturn(-1, [], lang('You can`t take your own'));
            }

             //计算前后范围内有没有重复行程
             if ($InfoModel->checkRepetition(strtotime($datas->time.'00'), $uid, 60*5)) {
                return $this->jsonReturn(30007, [], $InfoModel->errorMsg);
            }
            //计算前后范围内有没有重复行程
            if ($WallModel->checkRepetition(strtotime($datas->time.'00'), $uid, 60*5)) {
                return $this->jsonReturn(30007, [], $WallModel->errorMsg);
            }
        

            $seat_count = $datas->seat_count;
            $checkInfoMap = [['love_wall_ID','=',$id],['status','<>',2]];
            $took_count = InfoModel::where($checkInfoMap)->count();

            if ($took_count >= $seat_count) {
                return $this->jsonReturn(-1, [$seat_count,$took_count], lang('Failed, seat is full'));
            }
            $checkInfoMap[] = ['passengerid','=',$uid];
            $checkHasTake = InfoModel::where($checkInfoMap)->count();
            if ($checkHasTake>0) {
                return $this->jsonReturn(-1, [], lang('You have already taken this trip'));
            }

            $setInfoDatas = array(
              'passengerid'   => $uid,
              'carownid'      => $datas->carownid,
              'love_wall_ID'  => $id,
              'subtime'       => date('YmdHi', time()),
              'time'          => $datas->time,
              'startpid'      => $datas->startpid,
              'startname'     => $datas->startname,
              'start_gid'     => $datas->start_gid,
              'endpid'        => $datas->endpid,
              'endname'       => $datas->endname,
              'end_gid'       => $datas->end_gid,
              'type'          => $datas->type,
              'map_type'      => $datas->map_type,
              'status'        => 1,
            );
            if ($datas->start_lat && $datas->start_lng) {
                $setInfoDatas['start_latlng'] = Db::raw("geomfromtext('point(".$datas->start_lng." ".$datas->start_lat.")')");
                $setInfoDatas['end_latlng'] = Db::raw("geomfromtext('point(".$datas->end_lng." ".$datas->end_lat.")')");
            }


            $res = InfoModel::insertGetId($setInfoDatas);
            if (!$res) {
                return $this->jsonReturn(-1, [], lang('Fail'));
            }
            $datas->status = 1 ;
            $datas->save();
            $this->pushMsg($datas->carownid, lang('{:name} took your car', ["name"=>$userData['name']]), $appid);
            $extra = $this->errorMsg ? ['pushMsg'=>$this->errorMsg] : [1];
            return $this->jsonReturn(0, ['infoid'=>$res], 'success', $extra);
        }

        /*********** pickup 接受需求  ***********/
        if ($type == "pickup") {
            if ($from !="info") {
                return $this->jsonReturn(992, [], lang('Parameter error'));
            }
            // 断定是否自己搭自己
            if ($datas->passengerid == $uid) {
                return $this->jsonReturn(-1, [], lang('You can`t take your own'));
            }
            if ($datas->status > 0) {
                return $this->jsonReturn(-1, [], lang("This requirement has been picked up or cancelled"));
            }

             //计算前后范围内有没有重复行程
            // if ($InfoModel->checkRepetition(strtotime($datas->time.'00'), $uid, 120)) {
            //     return $this->jsonReturn(30007, [], $InfoModel->errorMsg);
            // }
            // 如果你有一趟空座位
            $checkWallRes = $WallModel->checkRepetition(strtotime($datas->time.'00'), $uid, 60*5);
            if($checkWallRes){
                if($step == 1){
                    $datas->love_wall_ID  = $checkWallRes['love_wall_ID'];
                }else{
                    return $this->jsonReturn(50008, $checkWallRes, $WallModel->errorMsg);
                    // return $this->jsonReturn(50008, $checkWallRes, "你在该时间段内有一个已发布的空座位，是否将该乘客请求合并到你的空座位上");
                }
            }
            $datas->carownid = $uid;
            $datas->status = 1;
            $res = $datas->save();
            if ($res) {
                $this->pushMsg($datas->passengerid, lang('{:name} accepted your ride requst', ["name"=>$userData['name']]), $appid);
                return $this->jsonReturn(0, [], 'success');
            }
        }

        /*********** startaddress|endaddress 修改起点终点  ***********/
        if (in_array($type, ["startaddress","endaddress"]) && $from =="info") {
            if ($datas->passengerid != $uid) {
                return $this->jsonReturn(-1, [], lang('No permission'));
            }
            $addressDatas       = input('post.address');
            $map_type       =  $addressDatas['map_type'];
            $addressSign = $type == "startaddress" ? "start" : "end";
            //处理站点
            if (!$addressDatas['addressid'] && !$map_type) {
                $addressDatas['company_id'] = $userData['company_id'];
                $addressDatas['create_uid'] = $userData['uid'];
                $addressRes = $AddressModel->addFromTrips($addressDatas);
                if (!$addressRes) {
                    $this->jsonReturn(-1, [], lang("The adress must not be empty"));
                }
                $addressDatas['addressid'] = $addressRes['addressid'];
                $createAddress[0] = $addressRes;
            }
            $inputData = [
              $addressSign.'pid'  => $map_type ? (isset($addressDatas['gid']) &&  $addressDatas['gid'] ? -1 : 0) : $addressDatas['addressid'] ,
              $addressSign.'name'  => $addressDatas['addressname'] ,
              $addressSign.'_latlng'  => Db::raw("geomfromtext('point(".$addressDatas['longitude']." ".$addressDatas['latitude'].")')"),
            ];
            if ($map_type) {
                if (isset($addressDatas['gid']) &&  $addressDatas['gid']) {
                    $inputData[$addressSign.'_gid'] = $addressDatas['gid'];
                }
            }
            $res = $datas->save($inputData);
            if ($res) {
                return $this->jsonReturn(0, [], 'success');
            }
        }

        $extra = $this->errorMsg ? ['pushMsg'=>$this->errorMsg] : [];
        return $this->jsonReturn(-1, [], lang("Fail"), $extra);
    }


    /**
     * 取消行程
     * @param string $from  行程类型 wall|info
     */
    public function cancel($from, $id)
    {
        return $this->change($from, $id, 'cancel');
    }


    /**
     * 检查是否有未评分行程
     */
    public function check_my_status(){
      $userData = $this->getUserData(1);
      $uid = $userData['uid'];
      $redis = new RedisData();
      $exp = "30";   //缓存过期时间
      $cacheKey = [];
      $cacheKey['not_rated'] = "carpool:trips:check_my_status:not_rated:".$uid;
      $cacheData = $redis->get($cacheKey['not_rated']);
      if($cacheData){
        $returnList = json_decode($cacheData,true);
        $returnList = $returnList ? $returnList : [];
      }
      if(!isset($returnList)){
        $GradeModel =  new GradeModel();
        // $isGrade = $GradeModel->isGrade('trips',$app_id,$datas[$key]['time']);

        $gradeConfigData = config('trips.grade');

        $gradeBaseSql = GradeModel::where("uid",$uid)->buildSql();

        $InfoModel = new InfoModel();
        $baseSql  =  $InfoModel->buildUnionSql($uid, [] , "(3)" );
        $fields = 't.infoid , t.love_wall_ID , t.time, t.trip_type ,  t.status, t.passengerid, t.carownid , t.seat_count,  t.subtime, t.map_type
            ,(case when t.love_wall_ID > 0 AND t.carownid = '.$uid.'  then t.love_wall_ID else t.infoid end) as  object_id
            , (case when t.love_wall_ID > 0 AND t.carownid = '.$uid.' then 1 else 0 end) as  grade_type
        ';
        $map = [];
        $orderby = 't.time Desc, t.infoid Desc, t.love_wall_id Desc';
        // buildSql
        $baseSql2 =  Db::connect('database_carpool')->table("($baseSql)" . ' t')->field($fields)->where($map)->order($orderby)
        // ->select();
        ->buildSql();
        // dump($baseSql2);exit;

        $map2 = [];
        $fields2 = 'tt.trip_type as type, tt.object_id as id, tt.time, tt.status, tt.map_type, tt.grade_type,tt.passengerid,tt.carownid, g.grade ';
        $join2 = [
          ["t_grade g"," g.object_id = tt.object_id AND g.type=tt.grade_type AND g.uid = $uid",'left'],
        ];
        $map2 = [
          ["tt.time","<",date('YmdHi', strtotime("-1 hour"))],
          ["tt.status","=",3],
          ['',"EXP",Db::raw('g.grade is null')]
        ];
        $res = Db::connect('database_carpool')->table("($baseSql2)" . ' tt')->field($fields2)->where($map2)->join($join2)->select();

        $returnList = [];
        foreach ($res as $key => $value) {
          $formatTime  = strtotime($value['time'].'00');
          $returnValue = [
            "time" =>  $formatTime ? $formatTime : 0 ,
            "type" =>  intval($value['grade_type']),
            "id" =>  intval($value['id']),
          ];
          $returnValue['time'] = $formatTime ? $formatTime : 0 ;
          $app_id = $value['map_type'] == 1 ? 2 : 1;
          $isGrade = $GradeModel->isGrade('trips',$app_id,$returnValue['time']);
          if($isGrade && $value['passengerid']){
            $returnList[] = $returnValue;
          }

        }
        $redis->setex($cacheKey['not_rated'], $exp, json_encode($returnList));
      }

      $returnData = [
        "not_rated" => $returnList ? $returnList : [] ,
      ];
      $this->jsonReturn(0,$returnData,lang('Successfully'));
    }


    /**
     * 用户位置
     */
    public function user_position($from, $id, $uid)
    {
        $this->checkPassport(1);
        if (!$from || !$id || !$uid) {
            $this->jsonReturn(992, [], lang('Parameter error'));
        }
        $msg = "";
        $uids = [];
        $now = time();
        $myUid = $this->userBaseInfo['uid'];

        if (in_array($from, ['rides','wall'])) { //如果是墙上空座位
            $from = 'wall';
            $tripData = $this->wall_detail($id, 0);
            if (!$tripData) {
                $this->jsonReturn(-1, [], lang('The trip does not exist'));
            }
            $wid = $id ;
        } elseif (in_array($from, ['requests','info'])) { //如果是行程约车需求
            $from = 'info';
            $tripData = $this->info_detail($id, 0);
            if (!$tripData) {
                $this->jsonReturn(-1, [], lang('The trip does not exist'));
            }

            $wid = $tripData['love_wall_ID'] ;
            if (!$wid) {
                if ($tripData['d_uid']) {
                    $uids[] = $tripData['d_uid'];
                }
                $uids[] = $tripData['p_uid'];
            }
        } else {
            $this->jsonReturn(992, [], lang('Parameter error'));
        }


        if (isset($tripData['d_uid']) && $tripData['d_uid'] == $uid) {
            $user_prefix = 'd';
        } elseif (isset($tripData['p_uid']) && $tripData['p_uid'] == $uid) {
            $user_prefix = 'p';
        } else {
            $this->jsonReturn(-1, [], lang('This user has not joined this trip or has cancelled the itinerary'));
        }

        $userData = [
          'uid' =>  $uid,
          'name' => $tripData[$user_prefix.'_name'],
          'loginname' => $tripData[$user_prefix.'_loginname'],
          'sex' => $tripData[$user_prefix.'_sex'],
          'department' => $tripData[$user_prefix.'_department'],
          'full_department' => $tripData[$user_prefix.'_full_department'],
          'imgpath' => $tripData[$user_prefix.'_imgpath'],
        ];

        $returnData = [
          'from' => $from,
          'tripData' => $tripData,
          'userData' => $userData,
          'position' => null,
          'now'  => $now,
        ];


        //验证是否成员，查找该行程的所有乘客id，以便用检查是否有权查询其它用户的位置信息
        if ($wid) {
            if ($tripData['d_uid']) {
                $uids[] = $tripData['d_uid'];
            }
            $passengerids = InfoModel::where([["love_wall_ID",'=',$wid],['status',"<>",2]])->column('passengerid');
            $uids = array_merge($uids, $passengerids);
        }
        if (!in_array($myUid, $uids)) {
            $this->jsonReturn(0, $returnData, lang('You are not the driver or passenger of this trip').lang('.').lang('Not allowed to view other`s location information').lang('.'));
        }
        //验证允许时间
        if ($tripData['time'] - $now > 3600 || $now - $tripData['time'] > 7200) {
            $msg = lang("Can't see other people's location information,Because not in the allowed range of time");
            $this->jsonReturn(0, $returnData, $msg);
        }

        $res = UserPositionModel::find($uid);

        if ($res) {
            $res = array_change_key_case($res->toArray(), CASE_LOWER);
            $position = [
                'longitude' => floatval($res['longitude']),
                'latitude'  => floatval($res['latitude']),
                'update_time'  => $res['update_time'] ? strtotime($res['update_time']) : 0 ,
            ];
            if (($position['longitude']>0 || $position['latitude']>0) && $now - $position['update_time'] < 7200) {
                $returnData['position']  =    $position;
                $msg = 'Load success';
            } else {
                $msg = lang("User has not uploaded location information recently");
            }
        } else {
            $msg = lang("User has not uploaded location information recently");
        }

        $this->jsonReturn(0, $returnData, $msg);
    }


    /* ----------------------------------------------------------------- */

    /**
     * 创件状态筛选map
     */
    protected function buildStatusMap($status, $t="t")
    {
        $statusExp = '=';
        if (is_string($status) && strpos($status, '|')) {
            $statusArray = explode('|', $status);
            if (count($statusArray)>1) {
                $statusExp = $statusArray[0];
                $status = $statusArray[1];
            } else {
                $status = $status[0];
            }
        }
        if (is_string($status) && strpos($status, ',')) {
            $status = explode(',', $status);
        }
        if (is_array($status) && $statusExp == "=") {
            $statusExp = "in";
        }
        if (in_array(mb_strtolower($statusExp), ['=','<=','>=','<','>','<>','in','eq','neq','not in','lt','gt','egt','elt'])) {
            return [$t.'.status',$statusExp,$status];
        } else {
            return [$t.'.status',"=",$status];
        }
    }


    /**
     * 创件要select的用户字段
     */
    protected function buildUserFields($a="u", $fields=[])
    {
        $format_array = [];
        $fields = !empty($fields) ? $fields : ['uid','loginname','name','phone','mobile','Department','sex','company_id','department_id','companyname','imgpath','carnumber','carcolor','im_id'];

        foreach ($fields as $key => $value) {
            $format_array[$key] = $a.".".$value." as ".$a."_".mb_strtolower($value);
        }
        return join(",", $format_array);
    }

    /**
     * 创件要select的地址字段
     */
    protected function buildAddressFields($fields="", $start_latlng = false)
    {
        $fields .= ',t.startpid, t.endpid';
        $fields .= ', x(t.start_latlng) as start_lng, y(t.start_latlng) as start_lat' ;
        $fields .= ', x(t.end_latlng) as end_lng, y(t.end_latlng) as end_lat' ;
        $fields .= ', t.startname , t.start_gid ';
        $fields .= ', t.endname , t.end_gid ';
        $fields .= ',s.addressname as start_addressname, s.latitude as start_latitude, s.longtitude as start_longitude';
        $fields .= ',e.addressname as end_addressname, e.latitude as end_latitude, e.longtitude as end_longitude';
        return $fields;
    }


    /**
     * 创健要join的表的数缓
     * @param  string|array $filter
     * @return array
     */
    protected function buildTripJoins($filter="d,p,s,e,department")
    {
        if (is_string($filter)) {
            $filter = explode(",", $filter);
        }
        $join = [];
        if (is_array($filter)) {
            foreach ($filter as $key => $value) {
                $filter[$key] = mb_strtolower($value);
            }
            if (in_array('s', $filter) || in_array('start', $filter)) {
                $join[] = ['address s','s.addressid = t.startpid', 'left'];
            }
            if (in_array('e', $filter) || in_array('end', $filter)) {
                $join[] = ['address e','e.addressid = t.endpid', 'left'];
            }
            if (in_array('d', $filter) || in_array('driver', $filter)) {
                $join[] = ['user d','d.uid = t.carownid', 'left'];
                if (in_array('department', $filter)) {
                    $join[] = ['t_department dd','dd.id = d.department_id', 'left'];
                }
            }
            if (in_array('p', $filter) || in_array('passenger', $filter)) {
                $join[] = ['user p','p.uid = t.passengerid', 'left'];
                if (in_array('department', $filter)) {
                    $join[] = ['t_department pd','pd.id = p.department_id', 'left'];
                }
            }
        }
        return $join;
    }


    /**
     * 格式化结果字段
     */
    protected function formatResultValue($value, $merge_ids = [], $unDo = [])
    {
        $value_format = $value;
        $value_format['subtime'] = intval(strtotime($value['subtime'])) ;
        // $value_format['go_time'] = $value['go_time'] ?  $value['go_time'] : strtotime($value['time']."00");
        // $value_format['time'] = date('Y-m-d H:i',strtotime($value['time'].'00'));
        // $value_format['time'] = $value_format['go_time'];
        $value_format['time'] = intval(strtotime($value['time'].'00')) ;
        //整理指定字段为整型
        $int_field_array = [
          'p_uid','p_sex','p_company_id','p_department_id',
          'd_uid','d_sex','d_company_id','d_department_id',
          'infoid','love_wall_ID',
          'startpid','endpid',
          'seat_count','trip_type'
        ];
        $value = json_decode(json_encode($value),true);
        foreach ($value as $key => $v) {
           if(in_array($key,$int_field_array)){
             $value_format[$key] = intval($v);
           }
        }
        if (!empty($merge_ids)) {
            if (!in_array('p', $unDo) && isset($value['p_uid']) &&  in_array($value['p_uid'], $merge_ids)) {
                $value_format['p_uid'] = $uid;
                $value_format['passengerid'] = $uid;
                $value_format['p_name'] = $userData['name'];
            }
            if (!in_array('d', $unDo) && isset($value['d_uid']) && in_array($value['d_uid'], $merge_ids)) {
                $value_format['d_uid'] = $uid;
                $value_format['carownid'] = $uid;
                $value_format['d_name'] = $userData['name'];
            }
        }

        if (!is_numeric($value['startpid']) || $value['startpid'] < 1) {
            $value_format['start_addressid'] = $value['startpid'];
            $value_format['start_addressname'] = $value['startname'];
            $value_format['start_longitude'] = $value['start_lng'];
            $value_format['start_latitude'] = $value['start_lat'];
        }
        if (!is_numeric($value['endpid']) || $value['endpid'] < 1) {
            $value_format['end_addressid'] = $value['endpid'];
            $value_format['end_addressname'] = $value['endname'];
            $value_format['end_longitude'] = $value['end_lng'];
            $value_format['end_latitude'] = $value['end_lat'];
        }

        if (isset($value['d_full_department']) || isset($value['p_full_department']) ) {
            $DepartmentModel = new DepartmentModel;
        }
        if (isset($value['d_full_department'])) {
            $value_format['d_department'] = $DepartmentModel->formatFullName($value['d_full_department'], 3);
        }
        if (isset($value['p_full_department'])) {
            $value_format['p_department'] = $DepartmentModel->formatFullName($value['p_full_department'], 3);
        }
        if (isset($value['d_imgpath']) && trim($value['d_imgpath'])=="") {
            $value_format['d_imgpath'] = 'default/avatar.png';
        }
        if (isset($value['p_imgpath']) && trim($value['p_imgpath'])=="") {
            $value_format['p_imgpath'] = 'default/avatar.png';
        }
        return $value_format;
    }


    /**
     * 取消显示的字段
     * @param array        $data         数据
     * @param string|array $unsetFields  要取消的字段
     * @param array        $unsetFields2 要取消的字段
     */
    protected function unsetResultValue($data, $unsetFields = "", $unsetFields2 = [])
    {
        $unsetFields_default = [
           'start_lat','start_lng','start_gid','startname','startpid'
          , 'end_lat','end_lng','end_gid','endname','endpid'
          ,'passengerid','carownid','passenger_id','driver_id'
        ];
        if (is_string($unsetFields) && $unsetFields=="list") {
            $unsetFields = [
              'p_companyname','d_companyname'
              ,'p_company_id','d_company_id'
              ,'p_department_id','d_department_id'
              ,'p_sex','d_sex'
              ,'like_count'
              // ,'d_im_id','p_im_id'
              // ,'start_longitude','start_latitude'
              ,'start_addressid'
              // ,'end_longitude','end_latitude'
              ,'end_addressid'
            ];
            $unsetFields = array_merge($unsetFields_default, $unsetFields);
        }
        if (is_string($unsetFields) && ($unsetFields=="" ||$unsetFields=="detail")) {
            $unsetFields = [];
            $unsetFields = array_merge($unsetFields_default, $unsetFields);
        }
        if (is_string($unsetFields) && $unsetFields=="detail_pb") {
            $unsetFields = [
              'p_companyname','d_companyname','d_im_id','p_im_id'
              ,'d_phone','d_mobile','d_full_department','d_company_id','d_department_id','d_department'
              ,'p_phone','p_mobile','p_full_department','p_company_id','p_department_id','p_department'
              ,'start_addressid'
              ,'end_addressid'
            ];
            $unsetFields = array_merge($unsetFields_default, $unsetFields);
        }
        if (is_array($unsetFields)) {
            foreach ($unsetFields as $key => $value) {
                unset($data[$value]);
            }
        }
        return $data;
    }


    /**
     * 通过id取得用户姓名
     * @param  integer $uid 用户id
     */
    protected function getUserName($uid)
    {
        $name = UserModel::where('uid', $uid)->value('name');
        return $name ? $name : "";
    }

    /**
     * 通过id取得用户姓名
     * @param  integer $uid      [对方id]
     * @param  string  $message  [发送的内容]
     */
    protected function pushMsg($uid, $message, $appid = 1)
    {
        if (!$uid || !$message) {
            return false;
        }
        $PushMessage = new PushMessage();
        if (is_array($uid)) {
            $res = [];
            foreach ($uid as $key => $value) {
                if (is_numeric($value)) {
                    $res[] = $PushMessage->add($value, $message, lang("Car pooling"), 101,101, 0);
                    // $PushMessage->push($value,$message,lang("Car pooling"),2);
                }
            }
        } elseif (is_numeric($uid)) {
            $res = $PushMessage->add($uid, $message, lang("Car pooling"), 101,101, 0);
            // $PushMessage->push($uid,$message,lang("Car pooling"),2);
        } else {
            return false;
        }
        // try {
        //     $pushRes = $PushMessage->push($uid, $message, lang("Car pooling"), $appid);
        //     $this->errorMsg = $pushRes;
        // } catch (\Exception $e) {
        //     $this->errorMsg = $e->getMessage();
        //     $pushRes =  $e->getMessage();
        // }
        return $res;
    }
}
