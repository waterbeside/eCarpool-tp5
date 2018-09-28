<?php
namespace app\user\model;

use think\Model;

class UserTemp extends Model
{


  protected $table = 't_user_temp';
  protected $connection = 'database_carpool';
  protected $pk = 'id';



}
