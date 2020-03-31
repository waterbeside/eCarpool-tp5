<?php

namespace app\carpool\service;

use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\service\Trips as TripsService;
use my\RedisData;

class TripsDetail
{
    /**
     * 取得详情缓存
     *
     * @param string $from wall or info
     * @param integer $id id
     */
    public function getDetailCacheKey($from, $id)
    {
        if ($from == 'wall') {
            $cacheKey = "carpool:nmTrip:wall_detail:{$id}";
        } else {
            $cacheKey = "carpool:nmTrip:info_detail:{$id}";
        }
        return $cacheKey;
    }

    /**
     * 消除详情缓存
     *
     * @param string $from wall or info
     * @param integer $id id
     */
    public function delDetailCache($from, $id)
    {
        $redis = RedisData::getInstance();
        $cacheKey = $this->getDetailCacheKey($from, $id);
        $redis->del($cacheKey);
        if ($from == 'wall') {
            $Model = new WallModel();
        } else {
            $Model = new InfoModel();
        }
        $Model->delItemCache($id);
        return true;
    }

    /**
     * 取得行情明细
     *
     * @param string $from wall or info
     * @param integer $id id
     */
    public function detail($from, $id, $pb)
    {

        $data = null;
        $TripsService = new TripsService();

        // 查缓存
        $redis = RedisData::getInstance();
        $cacheKey = $this->getDetailCacheKey($from, $id);
        $cacheExp = 30;
        $cacheData = $redis->cache($cacheKey);
        if ($cacheData) {
            if ($cacheData == "-1") {
                return false;
            }
            $data = $cacheData;
        }

        if (!$data || !is_array($data)) {
            $TripsService = new TripsService();
            $fields     = $TripsService->buildQueryFields($from . '_detail');
            if ($from == 'wall') {
                $join = $TripsService->buildTripJoins("s,e,d,department");
                $data = WallModel::alias('t')->field($fields)->join($join)->where("t.love_wall_ID", $id)->find();
            }
            if ($from == 'info') {
                $join = $TripsService->buildTripJoins();
                $data = InfoModel::alias('t')->field($fields)->join($join)->where("t.infoid", $id)->find();
            }
            if (!$data) {
                $redis->cache($cacheKey, -1, $cacheExp);
                return false;
            }
            $data = $data->toArray();
            if ($from == 'wall') {
                $countBaseMap = ['love_wall_ID', '=', $data['love_wall_ID']];
                $data['took_count']       = InfoModel::where([$countBaseMap, ["status", "in", [0, 1, 3, 4]]])->count(); //取已坐数
                $data['took_count_all']   = InfoModel::where([$countBaseMap, ['status', '<>', 2]])->count(); //取已坐数
            }
            $redis->cache($cacheKey, $data, $cacheExp);
        }
        $data = $TripsService->unsetResultValue($TripsService->formatResultValue($data), ($pb ? "detail_pb" : "detail"));
        return $data;
    }
}
