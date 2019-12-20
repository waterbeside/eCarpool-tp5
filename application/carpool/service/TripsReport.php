<?php

namespace app\carpool\service;

use app\common\service\Service;
use app\carpool\service\Trips as TripsService;

use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\model\Address;
use app\carpool\model\Configs as ConfigsModel;
use app\carpool\model\User as UserModel;
use app\user\model\Department as DepartmentModel;

use think\Db;

class TripsReport extends Service
{


    /**
     * 计算期间
     */
    public function getMonthPeriod($date, $format = 'Y-m-d')
    {
        $date = str_replace('.', '-', $date);
        $firstday = date("Y-m-01", strtotime($date));
        $lastday = date("Y-m-d", strtotime("$firstday +1 month"));
        return array(date($format, strtotime($firstday)), date($format, strtotime($lastday)));
    }



    /**
     * 计算相差月数
     *
     * @param string $start 起始月（Y-m）
     * @param string $end   结束月（Y-m）
     * @param integer $isIncludeEnd  是否包含结束月份
     * @return integer
     */
    public function countMonthNum($start, $end, $isIncludeEnd = 1)
    {
        $start_t = strtotime($start);
        $end_t = strtotime($end);
        $start_y = date('Y', $start_t);
        $start_m = date('m', $start_t);
        $end_y = date('Y', $end_t);
        $end_m = date('m', $end_t);
        $diff_y = $end_y - $start_y;
        $diff_m = $end_m - $start_m;
        $diff = $diff_y * 12 + $diff_m;
        return $isIncludeEnd ? $diff + 1 : $diff;
    }

    /**
     * 取得起终时间的所有月分，并返回数组
     *
     * @param string $start 起始月（Y-m）
     * @param string $end 结束月（Y-m）
     * @return array
     */
    public function getMonthArray($start, $end, $format = "Y-m")
    {
        $num = $this->countMonthNum($start, $end, 1);
        $months = [];
        for ($i = 0; $i < $num; $i++) {
            $months[] = date($format, strtotime("$start +" . $i . " month"));
        }
        return $months;
    }


    /**
     * 取得起终时间的所有月分，并返回数组
     *
     * @param string $start 起始月（Y-m）
     * @param string $end 结束月（Y-m）
     * @return array
     */
    public function defautlDateRange($start, $end)
    {
        $returnData = [
            'start' => $start,
            'end' => $end,
        ];
        return $returnData;
    }



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
        $department = $data['department'];
        $cacheKey = "carpool:reports:trips:month_{$month}:department_{$department},showType_{$show_type}";
        $redis = $this->redis();
        $itemData = $redis->cache($cacheKey);
        if ($itemData === false) {
            $c = false;
            $cacheExp = 3600 * 24 * 30;
            if ($yearMonth_current ==  str_replace('.', '-', $month)) {
                $cacheExp = 3600 * 1;
            }
            $TripsService = new TripsService();

            $period = $this->getMonthPeriod($month, 'Ymd0000');
            $map_base = [
                ['t.time', '>=', $period[0]],
                ['t.time', '<', $period[1]],
            ];
            $join = [];
            //取得有效行程数
            if ($show_type == 1) {
                $map_base[] =  ['t.status', 'in', [1, 3, 4]];
                $map_base[] = ['t.carownid', 'exp', Db::raw('IS NOT NULL')];
                if ($department > 0) {
                    $join = $TripsService->buildTripJoins('d,p,department');
                    $map_base[] = ['', 'exp', Db::raw("( FIND_IN_SET($department,dd.path) OR FIND_IN_SET($department,pd.path) OR dd.id = $department OR pd.id = $department )")];
                }
                $c = InfoModel::alias('t')->where($map_base)->join($join)->count();
            }

            if (in_array($show_type, [2, 3, 4])) {
                $map_base[] = ['t.status', '<>', 2];
                $join = [];
                $map_j = [];
                if ($department > 0) {
                    $map_j[] = ['', 'exp', Db::raw("( FIND_IN_SET($department,ud.path) OR $department = u.department_id)")];
                    $join = $TripsService->buildTripJoins('u,department', 'j');
                }

                $viewSql_info_d = InfoModel::alias('t')->field('carownid as userid')->where($map_base)->group('carownid')->buildSql();
                $viewSql_wall_d = WallModel::alias('t')->field('carownid as userid')->where($map_base)->group('carownid')->buildSql();
                $viewSql_info_p = InfoModel::alias('t')->field('passengerid as userid')->where($map_base)->group('passengerid')->buildSql();


                //参与司机人数
                if ($show_type == 2) {
                    $baseSql_u = "($viewSql_info_d UNION $viewSql_wall_d )";
                    $baseSql = Db::connect('database_carpool')->table($baseSql_u)->alias('j')->field('userid')
                        ->join($join)
                        ->where($map_j)
                        ->group('j.userid')
                        ->buildSql();
                }

                //参与乘客数
                if ($show_type == 3) {
                    $baseSql = Db::connect('database_carpool')->table($viewSql_info_p)->alias('j')->field('userid')
                        ->join($join)
                        ->where($map_j)
                        ->group('j.userid')
                        ->buildSql();
                }

                //参与乘客数
                if ($show_type == 4) {
                    $baseSql_u = "($viewSql_info_d UNION $viewSql_wall_d  UNION $viewSql_info_p)";
                    $baseSql = Db::connect('database_carpool')->table($baseSql_u)->alias('j')->field('userid')
                        ->join($join)
                        ->where($map_j)
                        ->group('j.userid')
                        ->buildSql();
                }
                $map = [['', 'exp', Db::raw('un.userid IS NOT NULL AND un.userid > 0')]];
                $c = Db::connect('database_carpool')->table($baseSql)->alias('un')->field('userid')->where($map)->group('un.userid')->count();
            }
            $itemData = $c;
            $redis->cache($cacheKey, $itemData, $cacheExp);
        }

        return $itemData;
    }
}
