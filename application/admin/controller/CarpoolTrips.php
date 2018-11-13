<?php
namespace app\admin\controller;

use app\carpool\model\User as CarpoolUserModel;
use app\carpool\model\Company as CompanyModel;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\admin\controller\AdminBase;
use think\Db;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

/**
 * 拼车行程管理
 * Class Link
 * @package app\admin\controller
 */
class CarpoolTrips extends AdminBase
{
  /**
   * [index description]
   * @param  array   $filter   筛选项
   * @param  string||integer  $status  行程状态
   * @param  integer  $type   0： info表， 1：love_wall 空座位表
   * @param  integer $page      当前第几页
   * @param  integer $pagesize  每页多少条
   * @return mixed
   *
   */
  public function index($filter=[],$status="all",$type=0,$page = 1,$pagesize = 20,$export=0)
  {
    if($export){
      ini_set ('memory_limit', '128M');
      if( !$filter['time'] && !$filter['keyword'] && !$filter['keyword_dept']){
        $this->error('数据量过大，请筛选后再导出');
      }
    }

    $map = [];
    if(isset($filter['status']) && is_numeric($filter['status'])){
      $map[] = ['status','=', $filter['status']];
    }
    //筛选状态
    if(is_numeric($status)){
      $map[] = ['t.status','=', $status];
    }
    //筛选时间
    if(!isset($filter['time']) || !$filter['time'] || !is_array(explode(' ~ ',$filter['time']))){
      $time_s = date("Y-m-01");
      $time_e = date("Y-m-d",strtotime("$time_s +1 month"));
      $time_e_o = date("Y-m-d",strtotime($time_e)- 24*60*60);
      $filter['time'] = $time_s." ~ ".$time_e_o;
    }

    //筛选部门
    if (isset($filter['keyword_dept']) && $filter['keyword_dept'] ){
      $keyword_dept_str = $type == 1 ? 'd.Department|d.companyname' : 'd.Department|d.companyname|p.Department|p.companyname';
      $map[] = [$keyword_dept_str,'like', "%{$filter['keyword_dept']}%"];
    }

    //筛选部门
    if (isset($filter['keyword_address']) && $filter['keyword_address'] ){
      $map[] = ['s.addressname|e.addressname','like', "%{$filter['keyword_address']}%"];
    }
    $time_arr = explode(' ~ ',$filter['time']);
    $time_s = date('YmdHi',strtotime($time_arr[0]));
    $time_e = date('YmdHi',strtotime($time_arr[1]) + 24*60*60);
    $map[] = ['time', '>=', $time_s];
    $map[] = ['time', '<', $time_e];

    $fields = 't.time, t.status';
    $fields .= ',s.addressname as start_addressname, s.latitude as start_latitude, s.longtitude as start_longtitude';
    $fields .= ',e.addressname as end_addressname, e.latitude as end_latitude, e.longtitude as end_longtitude';
    $join = [];
    $join[] = ['address s','s.addressid = t.startpid', 'left'];
    $join[] = ['address e','e.addressid = t.endpid', 'left'];
    //从info表取得行程
    if(is_numeric($type) && $type == 0 ){
      //筛选用户信息
      if (isset($filter['keyword']) && $filter['keyword'] ){
        $map[] = ['d.loginname|d.phone|d.name|p.loginname|p.phone|p.name','like', "%{$filter['keyword']}%"];
      }
      $fields .= ',t.infoid, t.love_wall_ID , t.subtime , t.carownid as driver_id , t.passengerid as passenger_id ';
      $fields .= ',d.loginname as driver_loginname , d.name as driver_name, d.phone as driver_phone, d.Department as driver_department, d.sex as driver_sex ,d.company_id as driver_company_id, d.companyname as driver_companyname, d.carnumber';
      $fields .= ',p.loginname as passenger_loginname , p.name as passenger_name, p.phone as passenger_phone, p.Department as passenger_department, p.sex as passenger_sex ,p.company_id as passenger_company_id, p.companyname as passenger_companyname';
      $join[] = ['user d','d.uid = t.carownid', 'left'];
      $join[] = ['user p','p.uid = t.passengerid', 'left'];
      if(is_numeric($status)){
        $map[] = ['status', '=', $status];
      }else{
        switch ($status) {
          case 'cancel':
            $map[] = ['status', '=', 2];
            break;
          case 'success':
            $map[] = ['status', 'in', [1,3]];
            break;
          case 'fail':
            $map[] = ['status', '=', 0];
            break;
          default:
            // code...
            break;
        }
      }

      if($export){
        $lists = InfoModel::alias('t')->field($fields)->join($join)->where($map)->order('love_wall_ID DESC , time DESC')->select();
      }else{
        $lists = InfoModel::alias('t')->field($fields)->join($join)->where($map)->order('love_wall_ID DESC , time DESC')
        ->paginate($pagesize, false,  ['query'=>request()->param()]);
      }

      // ->fetchSql()->select();
      // dump($lists);exit;
      foreach ($lists as $key => $value) {
         $lists[$key]['time'] = date('Y-m-d H:i',strtotime($value['time'].'00'));
         $lists[$key]['subtime'] = date('Y-m-d H:i',strtotime($value['subtime'].'00'));
      }
      // dump($lists);exit;

    }

    if($type ==1 ){
      //筛选用户信息
      if (isset($filter['keyword']) && $filter['keyword'] ){
        $map[] = ['d.loginname|d.phone|d.name','like', "%{$filter['keyword']}%"];
      }
      $subQuery = InfoModel::field('count(love_wall_ID) as count , love_wall_ID')->group('love_wall_ID')->where('status <> 2')->fetchSql(true)->buildSql();
      $fields .= ', t.love_wall_ID, t.subtime , t.carownid as driver_id , t.seat_count';
      // $fields .=' , (select count(*) from info as i where i.love_wall_ID = t.love_wall_ID and i.status <> 2 ) AS took_count'
      $fields .= ',d.loginname as driver_loginname , d.name as driver_name, d.phone as driver_phone, d.Department as driver_department, d.sex as driver_sex ,d.company_id as driver_company_id, d.companyname as driver_companyname, d.carnumber';
      $fields .= ',ic.count as took_count';
      $join[] = ['user d','d.uid = t.carownid', 'left'];
      $join[] = [[$subQuery=>'ic'],'ic.love_wall_ID = t.love_wall_ID', 'left'];
      $map_stauts = [];
      if(is_numeric($status)){
        $map_stauts[] = ['status', '=', $status];
      }else{
        switch ($status) {
          case 'cancel':
            $map_stauts[] = ['status', '=', 2];
            break;
          case 'success':
            $map_stauts[] = ['status', '<>', 2];
            $map_stauts[] = ['ic.count', '>', 0];
            break;
          case 'fail':
            $map_stauts  = 'status <> 2 AND (ic.count IS NULL OR ic.count = 0)';
            break;
          default:
            // code...
            break;
        }
      }

      if($export){
        $lists = WallModel::alias('t')->field($fields)->join($join)->where($map)->where($map_stauts)->order('time DESC')->select();
      }else{
        $lists = WallModel::alias('t')->field($fields)->join($join)->where($map)->where($map_stauts)->order('time DESC')
        ->paginate($pagesize, false,  ['query'=>request()->param()]);
      }

      // ->fetchSql()->select();dump($lists);exit;

      foreach ($lists as $key => $value) {
         $lists[$key]['time'] = date('Y-m-d H:i',strtotime($value['time'].'00'));
         $lists[$key]['subtime'] = date('Y-m-d H:i',strtotime($value['subtime'].'00'));
         // $lists[$key]['took_count']       = InfoModel::where([['love_wall_ID','=',$value['love_wall_ID']],['status','<>',2]])->count(); //取已坐数
         // $data['took_count_all']   = Info::model()->count('love_wall_ID='.$data['love_wall_ID'].' and status <> 2'); //取已坐数
         // $data['hasTake']          = Info::model()->count('love_wall_ID='.$data['love_wall_ID'].' and status < 2 and passengerid ='.$uid.''); //查看是否已搭过此车主的车
         // $data['hasTake_finish']   = Info::model()->count('love_wall_ID='.$data['love_wall_ID'].' and status = 3 and passengerid ='.$uid.''); //查看是否已搭过此车主的车
      }
      // dump($lists);exit;

    }



    $companyLists = (new CompanyModel())->getCompanys();
    $companys = [];
    foreach($companyLists as $key => $value) {
      $companys[$value['company_id']] = $value['company_name'];
    }
    // dump($lists);
    if(!$export){
      if($type){
        return $this->fetch('wall_index', ['lists' => $lists, 'pagesize'=>$pagesize,'type'=>$type,'status'=>$status,'filter'=>$filter,'companys'=>$companys]);
      }else{
        return $this->fetch('index', ['lists' => $lists, 'pagesize'=>$pagesize,'type'=>$type,'status'=>$status,'filter'=>$filter,'companys'=>$companys]);
      }
    }
    //导出表格
    if($export){
      $filename = md5(json_encode($filter)).'_'.time().'.csv';

      $spreadsheet = new Spreadsheet();
      $sheet = $spreadsheet->getActiveSheet();

      /*设置表头*/
      $sheet->setCellValue('A1', '起点')
      ->setCellValue('B1','终点')
      ->setCellValue('C1','出发时间')
      ->setCellValue('D1','司机')
      ->setCellValue('E1','司机工号')
      ->setCellValue('F1','司机部门')
      ->setCellValue('G1','司机电话')
      ->setCellValue('H1', $type ? '乘客数' : '乘客')
      ->setCellValue('I1', $type ? '发布空位数' : '乘客工号')
      ->setCellValue('J1', $type ? '-' : '乘客电话')
      ->setCellValue('K1', $type ? '-' : '乘客电话')
      ;

      foreach ($lists as $key => $value) {
        $rowNum = $key+2;
        $sheet->setCellValue('A'.$rowNum, $value['start_addressname'])
        ->setCellValue('B'.$rowNum,$value['end_addressname'])
        ->setCellValue('C'.$rowNum,$value['time'])
        ->setCellValue('D'.$rowNum,$value['driver_name'])
        ->setCellValue('E'.$rowNum,$value['driver_loginname'] )
        ->setCellValue('F'.$rowNum, isset($companys[$value['driver_company_id']]) ? $value['driver_department'].'/'.$companys[$value['driver_company_id']] :  $value['driver_department'] )
        ->setCellValue('G'.$rowNum,$value['driver_phone'])
        ->setCellValue('H'.$rowNum,$type ? $value['took_count'] :  $value['passenger_name'])
        ->setCellValue('I'.$rowNum,$type ? $value['seat_count'] :  $value['passenger_loginname'])
        ->setCellValue('J'.$rowNum,$type ? "-" :  (  isset($companys[$value['passenger_company_id']]) ? $value['passenger_department'].'/'.$companys[$value['passenger_company_id']] : $value['passenger_department']))
        ->setCellValue('K'.$rowNum,$type ? "-" :  $value['passenger_phone'])
        ;
      }
      /*$value = "Hello World!" . PHP_EOL . "Next Line";
      $sheet->setCellValue('A1', $value)；
      $sheet->getStyle('A1')->getAlignment()->setWrapText(true);*/

      $writer = new Csv($spreadsheet);
      /*$filename = Env::get('root_path') . "public/uploads/temp/hello_world.xlsx";
      $writer->save($filename);*/
      header('Content-Disposition: attachment;filename="'.$filename.'"');//告诉浏览器输出浏览器名称
      header('Cache-Control: max-age=0');//禁止缓存
      $writer->save('php://output');
      $spreadsheet->disconnectWorksheets();
      unset($spreadsheet);
      // dump($lists);
      exit;
    }

  }

  /**
   * 明细
   */
  public function detail($id,$type=0){
    if(!$id){
      return $this->error('Lost id');
    }

    $fields = 't.time, t.status';
    $fields .= ',s.addressname as start_addressname, s.latitude as start_latitude, s.longtitude as start_longtitude';
    $fields .= ',e.addressname as end_addressname, e.latitude as end_latitude, e.longtitude as end_longtitude';
    $join = [];
    $join[] = ['address s','s.addressid = t.startpid', 'left'];
    $join[] = ['address e','e.addressid = t.endpid', 'left'];


    if($type){ // type为1时，查love_wall表的数据
      $fields .= ', t.love_wall_ID,   t.love_wall_ID , t.subtime , t.carownid as driver_id , t.seat_count, (select count(*) from info as i where i.love_wall_ID = t.love_wall_ID and i.status <> 2 ) AS took_count';
      $fields .= ',d.loginname as driver_loginname , d.name as driver_name, d.phone as driver_phone, d.Department as driver_department, d.sex as driver_sex ,d.company_id as driver_company_id, d.companyname as driver_companyname, d.carnumber,d.imgpath as driver_imgpath';
      $join[] = ['user d','d.uid = t.carownid', 'left'];

      $data = WallModel::alias('t')->field($fields)->join($join)
      // ->fetchSql()
      ->find($id);


      $data['driver_avatar'] = $data['driver_imgpath'] ? config('secret.avatarBasePath').$data['driver_imgpath'] : config('secret.avatarBasePath')."im/default.png";
      $data['time'] = date('Y-m-d H:i',strtotime($data['time'].'00'));
      $data['subtime'] = date('Y-m-d H:i',strtotime($data['subtime'].'00'));
      // $data['took_count']       = InfoModel::where([['love_wall_ID','=',$data['love_wall_ID']],['status','<>',2]])->count(); //取已坐数
      $fields2 = 't.*';
      $fields2 .= ',p.uid ,p.loginname  , p.name , p.phone , p.Department as department, p.sex, p.company_id , p.companyname, p.carnumber, p.imgpath ';
      $join2 = [
        ['user p','p.uid = t.passengerid', 'left'],
      ];

      $data['passengers']       = InfoModel::alias('t')->field($fields2)->join($join2)->where([['love_wall_ID','=',$data['love_wall_ID']],['status','<>',2]])->select(); //取乘客
      foreach ($data['passengers'] as $key => $value) {
        $data['passengers'][$key]['avatar'] = $value['imgpath'] ? config('secret.avatarBasePath').$value['imgpath'] : config('secret.avatarBasePath')."im/default.png";
      }


      // dump($data);exit;


    }else{ // type为0时，查info表的数据
      $fields .= ',t.infoid, t.love_wall_ID , t.subtime , t.carownid as driver_id , t.passengerid as passenger_id ';
      $fields .= ',d.loginname as driver_loginname , d.name as driver_name, d.phone as driver_phone, d.Department as driver_department, d.sex as driver_sex ,d.company_id as driver_company_id, d.companyname as driver_companyname, d.carnumber, d.imgpath as driver_imgpath';
      $fields .= ',p.loginname as passenger_loginname , p.name as passenger_name, p.phone as passenger_phone, p.Department as passenger_department, p.sex as passenger_sex ,p.company_id as passenger_company_id, p.companyname as passenger_companyname , p.imgpath as passenger_imgpath';
      $join[] = ['user d','d.uid = t.carownid', 'left'];
      $join[] = ['user p','p.uid = t.passengerid', 'left'];

      $data = InfoModel::alias('t')->field($fields)->join($join)->find($id);


      $data['driver_avatar'] = $data['driver_imgpath'] ? config('secret.avatarBasePath').$data['driver_imgpath'] : config('secret.avatarBasePath')."im/default.png";
      $data['passenger_avatar'] = $data['passenger_imgpath'] ? config('secret.avatarBasePath').$data['passenger_imgpath'] : config('secret.avatarBasePath')."im/default.png";
      $data['time'] = date('Y-m-d H:i',strtotime($data['time'].'00'));
      $data['subtime'] = date('Y-m-d H:i',strtotime($data['subtime'].'00'));


    }
    $companyLists = (new CompanyModel())->getCompanys();
    $companys = [];
    foreach($companyLists as $key => $value) {
      $companys[$value['company_id']] = $value['company_name'];
    }
    $returnData = [
      'data'=>$data,
      'companys'=>$companys,
    ];
    $template_name = $type ? 'wall_detail' : 'detail';
    return $this->fetch($template_name, $returnData);



  }


}
