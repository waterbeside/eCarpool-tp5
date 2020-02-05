<?php

namespace app\api\controller\v1\shuttle;

use app\api\controller\ApiBase;
use app\carpool\model\ShuttleTrip;
use app\carpool\model\ShuttleTripPartner;
use app\carpool\service\shuttle\Partner as ShuttlePartnerServ;
use app\carpool\model\User as UserModel;
use app\carpool\service\shuttle\Trip as ShuttleTripService;
use app\carpool\service\Trips as TripsService;
use app\carpool\validate\shuttle\Partner as ShuttlePartnerVali;
use my\RedisData;
use my\Utils;

use think\Db;

/**
 * 历史同行者
 * Class Partner
 * @package app\api\controller
 */
class Partner extends ApiBase
{


    public function my()
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $PartnerModel = new ShuttleTripPartner();
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];

        $redis = new RedisData();
        $ex = 60 * 2;
        $cacheKey  = $PartnerModel->getCommonListCacheKey($uid);
        $listData = $redis->cache($cacheKey);

        if (is_array($listData) && empty($listData)) {
            return $this->jsonReturn(20002, lang('No data'));
        }

        if (!$listData) {
            $field = 'uid, max(name) as name, max(sex) as sex, max(create_time) as create_time, count(uid) as use_count';
            $map = [
                ['creater_id', '=', $uid],
                ['is_delete', '=', Db::raw(0)],
            ];
            $listData = $PartnerModel::alias('t')->field($field)->where($map)
                ->group('uid')->limit(12)
                ->order('create_time DESC, use_count DESC')->select();
            if (!$listData) {
                $redis->cache($cacheKey, [], 10);
                $this->jsonReturn(20002, 'No data');
            }
            $listData = $listData->toArray();
            $redis->cache($cacheKey, $listData, $ex);
        }
        $list = ShuttleTripService::getInstance()->formatTimeFields($listData, 'list', ['create_time']);
        $list = Utils::getInstance()->filterListFields($list, ['create_time', 'use_count'], true);
        $returnData = [
            'lists' => $list
        ];
        return $this->jsonReturn(0, $returnData, 'Successful');
    }

    /**
     * 取得约车需求行程同行者
     *
     * @param integer $id 行程id;
     * @param integer $from_type 0普通行程，1上下班行程;
     */
    public function list($id)
    {
        if (!is_numeric($id)) {
            $this->jsonReturn(992, lang('Error Param'));
        }

        $from = input('param.from');
        if (in_array($from, ['info'])) {
            $from_type = 0;
        } elseif ($from == 'shuttle_trip') {
            $from_type = 1;
        } else {
            return $this->jsonReturn('992', lang('Error Param'));
        }

        $ShuttleTripPartner = new ShuttleTripPartner();
        $list = $ShuttleTripPartner->getPartners($id, $from_type) ?: [];
        if (empty($list)) {
            $this->jsonReturn(20002, 'No Data');
        }
        $UserModel = new UserModel();
        $Utils = new Utils();
        $userFields = ['uid', 'name', 'phone', 'mobile', 'Department', 'imgpath', 'im_id'];
        foreach ($list as $key => $value) {
            $userData = $UserModel->getItem($value['uid'], $userFields);
            $userData = $Utils->filterDataFields($userData, [], true, 'u_', -1);
            $list[$key] = array_merge($value, $userData);
        }
        $list = $Utils->filterListFields($list, ['uid', 'name', 'sex', 'is_delete', 'status', 'time', 'update_time'], true);
        $list = $Utils->formatTimeFields($list, 'list', ['time', 'create_time']);

        $returnData = [
            'lists' => $list
        ];
        return $this->jsonReturn(0, $returnData, 'Successful');
    }

    /**
     * 添加同行者
     *
     */
    public function save()
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $trip_id = input('post.trip_id');
        $from = input('post.from');
        if (in_array($from, ['info'])) {
            $from_type = 0;
        } elseif ($from == 'shuttle_trip') {
            $from_type = 1;
        } else {
            return $this->jsonReturn('992', lang('Error Param'));
        }

        $rqData['partners'] = input('post.partners');
        $rqData['partners'] = Utils::getInstance()->stringSetToArray($rqData['partners'], 'intval', true);

        $ShuttleTripModel = new ShuttleTrip();
        $PartnerServ = new ShuttlePartnerServ();
        $ShuttleTripPartner = new ShuttleTripPartner();

        // 加锁
        $lockKeyFill = "savePartners,uid_{$uid}";
        $lockKeyFill2 = "savePartners";
        if (!$ShuttleTripModel->lockItem($trip_id, $lockKeyFill, 5, 1)) { // 操作锁
            return $this->jsonReturn(30006, lang('Please do not repeat the operation'));
        }
        if (!$ShuttleTripModel->lockItem($trip_id, $lockKeyFill2)) { // 行锁
            return $this->jsonReturn(20009, lang('The network is busy, please try again later'));
        }

        $tripData = $ShuttleTripModel->getItem($trip_id);

        // 验证
        $ShuttlePartnerVali = new ShuttlePartnerVali();
        if (!$ShuttlePartnerVali->checkSave($rqData, $tripData, $userData)) {
            $errorData = $ShuttlePartnerVali->getError();
            $ShuttleTripModel->unlockItem($trip_id, $lockKeyFill); // 解锁操作锁
            $ShuttleTripModel->unlockItem($trip_id, $lockKeyFill2); // 解锁行锁
            return $this->jsonReturn($errorData['code'] ?? -1, $errorData['data'] ?? [], $errorData['msg'] ?? 'Error check');
        }

        // 取得入库的partner用户数据
        $partners_uData = $PartnerServ->getPartnersUserData($rqData['partners'], $userData['uid']);
        if (empty($partners_uData) && in_array($uid, $rqData['partners'])) {
            $ShuttleTripModel->unlockItem($trip_id, $lockKeyFill); // 解锁操作锁
            $ShuttleTripModel->unlockItem($trip_id, $lockKeyFill2); // 解锁行锁
            return $this->jsonReturn(-1, lang('You can not add yourself as a fellow partner'));
        }

        $time = strtotime($tripData['time']);

        // 查查重复
        if (!$ShuttlePartnerVali->checkRepetition($partners_uData, $time)) {
            $errorData = $ShuttlePartnerVali->getError();
            $ShuttleTripModel->unlockItem($trip_id, $lockKeyFill); // 解锁操作锁
            $ShuttleTripModel->unlockItem($trip_id, $lockKeyFill2); // 解锁行锁
            return $this->jsonReturn($errorData['code'] ?? -1, $errorData['data'] ?? [], $errorData['msg'] ?? 'Error check');
        }
        $from_type = $tripData['line_type'] > 0 ? 1 : 0;
        Db::connect('database_carpool')->startTrans();
        try {
            // 处理同行者
            if ($partners_uData && count($partners_uData) > 0) {
                $partnerUids = $ShuttleTripPartner->getPartnerUids($trip_id, $tripData['line_type']) ?: [];

                $tripData = [
                    'id' => $trip_id,
                    'uid' => $userData['uid'],
                    'line_type' => $tripData['line_type'],
                    'time' => date('Y-m-d H:i:s', $time)
                ];
                $addPartnerRes = $PartnerServ->insertPartners($partners_uData, $tripData, $partnerUids);
                // 处理原行程seat_count数
                $ShuttleTripServ = new ShuttleTripService();
                $ShuttleTripServ->resetRequestSeatCount($trip_id, $from_type);
            }
            // 提交事务
            Db::connect('database_carpool')->commit();
        } catch (\Exception $e) {
            Db::connect('database_carpool')->rollback();
            $errorMsg = $e->getMessage();
            $ShuttleTripModel->unlockItem($trip_id, $lockKeyFill); // 解锁操作锁
            $ShuttleTripModel->unlockItem($trip_id, $lockKeyFill2); // 解锁行锁
            return $this->jsonReturn(-1, null, lang('Failed'), ['errorMsg'=>$errorMsg]);
        }
        $ShuttleTripModel->unlockItem($trip_id, $lockKeyFill); // 解锁操作锁
        $ShuttleTripModel->unlockItem($trip_id, $lockKeyFill2); // 解锁行锁
        if (isset($addPartnerRes) && is_array($addPartnerRes)) {
            $PartnerServ->doAfterAddPartners($addPartnerRes, $tripData, $userData);
        }
        return $this->jsonReturn(0, 'Successful');
    }

    /**
     * 移除同行者
     */
    public function del($id)
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $ShuttleTripPartner = new ShuttleTripPartner();
        // 加锁
        $lockKeyFill = "del,uid_{$uid}";
        if (!$ShuttleTripPartner->lockItem($id, $lockKeyFill, 5, 1)) { // 操作锁
            return $this->jsonReturn(30006, lang('Please do not repeat the operation'));
        }
        $itemData = $ShuttleTripPartner->find($id);
        // 验证
        $ShuttlePartnerVali = new ShuttlePartnerVali();
        if (!$ShuttlePartnerVali->checkDel($itemData, $userData)) {
            $errorData = $ShuttlePartnerVali->getError();
            $ShuttleTripPartner->unlockItem($id, $lockKeyFill); // 解锁操作锁
            return $this->jsonReturn($errorData['code'] ?? -1, $errorData['data'] ?? [], $errorData['msg'] ?? 'Error check');
        }
        $from_type = $itemData['line_type'] > 0 ? 1 : 0;
        Db::connect('database_carpool')->startTrans();
        try {
            // 执行删除
            $ShuttleTripPartner->where('id', $id)->update(['is_delete'=>1]);
            // 处理原行程seat_count数
            $ShuttleTripServ = new ShuttleTripService();
            $ShuttleTripServ->resetRequestSeatCount($itemData['trip_id'], $from_type);
        } catch (\Exception $e) {
            Db::connect('database_carpool')->rollback();
            $errorMsg = $e->getMessage();
            $ShuttleTripPartner->unlockItem($id, $lockKeyFill); // 解锁操作锁
            return $this->jsonReturn(-1, null, lang('Failed'), ['errorMsg'=>$errorMsg]);
        }
        $ShuttleTripPartner->unlockItem($id, $lockKeyFill); // 解锁操作锁
        return $this->jsonReturn(0, 'Successful');
    }
}
