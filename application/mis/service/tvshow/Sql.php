<?php
namespace app\mis\service\tvshow;

use app\common\service\Service;
use my\RedisData;

use think\Db;
use my\Utils;

class Sql extends Service
{

    /**
     * get redis key
     *
     * @param String $lastKey
     * @return String
     */
    public function getKey($lastKey)
    {
        $key = "esquel:tv:sql:$lastKey";
        return $key;
    }

    /**
     * 检查key是否存在
     *
     * @param String $lastKey
     * @return Boolean
     */
    public function checkKey($lastKey)
    {
        $key = $this->getKey($lastKey);
        return RedisData::getInstance()->exists($key);
    }

    /**
     * 取得sql语句
     *
     * @param String $lastKey
     * @return String
     */
    public function getSql($lastKey)
    {
        $key = $this->getKey($lastKey);
        $data = RedisData::getInstance()->get($key);

        $returnData = '';
        if ($data) {
            try {
                $returnData = base64_decode($data);
            } catch (\Throwable $th) {
                throw $th;
            }
        }
        return $returnData;
    }

    /**
     * 更新 sql语句;
     *
     * @param String $lastKey
     * @param String $sql sql
     * @return boolean
     */
    public function updateSql($lastKey, $sql)
    {
        $data = base64_encode($sql);
        $key = $this->getKey($lastKey);
        return RedisData::getInstance()->cache($key, "\"$data\"");
    }
}
