<?php

namespace app\admin\service;

use think\Model;
use think\Db;
use my\RedisData;
use app\common\service\Service;
use app\common\model\AuthNpdsite as AuthNpdsiteModel;
use app\npd\model\Site as NpdSite;

class AuthNpdsite extends Service
{

    /**
     * 取得用户允许权限的NPD站点id列表
     *
     * @param integer $uid 后台用户id
     * @param boolean $useCache 是否使用缓存
     * @return array
     */
    public function getUserSiteIds($uid, $useCache)
    {
        $authNpdsiteModel = new AuthNpdsiteModel();
        return $authNpdsiteModel->getUserSiteIds($uid, $useCache);
    }

    /**
     * 取得用户允许权限的NPD站点列表
     *
     * @param integer $uid 后台用户id
     * @param boolean $useCache 是否使用缓存
     * @return array
     */
    public function getUserNpdSite($uid, $useCache = false)
    {
        $userSiteIdList = $this->getUserSiteIds($uid, $useCache);
        if (empty($userSiteIdList)) {
            return null;
        }
        return $this->getSiteListByIds($userSiteIdList, $useCache);
    }

    /**
     * 取得site列表
     *
     * @param boolean $useCache 是否使用缓存
     * @return array
     */
    public function getSiteList($useCache = false)
    {
        $npdSite = new NpdSite();
        return $npdSite->getList($useCache ? 60 * 60 : 0);
    }


    /**
     * 通过id list查出site data list
     *
     * @param array $siteIds 站点列表
     * @param boolean $useCache 是否使用缓存
     * @return array
     */
    public function getSiteListByIds($siteIds, $useCache = false)
    {
        $siteList = $this->getSiteList($useCache);
        $returnList = [];
        foreach ($siteList as $key => $value) {
            if (in_array($value['id'], $siteIds)) {
                $returnList[] = $value;
            }
        }
        return $returnList;
    }

    /**
     * 更瓣npd站点权限
     *
     * @param integer $uid 后台用户id
     * @param array<integer> $siteIds 站点id列表
     * @return void
     */
    public function updataAuth($uid, $siteIds)
    {
        $authNpdsiteModel = new AuthNpdsiteModel();
        // $db = new Db();
        $res = false;
        $authNpdsiteModel->startTrans();
        try {
            $authNpdsiteModel->where([['uid','=',$uid]])->delete();
            $data = [];
            foreach ($siteIds as $siteId) {
                if (!$siteId) {
                    continue;
                }
                $data[] = [
                    'uid' => $uid,
                    'site_id' => $siteId
                ];
            }
            if (!empty($data)) {
                $res = $authNpdsiteModel->insertAll($data);
            }
            // 提交事务
            $authNpdsiteModel->commit();
        } catch (\Exception $e) {
            // 回滚事务
            $authNpdsiteModel->rollback();
            $logMsg = '失败' . implode(',', $siteIds);
            // $this->log($logMsg, -1);
            return false;
        }
        return $res;
    }
}
