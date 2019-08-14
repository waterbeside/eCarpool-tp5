<?php

namespace app\admin\controller;

use app\common\model\DeptGroup as DeptGroupModel;
use app\user\model\Department;
use app\admin\controller\AdminBase;

/**
 * 部门权限组
 * Class DeptGroup
 * @package app\admin\controller
 */
class DeptGroup extends AdminBase
{
    protected $auth_group_model;
    protected $auth_rule_model;

    protected function initialize()
    {
        parent::initialize();
        $this->dept_group_model = new DeptGroupModel();
    }

    /**
     * 权限组
     * @return mixed
     */
    public function index()
    {
        $lists = $this->dept_group_model->select();
        return $this->fetch('index', ['lists' => $lists]);
    }

    /**
     * 添加权限组
     * @return mixed
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $DepartmentModel = new Department();
            $deptsArray = $DepartmentModel->excludeChildrens($data['depts']);
            if (!$deptsArray || !$data['title']) {
                $this->jsonReturn(-1, "标题或部门不能为空");
            }
            if (is_array($deptsArray)) {
                $data['depts'] = implode(',', $deptsArray);
            }
            if ($this->dept_group_model->save($data) !== false) {
                $this->jsonReturn(0, '保存成功');
            } else {
                $this->jsonReturn(-1, '保存失败');
            }
        } else {
            return $this->fetch();
        }
    }



    /**
     * 编辑权限组
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();

            if ($id == 1 && $data['status'] != 1) {
                $this->jsonReturn(-1, '超级管理组不可禁用');
            }
            if ($this->dept_group_model->save($data, $id) !== false) {
                $this->jsonReturn(0, '更新成功');
            } else {
                $this->jsonReturn(-1, '更新失败');
            }
        } else {
            $data = $this->dept_group_model->find($id);
            $deptsArray = explode(',', $data['depts']);
            $deptsData = [];
            $DepartmentModel = new Department();
            foreach ($deptsArray as $key => $value) {
                $deptsItemData = $DepartmentModel->field('id , path, fullname , name')->find($value);
                $deptsData[$value] = $deptsItemData ? $deptsItemData->toArray() : [];
            }
            return $this->fetch('edit', ['data' => $data, 'deptsData' => $deptsData]);
        }
    }



    /**
     * 删除权限组
     * @param $id
     */
    public function delete($id)
    {
        if ($id == 1) {
            $this->jsonReturn(-1, '超级管理组不可删除');
        }
        if ($this->dept_group_model->destroy($id)) {
            $this->jsonReturn(0, '删除成功');
        } else {
            $this->jsonReturn(-1, '删除失败');
        }
    }
}
