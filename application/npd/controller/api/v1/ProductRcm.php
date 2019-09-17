<?php

namespace app\npd\controller\api\v1;

use app\api\controller\ApiBase;
use app\npd\model\ProductRecommend;
use think\Db;

/**
 * Api Product_rcm
 * Class ProductRcm
 * @package app\npd\controller\api\v1
 */
class ProductRcm extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }

    /**
     * 取得列表
     */
    public function index($pagesize = 30)
    {
        $where = [
            ['is_delete','=','0'],
            ['status','=','1'],
        ];
        $list = ProductRecommend::where($where)->order('sort DESC, id DESC')->select();
        $returnData = [
            'list' => $list,
        ];
        $this->jsonReturn(0, $returnData, 'Successful');
    }
}
