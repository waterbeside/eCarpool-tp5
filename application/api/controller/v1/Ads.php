<?php
namespace app\api\controller\v1;

use app\api\controller\ApiBase;
use app\content\model\Ads as AdsModel;
use my\RedisData;


use think\Db;

/**
 * banners相关
 * Class Banners
 * @package app\api\controller
 */
class Ads extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }


    public function index($type=1,$app_id = 0,$platform = 0,$ver=0){
      if(!$type || !$app_id || !$platform){
        return $this->jsonReturn(992,[],lang('Parameter error'));
      }

      $keyOfDataVersion  = "carpool:ads:version:".$app_id."_".$type;
      $redis = new RedisData();
      $lastVeison = $redis->get($keyOfDataVersion);

      if($ver > 0 && $lastVeison && $ver > intval($lastVeison)){
        return $this->jsonReturn(20008,[],'No new data');
      }


      $map  = [];
      $map[] = ['status','=',1];
      $map[]  = ['is_delete',"=",0];
      $map[] = ['type','=',$type];

      $whereExp = '';
      $whereExp .= $app_id ." in(app_ids)";
      $whereExp .= "And ".$platform ." in(platforms)";

      $res  = AdsModel::where($map)->where($whereExp)->json(['images'])->order(['sort' => 'DESC', 'id' => 'DESC'])->select();
      if(!$res){
        return $this->jsonReturn(20002,[],lang('No data'));
      }
      foreach ($res as $key => $value) {
        $res[$key] = [
          "id" => $value["id"],
          "title" => $value["title"],
          "images" => $value["images"],
          "link_type" => $value["link_type"],
          "link" => $value["link"],
          "create_time" => $value["create_time"],
          "type" => $value["type"],
        ];
      }
      $returnData = [
        'lists'=>$res,
        'data_version'=> intval($lastVeison),
      ];
      return $this->jsonReturn(0,$returnData,'success');


    }






}
