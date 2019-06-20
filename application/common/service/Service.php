<?php
namespace app\common\service;

use think\Db;

class service
{

  public $errorCode = 0;
  public $errorMsg = '';
  public $data = [];

  public function error($code, $msg, $data = [])
  {
    $this->errorCode = $code;
    $this->errorMsg = $msg;
    $this->data = $data;
    return false;
  }

}
