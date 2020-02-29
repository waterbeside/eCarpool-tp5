<?php

namespace app\carpool\model;

use think\Model;
use app\common\model\BaseModel;

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
     * 创建起终点
     */
    public function createAddress($datas, $userData)
    {
        $createAddress = [];
        //处理起点
        $startData = $datas['start'];
        if ((!isset($datas['start']['addressid']) || !(is_numeric($datas['start']['addressid']) && $datas['start']['addressid'] > 0))) {
            $startRes = $this->createAnAddress($startData, $userData);
        } else { // 如果有address id
            $startRes = $this->getItem($datas['start']['addressid'], ['addressid','addressname','longtitude','latitude','address_type']);
            if (empty($startRes)) {
                $startRes = $this->createAnAddress($startData, $userData);
            } else {
                $startRes['longitude'] = $startRes['longtitude'];
                unset($startRes['longtitude']);
            }
        }
        if (!$startRes) {
            return $this->setError(-1, lang("The point of departure must not be empty"));
        }
        $createAddress['start'] = $startRes;

        //处理终点
        $endDatas = $datas['end'];
        if ((!isset($datas['end']['addressid']) || !(is_numeric($datas['end']['addressid']) && $datas['end']['addressid'] > 0))) {
            $endRes = $this->createAnAddress($endDatas, $userData);
        } else { // 如果有address id
            $endRes = $this->getItem($datas['end']['addressid'], ['addressid','addressname','longtitude','latitude','address_type']);
            if (empty($endRes)) {
                $endRes = $this->createAnAddress($endRes, $userData);
            } else {
                $endRes['longitude'] = $endRes['longtitude'];
                unset($endRes['longtitude']);
            }
        }
        if (!$endRes) {
            return $this->setError(-1, lang("The destination cannot be empty"));
        }
        $createAddress['end'] = $endRes;
        return $createAddress;
    }

    /**
     * 创建一个站点
     */
    public function createAnAddress($addressDatas, $userData)
    {
        $addressDatas['company_id'] = $userData['company_id'];
        $addressDatas['create_uid'] = $userData['uid'];
        $addressRes = $this->addFromTrips($addressDatas);
        if (!$addressRes) {
            return $this->setError(-1, lang("The adress must not be empty"));
        }
        // $addressDatas['addressid'] = $addressRes['addressid'];
        return $addressRes;
    }

    /**
     * 通过行程请求来的数据新建站点
     *
     * @param array $data 请求数据
     * @return array 返回有用的站点数据
     */
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
