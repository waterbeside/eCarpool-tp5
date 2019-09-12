<?php

namespace app\npd\model;

use think\Model;

class Gallery extends Model
{
    protected $connection = 'database_npd';
    protected $table = 't_gallery';
    protected $pk = 'id';
}
