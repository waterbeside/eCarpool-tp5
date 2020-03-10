<?php
namespace app\carpool\service\shuttle;

use app\common\service\Service;
use app\carpool\model\User as UserModel;
use app\carpool\model\ShuttleLine as ShuttleLineModel;
use app\carpool\model\ShuttleTrip as ShuttleTripModel;
use app\carpool\model\Address as AddressModel;
use app\carpool\service\Trips as TripsService;
use app\carpool\service\TripsList as TripsListService;
use app\carpool\service\TripsMixed as TripsMixedService;
use app\carpool\service\shuttle\Partner as PartnerService;
use app\carpool\service\TripsPushMsg;
use app\carpool\model\ShuttleLineRelation;
use app\carpool\model\ShuttleTripPartner;
use app\user\model\Department;
use my\RedisData;
use my\Utils;
use think\Db;

class Trip extends Service
{

    public $defaultUserFields = [
        'uid', 'loginname', 'name','nativename', 'phone', 'mobile', 'Department', 'sex',
        'company_id', 'department_id', 'companyname', 'imgpath', 'carcolor', 'im_id'
    ];

    /**
     * 取得请求时间
     *
     * @param array $rqData 请求的数据
     * @return array
     */
    public function getRqData($rqData = null)
    {
        $Utils = Utils::getInstance();
        $rqData = $rqData ?: [];
        $rqData['create_type'] = $rqData['create_type'] ?? input('post.create_type');
        $rqData['line_id'] = $rqData['line_id'] ?? input('post.line_id/d', 0);
        $rqData['line_type'] = $rqData['line_type'] ?? input('post.line_type/d', 0);
        $rqData['trip_id'] = $rqData['trip_id'] ?? input('post.trip_id/d', 0);
        $rqData['seat_count'] = $rqData['seat_count'] ?? input('post.seat_count/d', 0);
        $rqData['time'] = $rqData['time'] ?? input('post.time/d', 0);
        $rqData['time_offset'] = $rqData['time_offset'] ?? input('post.time_offset/d', 0);
        $rqData['map_type'] =  $rqData['map_type'] ?? input('post.map_type/d', 0);
        $rqData['start'] =  $rqData['start'] ?? input('post.start');
        $rqData['end'] =  $rqData['end'] ?? input('post.end');
        $rqData['waypoints'] =  $rqData['waypoints'] ?? input('post.waypoints');
        $rqData['start']       = is_array($rqData['start']) ? $rqData['start'] : $Utils->json2Array($rqData['start']);
        $rqData['end']         = is_array($rqData['end']) ? $rqData['end'] : $Utils->json2Array($rqData['end']);
        $rqData['waypoints']   = is_array($rqData['waypoints']) ? $rqData['waypoints'] : $Utils->json2Array($rqData['waypoints']);
        
        return $rqData;
    }

    /**
     * 验证添加行程时的时间
     */
    public function checkAddData($rqData, $uid, $checkRept = 1)
    {
        // 取得与 create_type 相关数据
        $createTypeData = $this->getCreateTypeInfo($rqData['create_type']);
        $rqData = array_merge($rqData, $createTypeData);


        $time = $rqData['time'] ?: null;
        if (empty($rqData['line_data'])) {
            return $this->error(992, lang('Please select a route'));
        }
        if (empty($time)) {
            return $this->error(992, lang('Please select a departure time'));
        }

        $create_type = $rqData['create_type'];
        // 司机至少发一个空座位。
        if ($create_type === 'cars' && (!isset($rqData['seat_count']) || $rqData['seat_count'] < 1)) {
            return $this->error(992, lang('The number of empty seats cannot be empty'));
        }
        if (in_array($create_type, ['pickup', 'requests'])) { // 发需接客，至少插一个空座位
            $rqData['seat_count'] = isset($rqData['seat_count']) && $rqData['seat_count'] > 1 ? $rqData['seat_count'] : 1;
        }

        if ($create_type == 'partner') { // 如果是同行者，直接跳过之后验证，因为只验证发布需求的人；
            return $rqData;
        }

        //检查出发时间是否已经过了 (乘车和搭客另外检查时间)
        if ($create_type != 'hitchhiking' && $create_type != 'pickup' && time() > $time) {
            return $this->error(992, lang("The departure time has passed. Please select the time again"));
        }
        // 验证重复行程
        if ($checkRept) {
            $TripsService = new TripsService();
            $repetitionList = $TripsService->getRepetition($time, $uid, 60 * 30, null, ['new']);
            if ($repetitionList) {
                $TripsMixedService = new TripsMixedService();
                // 为重复行程列表添加明细
                $repetitionList = $TripsMixedService->getMixedDetailListByRpList($repetitionList);
                $errorData = $TripsService->getError();
                // 检查有没有可以合并的行程
                $matchingList =  in_array($create_type, ['pickup', 'hitchhiking']) ? $this->getMatchingsByRplist($repetitionList, $rqData) : [];
                if (!empty($matchingList)) {
                    return $this->error(50008, lang('You have similar trips that can be merged'), ['lists'=>$repetitionList]);
                }
                $this->error($errorData['code'], $errorData['msg'], ['lists'=>$repetitionList]);
                return false;
            }
        }
        return $rqData;
    }

    /**
     * 取得由 create_type 得到的延申数据，如comefrom,userType等
     */
    public function getCreateTypeInfo($create_type)
    {
        // 添加设置数据
        if ($create_type === 'cars') { // 发布空座位
            return [
                'comefrom' => 1,
                'user_type' => 1,
                'trip_id' => 0,
            ];
        } elseif ($create_type === 'requests') { // 发布约车需求
            return [
                'comefrom' => 2,
                'user_type' => 0,
                'trip_id' => 0,
            ];
        } elseif ($create_type === 'hitchhiking') { // 乘客从空座位搭车
            return [
                'comefrom' => 3,
                'user_type' => 0,
                'seat_count' => 1,
            ];
        } elseif ($create_type === 'pickup') { // 司机从约车需求拉客
            return [
                'comefrom' => 1,
                'user_type' => 1,
            ];
        } elseif ($create_type === 'partner') { // 同行者上车
            return [
                'comefrom' => 4,
                'user_type' => 0,
                'seat_count' => 1,
            ];
        } else {
            return $this->error(992, lang('Error create_type'));
        }
    }

    /**
     * 发布行程
     *
     * @param array $rqData 请求参数
     * @param mixed $uidOrData uid or userData
     * @param integer,boolean $checkRept 是否验证重复行程
     * @return void
     */
    public function addTrip($rqData, $uidOrData, $checkRept = 1)
    {

        // 取得用户信息
        $userModel = new UserModel();
        $userData = is_numeric($uidOrData) ? $userModel->findByUid($uidOrData) : $uidOrData;
        $uid = $userData['uid'];

        // 验证字段合法性
        $rqData = $this->checkAddData($rqData, $uid, $checkRept);
        if (!$rqData) {
            return false;
        }
        $plate = $rqData['user_type'] == 1 ? $userData['carnumber'] : '';
        $trip_id = isset($rqData['trip_id']) && is_numeric($rqData['trip_id']) ? $rqData['trip_id'] : 0;

        $time_offset = isset($rqData['time_offset']) && is_numeric($rqData['time_offset']) ? $rqData['time_offset'] : 0;
        $time_offset = $time_offset > 60 * 60 ? 60 * 60 : ($time_offset < 0 ? 0 : $time_offset);
        // 创建入库数据
        $updata = [
            'line_id' => $rqData['line_id'] ?: 0,
            'time' => date('Y-m-d H:i:s', $rqData['time']),
            'time_offset' => $time_offset,
            'uid' => $uid,
            'user_type' => $rqData['user_type'],
            'comefrom' => $rqData['comefrom'],
            'trip_id' => $trip_id,
            'status' => 0,
            'seat_count' => intval($rqData['seat_count']),
            'department_id' => $userData['department_id'] ?: 0,
        ];
        if ($plate) {
            $updata['plate'] = $plate;
        }
        if (isset($rqData['line_data']) && is_array($rqData['line_data'])) {
            $updata['line_type'] = intval($rqData['line_data']['type']);
            $updata['extra_info'] = json_encode(['line_data' => $rqData['line_data']]);
        }
        $upLineData = [
            'start_id' => $rqData['line_data']['start_id'] ?? 0,
            'start_name' => $rqData['line_data']['start_name'] ?? '',
            'start_longitude' => $rqData['line_data']['start_longitude'] ?? 0,
            'start_latitude' => $rqData['line_data']['start_latitude'] ?? 0,
            'end_id' => $rqData['line_data']['end_id'] ?? 0,
            'end_name' => $rqData['line_data']['end_name'] ?? '',
            'end_longitude' => $rqData['line_data']['end_longitude'] ?? 0,
            'end_latitude' => $rqData['line_data']['end_latitude'] ?? 0,
        ];
        $updata = array_merge($updata, $upLineData);
        $ShuttleTripModel = new ShuttleTripModel();
        $newid = $ShuttleTripModel->insertGetId($updata);
        if (!$newid) {
            return $this->error(-1, lang('failed'));
        }
        // 清除缓存;
        $cData = [
            'create_type' => $rqData['create_type'],
            'line_id' => $rqData['line_id'],
            'id' => $newid,
            'trip_id' => $trip_id,
            'uid' => $uid,
            'myType' => 'my',
        ];
        $ShuttleTripModel->delCacheAfterAdd($cData); // 清除行程相关缓存
        (new ShuttleLineModel())->delCommonListCache($uid, $rqData['line_id']); // 清除路线相关的缓存
        TripsMixedService::getInstance()->delMyListCache($uid); // 清除我将要发生的行程缓存
        return $newid;
    }

    /**
     * 通过重复行程列表查出可合并行程的
     *
     * @param array $repetitionList 重复行程列表
     * @param array $rqData 添加行程时的请求数据
     * @return array
     */
    public function getMatchingsByRplist($repetitionList, $rqData)
    {
        if (empty($repetitionList) || !is_array($repetitionList)) {
            return [];
        }
        $matchingList = [];
        $matchUserType = $rqData['user_type'];
        $ShuttleTripModel = new ShuttleTripModel();
        foreach ($repetitionList as $key => $value) {
            $userType = $value['user_type'] ?? false;
            $line_id = $value['line_id'];
            $tripMatching = $value['from'] === 'shuttle_trip' && $userType === $matchUserType && $line_id == $rqData['line_id'] ? true : false;
            $userTypeMatching = $value['user_type'] > 0 || ($value['trip_id'] == 0 && $value['comefrom'] == 2 ) ? true : false;
            $timeMatch = $value['check_level']['k'] == 0 ? true : false;
            if ($tripMatching && $userTypeMatching && $timeMatch) {
                $value['took_count'] = $ShuttleTripModel->countPassengers($value['id']);
                $matchingList[] = $value;
            }
        }
        return $matchingList;
    }

    /**
     * 取得用于冗余进行程表的路线信息；
     *
     * @param integer $id line_id  or trip_id  or trip_data;
     * @param integer $type type=0时 id为路线id（line_id）, type = 1时id为行程id (trip_id), type = 2 时，先以type=1查，再以type=0查;
     * @return array
     */
    public function getExtraInfoLineData($idOrData, $type = 0, $filterWaypointField = null)
    {
        if ($type > 0) {
            $ShuttleTripModel = new ShuttleTripModel();
            $itemData = $ShuttleTripModel->getDataByIdOrData($idOrData);
            if (!$itemData) {
                return null;
            }
            try {
                $trip_info = json_decode($itemData['extra_info'], true);
                $lineData = $trip_info['line_data'];
            } catch (\Exception $e) {  //其他错误
                $lineData = null;
            }
            if ($lineData) {
                $lineData['start_longitude'] = floatval($lineData['start_longitude']);
                $lineData['start_latitude'] = floatval($lineData['start_latitude']);
                $lineData['end_longitude'] = floatval($lineData['end_longitude']);
                $lineData['end_latitude'] = floatval($lineData['end_latitude']);
                $lineData['waypoints'] = $lineData['waypoints'] ? $ShuttleTripModel->formatExtraInfoWaypointField($lineData['waypoints'], $filterWaypointField) : [];
            }
            if (!$lineData && $type == 2) {
                $lineData = $this->getExtraInfoLineData($itemData['line_id'], 0);
            }
        } else {
            $lineFields = 'id, start_id, start_name, start_longitude, start_latitude, end_id, end_name, end_longitude, end_latitude, map_type, type';
            $ShuttleLineModel = new ShuttleLineModel();
            $lineData = $ShuttleLineModel->getItem($idOrData, $lineFields);
            if ($lineData) {
                $lineData['start_type'] = $lineData['type'] == 1 ? 4 : ($lineData['type'] == 2 ? 3 : 0 );
                $lineData['end_type'] = $lineData['type'] == 1 ? 3 : ($lineData['type'] == 2 ? 4 : 0 );
            }
        }
        return $lineData;
    }

    /**
     * 通过行程取得line_data数据
     *
     * @param integer $idOrData trip id or trip_data;
     * @return array
     */
    public function getLineDataByTrip($idOrData, $filterField = null)
    {
        $filterFieldBase = ['start_id', 'start_name', 'start_longitude', 'start_latitude', 'end_id', 'end_name', 'end_longitude', 'end_latitude'];
        $filterField = !empty($filterField) && is_array($filterField) ? $filterField : array_merge($filterFieldBase, ['map_type']);
        $ShuttleTripModel = new ShuttleTripModel();
        $itemData = $ShuttleTripModel->getDataByIdOrData($idOrData);
        if (!$itemData) {
            return null;
        }
        $lineData =  Utils::getInstance()->filterDataFields($itemData, $filterFieldBase);
        $lineDataExtra = $this->getExtraInfoLineData($itemData, 1);
        foreach ($lineData as $key => $value) {
            $numberField = ['start_id', 'start_longitude', 'start_latitude', 'end_id', 'end_longitude', 'end_latitude'];
            $lineData[$key] = $value ?: ($lineDataExtra[$key] ?? (in_array($key, $numberField) ? 0 : ''));
        }
        $lineData['map_type'] = $lineDataExtra['map_type'];
        $lineData = Utils::getInstance()->filterDataFields($itemData, $filterField);
        return $lineData;
    }

    /**
     * 通过请求来的数据生成路线数据
     *
     * @param array $rqData 创建行程时请求来的数据
     * @param array $userData 操作用户的用户数据
     * @return array 返回路线字段信息
     */
    public function getLineDataByRq($rqData = null, $userData = null)
    {
        $AddressModel = new AddressModel();
        $createAddress = array();
        //处理起终点
        if (!$rqData['line_id']) {
            if ($rqData['map_type'] != 1) { // 如果是高德地图
                $createAddress = $AddressModel->createTripAddress($rqData, $userData);
                if ($createAddress === false) {
                    return $this->setError(992, $AddressModel->errorMsg);
                }
            } else { // 如果是谷歌地图
                $createAddress = [
                    'start' => $rqData['start'],
                    'end' => $rqData['end'],
                    'waypoints' => $rqData['waypoints'],
                ];
                $start_id = $createAddress['start']['addressid'] ?? 0;
                $start_id = $start_id > 0 ? $start_id : 0;
                $end_id = $createAddress['end']['addressid'] ?? 0;
                $end_id = $end_id > 0 ? $end_id : 0;
                $createAddress['start']['addressid'] = $start_id;
                $createAddress['end']['addressid'] = $end_id;
            }
            $startType = $createAddress['start']['address_type'] ?? 1;
            $endType = $createAddress['end']['address_type'] ?? 1;
            $lineType = 0;
            if ($startType == 3 && $endType != 3) { // 如果起点是公司
                $lineType = 2; // 下班
            } elseif ($startType != 3 &&  $endType == 3) { // 如果终点是公司
                $lineType = 1; // 上班
            }

            $returnData = [
                'id' => 0, // 没有line_id
                'start_id' => $createAddress['start']['addressid'] ?? 0,
                'start_name' => $createAddress['start']['addressname'],
                'start_longitude' => $createAddress['start']['longitude'],
                'start_latitude' => $createAddress['start']['latitude'],
                'start_type' => $createAddress['start']['address_type'],
                'end_id' => $createAddress['end']['addressid'] ?? 0,
                'end_name' => $createAddress['end']['addressname'],
                'end_longitude' => $createAddress['end']['longitude'],
                'end_latitude' => $createAddress['end']['latitude'],
                'end_type' => $createAddress['end']['address_type'],
                'map_type' => $rqData['map_type'] ?? 0,
                'waypoints' => $createAddress['waypoints'] ?? [],
                'type' => $lineType,
            ];
            $emptyStart = empty($returnData['start_name']) || (empty($returnData['start_longitude']) && empty($returnData['start_latitude'])) ? true : false;
            $emptyEnd = empty($returnData['end_name']) || (empty($returnData['end_longitude']) && empty($returnData['end_latitude'])) ? true : false;
            if ($emptyStart || $emptyEnd) {
                return $this->setError(992, lang('Error route line data'));
            }
        } elseif ($rqData['line_id'] > 0) {
            $returnData = $this->getExtraInfoLineData($rqData['line_id'], 0);
            if (empty($returnData)) {
                return $this->setError(20002, lang('The route does not exist'));
            }
        }
        return $returnData;
    }

    /**
     * 取得详情
     *
     * @param integer $id 行程id
     * @param array $userFields 要读出的用户字段
     * @param array $tripFields 要读出的行程字段
     * @param integer $show_line 是否显示路线信息
     */
    public function getUserTripDetail($id = 0, $userFields = [], $tripFields = [], $show_line = 1, $lineFields = [])
    {
        if (!$id) {
            return $this->error(992, lang('Error Param'));
        }
        $ShuttleTripModel = new ShuttleTripModel();
        $User = new UserModel();
        $TripsMixedService = new TripsMixedService();

        $itemData = $ShuttleTripModel->getItem($id);
        if (!$itemData) {
            return $this->error(20002, lang('No data'));
        }
        $uid = $itemData['uid'];

        $itemData = $this->formatTimeFields($itemData, 'item', ['time','create_time']);
        $tripFields = $tripFields ?: [
            'id', 'time', 'time_offset', 'create_time', 'status', 'user_type', 'comefrom', 'trip_id',
            'seat_count', 'line_type', 'plate',
        ];
        if ($show_line) {
            $lineFields = $lineFields ?: [
                'start_id', 'start_name', 'start_longitude', 'start_latitude',
                'end_id', 'end_name', 'end_longitude', 'end_latitude',
                'map_type', 'waypoints',
            ];
            $lineData = $ShuttleTripModel->getCommonLineData($itemData, null, ['addressid','name','longitude','latitude']) ?: [];
            $itemData= array_merge($itemData, $lineData);
            $tripFields = array_merge($tripFields, $lineFields);
        }

        $itemData = Utils::getInstance()->filterDataFields($itemData, $tripFields);
        $userFields = $userFields ?: $this->defaultUserFields;
        $userData = $User->findByUid($uid);
        $userData = $userData ? Utils::getInstance()->filterDataFields($userData, $userFields, false, 'u_', -1) : null;
        $userData = $TripsMixedService->formatResultValue($userData);
        $data = array_merge($itemData ?? [], $userData ?: []);
        return $data ?: null;
    }


    /**
     * 取得乘客列表
     *
     * @param integer $id 行程id
     * @param array $userFields 要读出的用户字段
     * @param integer $returnType 返回数据 1:支持抛出json，2:以数组形式返回数据
     * @param string $as 字段的补充前缀
     * @param integer $getFullDepartment 是否取得完整部门数据
     *
     * @return array
     */
    public function passengers($id = 0, $userFields = [], $tripFields = [], $as = 'u', $getFullDepartment = 0)
    {
        if (!$id) {
            $this->error(992, lang('Error Param'));
            return [];
        }
        $ShuttleTripModel = new ShuttleTripModel();
        $Utils = new Utils();
        $res = $ShuttleTripModel->passengers($id);
        if (empty($res)) {
            $this->error(20002, 'No data');
            return [];
        }
        $res = $this->formatTimeFields($res, 'list', ['time','create_time']);
        $tripFields = !empty($tripFields) && is_string($tripFields) ? array_map('trim', explode(',', $tripFields)) : $tripFields;
        if ($userFields !== false) {
            $userFields = $userFields ?: $this->defaultUserFields;
            $userFields = is_string($userFields) ? array_map('trim', explode(',', $userFields)) : $userFields;
            $UserModel = new UserModel();
            foreach ($res as $key => $value) {
                $userData = $UserModel->getItem($value['uid'], $userFields);
                $userData = $Utils->filterDataFields($userData, [], true, 'u_', 0);
                $res[$key] = array_merge($value, $userData);
            }
            $userFields_fill = $Utils->arrayAddString($userFields, 'u_', '', -1);
            $filterFields = array_merge($tripFields, $userFields_fill ?: []);
        } else {
            $filterFields = $tripFields;
        }
        $res = $Utils->formatFieldType($res, ["{$as}_sex"=>'int', "{$as}_company_id"=>'int'], 'list');
        $res = $Utils->filterListFields($res, $filterFields, false, '', -1);
        return $res ?: [];
    }

    /**
     * 取得相似行程
     *
     * @param mixed $tripData 行程数据
     * @param integer $time 时间
     * @param integer $userType  0=匹配乘客行程，1=匹配司机行程
     * @param integer $uid  大于0时=要查找的用户id的行程，小于0时=要排除的用户id的行程
     * @param mixed $timeOffset  时间前后偏移，格式为[10,10]数组。 当为单个数字时，自动变成[数字,数字]
     * @param integer $radius  起终点半径距离范围
     * @return array
     */
    public function getSimilarTrips($tripData, $time = 0, $userType = false, $uid = 0, $timeOffset = [60*10, 60*10], $radius = null)
    {
        $lineId = $tripData['line_id'] ?? 0;
        $startId = $tripData['start_id'] ?? 0;
        $endId = $tripData['end_id'] ?? 0;
        $startLng = $tripData['start_longitude'] ?? 0;
        $startLat = $tripData['start_latitude'] ?? 0;
        $endLng = $tripData['end_longitude'] ?? 0;
        $endLat = $tripData['end_latitude'] ?? 0;
        $radius = $radius ?: (config('trips.trip_matching_radius') ?? 200);
        $Utils = new Utils();
        if (empty($lineId) && (!$startLng && !$startLat && !$endLng && !$endLat)) {
            return [];
        }
        $tripFields = ['id', 'time', 'time_offset', 'create_time', 'status', 'user_type', 'comefrom','line_id', 'seat_count', 'extra_info'];
        $userFields =[
            'uid','loginname','name','nativename','phone','mobile','Department',
            'sex','company_id','department_id','imgpath','carcolor', 'im_id'
        ];
        $fieldsStr = implode(',', $Utils->arrayAddString($tripFields, 't.') ?: []);
        // 设置查询时间范围
        $time = $time ?: time();
        if (is_numeric($timeOffset)) {
            $timeOffset = [$timeOffset, $timeOffset];
        }
        // $start_time = $time - $timeOffset[0];
        // $end_time = $time + $timeOffset[1];
        $start_time = $time - 60 * 60;
        $end_time = $time + 60 * 60;
        // 查询有没有关系路线，有的话也把这些路线作为条件查询
        
        if ($lineId > 0) {
            $friendLines = (new ShuttleLineRelation())->getFriendsLine($lineId, 0, 0) ?: [];
            $lineMap = count($friendLines) > 0 ? ['t.line_id', 'in', array_merge($friendLines, [$lineId])] : ['t.line_id', '=', $lineId];
            $fieldsStr .= ", (CASE WHEN t.line_id = $lineId THEN 1 ELSE 0 END) AS line_sort ";
        } else {
            $lineSortWhen = '';
            if ($startId > 0 && $endId > 0) {
                $lineSortWhen .= " WHEN t.start_id = $startId AND  t.end_id = $endId THEN 10 ";
            }
            if ($startId > 0) {
                $lineSortWhen .= " WHEN t.start_id = $startId THEN 8 ";
            }
            if ($endId > 0) {
                $lineSortWhen .= " WHEN t.end_id = $endId THEN 5 ";
            }
            if (!empty($lineSortWhen)) {
                $fieldsStr .= ", (CASE $lineSortWhen ELSE 0 END) AS line_sort ";
            }
            $startRangeSql = $Utils->buildCoordRangeWhereSql('t.start_longitude', 't.start_latitude', $startLng, $startLat, $radius);
            $endRangeSql = $Utils->buildCoordRangeWhereSql('t.end_longitude', 't.end_latitude', $endLng, $endLat, $radius);
        }
        
        // 构建查询map
        $map = [
            ['t.status', 'in', [0,1]],
            ['t.time', 'between', [date('Y-m-d H:i:s', $start_time), date('Y-m-d H:i:s', $end_time)]],
        ];
        if (isset($lineMap)) {
            $map[] = $lineMap;
        }
        if (isset($startRangeSql) && isset($endRangeSql)) {
            $map[] = ['', 'EXP', Db::raw("$startRangeSql AND $endRangeSql")];
        }
        if ($userType === 1) {
            $map[] = ['t.user_type', '=', 1];
            $map[] = ['t.comefrom', '=', 1];
        } elseif ($userType === 0) {
            $map[] = ['t.user_type', '=', 0];
            $map[] = ['t.comefrom', '=', 2];
            $map[] = ['t.trip_id', '=', 0];
        } else {
            $map[] = ['t.trip_id', '=', 0];
            $map[] = ['t.comefrom', 'between', [1,2]];
        }
        if ($uid > 0) {
            $map[] = ['t.uid', '=', $uid];
            $join = [];
        }
        if ($uid <= 0) {
            if ($uid < 0) {
                $map[] = ['t.uid', '<>', -1 * $uid];
            }
            $TripsService = new TripsService();
            $fields_user = $TripsService->buildUserFields('u', $userFields);
            $fieldsStr .=  ',' .$fields_user;
            $join = [
                ['user u', 'u.uid = t.uid', 'left'],
            ];
        }
        $ShuttleTripModel = new ShuttleTripModel();
        $TripsMixedService = new TripsMixedService();
        $list = $ShuttleTripModel->alias('t')->field($fieldsStr)->where($map)->join($join)->order('t.time ASC, line_sort DESC')->select();
        if (!$list) {
            return [];
        }
        $list = $list->toArray();
        // 如果uid > 0 查询用户信息
        if ($uid > 0) {
            $User = new UserModel();
            $userData = $User->findByUid($uid);
            $userData = $Utils->filterDataFields($userData, $userFields, false, 'u_', -1);
            foreach ($list as $key => $value) {
                $value = array_merge($value, $userData);
                $list[$key] = $value;
            }
        }
        $newList = [];
        foreach ($list as $key => $value) {
            // 遍历添加line_data
            $value = $this->formatTimeFields($value, 'item', ['time','create_time']);
            $itemlineData =  $this->getExtraInfoLineData($value, 2);
            $lineData = [
                'start_name' => $itemlineData['start_name'],
                'start_longitude' => $itemlineData['start_longitude'],
                'start_latitude' => $itemlineData['start_latitude'],
                'end_name' => $itemlineData['end_name'],
                'end_longitude' => $itemlineData['end_longitude'],
                'end_latitude' => $itemlineData['end_latitude'],
                'map_type' => $itemlineData['map_type'] ?? 0,
            ];
            $value['line_data'] = $lineData;
            $value = $TripsMixedService->formatResultValue($value, ['extra_info']);

            // 根据time_offset排除个别行
            $vTimeOffset = $value['time_offset'] > 0 ? [$value['time_offset'], $value['time_offset']] : $timeOffset;
            if (!($value['time'] > $time - $vTimeOffset[0] &&  $value['time'] < $time + $vTimeOffset[1])) {
                continue;
            }
            // 排序指定范围外 (理论上sql已筛好, 为保险再筛一次，以保证合拼接口不因为范围问题而拒绝)
            $startDistance = $Utils->getDistance($lineData['start_longitude'], $lineData['start_latitude'], $startLng, $startLat);
            $endDistance = $Utils->getDistance($lineData['end_longitude'], $lineData['end_latitude'], $endLng, $endLat);
            if ($startDistance > $radius || $endDistance > $radius) {
                continue;
            }
            $newList[] = $value;
        }
        return $newList;
    }


    /**
     * 取消行程
     *
     * @param mixed $idOrData 当为数字时，为行程id；当为array时，为该行程的data;
     * @param mixed $uidOrData 当为数字时，为用户id；当为array时，为该用户的data;
     * @return void
     */
    public function cancel($idOrData, $uidOrData)
    {
        $ShuttleTripModel = new ShuttleTripModel();
        $userModel = new UserModel();
        $tripData = $ShuttleTripModel->getDataByIdOrData($idOrData);
        $id = $tripData['id'];
        $userData = is_numeric($uidOrData) ? $userModel->findByUid($uidOrData) : $uidOrData;
        $uid = $userData['uid'];

        if (empty($tripData)) {
            return $this->setError(20002, lang('The trip does not exist'), $tripData);
        }
        if (time() - strtotime($tripData['time']) > 20 * 60) {
            return $this->setError(30007, lang('The trip has been going on for a while. Operation is not allowed'));
        }
        $userType = $tripData['user_type'];
        $extData = [];
        $res = false;
        if ($userType == 1) { // 如果从司机行程进行操作
            //检查是否已取消或完成
            if (in_array($tripData['status'], [-1, 3, 4, 5])) {
                return $this->setError(30001, lang('The trip has been completed or cancelled. Operation is not allowed'), $tripData);
            }
            // 检查是否该行程的成员
            if ($tripData['uid'] == $uid) { //如果是司机自已操作
                $extData['i_am_driver'] = true;
                $passengers = $ShuttleTripModel->passengers($id);
                $extData['passengers'] = $passengers;
                $res = $this->runRowCancel($tripData); // 执行取消
            } else { // 如果是乘客从司机空座位上操作
                $myTripData = $ShuttleTripModel->findPtByDt($id, $uid, ['status','between',[0,5]]);
                if ($myTripData) {
                    if ($myTripData['status'] > 2) {
                        return $this->setError(30001, lang('This trip has expired or ended and cannot be operated'));
                    }
                    $extData['myTripData'] = $myTripData;
                    $res = $this->runRowCancel($myTripData); // 执行取消
                } else {
                    return $this->setError(30001, lang('You are not the driver or passenger of the trip and cannot operate'));
                }
            }
        } else { // 从乘客行程操作
            if ($tripData['status'] == -1) { // 如果本身已取消，直接返回成功
                return true;
            }
            if ($tripData['trip_id'] > 0) { // 如果行程
                $driverTripData = $ShuttleTripModel->getItem($tripData['trip_id']);
                $extData['driverTripData'] = $driverTripData;
                if ($driverTripData['status'] == 3) {
                    // 如果司机行程为结束，则此无法操作, （如果司结行程未结束，即使自己行程点了结束，也可进行取消）
                    return $this->setError(-1, lang('Trip has ended and cannot be operated'), $tripData);
                }
            } elseif ($tripData['status'] == 3) { // 如果trip_id不大于0且状态为结束，则无法操作
                return $this->setError(-1, lang('Trip has ended and cannot be operated'), $tripData);
            }
            if ($tripData['uid'] == $uid) { //如果是乘客自已操作
                // XXX: 如果是乘客自已操作
            } elseif ($ShuttleTripModel->checkIsDriver($tripData, $uid)) { // 如果是司机操作乘客
                $extData['i_am_driver'] = true;
            } else {
                return $this->setError(30001, lang('You can not operate someone else`s trip'));
            }
            // 执行取消
            $res = $this->runRowCancel($tripData); // 执行取消
        }
        if ($res) {
            $this->doAfterStatusChange($tripData, $userData, 'cancel', $extData);
        }
        return $res;
    }

    /**
     * 执行取消行程并删除同行者行
     *
     * @param mixed $idOrData 要取消的行程行
     * @param integer $must 强制取消：如果是乘客行程，是否执行不还源取消，默认还原(即不强制)
     * @return boolean
     */
    public function runRowCancel($idOrData, $must = 0)
    {
        $ShuttleTripModel = new ShuttleTripModel();
        $PartnerService  = new PartnerService();
        $tripData =  $ShuttleTripModel->getDataByIdOrData($idOrData);
        $userType = $tripData['user_type'];
        Db::connect('database_carpool')->startTrans();
        try {
            if ($userType == 1) { // 如果是司机行程
                $res = $ShuttleTripModel->cancelDriveTrip($tripData);
                if ($res) {
                    // 如果有同行者要还原，则还原同行者
                    $resData = $ShuttleTripModel->getError();
                    $partnerTripList = $resData['partnerTripList'] ?? [];
                    $PartnerService->getOffCar($partnerTripList);
                }
            } else { // 如果是乘客行程
                $res = $ShuttleTripModel->cancelPassengerTrip($tripData, $must);
                if ($res && $tripData['trip_id'] > 0) { // 如果该行程是有司机配对的，成功后：
                    $from_type = $tripData['line_type'] > 0 ? 1 : 0 ;
                    $PartnerModel  = new ShuttleTripPartner();
                    if ($tripData['comefrom'] == 4) { // 如果该行程本身是同行者行程
                        $partnerData = $ShuttleTripModel->getExtraInfo($tripData, 'partner_data');
                        if (isset($partnerData['id']) && $partnerData['id'] > 0) {
                            $PartnerModel->where('id', $partnerData['id'])->update(['is_delete' => 1]); // 删
                            $this->resetRequestSeatCount($tripData['id'], $from_type); // 重计seat_count;
                        }
                    } elseif ($tripData['comefrom'] == 2 &&  time() <= strtotime($tripData['time'])) { // 如果该行程是发起的约车需求
                        $partnerMap = [
                            ['is_delete', '=', Db::raw(0)],
                            ['trip_id', '=', $tripData['id']],
                        ];
                        $partnerMap[] = $from_type ? ['line_type', '>', 0] : ['line_type', '=', 0];
                        $havePartner = $PartnerModel->where($partnerMap)->count(); // 查出所有partners
                        if ($havePartner > 0) {
                            $PartnerModel->where($partnerMap)->update(['is_delete' => 1]); // 删
                            $this->resetRequestSeatCount($tripData['id'], $from_type); // 重计seat_count;
                        }
                    }
                }
            }
            // 提交事务
            Db::connect('database_carpool')->commit();
        } catch (\Exception $e) {
            Db::connect('database_carpool')->rollback();
            $errorMsg = $e->getMessage();
            return $this->setError(-1, lang('Failed'), ['errorMsg'=>$errorMsg]);
        }
        if (isset($res) && $res) {
            return true;
        }
        $errorData = $ShuttleTripModel->getError();
        return $this->setError($errorData['code'] ?? -1, $errorData['msg'] ?? 'Failed', $errorData['data'] ?? []);
    }


    /**
     * 重计算约车需求的seat_count（计算同行者）
     * 成功后会自动清除同行者列表缓存，以及该行程item缓存
     *
     * @param integer $trip_id 行程id
     * @param integer $from_type 0普通行程，1上下班行程
     */
    public function resetRequestSeatCount($trip_id, $from_type)
    {
        $ShuttleTripPartner = new ShuttleTripPartner();
        $ShuttleTripModel = new ShuttleTripModel();
        // 处理原行程seat_count数
        $partnersCount = $ShuttleTripPartner->countPartners($trip_id, $from_type);
        $seat_count = intval($partnersCount) + 1;
        $res = $ShuttleTripModel->where('id', $trip_id)->update(['seat_count'=>$seat_count]);
        if ($res) {
            $ShuttleTripModel->delItemCache($trip_id); // 清除约车需求行程的缓存
            $ShuttleTripPartner->delPartnersCache($trip_id, $from_type); // 清除行程的同行者列表缓存
        }
        return $res;
    }

    /**
     * 完结行程
     *
     * @param mixed $idOrData 当为数字时，为行程id；当为array时，为该行程的data;
     * @param mixed $uidOrData 当为数字时，为用户id；当为array时，为该用户的data; ;
     * @return boolean
     */
    public function finish($idOrData, $uidOrData)
    {
        $ShuttleTripModel = new ShuttleTripModel();
        $userModel = new UserModel();
        $tripData = $ShuttleTripModel->getDataByIdOrData($idOrData);
        $id = $tripData['id'];
        $userData = is_numeric($uidOrData) ? $userModel->findByUid($uidOrData) : $uidOrData;
        $uid = $userData['uid'];
        if (empty($tripData)) {
            return $this->error(20002, lang('The trip does not exist'), $tripData);
        }
        //检查是否已取消或完成
        if (in_array($tripData['status'], [-1, 3, 4, 5])) {
            return $this->error(30001, lang('The trip has been completed or cancelled. Operation is not allowed'), $tripData);
        }
        $userType = $tripData['user_type'];
        $extData = [];

        if ($userType == 1) { // 如果从司机行程进行操作
            // 检查是否该行程的成员
            if ($tripData['uid'] == $uid) { //如果是司机自已操作
                $passengers = $ShuttleTripModel->passengers($id);
                $extData['passengers'] = $passengers;
                $res = $ShuttleTripModel->finishDriverTrip($tripData);
            } else { // 如果是乘客从司机空座位上操作
                $myTripData = $ShuttleTripModel->findPtByDt($id, $uid, ['status', 'between', [0,5]]);
                if ($myTripData) {
                    if ($myTripData['status'] == 3) {
                        return true;
                    }
                    if ($myTripData['status'] > 2) {
                        return $this->error(30001, lang('This trip has expired or ended and cannot be operated'));
                    }
                    $extData['myTripData'] = $myTripData;
                    $res = $ShuttleTripModel->finishPassengerTrip($myTripData);
                } else {
                    return $this->error(30001, lang('You are not the driver or passenger of the trip and cannot operate'));
                }
            }
        } else { // 从乘客行程操作
            if ($tripData['uid'] == $uid) { //如果是乘客自已操作
                $res = $ShuttleTripModel->finishPassengerTrip($tripData);
            } else {
                return $this->error(30001, lang('You can not operate someone else`s trip'));
            }
        }
        if (isset($res) && $res) {
            $this->doAfterStatusChange($tripData, $userData, 'finish', $extData);
            return true;
        }
        $errorData = $ShuttleTripModel->getError();
        return $this->error($errorData['code'] ?? -1, $errorData['msg'] ?? 'Failed', $errorData['data'] ?? []);
    }

    /**
     * 执行完完结或取消行程后，进行清除缓存
     *
     * @param array $tripData 该行程的data;
     * @param array $userData 操作用户的data;
     * @param string $runType 'cancel' or 'finish'
     * @return void
     */
    public function delCacheAfterStatusChange($tripData, $userData, $runType, $extData = [])
    {
        return $this->doAfterStatusChange($tripData, $userData, $runType, $extData, 1);
    }

    /**
     * 执行完完结或取消行程后，进行推送或清除缓存
     *
     * @param array $tripData 该行程的data;
     * @param array $userData 操作用户的data;
     * @param string $runType 'cancel' or 'finish'
     * @param integer $dontPushMsg  是否不推送， 0进行推送，1不推送
     * @return void
     */
    public function doAfterStatusChange($tripData, $userData, $runType, $extData = [], $dontPushMsg = 0)
    {
        $ShuttleTripModel = new ShuttleTripModel();
        $TripsMixedService = new TripsMixedService();
        $id = $tripData['id'];
        $uid = $userData['uid'];
        $userType = $tripData['user_type'];

        $TripsPushMsg = new TripsPushMsg();
        $pushMsgData = [
            'from' => 'shuttle_trip',
            'runType' => $runType,
            'userData'=> $userData,
            'tripData'=> $tripData,
            'id' => $extData['id'] ?? $id,
        ];
        $iAmDriver = $extData['i_am_driver'] ?? 0;
        $doPush = false;
        $ShuttleTripModel->delMyListCache($uid); //清除自己的“我的行程”列表缓存
        $ShuttleTripModel->delItemCache($id); // 清乘客单项行程缓存
        $TripsMixedService->delMyListCache($uid); // 清除我将要发生的行程缓存
        $TripsMixedService->delUpGpsInfoidCache($uid); // 清除是否要上传GPS接口缓存

        if ($userType == 1) { // 如果从司机行程进行操作
            if ($tripData['uid'] == $uid) { //如果是司机自已操作
                // 清缓存
                $ShuttleTripModel->delPassengersCache($id); //清除乘客列表缓存
                // 推消息
                $targetUserid = [];
                $passengers = $extData['passengers'] ?? [];
                foreach ($passengers as $key => $value) {
                    $p_uid = $value['uid'] ?? $value['u_uid'];
                    $targetUserid[] = $p_uid;
                    $ShuttleTripModel->delItemCache($value['id']); // 清乘客单项行程缓存
                    $ShuttleTripModel->delMyListCache($p_uid, 'my'); //清除所有乘客的“我的行程”列表缓存
                    $TripsMixedService->delMyListCache($p_uid); // 清除乘客将要发生的行程缓存
                    $TripsMixedService->delUpGpsInfoidCache($p_uid); // 清除是否要上传GPS接口缓存
                }
                // 推送设置
                $pushMsgData['isDriver'] = true;
                $doPush = true;
            } else { // 如果是乘客从司机空座位上操作
                // 清缓存
                $myTripData = $extData['myTripData'] ?? [];
                if (!empty($myTripData) && $myTripData['id']) {
                    $ShuttleTripModel->delItemCache($myTripData['id']); // 清乘客单项行程缓存
                    $ShuttleTripModel->delMyListCache($tripData['uid']); //清除司机的“我的行程”列表缓存
                    $TripsMixedService->delMyListCache($tripData['uid']); // 清除司机将要发生的行程缓存
                    $TripsMixedService->delUpGpsInfoidCache($tripData['uid']); // 清除是否要上传GPS接口缓存
                }
                $ShuttleTripModel->delPassengersCache($id); //清除乘客列表缓存
                // 推送设置
                $targetUserid = $tripData['uid'];
                $pushMsgData['isDriver'] = false;
                $doPush = true;
            }
        } else { // 从乘客行程操作
            if ($tripData['uid'] == $uid) { //如果是乘客自已操作
                // 清缓存
                if ($tripData['trip_id'] > 0) { //如果有司机，查出司机以便推送
                    $ShuttleTripModel->delPassengersCache($tripData['trip_id']); // 清司机行程的乘客列表缓存
                    $driverTripData = $extData['driverTripData'] ?: $ShuttleTripModel->getItem($tripData['trip_id']);
                    $ShuttleTripModel->delMyListCache($driverTripData['uid']); //清除司机的“我的行程”列表缓存
                    $TripsMixedService->delMyListCache($driverTripData['uid']); // 清除司机将要发生的行程缓存
                    $TripsMixedService->delUpGpsInfoidCache($driverTripData['uid']); // 清除是否要上传GPS接口缓存
                    // 推送设置
                    $targetUserid = $driverTripData ? $driverTripData['uid'] : 0;
                    $pushMsgData['isDriver'] = false;
                    $pushMsgData['id'] = $tripData['trip_id'];
                    $doPush = $targetUserid > 0 ? true : false;
                }
            } elseif ($iAmDriver) { // 如果是司机操作乘客
                // 清缓存
                $ShuttleTripModel->delPassengersCache($tripData['trip_id']); // 清除司机的乘客列表缓存
                $ShuttleTripModel->delMyListCache($tripData['uid']); //清除乘客的“我的行程”列表缓存
                $TripsMixedService->delMyListCache($tripData['uid']); // 清除乘客将要发生的行程缓存
                $TripsMixedService->delUpGpsInfoidCache($tripData['uid']); // 清除是否要上传GPS接口缓存
                // 推送设置
                $targetUserid = $tripData['uid'];
                $pushMsgData['isDriver'] = true;
                $pushMsgData['id'] = $tripData['trip_id'] > 0 ? $tripData['trip_id'] : $pushMsgData['id'];
                $doPush = true;
            }
        }
        // 推送
        if ($runType == 'cancel' && $doPush && !$dontPushMsg) {
            $TripsPushMsg->pushMsg($targetUserid, $pushMsgData); // 推消息
        }
    }

    /**
     * 执行完合并行程后，进行推送或清除缓存
     *
     * @param array $dvTripData 司机行程的data;
     * @param array $psTripData 乘客行程的data;
     * @param array $userData 操作用户的data;
     * @return void
     */
    public function doAfterMerge($dvTripData, $psTripData, $userData)
    {
        $ShuttleTripModel = new ShuttleTripModel();
        $TripsMixedService = new TripsMixedService();
        // 清除“我的行程”列表缓存
        $ShuttleTripModel->delMyListCache($dvTripData['uid'], 'my');
        $ShuttleTripModel->delMyListCache($psTripData['uid'], 'my');
        $TripsMixedService->delUpGpsInfoidCache([$dvTripData['uid'], $psTripData['uid']]); // 清除是否要上传GPS接口缓存
        // 清除行情明细缓存
        $ShuttleTripModel->delItemCache($dvTripData['id']);
        $ShuttleTripModel->delItemCache($psTripData['id']);
        // 清除乘客数缓存
        $ShuttleTripModel->delPassengersCache($dvTripData['id']);
        $ShuttleTripModel->delTookCountCache($dvTripData['id']);
        // 推送
        $iAmDriver = $userData['uid'] == $dvTripData['uid'] ? 1 : 0;
        $runType = $iAmDriver ? 'pickup' : 'hitchhiking';
        $targetUserid = $iAmDriver ? $psTripData['uid'] : $dvTripData['uid'];
        $pushMsgData = [
            'from' => 'shuttle_trip',
            'runType' => $runType,
            'userData'=> $userData,
            'tripData'=> $iAmDriver ? $dvTripData : $psTripData,
            'id' =>  $iAmDriver ? $dvTripData['id'] : $psTripData['id'],
            'isDriver' => $iAmDriver,
        ];
        $TripsPushMsg = new TripsPushMsg();
        $TripsPushMsg->pushMsg($targetUserid, $pushMsgData);
    }
}
