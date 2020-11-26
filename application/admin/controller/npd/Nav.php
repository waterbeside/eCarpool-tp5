<?php

namespace app\admin\controller\npd;

use app\npd\model\Nav as NavModel;
use app\admin\controller\npd\NpdAdminBase;
use think\Db;
use my\Tree;

/**
 * 导航栏管理
 * Class Nav
 * @package app\admin\controller\npd
 */

class Nav extends NpdAdminBase
{

    protected $nav_model;
    protected $cacheVersionKey = "NPD:nav:version";

    protected function initialize()
    {
        parent::initialize();
        $this->nav_model = new NavModel();
    }

    /**
     * 导航菜单管理
     * @return mixed
     */
    public function index($json = 0, $recycled = 0)
    {
        if ($json) {
            if (!$recycled) {
                $data = $this->nav_model->getList(1);
            } else {
                $data  = $this->nav_model->order(['sort' => 'DESC', 'id' => 'ASC'])->select()->toArray();
            }
            $tree = new Tree();
            $tree->init($data);
            $tree->parentid_name = 'pid';
            $treeData = $tree->get_tree_array(0, 'id');
            $this->jsonReturn(0, $treeData);
        } else {
            $this->assign('recycled', $recycled);
            return $this->fetch();
        }
    }

    /**
     * 添加栏目
     * @param string $pid
     * @return mixed
     */
    public function add($pid = '')
    {
        if ($this->request->isPost()) {
            $data            = $this->request->param();
            $validate_result = $this->validate($data, 'app\npd\validate\Nav');
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            }
            if (!isset($data['pid']) || !is_numeric($data['pid'])) {
                $data['pid'] = 0;
            }
            if ($this->nav_model->allowField(true)->save($data)) {
                $this->nav_model->deleteListCache();
                $this->updateDataVersion($this->cacheVersionKey);
                $this->log('添加导航菜单成功', 0);
                $this->jsonReturn(0, '保存成功');
            } else {
                $this->log('添加导航菜单失败', -1);
                $this->jsonReturn(-1, '保存失败');
            }
        } else {
            $nav_level_list       = $this->nav_model->where('is_delete', Db::raw(0))->order(['sort' => 'DESC', 'id' => 'ASC'])->select();
            foreach ($nav_level_list as $key => $value) {
                $nav_level_list[$key]['pid'] = $value['pid'];
            }
            $nav_level_list = array2level($nav_level_list);
            $this->assign('nav_level_list', $nav_level_list);
            return $this->fetch('add', ['pid' => $pid]);
        }
    }



    /**
     * 编辑栏目
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        if ($this->request->isPost()) {
            $data            = $this->request->param();
            $validate_result = $this->validate($data, 'app\npd\validate\Nav');

            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            }

            $children = $this->nav_model->getChildrensId($id);
            if (in_array($data['pid'], $children)) {
                $this->jsonReturn(-1, '不能移动到自己的子菜单');
            } else {
                if ($this->nav_model->allowField(true)->save($data, $id) !== false) {
                    $this->nav_model->deleteListCache();
                    $this->updateDataVersion($this->cacheVersionKey);
                    $this->log('更新导航菜单成功', 0);
                    $this->jsonReturn(0, '更新成功');
                } else {
                    $this->log('更新导航菜单失败', -1);
                    $this->jsonReturn(-1, '更新失败');
                }
            }
        } else {
            $nav_level_list       = $this->nav_model->where('is_delete', Db::raw(0))->order(['sort' => 'DESC', 'id' => 'ASC'])->select();
            foreach ($nav_level_list as $key => $value) {
                $nav_level_list[$key]['pid'] = $value['pid'];
            }
            $nav_level_list = array2level($nav_level_list);
            $this->assign('nav_level_list', $nav_level_list);

            $data = $this->nav_model->find($id);
            return $this->fetch('edit', ['data' => $data]);
        }
    }



    /**
     * 删除栏目
     * @param $id
     */
    public function delete($id)
    {
        if ($this->nav_model->where('id', $id)->update(['is_delete' => 1])) {
            $this->nav_model->deleteListCache();
            $this->updateDataVersion($this->cacheVersionKey);
            $this->log('删除导航菜单成功', 0);
            $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除导航菜单失败', -1);
            $this->jsonReturn(-1, '删除失败');
        }
    }


    /**
     * 还原栏目
     * @param $id
     */
    public function recycle($id)
    {
        if ($this->nav_model->where('id', $id)->update(['is_delete' => 0])) {
            $this->nav_model->deleteListCache();
            $this->updateDataVersion($this->cacheVersionKey);
            $this->log('还原导航菜单成功', 0);
            $this->jsonReturn(0, '还原成功');
        } else {
            $this->log('还原导航菜单失败', -1);
            $this->jsonReturn(-1, '还原失败');
        }
    }
}
