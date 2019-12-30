<?php

namespace app\carpool\model;

use think\facade\Cache;
use my\RedisData;
use app\common\model\BaseModel;

use think\Db;

class ShuttleTripPartner extends BaseModel
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 't_shuttle_trip_partner';

    // 直接使用配置参数名
    protected $connection = 'database_carpool';
    protected $pk = 'id';

    /**
     * 取得单项数据缓存Key设置
     *
     * @param integer $id 主键
     * @return string
     */
    public function getItemCacheKey($id)
    {
        return "carpool:shuttle:tripPartner:$id";
    }

    /**
     * 取得同行者列表cacheKey
     *
     * @param integer $trip_id 路线id
     * @param string $from_type 类型，0普通行程，1上下班行程
     * @return string
     */
    public function getPartnersCacheKey($trip_id, $from_type = 0)
    {
        return "carpool:shuttle:tripPartners:tripId_{$trip_id},fromType_{$from_type}";
    }

    /**
     * 取得同行者列表
     *
     * @param integer $trip_id 路线id
     * @param string $from_type 类型，0普通行程，1上下班行程
     * @param integer $ex 类型，0普通行程，1上下班行程
     * @return array
     */
    public function getPartners($trip_id, $from_type = 0, $ex = 60)
    {
        $cacheKey = $this->getPartnersCacheKey($trip_id, $from_type);
        $redis = new RedisData();
        $res = $redis->cache($cacheKey);
        if ($res === false) {
            $map = [
                ['is_delete', '=', Db::raw(0)],
                ['trip_id', '=', $trip_id],
            ];
            if ($from_type) {
                $map[] = $from_type ? ['line_type', '>', 0] : ['line_type', '=', 0];
            }
            $res = $this->where($map)->select();
            $res = $res ? $res->toArray() : [];
            if (is_numeric($ex)) {
                $redis->cache($cacheKey, $res, $ex);
            }
        }
        return $res;
    }
}
