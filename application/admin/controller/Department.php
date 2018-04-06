<?php
namespace app\admin\controller;

use app\carpool\model\Department as DepartmentModel;
use app\carpool\model\CompanySub as CompanySubModel;
use app\common\controller\AdminBase;
use think\Config;
use think\Db;
use think\Cache;

/**
 * 部门管理
 * Class Department
 * @package app\admin\controller
 */
class Department extends AdminBase
{
    protected $department_model;

    protected function _initialize()
    {
        parent::_initialize();
        $this->department_model = new DepartmentModel();
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
            $map['d.department_name'] = ['like', "%{$keyword}%"];
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
        if(!$scid || ($scid > 0 && $scid == $value['company_id'])){
          $returnLists[] = [
            'id'=>$value['departmentid'],
            'name'=>$value['department_name'],
            'status'=>$value['is_active'],
          ];
        }
      }
      return json(['data'=>['lists'=>$returnLists],'code'=>0,'desc'=>'success']);
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
              $this->error($validate_result);
          }

          $sub_company_name = CompanySubModel::where(['sub_company_id'=>$data['sub_company_id']])->value('sub_company_name');
          $data['sub_company_name'] = $sub_company_name ? $sub_company_name : '';

          if ($this->department_model->allowField(true)->save($data)) {
              $this->success('保存成功');
          } else {
              $this->error('保存失败');
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
              $this->error($validate_result);
          }

          $sub_company_name = CompanySubModel::where(['sub_company_id'=>$data['sub_company_id']])->value('sub_company_name');
          $data['sub_company_name'] = $sub_company_name ? $sub_company_name : '';

          if ($this->department_model->allowField(true)->save($data, ['departmentid'=>$id]) !== false) {
              $this->success('更新成功');
          } else {
              $this->error('更新失败');
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
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }
}
