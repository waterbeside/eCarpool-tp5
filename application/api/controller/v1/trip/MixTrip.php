<?php

namespace app\api\controller\v1\trip;

use app\api\controller\ApiBase;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\ShuttleTrip as ShuttleTripModel;
use app\carpool\service\shuttle\Trip as ShuttleTripService;
use app\carpool\service\TripsMixed as TripsMixedService;
use app\carpool\service\Trips as TripsService;
use my\RedisData;
use my\Utils;

use think\Db;

/**
 * 混合行程
 * Class MixTrip
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
        $cacheKey =  $TripsMixedService->getMyListCacheKey($uid);
        $rowKey = "coming";
        $listData = $redis->hCache($cacheKey, $rowKey);
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
            $res1 = InfoModel::field("infoid as id, time, love_wall_ID as trip_id")->where($map1)->order('time ASC')->select();
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
            $res2_list = Utils::getInstance()->filterListFields($res2_list, ['user_type', 'uid'], true);
            // 合并列表
            $listData = array_merge($res1, $res2_list);
            if (count($listData) === 0) {
                $redis->cache($cacheKey, [], 10);
                return $this->jsonReturn(20002, lang('No Data'));
            }
            $redis->cache($cacheKey, $listData, 60 * 2);
        }
        
        return $this->jsonReturn(0, ['lists' => $listData], 'Success');
    }

    /**
     * 我的未来n小时内的行程
     *
     */
    public function my($type = 0)
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $redis = new RedisData();
        $TripsMixedService = new TripsMixedService();
        
        $cacheKey =  $TripsMixedService->getMyListCacheKey($uid);
        $pz = 0;
        if ($type > 0) {
            $nextHour = -1;
            $pz = $type == 1 ? 1 : $pz;
            $offsetTime = [60 * 10, 'all'];
        } else {
            $nextHour = 48; // 取未来多少小时的数据
            $offsetTime = [60 * 10, 60 * 60 * $nextHour];
        }
        $returnData = [
            'next_hour'=> $nextHour,
        ];
        $rowKey = "page_1,pz_{$pz},nextHour_{$nextHour}";
        $listData = $redis->hCache($cacheKey, $rowKey);
        if (is_array($listData) && empty($listData)) {
            return $this->jsonReturn(20002, $returnData, lang('No data'));
        }
        if (!$listData) {
            $TripsServ = new TripsService();
            $ShuttleTripModel = new ShuttleTripModel();

            // 先取上下班行程数据
            $list_1 = $ShuttleTripModel->getListByTimeOffset(time(), $uid, $offsetTime, $pz);
            // 先查普通行程數據
            $list_2 = $TripsServ->getUnionListByTimeOffset(time(), $uid, $offsetTime, $pz);
            
            $listData = array_merge($list_1 ?: [], $list_2 ?: []);
            if ($listData) {
                $listData =  Utils::getInstance()->listSortBy($listData, 'time', 'asc');
            }
        }
        if (empty($listData)) {
            $listData = $redis->hCache($cacheKey, $rowKey, [], 10);
            return $this->jsonReturn(20002, $returnData, lang('No data'));
        }
        $redis->hCache($cacheKey, $rowKey, $listData, 60 * 2);
        $returnData['lists'] = $listData;
        return $this->jsonReturn(0, $returnData, 'Success');
    }

    /**
     * 推荐列表
     *
     * @return void
     */
    public function rclist($userType = null)
    {
        $redis = new RedisData();
        $lockKey = 'carpool:mixTrip:rclist';
        if (!$redis->lock($lockKey, 10, 200, 20 * 1000)) { // 添加并发锁
            return $this->jsonReturn(20009, lang('The network is busy, please try again later'));
        }

        $type = input('param.type', -1);
        $lineId = input('param.line_id');
        $lnglat = input('param.lnglat');
        $showPassenger = input('param.show_passenger', 0);

        $userData = $this->getUserData(1);
        if ($userType === null) {
            return $this->jsonReturn(992, Lang('Error param'));
        }
        $TripsMixedService = new TripsMixedService();
        $extData = [
            'limit' => 30,
        ];
        if ($lineId) {
            $extData['line_id'] = $lineId;
        }
        if ($lnglat) {
            $extData['lnglat'] = $lnglat;
        }
        if ($showPassenger) {
            $extData['show_passenger'] = $showPassenger;
        }
        $returnData = $TripsMixedService->lists($userType, $userData, $type, $extData);
        $list = $returnData['lists'];
        $redis->unlock($lockKey); // 解锁
        if (empty($list)) {
            return $this->jsonReturn(20002, lang('No data'));
        }
        return $this->jsonReturn(0, $returnData);
    }

    public function cars()
    {
        $this->rclist(1);
    }

    public function requests()
    {
        $this->rclist(0);
    }
}
