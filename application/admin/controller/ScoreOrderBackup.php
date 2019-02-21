<?php
namespace app\admin\controller;


use app\admin\controller\AdminBase;
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

    if (isset($filter['order_num'])){
      if( strpos($filter['order_num'],"/")>0){
        $oNums = explode("/",$filter['order_num']);
        $map[] = ['t.uuid','like', "%{$oNums[0]}%"];
      }else{
        $map[] = ['t.uuid','like', "%{$filter['order_num']}%"];
      }
    }
    $fields = 't.*, ac.carpool_account, ac.balance ';

    $join = [
      ['account ac','t.creator = ac.id', 'left'],
    ];
    $lists = OrderModel::alias('t')->field($fields)->join($join)->where($map)->json(['content'])->order('t.operation_time DESC, t.creation_time DESC , t.id DESC')->paginate($pagesize, false,  ['query'=>request()->param()]);
    $goodList = [];
    $GoodsModel = new GoodsModel();
    foreach($lists as $key => $value) {
      $userInfo = CarpoolUserModel::where(['loginname'=>$value['carpool_account']])->find();
      $lists[$key]['userInfo'] = $userInfo ;
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
  public function goods($filter=[])
  {
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

      $fields = 'o.id, o.content, o.creation_time ';
      $orders_list = OrderModel::alias('o')->field($fields)->where($map)->json(['content'])->order(' o.creation_time ASC , o.id ASC')->select();
      $goods = [];
      $good_ids = [];
      foreach ($orders_list as $key => $value) {
        foreach ($value['content'] as $gid => $num) {
          $gid = strval($gid);
          $goods[$gid] = isset($goods[$gid]) ? intval($goods[$gid])+intval($num) : intval($num);
          if(!in_array($gid,$good_ids)){
            $good_ids[] = $gid;
          }
        }
      }
      $GoodsModel = new GoodsModel();
      $lists = $GoodsModel->json(['images'])->order(' id DESC')->where('id','in',$good_ids)->select();
      foreach ($lists as $key => $value) {
        $lists[$key]['thumb'] = is_array($value["images"]) ? $value["images"][0] : "" ;
        $num = 0;
        if($value['id']){
          $num = $goods[strval($value['id'])] ? $goods[strval($value['id'])] : 0;
        }
        $lists[$key]['num'] = $num;
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
  public function finish($id=0,$order_no=null)
  {
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
  public function good_owners($gid,$time,$pagesize = 20,$filter=['keyword'=>''])
  {
    if(!$gid){
      $this->error('Lost id');
    }
    $goodInfo = GoodsModel::where("id",$gid)->json(['images'])->find();
    if(!$goodInfo){
      $this->error("商品不存在");
    }

    $map[] = ['t.is_delete','<>', 1];
    $map[] = ['t.status','=', 0];
    $map[] = ['t.content->"'.$gid.'"', '>', ':good_num'];
    if(is_array($time_arr)){
      $time_s = date('Y-m-d H:i:s',strtotime($time_arr[0]));
      $time_e = date('Y-m-d H:i:s',strtotime($time_arr[1])+24*60*60);
      $map[] = ['t.creation_time', 'between time', [$time_s, $time_e]];
    }
    $fields = "t.*,
    ac.carpool_account, ac.balance,
    cu.uid, cu.loginname,cu.name, cu.phone, cu.Department, cu.sex ,cu.company_id, cu.companyname ";
    $join = [
      ['account ac','t.creator = ac.id', 'left'],
      ['carpool.user cu','cu.loginname = ac.carpool_account', 'left'],
    ];
    // $lists = OrderModel::alias('t')->field($fields)->join($join)->json(['content'])->where($map)->bind('good_num', 0, \PDO::PARAM_INT)->order('t.creator ASC ')->fetchSql()->select();
    $lists = OrderModel::alias('t')->field($fields)->join($join)->json(['content'])->where($map)->bind('good_num', 0, \PDO::PARAM_INT)->order('t.creator ASC ')->paginate($pagesize, false,  ['query'=>request()->param()]);


    foreach ($lists as $key => $value) {
      $lists[$key]['userInfo'] = CarpoolUserModel::where(['loginname'=>$value['carpool_account']])->find();
      // $lists[$key]['num'] = $value['content']->$gid;
    }
    $companyLists = (new CompanyModel())->getCompanys();
    $companys = [];
    foreach($companyLists as $key => $value) {
      $companys[$value['company_id']] = $value['company_name'];
    }
    return $this->fetch('good_owners', ['goodInfo'=>$goodInfo,'lists' => $lists,'companys'=>$companys,'time'=>$time,'filter'=>$filter,'pagesize'=>$pagesize]);


  }



}
