<?php

namespace app\content\model;

use app\common\model\BaseModel;
use think\Db;

class Colletion extends BaseModel
{


    protected $table = 't_colletion';
    protected $connection = 'database_carpool';
    protected $pk = 'id';

    /**
     * 取得用户是否收藏cacheKey;
     *
     * @param integer $type 收藏对象类型
     * @return string
     */
    public function getIsCollectCacheKey($type)
    {
        // 此key用hset
        return "carpool:web:content:isCollect:$type";
    }


    /**
     * 是否已收藏
     *
     * @param integer $oid 被收藏对象id
     * @param integer $uid 用户id
     * @param integer $type 收藏对象类型
     * @param boolean $unGetCache 是否取自缓存
     * @return boolean,integer
     */
    public function isCollected($oid, $uid, $type = 100, $unGetCache = false)
    {
        $cacheKey = $this->getIsCollectCacheKey($type);
        $rowKey = "{$oid}_{$uid}";
        $Redis = $this->redis();
        if (!$unGetCache) {
            $res = $Redis->hCache($cacheKey, $rowKey);
            if ($res > 0) {
                return $res;
            } elseif ($res == -1) {
                return false;
            }
        }

        $map = [
            ['type', '=', $type],
            ['user_id', '=', $uid],
            ['object_id', '=', $oid],
            ['is_delete', '=', Db::raw(0)],
        ];
        $isCollected= $this->where($map)->find();

        if ($isCollected) { // 如果已收藏
            $Redis->hCache($cacheKey, $rowKey, $isCollected['id'], 60 * 60 * 24);
            return $isCollected['id'];
        }
        $Redis->hCache($cacheKey, $rowKey, -1, 60 * 3);
        return false;
    }

    /**
     * 删除是否已收藏的查询缓存
     *
     * @param integer $oid 被收藏对象id
     * @param integer $uid 用户id
     * @param integer $type 收藏对象类型
     * @return boolean
     */
    public function delIsCollectCache($oid, $uid, $type = 100)
    {
        $cacheKey = $this->getIsCollectCacheKey($type);
        $rowKey = "{$oid}_{$uid}";
        $Redis = $this->redis();
        return $Redis->hCacheDel($cacheKey, $rowKey);
    }


    /**
     * 收藏
     *
     * @param integer $oid 被收藏对象id
     * @param integer $uid 用户id
     * @param integer $type 收藏对象类型
     * @param boolean $isCheck 是否验证被收藏过
     * @return boolean
     */
    public function collect($oid, $uid, $type = 100, $isCheck = true)
    {
        if ($isCheck) {
            $isCollected = $this->isCollected($oid, $uid, $type, true);
            if ($isCollected) { // 如果已收藏
                return $this->setError(0, '你已经收藏过');
            }
        }
        $upData = [
            'type' => $type,
            'user_id' => $uid,
            'object_id' => $oid,
            'is_delete' => 0,
        ];
        $insertId = $this->insertGetId($upData);
        $this->delIsCollectCache($oid, $uid, $type);
        return $insertId;
    }

    /**
     * 取消收藏
     *
     * @param integer $oid 被收藏对象id
     * @param integer $uid 用户id
     * @param integer $type 收藏对象类型
     * @param boolean $isCheck 是否验证被收藏过
     * @return boolean
     */
    public function unCollect($oid, $uid, $type = 100, $isCheck = true)
    {
        if ($isCheck) {
            $isCollected = $this->isCollected($oid, $uid, $type, true);
            if (!$isCollected) { // 如果已收藏
                return $this->setError(0, '你没有收藏过');
            }
        }
        $map = [
            ['type', '=', $type],
            ['user_id', '=', $uid],
            ['object_id', '=', $oid],
            ['is_delete', '=', Db::raw(0)],
        ];
        $results = $this->where($map)->update([ 'is_delete' => 1 ]);
        $this->delIsCollectCache($oid, $uid, $type);
        return $results;
    }
}
