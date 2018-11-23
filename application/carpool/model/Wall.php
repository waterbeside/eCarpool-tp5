<?php
namespace app\carpool\model;

use think\Model;

class Wall extends Model
{

   // 设置当前模型对应的完整数据表名称
   protected $table = 'love_wall';


   // 直接使用配置参数名
   protected $connection = 'database_carpool';

   protected $pk = 'love_wall_ID';

   protected $type = [
      'status'    =>  'integer',
   ];
   
   public $errorMsg = "";

   /**
    * 发布行程时检查行程是否有重复
    * @param  Timestamp   $time       出发时间的时间戳
    * @param  Integer     $uid        发布者ID
    * @param  String      $offsetTime 时间偏差范围
    */
   public function checkRepetition($time,$uid,$offsetTime = "150"){
     $startTime = $time - $offsetTime;
     $endTime =   $time + $offsetTime;
     $map = [
       ["status","<",2],
       ["carownid","=",$uid],
       ["time",">=",date('YmdHi',$startTime)],
       ["time","<=",date('YmdHi',$endTime)],
       // ["go_time",">=",$startTime],
       // ["go_time","<=",$endTime],
     ];
     $res = $this->where($map)->find();
     if($res){
       $resTime  = date('Y-m-d H:i',strtotime($res['time'].'00'));
       $this->errorMsg = lang("You have already made one trip at {:time}, should not be published twice within the same time",["time"=>$resTime]);
       return false;
     }else{
       return true;
     }
   }

}
