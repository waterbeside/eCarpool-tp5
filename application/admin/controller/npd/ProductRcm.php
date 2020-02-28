<?php

namespace app\admin\controller\npd;

use app\npd\model\Product as ProductModel;
use app\npd\model\ProductRecommend;

use app\admin\controller\AdminBase;
use think\Db;

/**
 * 产品推荐
 * Class ProductRcm
 * @package app\admin\controller
 */

class ProductRcm extends AdminBase
{


    /**
     * 产品推荐
     * @param array $filter  筛选
     * @return mixed
     */
    public function index($filter = ['keyword' => ''])
    {
        $map   = [];
        $map[] = ['t.is_delete', '=', Db::raw(0)];

        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['title|title_en', 'like', "%{$filter['keyword']}%"];
        }

        if (isset($filter['status']) && is_numeric($filter['status'])) {
            $map[] = ['status', '=', $filter['status']];
        }

        $listBef  = ProductRecommend::alias('t')->where($map)->order('t.sort DESC , t.create_time DESC , t.id DESC ');
        $list =  $listBef->select();
        $total = count($list);
        
        $returnData = [
            'filter' => $filter,
            'lists' => $list,
            'total' => $total,
        ];
        return $this->fetch('index', $returnData);
    }


    /**
     * 添加推荐
     * @return mixed
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data          = $this->request->param();
            $data['model'] = 'product';
            $data['status'] = isset($data['status']) ? isset($data['status']) : 0;
            $validate_result = $this->validate($data, 'app\npd\validate\Recommend');
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            }
            $ProductRecommend = new ProductRecommend();
            if ($ProductRecommend->allowField(true)->save($data)) {
                $this->jsonReturn(0, '保存成功');
            } else {
                $this->jsonReturn(-1, '保存失败');
            }
        } else {
            return $this->fetch('add');
        }
    }



    /**
     * 编辑推荐
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        if ($this->request->isPost()) {
            $data          = $this->request->param();
            $data['status'] = isset($data['status']) ? isset($data['status']) : 0;
            $validate_result = $this->validate($data, 'app\npd\validate\Recommend');
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            }
            $ProductRecommend = new ProductRecommend();
            if ($ProductRecommend->allowField(true)->save($data, $id)) {
                $this->jsonReturn(0, '保存成功');
            } else {
                $this->jsonReturn(-1, '保存失败');
            }
        } else {
            $data     = ProductRecommend::find($id);
            $this->assign('id', $id);
            return $this->fetch('edit', ['data' => $data]);
        }
    }


    /**
     * 删除推荐
     * @param $id
     */
    public function delete($id)
    {
        if (ProductRecommend::where('id', $id)->update(['is_delete' => 1])) {
            $this->log('删除产品推荐成功', 0);
            $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除产品推荐失败', -1);
            $this->jsonReturn(-1, '删除失败');
        }
    }
}
