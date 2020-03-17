<?php

namespace app\common\model;

use think\Model;
use think\Db;
use my\RedisData;

class BaseModel extends Model
{
    protected static $redisObj = null;
    public $errorMsg = null;
    public $errorCode = 0;
    public $errorData = null;
    public $itemCacheExpire = 5 * 60;
    public $itemFieldsMap = [];

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
     * 把temFieldsMap数组数据格式化成字符串
     *
     * @param array $data
     * @return string
     */
    public function itemFieldsMap2Str($data)
    {
        if (empty($data)) {
            return '';
        }
        $str = '';
        foreach ($data as $key => $value) {
            $sp = !empty($str) ? ',' : '';
            $as = empty(trim($value)) ? '' : "AS $value";
            $str .= "$sp $key $as";
        }
        return $str;
    }

    /**
     * 取得单行数据
     *
     * @param integer $id ID
     * @param mixed $field 选择返回的字段，默认为'*', 为* null时，返回全部字段。 当为数字时或false时，为缓存时效，即提前参数$ex, 当===true时，为重建缓存
     * @param integer $ex 缓存时效
     * @param array $randomExOffset 有效期随机偏移
     * @param boolean $hSet 是否使用hSet,默认是hSet
     * @return mixed
     */
    public function getItem($id, $field = '*', $ex = 'default', $randomExOffset = [1,2,3], $hSet = true)
    {
        if (is_numeric($field) || $field === false) {
            $ex = $field;
            $field = "*";
        }
        $reCache = false;
        if ($field === true) {
            $reCache = true;
            $field = '*';
        }
        if ($ex == 'default' || $ex === null) {
            $ex = $this->itemCacheExpire;
        }
        $exType = 0;
        if (is_array($ex)) {
            $exType = $ex[1] ?? $exType;
            $ex = $ex[0];
        }
        $redis = self::redis();
        $res = false;
        if (is_numeric($ex)) {
            $cacheKey =  static::getItemCacheKey($id);
            $cacheFeild = 'item';
            $res = $hSet ? $redis->hCache($cacheKey, $cacheFeild) : $redis->cache($cacheKey);
        }

        if (!$res || $ex === false || $reCache) {
            $queryFields = $this->itemFieldsMap2Str($this->itemFieldsMap);
            $queryFields = !empty($queryFields) ? '*,'.$queryFields : '';
            $res = empty($queryFields) ? self::find($id) : self::field($queryFields)->find($id); // 查询
            $res = $res ? $res->toArray() : [];
            if (is_numeric($ex) && $ex > 0) {
                $randomExOffset = is_array($randomExOffset) ? $randomExOffset : [1,2];
                $exp_offset = getRandValFromArray($randomExOffset);
                $ex +=  $exp_offset * ($ex > 60 ? 60 : ($ex > 10 ? 10 : 1));
                $ex = empty($res) ? ($ex > 60 * 5 ? round($ex/60) : 5) : $ex;
                if ($hSet) {
                    $redis->hCache($cacheKey, $cacheFeild, $res, $ex, $exType);
                } else {
                    $redis->cache($cacheKey, $res, $ex);
                }
            }
        }
        $returnData = [];
        if ($field != '*' && $field != null && !empty($res)) {
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
     * 改变status
     *
     * @return void
     */
    public function changeStatus($id, $status, $fieldName = null)
    {
        $fieldName = $fieldName ?: 'id';
        $update = [
            'status' => $status,
        ];
        return $this->where([[$fieldName, '=', $id]])->update($update);
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

    /**
     * 给item加并发锁
     *
     * @param integer $id 数据主键
     * @param string $keyFill key补充字符串 (一般填写要对该item做什么操作)
     * @return boolean
     */
    public function lockItem($id, $keyFill = '', $ex = 10, $runCount = 50)
    {
        $redis = self::redis();
        $lockKey =  static::getItemCacheKey($id);
        if (!empty($keyFill)) {
            $lockKey = $lockKey.":".$keyFill;
        }
        return $redis->lock($lockKey, $ex, $runCount);
    }

    /**
     * 给item解锁
     *
     * @param integer $id 数据主键
     * @param string $keyFill key补充字符串
     * @return boolean
     */
    public function unlockItem($id, $keyFill = '')
    {
        $redis = self::redis();
        $lockKey =  static::getItemCacheKey($id);
        if (!empty($keyFill)) {
            $lockKey = $lockKey.":".$keyFill;
        }
        return $redis->unlock($lockKey);
    }

    /**
     * 取得给座标入库geometry类型字段时要用到的字符串值
     *
     * @param double $lng 经度
     * @param double $lat 纬度
     * @param boolean $returnDb 是否Db::raw()
     * @return mixed
     */
    public function geomfromtextPoint($lng, $lat, $returnDb = false)
    {
        $str = "ST_GEOMFROMTEXT('point(" . $lng . " " . $lat . ")')";
        return $returnDb ? Db::raw($str) : $str;
    }
}
