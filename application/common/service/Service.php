<?php
namespace app\common\service;

use my\RedisData;

use think\Db;

class service
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


  public function redis()
  {
    if ($this->redisObj) {
      return $this->redisObj;
    }
    $this->redisObj = new RedisData();
    return $this->redisObj;
  }
}
