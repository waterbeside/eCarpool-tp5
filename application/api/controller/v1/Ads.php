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

      $department_id = -1;
      $userData = $this->getUserData();
      // dump($userData);

      if($userData){
        $department_id = $userData['department_id'];
      }
      
      

      $keyOfDataVersion  = "carpool:ads:version:".$app_id."_".$type;

      $redis = new RedisData();
      $lastVeison = $redis->get($keyOfDataVersion);
      
      if($ver > 0 && $lastVeison && $ver >= intval($lastVeison)){
        return $this->jsonReturn(20008,[],'No new data');
      }


      $map  = [];
      $map[] = ['status','=',1];
      $map[]  = ['is_delete',"=",0];
      $map[] = ['type','=',$type];

      $whereExp = '';
      $whereExp .= " FIND_IN_SET($app_id,app_ids) ";
      $whereExp .= " AND FIND_IN_SET($platform,platforms) ";

      $res  = AdsModel::where($map)->where($whereExp)->json(['images'])->order(['sort' => 'DESC', 'id' => 'DESC'])->select();
      // dump($res);exit;
      if(!$res){
        return $this->jsonReturn(20002,[],lang('No data'));
      }
      $res_filt = [];
      foreach ($res as $key => $value) {
        if($this->checkDeptAuth($department_id,$value['region_id'])){
          $res_filt[] = [
            "id" => $value["id"],
            "title" => $value["title"],
            "images" => $value["images"],
            "link_type" => $value["link_type"],
            "link" => $value["link"],
            "create_time" => $value["create_time"],
            "type" => $value["type"],
          ];
        }
      }
      $returnData = [
        'lists'=>$res_filt,
        'data_version'=> intval($lastVeison),
      ];
      return $this->jsonReturn(0,$returnData,'success');


    }






}
