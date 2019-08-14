<?php

namespace app\content\model;

use think\Model;

class Comment extends Model
{


    protected $table = 't_comment';
    protected $connection = 'database_carpool';
    protected $pk = 'id';
}
