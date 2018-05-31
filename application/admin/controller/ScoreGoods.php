<?php
namespace app\admin\controller;


use app\score\model\Goods as GoodsModel;
use app\common\controller\AdminBase;
use think\Db;

/**
 * 商品管理
 * Class ScoreGoods
 * @package app\admin\controller
 */
class ScoreGoods extends AdminBase
{
  protected $good_status = [
    "-2" => "下架",
    "-1" => "准备中",
    "0" => "正常",
    "1" => "推荐",
    "2" => "置顶",
  ];


  /**
   * 商品列表
   * @return mixed
   */
  public function index($type='2',$keyword="",$filter=[],$page = 1,$pagesize = 20)
  {
    $map = [];
    if(isset($filter['status']) && $filter['status']!==false){
      $map[] = ['status','=', $filter['status']];
    }
    if(isset($filter['is_hidden']) && $filter['is_hidden']!==false){
      $map[] = ['is_delete','=', $filter['is_hidden']];
    }
    if ($keyword) {
        $map[] = ['name|desc','like', "%{$keyword}%"];
    }
    $lists = GoodsModel::where($map)->json(['images'])->order(' id DESC')->paginate($pagesize, false,  ['query'=>request()->param()]);
    foreach ($lists as $key => $value) {

      $lists[$key]['thumb'] = is_array($value["images"]) ? $value["images"][0] : "" ;
    }
    $statusList = $this->good_status;
    return $this->fetch('index', ['lists' => $lists, 'keyword' => $keyword,'pagesize'=>$pagesize,'type'=>$type,'statusList'=>$statusList,'filter'=>$filter]);
  }



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
          $this->log('添加商品成功，id='.$id,0);
          return $this->jsonReturn(0,'保存成功');
      } else {
          $this->log('添加商品失败',-1);
          return $this->jsonReturn(-1,'保存失败');
      }
    }else{
      return $this->fetch('add', ['good_status' => $this->good_status]);
    }
  }



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
      return $this->fetch('edit', ['data' => $data,'good_status' => $this->good_status]);

    }
  }


}
