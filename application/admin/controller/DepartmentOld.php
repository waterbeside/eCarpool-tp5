<?php
namespace app\admin\controller;

use app\carpool\model\Department as DepartmentModel_o;
use app\user\model\Department as DepartmentModel;
use app\user\model\UserTemp ;
use app\carpool\model\CompanySub as CompanySubModel;
use app\admin\controller\AdminBase;
use think\facade\Config;
use think\Db;
use think\facade\Cache;

/**
 * 部门管理
 * Class Department
 * @package app\admin\controller
 */
class DepartmentOld extends AdminBase
{
    protected $department_model;

    protected function initialize()
    {
        parent::initialize();
        $this->department_model = new DepartmentModel_o();
    }

    /**
     * 部门管理
     * @param string $keyword
     * @param int    $page
     * @return mixed
     */
    public function index($keyword = '', $page = 1)
    {

        $map = [];
        if ($keyword) {
            $map[] = ['d.department_name','like', "%{$keyword}%"];
        }

        $fields = 'd.departmentid,d.department_name,d.is_active,d.sub_company_id,d.company_id,cs.sub_company_name,cs.city_name,c.company_name,c.short_name as company_short_name';
        $join = [
          ['company_sub cs','d.sub_company_id=cs.sub_company_id','left'],
          ['company c','d.company_id = c.company_id','left'],
        ];
        $order = 'd.company_id ASC , d.department_name, d.departmentid ASC ';

        $lists = $this->department_model->alias('d')->join($join)->where($map)->order($order)->field($fields)->paginate(50, false, ['query'=>request()->param()]);

        return $this->fetch('index', ['lists' => $lists, 'keyword' => $keyword]);
    }

    /**
     * 用于选择列表
     */
    public function public_lists(){
      $scid = $this->request->get('scid/d');
      $lists_cache = Cache::tag('public')->get('departments');
      if($lists_cache){
        $lists = $lists_cache;
      }else{
        $lists = $this->department_model->where('is_active',1)->order('departmentid ASC ')->select();
        if($lists){
          Cache::tag('public')->set('departments',$lists,3600);
        }
      }
      $returnLists = [];
      foreach($lists as $key => $value) {
        if(!$scid || ($scid > 0 && $scid == $value['sub_company_id'])){
          $returnLists[] = [
            'id'=>$value['departmentid'],
            'name'=>$value['department_name'],
            'status'=>$value['is_active'],
          ];
        }
      }
      // return json(['data'=>['lists'=>$returnLists],'code'=>0,'desc'=>'success']);
      $this->jsonReturn(0,['lists'=>$returnLists],'success');
    }

    /**
     * 添加部门
     * @return mixed
     */
    public function add()
    {
      if ($this->request->isPost()) {
          $data     = $this->request->param();
          // $validate_result = $this->validate($data, 'CompanySub');
          $validate_result = $this->validate($data,'app\carpool\validate\Department');

          if ($validate_result !== true) {
              $this->jsonReturn(-1,$validate_result);
          }

          $sub_company_name = CompanySubModel::where(['sub_company_id'=>$data['sub_company_id']])->value('sub_company_name');
          $data['sub_company_name'] = $sub_company_name ? $sub_company_name : '';

          if ($this->department_model->allowField(true)->save($data)) {
              $pk = $this->department_model->departmentid; //插入成功后取得id
              Cache::tag('public')->rm('departments');
              $this->log('新加部门成功，id='.$pk,0);
              $this->jsonReturn(0,'保存成功');
          } else {
            $this->log('新加部门失败',-1);
            $this->jsonReturn(-1,'保存失败');
          }

      }else{
        return $this->fetch();
      }
    }



    /**
     * 编辑部门
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          // $validate_result = $this->validate($data, 'CompanySub');
          $validate_result = $this->validate($data,'app\carpool\validate\Department');

          if ($validate_result !== true) {
              $this->jsonReturn(-1,$validate_result);
          }

          $sub_company_name = CompanySubModel::where(['sub_company_id'=>$data['sub_company_id']])->value('sub_company_name');
          $data['sub_company_name'] = $sub_company_name ? $sub_company_name : '';

          if ($this->department_model->allowField(true)->save($data, ['departmentid'=>$id]) !== false) {
              Cache::tag('public')->rm('departments');
              $this->log('更新部门成功，id='.$id,0);
              $this->jsonReturn(0,'更新成功');
          } else {
              $this->log('更新部门失败，id='.$id,-1);
              $this->jsonReturn(-1,'更新失败');
          }

       }else{
        $datas = $this->department_model->find($id);

        return $this->fetch('edit', ['datas' => $datas]);
      }
    }



    /**
     * 删除用户
     * @param $id
     */
    public function delete($id)
    {
        if ($this->department_model->destroy($id)) {
            Cache::tag('public')->rm('departments');
            $this->log('删除部门成功，id='.$id,0);
            $this->jsonReturn(0,'删除成功');
        } else {
            $this->log('删除部门失败，id='.$id,-1);
            $this->jsonReturn(-1,'删除失败');
        }
    }


    /**
     * /
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


}
