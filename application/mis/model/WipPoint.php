<?php
namespace app\mis\model;

use app\common\model\Configs;
use think\Db;
use app\common\model\BaseModel;

class WipPoint extends BaseModel
{
    protected $connection = 'database_mis';
    protected $table = 't_wip_point';
    protected $pk = 'id';
}
