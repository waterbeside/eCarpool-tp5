<?php

namespace app\api\controller\v1\shuttle;

use app\api\controller\ApiBase;
use app\carpool\model\ShuttleLine as ShuttleLineModel;
use app\carpool\model\ShuttleTrip;
use app\carpool\service\shuttle\Trip as ShuttleTripService;
use app\carpool\model\ShuttleLineDepartment;
use app\user\model\Department;
use my\RedisData;
use my\Utils;


use think\Db;

/**
 * 班车路线
 * Class Line
 * @package app\api\controller
 */
class Line extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }


    public function index($type = -2, $show_count = 1, $get_sort = 1, $page = 1, $pagesize = 0)
    {
        $userData = $this->getUserData(0);
        $uid = $userData['uid'] ?? 0;
        $redis = new RedisData();
        $departmentModel = new Department();
        $shuttleLineModel = new ShuttleLineModel();
        $shuttleTrip = new ShuttleTrip();
        $Utils = new Utils();
        $lnglat = input('param.lnglat');
        $lnglat = $lnglat ? $Utils->stringSetToArray($lnglat, null, false) : null ;

        $ex = 60 * 30;
        $keyword = input('get.keyword');
        $comid = input('get.comid', 0);
        $userData = $this->getUserData(1);
        // 取理部门可见的数据
        $department_id = $userData['department_id'];
        if (!$department_id) {
            return $this->jsonReturn(20002, lang('No data'));
        }
        $departmentData = $departmentModel->getItem($department_id);
        if (!$departmentData) {
            return $this->jsonReturn(20002, lang('No data'));
        }
        // 处理上下班类型
        $type = is_numeric($type) ? intval($type) : -1;
        $pagesize = is_numeric($pagesize) ? intval($pagesize) : 0;
        $pagesize = in_array($type, [-1, 0]) && $pagesize  < 1 ? 50 : $pagesize;
        // 处理缓存
        $returnData = null;
        if (!$keyword) {
            $cacheKey  = $shuttleLineModel->getListCacheKey($type);
            $rowCacheKey = "department_{$department_id},pz_{$pagesize},page_$page";
            $returnData = $redis->hCache($cacheKey, $rowCacheKey);
        }
        if (is_array($returnData) && empty($returnData)) {
            return $this->jsonReturn(20002, [], lang('No data'));
        }

        if (!$returnData) {
            // 先查出该部门可访问的line_id
            $lineidSql = (new ShuttleLineDepartment())->getIdsByDepartmentId($departmentData, true);
            $map  = [
                ['is_delete', "=", Db::raw(0)],
                ['status', "=", Db::raw(1)],
                ['', 'exp', Db::raw("id in $lineidSql")],
            ];
            if (is_numeric($type) && $type > -1) {
                $map[] = ['type', '=', $type];
            } elseif ($type == -2) {
                $map[] = ['type', '>', 0];
            }
            if ($keyword) {
                $map[] = ['start_name|end_name','line',"%$keyword%"];
            }
            $ctor = ShuttleLineModel::field('is_delete, status, create_time, update_time, admin_department_id', true)
                ->where($map)->order('sort Desc');
            $returnData = $this->getListDataByCtor($ctor, $pagesize);
            if (empty($returnData['lists'])) {
                if (!$keyword) {
                    $redis->hCache($cacheKey, $rowCacheKey, [], $ex);
                }
                return $this->jsonReturn(20002, 'No data');
            }
            if (!$keyword) {
                $redis->hCache($cacheKey, $rowCacheKey, $returnData, $ex);
            }
        }

        foreach ($returnData['lists'] as $key => $value) {
            // 取得当前路线的约车需求数
            if (is_numeric($show_count) && $show_count > 0) {
                $countData = [
                    'cars' => $shuttleTrip->countByLine($value['id'], 'cars'),
                    'requests' => $shuttleTrip->countByLine($value['id'], 'requests'),
                ];
                $value['countData'] = $countData;
            }
            // 排序方法
            if (is_numeric($get_sort) && $get_sort > 0) {
                $used_total = $shuttleTrip->countByLine($value['id'], 'used_total');
                $user_used_total = $uid > 0 ? $shuttleTrip->countByLine($value['id'], 'used_total', $uid) : 0;
                $sort_0 = $value['sort'];
                $sort = $sort_0 * 2 + $used_total * 10 + $user_used_total * 88;
                $value['sort'] = $sort;
                $value['sort_hot'] = $used_total;
            }
            $value['distance'] = 0;
            if ($lnglat) {
                $startPoint = [$value['start_longitude'], $value['start_latitude']];
                $value['distance'] = $Utils->getDistance($startPoint, $lnglat) ?: 0;
                $sortDistanc = -1 * round(($value['distance'] / 1000), 1);
                $value['sort'] = $value['sort'] + $sortDistanc * 10;
            }
            // 检查颜色
            if (empty($value['color'])) {
                $value['color'] = $shuttleLineModel->getRandomColor() ?: $value['color'];
            }
            
            $returnData['lists'][$key] = $value;
        }
        if ($comid > 0) {
            $fitList = [];
            foreach ($returnData['lists'] as $key => $value) {
                if ($comid == $value['start_id'] || $comid == $value['end_id']) {
                    $fitList[] = $value;
                }
            }
            $returnData['lists'] = $fitList;
        }
        if (empty($returnData['lists'])) {
            return $this->jsonReturn(20002, 'No data');
        }
        return $this->jsonReturn(0, $returnData, 'Successful');
    }

    /**
     * 常用路线
     *
     * @param integer $type -2包括上下班，-1包括所有，0普通，1上班，2下班
     */
    public function common($type = -2)
    {
        if (!is_numeric($type)) {
            $this->jsonReturn(992, lang('Error Param'));
        }

        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $shuttleLineModel = new ShuttleLineModel();
        $redis = new RedisData();
        $ex = 60 * 60;
        $cacheKey  = $shuttleLineModel->getCommonListCacheKey($uid, $type);
        $listData = $redis->cache($cacheKey);

        if (is_array($listData) && empty($listData)) {
            return $this->jsonReturn(20002, lang('No data'));
        }
        if (!$listData) {
            $field = 'l.id, l.start_name, l.end_name, l.type, l.map_type,
            count(t.line_id) as use_count, max(t.time) as time';
            $map = [
                ['t.status', '>', '-1'],
                ['t.uid', '=', $uid],
                ['l.is_delete', '=', Db::raw(0)],
                ['l.status', "=", Db::raw(1)],
            ];
            if (is_numeric($type) && $type > -1) {
                $map[] = ['l.type', '=', $type];
            } elseif ($type == -2) {
                $map[] = ['l.type', '>', 0];
            }
            $join = [
                ['t_shuttle_line l','l.id = t.line_id', 'left'],
            ];
            $listData = ShuttleTrip::alias('t')->field($field)->join($join)->where($map)->group('line_id')->limit(10)->order('time DESC')->select();
            if (!$listData) {
                $redis->cache($cacheKey, [], 10);
                $this->jsonReturn(20002, 'No data');
            }
            $listData = $listData->toArray();
            $redis->cache($cacheKey, $listData, $ex);
        }
        
        $list = ShuttleTripService::getInstance()->formatTimeFields($listData, 'list', ['time']);
        $returnData = [
            'lists' => $list
        ];
        return $this->jsonReturn(0, $returnData, 'Successful');
    }


    /**
     * 查找我可选的路线公司
     *
     */
    public function companys()
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $departmentId = $userData['department_id'];
        $cacheKey = "carpool:shuttle:line:companys:dptId_$departmentId";
        $redis = new RedisData();
        $list = $redis->cache($cacheKey);
        if (is_array($list) && empty($list)) {
            $this->jsonReturn(20002, 'No data');
        }

        if (empty($list)) {
            $ShuttleLineDepartment = new ShuttleLineDepartment();
            $DepartmentModel = new Department();
            $departmentData =  $DepartmentModel->getItem($departmentId);
            if (!$departmentData) {
                return $this->jsonReturn(20002, lang('No data'));
            }
            $departmentPath = $departmentData['path'].','.$departmentId;
            $join = [
                ['t_shuttle_line l', 't.line_id = l.id', 'left'],
            ];
            $field = 'l.end_id as id, l.end_name as name, l.end_longitude as longitude, l.end_latitude as latitude';
            $map = [
                ['l.is_delete', "=", Db::raw(0)],
                ['l.status', "=", Db::raw(1)],
                ['l.type', "=", '1'],
                ['t.department_id', 'in', $departmentPath],
            ];
            $res = $ShuttleLineDepartment->alias('t')->field($field)->distinct(true)->join($join)->where($map)->select();
            if (!$res) {
                $redis->cache($cacheKey, [], 20);
                return $this->jsonReturn(20002, 'No data');
            }
            $listRes = $res->toArray();
            $haveIds = [];
            $list = [];
            foreach ($listRes as $key => $value) {
                if (in_array($value['id'], $haveIds)) {
                    continue;
                }
                $haveIds[] = $value['id'];
                $list[] = $value;
            }
            $redis->cache($cacheKey, $list, 60);
        }
        $returnData = [
            'lists' => $list,
        ];
        return $this->jsonReturn(0, $returnData, 'Successful');
    }
}
