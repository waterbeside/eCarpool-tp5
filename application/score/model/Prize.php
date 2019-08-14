<?php

namespace app\score\model;

use think\Model;
use app\common\model\Configs;
use my\RedisData;
use my\CurlRequest;

class Prize extends Model
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

    protected $pk = 'id';
}
