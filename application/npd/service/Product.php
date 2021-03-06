<?php

namespace app\npd\service;

use app\common\service\Service;


use app\npd\model\Product as ProductModel;
use app\npd\model\ProductData;
use app\npd\model\ProductMerchandizing;
use app\npd\model\ProductPatent;
use app\npd\model\Category;
use my\RedisData;

use think\Db;

class Product extends Service
{
    /**
     * 取得栏目的所有子栏目id
     *
     * @param integer $pid 当前栏目id
     * @param integer $exp 缓存时间
     * @return array
     */
    public function getCateChildrenIds($pid, $exp = 3600 * 24)
    {
        $CategoryModel = new Category();
        return $CategoryModel->getCateChildrenIds($pid, 'product', $exp);
    }


    /**
     * 取得分类详情
     *
     * @param integer $cid 分类id
     * @param integer $exp 缓存时间
     * @return void
     */
    public function getCateDetail($cid, $exp = 3600 * 24)
    {
        $CategoryModel = new Category();
        return $CategoryModel->getDetail($cid, $exp);
    }

    /**
     * 取得栏目面包屑导航列精数据
     *
     * @param integer|string|array $cid integer:栏目id,string:栏目path,array:栏目数据
     * @return array
     */
    public function getCateBreadcrumb($cid)
    {
        $CategoryModel = new Category();
        return $CategoryModel->getCateBreadcrumb($cid, 'product');
    }
}
