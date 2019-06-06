<?php
namespace app\content\model;

use think\Db;
use think\Model;
use my\RedisData;

class IdleCategory extends Model
{
    protected $connection = 'database_carpool';
    protected $table = 't_idle_category';
    protected $pk = 'id';

    protected static function init()
    {
        parent::init();


    }








}
