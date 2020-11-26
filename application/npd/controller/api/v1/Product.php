<?php

namespace app\npd\controller\api\v1;

use think\Db;
use app\npd\controller\api\NpdApiBase;
use app\npd\service\Product as ProductService;
use app\npd\model\Product as ProductModel;
use app\npd\model\Customer as CustomerModel;
use app\npd\model\Category;

/**
 * Api Product
 * Class Product
 * @package app\npd\controller\api\v1
 */
class Product extends NpdApiBase
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
        $keyword = input('keyword', '', 'addslashes');
        $mapExp = '';
        $orderBy = 'is_top DESC , sort DESC';
        $map = [
            ['is_delete', '=', Db::raw(0)],
            ['status', '=', 1],
            ['site_id', '=', $this->siteId]
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
        if ($keyword) {
            // $map()
            $mapExp = " match(`title`,`title_en`,`desc`) against ('*$keyword*' IN BOOLEAN MODE) ";
            $orderBy = null;
        }
        $lists_res = ProductModel::where($map)->where($mapExp)->order($orderBy)
            ->paginate($pagesize, false, ['query' => request()->param()]);
        // ->fetchSql()->select();
        // dump($lists_res);
        // exit;


        $pagination = [
            'total' => $lists_res->total(),
            'page' => input('page', 1),
            'pagesize' => $pagesize,
            // 'render' => $lists_res->render(),
        ];
        $lists_to_array = $lists_res->toArray();
        $lists = $lists_to_array['data'];
        $returnData = [
            'list' => $this->replaceAttachmentDomain($lists, 'thumb'),
            // 'list' => $lists,
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
        $this->checkPassport(true);

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

        $customerListOrder = Db::raw("find_in_set( id, '{$data["customers"]}' )");
        $where = [
            ['id', 'in', $data['customers']],
            ['is_delete', '=', 0],
            ['site_id', '=', $this->siteId],
        ];
        $customer_list = CustomerModel::where($where)->order($customerListOrder)->select();
        $data['thumb'] = $this->replaceAttachmentDomain($data['thumb']);
        $returnData = [
            'data' => $data,
            'category' => $cate_data,
            'breadcrumd' => $breadcrumd,
            'customer_list' => $customer_list,
        ];

        $this->jsonReturn(0, $returnData, 'Successful');
    }
}
