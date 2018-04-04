<?php
namespace app\carpool\model;

use think\Model;

class Company extends Model
{

  // 设置当前模型对应的完整数据表名称
  protected $table = 'company';

    // 直接使用配置参数名
   protected $connection = 'database_carpool';

   protected $pk = 'company_id';

   protected $insert = ['create_date','status' => 1,'is_sync_photo'=>0];
   protected $update = [];

    protected function setCreateDateAttr()
    {
        return date('Y-m-d');
    }

}
