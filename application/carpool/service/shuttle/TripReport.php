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

    /**
     * 取得用户排名列表
     *
     * @return array
     */
    public function getUserRanking($timeBetween = null, $userType = null, $limit = 50, $returnSql = false)
    {
        if (empty($timeBetween) || !is_array($timeBetween) || count($timeBetween) < 2) {
            return false;
        }
        if (!in_array($userType, [0, 1])) {
            return false;
        }
        $whereBase = [
            ['is_delete', '=', Db::raw(0)],
            ['status', 'in', [0,1,2,3,4,5]],
            ['user_type', '=', 0],
            ['trip_id', '>', 0],
            ['time', 'between', [$timeBetween[0], $timeBetween[1]]]
        ];
        $fieldBase = 'id, trip_id, uid, status, time';
        $ShuttleTripModel = new ShuttleTripModel();
        $baseSql = $ShuttleTripModel->field($fieldBase)->where($whereBase)->buildSql(); // 查出所有有司机的乘客行程。
        $resFields = "u.uid, u.loginname, u.nativename, u.name, u.Department as department, u.companyname, count(u.uid) as num";
        if ($userType === 1) { // 查询司机排名
            $join = [
                ['t_shuttle_trip b', 'b.id = a.trip_id', 'left'],
                ['user u', 'u.uid = b.uid', 'left'],
            ];
        } else { // 查询乘客排名
            $join = [
                ['user u', 'u.uid = a.uid', 'left'],
            ];
        }
        $ctor = Db::connect('database_carpool')->table($baseSql)->alias('a')->field($resFields)->join($join)->group('u.uid')->order('num DESC');
        if ($limit === true) {
            $returnSql = true;
        } else {
            $limit = is_numeric($limit) ? $limit : 50;
        }
        return $returnSql ? $ctor->buildSql() : $ctor->limit($limit)->select();
    }


    /**
     * 取得今天有司机乘客组合的行程
     *
     * @return array
     */
    public function getTodayJoint()
    {
        $ShuttleTripModel = new ShuttleTripModel();

        $timeBetween = [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')];
        $where =  $ShuttleTripModel->buildJointWhere($timeBetween, [], 't');
        $field = "t.id, t.trip_id, 'shuttle_trip' as `from`, t.time, tt.plate,
                d.uid as d_uid, d.name as d_name, d.companyname as d_companyname, d.Department as d_department,
                p.uid as p_uid, p.name as p_name, p.companyname as p_companyname, p.Department as p_department
            ";
        $join = [
            ['t_shuttle_trip tt', 'tt.id = t.trip_id', 'left'],
            ['user d', 'd.uid = tt.uid', 'left'],
            ['user p', 'p.uid = t.uid', 'left'],
        ];
        $res = $ShuttleTripModel->alias('t')->field($field)->join($join)->where($where)->order('d.Department, t.trip_id, d.uid, t.time')->select();
        return $res ? $res->toArray() : [];
    }
}
