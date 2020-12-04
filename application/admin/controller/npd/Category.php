<?php

namespace app\admin\controller\npd;

use app\npd\model\Category as CategoryModel;
use app\admin\controller\npd\NpdAdminBase;
use think\Db;
use my\Tree;
use my\Utils;

/**
 * 栏目管理
 * Class Category
 * @package app\admin\controller\npd
 */
class Category extends NpdAdminBase
{

    protected $category_model;
    protected $cacheVersionKey = "NPD:category:version";
    protected $siteList;

    protected function initialize()
    {
        parent::initialize();
        $this->category_model = new CategoryModel();
    }

    /**
     * 栏目管理
     * @return mixed
     */
    public function index($json = 0, $recycled = 0)
    {
        if ($json) {
            $where = [];
            $siteIdwhere = $this->authNpdSite['sql_site_map'];
            $siteListIdMap = $this->getSiteListIdMap();
            if (!empty($siteIdwhere)) {
                $where[] = $siteIdwhere;
            }
            if (!$recycled) {
                $data = $this->category_model->getList($this->authNpdSite['filter_site_ids'], 1);
            } else {
                $data  = $this->category_model->where($where)->order(['sort' => 'DESC', 'id' => 'ASC'])->select()->toArray();
            }
            if (!empty($data)) {
                foreach ($data as $key => $item) {
                    $siteData = $siteListIdMap[$item['site_id']] ?? [];
                    $data[$key]['site_name'] = $siteData['title'] ?? '';
                }
            }
            $tree = new Tree();
            $tree->init($data);
            $tree->parentid_name = 'parent_id';
            $treeData = $tree->get_tree_array(0, 'id');
            $this->jsonReturn(0, $treeData);
        } else {
            $category_model_list = config('npd.category_model_list');
            $this->assign('recycled', $recycled);
            $this->assign('category_model_list', $category_model_list);
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
            $validate_result = $this->validate($data, 'app\npd\validate\Category');
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            }
            if (!isset($data['parent_id']) || !is_numeric($data['parent_id'])) {
                $data['parent_id'] = 0;
            }
            if (empty($data['content'])) {
                unset($data['content']);
            }
            if (empty($data['content_en'])) {
                unset($data['content_en']);
            }
            if ($this->category_model->allowField(true)->save($data)) {
                $this->category_model->deleteListCache();
                $this->updateDataVersion($this->cacheVersionKey);
                $this->log('添加分类成功', 0);
                $this->jsonReturn(0, '保存成功');
            } else {
                $this->log('添加分类失败', -1);
                $this->jsonReturn(-1, '保存失败');
            }
        } else {
            $category_level_list = $this->getNpdCategoryList(null, $this->authNpdSite['site_id'], false, true, true);
            foreach ($category_level_list as $key => $value) {
                $category_level_list[$key]['pid'] = $value['parent_id'];
                $category_level_list[$key]['site_name'] = $value['site_data']['title'] ?? '';
            }
            $category_level_list = array2level($category_level_list);
            $this->assign('category_level_list', $category_level_list);

            $category_model_list = config('npd.category_model_list');
            $this->assign('category_model_list', $category_model_list);

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
        $itemRes = $this->getItemAndCheckAuthSite($this->category_model, $id);

        if ($this->request->isPost()) {
            if (!$itemRes['auth']) {
                $this->jsonReturn(-1, '没有权限');
            }
            $data            = $this->request->param();
            $validate_result = $this->validate($data, 'app\npd\validate\Category');

            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            }

            $children = $this->category_model->getChildrensId($id);
            if (in_array($data['parent_id'], $children)) {
                $this->jsonReturn(-1, '不能移动到自己的子分类');
            } else {
                if (empty($data['content'])) {
                    $data['content'] = null;
                }
                if (empty($data['content_en'])) {
                    $data['content_en'] = null;
                }
                if ($this->category_model->allowField(true)->save($data, $id) !== false) {
                    $this->category_model->deleteListCache();
                    $this->updateDataVersion($this->cacheVersionKey);
                    $this->log('更新分类成功', 0);
                    $this->jsonReturn(0, '更新成功');
                } else {
                    $this->log('更新分类失败', -1);
                    $this->jsonReturn(-1, '更新失败');
                }
            }
        } else {
            if (!$itemRes['auth']) {
                return '你没有权限';
            }
            $data = $itemRes['data'] ?? [];

            $categoryListwhere = [['is_delete', '=', Db::raw(0)]];
            // $authSiteWhere = $this->authNpdSite['sql_site_map'];
            // if (!empty($authSiteWhere)) {
            //     $categoryListwhere[] = $authSiteWhere;
            // }
            if ($data['site_id']) {
                $categoryListwhere[] = ['site_id', '=', $data['site_id']];
            }
            $category_level_list = $this->getNpdCategoryList(null, $data['site_id'], false, true, true);
            foreach ($category_level_list as $key => $value) {
                $category_level_list[$key]['pid'] = $value['parent_id'];
                $category_level_list[$key]['site_name'] = $value['site_data']['title'] ?? '';
            }
            $category_level_list = array2level($category_level_list);
            $this->assign('category_level_list', $category_level_list);

            $category_model_list = config('npd.category_model_list');
            $this->assign('category_model_list', $category_model_list);

            return $this->fetch('edit', ['data' => $data]);
        }
    }



    /**
     * 删除栏目
     * @param $id
     */
    public function delete($id)
    {
        $this->getItemAndCheckAuthSite($this->category_model, $id, false, 1);
        if ($this->category_model->where('id', $id)->update(['is_delete' => 1])) {
            $this->category_model->deleteListCache();
            $this->updateDataVersion($this->cacheVersionKey);
            $this->log('删除分类成功', 0);
            $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除分类失败', -1);
            $this->jsonReturn(-1, '删除失败');
        }
        /*  $category = $this->category_model->where(['parent_id' => $id])->find();
        if (!empty($category)) {
            $this->jsonReturn(-1,'此分类下存在子分类，不可删除');
        }*/
        /*if ($this->category_model->destroy($id)) {
          $this->jsonReturn(0,'删除成功');
      } else {
          $this->jsonReturn(-1,'删除失败');
      }*/
    }


    /**
     * 还原栏目
     * @param $id
     */
    public function recycle($id)
    {
        $this->getItemAndCheckAuthSite($this->category_model, $id, false, 1);
        if ($this->category_model->where('id', $id)->update(['is_delete' => 0])) {
            $this->category_model->deleteListCache();
            $this->updateDataVersion($this->cacheVersionKey);
            $this->log('还原分类成功', 0);
            $this->jsonReturn(0, '还原成功');
        } else {
            $this->log('还原分类失败', -1);
            $this->jsonReturn(-1, '还原失败');
        }
    }
}
