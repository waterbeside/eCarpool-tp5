<?php

namespace app\npd\model;

use think\Db;
use my\RedisData;
use think\Model;

class Site extends Model
{
    protected $connection = 'database_npd';
    protected $table = 't_site';
    protected $pk = 'id';

    public function getListCacheKey()
    {
        return 'npd:sites:list';
    }


    /**
     * 取得列表
     *
     * @param integer $exp 缓存时间
     * @return array
     */
    public function getList($exp = 60 * 60)
    {
        $rKey = $this->getListCacheKey();
        $redis = RedisData::getInstance();
        $data = $redis->cache($rKey);
        if (!$data || $exp < 1) {
            $data  = $this->where([['is_delete', '=', Db::raw(0)]])->order(['id' => 'ASC'])->select()->toArray();
            $redis->cache($rKey, $data, $exp);
        }
        return $data;
    }

    /**
     * 删列表缓存
     *
     */
    public function delListCache()
    {
        $rKey = $this->getListCacheKey();
        $redis = RedisData::getInstance();
        return $redis->del($rKey);
    }
}
