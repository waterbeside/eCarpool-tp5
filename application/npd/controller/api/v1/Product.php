<?php

namespace app\npd\controller\api\v1;

use app\api\controller\ApiBase;

use app\npd\service\Product as ProductService;
use app\npd\model\Product as ProductModel;
use app\npd\model\Customer as CustomerModel;
use app\npd\model\Category;

use think\Db;

/**
 * Api Product
 * Class Product
 * @package app\npd\controller\api\v1
 */
class Product extends ApiBase
{

  protected function initialize()
  {
    parent::initialize();
    // $this->checkPassport(1);
  }

  /**
   * 取得列表
   */
  public function index($cid = 0, $pagesize = 30)
  {
    $map = [
      ['is_delete', '=', 0],
      ['status', '=', 1],
    ];
    $cate_data = null;
    $breadcrumd = null;
    if (is_numeric($cid) && $cid > 0) {
      $ProductService = new ProductService();
      $cate_data = $ProductService->getCateDetail($cid);
      $breadcrumd  = $ProductService->getCateBreadcrumb($cate_data);
      $cate_Ids  = $ProductService->getCateChildrenIds($cid);
      $map[] = ['cid', 'in', $cate_Ids];
    }

    if ((is_numeric($cid) && $cid == -1) ||  $cid === 'recommend') {
      $map[] = ['is_recommend', '=', 1];
    }
    $lists_res = ProductModel::where($map)->order('is_top DESC , sort DESC')->paginate($pagesize, false,  ['query' => request()->param()]);

    $pagination = [
      'total' => $lists_res->total(),
      'page' => input('page', 1),
      'pagesize' => $pagesize,
      // 'render' => $lists_res->render(),
    ];
    $lists_to_array = $lists_res->toArray();
    $lists = $lists_to_array['data'];
    $returnData = [
      'list' => $lists,
      'pagination' => $pagination,
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
    $ProductModel = new ProductModel();
    $data     = $ProductModel->getDetail($id);
    if (!$data) {
      return $this->jsonReturn(20002, 'No data');
    }
    $ProductService = new ProductService();
    $cate_data = $ProductService->getCateDetail($data['cid']);
    $breadcrumd  = $ProductService->getCateBreadcrumb($cate_data);

    $customer_list = CustomerModel::where([['id', 'in', $data['customers']], ['is_delete', '=', 0]])->select();
    $returnData = [
      'data' => $data,
      'category' => $cate_data,
      'breadcrumd' => $breadcrumd,
      'customer_list' => $customer_list,
    ];

    $this->jsonReturn(0, $returnData, 'Successful');
  }
}
