<?php

namespace app\carpool\model;

use app\common\model\BaseModel;

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

    /**
     * 取得乘客列表cacheKey;
     *
     * @param integer $wall_id 行程id
     * @return string
     */
    public function getPassengersCacheKey($wall_id)
    {
        return "carpool:nm_trip:passengers:wall_{$wall_id}";
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
     * 计算乘客个数
     *
     * @param integer $id 空座位id
     * @return integer
     */
    public function countPassengers($id)
    {
        $map = [
            ['love_wall_ID', '=', $id],
            ['status', 'in', [0,1,3,4]],
        ];
        return $this->where($map)->count();
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

}
