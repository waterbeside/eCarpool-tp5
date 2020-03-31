<?php

namespace app\carpool\service\nmtrip;

use app\common\service\Service;
use app\carpool\service\TripsReport as TripsReportService;
use app\user\model\Department as DepartmentModel;

use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;


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
        $cacheKey = "carpool:reports:nmTrip:months";
        $rowKey = "month_{$month},department_{$department_id},showType_{$show_type}";
        $redis = $this->redis();
        $itemData = $redis->hCache($cacheKey, $rowKey);
        if ($itemData === false) {
            $c = false;
            $cacheExp = 3600 * 24 * 30;
            if ($yearMonth_current ==  str_replace('.', '-', $month)) {
                $cacheExp = 3600 * 1;
            }
            $DepartmentModel = new DepartmentModel();
            $TripsReportService = new TripsReportService();

            $period = $TripsReportService->getMonthPeriod($month, 'Ymd0000');
            $map_base = [
                ['t.time', '>=', $period[0]],
                ['t.time', '<', $period[1]],
            ];
            if ($department_id > 0) {
                $dids = $DepartmentModel->getChildrenIds($department_id) ?: [];
                $dids[] = $department_id;
            }
            $join = [];
            //取得有效行程数
            if ($show_type == 1) {
                $map_base[] =  ['t.status', 'in', [1, 3, 4]];
                $map_base[] = ['t.carownid', 'exp', Db::raw('IS NOT NULL')];
                $map_base[] = ['t.carownid', '>', 0];
                if ($department_id > 0) {
                    $join[] = ["user d", "d.uid = t.carownid", 'left'];
                    $join[] = ["user p", "p.uid = t.passengerid", 'left'];
                    $map_base[] = ['d.department_id|p.department_id', 'in', $dids];
                }
                $c = InfoModel::alias('t')->where($map_base)->join($join)->count();
            }

            if (in_array($show_type, [2, 3, 4])) {
                $map_base[] = ['t.status', '<>', 2];
                $join = [];
                $map_j = [];
                if ($department_id > 0) {
                    $map_j[] = ['u.department_id', 'in', $dids];
                    $join[] = ["user u", "u.uid = j.uid", 'left'];
                }

                $viewSql_info_d = InfoModel::alias('t')->field('carownid as uid')->where($map_base)->group('carownid')->buildSql();
                $viewSql_wall_d = WallModel::alias('t')->field('carownid as uid')->where($map_base)->group('carownid')->buildSql();
                $viewSql_info_p = InfoModel::alias('t')->field('passengerid as uid')->where($map_base)->group('passengerid')->buildSql();


                //参与司机人数
                if ($show_type == 2) {
                    $baseSql_u = "($viewSql_info_d UNION $viewSql_wall_d )";
                    $baseSql = Db::connect('database_carpool')->table($baseSql_u)->alias('j')->field('uid')
                        ->join($join)
                        ->where($map_j)
                        ->group('j.uid')
                        ->buildSql();
                }

                //参与乘客数
                if ($show_type == 3) {
                    $baseSql = Db::connect('database_carpool')->table($viewSql_info_p)->alias('j')->field('uid')
                        ->join($join)
                        ->where($map_j)
                        ->group('j.uid')
                        ->buildSql();
                }

                //参与乘客数
                if ($show_type == 4) {
                    $baseSql_u = "($viewSql_info_d UNION $viewSql_wall_d  UNION $viewSql_info_p)";
                    $baseSql = Db::connect('database_carpool')->table($baseSql_u)->alias('j')->field('uid')
                        ->join($join)
                        ->where($map_j)
                        ->group('j.uid')
                        ->buildSql();
                }
                $map = [['', 'exp', Db::raw('un.uid IS NOT NULL AND un.uid > 0')]];
                $c = Db::connect('database_carpool')->table($baseSql)->alias('un')->field('uid')->where($map)->group('un.uid')->count();
            }
            $itemData = $c;
            $redis->hCache($cacheKey, $rowKey, $itemData, $cacheExp);
        }

        return $itemData;
    }
}
