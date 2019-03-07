<?php
namespace app\admin\controller;

use app\user\model\Department as DepartmentModel;
use app\user\model\UserTemp ;
use app\carpool\model\User ;

use app\admin\controller\AdminBase;
use think\facade\Config;
use think\Db;
use think\facade\Cache;
use my\Tree;

/**
 * 部门管理
 * Class Department
 * @package app\admin\controller
 */
class Department extends AdminBase
{

    public  $un_check = ['admin/department/list_dialog'];

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * 部门管理
     * @param string $keyword
     * @param int    $page
     * @return mixed
     */
    public function index($pid=0,$filter = [], $page = 1, $pagesize = 50, $returnType = 1)
    {

        $map = [];
        if(is_numeric($pid) && (!isset($filter['is_all']) || !$filter['is_all'])){
          $map[] = ['pid','=', $pid];
        }else if(is_numeric($pid)){
          $pid = 0;
        }
        $deep = false;
        if(!is_numeric($pid) && strpos($pid,'p_') === 0){
          $deep = intval(str_replace('p_','',$pid));
          $map[] = ['deep','=', $deep];
        }
        if(isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['fullname','like', "%".$filter['keyword']."%"];
        }
        $fields = '*';
        $order = 'name ';

        $DepartmentModel = new DepartmentModel();
        $lists = $DepartmentModel->where($map)->order($order)->field($fields)->paginate($pagesize, false, ['query'=>request()->param()]);

        //start 父级部门导航
        $path_data = $DepartmentModel->where('id',$pid)->find();
        $path_array = $path_data ? explode(',',$path_data['path']) : [];
        $path_array[] = $pid;
        $fullname_array = explode(',',$path_data['fullname']);
        $path = [];
        foreach ($path_array as $key => $value) {
          $path[$key]['id'] = $value;
          $path[$key]['name'] = $key > 0 ? $fullname_array[$key-1] : "HOME";
        }
        //end 父级部门导航

        $returnData = [
          'lists' => $lists,
          'filter' => $filter,
          'pid'=>$pid,
          'path' => $path,
          'deep' => $deep,
          'pagesize' => $pagesize,
        ];
        return $returnType ? $this->fetch('index',$returnData ) : $returnData;
    }


    /**
     * 部门对话框
     * @return mixed
     */
    public function list_dialog($pid=0,$filter = [], $page = 1,$fun = "select_dept",$multi = 0)
    {
      $returnData = $this->index($pid,$filter,$page,10,0);
      $returnData['fun'] = $fun;
      $returnData['multi'] = $multi;

      return $this->fetch('list_dialog',$returnData );
    }

    /**
     * 用于选择列表
     */
    public function public_lists($deep=4,$pid = 0){
      $map = [];
      if($pid>0){
        $map[] = ['','exp', Db::raw("FIND_IN_SET($pid,pid)")];
        $cacheKey = 'carpool:department:pid:'.$pid;
      }else{
        $map[] = ['deep','=',$deep];
        $cacheKey = 'carpool:department:deep:'.$deep;
      }
      $lists_cache = Cache::tag('public')->get($cacheKey);
      if($lists_cache){
        $lists = $lists_cache;
      }else{
        $lists = DepartmentModel::where($map)->order('fullname ASC ')->select();
        if($lists){
          Cache::tag('public')->set($cacheKey,$lists,3600*4);
        }
      }
      $this->jsonReturn(0,['lists'=>$lists],'success');
    }




    /**
     * 临时方法，创建所有部门数据
     */
    public function create_all_department($page = 0, $pagesize = 50,$return = 1){
      if($page>0){
        $lists = UserTemp::field('department')->group('department')->page($page,$pagesize)->select();
      }else{
        $lists = UserTemp::field('department')->group('department')->select();
      }
      // dump($lists);exit;
      if(!count($lists)){
        if($page>0 && $return ){
          return $this->fetch('index/multi_jump',['url'=>'','msg'=>'完成']);
        }else{
          return $this->jsonReturn(20002,"No Data");
        }
      }
      $department_model = new DepartmentModel();
      $success = [];
      $fail = [];
      foreach ($lists as $key => $value) {
        $res = $department_model->create_department_by_str($value['department']);
        if($res){
          $success[] = $value['department'];
        }else{
          $fail[]    = $value['department'];
        }
      }
      if( $return ){
            $jumpUrl  = url('create_all_department',['page'=>$page+1,'pagesize'=>$pagesize,'return'=>$return]);
            $msg = "";
            $successMsg = "success:<br />";
            foreach ( $success as $key => $value) {
              $br = $key%2 == 1 ? "<br />" : "";
              $successMsg .= $value.",".$br;
            }
            $failMsg = "fail:<br />";
            foreach ( $fail as $key => $value) {
              $br = $key%2 == 1 ? "<br />" : "";
              $failMsg .= $value.",".$br;
            }
            $msg .= $successMsg."<br />".$failMsg."<br />";
            return $this->fetch('index/multi_jump',['url'=>$jumpUrl,'msg'=>$msg]);

      }else{
        $this->jsonReturn(0,["success"=>$success,"fail"=>$fail],"成功");
      }
    }

    /**
     * 临时方法，把Department字段为空的用户填上
     */
     public function reset_user_department($page = 0, $pagesize = 20){
       $map = [
         ['department_id','>',0],
         ['company_id','in',[1,11]],
       ];
       $fields = 'department_id,company_id,count(department_id) as c';
       $group = 'department_id,company_id';
       $total =  User::group($group)->where($map)->count(); //总条数

       $lists =  User::field($fields)
        ->group($group)->where($map)
        ->page($page,$pagesize)->select();

       if(!count($lists) && $page>0 ){
         return $this->fetch('index/multi_jump',['url'=>'','msg'=>'完成']);
       }
       $success = [];
       $fail = [];
       $department_model = new DepartmentModel();
       foreach ($lists as $key => $value) {
         $department_data = $department_model->getItem($value['department_id']);
         $format_department = $department_data['department_format']['format_name'];
         $value['format_department'] = $format_department;
         // dump($format_department);
         //执行更新
         $updateMap = [
           ['company_id','=',$value['company_id']],
           ['department_id','=',$value['department_id']],
         ];
         $updateData = [
           'Department'=>$format_department,
           'companyname'=>$department_data['department_format']['branch']
         ];
         if($department_data['department_format']['branch'] && $department_data['department_format']['department']){
           $updateRes = User::where($updateMap)->update($updateData);
         }else{
           $updateRes = 0;
         }
         $value['update_res'] = $updateRes;
         if($updateRes){
           $success[] = $value;
         }else{
           $fail[] = $value;
         }
       }
       // dump($lists);
       $jumpUrl  = url('reset_user_department',['page'=>$page+1,'pagesize'=>$pagesize]);
       $msg = "";
       $successMsg = "success:<br />";
       foreach ( $success as $key => $value) {
         $br = "<br />";
         $successMsg .= $value['department_id'].":".$value['format_department'].":".$value['update_res'].$br;
       }
       $failMsg = "fail:<br />";
       foreach ( $fail as $key => $value) {
         $br = "<br />";
         $failMsg .= $value['department_id'].":".$value['format_department'].":".$value['update_res'].$br;
       }
       $msg .= $successMsg."<br />".$failMsg."<br />";
       // return $this->fetch('index/multi_jump',['url'=>'','msg'=>$msg]);
       return $this->fetch('index/multi_jump',['url'=>$jumpUrl,'msg'=>$msg]);

     }


}
