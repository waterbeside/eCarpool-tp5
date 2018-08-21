<?php
namespace app\admin\controller;

use app\score\model\Account as ScoreAccountModel;
use app\carpool\model\User as CarpoolUserModel;
use app\carpool\model\WaitingProcess;
use app\carpool\model\Info as InfoModel;
use app\admin\controller\AdminBase;
use think\Db;

/**
 * 无效拼车处理
 * Class Link
 * @package app\admin\controller
 */
class ScoreCarpool extends AdminBase
{

  /**
   * 失败拼车管理
   * @param string $keyword
   * @param int    $page
   * @return mixed
   */
  public function fails($keyword = '', $page = 1,$pagesize = 30)
  {
      $status   = input('param.status/s',"");

      $map = [];
      if ($keyword) {
          $map[] = ['t.infoid|t.code|d_name|p_name','like', "%{$keyword}%"];
      }
      if($status!==""){
        $map[] = ['t.status','=',$status];

      }

      $subJoin = [
        ['user d','i.carownid = d.uid','left'],
        ['user p','i.passengerid = p.uid','left'],
        ['address s','i.startpid = s.addressid','left'],
        ['address e','i.endpid = e.addressid','left'],
      ];
      $subField = "infoid,i.time as info_time ,i.status as info_status, startpid ,endpid,carownid,passengerid,
                    d.loginname as d_loginname , d.name as d_name , d.phone as d_phone,
                    p.loginname as p_loginname , p.name as p_name , p.phone as p_phone,
                    s.addressname as s_addressname , s.latitude as s_latitude , s.longtitude as s_longtitude ,
                    e.addressname as e_addressname ,  e.latitude as e_latitude , e.longtitude as e_longtitude
                    ";
      $subSql = InfoModel::alias('i')->field($subField)->join($subJoin)->buildSql();

      $lists = WaitingProcess::alias('t')->join([$subSql=> 'ii'], 't.infoid = ii.infoid','left')->where($map)->order('wpid DESC')->paginate($pagesize, false,  ['query'=>request()->param()]);
      foreach ($lists as $key => $value) {
        $lists[$key]['info_time'] =date('Y-m-d H:i',strtotime($value['info_time'].'00'));
      }
      // $lists = WaitingProcess::alias('w')->join([$subSql=> 'ii'], '.w.infoid = ii.infoid','left')->where($map)->order('wpid DESC')->fetchSql()->select();

      return $this->fetch('fails', ['lists' => $lists, 'keyword' => $keyword,'pagesize'=>$pagesize,'status'=>$status]);
  }


  //处理不合格拼车是否得分
  public function fail_operate(){
    if ($this->request->isPost()) {
      $id = input("post.id/d",0);
      $status = input("post.status/d",0);
      $admin_id = $this->userBaseInfo['uid'];
      if(!$id){
        $this->error("Params error");
      }
      $rowData = WaitingProcess::where('wpid',$id)->find();
      if(!$rowData ||!$rowData['infoid'] ){
        $this->error("行程不存在");
      }
      if($rowData['status'] == 1 || $rowData['status']==-1){
        $this->error("该条已处理，不可操作");
      }
      // dump($rowData['infoid']);
      if($status === 1){

        $result = Db::connect('database_carpool')->query('call update_score_by_infoid(:infoid,:admin_id)', [
          'infoid' => $rowData['infoid'],
          'admin_id' => $admin_id,
        ]);
        if($result){
          $resultData = json_decode($result[0][0]["result"],true);
          if($resultData['code']===0){
            $this->success("提交成功");
          }else{
            $this->error("提交失败");
          }
        }else{
          $this->error("提交失败");
        }
      }elseif($status === -1){
         // $result = WaitingProcess::where('wpid',$id)->update(["status"=>-1]);
         //
         $result = Db::connect('database_carpool')->query('call add_history_by_infoid(:infoid,:admin_id)', [
           'infoid' => $rowData['infoid'],
           'admin_id' => $admin_id,
         ]);
         if($result){
           $resultData = json_decode($result[0][0]["result"],true);
           // dump($resultData);
           if($resultData['code']===0){
             $this->success("提交成功");
           }else{
             $this->error("提交失败");
           }
         }else{
           $this->error("提交失败");
         }

      }else{
        $this->error("不作处理");
      }


    }else{
      $id = input("param.id/d",0);
      if(!$id){
        $this->error("Lost id");
      }
      $subJoin = [
        ['user d','i.carownid = d.uid','left'],
        ['user p','i.passengerid = p.uid','left'],
        ['address s','i.startpid = s.addressid','left'],
        ['address e','i.endpid = e.addressid','left'],
      ];
      $subField = "infoid,i.time as info_time ,i.status as info_status, startpid ,endpid,carownid,passengerid,
                    d.loginname as d_loginname , d.name as d_name , d.phone as d_phone,
                    p.loginname as p_loginname , p.name as p_name , p.phone as p_phone,
                    s.addressname as s_addressname , s.latitude as s_latitude , s.longtitude as s_longtitude ,
                    e.addressname as e_addressname ,  e.latitude as e_latitude , e.longtitude as e_longtitude
                    ";
      $subSql = InfoModel::alias('i')
        ->field($subField)
        ->join($subJoin)
        ->buildSql();
      $data = WaitingProcess::alias('t')->join([$subSql=> 'ii'], 't.infoid = ii.infoid','left')->where('wpid',$id)->find();
      if(!$data){
        $this->error("no data");
      }
      $data['info_time'] = date('Y-m-d H:i',strtotime($data['info_time'].'00'));

      return $this->fetch('', ['data' => $data]);



    }

  }


}
