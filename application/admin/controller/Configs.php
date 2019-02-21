<?php
namespace app\admin\controller;

use think\facade\Env;
use app\admin\controller\AdminBase;
use think\facade\Cache;
use think\Db;
use my\RedisData;
use app\common\model\Apps as AppsModel;

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
      $appid =  $this->request->param('app_id',1);
      $redis = new RedisData();
      $cacheKey = "AUDIT_SETTING:".$appid;
      $data = json_decode($redis->get($cacheKey),true);
      if ($this->request->isPost()){
        $val   = $this->request->post('val');
        if(!$val){
          $this->error("不能为空");
        }
        $redis->set($cacheKey, $val);
        $this->success("修改成功");
      }else{
        $apps = (new AppsModel())->getList();
        $returnData = [
          'data'=>$data,
          'apps'=>$apps,
          'app_id'=>$appid,
        ];
        return $this->fetch('ios_audit_switch',$returnData);
      }
    }


}
