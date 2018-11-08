<?php
namespace app\admin\controller;


use app\admin\controller\AdminBase;

use app\carpool\model\User as OldUserModel;
use app\user\model\User as NewUserModel;
use app\user\model\UserTemp ;
use Firebase\JWT\JWT;
use my\RedisData;
use think\Db;

/**
 * 同步hr系统
 * Class Passport
 * @package app\api\controller
 */
class SyncHr extends AdminBase
{



  /**
   * 用户管理
   * @param string $keyword
   * @param int    $page
   * @return mixed
   */
  public function index($filter = [], $page = 1,$pagesize = 50)
  {
      $map = [];
      //筛选用户信息
      if (isset($filter['keyword']) && $filter['keyword'] ){
        $map[] = ['code|name','like', "%{$filter['keyword']}%"];
      }
      //筛选部门
      if (isset($filter['keyword_dept']) && $filter['keyword_dept'] ){
        $map[] = ['department','like', "%{$filter['keyword_dept']}%"];
      }
      //筛选部门
      if (isset($filter['status']) &&  is_numeric($filter['status'])  ){
        $map[] = ['status','=', $filter['status']];
      }

      $lists = UserTemp::alias('u')->where($map)->order('id DESC')->paginate($pagesize, false,  ['query'=>request()->param()]);

      // dump($lists);exit;
      //
      $port = $this->request->port();
      return $this->fetch('index', ['lists' => $lists, 'filter' => $filter,'pagesize'=>$pagesize,'port'=>$port]);
  }



  /**
   *
   */

  public function sync_all($date = null, $type = 0, $page = 0, $pagesize = 50,$return = 1 )
  {

    ini_set ('memory_limit', '128M');
    ini_set('max_execution_time','180');
    $url = "http://127.0.0.1:8082/api/v1/sync_hr/all";
    $params = [
      'type'=>$type,
      'page'=>$page,
      'pagesize'=>$pagesize,
    ];
    if($date){ $params['date'] = $date; }

    try {
      $client = new \GuzzleHttp\Client();
      $response = $client->request('get', $url, ['query' => $params]);
      $content = $response->getBody()->getContents();

      $res = json_decode($content,true);
    } catch (Exception $e) {
      // $this->errorMsg ='拉取失败';
      $this->error("同步失败");
    }
    if(!$res){
      $this->error("同步失败");
    }
    if($res && $res['code'] ===20002){
      return  $type==1 && $page >0 && $return ? $this->fetch('index/multi_jump',['url'=>"",'msg'=>$res['desc']]) : $this->jsonReturn($res['code'],$res['data'],$res['desc']);
    }
    if($res && $res['code'] !== 0){
      return $this->jsonReturn($res['code'],$res['data'],$res['desc']);
    }

    $data = $res['data'];
    if($type == 1 && $page > 0  && $return ){
          $jumpUrl  = url('sync_all',['date'=>$date,'type'=>$type,'page'=>$page+1,'pagesize'=>$pagesize,'return'=>$return]);
          if($data['total'] > 0){
            $msg = "total:".$data['total']."<br />";
            $successMsg = "success:<br />";
            foreach ( explode(',',$data['success']) as $key => $value) {
              $br = $key%2 == 1 ? "<br />" : "";
              $successMsg .= $value.",".$br;
            }
            $failMsg = "fail:<br />";
            foreach ( explode(',',$data['fail']) as $key => $value) {
              $br = $key%2 == 1 ? "<br />" : "";
              $failMsg .= $value.",".$br;
            }
            $msg .= $successMsg."<br />".$failMsg."<br />";
            return $this->fetch('index/multi_jump',['url'=>$jumpUrl,'msg'=>$msg]);
          }else{
            return $this->success("同步完成");
          }
    }else{
      return $this->jsonReturn($res['code'],$res['data'],$res['desc']);
    }

  }





    public function sync_single($code=0,$tid=0){

      $url = "http://127.0.0.1:8082/api/v1/sync_hr/single";
      $params = [
        'code'=>$code,
        'tid'=>$tid,
      ];

      try {
        $client = new \GuzzleHttp\Client();
        $response = $client->request('get', $url, ['query' => $params]);
        $content = $response->getBody()->getContents();
        $res = json_decode($content,true);
      } catch (Exception $e) {
        // $this->errorMsg ='拉取失败';
        $this->jsonReturn(0,'同步失败');
      }
      if($content){
        return $this->jsonReturn($res['code'],$res['data'],$res['desc']);
      }
      exit;
    }








}
