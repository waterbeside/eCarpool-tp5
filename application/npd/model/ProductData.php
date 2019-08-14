<?php

namespace app\npd\model;

use think\Db;
use think\Model;
use my\RedisData;

class ProductData extends Model
{
    protected $connection = 'database_npd';
    protected $table = 't_product_data';
}
