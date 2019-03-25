<?php
namespace app\admin\controller;


use think\facade\Env;
use app\admin\controller\AdminBase;
use app\common\model\Configs;
use app\carpool\model\User as CarpoolUserModel;
use app\carpool\model\Company as CompanyModel;
use app\user\model\Department as DepartmentModel;
use app\score\model\Account as ScoreAccountModel;
use app\score\model\Order as OrderModel;
use app\score\model\Goods as GoodsModel;
use app\score\model\OrderGoods as OrderGoodsModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use my\CurlRequest;
use think\Db;

/**
 * 订单管理
 * Class ScoreGoods
 * @package app\admin\controller
 */
class ScoreOrder extends AdminBase
{

  /**
   * 订单列表
   * @return mixed
   */
  public function index($filter=[],$status="0",$page = 1,$pagesize = 15,$region_id=0,$export=0)
  {
    //构建sql
    $fields = 't.*, ac.carpool_account, ac.balance    ';
    $fields .= ',cu.uid, cu.loginname,cu.name, cu.phone, cu.Department, cu.sex ,cu.company_id, cu.companyname, d.fullname as full_department';

    $join = [
      ['account ac','t.creator = ac.id', 'left'],
    ];
    $join[] = ['carpool.user cu','cu.loginname = ac.carpool_account', 'left'];
    // $join[] = ['carpool.t_department d','cu.department_id = d.id','left'];
    $join[] = ['carpool.t_department d','t.region_id = d.id','left'];


    $map = [];
    $map[] = ['t.is_delete','<>', 1];
    //筛选状态
    if(is_numeric($status)){
      $map[] = ['t.status','=', $status];
    }
    //地区排查
    if($region_id){
      if(is_numeric($region_id)){
        $regionData = $this->getDepartmentById($region_id);
      }
      $region_map_sql = $this->buildRegionMapSql($region_id);
      $map[] = ['','exp', Db::raw($region_map_sql)];
    }


    //筛选时间
    if(!isset($filter['time']) || !$filter['time'] || !is_array(explode(' ~ ',$filter['time']))){
      $time_s = date("Y-m-01");
      $time_e = date("Y-m-d",strtotime("$time_s +1 month"));
      $time_e_o = date("Y-m-d",strtotime($time_e)- 24*60*60);
      $filter['time'] = $time_s." ~ ".$time_e_o;
    }
    $time_arr = $this->formatFilterTimeRange($filter['time'],'Y-m-d H:i:s','m');
    $map[] = ['creation_time', 'between time', $time_arr];

    //筛选单号
    $mapOrderRaw = '';
    if (isset($filter['order_num']) && $filter['order_num']){
      $orderNums = explode("|",$filter['order_num']);
      foreach ($orderNums as $key => $value) {
        $mapOrderRaw = $mapOrderRaw ? $mapOrderRaw." or " : " ";
        if( strpos($value,"/")>0){
          $oNums = explode("/",$value);
          $mapOrderRaw  .= " t.uuid like  '%{$oNums[0]}%' ";
        }else{
          if(is_numeric($value)){
            $mapOrderRaw .= " t.id = '{$value}' ";
          }else{
            $mapOrderRaw .= " t.uuid like '%{$value}%'  ";
          }
        }
      }
    }

    //筛选用户信息
    if (isset($filter['keyword']) && $filter['keyword'] ){
      $map[] = ['cu.loginname|cu.phone|cu.name','like', "%{$filter['keyword']}%"];
    }
    //筛选部门
    if (isset($filter['keyword_dept']) && $filter['keyword_dept'] ){
      //筛选状态
      if(isset($filter['is_hr']) && $filter['is_hr'] == 0){
        $map[] = ['cu.Department|cu.companyname','like', "%{$filter['keyword_dept']}%"];
      }else{
        $map[] = ['d.fullname','like', "%{$filter['keyword_dept']}%"];
      }
    }




    $ModelBase = OrderModel::alias('t')->field($fields)->join($join)->where($map)->json(['content'])->order('t.operation_time ASC, t.creation_time ASC , t.id ASC');
    if(!empty($mapOrderRaw)){
      $ModelBase = $ModelBase->whereRaw($mapOrderRaw);
    }
    if($export){
      $lists = $ModelBase->select();
    }else{
      $lists = $ModelBase
      // ->fetchSql()->select();
      ->paginate($pagesize, false,  ['query'=>request()->param()]);
    }

    $goodList = []; //商品缓存
    $GoodsModel = new GoodsModel();
    $DepartmentModel = new DepartmentModel();
    foreach($lists as $key => $value) {

      $lists[$key]['Department'] = $lists[$key]['full_department'] ? $DepartmentModel->formatFullName($lists[$key]['full_department'],1) : $lists[$key]['Department']  ;

      $goods = []; //商品
      foreach ($value['content'] as $gid => $num) {
        if(isset($goodList[$gid])){
          $good = $goodList[$gid];
        }else{
          $good = $GoodsModel->getFromRedis($gid);
          $goodList[$gid] =  $good ? $good : [];
        }

        if($good){
          $images = json_decode($good['images'],true);
          $good['thumb'] = $images ? $images[0] : "" ;
        }else{
          $good['id'] = $gid ;
          $good['name'] = "#".$gid ;
          $good['thumb'] = '';
        }
        $good['num'] = $num;
        $goods[] =  $good;
      }
      $lists[$key]['goods'] = $goods;
    }


    $companyLists = (new CompanyModel())->getCompanys();
    $companys = [];
    foreach($companyLists as $key => $value) {
      $companys[$value['company_id']] = $value['company_name'];
    }


    /* 导出报表 */
    if($export){
      $filename = md5(json_encode($filter)).'_'.$status.'_'.time().'.csv';

      $spreadsheet = new Spreadsheet();
      $sheet = $spreadsheet->getActiveSheet();

      /*设置表头*/
      $sheet->setCellValue('A1', '单号')
      ->setCellValue('B1','姓名')
      ->setCellValue('C1','电话')
      ->setCellValue('D1','账号')
      ->setCellValue('E1','公司')
      ->setCellValue('F1','部门')
      ->setCellValue('G1','分厂')
      ->setCellValue('H1','下单时间')
      ->setCellValue('I1','奖品')
      ->setCellValue('J1','状态')
      ;

      foreach ($lists as $key => $value) {
        $rowNum = $key+2;
        $goodStr = '';

        foreach ($value['goods'] as $k => $good) {
          $goodStr .= $good['name'].'×'.$good['num'].PHP_EOL;
        }

        $sheet->setCellValue('A'.$rowNum, iconv_substr($value['uuid'],0,8).'/'.$value['id'])
        ->setCellValue('B'.$rowNum,$value['name']."(#".$value['uid'].")")
        ->setCellValue('C'.$rowNum,$value['phone'])
        ->setCellValue('D'.$rowNum,$value['loginname'])
        ->setCellValue('E'.$rowNum,isset($companys[$value['company_id']]) ? $companys[$value['company_id']] :  $value['company_id'] )
        ->setCellValue('F'.$rowNum,$value['Department'])
        ->setCellValue('G'.$rowNum,$value['companyname'])
        ->setCellValue('H'.$rowNum,$value['creation_time'])
        ->setCellValue('I'.$rowNum,$goodStr)
        ->setCellValue('J'.$rowNum,$value['status']);
        $sheet->getStyle('I'.$rowNum)->getAlignment()->setWrapText(true);
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
    }else{
      // dump($lists);
      $statusList = config('score.order_status');
      $returnData = [
        'regionData'=> isset($regionData) ? $regionData : NULL,
        'region_id' => $region_id,
        'lists' => $lists,
        'pagesize'=>$pagesize,
        'statusList'=>$statusList,
        'filter'=>$filter,
        'status'=>$status,
        'companys'=>$companys
      ];
      return $this->fetch('index', $returnData);
    }


  }


  /**
   * 订单详情
   * @return mixed
   */
  public function detail($id)
  {
    if(!$id){
      $this->error("Lost id");
    }

    $fields = 't.*, ac.carpool_account, ac.balance , d.fullname as full_department';
    $join = [
      ['account ac','t.creator = ac.id', 'left'],
      ['carpool.t_department d','t.region_id = d.id', 'left'],
    ];
    $data = OrderModel::alias('t')->field($fields)->join($join)->where('t.id',$id)->json(['content'])->find();
    if(!$data){
      $this->error("订单不存在");
    }else{
      $data['userInfo'] = CarpoolUserModel::alias('t')
                          ->field('t.*, d.fullname as full_department')
                          ->join([['t_department d','t.department_id = d.id','left']])
                          ->where(['loginname'=>$data['carpool_account']])
                          ->find();
      if($data['userInfo']){
        $data['userInfo']['avatar'] = $data['userInfo']['imgpath'] ? config('secret.avatarBasePath').$data['userInfo']['imgpath'] : config('secret.avatarBasePath')."im/default.png";
      }

      $goods = [];
      $GoodsModel = new GoodsModel();
      foreach ($data['content'] as $gid => $num) {
        $good = $GoodsModel->getFromRedis($gid);
        if($good){
          $images = json_decode($good['images'],true);
          $good['thumb'] = $images ? $images[0] : "" ;
        }else{
          $good['name'] = "#".$gid ;
          $good['id'] = $gid;
          $good['price'] = '-';
          $good['thumb'] = '';
        }
        $good['num'] = $num;
        $goods[] =  $good;
      }
      $data['goods'] = $goods;

    }
    $companyLists = (new CompanyModel())->getCompanys();
    $companys = [];
    foreach($companyLists as $key => $value) {
      $companys[$value['company_id']] = $value['company_name'];
    }
    $statusList = config('score.order_status');
    $auth = [];
    $auth['admin/ScoreOrder/finish'] = $this->checkActionAuth('admin/ScoreOrder/finish');

    return $this->fetch('detail', ['data' => $data,'companys' => $companys,'statusList'=>$statusList,'auth'=>$auth]);

  }


  /**
   * 商品兑换数统计
   * @return mixed
   */
  public function goods($filter=['status'=>0],$region_id=0){
    $map = [];
    $map[] = ['o.is_delete','<>', 1];
    //筛选状态
    if(is_numeric($filter['status'])){
      $map[] = ['o.status','=', $filter['status']];
    }elseif($filter['status']=="all_01"){
      $map[] = ['o.status','in', [0,1]];
    }
    $fields = "g.*, sum(t.count) as num, d.fullname, g.p_region_id ";
    $join = [
      ['order o','o.id = t.oid', 'left'],
      ['goods g','g.id = t.gid', 'left'],
      ['carpool.t_department d','g.p_region_id = d.id','left']
    ];
    if($region_id){
      if(is_numeric($region_id)){
        $regionData = $this->getDepartmentById($region_id);
      }
      $region_map_sql = $this->buildRegionMapSql($region_id);
      $map[] = ['','exp', Db::raw($region_map_sql)];
    }


    //筛选时间
    if(!isset($filter['time']) || !$filter['time'] || !is_array(explode(' ~ ',$filter['time']))){
      $time_s = date("Y-m-01");
      $time_e = date("Y-m-d",strtotime("$time_s +1 month"));
      $time_e_o = date("Y-m-d",strtotime($time_e)- 24*60*60);
      $filter['time'] = $time_s." ~ ".$time_e_o;
    }
    $time_arr = explode(' ~ ',$filter['time']);
    $time_s = date('Y-m-d H:i:s',strtotime($time_arr[0]));
    $time_e = date('Y-m-d H:i:s',strtotime($time_arr[1]) + 24*60*60);
    $map[] = ['o.creation_time', '>=', $time_s];
    $map[] = ['o.creation_time', '<', $time_e];


    $lists = OrderGoodsModel::alias('t')->field($fields)->json(['images'])->join($join)->where($map)->group('t.gid')->order('t.gid DESC')->select();
    foreach ($lists as $key => $value) {
      $lists[$key]['thumb'] = is_array($value["images"]) ? $value["images"][0] : "" ;
    }

    $statusList = config('score.order_status');

    $returnData = [
      'regionData'=> isset($regionData) ? $regionData : NULL,
      'region_id' => $region_id,
      'lists' => $lists,
      'filter'=>$filter,
      'statusList'=>$statusList
    ];
    return $this->fetch('goods', $returnData);

  }




  /**
   * 完结订单
   * @param  integer $id       订单id
   * @param  string  $order_no 订单号
   */
  public function finish($id=0,$order_no=null){
    $statusList = config('score.order_status');
    $admin_id = $this->userBaseInfo['uid'];


    if ($this->request->isPost()) {
      if(!$id ){
        $this->error("Params error");
      }
      /*if(!$order_no ){
        $this->error("Params error");
      }*/

      $data = OrderModel::alias('t')->where('id',$id)->json(['content'])->find();
      if(!$data || $data['is_delete']==1){
        $this->error("订单不存在");
      }

      if(intval($data['status'])!==0){
        $statusMsg = isset($statusList[$data['status']]) ? $statusList[$data['status']] : $data['status'];
        $this->error("该订单状态为【".$statusMsg."】，不可操作。");
      }

      $result = OrderModel::where('id',$id)->update(["status"=>1,"handler"=> -1 * intval($admin_id)]);

      if($result){
        $this->log('完结订单成功'.json_encode($this->request->post()),0);
        $this->success('完结订单成功');
      }else{
        $this->log('完结订单失败'.json_encode($this->request->post()),-1);
        $this->success('完结订单失败');
      }
    }
  }



  /**
   * 商品订单 下单者列表
   */
  public function good_owners($gid,$time,$pagesize = 30,$status= 0, $filter=['keyword'=>'' ])
  {
    if(!$gid){
      $this->error('Lost id');
    }
    $join = [
      ['carpool.t_department d','t.p_region_id = d.id','left'],
    ];
    $good = GoodsModel::alias('t')->field('t.*, d.fullname as full_department')->where("t.id",$gid)->json(['images'])->join($join)->find();
    if(!$good){
      $this->error("商品不存在");
    }
    $good['thumb'] = is_array($good["images"]) ? $good["images"][0] : "" ;



    $map = [];
    $map[] = ['t.gid', '=', $gid];
    $map[] = ['o.is_delete','<>', 1];
    //筛选状态
    if(is_numeric($status)){
      $map[] = ['o.status','=', $status];
    }elseif($status=="all_01"){
      $map[] = ['o.status','in', [0,1]];
    }


    $time_arr = explode(' ~ ',$time);
    $time_arr = count($time_arr) > 1 ? $time_arr : explode('+~+',$time);

    if(is_array($time_arr)){
      $time_s = date('Y-m-d H:i:s',strtotime($time_arr[0]));
      $time_e = date('Y-m-d H:i:s',strtotime($time_arr[1]) + 24*60*60);
      // $map[] = ['o.creation_time', 'between', [$time_s, $time_e]];
      $map[] = ['o.creation_time', '>=', $time_s];
      $map[] = ['o.creation_time', '<', $time_e];
    }




    $map_sub  = [];
    $fields_sub = "SUM(t.count) as num, t.creator , MIN(t.add_time) as add_time ";
    $join_sub = [
      ['order o','o.id = t.oid', 'left'],
    ];
    $subQuery = OrderGoodsModel::alias('t')->field($fields_sub)->join($join_sub)->where($map)->group('t.creator')->buildSql();

    $map_2  = [];
    if (isset($filter['keyword']) && $filter['keyword']){
      $map_2[] = ['cu.loginname|cu.phone|cu.Department|cu.name|cu.companyname','like', "%{$filter['keyword']}%"];
    }
    if (isset($filter['company_id']) && $filter['company_id']){
      $map_2[] = ['cu.company_id','=', $filter['company_id']];
    }

    $fields = "s.num , s.creator, s.add_time,  ac.id as account_id , ac.carpool_account,  ac.is_delete as ac_is_delete, ac.balance,
    cu.uid, cu.loginname,cu.name, cu.phone, cu.Department, cu.sex ,cu.company_id, cu.companyname
     ";
    $join = [
      ['account ac','s.creator = ac.id', 'left'],
      ['carpool.user cu','cu.loginname = ac.carpool_account', 'left'],
    ];

    $sumRes = Db::connect('database_score')->table($subQuery . ' s')->field('sum(s.num) as sum')->join($join)->where($map_2)->find();
    $lists = Db::connect('database_score')->table($subQuery . ' s')->field($fields)->join($join)->where($map_2)->order('s.creator ASC ')->paginate($pagesize, false,  ['query'=>request()->param()]);
    // $lists = Db::connect('database_score')->table($subQuery . ' s')->field($fields)->join($join)->where($map_2)->fetchSql()->select();
    $total = $lists->total();
    $sum = $sumRes ? $sumRes['sum'] : 0;

    $companyLists = (new CompanyModel())->getCompanys();
    $companys = [];
    foreach($companyLists as $key => $value) {
      $companys[$value['company_id']] = $value['company_name'];
    }
    $statusList = config('score.order_status');

    return $this->fetch('good_owners', ['good'=>$good,'lists' => $lists,'companys'=>$companys,'time'=>$time,'filter'=>$filter,'status'=>$status,'statusList'=>$statusList,'pagesize'=>$pagesize,'total'=>$total,'sum'=>$sum]);


  }


  /**
   *
   */
  public function owners($time,$pagesize = 30,$filter=['keyword'=>''])
  {

  }



}
