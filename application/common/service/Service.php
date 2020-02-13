<?php

namespace app\common\service;

use my\RedisData;
use my\Utils;
use think\Db;

class Service
{

    protected static $redisObj = null;
    protected $models = null;
    public $errorCode = 0;
    public $errorMsg = '';
    public $data = [];

    protected static $instance;

    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * 取得模型
     *
     * @param string $name 模型名称
     * @param string $common 模块名
     * @return model
     */
    public function getModel($name = '', $common = 'common')
    {
        $formatName = md5($name.'|'.$common);
        if (!isset($this->models[$formatName]) || !$this->models[$formatName]) {
            $this->models[$formatName] = app()->model($name, 'model', false, $common);
        }
        return $this->models[$formatName];
    }

    public function error($code, $msg, $data = [])
    {
        $this->errorCode = $code;
        $this->errorMsg = $msg;
        $this->data = $data;
        return false;
    }

    public function setError($code, $msg, $data = [])
    {
        $this->error($code, $msg, $data);
        return false;
    }

    /**
     * 取得错误信息
     *
     * @return array {code,msg,data}
     */
    public function getError()
    {
        return [
            'code' => $this->errorCode,
            'msg' => $this->errorMsg,
            'data' => $this->data,
        ];
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
     * 格式化时间字段
     *
     * @param array $data 要处理的数据
     * @param string $dataType 处理的数据格式类型 [list or item]
     * @param mixed $fields 要处理的字段 可为string||array
     * @return array
     */
    public function formatTimeFields($data, $dataType = 'item', $fields = ['time','create_time'])
    {
        return Utils::getInstance()->formatTimeFields($data, $dataType, $fields);
    }
}
