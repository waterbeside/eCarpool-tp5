<?php
namespace app\admin\controller;


use app\content\model\Label as LabelModel;
use app\admin\controller\AdminBase;
use think\Db;
use my\Tree;

/**
 * 标签管理
 * Class Label
 * @package app\admin\controller
 */
class ContentLabel extends AdminBase
{

    protected $label_model;

    protected function initialize()
    {
        parent::initialize();
        $this->label_model = new LabelModel();
    }

    /**
     * 标签管理
     * @return mixed
     */
    public function index($filter = [], $page = 1,$recycled=0)
    {

        $map = [];
        if($recycled){
          $map[] = ['is_delete','=',1];
        }else{
          $map[] = ['is_delete','=',0];
        }

        if (isset($filter['keyword']) && $filter['keyword'] ){
          $map[] = ['name_zh|name_en|name_vi','like', "%{$filter['keyword']}%"];
        }
        if (isset($filter['type']) && is_numeric($filter['type'])){
          $map[] = ['type','=', $filter['type']];
        }
        $data  = $this->label_model->where($map)->order(['sort' => 'DESC', 'id' => 'ASC'])->paginate(20, false, ['page' => $page]);
        $typeList = config('content.label_type');
        $this->assign('typeList', $typeList);
        $this->assign('recycled', $recycled);
        $this->assign('filter', $filter);
        $this->assign('lists', $data);
        return $this->fetch();

    }

    /**
     * 添加标签
     * @param string $pid
     * @return mixed
     */
    public function add()
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          $validate_result = $this->validate($data, 'app\content\validate\Label');
          if ($validate_result !== true) {
              $this->jsonReturn(-1,$validate_result);
          }
          if(!isset($data['parent_id']) || !is_numeric($data['parent_id'])){
            $data['parent_id'] = 0 ;
          }
          if ($this->label_model->allowField(true)->save($data)) {
            $this->label_model->deleteListCache();
            $this->log('添加标签成功',0);
            $this->jsonReturn(0,'保存成功');
          } else {
            $this->log('添加标签失败',-1);
            $this->jsonReturn(-1,'保存失败');
          }

      }else{


        $typeList = config('content.label_type');
        $this->assign('typeList', $typeList);

        return $this->fetch('add');
      }
    }



    /**
     * 编辑标签
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {


      if ($this->request->isPost()) {
          $data            = $this->request->param();
          $validate_result = $this->validate($data, 'app\content\validate\Label');

          if ($validate_result !== true) {
              $this->jsonReturn(-1,$validate_result);
          }


          if ($this->label_model->allowField(true)->save($data, $id) !== false) {
            $this->label_model->deleteListCache();
            $this->log('更新标签成功',0);
              $this->jsonReturn(0,'更新成功');
          } else {
            $this->log('更新标签失败',-1);
              $this->jsonReturn(-1,'更新失败');
          }


      }else{


        $typeList = config('content.label_type');
        $this->assign('typeList', $typeList);
        $category_data = $this->label_model->find($id);


        return $this->fetch('edit', ['data' => $category_data]);
      }
    }



    /**
     * 删除标签
     * @param $id
     */
    public function delete($id)
    {

        if($this->label_model->where('id', $id)->update(['is_delete' => 1])){
          $this->label_model->deleteListCache();
          $this->log('删除标签成功',0);
          $this->jsonReturn(0,'删除成功');
        }else{
          $this->log('删除标签失败',-1);
          $this->jsonReturn(-1,'删除失败');
        }

    }


    /**
     * 还原标签
     * @param $id
     */
    public function recycle($id)
    {
        if($this->label_model->where('id', $id)->update(['is_delete' => 0])){
          $this->label_model->deleteListCache();
          $this->log('还原标签成功',0);
          $this->jsonReturn(0,'还原成功');
        }else{
          $this->log('还原标签失败',-1);
          $this->jsonReturn(-1,'还原失败');
        }

    }









}
