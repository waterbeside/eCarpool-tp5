<?php
namespace app\admin\controller;

use app\admin\controller\AdminBase;
use think\Db;

/**
 * 轮播图分类
 * Class SlideCategory
 * @package app\admin\controller
 */
class SlideCategory extends AdminBase
{
    protected function initialize()
    {
        parent::initialize();

    }

    /**
     * 轮播图分类
     * @return mixed
     */
    public function index()
    {
        $slide_category_list = Db::name('slide_category')->select();

        return $this->fetch('index', ['slide_category_list' => $slide_category_list]);
    }

    /**
     * 添加分类
     * @return mixed
     */
    public function add()
    {
      if ($this->request->isPost()) {
          $data = $this->request->post();

          if (Db::name('slide_category')->insert($data)) {
            $this->jsonReturn(0,'保存成功');
          } else {
            $this->jsonReturn(-1,'保存失败');
          }
      }else{
        return $this->fetch();
      }
    }



    /**
     * 编辑分类
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
      if ($this->request->isPost()) {
          $data = $this->request->post();

          if (Db::name('slide_category')->update($data) !== false) {
              $this->jsonReturn(0,'更新成功');
          } else {
              $this->jsonReturn(-1,'更新失败');
          }
      }else{
        $slide_category = Db::name('slide_category')->find($id);
        return $this->fetch('edit', ['slide_category' => $slide_category]);
      }

    }


    /**
     * 删除分类
     * @param $id
     * @throws \think\Exception
     */
    public function delete($id)
    {
        if (Db::name('slide_category')->delete($id) !== false) {
          $this->jsonReturn(0,'删除成功');
        } else {
          $this->jsonReturn(-1,'删除失败');
        }
    }
}
