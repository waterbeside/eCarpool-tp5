<?php
namespace app\carpool\service\shuttle;

use app\common\service\Service;
use app\carpool\model\User as UserModel;
use app\carpool\model\ShuttleLine as ShuttleLineModel;
use app\carpool\model\ShuttleTrip as ShuttleTripModel;
use app\carpool\service\Trips as TripsService;
use app\carpool\service\TripsList as TripsListService;
use app\carpool\service\TripsPushMsg;
use app\carpool\model\ShuttleLineDepartment;
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
        $rqData = $rqData ?: [];
        $rqData['create_type'] = $rqData['create_type'] ?? input('post.create_type');
        $rqData['line_id'] = $rqData['line_id'] ?? input('post.line_id/d', 0);
        $rqData['line_type'] = $rqData['line_type'] ?? input('post.line_type/d', 0);
        $rqData['trip_id'] = $rqData['trip_id'] ?? input('post.trip_id/d', 0);
        $rqData['seat_count'] = $rqData['seat_count'] ?? input('post.seat_count/d', 0);
        $rqData['time'] = $rqData['time'] ?? input('post.time/d', 0);
        return $rqData;
    }

    /**
     * 验证添加行程时的时间
     */
    public function checkAddData($rqData, $uid)
    {
        // 取得与 create_type 相关数据
        $createTypeData = $this->getCreateTypeInfo($rqData['create_type']);
        $rqData = array_merge($rqData, $createTypeData);


        $time = $rqData['time'] ?: null;
        if (empty($rqData['line_id'])) {
            return $this->error(992, '请选择路线');
        }
        if (empty($time)) {
            return $this->error(992, '请选择时间');
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

        //检查出发时间是否已经过了
        if (time() > $time) {
            return $this->error(992, lang("The departure time has passed. Please select the time again"));
        }
        // 验证重复行程
        $TripsService = new TripsService();
        $repetitionList = $TripsService->getRepetition($time, $uid);
        if ($repetitionList) {
            $TripsListService = new TripsListService();
            // 为重复行程列表添加明细
            $repetitionList = $TripsListService->getMixedDetailListByRpList($repetitionList);
            $errorData = $TripsService->getError();
            // 检查有没有可以合并的行程
            $matchingList =  in_array($create_type, ['pickup', 'hitchhiking']) ? $this->getMatchingsByRplist($repetitionList, $rqData) : [];
            if (!empty($matchingList)) {
                return $this->error(50008, lang('查到你有相似的行程可以合并'), ['lists'=>$repetitionList]);
            }
            $this->error($errorData['code'], $errorData['msg'], ['lists'=>$repetitionList]);
            return false;
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
            return $this->error(992, 'Error create_type');
        }
    }

    /**
     * 发布行程
     *
     * @param array $rqData 请求参数
     * @param mixed $uidOrData uid or userData
     * @return void
     */
    public function addTrip($rqData, $uidOrData)
    {

        // 取得用户信息
        $userModel = new UserModel();
        $userData = is_numeric($uidOrData) ? $userModel->findByUid($uidOrData) : $uidOrData;
        $uid = $userData['uid'];

        // 验证字段合法性
        $rqData = $this->checkAddData($rqData, $uid);
        if (!$rqData) {
            return false;
        }
        $plate = $rqData['user_type'] == 1 ? $userData['carnumber'] : '';
        $trip_id = isset($rqData['trip_id']) && is_numeric($rqData['trip_id']) ? $rqData['trip_id'] : 0;

        // 创建入库数据
        $updata = [
            'line_id' => $rqData['line_id'],
            'time' => date('Y-m-d H:i:s', $rqData['time']),
            'uid' => $uid,
            'user_type' => $rqData['user_type'],
            'comefrom' => $rqData['comefrom'],
            'trip_id' => $trip_id,
            'status' => 0,
            'seat_count' => intval($rqData['seat_count']),
        ];
        if ($plate) {
            $updata['plate'] = $plate;
        }
        if (isset($rqData['line_data']) && is_array($rqData['line_data'])) {
            $updata['line_type'] = intval($rqData['line_data']['type']);
            $updata['extra_info'] = json_encode(['line_data' => $rqData['line_data']]);
        }
        $ShuttleTripModel = new ShuttleTripModel();
        $newid = $ShuttleTripModel->insertGetId($updata);
        if (!$newid) {
            return $this->error(-1, '添加数据失败');
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
        $ShuttleTripModel->delCacheAfterAdd($cData);
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
        foreach ($repetitionList as $key => $value) {
            $userType = $value['user_type'] ?: false;
            $line_id = $value['line_id'];
            $tripMatching = $value['from'] === 'shuttle_trip' && $userType === $matchUserType && $line_id == $rqData['line_id'] ? true : false;
            $userTypeMatching = $value['user_type'] > 0 || ($value['trip_id'] == 0 && $value['comefrom'] == 2 ) ? true : false;
            $timeMatch = $value['check_level']['k'] == 0 ? true : false;
            if ($tripMatching && $userTypeMatching && $timeMatch) {
                $matchingList[] = $value;
            }
        }
        return $matchingList;
    }

    /**
     * 取得用于冗余进行程表的路线信息；
     *
     * @param integer $id line_id  or trip_id ;
     * @param integer $type type=0时 id为路线id（line_id）, type = 1时id为行程id (trip_id), type = 2 时，先以type=1查，再以type=0查;
     * @return array
     */
    public function getExtraInfoLineData($id, $type = 0)
    {
        if ($type > 0) {
            $ShuttleTripModel = new ShuttleTripModel();
            $itemData = $ShuttleTripModel->getDataByIdOrData($id);
            if (!$itemData) {
                return null;
            }
            try {
                $trip_info = json_decode($itemData['extra_info'], true);
                $lineData = $trip_info['line_data'];
            } catch (\Exception $e) {  //其他错误
                $lineData = null;
            }
            if (!$lineData && $type == 2) {
                $lineData = $this->getExtraInfoLineData($itemData['line_id'], 0);
            }
        } else {
            $lineFields = 'id, start_name, start_longitude, start_latitude, end_name, end_longitude, end_latitude, map_type, type';
            $ShuttleLineModel = new ShuttleLineModel();
            $lineData = $ShuttleLineModel->getItem($id, $lineFields);
        }
        return $lineData;
    }

    /**
     * 取得详情
     *
     * @param integer $id 行程id
     * @param array $userFields 要读出的用户字段
     * @param array $tripFields 要读出的行程字段
     * @param integer $show_line 是否显示路线信息
     */
    public function getUserTripDetail($id = 0, $userFields = [], $tripFields = [], $show_line = 1)
    {
        if (!$id) {
            return $this->error(992, 'Error param');
        }
        $ShuttleTripModel = new ShuttleTripModel();

        $User = new UserModel();

        $itemData = $ShuttleTripModel->getItem($id);
        if (!$itemData) {
            return $this->error(20002, 'No data');
        }
        $uid = $itemData['uid'];
        $line_id = $itemData['line_id'];
        try {
            $trip_info = json_decode($itemData['extra_info'], true);
        } catch (\Exception $e) {  //其他错误
            $trip_info = null;
        }
        $itemData = $this->formatTimeFields($itemData, 'item', ['time','create_time']);
        $tripFields = $tripFields ?: ['id', 'time', 'create_time', 'status', 'user_type', 'comefrom', 'trip_id', 'seat_count'];

        $itemData = Utils::getInstance()->filterDataFields($itemData, $tripFields);
        $userFields = $userFields ?: $this->defaultUserFields;
        $userData = $User->findByUid($uid);
        $userData = Utils::getInstance()->filterDataFields($userData, $userFields, false, 'u_', -1);
        
        if ($show_line) {
            $lineData = null;
            $lineData = $trip_info['line_data'] ?? $this->getExtraInfoLineData($line_id, 0);
            $lineFields = 'start_name, start_longitude, start_latitude, end_name, end_longitude, end_latitude, map_type, type';
            $lineData = Utils::getInstance()->filterDataFields($lineData, $lineFields);
            $itemData = array_merge($itemData, $lineData);
        }
        $data = array_merge($itemData ?? [], $userData ?? []);
        return $data;
    }


    /**
     * 取得乘客列表
     *
     * @param integer $id 行程id
     * @param array $userFields 要读出的用户字段
     * @param integer $returnType 返回数据 1:支持抛出json，2:以数组形式返回数据
     */
    public function passengers($id = 0, $userFields = [], $tripFields = [])
    {
        if (!$id) {
            $this->error(992, 'Error param');
            return [];
        }
        
        $ShuttleTripModel = new ShuttleTripModel();
        $TripsService = new TripsService();
        $cacheKey = $ShuttleTripModel->getPassengersCacheKey($id);
        $redis = new RedisData();
        $userAlias = 'u';
        $res = $redis->cache($cacheKey);
        $userFields = $userFields === false ? $userFields : ($userFields ?: $this->defaultUserFields);
        if ($res === false) {
            $tripFieldsArray = ['id', 'time', 'create_time', 'status', 'comefrom'];
            $fields = Utils::getInstance()->arrayAddString($tripFieldsArray, 't.');
            $fields = is_array($fields) ? implode(',', $fields) : $fields;
            if ($userFields !== false) {
                $fields_user = $TripsService->buildUserFields($userAlias, $this->defaultUserFields);
                $fields .=  ',' .$fields_user;
                $join = [
                    ["user {$userAlias}", "t.uid = {$userAlias}.uid", 'left'],
                ];
            } else {
                $join = [];
            }

            $map = [
                ['t.user_type', '=', Db::raw(0)],
                ['t.trip_id', '=', $id],
                ['t.status', 'between', [0,3]],
            ];
            $res = $ShuttleTripModel->alias('t')->field($fields)->join($join)->where($map)->order('t.create_time ASC')->select()->toArray();
            $redis->cache($cacheKey, $res, 60);
        }
        if (!$res) {
            $this->error(20002, 'No data');
        }
        $res = $this->formatTimeFields($res, 'list', ['time','create_time']);
        if (!empty($tripFields)) {
            $tripFields = is_string($tripFields) ? array_map('trim', explode(',', $tripFields)) : $tripFields;
            if ($userFields !== false) {
                $userFields = is_string($userFields) ? array_map('trim', explode(',', $userFields)) : $userFields;
                $userFields = Utils::getInstance()->arrayAddString($userFields, 'u_') ?: [];
                $filterFields = array_merge($tripFields, $userFields);
            } else {
                $filterFields = $tripFields;
            }
            $res = Utils::getInstance()->filterListFields($res, $filterFields);
        }
        return $res;
    }

    /**
     * 取得相似行程
     *
     * @param integer $line_id 路线id
     * @param integer $time 时间
     * @param integer $userType  0=匹配乘客行程，1=匹配司机行程
     * @param integer $uid  大于0时=要查找的用户id的行程，小于0时=要排除的用户id的行程
     * @param mixed $timeOffset  时间前后偏移，格式为[10,10]数组。 当为单个数字时，自动变成[数字,数字]
     * @return array
     */
    public function getSimilarTrips($line_id, $time = 0, $userType = false, $uid = 0, $timeOffset = [60*15, 60*15])
    {
        $tripFields = ['id', 'time', 'create_time', 'status', 'user_type', 'comefrom','line_id', 'seat_count'];
        $userFields =[
            'uid','loginname','name','nativename','phone','mobile','Department',
            'sex','company_id','department_id','imgpath','carcolor', 'im_id'
        ];
        $fieldsStr = implode(',', Utils::getInstance()->arrayAddString($tripFields, 't.') ?: []);
        $time = $time ?: time();
        if (is_numeric($timeOffset)) {
            $timeOffset = [$timeOffset, $timeOffset];
        }
        $start_time = $time - $timeOffset[0];
        $end_time = $time + $timeOffset[1];
        $map = [
            ['t.line_id', '=', $line_id],
            ['t.status', 'between', [0,1]],
            ['t.time', 'between', [date('Y-m-d H:i:s', $start_time), date('Y-m-d H:i:s', $end_time)]],
        ];
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
        $TripsService = new TripsService();
        if ($uid > 0) {
            $map[] = ['t.uid', '=', $uid];
            $join = [];
        }
        if ($uid <= 0) {
            if ($uid < 0) {
                $map[] = ['t.uid', '<>', -1 * $uid];
            }
            $fields_user = $TripsService->buildUserFields('u', $userFields);
            $fieldsStr .=  ',' .$fields_user;
            $join = [
                ['user u', 'u.uid = t.uid', 'left'],
            ];
        }
        $ShuttleTripModel = new ShuttleTripModel();
        $list = $ShuttleTripModel->alias('t')->field($fieldsStr)->where($map)->join($join)->order('t.time ASC')->select();
        if (!$list) {
            return [];
        }
        $list = $list->toArray();
        if ($uid > 0) {
            $User = new UserModel();
            $userData = $User->findByUid($uid);
            $userData = Utils::getInstance()->filterDataFields($userData, $userFields, false, 'u_', -1);
            foreach ($list as $key => $value) {
                $value = array_merge($value, $userData);
                $list[$key] = $value;
            }
        }
        return $list;
    }


    /**
     * 取消行程
     *
     * @param mixed $idOrData 当为数字时，为行程id；当为array时，为该行程的data;
     * @param mixed $uidOrData 当为数字时，为用户id；当为array时，为该用户的data; ;
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
            return $this->error(20002, '该行程不存在', $tripData);
        }
        
        $userType = $tripData['user_type'];
        $extData = [];

        if ($userType == 1) { // 如果从司机行程进行操作
            //检查是否已取消或完成
            if (in_array($tripData['status'], [-1, 3])) {
                return $this->error(-1, lang('The trip has been completed or cancelled. Operation is not allowed'), $tripData);
            }
            // 检查是否该行程的成员
            if ($tripData['uid'] == $uid) { //如果是司机自已操作
                $extData['i_am_driver'] = true;
                $passengers = $this->passengers($id, false);
                $extData['passengers'] = $passengers;
                $res = $ShuttleTripModel->cancelDriveTrip($tripData);
            } else { // 如果是乘客从司机空座位上操作
                $myTripData = $ShuttleTripModel->findPtByDt($id, $uid, ['status','between',[0,3]]);
                if ($myTripData) {
                    $extData['myTripData'] = $myTripData;
                    $res = $ShuttleTripModel->cancelPassengerTrip($myTripData);
                } else {
                    return $this->error(30001, lang('你不是司机或乘客，无法操作'));
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
                    return $this->error(-1, lang('行程已结束，无法操作'), $tripData);
                }
            } elseif ($tripData['status'] == 3) { // 如果trip_id不大于0且状态为结束，则无法操作
                return $this->error(-1, lang('行程已结束，无法操作'), $tripData);
            }
            if ($tripData['uid'] == $uid) { //如果是乘客自已操作
                $res = $ShuttleTripModel->cancelPassengerTrip($tripData);
            } elseif ($ShuttleTripModel->checkIsDriver($tripData, $uid)) { // 如果是司机操作乘客
                $extData['i_am_driver'] = true;
                $res = $ShuttleTripModel->cancelPassengerTrip($tripData);
            } else {
                return $this->error(30001, lang('你不能操作别人的行程'));
            }
        }
        if (isset($res) && $res) {
            $this->doAfterStatusChange($tripData, $userData, 'cancel', $extData);
            return true;
        }
        $errorData = $ShuttleTripModel->getError();
        return $this->error($errorData['code'] ?? -1, $errorData['msg'] ?? 'Failed', $errorData['data'] ?? []);
    }

    /**
     * 完结行程
     *
     * @param mixed $idOrData 当为数字时，为行程id；当为array时，为该行程的data;
     * @param mixed $uidOrData 当为数字时，为用户id；当为array时，为该用户的data; ;
     * @return void
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
            return $this->error(20002, '该行程不存在', $tripData);
        }
        //检查是否已取消或完成
        if (in_array($tripData['status'], [-1, 3])) {
            return $this->error(-1, lang('The trip has been completed or cancelled. Operation is not allowed'), $tripData);
        }
        $userType = $tripData['user_type'];
        $extData = [];

        if ($userType == 1) { // 如果从司机行程进行操作
            // 检查是否该行程的成员
            if ($tripData['uid'] == $uid) { //如果是司机自已操作
                $passengers = $this->passengers($id, false);
                $extData['passengers'] = $passengers;
                $res = $ShuttleTripModel->finishDriverTrip($tripData);
            } else { // 如果是乘客从司机空座位上操作
                $myTripData = $ShuttleTripModel->findPtByDt($id, $uid);
                if ($myTripData) {
                    $extData['myTripData'] = $myTripData;
                    $res = $ShuttleTripModel->finishPassengerTrip($myTripData);
                } else {
                    $myTripData = $ShuttleTripModel->findPtByDt($id, $uid, 3);
                    if ($myTripData) {
                        return true;
                    }
                    return $this->error(30001, lang('你不是司机或乘客，无法操作'));
                }
            }
        } else { // 从乘客行程操作
            if ($tripData['uid'] == $uid) { //如果是乘客自已操作
                $res = $ShuttleTripModel->finishPassengerTrip($tripData);
            } else {
                return $this->error(30001, lang('你不能操作别人的行程'));
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
     * 执行完完结或取消行程后，进行推送或清除缓存
     *
     * @param array $tripData 该行程的data;
     * @param array $userData 操作用户的data;
     * @param string $runType 'cancel' or 'finish'
     * @return void
     */
    public function doAfterStatusChange($tripData, $userData, $runType, $extData = [])
    {
        $ShuttleTripModel = new ShuttleTripModel();
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
        $ShuttleTripModel->delMyListCache($uid); //清除自己的“我的行程”列表缓存

        if ($userType == 1) { // 如果从司机行程进行操作
            if ($tripData['uid'] == $uid) { //如果是司机自已操作
                // 清缓存
                $ShuttleTripModel->delItemCache($id); // 清单项行程缓存
                $ShuttleTripModel->delPassengersCache($id); //清除乘客列表缓存
                // 推消息
                $targetUserid = [];
                $passengers = $extData['passengers'] ?? [];
                foreach ($passengers as $key => $value) {
                    $targetUserid[] = $value['u_uid'];
                    $ShuttleTripModel->delItemCache($value['id']); // 清乘客单项行程缓存
                    $ShuttleTripModel->delMyListCache($value['u_uid'], 'my'); //清除所有乘客的“我的行程”列表缓存
                }
                if ($runType == 'cancel') {
                    $pushMsgData['isDriver'] = true;
                    $TripsPushMsg->pushMsg($targetUserid, $pushMsgData); // 推消息
                }
            } else { // 如果是乘客从司机空座位上操作
                // 清缓存
                $myTripData = $extData['myTripData'] ?? [];
                if (!empty($myTripData) && $myTripData['id']) {
                    $ShuttleTripModel->delItemCache($myTripData['id']); // 清乘客单项行程缓存
                    $ShuttleTripModel->delMyListCache($tripData['uid']); //清除司机的“我的行程”列表缓存
                }
                $ShuttleTripModel->delPassengersCache($id); //清除乘客列表缓存
            }
        } else { // 从乘客行程操作
            if ($tripData['uid'] == $uid) { //如果是乘客自已操作
                // 清缓存
                $ShuttleTripModel->delItemCache($id); // 清乘客单项行程缓存
                if ($tripData['trip_id'] > 0) { //如果有司机，查出司机以便推送
                    $ShuttleTripModel->delPassengersCache($tripData['trip_id']); // 清司机行程的乘客列表缓存
                    $driverTripData = $extData['driverTripData'] ?: $ShuttleTripModel->getItem($tripData['trip_id']);
                    $targetUserid = $driverTripData ? $driverTripData['uid'] : 0;
                    $ShuttleTripModel->delMyListCache($targetUserid); //清除司机的“我的行程”列表缓存
                }
                if ($runType === 'cancel' && isset($targetUserid) && $targetUserid > 0) {
                    // 推送
                    $pushMsgData['isDriver'] = false;
                    $pushMsgData['id'] = $tripData['trip_id'];
                    $TripsPushMsg->pushMsg($targetUserid, $pushMsgData);
                }
            } elseif ($runType === 'cancel' && $iAmDriver) { // 如果是司机操作乘客
                // 清缓存
                $ShuttleTripModel->delItemCache($id); // 清乘客单项行程缓存
                $ShuttleTripModel->delPassengersCache($tripData['trip_id']); // 清除司机的乘客列表缓存
                $ShuttleTripModel->delMyListCache($tripData['uid']); //清除乘客的“我的行程”列表缓存
                // 推送
                $pushMsgData['isDriver'] = false;
                $targetUserid = $tripData['uid'];
                $pushMsgData['id'] = $tripData['trip_id'] > 0 ? $tripData['trip_id'] : $pushMsgData['id'];
                $TripsPushMsg->pushMsg($targetUserid, $pushMsgData);
            }
        }
    }
}
