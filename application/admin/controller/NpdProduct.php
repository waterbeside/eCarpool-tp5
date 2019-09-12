<?php

namespace app\admin\controller;

use app\npd\model\Product as ProductModel;
use app\npd\model\ProductData as ProductDataModel;
use app\npd\model\ProductMerchandizing;
use app\npd\model\ProductPatent;

use app\npd\model\Category as CategoryModel;
use app\admin\controller\AdminBase;
use think\Db;

/**
 * 产品管理
 * Class NpdProduct
 * @package app\admin\controller
 */

class NpdProduct extends AdminBase
{



    /**
     * 产品管理
     * @param int    $cid     分类ID
     * @param string $keyword 关键词
     * @param int    $page
     * @return mixed
     */
    public function index($cid = 0, $filter = ['keyword' => ''], $page = 1)
    {
        $map   = [];
        $map[] = ['t.is_delete', '=', 0];
        $field = 't.*,c.name as c_name';
        $CategoryModel = new CategoryModel();
        if ($cid > 0) {
            $cids = $CategoryModel->getChildrensId($cid);
            $map[] = ['cid', 'in', $cids];
        }

        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['title|title_en', 'like', "%{$filter['keyword']}%"];
        }

        if (isset($filter['is_recommend'])  && is_numeric($filter['is_recommend'])) {
            $map[] = ['is_recommend', '=', $filter['is_recommend']];
        }

        $join = [
            ['t_category c', 't.cid = c.id', 'left'],
        ];
        $lists  = ProductModel::field($field)->alias('t')->join($join)->where($map)->order('t.sort DESC , t.create_time DESC , t.cid DESC ')
            // ->fetchSql()->select();
            ->paginate(15, false, ['page' => $page]);

        $category_level_list       = $CategoryModel->getListByModel('product');
        foreach ($category_level_list as $key => $value) {
            $category_level_list[$key]['pid'] = $value['parent_id'];
        }
        $category_level_list = array2level($category_level_list);
        $this->assign('category_level_list', $category_level_list);

        return $this->fetch('index', ['lists' => $lists, 'cid' => $cid, 'filter' => $filter]);
    }


    /**
     * 添加产品
     * @param string $pid
     * @return mixed
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data            = $this->request->param();
            $upData = $this->formatFormData($data);
            $validate_result = $this->validate($data, 'app\npd\validate\Product');
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            }
            Db::connect('database_npd')->startTrans();
            try {
                /******** 处理主表 ********/
                $id = ProductModel::insertGetId($upData['primary']);
                if (!$id) {
                    throw new \Exception("创建数据失败");
                }
                /******** 处理副表 ********/
                $upData = $this->formatFormData($data, $id);
                $this->upSubTableData($upData, $id);

                // 提交事务
                Db::connect('database_npd')->commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::connect('database_npd')->rollback();
                $errorMsg = $e->getMessage();
                $this->log('添加NPD产品失败 title=' . $data['title'], 0);
                return $this->jsonReturn(-1, $errorMsg);
            }
            $this->log('添加NPD产品成功 id=' . $id, -1);
            return $this->jsonReturn(0, '添加成功');
        } else {
            $CategoryModel = new CategoryModel();
            $category_level_list       = $CategoryModel->getListByModel('product');
            foreach ($category_level_list as $key => $value) {
                $category_level_list[$key]['pid'] = $value['parent_id'];
            }
            $category_level_list = array2level($category_level_list);
            $this->assign('category_level_list', $category_level_list);
            return $this->fetch();
        }
    }



    /**
     * 编辑产品
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        if ($this->request->isPost()) {
            $data            = $this->request->param();
            $upData = $this->formatFormData($data, $id);
            $validate_result = $this->validate($data, 'app\npd\validate\Product');
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            }
            Db::connect('database_npd')->startTrans();
            try {

                /******** 处理主表 ********/
                $res = ProductModel::where('id', $id)->update($upData['primary']);
                if ($res === 'false') {
                    throw new \Exception("创建数据失败");
                }
                $upData = $this->formatFormData($data, $id);
                /******** 处理副表 ********/
                $this->upSubTableData($upData, $id);

                // 提交事务
                Db::connect('database_npd')->commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::connect('database_npd')->rollback();
                $errorMsg = $e->getMessage();
                $this->log('更新NPD产品失败 id=' . $id, -1);
                return $this->jsonReturn(-1, $errorMsg);
            }
            $this->log('更新NPD产品成功 id=' . $id, 0);
            return $this->jsonReturn(0, '更新成功');
        } else {
            $CategoryModel = new CategoryModel();
            $category_level_list       = $CategoryModel->getListByModel('product');
            foreach ($category_level_list as $key => $value) {
                $category_level_list[$key]['pid'] = $value['parent_id'];
            }
            $category_level_list = array2level($category_level_list);
            $this->assign('category_level_list', $category_level_list);
            $ProductModel = new ProductModel();
            $data     = $ProductModel->getDetail($id);
            return $this->fetch('edit', ['data' => $data]);
        }
    }

    /**
     * 更新副表数据
     */
    protected function upSubTableData($upData, $id)
    {
        /******** 处理 data副表 ********/
        ProductDataModel::where('pid', $id)->delete();
        ProductDataModel::insert($upData['data_zh']);
        ProductDataModel::insert($upData['data_en']);

        /******** 处理 merchandizing副表 ********/
        ProductMerchandizing::where('pid', $id)->delete();
        if ($upData['merchandizing'] &&  count($upData['merchandizing']) > 0) {
            ProductMerchandizing::insertAll($upData['merchandizing']);
        }
        /******** 处理 patent副表 ********/
        ProductPatent::where('pid', $id)->delete();
        if ($upData['patent'] && count($upData['patent']) > 0) {
            ProductPatent::insertAll($upData['patent']);
        }
    }

    /**
     * 格式化表单数据
     */
    protected function formatFormData($data, $pid = 0)
    {
        $returnData = [];
        //创建主表数据
        $returnData['primary'] = [
            'cid' => $data['cid'],
            'title' => $data['title'],
            'title_en' => $data['title_en'],
            'thumb' => $data['thumb'],
            'is_recommend' => isset($data['is_recommend']) ? $data['is_recommend'] : 0,
            'publish_time' => $data['publish_time'],
            'update_time' => date('Y-m-d H:i:s'),
            'customers' => $data['customers'],
            'status' => isset($data['status']) ? $data['status'] : 0,
            'sort' => isset($data['sort']) ? $data['sort'] : 0,
            'is_top' => isset($data['is_top']) ? $data['is_top'] : 0,
        ];
        if (!$pid) {
            $returnData['primary']['create_time'] = date('Y-m-d H:i:s');
            $returnData['primary']['is_delete'] = 0;
            return $returnData;
        }
        //创建data副表数据
        $returnData['data_zh'] = [
            'pid'       => $pid,
            'content'   => $data['data']['zh']['content'],
            'testing'   => $data['data']['zh']['testing'],
            'bulk_note' => $data['data']['zh']['bulk_note'],
            'lang'      => 'zh-cn',
        ];
        $returnData['data_en'] = [
            'pid'       => $pid,
            'content'   => $data['data']['en']['content'],
            'testing'   => $data['data']['en']['testing'],
            'bulk_note' => $data['data']['en']['bulk_note'],
            'lang'      => 'en',
        ];
        //创建 merchandizing 副表数据
        $returnData['merchandizing'] = [];
        foreach ($data['merchandizing'] as $key => $value) {
            if (!empty($value['ppo_no']) && !empty($value['desc'])) {
                $value['pid'] = $pid;
                $returnData['merchandizing'][] = $value;
            }
        }
        //创建 patent 副表数据
        $returnData['patent'] = [];
        foreach ($data['patent'] as $key => $value) {
            $cty_name     = $value['cty_name'];
            $cty_name_en  = $value['cty_name_en'];
            $sn           = $value['sn'];
            $type_name    = $value['type_name'];
            if (!empty($cty_name) || !empty($sn) || !empty($cty_name_en)) {
                $returnData['patent'][] = [
                    'pid'            => $pid,
                    'cty_name'       => $cty_name,
                    'cty_name_en'    => $cty_name_en,
                    'sn'             => $sn,
                    'type_name'      => $type_name,
                ];
            }
        }

        return $returnData;
    }



    /**
     * 删除产品
     * @param $id
     */
    public function delete($id)
    {
        if (ProductModel::where('id', $id)->update(['is_delete' => 1])) {
            $this->log('删除产品成功', 0);
            $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除产品失败', -1);
            $this->jsonReturn(-1, '删除失败');
        }
    }
}
