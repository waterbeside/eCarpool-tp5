<?php
namespace my;
use app\common\model\Configs;
use \Redis;

/**
* Redis数据
***/

class RedisData extends Redis {

  protected $redisConfig = null;

  public function __construct()
  {
      $ConfigsModel = new Configs();
      $configs = $ConfigsModel->getConfigs();
      $this->connect($configs['redis_host'], $configs['redis_port']);
      // $this->connect("127.0.0.1", $configs['redis_port']);
      if($configs['redis_auth']){
        $this->auth($configs['redis_auth']);
      }
      $this->redisConfig = $configs;
  }



}

?>
