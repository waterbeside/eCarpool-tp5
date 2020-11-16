<?php
namespace app\mis\model;

use app\common\model\Configs;
use think\Db;
// use think\Model;
use app\common\model\BaseModel;

class LogTempData extends BaseModel
{
    protected $connection = 'database_mis';
    protected $table = 't_log_tempData';
    protected $pk = 'id';
}
