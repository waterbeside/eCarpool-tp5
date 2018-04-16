<?php
namespace app\admin\controller;

use app\carpool\model\User as UserModel;
use app\carpool\model\CompanySub as CompanySubModel;
use app\carpool\model\Department as DepartmentModel;
use app\common\controller\AdminBase;
use think\facade\Config;
use think\facade\Validate;
use think\Db;

/**
 * 用户管理
 * Class AdminUser
 * @package app\admin\controller
 */
class User extends AdminBase
{
    protected $user_model;

    protected function initialize()
    {
        parent::initialize();
        $this->user_model = new UserModel();
    }

    /**
     * 用户管理
     * @param string $keyword
     * @param int    $page
     * @return mixed
     */
    public function index($keyword = '', $page = 1,$pagesize = 50)
    {
        $map = [];
        if ($keyword) {
            $map[] = ['loginname|phone|Department|name|companyname','like', "%{$keyword}%"];
        }
        $join = [
          ['company c','u.company_id = c.company_id'],
        ];
        $user_list = $this->user_model->alias('u')->join($join)->where($map)->order('uid DESC')->paginate($pagesize, false,  ['query'=>request()->param()]);
        return $this->fetch('index', ['user_list' => $user_list, 'keyword' => $keyword,'pagesize'=>$pagesize]);
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

          $sub_company_name = CompanySubModel::where(['sub_company_id'=>$data['sub_company_id']])->value('sub_company_name');
          $data['companyname'] = $sub_company_name ? $sub_company_name : '';
          $department_name = DepartmentModel::where(['departmentid'=>$data['departmentid']])->value('department_name');
          $data['Department'] = $department_name ? $department_name : '';

          $validate_result = $this->validate($data, 'app\carpool\validate\User');
          if ($validate_result !== true) {
              $this->error($validate_result);
          }
          $data['password'] = "";
          $data['md5password'] = md5($data['password']);
          if ($this->user_model->allowField(true)->save($data)) {
              $uid_n = $this->user_model->uid; //插入成功后取得id
              $this->log('新加用户成功，id='.$uid_n,0);
              $this->success('保存成功');
          } else {
              $this->log('新加用户失败',1);
              $this->error('保存失败');
          }

      }else{
        $avatar =  config('app.avatarBasePath')."im/default.png";
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
          $data['is_active']  = $this->request->post('is_active/d',0);
          unset($data['md5password']);

          $sub_company_name = CompanySubModel::where(['sub_company_id'=>$data['sub_company_id']])->value('sub_company_name');
          $data['companyname'] = $sub_company_name ? $sub_company_name : '';
          $department_name = DepartmentModel::where(['departmentid'=>$data['departmentid']])->value('department_name');
          $data['Department'] = $department_name ? $department_name : '';

          //开始验证字段
          $validate = new \app\carpool\validate\User;

          if (!empty($data['password'])) {
            if (!$validate->scene('edit_change_password')->check($data)) {
              $this->error($validate->getError());
            }
            $data['md5password'] = md5($data['password']);
          }else{
            if (!$validate->scene('edit')->check($data)) {

              $this->error($validate->getError());
            }
            unset($data['password']);
          }

          // 验证手机号和帐号名是否重复
          $rule = [
            'loginname'  => 'unique:carpool/User,loginname,'.$id,
            'phone'       => 'unique:carpool/User,phone,'.$id,
          ];
          $msg = [
            'loginname.unique' => '该用户名已被占用',
            'phone.unique' => '电话已被使用',
          ];
          $validate   = Validate::make($rule,$msg);
          $validate_result = $validate->check($data);
          if ($validate_result !== true) {
              $this->error($validate->getError());
          }
          if ($this->user_model->allowField(true)->save($data, ['uid'=>$id]) !== false) {
              $this->log('保存用户成功，id='.$id,0);
              $this->success('保存成功');
          } else {
              $this->log('保存用户失败，id='.$id,1);
              $this->error('保存失败');
          }

      }else{
        $user = $this->user_model->find($id);
        $user->avatar = $user->imgpath ? config('app.avatarBasePath').$user->imgpath : config('app.avatarBasePath')."im/default.png";

        return $this->fetch('edit', ['user' => $user]);
      }

    }



    /**
     * 删除用户
     * @param $id
     */
    public function delete($id)
    {
        if ($this->user_model->destroy($id)) {
            $this->log('删除用户成功，id='.$id,0);
            $this->success('删除成功');
        } else {
            $this->log('删除用户失败，id='.$id,1);
            $this->error('删除失败');
        }
    }
}
