<?php
namespace app\carpool\service;

use app\common\service\Service;
use app\carpool\model\user as UserModel;
use app\carpool\model\ShuttleLine as ShuttleLineModel;
use app\carpool\model\ShuttleTrip as ShuttleTripModel;
use app\carpool\service\Trips as TripsService;
use app\carpool\model\ShuttleLineDepartment;
use app\user\model\Department;
use my\RedisData;
use think\Db;

class ShuttleTrip extends Service
{
    /**
     * 发布行程
     *
     * @param array $rqData 请求参数
     * @param mixed $uid uid or userData
     * @return void
     */
    public function addTrip($rqData, $uid)
    {
        if (empty($rqData['time'])) {
            $this->error(992, '请选择时间');
        }
        //检查出发时间是否已经过了
        if (time() > $rqData['time']) {
            return $this->error(992, lang("The departure time has passed. Please select the time again"));
        }
        // 取得用户信息
        if (is_numeric($uid)) {
            $userModel = new UserModel();
            $userData = $userModel->findByUid($uid);
        } else {
            $userData = $uid;
            $uid = $userData['uid'];
        }
        $trip_id = isset($rqData['trip_id']) && is_numeric($rqData['trip_id']) ? $rqData['trip_id'] : 0;
        // 添加设置数据
        if ($rqData['create_type'] === 'cars') { // 发布空座位
            $comefrom = 1;
            $userType = 1;
            $plate = $userData['carnumber'];
            if (!isset($rqData['seat_count']) || $rqData['seat_count'] < 1) {
                $this->error(992, '座位数不能少于一');
            }
        } elseif ($rqData['create_type'] === 'requests') { // 发布约车需求
            $comefrom = 2;
            $userType = 0;
            $plate = '';
        } elseif ($rqData['create_type'] === 'hitchhiking') { // 乘客从空座位搭车
            $comefrom = 3;
            $userType = 0;
            $plate = '';
        } elseif ($rqData['create_type'] === 'pickup') { // 司机从约车需求拉客
            $comefrom = 4;
            $userType = 1;
            $plate = $userData['carnumber'];
        } else {
            return $this->error(992, 'Error create_type');
        }

        $TripsService = new TripsService();
        $repetitionList = $TripsService->getRepetition($rqData['time'], $uid);
        if ($repetitionList) {
            $errorData = $TripsService->getError();
            $this->error($errorData['code'], $errorData['msg'], $repetitionList);
            return false;
        }

        // 创建入库数据
        $updata = [
            'line_id' => $rqData['line_id'],
            'time' => date('Y-m-d H:i:s', $rqData['time']),
            'uid' => $uid,
            'user_type' => $userType,
            'comefrom' => $comefrom,
            'trip_id' => $trip_id,
            'plate' => $plate,
            'status' => 0,
            'seat_count' => intval($rqData['seat_count']),
        ];
        if (isset($rqData['lineData']) && is_array($rqData['lineData'])) {
            $updata['info'] = json_encode($rqData['lineData']);
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
            'trip_id' => $rqData['trip_id'],
            'uid' => $rqData['uid'],
        ];

        $ShuttleTripModel->delCacheAfterAdd($cData);
        return $newid;
    }
}
