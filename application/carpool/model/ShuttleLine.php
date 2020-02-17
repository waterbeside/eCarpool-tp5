<?php

namespace app\carpool\model;

use think\Db;
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
        return $this->redis()->del($cacheKey);
    }

    /**
     * 取得接口的用户的常用列表缓存的Key
     *
     * @param integer $uid uid
     * @param integer $type 上下班类型
     * @return string
     */
    public function getCommonListCacheKey($uid, $type)
    {
        return "carpool:shuttle:common_lineList:{$uid},type_{$type}";
    }

    /**
     * 清除接口列表的缓存
     *
     * @param integer $type 上下班类型
     * @return void
     */
    public function delCommonListCache($uid, $type)
    {
        $cacheKey = $this->getCommonListCacheKey($uid, $type);
        return $this->redis()->del($cacheKey);
    }

    /**
     * 取得路线颜色数组缓存Key
     *
     * @return string
     */
    public function getColorsCacheKey()
    {
        return "carpool:shuttle:line:colors";
    }

    /**
     * 清除路线颜色数组缓存
     *
     * @return void
     */
    public function delColorsCache()
    {
        $cacheKey = $this->getColorsCacheKey();
        return $this->redis()->del($cacheKey);
    }

    /**
     * 取得颜色数组
     *
     * @param integer $limit 取数量
     * @param integer $ex 缓存时长
     * @return void
     */
    public function getColors($limit = 50, $ex = 60 * 60)
    {
        $cacheKey = $this->getColorsCacheKey();
        $rowKey = "limit_$limit";
        $res = $this->redis()->hCache($cacheKey, $rowKey);
        if (is_array($res) && empty($res)) {
            return $this->jsonReturn(20002, [], lang('No data'));
        }
        if (!$res || $ex === false) {
            $map = [
                ['is_delete', '=', Db::raw(0)],
                ['status', '=', Db::raw(1)],
                ['', 'exp', Db::raw('color IS NOT NULL')]
            ];
            $res = $this->where($map)->group('color')->limit($limit)->column('color');
            if (is_numeric($ex)) {
                if (!$res) {
                    $this->redis()->hCache($cacheKey, $rowKey, [], 60 * 5);
                } else {
                    $this->redis()->hCache($cacheKey, $rowKey, $res, $ex);
                }
            }
        }
        return $res;
    }

    /**
     * 随机取一个颜色
     *
     * @return string
     */
    public function getRandomColor()
    {
        $colors = $this->getColors() ?: [];
        $res = getRandValFromArray($colors, 1) ?: '';
        return !empty(trim($res)) ? trim($res) : '';
    }
}
