<?php

namespace app\carpool\model;

use my\RedisData;
use app\common\model\BaseModel;

class ShuttleTime extends BaseModel
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 't_shuttle_time';

    // 直接使用配置参数名
    protected $connection = 'database_carpool';

    protected $pk = 'id';

    protected $insert = [];
    protected $update = [];

    /**
     * 取得接口列表缓存的Key
     *
     * @param integer $type 上下班类型
     * @return string
     */
    public function getListCacheKey($type)
    {
        return "carpool:shuttle:timeList:type_{$type}";
    }


    /**
     * 清除接口列表的缓存
     *
     * @param integer $type 上下班类型
     * @return void
     */
    public function delListCache($type)
    {
        $cacheKey = $this->getListCacheKey($type);
        $redis = RedisData::getInstance();
        return $redis->del($cacheKey);
    }
}
