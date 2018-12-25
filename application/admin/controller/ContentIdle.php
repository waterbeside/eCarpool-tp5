<?php
namespace app\admin\controller;

use app\content\model\Idle as IdleModel;
use app\content\model\Category as CategoryModel;
use app\admin\controller\AdminBase;
use think\Db;


/**
 * 二手市场公开信息
 * Class Banners
 * @package app\api\controller
 */
class ContentIdle extends AdminBase
{

    protected function initialize()
    {
        parent::initialize();
    }


    public function index($filter=NULL,$pagesize=24){

      $fields = "t.id,t.user_id,t.title,t.desc,t.images,t.location,t.price,t.status,t.is_seller,t.post_time,t.original_price,t.show_level,u.name,u.phone,u.loginname";
      $map = [
        ["t.is_delete","<>",1],
        // ["u.loginname","exp",Db::raw("IS NOT NULL")],
        // ["show_level",">",0],
      ];

      if(isset($filter['keyword']) && $filter['keyword']){
        $map[] = ['t.title|t.desc','like','%'.$filter['keyword'].'%'];
      }
      if(isset($filter['keyword_user']) && $filter['keyword_user']){
        $map[] = ['u.name|u.nativename|u.loginname','like','%'.$filter['keyword'].'%'];
      }
      if(isset($filter['show_level']) && is_numeric($filter['show_level']) ){
        $map[] = ['t.show_level','=',$filter['show_level']];
      }
      if(isset($filter['status']) && is_numeric($filter['status']) ){
        $map[] = ['t.status','=',$filter['status']];
      }
      if(isset($filter['is_seller']) && is_numeric($filter['is_seller']) && $filter['is_seller'] > -1 ){
        $map[] = ['t.is_seller','=',$filter['is_seller']];
      }
      if(isset($filter['start_price']) && is_numeric($filter['start_price']) ){
        $map[] = ['t.price','>=',$filter['start_price']];
      }
      if(isset($filter['end_price']) && is_numeric($filter['end_price']) ){
        $map[] = ['t.price','<=',$filter['end_price']];
      }
      if($filter['time']){
        $time_arr = explode(' ~ ',$filter['time']);
        if(is_array($time_arr)){
          $time_s = date('Y-m-d H:i:s',strtotime($time_arr[0]));
          $time_e = date('Y-m-d H:i:s',strtotime($time_arr[1]) + 24*60*60);
          $map[] = ['post_time', 'between time', [$time_s, $time_e]];
        }
      }
      $join = [
        ['user u','u.uid = t.user_id', 'left']
      ];
      $orderby = "t.status DESC, post_time DESC";
      $results = IdleModel::alias('t')->field($fields)->json(['images'])->where($map)->join($join)->order($orderby)->paginate($pagesize, false,  ['query'=>request()->param()]);
      // dump($results);
      $datas = [];
      // if(!$datas){
      //   $this->jsonReturn(20002,[],'No Data');
      // }
      // dump($datas);exit;
      foreach ($results as $key => $value) {
        $results[$key]['thumb']       = isset($value['images'][0]->path) ? $value['images'][0]->path : '';
        $results[$key]['thumb']       = str_replace('http:/g','http://g',$results[$key]['thumb']);
        unset($results[$key]['images']);
      }
      $categorys_list = (new CategoryModel())->getList();
      $returnData = [
        'lists'=>$results,
        'pagesize'=>$pagesize,
        'filter'=>$filter,
        'status_list'=>  config('content.idle_status'),
        'showLevel_list'=>  config('content.show_level'),
        'categorys_list'=>  $categorys_list,
      ];
      return $this->fetch('index', $returnData);
    }


    public function read($id){
      $fields = "t.id,t.user_id,t.title,t.desc,t.images,t.location,t.price,t.status,t.is_seller,t.post_time,t.is_delete,t.original_price,u.name,u.phone,u.loginname";
      $map = [
        ["t.id","=",$id],
      ];
      $join = [
        ['user u','u.uid = t.user_id', 'left']
      ];
      $orderby = "t.status DESC, post_time DESC";
      $datas = IdleModel::alias('t')->field($fields)->json(['images'])->where($map)->join($join)->find();
      if(!$datas || $datas['is_delete'] == 1){
        $this->jsonReturn(20002,[],'No Data');
      }
      $datas['images'] = json_decode(json_encode($datas['images']),true);
      $imagesList = [];
      foreach ($datas['images'] as $key => $value) {
        if(isset($value['path'])){
          $value['path'] = str_replace('http:/g','http://g',$value['path']);
        }
        $imagesList[] = $value;
      }
      $datas['images'] = $imagesList;
      $datas['thumb']       = isset($datas['images'][0]['path']) ? $datas['images'][0]['path'] : '';
      $categorys_list = (new CategoryModel())->getList();
      $returnData = [
        'data'=>$datas,
        'status_list'=>  config('content.idle_status'),
        'showLevel_list'=>  config('content.show_level'),
        'categorys_list'=>  $categorys_list,
      ];
      return $this->fetch('read', $returnData);

    }


    /**
     * 审
     * @param  integer $id [description]
     */
    public function audit($id = NULL){
      $show_level          = $this->request->post('show_level');
      if(!$id || !$show_level){
        $this->jsonReturn(-1,'参数错误');
      }
      $data = [
        'show_level'=>$show_level,
      ];
      $map = [];
      $map[] = is_array($id) ? ['id','in',$id] : ['id','=',$id];
      $res = IdleModel::where($map)->update($data);
      if($res === false){
        $this->jsonReturn(-1,'提交失败');
      }
      $this->jsonReturn(0,'成功');
    }



    public function recache(){
      $redisObj = $this->redis();
      $redisObj->delete('carpool:idle:content');
      $this->jsonReturn(0,'success');
    }




}
