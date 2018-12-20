<?php
namespace app\content\model;

use think\Db;
use think\Model;
use my\RedisData;

class IdleCategory extends Model
{
    protected $table = 't_idle_category';
    protected $connection = 'database_content';
    protected $pk = 'id';

    protected static function init()
    {
        parent::init();


    }








}
