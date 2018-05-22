<?php
namespace app\carpool\model;

use think\Model;

class WaitingProcess extends Model
{
    // protected $insert = ['create_time'];

    /**
     * 创建时间
     * @return bool|string
     */
    

    // 直接使用配置参数名
   protected $connection = 'database_carpool';

   protected $pk = 'wpid';

}
