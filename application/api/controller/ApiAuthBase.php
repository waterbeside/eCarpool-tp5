<?php
namespace app\api\controller;

use app\api\controller\ApiBase;
use think\facade\Cache;
use think\Controller;
use think\Db;

class ApiAuthBase extends ApiBase
{


    protected function initialize()
    {
        parent::initialize();
        if(!$this->checkPassport()){
          $errorReturn = $this->passportError;
          $code = isset($errorReturn[0]) ? $errorReturn[0] : 10004 ;
          $msg = isset($errorReturn[1]) ? $errorReturn[1] : '您尚未登入' ;
          return $this->jsonReturn($code,$msg);
        }
    }






}
