<?php
namespace app\admin\controller;

use app\common\model\Docs as DocsModel;
use app\common\model\DocsCategory as DocsCategoryModel;
use app\common\controller\AdminBase;
use think\Db;

/**
 * 文档分类管理
 * Class Category
 * @package app\admin\controller
 */
class DocsCategory extends AdminBase
{


    /**
     * 栏目管理
     * @return mixed
     */
    public function index()
    {
      $lists = DocsCategoryModel::order('listorder Desc')->where('is_delete',0)->select();
        return $this->assign('lists', $lists)->fetch();
    }

    /**
     * 添加栏目
     * @param string $pid
     * @return mixed
     */
    public function add()
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          $validate_result = $this->validate($data, 'app\admin\validate\DocsCategory');
          if ($validate_result !== true) {
            return $this->jsonReturn(-1,$validate_result);
          }
          $model = new DocsCategoryModel();
          if ($model->allowField(true)->save($data)) {
              $this->log('添加文档分类成功',0);
              $this->jsonReturn(0,'添加文档分类成功');
          } else {
              $this->log('添加文档分类失败',-1);
              $this->jsonReturn(-1,'添加文档分类失败');
          }

      }else{
        return $this->fetch('add');
      }
    }



    /**
     * 编辑栏目
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          $validate_result = $this->validate($data, 'app\admin\validate\DocsCategory');
          if ($validate_result !== true) {
              $this->jsonReturn(-1,$validate_result);
          }
          $model = new DocsCategoryModel();
          if ($model->allowField(true)->save($data, $id) !== false) {
              $this->log('编辑文档分类成功',0);
              $this->jsonReturn(0,'编辑成功');
          } else {
              $this->log('编辑文档分类失败',-1);
              $this->jsonReturn(-1,'更新失败');
          }

      }else{
        $data = DocsCategoryModel::find($id);
        return $this->fetch('edit', ['data' => $data]);
      }
    }


    /**
     * 删除栏目
     * @param $id
     */
    public function delete($id)
    {
      $model = new DocsCategoryModel();
      $res = $model->where('id', $id)->update(['is_delete' => 1]);
        // $res = $model->destroy($id);
        if ($res) {
           $this->log('删除文档分类成功',0);
           $this->jsonReturn(0,'删除成功');
        } else {
          $this->log('删除文档分类失败',-1);
            $this->jsonReturn(-1,'删除失败');
        }
    }
}
