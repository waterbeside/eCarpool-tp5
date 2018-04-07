<?php
namespace app\admin\controller;

use app\carpool\model\User as UserModel;
use app\common\controller\AdminBase;
use think\facade\Config;
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
            $map['loginname|phone|Department|name|companyname'] = ['like', "%{$keyword}%"];
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
          $validate_result = $this->validate($data, 'app\carpool\validate\User');

          if ($validate_result !== true) {
              $this->error($validate_result);
          } else {
            // $data['password'] = md5($data['password'] . Config::get('salt'));
              $data['password'] = "";
              $data['md5password'] = md5($data['password']);
              if ($this->user_model->allowField(true)->save($data)) {
                  $this->success('保存成功');
              } else {
                  $this->error('保存失败');
              }
          }
      }else{
        return $this->fetch();

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

          $data            = $this->request->post();
          unset($data['md5password']);
          $validate = new \app\carpool\validate\User;
          if (!empty($data['password']) && !empty($data['confirm_password'])) {
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
        /*
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
          }*/
          if ($this->user_model->allowField(true)->save($data, ['uid'=>$id]) !== false) {
              $this->success('保存成功');
          } else {
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
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }
}
