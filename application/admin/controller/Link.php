<?php
namespace app\admin\controller;

use app\common\model\Link as LinkModel;
use app\common\controller\AdminBase;
use think\Db;

/**
 * 友情链接
 * Class Link
 * @package app\admin\controller
 */
class Link extends AdminBase
{
    protected $link_model;

    protected function initialize()
    {
        parent::initialize();
        $this->link_model = new LinkModel();
    }

    /**
     * 友情链接
     * @return mixed
     */
    public function index()
    {
        $link_list = $this->link_model->select();

        return $this->fetch('index', ['link_list' => $link_list]);
    }

    /**
     * 添加友情链接
     * @return mixed
     */
    public function add()
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          $validate_result = $this->validate($data, 'Link');

          if ($validate_result !== true) {
              $this->jsonReturn(1,$validate_result);
          } else {
              if ($this->link_model->allowField(true)->save($data)) {
                  $this->jsonReturn(0,'保存成功');
              } else {
                  $this->jsonReturn(1,'保存失败');
              }
          }
      }else{
        return $this->fetch();
      }
    }



    /**
     * 编辑友情链接
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          $validate_result = $this->validate($data, 'Link');

          if ($validate_result !== true) {
              $this->error($validate_result);
          } else {
              if ($this->link_model->allowField(true)->save($data, $id) !== false) {
                  $this->jsonReturn(0,'更新成功');
              } else {
                  $this->jsonReturn(1,'更新失败');
              }
          }
      }else{
        $link = $this->link_model->find($id);
        return $this->fetch('edit', ['link' => $link]);
      }

    }



    /**
     * 删除友情链接
     * @param $id
     */
    public function delete($id)
    {
        if ($this->link_model->destroy($id)) {
            $this->jsonReturn(0,'删除成功');
        } else {
            $this->jsonReturn(1,'删除失败');
        }
    }
}
