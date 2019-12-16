<?php

namespace app\api\controller\v1\shuttle;

use app\api\controller\ApiBase;
use app\carpool\model\ShuttleLine as ShuttleLineModel;
use app\carpool\model\ShuttleTrip;
use app\carpool\service\ShuttleTrip as ShuttleTripService;
use app\carpool\service\Trips as TripsService;
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
            'id','type','start_name','start_longitude','start_latitude','end_name','end_longitude','end_latitude','status','color','map_type'
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
        if (!$returnData || 1) {
            $userAlias = 'u';
            $offsetTimeArray = $ShuttleTrip->getDefatultOffsetTime(time(), 0, 'Y-m-d H:i:s');
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
            $fields = $ShuttleTrip->getListField('t');
            $fields .=  ',' .$TripsService->buildUserFields($userAlias);
            $offsetTimeArray = $ShuttleTrip->getDefatultOffsetTime(time(), 0, 'Y-m-d H:i:s');
            $join = [
                ["user {$userAlias}", "t.uid = {$userAlias}.uid", 'left'],
            ];
            $map  = [
                ['t.line_id', '=', $line_id],
                ['t.status', 'between', [0,1]],
                ['t.time', 'between', $offsetTimeArray],
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
            $modelObj = $ShuttleTrip->alias('t')->field($fields)->join($join)->where($map)->order('time');
            // ->fetchSql()->select();
            // dump($modelObj);
            // exit;
            if ($pagesize > 0) {
                $results =    $modelObj->paginate($pagesize, false, ['query' => request()->param()])->toArray();
                if (!$results['data']) {
                    if (!$keyword) {
                        $redis->hCache($cacheKey, $rowCacheKey, [], $ex);
                    }
                    return $returnType ? $this->jsonReturn(20002, 'No data') : [20002, null, 'No data'];
                }
                $resData = $results['data'];
                $pageData = $this->getPageData($results);
            } else {
                $resData =    $modelObj->select()->toArray();
                // ->fetchSql()->select();
                if (empty($resData)) {
                    if (!$keyword) {
                        $redis->hCache($cacheKey, $rowCacheKey, [], $ex);
                    }
                    return $returnType ? $this->jsonReturn(20002, 'No data') : [20002, null, 'No data'];
                }
                $total = count($resData);
                $pageData = [
                    'total' => $total,
                    'pageSize' => 0,
                    'lastPage' => 1,
                    'currentPage' => 1,
                ];
            }
            $returnData = [
                'lineData' => $lineData,
                'lists' => $resData,
                'page' => $pageData,
            ];
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
        
        if (isset($res[1]['lists']) && $isGetPassengers) {
            foreach ($res[1]['lists'] as $key => $value) {
                $resPassengers = $this->passengers($value['id'], ['uid', 'loginname', 'name', 'nativename','sex','phone','mobile'], 't.id, t.status', 0);
                dump($resPassengers);
                $res[1]['lists'][$key]['passengers'] = $resPassengers[0] === 0 ?  $resPassengers[1]['lists'] : [];
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
        return $this->jsonReturn($res[0], $res[1], $res[2]);
    }


    /**
     * 我的行程
     *
     * @param integer $page 页码
     * @param integer $pagesize 每页多少条;
     * @return void
     */
    public function my($page = 1, $pagesize = 0)
    {
        $redis = new RedisData();
        $ShuttleLineModel = new ShuttleLineModel();
        $ShuttleTrip = new ShuttleTrip();
        $TripsService = new TripsService();
    }


    /**
     * 取得乘客列表
     *
     * @param integer $id 行程id
     * @param array $userFields 要读出的用户字段
     * @param integer $returnType 返回数据 1:支持抛出json，2:以数组形式返回数据
     */
    public function passengers($id = 0, $userFields = [], $tripFields = ['t.id', 't.time', 't.create_time', 't.status'], $returnType = 1)
    {
        if (!$id) {
            return $returnType ? $this->jsonReturn(992, 'Error param') : [20002, null, 'No data'];
        }
        $ShuttleTrip = new ShuttleTrip();
        $TripsService = new TripsService();
        $ShuttleTripService = new ShuttleTripService();
        $cacheKey = $ShuttleTrip->getPassengersCacheKey($id);
        $redis = new RedisData();
        $res = $redis->cache($cacheKey);
        if ($res === false) {
            $userAlias = 'u';
            $fields = is_array($tripFields) && !empty($tripFields) ? implode(', ', $tripFields) : $tripFields;
            $fields = empty($fields) ? 't.*' : $fields;
            $fields_user = $TripsService->buildUserFields($userAlias, $userFields);

            $fields .=  ',' .$fields_user;
            
            $join = [
                ["user {$userAlias}", "t.uid = {$userAlias}.uid", 'left'],
            ];
            $map = [
                ['t.user_type', '=', Db::raw(0)],
                ['t.trip_id', '=', $id],
            ];
            $res = $ShuttleTrip->alias('t')->field($fields)->join($join)->where($map)->order('t.create_time ASC')->select()->toArray();
            $redis->cache($cacheKey, $res, 60);
        }
        if (!$res) {
            return $returnType ? $this->jsonReturn(20002, 'No data') : [20002, null, 'No data'];
        }
        $res = $ShuttleTripService->formatTimeFields($res, 'list', ['time','create_time']);

        $returnData = [
            'lists' => $res,
        ];
        return $returnType ? $this->jsonReturn(0, $returnData, 'Successful') : [0, $returnData, 'Successful'];
    }
    


    /**
     * 发布一个需求或行程, 或搭车
     *
     */
    public function save($create_type = null)
    {
        $rqData = [];
        $rqData['create_type'] = $create_type ? $create_type : input('post.create_type');
        $rqData['line_id'] = input('post.line_id/d', 0);
        $rqData['trip_id'] = input('post.trip_id/d', 0);
        $rqData['my_trip_id'] = input('post.my_trip_id/d', 0);
        $rqData['seat_count'] = input('post.seat_count/d', 0);
        $rqData['time'] = input('post.time/d', 0);
        $line_id = $rqData['line_id'];
        $comefrom = 0;


        $userData = $this->getUserData(1);
        $uid = $userData['uid'];


        $ShuttleLineModel = new ShuttleLineModel();
        $ShuttleTripService = new ShuttleTripService();
        

        if (!$rqData['create_type']) {
            $this->jsonReturn(992, 'Empty create_type');
        }
        if (!in_array($rqData['create_type'], ['cars', 'requests', 'hitchhiking', 'pickup'])) {
            $this->jsonReturn(992, 'Error create_type');
        }
        // // 检查 line_id
        // if (!$line_id && $rqData['trip_id']) {
        //     $tripData = $ShuttleTrip->getItem($rqData['trip_id']);
        //     $line_id = $tripData ? $tripData['line_id'] : 0;
        // }

        if ($line_id) {
            $lineData = $ShuttleLineModel->getItem($line_id);
            if (!$lineData) {
                $this->jsonReturn(20002, '该路线不存在');
            }
        }

        // 创建入库数据
        $addRes = $ShuttleTripService->addTrip($rqData, $userData);

        if (!$addRes) {
            $errorData = $ShuttleTripService->getError();
            $this->jsonReturn($errorData['code'], $errorData['data'], $errorData['msg']);
        }
        $this->jsonReturn(0, ['id'=>$addRes], 'Successful');

    }
    
    public function change()
    {

    }
}
