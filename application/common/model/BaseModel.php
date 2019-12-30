<?php

namespace app\common\model;

use think\Model;
use my\RedisData;

class BaseModel extends Model
{
    protected static $redisObj = null;
    public $errorMsg = null;
    public $errorCode = 0;
    public $errorData = null;
    public $itemCacheExpire = 5 * 60;

    /**
     * 设置error
     *
     * @param integer $code errorCode
     * @param string $msg 消息
     * @param array $data 数据
     * @return false
     */
    public function setError($code, $msg, $data = [])
    {
        $this->errorCode = $code;
        $this->errorMsg = $msg;
        $this->errorData = $data;
        $this->error = [
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ];
        return false;
    }

    /**
     * 创建redis对像
     * @return redis
     */
    public function redis()
    {
        if (is_null(static::$redisObj)) {
            static::$redisObj = new RedisData();
        }
        return static::$redisObj;
    }

    /**
     * 请求数据
     * @param  string $url  请求地址
     * @param  array  $data 请求参数
     * @param  string $type 请求类型
     */
    public function clientRequest($url, $data = [], $type = 'POST', $dataType = "json")
    {
        try {
            $client = new \GuzzleHttp\Client(['verify' => false]);
            $params = $data;
            $response = $client->request($type, $url, $params);

            $contents = $response->getBody()->getContents();
            if (mb_strtolower($dataType) == "json") {
                $contents = json_decode($contents, true);
            }
            return $contents;
        } catch (\GuzzleHttp\Exception\RequestException $exception) {
            if ($exception->hasResponse()) {
                $responseBody = $exception->getResponse()->getBody()->getContents();
            }
            $this->errorMsg = $exception->getMessage() ? $exception->getMessage()  : (isset($responseBody) ? $responseBody : '');
            return false;
        }
    }

    /**
     * 取得单项数据缓存key的默认值
     *
     * @param integer $id 表主键
     * @return string
     */
    public function getItemCacheKey($id)
    {
        return ($this->connection).':'.($this->table).':'.$id;
    }

    /**
     * 取得单行数据
     *
     * @param integer $id ID
     * @param mixed $field 选择返回的字段，默认为'*', 为* null时，返回全部字段。 当为数字时或false时，为缓存时效，即提前参数$ex
     * @param integer $ex 缓存时效
     * @param array $randomExOffset 有效期随机偏移
     * @param boolean $hSet 是否使用hSet,默认是
     * @return mixed
     */
    public function getItem($id, $field = '*', $ex = 'default', $randomExOffset = [1,2,3], $hSet = true)
    {
        if (is_numeric($field) || $field === false) {
            $ex = $field;
            $field = "*";
        }
        if ($ex == 'default' || $ex === null) {
            $ex = $this->itemCacheExpire;
        }
        $redis = self::redis();
        $res = false;
        if (is_numeric($ex)) {
            $cacheKey =  static::getItemCacheKey($id);
            $cacheFeild = 'item';
            $res = $hSet ? $redis->hCache($cacheKey, $cacheFeild) : $redis->cache($cacheKey);
        }

        if (!$res || $ex === false) {
            $res = self::find($id);
            $res = $res ? $res->toArray() : [];
            if (is_numeric($ex)) {
                $randomExOffset = is_array($randomExOffset) ? $randomExOffset : [1,2];
                $exp_offset = getRandValFromArray($randomExOffset);
                $ex +=  $exp_offset * ($ex > 60 ? 60 : ($ex > 10 ? 10 : 1));
                if ($hSet) {
                    $redis->hCache($cacheKey, $cacheFeild, $res, $ex);
                } else {
                    $redis->cache($cacheKey, $res, $ex);
                }
            }
        }
        $returnData = [];
        if ($field != '*' && $field != null) {
            $fields = is_array($field) ? $field : array_map('trim', explode(',', $field));
            foreach ($fields as $key => $value) {
                $returnData[$value] = isset($res[$value]) ? $res[$value] : null;
            }
        } else {
            $returnData =  $res;
        }
        return $returnData;
    }
    
    /**
     * 删除单项数据缓存
     *
     * @param integer $id 主键
     * @return void
     */
    public function delItemCache($id)
    {
        $cacheKey =  static::getItemCacheKey($id);
        $redis = self::redis();
        return $redis->del($cacheKey);
    }
}
