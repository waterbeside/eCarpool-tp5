<?php

namespace app\content\model;

use app\common\model\BaseModel;
use think\Db;

class Like extends BaseModel
{
    protected $table = 't_like';
    protected $connection = 'database_carpool';
    protected $pk = 'id';

    /**
     * 取得用户是否点赞cacheKey;
     *
     * @param integer $type 点赞对象类型
     * @return string
     */
    public function getIsLikeCacheKey($type)
    {
        // 此key用hset
        return "carpool:web:content:like:$type";
    }


    /**
     * 是否已点赞
     *
     * @param integer $oid 被点赞对象id
     * @param integer $uid 用户id
     * @param integer $type 点赞对象类型
     * @param boolean $unGetCache 是否取自缓存
     * @return boolean,integer
     */
    public function isLiked($oid, $uid, $type = 100, $unGetCache = false)
    {
        $cacheKey = $this->getIsLikeCacheKey($type);
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
        $isLiked = $this->where($map)->find();
        if ($isLiked) { // 如果已点赞
            $Redis->hCache($cacheKey, $rowKey, $isLiked['id'], 60 * 60 * 24);
            return $isLiked['id'];
        }
        $Redis->hCache($cacheKey, $rowKey, -1, 60 * 3);
        return false;
    }

    /**
     * 删除是否已点赞的查询缓存
     *
     * @param integer $oid 被点赞对象id
     * @param integer $uid 用户id
     * @param integer $type 点赞对象类型
     * @return boolean
     */
    public function delIsLikeCache($oid, $uid, $type = 100)
    {
        $cacheKey = $this->getIsLikeCacheKey($type);
        $rowKey = "{$oid}_{$uid}";
        $Redis = $this->redis();
        return $Redis->hCacheDel($cacheKey, $rowKey);
    }


    /**
     * 点赞
     *
     * @param integer $oid 被点赞对象id
     * @param integer $uid 用户id
     * @param integer $type 点赞对象类型
     * @param boolean $isCheck 是否验证被收藏过
     * @return boolean
     */
    public function like($oid, $uid, $type = 100, $isCheck = true)
    {
        if ($isCheck) {
            $isLiked = $this->isLiked($oid, $uid, $type);
            if ($isLiked) { // 如果已点赞
                return $this->setError(0, '你已经点赞过');
            }
        }
        $upData = [
            'type' => $type,
            'user_id' => $uid,
            'object_id' => $oid,
            'is_delete' => 0,
            'create_time'=>Date('Y-m-d H:i:s'),
        ];
        $insertId = $this->insertGetId($upData);
        $this->delIsLikeCache($oid, $uid, $type);
        return $insertId;
    }

    /**
     * 取消点赞
     *
     * @param integer $oid 被点赞对象id
     * @param integer $uid 用户id
     * @param integer $type 收藏对象类型
     * @param boolean $isCheck 是否验证被收藏过
     * @return boolean
     */
    public function unLike($oid, $uid, $type = 100, $isCheck = true)
    {
        if ($isCheck) {
            $isCollected = $this->isCollected($oid, $uid, $type, true);
            if (!$isCollected) { // 如果已收藏
                return $this->setError(0, '你没有点赞过');
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
