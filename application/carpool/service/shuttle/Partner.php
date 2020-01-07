<?php
namespace app\carpool\service\shuttle;

use app\common\service\Service;
use app\carpool\service\shuttle\Trip as ShuttleTripService;
use app\carpool\model\User as UserModel;
use app\carpool\model\ShuttleTripPartner;
use app\carpool\model\ShuttleTrip;
use app\carpool\service\TripsPushMsg;

use my\RedisData;
use my\Utils;
use think\Db;

class Partner extends Service
{

    public $defaultUserFields = [
        'uid', 'loginname', 'name','nativename', 'phone', 'mobile', 'Department', 'sex',
        'company_id', 'department_id', 'companyname', 'imgpath', 'carcolor', 'im_id'
    ];


    /**
     * 预处理接收到的partnerid数据
     *
     * @param mixed $data
     * @return array
     */
    public function partnerIdToArray($data)
    {
        $data = Utils::getInstance()->stringSetToArray($data, 'intval');
        return $data;
    }


    /**
     * 通过partners用户id列，取得用户信息列表
     *
     * @param mixed $ids [uid,uid];
     * @param array $exclude 被排除的uid
     * @return array
     */
    public function getPartnersUserData($uids, $exclude = [])
    {
        $uids = Utils::getInstance()->stringSetToArray($uids, 'intval');
        $exclude = Utils::getInstance()->stringSetToArray($exclude, 'intval');
        $returnData = [];
        $UserModel = new UserModel();
        foreach ($uids as $key => $value) {
            if (in_array($value, $exclude)) {
                continue;
            }
            $userData = $UserModel->getItem($value, $this->defaultUserFields);
            if ($userData) {
                $returnData[] = $userData;
            }
        }
        return $returnData;
    }

    
    /**
     * 生成插入同伴表的批量数据
     *
     * @param array $partners 同伴的用户信息列表
     * @param array $tripData 行程基本信息, $rqData['line_data']
     * @return array
     */
    public function buildInsertBatchData($partners, $tripData)
    {
        $defaultData = [
            'status' => 0,
            'is_delete' => 0,
            'creater_id' => $tripData['uid'],
            'line_type' => $tripData['line_type'],
            'trip_id' => $tripData['id'],
            'time' => $tripData['time']
        ];
        $batchDatas = [];
        foreach ($partners as $key => $value) {
            $upUserData = [
                'uid' => $value['uid'],
                'name' => $value['name'],
                'department_id' => $value['department_id'],
                'sex' => $value['sex'],
            ];
            $batchDatas[] = array_merge($defaultData, $upUserData);
        }
        return $batchDatas;
    }

    /**
     * 生成插入同伴表的批量数据
     *
     * @param array $partners 同伴的用户信息列表
     * @param array $tripData 行程基本信息, $rqData['line_data']
     * @return array
     */
    public function insertPartners($partners, $tripData)
    {
        $batchData = $this->buildInsertBatchData($partners, $tripData);
        return ShuttleTripPartner::insertAll($batchData);
    }

    /**
     * 同行者上车
     *
     * @param array $rqTripData 同行者信息
     * @param mixed $driverTripData 司机行程 ID or TripData
     */
    public function getOnCar($rqTripData, $driverTripData)
    {
        $ShuttleTripServ = new ShuttleTripService();
        $ShuttleTripPartner = new ShuttleTripPartner();
        $ShuttleTripModel = new ShuttleTrip();
        // 创建入库数据
        $lineId = $rqTripData['line_id'];
        $lineData = $rqTripData['line_data'] ?? null;
        $lineData = $lineData ?: ($ShuttleTripServ->getExtraInfoLineData($lineId) ?? []);
        $driverTripData = is_numeric($driverTripData) ? $ShuttleTripModel->getItem($driverTripData) : $driverTripData;
        $tripId = $driverTripData['id']; // 司机行程id
        $lineType = intval($lineData['type']);
        $defaultData = [
            'user_type' => 0,
            'trip_id' => $tripId,
            'line_id' => $lineId,
            'line_type' => $lineType,
            'time' => is_numeric($rqTripData['time']) ?  date('Y-m-d H:i:s', $rqTripData['time']) : $rqTripData['time'],
            'status' => 0,
            'comefrom' => 4,
            'seat_count' => 1,
            'extra_info' => json_encode(['line_data' => $lineData]),
        ];
        $partners = $ShuttleTripPartner->getPartners($rqTripData['id'], ($lineType > 0 ? 1 : 0)); //取得所有同行者
        $passengers = $ShuttleTripModel->passengers($tripId); // 取得司机空座位上的乘客 (用于检查是否早已在车上)
        $passengersUids = [];
        foreach ($passengers as $key => $value) {
            $passengersUids[] = $value['uid'];
        }
        $batchData = [];
        foreach ($partners as $key => $value) {
            if ($value['uid'] == $driverTripData['uid']) { // 如果同行者是司机，排除司机
                continue;
            }
            if (in_array($value['uid'], $passengersUids)) { // 如果本身是乘客之一
                continue;
            }
            $batchData[] = array_merge($defaultData, ['uid'=>$value['uid']]);
        }
        return ShuttleTrip::insertAll($batchData);
    }

    /**
     * 同行者上车后执行动作
     *
     * @param array $partners 同行者列表
     * @param array $driverTripData 司机行程数据
     * @param array $driverUserData 司机用户信息
     * @return boolean
     */
    public function doAfterGetOnCar($partners, $driverTripData, $driverUserData = null, $runType = 'pickup')
    {
        $TripsPushMsg = new TripsPushMsg();
        $ShuttleTripModel = new ShuttleTrip();
        $UserModel = new UserModel();
        $pushMsgData = [
            'from' => 'shuttle_trip',
            'runType' => 'pickup_partner',
            'userData'=> $driverUserData ?? $UserModel->getItem($driverTripData['uid']),
            'tripData'=> $driverTripData,
            'id' => $driverTripData['id'],
        ];
        $driverUid = $driverTripData['uid'] ?? ($driverUserData['uid'] ?? 0);
        foreach ($partners as $key => $value) {
            $ShuttleTripModel->delMyListCache($value['uid'], 'my');
            if ($value['uid'] == $driverUid) { // 排除司机
                continue;
            }
            $TripsPushMsg->pushMsg($value['uid'], $pushMsgData);
            if ($runType = 'hitchhiking') { // 把消息也推给司机
                $pushMsgData = [
                    'from' => 'shuttle_trip',
                    'runType' => 'hitchhiking',
                    'userData'=> $UserModel->getItem($value['uid']),
                    'tripData'=> $driverTripData,
                    'id' => $driverTripData['id'],
                ];
                $TripsPushMsg->pushMsg($value['uid'], $pushMsgData);
            }
        }
        return true;
    }
}
