<?php
namespace app\admin\controller;

use app\common\model\Docs as DocsModel;
use app\common\model\DocsCategory as DocsCategoryModel;
use app\admin\controller\AdminBase;

/**
 * 文档管理
 * Class Article
 * @package app\admin\controller
 */
class Docs extends AdminBase
{
    protected $docs_model;
    protected $category_model;

    protected function initialize()
    {
        parent::initialize();
        $this->docs_model  = new DocsModel();
        $this->category_model = new DocsCategoryModel();
    }

    /**
     * 文档管理
     * @param int    $cid     分类ID
     * @param string $keyword 关键词
     * @param int    $page
     * @return mixed
     */
    public function index($cid = 0, $keyword = '', $page = 1)
    {
        $map   = [];
        $field = 't.id,t.title,t.cid,t.update_time,t.create_time,t.listorder,t.status,t.lang';

        if ($cid > 0) {
            $map[] = ['cid','=',$cid];
        }

        if (!empty($keyword)) {
            $map[] = ['title','like', "%{$keyword}%"];
        }

        $join = [
          ['docs_category c','t.cid = c.id', 'left'],
        ];
        $lists  = $this->docs_model->field($field)->alias('t')->join($join)->where($map)->order('t.cid DESC , t.create_time DESC')->paginate(15, false, ['page' => $page]);
        // $category_list = $this->category_model->field('id,name,title')->where([['is_delete','=',0]])->select();
        $category_list = $this->category_model->column('title', 'id');
        return $this->fetch('index', ['lists' => $lists, 'category_list' => $category_list, 'cid' => $cid, 'keyword' => $keyword]);
    }


    /**
     * 添加文档
     * @return mixed
     */
    public function add()
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          $validate_result = $this->validate($data, 'Docs');
          $data['description'] = $data['description'] ? iconv_substr($data['description'],0,250) : '' ;
          if ($validate_result !== true) {
              $this->jsonReturn(-1,$validate_result);
          } else {
              if ($this->docs_model->allowField(true)->save($data)) {
                  $this->log('保存文档成功',0);
                  $this->jsonReturn(0,'保存成功');
              } else {
                  $this->log('保存文档失败',-1);
                  $this->jsonReturn(-1,'保存失败');
              }
          }
      }else{
        $category_list = $this->category_model->column('title', 'id');

        return $this->assign('category_list',$category_list)->fetch();

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
          $validate_result = $this->validate($data, 'Docs');
          $data['description'] = $data['description'] ? iconv_substr($data['description'],0,250) : '' ;
          if ($validate_result !== true) {
              $this->jsonReturn(-1,$validate_result);
          } else {
              if ($this->docs_model->allowField(true)->save($data, $id) !== false) {
                  $this->log('编辑文档成功',0);
                  $this->jsonReturn(0,'修改成功');
              } else {
                  $this->log('编辑文档失败',-1);
                  $this->jsonReturn(-1,'修改失败');
              }
          }
      }else{
        $data = $this->docs_model->find($id);
        $category_list = $this->category_model->column('title', 'id');

        return $this->fetch('edit', ['data' => $data,'category_list'=>$category_list]);
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
            if ($this->docs_model->destroy($id)) {
                $this->jsonReturn(0,'删除成功');
            } else {
                $this->jsonReturn(-1,'删除失败');
            }
        } else {
            $this->jsonReturn(-1,'请选择需要删除的文章');
        }
    }

}
