<?php

namespace app\api\controller\v2;

use think\facade\Env;
use app\api\controller\ApiBase;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\model\Address;
use app\carpool\model\user as UserModel;
use app\carpool\model\UserPosition as UserPositionModel;
use app\carpool\model\Grade as GradeModel;

use app\carpool\service\Trips as TripsService;
use app\carpool\service\TripsChange as TripsChangeService;
use app\carpool\service\TripsList as TripsListService;
use app\carpool\service\TripsDetail as TripsDetailService;
use InfoController;
use my\RedisData;
use think\Db;

/**
 * 行程相关
 * Class Trips
 * @package app\api\controller
 */
class Trips extends ApiBase
{

    protected $cacheKey_myTrip = "carpool:trips:my:";
    protected $cacheKey_myInfo = "carpool:trips:my_info:";
    protected $cacheKey_passengers = "carpool:trips:passengers:";

    /**
     * 我的行程
     */
    public function index($pagesize = 20, $type = 0, $fullData = 0)
    {
        $page = input('param.page', 1);
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];

        $redis            = new RedisData();
        $TripsListService = new TripsListService();

        if (!$type) {
            $cacheKey = $this->cacheKey_myTrip . "u{$uid}";
            $cacheField = "pz{$pagesize}_p{$page}_fd{$fullData}";
            $cacheExp = 60 * 2;
            $cacheData = $redis->hGet($cacheKey, $cacheField);
            if ($cacheData) {
                if ($cacheData == "-1") {
                    return $this->jsonReturn(20002, lang('No data'));
                }
                $returnData = json_decode($cacheData, true);
            }
        }

        if (!isset($returnData)) {
            $returnData = $TripsListService->myList($userData, $pagesize, $type);
            if ($returnData === false) {
                if (!$type) {
                    $redis->hSet($cacheKey, $cacheField, -1);
                    $redis->expire($cacheKey, 5);
                }
                return $this->jsonReturn(20002, lang('No data'));
            }
            if (!$type) {
                $redis->hSet($cacheKey, $cacheField, json_encode($returnData));
                $redis->expire($cacheKey, $cacheExp);
            }
        }
        
        // TODO::添加显示乘客到列表
        foreach ($returnData['lists'] as $k => $v) {
            $returnData['lists'][$k]['passengers'] = $v['love_wall_ID'] > 0 ?
                $this->passengers($v['love_wall_ID'], null, 0, ['p_name','p_sex','status','subtime','time'], 20) : [];
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

        $redis            = new RedisData();
        $TripsListService = new TripsListService();

        // 查缓存
        $cacheKey = "carpool:trips:history:u{$uid}:pz{$pagesize}_p{$page}";
        $cacheExp = 60 * 3;
        $cacheData = $redis->get($cacheKey);
        if ($cacheData) {
            if ($cacheData == "-1") {
                return $this->jsonReturn(20002, lang('No data'));
            }
            $returnData = json_decode($cacheData, true);
        }

        if (!isset($returnData)) {
            $returnData = $TripsListService->history($userData, $pagesize);
            if ($returnData === false) {
                $redis->setex($cacheKey, $cacheExp, -1);
                return $this->jsonReturn(20002, lang('No data'));
            }
            $redis->setex($cacheKey, $cacheExp, json_encode($returnData));
        }
        
        // TODO::添加显示乘客到列表
        foreach ($returnData['lists'] as $k => $v) {
            $returnData['lists'][$k]['passengers'] = $v['love_wall_ID'] > 0 ?
                $this->passengers($v['love_wall_ID'], null, 0, ['p_name','p_sex','status','subtime','time'], 3600) : [];
        }
        $this->jsonReturn(0, $returnData, "success");
        // $TripsService->unsetResultValue($this->index($pagesize, 1, 1));
    }


    /**
     * 墙上空座位
     */
    public function wall_list($pagesize = 20, $keyword = "", $city = null, $map_type = null)
    {
        $TripsService = new TripsService();
        $TripsListService = new TripsListService();
        $userData = $this->getUserData(1);
        $company_id = $userData['company_id'];
        $time_e = strtotime("+20 day");
        $time_s = strtotime("-1 hour");
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

        if ($city) {
            $map[] = ['s.city', '=', $city];
        }

        if (is_numeric($map_type)) {
            $map[] = ['t.map_type', '=', $map_type];
        }

        $returnData = $TripsListService->wall_list($map, $pagesize);
        if ($returnData === false) {
            return $this->jsonReturn(20002, lang('No data'));
        }

        // TODO::添加显示乘客到列表
        foreach ($returnData['lists'] as $k => $v) {
            $returnData['lists'][$k]['passengers'] = $this->passengers($v['id'], null, 0, ['p_name','p_sex','status','subtime','time'], 30);
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
                // ['p.company_id','=',$company_id], // from buildCompanyMap;
                // ['love_wall_ID','>',0],
                // ['go_time','between time',[date('YmdHi',$time_s),date('YmdHi',$time_e)]],
                ['t.time', '<', (date('YmdHi', $time_e))],
                ['t.time', '>', (date('YmdHi', $time_s))],
            ];
        }


        $map[] = $TripsService->buildCompanyMap($userData, 'p');
        $map[] = $TripsService->buildStatusMap($status);
        // dump($map);exit;
        if ($keyword) {
            $map[] = ['s.addressname|p.name|e.addressname|t.startname|t.endname', 'like', "%{$keyword}%"];
        }
        if ($city) {
            $map[] = ['s.city', '=', $city];
        }
        if (is_numeric($map_type)) {
            $map[] = ['t.map_type', '=', $map_type];
        }

        $returnData = $TripsListService->info_list($map, $pagesize, $wid, $orderby);
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
        if (empty($status)) {
            $status = "neq|2";
        }
        $TripsService = new TripsService();

        $res = false;
        if ($status == 'neq|2') {
            $cacheKey = $this->cacheKey_passengers."wall_$id";
            $randExp = getRandValFromArray([2, 4, 6]);
            $exp = $exp < 60 ? $exp + $randExp * 3 : $exp + $randExp * 5;
            $redis = new RedisData();
            $res = $redis->cache($cacheKey);
        }
        
        
        if ($res === false) {
            $res =  $this->info_list("", $status, 0, $id, 0, 'status ASC, time ASC');
            if ($status == 'neq|2') {
                $redis->cache($cacheKey, $res, $exp);
            }
        }
        if ($res) {
            foreach ($res['lists'] as $key => $value) {
                if (is_array($showFields)) {
                    $itemData = [];
                    foreach ($showFields as $field) {
                        $itemData[$field] = isset($value[$field]) ? $value[$field] : null;
                    }
                    $res['lists'][$key] = $itemData;
                } else {
                    $res['lists'][$key] = $TripsService->unsetResultValue($value, ['love_wall_ID']);
                }
            }
            if (isset($res['page'])) {
                unset($res['page']);
            }
        }
        $code = $res && count($res['lists']) > 0 ? 0  : 20002;
        $msg =  $res && count($res['lists']) > 0 ? 'Successful'  : 'No data';
        return $returnType ? $this->jsonReturn($code, $res, $msg) : $res['lists'] ;
    }


    /**
     * 删除我的缓存
     *
     * @param integer|array $uid 用户uid
     * @return void
     */
    protected function removeCache($uid)
    {
        if (is_array($uid)) {
            $uid = array_unique($uid);
            foreach ($uid as $v) {
                $this->removeCache($v);
            }
        } elseif (is_numeric($uid)) {
            $redis = new RedisData();
            $cacheKey_01 = $this->cacheKey_myInfo . "u{$uid}";
            $cacheKey_02 = $this->cacheKey_myTrip . "u{$uid}";
            $redis->delete($cacheKey_01, $cacheKey_02);
        }
    }
}
