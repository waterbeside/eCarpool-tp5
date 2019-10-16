<?php

namespace app\admin\controller;

use app\carpool\model\User as UserModel_o;
use app\user\model\Department;
use app\carpool\model\Company as CompanyModel;
use app\admin\controller\AdminBase;
use think\facade\Validate;
use think\Db;

/**
 * 用户管理
 * Class User
 * @package app\admin\controller
 */
class User extends AdminBase
{
    protected $user_model;
    public $un_check = ['admin/user/user_dialog'];
    public $check_dept_setting = [
        "action" => ['index', 'user_dialog']
    ];
    protected function initialize()
    {
        parent::initialize();
        $this->user_model = new UserModel_o();
    }

    /**
     * 用户管理
     * @param string $keyword
     * @param int    $page
     * @return mixed
     */
    public function index($filter = [], $page = 1, $pagesize = 50)
    {
        $fields = "u.*,c.*, d.fullname as full_department";
        $join = [
            ['company c', 'u.company_id = c.company_id', 'left'],
            ['t_department d', 'u.department_id = d.id', 'left'],
        ];
        $map = [];
        //地区排查 检查管理员管辖的地区部门
        $authDeptData = $this->authDeptData;
        if (isset($authDeptData['region_map'])) {
            $map[] = $authDeptData['region_map'];
        }
        //筛选用户信息
        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['u.uid|u.loginname|u.phone|u.name|u.nativename', 'like', "%{$filter['keyword']}%"];
        }
        //筛选部门
        if (isset($filter['keyword_dept']) && $filter['keyword_dept']) {
            $map[] = ['d.fullname|u.companyname|c.company_name', 'like', "%{$filter['keyword_dept']}%"];
            // $map[] = ['u.Department|u.companyname|c.company_name','like', "%{$filter['keyword_dept']}%"];
        }
        
        //筛选是否被删的用户
        if (isset($filter['is_delete']) && is_numeric($filter['is_delete'])) {
            $is_delete = $filter['is_delete'] ? Db::raw(1) : Db::raw(0);
            $map[] = ['u.is_delete', '=', $is_delete];
        }


        $user_list = $this->user_model->alias('u')->field($fields)->join($join)
            ->where($map)
            ->order('uid DESC')
            ->paginate($pagesize, false, ['query' => request()->param()]);
        $auth = [];
        $auth['admin/user/shift_delete'] = $this->checkActionAuth('admin/User/add');
        $auth['admin/pushmsg/add'] = $this->checkActionAuth('admin/Pushmsg/add');
        $auth['admin/User/shift_delete'] = $auth['admin/user/shift_delete'];
        $auth['admin/Pushmsg/add'] = $auth['admin/pushmsg/add'];
        $returnData = [
            'user_list' => $user_list,
            'filter' => $filter,
            'pagesize' => $pagesize,
            'auth' => $auth,
        ];
        return $this->fetch('index', $returnData);
    }


    /**
     * 用户列表对话框
     * @param string $keyword
     * @param int    $page
     * @return mixed
     */
    public function user_dialog($filter = [], $page = 1, $pagesize = 12, $fun = 'select_user')
    {
        $fields = "u.*,c.*, d.fullname as full_department";
        $join = [
            ['company c', 'u.company_id = c.company_id', 'left'],
            ['t_department d', 'u.department_id = d.id', 'left'],
        ];
        $map = [];
        //地区排查 检查管理员管辖的地区部门
        $authDeptData = $this->authDeptData;
        if (isset($authDeptData['region_map'])) {
            $map[] = $authDeptData['region_map'];
        }
        //筛选用户信息
        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['u.loginname|u.phone|u.name', 'like', "%{$filter['keyword']}%"];
        }
        //筛选部门
        if (isset($filter['keyword_dept']) && $filter['keyword_dept']) {
            $map[] = ['d.fullname|u.companyname|c.company_name', 'like', "%{$filter['keyword_dept']}%"];
            // $map[] = ['u.Department|u.companyname|c.company_name','like', "%{$filter['keyword_dept']}%"];
        }

        $lists = $this->user_model->alias('u')->field($fields)
            ->join($join)
            ->where($map)
            ->order('uid DESC')
            ->paginate($pagesize, false, ['query' => request()->param()]);


        $returnData = [
            'lists' => $lists,
            'filter' => $filter,
            'pagesize' => $pagesize,
            'fun' => $fun,
        ];
        return $this->fetch('', $returnData);
    }


    /**
     * 明细
     */
    public function public_detail($id)
    {
        if (!$id) {
            return $this->error('Lost id');
        }

        $userInfo = $this->user_model->find($id);
        if ($userInfo) {
            $avatarBasePath = config('secret.avatarBasePath');
            $userInfo->avatar = $userInfo->imgpath ? $avatarBasePath . $userInfo->imgpath : $avatarBasePath . "im/default.png";
        }
        $companyLists = (new CompanyModel())->getCompanys();
        $companys = [];
        foreach ($companyLists as $key => $value) {
            $companys[$value['company_id']] = $value['company_name'];
        }
        $DepartmentModel = new Department();
        $userInfo->full_department = $DepartmentModel->where('id', $userInfo->department_id)->value('fullname');
        $department_format = $DepartmentModel->formatFullName($userInfo->full_department);
        $userInfo->department_format = $department_format ? $department_format['branch'] . "/" . $department_format['short_name'] : '';
        $auth = [];
        $auth['admin/pushmsg/add'] = $this->checkActionAuth('admin/Pushmsg/add');
        $auth['admin/Pushmsg/add'] = $auth['admin/pushmsg/add'];
        $returnData = [
            'data' => $userInfo,
            'companys' => $companys,
            'auth' => $auth,
        ];
        return $this->fetch('detail', $returnData);
    }


    /**
     * 添加用户
     * @return mixed
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data            = $this->request->post();
            unset($data['md5password']);

            // $sub_company_name = CompanySubModel::where(['sub_company_id'=>$data['sub_company_id']])->value('sub_company_name');
            // $data['companyname'] = $sub_company_name ? $sub_company_name : '';
            // $department_name = DepartmentModel_old::where(['departmentid'=>$data['departmentid']])->value('department_name');
            // $data['Department'] = $department_name ? $department_name : '';

            $data['Department'] = $data['departmentid'];
            $validate_result = $this->validate($data, 'app\carpool\validate\User');
            if ($validate_result !== true) {
                return $this->jsonReturn(-1, $validate_result);
            }
            $data['password'] = trim($data['password']);
            $data['md5password'] = md5($data['password']);
            $data['indentifier'] = uuid_create();
            if ($this->user_model->allowField(true)->save($data)) {
                $uid_n = $this->user_model->uid; //插入成功后取得id
                $this->log('新加用户成功，id=' . $uid_n, 0);
                return $this->jsonReturn(0, '保存成功');
            } else {
                $this->log('新加用户失败', -1);
                return $this->jsonReturn(-1, '保存失败');
            }
        } else {
            $avatar =  config('secret.avatarBasePath') . "im/default.png";
            return $this->fetch('add', ['avatar' => $avatar]);
        }
    }


    /**
     * 编辑用户
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        if ($this->request->isPost()) {
            $data               = $this->request->post();
            $data['is_active']  = $this->request->post('is_active/d', 0);
            unset($data['md5password']);
            //
            // $sub_company_name = CompanySubModel::where(['sub_company_id'=>$data['sub_company_id']])->value('sub_company_name');
            // $data['companyname'] = $sub_company_name ? $sub_company_name : '';
            // $department_name = DepartmentModel_old::where(['departmentid'=>$data['departmentid']])->value('department_name');
            // $data['Department'] = $department_name ? $department_name : '';

            //开始验证字段
            $validate = new \app\carpool\validate\User;

            if (!empty($data['password'])) {
                if (!$validate->scene('edit_change_password')->check($data)) {
                    return $this->jsonReturn(-1, $validate->getError());
                }
                $data['md5password'] = md5($data['password']);
            } else {
                if (!$validate->scene('edit')->check($data)) {
                    return $this->jsonReturn(-1, $validate->getError());
                }
                unset($data['password']);
                if (isset($data['pw']) && !empty($data['pw'])) {
                    if (strlen($data['pw']) != 32) {
                        $this->jsonReturn(-1, "pw必为32位");
                    }
                    $data['md5password'] = strtolower($data['pw']);
                }
            }

            // 验证手机号和帐号名是否重复
            $rule = [
                'loginname'  => 'unique:carpool/User,loginname,' . $id,
                'phone'       => 'unique:carpool/User,phone,' . $id,
            ];
            $msg = [
                'loginname.unique' => '该用户名已被占用',
                'phone.unique' => '电话已被使用',
            ];
            $validate   = Validate::make($rule, $msg);
            $validate_result = $validate->check($data);
            if ($validate_result !== true) {
                return $this->jsonReturn(-1, $validate->getError());
            }

            $user = $this->user_model->find($id);
            // if (in_array($user['company_id'], [1, 11])) {
                $data['company_id'] = $user['company_id'];
            // }

            if ($this->user_model->allowField(true)->save($data, ['uid' => $id]) !== false) {
                $this->log('保存用户成功，id=' . $id, 0);
                $this->user_model->deleteDetailCache($user['loginname']);
                $this->user_model->deleteDetailCache($id, true);
                return $this->jsonReturn(0, '保存成功');
            } else {
                $this->log('保存用户失败，id=' . $id, -1);
                return $this->jsonReturn(-1, '保存失败');
            }
        } else {
            $user = $this->user_model->find($id);
            $user->full_department = Department::where('id', $user->department_id)->value('fullname');
            $this->checkDeptAuthByDid($user['department_id'], 1); //检查地区权限

            $user->avatar = $user->imgpath ? config('secret.avatarBasePath') . $user->imgpath : config('secret.avatarBasePath') . "im/default.png";

            return $this->fetch('edit', ['user' => $user]);
        }
    }



    /**
     * 删除用户
     * @param $id
     */
    public function delete($id)
    {
        $user = $this->user_model->find($id);
        $this->checkDeptAuthByDid($user['department_id'], 1); //检查地区权限
        if ($this->user_model->where('uid', $id)->update(['is_delete' => 1])) {
            $this->log('删除用户成功，id=' . $id, 0);
            return $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除用户失败，id=' . $id, -1);
            return $this->jsonReturn(-1, '删除失败');
        }
    }

    /**
     * 彻底删除用户
     * @param $id
     */
    public function shift_delete($id)
    {
        $user = $this->user_model->find($id);
        $this->checkDeptAuthByDid($user['department_id'], 1); //检查地区权限
        // if($this->user_model->where('uid', $id)->update(['is_delete' => 1])){
        if ($this->user_model->destroy($id)) {
            $this->log('删除用户成功，id=' . $id, 0);
            return $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除用户失败，id=' . $id, -1);
            return $this->jsonReturn(-1, '删除失败');
        }
    }
}
