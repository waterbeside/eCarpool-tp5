<?php
namespace app\score\model;

use think\Model;
use app\common\model\Configs;
use my\RedisData;
use my\CurlRequest;

class  Goods extends Model
{
    // protected $insert = ['create_time'];

    /**
     * 创建时间
     * @return bool|string
     */
    /*protected function setCreateTimeAttr()
    {
        return date('Y-m-d H:i:s');
    }*/

    // 直接使用配置参数名
    protected $connection = 'database_score';

    protected $pk = 'id';


    /**
     * 从redis取出商品详情
     * @param  Int  $id 商品id
     */
    public function getFromRedis($id){
      $redis = new RedisData();
      $good = json_decode($redis->get("GOODS_".$id),true);
      if(!$good){
        $res = $this->reBuildRedis($id);
        if(isset($res['code']) && $res['code']===0){
          $good = json_decode($redis->get("GOODS_".$id),true);
          return $good;
        }else{
          return false;
        }
      }else{
        return $good;
      }
    }

    /**
     * 刷新商品的redis缓存，依赖于python接口
     * @param  Int  $id 商品id
     */
    public function reBuildRedis($id){
      $scoreConfigs = (new Configs())->getConfigs("score");
      $url = "http://".$scoreConfigs['score_host'].":".$scoreConfigs['score_port']."/secret/refresh_goods";
      $token =  $scoreConfigs['score_token'];
      $CurlRequest = new CurlRequest();
      $res = $CurlRequest->postJsonDataFsockopen($url,["gid"=>[intval($id)],'token'=>$token]);
      return $res;

    }


}
