<?php

namespace app\api\controller\v1\shuttle;

use app\api\controller\ApiBase;
use app\carpool\model\ShuttleTripGps;
use my\RedisData;


use think\Db;

/**
 * 行程gps
 * Class Gps
 * @package app\api\controller
 */
class Gps extends ApiBase
{


    public function index($trip_id = 0, $uid = 0)
    {
        if (!is_numeric($trip_id) || !is_numeric($uid) || !$trip_id < 1 || !$uid < 1) {
            return $this->jsonReturn(992, 'Successful');
        }
        $redis = new RedisData();
        $ShuttleTripGps = new ShuttleTripGps();
        if ($trip_id > 0) {

        }
        $res = $ShuttleTripGps->getGpsByTripAndUid($trip_id, $uid);
        return $this->jsonReturn(0, $res, 'Successful');
    }
}
