<?php

namespace app\carpool\model;

use think\Model;
use app\common\model\BaseModel;
use my\Utils;
use think\Db;

class Address extends BaseModel
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 'address';

    // 直接使用配置参数名
    protected $connection = 'database_carpool';

    protected $pk = 'addressid';

    protected $insert = [];
    protected $update = [];

    public $errorMsg = "";
    public $itemCacheExpire = 2 * 60;
    public $itemFieldsMap = [
        'ST_ASTEXT(gis)' => 'gis',
        'X(gis)' => 'lng',
        'Y(gis)' => 'lat',
    ];

    public function getCommonFields($exFields = null)
    {
        $field = ['addressid', 'addressname', 'address_type', 'status', 'city','district', 'address', 'map_type', 'usage_count'];
        $exFields = is_array($exFields) ? $exFields : [];
        $field = array_merge($field, $exFields);
        $field = implode(',', $field);
        $exField = $this->itemFieldsMap2Str($this->itemFieldsMap);
        $field .= $exField ? ','.$exField : '';
        return $field;
    }

    /**
     * 取得单项数据缓存Key设置
     *
     * @param integer $id 主键
     * @return string
     */
    public function getItemCacheKey($id)
    {
        return "carpool:address:$id";
    }


    public function getCitysCacheKey($company_id, $type)
    {
        $type = $type == 'wall' ? 1 : ($type == 'info' ? 2 : $type);
        return "carpool:citys:company_id_$company_id:type_$type";
    }

    public function delCitysCache($company_id, $type)
    {
        $cacheKey = $this->getCitysCacheKey($company_id, $type);
        return $this->redis()->del($cacheKey);
    }

    /**
     * 取得我的推荐站点列表缓存key
     *
     * @param integer $uid 用户id
     * @return string
     */
    public function getMyCacheKey($uid)
    {
        return "carpool:address:my:$uid";
    }

    /**
     * 清除我的推荐站点列表缓存
     *
     * @param integer $uid 用户id
     * @return string
     */
    public function deltMyCache($uid)
    {
        $cacheKey = $this->getMyCacheKey($uid);
        return $this->redis()->del($cacheKey);
    }

    /**
     * 检查经纬度是否合法
     *
     * @param double $lng 经度
     * @param double $lat 纬度
     * @return boolean
     */
    public function checkLngLat($lng, $lat)
    {
        if ($lng > 180 || $lng < -180) {
            return $this->setError(-1, '经度不合法');
        }
        if ($lat > 90 || $lat < -90) {
            return $this->setError(-1, '纬度不合法');
        }
        return true;
    }

    /**
     * 创建起终点\途经点
     */
    public function createTripAddress($datas, $userData, $radius = 50)
    {
        $createAddress = [];
        $mapType = $datas['map_type'] ?? null;
        if ($mapType !== null) {
            $datas['start']['map_type'] = $mapType;
            $datas['end']['map_type'] = $mapType;
        }
        //处理起点
        $startRes = $this->createAnAddressOrGetItem($datas['start'], $userData, $radius);
        if (!$startRes) {
            return $this->setError(-1, lang("The point of departure must not be empty"));
        }
        $createAddress['start'] = $startRes;

        //处理终点
        $endRes = $this->createAnAddressOrGetItem($datas['end'], $userData, $radius);
        if (!$endRes) {
            return $this->setError(-1, lang("The destination cannot be empty"));
        }
        $createAddress['end'] = $endRes;

        // 处理途经点
        $waypoints = $datas['waypoints'] ?? [];
        $createAddress['waypoints'] = [];
        if (!empty($waypoints)) {
            Db::connect('database_carpool')->startTrans();
            try {
                foreach ($waypoints as $key => $value) {
                    if ($mapType !== null) {
                        $value['map_type'] = $mapType;
                    }
                    $pointRes = $this->createAnAddressOrGetItem($value, $userData, $radius);
                    $createAddress['waypoints'][] = $pointRes;
                }
                Db::connect('database_carpool')->commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::connect('database_carpool')->rollback();
                $errorMsg = $e->getMessage();
                return $this->setError(-1, $errorMsg, []);
            }
        }
        return $createAddress;
    }


    /**
     * 查找站点，如果没有，或者addressid为空，则创建站点
     *
     * @param array $addressData 站点数据
     * @param array $userData 操作用户的数据
     * @return array 返回站点数据
     */
    public function createAnAddressOrGetItem($addressData, $userData, $radius = 50)
    {
        $addressid = $addressData['addressid'] ?? 0;
        $addressid = is_numeric($addressid) ? $addressid : 0;
        if ($addressid > 0) {// 如果有address id
            $pointRes = $this->getItem($addressid, ['addressid','addressname','lng','lat','address_type', 'address', 'district']);
            if (empty($pointRes)) {
                $pointRes = $this->createAnAddress($addressData, $userData, $radius);
            } else {
                $pointRes['longitude'] = $pointRes['lng'];
                $pointRes['latitude'] = $pointRes['lat'];
                unset($pointRes['lng']);
                unset($pointRes['lat']);
            }
        } else {
            $pointRes = $this->createAnAddress($addressData, $userData, $radius);
        }
        return $pointRes;
    }

    /**
     * 创建一个站点
     */
    public function createAnAddress($addressData, $userData, $radius = 50)
    {
        $addressData['company_id'] = $userData['company_id'];
        $addressData['create_uid'] = $userData['uid'];
        $addressRes = $this->addOne($addressData, $radius);
        if (!$addressRes) {
            $errorData = $this->getError();
            return $this->setError($errorData['code'] ?? -1, $errorData['msg'] ?? lang("The adress must not be empty"));
        }
        // $addressDatas['addressid'] = $addressRes['addressid'];
        return $addressRes;
    }

    /**
     * 通过行程请求来的数据新建站点
     *
     * @param array $data 请求数据
     * @param integer $radius 如果同名称下，该米数半径内有站点，则不插行。返回查出的行.
     * @return array 返回有用的站点数据
     */
    public function addOne($data, $radius = 0)
    {
        if (empty($data['longitude']) || empty($data['latitude']) || empty($data['addressname'])) {
            return $this->setError(-1, lang('Parameter error'));
        }
        if (!$this->checkLngLat($data['longitude'], $data['latitude'])) {
            return $this->setError(-1, '经纬度不合法');
        }
        // 查找就近同名站点返回
        if ($radius > 0) {
            //先查找有没有相近对应的地址
            $extraMap = [
                ['addressname', '=', $data['addressname']],
            ];
            $extraField = ['create_time', 'company_id'];
            $nearAddress = $this->getNear([$data['longitude'], $data['latitude']], $radius, $extraMap, $extraField, 1);
            if (!empty($nearAddress)) {
                $nearData = $nearAddress[0];
                $data = [
                    'addressid' => $nearData['addressid'],
                    'address_type' => $nearData['address_type'],
                    'addressname' => $nearData['addressname'],
                    'longitude' => $nearData['lng'],
                    'latitude'   => $nearData['lat'],
                    'company_id'   => intval($nearData['company_id']),
                    'city'       => $nearData['city'],
                    'create_time'       => $nearData['create_time'],
                    'address'       => $nearData['address'],
                    'district'       => $nearData['district'],
                ];
                return $data;
            }
        }

        //如果没有数据，则创建
        $city = isset($data['city']) && $data['city'] ? $data['city'] : "";
        $inputData = [
            'address_type' => $data['address_type'] ?? 1,
            'addressname' => $data['addressname'],
            'longtitude' => $data['longitude'],
            'latitude'   => $data['latitude'],
            'create_time'   => date("Y-m-d H:i:s"),
            'company_id'   => isset($data['company_id']) ? intval($data['company_id']) : 0,
            'gis'  =>  $this->geomfromtextPoint($data['longitude'], $data['latitude'], true),
            'city'       => $city ? $city : '--',
            'map_type'       => $data['map_type'] ?? 0,
        ];
        if (isset($data['create_uid'])) {
            $inputData['create_uid'] = $data['create_uid'];
        }
        if (isset($data['address']) && $data['address']) {
            $inputData['address'] = $data['address'];
        }
        if (isset($data['district']) && $data['district']) {
            $inputData['district'] = $data['district'];
        }

        $createID = $this->insertGetId($inputData);
        if ($createID) {
            if (isset($data['create_uid'])) {
                $this->deleteMyCache($data['create_uid']);
            }
            $data['addressid'] = intval($createID);
            $data = array_merge($data, $inputData);
        } else {
            return $this->setError(-1, '添加站点失败');
        }
        unset($data['longtitude']);
        return $data;
    }

    /**
     * 查找附近站点
     *
     * @param array $lnglat [lng, lat] 经纬度
     * @param array $radius 范围半径
     * @param array $extraMap 符加筛选条件
     * @param array $extraFields 补充字段
     * @param integer $limit 取条数
     * @return array
     */
    public function getNear($lnglat, $radius, $extraMap = null, $extraFields = null, $limit = 10)
    {
        $distanceSql = "ST_Distance_Sphere(POINT({$lnglat[0]}, {$lnglat[1]}), gis, 6371000)";
        $fields = $this->getCommonFields($extraFields);
        $fields .= ",$distanceSql as distance";
        $redis = $this->redis();
        $geohash = $lnglat[2] ?? $redis->getGeohash($lnglat);
        $geohashLen = Utils::getInstance()->getGeohashLengthByRadius($radius);
        $subHash = substr($geohash, 0, $geohashLen);
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
        $res = $this->field($fields)->where($where)->order($orderby)
            ->limit($limit)
            ->select();
        $res = $res ? $res->toArray() : [];
        return $res;
    }

    /**
     * 高德逆地理编码查询
     *
     * @param string|array $lnglat 经纬度或经纬度列表，经续度为"lng,lat"格式字符串
     */
    public function regeo($lnglat)
    {
        $location = $lnglat ;
        $batch = false;
        if (is_array($lnglat)) {
            $batch = true;
            $location = join('|', $lnglat);
        }
        $data = [
            'location' => $location,
            'key' => config('secret.amap_key.web'),
            'batch' => $batch,
        ];

        try {
            $res = clientRequest('https://restapi.amap.com/v3/geocode/regeo', ['query'=>$data], 'get');
            return $res;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function myCache($uid, $value = false, $ex = 60 * 5)
    {
        $cacheKey = "carpool:address:my_$uid";
        $redis = $this->redis();
        if ($value === false) {
            $result =  $redis->cache($cacheKey);
            return $result;
        } else {
            $redis->cache($cacheKey, $value, $ex);
        }
    }

    public function deleteMyCache($uid)
    {
        $cacheKey = "carpool:address:my_$uid";
        $redis = $this->redis();
        $redis->del($cacheKey);
    }
}
