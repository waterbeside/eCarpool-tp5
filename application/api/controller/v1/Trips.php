<?php
namespace app\api\controller\v1;

use think\facade\Env;
use app\api\controller\ApiBase;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\model\Address;
use app\carpool\model\WallLike;
use app\carpool\model\user as UserModel;
use app\common\model\PullMessage;

use think\Db;

/**
 * 附件相关
 * Class Attachment
 * @package app\api\controller
 */
class Trips extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        $this->checkPassport(1);

    }

    /**
     * 我的行程
     */
    public function index($pagesize=20,$type=0,$fullData = 0){

      $userData = $this->getUserData();
      $uid = $userData['uid'];
      $extra_info = json_decode($userData['extra_info'],true);
      $merge_ids = isset($extra_info['merge_id']) && is_array($extra_info['merge_id']) && $type ==1  ? $extra_info['merge_id'] : [];

      $InfoModel = new InfoModel();
      $viewSql  =  $InfoModel->buildUnionSql($uid,$merge_ids);
      $fields = 't.infoid , t.love_wall_ID , t.time, t.trip_type , t.time, t.status, t.passengerid, t.carownid , t.seat_count,  t.subtime, t.map_type ';
      $fields .= ','.$this->buildUserFields('d');
      $fields .= ','.$this->buildUserFields('p');
      $fields .=  $this->buildAddressFields();
      $join = $this->buildTripJoins();

      if($type==1){
        $map = [
          ["t.time","<",date('YmdHi',strtotime('+15 minute'))],
          // ["t.go_time","<",strtotime('+15 minute')],
        ];
        $orderby = 't.time DESC, t.infoid DESC, t.love_wall_id DESC';
      }else{
        $map = [
          ["t.time",">",date('YmdHi',strtotime("-1 hour"))],
          // ["t.go_time",">",strtotime("-1 hour")],
        ];
        $orderby = 't.time ASC, t.infoid ASC, t.love_wall_id ASC';
      }


      $modelObj =  Db::connect('database_carpool')->table("($viewSql)" . ' t')->field($fields)->where($map)->join($join)->order($orderby);
      if(  $pagesize > 0 || $type > 0){
        $results =    $modelObj->paginate($pagesize, false,  ['query'=>request()->param()])->toArray();
        if(!$results['data']){
          return $this->jsonReturn(20002,$results,lang('No data'));
        }
        $datas = $results['data'];
        $pageData = [
          'total'=>$results['total'],
          'pageSize'=>$results['per_page'],
          'lastPage'=>$results['last_page'],
          'currentPage'=>$results['current_page'],
        ];
      }else{
        $datas =    $modelObj->select();
        if(!$datas){
          return $this->jsonReturn(20002,$datas,lang('No data'));
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

        $datas[$key] = $fullData ? $this->formatResultValue($value) : $this->unsetResultValue($this->formatResultValue($value),"list");

        // $datas[$key] = $this->formatResultValue($value,$merge_ids);
        $datas[$key]['show_owner']      = $value['infoid']>0 && $uid == $value['passengerid']  &&  $value['carownid'] > 0  ?  1 : 0;
        $datas[$key]['status'] = intval($value['status']);
        $data[$key]['took_count']   = $value['infoid'] > 0 ? 0 : InfoModel::where([['love_wall_ID','=',$value['love_wall_ID']],['status','<>',2]])->count() ; //取已坐数

      }
      $returnData = [
        'lists'=>$datas,
        'page' =>$pageData
      ];
      $this->jsonReturn(0,$returnData,"success");

    }


    /**
     * 历史行程
     */
    public function history($pagesize =20){
      return $this->unsetResultValue($this->index($pagesize,1,1));
    }

    /**
     * 墙上空座位
     */
    public function wall_list($pagesize=20,$keyword=""){
        $userData = $this->getUserData();
        $company_id = $userData['company_id'];
        $time_e = strtotime("+20 day");
        $time_s = strtotime("-1 hour");
        $map = [
          ['d.company_id','=',$company_id],
          // ['love_wall_ID','>',0],
          ['status','<',2],
          // ['go_time','between time',[date('YmdHi',$time_s),date('YmdHi',$time_e)]],
          ['time','<',(date('YmdHi',$time_e))],
          ['time','>',(date('YmdHi',$time_s))],
        ];
        if($keyword){
          $map[] = ['d.name|s.addressname|e.addressname|t.startname|t.endname','like',"%{$keyword}%"];
        }
        $fields = 't.time, t.status, t.seat_count';
        $fields .= ', t.love_wall_ID as id, t.subtime , t.carownid as driver_id  ';

        $fields .= ','.$this->buildUserFields('d');
        $fields .=  $this->buildAddressFields();
        $join = $this->buildTripJoins("s,e,d");


        $results = WallModel::alias('t')->field($fields)->join($join)->where($map)->order(' time DESC, t.love_wall_ID DESC  ')
        ->paginate($pagesize, false,  ['query'=>request()->param()])->toArray();
        if(!$results['data']){
          return $this->jsonReturn(20002,$results,lang('No data'));
        }
        $lists = [];
        foreach ($results['data'] as $key => $value) {
          $lists[$key] = $this->unsetResultValue($this->formatResultValue($value),"list");

          //取点赞数
          $lists[$key]['like_count']    = WallLike::where('love_wall_ID',$value['id'])->count();
          //取已坐数
          $lists[$key]['took_count']    =  InfoModel::where('love_wall_ID',$value['id'])->count();
        }
        $returnData = [
          'lists'=>$lists,
          'page' =>[
            'total'=>$results['total'],
            'pageSize'=>$results['per_page'],
            'lastPage'=>$results['last_page'],
            'currentPage'=>$results['current_page'],
          ]
        ];
        $this->jsonReturn(0,$returnData,"success");
    }


    /**
     * 空座位详情页
     * @param  integer $id 空座位id
     */
    public function wall_detail($id){
      $uid = $this->userBaseInfo['uid'];
      if(!$id){
        $this->jsonReturn(-1,[],'lost id');
        // return $this->error('lost id');
      }
      $fields = 't.time, t.status,  t.seat_count';
      $fields .= ', t.love_wall_ID as id ,t.love_wall_ID , t.subtime , t.carownid as driver_id  ';
      $fields .= ','.$this->buildUserFields('d');
      $fields .=  $this->buildAddressFields();
      $join = $this->buildTripJoins("s,e,d");
      $data = WallModel::alias('t')->field($fields)->join($join)->where("t.love_wall_ID",$id)->find();
      if(!$data){
        return $this->jsonReturn(20002,$data,lang('No data'));
      }

      $data = $this->unsetResultValue($this->formatResultValue($data),"detail");

      $data['uid']          = $uid;
      $countBaseMap = ['love_wall_ID','=',$data['love_wall_ID']];
      $data['took_count']       = InfoModel::where([$countBaseMap,['status','<',2]])->count(); //取已坐数
      $data['took_count_all']   = InfoModel::where([$countBaseMap,['status','<>',2]])->count() ; //取已坐数
      $data['hasTake']          = InfoModel::where([$countBaseMap,['status','<',2],['passengerid','=',$uid]])->count(); //查看是否已搭过此车主的车
      $data['hasTake_finish']   = InfoModel::where([$countBaseMap,['status','=',3],['passengerid','=',$uid]])->count();  //查看是否已搭过此车主的车
      // return $this->success('加载成功','',$data);
      $this->jsonReturn(0,$data,'success');
    }

    /**
     * 约车需求
     */
    public function info_list($keyword="",$status = 0 ,$pagesize=20, $wid = 0, $returnType = 1){
        $userData = $this->getUserData();
        $company_id = $userData['company_id'];


        $time_e = strtotime("+20 day");
        $time_s = strtotime("-1 hour");

        if($wid>0){
          $map = [
            ['love_wall_ID','=',$wid]
          ];
        }else{
          $map = [
            ['p.company_id','=',$company_id],
            // ['love_wall_ID','>',0],
            // ['go_time','between time',[date('YmdHi',$time_s),date('YmdHi',$time_e)]],
            ['time','<',(date('YmdHi',$time_e))],
            ['time','>',(date('YmdHi',$time_s))],
          ];
        }


        $map[] = $this->buildStatusMap($status);
        // dump($map);exit;

        if($keyword){
          $map[] = ['s.addressname|p.name|e.addressname|t.startname|t.endname','like',"%{$keyword}%"];
        }

        $fields = 't.time, t.status ';
        // $fields = ', FROM_UNIXTIME(t.go_time,'%Y-%M-%D %h:%i:%s') as format_time';
        $fields .= ',t.infoid as id, t.love_wall_ID , t.subtime , t.carownid as driver_id , t.passengerid as passenger_id  ';

        // $fields .= ','.$this->buildUserFields('d');
        $fields .= ','.$this->buildUserFields('p');
        $fields .=  $this->buildAddressFields();
        $join = $this->buildTripJoins("s,e,p");


        $modelObj =  InfoModel::alias('t')->field($fields)->join($join)->where($map)->order('time DESC, t.infoid DESC ');
        // $sql = $modelObj->fetchSql()->select();
        if(  $pagesize > 0 || $wid > 0){
          $results =    $modelObj->paginate($pagesize, false,  ['query'=>request()->param()])->toArray();

          if(!$results['data']){
            return $this->jsonReturn(20002,$results,lang('No data'));
          }
          $datas = $results['data'];
          $pageData = [
            'total'=>$results['total'],
            'pageSize'=>$results['per_page'],
            'lastPage'=>$results['last_page'],
            'currentPage'=>$results['current_page'],
          ];
        }else{
          $datas =    $modelObj->select();
          if(!$datas){
            return $this->jsonReturn(20002,$datas,lang('No data'));
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
          $lists[$key] = $this->unsetResultValue($this->formatResultValue($value),"list");

        }
        $returnData = [
          'lists'=>$lists,
          'page' =>$pageData,
        ];
        return $returnType ? $this->jsonReturn(0,$returnData,"success") : $returnData;
    }


    /**
     * 行程详情页
     * @param  integer $id 行程id
     */
    public function info_detail($id){
      $uid = $this->userBaseInfo['uid'];
      if(!$id){
        $this->jsonReturn(-1,[],'lost id');
        // return $this->error('lost id');
      }

      $fields = 't.time, t.status';
      $fields .= ',t.infoid as id, t.infoid , t.love_wall_ID , t.subtime , t.carownid as driver_id, t.passengerid as passenger_id  ';

      $fields .= ','.$this->buildUserFields('d');
      $fields .= ','.$this->buildUserFields('p');

      $fields .=  $this->buildAddressFields();
      $join = $this->buildTripJoins();
      $data = InfoModel::alias('t')->field($fields)->join($join)->where("t.infoid",$id)->find();
      if(!$data){
        return $this->jsonReturn(20002,$data,lang('No data'));
      }
      $data = $this->unsetResultValue($this->formatResultValue($data),"detail");
      $data['uid']          = $uid;

      // return $this->success('加载成功','',$data);
      $this->jsonReturn(0,$data,'success');
    }



    /**
     * 发布行程
     * @param string $from  行程类型 wall|info
     */
    public function add($from){
      if(!in_array($from,['wall','info'])){
        $this->jsonReturn(-10001,[],'参数有误');
      }
      $userData = $this->getUserData();
      $uid = $userData['uid']; //取得用户id


      $time                 = input('post.time');
      $map_type             = input('post.map_type');
      // $datas['startpid']    = input('post.startpid');
      // $datas['endpid']      = input('post.endpid');
      $datas['start']       = input('post.start');
      $datas['end']         = input('post.end');
      $datas['seat_count']  = input('post.seat_count');
      $datas['distance']    = input('post.distance',0);
      $datas['time']        = is_numeric($time) ? date('YmdHi',$time) : date('YmdHi',strtotime($time.":00"));

// $this->jsonReturn(-1,$datas);
// dump($datas);exit;

      if(empty($time)){
        $this->jsonReturn(-1,[],lang('Please select date and time'));
      }
      if($from=="wall" && empty($datas['seat_count'])){
        $this->jsonReturn(-1,[],lang('The number of empty seats cannot be empty'));
      }

      $AddressModel = new Address();
      $InfoModel    = new InfoModel();
      $WallModel    = new WallModel();

      //计算前后范围内有没有重复行程
      if(!$InfoModel->checkRepetition($time,$uid,120)){
        $this->jsonReturn(-1,[],$InfoModel->errorMsg);
      }
      if(!$WallModel->checkRepetition($time,$uid,120)){
        $this->jsonReturn(-1,[],$WalloModel->errorMsg);
      }

      $createAddress = array();
      //处理起点
      if(!$datas['start']['addressid'] && !$map_type){
        $startDatas = $datas['start'];
        $startDatas['company_id'] = $userData['company_id'];
        $startRes = $AddressModel->addFromTrips($startDatas);
        if(!$startRes){
          $this->jsonReturn(-1,[],lang("The point of departure must not be empty"));
        }
        $datas['start']['addressid'] = $startRes['addressid'];
        $createAddress[0] = $startRes;
      }

      //处理终点
      if(!$datas['end']['addressid'] && !$map_type ){
        $endDatas = $datas['end'];
        $endDatas['company_id'] = $userData['company_id'];
        $endRes = $AddressModel->addFromTrips($endDatas);
        if(!$endRes){
          $this->jsonReturn(-1,[],lang("The destination cannot be empty"));
        }
        $datas['end']['addressid'] = $endRes['addressid'];
        $createAddress[1] = $endRes;
      }

      //检查出发时间是否已经过了
      if(date('YmdHi',time()) > $datas['time']){
        $this->jsonReturn(-1,[],lang("The departure time has passed. Please select the time again"));
        // $this->error("出发时间已经过了<br /> 请重选时间");
      }






      //检查时间是否上下班时间
      $hourMin = date('Hi',strtotime($datas['time']));
      $datas['type']   = 2;
      if( $hourMin > 400 && $hourMin < 1000 ){
        $datas['type'] =  0 ;
      }elseif($hourMin > 1600 && $hourMin < 2200){
        $datas['type'] =  1 ;
      }

      $inputData = [
        'status'    => 0,
        'subtime'   => date('YmdHi'),
        'time'      => $datas['time'],
        'type'      => $datas['type'],
        'map_type'  => $map_type,
        'distance'  => $datas['distance'],
        'startpid'  => $map_type ? (isset($datas['start']['gid']) &&  $datas['start']['gid'] ? -1 : 0 ) : $datas['start']['addressid'] ,
        'endpid'  => $map_type ? (isset($datas['end']['gid']) &&  $datas['end']['gid'] ? -1 : 0 ) : $datas['end']['addressid'] ,
        'startname'  => $datas['start']['addressname'] ,
        'start_latlng'  => Db::raw("geomfromtext('point(".$datas['start']['longitude']." ".$datas['start']['latitude'].")')"),
        'endname'  => $datas['end']['addressname'] ,
        'end_latlng'  => Db::raw("geomfromtext('point(".$datas['end']['longitude']." ".$datas['end']['latitude'].")')"),

      ];
      if($map_type){
        if(isset($datas['start']['gid']) &&  $datas['start']['gid']){
          $inputData['start_gid'] = $datas['start']['gid'];
        }
        if(isset($datas['end']['gid']) &&  $datas['end']['gid']){
          $inputData['end_gid'] = $datas['start']['gid'];
        }
      }

      if($from == "wall"){
        $inputData['carownid'] = $uid;
        $inputData['seat_count']  = $datas['seat_count'];
        $result = $WallModel->insertGetId($inputData);

      }elseif($from == "info"){
        $inputData['passengerid'] = $uid;
        $inputData['carownid']    = -1;
        $result = $InfoModel->insertGetId($inputData);
      }

      // var_dump($model->attributes['infoid']);
      if ($result) {
        $this->jsonReturn(0,['createAddress'=>$createAddress,'id'=>$result],'success');
      }else{
        $this->jsonReturn(-1,lang('Fail'));
      }

    }

    /**
     * 乘客列表
     * @param  integer          $id 空座位id
     * @param  integer|string   $status 状态筛选
     */
    public function passengers($id , $status = "neq|2"){
      $res =  $this->info_list("",$status ,0, $id,0);
      if($res){
        foreach ($res['lists'] as $key => $value) {
          $res['lists'][$key] = $this->unsetResultValue($value,['love_wall_ID']);
        }
        if(isset($res['page'])) unset($res['page']);
      }

      $this->jsonReturn(0,$res,'success');
    }

    /**
     * 改变更新字段
     * @param string $from  行程类型 wall|info
     */
    public function change($from="",$id,$type=""){
      $type = mb_strtolower($type);
      $from = mb_strtolower($from);

      if(!in_array($type,['cancel','finish','riding','hitchhiking','pickup','startaddress','endaddress']) || !$id){

        $this->jsonReturn(-10001,[$type,$id],lang('Parameter error'));
      }
      if($from=="wall"){
        $Model    = new WallModel();
      }else if($from=="info"){
        $Model    = new InfoModel();
      }else{
        $this->jsonReturn(-10001,[],lang('Parameter error'));
      }
      $userData = $this->getUserData();
      $uid = $userData['uid']; //取得用户id
      $fields = "*,x(start_latlng) as start_lng , y(start_latlng) as start_lat,x(end_latlng) as end_lng,y(end_latlng) as end_lat";
      $datas = $Model->field($fields)->get($id);
      if(!$datas){
        return $this->jsonReturn(20002,[],lang('No data'));
      }

      /*********** 完成或取消 ***********/
      if(in_array($type,["cancel","finish"])){
        //检查是否已取消或完成
        if($datas->status > 1){
          return $this->jsonReturn(-1,[],lang('The trip has been completed or cancelled. Operation is not allowed'));
        }
        //检查时间
        if($type == "finish" && strtotime($datas->time.'00')  > time()){
          return $this->jsonReturn(-1,[],lang('The trip not started, unable to operate'));
        }
        //检查是否允许操作
        if($from=="info" && $datas->carownid != $uid && $datas->passengerid != $uid){
            return $this->jsonReturn(-1,[],lang('No permission'));
        }
        if($from=="wall" && $datas->carownid != $uid){ //从空座位取消
            $infoDatas = InfoModel::where([["love_wall_ID",'=',$id],['passengerid',"=",$uid]])->order("status")->find();
            if(!$infoDatas){
              return $this->jsonReturn(-1,[],lang('No permission'));
            }
            if($infoDatas['status'] > 1){
              return $this->jsonReturn(-1,[],lang('The trip has been completed or cancelled. Operation is not allowed'));
            }
            return $this->change("info",$infoDatas['infoid'],$type); exit;
        }
        //保存更新的数据
        $datas->status      = $type == "cancel" ? 2 : 3;
        $datas->cancel_time  = date('YmdHi',time());
        if($type == "cancel") $datas->cancel_user_id =$uid;
        $res = $datas->save();
        if(!$res){
          return $this->jsonReturn(-1,[],lang('Fail'));
        }
        if($from=="wall" && $datas->carownid == $uid ){ //如果是司机操作空座位，则同时对乘客行程进行操作。
          $upInfoData = $type == "finish" ? ["status"=>3] : ["status"=>0,"love_wall_ID"=>NULL,"carownid"=>-1] ;
          InfoModel::where([["love_wall_ID",'=',$id],["status","<",2]])->update($upInfoData);
          if($type == "canncel"){  //给乘客发送取消的推送消息
            $passengerids = InfoModel::where([["love_wall_ID",'=',$id],["status","<",2]])->column('passengerid');
            if($passengerids){
              $cancel_push_msg = lang("The driver {:name} cancelled the trip",["name"=>$userData['name']]) ;
              foreach ($passengerids as $key => $value) {
                $this->pushMsg($value,$cancel_push_msg);
              }
            }
          }
        }else{
          if($datas->carownid == $uid){  //如果司机取消，则推送给乘客
            $this->pushMsg( $datas->passengerid,lang("The driver {:name} cancelled the trip",["name"=>$userData['name']]));
          }else{ //如果乘客取消，则推送给司机
            $this->pushMsg( $datas->carownid,lang("The passenger {:name} cancelled the trip",["name"=>$userData['name']]));
          }
        }
        return $this->jsonReturn(0,[],"success");

      }

      /*********** riding 搭车  ***********/
      if($type == "riding" || $type = "hitchhiking"){
        if($from !="wall"){
          return $this->jsonReturn(-10001,[],lang('Parameter error'));
        }

        if( $datas->status == 2){
          return $this->jsonReturn(-1,[],lang('Failed, the owner has cancelled the trip'));
          // return $this->error('或车主或已取消空座位，<br />请选择其它司机。');
        }
        if($datas->status == 3){
          return $this->jsonReturn(-1,[],lang('Failed, the trip has ended'));
        }
        // 断定是否自己搭自己
        if($datas->carownid == $uid){
          return $this->jsonReturn(-1,[],lang('You can`t take your own'));
        }

        $seat_count = $datas->seat_count;
        $checkInfoMap = [['love_wall_ID','=',$id],['status','<>',2]];
        $took_count = InfoModel::where($checkInfoMap)->count();
        if($took_count >= $seat_count){
          return $this->jsonReturn(-1,[],lang('Failed, seat is full'));
        }
        $checkInfoMap[] = ['passengerid','=',$uid];
        $checkHasTake = InfoModel::where($checkInfoMap)->count();
        if($checkHasTake>0){
          return $this->jsonReturn(-1,[],lang('You have already taken this trip'));
        }
        $setInfoDatas = array(
          'passengerid'   => $uid,
          'carownid'      => $datas->carownid,
          'love_wall_ID'  => $id,
          'subtime'       => date('YmdHi',time()),
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
        if($datas->start_lat && $datas->start_lng ){
          $setInfoDatas['start_latlng'] = Db::raw("geomfromtext('point(".$datas->start_lng." ".$datas->start_lat.")')");
          $setInfoDatas['end_latlng'] = Db::raw("geomfromtext('point(".$datas->end_lng." ".$datas->end_lat.")')");
        }


        $res = InfoModel::insertGetId($setInfoDatas);
        if(!$res){
          return $this->jsonReturn(-1,[],lang('Fail'));
        }
        $datas->status = 1 ;
        $datas->save();
        $this->pushMsg($datas->carownid,lang('{:name} took your car',["name"=>$userData['name']]));
        return $this->jsonReturn(0,['infoid'=>$res],'success');
      }

      /*********** pickup 接受需求  ***********/
      if($type == "pickup" ){
        if($from !="info"){
          return $this->jsonReturn(-10001,[],lang('Parameter error'));
        }
        // 断定是否自己搭自己
        if($datas->passengerid == $uid){
          return $this->jsonReturn(-1,[],lang('You can`t take your own'));
        }
        if($datas->status > 0 ){
          return $this->jsonReturn(-1,[],lang("This requirement has been picked up or cancelled"));
          // return $this->error('此需求已被人搭载或被取消');
        }
        $datas->carownid = $uid;
        $datas->status = 1;
        $res = $datas->save();
        if($res){
          $this->pushMsg($datas->passengerid,lang('{:name} accepted your ride requst',["name"=>$userData['name']]));
          return $this->jsonReturn(0,[],'success');
        }
      }

      /*********** startaddress|endaddress 修改起点终点  ***********/
      if(in_array($type,["startaddress","endaddress"]) && $from =="info"){
        if($datas->passengerid != $uid){
            return $this->jsonReturn(-1,[],lang('No permission'));
        }
        $addressDatas       = input('post.address');
        $map_type       =  $addressDatas['map_type'];
        $addressSign = $type == "startaddress" ? "start" : "end";
        //处理起点
        if(!$addressDatas['addressid'] && !$map_type){
          $addressDatas['company_id'] = $userData['company_id'];
          $addressRes = $AddressModel->addFromTrips($addressDatas);
          if(!$addressRes){
            $this->jsonReturn(-1,[],lang("The adress must not be empty"));
          }
          $addressDatas['addressid'] = $startRes['addressid'];
          $createAddress[0] = $startRes;
        }
        $inputData = [
          $addressSign.'pid'  => $map_type ? (isset($addressDatas['gid']) &&  $addressDatas['gid'] ? -1 : 0 ) : $addressDatas['addressid'] ,
          $addressSign.'name'  => $addressDatas['addressname'] ,
          $addressSign.'_latlng'  => Db::raw("geomfromtext('point(".$addressDatas['longitude']." ".$addressDatas['latitude'].")')"),
        ];
        if($map_type){
          if(isset($addressDatas['gid']) &&  $addressDatas['gid']){
            $inputData[$addressSign.'_gid'] = $addressDatas['gid'];
          }
        }
        $res = $datas->save($inputData);
        if($res){
          return $this->jsonReturn(0,[],'success');
        }

      }


      return $this->jsonReturn(-1,[],lang("Fail"));
    }


    /**
     * 取消行程
     * @param string $from  行程类型 wall|info
     */
    public function cancel($from,$id){
       return $this->change($from,$id,'cancel');
    }



    /* ----------------------------------------------------------------- */

    /**
     * 创件状态筛选map
     */
    protected function buildStatusMap($status){
      $statusExp = '=';
      if(is_string($status) && strpos($status,'|')){
        $statusArray = explode('|',$status);
        if(count($statusArray)>1){
          $statusExp = $statusArray[0];
          $status = $statusArray[1];
        }else{
          $status = $status[0];
        }
      }
      if(is_string($status) && strpos($status,',')){
        $status = explode(',',$status);
      }
      if(is_array($status) && $statusExp == "="){
          $statusExp = "in";
      }
      if(in_array(mb_strtolower($statusExp),['=','<=','>=','<','>','<>','in','eq','neq','not in','lt','gt','egt','elt'])){
        return ['status',$statusExp,$status];
      }else{
        return ['status',"=",$status];
      }
    }


    /**
     * 创件要select的用户字段
     */
    protected function buildUserFields($a="u",$fields=[]){
      $format_array = [];
      $fields = !empty($fields) ? $fields : ['uid','loginname','name','phone','mobile','Department','sex','company_id','department_id','companyname','imgpath','carnumber'];

      foreach ($fields as $key => $value) {
        $format_array[$key] = $a.".".$value." as ".$a."_".mb_strtolower($value);
      }
      return join(",",$format_array);
    }

    /**
     * 创件要select的地址字段
     */
    protected function buildAddressFields($fields="",$start_latlng = false){
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
    protected function buildTripJoins($filter="d,p,s,e"){
      if(is_string($filter)){
        $filter = explode(",",$filter);
      }
      $join = [];
      if(is_array($filter)){
        foreach ($filter as $key => $value) {
          $filter[$key] = mb_strtolower($value);
        }
        if(in_array('s',$filter) || in_array('start',$filter))      $join[] = ['address s','s.addressid = t.startpid', 'left'];
        if(in_array('e',$filter) || in_array('end',$filter))        $join[] = ['address e','e.addressid = t.endpid', 'left'];
        if(in_array('d',$filter) || in_array('driver',$filter))    $join[] = ['user d','d.uid = t.carownid', 'left'];
        if(in_array('p',$filter) || in_array('passenger',$filter)) $join[] = ['user p','p.uid = t.passengerid', 'left'];
      }
      return $join;
    }


    /**
     * 格式化结果字段
     */
    protected function formatResultValue($value,$merge_ids = [] ,$unDo = []){
      $value_format = $value;
      $value_format['subtime'] = strtotime($value['subtime']);
      // $value_format['go_time'] = $value['go_time'] ?  $value['go_time'] : strtotime($value['time']."00");
      // $value_format['time'] = date('Y-m-d H:i',strtotime($value['time'].'00'));
      // $value_format['time'] = $value_format['go_time'];
      $value_format['time'] = strtotime($value['time'].'00');
      if(!empty($merge_ids)){
        if(!in_array('p',$unDo) && isset($value['p_uid']) &&  in_array($value['p_uid'],$merge_ids)){
          $value_format['p_uid'] = $uid;
          $value_format['passengerid'] = $uid;
          $value_format['p_name'] = $userData['name'];
        }
        if(!in_array('d',$unDo) && isset($value['d_uid']) && in_array($value['d_uid'],$merge_ids)){
          $value_format['d_uid'] = $uid;
          $value_format['carownid'] = $uid;
          $value_format['d_name'] = $userData['name'];
        }
      }
      if(!is_numeric($value['startpid']) || $value['startpid'] < 1 ){
        $value_format['start_addressid'] = $value['startpid'];
        $value_format['start_addressname'] = $value['startname'];
        $value_format['start_longitude'] = $value['start_lng'];
        $value_format['start_latitude'] = $value['start_lat'];
      }
      if(!is_numeric($value['endpid']) || $value['endpid'] < 1 ){
        $value_format['end_addressid'] = $value['endpid'];
        $value_format['end_addressname'] = $value['endname'];
        $value_format['end_longitude'] = $value['end_lng'];
        $value_format['end_latitude'] = $value['end_lat'];
      }
      return $value_format;
    }


    /**
     * 取消显示的字段
     * @param array        $data         数据
     * @param string|array $unsetFields  要取消的字段
     * @param array        $unsetFields2 要取消的字段
     */
    protected function unsetResultValue($data,$unsetFields = "",$unsetFields2 = []){
      $unsetFields_default = [
         'start_lat','start_lng','start_gid','startname','startpid'
        , 'end_lat','end_lng','end_gid','endname','endpid'
        ,'passengerid','carownid','passenger_id','driver_id'
      ];
      if(is_string($unsetFields) && $unsetFields=="list"){
        $unsetFields = [
          'p_companyname','d_companyname'
          ,'start_longitude','start_latitude','start_addressid'
         ,'end_longitude','end_latitude','end_addressid'
        ];
        $unsetFields = array_merge($unsetFields_default,$unsetFields);
      }
      if(is_string($unsetFields) && ($unsetFields=="" ||$unsetFields=="detail") ){
        $unsetFields = [];
        $unsetFields = array_merge($unsetFields_default,$unsetFields);
      }
      if(is_array($unsetFields)){
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
    protected function getUserName($uid){
      $name = UserModel::where('uid',$uid)->value('name');
      return $name ? $name : "";
    }

    /**
     * 通过id取得用户姓名
     * @param  integer $uid      [对方id]
     * @param  string  $message  [发送的内容]
     */
    protected function pushMsg($uid,$message){
      if(!$uid || !$message){
        return false;
      }
      $res = (new PullMessage())->add($uid,$message,lang("Car pooling"),101,1);
      return $res;
    }


}
