<?php

namespace app\api\controller\v1\publics;

use think\Db;
use app\api\controller\ApiBase;
use app\carpool\service\TripsReport as TripsReportService;
use app\carpool\service\shuttle\TripReport as ShTRS;
use app\carpool\service\nmtrip\TripReport as NmTRS;
use app\carpool\model\ShuttleTrip as ShuttleTripModel;
use app\carpool\model\Info as InfoModel;
use my\Utils;
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
        $redis = RedisData::getInstance();
        $returnData = $redis->cache($cacheKey);
        if (!$returnData) {
            // $sql = "select sum(sumqty) as sumtrip,sum(1) as sumdriver from carpool.reputation";
            // $res  =  Db::connect('database_carpool')->query($sql);
            // if (!$res) {
            //     return $this->jsonReturn(20002, 'No data');
            // }
            // 282969
            // $sumTrip_nm = intval($res[0]['sumtrip']);

            $InfoModel = new InfoModel();
            $sumTrip_nm = $InfoModel->countJoint();
            // $sumTrip_nm = 282901;
            
            $TripsReport = new TripsReportService();
            $isGetSh = $TripsReport->isGetShuttleStatis();
            
            $ShuttleTripModel = new ShuttleTripModel();
            $sumTrip_sh = $isGetSh ? $ShuttleTripModel->countJoint() : 0;
            $returnData = [
                'sumtrip' => $sumTrip_nm + $sumTrip_sh,
            ];
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

        // $show_type = explode(',', $filter['show_type']);
        $department = explode(',', $filter['department']);

        if (!in_array($filter['show_type'], [1, 2, 3, 4])) {
            return $this->jsonReturn(992, [], 'Error param');
        }


        $TripsReport = new TripsReportService();
        $ShTRS = new ShTRS(); // shuttle\TripReport
        $NmTRS = new NmTRS(); // nmtrip\TripReport
        
        $monthArray = $TripsReport->getMonthArray($filter['start'], $filter['end'], 'Y-m'); //取得所有月分作为x轴;
        $listData = [];

        foreach ($monthArray as $key => $value) {
            foreach ($department as $k => $did) {
                $paramData = [
                    'department_id' => $did,
                    'show_type' => $filter['show_type'],
                    'month' => $value,
                ];
                $isGetSh = $TripsReport->isGetShuttleStatis();

                // $nmRes = $TripsReport->getMonthSum($paramData) ?: 0;
                $nmRes = $NmTRS->getMonthSum($paramData) ?: 0;
                $shRes = $isGetSh ? ($ShTRS->getMonthSum($paramData) ?: 0) : 0;
                $listData[$value][$did] = $nmRes + $shRes;
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
    public function month_ranking($user_type = -1, $month = '', $recache = 0)
    {

        $TripsReport = new TripsReportService();

        $month_current   = date("Y-m");

        $yearMonth   = empty($month) ? date("Y-m", strtotime("$month_current -1 month")) : $month;
        $nmPeriod = $TripsReport->getMonthPeriod($yearMonth . '-01', "YmdHi", true);
        $shPeriod = $TripsReport->getMonthPeriod($yearMonth . '-01', "Y-m-d H:i:s", true);

        $redis = RedisData::getInstance();

        $cacheKey   = "carpool:reports:monthRanking:ut_{$user_type},{$yearMonth}";
        $cacheExp = 60 * 60 * 24;
        $cacheDatas = $redis->cache($cacheKey);
        if ($cacheDatas) {
            return $this->jsonReturn(0, $cacheDatas, "Successful");
        }
        $TripsReport = new TripsReportService();
        $isGetShM = $TripsReport->isGetShuttleStatis('m');
        $ShTRS = new ShTRS(); // shuttle\TripReport
        $NmTRS = new NmTRS(); // nmtrip\TripReport
        $shSqlRes = $ShTRS->getUserRanking($shPeriod, $user_type, true);
        $nmSqlRes = $NmTRS->getUserRanking($nmPeriod, $user_type, true);
        // $nmResOld = $NmTRS->getUserRankingOld($nmPeriod, 1);
        if ($isGetShM > 1) {
            $usql = $shSqlRes;
        } elseif ($isGetShM == 1) {
            $uField = 'uid, loginname, nativename, name, department, companyname';
            $usql = "(SELECT  $uField , sum(num) as num from ($shSqlRes UNION All $nmSqlRes ) un GROUP BY $uField)";
        } else {
            $usql = $nmSqlRes;
        }
        $res = Db::connect('database_carpool')->table($usql)->alias('t')->order('num DESC')->limit(50)->select();

        $returnData = array(
            "lists" => $res,
            "month" => $yearMonth
        );
        $redis->cache($cacheKey, $returnData, $cacheExp);
        $this->jsonReturn(0, $returnData, 'Successful');
    }



    /**
     * 取得今日拼车清单。
     */
    public function today_info()
    {
        $today    = date("Y-m-d");
        $tomorrow = date("Y-m-d", strtotime("$today +1 day"));
        $period = array(date("Ymd0000", strtotime($today)), date("Ymd0000", strtotime($tomorrow)));

        $redis = RedisData::getInstance();

        $cacheKey   = "carpool:reports:today_info";
        $cacheExp = 60 ;
        $cacheDatas = $redis->cache($cacheKey);

        // if ($cacheDatas) {
        //     return $this->jsonReturn(0, $cacheDatas, "Successful");
        // }

        $TripsReport = new TripsReportService();
        $isGetShM = $TripsReport->isGetShuttleStatis();
        
        $ShTRS = new ShTRS(); // shuttle\TripReport
        $NmTRS = new NmTRS(); // nmtrip\TripReport
        $shRes = $isGetShM ? $ShTRS->getTodayJoint() : [];
        $NmTRS = $isGetShM ? [] : $NmTRS->getTodayJoint();
        
        $list = array_merge($shRes, $NmTRS);
        if ($list !== false) {
            foreach ($list as $key => $value) {
                $list[$key]['time'] = strtotime($value['time']);
            }
            $Utils = new Utils();
            $list = $Utils->listSort($list, ['d_department'=>'ASC','d_uid'=>'ASC','trip_id'=>'ASC','time'=>'ASC']);
            $returnData = array(
                "lists" => $list,
            );
            $redis->cache($cacheKey, $returnData, $cacheExp);
            return $this->jsonReturn(0, $returnData, "success");
        } else {
            return $this->jsonReturn(-1, [], "fail");
        }
    }

    /*计算期间*/
    public function user_carpool_statis()
    {
        $userData = $this->getUserData(1);
        $uid =  $userData['uid'];
        $cacheKey = "carpool:reports:user_carpool_statis:$uid";
        $redis = RedisData::getInstance();
        $returnData = $redis->cache($cacheKey);
        if (!$returnData) {
            $result = Db::connect('database_carpool')->query("call load_userinfo_by_uid(:uid)", [
                'uid' => $uid,
            ]);
            if ($result) {
                $returnData = $result[0][0];
                $redis->cache($cacheKey, $returnData, 60 * 5);
            }
        }
        return $this->jsonReturn(0, $returnData, "success");
    }
}
