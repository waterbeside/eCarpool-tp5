<?php

namespace app\npd\model;

use think\Db;
use think\Model;
use my\RedisData;

class Customer extends Model
{
    protected $connection = 'database_npd';
    protected $table = 't_customer';
    protected $pk = 'id';

    /**
     * 取得列表，如果redis有
     * @param  integer $site_id 站点id
     * @param  integer $recache 是否不使用缓存
     */
    public function getList($site_id, $recache = 0)
    {
        $cacheKey = "npd:customer:list:siteId_$site_id";

        $redis = RedisData::getInstance();
        $lists = json_decode($redis->get($cacheKey), true);
        $where = [
            ['is_delete', '=', Db::raw(0)]
        ];
        if ($site_id > 0) {
            $where[] = ['site_id', '=', $site_id];
        }

        if (!$lists || $recache) {
            $lists  = $this->where($where)->order(['sort' => 'DESC', 'id' => 'ASC'])->select()->toArray();
            $redis->setex($cacheKey, 3600 * 4, json_encode($lists));
        }
        return $lists;
    }


    /**
     * 清除列表缓存
     *
     * @param  integer $site_id 站点id
     * @return void
     */
    public function deleteListCache($site_id)
    {
        $redis = RedisData::getInstance();
        $redis->del("npd:customer:list");
    }
}
