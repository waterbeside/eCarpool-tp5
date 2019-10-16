<?php

namespace app\api\controller\v1\publics;

use think\Db;
use app\api\controller\ApiBase;
use app\carpool\service\Trips as TripsService;
use app\carpool\service\TripsReport as TripsReportService;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use my\RedisData;

/**
 * 报表相关
 * Class Reports
 * @package app\api\controller
 */
class Reports extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }

    /**
     *  取得拼车合计数据
     */
    public function trips_summary()
    {
        $cacheKey = "carpool:reports:trips_summary";
        $redis = new RedisData();
        $returnData = $redis->cache($cacheKey);
        if (!$returnData) {
            $sql = "select sum(sumqty) as sumtrip,sum(1) as sumdriver from carpool.reputation";
            $res  =  Db::connect('database_carpool')->query($sql);
            if (!$res) {
                return $this->jsonReturn(20002, 'No data');
            }
            $returnData = $res[0];
            $redis->cache($cacheKey, $returnData, 3600 * 12);
        }
        $this->jsonReturn(0, $returnData, 'success');
    }

    /**
     * 取得每月数据
     */
    public function month_statis()
    {

        $filter['end']      = str_replace('.', '-', input('param.end'));
        $filter['start']    = str_replace('.', '-', input('param.start'));
        $filter['end']      = $filter['end'] ? $filter['end'] : date('Y-m');
        $filter['start']    = $filter['start'] ? $filter['start'] : date('Y-m', strtotime('-11 month', time()));
        $filter['show_type']    = input('param.show_type');
        $filter['department']    = input('param.department');
        $start =  date('Ym010000', strtotime($filter['start'] . '-01'));
        $end =  date('Ym010000', strtotime("+1 month", strtotime(str_replace('.', '-', $filter['end']) . '-01')));

        // $show_type = explode(',', $filter['show_type']);
        $department = explode(',', $filter['department']);

        if (!in_array($filter['show_type'], [1, 2, 3, 4])) {
            return $this->jsonReturn(992, [], 'Error param');
        }


        $TripsReport = new TripsReportService();
        $TripsService = new TripsService();

        $monthArray = $TripsReport->getMonthArray($filter['start'], $filter['end'], 'Y-m'); //取得所有月分作为x轴;
        $monthNum = count($monthArray);

        $listData = [];


        $listData = [];

        $redis = new RedisData();
        foreach ($monthArray as $key => $value) {
            foreach ($department as $k => $did) {
                $paramData = [
                    'department' => $did,
                    'show_type' => $filter['show_type'],
                    'month' => $value,
                ];
                $listData[$value][$did] =  $TripsReport->getMonthSum($paramData);
            }
            $listData[$value]['month'] = $value;
        }
        $returnData = [
            'months' => $monthArray,
            'list' => $listData,
        ];
        return $this->jsonReturn(0, $returnData, 'successful');
    }



    /**
     * 取得月排名
     */
    public function month_ranking($type = -1, $month = '', $recache = 0)
    {

        $month_current   = date("Y-m");

        $yearMonth   = empty($month) ? date("Y-m", strtotime("$month_current -1 month")) : $month;
        $period = $this->getMonthPeriod($yearMonth . '-01', "YmdHi");

        $redis = new RedisData();

        $cacheKey   = "carpool:reports:month_ranking:{$type}_{$yearMonth}";
        $cacheExp = 60 * 60 * 24;
        $cacheDatas = $redis->cache($cacheKey);

        if ($cacheDatas) {
            return $this->jsonReturn(0, $cacheDatas, "Successful");
        }

        switch ($type) {
            case 0:  //取得司机排名。
                $where = " t.status <> 2 AND carownid IS NOT NULL AND carownid > 0 AND t.time >=  " . $period[0] . " AND t.time < " . $period[1] . "";
                $tableAll = " SELECT carownid, passengerid ,time , MAX(infoid) as infoid FROM info as t WHERE $where GROUP BY carownid , time, passengerid "; //取得当月所有，去除拼同司机同时间同乘客的数据。
                $limit = " LIMIT 50 ";
                $sql = "SELECT u.uid, u.nativename as name, u.loginname , u.companyname , count(ta.passengerid) as num FROM ( $tableAll ) as ta LEFT JOIN user as u on ta.carownid =  u.uid  GROUP BY  carownid   ORDER BY num DESC $limit";

                $datas  =  Db::connect('database_carpool')->query($sql);
                $returnData = array(
                    "lists" => $datas,
                    "month" => $yearMonth
                );
                $redis->cache($cacheKey, $returnData, $cacheExp);
                return $this->jsonReturn(0, $returnData, "Successful");

                break;
            case 1: //取得乘客排名
                $where = " t.status <> 2 AND carownid IS NOT NULL AND carownid > 0 AND t.time >=  " . $period[0] . " AND t.time < " . $period[1] . "";
                $tableAll = " SELECT   passengerid ,time , MAX(infoid) as infoid , MAX(carownid) as carownid FROM info as t WHERE $where GROUP BY   time, passengerid "; //取得当月所有，去除拼同司机同时间同乘客的数据。
                $limit = " LIMIT 50 ";
                $sql = "SELECT u.uid,  u.loginname , u.nativename as name,  u.companyname , count(ta.infoid) as num  FROM ( $tableAll ) as ta 
                        LEFT JOIN user as u on ta.passengerid =  u.uid  
                        GROUP BY  passengerid   ORDER BY num DESC $limit";
                $datas  =  Db::connect('database_carpool')->query($sql);
                $returnData = array(
                    "lists" => $datas,
                    "month" => $yearMonth
                );
                $redis->cache($cacheKey, $returnData, $cacheExp);
                return $this->jsonReturn(0, $returnData, "Successful");
                break;

            default:
                return $this->jsonReturn(992, null, "Error params");
                break;
        }
    }



    /**
     * 取得今日拼车清单。
     */
    public function today_info()
    {
        $today    = date("Y-m-d");
        $tomorrow = date("Y-m-d", strtotime("$today +1 day"));
        $period = array(date("Ymd0000", strtotime($today)), date("Ymd0000", strtotime($tomorrow)));



        $redis = new RedisData();

        $cacheKey   = "carpool:reports:today_info";
        $cacheExp = 60 ;
        $cacheDatas = $redis->cache($cacheKey);

        if ($cacheDatas) {
            return $this->jsonReturn(0, $cacheDatas, "Successful");
        }


        $where = " i.status <> 2 AND carownid IS NOT NULL AND carownid > 0 AND i.time >=  " . $period[0] . " AND i.time < " . $period[1] . "";
        $whereIds = "SELECT MIN(ii.infoid) FROM  (select * from info as i where $where ) as ii GROUP BY ii.passengerid , ii.time    ";

        $sql = "SELECT i.infoid, i.carownid, i.passengerid, c.nativename as d_name, c.Department as d_department,c.carnumber, 
                p.nativename as p_name, p.Department as p_department, i.time
                FROM info as i
                LEFT JOIN user AS c ON c.uid = i.carownid
                LEFT JOIN user AS p ON p.uid = i.passengerid
                WHERE   i.infoid in($whereIds) 
                ORDER BY c.Department DESC,i.carownid DESC
            ";
        $datas  =  Db::connect('database_carpool')->query($sql);
        if ($datas !== false) {
            foreach ($datas as $key => $value) {
                $datas[$key]['time'] = strtotime($value['time']);
                $datas[$key]['date_time'] = date("Y-m-d H:i", strtotime($value['time']));
            }
            $returnData = array(
                "lists" => $datas,
            );
            $redis->cache($cacheKey, $returnData, $cacheExp);
            return $this->jsonReturn(0, $returnData, "success");
        } else {
            return $this->jsonReturn(-1, [], "fail");
        }
    }

    /*计算期间*/
    public function getMonthPeriod($date, $format = 'Y-m-d')
    {
        $firstday = date("Y-m-01", strtotime($date));
        $lastday = date("Y-m-d", strtotime("$firstday +1 month"));
        return array(date($format, strtotime($firstday)), date($format, strtotime($lastday)));
    }
}
