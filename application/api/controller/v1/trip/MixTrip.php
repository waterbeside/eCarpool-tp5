<?php

namespace app\api\controller\v1\trip;

use app\api\controller\ApiBase;
use app\carpool\model\Address as AddressModel;
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
            $redis->cache($cacheKey, $listData, 50);
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
        $TripsServ = new TripsService();
        $ShuttleTripModel = new ShuttleTripModel();
        if (!$listData) {
            // 先取上下班行程数据
            $list_1 = $ShuttleTripModel->getListByTimeOffset(time(), $uid, $offsetTime, $pz);
            // 先查普通行程數據
            // $list_2 = $TripsServ->getUnionListByTimeOffset(time(), $uid, $offsetTime, $pz);
            $list_2 = [];
            
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
        $newList = [];
        foreach ($listData as $key => $value) {
            $value['have_started'] = $TripsMixedService->haveStartedCode($value['time'], $value['time_offset']);
            $value['took_count'] = 0;
            if ($value['user_type'] == 1) { // 如果是司机行程
                $value['took_count'] = $TripsMixedService->countPassengers($value['id'], $value['from']) ?: 0;
                // if ($value['from'] == 'shuttle_trip') {
                //     $value['took_count'] = $ShuttleTripModel->countPassengers($value['id']);
                // } elseif ($value['from'] == 'wall') {
                //     $value['took_count'] =  $InfoModel->countPassengers($value['id']);
                // } elseif ($value['from'] == 'info') {
                //     $value['took_count'] = 1;
                // }
                // 如果行程已出发，而且无乘客，跳过这些行程
                if (empty($value['took_count']) && $value['have_started'] > 1) {
                    continue;
                }
            } else { // 如果是乘客行程
                // 如果行程已出发，且无司机，跳过这些行程
                if ($value['trip_id'] == 0 && $value['have_started'] > 1) {
                    continue;
                }
            }
            $newList[] = $value;
        }
        $returnData['lists'] = $newList;
        return $this->jsonReturn(0, $returnData, 'Success');
    }

    /**
     * 历史行程
     *
     * @param integer $page
     * @param integer $pagesize
     * @return void
     */
    public function history($page = 1, $pagesize = 20)
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $pagesize =  is_numeric($pagesize) &&  $pagesize > 0 ? $pagesize : 20;
        $redis = new RedisData();
        $ShuttleTrip = new ShuttleTripModel();
        $ShuttleTripService = new ShuttleTripService();
        $TripsMixed = new TripsMixedService();
        
        $cacheKey = $ShuttleTrip->getMyListCacheKey($uid, 'history');
        $rowCacheKey = "mixed_pz_{$pagesize},page_$page";
        $returnData = $redis->hCache($cacheKey, $rowCacheKey);
        if (is_array($returnData) && empty($returnData)) {
            return $this->jsonReturn(20002, $returnData, lang('No data'));
        }
        $now = time();
        if (!$returnData) {
            $ex = 60 * 5;

            $shuttleTripLaunchDate = config('trips.shuttle_trip_launch_date') ?? null;
            $timeBetweenInfo = [strtotime('2018-01-01 00:00:00'), $shuttleTripLaunchDate ?  strtotime($shuttleTripLaunchDate) : time('-30 minute')];
            $baseSql = $TripsMixed->buildListUnionSql($uid, [0,1,3,4,5], [null, strtotime('+60 minute')], $timeBetweenInfo);
            $ctor =  Db::connect('database_carpool')->table($baseSql)->alias('t')->order('t.time DESC');

            // **** 开始查询
            $returnData = $this->getListDataByCtor($ctor, $pagesize, false);
            if (empty($returnData['lists'])) {
                $redis->hCache($cacheKey, $rowCacheKey, [], $ex);
                return $this->jsonReturn(20002, 'No data');
            }
            $newList = [];
            foreach ($returnData['lists'] as $key => $value) {
                $value = $ShuttleTripService->formatTimeFields($value, 'item', ['time','create_time']);
                $value['have_started'] = $TripsMixed->haveStartedCode($value['time'], $value['time_offset']);
                // 排除出发未到30分钟，而状态还未结束的行程
                if ($value['have_started'] < 3 && $value['status'] < 3) {
                    continue;
                }
                $ex = $value['have_started'] > 1 ? 60 * 30 : 10;
                $value['took_count'] = 0;
                if ($value['user_type'] == 1) {
                    $value['took_count'] =  in_array($value['from'], ['shuttle_trip', 'wall']) ?  $TripsMixed->countPassengers($value['id'], $value['from'], $ex) : 1;
                }
                $newList[] = $value;
            }
            $returnData['lists'] = $newList;
            $redis->hCache($cacheKey, $rowCacheKey, $returnData, $ex);
        }

        $newList = [];
        $AddressModel = new AddressModel();
        $Utils = new Utils();
        foreach ($returnData['lists'] as $key => $value) {
            if ($value['from'] == 'shuttle_trip') {
                $extraInfo = $Utils->json2Array($value['extra_info']);
                $value['map_type'] = $extraInfo['line_data']['map_type'] ?? 0;
                $value['waypoints'] = $extraInfo['line_data']['waypoints'] ?? [];
            }
            // 修补缺失的行程相关字段
            if (empty($value['start_name']) && !empty($value['start_id'])) {
                $startItem = $AddressModel->getItem($value['start_id']);
                if ($startItem) {
                    $value['start_name'] = $startItem['addressname'];
                }
            }
            if (empty($value['end_name']) && !empty($value['end_id'])) {
                $startItem = $AddressModel->getItem($value['end_id']);
                if ($startItem) {
                    $value['end_name'] = $startItem['addressname'];
                }
            }
            $value = $ShuttleTrip->packLineDataFromTripData($value, ['start_name', 'end_name', 'waypoints'], ['name']);
            unset($value['extra_info']);
            $newList[] = $value;
        }
        $returnData['lists'] = $newList;
        return $this->jsonReturn(0, $returnData, 'Successful');
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
        $start_lnglat = input('param.start_lnglat');
        $end_lnglat = input('param.end_lnglat');
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
