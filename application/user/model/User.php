<?php
namespace app\user\model;

use think\Model;

class User extends Model
{


  protected $table = 't_user';
  protected $connection = 'database_carpool';
  protected $pk = 'id';



}
