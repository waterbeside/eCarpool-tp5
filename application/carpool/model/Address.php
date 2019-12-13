<?php

namespace app\carpool\model;

use think\facade\Cache;
use think\Model;
use my\RedisData;

class Address extends Model
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 'address';

    // 直接使用配置参数名
    protected $connection = 'database_carpool';

    protected $pk = 'addressid';

    protected $insert = [];
    protected $update = [];

    public $errorMsg = "";

    public function addFromTrips($data)
    {
        if (empty($data['longitude']) || empty($data['latitude']) || empty($data['addressname'])) {
            $this->errorMsg = lang('Parameter error');
            return false;
        }
        //先查找有没有对应的地址
        $findMap = [
            'addressname' => $data['addressname'],
            'latitude' => $data['latitude'],
            'longtitude' => $data['longitude'],
        ];
        $res = $this
            ->field("address_type,addressname,longtitude as longitude,latitude,create_time,company_id,city,addressid,address,district")
            ->where($findMap)->find();
        if ($res) {
            $data = array_merge($data, $res->toArray());
            if (isset($data['company_id'])) {
                $data['company_id'] = intval($data['company_id']);
            }
            return $data;
        }
        //如果没有数据，则创建
        $city = isset($data['city']) && $data['city'] ? $data['city'] : "";
        $inputData = [
            'address_type' => 1,
            'addressname' => $data['addressname'],
            'longtitude' => $data['longitude'],
            'latitude'   => $data['latitude'],
            'create_time'   => date("Y-m-d H:i:s"),
            'company_id'   => intval($data['company_id']),
            'city'       => $city ? $city : '--',
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
            $this->errorMsg = lang("Fail");
            return false;
        }
        unset($data['longtitude']);
        return $data;
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
        $redis = new RedisData();
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
        $redis = new RedisData();
        $redis->delete($cacheKey);
    }
}
