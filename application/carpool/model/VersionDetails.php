<?php

namespace app\carpool\model;

use think\Model;
use my\RedisData;

class VersionDetails extends Model
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 'version_details';

    // 直接使用配置参数名
    protected $connection = 'database_carpool';

    protected $pk = 'version_detail_id';


    /**
     * 通过版本详情
     *
     * @param String  $ver 版本号
     * @param String  $platform platform
     * @param String  $platform 语言
     * @param Integer $exp 缓存有效时长
     * @return Array
     */
    public function findByVer($ver, $platform, $lang, $exp = 60 * 10)
    {
        $cacheKey = "carpool:update_version_detail:{$ver}:{$platform}:{$lang}";
        $redis = new RedisData();
        $cacheData = $redis->cache($cacheKey);
        if ($cacheData) {
            return $cacheData;
        }
        $mapDetail = [
            ['app_id', '=', 1],
            ['platform', '=', $platform],
            ['language_code', '=', $lang],
            ['version_code', '=', $ver],
        ];
        $res  = $this->where($mapDetail)->find();
        if ($res) {
            $res = $res->toArray();
            $cacheData = $redis->cache($cacheKey, $res, $exp * 10);
        }
        return $res;
    }

    /**
     * 清除缓存
     *
     * @param String  $ver 版本号
     * @param String  $platform platform
     * @param String  $platform 语言
     * @return void
     */
    public function DeleteCacheByVer($ver, $platform, $lang)
    {
        $cacheKey = "carpool:update_version_detail:{$ver}:{$platform}:{$lang}";
        $redis = new RedisData();
        $cacheData = $redis->delete($cacheKey);
    }
}
