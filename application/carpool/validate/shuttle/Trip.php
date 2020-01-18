<?php
namespace app\carpool\validate\shuttle;

use app\common\validate\Base;
use app\carpool\model\ShuttleTrip;
use app\carpool\model\ShuttleTripPartner;
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
            return $this->jsonReturn(20002, '该行程不存在');
        }
        if ($tripData['user_type'] == 1) {
            return $this->setError(-1, lang('该行程不存在'));
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
            return $this->setError(50010, '你慢了一步，该乘客被其他司机抢去!');
        }
        if ($tripData['seat_count'] > 1) {
            $ShuttleTripPartner = new ShuttleTripPartner();
            $partners = $ShuttleTripPartner->getPartners($tripData['id'], 1) ?? [];
            // 如果有同行者，检查司机是否在同行者中
            if (!$this->checkDriverInPartners($partners, $userData['uid'], $userData['uid'])) {
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
            return $this->setError(20002, '该行程不存在');
        }
        if ($tripData['user_type'] != 1) {
            return $this->setError(20002, '该行程不存在');
        }
        //检查是否已取消或完成
        if (in_array($tripData['status'], [-1, 3])) {
            return $this->setError(30001, lang('The trip has been completed or cancelled. Operation is not allowed'));
        }
        // 断定是否自己上自己车
        if ($userData['uid'] == $tripData['uid']) {
            return $this->setError(-1, lang('You can`t take your own'));
        }
        // 检查是否已经是乘客成员之一
        $ShuttleTrip = new ShuttleTrip();
        $checkInTripRes = $ShuttleTrip->checkInTrip($tripData, $userData['uid']);
        if ($checkInTripRes) {
            return $this->setError(50006, lang('您已经搭上该趟车'), $checkInTripRes);
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
        if ($tripData['user_type'] != 1) {
            return $this->setError(992, lang('只有司机才可以改变行程座位数'));
        }
        if ($tripData['uid'] != $uid) {
            return $this->setError(30001, lang('你不能操作别人的行程'));
        }
        if (time() - strtotime($tripData['time']) > 20 * 60) {
            return $this->setError(30007, lang('你的行程已经开始一段时间了，无法操作'));
        }
        if ($rqData['seat_count'] < 1) {
            return $this->setError(992, lang('The number of empty seats cannot be empty'));
        }
        $ShuttleTrip = new ShuttleTrip();
        $took_count = $ShuttleTrip->countPassengers($rqData['id'], false); //计算已坐车乘客数
        if ($rqData['seat_count'] < $took_count) {
            return $this->setError(992, lang('您设置的座位数不能比已搭乘客数少'));
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
        if ($tripData['user_type'] != 1) {
            return $this->setError(992, lang('只有司机才可以改变行程座位数'));
        }
        if ($tripData['uid'] != $uid) {
            return $this->setError(30001, lang('你不能操作别人的行程'));
        }
        if (time() - strtotime($tripData['time']) > 20 * 60) {
            return $this->setError(30007, lang('你的行程已经开始一段时间了，无法操作'));
        }
        if (empty(trim($rqData['plate']))) {
            return $this->setError(992, lang('车牌号不能为空'));
        }
        $ShuttleTrip = new ShuttleTrip();
        $took_count = $ShuttleTrip->countPassengers($rqData['id'], false); //计算已坐车乘客数
        if ($rqData['seat_count'] < $took_count) {
            return $this->setError(992, lang('您设置的座位数不能比已搭乘客数少'));
        }
        return $tripData;
    }

    

    /**
     * 验证合并行程的数据
     *
     * @param array $tripData 自己的行程数据
     * @param array $targetTripData 对方的行程数据
     * @param array $userData 乘客用户信息
     */
    public function checkMerge($tripData, $targetTripData, $userData)
    {
        if (!$tripData) {
            return $this->setError(20002, 'No data');
        }
        if ($tripData['uid'] != $userData['uid']) {
            return $this->setError(30001, lang('你不能操作把别人的行程合并到别人的行程'));
        }
        if (in_array($tripData['status'], [-1, 3, 4, 5])) {
            return $this->setError(30001, lang('你的行程已取消或完结，无法操作'));
        }
        if (time() - strtotime($tripData['time']) > 20 * 60) {
            return $this->setError(30007, lang('你的行程已经开始一段时间了，无法操作'));
        }
        if (!$targetTripData) {
            return $this->setError(20002, '目标行程不存在');
        }
        if (in_array($targetTripData['status'], [-1, 3])) {
            return $this->setError(30001, lang('对方的行程已取消或完结，无法操作'));
        }
        if ($tripData['line_id'] != $targetTripData['line_id']) {
            return $this->setError(50011, lang('对方路线与你的不一致'));
        }
        $driverTripData = $tripData['user_type'] == 1 ? $tripData : $targetTripData;
        $passengerTripData = $tripData['user_type'] == 1 ?  $targetTripData : $tripData;

        $ShuttleTrip = new ShuttleTrip();
        // 检查座位是否已满
        $took_count = $ShuttleTrip->countPassengers($driverTripData['id'], false); //计算已坐车乘客数
        if ($took_count >= $driverTripData['seat_count'] || $took_count + $passengerTripData['seat_count'] > $driverTripData['seat_count']) {
            $returnData = [
                'seat_count' => $tripData['seat_count'],
                'took_count' => $took_count,
            ];
            return $this->setError(50003, $returnData, lang('空座位数不够'));
        }

        if ($passengerTripData['seat_count'] > 1) {
            $ShuttleTripPartner = new ShuttleTripPartner();
            $partners = $ShuttleTripPartner->getPartners($passengerTripData['id'], 1) ?? [];
            // 如果有同行者，检查司机是否在同行者中
            if (!$this->checkDriverInPartners($partners, $driverTripData['id'], $userData['uid'])) {
                return false;
            }
        }

        if ($tripData['user_type'] == 1) { // 如果自己是司机
            if ($targetTripData['user_type'] != 0 || $targetTripData['comefrom'] != 2) { // 检查对方是否一个约车需求
                return $this->setError(992, '对方行程不约车需求行程，无法合并');
            }
            if ($targetTripData['trip_id'] > 0) { // 检查对方是否已被司机搭了
                return $this->setError(-1, '你慢了一步，该乘客被其他司机抢去!');
            }
        } else { // 如果是乘客
            if ($targetTripData['user_type'] != 1) { // 检查对方是否司机
                return $this->setError(992, '对方行程不是司机行程，无法合并');
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
    public function checkDriverInPartners($partners, $driver_uid, $uid)
    {
        $partnerIds = [];
        foreach ($partners as $key => $value) {
            $partnerIds[] = $value['uid'];
        }
        if (in_array($driver_uid, $partnerIds)) {
            $msg = $uid == $driver_uid ? "对方把你作为同行乘客，你无法添加自己作为乘客" : "该司机是你发布需求时添加的一名乘客，司机无法自己搭自已";
            return $this->setError(-1, $msg);
        }
        return $partnerIds;
    }
}
