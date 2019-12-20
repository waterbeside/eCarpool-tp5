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
use my\Utils;
use think\Db;

class ShuttleTrip extends Service
{

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
            $trip_id = 0;
            $plate = $userData['carnumber'];
            if (!isset($rqData['seat_count']) || $rqData['seat_count'] < 1) {
                $this->error(992, lang('The number of empty seats cannot be empty'));
            }
        } elseif ($rqData['create_type'] === 'requests') { // 发布约车需求
            $comefrom = 2;
            $userType = 0;
            $trip_id = 0;
            $plate = '';
        } elseif ($rqData['create_type'] === 'hitchhiking') { // 乘客从空座位搭车
            $comefrom = 3;
            $userType = 0;
            $plate = '';
        } elseif ($rqData['create_type'] === 'pickup') { // 司机从约车需求拉客
            $comefrom = 1; // 不再设为4，只要是接客都会自动发空座位;
            $userType = 1;
            $plate = $userData['carnumber'];
            $rqData['seat_count'] = isset($rqData['seat_count']) && $rqData['seat_count'] > 1 ? $rqData['seat_count'] : 1;
        } else {
            return $this->error(992, 'Error create_type');
        }

        $TripsService = new TripsService();
        $repetitionList = $TripsService->getRepetition($rqData['time'], $uid);
        if ($repetitionList) {
            $errorData = $TripsService->getError();
            $this->error($errorData['code'], $errorData['msg'], ['lists'=>$repetitionList]);
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
            'trip_id' => $trip_id,
            'uid' => $uid,
            'myType' => 'my',
        ];

        $ShuttleTripModel->delCacheAfterAdd($cData);
        return $newid;
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
            $itemData = $ShuttleTripModel->getItem($id);
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

    
    public function getSimilarTrips($line_id, $time = 0, $userType = false, $uid = 0, $timeOffset = [60*15, 60*15])
    {
        $tripFields = ['id', 'time', 'create_time', 'status', 'user_type', 'comefrom','line_id'];
        $time = $time ?: time();
        if (is_numeric($timeOffset)) {
            $timeOffset = [$timeOffset, $timeOffset];
        }
        $start_time = $time - $timeOffset[0];
        $end_time = $time + $timeOffset[1];
        $map = [
            ['t.line_id', '=', $line_id],
            ['t.status', 'between', [0,1]],
            ['t.time', 'between', [$start_time, $end_time]],
        ];
        if ($userType === 1) {
            $map[] = ['t.user_type', '=', 1];
            $map[] = ['t.comefrom', '=', 1];
        } elseif ($userType === 2) {
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
            $userFields =[
                'uid','loginname','name','nativename','phone','mobile','Department',
                'sex','company_id','department_id','imgpath','carcolor', 'im_id'
            ];
            $User = new UserModel();
            $userData = $User->findByUid($uid);
            $userData = Utils::filterDataFields($userData, $userFields, false, 'u_', -1);
            $defaultUserFields = [
                'uid', 'loginname', 'name','nativename', 'phone', 'mobile', 'Department', 'sex',
                'company_id', 'department_id', 'companyname', 'imgpath', 'carcolor', 'im_id'
            ];
        }
        if ($uid < 0) {
            $map[] = ['t.uid', '<>', -1 * $uid];
            $join = [
                ['user u', 'u.uid = t.uid', 'left'],
            ];
        }
    }
}
