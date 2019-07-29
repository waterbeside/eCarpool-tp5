<?php

namespace app\admin\controller;


use app\npd\model\Single;
use app\npd\model\Category as CategoryModel;
use app\admin\controller\AdminBase;
use think\Db;

/**
 * NpdSingle 单页文章管理
 * Class Category
 * @package app\admin\controller
 */
class NpdSingle extends AdminBase
{


  protected function initialize()
  {
    parent::initialize();
  }

  public function index($cid = 0, $keyword = '', $page = 1)
  {
    $map   = [];
    $map[] = ['t.is_delete', '=', 0];

    $field = 't.*,c.name as c_name';
    $CategoryModel = new CategoryModel();
    if ($cid > 0) {
      $cids = $CategoryModel->getChildrensId($cid);
      $map[] = ['cid', 'in', $cids];
    }

    if (!empty($keyword)) {
      $map[] = ['title', 'like', "%{$keyword}%"];
    }

    $join = [
      ['t_category c', 't.cid = c.id', 'left'],
    ];
    $lists  = Single::field($field)->alias('t')->join($join)->where($map)->order('t.sort DESC , t.cid DESC , t.create_time DESC')
      ->paginate(15, false, ['page' => $page]);
    // ->fetchSql()->select();
    // dump($lists);exit;

    $category_level_list       = $CategoryModel->getListByModel('single');
    foreach ($category_level_list as $key => $value) {
      $category_level_list[$key]['pid'] = $value['parent_id'];
    }
    $category_level_list = array2level($category_level_list);
    $this->assign('category_level_list', $category_level_list);

    return $this->fetch('index', ['lists' => $lists, 'cid' => $cid, 'keyword' => $keyword]);
  }



  /**
   * 添加文档
   * @return mixed
   */
  public function add()
  {
    if ($this->request->isPost()) {
      $data            = $this->request->param();
      $validate_result = $this->validate($data, 'app\npd\validate\Single');
      $data['description'] = $data['description'] ? iconv_substr($data['description'], 0, 250) : '';
      if ($validate_result !== true) {
        $this->jsonReturn(-1, $validate_result);
      } else {
        $Single_model = new Single();
        if ($Single_model->allowField(true)->save($data)) {
          $this->log('添加NPD单页文档成功', 0);
          $this->jsonReturn(0, '保存成功');
        } else {
          $this->log('添加NPD单页文档失败', -1);
          $this->jsonReturn(-1, '保存失败');
        }
      }
    } else {
      $CategoryModel = new CategoryModel();
      $category_level_list       = $CategoryModel->getListByModel('single');
      foreach ($category_level_list as $key => $value) {
        $category_level_list[$key]['pid'] = $value['parent_id'];
      }
      $category_level_list = array2level($category_level_list);
      $this->assign('category_level_list', $category_level_list);
      return $this->fetch();
    }
  }



  /**
   * 编辑文档
   * @param $id
   * @return mixed
   */
  public function edit($id)
  {
    if ($this->request->isPost()) {
      $data            = $this->request->param();
      $validate_result = $this->validate($data, 'app\npd\validate\Single');
      $data['description'] = $data['description'] ? iconv_substr($data['description'], 0, 250) : '';
      if ($validate_result !== true) {
        $this->jsonReturn(-1, $validate_result);
      } else {
        $Single_model = new Single();
        if ($Single_model->allowField(true)->save($data, $id) !== false) {
          $this->log('编辑NPD单页文档成功', 0);
          $this->jsonReturn(0, '修改成功');
        } else {
          $this->log('编辑NPD单页文档失败', -1);
          $this->jsonReturn(-1, '修改失败');
        }
      }
    } else {
      $data = Single::find($id);
      $CategoryModel = new CategoryModel();
      $category_level_list       = $CategoryModel->getListByModel('single');
      foreach ($category_level_list as $key => $value) {
        $category_level_list[$key]['pid'] = $value['parent_id'];
      }
      $category_level_list = array2level($category_level_list);
      $this->assign('category_level_list', $category_level_list);
      $this->assign('data', $data);
      return $this->fetch();
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
      if (Single::destroy($id)) {
        $this->jsonReturn(0, '删除成功');
      } else {
        $this->jsonReturn(-1, '删除失败');
      }
    } else {
      $this->jsonReturn(-1, '请选择需要删除的文章');
    }
  }
}
