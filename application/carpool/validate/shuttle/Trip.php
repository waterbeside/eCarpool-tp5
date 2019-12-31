<?php
namespace app\carpool\validate\shuttle;

use app\common\validate\Base;
use app\carpool\model\ShuttleTrip;

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
        if (in_array($tripData['status'], [-1, 3])) {
            return $this->setError(-1, lang('The trip has been completed or cancelled. Operation is not allowed'));
        }
        // 断定是否自己上自己车
        if ($userData['uid'] == $tripData['uid']) {
            return $this->setError(-1, lang('You can`t take your own'));
        }
        if ($tripData['trip_id'] > 0) { // 检查对方是否已被其它司机搭了
            return $this->setError(50010, '你慢了一步，该乘客被其他司机抢去!');
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
        if (in_array($tripData['status'], [-1, 3])) {
            return $this->setError(-1, lang('The trip has been completed or cancelled. Operation is not allowed'));
        }
        if ($tripData['user_type'] != 1) {
            return $this->setError(992, lang('只有司机才可以改变行程座位数'));
        }
        if ($tripData['uid'] != $uid) {
            return $this->setError(30001, lang('你不能操作别人的行程'));
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
}
