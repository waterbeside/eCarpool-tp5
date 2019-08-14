<?php

namespace app\content\model;

use think\Model;

class Ads extends Model
{
    protected $connection = 'database_carpool';
    protected $table = 't_ads';
    protected $pk = 'id';
}
