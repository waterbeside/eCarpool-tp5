<?php

namespace app\admin\controller\npd;

use app\npd\model\Product as ProductModel;
use app\npd\model\ProductData as ProductDataModel;
use app\npd\model\ProductMerchandizing;
use app\npd\model\ProductPatent;
use app\npd\model\Customer;
use app\npd\model\Category as CategoryModel;

use app\admin\controller\npd\NpdAdminBase;
use think\Db;

/**
 * 产品管理
 * Class NpdProduct
 * @package app\admin\controller
 */

class Product extends NpdAdminBase
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
        $map[] = ['t.is_delete', '=', Db::raw(0)];
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
            $this->assign('patent_type_list', config('npd.patent_type'));
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
        $ProductModel = new ProductModel();
        $dataDetail     = $ProductModel->getDetail($id);
        if ($this->request->isPost()) {
            $data            = $this->request->param();
            $upData = $this->formatFormData($data, $id, $dataDetail);
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
            $this->assign('patent_type_list', config('npd.patent_type'));
            return $this->fetch('edit', ['data' => $dataDetail]);
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
    protected function formatFormData($data, $pid = 0, $dataDetail = null)
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
            'status' => isset($data['status']) ? $data['status'] : 0,
            'sort' => isset($data['sort']) ? $data['sort'] : 0,
            'is_top' => isset($data['is_top']) ? $data['is_top'] : 0,
        ];
        if (isset($data['customers'])) {
            $returnData['primary']['customers'] = $data['customers'];
        }
        if (!$pid) {
            $returnData['primary']['create_time'] = date('Y-m-d H:i:s');
            $returnData['primary']['is_delete'] = 0;
            return $returnData;
        }
        //创建data副表数据
        if ($dataDetail) {
            $oldDataExtraInfo_zh = $dataDetail['data_zh']['extra_info'] ?: null;
            $oldDataExtraInfo_en = $dataDetail['data_en']['extra_info'] ?: null;
        }
        $upcharge_leadtime_zh = $data['data']['zh']['upcharge_leadtime'] ? ['upcharge_leadtime' => $data['data']['zh']['upcharge_leadtime']] : [];
        $upcharge_leadtime_en = $data['data']['en']['upcharge_leadtime'] ? ['upcharge_leadtime' => $data['data']['en']['upcharge_leadtime']] : [];
        $extraInfo_data_zh = $this->addDataToData($upcharge_leadtime_zh, $oldDataExtraInfo_zh ?? []);
        $extraInfo_data_en = $this->addDataToData($upcharge_leadtime_en, $oldDataExtraInfo_en ?? []);
        $returnData['data_zh'] = [
            'pid'       => $pid,
            'intro'   => $data['data']['zh']['intro'] ?: null ,
            'feature'   => $data['data']['zh']['feature'] ?: null,
            'testing'   => $data['data']['zh']['testing'] ?: null,
            'bulk_note' => $data['data']['zh']['bulk_note'] ?: null,
            'extra_info' => $extraInfo_data_zh ? json_encode($extraInfo_data_zh) : null,
            'lang'      => 'zh-cn',
        ];
        $returnData['data_en'] = [
            'pid'       => $pid,
            'intro'   => $data['data']['en']['intro'] ?: null,
            'feature'   => $data['data']['en']['feature'] ?: null,
            'testing'   => $data['data']['en']['testing'] ?: null,
            'bulk_note' => $data['data']['en']['bulk_note'] ?: null,
            'extra_info' => $extraInfo_data_en ? json_encode($extraInfo_data_en) : null,
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

    /**
     * GET:取得产品客户列表, POST:更新产品客户
     *
     * @param integer $id 产品id
     * @return mixed
     */
    public function customers($pid = 0, $rt = 0)
    {
        if ($this->request->isPost()) {
            if (!$pid) {
                return $this->jsonReturn(992, 'Error pid');
            }
            $data = input('post.data');
            if (!is_array($data)) {
                $data = explode(',', $data);
            }
            $formatData = [];
            foreach ($data as $key => $value) {
                if (is_numeric($value)) {
                    $formatData[] = $value;
                }
            }
            $upData = [
                'customers' => implode(',', $formatData),
            ];
            $res = ProductModel::where('id', $pid)->update($upData);
            if ($res === false) {
                return $this->jsonReturn(-1, 'Failed');
            }
            return $this->jsonReturn(0, 'Successful');
        } else {
            $returnData = [
                'pid' => $pid,
                'data' => null,
                'lists' => null,
                'total' => 0,
            ];
            $data     = ProductModel::find($pid);
            if (!$data) {
                return $rt ? $this->jsonReturn(20002, $data, '找不到产品数据') : $this->fetch('', $returnData);
            }
            $returnData['data'] = $data;
            $listID = $data['customers'];
            if (!$listID) {
                return $rt ? $this->jsonReturn(0, $data, '找不到客户数据') : $this->fetch('', $returnData);
            }
            $listIDArray = explode(',', $listID);
    
            $map = [
                ['id','in', $listIDArray],
            ];
            $order = Db::raw("find_in_set( id, '$listID' )");
            $list = Customer::where($map)->order($order)->select();
            $returnData['lists'] = $list;
            $returnData['total'] = count($list);
    
            return $this->fetch('', $returnData);
        }
    }
}
