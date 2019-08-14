<?php

namespace app\score\model;

use think\Db;
use app\common\model\Configs;
use app\common\model\BaseModel;

// use think\Model;

class Account extends BaseModel
{
    // protected $insert = ['create_time'];

    /**
     * 创建时间
     * @return bool|string
     */
    /*protected function setCreateTimeAttr()
    {
        return date('Y-m-d H:i:s');
    }*/

    // 直接使用配置参数名
    protected $connection = 'database_score';
    protected $table = 't_account';
    protected $pk = 'id';
}
