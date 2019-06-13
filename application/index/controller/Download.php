<?php
namespace app\index\controller;

use app\common\controller\HomeBase;
use app\carpool\model\UpdateVersion as VersionModel;

class Download extends HomeBase
{
    public function carpool($platform = 2, $type=1)
    {
      if(!$platform){
        $this->error('Param platform Error');
      }
      $platform_list = config('others.platform_list');
      $platform_str = isset($platform_list[$platform]) ? $platform_list[$platform] : '';

      $map[] = ['app_id','=',1];
      $map[] = ['platform','=',$platform_str];
      $versionData  = VersionModel::where($map)->order('update_version_id DESC')->find();
      if(!$versionData){
        $this->error('No Data');
      }
      $urlField = $type == 2 ? 'url2' : 'url';
      if(!$versionData[$urlField]){
        $this->error('No URL');
      }
      // exit;

      $this->redirect($versionData[$urlField],302);
    }
}
