<?php

namespace app\carpool\model;

use app\common\model\BaseModel;
use app\carpool\service\Trips as TripsServ;
use my\Utils;

class Info extends BaseModel
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 'info';


    // 直接使用配置参数名
    protected $connection = 'database_carpool';

    protected $pk = 'infoid';


    protected $type = [
        'status'    =>  'integer',
    ];
    public $errorMsg = "";
    public $errorData = "";

    public function getItemCacheKey($id)
    {
        return "carpool:info:$id";
    }

    /**
     * 取得乘客列表cacheKey;
     *
     * @param integer $wall_id 行程id
     * @return string
     */
    public function getPassengersCacheKey($wall_id)
    {
        return "carpool:wall:passengers:wall_{$wall_id}";
    }

    /**
     * 删除乘客列表缓存;
     *
     * @param integer $wall_id 行程id
     * @return string
     */
    public function delPassengersCache($wall_id)
    {
        $cacheKey = $this->getPassengersCacheKey($wall_id);
        $redis = $this->redis();
        return $redis->del($cacheKey);
    }

    /**
     * 取得乘客数cacheKey;
     *
     * @param integer $wall_id 行程id
     * @return string
     */
    public function getTookCountCacheKey($wall_id)
    {
        return "carpool:wall:passengersCount:wall_{$wall_id}";
    }

    /**
     * 删除乘客数缓存
     */
    public function delTookCountCache($id)
    {
        $cacheKey = $this->getTookCountCacheKey($id);
        return $this->redis()->del($cacheKey);
    }


    /**
     * 取得接口的需车需求列表缓存Key
     *
     * @param string $company_id 公司id
     * @return string
     */
    public function getRqListCacheKey($company_id)
    {
        $TripsServ = new TripsServ();
        $company_ids = $TripsServ->getCompanyIds($company_id);
        $company_ids = is_array($company_ids) ? implode(',', $company_ids) : $company_ids;
        return "carpool:nm_trip:request_list:companyId_{$company_ids}";
    }

    /**
     * 计算乘客个数
     *
     * @param integer $id 空座位id
     * @param integer $ex 缓存的有效期，单位秒
     * @return integer
     */
    public function countPassengers($id, $ex = 5)
    {
        $cacheKey = $this->getTookCountCacheKey($id);
        $redis = $this->redis();
        $res = $redis->cache($cacheKey);
        if ($res === false || $ex === false) {
            $map = [
                ['love_wall_ID', '=', $id],
                ['status', 'in', [0,1,3,4]],
            ];
            $res = $this->where($map)->count();
            if (is_numeric($ex)) {
                $redis->cache($cacheKey, $res, $ex);
            }
        }
        return $res;
    }

    /**
     * 取得乘客行程列表
     *
     * @param [type] $wall_id
     * @return void
     */
    public function passengers($wall_id)
    {
        $cacheKey = $this->getPassengersCacheKey($wall_id);
        $redis = $this->redis();
        $res = $redis->cache($cacheKey);
        if ($res === false) {
            $map = [
                ['t.love_wall_ID', '=', $wall_id],
                ['t.status', 'in', [0,1,3,4]],
            ];
            $field = '*,
            startname as start_name, endname as end_name,
            x(start_latlng) as start_longitude , y(start_latlng) as start_latitude, 
            x(end_latlng) as end_longitude, y(end_latlng) as end_latitude';
            $field .= " ,t.infoid as id, 'info' as `from`, 0 as user_type ";
            $res = $this->alias('t')->field($field)->field('start_latlng, end_latlng', true)->where($map)->order('t.subtime ASC')->select();
            if (!$res) {
                $redis->cache($cacheKey, [], 5);
            }
            $res =  $res->toArray();
            foreach ($res as $key => $value) {
                unset($res[$key]['start_latlng']);
                unset($res[$key]['end_latlng']);
                $res[$key]['time'] = strtotime($value['time'].'00');
                $res[$key]['subtime'] = strtotime($value['subtime'].'00');
            }
            $redis->cache($cacheKey, $res, 20);
        }
        return $res;
    }

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
            ["carownid|passengerid", "=", $uid],
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
                'p_uid' => $res['passengerid'],
            ];
            $this->errorMsg = lang("You have already made one trip at {:time}, please do not post in a similar time", ["time" => $resTime]);
            return $returnData;
        } else {
            return false;
        }
    }





    //取得合并的info和wall表
    public function buildUnionSql($uid, $merge_ids = [], $statusSet = "(0,1,4)")
    {
        $whereUser = " a.carownid=$uid OR a.passengerid=$uid ";
        $whereUser2 = " a.carownid=$uid  ";
        $whereUser_lw = " lw.carownid=$uid  ";
        if (count($merge_ids) > 0) {
            foreach ($merge_ids as $key => $value) {
                $whereUser .= " OR a.carownid=$value OR a.passengerid=$value ";
                $whereUser2 .= " OR a.carownid=$value ";
                $whereUser_lw .= " OR lw.carownid=$value ";
            }
        }

        // 从info表取得数据
        $viewSql_u1 = "SELECT
            a.infoid, (case when a.love_wall_ID IS NULL  then '0' else a.love_wall_ID end) as  love_wall_ID ,'0' as trip_type,
            a.startpid,a.endpid,a.time,a.status, a.passengerid, a.carownid, a.subtime,a.map_type,
            -- a.go_time,
            a.startname, a.start_gid, a.start_latlng ,
            -- x(a.start_latlng) as start_lng , y(a.start_latlng) as start_lat ,
            a.endname, a.end_gid, a.end_latlng ,
                -- x(a.end_latlng) as end_lng , y(a.end_latlng) as end_lat ,
            '0' as seat_count
            -- '0' as liked_count,
            -- '0' as hitchhiked_count
            FROM
                info AS a
            WHERE
                ( $whereUser )
            AND a.status in $statusSet
            AND (a.love_wall_ID is null OR  a.love_wall_ID not in (select lw.love_wall_ID  from love_wall AS lw where $whereUser_lw and lw.status<>2 ) )
            ORDER BY a.time desc";

        // 从love_wall表取得数据
        $viewSql_u2 = "SELECT '0' AS infoid, a.love_wall_ID AS love_wall_ID,'1' AS trip_type,
            a.startpid,a.endpid,a.time,a.status, '0' as passengerid, a.carownid,a.subtime,a.map_type,
            -- a.go_time,
            a.startname, a.start_gid,a.start_latlng ,
                -- x(a.start_latlng) as start_lng , y(a.start_latlng) as start_lat ,
            a.endname, a.end_gid,a.end_latlng ,
                -- x(a.end_latlng) as end_lng , y(a.end_latlng) as end_lat ,
            a.seat_count
            -- (select count(*) from love_wall_like as cl where cl.love_wall_id=a.love_wall_ID) as liked_count,
            -- (select count(*)  from info as ci where ci.love_wall_ID=a.love_wall_ID and ci.status  <>2) as hitchhiked_count
            FROM
            love_wall as a
            WHERE
            a.status in $statusSet
            AND ($whereUser2)
            ORDER BY  a.time desc";

        $viewSql  =  "($viewSql_u1 ) union all ($viewSql_u2 )";
        return $viewSql;
    }

    /**
     * 通过wall_id取得列表
     *
     * @param integer $wall_id 空座位id
     * @return array
     */
    public function getListByWallId($wall_id, $fields = null)
    {
        $map = [
            ['love_wall_ID', '=', $wall_id],
            ['status', 'in', [0,1,3,4]],
        ];
        $list = $this->where($map)->select();
        if (!$list) {
            return null;
        }
        $list = $list->toArray();
        if ($fields) {
            $list = Utils::getInstance()->filterListFields($fields);
        }
        return $list;
    }

    /**
     * 取消info行程
     *
     * @param mixed $idOrData 要取消的行程id或数据
     * @param mixed $uidOrUData 操作者的id或数据
     * @param integer $must  是否强制取消：如果时间未过，是否不还原乘客到约车需求
     */
    public function cancelInfo($idOrData, $uidOrUData, $must = 0)
    {
        $infoData = is_numeric($idOrData) ? $this->getItem($idOrData, false) : $idOrData;
        $infoid = $infoData['infoid'];
        $uid = is_numeric($uidOrUData) ? $uidOrUData : $uidOrUData['uid'];
        $infoTime = strtotime($infoData['time'] . '00');
        $upData = [
            'cancel_user_id' => $uid,
            'cancel_time' => date('YmdHis', time()),
        ];
        if ($infoTime > time() && !$must && $infoData['comefrom'] == 2 && $infoData['carownid'] > 0) { // 如果未过出发时间 且为还原为约车需求
            $upData['status'] = 0;
            $upData['love_wall_ID'] = null;
            $upData['carownid'] = -1;
        } else { // 如果为直接取消
            $upData['status'] = 2;
        }
        $map = [
            ['infoid', '=', $infoid]
        ];
        return $this->where($map)->update($upData);
    }

    /**
     * 清理用户要上传Gps的info_id缓存
     *
     * @param integer $uid 用户id
     */
    public function delUpGpsInfoidCache($uid)
    {
        if (is_array($uid)) {
            foreach ($uid as $key => $value) {
                $this->delUpGpsInfoidCache($value);
            }
            return true;
        }
        $cacheKey =  "carpool:info_id:{$uid}";
        return $this->redis()->del($cacheKey);
    }
}
