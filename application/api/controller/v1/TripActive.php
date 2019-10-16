<?php

namespace app\api\controller\v1;

use think\Db;
use app\api\controller\ApiBase;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\model\InfoActiveLine;
use my\RedisData;

/**
 * 行程上传座标
 * Class TripActive
 * @package app\api\controller
 */
class TripActive extends ApiBase
{

    /**
     * 取得上传的座标点
     */
    public function gps($infoid = 0, $role = "driver")
    {

        $redis = new RedisData();
        $role  =   strtolower($role);
        if (!is_numeric($infoid) || !$infoid || !in_array($role, ['driver', 'passenger'])) {
            return $this->jsonReturn(992, '参数错误');
        }

        $cacheKey = "carpool:tripGps:v1:{$infoid}:{$role}";
        $redis = new RedisData();
        $cacheData = $redis->cache($cacheKey);
        if ($cacheData !== false) {
            $code = empty($cacheData) ? 20002 : 0;
            return $this->jsonReturn($code, ["lists" => $cacheData], "success");
        }
        $infoData = InfoModel::find($infoid);
        if (!$infoData) {
            $redis->cache($cacheKey, [], 60 * 2);
            return $this->jsonReturn(20002, ["lists" => []], "No info data");
        }
        $trip_time = strtotime($infoData['time'] . '00');
        // $cacheExp = $trip_time < time() - 3600 * 24 * 3 ? 3600 * 24 * 2 : ( $trip_time <  time() - 3600 * 3 ?   60 * 30 : 60 * 5 );
        $cacheExp = 30;

        // 取得司机座标
        if ($role == "driver") {
            $love_wall_id = $infoData['love_wall_ID'];
            $map = [
                ['', 'exp', Db::raw("uid in (select carownid from info where infoid= $infoid )")]
            ];
            if (!$love_wall_id) {
                $map[] = ['infoid', '=', "$infoid"];
            } else {
                $map[] = ['', 'exp', Db::raw("infoid in (select infoid from info where love_wall_ID = $love_wall_id)")];
            }
            $returnData = InfoActiveLine::where($map)->order('locationtime ASC')->select();
        } elseif ($role == "passenger") {
            $map = [
                ['infoid', '=', "$infoid"],
                ['', 'exp', Db::raw("uid in (select passengerid from info where infoid= $infoid )")]
            ];
            $returnData = InfoActiveLine::where($map)->order('locationtime ASC')->select();
        }
        $redis->cache($cacheKey, $returnData, $cacheExp);
        $code = empty($returnData) ? 20002 : 0;
        $this->jsonReturn($code, ["lists" => $returnData], "success");
    }
}
