<?php
namespace app\admin\controller;


use app\common\controller\AdminBase;
use app\common\model\Configs;
use app\carpool\model\User as CarpoolUserModel;
use app\carpool\model\Company as CompanyModel;
use app\score\model\Order as OrderModel;
use app\score\model\Goods as GoodsModel;
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
  public function index($filter=[],$status=null,$page = 1,$pagesize = 20)
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
        $time_e = date('Y-m-d H:i:s',strtotime($time_arr[1]));
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
    $lists = OrderModel::alias('t')->field($fields)->join($join)->where($map)->json(['content'])->order(' t.creation_time DESC , t.id DESC')->paginate($pagesize, false,  ['query'=>request()->param()]);
    $GoodsModel = new GoodsModel();
    foreach($lists as $key => $value) {
      $userInfo = CarpoolUserModel::where(['loginname'=>$value['carpool_account']])->find();
      $lists[$key]['userInfo'] = $userInfo ;
      $goods = [];
      foreach ($value['content'] as $gid => $num) {
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
    return $this->fetch('detail', ['data' => $data,'companys' => $companys,'statusList'=>$statusList]);

  }


  /**
   * 商品兑换数统计
   * @return mixed
   */
  public function goods($filter=[]){
    $map = [];
    $map[] = ['t.is_delete','<>', 1];
    $map[] = ['t.status','=', 0];
    if(isset($filter['time']) && $filter['time']){
      $time_arr = explode(' ~ ',$filter['time']);
      if(is_array($time_arr)){
        $time_s = date('Y-m-d H:i:s',strtotime($time_arr[0]));
        $time_e = date('Y-m-d H:i:s',strtotime($time_arr[1]));
        $map[] = ['creation_time', 'between time', [$time_s, $time_e]];
      }
      $fields = 't.id, t.content, t.creation_time ';

      $orders_list = OrderModel::alias('t')->field($fields)->where($map)->json(['content'])->order(' t.creation_time ASC , t.id ASC')->select();
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




}
