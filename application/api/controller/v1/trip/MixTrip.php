<?php

namespace app\api\controller\v1\trip;

use app\api\controller\ApiBase;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\ShuttleTrip as ShuttleTripModel;
use app\carpool\service\shuttle\Trip as ShuttleTripService;
use app\carpool\service\TripsMixed as TripsMixedService;
use my\RedisData;
use my\Utils;

use think\Db;

/**
 * 班车路线
 * Class index
 * @package app\api\controller
 */
class MixTrip extends ApiBase
{

    
    /**
     * 指定时间内的我的行程
     *
     */
    public function my_coming()
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $redis = new RedisData();
        $TripsMixedService = new TripsMixedService();
        $cacheKey =  $TripsMixedService->getComingListCacheKey($uid);
        $listData = $redis->cache($cacheKey);
        if (is_array($listData) && empty($listData)) {
            return $this->jsonReturn(20002, lang('No data'));
        }
        if (!$listData) {
            // 先取 info 表数据
            $map1 = [
                ['status', "<>", 2],
                ["time", "between", [date('YmdHi', strtotime('-10 minute')), date('YmdHi', strtotime('+12 hour'))]],
                ['carownid', '>', 0],
                ['carownid|passengerid', '=', $uid],
            ];
            $res1 = InfoModel::field("infoid as id, time")->where($map1)->order('time ASC')->select();
            $res1 = $res1 ? $res1->toArray() : [];
            foreach ($res1 as $key => $value) {
                $res1[$key]['time'] = intval(strtotime($value['time'] . '00'));
                $res1[$key]['from'] = 'info';
            }

            // 再取shuttle_trip数据
            $ShuttleTripModel = new ShuttleTripModel();
            $map2 = [
                ['status', ">", -1],
                ["time", "between", [date('Y-m-d H:i:s', strtotime('-10 minute')), date('Y-m-d H:i:s', strtotime('+12 hour'))]],
                ['uid', '=', $uid],
            ];
            $res2 = $ShuttleTripModel->field("id, time, user_type, uid, trip_id")->where($map2)->order('time ASC')->select();
            $res2 = $res2 ? $res2->toArray() : [];
            $res2_list = [];
            foreach ($res2 as $key => $value) {
                if ($value['user_type'] == 1) { // 如果是司机
                    $countPsg = $ShuttleTripModel->countPassengers($value['id']);
                    if ($countPsg < 1) {
                        continue;
                    }
                } else { // 如果是乘客
                    if ($value['trip_id'] < 1) {
                        continue;
                    }
                }
                $value['time'] = intval(strtotime($value['time'] . '00'));
                $value['from'] = 'shuttle_trip';
                $res2_list[] = $value;
            }
            $res2_list = Utils::getInstance()->filterListFields($res2_list, ['user_type', 'uid', 'trip_id'], true);
            // 合并列表
            $listData = array_merge($res1, $res2_list);
            if (count($listData) === 0) {
                $redis->cache($cacheKey, [], 10);
                return $this->jsonReturn(20002, 'No Data');
            }
            $redis->cache($cacheKey, $listData, 60 * 2);
        }
        
        return $this->jsonReturn(0, ['lists' => $listData], 'Success');
    }
}
