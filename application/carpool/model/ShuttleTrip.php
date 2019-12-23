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
     * 取得我的行程 或 历史行程 列表cacheKey;
     *
     * @param integer $uid 用户uid
     * @param string $type  my:我的行程, history:历史行程
     * @return string
     */
    public function getMyListCacheKey($uid, $type = 'my')
    {
        return "carpool:shuttle:trip:{$type}:uid_{$uid}";
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
        $startTime = $time - (20 * 60);
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
     * @param string $create_type 类型，['cars', 'requests'] 是空座位还是约车需求
     * @return integer
     */
    public function countByLine($line_id, $create_type)
    {
        $cacheKey = "carpool:shuttle:trip:countByLine:{$create_type}_lineId_{$line_id}";
        $redis = new RedisData();
        $res = $redis->cache($cacheKey);
        if ($res === false) {
            $mapBase = [
                ['line_id', '=', $line_id],
            ];
            $time = time();
            $offsetTimeArray = $this->getDefatultOffsetTime($time, 0, 'Y-m-d H:i:s');
            if ($create_type === 'requests') {
                $offsetTimeArray[0] = date('Y-m-d H:i:s');
                $map = $mapBase;
                $map[] = ['comefrom', '=', 2];
                $map[] = ['status', '=', 0];
                $map[] = ['trip_id', '=', 0];
                $map[] = ['time', 'between', $offsetTimeArray];
            } elseif ($create_type === 'cars') {
                $map = $mapBase;
                $map[] = ['comefrom', '=', 1];
                $map[] = ['status', 'between', [0,1]];
                $map[] = ['time', 'between', $offsetTimeArray];
            }
            $res = $this->where($map)->count();
            $redis->cache($cacheKey, $res, 10);
        }
        return $res;
    }


    /**
     * 取得接口列表需要的字段
     *
     * @param string $alias 创建sql时，表的别名
     * @return string
     */
    public function getListField($alias = '')
    {
        $fieldArray = ['id', 'comefrom', 'user_type','trip_id', 'line_id', 'plate', 'status', 'time', 'create_time', 'seat_count'];
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
    public function getListByTimeOffset($time, $uid, $offsetTime = 60 * 30)
    {
        $startTime = $time - $offsetTime;
        $endTime =   $time + $offsetTime;
        $map = [
            ["status", "between", [0,1]],
            ["time", "between", [date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime)]],
            ["uid", "=", $uid],
        ];
        $res_trip = $this->where($map)->select();
        $res = [];
        if ($res_trip) {
            foreach ($res_trip as $key => $value) {
                $data = [
                    'from'=>'shuttle_trip',
                    'id'  => $value['id'],
                    'line_id'  => $value['line_id'],
                    'time'=>strtotime($value['time']),
                    'user_type' => $value['user_type'],
                    'comefrom' => $value['comefrom'],
                    'love_wall_ID' => null,
                    'info_id' => null,
                    'd_uid' => $value['user_type'] == 1 ? $value['uid'] : 0,
                    'p_uid' => $value['user_type'] == 0 ? $value['uid'] : 0,
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
        $redis = new RedisData();

        if (isset($data['create_type'])) {
            // 清除指定line_id列表缓存
            if (isset($data['line_id']) && $data['line_id'] > 0 &&  in_array($data['create_type'], ['cars', 'requests'])) {
                $list_cacheKey = $this->getListCacheKeyByLineId($data['line_id'], $data['create_type']);
                $count_cacheKey = "carpool:shuttle:trip:countByLine:{$data['create_type']}_lineId_{$data['line_id']}";
                $redis->del($list_cacheKey, $count_cacheKey);
            }

            // 清除指定trip_id的乘客列表缓存
            if (isset($data['trip_id']) && $data['trip_id'] > 0  && in_array($data['create_type'], ['hitchhiking', 'pickup'])) {
                $this->delPassengersCache($data['trip_id']);
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
        $redis = new RedisData();
        $redis->del($cacheKey);
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
        $redis = new RedisData();
        $redis->del($cacheKey);
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
        $redis = new RedisData();
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
     * @param [type] $id
     * @return void
     */
    public function countPassengers($id)
    {
        $map = [
            ['user_type', '=', Db::raw(0)],
            ['trip_id', '=', $id],
            ['status', 'between', [0,3]],
        ];
        return $this->where($map)->count();
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
     * @return void
     */
    public function cancelPassengerTrip($idOrData)
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
        if (time() <= strtotime($tripData['time']) && $tripData['comefrom'] == 3 && $tripData['trip_id'] > 0) { // 如果未过出发时间 并且是有人搭的约车需求,
            $upData['status'] = 0;
            $upData['trip_id'] = 0;
        } else { // 如果过了出发时间
            $upData['status'] = -1;
        }
        $res = $this->where($map)->update($upData);
        if ($res === false) {
            return $this->setError(-1, 'Failed', []);
        }
        if ($upData['status'] === 0) { // 如果是取消到约车需求 则刷新约车需求缓存
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
        $redis = new RedisData();
        // 查出所有乘客行程，以便作消息推送;
        Db::connect('database_carpool')->startTrans();
        try {
            // 先取消司机行程
            $this->where('id', $id)->update(['status' => -1]);
            $upData = [
                'status' => -1,
                'operate_time'=>date('Y-m-d H:i:s')
            ];
            if (time() > strtotime($tripData['time'])) { // 如果过了出发时间
                $map = [
                    ['trip_id', '=', $id],
                    ['status', '>', 0],
                    ['comefrom', 'between', [2, 3]],
                ];
                $this->where($map)->update($upData);
            } else { // 如果未过出发时间
                $map = [
                    ['trip_id', '=', $id],
                    ['status', '>', 0],
                    ['comefrom', '=', 3],
                ];
                $this->where($map)->update($upData);
                $map2 = [
                    ['trip_id', '=', $id],
                    ['status', '>', 0],
                    ['comefrom', '=', 2],
                ];
                // 取消所有从约车需求上车乘客行程(还原成约车需求)
                $this->where($map2)->update(['status' => 0, 'trip_id'=>0, 'operate_time'=>date('Y-m-d H:i:s')]);
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
        $redis = new RedisData();
        // 查出所有乘客行程，以便作消息推送;
        Db::connect('database_carpool')->startTrans();
        try {
            // 先取消司机行程
            $this->where('id', $id)->update(['status' => 3]);
            $upData = [
                'status' => 3,
                'operate_time'=>date('Y-m-d H:i:s')
            ];
            $map = [
                ['trip_id', '=', $id],
                ['status', 'between', [0, 1]],
                ['comefrom', 'between', [2, 3]],
            ];
            $this->where($map)->update($upData);
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
}
