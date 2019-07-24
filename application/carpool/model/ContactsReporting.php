<?php
namespace app\carpool\model;

use think\facade\Cache;
use think\Model;

class ContactsReporting extends Model
{

  // 设置当前模型对应的完整数据表名称
  protected $table = 't_contacts_reporting';

    // 直接使用配置参数名
   protected $connection = 'database_carpool';

   protected $pk = 'id';


}
