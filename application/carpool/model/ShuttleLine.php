<?php

namespace app\carpool\model;

use think\facade\Cache;
use my\RedisData;
use app\common\model\BaseModel;

class ShuttleLine extends BaseModel
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 't_shuttle_line';

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
        return "carpool:shuttle:line:$id";
    }

    /**
     * 取得接口列表缓存的Key
     *
     * @param integer $type 上下班类型
     * @return string
     */
    public function getListCacheKey($type)
    {
        return "carpool:shuttle:lineList:type_{$type}";
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
        $redis = new RedisData();
        return $redis->del($cacheKey);
    }

}
