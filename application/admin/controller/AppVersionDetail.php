<?php
namespace app\admin\controller;

use app\carpool\model\UpdateVersion as VersionModel;
use app\carpool\model\VersionDetails as VersionDetailsModel;
use app\admin\controller\AdminBase;
use think\Db;

/**
 * app版本管理
 * Class AppVersion
 * @package app\admin\controller
 */
class AppVersionDetail extends AdminBase
{






    /**
     * 版本通知列表
     * @return mixed
     */
    public function index($app_id = NULL,$platform= NULL,$version_code= NULL){
      // if(!$app_id || !$platform || !$version_code){
      //   $this->error("error param");
      // }
      $map = [];
      if($app_id){
        $map[] = ['app_id','=',$app_id];
      }
      if($platform){
        $map[] = ['platform','=',$platform];
      }
      if($version_code){
        $map[] = ['version_code','=',$version_code];
      }

      $lists  = VersionDetailsModel::where($map)->order('version_detail_id DESC')->select();
// dump($lists);
      $returnData = [
        'list' => $lists,
        'app_id' => $app_id,
        'platform' => $platform,
        'version_code' => $version_code,
        "app_id_list"  => config('others.app_id_list'),
      ];
      return $this->fetch('index', $returnData);



    }

    /**
     * 添加
     * @return mixed
     */
    public function add($copy_id = 0)
    {
      if ($this->request->isPost()) {
          $data            = $this->request->post();
          if( (is_numeric($data['language_code']) && intval($data['language_code']) === 0 ) || $data['language_code'] == '-1'  ){
            $data['language_code'] = $data['language_code_input'];
          }
          $validate = new \app\carpool\validate\VersionDetails;
          if (!$validate->check($data)) {
            return $this->jsonReturn(-1,$validate->getError());
          }

          $model = new VersionDetailsModel();
          if ($model->allowField(true)->save($data)) {
              $this->log("版本详情添加成功",0);
              $this->jsonReturn(0,'添加成功');
          } else {
              $this->log("版本详情添加失败",-1);
              $this->jsonReturn(-1,'添加失败');
          }


      }else{
        $data = NULL;
        if($copy_id){
          $data = VersionDetailsModel::find($copy_id);
          return $this->fetch('add_copy',['data'=>$data]);
        }else{
          $data = [
            'platform' => $this->request->param('platform'),
            'app_id' => $this->request->param('app_id'),
          ];
          return $this->fetch('add',['data'=>$data]);
        }

      }
    }



    /**
     * 编辑
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          if( (is_numeric($data['language_code']) && intval($data['language_code']) === 0 ) || $data['language_code'] == '-1'  ){
            $data['language_code'] = $data['language_code_input'];
          }
          $validate = new \app\carpool\validate\VersionDetails;
          if (!$validate->scene('edit')->check($data)) {
            return $this->jsonReturn(-1,$validate->getError());
          }

          $model = new VersionDetailsModel();
          $data['version_detail_id'] = $id;
          if ($model->allowField(true)->save($data, $id) !== false) {
            $this->log("版本详情更新成功; id=$id",0);
              $this->jsonReturn(0,'更新成功');
          } else {
            $this->log("版本详情更新失败; id=$id",-1);
              $this->jsonReturn(-1,'更新失败');
          }

      }else{
        $data = VersionDetailsModel::find($id);
        return $this->fetch('edit',['data'=>$data]);
      }

    }



    /**
     * 删除友情链接
     * @param $id
     */
    public function delete($id)
    {
      if(!$id){
        $this->error("lost id");
      }
        if (VersionDetailsModel::destroy($id)) {
            $this->jsonReturn(0,'删除成功');
        } else {
            $this->jsonReturn(-1,'删除失败');
        }
    }
}
