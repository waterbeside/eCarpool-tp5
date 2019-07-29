<?php

namespace app\npd\controller\api\v1;

use app\api\controller\ApiBase;

use app\npd\model\Single as SingleModel;
use app\npd\model\Category;

use think\Db;

/**
 * Api Single
 * Class Single
 * @package app\npd\controller\api\v1
 */
class Single extends ApiBase
{

  protected function initialize()
  {
    parent::initialize();
    // $this->checkPassport(1);
  }

  /**
   * 按cid取得单页内容
   */
  public function index($cid = 0)
  {
    $map = [
      ['is_delete', '=', 0],
      ['status', '=', 1],
    ];
    $cate_data = null;
    $breadcrumd = null;
    if (is_numeric($cid) && $cid > 0) {
      $Category = new Category();
      $cate_data = $Category->getDetail($cid);
      $breadcrumd  = $Category->getCateBreadcrumb($cate_data, 'article');
      $cate_Ids  = $Category->getCateChildrenIds($cid, 'article');
      $map[] = ['cid', 'in', $cate_Ids];
    }

    $lang = $this->language;
    $map[] = ['lang', '=', ($lang !== "zh-cn" ? 'en' : 'zh-cn')];

    $field = 'id, title, cid, update_time, create_time, publish_time , content, sort, status, lang';
    $data = SingleModel::field($field)->where($map)->order('sort DESC')->find();

    $returnData = [
      'data' => $data,
      'category' => $cate_data,
      'breadcrumd' => $breadcrumd,
    ];
    $this->jsonReturn(0, $returnData, 'Successful');
  }

  /**
   * 取得产品详情
   *
   * @param integer $id
   */
  public function read($id = 0)
  {
    if (!$id) {
      $this->jsonReturn(992, 'Error id');
    }
    $field = 'id, title, cid, update_time, create_time, publish_time , content, sort, status, lang';
    $map   = [];
    $map = [
      ['status', '=', 1],
      ['is_delete', '=', 0],
    ];
    if (is_numeric($id)) {
      $map[] = ['t.id', '=', $id];
    }

    // $lang = $this->language;
    // $whereLang = ['lang'=>$lang] ;

    $data  = SingleModel::field($field)->alias('t')->where($map)->find();

    if (!$data) {
      return $this->jsonReturn(20002, 'No data');
    }

    $Category = new Category();
    $cate_data = $Category->getDetail($data['cid']);
    $breadcrumd = $Category->getCateBreadcrumb($cate_data, 'article');


    $returnData = [
      'data' => $data,
      'category' => $cate_data,
      'breadcrumd' => $breadcrumd,
    ];

    $this->jsonReturn(0, $returnData, 'Successful');
  }
}