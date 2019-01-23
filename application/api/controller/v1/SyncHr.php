<?php
namespace app\api\controller\v1;

use app\api\controller\ApiBase;
use app\carpool\model\User as OldUserModel;
// use app\user\model\UserTest as OldUserModel ;
use app\user\model\User as NewUserModel;
use app\user\model\Department;
use app\user\model\UserTemp ;
use my\RedisData;
use think\Db;

/**
 * 同步hr系统
 * Class Passport
 * @package app\api\controller
 */
class SyncHr extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
    }


    protected function check_localhost($returnType = 0){
      $host = $this->request->host();
      // dump($this->request->port());exit;
      // && !in_array($host,["gitsite.net:8082","admin.carpoolchina.test"])
      if(strpos($host,'127.0.0.1') === false && strpos($host,'localhost') === false ){
        return $returnType ?  $this->error(lang('Illegal access')) : false ;
      }else{
        return true;
      }
    }


    /**
     *
     */
    public function all($date = null, $type = 0, $page = 0, $pagesize = 30 )
    {
      $this->check_localhost(1);
      ini_set ('memory_limit', '128M');
      ini_set('max_execution_time','180');

      $userTempModel = new UserTemp();
      if($type == 1){
        $whereMap = [
          ['status','<',1],
          ['status','>',-2],
        ];
        if($date){
          $whereMap[] = ['modifty_time', '>=' ,$date];
        }
        if($page > 0 ){
          // $res =  $userTempModel->where($whereMap)->page($page,$pagesize)->select();
          $total =  $userTempModel->where($whereMap)->count();
          if($total < 1){
            $this->jsonReturn(20002,($page > 1 ? '同步完成' : '没有要更新的数据'));
          }
          $res =  $userTempModel->where($whereMap)->limit($pagesize)->select();
        }else{
          $res =  $userTempModel->where($whereMap)->select();
        }
        if(!$res){
          $this->jsonReturn(20002,"没有要更新的数据");
        }
      }else{
        $res = $userTempModel->pullListFromHr($date);
        if($res===false){
          $this->jsonReturn(-1,$userTempModel->errorMsg);
        }
      }

      $success ='';
      $fail = '';
      if($type > 0){
        foreach ($res as $key => $value) {
          $res_toPrimary = $userTempModel->toPrimary($value['code'],$value);
          if($res_toPrimary){
            $success .=$value['code'].",";
          }else{
            $fail    .=$value['code'].",";
          }
        }
      }
      if($type == 1 && $page > 0 ){
          $returnData = [
            "list"=>$res,
            "total"=>$total,
            "page"=>$page,
            "success"=>$success,
            "fail"=>$fail,
          ];
          $this->jsonReturn(0,$returnData,"同步成功");
      }else{
          $returnData = [
            "list"=>$res,
          ];
          if($type > 0){
            $returnData['success']=$success;
            $returnData['fail']=$fail;
            $returnData['total']=count($res);
          }
          $this->jsonReturn(0,$returnData,"拉取成功");
      }
    }



    public function single($code=0,$tid=0,$is_sync=0){
      if(!$this->check_localhost() && !$this->checkPassport()){
        $this->error(lang('Illegal access'));
      }
      if(!$code && !$tid){
        return $this->jsonReturn(992,[],lang('Parameter error'));
      }
      $userTempModel = new UserTemp();
      if($code){
        $tid = 0;
        $res = $userTempModel->pullUserFromHr($code,$is_sync);
        if(!$res){
          $this->jsonReturn(-1,$userTempModel->errorMsg);
        }
      }
      if($tid){
        $res = $userTempModel->where('id',$tid)->find();
        if(!$res){
          return $this->jsonReturn(20002,'无此数据');
        }
        if(!in_array($res['status'],[-1,0])){
          return $this->jsonReturn(-1,['status'=>$res['status']],'已同步过，无需再同步');
        }
      }

      if($res['code'] == -2){
        return $this->jsonReturn(-1,$res,$userTempModel->errorMsg);
      }

      if($res['code'] == -1 ){
        $userData = OldUserModel::where('loginname',$code)->find();
        if(!$userData){
          return $this->jsonReturn(20002,$res,"用户不存在");
        }
        if($userData &&  in_array($userData["company_id"],[1,11]) && !in_array($userData['Department'],['李宁','高明常安花园','高明一中','佛山市政府'])){
          // if($is_sync) OldUserModel::where('uid',$userData['uid'])->update(['is_active'=>0,'modifty_time'=>date("Y-m-d H:i:s")]);
          return $this->jsonReturn(10003,$res,"用户已离积");
        }
        return $this->jsonReturn(20002,$res,"用户不存在");
      }
      if($is_sync){
        $res_toPrimary = $userTempModel->toPrimary($res['code'],$res);
        if(!$res_toPrimary){
          $this->jsonReturn(-1,['status'=>$res['status']],($tid ? "同步失败": $userTempModel->errorMsg ));
        }
        $this->jsonReturn(0,$res_toPrimary,"同步成功");
      }else{
        $this->jsonReturn(0,$res,"success");
      }
      exit;
    }


    /**
     * 推用户数据到主库比较并更新
     * @param  string $code [description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function to_primary($code=''){
      $this->check_localhost(1);
      $userTempModel = new UserTemp();
      $res = $userTempModel->toPrimary($code);
      if(!$res){
        $this->jsonReturn(-1,$userTempModel->errorMsg);
      }
     $this->jsonReturn(0,$res,$userTempModel->errorMsg);

    }


    /**
     * 创建部门 并返回部门 id
     */
    public function create_department(){
       $this->check_localhost(1);
       $fullname  = input('post.fullname');
       if(!$fullname){
         $this->jsonReturn(992,'Param:fullname error');
       }
       // $sep       = input('post.sep',',');
       // if(!in_array($sep,['/',','])){
       //   $this->jsonReturn(992,'Param:ep error');
       // }
       $fullname = str_replace(',','/',$fullname);

       $DepartmentModel = new Department();

       $res = $DepartmentModel->create_department_by_str($fullname);
       if(!$res){
         $this->jsonReturn(-1,'fail');
       }
       $res['format_name'] = $DepartmentModel->formatFullName($res['fullname'],1);
       $returnData = $res;
       $this->jsonReturn(0,$returnData,'success');
    }


    /**
     * 取得部门
     */
    public function department($id,$uncache=0){
       $this->check_localhost(1);
       $DepartmentModel = new Department();
       $department = $DepartmentModel->itemCache($id);
       if(!$department || $uncache){
         $department =  $DepartmentModel->find($id);
         if(!$department){
           $this->jsonReturn(20002,Lang('No data'));
         }
         $department = $department->toArray();
         $DepartmentModel->itemCache($id,$department,3600*24);
       }
       $department['department_format'] = $DepartmentModel->formatFullName($department['fullname']);
       $this->jsonReturn(0,$department,'success');
    }



}
