<?php

namespace app\api\controller\v1\shuttle;

use app\api\controller\ApiBase;
use app\carpool\model\ShuttleLine as ShuttleLineModel;
use app\carpool\model\ShuttleLineDepartment;
use app\carpool\model\ShuttleTrip;
use app\carpool\model\ShuttleTripPartner;
use app\carpool\model\User;
use app\carpool\service\shuttle\Trip as ShuttleTripService;
use app\carpool\service\shuttle\Partner as ShuttlePartnerService;
use app\carpool\service\Trips as TripsService;
use app\carpool\service\TripsPushMsg;
use app\carpool\validate\shuttle\Trip as ShuttleTripVali;
use my\RedisData;


use think\Db;

/**
 * 班车行程
 * Class Line
 * @package app\api\controller
 */
class Trip extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }

    public function list($rqType, $page = 1, $pagesize = 0, $returnType = 1)
    {
        if (!in_array($rqType, ['cars','requests'])) {
            return $returnType ? $this->jsonReturn(992, 'Error params') : [992, null, 'Error params'];
        }
        $line_id = input('get.line_id/d', 0);
        if (!$line_id) {
            return $returnType ? $this->jsonReturn(992, 'Error line_id') : [992, null, 'Error line_id'];
        }
        $ex = 60 * 30;
        $keyword = input('get.keyword');

        $redis = new RedisData();
        $ShuttleLineModel = new ShuttleLineModel();
        $ShuttleTrip = new ShuttleTrip();
        $TripsService = new TripsService();
        $ShuttleTripService = new ShuttleTripService();

        $returnData = null;
        // 先查出路线数据
        $lineFields = [
            'id','type','start_name','start_longitude','start_latitude','end_name','end_longitude','end_latitude','status','map_type'
        ];
        $lineData = $ShuttleLineModel->getItem($line_id, $lineFields);
        if (!$lineData) {
            return $returnType ? $this->jsonReturn(20002, $returnData, 'No data') : [992, $returnData, 'No data'];
        }
        if (!$keyword) {
            $cacheKey  = $ShuttleTrip->getListCacheKeyByLineId($line_id, $rqType);
            $rowCacheKey = "pz_{$pagesize},page_$page";
            $returnData = $redis->hCache($cacheKey, $rowCacheKey);
        }
        if (is_array($returnData) && empty($returnData)) {
            $returnData['lineData'] = $lineData;
            return $returnType ? $this->jsonReturn(20002, $returnData, 'No data') : [20002, $returnData, 'No data'];
        }
        if (!$returnData) {
            $userAlias = 'u';
            $offsetTimeArray = $ShuttleTrip->getDefatultOffsetTime(time(), 0, 'Y-m-d H:i:s');
            $fields = $ShuttleTrip->getListField('t');
            $fields .=  ',' .$TripsService->buildUserFields($userAlias);
            if ($rqType === 'cars') {
                // $userAlias = 'd';
                $userType = 1;
                $comefrom = 1;
            } elseif ($rqType === 'requests') {
                // $userAlias = 'p';
                $offsetTimeArray[0] = date('Y-m-d H:i:s');
                $userType = 0;
                $comefrom = 2;
            }
            $offsetTimeArray = $ShuttleTrip->getDefatultOffsetTime(time(), 0, 'Y-m-d H:i:s');
            $join = [
                ["user {$userAlias}", "t.uid = {$userAlias}.uid", 'left'],
            ];
            $map  = [
                ['t.line_id', '=', $line_id],
                ['t.status', 'between', [0,1]],
                ['t.time', 'between', $offsetTimeArray],
                ['trip_id', '=', Db::raw(0)]
            ];
            if (isset($userType)) {
                $map[] = ['t.user_type', '=', Db::raw($userType)];
            }
            if (isset($comefrom)) {
                $map[] = ['t.comefrom', '=', Db::raw($comefrom)];
            }
            // 排除已删用户；
            $map[] = ["{$userAlias}.is_delete", '=', Db::raw(0)];

            if ($keyword) {
                $map[] = ["{$userAlias}.name|{$userAlias}.nativename", 'line', "%$keyword%"];
            }
            $ctor = $ShuttleTrip->alias('t')->field($fields)->join($join)->where($map)->order('t.time ASC');
            $returnData = $this->getListDataByCtor($ctor, $pagesize);
            if (empty($returnData['lists'])) {
                if (!$keyword) {
                    $redis->hCache($cacheKey, $rowCacheKey, [], $ex);
                }
                return $returnType ? $this->jsonReturn(20002, 'No data') : [20002, null, 'No data'];
            }

            $returnData['lineData'] = $lineData;
            if (!$keyword) {
                $redis->hCache($cacheKey, $rowCacheKey, $returnData, $ex);
            }
        }
        $returnData['lists'] = $ShuttleTripService->formatTimeFields($returnData['lists'], 'list', ['time','create_time']);
        // foreach ($returnData['lists'] as $key => $value) {
        //     $returnData['lists'][$key]['start_name'] = $lineData['start_name'];
        //     $returnData['lists'][$key]['start_longitude'] = $lineData['start_longitude'];
        //     $returnData['lists'][$key]['start_latitude'] = $lineData['start_latitude'];
        //     $returnData['lists'][$key]['end_name'] = $lineData['end_name'];
        //     $returnData['lists'][$key]['end_longitude'] = $lineData['end_longitude'];
        //     $returnData['lists'][$key]['end_latitude'] = $lineData['end_latitude'];
        // }
        return $returnType ? $this->jsonReturn(0, $returnData, 'Successful') : [0, $returnData, 'Successful'];
    }

    
    /**
     * 空座位列表
     *
     * @param integer $type 上下班类型
     * @param integer $page 页码
     * @param integer $pagesize 每页多少条
     */
    public function cars($page = 1, $pagesize = 50, $passengers = 1)
    {
        $isGetPassengers = $passengers;
        $res = $this->list('cars', $page, $pagesize, 0);
        if (isset($res[1]['lists'])) {
            $ShuttleTripModel = new ShuttleTrip();
            $ShuttleTripService = new ShuttleTripService();
            foreach ($res[1]['lists'] as $key => $value) {
                if ($isGetPassengers) {
                    // $userFields = ['uid', 'loginname', 'name', 'nativename','sex','phone','mobile'];
                    // $resPassengers = $ShuttleTripService->passengers($value['id'], $userFields, ['id','status'], 0);
                    // $res[1]['lists'][$key]['passengers'] = $resPassengers ?: [];
                    // $res[1]['lists'][$key]['took_count'] = count($resPassengers);
                    $res[1]['lists'][$key]['took_count'] = $ShuttleTripModel->countPassengers($value['id']);
                }
            }
        }
        return $this->jsonReturn($res[0], $res[1], $res[2]);
    }

    /**
     * 约车需求列表
     *
     * @param integer $page 页码
     * @param integer $pagesize 每页条数
     */
    public function requests($page = 1, $pagesize = 50)
    {
        $res = $this->list('requests', $page, $pagesize, 0);
        if (isset($res[1]['lists'])) {
            $lists = $res[1]['lists'];
            $lists = $this->filterListFields($lists, [], true);
            $res[1]['lists'] = $lists;
        }
        return $this->jsonReturn($res[0], $res[1], $res[2]);
    }


    /**
     * 我的行程
     *
     * @param integer $page 页码
     * @param integer $pagesize 每页多少条;
     * @return void
     */
    public function my($show_passengers = 1, $page = 1, $pagesize = 0)
    {
        $isGetPassengers = $show_passengers;
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $redis = new RedisData();
        $TripsService = new TripsService();
        $ShuttleTrip = new ShuttleTrip();
        $ShuttleTripService = new ShuttleTripService();
        $cacheKey = $ShuttleTrip->getMyListCacheKey($uid, 'my');
        $rowCacheKey = "pz_{$pagesize},page_$page";
        $returnData = $redis->hCache($cacheKey, $rowCacheKey);
        if (is_array($returnData) && empty($returnData)) {
            return $this->jsonReturn(20002, $returnData, 'No data');
        }
        $userFields = ['uid', 'loginname', 'name', 'nativename', 'sex', 'phone', 'mobile', 'imgpath', 'im_id'];
        if (!$returnData) {
            $ex = 60 * 3;
            $userAlias = 'u';
            $fields_user = $TripsService->buildUserFields($userAlias, $userFields);
            $fields = 't.id, t.trip_id, t.user_type, t.comefrom, t.line_id, t.plate, t.seat_count, t.status';
            $fields .= ', l.type as line_type, l.start_name, l.end_name, l.map_type, t.time, t.create_time';
            $fields .=  ',' .$fields_user;
            $map  = [
                ['t.status', 'between', [0,1]],
                ['t.uid', '=', $uid],
                ["t.time", ">", date('Y-m-d H:i:s', strtotime('-10 minute'))],
            ];
            $join = [
                ["user {$userAlias}", "t.uid = {$userAlias}.uid", 'left'],
                ["t_shuttle_line l", "l.id = t.line_id", 'left'],
            ];
            $ctor = $ShuttleTrip->alias('t')->field($fields)->join($join)->where($map)->order('t.time ASC');
            $returnData = $this->getListDataByCtor($ctor, $pagesize);

            if (empty($returnData['lists'])) {
                $redis->hCache($cacheKey, $rowCacheKey, [], $ex);
                return $this->jsonReturn(20002, 'No data');
            }
            $redis->hCache($cacheKey, $rowCacheKey, $returnData, $ex);
        }
        $lists = $returnData['lists'] ?? [];
        $lists = $ShuttleTripService->formatTimeFields($lists, 'list', ['time','create_time']);
        if (is_array($lists) && $isGetPassengers) {
            foreach ($lists as $key => $value) {
                if ($value['user_type'] == 1 && in_array($value['comefrom'], [1, 4])) {
                    $resPassengers = $ShuttleTripService->passengers($value['id'], $userFields, ['id','status'], 0);
                    $lists[$key]['passengers'] = $resPassengers ?: [];
                    $lists[$key]['took_count'] = count($lists[$key]['passengers']);
                    // $lists[$key]['took_count'] = $ShuttleTrip->countPassengers($value['id']);
                }
                if ($value['user_type'] == 0 && $value['trip_id'] > 0) {
                    $resDriver =  $ShuttleTripService->getUserTripDetail($value['trip_id'], $userFields, ['id','status','user_type','comefrom', 'plate', 'seat_count'], 0);
                    $lists[$key]['driver'] = $resDriver ?: [];
                }
            }
        }
        $returnData['lists'] = $lists;
        return $this->jsonReturn(0, $returnData, 'Successful');
    }


    /**
     * 我的历史行程
     *
     * @param integer $page 页码
     * @param integer $pagesize 每页多少条;
     * @return void
     */
    public function history($page = 1, $pagesize = 50)
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $redis = new RedisData();
        $ShuttleTrip = new ShuttleTrip();
        $ShuttleTripService = new ShuttleTripService();
        $cacheKey = $ShuttleTrip->getMyListCacheKey($uid, 'history');
        $rowCacheKey = "pz_{$pagesize},page_$page";
        $returnData = $redis->hCache($cacheKey, $rowCacheKey);
        if (is_array($returnData) && empty($returnData)) {
            return $this->jsonReturn(20002, $returnData, 'No data');
        }
        if (!$returnData) {
            $ex = 60 * 5;
            $fields = 't.id, t.trip_id, t.user_type, t.comefrom, t.line_id, t.uid, t.status, t.time, t.create_time';
            $fields .= ', l.type as line_type, l.start_name, l.end_name, l.map_type';
            $map  = [
                // ['t.status', 'between', [0,1,3]],
                ['t.uid', '=', $uid],
                ["t.time", "<", date('Y-m-d H:i:s', strtotime('-20 minute'))],
            ];
            $join = [
                ["t_shuttle_line l", "l.id = t.line_id", 'left'],
            ];
            $ctor = $ShuttleTrip->alias('t')->field($fields)->join($join)->where($map)->order('t.time ASC');
            $returnData = $this->getListDataByCtor($ctor, $pagesize);
            if (empty($returnData['lists'])) {
                $redis->hCache($cacheKey, $rowCacheKey, [], $ex);
                return $this->jsonReturn(20002, 'No data');
            }
            foreach ($returnData['lists'] as $key => $value) {
                $returnData['lists'][$key]['took_count'] = in_array($value['comefrom'], [1, 4]) ? $ShuttleTrip->countPassengers($value['id']) : null;
            }
            $redis->hCache($cacheKey, $rowCacheKey, $returnData, $ex);
        }
        $returnData['lists'] = $ShuttleTripService->formatTimeFields($returnData['lists'], 'list', ['time','create_time','update_time']);

        return $this->jsonReturn(0, $returnData, 'Successful');
    }


    /**
     * 取得乘客列表
     *
     * @param integer $id 行程id
     * @param array $userFields 要读出的用户字段
     * @param integer $returnType 返回数据 1:支持抛出json，2:以数组形式返回数据
     */
    public function passengers($id = 0)
    {
        if (!$id) {
            $this->jsonReturn(992, 'Error param');
        }
        $ShuttleTripService = new ShuttleTripService();
        $res = $ShuttleTripService->passengers($id);
        $returnData = [
            'lists' => $res,
        ];
        return $this->jsonReturn(0, $returnData, 'Successful');
    }

    /**
     * 取得详情
     *
     * @param integer $id 行程id
     * @param integer $show_line 是否返回路线数据
     * @param integer $show_member 是否返回对方参与者的用户数据
     */
    public function show($id = 0, $show_line = 1, $show_member = 2)
    {
        if (!$id) {
            return $this->jsonReturn(992, 'Error param');
        }
        $ShuttleTripServ = new ShuttleTripService();
        $data  = $ShuttleTripServ->getUserTripDetail($id, [], [], $show_line);

        if (!$data) {
            return $this->jsonReturn(20002, 'No data');
        }
        $trip_id = $data['trip_id'];

        if ($show_member) {
            if ($data['user_type'] == 1) {
                if ($show_member == 2) {
                    $data['passengers'] = $ShuttleTripServ->passengers($id) ?? [];
                    $data['took_count'] = count($data['passengers']);
                } else {
                    $ShuttleTrip = new ShuttleTrip();
                    $data['took_count'] = $ShuttleTrip->countPassengers($id);
                }
            } else {
                $ShuttleTripPartner = new ShuttleTripPartner();
                $data['partners'] = $ShuttleTripPartner->getPartners($id, 1) ?? [];
                $data['driver'] = $ShuttleTripServ->getUserTripDetail($trip_id, [], [], 0) ?: null;
            }
        }
        
        unset($data['trip_id']);
        return $this->jsonReturn(0, $data, 'Successful');
    }


    /**
     * 发布一个需求或行程, 或搭车
     *
     */
    public function save($rqData = null)
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $ShuttleTripService = new ShuttleTripService();
        $rqData = $ShuttleTripService->getRqData($rqData);
        $line_id = $rqData['line_id'];
        $dev = input('post.dev/d', 0);
        // 加锁
        $ShuttleTripModel = new ShuttleTrip();
        $lockKeyFill = "u_{$uid}:save";
        if (!$ShuttleTripModel->lockItem(0, $lockKeyFill, 5, 1)) {
            return $this->jsonReturn(30006, '请不要重复操作');
        }
        // 验证
        $ShuttleTripVali = new ShuttleTripVali();
        if (!$ShuttleTripVali->checkSave($rqData)) {
            $errorData = $ShuttleTripVali->getError();
            $ShuttleTripModel->unlockItem(0, $lockKeyFill); // 解锁
            return $this->jsonReturn($errorData['code'] ?? -1, $errorData['data'] ?? [], $errorData['msg'] ?? 'Error check');
        }

        $rqData['line_data'] = $ShuttleTripService->getExtraInfoLineData($line_id);
        if (!$rqData['line_data']) {
            $ShuttleTripModel->unlockItem(0, $lockKeyFill); // 解锁
            $this->jsonReturn(20002, '该路线不存在');
        }
        // 如果是约车需求，要处理同行者
        if ($rqData['create_type'] == 'requests') {
            $rqData['partners'] = input('post.partners');
            $PartnerServ = new ShuttlePartnerService();
            $partners = $PartnerServ->getPartnersUserData($rqData['partners'], $userData['uid']);
            $TripsService = new TripsService();
            $hasError = 0;
            $errorPartners = [];
            if ($dev) {
                dump($partners);
            }
            foreach ($partners as $key => $value) {
                 // 验证重复行程
                $repetitionList = $TripsService->getRepetition($rqData['time'], $value['uid']);
                $partners[$key]['repetitionList'] = $repetitionList ?? [];
                if ($repetitionList) {
                    $hasError = 50009;
                    $errorPartners[] = $partners[$key];
                }
            }
            if ($hasError === 50009) {
                $ShuttleTripModel->unlockItem(0, $lockKeyFill); // 解锁
                $this->jsonReturn(50009, ['lists'=>$errorPartners], '你添的加同行伙伴中，有人在相似的时间内已有一到多趟的行程');
            }
            $rqData['seat_count'] = count($partners) + 1;
        }

        Db::connect('database_carpool')->startTrans();
        try {
            // 入库
            $addRes = $ShuttleTripService->addTrip($rqData, $userData);
            // 如果是约车需求，并有同行者，处理同行者
            if ($rqData['create_type'] == 'requests' && isset($partners) && count($partners) > 0) {
                $tripData = [
                    'id' => $addRes,
                    'uid' => $userData['uid'],
                    'line_type' => $rqData['line_data']['type'],
                    'time' => date('Y-m-d H:i:s', $rqData['time'])
                ];
                $PartnerServ->insertPartners($partners, $tripData);
            }
            // 提交事务
            Db::connect('database_carpool')->commit();
        } catch (\Exception $e) {
            Db::connect('database_carpool')->rollback();
            $errorMsg = $e->getMessage();
            $ShuttleTripModel->unlockItem(0, $lockKeyFill); // 解锁
            return $this->jsonReturn(-1, null, lang('Failed'), ['errorMsg'=>$errorMsg]);
        }
        $ShuttleTripModel->unlockItem(0, $lockKeyFill); // 解锁
        if (!$addRes) {
            $errorData = $ShuttleTripService->getError();
            return $this->jsonReturn($errorData['code'], $errorData['data'], $errorData['msg']);
        }
        return $this->jsonReturn(0, ['id'=>$addRes], 'Successful');
    }
    
    /**
     * 乘客搭车
     */
    public function hitchhiking($id)
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];

        $ShuttleTripService = new ShuttleTripService();
        $ShuttleTripModel = new ShuttleTrip();
        $rqData = $ShuttleTripService->getRqData();
        $rqData['trip_id'] = $id;
        $rqData['create_type'] = 'hitchhiking';

        // 加锁
        if (!$ShuttleTripModel->lockItem($id, 'hitchhiking')) {
            return $this->jsonReturn(20009, '网络烦忙，请稍候再试');
        }

        $tripData = $ShuttleTripModel->getItem($id); //取得司机行
        // 验证
        $ShuttleTripVali = new ShuttleTripVali();
        if (!$ShuttleTripVali->checkHitchhiking($tripData, $userData)) {
            $errorData = $ShuttleTripVali->getError();
            $ShuttleTripModel->unlockItem($id, 'hitchhiking'); // 解锁
            return $this->jsonReturn($errorData['code'] ?? -1, $errorData['data'] ?? [], $errorData['msg'] ?? 'Error check');
        }

        $rqData['time'] = strtotime($tripData['time']);
        $rqData['line_id'] = $tripData['line_id'];
        $rqData['line_data'] = $ShuttleTripService->getExtraInfoLineData($id, 2);
        if (!$rqData['line_data']) {
            $ShuttleTripModel->unlockItem($id, 'hitchhiking'); // 解锁
            return $this->jsonReturn(20002, '该路线不存在');
        }

        // 入库
        $addRes = $ShuttleTripService->addTrip($rqData, $userData);
        $ShuttleTripModel->unlockItem($id, 'hitchhiking'); // 解锁
        if (!$addRes) {
            $errorData = $ShuttleTripService->getError();
            $this->jsonReturn($errorData['code'], $errorData['data'], lang('Failed').'. '.$errorData['msg']);
        }
        // 推消息
        $TripsPushMsg = new TripsPushMsg();
        $pushMsgData = [
            'from' => 'shuttle_trip',
            'runType' => 'hitchhiking',
            'userData'=> $userData,
            'tripData'=> $tripData,
            'id' => $id,
        ];
        $targetUserid = $tripData['uid'];
        $TripsPushMsg->pushMsg($targetUserid, $pushMsgData);
        // ok
        return $this->jsonReturn(0, ['id'=>$addRes], 'Successful');
    }

    /**
     * 司机接客
     */
    public function pickup($id)
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $ShuttleTripService = new ShuttleTripService();
        $ShuttleTripModel = new ShuttleTrip();
        $rqData = $ShuttleTripService->getRqData();
        $rqData['create_type'] = 'pickup';

        // 加锁
        if (!$ShuttleTripModel->lockItem($id, 'pickup')) {
            return $this->jsonReturn(20009, '网络烦忙，请稍候再试');
        }
        $tripData = $ShuttleTripModel->getItem($id); //取得乘客须求行程
        
        // 验证
        $ShuttleTripVali = new ShuttleTripVali();
        if (!$ShuttleTripVali->checkPickup($tripData, $userData)) {
            $errorData = $ShuttleTripVali->getError();
            $ShuttleTripModel->unlockItem($id, 'pickup'); // 解锁
            return $this->jsonReturn($errorData['code'] ?? -1, $errorData['data'] ?? [], $errorData['msg'] ?? 'Error check');
        }

        $rqData['seat_count'] = $tripData['seat_count'] > $rqData['seat_count'] ? $tripData['seat_count'] : $rqData['seat_count'];
        $rqData['time'] = strtotime($tripData['time']);
        $rqData['line_id'] = $tripData['line_id'];
        $rqData['line_data'] = $ShuttleTripService->getExtraInfoLineData($id, 2);
        
        if (!$rqData['line_data']) {
            $ShuttleTripModel->unlockItem($id, 'pickup'); // 解锁
            return $this->jsonReturn(20002, '该路线不存在');
        }

        Db::connect('database_carpool')->startTrans();
        try {
            // 入库
            $driverTripId = $ShuttleTripService->addTrip($rqData, $userData); // 添加一条司机行程
            if (!$driverTripId) {
                throw new \Exception("添加司机数据失败");
            }
            $ShuttleTripModel->where('id', $id)->update(['trip_id'=>$driverTripId]); // 乘客行程的trip_id设为司机行程id

            // 查询有没有同行者, 有的话把同行者也带上
            if ($tripData['seat_count'] > 1) {
                $ShuttleTripPartner = new ShuttleTripPartner();
                $partners = $ShuttleTripPartner->getPartners($tripData['id'], 1) ?? [];
                $ShuttlePartnerServ = new ShuttlePartnerService();
                $rqTripData = $tripData;
                $rqTripData['line_data'] = $rqData['line_data'];
                $ShuttlePartnerServ->getOnCar($rqTripData, $driverTripId);
            }
            // 提交事务
            Db::connect('database_carpool')->commit();
        } catch (\Exception $e) {
            Db::connect('database_carpool')->rollback();
            $ShuttleTripModel->unlockItem($id, 'pickup'); // 解锁
            $errorMsg = $e->getMessage();
            $err = $ShuttleTripService->getError();
            return $this->jsonReturn($err['code'] ?? -1, $err['data'] ?? null, $err['msg'] ?? lang('Failed'), ['errorMsg'=>$errorMsg]);
        }
        $ShuttleTripModel->unlockItem($id, 'pickup'); // 解锁
        // 消缓存
        $ShuttleTripModel->delItemCache($id); // 消单项行程缓存
        $ShuttleTripModel->delListCache($rqData['line_id']); // 取消该路线的空座位和约车需求列表
        // 推消息
        $TripsPushMsg = new TripsPushMsg();
        $pushMsgData = [
            'from' => 'shuttle_trip',
            'runType' => 'pickup',
            'userData'=> $userData,
            'tripData'=> $tripData,
            'id' => $id,
        ];
        $targetUserid = $tripData['uid'];
        $TripsPushMsg->pushMsg($targetUserid, $pushMsgData);
        // 入库成功后清理这些同行者的相关缓存，及推送消息
        if (isset($partners) && count($partners) > 0) {
            $driverTripData = [
                'id' => $driverTripId
            ];
            $ShuttlePartnerServ->doAfterGetOnCar($partners, $driverTripData, $userData);
        }
        return $this->jsonReturn(0, ['id'=>$driverTripId], 'Successful');
    }

    /**
     * 修改内容 (取消，完结，改变座位数)
     *
     */
    public function change($id = 0)
    {
        if (!$id) {
            return $this->jsonReturn(992, 'Error param');
        }

        $run = input('post.run') ?: input('param.run') ;
        if (!in_array($run, ['cancel', 'change_seat', 'change_plate'])) { // 不再支持完结
            return $this->jsonReturn(992, 'Error param');
        }
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $ShuttleTripModel = new ShuttleTrip();
        $tripData = $ShuttleTripModel->getItem($id);
        if (empty($tripData)) {
            return $this->jsonReturn(20002, '该行程不存在');
        }
        
        $ShuttleTripService = new ShuttleTripService();
        if ($run === 'cancel') {
            $res = $ShuttleTripService->cancel($tripData, $userData);
        } elseif ($run === 'finish') {
            $res = $ShuttleTripService->finish($tripData, $userData);
        } else {
            $ShuttleTripVali = new ShuttleTripVali();
            if ($run === 'change_seat') { // 改变空座位数
                $seat_count = input('post.seat_count/d', 0);
                $upData = [
                    'seat_count' => $seat_count,
                ];
                $checkRes = $ShuttleTripVali->checkChangeSeat($upData, $tripData, $userData['uid']);
            } elseif ($run === 'change_plate') { // 改变空座位数
                $plate = input('post.plate');
                $upData = [
                    'plate' => $plate,
                ];
                $checkRes = $ShuttleTripVali->checkChangePlate($upData, $tripData, $userData['uid']);
            } else {
                return $this->jsonReturn(992, 'Error param');
            }
            if (!$checkRes) {
                $errorData = $ShuttleTripVali->getError();
                return $this->jsonReturn($errorData['code'] ?? -1, $errorData['data'] ?? [], $errorData['msg'] ?? 'Error check');
            }
            $res = $ShuttleTripModel->where('id', $id)->update($upData);
        }

        if ($res === false) {
            if (in_array($run, ['cancel', 'finish'])) {
                $errorData = $ShuttleTripService->getError();
                return $this->jsonReturn($errorData['code'] ?? -1, $errorData['msg'] ?? 'Failed');
            } else {
                return $this->jsonReturn(-1, 'Failed');
            }
        }
        if (in_array($run, ['change_seat', 'change_plate'])) {
            $ShuttleTripModel->delItemCache($id); // 消单项行程缓存
            $ShuttleTripModel->delMyListCache($uid, 'my');
            $ShuttleTripModel->delListCache($tripData['line_id']);
        }
        return $this->jsonReturn(0, 'Successful');
    }


    /**
     * 通过行程id匹配行程列表
     */
    public function matching($id, $timeoffset = 60 * 15)
    {
        $ShuttleTripService = new ShuttleTripService();
        $ShuttleTripModel = new ShuttleTrip();
        $userData = $this->getUserData(1);
        $uid = intval($userData['uid']);

        $tripData = $ShuttleTripModel->getItem($id);
        if (!$tripData) {
            return $this->jsonReturn(20002, '路线不存在');
        }
        $line_id = $tripData['line_id'];
        $time = strtotime($tripData['time']);
        $timeoffset = is_numeric($timeoffset) ? [$timeoffset, $timeoffset] :
            (
                is_string($timeoffset) ? array_map('intval', explode(',', $timeoffset)) : $timeoffset
            );
        if (!is_array($timeoffset) || empty($timeoffset)) {
            return $this->jsonReturn(992, 'Error timeoffset');
        }
        $timeoffset = count($timeoffset) > 1 ? $timeoffset : [$timeoffset[0], $timeoffset[0]];

        $matchingUserType = $tripData['user_type'] == 1 ? 0 : 1;
        $list = $ShuttleTripService->getSimilarTrips($line_id, $time, $matchingUserType, -1*$uid, $timeoffset);
        if (!$list) {
            return $this->jsonReturn(20002, 'No data');
        }

        foreach ($list as $key => $value) {
            if ($matchingUserType == 1) {
                $list[$key]['took_count'] = $ShuttleTripModel->countPassengers($value['id']);
            }
        }
        $returnData = [
            'lists' => $list,
            'lineData' => $ShuttleTripService->getExtraInfoLineData($line_id) ?: null,
        ];
        return $this->jsonReturn(0, $returnData, 'Successful');
    }

    /**
     * 合并行程
     */
    public function merge($id = null)
    {
        $id = $id ?? input('post.id/d', 0); // 自己的行程id
        $tid = input('post.tid/d', 0); // 对方的行程id
        if (!$tid || !$id) {
            return $this->jsonReturn(992, 'Empty param');
        }
        $userData = $this->getUserData(1);
        $uid = intval($userData['uid']);

        $ShuttleTripModel = new ShuttleTrip();
        $tripData = $ShuttleTripModel->getItem($id);
        $targetTripData = $ShuttleTripModel->getItem($tid);

        $driverTripId = $tripData['user_type'] == 1 ? $id : $tid;
        $passengerTripId = $tripData['user_type'] == 1 ? $tid : $id;
        $driverTripData = $tripData['user_type'] == 1 ? $tripData : $targetTripData;
        $passengerTripData = $tripData['user_type'] == 1 ?  $targetTripData : $tripData;

        // 锁定对方资源
        $lockKeyFill_d = 'hitchhiking'; //锁自已 如果自己是司机，就锁hitchhiking, 否则锁pickup
        $lockKeyFill_p = 'pickup'; //锁对方 如果自己是司机，就锁pickup, 否则锁hitchhiking
        if (!$ShuttleTripModel->lockItem($driverTripId, $lockKeyFill_d) || !$ShuttleTripModel->lockItem($passengerTripId, $lockKeyFill_p)) {
            return $this->jsonReturn(20009, '网络烦忙，请稍候再试');
        }
        
        // 验证
        $ShuttleTripVali = new ShuttleTripVali();
        if (!$ShuttleTripVali->checkMerge($tripData, $targetTripData, $userData)) {
            $errorData = $ShuttleTripVali->getError();
            $ShuttleTripModel->unlockItem($driverTripId, $lockKeyFill_d); // 解锁司机行程
            $ShuttleTripModel->unlockItem($passengerTripId, $lockKeyFill_p); // 解锁乘客行程
            return $this->jsonReturn($errorData['code'] ?? -1, $errorData['data'] ?? [], $errorData['msg'] ?? 'Error check');
        }

        
        $driverUserData = $tripData['user_type'] == 1 ? $userData : (new User())->getItem($targetTripData['uid']);

        Db::connect('database_carpool')->startTrans();
        try {
            // 乘客行程的trip_id设为司机行程id
            $ShuttleTripModel->where('id', $passengerTripId)->update(['trip_id'=>$driverTripId]);
            // 处理同行者
            if ($passengerTripData['seat_count'] > 1) {
                $ShuttleTripPartner = new ShuttleTripPartner();
                $partners = $ShuttleTripPartner->getPartners($tripData['id'], 1) ?? [];
                $ShuttlePartnerServ = new ShuttlePartnerService();
                $ShuttlePartnerServ->getOnCar($passengerTripData, $driverTripData);
            }
            Db::connect('database_carpool')->commit();
        } catch (\Exception $e) {
            Db::connect('database_carpool')->rollback();
            $errorMsg = $e->getMessage();
            $ShuttleTripModel->unlockItem($driverTripId, $lockKeyFill_d); // 解锁司机行程
            $ShuttleTripModel->unlockItem($passengerTripId, $lockKeyFill_p); // 解锁乘客行程
            return $this->jsonReturn(-1, null, lang('Failed'), ['errorMsg'=>$errorMsg]);
        }
        $ShuttleTripModel->unlockItem($driverTripId, $lockKeyFill_d); // 解锁司机行程
        $ShuttleTripModel->unlockItem($passengerTripId, $lockKeyFill_p); // 解锁乘客行程
        // 合并后清理自己和对方的缓存及推消息给对方
        $ShuttleTripServ = new ShuttleTripService();
        $ShuttleTripServ->doAfterMerge($driverTripData, $passengerTripData, $userData);
        // 入库成功后清理这些同行者的相关缓存，及推送消息
        if (isset($partners) && count($partners) > 0) {
            $runType = $tripData['user_type'] == 1 ? 'pickup' : 'hitchhiking';
            $ShuttlePartnerServ->doAfterGetOnCar($partners, $driverTripData, $driverUserData, $runType);
        }
        return $this->jsonReturn(0, 'Successful');
    }
}
