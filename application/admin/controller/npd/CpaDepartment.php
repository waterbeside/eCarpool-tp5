<?php

namespace app\admin\controller\npd;

use app\user\model\Department as Department;
use app\npd\model\CpaDepartment as CpaDepartmentModel;
use app\admin\controller\npd\NpdAdminBase;
use think\Db;

/**
 * CpaUser NPD允许登入的Carpool部门管理
 * Class CpaDepartment
 * @package app\admin\controller\npd
 */

class CpaDepartment extends NpdAdminBase
{

    public function index($filter = [], $page = 1, $pagesize = 15)
    {
        $map   = [];
        // $map[] = ['t.is_delete', '=', Db::raw(0)];

        $field = 't.*';

        //筛选用户信息
        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['fullname', 'like', "%{$filter['keyword']}%"];
        }
        
        //筛选是否被删的用户
        $is_delete = isset($filter['is_delete']) && $filter['is_delete'] ? Db::raw(1) : Db::raw(0);
        $map[] = ['is_delete', '=', $is_delete];

        //筛选状态用户
        if (isset($filter['status']) && is_numeric($filter['status'])) {
            $status = $filter['status'] ? Db::raw(1) : Db::raw(0);
            $map[] = ['status', '=', $status];
        }
        $CpaDepartmentModel = new CpaDepartmentModel();

        $lists  = CpaDepartmentModel::field($field)->alias('t')->where($map)->order('t.create_time DESC, t.id DESC')
            ->paginate($pagesize, false, ['page' => $page]);

        $returnData = [
            'lists' => $lists,
            'filter' => $filter,
            'pagesize'=>$pagesize
        ];
        return $this->fetch('index', $returnData);
    }


    /**
     * 添加用户
     * @return mixed
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data            = $this->request->post();
            $departmentIds        = $data['department_ids'] ?? '';
            if (empty($departmentIds)) {
                return $this->jsonReturn(992, '请选择部门');
            }
            $departmentIdsArray = explode(',', $departmentIds);
            $departmentIdsArray = array_values(array_unique(array_diff($departmentIdsArray, [''])));
            
            if (empty($departmentIdsArray)) {
                return $this->jsonReturn(992, '请选择部门');
            }
            $Department = new Department();
            $newList = [];
            foreach ($departmentIdsArray as $key => $value) {
                $departmentDetail = $Department->getItem($value);
                if ($departmentDetail) {
                    $newList[] = $departmentDetail;
                }
            }
            $CpaDepartmentModel = new CpaDepartmentModel();
            $res = $CpaDepartmentModel->addByDataList($newList);
            
            if ($res) {
                $this->log('NPD添加Carpool授权部门成功', 0);
                return $this->jsonReturn(0, '保存成功');
            } else {
                $this->log('NPD添加Carpool授权站门失败', -1);
                return $this->jsonReturn(-1, '保存失败');
            }
        } else {
            return $this->fetch('add');
        }
    }

    /**
     * 更改状态
     *
     * @param integer $id 主键
     * @param integer $status 状态
     */
    public function change_status($id, $status)
    {
        $status = $status ? 1 : 0;
        $CpaDepartmentModel = new CpaDepartmentModel();
        $res = $CpaDepartmentModel->changeStatus($id, $status);
        if ($res === false) {
            return $this->jsonReturn(-1, '改变状态失败');
        }
        return $this->jsonReturn(0, '改变状态成功');
    }

    /**
     * 删除用户
     * @param $id
     */
    public function delete($id)
    {
        if (CpaDepartmentModel::where('id', $id)->update(['is_delete' => 1])) {
            $this->log('删除NPD用户成功，id=' . $id, 0);
            return $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除NPD用户失败，id=' . $id, -1);
            return $this->jsonReturn(-1, '删除失败');
        }
    }

    /**
     * 明细
     */
    public function public_detail($id)
    {
        if (!$id) {
            return $this->error('Lost id');
        }
        $userInfo = CpaDepartmentModel::find($id);
        $returnData = [
            'data' => $userInfo,
        ];
        return $this->fetch('detail', $returnData);
    }
}
