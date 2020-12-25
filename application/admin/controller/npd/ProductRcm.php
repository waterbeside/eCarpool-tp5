<?php

namespace app\admin\controller\npd;

use app\npd\model\Product as ProductModel;
use app\npd\model\ProductRecommend;

use app\admin\controller\npd\NpdAdminBase;
use think\Db;

/**
 * 产品推荐
 * Class ProductRcm
 * @package app\admin\controller\npd
 */

class ProductRcm extends NpdAdminBase
{


    /**
     * 产品推荐
     * @param array $filter  筛选
     * @return mixed
     */
    public function index($filter = ['keyword' => ''])
    {
        $where   = [];
        $where[] = ['t.is_delete', '=', Db::raw(0)];

        $siteListIdMap = $this->getSiteListIdMap();
        $siteIdwhere = $this->authNpdSite['sql_site_map'];
        if (!empty($siteIdwhere)) {
            $where[] = $siteIdwhere;
        }

        if (isset($filter['keyword']) && $filter['keyword']) {
            $where[] = ['title|title_en', 'like', "%{$filter['keyword']}%"];
        }

        if (isset($filter['status']) && is_numeric($filter['status'])) {
            $where[] = ['status', '=', $filter['status']];
        }

        $listBef  = ProductRecommend::alias('t')->where($where)->order('t.sort DESC , t.create_time DESC , t.id DESC ');
        $list =  $listBef->select();
        $total = count($list);
        foreach ($list as $key => $value) {
            $siteData = $siteListIdMap[$value['site_id']] ?? [];
            $list[$key]['site_name'] = $siteData['title'] ?? '';
        }
        
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
            $this->checkItemSiteAuth($data, 1); //检查权限
            $data['model'] = 'product';
            $data['status'] = isset($data['status']) ? isset($data['status']) : 0;
            $validate_result = $this->validate($data, 'app\npd\validate\Recommend');
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            }
            $ProductRecommend = new ProductRecommend();
            if ($ProductRecommend->allowField(true)->save($data)) {
                $this->log('添加产品推荐成功', 0);
                $this->jsonReturn(0, '保存成功');
            } else {
                $this->log('添加产品推荐失败', -1);
                $this->jsonReturn(-1, '保存失败');
            }
        } else {
            return $this->addPage(null, true, false);
        }
    }



    /**
     * 编辑推荐
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        $dataModel = new ProductRecommend();
        if ($this->request->isPost()) {
            $itemRes = $this->getItemAndCheckAuthSite($dataModel, $id);
            if (!$itemRes['auth']) {
                $this->jsonReturn(-1, '没有权限');
            }
            $data          = $this->request->param();
            $data['status'] = isset($data['status']) ? isset($data['status']) : 0;
            $validate_result = $this->validate($data, 'app\npd\validate\Recommend.edit');
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            }
            if ($dataModel->allowField(true)->save($data, $id)) {
                $this->log('编辑产品推荐成功', 0);
                $this->jsonReturn(0, '保存成功');
            } else {
                $this->log('编辑产品推荐失败', -1);
                $this->jsonReturn(-1, '保存失败');
            }
        } else {
            $this->assign('id', $id);
            $dataModel = new ProductRecommend();
            return $this->editPage($dataModel, $id, null, true);
        }
    }


    /**
     * 删除推荐
     * @param $id
     */
    public function delete($id)
    {
        $dataModel = new ProductRecommend();
        return $this->checkAuthAndDelete($dataModel, $id, true, '删除产品推荐');
    }
}
