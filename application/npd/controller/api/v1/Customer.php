<?php

namespace app\npd\controller\api\v1;

use app\api\controller\ApiBase;

use app\npd\model\Customer as CustomerModel;
use app\npd\model\Category;

use think\Db;

/**
 * Api Customer
 * Class Customer
 * @package app\npd\controller\api\v1
 */
class Customer extends ApiBase
{

  protected function initialize()
  {
    parent::initialize();
    // $this->checkPassport(1);
  }

  /**
   * 取得客户列表
   */
  public function index($is_recommend = 0, $pagesize = 24)
  {
    $map = [
      ['is_delete', '=', 0],
    ];
    if ($is_recommend) {
      $map[] = ['is_recommend', '=', 1];
    }

    $lists_res = CustomerModel::where($map)->order('sort DESC')->paginate($pagesize, false,  ['query' => request()->param()]);

    $pagination = [
      'total' => $lists_res->total(),
      'page' => input('page', 1),
      'pagesize' => $pagesize,
    ];
    $lists_to_array = $lists_res->toArray();
    $lists = $lists_to_array['data'];
    $returnData = [
      'list' => $lists,
      'pagination' => $pagination,
    ];
    $this->jsonReturn(0, $returnData, 'Successful');
  }

}
