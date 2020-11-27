<?php

namespace app\admin\behavior;

// use app\common\model\AuthNpdsite;
use app\admin\service\AuthNpdsite;
use think\Request;
use think\Db;

class CheckNpdSiteAuth
{
    public function run(Request $request, $controller = null, $setting = [])
    {
        $userBaseData = $controller->userBaseInfo; //用户信息
        $adminId = $userBaseData['uid'];
        $isRootAdmin = $adminId == 1;
        $params = $request->param();
        $action = strtolower($request->action());

        // 查出请求过来的site_id
        $siteId = isset($params['site_id']) ? $params['site_id'] : 0;
        $siteIdArray = is_array($siteId) ? $siteId : explode(',', $siteId);
        

        // 取得用户权限所设的site_id
        $authNpdsite = new AuthNpdsite();
        $authSiteIds = [];
        $siteList = $authNpdsite->getSiteList(true);
        if ($isRootAdmin) {
            foreach ($siteList as $item) {
                $authSiteIds[] = $item['id'];
            }
        } else {
            $authSiteIds = $authNpdsite->getUserSiteIds($userBaseData['uid'], true) ?: [];
        }
        $filterSiteIds = [];  // 从$siteId里查出允许访问的id

        if (count($authSiteIds) == 1) { // 如果用户只有一个站点的权限
            $filterSiteIds = $authSiteIds[0];
        } elseif ($siteId) {
            foreach ($siteIdArray as $sid) {
                if (!is_numeric($sid) && !$sid) {
                    continue;
                }
                if (in_array($sid, $authSiteIds)) {
                    $filterSiteIds[] = $sid;
                }
            }
        } else { // 如果请求来的siteId为空，则筛选全部
            $filterSiteIds = $authSiteIds ?: [];
        }

        if ($isRootAdmin && empty($filterSiteIds)) {
            $sqlSiteMap = null;
        } elseif (empty($filterSiteIds) || count($filterSiteIds) > 1) {
            $sqlSiteMap = ['site_id', 'in', $filterSiteIds];
        } else {
            $sqlSiteMap = ['site_id', '=', $filterSiteIds[0]];
        }


        $controller->authNpdSite = [
            "filter_site_ids" => $filterSiteIds,
            "auth_site_ids" => $authSiteIds,
            "auth_site_list" => $authNpdsite->filterSiteListByIds($authSiteIds, $siteList),
            "param_site_ids" => $siteIdArray,
            "sql_site_map" =>  $sqlSiteMap,
            "site_list" =>  $siteList,
            "site_id" =>  $siteId,
        ];


        switch ($action) {
            case 'index':
                break;
            case 'add':
                if ($request->isPost()) {
                    // TODO::处理提交添加
                } else {
                    // TODO::处理添加
                }
                break;
            default:
                // code...
                break;
        }
    }
}
