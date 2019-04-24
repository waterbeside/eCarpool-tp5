<?php
namespace app\api\controller\v1\publics;

use app\api\controller\ApiBase;
use app\content\model\Idle as IdleModel;
use my\RedisData;


use think\Db;

/**
 * 二手市场公开信息
 * Class Idle
 * @package app\api\controller
 */
class Idle extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }

    /**
     * 列表
     * @param  integer $pagesize  每页条数
     */
    public function index($pagesize=24){
      $filter['keyword']      = trim(input('param.keyword'));
      $filter['is_seller']    = input('param.is_seller',-1);
      $filter['start_price']  = input('param.start_price');
      $filter['end_price']  = input('param.end_price');
      $filter['start_time']  = input('param.start_time');
      $filter['end_time']  = input('param.end_time');
      $filter['cateid']  = input('param.cateid');

      $fields = "t.id,t.user_id,t.title,t.desc,t.images,t.location,t.price,t.status,t.is_seller,t.post_time,u.name,u.phone,u.loginname";
      $map = [
        ["t.is_delete","<>",1],
        ["u.loginname","exp",Db::raw("IS NOT NULL")],
        // ["show_level",">",0],
      ];

      if($filter['keyword']){
        $map[] = ['t.title|t.desc','like','%'.$filter['keyword'].'%'];
      }
      if(is_numeric($filter['cateid']) && $filter['cateid'] > 0 ){
        $map[] = ['ic.category_id','=',$filter['cateid']];
      }
      if(!is_numeric($filter['cateid']) && strpos($filter['cateid'],'!')===0  ){
        $cateArr = explode('!',$filter['cateid']);
        if(is_numeric($cateArr[1]) && $cateArr[1] >0 ) $map[] = ['ic.category_id','<>',$cateArr[1]];
      }
      if(is_numeric($filter['is_seller']) && $filter['is_seller'] > -1 ){
        $map[] = ['t.is_seller','=',$filter['is_seller']];
      }
      if(is_numeric($filter['start_price']) ){
        $map[] = ['t.price','>=',$filter['start_price']];
      }
      if(is_numeric($filter['end_price']) ){
        $map[] = ['t.price','<=',$filter['end_price']];
      }
      if(is_numeric($filter['start_time']) && $filter['start_time'] > 1200000000 ){
        $map[] = ['t.post_time','>= time',date('Y-m-d H:i:s',$filter['start_time'])];
      }
      if(is_numeric($filter['end_time']) && $filter['end_time'] > 1200000000 ){
        $map[] = ['t.post_time','<= time',date('Y-m-d H:i:s',$filter['end_time'])];
      }
      $join = [
        ['user u','u.uid = t.user_id', 'left'],
        ['(select max(category_id) as category_id ,idle_id from t_idle_category group by idle_id) ic','ic.idle_id = t.id', 'left']
      ];
      $orderby = "t.status DESC, post_time DESC";
      $results = IdleModel::alias('t')->field($fields)->json(['images'])->where($map)->join($join)->order($orderby)->paginate($pagesize, false,  ['query'=>request()->param()])->toArray();

      $datas = $results['data'];
      if(!$datas){
        $this->jsonReturn(20002,[],'No Data');
      }
      // dump($datas);exit;
      foreach ($datas as $key => $value) {
        // $datas[$key]['post_time']   =
        $datas[$key]['time']   = strtotime($value['post_time']);
        $datas[$key]['thumb']       = isset($value['images'][0]->path) ? $value['images'][0]->path : '';
        $datas[$key]['thumb']       = str_replace('http:/g','http://g',$datas[$key]['thumb']);
        unset($datas[$key]['images']);
      }
      $pageData = [
        'total'=>$results['total'],
        'pageSize'=>$results['per_page'],
        'lastPage'=>$results['last_page'],
        'currentPage'=> intval($results['current_page']),
      ];
      $returnData = [
        'lists'=>$datas,
        'page' =>$pageData,
        'filter'=>$filter
      ];
      $this->jsonReturn(0,$returnData,'success');
    }



    /**
     * 详情
     * @param  integer id  id
     */
    public function read($id){
      $fields = "t.id,t.user_id,t.title,t.desc,t.images,t.location,t.price,t.status,t.is_seller,t.post_time,t.is_delete,u.name,u.phone,u.loginname";
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
      $datas['time']   = strtotime($datas['post_time']);
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
      unset($datas['is_delete']);
      $this->jsonReturn(0,$datas,'success');
    }



}
