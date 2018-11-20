<?php
namespace app\api\controller\v1;

use think\facade\Env;
use app\api\controller\ApiBase;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\model\WallLike;

use think\Db;

/**
 * 附件相关
 * Class Attachment
 * @package app\api\controller
 */
class trips extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        $this->checkPassport(1);

    }

    /**
     * 我的行程
     */
    public function index($pagesize=20,$keyword=""){

      $userData = $this->getUserData();
      $uid = $userData['uid'];
      $extra_info = json_decode($userData['extra_info'],true);
      $merge_ids = isset($extra_info['merge_id']) && is_array($extra_info['merge_id'])  ? $extra_info['merge_id'] : [];

      $InfoModel = new InfoModel();

      $viewSql  =  $InfoModel->buildUnionSql($uid,$merge_ids);

      $fields = 't.infoid , t.love_wall_ID , t.time, t.trip_type , t.time, t.status, t.passengerid, t.carownid , t.seat_count, t.go_time, t.subtime, t.map_type ';

      $fields .= ','.$this->formatUserFields('d');
      $fields .= ','.$this->formatUserFields('p');
      $fields .=  $this->formatAddressFields();
      $join = [];
      $join[] = ['address s','s.addressid = t.startpid', 'left'];
      $join[] = ['address e','e.addressid = t.endpid', 'left'];
      $join[] = ['user d','d.uid = t.carownid', 'left'];
      $join[] = ['user p','p.uid = t.passengerid', 'left'];
      $map = [
        ["t.time",">",date('YmdHi',strtotime("-1 hour"))],
        // ["t.go_time",">",strtotime("-1 hour")],
      ];

      $modelObj =  Db::connect('database_carpool')->table("($viewSql)" . ' t')->field($fields)->where($map)->join($join)->order('t.time DESC, t.infoid DESC, t.love_wall_id DESC');
      if(  $pagesize > 0 ){
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
        $datas[$key] = $this->formatResultValue($value,$merge_ids);
        if($value['infoid']>0){
          $datas[$key]['show_owner']      = $uid == $value['passengerid']  &&  $value['carownid'] > 0  ?  1 : 0;
        }

      }
      $returnData = [
        'lists'=>$datas,
        'page' =>$pageData
      ];
      $this->jsonReturn(0,$returnData,"success");

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
        $fields = 't.time, t.status, t.go_time, t.seat_count';
        $fields .= ', t.love_wall_ID as id, t.subtime , t.carownid as driver_id  ';

        $fields .= ','.$this->formatUserFields('d');
        $fields .=  $this->formatAddressFields();
        $join[] = ['address s','s.addressid = t.startpid', 'left'];
        $join[] = ['address e','e.addressid = t.endpid', 'left'];
        $join[] = ['user d','d.uid = t.carownid', 'left'];
        // $join[] = ['user p','p.uid = t.passengerid', 'left'];

        $results = WallModel::alias('t')->field($fields)->join($join)->where($map)->order(' time DESC, t.love_wall_ID DESC  ')
        ->paginate($pagesize, false,  ['query'=>request()->param()])->toArray();
        if(!$results['data']){
          return $this->jsonReturn(20002,$results,lang('No data'));
        }
        $lists = [];
        foreach ($results['data'] as $key => $value) {
          $lists[$key] = $this->formatResultValue($value);
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

      $fields = 't.time, t.status, t.go_time, t.seat_count';
      $fields .= ', t.love_wall_ID as id ,t.love_wall_ID , t.subtime , t.carownid as driver_id  ';

      $fields .= ','.$this->formatUserFields('d');
      $fields .=  $this->formatAddressFields();
      $join[] = ['address s','s.addressid = t.startpid', 'left'];
      $join[] = ['address e','e.addressid = t.endpid', 'left'];
      $join[] = ['user d','d.uid = t.carownid', 'left'];
      // $join[] = ['user p','p.uid = t.passengerid', 'left'];
      $data = WallModel::alias('t')->field($fields)->join($join)->where("t.love_wall_ID",$id)->find();
      if(!$data){
        return $this->jsonReturn(20002,$data,lang('No data'));
      }
      $data = $this->formatResultValue($data);


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
    public function info_list($pagesize=20,$keyword=""){
        $userData = $this->getUserData();
        $company_id = $userData['company_id'];
        $time_e = strtotime("+20 day");
        $time_s = strtotime("-1 hour");
        $map = [
          ['p.company_id','=',$company_id],
          // ['love_wall_ID','>',0],
          ['status','=',0],
          // ['go_time','between time',[date('YmdHi',$time_s),date('YmdHi',$time_e)]],
          ['time','<',(date('YmdHi',$time_e))],
          ['time','>',(date('YmdHi',$time_s))],
        ];
        if($keyword){
          $map[] = ['s.addressname|p.name|e.addressname|t.startname|t.endname','like',"%{$keyword}%"];
        }

        $fields = 't.time, t.status, t.go_time ';
        // $fields = ', FROM_UNIXTIME(t.go_time,'%Y-%M-%D %h:%i:%s') as format_time';
        $fields .= ',t.infoid as id, t.love_wall_ID , t.subtime , t.carownid as driver_id , t.passengerid as passenger_id  ';

        // $fields .= ','.$this->formatUserFields('d');
        $fields .= ','.$this->formatUserFields('p');
        $fields .=  $this->formatAddressFields();
        $join[] = ['address s','s.addressid = t.startpid', 'left'];
        $join[] = ['address e','e.addressid = t.endpid', 'left'];
        // $join[] = ['user d','d.uid = t.carownid', 'left'];
        $join[] = ['user p','p.uid = t.passengerid', 'left'];

        $results = InfoModel::alias('t')->field($fields)->join($join)->where($map)->order('time DESC, t.infoid DESC ')
        ->paginate($pagesize, false,  ['query'=>request()->param()])->toArray();
        if(!$results['data']){
          return $this->jsonReturn(20002,$results,lang('No data'));
        }
        $lists = [];
        foreach ($results['data'] as $key => $value) {
          $lists[$key] = $this->formatResultValue($value);

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
     * 行程详情页
     * @param  integer $id 行程id
     */
    public function info_detail($id){
      $uid = $this->userBaseInfo['uid'];
      if(!$id){
        $this->jsonReturn(-1,[],'lost id');
        // return $this->error('lost id');
      }

      $fields = 't.time, t.status, t.go_time';
      $fields .= ',t.infoid as id, t.infoid , t.love_wall_ID , t.subtime , t.carownid as driver_id, t.passengerid as passenger_id  ';

      $fields .= ','.$this->formatUserFields('d');
      $fields .= ','.$this->formatUserFields('p');

      $fields .=  $this->formatAddressFields();
      $join[] = ['address s','s.addressid = t.startpid', 'left'];
      $join[] = ['address e','e.addressid = t.endpid', 'left'];
      $join[] = ['user d','d.uid = t.carownid', 'left'];
      $join[] = ['user p','p.uid = t.passengerid', 'left'];
      $data = InfoModel::alias('t')->field($fields)->join($join)->where("t.infoid",$id)->find();
      if(!$data){
        return $this->jsonReturn(20002,$data,lang('No data'));
      }
      $data = $this->formatResultValue($data);
      $data['uid']          = $uid;

      // return $this->success('加载成功','',$data);
      $this->jsonReturn(0,$data,'success');
    }

    /**
     * 历史行程
     */
    public function history($pagesize =15){
      $userData = $this->getUserData();
      $uid = $userData['uid'];
      $extra_info = json_decode($userData['extra_info'],true);
      $merge_ids = isset($extra_info['merge_id']) && is_array($extra_info['merge_id'])  ? $extra_info['merge_id'] : [];

      $InfoModel = new InfoModel();

      $viewSql  =  $InfoModel->buildUnionSql($uid,$merge_ids);

      $fields = 't.infoid , t.love_wall_ID , t.time, t.trip_type , t.time, t.status, t.passengerid, t.carownid , t.seat_count, t.go_time, t.subtime ,t.map_type';

      $fields .= ','.$this->formatUserFields('d');
      $fields .= ','.$this->formatUserFields('p');
      $fields .=  $this->formatAddressFields();
      $join = [];
      $join[] = ['address s','s.addressid = t.startpid', 'left'];
      $join[] = ['address e','e.addressid = t.endpid', 'left'];
      $join[] = ['user d','d.uid = t.carownid', 'left'];
      $join[] = ['user p','p.uid = t.passengerid', 'left'];
      $map = [
        ["t.time","<",date('YmdHi',strtotime('+15 minute'))],
        // ["t.go_time","<",strtotime('+15 minute')],
      ];

      $results =  Db::connect('database_carpool')->table("($viewSql)" . ' t')->field($fields)->where($map)->join($join)->order('t.time DESC, t.infoid DESC, t.love_wall_id DESC')->paginate($pagesize, false,  ['query'=>request()->param()])->toArray();

      if(!$results['data']){
        return $this->jsonReturn(20002,$results,lang('No data'));
      }
      $datas = $results['data'];

      foreach ($datas as $key => $value) {
        $datas[$key] = $this->formatResultValue($value,$merge_ids);

      }
      $returnData = [
        'lists'=>$datas,
        'page' =>[
          'total'=>$results['total'],
          'pageSize'=>$results['per_page'],
          'lastPage'=>$results['last_page'],
          'currentPage'=>$results['current_page'],
        ]
      ];
      $this->jsonReturn(0,$returnData,"success");

    }



    protected function formatUserFields($a="u",$fields=[]){
      $format_array = [];
      $fields = !empty($fields) ? $fields : ['uid','loginname','name','phone','mobile','Department','sex','company_id','department_id','companyname','imgpath','carnumber','im_id'];

      foreach ($fields as $key => $value) {
        $format_array[$key] = $a.".".$value." as ".$a."_".mb_strtolower($value);
      }
      return join(",",$format_array);
    }


    protected function formatAddressFields($fields="",$start_latlng = false){
      $fields .= ',t.startpid, t.endpid';
      $fields .= ', x(t.start_latlng) as start_lng, y(t.start_latlng) as start_lat' ;
      $fields .= ', x(t.end_latlng) as end_lng, y(t.end_latlng) as end_lat' ;
      $fields .= ', t.startname , t.start_gid ';
      $fields .= ', t.endname , t.end_gid ';
      $fields .= ',s.addressname as start_addressname, s.latitude as start_latitude, s.longtitude as start_longitude';
      $fields .= ',e.addressname as end_addressname, e.latitude as end_latitude, e.longtitude as end_longitude';

      return $fields;
    }

    protected function formatResultValue($value,$merge_ids = [] ,$unDo = []){
      $value_format = $value;

      $value_format['subtime'] = strtotime($value['subtime']);
      $value_format['go_time'] = $value['go_time'] ?  $value['go_time'] : strtotime($value['time']."00");
      // $value_format['time'] = date('Y-m-d H:i',strtotime($value['time'].'00'));
      $value_format['time'] = $value_format['go_time'];
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
        $value_format['start_addressname'] = $value['startname'];
        $value_format['start_longitude'] = $value['start_lng'];
        $value_format['start_latitude'] = $value['start_lat'];
      }
      if(!is_numeric($value['endpid']) || $value['endpid'] < 1 ){
        $value_format['end_addressname'] = $value['endname'];
        $value_format['end_longitude'] = $value['end_lng'];
        $value_format['end_latitude'] = $value['end_lat'];
      }
      return $value_format;
    }




}
