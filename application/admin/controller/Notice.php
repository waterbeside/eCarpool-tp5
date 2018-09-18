<?php
namespace app\admin\controller;


use app\content\model\CommonNotice as NoticeModel;
use app\admin\controller\AdminBase;
use think\Db;
use my\Tree;

/**
 * 通知管理
 * Class Label
 * @package app\admin\controller
 */
class Notice extends AdminBase
{


    /**
     * 通知管理
     * @return mixed
     */
    public function index($filter = ['keyword'=>'','app_id'=>0,'platform'=>''], $page = 1, $pagesize = 20 )
    {

        $map  = [];
        if (isset($filter['keyword']) && $filter['keyword'] ){
          $map[] = ['title','like', "%{$filter['keyword']}%"];
        }
        if (isset($filter['type']) && is_numeric($filter['type']) && $filter['type'] > 0){
          $map[] = ['type','=', $filter['type']];
        }
        $whereExp = '';
        if (isset($filter['app_id']) && $filter['app_id'] ){
          $whereExp .= $filter['app_id'] ." in(app_ids)";
        }
        if (isset($filter['platform']) && $filter['platform'] ){
          $whereExp .= $filter['platform'] ." in(platforms)";
        }
        $lists  = NoticeModel::where($map)->where($whereExp)->order(['sort' => 'DESC','create_time'=>'DESC', 'id' => 'DESC'])
        ->paginate(20, false, ['page' => $page]);
        // ->fetchSql()->select();
        // dump($lists);exit;
        foreach ($lists as $key => $value) {
          $lists[$key]['platform_list'] =  explode(',',$value['platforms']);
          $lists[$key]['app_id_list'] =  explode(',',$value['app_ids']);
        }
        // dump($lists);exit;
        $typeList = config('content.common_notice_type');
        $this->assign('typeList', $typeList);
        $this->assign('filter', $filter);
        $this->assign('lists', $lists);
        $this->assign('pagesize', $pagesize);
        $this->assign('app_id_list', config('others.app_id_list'));
        $this->assign('platform_list', config('others.platform_list'));

        return $this->fetch();
    }

    /**
     * 添加
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

          $validate_result = $this->validate($data, 'app\content\validate\CommonNotice');
          if ($validate_result !== true) {
            return $this->jsonReturn(-1,$validate_result);
          }
          $data['title'] =  iconv_substr($data['title'],0,250) ;

          $noticeModel           = new NoticeModel();
          if ($noticeModel->allowField(true)->save($data)) {
              $this->log('保存通知成功',0);
              $this->jsonReturn(0,'保存成功');
          } else {
              $this->log('保存通知失败',-1);
              $this->jsonReturn(-1,'保存失败');
          }

      }else{
        $typeList = config('content.common_notice_type');
        $this->assign('app_id_list', config('others.app_id_list'));
        $this->assign('platform_list', config('others.platform_list'));
        $this->assign('typeList', $typeList);
        return $this->fetch();

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
          $validate_result = $this->validate($data, 'app\content\validate\CommonNotice');
          if ($validate_result !== true) {
            return $this->jsonReturn(-1,$validate_result);
          }
          $data['title'] =  iconv_substr($data['title'],0,250) ;
          if(isset($data['is_refresh']) && $data['is_refresh']){
            $data['refresh_time']     = date("Y-m-d H:i:s");
          }

          $noticeModel           = new NoticeModel();
          if ($noticeModel->allowField(true)->save($data, $id) !== false) {
              $this->log('编辑通知成功',0);
              $this->jsonReturn(0,'修改成功');
          } else {
              $this->log('编辑通知失败',-1);
              $this->jsonReturn(-1,'修改失败');
          }

      }else{
        $data = NoticeModel::find($id);
        $typeList = config('content.common_notice_type');
        $data['platform_list'] =  explode(',',$data['platforms']);
        $data['app_id_list'] =  explode(',',$data['app_ids']);
        $this->assign('app_id_list', config('others.app_id_list'));
        $this->assign('platform_list', config('others.platform_list'));
        $this->assign('typeList', $typeList);
        return $this->fetch('edit', ['data' => $data]);
      }

    }



    /**
     * 删除文档
     * @param int   $id
     * @param array $ids
     */
    public function delete($id = 0, $ids = [])
    {
        $id = $ids ? $ids : $id;
        if ($id) {
            if (NoticeModel::destroy($id)) {
                $this->log('删除通知成功',0);
                $this->jsonReturn(0,'删除成功');
            } else {
                $this->jsonReturn(-1,'删除失败');
            }
        } else {
            $this->jsonReturn(-1,'请选择需要删除的通知');
        }
    }









}
