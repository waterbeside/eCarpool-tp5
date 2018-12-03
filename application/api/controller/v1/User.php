<?php
namespace app\api\controller\v1;

use app\api\controller\ApiBase;
use app\carpool\model\UserPosition;
use app\carpool\model\UserModel;

use think\Db;

/**
 * 用户相关接口
 * Class Docs
 * @package app\api\controller
 */
class User extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }



}
