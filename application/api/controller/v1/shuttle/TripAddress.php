<?php

namespace app\api\controller\v1\shuttle;

use app\api\controller\ApiBase;
use app\carpool\model\ShuttleTrip as ShuttleTripModel;
use app\carpool\model\Address as AddressModel;
use app\carpool\model\Info as InfoModel;
use app\user\model\Department as DepartmentModel;
use my\RedisData;
use my\Utils;
use think\Db;

/**
 * 行程站点
 * Class TripAddress
 * @package app\api\controller
 */
class TripAddress extends ApiBase
{


    /**
     * 取得城市数组
     *
     */
    public function citys()
    {
        $userData = $this->getUserData(1);
        $companyId = $userData['company_id'] ?: 0;
        $listType = input('param.list_type', '', 'strtolower');

        if (!in_array($listType, ['cars', 'requests'])) {
            return $this->jsonReturn(992, 'Error list_type');
        }
        $redis = RedisData::getInstance();
        $ShuttleTripModel = new ShuttleTripModel();

        $cacheKey = $ShuttleTripModel->getCitysCacheKey($companyId, $listType);
        $returnList = $redis->cache($cacheKey);
        if (is_array($returnList) && empty($returnList)) {
            return $this->jsonReturn(20002, $returnList, lang('No data'));
        }
        if (!$returnList) {
            $time = time();
            $betweenTimeBase = $ShuttleTripModel->getBaseTimeBetween($time, 'default', 'Y-m-d H:i:s');
            // join
            $join = [
                ["address a", "t.start_id = a.addressid", 'left']
            ];
            $comefrom = $listType == 'cars' ? 1 : 2;
            $userType = $listType == 'cars' ? 1 : 0;

            // where
            $map  = [
                ['t.time', 'between', $betweenTimeBase],
                ['t.is_delete', '=', Db::raw(0)],
                ['t.status', 'in', [0,1]],
                ['t.trip_id', '=', Db::raw(0)],
                ['t.line_type', 'in', [0,1,2]],
                ['t.comefrom', '=', $comefrom],
                ['t.user_type', '=', $userType],
            ];
            $resList = ShuttleTripModel::alias('t')->field('a.city , count(a.city) as num')->join($join)
                    ->where($map)->group('a.city')
                    ->order('num DESC')->select();
            $returnList = [];
            foreach ($resList as $key => $value) {
                if (in_array($value['city'], ['(null)', '--']) || empty($value['city'])) {
                    continue;
                }
                $returnList[] = $value;
            }
            $redis->cache($cacheKey, $returnList, 60 * 3);
            if (empty($returnList)) {
                return $this->jsonReturn(20002, $returnList, lang('No data'));
            }
        }

        $returnData = [
            'lists' => $returnList
        ];
        return $this->jsonReturn(0, $returnData, 'Successful');
    }

    /**
     * 我的推荐站点
     *
     */
    public function my()
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];


        $addressModel = new AddressModel();
        $redis = RedisData::getInstance();
        $ShuttleTripModel = new ShuttleTripModel();
        $DepartmentModel = new DepartmentModel();


        $cacheKey =  $addressModel->getMyCacheKey($uid);
        $returnList = $redis->cache($cacheKey);
        if (is_array($returnList) && empty($returnList)) {
            return $this->jsonReturn(20002, $returnList, lang('No data'));
        }
        if (!$returnList) {
            $fieldsAddress = 'a.addressid, a.addressname, a.address_type, X(gis) as longitude, Y(gis) as latitude, a.city, a.status, a.address, a.district, a.map_type';

            // 从行程取站点
            $joinShuttle = [
                ["address a", "t.addressid = a.addressid", 'left']
            ];
            $timeStart = strtotime('-180 day');
            $map = [
                ['status', '>', '-1'],
                ['uid', '=', $uid],
                ['time', '>', date('Y-m-d H:i:s', $timeStart)]
            ];
            $startMap = array_merge($map, [['start_id', '>', 0]]);
            $endMap = array_merge($map, [['end_id', '>', 0]]);
            $startField  = 'start_id as addressid, time';
            $endField  = 'end_id as addressid, time';

            // **** 从shuttle_trip取最常用10条
            $startSql = ShuttleTripModel::field($startField)->where($startMap)->order('time DESC')->buildSql();
            $endSql = ShuttleTripModel::field($endField)->where($endMap)->buildSql();
            $shuttleRes = Db::connect('database_carpool')->field($fieldsAddress.', max(t.time) as time, count(t.addressid) as used_count')
                    ->table("($startSql union $endSql)")->alias('t')->join($joinShuttle)->group('addressid')->limit(20)->order('time DESC, used_count DESC')->select();

            // **** 取得官方站点
            $mapOffical = [
                ['a.status', '>', 0],
                ['a.address_type', 'in', [3, 4]],
                ['a.is_delete', '=', Db::raw(0)],
            ];
            if ($userData['company_id'] != 20) { //班车公司可最得所有官方路线, 其它则根据部门地区过滤
                $departmentRes = $DepartmentModel->getItem($userData['department_id']);
                if ($departmentRes) {
                    $mapOffical[] = ['a.department_id', 'in', $departmentRes['path']];
                }
            }
            
            $officalRes = $addressModel->alias('a')->field("$fieldsAddress , create_time as time, 0 as used_count")
                    ->where($mapOffical)->order('address_type ASC, create_time DESC')->select();
            $officalRes = $officalRes ? $officalRes->toArray() : [];

            // *** 从info表取一周内常用10条;
            $joinShuttle = [
                ["address a", "t.startpid = a.addressid", 'left']
            ];
            $mapInfo = [
                ['t.status', '>', '-1'],
                ['t.carownid|t.passengerid', '=', $uid],
                ['t.time', 'between', [date('YmdHi', $timeStart), date('YmdHi')]],
                ['t.startpid', '>', 0]
            ];
            $infoRes = InfoModel::field("$fieldsAddress , max(DATE_FORMAT(CONCAT(time, '00'),'%Y-%m-%d %H:%i:%s')) as time, count(startpid) as used_count")
                    ->alias('t')->join($joinShuttle)->where($mapInfo)->group('t.startpid')->limit(15)->order('time DESC, used_count DESC')->select();
            $infoRes = $infoRes ? $infoRes->toArray() : [];
            // 合并结果
            $returnList = array_merge($shuttleRes, $officalRes, $infoRes);
            $Utils = new Utils();
            $returnList = $Utils->uniquListField($returnList, 'addressid', function ($value) use ($Utils) {
                return $Utils->formatTimeFields($value, 'item', ['time']);
            });
            // $redis->cache($cacheKey, $returnList, 60 * 60);
        }
        // $resultSet = Db::query('call get_my_address('.$uid.')');
        $returnData  = array(
            'lists' => $returnList,
            'total' => count($returnList)
        );
        $this->jsonReturn(0, $returnData, "success");
        // $this->success('加载成功','',$returnData);
    }

    /**
     * 查找我可选的路线公司
     *
     */
    public function companys($usage = null)
    {
        $addressModel = new AddressModel();
        $redis = RedisData::getInstance();
        $DepartmentModel = new DepartmentModel();

        $userData = $this->getUserData(1);
        $departmentId = $userData['department_id'];
        $cacheKey = "carpool:shuttle:address:companys:dptId_$departmentId";
        $cacheKey .= !empty($usage) ? ",usage_$usage" : '';
        
        $res = $redis->cache($cacheKey);
        if (is_array($res) && empty($res)) {
            $this->jsonReturn(20002, 'No data');
        }

        if (empty($res)) {
            // field
            $fieldsAddress = 'a.addressid as id, a.addressname as name, X(gis) as longitude, Y(gis) as latitude';
            // $fieldsAddress .= ',a.address_type,a.city, a.status, a.address, a.district, a.map_type';
            // where
            $mapOffical = [
                ['a.status', '>', 0],
                ['a.address_type', '=', 3],
                ['a.is_delete', '=', Db::raw(0)],
            ];
            // where department
            $departmentData =  $DepartmentModel->getItem($departmentId);
            if (!$departmentData) {
                return $this->jsonReturn(20002, lang('No data'));
            }
            $departmentPath = $departmentData['path'].','.$departmentId;
            $mapOffical[] = ['a.department_id', 'in', $departmentPath];

            $res = $addressModel->alias('a')->field($fieldsAddress)
                ->where($mapOffical)->order('address_type ASC, create_time DESC')->select();
            if (!$res) {
                $redis->cache($cacheKey, [], 20);
                return $this->jsonReturn(20002, 'No data');
            }

            $res = $res->toArray();
            $redis->cache($cacheKey, $res, 60);
        }
        $returnData = [
            'lists' => $res,
        ];
        return $this->jsonReturn(0, $returnData, 'Successful');
    }
}
