<?php
namespace app\api\controller\v1;

use think\facade\Env;
use app\api\controller\ApiBase;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\model\Address;
use app\carpool\model\user as UserModel;
use app\carpool\model\UserPosition as UserPositionModel;
use app\carpool\model\Grade as GradeModel;

use app\carpool\service\Trips as TripsService;
use app\carpool\service\TripsChange as TripsChangeService;
use app\carpool\service\TripsList as TripsListService;
use app\carpool\service\TripsDetail as TripsDetailService;

use my\RedisData;
use think\Db;

/**
 * 行程相关
 * Class Trips
 * @package app\api\controller
 */
class Trips extends ApiBase
{

    /**
     * 我的行程
     */
    public function index($pagesize=20, $type=0, $fullData = 0)
    {   
        $page = input('param.page',1);
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];

        $redis            = new RedisData();
        $TripsListService = new TripsListService();

        if (!$type) {
          $cacheKey = "carpool:trips:my:u{$uid}:pz{$pagesize}_p{$page}_fd{$fullData}";
          $cacheExp = 5;
          $cacheData = $redis->get($cacheKey);
          if($cacheData){
            if($cacheData == "-1"){
              return $this->jsonReturn(20002, lang('No data'));
            }
            $returnData = json_decode($cacheData,true);
            return $this->jsonReturn(0, $returnData, "success");
          }
        } 
        $returnData = $TripsListService->myList($userData,$pagesize,$type);
       
        if($returnData === false){
          if(!$type){
            $redis->setex($cacheKey, $cacheExp, -1);
          }
          return $this->jsonReturn(20002, lang('No data'));
        }
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
        $page = input('param.page',1);
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];

        $redis            = new RedisData();
        $TripsListService = new TripsListService();

        // 查缓存
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

        $returnData = $TripsListService->history($userData,$pagesize);
        if ($returnData === false) {
            $redis->setex($cacheKey, $cacheExp, -1);
            return $this->jsonReturn(20002, lang('No data'));
        }

        $redis->setex($cacheKey, $cacheExp, json_encode($returnData));
        $this->jsonReturn(0, $returnData, "success");
        // $TripsService->unsetResultValue($this->index($pagesize, 1, 1));
    }


    /**
     * 墙上空座位
     */
    public function wall_list($pagesize=20, $keyword="", $city=null, $map_type=NULL)
    {
        $TripsListService = new TripsListService();
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

        $returnData = $TripsListService->wall_list($map,$pagesize);
        if ($returnData === false) {
          return $this->jsonReturn(20002, lang('No data'));
        }
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

        $TripsService = new TripsService();
        $TripsDetailService = new TripsDetailService();

      
        $data  = $TripsDetailService->detail('wall',$id,$pb);
        $app_id = $data['map_type'] ? 1 : 2;
        $countBaseMap = ['love_wall_ID','=',$data['love_wall_ID']];

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
        $TripsService = new TripsService();
        $TripsListService = new TripsListService();
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

        $map[] = $TripsService->buildStatusMap($status);
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

        $returnData = $TripsListService->info_list($map,$pagesize,$wid,$orderby);
        if ($returnData === false) {
          return $this->jsonReturn(20002, lang('No data'));
        }
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
        $TripsDetailService = new TripsDetailService();

        $data  = $TripsDetailService->detail('info',$id, $pb);
        $app_id = $data['map_type'] ? 1 : 2;

        if (!$pb) {
            $data['uid']          = $uid;
            //查询评分情况
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
     * 乘客列表
     * @param  integer          $id 空座位id
     * @param  integer|string   $status 状态筛选
     */
    public function passengers($id, $status = "neq|2")
    {
      $TripsService = new TripsService();
      $res =  $this->info_list("", $status, 0, $id, 0, 'status ASC, time ASC');
      if ($res) {
        foreach ($res['lists'] as $key => $value) {
          $res['lists'][$key] = $TripsService->unsetResultValue($value, ['love_wall_ID']);
        }
        if (isset($res['page'])) {
          unset($res['page']);
        }
      }
      $this->jsonReturn(0, $res, 'success');
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
    
        $datas['start']       = input('post.start');
        $datas['start']       = is_array($datas['start']) ? $datas['start'] : json_decode($datas['start'],true);
        $datas['end']         = input('post.end');
        $datas['end']         = is_array($datas['end']) ? $datas['end'] : json_decode($datas['end'],true);
        $datas['seat_count']  = input('post.seat_count');
        $datas['distance']    = input('post.distance', 0);
        $datas['time']        = is_numeric($time) ? date('YmdHi', $time) : date('YmdHi', strtotime($time.":00"));

        if (empty($time)) {
            $this->jsonReturn(-1, [], lang('Please select date and time'));
        }
        if ($from=="wall" && empty($datas['seat_count'])) {
            $this->jsonReturn(-1, [], lang('The number of empty seats cannot be empty'));
        }

        $TripsService = new TripsService();
        $InfoModel    = new InfoModel();
        $WallModel    = new WallModel();

        //检查出发时间是否已经过了
        if (date('YmdHi', time()) > $datas['time']) {
          $this->jsonReturn(-1, [], lang("The departure time has passed. Please select the time again"));
        }

        //计算前后范围内有没有重复行程
        if ($InfoModel->checkRepetition($time, $uid, 60*5)) {
            $this->jsonReturn(30007, [], $InfoModel->errorMsg);
        }
        if ($WallModel->checkRepetition($time, $uid, 60*5)) {
            $this->jsonReturn(30007, [], $WallModel->errorMsg);
        }
        
        $createAddress = array();

        //处理起终点
        if (!$map_type) {
          $createAddress = $TripsService->createAddress($datas,$userData);
          if($createAddress === false){
            $this->jsonReturn(-1, [], $TripsService->errorMsg);            
          }else{
            if(isset($createAddress['start'])){
              $datas['start']['addressid'] = $createAddress['start']['addressid'];
            }
            if(isset($createAddress['end'])){
              $datas['end']['addressid'] = $createAddress['end']['addressid'];
            }
          }
        }

        //检查时间是否上下班时间
        $hourMin = date('Hi', strtotime($datas['time']));
        $datas['type']   = 2;
        if ($hourMin > 400 && $hourMin < 1000) {
            $datas['type'] =  0 ;
        } elseif ($hourMin > 1600 && $hourMin < 2200) {
            $datas['type'] =  1 ;
        }

        $inputData = $TripsService->createTripBaseData($datas,$map_type);

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
     * 改变更新字段
     * @param string $from  行程类型 wall|info
     */
    public function change($from="", $id, $type="",$step=0)
    {
        $this->checkPassport(1);
        $type = mb_strtolower($type);
        $from = mb_strtolower($from);

        $TripsService = new TripsService();
        $TripsChangeService = new TripsChangeService();
        $WallModel    = new WallModel();

        //检查参数
        $checkData = [
          'from'=>$from,
          'id'=>$id,
          'type'=>$type,
          'step'=>$step,
        ];
        if(!$TripsChangeService->checkParamData($checkData)){
          return $this->jsonReturn(992, [], $TripsChangeService->errorMsg);          
        }

        //验证用户
        $userData = $this->getUserData(1);
        $uid = $userData['uid']; //取得用户id

        //查询行程数据
        $datas = $TripsChangeService->getData($from,$id);
        if (!$datas) {
            return $this->jsonReturn(20002, lang('No data'));
        }
        $map_type = $datas->map_type;
        $isDriver    = $datas->carownid == $uid ? true : false; //是否司机操作

        //取得行程数据后再验证
        $checkData['tripData'] = $datas;
        $checkData['userData'] = $userData;
        $checkData['uid'] = $uid;
        
        $checkRes = $TripsChangeService->checkAfterData($checkData);
        if(!$checkRes){
          if($TripsChangeService->errorCode == 1){
            //如果是乘客从空座位操作, 则查出infoid，递归到from = info操作。
            return $this->change("info", $TripsChangeService->data['infoid'], $type);
          }
          return $this->jsonReturn($TripsChangeService->errorCode, $TripsChangeService->errorMsg);
        }
        

        /*********** 完成或取消或上车 ***********/
        if (in_array($type, ["cancel","finish","get_on"])) {
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

            // 顺便检查词机空座位状态并更正 -----2019-04-25
            if ($type == "cancel" && !$isDriver && $from == "info") {
              $took_count = InfoModel::where([["love_wall_ID",'=',$id],["status","in",[0,1,3,4]]])->count();
              if($took_count === 0){ //如果发现没有有效乘客，则更改空座位状态为0;
                  WallModel::where(["love_wall_ID",'=',$datas->love_wall_ID])->update(["status"=>0]);
              }
            }

            if ($from=="wall" && $isDriver) { //如果是司机操作空座位，则同时对乘客行程进行操作。
                $upInfoData = $type == "finish" ? ["status"=>3] : ["status"=>0,"love_wall_ID"=>null,"carownid"=>-1] ;
                InfoModel::where([["love_wall_ID",'=',$id],["status","in",[0,1,4]]])->update($upInfoData);
            }

            //如果是取消或上车操作，执行推送消息
            if ($type == "cancel" || $type == "get_on") {
                $TripsChangeService->pushMsg($checkData);
            }
            $extra = $TripsChangeService->errorMsg ? ['pushMsg'=>$TripsChangeService->errorMsg] : [];
            return $this->jsonReturn(0, [], "success", $extra);
        }

        /*********** riding 搭车  ***********/
        if ($type == "riding" || $type == "hitchhiking") {
            $res = $TripsChangeService->riding($datas,$uid);
            if (!$res) {
                return $this->jsonReturn(-1, [], lang('Fail'));
            }
            $datas->status = 1 ;
            $datas->save();

            $TripsChangeService->pushMsg($checkData);
            $extra = $TripsChangeService->errorMsg ? ['pushMsg'=>$TripsChangeService->errorMsg] : [];
            return $this->jsonReturn(0, ['infoid'=>$res], 'success', $extra);
        }

        /*********** pickup 接受需求  ***********/
        if ($type == "pickup") {
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
              $TripsChangeService->pushMsg($checkData);
              $extra = $TripsChangeService->errorMsg ? ['pushMsg'=>$TripsChangeService->errorMsg] : [];
              return $this->jsonReturn(0, [], 'success',$extra);
            }
        }

        /*********** startaddress|endaddress 修改起点终点  ***********/
        if (in_array($type, ["startaddress","endaddress"]) && $from =="info") {
            
            $addressDatas       = input('post.address');
            $map_type       =  $addressDatas['map_type'];
            $addressSign = $type == "startaddress" ? "start" : "end";
            //处理站点
            if (!$addressDatas['addressid'] && !$map_type) {
              $addressRes = $TripsService->createOneAddress($addressDatas,$userData);
              if (!$addressRes) {
                  $this->jsonReturn(-1, [], lang("The adress must not be empty"));
              }
              $addressDatas['addressid'] = $addressRes['addressid'];
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
      $TripsService = new TripsService();
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
        $TripsService = new TripsService();

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

}
