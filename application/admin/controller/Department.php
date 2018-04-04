<?php
namespace app\admin\controller;

use app\carpool\model\Department as DepartmentModel;
use app\common\controller\AdminBase;
use think\Config;
use think\Db;

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
          ['company_sub cs','d.sub_company_id=cs.sub_company_id'],
          ['company c','d.company_id = c.company_id'],
        ];
        $order = 'd.company_id ASC , d.department_name, d.departmentid ASC ';

        $lists = $this->department_model->alias('d')->join($join)->where($map)->order($order)->field($fields)->paginate(50, false, ['page' => $page]);

        return $this->fetch('index', ['lists' => $lists, 'keyword' => $keyword]);
    }

    /**
     * 添加部门
     * @return mixed
     */
    public function add()
    {
        return $this->fetch();
    }



    /**
     * 编辑部门
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        $datas = $this->department_model->find($id);
        return $this->fetch('edit', ['datas' => $datas]);
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
