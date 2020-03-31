<?php

namespace app\carpool\service;

use app\common\service\Service;
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
     * 是否进行新表统计
     *
     * @return boolean
     */
    public function isGetShuttleStatis()
    {
        $isGetSh = true;
        $shuttleTripLaunchDate = config('trips.shuttle_trip_launch_date') ?? null;
        if (!empty($shuttleTripLaunchDate) && time() < strtotime($shuttleTripLaunchDate)) {
            $isGetSh = false;
        }
        return $isGetSh;
    }
}
