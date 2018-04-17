<?php
namespace app\admin\controller;

use app\common\model\AuthRule as AuthRuleModel;
use app\common\controller\AdminBase;
use think\Db;

/**
 * 后台菜单
 * Class Menu
 * @package app\admin\controller
 */
class Menu extends AdminBase
{

    protected $auth_rule_model;

    protected function initialize()
    {
        parent::initialize();
        $this->auth_rule_model = new AuthRuleModel();
        $admin_menu_list       = $this->auth_rule_model->order(['sort' => 'DESC', 'id' => 'ASC'])->select();
        $admin_menu_level_list = array2level($admin_menu_list);

        $this->assign('admin_menu_level_list', $admin_menu_level_list);
    }

    /**
     * 后台菜单
     * @return mixed
     */
    public function index()
    {
        return $this->fetch();
    }

    /**
     * 添加菜单
     * @param string $pid
     * @return mixed
     */
    public function add($pid = '')
    {
      if ($this->request->isPost()) {
          $data            = $this->request->post();

          $validate_result = $this->validate($data, 'Menu');

          if ($validate_result !== true) {
            $this->jsonReturn(1,$validate_result);
          } else {
              if ($this->auth_rule_model->save($data)) {
                $pk = $this->auth_rule_model->id; //插入成功后取得id
                  $this->log('添加菜单成功，id='.$pk,0);
                  $this->jsonReturn(0,'保存成功');
              } else {
                  $this->log('添加菜单失败',1);
                  $this->jsonReturn(1,'保存失败');
              }
          }
      }else{
        return $this->fetch('add', ['pid' => $pid]);
      }
    }



    /**
     * 编辑菜单
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
      if ($this->request->isPost()) {
          $data            = $this->request->post();
          $validate_result = $this->validate($data, 'Menu');

          if ($validate_result !== true) {
              $this->jsonReturn(1,$validate_result);
          } else {

              if ($this->auth_rule_model->save($data, $id) !== false) {
                  $this->log('更新菜单成功，id='.$id,0);
                  $this->jsonReturn(0,'更新成功');
              } else {
                  $this->log('更新菜单失败，id='.$id,1);
                  $this->jsonReturn(1,'更新失败');
              }
          }
      }else{
        $admin_menu = $this->auth_rule_model->find($id);
        return $this->fetch('edit', ['admin_menu' => $admin_menu]);
      }

    }



    /**
     * 删除菜单
     * @param $id
     */
    public function delete($id)
    {
        $sub_menu = $this->auth_rule_model->where(['pid' => $id])->find();
        if (!empty($sub_menu)) {
            $this->jsonReturn(1,'此菜单下存在子菜单，不可删除');
        }
        if ($this->auth_rule_model->destroy($id)) {
            $this->log('删除菜单成功，id='.$id,0);
            $this->jsonReturn(0,'删除成功');
        } else {
            $this->log('删除菜单失败，id='.$id,1);
            $this->jsonReturn(1,'删除失败');
        }
    }
}
