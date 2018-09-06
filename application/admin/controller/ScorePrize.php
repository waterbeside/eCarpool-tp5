<?php
namespace app\admin\controller;


use app\score\model\Prize as PrizeModel;
use app\admin\controller\AdminBase;
use app\common\model\Configs;
use think\Db;

/**
 * 抽奖管理
 * Class ScorePrize
 * @package app\admin\controller
 */
class ScorePrize extends AdminBase
{

  /**
   * 抽奖列表
   * @return mixed
   */
  public function index($keyword="",$filter=['status'=>'0,1,2','is_hidden'=>''],$page = 1,$pagesize = 20)
  {
    $map = [];
    $status = input("param.status");
    if($status!==null){
      $filter['status'] = $status;
    }

    if(isset($filter['status']) && is_numeric($filter['status'])){
      $map[] = ['status','=', $filter['status']];
    }else{
      if(strpos($filter['status'],',')>0){
        $map[] = ['status','in', $filter['status']];

      }
    }
    if(  is_numeric($filter['is_hidden']) && $filter['is_hidden']!==0){
      $is_delete = $filter['is_hidden'] ? 1 : 0 ;
      $map[] = ['is_delete','=', $filter['is_hidden']] ;
    }
    if ($keyword) {
        $map[] = ['name|desc','like', "%{$keyword}%"];
    }

    $lists = PrizeModel::where($map)->json(['images'])->order('id DESC')->paginate($pagesize, false,  ['query'=>request()->param()]);
    // $lists = PrizeModel::where($map)->json(['images'])->order('id DESC')->fetchSql()->select();
    // dump($lists);exit;
    foreach ($lists as $key => $value) {
      $lists[$key]['thumb'] = is_array($value["images"]) ? $value["images"][0] : "" ;
    }

    $scoreConfigs = (new Configs())->getConfigs("score");
    $statusList = config('score.prize_status');
    $auth['admin/ScorePrize/add'] = $this->checkActionAuth('admin/ScorePrize/add');
    $returnData =  [
      'lists' => $lists,
      'keyword' => $keyword,
      'pagesize'=>$pagesize,
      'statusList'=>$statusList,
      'filter'=>$filter,
      'scoreConfigs'=>$scoreConfigs,
      'auth'=>$auth,

    ];


    return $this->fetch('index',$returnData);
  }


  /**
   * 添加抽奖
   */
  public function add(){

    if ($this->request->isPost()) {
      $data               = $this->request->post();


      //开始验证字段
      $validate = new \app\score\validate\Prize;
      if (!$validate->check($data)) {
        return $this->jsonReturn(-1,$validate->getError());
      }
      $data['publication_number'] = 1;
      if($data['identity']){
        $maxData = $this->checkMaxData($data['identity']);
        $data['publication_number'] = $maxData['max(publication_number)'] ? $maxData['max(publication_number)'] + 1 : 1 ;
      }else{
        $data['identity'] = uuid_create();
      }

      $upData = [
        'name' => $data['name'],
        'desc' => $data['desc'],
        'price' => $data['price'],
        'amount' => $data['amount'],
        'level' => $data['level'] ,
        'identity' => $data['identity'],
        'publication_number' => $data['publication_number'],
        'real_count' => 0,
        'total_count' => $data['total_count'],
        'is_shelves' => isset($data['un_shelves']) &&  isset($data['un_shelves']) == 1 ? 0 : 1,

        'status' =>  in_array($data['status'],[-1,0,1,2]) ? $data['status'] : -1 ,
        'is_delete'=> 0,

        'update_time' => date('Y-m-d H:i:s'),
      ];
      if($data['thumb'] && trim($data['thumb'])){
        $upData['images'][0] =  $data['thumb'];
      }
      $id = PrizeModel::json(['images'])->insertGetId($upData);
      if ( $id ) {
          $this->log('添加抽奖成功，id='.$id,0);
          $url =url('admin/ScorePrize/index',['status'=>'all']);
          if($data['status'] > -1){
            $url = url('admin/ScorePrize/index',['status'=>'0,1,2']);
          }else if($data['status'] == -1){
            $url = url('admin/ScorePrize/index',['status'=>-1]);
          }
          return $this->success('添加成功',$url);
      } else {
          $this->log('添加抽奖失败',-1);
          return $this->jsonReturn(-1,'添加失败');
      }
    }else{
      $prize_status = config('score.prize_status');
      $id           = $this->request->param('id/d',0);
      $data = [];
      if($id){
        $data = PrizeModel::json(['images'])->find($id);
        $maxData = $this->checkMaxData($data['identity']);
        $data['thumb'] = is_array($data["images"]) ? $data["images"][0] : "" ;

        $data['publication_number'] = $maxData['max(publication_number)'] ? $maxData['max(publication_number)'] + 1 : 1 ;
        // $data['publication_number'] = (PrizeModel::where('identity',$data['identity'])->max('publication_number')) + 1  ;
      }
      return $this->fetch('add', ['data'=>$data,'id'=>$id,'prize_status' => $prize_status]);

    }
  }




  /**
   * 编辑
   * @param  [int] $id 抽奖id
   */
  public function edit($id){
    if(!$id){
      $this->error("Lost id");
    }
    $prize_data = PrizeModel::where("id",$id)->json(['images'])->find();
    if(!$prize_data){
      $this->error("抽奖不存在");
    }

    if ($this->request->isPost()) {
      $data               = $this->request->post();
      if($prize_data['status']<-1){
        $this->error('该次抽奖已结束或下架，无法修改');
      }
      if(in_array($prize_data['status'],[-1])){
        //开始验证字段
        $validate = new \app\score\validate\Prize;
        if (!$validate->check($data)) {
          return $this->jsonReturn(-1,$validate->getError());
        }
      }

      $upData = [
        'desc' => $data['desc'],
        'amount' => $data['amount'],
        'level' => $data['level'] ,
        'update_time' => date('Y-m-d H:i:s'),
        'is_shelves' =>isset($data['un_shelves']) && $data['un_shelves'] == 1 ? 0 : 1,

      ];

      if($prize_data['status'] > -1){
        $inArrayStatus = $prize_data['real_count'] > 0 ? [0,1,2] : [-1,0,1,2];
        $upData['status'] = in_array($data['status'],$inArrayStatus) ? $data['status'] : 0 ;
      }else if($prize_data['status'] == -1){
        $upData['status'] = in_array($data['status'],[-2,-1,0,1,2]) ? $data['status'] : -1 ;
        $upData['name'] = $data['name'];
        $upData['price'] = $data['price'];
        $upData['total_count'] = $data['total_count'];

      }
      if(in_array($prize_data['status'],[-1,-2])){
        $upData['is_delete'] = isset($data['is_show']) && $data['is_show'] == 1 ? 0 : 1;
      }
      if(isset($upData['total_count']) && $upData['total_count'] < 1){
          $this->error('触发开奖票数值不能少于1');
      }
      if(isset($upData['price']) && !is_float($upData['price']) && !is_numeric($upData['price']) ){
          $this->error('抽奖所需分数必须为数字');
      }


      if($data['thumb'] && trim($data['thumb'])){
        $upData['images'][0] =  $data['thumb'];
      }

      if (PrizeModel::json(['images'])->where('id',$id)->update($upData) !== false) {
          $this->log('保存抽奖成功，id='.$id,0);
          return $this->jsonReturn(0,'保存成功');
      } else {
          $this->log('保存抽奖失败，id='.$id,-1);
          return $this->jsonReturn(-1,'保存失败');
      }

    }else{

     $prize_data['is_show'] = $prize_data['is_delete'] ? 0 : 1 ;
     $prize_data['thumb'] = is_array($prize_data["images"]) ? $prize_data["images"][0] : "" ;

      // $auth['admin/ScorePrize/edit'] = $this->checkActionAuth('admin/ScorePrize/edit');
      $prize_status = config('score.prize_status');

      return $this->fetch('edit', ['data' => $prize_data,'prize_status' => $prize_status]);
    }
  }

  /**
   */
  public function prizes_unq($page=1,$keyword=''){
    $pagesize = 20;
    $map = [];
    if ($keyword) {
        $map[] = ['name|desc','like', "%{$keyword}%"];
    }
    $lists = PrizeModel::field('identity,max(id)')->group('identity')->where($map)->order('status Desc, id DESC')->select();

  }

  /**
   * 查询奖品最大期数等信息
   * @param  String $identity 奖品标识
   */
  public function checkMaxData($identity){
    if(!$identity){
      return false;
    }
    $maxData = PrizeModel::field('max(publication_number),max(id),max(status) as max_status,min(status) as min_status ,identity')->group('identity')->where('identity',$identity)->find();
    if($maxData && $maxData['max_status'] > -2 ){
      return $this->error("最新一期未结束，无法创建下一期");
    }
    return $maxData;
  }


}
