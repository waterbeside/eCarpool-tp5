<?php
namespace app\admin\controller;

use think\facade\Env;
use app\common\controller\AdminBase;
use think\facade\Cache;
use think\Db;
use my\RedisData;

/**
 * 系统配置
 * Class System
 * @package app\admin\controller
 */
class Configs extends AdminBase
{
    public function initialize()
    {
        parent::initialize();
    }

    public function ios_audit_switch(){
      $redis = new RedisData();
      $data = json_decode($redis->get("AUDIT_SETTING"),true);
      if ($this->request->isPost()){
        $val   = $this->request->post('val');
        if(!$val){
          $this->error("不能为空");
        }
        $redis->set('AUDIT_SETTING', $val);
        $this->success("修改成功");
      }else{
        return $this->fetch('ios_audit_switch',['data'=>$data]);
      }
    }


}
