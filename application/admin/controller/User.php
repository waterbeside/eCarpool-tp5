<?php
namespace app\admin\controller;

use app\carpool\model\User as UserModel;
use app\common\controller\AdminBase;
use think\Config;
use think\Db;

/**
 * 用户管理
 * Class AdminUser
 * @package app\admin\controller
 */
class User extends AdminBase
{
    protected $user_model;

    protected function _initialize()
    {
        parent::_initialize();
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
          $validate_result = $this->validate($data, 'app\admin\validate\User');

          if ($validate_result !== true) {
              $this->error($validate_result);
          } else {
            // $data['password'] = md5($data['password'] . Config::get('salt'));
              $data['password'] = md5($data['password']);
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
          $validate_result = $this->validate($data, 'app\admin\validate\User');

          if ($validate_result !== true) {
              $this->error($validate_result);
          } else {
              $user               = $this->user_model->find($id);
              $user->uid           = $uid;
              $user->loginname     = $data['loginname'];
              $user->phone        = $data['phone'];
              $user->is_active    = $data['is_active'];
              if (!empty($data['password']) && !empty($data['confirm_password'])) {
                  // $user->password = md5($data['password'] . Config::get('salt'));
                  $user->md5password = md5($data['password']);
              }
              if ($user->save() !== false) {
                  $this->success('更新成功');
              } else {
                  $this->error('更新失败');
              }
          }
      }else{
        $user = $this->user_model->find($id);
        $user->avatar = $user->imgpath ? config('carpool.avatarBasePath').$user->imgpath : config('carpool.avatarBasePath')."im/default.png";

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
