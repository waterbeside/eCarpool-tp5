<?php
namespace app\common\model;

use think\Db;
use think\Model;

class PullMessage extends Model
{
  protected $table = 'pull_message';
  protected $connection = 'database_carpool';
  protected $pk = 'message_id';


  /**
   * 添加消息推送
   * @param integer $uid  [description]
   * @param  $msg  [description]
   * @param [type] $type [description]
   */
  public function  add($uid,$msg="",$title="",$type=101,$is_notify=1){
    $default_data = [
      'message_time' => date("Y-m-d H:i:s"),
      'module_id' => $type,
      'message_type' => $type,
      'is_notify' => 0,
    ];
    if(is_array($msg)){
      $data = $msg;
    }else{
      $data = [
        'uid' => $uid,
        'message_title' => $title,
        'message_content' => $msg,
        'is_notify' => $is_notify,
        'module_id' => $type,
        'message_type' => $type,
      ];
    }
    $data = array_merge($default_data,$data);
    return $this->insertGetId($data);

  }

}
