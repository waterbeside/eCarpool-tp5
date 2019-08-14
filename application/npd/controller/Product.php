<?php

namespace app\npd\controller;

use app\common\controller\Base;

use app\npd\model\Product as ProductModel;
use app\npd\model\Customer as CustomerModel;
use app\npd\model\Category as CategoryModel;

/**
 * 产品
 * Class Product
 * @package app\npd\controller
 */
class Product extends Base
{

    /**
     * 编辑详细
     * @param $id
     * @return mixed
     */
    public function detail($id)
    {

        $CategoryModel = new CategoryModel();
        $category_level_list       = $CategoryModel->getListByModel('product');
        foreach ($category_level_list as $key => $value) {
            $category_level_list[$key]['pid'] = $value['parent_id'];
        }

        $category_level_list = array2level($category_level_list);
        $this->assign('category_level_list', $category_level_list);
        $category_list_idKey = [];
        foreach ($category_level_list as $k => $v) {
            $category_list_idKey[$v['id']] = $v;
        }

        $ProductModel = new ProductModel();
        $data     = $ProductModel->getDetail($id);
        if (!$data) {
            return $this->error('No data');
        }
        $data['cateData'] = isset($category_list_idKey[$data['cid']]) ?  $category_list_idKey[$data['cid']] : [];
        $data['customer_list'] = CustomerModel::where([['id', 'in', $data['customers']], ['is_delete', '=', 0]])->select();

        return $this->fetch('detail', ['data' => $data]);
    }
}
