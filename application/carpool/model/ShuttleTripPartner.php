<?php

namespace app\carpool\model;

use think\facade\Cache;
use my\RedisData;
use my\Utils;
use app\common\model\BaseModel;

use think\Db;

class ShuttleTripPartner extends BaseModel
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 't_shuttle_trip_partner';

    // 直接使用配置参数名
    protected $connection = 'database_carpool';
    protected $pk = 'id';

    /**
     * 取得单项数据缓存Key设置
     *
     * @param integer $id 主键
     * @return string
     */
    public function getItemCacheKey($id)
    {
        return "carpool:shuttle:tripPartner:$id";
    }

    /**
     * 取得同行者列表cacheKey
     *
     * @param integer $trip_id 路线id
     * @param string $from_type 类型，0普通行程，1上下班行程
     * @return string
     */
    public function getPartnersCacheKey($trip_id, $from_type = 0)
    {
        return "carpool:shuttle:tripPartners:tripId_{$trip_id},fromType_{$from_type}";
    }

    /**
     * 取得我的常用同行者列表cacheKey
     *
     * @param integer $uid 用户uid
     * @return string
     */
    public function getCommonListCacheKey($uid)
    {
        return "carpool:shuttle:commonPartners:uid_{$uid}";
    }

    /**
     * 删除行程同行者列表缓存
     *
     * @param integer $trip_id 路线id
     * @param string $from_type 类型，0普通行程，1上下班行程
     * @return boolean
     */
    public function delPartnersCache($trip_id, $from_type = 0)
    {
        $cacheKey =  $this->getPartnersCacheKey($trip_id, $from_type);
        return $this->redis()->del($cacheKey);
    }

    /**
     * 删除常用同行者列表缓存
     *
     * @param integer $uid 用户uid
     * @return boolean
     */
    public function delCommonListCache($uid)
    {
        $cacheKey =  $this->getCommonListCacheKey($uid);
        return $this->redis()->del($cacheKey);
    }

    /**
     * 取得同行者列表
     *
     * @param integer $trip_id 路线id
     * @param integer $from_type 类型，0新普通行程，1上下班行程,  null: 上下班和新普通行程, -3旧普通行程
     * @param integer $ex 缓存有效期
     * @return array
     */
    public function getPartners($trip_id, $from_type = null, $ex = 60)
    {
        $cacheKey = $this->getPartnersCacheKey($trip_id, $from_type);
        $redis = RedisData::getInstance();
        $res = $redis->cache($cacheKey);
        if ($res === false) {
            $map = [
                ['is_delete', '=', Db::raw(0)],
                ["status", "=", Db::raw(0)],
                ['trip_id', '=', $trip_id],
            ];
            if (is_numeric($from_type)) {
                $map[] = $from_type > 0 ? ['line_type', '>', 0] : ['line_type', '=', $from_type];
            } else {
                $map[] = ['line_type', '>', -1];
            }
            $res = $this->where($map)->select();
            $res = $res ? $res->toArray() : [];
            if (is_numeric($ex)) {
                $redis->cache($cacheKey, $res, $ex);
            }
        }
        return $res;
    }

    /**
     * 取出同行者id数组
     *
     * @param mixed $tidOrPlist 行程id或同行者列表
     * @param integer $from_type 0新普通行程，1上下班行程，$tidOrPlist为列表时，此参数无效, null: 上下班和新普通行程
     * @return array
     */
    public function getPartnerUids($tidOrPlist, $from_type = null)
    {
        $partners = is_numeric($tidOrPlist) ? $this->getPartners($tidOrPlist, $from_type) : $tidOrPlist;
        $uids = [];
        foreach ($partners as $key => $value) {
            $uids[] = $value['uid'];
        }
        return $uids;
    }

    /**
     * 检查用户uid是否在同行者之内
     *
     * @param mixed $uid 要检查的用户id
     * @param mixed $tidOrPlist 行程id或同行者列表
     * @param integer $from_type 0普通行程，1上下班行程，$tidOrPlist为列表时，此参数无效
     * @return boolean 在同行者内return true 否则return false
     */
    public function inPartners($uid, $tidOrPlist, $from_type = 0)
    {
        $uids = $this->getPartnerUids($tidOrPlist, $from_type);
        return in_array($uid, $uids);
    }

    /**
     * 计算同行者数量
     *
     * @param integer $trip_id 路线id
     * @param integer $from_type 类型，0普通行程，1上下班行程
     * @return integer
     */
    public function countPartners($trip_id, $from_type)
    {
        $map = [
            ['is_delete', '=', Db::raw(0)],
            ["status", "=", Db::raw(0)],
            ['trip_id', '=', $trip_id],
        ];
        $map[] = $from_type ? ['line_type', '>', 0] : ['line_type', '=', 0];
        $res = $this->where($map)->count();
        return $res;
    }

    /**
     * 通过时间范围取得某用户参与的同行者列表
     *
     * @param  integer     $time       出发时间的时间戳
     * @param  integer     $uid        发布者ID
     * @param  string      $offsetTime 时间偏差范围
     *
     */
    public function getListByTimeOffset($time, $uid, $offsetTime = 60 * 30)
    {
        $time = is_numeric($time) ? $time : strtotime($time);
        $startTime = $time - $offsetTime;
        $endTime =   $time + $offsetTime;
        $map = [
            ["is_delete", "=", Db::raw(0)],
            ["status", "=", Db::raw(0)],
            ["time", "between", [date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime)]],
            ["uid", "=", $uid],
        ];
        $res = $this->where($map)->select();
        $res = $res ? $res->toArray() : [];
        $Utils = new Utils();
        $res = $Utils->formatTimeFields($res, 'item', ['time']);
        $res = $Utils->filterListFields($res, ['is_delete', 'update_time', 'create_time'], true);
        return $res;
    }

    /**
     * 标记同行者状态为已被搭
     *
     * @param integer $trip_id 约车需求的行程id
     * @param integer $from_type 0为某通行程，1为上下班行程
     */
    public function signGetOn($trip_id, $from_type = 0)
    {
        $map = [
            ['is_delete', '=', Db::raw(0)],
            ["status", "=", Db::raw(0)],
            ['trip_id', '=', $trip_id],
        ];
        $map[] = $from_type ? ['line_type', '>', 0] : ['line_type', '=', 0];
        return $this->where($map)->update(['status'=>1]);
    }

    /**
     * 标记同行者状态为未搭(司机取消行程后还原约车需求时要用到)
     *
     * @param integer $id 同行者行id
     */
    public function signGetOff($id)
    {
        $map = [
            ['id', '=', $id],
        ];
        return $this->where($map)->update(['status'=>0]);
    }
}
