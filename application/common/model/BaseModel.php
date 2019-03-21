<?php
namespace app\common\model;

use think\Model;
use my\RedisData;
use think\exception\HttpResponseException;

class BaseModel extends Model
{
  protected $redisObj = null;
  public $errorMsg = null;
  public $errorCode = 0;

  /**
   * 创建redis对像
   * @return redis
   */
  public function redis(){
    if(!$this->redisObj){
        $this->redisObj = new RedisData();
    }
    return $this->redisObj;
  }


  /**
   * 处理cache
   */
  public function itemCache($cacheKey,$value = false,$ex = 3600*24){
    $redis = $this->redis();
    if($value === null){
      return $redis->delete($cacheKey);
    }else if($value){
      if(is_array($value)){
        $value = json_encode($value);
      }
      if($ex > 0){
        return $redis->setex($cacheKey,$ex,$value);
      }else{
        return $redis->set($cacheKey,$value);
      }
    }else{
      $str =  $redis->get($cacheKey);
      $redData = $str ? json_decode($str,true) : false;
      return $redData;
    }
  }

  /**
   * 请求数据
   * @param  string $url  请求地址
   * @param  array  $data 请求参数
   * @param  string $type 请求类型
   */
  public function clientRequest($url,$data=[],$type='POST',$dataType="json"){
    try {
      $client = new \GuzzleHttp\Client(['verify'=>false]);

      $params = $data;
      $response = $client->request($type, $url, $params);

      $contents = $response->getBody()->getContents();
      if(mb_strtolower($dataType)=="json"){
        $contents = json_decode($contents,true);
      }
      return $contents;
    } catch (\GuzzleHttp\Exception\RequestException $exception) {
      if ($exception->hasResponse()) {
        $responseBody = $exception->getResponse()->getBody()->getContents();
      }
      $this->errorMsg = $exception->getMessage() ? $exception->getMessage()  :(isset($responseBody) ? $responseBody : '')  ;
      return false;
    }

  }

}
