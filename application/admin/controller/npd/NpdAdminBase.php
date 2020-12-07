<?php

namespace app\admin\controller\npd;

use app\admin\controller\AdminBase;
// use app\admin\behavior\CheckNpdSiteAuth;
use app\npd\model\Category;
use think\facade\Hook;
use think\Db;
use my\Utils;

/**
 * NPD后台管理基础类
 * Class NpdAdminBase
 * @package app\admin\controller\npd
 */
class NpdAdminBase extends AdminBase
{

    public $authNpdSite = [];
    public $siteListIdMap = null;

    protected function initialize()
    {
        parent::initialize();
        $res = Hook::listen("check_npd_site_auth", $this, [], true);
        $this->assign('authNpdSite', $this->authNpdSite);
    }

    /**
     * 取得单条数据并返回检查是否有权限
     *
     * @param object $modelInstance 模型实例
     * @param array,integer $whereOrId 当为数字时，为该数据id, 数组时，为查询where
     * @param boolean $unGetDeleted 是否不取delete的。
     * @param integer $resFlag 当为1时，如果没有权限，则直接抛出json；
     * @return array [data,auth]
     */
    public function getItemAndCheckAuthSite($modelInstance, $whereOrId, $unGetDeleted = false, $resFlag = 0)
    {
        if (is_numeric($whereOrId)) {
            $data = $modelInstance->find($whereOrId);
        } else {
            $data = $modelInstance->where($whereOrId)->find();
        }
        if ($unGetDeleted && !empty($data) && $data['is_delete'] == 1) {
            $data = null;
        }
        $checkRes = $this->checkItemSiteAuth($data, $resFlag);
        return [
            'data' => $data,
            'auth' => $checkRes,
        ];
    }

    /**
     * 验证所有数据id都是否权限，其中一个没有则返回false
     *
     * @param object $modelInstance 模型实例
     * @param array $ids 数据id列表
     * @param boolean $unGetDeleted 是否排除已删的数据
     * @param integer $resFlag 当为1时，如果没有权限，则直接抛出json；
     * @return boolean
     */
    public function checkIdsAuth($modelInstance, $ids, $unGetDeleted = false, $resFlag = 0)
    {
        $adminId = $this->userBaseInfo['uid'];
        if ($adminId == 1) {
            return true;
        }
        // 先查出所有数据的site_id
        $where = [
            ['id', 'in', $ids]
        ];
        if ($unGetDeleted) {
            $where[] = ['is_delete', '=', Db::raw(0)];
        }
        $res = $modelInstance->where($where)->column('site_id');
        if (empty($res)) {
            return null;
        }
        $hasAuth = true;
        foreach ($res as $item) {
            if (!$this->checkSiteIdAuth($item, 0)) {
                $hasAuth = false;
                break;
            }
        }
        if ($resFlag == 1 && !$hasAuth) {
            return $this->jsonReturn(-1, '包含没有权限的数据，操作失败');
        }
        return $hasAuth;
    }
    
    /**
     * 验证某个Site_id是否有权限.
     *
     * @param integer $siteId 站点id
     * @param integer $resFlag 当为1时，如果没有权限，则直接抛出json；
     * @return void
     */
    public function checkSiteIdAuth($siteId, $resFlag = 0)
    {
        $adminId = $this->userBaseInfo['uid'];
        if ($adminId == 1) {
            return true;
        }
        if (empty($siteId)) {
            return $resFlag == 1 ? $this->jsonReturn(-1, '你没有权限') : false;
        }
        $authSiteIds = $this->authNpdSite['auth_site_ids'];
        if (in_array($siteId, $authSiteIds)) {
            return true;
        }
        return $resFlag == 1 ? $this->jsonReturn(-1, '你没有权限') : false;
    }

    /**
     * 验证数据有没有权限
     *
     * @param array $data 数据
     * @param integer $resFlag 当为1时，如果没有权限，则直接抛出json；
     * @return boolean
     */
    public function checkItemSiteAuth($data, $resFlag = 0)
    {
        return $this->checkSiteIdAuth($data['site_id'], $resFlag);
    }

    /**
     * 把站点数据添加到列表上
     *
     * @param array $list 目标列表
     * @return array
     */
    public function listDataAddSiteData($list)
    {
        $siteListIdMap = Utils::getInstance()->list2Map($this->authNpdSite['site_list'], 'id');
        foreach ($list as $key => $value) {
            $siteId = $value['site_id'];
            $siteData = $siteListIdMap[$siteId] ?? null;
            $list[$key]['site_data'] = $siteData;
            $list[$key]['site_name'] = $siteData['name'] ?? '';
        }
        return $list;
    }

    /**
     * 取得NPD栏目分类(包含siteData)
     *
     * @param string $model model
     * @param boolean,integer,array $siteIds 站点id, 为false时显示所有站点, 当为array时为站点id列表
     * @param boolean,integer $status 状态, 为false时显示所有状态
     * @param  boolean $unGetDeleted 是否不取已删
     * @param boolean $useCache 是否使用缓存
     * @return array
     */
    public function getNpdCategoryList($model = null, $siteIds = false, $status = false, $unGetDeleted = false, $useCache = true)
    {
        $categoryInst = new Category();
        $exp = $useCache ? 3600 * 2 : false;
        $list = $categoryInst->getListByModel($model, $siteIds, $status, $unGetDeleted, $exp);
        $list = $this->listDataAddSiteData($list);
        return $list;
    }

    /**
     * 取得siteListIdMap;
     *
     * @return array 站点以id为key的字典
     */
    public function getSiteListIdMap()
    {
        if ($this->siteListIdMap != null) {
            return $this->siteListIdMap;
        }
        $siteList = $this->authNpdSite['site_list'];
        $this->siteListIdMap = Utils::getInstance()->list2Map($siteList, 'id');
        return $this->siteListIdMap;
    }

    /**
     * 显示编辑页
     *
     * @param object $modelInstance 数据模型实例
     * @param integer $id 数据id
     * @param string $cateModelName 分类的所属模型名
     * @param boolean $isFetch 是否直接渲染模板
     * @return void
     */
    public function editPage($modelInstance, $id, $cateModelName, $isFetch = false)
    {
        $itemRes = $this->getItemAndCheckAuthSite($modelInstance, $id);
        if (!$itemRes['auth']) {
            return '你没有权限';
        }
        $data = $itemRes['data'] ?? [];
        $this->assign('data', $data);

        $this->getCateLevelList($cateModelName, $data['site_id']);
        if ($isFetch) {
            return $this->fetch();
        }
    }

    /**
     * 显示添加页
     *
     * @param string $cateModelName 分类的所属模型名
     * @param boolean $isFetch 是否直接渲染模板
     * @return void
     */
    public function addPage($cateModelName, $isFetch = false)
    {

        $siteId = $this->authNpdSite['site_id'];
        if (empty($siteId)) {
            return view('npd/common/select_site');
        }

        $this->getCateLevelList($cateModelName, $siteId);
        if ($isFetch) {
            return $this->fetch();
        }
        return null;
    }

    /**
     * 取得 category_level_list
     *
     * @param string $cateModelName 分类的所属模型名
     * @param integer $siteId 站点id
     * @return array
     */
    public function getCateLevelList($cateModelName, $siteId)
    {
        $category_level_list = $this->getNpdCategoryList($cateModelName, $siteId, false, true, true);
        $category_level_list = array2level($category_level_list, 0, 1, 'parent_id');
        $this->assign('category_level_list', $category_level_list);
        return $category_level_list;
    }

    /**
     * 检查权限并册除
     *
     * @param object $modelInstance 数据模型实例
     * @param integer|array $id 数据id
     * @param boolean $isFetch 是否直接渲染模板
     * @param string $logFlag 记录日志时语句，为null时不记录
     * @return void
     */
    public function checkAuthAndDelete($modelInstance, $id, $isFetch = false, $logFlag = null)
    {
        
        if (empty($id)) {
            return $this->jsonReturn(-1, '请选择需要删除的内容');
        }
        if (is_array($id)) {
            $this->checkIdsAuth($modelInstance, $id, true, 1);
            $where = [['id', 'in', $id]];
        } else {
            $this->getItemAndCheckAuthSite($modelInstance, $id, false, 1);
            $where = [['id', '=', $id]];
        }
        $res = $modelInstance->where($where)->update(['is_delete' => 1]);
        if (!$isFetch) {
            return $res;
        }
        if ($res) {
            if ($logFlag) {
                $this->log("{$logFlag}成功", 0);
            }
            return $this->jsonReturn(0, '删除成功');
        } else {
            if ($logFlag) {
                $this->log("{$logFlag}失败", -1);
            }
            return $this->jsonReturn(-1, '删除失败');
        }
    }
}
