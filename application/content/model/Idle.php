<?php
namespace app\content\model;

use think\Model;

class Idle extends Model
{
  protected $connection = 'database_carpool';
  protected $table = 't_idle';
  protected $pk = 'id';


}
