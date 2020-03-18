<?php

namespace app\api\controller\v1\shuttle;

use app\api\controller\ApiBase;
use app\carpool\model\ShuttleTrip;
use app\carpool\model\ShuttleTripGps;
use app\carpool\model\ShuttleTripPartner;
use app\carpool\model\ShuttleTripWaypoint;
use app\carpool\model\User;
use app\carpool\service\shuttle\Trip as ShuttleTripService;
use app\carpool\service\shuttle\Partner as ShuttlePartnerService;
use app\carpool\service\Trips as TripsService;
use app\carpool\service\TripsMixed;
use app\carpool\service\TripsPushMsg;
use app\carpool\validate\shuttle\Trip as ShuttleTripVali;
use app\carpool\validate\shuttle\Partner as ShuttlePartnerVali;
use my\RedisData;
use my\Utils;

use think\Db;

/**
 * 班车行程
 * Class Trip
 * @package app\api\controller
 */
class Trip extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }

    public function list($rqType, $page = 1, $pagesize = 0, $returnType = 1)
    {
        $userData = $this->getUserData(1);
        if (!in_array($rqType, ['cars','requests'])) {
            return $returnType ? $this->jsonReturn(992, lang('Error params')) : [992, null, lang('Error params')];
        }
        $dev = input('param.dev/d', 0);
        $keyword = input('get.keyword/s', '', 'trim');
        $line_id = input('get.line_id/d', 0);
        $type = input('get.type/d', -1);
        $comid = input('get.comid/d', 0);
        $city = input('get.city/s', '', 'trim');
        $orderby = input('get.orderby', 'time', 'strtolower');
        $orderby = $orderby == 'distance' ? 'distance' : 'time';
        $lnglat = input('get.lnglat', '');
        $lnglat = is_array($lnglat) ? $lnglat : explode(',', $lnglat);
        $lnglat = count($lnglat) > 1 ? array_map('trim', $lnglat) : null;
        // if (!$line_id) {
        //     return $returnType ? $this->jsonReturn(992, lang('Error line_id')) : [992, null, lang('Error line_id')];
        // }

        $Utils = new Utils();
        $redis = new RedisData();
        $ShuttleTrip = new ShuttleTrip();
        $TripsService = new TripsService();
        $ShuttleTripService = new ShuttleTripService();
        $TripsMixed = new TripsMixed();

        $returnData = null;
        $geohash = !empty($lnglat) ? $redis->getGeohash($lnglat) : null;
        $geohash_n = !empty($geohash) ? substr($geohash, 0, 7) : null;
        
        // 缓存处理
        $ex = (!empty($lnglat) && $line_id == 0) || !empty($keyword) ? 10 : 60;
        $cacheKey  = $ShuttleTrip->getListCacheKeyByLineId($line_id, $rqType);
        $rowCacheKey = "pz_{$pagesize},page_$page,type_$type,comid_$comid,orderby_$orderby,keyword_$keyword";
        $rowCacheKey .= ",uCompanyId_{$userData['company_id']}";
        $rowCacheKey .= !empty($geohash_n) && $line_id == 0 ? ",geohash_{$geohash_n}" : '';
        $rowCacheKey .= !empty($city)  && $city == 'all' ? ",city_{$city}" : '';
        
        $returnData = $redis->hCache($cacheKey, $rowCacheKey);
        if (is_array($returnData) && empty($returnData)) {
            // $returnData['lineData'] = $lineData;
            return $returnType ? $this->jsonReturn(20002, $returnData, lang('No data')) : [20002, $returnData, lang('No data')];
        }
        if (!$returnData) {
            $userAlias = 'u';
            $time = time();
            $betweenTimeBase = $ShuttleTrip->getBaseTimeBetween($time, 'default', 'Y-m-d H:i:s');
            // 字段
            $fields = $ShuttleTrip->getListField('t'); // 行程相关字段
            $fields .=  ',' .$TripsService->buildUserFields($userAlias); // 用户相关字段
            // $fields .= ',l.id as l_id, l.start_id, l.start_name, l.start_longitude, l.start_latitude, l.end_id, l.end_name, l.end_longitude, l.end_latitude, l.map_type'; // 路线相关字段
            if (!empty($lnglat) && $line_id == 0) {
                // 添加 distance 字段以排序
                $fieldLng = $type == 2 && $comid > 0 ? 't.end_longitude' : 't.start_longitude';
                $fieldLat = $type == 2  && $comid > 0 ? 't.end_latitude' : 't.start_latitude';
                $distanceSql = $Utils->getDistanceFieldSql($fieldLng, $fieldLat, $lnglat[0], $lnglat[1], 'distance');
                $fields .=  ','.$distanceSql;
                $lnglatRangeSql = $Utils->buildCoordRangeWhereSql($fieldLng, $fieldLat, $lnglat[0], $lnglat[1], 1000*200);
            } else {
                $fields .=  ', 0 as distance';
            }
            // 排序
            $order = 't.time ASC';
            if ($orderby == 'distance') {
                $order = 'distance ASC, t.time ASC';
            } else {
                $order = 't.time ASC, distance ASC';
            }
            if ($rqType === 'cars') {
                // $userAlias = 'd';
                $betweenTimeArray = $ShuttleTrip->getBaseTimeBetween($time, [60 * 5, 0]);
                $userType = 1;
                $comefrom = 1;
            } elseif ($rqType === 'requests') {
                // $userAlias = 'p';
                $betweenTimeArray = $ShuttleTrip->getBaseTimeBetween($time, [10, 0]);
                $userType = 0;
                $comefrom = 2;
            }
            // join
            $join = [
                ["user {$userAlias}", "t.uid = {$userAlias}.uid", 'left'],
                // ["t_shuttle_line l", "t.line_id = l.id", 'left'],
            ];

            // where
            $map  = [
                ['t.time', 'between', $betweenTimeBase],
                ['t.is_delete', '=', Db::raw(0)],
                ['t.status', 'in', [0,1]],
                ['t.trip_id', '=', Db::raw(0)],
                ['', 'exp', $ShuttleTrip->whereTime2Str($betweenTimeArray[0], $betweenTimeArray[1], true)]
            ];
            // 筛司机乘客
            if (isset($userType)) {
                $map[] = ['t.user_type', '=', Db::raw($userType)];
            }
            // 筛乘客类型
            if (isset($comefrom)) {
                $map[] = ['t.comefrom', '=', Db::raw($comefrom)];
            }
            // 允许相互拼车的公司组
            $map[] = $TripsService->buildCompanyMap($userData, 'u');
            // 约束座标范围 (当选了城市后不约束)
            if (isset($lnglatRangeSql) && empty($city)) {
                $map[] = ['', 'exp', Db::raw($lnglatRangeSql)];
            }

            // 筛路线 或上下班类型
            if ($line_id > 0) {
                $map[] =  ['t.line_id', '=', $line_id];
            } elseif (is_numeric($type)) {
                if ($type > -1) {
                    $map[] = ['t.line_type', '=', $type];
                } elseif ($type == -2) {
                    $map[] = ['t.line_type', 'in', [1,2]];
                }
            }
            // 筛公司站点
            if ($comid > 0) {
                $cmpFieldName = 't.start_id|t.end_id';
                if ($type == 1) {
                    $cmpFieldName = 't.end_id';
                } elseif ($type == 2) {
                    $cmpFieldName = 't.start_id';
                }
                $map[] = [$cmpFieldName, '=', $comid];
            }
            // 排除已删用户；
            $map[] = ["{$userAlias}.is_delete", '=', Db::raw(0)];

            // 如果城市不为空,且没传$comid,则 join站点表以查询城市;
            if (!empty($city) && empty($comid)) {
                $join[] = ["address a", "t.start_id = a.addressid", 'left'];
                $map[] = ["a.city", '=', $city];
            }
            // 关键字搜索
            $waypointTripIds = [];
            if ($keyword && $userType == 1) { // 查询途经点
                $mapWaypoint = [
                    ["name", 'like', "%$keyword%"],
                    ['time', 'between', $betweenTimeBase],
                ];
                $waypointTripIds = ShuttleTripWaypoint::where($mapWaypoint)->cache(5)->column('trip_id');
            }
            $ctor = $ShuttleTrip->alias('t')->field($fields)->join($join)->where($map)->where(function ($query) use ($keyword, $waypointTripIds, $userAlias) {
                if (!empty($keyword)) {
                    $mapKeyword= [
                        ["t.start_name|t.end_name|{$userAlias}.name|{$userAlias}.nativename", 'like', "%$keyword%"]
                    ];
                    $query->where($mapKeyword);
                    if (!empty($waypointTripIds) && is_array($waypointTripIds)) {
                        $query->whereOr([
                            ['id', 'in', $waypointTripIds]
                        ]);
                    }
                }
            })->order($order);
            if ($dev) {
                return $this->jsonReturn(0, $ctor->fetchSql()->select());
            }
            $returnData = $this->getListDataByCtor($ctor, $pagesize, false);
            if (empty($returnData['lists'])) {
                if (!$keyword) {
                    $redis->hCache($cacheKey, $rowCacheKey, [], $ex);
                }
                return $returnType ? $this->jsonReturn(20002, lang('No data')) : [20002, null, lang('No data')];
            }
            // $returnData['lineData'] = $lineData;
            if (!$keyword) {
                $redis->hCache($cacheKey, $rowCacheKey, $returnData, $ex);
            }
        }
        // $returnData['lists'] = $ShuttleTripService->formatTimeFields($returnData['lists'], 'list', ['time','create_time']);

        foreach ($returnData['lists'] as $key => $value) {
            $value = $TripsMixed->formatResultValue($value);
            $value = $ShuttleTrip->packLineDataFromTripData($value, null, ['name', 'longitude', 'latitude']);
            unset($value['extra_info']);
            $returnData['lists'][$key] = $value;
        }
        return $returnType ? $this->jsonReturn(0, $returnData, 'Successful') : [0, $returnData, 'Successful'];
    }

    
    /**
     * 空座位列表
     *
     * @param integer $page 页码
     * @param integer $pagesize 每页多少条
     */
    public function cars($page = 1, $pagesize = 50, $passengers = 1)
    {
        $isGetPassengers = $passengers;
        $res = $this->list('cars', $page, $pagesize, 0);
        if (isset($res[1]['lists'])) {
            $ShuttleTripModel = new ShuttleTrip();
            $ShuttleTripService = new ShuttleTripService();
            foreach ($res[1]['lists'] as $key => $value) {
                if ($isGetPassengers) {
                    // $userFields = ['uid', 'loginname', 'name', 'nativename','sex','phone','mobile'];
                    // $resPassengers = $ShuttleTripService->passengers($value['id'], $userFields, ['id','status'], 0);
                    // $res[1]['lists'][$key]['passengers'] = $resPassengers ?: [];
                    // $res[1]['lists'][$key]['took_count'] = count($resPassengers);
                    $res[1]['lists'][$key]['took_count'] = $ShuttleTripModel->countPassengers($value['id']);
                }
            }
        }
        return $this->jsonReturn($res[0], $res[1], $res[2]);
    }

    /**
     * 约车需求列表
     *
     * @param integer $page 页码
     * @param integer $pagesize 每页条数
     */
    public function requests($page = 1, $pagesize = 50)
    {
        $res = $this->list('requests', $page, $pagesize, 0);
        if (isset($res[1]['lists'])) {
            $lists = $res[1]['lists'];
            $lists = $this->filterListFields($lists, [], true);
            $res[1]['lists'] = $lists;
        }
        return $this->jsonReturn($res[0], $res[1], $res[2]);
    }

    /**
     * 地图上的墙上空座位
     */
    public function map_cars()
    {
        $TripsService = new TripsService();
        $ShuttleTrip = new ShuttleTrip();
        $ShuttleTripService = new ShuttleTripService();
        $redis = new RedisData();

        $type = input('get.type/d', -1);
        $userData = $this->getUserData(1);

        $time = time();
        
         // 缓存处理
        $ex = 60;
        $cacheKey  = $ShuttleTrip->getListCacheKeyByLineId(0, 'cars');
        $rowCacheKey = "map_cars,type_$type";
        $rowCacheKey .= ",uCompanyId_{$userData['company_id']}";
        
        $returnData = $redis->hCache($cacheKey, $rowCacheKey);
        if (is_array($returnData) && empty($returnData)) {
            return $this->jsonReturn(20002, $returnData, lang('No data'));
        }
        if (!$returnData) {
            if (!$this->lockAction()) { // 添加并发锁
                return $this->jsonReturn(20009, lang('The network is busy, please try again later'));
            }
            $fields = $ShuttleTrip->getListField('t'); // 行程相关字段
            $fields .= ', u.carcolor as u_carcolor';
            $betweenTimeBase = $ShuttleTrip->getBaseTimeBetween($time, 'default', 'Y-m-d H:i:s');
            $betweenTimeArray = $ShuttleTrip->getBaseTimeBetween($time, [60 * 5, 0]);
            // where
            $map  = [
                ['t.time', 'between', $betweenTimeBase],
                ['t.is_delete', '=', Db::raw(0)],
                ['t.status', 'in', [0,1]],
                ['t.trip_id', '=', Db::raw(0)],
                ['t.user_type', '=', 1],
                ['t.comefrom', '=', 1],
                ['', 'exp', $ShuttleTrip->whereTime2Str($betweenTimeArray[0], $betweenTimeArray[1], true)]
            ];
            $map[] = $TripsService->buildCompanyMap($userData, 'u');
            if (is_numeric($type)) {
                if ($type > -1) {
                    $map[] = ['t.line_type', '=', $type];
                } elseif ($type == -2) {
                    $map[] = ['t.line_type', 'in', [1,2]];
                }
            }
            // join
            $join = [
                ["user u", "t.uid = u.uid", 'left'],
                // ["t_shuttle_line l", "t.line_id = l.id", 'left'],
            ];
            $lists = $ShuttleTrip->alias('t')->field($fields)->join($join)->where($map)->select();
            
            if ($lists) {
                $lists = $lists->toArray();
            }
            $returnData = [
                'lists' => $lists,
            ];
            $redis->hCache($cacheKey, $rowCacheKey, $returnData, $ex);
        }
        if ($returnData === false) {
            $this->unlockAction();
            return $this->jsonReturn(20002, lang('No data'));
        }
        foreach ($returnData['lists'] as $key => $value) {
            $value = $ShuttleTripService->formatTimeFields($value, 'item', ['time','create_time']);
            $value = $ShuttleTrip->packLineDataFromTripData($value, [
                'start_name', 'start_longitude', 'start_latitude',
                'end_name', 'end_longitude', 'end_latitude',
                'map_type'], []);
            unset($value['extra_info']);
            $returnData['lists'][$key] = $value;
        }
        $this->unlockAction();
        $this->jsonReturn(0, $returnData, "success");
    }

    /**
     * 我的行程
     *
     * @param integer $page 页码
     * @param integer $show_member 是否显示成员
     */
    public function my($show_member = 1, $type = -1)
    {
        $page = 1;
        $pagesize = 0;
        $isShowMember = $show_member;
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $Utils = new Utils();
        $redis = new RedisData();
        $TripsService = new TripsService();
        $ShuttleTrip = new ShuttleTrip();
        $ShuttleTripService = new ShuttleTripService();
        $TripsMixed = new TripsMixed();
        $cacheKey = $ShuttleTrip->getMyListCacheKey($uid, 'my');
        $rowCacheKey = "pz_{$pagesize},page_$page,type_$type";
        $returnData = $redis->hCache($cacheKey, $rowCacheKey);
        if (is_array($returnData) && empty($returnData)) {
            return $this->jsonReturn(20002, $returnData, 'No data');
        }
        $userFields = ['uid', 'loginname', 'name', 'nativename', 'sex', 'phone', 'mobile', 'imgpath', 'im_id', 'Department'];
        if (!$returnData) {
            $ex = 60 * 3;
            $userAlias = 'u';
            $fields_user = $TripsService->buildUserFields($userAlias, $userFields);
            $fields = 't.id, t.trip_id, t.user_type, t.comefrom, t.line_id, t.plate, t.seat_count, t.status, t.time, t.create_time, t.time_offset';
            $fields .= ', t.line_type, t.start_name, t.end_name, t.extra_info ';
            $fields .=  ',' .$fields_user;
            $map  = [
                ['t.status', 'in', [0,1]],
                ['t.is_delete', '=', Db::raw(0)],
                ['t.uid', '=', $uid],
                ["t.time", ">", date('Y-m-d H:i:s', strtotime('-60 minute'))],
            ];
            if (is_numeric($type)) {
                if ($type > -1) {
                    $map[] = ['t.line_type', '=', $type];
                } elseif ($type == -2) {
                    $map[] = ['t.line_type', 'in', [1,2]];
                } elseif ($type = -1) {
                    $map[] = ['t.line_type', 'in', [0,1,2]];
                } else {
                    $type = $Utils->stringSetToArray($type);
                    if (!empty($type)) {
                        $map[] = ['t.line_type', 'in', $type];
                    }
                }
            }
            $join = [
                ["user {$userAlias}", "t.uid = {$userAlias}.uid", 'left'],
            ];
            $ctor = $ShuttleTrip->alias('t')->field($fields)->join($join)->where($map)->order('t.time ASC');
            $returnData = $this->getListDataByCtor($ctor, $pagesize);

            if (empty($returnData['lists'])) {
                $redis->hCache($cacheKey, $rowCacheKey, [], $ex);
                return $this->jsonReturn(20002, 'No data');
            }
            $redis->hCache($cacheKey, $rowCacheKey, $returnData, $ex);
        }
        $lists = $returnData['lists'] ?? [];
        $lists = $ShuttleTripService->formatTimeFields($lists, 'list', ['time','create_time']);
        $newList = [];
        foreach ($lists as $key => $value) {
            $value['have_started'] = $TripsMixed->haveStartedCode($value['time'], $value['time_offset']);
            if ($value['user_type'] == 1) { // 如果是司机行程
                if ($isShowMember) {
                    $resPassengers = $ShuttleTripService->passengers($value['id'], $userFields, ['id','status'], 0);
                    $value['passengers'] = $resPassengers ?: [];
                    $value['took_count'] = count($value['passengers']);
                } else {
                    $value['took_count'] = $ShuttleTrip->countPassengers($value['id']);
                }
                // 如果行程已出发，而且无乘客，跳过这些行程
                if (empty($value['took_count']) && $value['have_started'] > 2) {
                    continue;
                }
            } else { // 如果是乘客行程
                // 如果行程已出发，且无司机，跳过这些行程
                if ($value['trip_id'] == 0 && $value['have_started'] > 2) {
                    continue;
                }
                if ($isShowMember) {
                    $tripFields = ['id','status','user_type','comefrom', 'plate', 'seat_count'];
                    $value['driver'] = $value['trip_id'] > 0 ? ($ShuttleTripService->getUserTripDetail($value['trip_id'], $userFields, $tripFields, 0) ?: null) : null;
                }
            }

            // 组合路线字段
            $value = $ShuttleTrip->packLineDataFromTripData($value, null, ['name', 'longitude', 'latitude']);
            unset($value['extra_info']);
            $newList[] = $value;
        }
        $returnData['lists'] = $newList;
        return $this->jsonReturn(0, $returnData, 'Successful');
    }


    /**
     * 我的历史行程
     *
     * @param integer $page 页码
     * @param integer $pagesize 每页多少条;
     * @return void
     */
    public function history($page = 1, $pagesize = 20)
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $Utils = new Utils();
        $redis = new RedisData();
        $ShuttleTrip = new ShuttleTrip();
        $ShuttleTripService = new ShuttleTripService();
        $TripsMixed = new TripsMixed();
        $cacheKey = $ShuttleTrip->getMyListCacheKey($uid, 'history');
        $rowCacheKey = "pz_{$pagesize},page_$page";
        $returnData = $redis->hCache($cacheKey, $rowCacheKey);
        if (is_array($returnData) && empty($returnData)) {
            return $this->jsonReturn(20002, $returnData, lang('No data'));
        }
        if (!$returnData) {
            $ex = 60 * 5;
            $fields = 't.id, t.trip_id, t.user_type, t.comefrom, t.line_id, t.uid, t.status, t.time, t.create_time, t.time_offset';
            $fields .= ', t.line_type, t.start_name, t.end_name, t.extra_info ';
            $map  = [
                ['t.status', 'in', [-1,0,1,2,3,4,5]],
                ['t.is_delete', '=', Db::raw(0)],
                ['t.uid', '=', $uid],
                ["t.time", "<", date('Y-m-d H:i:s', strtotime('-60 minute'))],
            ];
            $join = [
                // ["t_shuttle_line l", "l.id = t.line_id", 'left'],
            ];
            $ctor = $ShuttleTrip->alias('t')->field($fields)->join($join)->where($map)->order('t.time DESC');
            $returnData = $this->getListDataByCtor($ctor, $pagesize);
            if (empty($returnData['lists'])) {
                $redis->hCache($cacheKey, $rowCacheKey, [], $ex);
                return $this->jsonReturn(20002, 'No data');
            }
            foreach ($returnData['lists'] as $key => $value) {
                $returnData['lists'][$key]['took_count'] = in_array($value['comefrom'], [1]) ? $ShuttleTrip->countPassengers($value['id']) : null;
            }
            $redis->hCache($cacheKey, $rowCacheKey, $returnData, $ex);
        }
        $returnData['lists'] = $ShuttleTripService->formatTimeFields($returnData['lists'], 'list', ['time','create_time','update_time']);
        foreach ($returnData['lists'] as $key => $value) {
            $returnData['lists'][$key]['have_started'] = $TripsMixed->haveStartedCode($value['time'], $value['time_offset']);
            // 组合路线字段
            $value = $ShuttleTrip->packLineDataFromTripData($value, null, ['name', 'longitude', 'latitude']);
            unset($value['extra_info']);
        }
        return $this->jsonReturn(0, $returnData, 'Successful');
    }


    /**
     * 取得乘客列表
     *
     * @param integer $id 行程id
     * @param array $userFields 要读出的用户字段
     * @param integer $returnType 返回数据 1:支持抛出json，2:以数组形式返回数据
     */
    public function passengers($id = 0)
    {
        if (!$id) {
            $this->jsonReturn(992, 'Error param');
        }
        $ShuttleTripService = new ShuttleTripService();
        $tripFields = ['id', 'time', 'create_time', 'status', 'comefrom', 'user_type'];
        $res = $ShuttleTripService->passengers($id, [], $tripFields) ?: [];
        $returnData = [
            'lists' => $res,
        ];
        if (empty($res)) {
            return $this->jsonReturn(20002, lang('No data'));
        }
        return $this->jsonReturn(0, $returnData, 'Successful');
    }

    /**
     * 取得详情
     *
     * @param integer $id 行程id
     * @param integer $show_line 是否返回路线数据
     * @param integer $show_member 是否返回对方参与者的用户数据
     */
    public function show($id = 0, $show_line = 1, $show_member = 2, $show_gps = 0)
    {
        if (!$id) {
            return $this->jsonReturn(992, lang('Error Param'));
        }
        $ShuttleTripServ = new ShuttleTripService();
        
        $data  = $ShuttleTripServ->getUserTripDetail($id, [], [], $show_line);
        if (!$data) {
            return $this->jsonReturn(20002, 'No data');
        }
        $trip_id = $data['trip_id'];

        if ($show_gps) {
            $ShuttleTripGps = new ShuttleTripGps();
            $gpsRes = $ShuttleTripGps->getGpsByTripAndUid($id, $data['u_uid']);
            $data['gps'] = $gpsRes['gps'] ?? null;
        }

        if ($show_member) {
            if ($data['user_type'] == 1) {
                if ($show_member == 2) {
                    $tripFields = ['id', 'time', 'create_time', 'status', 'comefrom', 'user_type'];
                    $data['passengers'] = $ShuttleTripServ->passengers($id, [], $tripFields) ?: [];
                    $data['took_count'] = count($data['passengers']);
                } else {
                    $ShuttleTrip = new ShuttleTrip();
                    $data['took_count'] = $ShuttleTrip->countPassengers($id);
                }
            } else {
                $ShuttleTripPartner = new ShuttleTripPartner();
                $data['partners'] = $ShuttleTripPartner->getPartners($id, null) ?? [];
                $data['driver'] = $ShuttleTripServ->getUserTripDetail($trip_id, [], [], 0) ?: null;
                if ($show_gps) {
                    $gpsRes = isset($data['driver']['id']) ? $ShuttleTripGps->getGpsByTripAndUid($data['driver']['id'], $data['driver']['u_uid']) : null;
                    $data['driver']['gps'] = $gpsRes['gps'] ?? null;
                }
            }
        }

        $TripsMixed = new TripsMixed();
        $data['have_started'] = $TripsMixed->haveStartedCode($data['time']);

        unset($data['trip_id']);
        return $this->jsonReturn(0, $data, 'Successful');
    }


    /**
     * 发布一个需求或行程, 或搭车
     *
     */
    public function save($rqData = null)
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $ShuttleTripService = new ShuttleTripService();
        $rqData = $ShuttleTripService->getRqData($rqData);


        // 加锁
        $ShuttleTripModel = new ShuttleTrip();
        $lockKeyFill = "u_{$uid}:save";
        if (!$ShuttleTripModel->lockItem(0, $lockKeyFill, 5, 1)) {
            return $this->jsonReturn(30006, lang('Please do not repeat the operation'));
        }
        // 验证
        $ShuttleTripVali = new ShuttleTripVali();
        if (!$ShuttleTripVali->checkSave($rqData)) {
            $errorData = $ShuttleTripVali->getError();
            $ShuttleTripModel->unlockItem(0, $lockKeyFill); // 解锁
            return $this->jsonReturn($errorData['code'] ?? -1, $errorData['data'] ?? [], $errorData['msg'] ?? 'Error check');
        }

        $rqData['line_data'] = $ShuttleTripService->getLineDataByRq($rqData);
        if (!$rqData['line_data']) {
            $ShuttleTripModel->unlockItem(0, $lockKeyFill); // 解锁
            $errorData = $ShuttleTripService->getError();
            return $this->jsonReturn($errorData['code'] ?? -1, $errorData['data'] ?? [], $errorData['msg'] ?? 'Error line data');
        }

        // 如果是约车需求，要处理同行者
        if ($rqData['create_type'] == 'requests') {
            $rqData['partners'] = input('post.partners');
            $PartnerServ = new ShuttlePartnerService();
            $partners = $PartnerServ->getPartnersUserData($rqData['partners'], $userData['uid']);
            $ShuttlePartnerVali = new ShuttlePartnerVali();
            if (!$ShuttlePartnerVali->checkRepetition($partners, $rqData['time'])) {
                $errorData = $ShuttlePartnerVali->getError();
                $ShuttleTripModel->unlockItem(0, $lockKeyFill); // 解锁
                return $this->jsonReturn($errorData['code'] ?? -1, $errorData['data'] ?? [], $errorData['msg'] ?? 'Error check');
            }
            $rqData['seat_count'] = count($partners) + 1;
        }

        Db::connect('database_carpool')->startTrans();
        try {
            // 入库
            $addRes = $ShuttleTripService->addTrip($rqData, $userData);
            $tripData = [
                'id' => $addRes,
                'uid' => $userData['uid'],
                'line_type' => $rqData['line_data']['type'],
                'time' => date('Y-m-d H:i:s', $rqData['time']),
                'map_type' => $rqData['map_type'],
            ];
            // 处理途经点
            if (isset($rqData['line_data']['waypoints']) && is_array($rqData['line_data']['waypoints'])) {
                $waypoinRes = (new ShuttleTripWaypoint())->insertPoints($rqData['line_data']['waypoints'], $tripData);
            }
            // 如果是约车需求，并有同行者，处理同行者
            if ($rqData['create_type'] == 'requests' && isset($partners) && count($partners) > 0) {
                $addPartnerRes = $PartnerServ->insertPartners($partners, $tripData);
            }
            // 提交事务
            Db::connect('database_carpool')->commit();
        } catch (\Exception $e) {
            Db::connect('database_carpool')->rollback();
            $errorMsg = $e->getMessage();
            $ShuttleTripModel->unlockItem(0, $lockKeyFill); // 解锁
            return $this->jsonReturn(-1, null, lang('Failed'), ['errorMsg'=>$errorMsg]);
        }
        $ShuttleTripModel->unlockItem(0, $lockKeyFill); // 解锁
        if (!$addRes) {
            $errorData = $ShuttleTripService->getError();
            return $this->jsonReturn($errorData['code'], $errorData['data'], $errorData['msg']);
        }
        if (isset($addPartnerRes) && is_array($addPartnerRes)) {
            $PartnerServ->doAfterAddPartners($addPartnerRes, $tripData, $userData);
        }
        return $this->jsonReturn(0, ['id'=>$addRes], 'Successful');
    }
    
    /**
     * 乘客搭车
     */
    public function hitchhiking($id)
    {
        $userData = $this->getUserData(1);

        $ShuttleTripService = new ShuttleTripService();
        $ShuttleTripModel = new ShuttleTrip();
        $TripsMixed = new TripsMixed();
        $rqData = $ShuttleTripService->getRqData();
        $rqData['trip_id'] = $id;
        $rqData['create_type'] = 'hitchhiking';
        // 加锁
        if (!$ShuttleTripModel->lockItem($id, 'hitchhiking')) {
            return $this->jsonReturn(20009, lang('The network is busy, please try again later'));
        }
        // 处理有没有要同时被取消的行程
        $cancel_id = input('post.cancel_id/d', 0);
        $cancel_id_from = input('post.cancel_id_from');
        if (!in_array($cancel_id_from, ['shuttle_trip', 'wall', 'info'])) {
            $cancel_id = 0;
        }
        if ($cancel_id > 0) {
            $cancelTripData = $TripsMixed->getTripItem($cancel_id, $cancel_id_from);
            if (!$TripsMixed->checkBeforeCancel($cancelTripData, $cancel_id_from, $userData)) {
                $errorData = $TripsMixed->getError();
                return $this->jsonReturn($errorData['code'] ?? -1, $errorData['data'] ?? [], $errorData['msg'] ?? 'Error check');
            }
        }
        //取得司机行
        $tripData = $ShuttleTripModel->getItem($id);
        // 验证
        $ShuttleTripVali = new ShuttleTripVali();
        if (!$ShuttleTripVali->checkHitchhiking($tripData, $userData)) {
            $errorData = $ShuttleTripVali->getError();
            $ShuttleTripModel->unlockItem($id, 'hitchhiking'); // 解锁
            return $this->jsonReturn($errorData['code'] ?? -1, $errorData['data'] ?? [], $errorData['msg'] ?? 'Error check');
        }

        $rqData['time'] = strtotime($tripData['time']);
        $rqData['line_id'] = $tripData['line_id'];
        $rqData['line_data'] = $ShuttleTripService->getExtraInfoLineData($id, 2);
        if (!$rqData['line_data']) {
            $ShuttleTripModel->unlockItem($id, 'hitchhiking'); // 解锁
            return $this->jsonReturn(20002, lang('The route does not exist'));
        }

        // 入库
        Db::connect('database_carpool')->startTrans();
        try {
            $checkRept = 1;
            if ($cancel_id > 0) { // 如果有行程要取消
                $cancelRes = $TripsMixed->cancelTrip($cancelTripData, $cancel_id_from, $userData, 1);
                $timeDiff = $rqData['time'] - $tripData['time_x'];
                $checkRept = $cancelRes && ($timeDiff >= - 30 * 15 && $timeDiff <= 30 * 15) ? 0 : $checkRept;
                if ($cancelRes) {
                    $errorData = $TripsMixed->getError();
                    $pushDatas = $errorData['data'] ?? null;
                }
            }
            $addRes = $ShuttleTripService->addTrip($rqData, $userData, $checkRept);
            // 提交事务
            Db::connect('database_carpool')->commit();
        } catch (\Exception $e) {
            Db::connect('database_carpool')->rollback();
            $ShuttleTripModel->unlockItem($id, 'hitchhiking'); // 解锁
            $errorMsg = $e->getMessage();
            $err = $ShuttleTripService->getError();
            return $this->jsonReturn($err['code'] ?? -1, $err['data'] ?? null, $err['msg'] ?? lang('Failed'), ['errorMsg'=>$errorMsg]);
        }
        $ShuttleTripModel->unlockItem($id, 'hitchhiking'); // 解锁
        if (!$addRes) {
            $errorData = $ShuttleTripService->getError();
            $this->jsonReturn($errorData['code'], $errorData['data'], lang('Failed').'. '.$errorData['msg']);
        }
        // 清缓存
        $TripsMixed->delUpGpsInfoidCache([$userData['uid'], $tripData['uid']]); // 清除是否要上传GPS接口缓存
        // 推消息
        $TripsPushMsg = new TripsPushMsg();
        $pushMsgData = [
            'from' => 'shuttle_trip',
            'runType' => 'hitchhiking',
            'userData'=> $userData,
            'tripData'=> $tripData,
            'id' => $id,
        ];
        $targetUserid = $tripData['uid'];
        $TripsPushMsg->pushMsg($targetUserid, $pushMsgData);
        // 如果有要取消的行程，进行取消后推送
        if ($cancel_id > 0 && isset($pushDatas) && $pushDatas && isset($pushDatas['pushMsgData'])) {
            $TripsPushMsg->pushMsg($pushDatas['pushTargetUid'] ?? null, $pushDatas['pushMsgData']);
        }
        // ok
        return $this->jsonReturn(0, ['id'=>$addRes], 'Successful');
    }

    /**
     * 司机接客
     */
    public function pickup($id)
    {
        $userData = $this->getUserData(1);

        $ShuttleTripService = new ShuttleTripService();
        $ShuttleTripModel = new ShuttleTrip();
        $TripsMixed = new TripsMixed();
        $rqData = $ShuttleTripService->getRqData();
        $rqData['create_type'] = 'pickup';

        // 加锁
        if (!$ShuttleTripModel->lockItem($id, 'pickup')) {
            return $this->jsonReturn(20009, lang('The network is busy, please try again later'));
        }
        // 处理有没有要同时被取消的行程
        $cancel_id = input('post.cancel_id/d', 0);
        $cancel_id_from = input('post.cancel_id_from');
        if (!in_array($cancel_id_from, ['shuttle_trip', 'wall', 'info'])) {
            $cancel_id = 0;
        }
        if ($cancel_id > 0) {
            $cancelTripData = $TripsMixed->getTripItem($cancel_id, $cancel_id_from);
            if (!$TripsMixed->checkBeforeCancel($cancelTripData, $cancel_id_from, $userData)) {
                $errorData = $TripsMixed->getError();
                return $this->jsonReturn($errorData['code'] ?? -1, $errorData['data'] ?? [], $errorData['msg'] ?? 'Error check');
            }
        }

        //取得乘客须求行程
        $tripData = $ShuttleTripModel->getItem($id);
        // 验证
        $ShuttleTripVali = new ShuttleTripVali();
        if (!$ShuttleTripVali->checkPickup($tripData, $userData)) {
            $errorData = $ShuttleTripVali->getError();
            $ShuttleTripModel->unlockItem($id, 'pickup'); // 解锁
            return $this->jsonReturn($errorData['code'] ?? -1, $errorData['data'] ?? [], $errorData['msg'] ?? 'Error check');
        }

        $rqData['seat_count'] = $tripData['seat_count'] > $rqData['seat_count'] ? $tripData['seat_count'] : $rqData['seat_count'];
        $rqData['time'] = strtotime($tripData['time']);
        $rqData['line_id'] = $tripData['line_id'];
        $rqData['line_data'] = $ShuttleTripService->getExtraInfoLineData($id, 2);
        
        if (!$rqData['line_data']) {
            $ShuttleTripModel->unlockItem($id, 'pickup'); // 解锁
            return $this->jsonReturn(20002, lang('The route does not exist'));
        }

        Db::connect('database_carpool')->startTrans();
        try {
            $checkRept = 1;
            if ($cancel_id > 0) { // 如果有行程要取消
                $cancelRes = $TripsMixed->cancelTrip($cancelTripData, $cancel_id_from, $userData, 1);
                $timeDiff = $rqData['time'] - $tripData['time_x'];
                $checkRept = $cancelRes && ($timeDiff >= - 30 * 15 && $timeDiff <= 30 * 15) ? 0 : $checkRept;
                if ($cancelRes) {
                    $errorData = $TripsMixed->getError();
                    $pushDatas = $errorData['data'] ?? null;
                }
            }
            // 入库
            $driverTripId = $ShuttleTripService->addTrip($rqData, $userData); // 添加一条司机行程
            if (!$driverTripId) {
                throw new \Exception(lang('Adding driver data failed'));
            }
            $ShuttleTripModel->where('id', $id)->update(['trip_id'=>$driverTripId]); // 乘客行程的trip_id设为司机行程id

            // 查询有没有同行者, 有的话把同行者也带上
            if ($tripData['seat_count'] > 1) {
                $ShuttleTripPartner = new ShuttleTripPartner();
                $partners = $ShuttleTripPartner->getPartners($tripData['id'], null) ?? [];
                $ShuttlePartnerServ = new ShuttlePartnerService();
                $rqTripData = $tripData;
                $rqTripData['line_data'] = $rqData['line_data'];
                $ShuttlePartnerServ->getOnCar($rqTripData, $driverTripId);
            }
            // 提交事务
            Db::connect('database_carpool')->commit();
        } catch (\Exception $e) {
            Db::connect('database_carpool')->rollback();
            $ShuttleTripModel->unlockItem($id, 'pickup'); // 解锁
            $errorMsg = $e->getMessage();
            $err = $ShuttleTripService->getError();
            return $this->jsonReturn($err['code'] ?? -1, $err['data'] ?? null, $err['msg'] ?? lang('Failed'), ['errorMsg'=>$errorMsg]);
        }
        $ShuttleTripModel->unlockItem($id, 'pickup'); // 解锁
        // 消缓存
        $ShuttleTripModel->delItemCache($id); // 消单项行程缓存
        $ShuttleTripModel->delListCache($rqData['line_id']); // 取消该路线的空座位和约车需求列表
        $TripsMixed->delUpGpsInfoidCache([$userData['uid'], $tripData['uid']]); // 清除是否要上传GPS接口缓存
        // 推消息
        $TripsPushMsg = new TripsPushMsg();
        $pushMsgData = [
            'from' => 'shuttle_trip',
            'runType' => 'pickup',
            'userData'=> $userData,
            'tripData'=> $tripData,
            'id' => $id,
        ];
        $targetUserid = $tripData['uid'];
        $TripsPushMsg->pushMsg($targetUserid, $pushMsgData);
        // 如果有要取消的行程，进行取消后推送
        if ($cancel_id > 0 && isset($pushDatas) && $pushDatas && isset($pushDatas['pushMsgData'])) {
            $TripsPushMsg->pushMsg($pushDatas['pushTargetUid'] ?? null, $pushDatas['pushMsgData']);
        }
        // 入库成功后清理这些同行者的相关缓存，及推送消息
        if (isset($partners) && count($partners) > 0) {
            $driverTripData = [
                'id' => $driverTripId,
                'uid' => $userData['uid']
            ];
            $ShuttlePartnerServ->doAfterGetOnCar($partners, $driverTripData, $userData);
        }
        return $this->jsonReturn(0, ['id'=>$driverTripId], 'Successful');
    }

    /**
     * 修改内容 (取消，完结，改变座位数)
     *
     */
    public function change($id = 0)
    {
        if (!$id) {
            return $this->jsonReturn(992, lang('Error Param'));
        }

        $run = input('post.run') ?: input('param.run') ;
        if (!in_array($run, ['cancel', 'change_seat', 'change_plate'])) { // 不再支持完结
            return $this->jsonReturn(992, lang('Error Param'));
        }
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $ShuttleTripModel = new ShuttleTrip();
        $tripData = $ShuttleTripModel->getItem($id);
        if (empty($tripData)) {
            return $this->jsonReturn(20002, lang('The trip does not exist'));
        }
        
        $ShuttleTripService = new ShuttleTripService();
        if ($run === 'cancel') {
            $res = $ShuttleTripService->cancel($tripData, $userData);
        } elseif ($run === 'finish') {
            $res = $ShuttleTripService->finish($tripData, $userData);
        } else {
            $ShuttleTripVali = new ShuttleTripVali();
            if ($run === 'change_seat') { // 改变空座位数
                $seat_count = input('post.seat_count/d', 0);
                $upData = [
                    'seat_count' => $seat_count,
                ];
                $checkRes = $ShuttleTripVali->checkChangeSeat($upData, $tripData, $userData['uid']);
            } elseif ($run === 'change_plate') { // 改变空座位数
                $plate = input('post.plate');
                $upData = [
                    'plate' => $plate,
                ];
                $checkRes = $ShuttleTripVali->checkChangePlate($upData, $tripData, $userData['uid']);
            } else {
                return $this->jsonReturn(992, lang('Error Param'));
            }
            if (!$checkRes) {
                $errorData = $ShuttleTripVali->getError();
                return $this->jsonReturn($errorData['code'] ?? -1, $errorData['data'] ?? [], $errorData['msg'] ?? 'Error check');
            }
            $res = $ShuttleTripModel->where('id', $id)->update($upData);
        }

        if ($res === false) {
            if (in_array($run, ['cancel', 'finish'])) {
                $errorData = $ShuttleTripService->getError();
                return $this->jsonReturn($errorData['code'] ?? -1, $errorData['msg'] ?? 'Failed');
            } else {
                return $this->jsonReturn(-1, lang('Failed'));
            }
        }
        if (in_array($run, ['change_seat', 'change_plate'])) {
            $ShuttleTripModel->delItemCache($id); // 消单项行程缓存
            $ShuttleTripModel->delMyListCache($uid, 'my');
            $ShuttleTripModel->delListCache($tripData['line_id']);
        }
        return $this->jsonReturn(0, 'Successful');
    }


    /**
     * 通过行程id匹配行程列表
     */
    public function matching($id)
    {
        $returnData = [];
        $ShuttleTripService = new ShuttleTripService();
        $ShuttleTripModel = new ShuttleTrip();
        $userData = $this->getUserData(1);
        $uid = intval($userData['uid']);

        // trip data
        $tripData = $ShuttleTripModel->getItem($id);
        if (!$tripData) {
            return $this->jsonReturn(20002, lang('The route does not exist'));
        }
        
        $tripData = $ShuttleTripModel->packLineDataFromTripData($tripData, null, ['name', 'longitude', 'latitude']);
        $tripData = TripsMixed::getInstance()->formatResultValue($tripData, ['extra_info']);
        if ($tripData['user_type'] == 1) { // 如果是司机行程，查出乘客
            $passengers = $ShuttleTripModel->passengers($id);
            $tripData['took_count'] = count($passengers) ?: 0;
        } else { // 如果乘客行程，取UID
            $passengerUid = $tripData['uid'];
        }
        $fields = [
            'id', 'user_type', 'trip_id', 'line_type', 'uid', 'time', 'time_offset', 'status', 'comefrom',  'plate',
            'seat_count', 'took_count', 'line_data'
        ];
        $returnData['detail'] = Utils::getInstance()->filterDataFields($tripData, $fields);
        $returnData['lists'] = [];
        if (!$this->lockAction(['ex'=>5, 'runCount'=>150, 'sleep'=>40 * 1000])) { // 添加并发锁
            return $this->jsonReturn(20009, $returnData, lang('The network is busy, please try again later'));
        }

        $list = $ShuttleTripService->getSimilarTrips($tripData, -1*$uid, null, 0, 1) ?: [];
        
        $newList = [];
        foreach ($list as $key => $value) {
            if ($value['user_type'] == 1) { // 如果对方行程是司机行程
                $passengers = $ShuttleTripModel->passengers($id);
                $value['took_count'] = count($passengers) ?: 0;
                // $value['took_count'] = $ShuttleTripModel->countPassengers($value['id']);
            } else {
                $passengerUid = $value['u_uid'];
            }
            // 检查如果乘客已经是司机行程的成员之一，排除这些行程
            $inPassRes = $ShuttleTripModel->inPassengers($passengerUid, $passengers);
            if ($inPassRes) {
                continue;
            }
            $newList[] = $value;
        }
        $returnData['lists'] = $newList;
        $this->unlockAction();
        return $this->jsonReturn(0, $returnData, 'Successful');
    }

    /**
     * 合并行程
     */
    public function merge($id = null)
    {
        $id = $id ?? input('post.id/d', 0); // 自己的行程id
        $tid = input('post.tid/d', 0); // 对方的行程id

        if (!$tid || !$id) {
            return $this->jsonReturn(992, lang('Empty param'));
        }
        $userData = $this->getUserData(1);

        $ShuttleTripModel = new ShuttleTrip();
        $tripData = $ShuttleTripModel->getItem($id);
        $targetTripData = $ShuttleTripModel->getItem($tid);

        $driverTripId = $tripData['user_type'] == 1 ? $id : $tid;
        $passengerTripId = $tripData['user_type'] == 1 ? $tid : $id;
        $driverTripData = $tripData['user_type'] == 1 ? $tripData : $targetTripData;
        $passengerTripData = $tripData['user_type'] == 1 ?  $targetTripData : $tripData;

        // 锁定对方资源
        $lockKeyFill_d = 'hitchhiking'; //锁自已 如果自己是司机，就锁hitchhiking, 否则锁pickup
        $lockKeyFill_p = 'pickup'; //锁对方 如果自己是司机，就锁pickup, 否则锁hitchhiking
        if (!$ShuttleTripModel->lockItem($driverTripId, $lockKeyFill_d) || !$ShuttleTripModel->lockItem($passengerTripId, $lockKeyFill_p)) {
            return $this->jsonReturn(20009, lang('The network is busy, please try again later'));
        }
        
        // 验证
        $ShuttleTripVali = new ShuttleTripVali();
        if (!$ShuttleTripVali->checkMerge($tripData, $targetTripData, $userData)) {
            $errorData = $ShuttleTripVali->getError();
            $ShuttleTripModel->unlockItem($driverTripId, $lockKeyFill_d); // 解锁司机行程
            $ShuttleTripModel->unlockItem($passengerTripId, $lockKeyFill_p); // 解锁乘客行程
            return $this->jsonReturn($errorData['code'] ?? -1, $errorData['data'] ?? [], $errorData['msg'] ?? 'Error check');
        }

        
        $driverUserData = $tripData['user_type'] == 1 ? $userData : (new User())->getItem($targetTripData['uid']);

        Db::connect('database_carpool')->startTrans();
        try {
            // 乘客行程的trip_id设为司机行程id
            $ShuttleTripModel->where('id', $passengerTripId)->update(['trip_id'=>$driverTripId]);
            // 处理同行者
            if ($passengerTripData['seat_count'] > 1) {
                $ShuttleTripPartner = new ShuttleTripPartner();
                $partners = $ShuttleTripPartner->getPartners($tripData['id'], null) ?? [];
                $ShuttlePartnerServ = new ShuttlePartnerService();
                $ShuttlePartnerServ->getOnCar($passengerTripData, $driverTripData);
            }
            Db::connect('database_carpool')->commit();
        } catch (\Exception $e) {
            Db::connect('database_carpool')->rollback();
            $errorMsg = $e->getMessage();
            $ShuttleTripModel->unlockItem($driverTripId, $lockKeyFill_d); // 解锁司机行程
            $ShuttleTripModel->unlockItem($passengerTripId, $lockKeyFill_p); // 解锁乘客行程
            return $this->jsonReturn(-1, null, lang('Failed'), ['errorMsg'=>$errorMsg]);
        }
        $ShuttleTripModel->unlockItem($driverTripId, $lockKeyFill_d); // 解锁司机行程
        $ShuttleTripModel->unlockItem($passengerTripId, $lockKeyFill_p); // 解锁乘客行程
        // 合并后清理自己和对方的缓存及推消息给对方
        $ShuttleTripServ = new ShuttleTripService();
        $ShuttleTripServ->doAfterMerge($driverTripData, $passengerTripData, $userData);
        // 入库成功后清理这些同行者的相关缓存，及推送消息
        if (isset($partners) && count($partners) > 0) {
            $runType = $tripData['user_type'] == 1 ? 'pickup' : 'hitchhiking';
            $ShuttlePartnerServ->doAfterGetOnCar($partners, $driverTripData, $driverUserData, $runType);
        }
        return $this->jsonReturn(0, 'Successful');
    }
}
