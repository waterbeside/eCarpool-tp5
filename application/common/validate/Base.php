<?php
namespace app\common\validate;

use think\Validate;

class Base extends Validate
{
    /**
     * 设置error
     *
     * @param integer $code errorCode
     * @param string $msg 消息
     * @param array $data 数据
     * @return false
     */
    protected function setError($code, $msg, $data = [])
    {
        $this->error = [
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ];
        return false;
    }
}
