<?php

namespace app\carpool\model;

use think\Model;
use app\common\model\BaseModel;
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
                'address_id' =>  $value['address_id'] ?? $value['addressid'],
                'gis' => $this->geomfromtextPoint($value['longitude'], $value['latitude'], true),
                'name' => $value['name'] ?? $value['addressname'],
                'uid' => $tripData['uid'],
                'time' => $tripData['time'],
                'type' => 0,
                'map_type' => $value['map_type'] ?? 0,
            ];
            $upData[] = $itemUpData;
        }
        $this->where('trip_id', $tripData['id'])->delete();
        $res = $this->insertAll($upData);
        return $res;
    }
}
