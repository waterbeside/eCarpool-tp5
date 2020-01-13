<?php

namespace app\api\controller\v1\shuttle;

use app\api\controller\ApiBase;
use app\carpool\model\ShuttleLine as ShuttleLineModel;
use app\carpool\model\ShuttleTrip;
use app\carpool\service\shuttle\Trip as ShuttleTripService;
use app\carpool\model\ShuttleLineDepartment;
use app\user\model\Department;
use my\RedisData;


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


    public function index($type = 1, $show_count = 1, $get_sort = 1, $page = 1, $pagesize = 0)
    {
        $userData = $this->getUserData(0);
        $uid = $userData['uid'] ?? 0;
        $redis = new RedisData();
        $departmentModel = new Department();
        $shuttleLineModel = new ShuttleLineModel();
        $shuttleTrip = new ShuttleTrip();

        $ex = 60 * 30;
        $keyword = input('get.keyword');
        $department_id = -1;
        $userData = $this->getUserData(1);

        $department_id = $userData['department_id'];
        if (!$department_id) {
            return $this->jsonReturn(20002, lang('No data'));
        }
        $departmentData = $departmentModel->getItem($department_id);
        if (!$departmentData) {
            return $this->jsonReturn(20002, lang('No data'));
        }
        $departmentPath = $departmentData['path'].','.$departmentData['id'];

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
            $map_lineDept = [
                ['department_id','in', $departmentPath],
            ];
            $lineidSql = ShuttleLineDepartment::field('line_id')->distinct(true)->where($map_lineDept)->buildSql();
            $map  = [
                ['is_delete', "=", Db::raw(0)],
                ['status', "=", Db::raw(1)],
                ['type', '=', $type],
                ['', 'exp', Db::raw("id in $lineidSql")],
            ];
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

        // 取得当前路线的约车需求数
        if (is_numeric($show_count) && $show_count > 0) {
            foreach ($returnData['lists'] as $key => $value) {
                $countData = [
                    'cars' => $shuttleTrip->countByLine($value['id'], 'cars'),
                    'requests' => $shuttleTrip->countByLine($value['id'], 'requests'),
                ];
                $returnData['lists'][$key]['countData'] = $countData;
            }
        }

        // 排序方法
        if (is_numeric($get_sort) && $get_sort > 0) {
            foreach ($returnData['lists'] as $key => $value) {
                $used_total = $shuttleTrip->countByLine($value['id'], 'used_total');
                $user_used_total = $uid > 0 ? $shuttleTrip->countByLine($value['id'], 'used_total', $uid) : 0;
                $sort_0 = $value['sort'];
                $sort = $sort_0 * 2 + $used_total * 10 + $user_used_total * 88;
                $value['sort'] = $sort;
                $value['sort_hot'] = $used_total;
            }
        }
        return $this->jsonReturn(0, $returnData, 'Successful');
    }


    /**
     * 常用路线
     *
     */
    public function common($type = 0)
    {
        if (!is_numeric($type)) {
            $this->jsonReturn(992, 'Error param');
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
                ['l.type', "=", $type]
            ];
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
}
