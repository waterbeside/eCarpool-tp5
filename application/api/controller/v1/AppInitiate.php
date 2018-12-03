<?php
namespace app\api\controller\v1;

use app\api\controller\ApiBase;
use app\content\model\CommonNotice as NoticeModel;
use app\carpool\model\UpdateVersion as VersionModel;
use app\carpool\model\VersionDetails as VersionDetailsModel;
use app\content\model\Ads as AdsModel;

use app\common\model\I18nLang as I18nLangModel;
use think\Db;

/**
 * 通知
 * Class Notice
 * @package app\api\controller
 */
class AppInitiate extends ApiBase
{

  protected function initialize()
  {
    parent::initialize();
      // $this->checkPassport(1);
  }

  /**
   * 启动时调用接口
   * @return mixed
   */
  public function index($app_id = 0,$platform = 0,$version_code = 0)
  {
    $lang = (new I18nLangModel())->formatLangCode($this->language);
    $platform_list = config('others.platform_list');


    /**
     * 通知列表
     */
    $field = 't.id,t.title,t.content,t.type,t.start_time,t.end_time,t.create_time,t.refresh_time,t.sort,t.status,t.lang';
    $map   = [];
    $map[] = ['status','=',1];
    $map[] = ['type','=',1];
    $map[] = ['lang','=',$lang];
    $map[] = ['end_time','>=',date("Y-m-d H:i:s")];
    $map[] = ['start_time','<',date("Y-m-d H:i:s")];
    $whereExp = '';
    if (isset($filter['app_id']) && $filter['app_id'] ){
      $whereExp .= $filter['app_id'] ." in(app_ids)";
    }
    if (isset($filter['platform']) && $filter['platform'] ){
      $whereExp .= $filter['platform'] ." in(platforms)";
    }
    $notices  = NoticeModel::field($field)->alias('t')->where($map)->where($whereExp)->order('t.sort DESC , t.id DESC')
    // ->fetchSql(true)
    ->select();
// dump($notices);exit;

    foreach ($notices as $key => $value) {
      $notices[$key]['token'] = md5(strtotime($value['refresh_time']));
    }

    /**
     * 启屏广告图
     */
    $map  = [];
    $map[] = ['status','=',1];
    $map[]  = ['is_delete',"=",0];
    $map[] = ['type','=',1];

    $whereExp = '';
    $whereExp .= $app_id ." in(app_ids)";
    $whereExp .= "And ".$platform ." in(platforms)";

    $adsData  = AdsModel::where($map)->where($whereExp)->json(['images'])->order(['sort' => 'DESC', 'id' => 'DESC'])->select();
    foreach ($adsData as $key => $value) {
      $adsData[$key] = [
        "id" => $value["id"],
        "title" => $value["title"],
        "images" => $value["images"],
        "link_type" => $value["link_type"],
        "link" => $value["link"],
        "create_time" => $value["create_time"],
      ];
    }


    /**
     * 检查更新
     */
     $map   = [];
     $version = intval($version_code);
     // dump($version);exit;
     $platform_str = isset($platform_list[$platform]) ? $platform_list[$platform] : '';
     // dump($platform_str);exit;

     $map[] = ['is_new','=',1];
     $map[] = ['app_id','=',$app_id];
     $map[] = ['platform','=',$platform_str];
     $versionData  = VersionModel::where($map)->order('update_version_id DESC')->find();


     $returnVersionData = [
       'forceUpdate' => 'N',
       'is_update' => 0,
       'latest_version' => $versionData['latest_version'] ? $versionData['latest_version'] : '',
       'latest_version_code' => $versionData['current_versioncode'] ? $versionData['current_versioncode'] : '',
       'desc' => ''
     ];

     if($versionData){

       $mapDetail = [
         ['app_id','=',$app_id],
         ['platform','=',$platform_str],
         ['language_code','=',$lang],
         ['version_code','=',$versionData['latest_version']],

       ];
       $versionDescription  = VersionDetailsModel::where($mapDetail)->find();
       // dump($versionDescription);exit;

       $returnVersionData['desc'] = $versionDescription['description'] ? $versionDescription['description'] : "";

       if( $versionData['min_versioncode'] < $version   && $version  < $versionData['max_versioncode']  ){
         $returnVersionData['forceUpdate'] = 'F';
         $returnVersionData['is_update'] = 2;

       }else if($versionData && $version < $versionData['current_versioncode']){
         $returnVersionData['forceUpdate'] = 'A';
         $returnVersionData['is_update'] = 1;
       }else{
         $returnVersionData['forceUpdate'] = 'N';
         $returnVersionData['is_update'] = 0;
       }
     }

     /**
      * 按ip取得城市信息
      */
      $ip = request()->ip();
      // $ip_res = $this->clientRequest("http://ip.taobao.com/service/getIpInfo.php?ip=".$ip,[],'GET');
      // $ip_data = ['ip'=>$ip];
      // if($ip_res){
      //   if($ip_res['code'] === 0){
      //     $ip_data['country'] = $ip_res['data']['country'];
      //     $ip_data['region'] = $ip_res['data']['region'];
      //     $ip_data['city'] = $ip_res['data']['city'];
      //     $ip_data['country_id'] = $ip_res['data']['country_id'];
      //   }
      // }
      $returnData = [
        'notices' => $notices,
        'ads' =>  $adsData,
        'version' => $returnVersionData,
        'ip' => $ip,
      ];
      // dump($lists);
      return $this->jsonReturn(0,$returnData,'success');
    }


}
