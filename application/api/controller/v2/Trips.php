<?php

namespace app\api\controller\v2;

use think\facade\Env;
use app\api\controller\ApiBase;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\model\Address;
use app\carpool\model\User as UserModel;
use app\carpool\model\UserPosition as UserPositionModel;
use app\carpool\model\Grade as GradeModel;

use app\carpool\service\Trips as TripsService;
use app\carpool\service\TripsChange as TripsChangeService;
use app\carpool\service\TripsList as TripsListService;
use app\carpool\service\TripsDetail as TripsDetailService;
use app\carpool\service\nmtrip\Trip as NmTripService;

use my\RedisData;
use think\Db;

/**
 * 行程相关
 * Class Trips
 * @package app\api\controller
 */
class Trips extends ApiBase
{

    /**
     * 我的行程
     */
    public function index($pagesize = 20, $type = 0, $fullData = 0)
    {
        $page = input('param.page', 1);
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];

        $redis = RedisData::getInstance();
        $TripsListService = new TripsListService();
        $TripsService = new TripsService();

        if (!$type) {
            $cacheKey = $TripsService->getMyListCacheKey($uid);
            $cacheField = "pz{$pagesize}_p{$page}_fd{$fullData}";
            $cacheExp = 60 * 2;
            $cacheData = $redis->hCache($cacheKey, $cacheField);
            if ($cacheData) {
                if ($cacheData == "-1") {
                    return $this->jsonReturn(20002, lang('No data'));
                }
                $returnData = $cacheData;
            }
        }

        if (!isset($returnData)) {
            $returnData = $TripsListService->myList($userData, $pagesize, $type);
            if ($returnData === false) {
                if (!$type) {
                    $redis->hCache($cacheKey, $cacheField, -1, 5, 10);
                }
                return $this->jsonReturn(20002, lang('No data'));
            }
            if (!$type) {
                $redis->hCache($cacheKey, $cacheField, $returnData, $cacheExp, $cacheExp);
            }
        }
        
        // TODO::添加显示乘客到列表
        $NmTripService = new NmTripService();
        foreach ($returnData['lists'] as $k => $v) {
            $returnData['lists'][$k]['passengers'] = $v['love_wall_ID'] > 0 ?
                $NmTripService->passengers($v['love_wall_ID'], ['name', 'sex', 'phone', 'mobile'], ['time', 'subtime', 'status'], 'p')
                // $this->passengers($v['love_wall_ID'], null, 0, ['p_name','p_sex','p_phone','p_mobile','status','subtime','time'], 20)
                : [];
        }
        $this->jsonReturn(0, $returnData, "success");
    }

    /**
     * 历史行程
     */
    public function history($pagesize = 20)
    {
        $page = input('param.page', 1);
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];

        $redis = RedisData::getInstance();
        $TripsListService = new TripsListService();

        // 查缓存
        $cacheKey = "carpool:trips:history:u{$uid}";
        $rowCacheKey = "pz{$pagesize}_p{$page}";
        $cacheExp = 60 * 5;
        $cacheData = $redis->hCache($cacheKey, $rowCacheKey);
        if ($cacheData) {
            if ($cacheData == "-1") {
                return $this->jsonReturn(20002, lang('No data'));
            }
            $returnData = $cacheData;
        }

        if (!isset($returnData)) {
            $returnData = $TripsListService->history($userData, $pagesize);
            if ($returnData === false) {
                $redis->hCache($cacheKey, $rowCacheKey, -1, $cacheExp);
                return $this->jsonReturn(20002, lang('No data'));
            }
            $redis->hCache($cacheKey, $rowCacheKey, $returnData, $cacheExp);
        }
        
        // TODO::添加显示乘客到列表
        // foreach ($returnData['lists'] as $k => $v) {
        //     $returnData['lists'][$k]['passengers'] = $v['love_wall_ID'] > 0 ?
        //         $this->passengers($v['love_wall_ID'], null, 0, ['p_name','p_sex','p_phone','p_mobile','status','subtime','time'], 3600) : [];
        // }
        $this->jsonReturn(0, $returnData, "success");
        // $TripsService->unsetResultValue($this->index($pagesize, 1, 1));
    }


    /**
     * 墙上空座位
     */
    public function wall_list($pagesize = 20, $keyword = "", $city = null, $map_type = -1)
    {
        $TripsService = new TripsService();
        $TripsListService = new TripsListService();
        $WallModel = new WallModel();
        $userData = $this->getUserData(1);
        $company_id = $userData['company_id'] ?: 0;
        $time_e = strtotime("+20 day");
        $time_s = strtotime("-1 hour");
        $page = input('get.page', 1);

        $map = [
            // ['d.company_id','=',$company_id], // from buildCompanyMap;
            // ['love_wall_ID','>',0],
            ['t.status', '<', 2],
            // ['go_time','between time',[date('YmdHi',$time_s),date('YmdHi',$time_e)]],
            ['t.time', '<', (date('YmdHi', $time_e))],
            ['t.time', '>', (date('YmdHi', $time_s))],
        ];
        $map[] = $TripsService->buildCompanyMap($userData, 'd');

        if ($keyword) {
            $map[] = ['d.name|s.addressname|e.addressname|t.startname|t.endname', 'like', "%{$keyword}%"];
        }
        

        if ($city && !in_array(trim($city), ['Fail', 'Fail', '获取失败', 'Thất bại', '获取城市中', 'All', 'all', '全部'])) {
            $map[] = ['s.city', '=', $city];
        } else {
            $city = '';
        }

        if (is_numeric($map_type) && $map_type > -1) {
            $map[] = ['t.map_type', '=', $map_type];
        }

        $redis = RedisData::getInstance();
        $cacheKey = $WallModel->getListCacheKey($company_id);
        $rowCacheKey = "pz{$pagesize}_p{$page}_mapType_{$map_type}_city_{$city}";
        $returnData = false;
        if ($cacheKey && !$keyword) {
            $returnData = $redis->hCache($cacheKey, $rowCacheKey);
        }
        if (!$returnData) {
            $returnData = $TripsListService->wall_list($map, $pagesize);
            if ($cacheKey && !$keyword && $returnData) {
                $ex = $page > 1 ? 3 : 60 * 2;
                $redis->hCache($cacheKey, $rowCacheKey, $returnData, $ex);
            }
        }
        if ($returnData === false) {
            return $this->jsonReturn(20002, lang('No data'));
        }

        // TODO::添加显示乘客到列表
        $NmTripService = new NmTripService();
        foreach ($returnData['lists'] as $k => $v) {
            // $returnData['lists'][$k]['passengers'] = $this->passengers($v['id'], null, 0, ['p_name','p_sex','p_phone','p_mobile','status','subtime','time'], 30);
            $returnData['lists'][$k]['passengers'] = $NmTripService->passengers($v['id'], ['name', 'sex', 'phone', 'mobile'], ['time', 'subtime', 'status'], 'p');
        }
        $this->jsonReturn(0, $returnData, "success");
    }


    /**
     * 约车需求
     */
    public function info_list($keyword = "", $status = 0, $pagesize = 50, $wid = 0, $returnType = 1, $orderby = '', $city = null, $map_type = null)
    {
        $TripsService = new TripsService();
        $TripsListService = new TripsListService();
        $userData = $this->getUserData(1);
        $company_id = $userData['company_id'];
        $time_e = strtotime("+20 day");
        $time_s = strtotime("-1 hour");

        if ($wid > 0) {
            $map = [
                ['love_wall_ID', '=', $wid]
            ];
        } else {
            $map = [
                ['t.time', '<', (date('YmdHi', $time_e))],
                ['t.time', '>', (date('YmdHi', $time_s))],
            ];
            if (is_numeric($map_type)) {
                $map[] = ['t.map_type', '=', $map_type];
            }
            $map[] = $TripsService->buildCompanyMap($userData, 'p');
        }


        $map[] = $TripsService->buildStatusMap($status);
        // dump($map);exit;
        if ($keyword) {
            $map[] = ['s.addressname|p.name|e.addressname|t.startname|t.endname', 'like', "%{$keyword}%"];
        }
        if ($city) {
            $map[] = ['s.city', '=', $city];
        }

        $returnData = $TripsListService->info_list($map, $pagesize, $orderby);
        

        if ($returnType && $returnData === false) {
            return $this->jsonReturn(20002, lang('No data'));
        }
        return $returnType ? $this->jsonReturn(0, $returnData, "success") : $returnData;
    }



    /**
     * 乘客列表
     * @param  integer          $id 空座位id
     * @param  integer|string   $status 状态筛选
     */
    public function passengers($id, $status = "neq|2", $returnType = 1, $showFields = null, $exp = 10)
    {
        $NmTripService = new NmTripService();
        $tripFields = [
            'id', 'status', 'time', 'subtime',
            'start_name', 'start_latitude', 'start_longitude',
            'end_name', 'end_latitude', 'end_longitude'
        ];
        $list = $NmTripService->passengers($id, [], $tripFields, 'p', 1);
        $returnData = [
            'lists' => $list
        ];
        return $this->jsonReturn(0, $returnData, 'Successful');

        // dump($list);exit;
        /** 下边为旧方法，暂时弃用 */
        // if (empty($status)) {
        //     $status = "neq|2";
        // }
        // $TripsService = new TripsService();
        // $InfoModel = new InfoModel();
        // $res = false;
        // if ($status == 'neq|2') {
        //     $cacheKey = $InfoModel->getPassengersCacheKey($id);
        //     $randExp = getRandValFromArray([2, 4, 6]);
        //     $exp = $exp < 60 ? $exp + $randExp * 3 : $exp + $randExp * 5;
        //      $redis = RedisData::getInstance();
        //     $res = $redis->cache($cacheKey);
        // }
        
        
        // if ($res === false) {
        //     $res =  $this->info_list("", $status, 0, $id, 0, 'status ASC, time ASC');
        //     if ($status == 'neq|2') {
        //         $redis->cache($cacheKey, $res, $exp);
        //     }
        // }
        // if ($res) {
        //     foreach ($res['lists'] as $key => $value) {
        //         if (is_array($showFields)) {
        //             $itemData = [];
        //             foreach ($showFields as $field) {
        //                 $itemData[$field] = isset($value[$field]) ? $value[$field] : null;
        //             }
        //             $res['lists'][$key] = $itemData;
        //         } else {
        //             $res['lists'][$key] = $TripsService->unsetResultValue($value, ['love_wall_ID']);
        //         }
        //     }
        //     if (isset($res['page'])) {
        //         unset($res['page']);
        //     }
        // }
        // $code = $res && count($res['lists']) > 0 ? 0  : 20002;
        // $msg =  $res && count($res['lists']) > 0 ? 'Successful'  : 'No data';
        // return $returnType ? $this->jsonReturn($code, $res, $msg) : ( $res['lists'] ? $res['lists'] : [] );
    }

    /**
     * 地图上的墙上空座位
     */
    public function map_cars($map_type = -1)
    {
        $TripsService = new TripsService();
        $TripsListService = new TripsListService();
        $WallModel = new WallModel();
        $userData = $this->getUserData(1);
        $company_id = $userData['company_id'];
        $time_e = strtotime("+20 day");
        $time_s = strtotime("-1 hour");

        $redis = RedisData::getInstance();
        $cacheKey = $WallModel->getMapCarsCacheKey($company_id);
        $rowCacheKey = "mapType_{$map_type}";
        $returnData = $redis->hCache($cacheKey, $rowCacheKey);
        if (!$returnData) {
            $map = [
                ['t.status', '<', 2],
                ['t.time', '<', (date('YmdHi', $time_e))],
                ['t.time', '>', (date('YmdHi', $time_s))],
            ];
            $map[] = $TripsService->buildCompanyMap($userData, 'd');
            if (is_numeric($map_type) && $map_type > -1) {
                $map[] = ['t.map_type', '=', $map_type];
            }
            $returnData = $TripsListService->wall_list($map, 0);
            if ($returnData) {
                $redis->hCache($cacheKey, $rowCacheKey, $returnData, 60 * 5);
            }
        }
        if ($returnData === false) {
            return $this->jsonReturn(20002, lang('No data'));
        }
        $this->jsonReturn(0, $returnData, "success");
    }
}
