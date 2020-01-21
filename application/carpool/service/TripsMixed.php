<?php

namespace app\carpool\service;

use app\common\service\Service;
use app\carpool\service\shuttle\Trip as ShuttleTripService;
use app\carpool\service\Trips as TripService;
use app\carpool\service\TripsDetail as TripsDetailService;
use app\carpool\model\ShuttleTrip as ShuttleTripModel;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\model\Address;
use app\carpool\model\Configs as ConfigsModel;
use app\carpool\model\User as UserModel;
use think\Db;
use my\Utils;
use Wall;

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
            return $this->setError(20002, 'No trip data');
        }
        if ($from === 'shuttle_from') { // 上下班行程
            if ($uid != $tripData['uid']) {
                return $this->setError(30001, lang('你无权取消与自己无关的行程'));
            }
        } elseif ($from === 'wall') { // 普通行程空座位
            if ($uid != $tripData['carownid']) {
                return $this->setError(30001, lang('你无权取消与自己无关的行程'));
            }
        } elseif ($from === 'info') { // info行程
            if ($uid != $tripData['passengerid'] && $uid != $tripData['carownid']) {
                return $this->setError(30001, lang('你无权取消与自己无关的行程'));
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
            return $this->setError(992, 'Error Param');
        }
        // 取用户数据
        $userData = is_numeric($uidOrUData) ? (new UserModel())->getItem($uidOrUData) : $uidOrUData;
        $uid = $userData['uid'];
        // 取行程数据
        $tripData = is_numeric($idOrData) ? $this->getTripItem($idOrData, $from) : $idOrData;
        if (!$tripData) {
            return $this->setError(20002, 'No trip data');
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
                return $this->setError(30001, lang('你无权取消与自己无关的行程'));
            }
            Db::connect('database_carpool')->startTrans();
            try {
                $res = $InfoModel->cancelInfo($tripData, $uidOrUData, $must);
                if (is_numeric($tripData['love_wall_ID']) && $tripData['love_wall_ID'] > 0) {
                    $took_count = $InfoModel->countPassengers($tripData['love_wall_ID']) ?: 0;
                    if ($took_count === 0) { //如果发现没有有效乘客，则更改空座位状态为0;
                        WallModel::where(["love_wall_ID", '=', $tripData['love_wall_ID']])->update(["status" => 0]);
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
}
