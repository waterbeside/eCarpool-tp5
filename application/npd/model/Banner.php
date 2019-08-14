<?php

namespace app\npd\model;

use think\Model;

class Banner extends Model
{
    protected $connection = 'database_npd';
    protected $table = 't_banner';
    protected $pk = 'id';
}
