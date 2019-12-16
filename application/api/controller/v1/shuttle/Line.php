<?php

namespace app\api\controller\v1\shuttle;

use app\api\controller\ApiBase;
use app\carpool\model\ShuttleLine as ShuttleLineModel;
use app\carpool\model\ShuttleTrip;
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


    public function index($type = 1, $page = 1, $pagesize = 0)
    {
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
            // 先查出该部门可访问的line_i
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
            $modelObj = ShuttleLineModel::field('is_delete, status, create_time, update_time, admin_department_id, sort', true)->where($map)->order('sort Desc');
            if ($pagesize > 0) {
                $results =    $modelObj->paginate($pagesize, false, ['query' => request()->param()])->toArray();
                if (!$results['data']) {
                    if (!$keyword) {
                        $redis->hCache($cacheKey, $rowCacheKey, [], $ex);
                    }
                    return $this->jsonReturn(20002, [], lang('No data'));
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
                    return $this->jsonReturn(20002, [], lang('No data'));
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
                'lists' => $resData,
                'page' => $pageData,
            ];
            if (!$keyword) {
                $redis->hCache($cacheKey, $rowCacheKey, $returnData, $ex);
            }
        }
        foreach ($returnData['lists'] as $key => $value) {
            $countData = [
                'cars' => $shuttleTrip->countByLine($value['id'], 'cars'),
                'requests' => $shuttleTrip->countByLine($value['id'], 'requests'),
            ];
            $returnData['lists'][$key]['countData'] = $countData;
        }
        return $this->jsonReturn(0, $returnData, 'Successful');
    }
}
