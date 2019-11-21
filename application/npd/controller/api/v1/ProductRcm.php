<?php

namespace app\npd\controller\api\v1;

use think\Db;
use app\npd\controller\api\NpdApiBase;
use app\npd\model\ProductRecommend;

/**
 * Api Product_rcm
 * Class ProductRcm
 * @package app\npd\controller\api\v1
 */
class ProductRcm extends NpdApiBase
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
            ['is_delete','=', Db::raw(0)],
            ['status', '=', 1],
        ];
        $list = ProductRecommend::where($where)->order('sort DESC, id DESC')->select();
        $returnData = [
            'list' => $list,
        ];
        $this->jsonReturn(0, $returnData, 'Successful');
    }
}
