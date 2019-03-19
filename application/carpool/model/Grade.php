<?php
namespace app\carpool\model;

use think\Model;


/**
 * 评分表模型
 */
class Grade extends Model
{

  // 设置当前模型对应的完整数据表名称
  protected $table = 't_grade';

  // 直接使用配置参数名
  protected $connection = 'database_carpool';

  protected $pk = 'id';


  public  function isGrade($type){
    if($type=='trips'){
      $grade_start_date = config('trips.grade_start_date');
      $grade_end_date = config('trips.grade_end_date');
      $isGrade = time() >= strtotime($grade_start_date)  && time() < strtotime($grade_end_date) ? true : false;
      return $isGrade;
    }else{
      return false;
    }
  }


}
