<?php

namespace app\carpool\model;

use think\facade\Cache;
use my\RedisData;
use my\Utils;
use app\common\model\BaseModel;

use think\Db;

class ShuttleTrip extends BaseModel
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 't_shuttle_trip';

    // 直接使用配置参数名
    protected $connection = 'database_carpool';
    protected $pk = 'id';
    protected $insert = [];
    protected $update = [];

    /**
     * 取得单项数据缓存Key设置
     *
     * @param integer $id 主键
     * @return string
     */
    public function getItemCacheKey($id)
    {
        return "carpool:shuttle:trip:$id";
    }

    /**
     * 取得列表cacheKey;
     *
     * @param integer $line_id 路线id
     * @param string $create_type 类型，['cars', 'requests'] 是空座位还是约车需求
     * @param integer $uid 用户id
     * @return string
     */
    public function getListCacheKeyByLineId($line_id, $create_type, $uid = null)
    {
        $cacheKey =  "carpool:shuttle:tripList:lineId_{$line_id}:{$create_type}";
        if (is_string($uid) || is_numeric($uid)) {
            $cacheKey .= ":uid_{$uid}";
        }
        return $cacheKey;
    }

    /**
     * 取得乘客列表cacheKey;
     *
     * @param integer $trip_id 行程id
     * @return string
     */
    public function getPassengersCacheKey($trip_id)
    {
        return "carpool:shuttle:trip:passengers:tripId_{$trip_id}";
    }

    /**
     * 取得乘客数cacheKey;
     *
     * @param integer $trip_id 行程id
     * @return string
     */
    public function getTookCountCacheKey($trip_id)
    {
        return "carpool:shuttle:trip:passengersCount:tripId_{$trip_id}";
    }

    /**
     * 取得我的行程 或 历史行程 列表cacheKey;
     *
     * @param integer $uid 用户uid
     * @param string $type  my:我的行程, history:历史行程
     * @return string
     */
    public function getMyListCacheKey($uid, $type = 'my')
    {
        return "carpool:shuttle:trip:{$type}:{$uid}";
    }

    /**
     * 取得行程城市数组cacheKey
     *
     * @param integer $companyId 公司id
     * @param string $listType 类型，['cars', 'requests'] 是空座位还是约车需求
     * @return string
     */
    public function getCitysCacheKey($companyId, $listType)
    {
        return "carpool:shuttle:trip:citys:$companyId,$listType";
    }


    /**
     * 取得行程的基本时间范围
     *
     * @param integer $time 时间戳
     * @param mixed $maxOffset integer or array 最大范围偏移, 当为array时格式必为[前偏移值,后偏称值]
     * @param string $format 时间格式
     * @param string $addEndTime 多少秒后结束，默认值为7天。
     * @return array
     */
    public function getBaseTimeBetween($time = null, $maxOffset = 60 * 60, $format = null, $addEndTime = 60 * 60 * 24 * 7)
    {
        if (is_array($maxOffset)) {
            $offsetA = $maxOffset[0];
            $offsetB = $maxOffset[1] ?? $offsetA;
        } elseif (is_numeric($maxOffset)) {
            $offsetA = $maxOffset;
            $offsetB = $maxOffset;
        } else {
            return $this->getBaseTimeBetween($time, 60 * 60, $format, $addEndTime);
        }
        $startTime = $time - $offsetA;
        $endTime = $time +  $offsetB + $addEndTime;
        return [
            $format ? date($format, $startTime) : $startTime,
            $format ? date($format, $endTime) : $endTime,
        ];
    }

    /**
     * 取得行程数据
     *
     * @param mixed $idOrData 当为数字时，为行程id；当为array时，为该行程的data;
     * @return array
     */
    public function getDataByIdOrData($idOrData)
    {
        $tripData = is_numeric($idOrData) ? $this->getItem($idOrData) : $idOrData;
        return $tripData;
    }


    /**
     * 计算空座位数和约车需求数
     *
     * @param integer $line_id 路线id
     * @param string $count_type 类型，['cars', 'requests'] 是空座位还是约车需求
     * @return integer
     */
    public function countByLine($line_id, $count_type, $uid = 0, $ex = 60)
    {
        $cacheKey = "carpool:shuttle:trip:countByLine:lineId_{$line_id}:{$count_type}";
        if ($uid) {
            $cacheKey .= ":uid_{$uid}";
        }
        $redis = $this->redis();
        $res = $redis->cache($cacheKey);
        if ($res === false) {
            $mapBase = [
                ['line_id', '=', $line_id],
            ];
            $time = time();
            $offsetTimeArray = $this->getBaseTimeBetween($time, 'default', 'Y-m-d H:i:s');
            if ($count_type === 'requests') { // 当前约车需求数
                $map = $mapBase;
                $betweenTimeArray = $this->getBaseTimeBetween($time, [10, 0]);
                $map[] = ['comefrom', '=', 2];
                $map[] = ['status', '=', 0];
                $map[] = ['trip_id', '=', 0];
                $map[] = ['time', 'between', $offsetTimeArray];
                $map[] = ['', 'exp', Db::raw(" (UNIX_TIMESTAMP(time) - time_offset) < {$betweenTimeArray[1]} AND (UNIX_TIMESTAMP(time) + time_offset) > {$betweenTimeArray[0]}")];
            } elseif ($count_type === 'cars') { // 当前空座位数
                $map = $mapBase;
                $betweenTimeArray = $this->getBaseTimeBetween($time, [60 * 5, 0]);
                $map[] = ['comefrom', '=', 1];
                $map[] = ['status', 'between', [0,1]];
                $map[] = ['time', 'between', $offsetTimeArray];
                $map[] = ['', 'exp', Db::raw(" (UNIX_TIMESTAMP(time) - time_offset) < {$betweenTimeArray[1]} AND (UNIX_TIMESTAMP(time) + time_offset) > {$betweenTimeArray[0]}")];
            } elseif ($count_type === 'used_total') { // 总使用次数
                $map = $mapBase;
                $map[] = ['comefrom', '=', 1];
                $map[] = ['status', '>', -1];
                if ($uid > 0) {
                    $map[] = ['uid', '=', $uid];
                }
            } elseif ($count_type === 'user_used_total') {
                $map = $mapBase;
                $map[] = ['comefrom', '=', 1];
                $map[] = ['status', '>', -1];
            } else {
                return 0;
            }
            $res = $this->where($map)->count();
            
            $randomExOffset = [1,2,3];
            $exp_offset = getRandValFromArray($randomExOffset);
            $ex = $ex ?: 10;
            $ex +=  $exp_offset * ($ex > 60 ? 60 : ($ex > 10 ? 5 : 1));
            $redis->cache($cacheKey, $res, $ex);
        }
        return $res ?: 0;
    }


    /**
     * 取得接口列表需要的字段
     *
     * @param string $alias 创建sql时，表的别名
     * @return string
     */
    public function getListField($alias = '', $fieldArray = null)
    {
        $fieldArray = empty($fieldArray) ?
            [
                'id', 'comefrom', 'user_type','trip_id', 'line_id', 'plate', 'status', 'time', 'time_offset', 'create_time', 'seat_count',
                'line_type', 'start_id', 'start_name', 'start_longitude', 'start_latitude', 'end_id', 'end_name', 'end_longitude', 'end_latitude', 'extra_info'
            ] : $fieldArray;
        $newFieldArray = [];
        foreach ($fieldArray as $key => $value) {
            $newField = $alias ? $alias.'.'.$value : $value;
            $newFieldArray[] = $newField;
        }
        return implode(',', $newFieldArray);
    }

    /**
     * 通过时间范围取得行程数据内容
     *
     * @param  integer     $time       出发时间的时间戳
     * @param  integer     $uid        发布者ID
     * @param  string      $offsetTime 时间偏差范围
     *
     */
    public function getListByTimeOffset($time, $uid, $offsetTime = 60 * 30, $limit = 0)
    {
        $map = [
            ["status", "between", [0,1]],
            ["uid", "=", $uid],
        ];
        if (is_numeric($offsetTime)) {
            $offsetTime = [$offsetTime, $offsetTime];
        }
        if (count($offsetTime) < 2) {
            $offsetTime = [$offsetTime[0], $offsetTime[0]];
        }
        if (is_numeric($offsetTime[0])) {
            $startTime = $time - $offsetTime[0];
            $map[] = ["time", ">=", date('Y-m-d H:i:s', $startTime)];
        }
        if (is_numeric($offsetTime[1])) {
            $endTime = $time + $offsetTime[1];
            $map[] = ["time", "<=", date('Y-m-d H:i:s', $endTime)];
        }

        $res_trip = $this->where($map)->limit($limit)->select();
        $res = [];
        if ($res_trip) {
            foreach ($res_trip as $key => $value) {
                $data = [
                    'from'=>'shuttle_trip',
                    'id' => $value['id'],
                    'love_wall_ID' => 0,
                    'info_id' => 0,
                    'd_uid' => $value['user_type'] == 1 ? $value['uid'] : 0,
                    'p_uid' => $value['user_type'] == 0 ? $value['uid'] : 0,
                    'time'=>strtotime($value['time']),
                    'seat_count' => $value['seat_count'],
                    'user_type' => $value['user_type'],
                    'comefrom' => $value['comefrom'],
                    'trip_id' => $value['trip_id'],
                    'line_id'  => $value['line_id'],
                    'start_id' => $value['start_id'] ?: 0,
                    'start_name' => $value['start_name'] ?: '',
                    'end_id' => $value['end_id'] ?: 0,
                    'end_name' => $value['end_name'] ?: '',
                    'status' => $value['status'],
                ];
                $res[] = $data;
            }
        }
        return $res;
    }

    /**
     * 添加行程后清除缓存
     *
     * @param array $data 数据;
     * @return void
     */
    public function delCacheAfterAdd($data)
    {
        $redis = $this->redis();

        if (isset($data['create_type'])) {
            // 清除指定line_id列表缓存
            if (isset($data['line_id']) && $data['line_id'] > 0 &&  in_array($data['create_type'], ['cars', 'requests'])) {
                $this->delListCache($data['line_id'], $data['create_type']);
                $count_cacheKey = "carpool:shuttle:trip:countByLine:{$data['create_type']}_lineId_{$data['line_id']}";
                $redis->del($count_cacheKey);
            }
            // 清除自己所见的空座位和或约车需求列表缓存
            if (isset($data['uid'])  &&  in_array($data['create_type'], ['cars', 'requests'])) {
                $this->delListCache(0, $data['create_type'], $data['uid']);
            }
            // 清除乘客列表缓存
            if (isset($data['trip_id']) && $data['trip_id'] > 0  && in_array($data['create_type'], ['hitchhiking'])) {
                $this->delPassengersCache($data['trip_id']);
            }
            if (in_array($data['create_type'], ['cars', 'pickup'])) {
                $this->delPassengersCache($data['id']);
            }
        }

        // 清除指定用户我的历史列表缓存
        if (isset($data['myType']) && isset($data['uid'])) {
            $myTypes = explode(',', $data['myType']);
            foreach ($myTypes as $key => $value) {
                $this->delMyListCache($data['uid'], trim($value));
            }
        }
    }

    /**
     * 清除乘客列表缓存
     *
     * @param integer $id 行程id
     * @return void
     */
    public function delPassengersCache($id)
    {
        $cacheKey = $this->getPassengersCacheKey($id);
        $redis = $this->redis();
        return $redis->del($cacheKey);
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
     * 清除我的行程或历史行程缓存
     *
     * @param integer $uid 用户uid
     * @param string $type 'my' or 'history'
     * @return void
     */
    public function delMyListCache($uid, $type = 'my')
    {
        $cacheKey = $this->getMyListCacheKey($uid, $type);
        return $this->redis()->del($cacheKey);
    }

    /**
     * 清除空座位和约车需求列表缓存
     *
     * @param integer $line_id 路线id
     * @param string $type 'cars' or 'requests' or 'my' or 'history'
     * @param integer $uid 用户id
     * @return void
     */
    public function delListCache($line_id, $type = null, $uid = null)
    {
        if (in_array($type, ['my', 'history'])) {
            $this->delMyListCache($line_id, $type);
        }
        $redis = $this->redis();
        if (!$type) {
            $this->delListCache($line_id, 'cars', $uid);
            $this->delListCache($line_id, 'requests', $uid);
        } else {
            if (!$line_id > 0) {
                $cacheKey_0 = $this->getListCacheKeyByLineId(0, $type);
                $redis->del($cacheKey_0);
            }
            $cacheKey = $this->getListCacheKeyByLineId($line_id, $type, $uid);
            $redis->del($cacheKey);
        }
    }

    /**
     * 删除行程城市数组缓存
     *
     * @param integer $companyId 公司id
     * @param string $listType 类型，['cars', 'requests'] 是空座位还是约车需求
     */
    public function delCitysCacheKey($companyId, $listType)
    {
        $cacheKey = $this->getCitysCacheKey($companyId, $listType);
        return $this->redis()->del($cacheKey);
    }

    /**
     * 计算乘客个数
     *
     * @param integer $id 司机行程id
     * @param integer $ex 缓存有效期，当为false时，不取缓存数据
     * @return integer
     */
    public function countPassengers($id, $ex = 10)
    {
        $cacheKey = $this->getTookCountCacheKey($id);
        $redis = $this->redis();
        $res = $redis->cache($cacheKey);
        if ($res === false || $ex === false) {
            $map = [
                ['user_type', '=', Db::raw(0)],
                ['trip_id', '=', $id],
                ['status', 'between', [0,5]],
            ];
            $res = $this->where($map)->count();
            if (is_numeric($ex)) {
                $redis->cache($cacheKey, $res, $ex);
            }
        }
        return $res;
    }

    /**
     * 检查我是否这个行程的司机
     *
     * @param mixed $idOrData 当为数字时，为行程id；当为array时，为该行程的data;
     * @param integer $uid 司机的id
     * @return boolean
     */
    public function checkIsDriver($idOrData, $uid)
    {
        $tripData = $this->getDataByIdOrData($idOrData);
        if (empty($tripData)) {
            return $this->setError(20002, lang('The trip does not exist'), $tripData);
        }
        if ($tripData['user_type'] == 1 && $tripData['uid'] == $uid) {
            return true; // 我是该司机行程的主人
        } elseif ($tripData['user_type'] == 0 && $tripData['trip_id'] > 0) { //如果是乘客行程
            $driverTripData = $this->getItem($tripData['trip_id']); // 查出该乘客的司机行程
            if ($driverTripData && $driverTripData['uid'] == $uid) { //如果我是司机
                return true;
            }
        }
        return false;
    }

    /**
     * 取消乘客行程
     *
     * @param mixed $idOrData 当为数字时，为行程id；当为array时，为该行程的data;
     * @param integer $must 当为0时，如果未出过出发时间且来自约车需求的，则还原为约车需求，否则直接取消
     * @return void
     */
    public function cancelPassengerTrip($idOrData, $must = 0)
    {
        $tripData = $this->getDataByIdOrData($idOrData);
        $id = $tripData['id'];
        if (empty($tripData)) {
            return $this->setError(20002, lang('The trip does not exist'), $tripData);
        }
        if ($tripData['status'] == -1) {
            return true;
        }
        $map = [
            ['id', '=', $id],
        ];
        $upData = [
            'operate_time'=>date('Y-m-d H:i:s')
        ];
        if ($must === 0 && time() <= strtotime($tripData['time']) && $tripData['comefrom'] == 2 && $tripData['trip_id'] > 0 && $tripData['seat_count'] < 2) { // 如果未过出发时间 并且是有人搭的约车需求,
            $upData['status'] = 0;
            $upData['trip_id'] = 0;
            $upData['seat_count'] = 1;
        } else { // 如果过了出发时间, 或者是带同伴的行程
            $upData['status'] = -1;
        }
        $res = $this->where($map)->update($upData);
        if ($res === false) {
            return $this->setError(-1, 'Failed', []);
        }
        if ($upData['status'] == 0 || $tripData['trip_id']  == 0) { // 如果是取消到约车需求 则刷新约车需求缓存
            $this->delListCache($tripData['line_id'], 'requests');
            $this->delListCache(0, 'requests', $tripData['uid']);
        }
        return true;
    }

    /**
     * 取消司机行程并同时取消该司机下乘客的行程
     *
     * @param mixed $idOrData 当为数字时，为行程id；当为array时，为该行程的data;
     * @return void
     */
    public function cancelDriveTrip($idOrData)
    {
        $tripData = $this->getDataByIdOrData($idOrData);
        $id = $tripData['id'];
        if (empty($tripData)) {
            return $this->setError(20002, lang('The trip does not exist'), $tripData);
        }
        $returnData = [];
        // 查出所有乘客行程，以便作消息推送;
        Db::connect('database_carpool')->startTrans();
        try {
            $upData = [
                'status' => -1,
                'operate_time'=>date('Y-m-d H:i:s')
            ];
            // 先取消司机行程
            $this->where('id', $id)->update($upData);
            // 再取消乘客行程
            if (time() > strtotime($tripData['time'])) { // 如果过了出发时间
                $map = [
                    ['trip_id', '=', $id],
                    ['status', '>', -1],
                    ['comefrom', 'between', [2, 4]],
                ];
                $this->where($map)->update($upData);
            } else { // 如果未过出发时间
                // 取消自主上车的乘客
                $map = [
                    ['trip_id', '=', $id],
                    ['status', '>', -1],
                    ['comefrom', '=', 3],
                ];
                $this->where($map)->update($upData);
                // 还原发了需求的乘客
                $map2 = [
                    ['trip_id', '=', $id],
                    ['status', '>', -1],
                    ['comefrom', '=', 2],
                ];
                $requestTripList = $this->where($map2)->select(); // 先查出所有约车需求，以便还原partner表的内容
                $returnData['requestTripList'] = $requestTripList;
                $this->where($map2)->update(['status' => 0, 'trip_id'=>0, 'operate_time'=>date('Y-m-d H:i:s')]);
                // 还原通过同行者方式搭车的乘客 (取消这些乘客的行程)
                $map3 = [
                    ['trip_id', '=', $id],
                    ['status', '>', -1],
                    ['comefrom', '=', 4],
                ];
                $partnerTripList = $this->where($map3)->select(); // 先查出，以便还原partner表的内容
                $returnData['partnerTripList'] = $partnerTripList;
                $this->where($map3)->update($upData); // 更改状态为取消
                // 清除约车需求缓存
                $this->delListCache($tripData['line_id'], 'requests');
                foreach ($requestTripList as $key => $value) {
                    $this->delListCache(0, 'requests', $value['uid']);
                }
            }
            // 提交事务
            Db::connect('database_carpool')->commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::connect('database_carpool')->rollback();
            $errorMsg = $e->getMessage();
            return $this->setError(-1, $errorMsg, []);
        }
        // 清司机列表缓存
        $this->delListCache($tripData['line_id'], 'cars');
        $this->delListCache(0, 'cars', $tripData['uid']);
        $this->setError(0, 'Successful', $returnData); // 如果有同行者信息，则放在此，以便还原。
        return true;
    }


    /**
     * 完结乘客行程
     *
     * @param mixed $idOrData 当为数字时，为行程id；当为array时，为该行程的data;
     * @return void
     */
    public function finishPassengerTrip($idOrData)
    {
        $tripData = $this->getDataByIdOrData($idOrData);
        $id = $tripData['id'];
        if (empty($tripData)) {
            return $this->setError(20002, lang('The trip does not exist'), $tripData);
        }
        if ($tripData['status'] == 3) {
            return true;
        }
        $map = [
            ['id', '=', $id],
        ];
        $upData = [
            'status' => 3,
            'operate_time' => date('Y-m-d H:i:s')
        ];
        $res = $this->where($map)->update($upData);
        if ($res === false) {
            return $this->setError(-1, 'Failed', []);
        }
        return true;
    }

    /**
     * 完结司机行程并同时完结该司机下乘客的行程
     *
     * @param mixed $idOrData 当为数字时，为行程id；当为array时，为该行程的data;
     * @return void
     */
    public function finishDriverTrip($idOrData)
    {
        $tripData = $this->getDataByIdOrData($idOrData);
        $id = $tripData['id'];
        if (empty($tripData)) {
            return $this->setError(20002, lang('The trip does not exist'), $tripData);
        }
        Db::connect('database_carpool')->startTrans();
        try {
            $upData = [
                'status' => 3,
                'operate_time'=>date('Y-m-d H:i:s')
            ];
            // 先取消司机行程
            $this->where('id', $id)->update($upData);
            // 不再完结乘客行程
            // $map = [
            //     ['trip_id', '=', $id],
            //     ['status', 'between', [0, 1]],
            //     ['comefrom', 'between', [2, 3]],
            // ];
            // $this->where($map)->update($upData);
            // 提交事务
            Db::connect('database_carpool')->commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::connect('database_carpool')->rollback();
            $errorMsg = $e->getMessage();
            return $this->setError(-1, $errorMsg, []);
        }
        $this->delListCache($tripData['line_id'], 'cars');
        $this->delListCache(0, 'cars', $tripData['uid']);
        return true;
    }

    /**
     * 检查是否在行程 （是否参与乘客）
     *
     * @param mixed $idOrData 当为数字时，为行程id；当为array时，为该行程的data;
     * @param integer $idOrData 当为数字时，为行程id；当为array时，为该行程的data;
     * @return void
     */
    public function checkInTrip($idOrData, $uid)
    {
        $tripData = $this->getDataByIdOrData($idOrData);
        $id = $tripData['id'];
        if (empty($tripData)) {
            return $this->setError(20002, lang('The trip does not exist'), $tripData);
        }
        $map = [
            ['status', '>', 0],
            ['uid', '=', $uid],
            ['trip_id', '=', $id],
        ];
        $res = $this->where($map)->find();
        return $res;
    }

    /**
     * 通过司机行程查找某用户是否该行程乘客，并返回乘客行程数据
     *
     * @param integer $trip_id 司机行程id
     * @param integer $uid 用户id
     * @param array $statusMap
     * @return array
     */
    public function findPtByDt($trip_id, $uid, $statusMap = ['status', '>', -1])
    {
        $myTripMap = [
            ['trip_id', '=', $trip_id],
            ['uid', '=', $uid],
        ];
        if (is_numeric($statusMap)) {
            $myTripMap[] = ['status', '=', $statusMap];
        } else {
            $myTripMap[] = $statusMap;
        }
        $res = $this->where($myTripMap)->find();
        return $res;
    }
    
    /**
     * 取得乘客的行程列表
     *
     * @param integer $id 行程id
     * @return mixed
     */
    public function passengers($id)
    {
        $cacheKey = $this->getPassengersCacheKey($id);
        $redis = $this->redis();
        $res = $redis->cache($cacheKey);
        if ($res === false) {
            $map = [
                ['t.user_type', '=', Db::raw(0)],
                ['t.trip_id', '=', $id],
                ['t.status', 'between', [0,5]],
            ];
            $res = $this->alias('t')->where($map)->order('t.create_time ASC')->select();
            if (!$res) {
                $redis->cache($cacheKey, [], 5);
            }
            $res =  $res->toArray();
            $redis->cache($cacheKey, $res, 60);
        }
        return $res;
    }

    /**
     * 取得Extra_info内容
     *
     * @param mixed $idOrData 当为数字时，为行程id；当为array时，为该行程的data;
     * @return array
     */
    public function getExtraInfo($idOrData, $key = null)
    {
        $tripData = $this->getDataByIdOrData($idOrData);
        $Utils = new Utils();
        $extra_data = $Utils->json2Array($tripData['extra_info'], true);
        return $key ? ($extra_data[$key] ?? null) : $extra_data;
    }


    /**
     * 取得常用的lineData数据, 并合并返回行程数据
     *
     * @param mixed $idOrData 当为数字时，为行程id；当为array时，为该行程的data;
     * @param array $lineDataField 要筛选的line_data字段
     * @param array $waypointField 要筛选的waypoint字段
     * @return array
     */
    public function packLineDataFromTripData($idOrData, $lineDataField = null, $waypointField = null)
    {
        $Utils = new Utils();
        $tripData = $this->getDataByIdOrData($idOrData);
        $extraLineData = $this->getExtraInfo($tripData, 'line_data');
        $lineDataField = $lineDataField ?: [
            'start_id', 'start_name', 'start_longitude', 'start_latitude',
            'end_id', 'end_name', 'end_longitude', 'end_latitude',
            'map_type','waypoints'
        ];
        $tripData['map_type'] = $extraLineData['map_type'] ?? 0;
        $tripData['waypoints'] = isset($extraLineData['waypoints']) ? $this->formatExtraInfoWaypointField($extraLineData['waypoints'], $waypointField) : [];
        $tripData = $Utils->packFieldsToField($tripData, [
            'line_data' => [
                'start_id', 'start_name', 'start_longitude', 'start_latitude',
                'end_id', 'end_name', 'end_longitude', 'end_latitude',
                'map_type','waypoints'
            ],
        ]);
        $tripData['line_data'] = $Utils->filterDataFields($tripData['line_data'], $lineDataField);
        return $tripData;
    }

    /**
     * 取得常用的lineData数据
     *
     * @param mixed $idOrData 当为数字时，为行程id；当为array时，为该行程的data;
     * @param array $lineDataField 要筛选的line_data字段
     * @param array $waypointField 要筛选的waypoint字段
     * @return void
     */
    public function getCommonLineData($idOrData, $lineDataField = null, $waypointField = null)
    {
        $tripData = $this->packLineDataFromTripData($idOrData, $lineDataField, $waypointField);
        $lineData = $tripData['line_data'] ?? null;
        return $lineData;
    }

    /**
     * 格式化extraInfo取得的waypoint数据
     *
     * @param array $waypoints 途经点数据
     * @param array $fields 途经点字段
     * @return array
     */
    public function formatExtraInfoWaypointField($waypoints, $fields = null)
    {
        if (empty($waypoints)) {
            return [];
        }
        $Utils = new Utils();
        foreach ($waypoints as $key => $value) {
            $value['name'] = $value['name'] ?? $value['addressname'];
            unset($value['addressname']);
            $value = $Utils->filterDataFields($value, $fields);
            $waypoints[$key] = $value;
        }
        return $waypoints;
    }
}
