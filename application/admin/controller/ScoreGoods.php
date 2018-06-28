<?php
namespace app\admin\controller;


use app\score\model\Goods as GoodsModel;
use app\common\controller\AdminBase;
use app\common\model\Configs;
use my\CurlRequest;
use think\Db;

/**
 * 商品管理
 * Class ScoreGoods
 * @package app\admin\controller
 */
class ScoreGoods extends AdminBase
{
  protected $goods_status ;

  protected function initialize()
  {
      parent::initialize();
      $this->goods_status  = config('score.goods_status');
  }

  /**
   * 商品列表
   * @return mixed
   */
  public function index($type='2',$keyword="",$filter=['status'=>'','is_hidden'=>''],$page = 1,$pagesize = 20)
  {
    $map = [];

    if(isset($filter['status']) && is_numeric($filter['status'])){
      $map[] = ['status','=', $filter['status']];
    }
    if(isset($filter['is_hidden']) && is_numeric($filter['is_hidden']) && $filter['is_hidden']!==0){
      $is_delete = $filter['is_hidden'] ? 1 : 0 ;
      $map[] = ['is_delete','=', $filter['is_hidden']] ;
    }
    if ($keyword) {
        $map[] = ['name|desc','like', "%{$keyword}%"];
    }
    $lists = GoodsModel::where($map)->json(['images'])->order(' id DESC')->paginate($pagesize, false,  ['query'=>request()->param()]);
    foreach ($lists as $key => $value) {
      $lists[$key]['thumb'] = is_array($value["images"]) ? $value["images"][0] : "" ;
    }
    $statusList = $this->goods_status;
    $scoreConfigs = (new Configs())->getConfigs("score");
    return $this->fetch('index', ['lists' => $lists, 'keyword' => $keyword,'pagesize'=>$pagesize,'type'=>$type,'statusList'=>$statusList,'filter'=>$filter,'scoreConfigs'=>$scoreConfigs]);
  }


  /**
   * 添加商品
   */
  public function add(){
    if ($this->request->isPost()) {
      $data               = $this->request->post();
      //开始验证字段
      $validate = new \app\score\validate\Goods;
      if (!$validate->check($data)) {
        return $this->jsonReturn(-1,$validate->getError());
      }

      $upData = [
        'name' => $data['name'],
        'desc' => $data['desc'],
        'price' => $data['price'],
        'amount' => $data['amount'],
        'inventory' => $data['inventory'],
        'status' => $data['status'],
        'is_delete'=> isset($data['is_show']) && $data['is_show'] ? 0 : 1,
        'operator' => $this->userBaseInfo['uid'],
        'update_time' => date('Y-m-d H:i:s'),
      ];
      if($data['thumb'] && trim($data['thumb'])){
        $upData['images'][0] =  $data['thumb'];
      }

      $id = GoodsModel::json(['images'])->insertGetId($upData);
      if ( $id ) {
          $this->public_recache($id);
          $this->log('添加商品成功，id='.$id,0);
          return $this->jsonReturn(0,'保存成功');
      } else {
          $this->log('添加商品失败',-1);
          return $this->jsonReturn(-1,'保存失败');
      }
    }else{
      return $this->fetch('add', ['goods_status' => $this->goods_status]);
    }
  }


  /**
   * 编辑
   * @param  [int] $id 商品id
   */
  public function edit($id){
    if ($this->request->isPost()) {
      $data               = $this->request->post();
      //开始验证字段
      $validate = new \app\score\validate\Goods;
      if (!$validate->check($data)) {
        return $this->jsonReturn(-1,$validate->getError());
      }

      $upData = [
        'name' => $data['name'],
        'desc' => $data['desc'],
        'price' => $data['price'],
        'amount' => $data['amount'],
        'inventory' => $data['inventory'],
        'status' => $data['status'],
        'is_delete'=> isset($data['is_show']) && $data['is_show'] ? 0 : 1,
        'operator' => $this->userBaseInfo['uid'],
        'update_time' => date('Y-m-d H:i:s'),
      ];
      if($data['thumb'] && trim($data['thumb'])){
        $upData['images'][0] =  $data['thumb'];
      }


      if (GoodsModel::json(['images'])->where('id',$id)->update($upData) !== false) {
          $this->public_recache($id);
          $this->log('保存商品成功，id='.$id,0);
          return $this->jsonReturn(0,'保存成功');
      } else {
          $this->log('保存商品失败，id='.$id,-1);
          return $this->jsonReturn(-1,'保存失败');
      }

    }else{
      $id = input("param.id/d",0);
      if(!$id){
        $this->error("Lost id");
      }
      $data = GoodsModel::where("id",$id)->json(['images'])->find();
      if(!$data){
        $this->error("商品不存在");
      }else{
        $data['is_show'] = $data['is_delete'] ? 0 : 1 ;
        $data['thumb'] = is_array($data["images"]) ? $data["images"][0] : "" ;
      }
      return $this->fetch('edit', ['data' => $data,'goods_status' => $this->goods_status]);
    }
  }

  /**
   * 刷新缓存
   * @var [type]
   */
  public function public_recache($id=0){
    $scoreConfigs = (new Configs())->getConfigs("score");
    return (new GoodsModel())->reBuildRedis($id);
  }


}
