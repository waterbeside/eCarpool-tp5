<?php

namespace app\npd\model;

use think\Db;
use think\Model;

class ProductRecommend extends Model
{
    protected $connection = 'database_npd';
    protected $table = 't_product_recommend';
    protected $pk = 'id';
}
