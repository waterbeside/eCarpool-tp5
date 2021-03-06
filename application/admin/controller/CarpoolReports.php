<?php

namespace app\admin\controller;

use app\carpool\model\User as CarpoolUserModel;
use app\carpool\model\Company as CompanyModel;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\admin\controller\AdminBase;
use think\Db;

// 1、上周总拼车人数（用户数量）
// 2、上周总拼车次数
// 3、上周减少碳排放
// 4、高明、新疆、泰州等等各地拼车次数
// 5、上周司机、乘客排行榜

/**
 * 拼车情况查询
 * Class Link
 * @package app\admin\controller
 */
class CarpoolReports extends AdminBase
{
    /**
     * [index description]
     *
     */
    public function index($filter = [])
    {
        $map = [];

        $map[] = ['status', '<>', 2];

        // echo date("Y-m-d",strtotime('-1 week last monday'))." 00:00:00";
        // echo date("Y-m-d",strtotime('last sunday'))." 23:59:59";



        $timeStr = isset($filter['time']) ? $filter['time'] : 0;
        $period = $this->get_period(isset($filter['time']) ? $filter['time'] : 0);
        $filter['time'] = date('Y-m-d', strtotime($period[0])) . " ~ " . date('Y-m-d', strtotime($period[1]) - 24 * 60 * 60);
        // $datas = array(
        //   "driver_count"=> $this->public_driver_count($timeStr),
        //   "passenger_count"=> $this->public_passenger_count($timeStr),
        //   "user_count" => $this->public_user_count($timeStr),
        // );
        //
        // $datas['carbon'] = $datas['passenger_count']*7.6*2.3/10;
        // dump($datas);

        // $cacheExpiration = strtotime($value) >= strtotime(date('Y-m',strtotime("now"))) ? 900 : 3600*24*60 ;
        // Yii::app()->cache->set($cacheDatasKey, $listItem ,$cacheExpiration);

        return $this->fetch('index', ['filter' => $filter]);
    }

    /**
     * 计算司机数
     */
    public function public_driver_count($timeStr = 0)
    {
        $period = $this->get_period($timeStr);

        $where_base =  " i.status IN(1,3)  AND carownid IS NOT NULL AND carownid > 0 AND time >=  " . $period[0] . " AND time < " . $period[1] . " ";


        //从info表取得非空座位的乘搭的司机数
        $from['count_c'] = "SELECT carownid FROM info as i  where  $where_base AND love_wall_ID is Null   GROUP BY carownid  , time";
        $sql['count_c']  = "SELECT  count(*) as c  FROM  (" . $from['count_c'] . " ) as p_info ";
        $datas['count_c'] = Db::connect('database_carpool')->query($sql['count_c']);


        //从love_wall表取得非空座位的乘搭的司机数
        $from['count_c1'] = "SELECT love_wall_ID , 
            (select count(infoid) from info as i where i.love_wall_ID = t.love_wall_ID AND i.status <> 2 ) as pa_num 
            FROM love_wall as t  where  t.status <> 2  AND t.time >=  " . $period[0] . " AND t.time < " . $period[1] . "   ";
        $sql['count_c1']  = "SELECT  count(*) as c   FROM (" . $from['count_c1'] . " ) as ta   WHERE pa_num > 0   ";
        $datas['count_c1'] = Db::connect('database_carpool')->query($sql['count_c1']);
        return $this->jsonReturn(0, ['total' => $datas['count_c'][0]['c'] + $datas['count_c1'][0]['c']]);
    }

    /**
     * 计算乘客数
     */
    public function public_passenger_count($timeStr)
    {
        $period = $this->get_period($timeStr);
        $where_base =  " i.status IN(1,3)  AND carownid IS NOT NULL AND carownid > 0 AND time >=  " . $period[0] . " AND time < " . $period[1] . " ";
        //取得该月乘客人次
        // $from['count_p'] = "SELECT love_wall_ID FROM info as i  where  $where_base  GROUP BY carownid, passengerid, love_wall_ID, time";
        // $from = "SELECT * FROM info as i  where  i.status <> 2  AND time >=  ".$period[0]." AND time < ".$period[1]." ";
        $from['count_p'] = "SELECT distinct startpid,endpid,time,carownid,passengerid FROM info as i  where  $where_base ";
        $sql['count_p']  = "SELECT  count(*) as c FROM (" . $from['count_p'] . " ) as p_info ";
        $datas['count_p'] =  Db::connect('database_carpool')->query($sql['count_p']);
        return $this->jsonReturn(0, ['total' => $datas['count_p'][0]['c']]);
    }




    /**
     * 计算用户数
     */
    public function public_user_count($timeStr)
    {
        $period = $this->get_period($timeStr);
        $where_base =  " i.status IN(1,3)  AND carownid IS NOT NULL AND carownid > 0 AND time >=  " . $period[0] . " AND time < " . $period[1] . " ";

        $from_01 = "SELECT distinct startpid,endpid,time,carownid,passengerid FROM info as i  where  $where_base ";
        $from_passenger  = "SELECT  passengerid
            FROM
            (" . $from_01 . " ) as i
            GROUP BY
            passengerid
        ";
        $from_driver  = "SELECT  carownid
            FROM
            (" . $from_01 . " ) as i
            GROUP BY
                carownid
        ";
        $sql_driver = "SELECT count(carownid) as c FROM (" . $from_driver . " ) as ci ";
        $sql_passenger = "SELECT count(passengerid) as c FROM (" . $from_passenger . " ) as ci where passengerid not in (" . $from_driver . ")";
        $res_driver =  Db::connect('database_carpool')->query($sql_driver);
        $res_passenger =  Db::connect('database_carpool')->query($sql_passenger);
        return $this->jsonReturn(0, ['total' => $res_passenger[0]['c'] + $res_driver[0]['c']]);
    }


    /**
     * 各分厂拼车情况
     */
    public function public_subcompany_count($timeStr)
    {
        $period = $this->get_period($timeStr);
        $where_base =  " i.status IN(1,3)  AND carownid IS NOT NULL AND carownid >0 AND time >=  " . $period[0] . " AND time < " . $period[1] . " ";
    }


    /**
     * 乘客|司机排行
     *
     * @param integer $timeStr
     * @param string $type
     * @return void
     */
    public function public_ranking($timeStr = 0, $type = '')
    {
        $period = $this->get_period($timeStr);
        $where_base =  " i.status IN(1,3)  AND carownid IS NOT NULL AND carownid >0 AND time >=  " . $period[0] . " AND time < " . $period[1] . " ";
        $from_01 = "SELECT distinct startpid,endpid,time,carownid,passengerid FROM info as i  where  $where_base ";
        if (!in_array($type, ['driver', 'passenger'])) {
            return $this->jsonReturn(-1, 'type error');
        }
        if ($type == "driver") {
            $fieldname = 'carownid';
        } elseif ($type == "passenger") {
            $fieldname = 'passengerid';
        }
        $sql_user  = "SELECT {$fieldname} , count({$fieldname}) as c , 
            u.name, u.loginname, u.nativename, u.Department , u.sex, u.phone, c.company_name
            FROM
            (" . $from_01 . " ) as i
            LEFT JOIN user as u ON u.uid = {$fieldname}
            LEFT JOIN company as c ON u.company_id = c.company_id
            GROUP BY
            {$fieldname}
            ORDER BY
            c DESC
        ";
        $res_user =  Db::connect('database_carpool')->query($sql_user);
        foreach ($res_user as $key => $value) {
            $res_user[$key]['nativename'] = $value['nativename'] && trim($value['nativename']) != '' ? $value['nativename'] : $value['name'];
        }
        $this->jsonReturn(0, ['lists' => $res_user]);
    }


    /**
     * 区域占比
     */
    public function area($filter = [])
    {
        $timeStr = isset($filter['time']) ? $filter['time'] : 0;
        // dump($timeStr);exit;
        $period = $this->get_period(isset($filter['time']) ? $filter['time'] : 0);
        $filter['time'] = date('Y-m-d', strtotime($period[0])) . " ~ " . date('Y-m-d', strtotime($period[1]) - 24 * 60 * 60);
        return $this->fetch('area', ['filter' => $filter]);
    }

    /**
     * 区域占比
     */
    public function public_areas($timeStr = 0, $type = '')
    {
        if ($type == "start") {
            $fieldname = 'startpid';
        } elseif ($type == "end") {
            $fieldname = 'endpid';
        } else {
            return $this->jsonReturn(-1, 'type error');
        }
        $period = $this->get_period($timeStr);
        $where_base =  " i.status IN(1,3)  AND carownid IS NOT NULL AND carownid >0 AND time >=  " . $period[0] . " AND time < " . $period[1] . " ";
        $from_01 = "SELECT distinct startpid,endpid,time,carownid,passengerid FROM info as i where  $where_base   ";

        $sql  = "SELECT  {$fieldname} , count({$fieldname}) as c , a.addressname, a.latitude, a.longtitude, a.city
            FROM
            (" . $from_01 . " ) as i
            LEFT JOIN address as a ON a.addressid = {$fieldname}
            GROUP BY
                {$fieldname}
            ORDER BY
                c DESC
        ";

        $res =  Db::connect('database_carpool')->query($sql);
        foreach ($res as $key => $value) {
            $res[$key]['latitude'] = floatval($value['latitude']);
            $res[$key]['longtitude'] = floatval($value['longtitude']);
        }
        $this->jsonReturn(0, ['lists' => $res]);
    }





    /**
     * 取得周期数
     * @param  String $timeStr 时间范围
     * @param  String $type    [description]
     */
    public function public_cycle_datas($timeStr = 0, $period = false, $type = false)
    {
        // $period = $this->get_period($timeStr);
        if (!isset($period) || !in_array($period, ['year', 'month', 'week', 'day'])) {
            $this->jsonReturn(-1, 'error param');
        }
        if (!$type) {
            $this->jsonReturn(-1, 'error param');
        }
        $now = date('YmdHi', time());
        switch ($period) {
            case 'year':
                $time = "DATE_FORMAT(concat(`time`,'00'),'%Y')";
                $whereTime = " AND time < " . $now;
                break;
            case 'month':
                $time = "DATE_FORMAT(concat(`time`,'00'),'%Y-%m')";
                $whereTime = " AND time < " . $now;
                break;
            case 'week':
                $time = "DATE_FORMAT(concat(`time`,'00'),'%Y#%U')";
                $whereTime = " AND time >= " . date("YmdHi", time() - 24 * 60 * 60 * 365 * 2) . " AND time < " . $now;
                break;
            case 'day':
                $time = "DATE_FORMAT(concat(`time`,'00'),'%Y-%m-%d')";
                $whereTime = " AND time >= " . date("YmdHi", time() - 24 * 60 * 60 * 365 * 1) . " AND time < " . $now;
                break;
            default:
                $time  = "YEAR(concat(`time`,'00'))";
                $whereTime = " AND time < " . $now;
                break;
        }
        $cache_key = "carpool_reports_cycle_" . $period . "_" . str_replace("&", "and", $type);
        if (cache($cache_key)) {
            $lists = cache($cache_key);
            $this->jsonReturn(0, ['lists' => $lists]);
        }

        $where_base =  " i.status IN(1,3,4)  AND carownid IS NOT NULL AND carownid >0  $whereTime ";
        $from = "SELECT distinct startpid,endpid,time,carownid,passengerid FROM info as i  where  $where_base ";

        if ($type == 'trips') {
            $from_2 = "SELECT
                count(*) as c , $time as t
                FROM ($from) as f
                GROUP BY t
                ORDER BY t DESC
                -- LIMIT 200;
            ";
            $sql = "SELECT *
                FROM ({$from_2}) as f2
                HAVING t IS NOT NULL
                ORDER BY t ASC
            ";
            $lists =  Db::connect('database_carpool')->query($sql);
        } elseif ($type == 'users') {
            //查司机数
            $sql_driver = "SELECT
                $time as t, carownid
                FROM ($from) as f
                GROUP BY t , carownid
                HAVING t IS NOT NULL
                ORDER BY t ASC
            ";
            //查乘客数
            $sql_passenger = "SELECT
                $time as t, passengerid
                FROM ($from) as f
                GROUP BY t , passengerid
                HAVING t IS NOT NULL
                -- AND passengerid NOT IN(SELECT carownid from info as i where $time = t AND $where_base)
                ORDER BY t ASC
            ";

            $list_driver =  Db::connect('database_carpool')->cache(true, 60 * 60)->query($sql_driver);
            $list_passenger =  Db::connect('database_carpool')->cache(true, 60 * 60)->query($sql_passenger);

            $array_date_userid = [];

            foreach ($list_driver as $key => $value) {
                if (!isset($array_date_userid[$value['t']]) || !is_array($array_date_userid[$value['t']])) {
                    $array_date_userid[$value['t']] = [];
                }
                if (!in_array($value['carownid'], $array_date_userid[$value['t']])) {
                    array_push($array_date_userid[$value['t']], $value['carownid']);
                }
            }

            foreach ($list_passenger as $key => $value) {
                if (!isset($array_date_userid[$value['t']]) || !is_array($array_date_userid[$value['t']])) {
                    $array_date_userid[$value['t']] = [];
                }
                if (!in_array($value['passengerid'], $array_date_userid[$value['t']])) {
                    array_push($array_date_userid[$value['t']], $value['passengerid']);
                }
            }
            $lists = [];
            foreach ($array_date_userid as $key => $value) {
                $arr = ["c" => count($value), "t" => $key];
                $lists[] = $arr;
            }
        } elseif ($type == "d&p") {
            //查司机数
            $sql_driver = "SELECT $time as t, carownid
                FROM ($from) as f
                GROUP BY t , carownid
                HAVING t IS NOT NULL
                ORDER BY t ASC
            ";
            //查乘客数
            $sql_passenger = "SELECT $time as t, passengerid
                FROM ($from) as f
                GROUP BY t , passengerid
                HAVING t IS NOT NULL
                ORDER BY t ASC
            ";
            $sql_count_driver = "SELECT t , count(carownid) as c
                FROM ($sql_driver) as f2
                GROUP BY t
            ";
            $sql_count_passenger = "SELECT t , count(passengerid) as c
                FROM ($sql_passenger) as f2
                GROUP BY t
            ";
            $list_driver =  Db::connect('database_carpool')->query($sql_count_driver);
            $list_passenger =  Db::connect('database_carpool')->query($sql_count_passenger);
            $lists = [];
            $drivers_kv = [];
            foreach ($list_driver as $key => $value) {
                $drivers_kv[$value['t']] = $value['c'];
            }
            foreach ($list_passenger as $key => $value) {
                $driver_count = isset($drivers_kv[$value['t']]) ? $drivers_kv[$value['t']] : 0;
                $arr = ['t' => $value['t'], 'p' => $value['c'], 'd' => $driver_count];
                $lists[] = $arr;
            }
        } else {
            $lists = [];
        }
        if (count($lists) > 0) {
            cache($cache_key, $lists, 60 * 60 * 12);
        }
        $this->jsonReturn(0, ['lists' => $lists]);
    }




    protected function get_period($timeStr)
    {
        $returnData = [];
        //筛选时间
        if (!$timeStr || !is_array(explode(' ~ ', $timeStr))) {
            $time_s = date("Y-m-d", strtotime('-1 week last sunday'));
            $time_e = date("Y-m-d", strtotime("$time_s +1 week"));
            $time_e_o = date("Y-m-d", strtotime($time_e) - 24 * 60 * 60);
            $timeStr = $time_s . " ~ " . $time_e_o;
        }

        $time_arr = explode(' ~ ', $timeStr);
        $time_s = date('YmdHi', strtotime($time_arr[0]));
        $time_e = date('YmdHi', strtotime($time_arr[1]) + 24 * 60 * 60);
        $returnData = [$time_s, $time_e];
        return $returnData;
    }
}
