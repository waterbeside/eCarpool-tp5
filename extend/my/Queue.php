<?php

namespace my;

use my\RedisData;

/**
 * Redis数据
 ***/
class Queue
{

    public $redis = null;
    public $queueKey = null;
    public $keyPrefix = 'queue:';

    protected $setting = [
        'db' => 4
    ];

    public function __construct($key, $setting = [])
    {
        $setting = array_merge($this->setting, $setting);
        $keyPrefix = isset($setting['keyPrefix']) ? $setting['keyPrefix'] : $this->keyPrefix;
        $this->queueKey = $keyPrefix.$key;
        $this->redis = RedisData::getInstance('queue');
        $this->redis->select($setting['db']);
    }

    public static function key($key, $setting = [])
    {
        return new Queue($key, $setting);
    }

    /**
     * 队列个数
     *
     * @param string $key 队列的key
     * @return integer
     */
    public function count($key = null)
    {
        $key = $key ? $key : $this->queueKey;
        return $this->redis->lLen($key);
    }

    /**
     * 加入队列
     *
     * @param string|array $val 值
     * @param integer $type 1 为右插，0为左插
     * @return void
     */
    public function push($val, $type = 1)
    {
        $key = $this->queueKey;
        $value = $this->redis->formatValue($val);
        return $type > 0 ? $this->redis->rPush($key, $value) : $this->redis->lPush($key, $value);
    }


    /**
     * 批量插入到队列
     *
     * @param array $list
     * @param integer $type 1 为右插，0为左插
     * @return void
     */
    public function pushAll($list, $extra = [], $type = 1)
    {
        if (!is_array($list)) {
            return false;
        }
        if (is_numeric($extra)) {
            $type = $extra;
        }
        foreach ($list as $k => $value) {
            if (!empty($extra) && is_array($extra)) {
                $value = array_merge($value, $extra);
            }
            $res = $this->push($value, $type);
        }
        return true;
    }

    /**
     * 出列
     *
     */
    public function pop()
    {
        $key = $this->queueKey;
        $val = $this->redis->lPop($key);
        $value = $this->redis->formatRes($val);
        return $value;
    }

    /**
     * 批量出列
     *
     * @param string $key 队列的key
     */
    public function pops($len = 0)
    {
        $list = [];
        $total = $this->count();
        $len = $total < $len ? $total : $len;
        for ($i = 0; $i < $len; $i++) {
            $list[] = $this->pop();
        }
        return $list;
    }

    /**
     * 队列查询
     */
    public function list($pagesize = 50, $page = 1)
    {
        $key = $this->queueKey;
        $list = [];
        $s = $pagesize > 0 ? ($page - 1) * $pagesize : 0;
        $e = $pagesize > 0 ? $page * $pagesize - 1 : -1;
        $res = $this->redis->lRange($key, $s, $e);
        foreach ($res as $k => $v) {
            $value = json_decode($v, true);
            $list[] = $value;
        }
        return $list;
    }

    /**
     * 队列查询
     */
    public function delete()
    {
        $key = $this->queueKey;
        $this->redis->del($key);
    }
}
