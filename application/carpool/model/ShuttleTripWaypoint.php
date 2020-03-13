<?php

namespace app\carpool\model;

use think\Model;
use app\common\model\BaseModel;
use my\Utils;
use think\Db;

class ShuttleTripWaypoint extends BaseModel
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 't_shuttle_trip_waypoint';

    // 直接使用配置参数名
    protected $connection = 'database_carpool';

    protected $pk = 'id';

    public $errorMsg = "";
    public $itemCacheExpire = 10 * 60;
    public $itemFieldsMap = [
        'ST_ASTEXT(gis)' => 'gis',
        'X(gis)' => 'lng',
        'Y(gis)' => 'lat',
    ];

    /**
     * 取得单项数据缓存Key设置
     *
     * @param integer $id 主键
     * @return string
     */
    public function getItemCacheKey($id)
    {
        return "carpool:shuttle:tripWayPoint:$id";
    }

    /**
     * 取得行程的程经点缓存key
     *
     * @param integer $tripId 行程id
     * @return string
     */
    public function getTripWaypointsCacheKey($tripId)
    {
        return "carpool:shuttle:trip:$tripId:waypoints";
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getNearCacheKey($lnglatOrGeohash, $radius)
    {
        if (is_array($lnglatOrGeohash)) {
            $redis = $this->redis();
            $geohash =$redis->getGeohash($lnglatOrGeohash);
        } else {
            $geohash = $lnglatOrGeohash;
        }
        $subHash_10 = substr($geohash, 0, 8); // 8位，精度在19米
        return "carpool:shuttle:wapoint:near:$subHash_10:$radius";
    }

    /**
     * 添加多个途字经点
     *
     * @param array $pointList 途经点列表
     * @param array $tripId 行程数据
     * @return boolean
     */
    public function insertPoints($pointList, $tripData)
    {
        if (empty($pointList)) {
            return 0;
        }
        if (empty($tripData) || !$tripData['id']) {
            return 0;
        }
        $upData = [];
        foreach ($pointList as $key => $value) {
            $itemUpData = [
                'trip_id' => $tripData['id'],
                'addressid' =>  $value['address_id'] ?? $value['addressid'],
                'gis' => $this->geomfromtextPoint($value['longitude'], $value['latitude'], true),
                'name' => $value['name'] ?? $value['addressname'],
                'uid' => $tripData['uid'],
                'time' => $tripData['time'],
                'type' => 0,
                'map_type' => $value['map_type'] ?? ($tripData['map_type'] ?? 0),
            ];
            $upData[] = $itemUpData;
        }
        $this->where('trip_id', $tripData['id'])->delete();
        $res = $this->insertAll($upData);
        return $res;
    }

    /**
     * 取过附近的途经点
     *
     * @param array $lnglat [lng,lat]
     * @param integer $radius 半径范围
     * @param array $extraMap 额外的筛选条件
     * @param integer $limit 取数据条数，默认null即全取
     * @param string $only 只返某个字段？默认null全返，传入字段名，即返该字段名值的数组
     * @return array
     */
    public function getNear($lnglat, $radius, $extraMap = null, $limit = null, $only = null)
    {
        
        $redis = $this->redis();
        $geohash = $lnglat[2] ?? $redis->getGeohash($lnglat);
        $geohashLen = Utils::getInstance()->getGeohashLengthByRadius($radius);
        $subHash = substr($geohash, 0, $geohashLen);
        // cache from redis
        $cacheKey = $this->getNearCacheKey($geohash, $radius);
        $extraJson = md5(json_encode(($extraMap ?: [])));
        $rowKey = "limit_$limit,extra_{$extraJson}";
        $res = $redis->hCache($cacheKey, $rowKey);
        // query from db
        if ($res === false) {
            // field
            $distanceSql = "ST_Distance_Sphere(POINT({$lnglat[0]}, {$lnglat[1]}), gis, 6371000)";
            $fields = 'trip_id, addressid, X(gis) as longitude, Y(gis) as latitude, geohash, name, uid, time, map_type';
            $fields .= ",$distanceSql as distance";
            // where
            $where = [
                ['', 'EXP', Db::raw("$distanceSql < $radius")]
            ];
            if ($geohash) {
                $where[] =  ['', 'EXP', Db::raw("geohash like '{$subHash}%' ")];
            }
            if (!empty($extraMap)) {
                $where = array_merge($where, $extraMap);
            }
            $orderby = 'distance ASC';
            $ctor = $this->field($fields)->where($where)->order($orderby);
            $ctor = $limit > 0 ? $ctor->limit($limit) : $ctor;
            $res = $ctor->select();
            $res = $res ? $res->toArray() : $res;
            if (empty($res)) {
                $redis->hCache($cacheKey, $rowKey, [], 3);
            }
            $redis->hCache($cacheKey, $rowKey, $res, 8, 30);
        }
        if (!empty($only) && !empty($res)) {
            $columnRes = [];
            foreach ($res as $key => $value) {
                if (isset($value[$only])) {
                    $columnRes[] = $value[$only];
                }
            }
            return $columnRes;
        }
        return $res;
    }

    /**
     * 检查站点是否按途经点顺序
     *
     * @param array $checkPointsArray 要检查的站点
     * @param array $points 行程的途经点列表
     * @param array $checkPointsSort 要检查的站点的重排序数组。
     * @param string $checkPointsSortBy 要检查的站点的重排序方式 asc or desc
     * @return boolean
     */
    public function checkPointSortByName($checkPointsArray, $points, $checkPointsSort = [], $checkPointsSortBy = 'asc')
    {
        if (!empty($checkPointsSort) && is_array(($checkPointsSort))) {
            $checkPointsSortBy = $checkPointsSortBy ? strtolower($checkPointsSortBy) : 'asc';
            if ($checkPointsSortBy === 'desc') {
                arsort($checkPointsSort);
            } else {
                asort($checkPointsSort);
            }
            $checkPoints_afterSort = [];
            foreach ($checkPointsSort as $key => $val) {
                $checkPoints_afterSort[] = &$checkPointsArray[$key];
            }
            $checkPointsArray = $checkPoints_afterSort;
        }
        //   b  d e
        // a b c d e f
        $matchPointsArray = []; // 把要检查站点，从所有途经点中查出来
        foreach ($points as $key => $value) {
            if (in_array($value['name'], $checkPointsArray)) {
                $matchPointsArray[] = $value['name'];
            }
        }
        $matchStr = implode(',', $matchPointsArray);
        $checkStr = implode(',', $checkPointsArray);
        if (strpos($matchStr, $checkStr) === false) {
            return false;
        }
        return true;
    }
}
