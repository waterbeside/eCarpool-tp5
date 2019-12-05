<?php

namespace app\carpool\model;

use think\facade\Cache;
use my\RedisData;
use app\common\model\BaseModel;

class ShuttleLine extends BaseModel
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 't_shuttle_line';

    // 直接使用配置参数名
    protected $connection = 'database_carpool';

    protected $pk = 'id';

    protected $insert = [];
    protected $update = [];
}
