<?php

namespace app\carpool\model;

use app\common\model\BaseModel;
use app\carpool\service\Trips as TripsServ;

class Wall extends BaseModel
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 'love_wall';


    // 直接使用配置参数名
    protected $connection = 'database_carpool';

    protected $pk = 'love_wall_ID';

    protected $type = [
        'status'    =>  'integer',
    ];

    public $errorMsg = "";

    /**
     * 发布行程时检查行程是否有重复
     * @param  Timestamp   $time       出发时间的时间戳
     * @param  Integer     $uid        发布者ID
     * @param  String      $offsetTime 时间偏差范围
     */
    public function checkRepetition($time, $uid, $offsetTime = "150")
    {
        $startTime = $time - $offsetTime;
        $endTime =   $time + $offsetTime;
        $map = [
            ["status", "<", 2],
            ["carownid", "=", $uid],
            ["time", ">=", date('YmdHi', $startTime)],
            ["time", "<=", date('YmdHi', $endTime)],
            // ["go_time",">=",$startTime],
            // ["go_time","<=",$endTime],
        ];
        $res = $this->where($map)->find();
        if ($res) {
            $resTime  = date('Y-m-d H:i', strtotime($res['time'] . '00'));
            $returnData = [
                'time' => strtotime($res['time'] . '00'),
                'love_wall_ID' => $res['love_wall_ID'],
                'd_uid' => $res['carownid'],
                'p_uid' => 0,
            ];
            $this->errorMsg = lang("You have already made one trip at {:time}, please do not post in a similar time", ["time" => $resTime]);
            return $returnData;
        } else {
            return false;
        }
    }

    /**
     * 取得接口的空座位列表缓存Key
     *
     * @param string $company_id 公司id
     * @return string
     */
    public function getListCacheKey($company_id)
    {
        $TripsServ = new TripsServ();
        $company_ids = $TripsServ->getCompanyIds($company_id);
        $company_ids = is_array($company_ids) ? implode(',', $company_ids) : $company_ids;
        return "carpool:nm_trip:wall_list:companyId_{$company_ids}";
    }

    /**
     * 清除空座位列表接口缓存
     *
     * @param string $company_id 公司id
     */
    public function delListCache($company_id)
    {
        $redis = $this->redis();
        $cacheKey = $this->getListCacheKey($company_id);
        return $redis->del($cacheKey);
    }

    /**
     * 取得接口的地图版空座位列表缓存Key
     *
     * @param string $company_id 公司id
     * @return string
     */
    public function getMapCarsCacheKey($company_id)
    {
        $TripsServ = new TripsServ();
        $company_ids = $TripsServ->getCompanyIds($company_id);
        $company_ids = is_array($company_ids) ? implode(',', $company_ids) : $company_ids;
        return "carpool:nm_trip:mapCars_list:companyId_{$company_ids}";
    }

    /**
     * 清除接口的地图版空座位列表缓存
     *
     * @param string $company_id 公司id
     */
    public function delMapCarsCache($company_id)
    {
        $redis = $this->redis();
        $cacheKey = $this->getMapCarsCacheKey($company_id);
        return $redis->del($cacheKey);
    }
}
