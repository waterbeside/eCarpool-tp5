<?php

namespace app\api\controller\v1\shuttle;

use app\api\controller\ApiBase;
use app\carpool\model\ShuttleTime as ShuttleTimeModel;
use my\RedisData;


use think\Db;

/**
 * 班车时刻
 * Class Time
 * @package app\api\controller
 */
class Time extends ApiBase
{


    public function index($type = -1)
    {
        $redis = new RedisData();
        $ShuttleTimeModel = new ShuttleTimeModel();

        $ex = 60 * 30;
        $keyword = input('get.keyword');
        $userData = $this->getUserData(1);


        $returnData = null;
        $cacheKey  = $ShuttleTimeModel->getListCacheKey($type);
        $returnData = $redis->cache($cacheKey);

        if (is_array($returnData) && empty($returnData)) {
            return $this->jsonReturn(20002, [], lang('No data'));
        }

        if (!$returnData) {
            $map  = [
                ['is_delete', "=", Db::raw(0)],
                ['status', "=", Db::raw(1)],
            ];
            if (is_numeric($type) && $type > -1) {
                $map[] = ['type', '=', $type];
            }
            $resData = $ShuttleTimeModel->distinct(true)->field('type, hours, minutes')
                ->where($map)->order('hours ASC, minutes ASC')->select()->toArray();
            if (empty($resData)) {
                if (!$keyword) {
                    $redis->cache($cacheKey, [], $ex);
                }
                return $this->jsonReturn(20002, [], lang('No data'));
            }
            $redis->cache($cacheKey, $resData, $ex);
            $returnData = [
                'lists' => $resData,
            ];
        }
        return $this->jsonReturn(0, $returnData, 'Successful');
    }
}
