<?php

namespace app\common\model;

use think\Db;
use think\Model;
use com\getui_sdk\IGt;
use app\carpool\model\User;
use think\facade\Env;

// require_once(Env::get('root_path') . 'extend/org/getui_sdk/IGt.php');


class Pushmsg extends Model
{
    // protected $table = 'pushmsg';
    public $errorMsg = null;

    /**
     * 添加消息推送
     * @param integer $uid  [description]
     * @param  $msg  [description]
     * @param [type] $type [description]
     */
    public function add($data)
    {
        $default_data = [
            'create_time' => date("Y-m-d H:i:s"),
        ];
        $data = array_merge($default_data, $data);
        return $this->insertGetId($data);
    }



    public function push($uid, $data = null, $app_id = 1)
    {

        $getuiSetting = config('secret.getui')["$app_id"];

        if (is_array($uid)) {
            $cid = User::where("uid", "in", $uid)->column('client_id');
        } else {
            $cid = User::where("uid", $uid)->value('client_id');
        }

        if (!$cid) {
            $this->errorMsg = "No client_id";
            return false;
        }

        $default_data = [
            'title' => isset($data['title']) ? $data['title'] : "Carpool",
            'content' => $data['body'],
            'text' => isset($data['text']) ? $data['text'] : $data['body'],
            'payload' => isset($data['payload']) ? $data['payload'] : $data['body'],
        ];
        $tplSetting =  array_merge($default_data, $data);

        $IGT = new IGt($getuiSetting['appKey'], $getuiSetting['masterSecret'], $getuiSetting['appID']);
        if (is_array($cid)) {
            $cids = [];
            foreach ($cid as $key => $value) {
                if ($value) {
                    $cids[] = $value;
                }
            }
            return $IGT->pushMessageToList($tplSetting, $cid);
        } else {
            return $IGT->pushMessageToSingle($tplSetting, $cid, 2);
        }
    }
}
