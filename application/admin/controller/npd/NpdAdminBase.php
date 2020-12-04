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
     * 验证数据有没有权限
     *
     * @param array $data 数据
     * @param integer $resFlag 当为1时，如果没有权限，则直接抛出json；
     * @return boolean
     */
    public function checkItemSiteAuth($data, $resFlag = 0)
    {
        $adminId = $this->userBaseInfo['uid'];
        if ($adminId == 1) {
            return true;
        }
        $siteId = $data['site_id'];
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
            $list[$key]['site_data'] = $siteListIdMap[$siteId] ?? null;
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
}
