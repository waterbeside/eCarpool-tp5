<?php

namespace app\carpool\service\shuttle;

use app\common\service\Service;
use app\carpool\service\TripsReport as TripsReportService;
use app\carpool\model\ShuttleTrip as ShuttleTripModel;
use app\user\model\Department as DepartmentModel;

use think\Db;

class TripReport extends Service
{

    /**
     * 取得提定月分煌数据
     *
     * @param array $data
     * @return array
     */
    public function getMonthSum($data)
    {
        $yearMonth_current = date("Y-m"); //当前年月
        $month = $data['month'];
        $show_type = $data['show_type'] ? $data['show_type'] : 1;
        $department_id = $data['department_id'];
        $cacheKey = "carpool:reports:shuttleTrip:months";
        $rowKey = "month_{$month},department_{$department_id},showType_{$show_type}";
        $redis = $this->redis();
        $itemData = $redis->hCache($cacheKey, $rowKey);
        if ($itemData === false) {
            $c = false;
            $cacheExp = 3600 * 24 * 30;
            if ($yearMonth_current ==  str_replace('.', '-', $month)) {
                $cacheExp = 3600 * 1;
            }
            $TripsReportService = new TripsReportService();
            $DepartmentModel = new DepartmentModel();

            $period = $TripsReportService->getMonthPeriod($month, 'Y-m-d 00:00:00');
            $map_base = [
                ['t.time', '>=', $period[0]],
                ['t.time', '<', $period[1]],
                ['t.status', 'in', [0, 1, 2, 3, 5]],
            ];
            if ($department_id > 0) {
                $dids = $DepartmentModel->getChildrenIds($department_id) ?: [];
                $dids[] = $department_id;
                $map_base[] = ['t.department_id', 'in', $dids];
            }
            $join = [];
            //取得有效行程数
            if ($show_type == 1) {
                $map_base[] = ['t.trip_id', '>', 0];
                $c = ShuttleTripModel::alias('t')->where($map_base)->join($join)->count();
            }

            if (in_array($show_type, [2, 3, 4])) {
                if ($show_type == 2) { // 参与司机数
                    $map_base[] = ['t.user_type', '=', 1];
                } elseif ($show_type == 3) { // 参与乘客数
                    $map_base[] = ['t.trip_id', '=', 0];
                    // $map_base[] = ['t.trip_id', '>', 0];
                } elseif ($show_type == 4) { // 参与人数
                    // TODO: 参与人数
                }
                $viewSql = ShuttleTripModel::alias('t')->field('uid, count(*) as c, max(id) as id')->where($map_base)->group('uid')->buildSql();
                $c = Db::connect('database_carpool')->table($viewSql)->alias('un')->field('uid')->count();
            }
            $itemData = $c;
            $redis->hCache($cacheKey, $rowKey, $itemData, $cacheExp);
        }
        return $itemData;
    }
}
