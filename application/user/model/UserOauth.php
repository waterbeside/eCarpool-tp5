<?php

namespace app\user\model;

use think\Model;

class UserOauth extends Model
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 't_user_oauth';


    // 直接使用配置参数名
    protected $connection = 'database_carpool';
    protected $pk = 'id';
}
