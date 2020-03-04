<?php

namespace app\carpool\service;

use app\common\service\Service;
use app\carpool\service\shuttle\Trip as ShuttleTripService;
use app\carpool\service\shuttle\TripList as ShuttleTripListService;
use app\carpool\service\nmtrip\TripList as NmTripListService;
use app\carpool\service\Trips as TripService;
use app\carpool\service\TripsDetail as TripsDetailService;
use app\carpool\model\ShuttleTrip as ShuttleTripModel;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\model\Address;
use app\carpool\model\Configs as ConfigsModel;
use app\carpool\model\User as UserModel;
use app\carpool\model\ShuttleLineDepartment;
use think\Db;
use my\Utils;

class TripsMixed extends Service
{


    /**
     * 取得我的混合行程Cache Key
     *
     * @param integer $uid 用户id
     * @return string
     */
    public function getMyListCacheKey($uid)
    {
        return "carpool:mixTrip:my:{$uid}";
    }

    /**
     * 删除我的未来所有混合行程缓存
     *
     * @param integer $uid 用户id
     * @return string
     */
    public function delMyListCache($uid)
    {
        $cacheKey = $this->getMyListCacheKey($uid);
        $this->redis()->del($cacheKey);
    }

    /**
     * 为重复行程列表添加明细;
     */
    public function getMixedDetailListByRpList($rpList)
    {
        if (!is_array($rpList)) {
            return [];
        }
        $list = [];
        $userFields = ['uid', 'name', 'nativename', 'imgpath', 'Department', 'sex'];

        foreach ($rpList as $key => $value) {
            $from = $value['from'];
            $UserModel = new UserModel();
            $userType = $value['user_type'];
            if ($from == 'wall') { // 来自普通行程空座位
                $InfoModel = new InfoModel();
                $value['took_count'] =  $InfoModel->countPassengers($value['love_wall_ID']);
            }
            if ($from == 'info') { // 来自普通行程
                $InfoModel = new InfoModel();
                $wall_id = $value['love_wall_ID'];
                if ($userType) { //如果是司机
                    $value['took_count'] = $wall_id > 0 ? $InfoModel->countPassengers($wall_id) : ($value['p_uid'] > 0 ? 1 : 0);
                } else { // 如果是乘客
                    $userData = $UserModel->findByUid($value['d_uid']);
                    $value['driver'] = $userData ? Utils::getInstance()->filterDataFields($userData, $userFields, false, 'u_', -1) : null;
                }
            }
            if ($from == "shuttle_trip") { // 来自上下班行程
                $ShuttleTripModel = new ShuttleTripModel();
                $trip_id = $value['trip_id'];
                if ($userType) { //如果是司机
                    $value['took_count'] =  $ShuttleTripModel->countPassengers($value['id']);
                } else { // 如果是乘客
                    $userData = $UserModel->findByUid($value['d_uid']);
                    $value['driver'] = $userData ? Utils::getInstance()->filterDataFields($userData, $userFields, false, 'u_', -1) : null;
                }
            }
            $list[$key] = $value;
        }
        return $list;
    }

    /**
     * 取得单行行程数据;
     *
     * @param mixed $idOrData 行程id或数据
     * @param string $from  来自哪个表: value in ['shuttle_trip', 'wall', 'info]
     * @return array array or false
     */
    public function getTripItem($id, $from)
    {
        if ($from === 'shuttle_from') { // 上下班行程
            $ShuttleTripModel = new ShuttleTripModel();
            $tripData = $ShuttleTripModel->getItem($id);
        } elseif ($from === 'wall') { // 普通行程空座位
            $WallModel = new WallModel();
            $tripData = $WallModel->getItem($id, 1);
        } elseif ($from === 'ino') { // 普通行程空座位
            $InfoModel = new InfoModel();
            $tripData = $InfoModel->getItem($id, 1);
        } else {
            return false;
        }
        if ($tripData) {
            $tripData['time_x'] = $from === 'shuttle_from' ? strtotime($tripData['time']) : strtotime($tripData['time'].'00');
        }
        return $tripData;
    }

    /**
     * 取消行程前的验证
     *
     * @param mixed $idOrData 行程id或数据
     * @param string $from  来自哪个表: value in ['shuttle_trip', 'wall', 'info]
     * @param mixed $uidOrUData 操作者id或数据
     */
    public function checkBeforeCancel($idOrData, $from, $uidOrUData)
    {
        if (!in_array($from, ['shuttle_from', 'wall', 'info'])) {
            return $this->setError(992, 'Error Param');
        }
        // 取用户数据
        $userData = is_numeric($uidOrUData) ? (new UserModel())->getItem($uidOrUData) : $uidOrUData;
        $uid = $userData['uid'];
        // 取行程数据
        $tripData = is_numeric($idOrData) ? $this->getTripItem($idOrData, $from) : $idOrData;
        if (!$tripData) {
            return $this->setError(20002, lang('The trip does not exist'));
        }
        if ($from === 'shuttle_from') { // 上下班行程
            if ($uid != $tripData['uid']) {
                return $this->setError(30001, lang('You cannot cancel this trip that you have not participated in'));
            }
        } elseif ($from === 'wall') { // 普通行程空座位
            if ($uid != $tripData['carownid']) {
                return $this->setError(30001, lang('You cannot cancel this trip that you have not participated in'));
            }
        } elseif ($from === 'info') { // info行程
            if ($uid != $tripData['passengerid'] && $uid != $tripData['carownid']) {
                return $this->setError(30001, lang('You cannot cancel this trip that you have not participated in'));
            }
        }
        return true;
    }

    /**
     * 取消行程
     *
     * @param mixed $idOrData 行程id或数据
     * @param string $from  来自哪个表: value in ['shuttle_trip', 'wall', 'info]
     * @param mixed $uidOrUData 操作者id或数据
     * @param integer $must
     */
    public function cancelTrip($idOrData, $from, $uidOrUData, $must = 0)
    {
        if (!in_array($from, ['shuttle_from', 'wall', 'info'])) {
            return $this->setError(992, lang('Error Param'));
        }
        // 取用户数据
        $userData = is_numeric($uidOrUData) ? (new UserModel())->getItem($uidOrUData) : $uidOrUData;
        $uid = $userData['uid'];
        // 取行程数据
        $tripData = is_numeric($idOrData) ? $this->getTripItem($idOrData, $from) : $idOrData;
        if (!$tripData) {
            return $this->setError(20002, lang('The trip does not exist'));
        }

        // $TripsPushMsg = new TripsPushMsg();
        $pushMsgData = [
            'from' => $from,
            'runType' => 'cancel',
            'userData'=> $userData,
        ];
        $pushTargetUid = null;
        $extData = [];
        if ($from === 'shuttle_from') { // 上下班行程
            $ShuttleTripModel = new ShuttleTripModel();
            $ShuttleTripServ = new ShuttleTripService();
            $id = $tripData['id'];
            $isDriver = $tripData['user_type'] == 1 ? 1 : 0; // 是否司机
            if ($tripData['user_type'] == 1) { // 如果是司机，先查出乘客，以便推送和清缓存
                $passengers = $ShuttleTripModel->passengers($id);
                $extData['passengers'] = $passengers;
                $pushTargetUid = [];
                foreach ($passengers as $key => $value) {
                    $pushTargetUid[] = $value['uid'];
                }
            }
            $res = $ShuttleTripServ->runRowCancel($tripData, $must); // 执行取消
            if (!$res) {
                $errorData = $ShuttleTripServ->getError();
                return $this->setError($errorData['code'] ?? -1, $errorData['msg'] ?? 'Failed', $errorData['data'] ?? []);
            }
        } elseif ($from === 'wall') { // 普通行程空座位
            $WallModel = new WallModel();
            $InfoModel = new InfoModel();
            $id = $tripData['love_wall_ID'];
            $isDriver = 1; // 是否司机
            Db::connect('database_carpool')->startTrans();
            try {
                $WallModel->cancelWall($tripData);
                $passengerInfoList = $InfoModel->getListByWallId($id); //查出所有程客行程
                if ($passengerInfoList) { // 取消乘客行程
                    $extData['passengers'] = $passengerInfoList;
                    $pushTargetUid = [];
                    foreach ($passengerInfoList as $key => $value) {
                        $InfoModel->cancelInfo($value, $uidOrUData, $must);
                        $pushTargetUid[] = $value['passengerid'];
                    }
                }
                // 提交事务
                Db::connect('database_carpool')->commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::connect('database_carpool')->rollback();
                $errorMsg = $e->getMessage();
                return $this->setError(-1, $errorMsg, []);
            }
        } elseif ($from === 'info') { // info行程
            $InfoModel = new InfoModel();
            $id = $tripData['infoid'];
            if ($uid == $tripData['carownid']) { // 如果是司机
                $isDriver = 1;
                $pushTargetUid = $tripData['passengerid'];
            } elseif ($uid == $tripData['passengerid']) { // 如果是乘客
                $isDriver = 0;
                $pushTargetUid = $tripData['carownid'];
            } else {
                return $this->setError(30001, lang('You cannot cancel this trip that you have not participated in'));
            }
            Db::connect('database_carpool')->startTrans();
            try {
                $res = $InfoModel->cancelInfo($tripData, $uidOrUData, $must);
                if (is_numeric($tripData['love_wall_ID']) && $tripData['love_wall_ID'] > 0) {
                    $took_count = $InfoModel->countPassengers($tripData['love_wall_ID']) ?: 0;
                    if ($took_count === 0) { //如果发现没有有效乘客，则更改空座位状态为0;
                        WallModel::where([["love_wall_ID", '=', $tripData['love_wall_ID']]])->update(["status" => 0]);
                    }
                }
                // 提交事务
                Db::connect('database_carpool')->commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::connect('database_carpool')->rollback();
                $errorMsg = $e->getMessage();
                return $this->setError(-1, $errorMsg, []);
            }
            if (!$res) {
                return $this->setError(-1, 'Failed', []);
            }
        }
        // 清缓存
        $this->delCacheAfterCancel($tripData, $from, $userData, $extData);
        // 推消息设置
        $pushMsgData['tripData'] = $tripData;
        $pushMsgData['id'] = $id;
        $pushMsgData['isDriver'] = $isDriver;
        // $TripsPushMsg->pushMsg($targetUserid, $pushMsgData); // 推消息
        $this->setError(0, 'succefull', ['pushMsgData'=>$pushMsgData, 'pushTargetUid'=>$pushTargetUid]);
        return true;
    }

    /**
     * 取消行程
     *
     * @param mixed $idOrData 行程id或数据
     * @param string $from  来自哪个表: value in ['shuttle_trip', 'wall', 'info]
     * @param mixed $uidOrUData 操作者id或数据
     * @param integer $must
     * @return void
     */
    public function delCacheAfterCancel($tripData, $from, $userData, $extData = [])
    {
        if ($from === 'shuttle_trip') {
            $ShuttleTripServ = new ShuttleTripService(); // 清除上下班缓存
            $ShuttleTripServ->delCacheAfterStatusChange($tripData, $userData, 'cancel', $extData);
        } else { // 清除普通行程缓存
            $TripsService = new TripService();
            $TripsDetailServ = new TripsDetailService();
            $id = $from == 'wall' ? $tripData['love_wall_ID'] : $tripData['infoid'];
            $wall_id = $tripData['love_wall_ID'] ?: 0;
            $actor = $from == 'wall' ? $tripData['carownid'] : [$tripData['carownid'], $tripData['passengerid']];
            $TripsService->removeCache($actor);
            if ($wall_id > 0) {
                $TripsService->delWallCache($wall_id);
            }
            if (isset($extData['passengers']) && !empty($extData['passengers'])) {
                foreach ($extData['passengers'] as $key => $value) {
                    $TripsService->delMyListCache($value['passengerid']);
                    $TripsService->delMyInfosCache($value['passengerid']);
                }
            }
            $TripsDetailServ->delDetailCache($from, $id);
        }
        return true;
    }


    /**
     * 推荐列表
     *
     * @param integer $user_type 1司机，0乘客
     * @param array $userData 用户数据
     * @param integer $type 上下班类型，-2包括上下班，-1包括所有，0普通，1上班，2下班
     * @param array $extData 扩展数据
     * @return array
     */
    public function lists($user_type, $userData = null, $type = -1, $extData = null)
    {
        $Utils = new Utils();
        // 取得班车行程
        $list_shuttle = [
            'lists' => [],
        ];
        $user_type = intval($user_type);
        $limit = ( $extData['limit'] ?? 0 ) ?: 0;
        $lnglat = $extData['lnglat'] ?? null;
        $lnglat = $lnglat ? $Utils->stringSetToArray($lnglat, null, false) : null ;
        $lnglat = is_array($lnglat) && count($lnglat) > 1 ? $lnglat : null;
        $userData =  $userData ?: (( $extData['userData'] ?? null ) ?: null);
        
        if (in_array($type, [-3, -1, -2 ,0, 1, 2])) {
            $ShuttleTripListService = new ShuttleTripListService();
            $lineType = $type < 0 ? -2 : $type;
            $list_shuttle = $ShuttleTripListService->lists($user_type, $userData, $lineType, $extData);
        }
        // 普通行程
        $list_nm = [
            'lists' => [],
        ];
        if (in_array($type, [-3, -4])) {
            $NmTripListService = new NmTripListService();
            $list_nm = $NmTripListService->lists($user_type, $userData, $extData);
        }
        
        $list = array_merge($list_shuttle['lists'], $list_nm['lists']);
        foreach ($list as $key => $value) {
            $value = $this->formatResultValue($value, ['extra_info', 'driver_id', 'startpid', 'endpid']);
            $value['distance'] = 0;
            if ($lnglat) {
                $lineData = $value['line_data'];
                $startPoint = [$lineData['start_longitude'], $lineData['start_latitude']];
                $value['distance'] = $Utils->getDistance($startPoint, $lnglat) ?: 0;
            }
            $list[$key] = $value;
        }
        $list = $Utils->listSort($list, ['line_sort'=>'DESC', 'distance'=>'ASC', 'time'=>'ASC']);
        $returnData = [
            'lists' => $list,
        ];
        return $returnData;
    }

    /**
     * 为混合行程取得合并的表数据的sql;
     *
     * @param integer $uid 用户id
     * @param mixed $statusSet 状态筛选 array [1,2,3]
     * @param array $timeBetween 时间筛选 [timestamp,timestamp]
     * @param array $timeBetweenInfo 时间筛选(旧行程表) [timestamp,timestamp]
     * @return string
     */
    public function buildListUnionSql($uid, $statusSet = [0,1,3,4,5], $timeBetween = null, $timeBetweenInfo = null)
    {
        // 先取shuttle_trip表行程
        $ShuttleTripModel = new ShuttleTripModel();
        $map = [
            ['is_delete', '=', Db::raw(0)]
        ];
        if (!empty($timeBetween) && is_array($timeBetween)) {
            $timeStart = date('Y-m-d H:i:s', $timeBetween[0]);
            $timeEnd = date('Y-m-d H:i:s', $timeBetween[1]);
            if (empty($timeBetween[0])) {
                $map[] = ['time', '<', $timeEnd];
            } elseif (empty($timeBetween[1])) {
                $map[] = ['time', '>', $timeStart];
            } else {
                $map[] = ['time', 'between', [$timeStart, $timeEnd]];
            }
        }
        if (is_array($statusSet)) {
            $map[] = ['status', 'in', $statusSet];
        } elseif (is_numeric($statusSet)) {
            $map[] = ['status', '=', $statusSet];
        } elseif (is_string($statusSet)) {
            $map[] = ['status', 'in', explode(',', $statusSet)];
        } else {
            $map[] = ['status', 'in', [0,1,3,4,5]];
        }
        $map[] = ['uid', '=', $uid];

        // XXX: 因用于Union, 请勿改变字段顺序
        $fields = "'shuttle_trip' as 'from', id, trip_id, status, uid, user_type, comefrom, seat_count";
        $fields .= ', start_id, start_name, start_longitude, start_latitude, end_id, end_name, end_longitude, end_latitude, null as map_type';
        $fields .= ', time, time_offset, create_time, plate,line_type, extra_info';
        $sql_uShuttle = $ShuttleTripModel->alias('a')->field($fields)->where($map)->order('time Desc')->buildSql();

        // 再取wall和info行程
        $InfoModel = new InfoModel();
        $sql_uOld = $InfoModel->buildMixedUnionSql($uid, $statusSet, $timeBetweenInfo);
        
        if (isset($sql_uOld)) {
            return "($sql_uShuttle union $sql_uOld)";
        }
        return $sql_uShuttle;
    }

    /**
     * 格式化结果字段
     */
    public function formatResultValue($value, $unset = [])
    {
        $value_format = $value;
        
        //整理指定字段为整型
        $int_field_array = [
            'u_uid', 'u_sex', 'u_company_id', 'u_department_id',
        ];
        $value = json_decode(json_encode($value), true);
        foreach ($value as $key => $v) {
            if (in_array($key, $int_field_array)) {
                $value_format[$key] = intval($v);
            }
            if (!empty($unset) && in_array($key, $unset)) {
                unset($value_format[$key]);
            }
        }
        if (isset($value['u_imgpath']) && trim($value['u_imgpath']) == "") {
            $value_format['u_imgpath'] = 'default/avatar.png';
        }
        return $value_format;
    }

    /**
     * 检查是否已过出发时间
     *
     * @param integer $time 行程出发时间的时间戳
     * @return integer 0 未出发，大于0为已出发，1为刚出发不久;
     */
    public function haveStartedCode($time, $time_offset = 0)
    {
        $haveStart = 0;
        $timePass = time() - ($time + $time_offset);
        if ($timePass > 60 * 60 * 24) {
            $haveStart = 4;
        } elseif ($timePass > 30 * 60) {
            $haveStart = 3;
        } elseif ($timePass > 5 * 60) {
            $haveStart = 2;
        } elseif ($timePass >= 0) {
            $haveStart = 1;
        }
        return $haveStart;
    }

    /**
     * 取得乘客数
     *
     * @param integer $id 行程id
     * @param string $from 来自 shuttle_trip or wall or info
     * @return integer
     */
    public function countPassengers($id, $from, $ex = 10)
    {
        $count = 0;
        if ($from == 'shuttle_trip') {
            $count = $this->getModel('ShuttleTrip', 'carpool')->countPassengers($id, $ex);
        } elseif ($from == 'wall') {
            $count =  $this->getModel('Info', 'carpool')->countPassengers($id, $ex);
        } elseif ($from == 'info') {
            $count = 1;
        }
        return $count ?: 0;
    }

    /**
     * 清理用户要上传Gps的info_id缓存
     *
     * @param integer $uid 用户id
     */
    public function delUpGpsInfoidCache($uid)
    {
        if (is_array($uid)) {
            foreach ($uid as $key => $value) {
                $this->delUpGpsInfoidCache($value);
            }
            return true;
        }
        $cacheKey =  "carpool:info_id:{$uid}";
        return $this->redis()->del($cacheKey);
    }
}
