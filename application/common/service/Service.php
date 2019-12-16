<?php

namespace app\common\service;

use my\RedisData;

use think\Db;

class Service
{

    public $errorCode = 0;
    public $errorMsg = '';
    public $data = [];
    public $redisObj = null;

    public function error($code, $msg, $data = [])
    {
        $this->errorCode = $code;
        $this->errorMsg = $msg;
        $this->data = $data;
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

    public function redis()
    {
        if ($this->redisObj) {
            return $this->redisObj;
        }
        $this->redisObj = new RedisData();
        return $this->redisObj;
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
        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }

        if ($dataType == 'list') {
            foreach ($data as $key => $value) {
                $data[$key] = $this->formatTimeFields($value, 'item', $fields);
            }
        } else {
            foreach ($fields as $key => $value) {
                if (isset($data[$value])) {
                    $data[$value] = strtotime($data[$value]);
                }
            }
        }
        return $data;
    }
}
