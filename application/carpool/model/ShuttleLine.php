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

    /**
     * 取得单行数据
     *
     * @param integer $id ID
     * @param * $field 选择返回的字段，当为数字时，为缓存时效
     * @param integer $ex 缓存时效
     * @return void
     */
    public function getItem($id, $field = '*', $ex = 60 * 5)
    {
        if (is_numeric($field)) {
            $ex = $field;
            $field = "*";
        }
        $cacheKey =  "carpool:shuttle:line:$id";
        $redis = new RedisData();
        $res = $redis->cache($cacheKey);
        if (!$res) {
            $res = $this->find($id)->toArray();
            $redis->cache($cacheKey, $res, $ex);
        }
        $returnData = [];
        if ($field != '*') {
            $fields = is_array($field) ? $field : explode(',', $field);
            foreach ($fields as $key => $value) {
                $returnData[$value] = isset($res[$value]) ? $res[$value] : null;
            }
        } else {
            $returnData =  $res;
        }
        return $returnData;
    }
    
    public function delItemCache($id)
    {
        $cacheKey =  "carpool:shuttle:line:$id";
        $redis = new RedisData();
        $redis->del($cacheKey);
    }
}
