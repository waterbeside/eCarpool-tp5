<?php
namespace app\admin\controller;


use app\common\controller\AdminBase;
use app\common\model\Configs;
use app\carpool\model\User as CarpoolUserModel;
use app\carpool\model\Company as CompanyModel;
use app\score\model\Account as ScoreAccountModel;
use app\score\model\Order as OrderModel;
use app\score\model\Goods as GoodsModel;
use app\score\model\OrderGoods as OrderGoodsModel;
use my\CurlRequest;
use think\Db;

/**
 * 商品管理
 * Class ScoreGoods
 * @package app\admin\controller
 */
class ScoreOrder extends AdminBase
{



  /**
   * 订单列表
   * @return mixed
   */
  public function index($filter=[],$status="0",$page = 1,$pagesize = 15)
  {
    $map = [];
    $map[] = ['t.is_delete','<>', 1];

    if(is_numeric($status)){
      $map[] = ['t.status','=', $status];
    }
    if(isset($filter['time']) && $filter['time']){
      $time_arr = explode(' ~ ',$filter['time']);
      if(is_array($time_arr)){
        $time_s = date('Y-m-d H:i:s',strtotime($time_arr[0]));
        $time_e = date('Y-m-d H:i:s',strtotime($time_arr[1]) + 24*60*60);
        $map[] = ['creation_time', 'between time', [$time_s, $time_e]];
      }
    }


    if (isset($filter['order_num']) && $filter['order_num']){
      if( strpos($filter['order_num'],"/")>0){
        $oNums = explode("/",$filter['order_num']);
        $map[] = ['t.uuid','like', "%{$oNums[0]}%"];
      }else{
        $map[] = ['t.uuid','like', "%{$filter['order_num']}%"];
      }
    }

    if (isset($filter['keyword']) && $filter['keyword'] ){
      $map[] = ['cu.loginname|cu.phone|cu.Department|cu.name|cu.companyname','like', "%{$filter['keyword']}%"];
    }


    $fields = 't.*, ac.carpool_account, ac.balance ,
    cu.uid, cu.loginname,cu.name, cu.phone, cu.Department, cu.sex ,cu.company_id, cu.companyname
    ';

    $join = [
      ['account ac','t.creator = ac.id', 'left'],
      ['carpool.user cu','cu.loginname = ac.carpool_account', 'left'],
    ];
    $lists = OrderModel::alias('t')->field($fields)->join($join)->where($map)->json(['content'])->order('t.operation_time ASC, t.creation_time ASC , t.id ASC')->paginate($pagesize, false,  ['query'=>request()->param()]);
    $goodList = [];
    $GoodsModel = new GoodsModel();
    foreach($lists as $key => $value) {
      // $userInfo = CarpoolUserModel::where(['loginname'=>$value['carpool_account']])->find();
      // $lists[$key]['userInfo'] = $userInfo ;
      $goods = [];
      foreach ($value['content'] as $gid => $num) {
        if(isset($goodList[$gid])){
          $good = $goodList[$gid];
        }else{
          $good = $GoodsModel->getFromRedis($gid);
          $goodList[$gid] =  $good ;
        }

        if($good){
          $images = json_decode($good['images'],true);
          $good['thumb'] = $images ? $images[0] : "" ;
        }else{
          $good['id'] = $gid;
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

    // dump($lists);
    $statusList = config('score.order_status');

    return $this->fetch('index', ['lists' => $lists, 'pagesize'=>$pagesize,'statusList'=>$statusList,'filter'=>$filter,'status'=>$status,'companys'=>$companys]);

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

    $fields = 't.*, ac.carpool_account, ac.balance ';
    $join = [
      ['account ac','t.creator = ac.id', 'left'],
    ];
    $data = OrderModel::alias('t')->field($fields)->join($join)->where('t.id',$id)->json(['content'])->find();
    if(!$data){
      $this->error("订单不存在");
    }else{
      $data['userInfo'] = CarpoolUserModel::where(['loginname'=>$data['carpool_account']])->find();
      if($data['userInfo']){
        $data['userInfo']['avatar'] = $data['userInfo']['imgpath'] ? config('app.avatarBasePath').$data['userInfo']['imgpath'] : config('app.avatarBasePath')."im/default.png";
      }

      $goods = [];
      $GoodsModel = new GoodsModel();
      foreach ($data['content'] as $gid => $num) {
        $good = $GoodsModel->getFromRedis($gid);
        if($good){
          $images = json_decode($good['images'],true);
          $good['thumb'] = $images ? $images[0] : "" ;
        }else{
          $good['id'] = $gid;
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
  public function goods($filter=[]){
    $map = [];
    $map[] = ['o.is_delete','<>', 1];
    $map[] = ['o.status','=', 0];
    if(isset($filter['time']) && $filter['time']){
      $time_arr = explode(' ~ ',$filter['time']);
      if(is_array($time_arr)){
        $time_s = date('Y-m-d H:i:s',strtotime($time_arr[0]));
        $time_e = date('Y-m-d H:i:s',strtotime($time_arr[1]) + 24*60*60);
        // $map[] = ['t.creation_time', 'between', [$time_s, $time_e]];
        $map[] = ['o.creation_time', '>=', $time_s];
        $map[] = ['o.creation_time', '<', $time_e];
      }

      $join = [
        ['order o','o.id = t.oid', 'left'],
        ['goods g','g.id = t.gid', 'left'],
      ];
      $lists = OrderGoodsModel::alias('t')->field("g.*, sum(t.count) as num ")->json(['images'])->join($join)->where($map)->group('t.gid')->order(' t.gid DESC')->select();
      foreach ($lists as $key => $value) {
        $lists[$key]['thumb'] = is_array($value["images"]) ? $value["images"][0] : "" ;
      }

    }else{
      $lists = false;
    }

    return $this->fetch('goods', ['lists' => $lists,'filter'=>$filter]);

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

      if($data['status']!==0){
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
  public function good_owners($gid,$time,$pagesize = 30,$filter=['keyword'=>'']){
    if(!$gid){
      $this->error('Lost id');
    }
    $good = GoodsModel::where("id",$gid)->json(['images'])->find();
    if(!$good){
      $this->error("商品不存在");
    }
    $good['thumb'] = is_array($good["images"]) ? $good["images"][0] : "" ;



    $map = [];
    $map[] = ['t.gid', '=', $gid];
    $map[] = ['o.is_delete','<>', 1];
    $map[] = ['o.status','=', 0];


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
    return $this->fetch('good_owners', ['good'=>$good,'lists' => $lists,'companys'=>$companys,'time'=>$time,'filter'=>$filter,'pagesize'=>$pagesize,'total'=>$total,'sum'=>$sum]);


  }



}
