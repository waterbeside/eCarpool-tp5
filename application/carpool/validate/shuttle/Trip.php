<?php
namespace app\carpool\validate\shuttle;

use app\common\validate\Base;
use app\carpool\model\ShuttleTrip;
use app\carpool\model\ShuttleTripPartner;
use app\carpool\service\TripsMixed;
use my\Utils;

class Trip extends Base
{
    protected $rule = [
    ];

    protected $message = [
    ];

    /**
     * 验证司机接客
     *
     * @param array $tripData 乘客行程数据
     * @param array $userData 司机用户信息
     * @return mixed tripData
     */
    public function checkPickup($tripData, $userData)
    {
        if (empty($tripData)) {
            return $this->jsonReturn(20002, lang('The trip does not exist'));
        }
        if ($tripData['user_type'] == 1) {
            return $this->setError(20002, lang('The trip does not exist'));
        }
        //检查是否已取消或完成
        if (in_array($tripData['status'], [-1, 3, 4, 5])) {
            return $this->setError(-1, lang('The trip has been completed or cancelled. Operation is not allowed'));
        }
        // 断定是否自己上自己车
        if ($userData['uid'] == $tripData['uid']) {
            return $this->setError(-1, lang('You can`t take your own'));
        }
        if ($tripData['trip_id'] > 0) { // 检查对方是否已被其它司机搭了
            return $this->setError(50010, lang('You are too slow, the passenger was snatched by another driver'));
        }
        //检查出发时间是否已经过了
        $TripsMixed = new TripsMixed();
        $time = strtotime($tripData['time']);
        $haveStarted = $TripsMixed->haveStartedCode($time, $tripData['time_offset']);
        if ($haveStarted > 1) {
            return $this->setError(30007, lang('The trip has been going on for a while. Operation is not allowed'));
        }
        if ($tripData['seat_count'] > 1) {
            $ShuttleTripPartner = new ShuttleTripPartner();
            $partners = $ShuttleTripPartner->getPartners($tripData['id'], null) ?? [];
            // 如果有同行者，检查司机是否在同行者中
            if ($this->checkDriverNotInPartners($partners, $userData['uid'], $userData['uid']) === false) {
                return false;
            }
        }
        return $tripData;
    }

    /**
     * 验证发布行程
     *
     * @param array $rqData 请求的数据
     * @return mixed rqData
     */
    public function checkSave($rqData)
    {
        if (!$rqData['create_type']) {
            return $this->setError(992, 'Empty create_type');
        }
        if (!in_array($rqData['create_type'], ['cars', 'requests'])) {
            return $this->setError(992, 'Error create_type');
        }
        return $rqData;
    }

    /**
     * 验证乘客上车
     *
     * @param array $tripData 司机行程数据
     * @param array $userData 乘客用户信息
     * @return mixed tripData
     */
    public function checkHitchhiking($tripData, $userData)
    {
        if (empty($tripData)) {
            return $this->setError(20002, lang('The trip does not exist'));
        }
        if ($tripData['user_type'] != 1) {
            return $this->setError(20002, lang('The trip does not exist'));
        }
        //检查是否已取消或完成
        if (in_array($tripData['status'], [-1, 3, 4, 5])) {
            return $this->setError(30001, lang('The trip has been completed or cancelled. Operation is not allowed'));
        }
        // 断定是否自己上自己车
        if ($userData['uid'] == $tripData['uid']) {
            return $this->setError(-1, lang('You can`t take your own'));
        }
        //检查出发时间是否已经过了
        $time = strtotime($tripData['time']);
        $TripsMixed = new TripsMixed();
        $haveStarted = $TripsMixed->haveStartedCode($time, $tripData['time_offset']);
        if ($haveStarted > 1) {
            return $this->setError(30007, lang('The trip has been going on for a while. Operation is not allowed'));
        }
        // 检查是否已经是乘客成员之一
        $ShuttleTrip = new ShuttleTrip();
        $checkInTripRes = $ShuttleTrip->inTrip($userData['uid'], $tripData);
        if ($checkInTripRes) {
            return $this->setError(50006, lang('You have joined the trip'), $checkInTripRes);
        }
        // 检查座位是否已满
        $took_count = $ShuttleTrip->countPassengers($tripData['id'], false); //计算已坐车乘客数
        if ($took_count >= $tripData['seat_count']) {
            $returnData = [
                'seat_count' => $tripData['seat_count'],
                'took_count' => $took_count,
            ];
            return $this->setError(50003, lang('Failed, seat is full'), $returnData);
        }
        return $tripData;
    }

    /**
     * 检查“改变行程座位数”
     *
     * @param array $rqData 请求数据
     * @param array $tripData 行程数据
     * @param integer $uid 操作者用户id
     * @return mixed
     */
    public function checkChangeSeat($rqData, $tripData, $uid)
    {
        //检查是否已取消或完成
        if (in_array($tripData['status'], [-1, 3, 4, 5])) {
            return $this->setError(-1, lang('The trip has been completed or cancelled. Operation is not allowed'));
        }
        if ($tripData['uid'] != $uid) {
            return $this->setError(30001, lang('You can not operate someone else`s trip'));
        }
        if ($tripData['user_type'] != 1) {
            return $this->setError(992, lang('Only the driver can change the number of seats'));
        }
        $TripsMixed = new TripsMixed();
        $haveStarted = $TripsMixed->haveStartedCode(strtotime($tripData['time']), $tripData['time_offset']);
        if ($haveStarted > 1) {
            return $this->setError(30007, lang('The trip has been going on for a while. Operation is not allowed'));
        }
        if ($rqData['seat_count'] < 1) {
            return $this->setError(992, lang('The number of empty seats cannot be empty'));
        }
        $ShuttleTrip = new ShuttleTrip();
        $took_count = $ShuttleTrip->countPassengers($rqData['id'], false); //计算已坐车乘客数
        if ($rqData['seat_count'] < $took_count) {
            return $this->setError(992, lang('The number of seats you set cannot be less than the number of passengers on your trip'));
        }
        return $tripData;
    }

    /**
     * 检查“改变行程车牌”
     *
     * @param array $rqData 请求数据
     * @param array $tripData 行程数据
     * @param integer $uid 操作者用户id
     * @return mixed
     */
    public function checkChangePlate($rqData, $tripData, $uid)
    {
        //检查是否已取消或完成
        if (in_array($tripData['status'], [-1, 3, 4, 5])) {
            return $this->setError(-1, lang('The trip has been completed or cancelled. Operation is not allowed'));
        }
        if ($tripData['uid'] != $uid) {
            return $this->setError(30001, lang('You can not operate someone else`s trip'));
        }
        if ($tripData['user_type'] != 1) {
            return $this->setError(992, lang('Only the driver can change the number of seats'));
        }
        $TripsMixed = new TripsMixed();
        $haveStarted = $TripsMixed->haveStartedCode(strtotime($tripData['time']), $tripData['time_offset']);
        if ($haveStarted > 1) {
            return $this->setError(30007, lang('The trip has been going on for a while. Operation is not allowed'));
        }
        if (empty(trim($rqData['plate']))) {
            return $this->setError(992, lang('License plate number cannot be empty'));
        }
        return $tripData;
    }

    

    /**
     * 验证合并行程的数据
     *
     * @param array $tripData 自己的行程数据
     * @param array $targetTripData 对方的行程数据
     * @param array $userData 操作人的用户信息
     */
    public function checkMerge($tripData, $targetTripData, $userData)
    {
        if (!$tripData) {
            return $this->setError(20002, lang('No data'));
        }
        if ($tripData['uid'] != $userData['uid']) {
            return $this->setError(30001, lang('You can not operate someone else`s trip'));
        }
        if (in_array($tripData['status'], [-1, 3, 4, 5])) {
            return $this->setError(50015, lang('The trip has been completed or cancelled. Operation is not allowed'));
        }
        $TripsMixed = new TripsMixed();
        // 检查出发时间
        $haveStarted = $TripsMixed->haveStartedCode(strtotime($tripData['time']), $tripData['time_offset']);
        if ($haveStarted > 2) {
            return $this->setError(30007, lang('The trip has been going on for a while. Operation is not allowed'));
        }
        // 检查对方行程是否存在
        if (!$targetTripData) {
            return $this->setError(20002, lang('The trip does not exist'));
        }
        // 检查对方行程状态
        if (in_array($targetTripData['status'], [-1, 3, 4, 5])) {
            return $this->setError(50005, lang('The trip has been completed or cancelled. Operation is not allowed'));
        }

        // 检查对方行程出发时间
        $haveStarted2 = $TripsMixed->haveStartedCode(strtotime($targetTripData['time']), $tripData['time_offset']);
        if ($haveStarted2 > 2) {
            return $this->setError(30007, lang('The trip has been going on for a while. Operation is not allowed'));
        }
        
        // 检查两起点是否相差太远
        // $Utils = new Utils();
        // $radius = config('trips.trip_matching_radius') ?? 500;
        // $startDistance = $Utils->getDistance($tripData['start_longitude'], $tripData['start_latitude'], $targetTripData['start_longitude'], $targetTripData['start_latitude']);
        // $endDistance = $Utils->getDistance($tripData['end_longitude'], $tripData['end_latitude'], $targetTripData['end_longitude'], $targetTripData['end_latitude']);
        // if ($startDistance > $radius || $endDistance > $radius) {
        //     return $this->setError(50011, lang('The route of trip is different from yours'));
        // }

        $ShuttleTrip = new ShuttleTrip();
        // 区分司机程客行程
        $driverTripData = $tripData['user_type'] == 1 ? $tripData : $targetTripData;
        $passengerTripData = $tripData['user_type'] == 1 ?  $targetTripData : $tripData;
        
        // 检查是否司机和乘客问的匹配
        if ($tripData['user_type'] == 1) { // 如果自己是司机
            if ($targetTripData['user_type'] != 0 || $targetTripData['comefrom'] != 2) { // 检查对方是否一个约车需求
                return $this->setError(992, 'This trip is not a passenger`s trip, and cannot merge');
            }
            if ($targetTripData['trip_id'] > 0) { // 检查对方是否已被司机搭了
                return $this->setError(50010, lang('You are too slow, the passenger was snatched by another driver'));
            }
        } else { // 如果自己是乘客
            if ($driverTripData['user_type'] != 1) { // 检查对方是否司机
                return $this->setError(992, 'This trip is not a driver`s trip, and cannot merge');
            }
            // 检查自己是否已有司机搭了
            if ($tripData['trip_id'] > 0) {
                if ($tripData['trip_id'] == $driverTripData['id']) {
                    return $this->setError(50013, lang('You have already taken this trip'));
                } else {
                    return  $this->setError(50013, lang('You have been picked up by another trip'));
                }
            }
        }
        // 检查乘客UID是否已经搭上过司机行程
        $checkInTripRes = $ShuttleTrip->inTrip($passengerTripData['uid'], $driverTripData);
        if ($checkInTripRes) {
            if ($tripData['user_type'] == 1) { // 如果我是司机
                return $this->setError(50016, lang('This user is already on the passenger list for your current trip'), $checkInTripRes);
            } else {
                return $this->setError(50016, lang('You are already a passenger on this trip'), $checkInTripRes);
            }
        }

        
        // 检查座位是否已满
        $took_count = $ShuttleTrip->countPassengers($driverTripData['id'], false); //计算已坐车乘客数
        if ($took_count >= $driverTripData['seat_count'] || $took_count + $passengerTripData['seat_count'] > $driverTripData['seat_count']) {
            $returnData = [
                'seat_count' => $tripData['seat_count'],
                'took_count' => $took_count,
            ];
            return $this->setError(50003, $returnData, lang('Not enough seats'));
        }



        if ($passengerTripData['seat_count'] > 1) {
            $ShuttleTripPartner = new ShuttleTripPartner();
            $partners = $ShuttleTripPartner->getPartners($passengerTripData['id'], null) ?? [];
            // 如果有同行者，检查司机是否在同行者中
            if ($this->checkDriverNotInPartners($partners, $driverTripData['id'], $userData['uid']) === false) {
                return false;
            }
        }

        return true;
    }


    /**
     * 检查司机是否该约车需求的同行者
     *
     * @param mixed $partners 同行者列表
     * @param integer $driver_uid 司机uid
     * @param integer $uid 操作者uid
     * @return void
     */
    public function checkDriverNotInPartners($partners, $driver_uid, $uid)
    {
        if (empty($partners)) {
            return true;
        }
        $partnerIds = [];
        foreach ($partners as $key => $value) {
            $partnerIds[] = $value['uid'];
        }
        if (in_array($driver_uid, $partnerIds)) {
            $msg = $uid == $driver_uid ? lang('You are among this passenger`s partner, so you cannot add yourself as a passenger')
                : lang('The driver is on your list of travel partners, so the driver cannot add himself as a passenger');
            return $this->setError(-1, $msg);
        }
        return $partnerIds;
    }
}
