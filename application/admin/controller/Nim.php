<?php
namespace app\admin\controller;

use app\carpool\model\User as UserModel;

use app\common\controller\AdminBase;
use think\facade\Config;
use think\facade\Validate;
use think\Db;
use extend\NIM;

/**
 * 云信管理
 * Class AdminUser
 * @package app\admin\controller
 */
class Nim extends AdminBase
{
    protected $NIM;

    protected function initialize()
    {
        parent::initialize();
        $appKey = config('nim')['appKey'];
        $appSecret = config('nim')['appSecret'];
        $this->NIM = new Nim($appKey,$appSecret);
    }

    /**
     * @return mixed
     */
    public function index()
    {

    }

    /**
     * 创建云信帐号
     * @return mixed
     */
    public function add()
    {

    }


    /**
     * 编辑
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {

    }







}
