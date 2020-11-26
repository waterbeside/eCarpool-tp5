<?php

namespace app\common\model;

use think\Model;
use my\RedisData;

class AuthNpdsite extends Model
{

    protected $table = 't_auth_npdsite';


    public function getUserSiteCacheKey($uid)
    {
        return "carpool_admin:authSite:uid_$uid";
    }

    /**
     * 通过管理员ID取得其可管理NPD的站点ID列表
     *
     * @param integer  $uid 管理员用户id
     * @param boolean  $useCache 是否使用缓存
     * @return array
     */
    public function getUserSiteIds($uid, $useCache = false)
    {
        $cacheKey = $this->getUserSiteCacheKey($uid);
        $redis = RedisData::getInstance();
        $data = $redis->cache($cacheKey);
        if (empty($data) || !$useCache) {
            $data  = $this->where([['uid', '=', $uid]])->column('site_id');
            $redis->cache($cacheKey, $data, 60);
        }
        return $data;
    }
}
