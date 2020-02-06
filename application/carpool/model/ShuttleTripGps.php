<?php

namespace app\carpool\model;

use think\facade\Cache;
use my\RedisData;
use app\common\model\BaseModel;

use think\Db;

class ShuttleTripGps extends BaseModel
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 't_shuttle_trip_gps';

    // 直接使用配置参数名
    protected $connection = 'database_carpool';
    protected $pk = 'id';
    

    /**
     * 通过行程id和用户id取得GPS数据
     *
     * @param integer $trip_id 行程id
     * @param integer $uid 用户id
     * @param integer $ex 缓存时间
     * @return array
     */
    public function getGpsByTripAndUid($trip_id, $uid, $ex = 20)
    {
        $cacheKey = "carpool:shuttle:tripGps:tid_{$trip_id}_uid_{$uid}";
        $redis = $this->redis();
        $res = $redis->cache($cacheKey);
        if ($res === false || $ex === false) {
            $map = [
                ['uid', '=', $uid],
                ['trip_id', '=', $trip_id],
            ];
            $res = $this->where($map)->find();
            if ($res) {
                $res = $res->toArray();
                $res['gps'] = $redis->formatRes($res['gps']) ?: [];
                foreach ($res['gps'] as $key => $value) {
                    $res['gps'][$key] = $redis->formatRes($value) ?: [];
                }
                $redis->cache($cacheKey, $res, $ex);
            } else {
                $redis->cache($cacheKey, [], 4);
            }
        }
        return $res;
    }
}
