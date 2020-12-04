<?php

namespace app\npd\model;

use think\Db;
use think\Model;
use my\RedisData;

class ProductFieldnameSite extends Model
{
    protected $connection = 'database_npd';
    protected $table = 't_product_fieldname_site';


    /**
     * 取得getSiteProductFields对应的缓存key
     *
     * @param integer $siteid 站点id
     * @return string
     */
    public function getSiteProFieldsCacheKey($siteid)
    {
        return "npd:productFieldnameSite:siteId_$siteid";
    }

    /**
     * 通过siteid取得列表
     *
     * @param integer $siteid 站点id
     * @param boolean $useCache 是否使用缓存
     * @return array
     */
    public function getSiteProductFields($siteid, $useCache = false)
    {
        $cacheKey = $this->getSiteProFieldsCacheKey($siteid);
        $redis = RedisData::getInstance();
        $data = $redis->cache($cacheKey);
        if (!$data || !$useCache) {
            $data = $this->where('site_id', $siteid)->order(['id' => 'ASC'])->select()->toArray();
            $exp = 3600 * 2;
            $redis->cache($cacheKey, $data, $exp);
        }
        return $data;
    }

    /**
     * 通过siteid取得字段名数组
     *
     * @param integer $siteid 站点id
     * @param boolean $useCache 是否使用缓存
     * @return array
     */
    public function getFieldNames($siteid, $useCache = false)
    {
        $data = $this->getSiteProductFields($siteid, $useCache);
        if (empty($data)) {
            return [];
        }
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = $value['field_name'];
        }
        return $fields;
    }
}
