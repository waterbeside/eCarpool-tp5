<?php
namespace app\carpool\model;

use think\Model;

class Wall extends Model
{

  // 设置当前模型对应的完整数据表名称
     protected $table = 'love_wall';


    // 直接使用配置参数名
   protected $connection = 'database_carpool';

}
