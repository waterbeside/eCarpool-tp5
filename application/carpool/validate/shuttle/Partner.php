<?php
namespace app\carpool\validate\shuttle;

use app\common\validate\Base;
use app\carpool\model\ShuttleTrip;
use app\carpool\model\ShuttleTripPartner;
use app\carpool\service\shuttle\Partner as ShuttlePartnerServ;
use app\carpool\service\Trips as TripsService;

use my\Utils;

class Partner extends Base
{
    protected $rule = [
    ];

    protected $message = [
    ];

    

    /**
     * 验证添加同行者
     *
     * @param array $rqData 请求的数据
     * @return mixed rqData
     */
    public function checkSave($rqData, $tripData, $userData)
    {
        if (empty($tripData)) {
            return $this->setError(20002, '该行程不存在');
        }
        //检查是否已取消或完成
        if (in_array($tripData['status'], [-1, 3, 4, 5])) {
            return $this->setError(30001, lang('The trip has been completed or cancelled. Operation is not allowed'));
        }
        // 检查行程是否一个约车需求
        if ($tripData['user_type'] == 0 && $tripData['comefrom'] == 2) {
            return $this->setError(20002, '该约车需求不存在');
        }
        // 检查该约车需求是否已经被司机接了
        if ($tripData['trip_id'] > 0) {
            return $this->setError(-1, '该约车需求已被司机接上，不能添加同行者');
        }
        return $rqData;
    }

    /**
     * 检查用户在某时间内是否加入过同行者以及添加过行程
     *
     * @param array $partners_uData 要检查的用户列表
     * @param integer $time 要检查的时间的时间戳
     * @return boolean
     */
    public function checkRepetition($partners_uData, $time)
    {
        $time = is_numeric($time) ? $time : strtotime($time);
        // 先检查有没有重复的行程
        $TripsService = new TripsService();
        $PartnerServ = new ShuttlePartnerServ();
        // 检查重复行程
        $errorPartners = [];
        $hasError = 0;
        $errorMsg = '';
        foreach ($partners_uData as $key => $value) {
            // 验证重复行程
            $repetitionList = $TripsService->getRepetition($time, $value['uid']);
            $partners_uData[$key]['repetitionList'] = $repetitionList ?? [];
            if ($repetitionList) {
                $hasError = 50009;
                $errorMsg = lang('添加失败，"{:name}"在相近时间内有一个或多个行程', ['name'=>$value['name']]);
                $errorPartners[] = $partners_uData[$key];
                break;
            }
            // 检查重复参与同行者
            $repPartnerList = $PartnerServ->getRepetition($time, $value['uid']);
            $partners_uData[$key]['repPartnerList'] = $repPartnerList ?? [];
            if ($repetitionList) {
                $hasError = 50009;
                $errorMsg = lang('添加失败，"{:name}"在相近时间内已被其它人添加过作为同行者', ['name'=>$value['name']]);
                $errorPartners[] = $partners_uData[$key];
                break;
            }
        }
        if ($hasError > 0) {
            return $this->setError($hasError, $errorMsg, ['lists'=>$errorPartners]);
        }
        return true;
    }

    /**
     * 检查删除同行者信息
     *
     * @param array $idOrData 同行者记录的id或数据
     * @param array $userData 司机用户信息
     * @return array  返回同行者数据行信息
     */
    public function checkDel($idOrData, $userData)
    {
        $uid = $userData['uid'];
        $ShuttleTripPartner = new ShuttleTripPartner();
        $itemData = is_numeric($idOrData) ? $ShuttleTripPartner->find($idOrData) : $idOrData;
        if (empty($itemData)) {
            return $this->setError(20002, lang('同行者不存在，或已删除'));
        }
        if ($itemData['uid'] != $uid || $itemData['creater_id'] != $uid) {
            return $this->setError(30001, lang('你无限操作与自己无关的行程'));
        }
        $id = $itemData['id'];
        if ($itemData['is_delete'] === 1) {
            return $this->setError(0, 'Successful');
        }
        $ShuttleTrip = new ShuttleTrip();
        $tripData = $ShuttleTrip->getItem($itemData['trip_id']);
        // 检查该约车需求是否已经被司机接了
        if ($tripData['trip_id'] > 0) {
            return $this->setError(-1, lang('该约车需求已被司机接上，不能修改同行者'));
        }
        return $itemData;
    }

}
