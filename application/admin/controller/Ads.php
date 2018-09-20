<?php
namespace app\admin\controller;

use app\content\model\Ads as AdsModel;
use app\admin\controller\AdminBase;
use think\Db;

/**
 * 广告图管理
 * Class Slide
 * @package app\admin\controller
 */
class Ads extends AdminBase
{

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * 广告图管理
     * @return mixed
     */
    public function index($filter = ['keyword'=>'','app_id'=>0,'platform'=>''], $page = 1, $pagesize = 20 )
    {


        $map  = [];
        $map[]  = ['is_delete',"=",0];
        if (isset($filter['keyword']) && $filter['keyword'] ){
          $map[] = ['title','like', "%{$filter['keyword']}%"];
        }
        if (isset($filter['type']) && is_numeric($filter['type'])){
          $map[] = ['type','=', $filter['type']];
        }
        $whereExp = '';
        if (isset($filter['app_id']) && $filter['app_id'] ){
          $whereExp .= $filter['app_id'] ." in(app_ids)";
        }
        if (isset($filter['platform']) && $filter['platform'] ){
          $whereExp .= $whereExp ? "AND  ".$filter['platform'] ." in(platforms)" : $filter['platform'] ." in(platforms)";
        }
        $lists  = AdsModel::where($map)->where($whereExp)->json(['images'])->order(['sort' => 'DESC', 'id' => 'DESC'])->paginate(20, false, ['page' => $page]);
        $typeList = config('content.common_notice_type');
        foreach ($lists as $key => $value) {
          $lists[$key]['platform_list'] =  explode(',',$value['platforms']);
          $lists[$key]['app_id_list'] =  explode(',',$value['app_ids']);
          $lists[$key]['thumb'] = is_array($value["images"]) ? $value["images"][0] : "" ;
        }

        $this->assign('typeList', $typeList);
        $this->assign('filter', $filter);
        $this->assign('lists', $lists);
        $this->assign('pagesize', $pagesize);
        $this->assign('app_id_list', config('others.app_id_list'));
        $this->assign('platform_list', config('others.platform_list'));
        return $this->fetch();

    }

    /**
     * 添加轮播图
     * @return mixed
     */
    public function add()
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          if($data['platform']){
            $data['platforms'] = '';
            foreach ($data['platform'] as $key => $value) {
              $data['platforms'] .=  $data['platforms'] ? "," : "";
              $data['platforms'] .=  $key;
            }
          }
          if($data['app_id']){
            $data['app_ids'] = '';
            foreach ($data['app_id'] as $key => $value) {
              $data['app_ids'] .=  $data['app_ids'] ? "," : "";
              $data['app_ids'] .=  $key;
            }
          }

          $validate_result = $this->validate($data, 'app\content\validate\Ads');
          if ($validate_result !== true) {
            return $this->jsonReturn(-1,$validate_result);
          }

          $upData = [
            'title' =>   iconv_substr($data['title'],0,100) ,
            'app_ids' => $data['app_ids'],
            'platforms' => $data['platforms'],
            'status' => $data['status'],
            'type' => $data['type'],
            'sort' => $data['sort'],
            'create_time' => date('Y-m-d H:i:s'),
          ];
          if($data['thumb'] && trim($data['thumb'])){
            $upData['images'][0] =  $data['thumb'];
          }

          $id = AdsModel::json(['images'])->insertGetId($upData);
          if ( $id ) {
              $this->log('保存广告图成功',0);
              $this->jsonReturn(0,'保存成功');
          } else {
              $this->log('保存广告图失败',-1);
              $this->jsonReturn(-1,'保存失败');
          }

      }else{
        $typeList = config('content.ads_type');
        $this->assign('app_id_list', config('others.app_id_list'));
        $this->assign('platform_list', config('others.platform_list'));
        $this->assign('typeList', $typeList);
        return $this->fetch();

      }

    }



    /**
     * 编辑轮播图
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          if($data['platform']){
            $data['platforms'] = '';
            foreach ($data['platform'] as $key => $value) {
              $data['platforms'] .=  $data['platforms'] ? "," : "";
              $data['platforms'] .=  $key;
            }
          }
          if($data['app_id']){
            $data['app_ids'] = '';
            foreach ($data['app_id'] as $key => $value) {
              $data['app_ids'] .=  $data['app_ids'] ? "," : "";
              $data['app_ids'] .=  $key;
            }
          }
          $validate_result = $this->validate($data, 'app\content\validate\Ads');
          if ($validate_result !== true) {
            return $this->jsonReturn(-1,$validate_result);
          }
          $upData = [
            'title' =>   iconv_substr($data['title'],0,100) ,
            'app_ids' => $data['app_ids'],
            'platforms' => $data['platforms'],
            'status' => $data['status'],
            'type' => $data['type'],
            'sort' => $data['sort'],
          ];
          if($data['thumb'] && trim($data['thumb'])){
            $upData['images'][0] =  $data['thumb'];
          }

          if (AdsModel::json(['images'])->where('id',$id)->update($upData) !== false) {
              $this->log('编辑广告图成功',0);
              $this->jsonReturn(0,'修改成功');
          } else {
              $this->log('编辑广告图失败',-1);
              $this->jsonReturn(-1,'修改失败');
          }

      }else{
        $data = AdsModel::json(['images'])->find($id);
        $typeList = config('content.common_notice_type');
        $data['thumb'] = is_array($data["images"]) ? $data["images"][0] : "" ;
        $data['platform_list'] =  explode(',',$data['platforms']);
        $data['app_id_list'] =  explode(',',$data['app_ids']);
        $this->assign('app_id_list', config('others.app_id_list'));
        $this->assign('platform_list', config('others.platform_list'));
        $this->assign('typeList', $typeList);
        return $this->fetch('edit', ['data' => $data]);
      }

    }


    /**
     * 删除轮播图
     * @param $id
     */
    public function delete($id)
    {

      if(AdsModel::where('id', $id)->update(['is_delete' => 1])){
        $this->log('删除广告图成功',0);
        $this->jsonReturn(0,'删除成功');
      }else{
        $this->log('删除广告图失败',-1);
        $this->jsonReturn(-1,'删除失败');
      }

    }
}
