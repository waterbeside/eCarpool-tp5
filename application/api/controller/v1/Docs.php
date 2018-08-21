<?php
namespace app\api\controller\v1;

use app\api\controller\ApiBase;
use app\common\model\Docs as DocsModel;

use think\Db;

/**
 * 文档相关
 * Class Link
 * @package app\admin\controller
 */
class Docs extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * 文档列表
     * @return mixed
     */
    public function index($cate=NULL)
    {

        $field = 't.id,t.title,t.cid,t.update_time,t.create_time,t.listorder,t.status,t.lang, c.name , c.title as cate_title';
        $map   = [];
        $map[] = ['status','=',1];

        if ($cate ) {
            $map[] = ['c.name','=',$cate];
        }

        $join = [
          ['docs_category c','t.cid = c.id', 'left'],
        ];

        $lists  = DocsModel::field($field)->alias('t')->join($join)->where($map)->order('t.cid DESC , t.create_time DESC')->select();
        // $category_list = $this->category_model->field('id,name,title')->where([['is_delete','=',0]])->select();

        return $this->jsonReturn(0,['lists' => $lists]);

    }


    /**
     * get方式
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function read($id)
    {
      $field = 't.id,t.title,t.cid,t.update_time,t.create_time,t.content,t.listorder,t.status,t.lang, c.name, c.title as cate_title';
      $map   = [];
      $map[] = ['status','=',1];
      if (is_numeric($id)) {
        $map[] = ['t.id','=',$id];
      }else{
        $map[] = ['c.name','=',$id];
      }
      $lang = $this->language;
      $whereLang = is_numeric($id) ? [] :['lang'=>$lang] ;
      $join = [
        ['docs_category c','t.cid = c.id', 'left'],
      ];
      $data  = DocsModel::field($field)->alias('t')->join($join)->where($map)->where($whereLang)->find();
      if(!$data  && $lang !='zh-cn'){
        $whereLang = is_numeric($id) ? [] :['lang'=>'zh-cn'] ;
        $data  = DocsModel::field($field)->alias('t')->join($join)->where($map)->where($whereLang)->find();
      }

      return $this->jsonReturn(0,$data);
    }

}
