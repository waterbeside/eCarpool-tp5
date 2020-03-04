<?php

namespace app\common\model;

use app\common\model\BaseModel;
use my\RedisData;

class Apps extends BaseModel
{

    /**
     * 取得单项数据缓存key的默认值
     *
     * @param integer $id 表主键
     * @return string
     */
    public function getItemCacheKey($id)
    {
        return "carpool_management:apps:{$id}";
    }

    /**
     * get list
     */
    public function getList()
    {
        $cacheKey = "carpool_management:apps";
        $redis = $this->redis();
        $lists =  json_decode($redis->get($cacheKey), true);
        if (!$lists) {
            $lists = $this->order('sort DESC')->select();
        }
        return $lists;
    }
}
