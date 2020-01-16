<?php

namespace app\carpool\model;

use think\facade\Cache;
use my\RedisData;
use app\common\model\BaseModel;

class ShuttleLineRelation extends BaseModel
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 't_shuttle_line_relation';

    // 直接使用配置参数名
    protected $connection = 'database_carpool';


    /**
     * 取得关系路线
     *
     * @param integer $line_id 路线id
     * @param integer $type 关系类型
     * @param integer $rv 是否返向取
     * @return string
     */
    public function getFriendsCacheKey($line_id, $type = 0)
    {
        return "carpool:shuttle:lineRelation:{$line_id}:friends_t{$type}";
    }

    /**
     * 清除关系路线缓存
     *
     * @param integer $line_id 路线id
     * @param integer $type 关系类型
     * @return void
     */
    public function delFriendsCache($line_id, $type = 0)
    {
        $cacheKey = $this->getFriendsCacheKey($line_id, $type);
        return $this->redis()->del($cacheKey);
    }

    /**
     * 取得关系路线数组
     *
     * @param integer $line_id 路线id
     * @param integer $type 关系类型，默认为0
     * @param integer $rv 是否返向 0否，1是。默认0
     * @param integer $ex 缓存时间
     * @return void
     */
    public function getFriendsLine($line_id, $type = 0, $rv = 0, $ex = 60 * 30)
    {
        $redis = $this->redis();
        $cacheKey = $this->getFriendsCacheKey($line_id, $type);
        $rowKey = "rv_$rv";
        $res = $redis->hCache($cacheKey, $rowKey);
        if (is_array($res) && empty($res)) {
            return $res;
        }
        if (!$res || true) {
            $map = [
                [$rv ? 'relation_id' : 'line_id', '=', $line_id],
            ];
            $res = $this->where($map)->column($rv ? 'line_id' : 'relation_id');
            if (!$res) {
                $redis->hCache($cacheKey, $rowKey, [], 60 * 5);
            } else {
                $redis->hCache($cacheKey, $rowKey, $res, $ex);
            }
        }
        return $res;
    }
}
