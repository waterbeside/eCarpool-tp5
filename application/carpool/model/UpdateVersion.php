<?php

namespace app\carpool\model;

use think\Model;
use my\RedisData;
use think\Db;

class UpdateVersion extends Model
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 'update_version';

    // 直接使用配置参数名
    protected $connection = 'database_carpool';

    protected $pk = 'update_version_id';

    /**
     * 通过平台查找数据
     *
     * @param String  $platform platform
     * @param Integer $exp 缓存有效时长
     * @return Array
     */
    public function findByPlatform($platform, $exp = 60 * 10)
    {
        $cacheKey = "carpool:update_version:{$platform}";
        $redis = RedisData::getInstance();
        $cacheData = $redis->cache($cacheKey);
        if ($cacheData) {
            return $cacheData;
        }
        $map = [
            ['app_id', '=', Db::raw(1)],
            ['is_delete', '=', Db::raw(0)],
            ['platform', '=', $platform]
        ];
        $res  = $this->where($map)->order('update_version_id DESC')->find();
        if ($res) {
            $res = $res->toArray();
            $cacheData = $redis->cache($cacheKey, $res, $exp);
            $cacheKey2 = "carpool:update_version:{$platform}:white_list";
            $cacheData = $redis->cache($cacheKey2, array_filter(explode(',', $res['white_list'])), $exp);
        }
        return $res;
    }

    /**
     * 清除缓存
     *
     * @param String  $platform platform
     * @return void
     */
    public function DeleteCacheByPlatform($platform)
    {
        $cacheKey = "carpool:update_version:{$platform}";
        $cacheKey2 = "carpool:update_version:{$platform}:white_list";
        $redis = RedisData::getInstance();
        $redis->del($cacheKey, $cacheKey2);
    }
}
