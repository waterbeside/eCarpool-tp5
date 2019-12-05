<?php

namespace app\admin\controller\shuttle;

use app\carpool\model\User as UserModel;
use app\user\model\Department;
use app\carpool\model\ShuttleLine as ShuttleLineModel;
use app\carpool\model\ShuttleLineDepartment;
use app\admin\controller\AdminBase;
use think\facade\Validate;
use think\Db;

/**
 * 班车路线管理
 * Class line
 * @package app\admin\controller
 */
class Line extends AdminBase
{

    /**
     *
     * @param string $keyword
     * @param int    $page
     * @return mixed
     */
    public function index($filter = [], $page = 1, $pagesize = 50)
    {
        $fields = "t.*, d.fullname as admin_full_department";
        $map = [];
        $DepartmentModel = new Department();
        //地区排查 检查管理员管辖的地区部门
        $authDeptData = $this->authDeptData;
        $region_id = $authDeptData['region_id'];
        if ($region_id) {
            $department_children_ids = $DepartmentModel->getChildrenIds($region_id);
            $ids = ShuttleLineDepartment::distinct(true)->where([['department_id', 'in', $department_children_ids]])->column('line_id');
            if ($ids) {
                $map[] = ['t.id', 'in', $ids];
            }
            // $departmentSql = "FIND_IN_SET($region_id, department_ids)";
            // foreach ($department_children_ids as $key => $value) {
            //     $departmentSql .= "OR FIND_IN_SET($value, department_ids)";
            // }
            // $map[] = ['','EXP',Db::raw($departmentSql)];
        }

        //筛选用户信息
        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['t.start_name|t.end_name', 'like', "%{$filter['keyword']}%"];
        }

        //筛选是否被删的
        $is_delete = isset($filter['is_delete']) &&  $filter['is_delete'] ? Db::raw(1) : Db::raw(0);
            $map[] = ['t.is_delete', '=', $is_delete];

        $join = [
            ['t_department d', 't.admin_department_id = d.id', 'left']
        ];
        $list = ShuttleLineModel::alias('t')->field($fields)
            ->join($join)
            ->where($map)
            ->order('id DESC')
            ->paginate($pagesize, false, ['query' => request()->param()])->each(function ($item, $key) use ($DepartmentModel) {
                $item['deptData'] = [];
                $department_ids_res = ShuttleLineDepartment::distinct(true)->where([['line_id', '=', $item['id']]])->column('department_id');
                $item['deptData'] = $department_ids_res ? $DepartmentModel->getDeptDataList($department_ids_res) : [];
                return $item;
            });
            // dump($list);
        
        $returnData = [
            'list' => $list,
            'filter' => $filter,
            'pagesize' => $pagesize,
        ];
        return $this->fetch('index', $returnData);
    }


    /**
     * 添加路线
     * @return mixed
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->param();
            $validate_result = $this->validate($data, 'app\carpool\validate\ShuttleLine');
            if ($validate_result !== true) {
                return $this->jsonReturn(-1, $validate_result);
            }
            Db::connect('database_carpool')->startTrans();
            try {
                $ShuttleLine = new ShuttleLineModel();
                $ShuttleLineDepartment = new ShuttleLineDepartment();
                $ShuttleLine->allowField(true)->save($data);
                $id = $ShuttleLine->id;
                $ShuttleLineDepartment->where('line_id', $id)->delete();
                $upDeptList = [];
                $department_ids = $data['department_ids'];
                $department_ids_arr = explode(',', $department_ids);
                if (count($department_ids_arr) > 0) {
                    foreach ($department_ids_arr as $key => $value) {
                        $upDeptItem = [
                            'line_id' => $id,
                            'department_id' => $value,
                        ];
                        $upDeptList[] = $upDeptItem;
                    }
                    $ShuttleLineDepartment->saveAll($upDeptList);
                }
                // 提交事务
                Db::connect('database_carpool')->commit();
            } catch (\Exception $e) {
                Db::connect('database_carpool')->rollback();
                $errorMsg = $e->getMessage();
                return $this->jsonReturn(-1, null, '添加失败', ['errMsg'=>$errorMsg]);
            }
            return $this->jsonReturn(0, '保存成功');
        } else {
            $this->assign('shuttle_line_type', config('carpool.shuttle_line_type'));
            return $this->fetch('add');
        }
    }


    /**
     * 编辑路线
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        if ($this->request->isPost()) {
            $data = $this->request->param();
            $validate_result = $this->validate($data, 'app\carpool\validate\ShuttleLine');
            if ($validate_result !== true) {
                return $this->jsonReturn(-1, $validate_result);
            }
            Db::connect('database_carpool')->startTrans();
            try {
                $ShuttleLine = new ShuttleLineModel();
                $ShuttleLineDepartment = new ShuttleLineDepartment();
                $ShuttleLine->allowField(true)->save($data, ['id'=>$id]);
                $ShuttleLineDepartment->where('line_id', $id)->delete();
                $upDeptList = [];
                $department_ids = $data['department_ids'];
                $department_ids_arr = explode(',', $department_ids);
                if (count($department_ids_arr) > 0) {
                    foreach ($department_ids_arr as $key => $value) {
                        $upDeptItem = [
                            'line_id' => $id,
                            'department_id' => $value,
                        ];
                        $upDeptList[] = $upDeptItem;
                    }
                    $ShuttleLineDepartment->saveAll($upDeptList);
                }
                // 提交事务
                Db::connect('database_carpool')->commit();
            } catch (\Exception $e) {
                Db::connect('database_carpool')->rollback();
                $errorMsg = $e->getMessage();
                return $this->jsonReturn(-1, null, '保存失败', ['errMsg'=>$errorMsg]);
            }
            return $this->jsonReturn(0, '保存成功');
        } else {
            $fields = "t.*, d.fullname as admin_full_department";
            $join = [
                ['t_department d', 't.admin_department_id = d.id', 'left']
            ];
            $data = ShuttleLineModel::alias('t')->field($fields)->join($join)->find($id);
            $DepartmentModel = new Department();
            $department_ids_res = ShuttleLineDepartment::where([['line_id', '=', $id]])->column('department_id');
            $department_ids = $department_ids_res ? implode(',', $department_ids_res) : '';
            $deptsData = $department_ids_res ? $DepartmentModel->getDeptDataIdList($department_ids_res) : [];

            $this->assign('shuttle_line_type', config('carpool.shuttle_line_type'));
            $this->assign('department_ids', $department_ids);
            $this->assign('deptsData', $deptsData);
            $this->assign('data', $data);
            return $this->fetch('edit');
        }
    }



    /**
     * 删除路线
     * @param $id 数据id
     */
    public function delete($id)
    {
        $data = ShuttleLineModel::find($id);
        $this->checkDeptAuthByDid($data['admin_department_id'], 1); //检查地区权限
        if (ShuttleLineModel::where('id', $id)->update(['is_delete' => 1]) !== false) {
            $this->log('删除路线成功，id=' . $id, 0);
            return $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除路线失败，id=' . $id, -1);
            return $this->jsonReturn(-1, '删除失败');
        }
    }
}
