<?php

namespace app\api\controller\v1\content;

use think\Db;
use app\api\controller\ApiBase;
use app\content\model\Idle as IdleModel;
use app\content\model\Category as CategoryModel;
use my\RedisData;

/**
 * 二手市场分类
 * Class Category
 * @package app\api\controller
 */
class Category extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }

    /**
     * 列表
     */
    public function index()
    {
        $redis = RedisData::getInstance();
        $type = input('param.type', '');
        $cacheData = $redis->cache('carpool:category:selects:'.($type ?: 'all'));
        if ($cacheData) {
            return $this->jsonReturn(0, ['lists'=>$cacheData], 'success');
        }
        $fields = "t.id,t.parent_id,t.type,t.sort,t.name_zh,t.name_en,t.name_vi";
        $map = [
            ["t.is_delete", "=", Db::raw(0)],
        ];
        if ($type) {
            $map[] = ['t.type', '=', $type];
        }
        $orderby = "t.sort DESC";
        $results = CategoryModel::alias('t')->field($fields)->where($map)->order($orderby)->select();
        if (!$results) {
            $this->jsonReturn(20002, [], 'No Data');
        }
        $redis->cache('carpool:category:selects:'.($type ?: 'all'), $results, 60 * 60);

        $returnData = [
            'lists' => $results
        ];
        $this->jsonReturn(0, $returnData, 'success');
    }

}
