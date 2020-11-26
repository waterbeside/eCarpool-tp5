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
        $params = $request->param();
        $action = strtolower($request->action());

        // 查出请求过来的site_id
        $siteId = isset($params['npd_site_id']) ? $params['npd_site_id'] : 0;
        $siteIdArray = is_array($siteId) ? $siteId : explode(',', $siteId);
        

        // 取得用户权限所设的site_id
        $authNpdsite = new AuthNpdsite();
        $authSiteIds = [];
        if ($adminId == 1) {
            $siteList = $authNpdsite->getSiteList(true);
            foreach ($siteList as $item) {
                $siteList[] = $item['id'];
            }
        } else {
            $authSiteIds = $authNpdsite->getUserSiteIds($userBaseData['uid'], true);
        }
        $filterSiteIds = [];  // 从$siteId里查出允许访问的id
    
        //查找选的地区id，自己是否有权
        if ($siteId) {
            foreach ($siteIdArray as $sid) {
                if (!is_numeric($sid) && !$sid) {
                    continue;
                }
                if (in_array($sid, $authSiteIds)) {
                    $filterSiteIds[] = $sid;
                }
            }
        } else {
            $filterSiteIds = $authSiteIds;
        }
        


        $controller->authNpdSite = [
            "filter_site_ids" => $filterSiteIds,
            "auth_site_ids" => $authSiteIds,
            "param_site_ids" => $siteIdArray,
            "sql_site_map" => count($filterSiteIds) > 1 ?  ['site_id', 'in', $filterSiteIds] : ['site_id', '=', $filterSiteIds[0]]
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
