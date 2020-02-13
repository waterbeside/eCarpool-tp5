<?php

namespace app\carpool\model;

use think\facade\Cache;
use my\RedisData;
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
     * @return string
     */
    public function getListCacheKeyByLineId($line_id, $create_type)
    {
        return "carpool:shuttle:tripList:lineId_{$line_id}:{$create_type}";
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
     * 取得空座位列表的时间起终值
     *
     * @param integer $time 时间戳
     * @param integer $offset 结束时间向前偏移秒数
     * @param string $format 时间格式
     * @return array
     */
    public function getDefatultOffsetTime($time, $offset = 0, $format = null)
    {
        $startTime = $time - (5 * 60);
        $endTime = $time + (60 * 60 * 24 * 7 - $offset);

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
    public function countByLine($line_id, $count_type, $uid = 0, $ex = 10)
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
            $offsetTimeArray = $this->getDefatultOffsetTime($time, 0, 'Y-m-d H:i:s');
            if ($count_type === 'requests') { // 当前约车需求数
                $offsetTimeArray[0] = date('Y-m-d H:i:s');
                $map = $mapBase;
                $map[] = ['comefrom', '=', 2];
                $map[] = ['status', '=', 0];
                $map[] = ['trip_id', '=', 0];
                $map[] = ['time', 'between', $offsetTimeArray];
            } elseif ($count_type === 'cars') { // 当前空座位数
                $map = $mapBase;
                $map[] = ['comefrom', '=', 1];
                $map[] = ['status', 'between', [0,1]];
                $map[] = ['time', 'between', $offsetTimeArray];
            } elseif ($count_type === 'used_total') { // 总使用次数
                $map = $mapBase;
                $map[] = ['comefrom', '=', 1];
                $map[] = ['status', '>', -1];
                if ($uid > 0) {
                    $map[] = ['uid', '>', $uid];
                }
            } elseif ($count_type === 'user_useed_total') {
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
            ['id', 'comefrom', 'user_type','trip_id', 'line_id', 'plate', 'status', 'time', 'create_time', 'seat_count'] : $fieldArray;
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
                try {
                    $trip_info = json_decode($value['extra_info'], true);
                    $lineData = $trip_info['line_data'];
                } catch (\Exception $e) {  //其他错误
                    $lineData = [];
                }
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
                    'start_name' => $lineData['start_name'] ?: '',
                    'end_name' => $lineData['end_name'] ?: '',
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
                $list_cacheKey = $this->getListCacheKeyByLineId($data['line_id'], $data['create_type']);
                $count_cacheKey = "carpool:shuttle:trip:countByLine:{$data['create_type']}_lineId_{$data['line_id']}";
                $redis->del($list_cacheKey, $count_cacheKey);
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
     * @param string $type 'cars' or 'requests'
     * @return void
     */
    public function delListCache($line_id, $type = null)
    {
        if (in_array($type, ['my', 'history'])) {
            return $this->delMyListCache($line_id, $type);
        }
        $redis = $this->redis();
        if (!$type) {
            $cacheKey_1 = $this->getListCacheKeyByLineId($line_id, 'cars');
            $cacheKey_2 = $this->getListCacheKeyByLineId($line_id, 'requests');
            $redis->del($cacheKey_1, $cacheKey_2);
        } else {
            $cacheKey = $this->getListCacheKeyByLineId($line_id, $type);
            $redis->del($cacheKey);
        }
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
            return $this->setError(20002, '该行程不存在', $tripData);
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
            return $this->setError(20002, '该行程不存在', $tripData);
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
            return $this->setError(20002, '该行程不存在', $tripData);
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
                $this->delListCache($tripData['line_id'], 'requests');
            }
            // 提交事务
            Db::connect('database_carpool')->commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::connect('database_carpool')->rollback();
            $errorMsg = $e->getMessage();
            return $this->setError(-1, $errorMsg, []);
        }
        $this->delListCache($tripData['line_id'], 'cars');
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
            return $this->setError(20002, '该行程不存在', $tripData);
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
            return $this->setError(20002, '该行程不存在', $tripData);
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
            return $this->setError(20002, '该行程不存在', $tripData);
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
        try {
            $extra_data = json_decode($tripData['extra_info'], true);
        } catch (\Exception $e) {  //其他错误
            $extra_data = null;
        }
        return $key ? ($extra_data[$key] ?? null) : $extra_data;
    }
}
