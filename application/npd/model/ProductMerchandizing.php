<?php

namespace app\npd\model;

use think\Db;
use think\Model;
use my\RedisData;

class ProductMerchandizing extends Model
{

    protected $connection = 'database_npd';
    protected $table = 't_product_merchandizing';
}
