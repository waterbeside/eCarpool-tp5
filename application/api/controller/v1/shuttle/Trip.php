<?php

namespace app\api\controller\v1\shuttle;

use app\api\controller\ApiBase;
use app\carpool\model\ShuttleLine as ShuttleLineModel;
use app\carpool\model\ShuttleTrip;
use app\carpool\model\User;
use app\carpool\service\ShuttleTrip as ShuttleTripService;
use app\carpool\service\Trips as TripsService;
use app\carpool\service\TripsPushMsg;
use app\carpool\model\ShuttleLineDepartment;
use app\user\model\Department;
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
            $lists = $this->filterListFields($lists, ['seat_count'], true);
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
            $ex = 60 * 5;
            $userAlias = 'u';
            $fields_user = $TripsService->buildUserFields($userAlias, $userFields);
            $fields = 't.id, t.trip_id, t.user_type, t.comefrom, t.line_id';
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
                }
                if ($value['user_type'] == 0 && $value['trip_id'] > 0) {
                    $resDriver =  $ShuttleTripService->getUserTripDetail($value['id'], $userFields, ['id','status','user_type','comefrom'], 0);
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
     * @param array $userFields 要读出的用户字段
     * @param integer $returnType 返回数据 1:支持抛出json，2:以数组形式返回数据
     */
    public function show($id = 0, $show_line = 1, $show_driver = 1, $show_passengers = 2)
    {
        if (!$id) {
            return $this->jsonReturn(992, 'Error param');
        }
        $ShuttleTripService = new ShuttleTripService();
        $data  = $ShuttleTripService->getUserTripDetail($id, [], [], $show_line);

        if (!$data) {
            return $this->jsonReturn(20002, 'No data');
        }
        $trip_id = $data['trip_id'];

        if ($trip_id > 0 && $show_driver) {
            $data['driver'] = $ShuttleTripService->getUserTripDetail($trip_id, [], [], 0);
        }
        if ($trip_id === 0 && $show_passengers) {
            if ($show_passengers == 2) {
                $data['passengers'] = $ShuttleTripService->passengers($id) ?: [];
                $data['took_count'] = count($data['passengers']);
            } else {
                $ShuttleTrip = new ShuttleTrip();
                $data['took_count'] = $ShuttleTrip->countPassengers($id);
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
        $ShuttleTripService = new ShuttleTripService();
        $rqData = $ShuttleTripService->getRqData($rqData);
        $line_id = $rqData['line_id'];
        $comefrom = 0;

        if (!$rqData['create_type']) {
            $this->jsonReturn(992, null, 'Empty create_type', input('post.'));
        }
        if (!in_array($rqData['create_type'], ['cars', 'requests'])) {
            $this->jsonReturn(992, 'Error create_type');
        }

        $rqData['line_data'] = $ShuttleTripService->getExtraInfoLineData($line_id);
        if (!$rqData['line_data']) {
            $this->jsonReturn(20002, '该路线不存在');
        }

        // 创建入库数据
        $addRes = $ShuttleTripService->addTrip($rqData, $userData);
        if (!$addRes) {
            $errorData = $ShuttleTripService->getError();
            $this->jsonReturn($errorData['code'], $errorData['data'], $errorData['msg']);
        }
        $this->jsonReturn(0, ['id'=>$addRes], 'Successful');
    }
    
    /**
     * 乘客搭车
     */
    public function hitchhiking($id, $dev = 0)
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];

        $ShuttleTripService = new ShuttleTripService();
        $ShuttleTripModel = new ShuttleTrip();
        $rqData = $ShuttleTripService->getRqData();
        $rqData['trip_id'] = $id;
        $rqData['create_type'] = 'hitchhiking';

        $tripData = $ShuttleTripModel->getItem($id); //取得司机行程
        if ($dev) {
            return $this->jsonReturn(-1, null, 'test tripData', ['trip_data'=>$tripData, 'uid'=>$uid]);
        }
        if (empty($tripData)) {
            return $this->jsonReturn(20002, '该行程不存在');
        }
        if ($tripData['user_type'] != 1) {
            return $this->jsonReturn(20002, null, '该行程不存在', ['errorMsg'=>'该行程不是司机行程']);
        }
        //检查是否已取消或完成
        if (in_array($tripData['status'], [-1, 3])) {
            return $this->jsonReturn(-1, lang('The trip has been completed or cancelled. Operation is not allowed'));
        }
        // 断定是否自己上自己车
        if ($uid == $tripData['uid']) {
            return $this->jsonReturn(-1, lang('You can`t take your own'));
        }
        // 检查座位是否已满
        $took_count = $ShuttleTripModel->countPassengers($id); //计算已坐车乘客数
        if ($took_count >= $tripData['seat_count']) {
            $returnData = [
                'seat_count' => $tripData['seat_count'],
                'took_count' => $took_count,
            ];
            return $this->jsonReturn(50007, $returnData, lang('Failed, seat is full'));
        }

        $rqData['time'] = strtotime($tripData['time']);
        $rqData['line_id'] = $tripData['line_id'];
        $rqData['line_data'] = $ShuttleTripService->getExtraInfoLineData($id, 2);
        if (!$rqData['line_data']) {
            return $this->jsonReturn(20002, '该路线不存在');
        }

        // 入库
        $addRes = $ShuttleTripService->addTrip($rqData, $userData);
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

        $tripData = $ShuttleTripModel->getItem($id); //取得乘客须求行程
        if (empty($tripData)) {
            $this->jsonReturn(20002, '该行程不存在');
        }
        if ($tripData['user_type'] == 1) {
            return $this->jsonReturn(20002, null, '该行程不存在', ['errorMsg'=>'该行程不是乘客行程']);
        }
        //检查是否已取消或完成
        if (in_array($tripData['status'], [-1, 3])) {
            return $this->jsonReturn(-1, lang('The trip has been completed or cancelled. Operation is not allowed'));
        }
        // 断定是否自己上自己车
        if ($uid == $tripData['uid']) {
            return $this->jsonReturn(-1, lang('You can`t take your own'));
        }
        $rqData['time'] = strtotime($tripData['time']);
        $rqData['line_id'] = $tripData['line_id'];
        $rqData['line_data'] = $ShuttleTripService->getExtraInfoLineData($id, 2);
        
        if (!$rqData['line_data']) {
            return $this->jsonReturn(20002, '该路线不存在');
        }
        Db::connect('database_carpool')->startTrans();
        try {
            // 入库
            $addRes = $ShuttleTripService->addTrip($rqData, $userData); // 添加一条司机行程
            if (!$addRes) {
                $errorData = $ShuttleTripService->getError();
                return $this->jsonReturn($errorData['code'], $errorData['data'], lang('Failed').'. '.$errorData['msg']);
            }
            $ShuttleTripModel->where('id', $id)->update(['trips_id'=>$addRes]); // 乘客行程的trip_id设为司机行程id
            // 提交事务
            Db::connect('database_carpool')->commit();
        } catch (\Exception $e) {
            Db::connect('database_carpool')->rollback();
            $errorMsg = $e->getMessage();
            return $this->jsonReturn(-1, null, lang('Failed'), ['errorMsg'=>$errorMsg]);
        }
        // 消缓存
        $ShuttleTripModel->delItemCache($id); // 消单项行程缓存
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
        return $this->jsonReturn(0, ['id'=>$addRes], 'Successful');
    }

    /**
     * 修改内容 (取消，完结，改变座位数)
     *
     * @return void
     */
    public function change($id = 0)
    {
        if (!$id) {
            return $this->jsonReturn(992, 'Error param');
        }

        $run = input('post.run') ?: input('param.run') ;
        if (!in_array($run, ['cancel', 'finish', 'change_seat'])) {
            return $this->jsonReturn(992, 'Error param');
        }
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $ShuttleTripModel = new ShuttleTrip();
        $tripData = $ShuttleTripModel->getItem($id);
        if (empty($tripData)) {
            return $this->jsonReturn(20002, '该行程不存在');
        }
        //检查是否已取消或完成
        if (in_array($tripData['status'], [-1, 3])) {
            return $this->jsonReturn(-1, lang('The trip has been completed or cancelled. Operation is not allowed'));
        }
        $userType = $tripData['user_type'];
        
        $ShuttleTripService = new ShuttleTripService();
        if ($run === 'cancel') {
            $res = $ShuttleTripService->cancel($tripData, $userData);
        } elseif ($run === 'finish') {
            $res = $ShuttleTripService->finish($tripData, $userData);
        } elseif ($run === 'change_seat') {
            $seat_count = input('post.seat_count/d', 0);
            if ($seat_count < 1) {
                return $this->jsonReturn(992, lang('The number of empty seats cannot be empty'));
            }
            $took_count = $ShuttleTripModel->countPassengers($id); //计算已坐车乘客数
            if ($seat_count < $took_count) {
                return $this->jsonReturn(992, lang('您设置的座位数不能比已搭乘客数少'));
            }
            $res = $ShuttleTripModel->where('id', $id)->update(['seat_count'=>$seat_count]);
        }

        if ($res === false) {
            if (in_array($run, ['cacnel', 'finish'])) {
                $errorData = $ShuttleTripService->getError();
                return $this->jsonReturn($errorData['code'] ?: -1, $errorData['msg'] ?: 'Failed');
            } else {
                return $this->jsonReturn(-1, 'Failed');
            }
        }
        $ShuttleTripModel->delItemCache($id); // 消单项行程缓存
        return $this->jsonReturn(0, 'Successful');
    }
}
