<?php

namespace app\npd\controller\api\v1;

use app\api\controller\ApiBase;

use app\npd\model\Customer as CustomerModel;
use app\npd\model\Category;
use my\RedisData;

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
        // $this->checkPassport(1);s
    }

    /**
     * 取得客户列表
     */
    public function index($group = '', $is_recommend = 0, $pagesize = 24)
    {
        $page = input('param.page', 1);
        $cacheKey = "NPD:customer:list:g_{$group}_re_{$is_recommend}_page_{$page}_pz_{$pagesize}";
        $redis = new RedisData();
        $returnData= $redis->cache($cacheKey);
        if ($returnData) {
            $this->jsonReturn(0, $returnData, 'Successful');
        }
        $map = [
            ['is_delete', '=', 0],
        ];
        if ($is_recommend) {
            $map[] = ['is_recommend', '=', 1];
        }
        if (!empty($group)) {
            $map[] = $group === 'other' ? ['r_group', 'in', ['', 'other'] ] : ['r_group', '=', $group];
        }
        $lists_res = CustomerModel::where($map)->order('sort DESC')->paginate($pagesize, false, ['query' => request()->param()]);
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
        $redis->cache($cacheKey, $returnData, 60);
        $this->jsonReturn(0, $returnData, 'Successful');
    }


    /**
     * 取得分组
     *
     */
    public function group()
    {
        $returnData = [
            'list' => config('npd.customer_group'),
        ];
        $this->jsonReturn(0, $returnData, 'Successful');
    }
}
