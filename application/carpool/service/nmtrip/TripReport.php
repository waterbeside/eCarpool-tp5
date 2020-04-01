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
                    $baseSql_u = "($viewSql_info_d UNION ALL $viewSql_wall_d )";
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
                    $baseSql_u = "($viewSql_info_d UNION ALL $viewSql_wall_d  UNION ALL $viewSql_info_p)";
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

    /**
     * 取得用户排名列表
     *
     * @param array $timeBetween 时间范围 [startTime, endTime]
     * @param integer $userType 用户类型 0乘客 ，1司机
     * @param integer $limit 条数
     * @param boolean $returnSql 是否返回sql 默认false
     * @return mixed array or string
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
            ['status', 'in', [0,1,3,4]],
            ['carownid', 'exp', Db::raw('IS NOT NULL')],
            ['carownid', '>', 0],
            ['time', 'between', [$timeBetween[0], $timeBetween[1]]]
        ];

        $fieldBase = 'infoid as id, love_wall_ID as trip_id, carownid, passengerid, status, time';
        $InfoModel = new InfoModel();
        $baseSql = $InfoModel->field($fieldBase)->where($whereBase)->buildSql(); // 查出所有有司机的乘客行程。

        $uidFieldName = intval($userType) === 1 ? 'carownid' : 'passengerid';
        $resFields = "u.uid, u.loginname, u.nativename, u.name, u.Department as department, u.companyname, count(u.uid) as num";
        $join = [
            ['user u', "u.uid = a.$uidFieldName", 'left'],
        ];
        $ctor = Db::connect('database_carpool')->table($baseSql)->alias('a')->field($resFields)->join($join)->group('u.uid')->order('num DESC');
        if ($limit === true) {
            $returnSql = true;
        } else {
            $limit = is_numeric($limit) ? $limit : 50;
        }
        return $returnSql ? $ctor->buildSql() : $ctor->limit($limit)->select();
    }

    /**
     * 取得用户排名 (旧方法)
     *
     * @param array $timeBetween 时间范围 [startTime, endTime]
     * @param integer $userType 用户类型 0乘客 ，1司机
     * @param integer $limit 条数
     * @return array
     */
    public function getUserRankingOld($timeBetween = null, $userType = null, $limit = 50)
    {
        $where = " t.status <> 2 AND carownid IS NOT NULL AND carownid > 0 AND t.time >=  " . $timeBetween[0] . " AND t.time < " . $timeBetween[1] . "";
        switch ($userType) {
            case 0: //取得乘客排名
                $tableAll = " SELECT   passengerid ,time , MAX(infoid) as infoid , MAX(carownid) as carownid FROM info as t WHERE $where GROUP BY   time, passengerid "; //取得当月所有，去除拼同司机同时间同乘客的数据。
                $limit = " LIMIT 50 ";
                $sql = "SELECT u.uid,  u.loginname , u.nativename as name,  u.companyname , count(ta.infoid) as num  FROM ( $tableAll ) as ta 
                        LEFT JOIN user as u on ta.passengerid =  u.uid  
                        GROUP BY  passengerid   ORDER BY num DESC $limit";
                $datas  =  Db::connect('database_carpool')->query($sql);
                return $datas;
                break;
            case 1: //取得司机排名。
                $tableAll = " SELECT carownid, passengerid ,time , MAX(infoid) as infoid FROM info as t WHERE $where GROUP BY carownid , time, passengerid "; //取得当月所有，去除拼同司机同时间同乘客的数据。
                $limit = " LIMIT 50 ";
                $sql = "SELECT u.uid, u.nativename as name, u.loginname , u.companyname , count(ta.passengerid) as num 
                    FROM ( $tableAll ) as ta LEFT JOIN user as u on ta.carownid =  u.uid  
                    GROUP BY  carownid   
                    ORDER BY num DESC $limit";
                $datas  =  Db::connect('database_carpool')->query($sql);
                return $datas;
                break;
            default:
                return false;
                break;
        }
    }

    /**
     * 取得今天有司机乘客组合的行程
     *
     * @return array
     */
    public function getTodayJoint()
    {
        $InfoModel = new InfoModel();
        $timeBetween = [date('Ymd0000'), date('Ymd2359')];
        $where = [
            ['t.status', 'in', [0,1,3,4]],
            ['t.carownid', 'exp', Db::raw('IS NOT NULL')],
            ['t.carownid', '>', 0],
            ['t.time', 'between', [$timeBetween[0], $timeBetween[1]]]
        ];
        $join = [
            ['user d', 'd.uid = t.carownid', 'left'],
            ['user p', 'p.uid = t.passengerid', 'left'],
        ];
        $field = "t.infoid as id, t.love_wall_ID as trip_id, 'info' as `from`, t.time, d.carnumber as plate,
                d.uid as d_uid, d.name as d_name, d.companyname as d_companyname, d.Department as d_department,
                p.uid as p_uid, p.name as p_name, p.companyname as p_companyname, p.Department as p_department
            ";
        
        $res = $InfoModel->alias('t')->field($field)->join($join)->where($where)->order('d.Department, t.love_wall_ID, d.uid, t.time')->select();
        /**** 下边是旧方法 ****/
        // $where = " i.status <> 2 AND carownid IS NOT NULL AND carownid > 0 AND i.time >=  " . $timeBetween[0] . " AND i.time <= " . $timeBetween[1] . "";
        // $whereIds = "SELECT MIN(ii.infoid) FROM  (select * from info as i where $where ) as ii GROUP BY ii.passengerid , ii.time    ";

        // $sql = "SELECT i.infoid, i.carownid, i.passengerid, c.nativename as d_name, c.Department as d_department,c.carnumber, 
        //         p.nativename as p_name, p.Department as p_department, i.time
        //         FROM info as i
        //         LEFT JOIN user AS c ON c.uid = i.carownid
        //         LEFT JOIN user AS p ON p.uid = i.passengerid
        //         WHERE   i.infoid in($whereIds) 
        //         ORDER BY c.Department DESC,i.carownid DESC
        //     ";
        // $res  =  Db::connect('database_carpool')->query($sql);
        return $res ? $res->toArray() : [];
    }
}
