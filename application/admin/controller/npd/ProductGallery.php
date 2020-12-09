<?php

namespace app\admin\controller\npd;

use app\npd\model\Product as ProductModel;
use app\npd\model\Gallery as GalleryModel;

use app\admin\controller\npd\NpdAdminBase;
use think\Db;

/**
 * 产品图册管理
 * Class ProductGallery
 * @package app\admin\controller\npd
 */

class ProductGallery extends NpdAdminBase
{


    /**
     * 产品图册管理
     * @param int    $pid    产品id
     * @param array $filter  筛选
     * @return mixed
     */
    public function index($pid = -1, $filter = ['keyword' => ''], $pagesize = 20)
    {
        $map   = [];
        $map[] = ['t.is_delete', '=', Db::raw(0)];


        $siteListIdMap = $this->getSiteListIdMap();

        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['title|title_en', 'like', "%{$filter['keyword']}%"];
        }
        if (isset($filter['status']) && is_numeric($filter['status'])) {
            $map[] = ['status', '=', $filter['status']];
        }
        $map[] = ['model', '=', 'product'];
        if ($pid > 0) {
            $ProductModel = new ProductModel();
            $itemRes = $this->getItemAndCheckAuthSite($ProductModel, $pid);
            if (!$itemRes['auth']) {
                return '没有权限';
            }
            $map[] = ['aid', '=', $pid];
        } else {
            $siteIdwhere = $this->authNpdSite['sql_site_map'];
            if (!empty($siteIdwhere)) {
                $siteIdwhere[0] = 't.site_id';
                $map[] = $siteIdwhere;
            }
        }

        $listBef  = GalleryModel::alias('t')->where($map)->order('t.sort DESC , t.create_time DESC , t.id DESC ');
        if ($pagesize > 0 && $pid < 1) {
            $list =  $listBef->paginate($pagesize, false, ['query' => request()->param()])
            ->each(function ($item, $key) use ($siteListIdMap) {
                $siteData = $siteListIdMap[$item->site_id] ?? [];
                $item->site_name = $siteData['title'] ?? '';
            });
            $total = $list->total();
        } else {
            $list =  $listBef->select();
            $total = count($list);
        }
        
        $returnData = [
            'pid' => $pid,
            'filter' => $filter,
            'pagesize' => $pagesize,
            'lists' => $list,
            'total' => $total,
        ];
        return $this->fetch('index', $returnData);
    }


    /**
     * 添加图片
     * @return mixed
     */
    public function add()
    {
        $pid = input('post.pid') ?: input('param.pid');
        $ProductModel = new ProductModel();
        $itemRes = $this->getItemAndCheckAuthSite($ProductModel, $pid);

        if ($this->request->isPost()) {
            if (!$itemRes['auth']) {
                return $this->jsonReturn(-1, '没有权限');
            }
            $data          = $this->request->param();
            $data['model'] = 'product';
            $data['site_id'] = $itemRes['data']['site_id'] ?? 0;
            $data['status'] = isset($data['status']) ? isset($data['status']) : 0;
            $validate_result = $this->validate($data, 'app\npd\validate\Gallery');
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            }
            $GalleryModel = new GalleryModel();
            if ($GalleryModel->allowField(true)->save($data)) {
                $this->jsonReturn(0, '保存成功');
            } else {
                $this->jsonReturn(-1, '保存失败');
            }
        } else {
            if (!$itemRes['auth']) {
                return '没有权限';
            }
            $this->assign('model', 'product');
            $this->assign('aid', $pid);
            $this->assign('form_action', url('admin/npd.productGallery/add', ['pid'=>$pid]));
            return $this->fetch('npd/gallery/add');
        }
    }


    /**
     * 编辑图片
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        $itemData     = GalleryModel::find($id);
        if ($itemData) {
            $ProductModel = new ProductModel();
            $itemRes = $this->getItemAndCheckAuthSite($ProductModel, $itemData['aid']);
        }
        if ($this->request->isPost()) {
            if (!$itemRes['auth']) {
                return $this->jsonReturn(-1, '没有权限');
            }
            $data          = $this->request->param();
            $data['model'] = 'product';
            $data['site_id'] = $itemRes['data']['site_id'] ?? 0;
            $data['status'] = isset($data['status']) ? isset($data['status']) : 0;
            $validate_result = $this->validate($data, 'app\npd\validate\Gallery');
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            }
            $GalleryModel = new GalleryModel();
            if ($GalleryModel->allowField(true)->save($data, $id)) {
                $this->jsonReturn(0, '保存成功');
            } else {
                $this->jsonReturn(-1, '保存失败');
            }
        } else {
            if (!$itemRes['auth']) {
                return '没有权限';
            }
            $this->assign('form_action', url('admin/npd.productGallery/edit'));
            $this->assign('id', $id);
            return $this->fetch('npd/gallery/edit', ['data' => $itemData]);
        }
    }


    /**
     * 删除图片
     * @param $id
     */
    public function delete($id)
    {
        $data     = GalleryModel::find($id);
        if ($data) {
            $ProductModel = new ProductModel();
            $itemRes = $this->getItemAndCheckAuthSite($ProductModel, $data['aid']);
            if (!$itemRes['auth']) {
                $this->jsonReturn(-1, '没有权限');
            }
        }
        if (GalleryModel::where('id', $id)->update(['is_delete' => 1])) {
            $this->log('删除产品图片成功', 0);
            $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除产品图片失败', -1);
            $this->jsonReturn(-1, '删除失败');
        }
    }
}
