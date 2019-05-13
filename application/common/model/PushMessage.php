<?php
namespace app\common\model;

use think\Db;
use think\Model;
use com\getui_sdk\IGt;
use app\carpool\model\User;
use think\facade\Env;
// require_once(Env::get('root_path') . 'extend/org/getui_sdk/IGt.php');


class PushMessage extends Model
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
  public function  add($uid,$msg="",$title="",$module_id= 101,$type=101,$is_notify=0){
    $default_data = [
      'message_time' => date("Y-m-d H:i:s"),
      'module_id' => $module_id,
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
        'module_id' => $module_id,
        'message_type' => $type,
      ];
    }
    $data = array_merge($default_data,$data);
    return $this->insertGetId($data);

  }



  public function push($uid,$msg="",$title="",$app_id){
    $getuiSetting = config('secret.getui')["$app_id"];
    if(is_array($uid)){
      $cid = User::where("uid","in",$uid)->column('client_id');
    }else{
      $cid = User::where("uid",$uid)->value('client_id');
    }

    if(!$cid){
      return false;
    }
    $tplSetting = [
      'title' => $title,
      'text' => $msg,
      'content' => $msg,
    ];
    $IGT = new IGt($getuiSetting['appKey'],$getuiSetting['masterSecret'],$getuiSetting['appID']);
    if(is_array($cid)){
      $cids = [];
      foreach ($cid as $key => $value) {
        if($value){
          $cids[] = $value;
        }
      }
      return $IGT->pushMessageToList($tplSetting,$cid);
    }else{
      return $IGT->pushMessageToSingle($tplSetting,$cid);
    }


  }



}
