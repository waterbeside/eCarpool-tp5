<?php
namespace app\admin\controller;

use app\common\model\AdminUser as AdminUserModel;
use app\common\model\AuthGroup as AuthGroupModel;
use app\common\model\AuthGroupAccess as AuthGroupAccessModel;
use app\common\model\DeptGroup as DeptGroupModel;
use app\admin\controller\AdminBase;
use think\facade\Config;
use think\Db;

/**
 * 管理员管理
 * Class AdminUser
 * @package app\admin\controller
 */
class AdminUser extends AdminBase
{
    protected $admin_user_model;
    protected $auth_group_model;
    protected $auth_group_access_model;

    protected function initialize()
    {
        parent::initialize();
        $this->admin_user_model        = new AdminUserModel();
        $this->auth_group_model        = new AuthGroupModel();
        $this->auth_group_access_model = new AuthGroupAccessModel();
    }

    /**
     * 管理员管理
     * @return mixed
     */
    public function index($filter = [],$pagesize=50)
    {
        $auth_group_list = $this->auth_group_model->select();
        $dept_group_list = DeptGroupModel::select();
        $groups = [];
        foreach ($auth_group_list as $key => $value) {
          $groups[$value['id']] = $value;
        }
        $dept_groups = [];
        foreach ($dept_group_list as $key => $value) {
          $dept_groups[$value['id']] = $value;
        }

        $map = [];
        //筛选用户信息
        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['u.username|real_name|nickname','like', "%{$filter['keyword']}%"];
        }
        $lists = $this->admin_user_model->alias('u')->where($map)->order('id ASC')->paginate($pagesize, false, ['query'=>request()->param()])
        ->each(function($item, $key){
            $groupData = $this->auth_group_access_model->where('uid',$item->id)->find();
            $item->group_id = $groupData['group_id'] ;
            $item->dept_group_id =  $groupData['dept_group_id'];
        });

        return $this->fetch('index', ['lists' => $lists,'groups'=>$groups,'dept_groups'=>$dept_groups]);
    }

    /**
     * 添加管理员
     * @return mixed
     */
    public function add()
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          $validate_result = $this->validate($data, 'AdminUser');

          if ($validate_result !== true) {
              $this->jsonReturn(1,$validate_result);
          } else {
              // $data['password'] = md5($data['password'] . Config::get('salt'));
              $data['password']  = password_hash($data['password'], PASSWORD_BCRYPT);
              if ($this->admin_user_model->allowField(true)->save($data)) {
                  $auth_group_access['uid']      = $this->admin_user_model->id;
                  $auth_group_access['group_id'] = $data['group_id'];
                  $auth_group_access['dept_group_id'] = $data['dept_group_id'];
                  $this->auth_group_access_model->save($auth_group_access);
                  $pk = $this->admin_user_model->id; //插入成功后取得id
                  $this->log('添加后台用户成功，id='.$pk,0);
                  $this->jsonReturn(0,'保存成功');
              } else {
                  $this->log('添加后台用户失败',1);
                  $this->jsonReturn(1,'保存失败');
              }
          }
      }else{
        $auth_group_list = $this->auth_group_model->select();
        $dept_group_list = DeptGroupModel::select();
        return $this->fetch('add', ['auth_group_list' => $auth_group_list,'dept_group_list'=>$dept_group_list]);
      }

    }



    /**
     * 编辑管理员
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          $validate_result = $this->validate($data, 'AdminUser');
          if($id==1){
            $this->jsonReturn(-1,"创始人账号无法编辑");
          }
          if ($validate_result !== true) {
              $this->jsonReturn(-1,$validate_result);
          } else {
              // $admin_user = $this->admin_user_model->find($id);
              $updateData = [
                'username' => $data['username'],
                'status' => $data['status'],
                'nickname' => $data['nickname'],
                'real_name' => $data['real_name'],
                'carpool_uid' => $data['carpool_uid'],
                'carpool_account' => $data['carpool_account'],
              ];


              if (!empty($data['password']) && !empty($data['confirm_password'])) {
                  // $admin_user->password = md5($data['password'] . Config::get('salt'));
                  $updateData['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
              }
              $res = $this->admin_user_model->where('id',$id)->update($updateData);
              if ($res !== false) {
                  $auth_group_access['uid']      = $id;
                  $auth_group_access['group_id'] = $data['group_id'];;
                  $auth_group_access['dept_group_id'] = $data['dept_group_id'];
                  $this->auth_group_access_model->where('uid', $id)->update($auth_group_access);
                  $this->log('更新后台用户成功，id='.$id,0);
                  $this->jsonReturn(0,'更新成功');
              } else {
                  $this->log('更新后台用户失败，id='.$id,-1);
                  $this->jsonReturn(-1,'更新失败');
              }
          }
      }else{
        $data                   = $this->admin_user_model->find($id);
        $auth_group_list        = $this->auth_group_model->select();
        $auth_group_access      = $this->auth_group_access_model->where('uid', $id)->find();
        $data['group_id'] = $auth_group_access['group_id'];
        $data['dept_group_id'] = $auth_group_access['dept_group_id'];
        $dept_group_list = DeptGroupModel::select();
        return $this->fetch('edit', ['data' => $data, 'auth_group_list' => $auth_group_list,'dept_group_list'=>$dept_group_list]);
      }

    }


    /**
     * 删除管理员
     * @param $id
     */
    public function delete($id)
    {
        if ($id == 1) {
            $this->error('默认管理员不可删除');
        }
        if ($this->admin_user_model->destroy($id)) {
            $this->auth_group_access_model->where('uid', $id)->delete();
            $this->log('删除后台用户成功，id='.$id,0);
            $this->jsonReturn(0,'删除成功');
        } else {
          $this->log('删除后台用户失败，id='.$id,1);
            $this->jsonReturn(1,'删除失败');
        }
    }
}
