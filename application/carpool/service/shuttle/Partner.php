<?php
namespace app\carpool\service\shuttle;

use app\common\service\Service;
use app\carpool\service\shuttle\Trip as ShuttleTripService;
use app\carpool\service\Trips as TripsService;
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
     * @param array $oldPartnerUids 本身已在行程的同行者Uid数组
     * @return array
     */
    public function buildInsertBatchData($partners, $tripData, $oldPartnerUids = [])
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
            if (!empty($oldPartnerUids) && is_array($oldPartnerUids) && in_array($value['uid'], $oldPartnerUids)) {
                continue; // 如果已在旧的同行者内，则跳过
            }
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
     * @param array $oldPartnerUids 本身已在行程的同行者Uid数组
     * @return array
     */
    public function insertPartners($partners, $tripData, $oldPartnerUids = [])
    {
        $ShuttleTripPartner = new ShuttleTripPartner();
        $batchData = $this->buildInsertBatchData($partners, $tripData, $oldPartnerUids);
        $res = $ShuttleTripPartner->insertAll($batchData);
        if ($res) {
            // 如果插入成功，执行以下操作
            if (isset($tripData['id']) && isset($tripData['line_type'])) {
                $ShuttleTripPartner->delPartnersCache($tripData['id'], $tripData['line_type'] > 0 ? 1 :0); // 清理同行者列表缓存
            }
        }
        return $res;
    }

    /**
     * 同行者上车
     *
     * @param array $rqTripData 约车需求的发起者行程信息
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
            $upData = $defaultData;
            $upData['uid'] = $value['uid'];
            $upData['extra_info'] = json_encode([
                'line_data' => $lineData,
                'partner_data' => [ // 把partner表的id写入扩展字段
                    'id' => $value['id'],
                    'creater_id' => $rqTripData['uid'],
                    'trip_id' => $rqTripData['id'],
                ]
            ]);
            $batchData[] = $upData;
        }
        Db::connect('database_carpool')->startTrans();
        try {
            // 插入主表
            ShuttleTrip::insertAll($batchData);
            // 把同行者标记为已上车
            $ShuttleTripPartner->signGetOn($rqTripData['id'], $lineType > 0 ? 1 : 0);
            // 提交事务
            Db::connect('database_carpool')->commit();
        } catch (\Exception $e) {
            Db::connect('database_carpool')->rollback();
            throw new \Exception($e->getMessage());
            return false;
        }
        
        return true;
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
        $ShuttleTripPartner = new ShuttleTripPartner();
        $pushMsgData = [
            'from' => 'shuttle_trip',
            'runType' => 'pickup_partner',
            'userData'=> $driverUserData ?? $UserModel->getItem($driverTripData['uid']),
            'tripData'=> $driverTripData,
            'id' => $driverTripData['id'],
        ];
        $driverUid = $driverTripData['uid'] ?? ($driverUserData['uid'] ?? 0);
        if (count($partners) > 0) {
            $ShuttleTripPartner->delPartnersCache($partners[0]['trip_id'], $partners[0]['line_type'] > 0 ? 1 :0); // 清除需求的同行者列表缓存
        }
        foreach ($partners as $key => $value) {
            $ShuttleTripModel->delMyListCache($value['uid'], 'my'); // 清除各同行者用户的我的行程
            if ($value['uid'] == $driverUid) { // 排除司机
                continue;
            }
            $TripsPushMsg->pushMsg($value['uid'], $pushMsgData);
            if ($runType == 'hitchhiking') { // 把消息也推给司机
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


    /**
     * 取消行程而还原同行者
     *
     * @param array $tripList 被取消的行程列表
     * @return void
     */
    public function getOffCar($tripList)
    {
        $ShuttleTripModel = new ShuttleTrip();
        $ShuttleTripPartner = new ShuttleTripPartner();
        foreach ($tripList as $key => $value) {
            $partnerData = $ShuttleTripModel->getExtraInfo($value, 'partner_data');
            if (isset($partnerData['id'])) {
                // $rqTripData = $ShuttleTripModel->getItem($partnerData['id']);
                $ShuttleTripPartner->signGetOff($partnerData['id']);
                $ShuttleTripPartner->delPartnersCache($partnerData['trip_id'], $value['line_type'] > 0 ? 1 : 0); // 清理缓存
            }
        }
        return true;
    }

    /**
     * 发布行程时检查行程是否有重复 (包含ShuttleTrip, love_wall , info)
     * @param  integer     $time       timestamp 出发时间的时间戳
     * @param  integer     $uid        发布者ID
     * @param  boolean     $listDetail    是否返回列表详情
     * @param  string      $offsetTime 时间偏差范围
     */
    public function getRepetition($time, $uid, $offsetTime = 60 * 15, $level = null)
    {
        $time = is_numeric($time) ? $time : strtotime($time);
        $level = $level ?: [[60*10,10]];
        $ShuttleTripPartner = new ShuttleTripPartner();
        $TripsService = new TripsService();
        $list = $ShuttleTripPartner->getListByTimeOffset($time, $uid, $offsetTime);
        $res = $TripsService->checkRepetitionByList($list, $time, 0, $offsetTime, $level);
        return $res;
    }
}
