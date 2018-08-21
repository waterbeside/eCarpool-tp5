<?php
namespace app\admin\controller;

use app\common\model\SlideCategory as SlideCategoryModel;
use app\common\model\Slide as SlideModel;
use app\admin\controller\AdminBase;
use think\Db;

/**
 * 轮播图管理
 * Class Slide
 * @package app\admin\controller
 */
class Slide extends AdminBase
{

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * 轮播图管理
     * @return mixed
     */
    public function index()
    {
        $slide_category_model = new SlideCategoryModel();
        $slide_category_list  = $slide_category_model->column('name', 'id');
        $slide_list           = SlideModel::all();

        return $this->fetch('index', ['slide_list' => $slide_list, 'slide_category_list' => $slide_category_list]);
    }

    /**
     * 添加轮播图
     * @return mixed
     */
    public function add()
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          $validate_result = $this->validate($data, 'Slide');

          if ($validate_result !== true) {
              $this->jsonReturn(-1,$validate_result);
          } else {
              $slide_model = new SlideModel();
              if ($slide_model->allowField(true)->save($data)) {
                $this->jsonReturn(0,'保存成功');
              } else {
                $this->jsonReturn(-1,'保存失败');
              }
          }
      }else{
        $slide_category_list = SlideCategoryModel::all();
        return $this->fetch('add', ['slide_category_list' => $slide_category_list]);
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
          $validate_result = $this->validate($data, 'Slide');

          if ($validate_result !== true) {
              $this->jsonReturn(-1,$validate_result);
          } else {
              $slide_model = new SlideModel();
              if ($slide_model->allowField(true)->save($data, $id) !== false) {
                $this->jsonReturn(0,'更新成功');
              } else {
                $this->jsonReturn(-1,'更新失败');
              }
          }
      }else{
        $slide_category_list = SlideCategoryModel::all();
        $slide               = SlideModel::get($id);
        return $this->fetch('edit', ['slide' => $slide, 'slide_category_list' => $slide_category_list]);
      }

    }


    /**
     * 删除轮播图
     * @param $id
     */
    public function delete($id)
    {
        if (SlideModel::destroy($id)) {
          $this->jsonReturn(0,'删除成功');
        } else {
          $this->jsonReturn(-1,'删除失败');
        }
    }
}
