<?php
namespace app\npd\model;

use think\Db;
use think\Model;

class Single extends Model
{
    protected $connection = 'database_npd';
    protected $table = 't_single';
    protected $pk = 'id';
  
    protected $insert = ['create_time'];

    /**
     * 自动生成时间
     * @return bool|string
     */
    protected function setCreateTimeAttr()
    {
        return date('Y-m-d H:i:s');
    }


}
